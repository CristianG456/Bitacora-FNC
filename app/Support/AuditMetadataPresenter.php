<?php

namespace App\Support;

use App\Models\Bitacora;
use Carbon\CarbonImmutable;

class AuditMetadataPresenter
{
    private const EVENT_LABELS = [
        'case_created' => 'Creación del caso',
        'responsible_assigned' => 'Asignación de responsable',
        'responsible_reassigned' => 'Reasignación de responsable',
        'task_assigned' => 'Asignación de tarea',
        'task_completed' => 'Tarea completada',
        'case_finalized' => 'Finalización del caso',
        'consultant_note' => 'Anotación de seguimiento',
    ];

    public static function make(Bitacora $evento): array
    {
        $meta = is_array($evento->metadata) ? $evento->metadata : [];
        $tipo = $meta['event_type'] ?? null;
        $correccion = TaskCorrectionAuditPresenter::make($evento);
        $actor = self::persona($meta['actor'] ?? null, $evento->usuario?->name, $evento->usuario?->role?->nombre);
        $responsable = self::persona($meta['responsable'] ?? null);
        $ans = self::ans($meta['ans'] ?? null);

        return [
            'titulo' => self::EVENT_LABELS[$tipo] ?? ($correccion['accion'] ?? $evento->accion ?: 'Evento de auditoría'),
            'actor' => $actor,
            'actor_label' => match ($tipo) {
                'task_assigned' => 'Asignada por',
                'task_completed' => 'Completada por',
                'case_created' => 'Creado por',
                'case_finalized' => 'Finalizado por',
                default => 'Realizado por',
            },
            'responsable' => $responsable,
            'responsable_anterior' => self::persona($meta['responsable_anterior'] ?? null),
            'responsable_nuevo' => self::persona($meta['responsable_nuevo'] ?? null),
            'tarea' => self::texto(data_get($meta, 'tarea.descripcion')),
            'tipo_tarea' => self::texto(data_get($meta, 'tarea.tipo')),
            'estado_tarea' => self::texto(data_get($meta, 'tarea.estado')),
            'caso' => self::texto(data_get($meta, 'caso.radicado')),
            'fecha_hora' => self::fecha($meta['fecha_hora'] ?? null),
            'fecha_asignacion' => self::fecha($meta['fecha_asignacion'] ?? null),
            'fecha_finalizacion' => self::fecha($meta['fecha_finalizacion'] ?? null),
            'tiempo_atencion' => self::texto($meta['tiempo_atencion'] ?? null),
            'dias_habiles_transcurridos' => $meta['dias_habiles_transcurridos'] ?? null,
            'resultado' => self::texto($meta['resultado'] ?? null),
            'observacion' => self::texto($meta['observacion'] ?? $meta['anotacion'] ?? null),
            'ans' => $ans,
            'correccion' => $correccion,
            'motivo' => self::texto($meta['motivo'] ?? $correccion['motivo'] ?? null),
            'autorizador' => self::texto($correccion['autorizador'] ?? null),
            'motivo_rechazo' => self::texto($correccion['motivo_rechazo'] ?? null),
            'cambios' => $correccion['cambios'] ?? [],
        ];
    }

    private static function persona(mixed $valor, ?string $nombre = null, ?string $rol = null): ?string
    {
        if (is_array($valor)) {
            $nombre = $valor['nombre'] ?? $nombre;
            $rol = $valor['rol'] ?? $rol;
        }

        if (blank($nombre)) {
            return null;
        }

        return trim($nombre.($rol ? ' · '.$rol : ''));
    }

    private static function ans(mixed $valor): ?array
    {
        if (!is_array($valor) || empty($valor)) {
            return null;
        }

        $estados = [
            'vigente' => 'Vigente',
            'preventivo' => 'Preventivo',
            'critico' => 'Crítico',
            'vencido' => 'Vencido',
            'cumplido' => 'Cumplido',
            'incumplido' => 'Incumplido',
        ];

        return [
            'estado' => $estados[strtolower((string) ($valor['estado'] ?? ''))] ?? self::texto($valor['estado'] ?? null),
            'configurado' => isset($valor['dias_configurados']) ? $valor['dias_configurados'].' días '.self::tipoDias($valor['tipo_dias'] ?? null) : null,
            'inicio' => self::fechaCorta($valor['fecha_inicio'] ?? null),
            'limite' => self::fechaCorta($valor['fecha_limite'] ?? null),
            'restante' => isset($valor['dias_restantes']) ? $valor['dias_restantes'].' '.(($valor['dias_restantes'] ?? 0) === 1 ? 'día' : 'días').' '.self::tipoDias($valor['tipo_dias'] ?? null) : null,
        ];
    }

    private static function tipoDias(mixed $tipo): string
    {
        return match (strtolower((string) $tipo)) {
            'habil', 'habiles' => 'hábiles',
            'calendario' => 'calendario',
            default => trim((string) $tipo),
        };
    }

    private static function fecha(mixed $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        try {
            return LocalDate::inBogota(CarbonImmutable::parse($valor))?->locale('es')->translatedFormat('d M Y · g:i a');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function fechaCorta(mixed $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        try {
            return LocalDate::inBogota(CarbonImmutable::parse($valor))?->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function texto(mixed $valor): ?string
    {
        return blank($valor) || is_array($valor) ? null : (string) $valor;
    }
}
