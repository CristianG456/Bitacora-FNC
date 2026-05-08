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
        'descripcion'  => 'encrypted',
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

    public function observacion()
    {
        return $this->hasOne(Observacion::class, 'tarea_id');
    }
}
