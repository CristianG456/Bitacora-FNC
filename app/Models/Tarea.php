<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tarea extends Model
{
    use SoftDeletes;

    protected $table = 'tareas';

    protected $fillable = [
        'caso_id',
        'user_id',
        'descripcion',
        'estado',
        'orden',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin'    => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────────────

    public function caso()
    {
        return $this->belongsTo(Caso::class, 'caso_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function observaciones()
    {
        return $this->hasMany(Observacion::class, 'tarea_id');
    }

    // ─── Helpers ───────────────────────────────────────────────────

    public function estaPendiente(): bool
    {
        return $this->estado === 'Pendiente';
    }

    public function estaEnProceso(): bool
    {
        return $this->estado === 'En proceso';
    }

    public function estaCompletada(): bool
    {
        return $this->estado === 'Completada';
    }

    /**
     * Badge de color según estado para la UI.
     */
    public function badgeEstado(): string
    {
        return match ($this->estado) {
            'Pendiente'  => 'badge-pendiente',
            'En proceso' => 'badge-proceso',
            'Completada' => 'badge-completada',
            default      => 'badge-default',
        };
    }
}
