<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caso extends Model
{
    use SoftDeletes;

    protected $table = 'casos';

    protected $fillable = [
        'radicado',
        'tipo_id',
        'subtipo_id',
        'descripcion',
        'observacion_inicial',
        'link_drive',
        'solicitante_id',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'created_by',
    ];

    protected $casts = [
        'fecha_inicio'        => 'date',
        'fecha_fin'           => 'date',
        'link_drive'          => 'encrypted',
        'observacion_inicial' => 'encrypted',
    ];

    // ─── Relaciones ────────────────────────────────────────────────

    public function tipo()
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_id');
    }

    public function subtipo()
    {
        return $this->belongsTo(SubtipoProceso::class, 'subtipo_id');
    }

    public function solicitante()
    {
        return $this->belongsTo(Solicitante::class, 'solicitante_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'caso_usuario')
                    ->withPivot('estado', 'fecha_asignacion', 'fecha_finalizacion', 'activo', 'motivo_salida')
                    ->withTimestamps();
    }

    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'caso_id');
    }

    public function mensajes()
    {
        return $this->hasMany(Mensaje::class, 'caso_id');
    }

    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class, 'caso_id');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Genera el radicado automático: TIPO-SUBTIPO-YYYYMMDD-XXXX
     */
    public static function generarRadicado(TipoProceso $tipo, SubtipoProceso $subtipo): string
    {
        $fecha  = now()->format('Ymd');
        $prefix = strtoupper($tipo->codigo) . '-' . strtoupper($subtipo->codigo) . '-' . $fecha . '-';

        $ultimo = self::withTrashed()
            ->where('radicado', 'like', $prefix . '%')
            ->orderByDesc('radicado')
            ->value('radicado');

        $secuencia = $ultimo
            ? (intval(substr($ultimo, -4)) + 1)
            : 1;

        return $prefix . str_pad($secuencia, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Indica si el caso puede cerrarse (no debe tener tareas pendientes).
     */
    public function puedeFinalizarse(): bool
    {
        return !$this->tareas()
            ->whereIn('estado', ['Pendiente', 'En proceso'])
            ->exists();
    }

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeRecientes($query, int $limit = 10)
    {
        return $query->with(['tipo', 'subtipo'])->latest()->limit($limit);
    }
}