<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Tarea;
use App\Models\User;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class CaseAuditService
{
    private const VERSION = 1;

    public function __construct(
        private readonly AnsService $ans,
        private readonly CalendarioLaboralService $calendario,
    ) {
    }

    /**
     * Registra el snapshot inicial y la cronología individual verificable.
     */
    public function registrarCreacion(Caso $caso): void
    {
        $caso->loadMissing([
            'tipo', 'subtipo', 'solicitanteTipoDocumento', 'creador.role',
            'usuarios.role', 'tareas.usuario.role',
        ]);

        $actor = $caso->creador ?: Auth::user();
        $momento = $caso->created_at ?: now();
        $responsables = $caso->usuarios
            ->map(function (User $responsable) use ($caso, $actor, $momento) {
                $asignada = $this->fecha($responsable->pivot?->fecha_asignacion) ?? $momento;
                $tareas = $caso->tareas->where('user_id', $responsable->id)
                    ->map(fn (Tarea $tarea) => $this->snapshotTareaAsignada($caso, $tarea, $actor, $tarea->created_at ?: $asignada))
                    ->values()
                    ->all();

                return [
                    ...$this->persona($responsable),
                    'activo' => (bool) ($responsable->pivot?->activo ?? true),
                    'estado' => $responsable->pivot?->estado,
                    'fecha_asignacion' => $this->fechaTexto($asignada),
                    'tareas' => $tareas,
                ];
            })
            ->values()
            ->all();

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Crear',
            descripcion: "{$actor?->name} creó el caso {$caso->radicado} con su configuración inicial.",
            casoId: $caso->id,
            entidadId: $caso->id,
            metadata: [
                'audit_version' => self::VERSION,
                'event_type' => 'case_created',
                'actor' => $this->persona($actor),
                'fecha_hora' => $this->fechaTexto($momento),
                'caso' => [
                    'radicado' => $caso->radicado,
                    'fecha_solicitud' => $caso->fecha_solicitud?->toDateString(),
                    'tipo' => $caso->tipo?->nombre,
                    'subtipo' => $caso->subtipo?->nombre,
                    'estado_inicial' => $caso->estado,
                ],
                'solicitante' => [
                    'nombre' => $caso->solicitanteNombreActual(),
                    'tipo' => $caso->solicitanteTipoActual(),
                    'tipo_documento' => $caso->solicitanteTipoDocumentoActual()?->codigo
                        ?? $caso->solicitanteTipoDocumentoActual()?->nombre,
                    'documento' => $caso->solicitanteDocumentoActual(),
                ],
                'ans' => $this->snapshotAns($caso, $momento),
                'responsables' => $responsables,
            ],
        );

        foreach ($caso->usuarios as $responsable) {
            $this->registrarResponsable($caso, $responsable, 'Asignación de responsable', null, $actor);
            foreach ($caso->tareas->where('user_id', $responsable->id) as $tarea) {
                $this->registrarTareaAsignada($caso, $tarea, $actor, 'Asignación de tarea');
            }
        }
    }

    public function registrarResponsable(
        Caso $caso,
        User $responsable,
        string $accion = 'Asignacion',
        ?string $motivo = null,
        ?User $actor = null,
        bool $reactivacion = false,
    ): void {
        $actor ??= Auth::user();
        $actor?->loadMissing('role');
        $responsable->loadMissing('role');
        $momento = $this->fecha($responsable->pivot?->fecha_asignacion) ?? now();
        $autoasignacion = $actor && $actor->id === $responsable->id;
        $descripcion = $autoasignacion
            ? "{$actor->name} se autoasignó como responsable del caso {$caso->radicado}."
            : ($reactivacion
                ? "{$actor?->name} reactivó a {$responsable->name} como responsable del caso {$caso->radicado}."
                : "{$actor?->name} asignó a {$responsable->name} como responsable del caso {$caso->radicado}.");

        Bitacora::registrar(
            modulo: 'Casos',
            accion: $accion,
            descripcion: $descripcion,
            casoId: $caso->id,
            entidadId: $responsable->id,
            usuarioAfectado: $responsable->id,
            metadata: [
                'audit_version' => self::VERSION,
                'event_type' => 'responsible_assigned',
                'actor' => $this->persona($actor),
                'responsable' => $this->persona($responsable),
                'autoasignacion' => $autoasignacion,
                'reactivacion' => $reactivacion,
                'activo' => true,
                'motivo' => $motivo,
                'fecha_hora' => $this->fechaTexto($momento),
                'caso' => ['radicado' => $caso->radicado],
                'ans' => $this->snapshotAns($caso, $momento),
            ],
        );
    }

    public function registrarTareaAsignada(
        Caso $caso,
        Tarea $tarea,
        ?User $actor = null,
        string $accion = 'Crear',
    ): void
    {
        $actor ??= Auth::user();
        $tarea->loadMissing('usuario.role');
        $momento = $tarea->created_at ?: now();
        $responsable = $tarea->usuario;

        Bitacora::registrar(
            modulo: 'Tareas',
            accion: $accion,
            descripcion: "{$actor?->name} asignó la tarea '{$tarea->descripcion}' a {$responsable?->name}.",
            casoId: $caso->id,
            entidadId: $tarea->id,
            usuarioAfectado: $tarea->user_id,
            metadata: [
                'audit_version' => self::VERSION,
                'event_type' => 'task_assigned',
                ...$this->snapshotTareaAsignada($caso, $tarea, $actor, $momento),
            ],
        );
    }

    public function registrarTareaCompletada(
        Caso $caso,
        Tarea $tarea,
        string $observacion,
        ?User $actor = null,
    ): void {
        $actor ??= Auth::user();
        $tarea->loadMissing('usuario.role');
        $asignada = $tarea->created_at ?: $tarea->fecha_inicio ?: now();
        $completada = $tarea->fecha_fin ?: now();
        $restantes = $this->ans->diasRestantes($caso, $completada);
        $resultado = $restantes === null ? null : ($restantes >= 0 ? 'A TIEMPO' : 'FUERA DE ANS');

        Bitacora::registrar(
            modulo: 'Tareas',
            accion: 'Completar',
            descripcion: "{$actor?->name} completó la tarea '{$tarea->descripcion}'.",
            casoId: $caso->id,
            entidadId: $tarea->id,
            usuarioAfectado: $tarea->user_id,
            metadata: [
                'audit_version' => self::VERSION,
                'event_type' => 'task_completed',
                'actor' => $this->persona($actor),
                'responsable' => $this->persona($tarea->usuario),
                'tarea' => [
                    'id' => $tarea->id,
                    'descripcion' => $tarea->descripcion,
                    'tipo' => $this->tipoTarea($tarea->tipo_accion),
                ],
                'caso' => ['radicado' => $caso->radicado],
                'fecha_asignacion' => $this->fechaTexto($asignada),
                'fecha_finalizacion' => $this->fechaTexto($completada),
                'tiempo_atencion' => $this->duracion($asignada, $completada),
                'dias_habiles_transcurridos' => max(0, $this->calendario->diferenciaDiasHabiles($asignada, $completada)),
                'ans' => $this->snapshotAns($caso, $completada),
                'ans_restante' => $restantes !== null && $restantes >= 0 ? $restantes : null,
                'retraso_dias' => $restantes !== null && $restantes < 0 ? abs($restantes) : null,
                'resultado' => $resultado,
                'observacion' => $observacion,
            ],
        );
    }

    public function registrarReasignacion(Caso $caso, User $anterior, User $nuevo, ?string $motivo = null): void
    {
        $actor = Auth::user();
        $anterior->loadMissing('role');
        $nuevo->loadMissing('role');

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Reemplazar',
            descripcion: "{$actor?->name} reemplazó a {$anterior->name} por {$nuevo->name} y transfirió sus tareas.",
            casoId: $caso->id,
            entidadId: $nuevo->id,
            usuarioAfectado: $anterior->id,
            metadata: [
                'audit_version' => self::VERSION,
                'event_type' => 'responsible_reassigned',
                'actor' => $this->persona($actor),
                'responsable_anterior' => $this->persona($anterior),
                'responsable_nuevo' => $this->persona($nuevo),
                'fecha_hora' => $this->fechaTexto(now()),
                'motivo' => $motivo,
                'caso' => ['radicado' => $caso->radicado],
                'ans' => $this->snapshotAns($caso, now()),
            ],
        );
    }

    public function registrarFinalizacion(Caso $caso, ?User $actor = null): void
    {
        $actor ??= Auth::user();
        $finalizada = now();
        $inicio = $caso->created_at ?: $caso->ans_fecha_inicio ?: $finalizada;
        $restantes = $this->ans->diasRestantes($caso, $finalizada);
        $ultimaTarea = Tarea::withTrashed()
            ->where('caso_id', $caso->id)
            ->whereNotNull('fecha_fin')
            ->latest('fecha_fin')
            ->first();

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Cambio de Estado',
            descripcion: "El caso fue marcado como Finalizado por {$actor?->name}.",
            casoId: $caso->id,
            entidadId: $caso->id,
            metadata: [
                'audit_version' => self::VERSION,
                'event_type' => 'case_finalized',
                'actor' => $this->persona($actor),
                'caso' => ['radicado' => $caso->radicado],
                'fecha_solicitud' => $caso->fecha_solicitud?->toDateString(),
                'fecha_finalizacion' => $this->fechaTexto($finalizada),
                'tiempo_total' => $this->duracion($inicio, $finalizada),
                'dias_habiles_totales' => max(0, $this->calendario->diferenciaDiasHabiles($inicio, $finalizada)),
                'responsables_relacionados' => $caso->usuarios()->count(),
                'tareas_total' => Tarea::withTrashed()->where('caso_id', $caso->id)->count(),
                'ultima_tarea_completada' => $ultimaTarea?->fecha_fin ? $this->fechaTexto($ultimaTarea->fecha_fin) : null,
                'ans' => $this->snapshotAns($caso, $finalizada),
                'resultado' => $restantes === null ? null : ($restantes >= 0 ? 'CUMPLIDO' : 'INCUMPLIDO'),
                'ans_restante' => $restantes !== null && $restantes >= 0 ? $restantes : null,
                'retraso_dias' => $restantes !== null && $restantes < 0 ? abs($restantes) : null,
            ],
        );
    }

    /** @return array<string, mixed> */
    public function resumen(Caso $caso): array
    {
        $tareas = Tarea::withTrashed()->with('usuario.role')->where('caso_id', $caso->id)->get();
        $responsables = $caso->usuarios()->with('role')->get();
        $porResponsable = $responsables->map(function (User $responsable) use ($caso, $tareas) {
            $propias = $tareas->where('user_id', $responsable->id);
            $completadas = $propias->where('estado', 'Completada');
            $aTiempo = $completadas->filter(fn (Tarea $tarea) => $this->resultadoTarea($caso, $tarea) === 'A TIEMPO')->count();
            $fuera = $completadas->filter(fn (Tarea $tarea) => $this->resultadoTarea($caso, $tarea) === 'FUERA DE ANS')->count();
            return [
                ...$this->persona($responsable),
                'activo' => (bool) ($responsable->pivot?->activo ?? false),
                'asignadas' => $propias->count(),
                'completadas' => $completadas->count(),
                'pendientes' => $propias->where('estado', '!=', 'Completada')->count(),
                'a_tiempo' => $aTiempo,
                'fuera_ans' => $fuera,
                // No se presenta como tiempo de atención: la diferencia calendario
                // no demuestra dedicación efectiva y podría inducir a error.
                'tiempo_promedio' => null,
            ];
        })->values()->all();

        $completadas = $tareas->where('estado', 'Completada');

        return [
            'ans_estado' => $this->ans->estadoEnFecha($caso) ?? $caso->ans_estado,
            'fecha_limite' => $caso->ans_fecha_limite?->toDateString(),
            'responsables' => $responsables->count(),
            'tareas_total' => $tareas->count(),
            'completadas' => $completadas->count(),
            'pendientes' => $tareas->where('estado', '!=', 'Completada')->count(),
            'a_tiempo' => $completadas->filter(fn (Tarea $tarea) => $this->resultadoTarea($caso, $tarea) === 'A TIEMPO')->count(),
            'fuera_ans' => $completadas->filter(fn (Tarea $tarea) => $this->resultadoTarea($caso, $tarea) === 'FUERA DE ANS')->count(),
            'por_responsable' => $porResponsable,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotTareaAsignada(Caso $caso, Tarea $tarea, ?User $actor, CarbonInterface $momento): array
    {
        $tarea->loadMissing('usuario.role');

        return [
            'actor' => $this->persona($actor),
            'responsable' => $this->persona($tarea->usuario),
            'tarea' => [
                'id' => $tarea->id,
                'descripcion' => $tarea->descripcion,
                'tipo' => $this->tipoTarea($tarea->tipo_accion),
                'estado' => $tarea->estado,
            ],
            'fecha_asignacion' => $this->fechaTexto($momento),
            'caso' => ['radicado' => $caso->radicado],
            'ans' => $this->snapshotAns($caso, $momento),
        ];
    }

    /** @return array<string, mixed>|null */
    private function snapshotAns(Caso $caso, CarbonInterface $momento): ?array
    {
        if (!$caso->ans_fecha_limite) {
            return null;
        }

        $restantes = $this->ans->diasRestantes($caso, $momento);

        return [
            'dias_configurados' => $caso->ans_dias,
            'tipo_dias' => $caso->ans_tipo_dias,
            'fecha_inicio' => $caso->ans_fecha_inicio?->toDateString(),
            'fecha_limite' => $caso->ans_fecha_limite?->toDateString(),
            'estado' => $this->ans->estadoEnFecha($caso, $momento),
            'dias_restantes' => $restantes,
        ];
    }

    /** @return array{id:int|null,nombre:string,rol:string} */
    private function persona(?User $user): array
    {
        $user?->loadMissing('role');

        return [
            'id' => $user?->id,
            'nombre' => $user?->name ?? 'Sistema',
            'rol' => $user?->role?->nombre ?? 'Sistema',
        ];
    }

    private function resultadoTarea(Caso $caso, Tarea $tarea): ?string
    {
        if (!$tarea->fecha_fin || !$caso->ans_fecha_limite) {
            return null;
        }

        return $this->ans->diasRestantes($caso, $tarea->fecha_fin) >= 0 ? 'A TIEMPO' : 'FUERA DE ANS';
    }

    private function duracion(CarbonInterface $desde, CarbonInterface $hasta): string
    {
        $diasHabiles = max(0, $this->calendario->diferenciaDiasHabiles($desde, $hasta));
        if ($diasHabiles > 0) {
            return $diasHabiles.' '.($diasHabiles === 1 ? 'día hábil' : 'días hábiles');
        }

        return $this->duracionMinutos(max(0, $desde->diffInMinutes($hasta)));
    }

    private function duracionMinutos(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos.' '.($minutos === 1 ? 'minuto' : 'minutos');
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $horas.' '.($horas === 1 ? 'hora' : 'horas').($resto ? " {$resto} minutos" : '');
    }

    private function tipoTarea(?string $tipo): string
    {
        return strtolower((string) $tipo) === 'firma' ? 'Firma' : 'Normal';
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if (!$valor) {
            return null;
        }

        return CarbonImmutable::parse($valor)->setTimezone('America/Bogota');
    }

    private function fechaTexto(CarbonInterface $fecha): string
    {
        return LocalDate::inBogota($fecha)?->toIso8601String();
    }
}
