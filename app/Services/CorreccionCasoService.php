<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\SubtipoProceso;
use App\Models\TipoDocumentoSolicitante;
use App\Models\TipoProceso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorreccionCasoService
{
    public function __construct(private readonly AnsService $ansService)
    {
    }

    public function corregir(Caso $caso, array $data): Caso
    {
        return DB::transaction(function () use ($caso, $data) {
            $caso = Caso::query()->lockForUpdate()->findOrFail($caso->id);
            $caso->load(['solicitante.tipoDocumento', 'solicitanteTipoDocumento', 'tipo', 'subtipo']);

            $tipo = TipoProceso::findOrFail($data['tipo_proceso_id']);
            $subtipo = SubtipoProceso::where('tipo_id', $tipo->id)
                ->findOrFail($data['subtipo_proceso_id']);
            $tipoDocumento = isset($data['tipo_documento_solicitante_id'])
                ? TipoDocumentoSolicitante::findOrFail($data['tipo_documento_solicitante_id'])
                : null;

            $fechaSolicitudAnterior = $caso->fecha_solicitud?->toDateString();
            $afectaAns = $caso->tipo_id !== $tipo->id
                || $fechaSolicitudAnterior !== $data['fecha_solicitud'];

            if ($afectaAns && empty($data['confirmar_recalculo_ans'])) {
                throw ValidationException::withMessages([
                    'confirmar_recalculo_ans' => 'Debes confirmar el recálculo del ANS para cambiar la fecha o el tipo de proceso.',
                ]);
            }

            $ansAnterior = [
                'dias' => $caso->ans_dias,
                'tipo_dias' => $caso->ans_tipo_dias,
                'fecha_inicio' => $caso->ans_fecha_inicio?->toDateString(),
                'fecha_limite' => $caso->ans_fecha_limite?->toDateString(),
                'estado' => $caso->ans_estado,
            ];
            $ansNuevo = $afectaAns
                ? $this->ansService->snapshot(
                    $tipo,
                    CarbonImmutable::parse($data['fecha_solicitud'], 'America/Bogota'),
                )
                : null;

            $antes = [
                'Nombre/Razón social' => $caso->solicitanteNombreActual(),
                'Tipo de solicitante' => $this->etiquetaTipoSolicitante($caso->solicitanteTipoActual()),
                'Tipo de documento' => $caso->solicitanteTipoDocumentoActual()?->codigo,
                'Documento/NIT' => $caso->solicitanteDocumentoActual(),
                'Fecha de solicitud' => $fechaSolicitudAnterior,
                'Descripción' => $caso->descripcion,
                'Observación inicial' => $caso->observacion_inicial,
                'Enlace asociado' => $caso->link_drive,
                'Tipo de proceso' => $caso->tipo?->nombre,
                'Subtipo de proceso' => $caso->subtipo?->nombre,
            ];
            $despues = [
                'Nombre/Razón social' => $data['nombre_solicitante'],
                'Tipo de solicitante' => $this->etiquetaTipoSolicitante($data['tipo_solicitante']),
                'Tipo de documento' => $tipoDocumento?->codigo,
                'Documento/NIT' => $data['documento_solicitante'] ?? null,
                'Fecha de solicitud' => $data['fecha_solicitud'],
                'Descripción' => $data['descripcion'],
                'Observación inicial' => $data['observacion_inicial'] ?? null,
                'Enlace asociado' => $data['enlace_google_drive'] ?? null,
                'Tipo de proceso' => $tipo->nombre,
                'Subtipo de proceso' => $subtipo->nombre,
            ];

            $cambios = [];
            foreach ($antes as $campo => $valorAnterior) {
                $valorNuevo = $despues[$campo];
                if ($valorAnterior !== $valorNuevo) {
                    $cambios[] = [
                        'campo' => $campo,
                        'anterior' => $valorAnterior,
                        'nuevo' => $valorNuevo,
                    ];
                }
            }

            if ($cambios === []) {
                throw ValidationException::withMessages([
                    'motivo_correccion' => 'No se detectaron cambios para registrar.',
                ]);
            }

            $actualizacion = [
                'tipo_id' => $tipo->id,
                'subtipo_id' => $subtipo->id,
                'descripcion' => $data['descripcion'],
                'observacion_inicial' => $data['observacion_inicial'] ?? null,
                'link_drive' => $data['enlace_google_drive'] ?? null,
                'fecha_solicitud' => $data['fecha_solicitud'],
                'solicitante_nombre_snapshot' => $data['nombre_solicitante'],
                'solicitante_tipo_snapshot' => $data['tipo_solicitante'],
                'solicitante_tipo_documento_id' => $tipoDocumento?->id,
                'solicitante_documento_snapshot' => $data['documento_solicitante'] ?? null,
            ];

            if ($ansNuevo !== null) {
                $actualizacion = [...$actualizacion, ...$ansNuevo];
            }

            $caso->update($actualizacion);

            Bitacora::registrar(
                modulo: 'Casos',
                accion: 'Corregir',
                descripcion: auth()->user()->name." corrigió información del caso {$caso->radicado}.",
                casoId: $caso->id,
                entidadId: $caso->id,
                metadata: [
                    'motivo' => $data['motivo_correccion'],
                    'cambios' => $cambios,
                    'ans_anterior' => $afectaAns ? $ansAnterior : null,
                    'ans_corregido' => $afectaAns ? [
                        'dias' => $ansNuevo['ans_dias'],
                        'tipo_dias' => $ansNuevo['ans_tipo_dias'],
                        'fecha_inicio' => $ansNuevo['ans_fecha_inicio'],
                        'fecha_limite' => $ansNuevo['ans_fecha_limite'],
                        'estado' => $ansNuevo['ans_estado'],
                    ] : null,
                ],
            );

            return $caso->fresh();
        });
    }

    private function etiquetaTipoSolicitante(?string $tipo): ?string
    {
        return match ($tipo) {
            'persona' => 'Persona natural',
            'empresa' => 'Persona jurídica',
            default => $tipo,
        };
    }
}
