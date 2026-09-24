<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Notificacion;
use App\Models\TipoProceso;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AnsService
{
    public function __construct(
        private readonly CalendarioLaboralService $calendarioLaboral,
    ) {
    }

    public function snapshot(TipoProceso $tipo, ?CarbonInterface $inicio = null): array
    {
        if (!$tipo->ans_dias) {
            return [
                'ans_fecha_inicio' => null,
                'ans_dias' => null,
                'ans_tipo_dias' => null,
                'ans_fecha_limite' => null,
                'ans_estado' => null,
            ];
        }

        $fechaInicio = CarbonImmutable::instance($inicio ?? now('America/Bogota'))
            ->setTimezone('America/Bogota')
            ->startOfDay();
        $tipoDias = $tipo->ans_tipo_dias ?: 'calendario';
        $fechaLimite = $tipoDias === 'habiles'
            ? $this->calendarioLaboral->sumarDiasHabiles($fechaInicio, (int) $tipo->ans_dias)
            : $fechaInicio->addDays((int) $tipo->ans_dias);

        return [
            'ans_fecha_inicio' => $fechaInicio->toDateString(),
            'ans_dias' => (int) $tipo->ans_dias,
            'ans_tipo_dias' => $tipoDias,
            'ans_fecha_limite' => $fechaLimite->toDateString(),
            'ans_estado' => 'vigente',
        ];
    }

    public function procesarAlertas(?CarbonInterface $fecha = null): int
    {
        $hoy = CarbonImmutable::instance($fecha ?? now('America/Bogota'))->startOfDay();
        $eventos = 0;

        Caso::query()
            ->whereNotNull('ans_fecha_limite')
            ->whereNotIn('estado', ['Finalizado', 'Cancelado'])
            ->orderBy('id')
            ->chunkById(100, function ($casos) use ($hoy, &$eventos) {
                foreach ($casos as $caso) {
                    $eventos += $this->procesarCaso($caso->id, $hoy);
                }
            });

        return $eventos;
    }

    public function diasRestantes(Caso $caso, ?CarbonInterface $fecha = null): ?int
    {
        if (!$caso->ans_fecha_limite) {
            return null;
        }

        $hoy = CarbonImmutable::parse(
            ($fecha ?? now('America/Bogota'))->toDateString(),
            'America/Bogota',
        );
        $limite = CarbonImmutable::parse(
            $caso->ans_fecha_limite->toDateString(),
            'America/Bogota',
        );

        return $caso->ans_tipo_dias === 'habiles'
            ? $this->calendarioLaboral->diferenciaDiasHabiles($hoy, $limite)
            : (int) $hoy->diffInDays($limite, false);
    }

    public function cerrarSeguimiento(Caso $caso, ?CarbonInterface $fecha = null): void
    {
        if (!$caso->ans_fecha_limite) {
            return;
        }

        $diasRestantes = $this->diasRestantes($caso, $fecha);
        $estado = $diasRestantes >= 0
            ? 'cumplido'
            : 'incumplido';

        $caso->update(['ans_estado' => $estado]);

        Bitacora::registrar(
            modulo: 'ANS',
            accion: 'Cerrar seguimiento',
            descripcion: "El seguimiento ANS del caso {$caso->radicado} finalizó como {$estado}.",
            casoId: $caso->id,
            entidadId: $caso->id,
            metadata: [
                'estado_ans' => $estado,
                'fecha_limite' => $caso->ans_fecha_limite->toDateString(),
            ],
        );
    }

    private function procesarCaso(int $casoId, CarbonImmutable $hoy): int
    {
        return DB::transaction(function () use ($casoId, $hoy) {
            $caso = Caso::query()->lockForUpdate()->find($casoId);

            if (!$caso || !$caso->ans_fecha_limite
                || in_array($caso->estado, ['Finalizado', 'Cancelado'], true)) {
                return 0;
            }

            $restantes = $this->diasRestantes($caso, $hoy);
            $eventos = 0;

            if ($restantes > 1 && $restantes <= 5) {
                $eventos += $this->registrarAlerta(
                    $caso,
                    'Alerta preventiva',
                    'Alerta preventiva ANS',
                    "El caso {$caso->radicado} tiene {$restantes} días restantes para cumplir el ANS.",
                    $restantes,
                );
                $caso->ans_estado = 'preventivo';
            }

            if ($restantes >= 0 && $restantes <= 1) {
                $mensaje = $restantes === 1
                    ? "El caso {$caso->radicado} está próximo a vencer su tiempo máximo de respuesta. Falta 1 día."
                    : "El caso {$caso->radicado} alcanza hoy su fecha límite de ANS.";
                $eventos += $this->registrarAlerta(
                    $caso,
                    'Alerta crítica',
                    'Alerta crítica ANS',
                    $mensaje,
                    $restantes,
                );
                $caso->ans_estado = 'critico';
            }

            if ($restantes <= 0) {
                $eventos += $this->registrarEventoBitacora(
                    $caso,
                    'Fecha límite alcanzada',
                    "El caso {$caso->radicado} alcanzó la fecha límite de su ANS.",
                    $restantes,
                );
            }

            if ($restantes < 0) {
                $retraso = abs($restantes);
                $eventos += $this->registrarAlerta(
                    $caso,
                    'Incumplimiento',
                    'ANS incumplido',
                    "El caso {$caso->radicado} superó el ANS establecido. Retraso actual: {$retraso} días.",
                    $restantes,
                );
                $caso->ans_estado = 'vencido';
            }

            if ($caso->isDirty('ans_estado')) {
                $caso->save();
            }

            return $eventos;
        });
    }

    private function registrarAlerta(
        Caso $caso,
        string $accion,
        string $titulo,
        string $mensaje,
        int $diasRestantes,
    ): int {
        if ($this->eventoRegistrado($caso, $accion)) {
            return 0;
        }

        $destinatarios = $caso->usuarios()
            ->wherePivot('activo', true)
            ->pluck('users.id')
            ->push($caso->created_by)
            ->filter()
            ->unique();

        foreach ($destinatarios as $userId) {
            Notificacion::enviar(
                (int) $userId,
                $titulo,
                $mensaje,
                'ans',
                $caso->id,
            );
        }

        Bitacora::registrar(
            modulo: 'ANS',
            accion: $accion,
            descripcion: $mensaje,
            casoId: $caso->id,
            entidadId: $caso->id,
            metadata: [
                'dias_restantes' => $diasRestantes,
                'fecha_limite' => $caso->ans_fecha_limite->toDateString(),
            ],
        );

        return 1;
    }

    private function registrarEventoBitacora(
        Caso $caso,
        string $accion,
        string $descripcion,
        int $diasRestantes,
    ): int {
        if ($this->eventoRegistrado($caso, $accion)) {
            return 0;
        }

        Bitacora::registrar(
            modulo: 'ANS',
            accion: $accion,
            descripcion: $descripcion,
            casoId: $caso->id,
            entidadId: $caso->id,
            metadata: [
                'dias_restantes' => $diasRestantes,
                'fecha_limite' => $caso->ans_fecha_limite->toDateString(),
            ],
        );

        return 1;
    }

    private function eventoRegistrado(Caso $caso, string $accion): bool
    {
        return $caso->bitacoras()
            ->where('modulo', 'ANS')
            ->where('accion', $accion)
            ->exists();
    }
}
