<?php

namespace App\Support;

use App\Models\Bitacora;
use App\Models\Tarea;

class TaskCorrectionAuditPresenter
{
    private const ACTIONS = [
        'solicitar corrección',
        'aprobar corrección',
        'rechazar corrección',
        'corregir tarea',
    ];

    public static function make(Bitacora $evento): ?array
    {
        $accion = mb_strtolower(trim($evento->accion));

        if (!in_array($accion, self::ACTIONS, true)) {
            return null;
        }

        $metadata = is_array($evento->metadata) ? $evento->metadata : [];
        $eventoSolicitud = in_array($accion, ['aprobar corrección', 'rechazar corrección'], true)
            ? self::previousEvent($evento, ['Solicitar corrección'])
            : null;
        $metadataSolicitud = is_array($eventoSolicitud?->metadata) ? $eventoSolicitud->metadata : [];
        $eventoCompletar = $accion === 'solicitar corrección'
            ? self::previousEvent($evento, ['Completar'])
            : null;
        $metadataCompletar = is_array($eventoCompletar?->metadata) ? $eventoCompletar->metadata : [];
        $anterior = self::arrayValue($metadata['anterior'] ?? null);
        $nuevo = self::arrayValue($metadata['nuevo'] ?? null);
        $campos = $accion === 'corregir tarea'
            ? self::arrayValue($metadata['campos'] ?? null)
            : [];
        $cambios = [];

        foreach ($campos as $campo) {
            if (!is_string($campo)) {
                continue;
            }

            $cambios[] = [
                'campo' => self::fieldLabel($campo),
                'anterior' => $anterior[$campo] ?? null,
                'nuevo' => $nuevo[$campo] ?? null,
            ];
        }

        return [
            'accion' => $accion,
            'tarea' => $metadata['tarea']
                ?? ($evento->entidad_id ? Tarea::query()->whereKey($evento->entidad_id)->value('descripcion') : null),
            'solicitante' => $metadata['solicitante']
                ?? ($accion === 'solicitar corrección'
                    ? $evento->usuario?->name
                    : ($metadataSolicitud['solicitante'] ?? $eventoSolicitud?->usuario?->name)),
            'motivo' => $metadata['motivo']
                ?? (in_array($accion, ['aprobar corrección', 'rechazar corrección'], true)
                    ? ($metadataSolicitud['motivo'] ?? null)
                    : null),
            'autorizador' => match ($accion) {
                'aprobar corrección' => $metadata['autorizada_por'] ?? $evento->usuario?->name,
                'corregir tarea' => $metadata['autorizada_por'] ?? null,
                'rechazar corrección' => $metadata['rechazada_por'] ?? $evento->usuario?->name,
                default => null,
            },
            'motivo_rechazo' => $accion === 'rechazar corrección'
                ? ($metadata['motivo_rechazo'] ?? null)
                : null,
            'observacion_original' => $accion === 'solicitar corrección'
                ? ($metadata['observacion_original'] ?? $metadataCompletar['observacion'] ?? null)
                : null,
            'cambios' => $cambios,
            'resultado' => match ($accion) {
                'aprobar corrección' => 'Corrección autorizada para un solo uso.',
                'rechazar corrección' => 'Corrección no autorizada.',
                default => null,
            },
        ];
    }

    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function previousEvent(Bitacora $evento, array $actions): ?Bitacora
    {
        return Bitacora::query()
            ->with('usuario')
            ->where('caso_id', $evento->caso_id)
            ->where('entidad_id', $evento->entidad_id)
            ->whereIn('accion', $actions)
            ->where(function ($query) use ($evento) {
                $query->where('created_at', '<', $evento->created_at)
                    ->orWhere(function ($sameMoment) use ($evento) {
                        $sameMoment->where('created_at', $evento->created_at)
                            ->where('id', '<', $evento->id);
                    });
            })
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'observacion' => 'Observación',
            'fecha_fin' => 'Fecha registrada',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }
}
