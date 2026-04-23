<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observacion extends Model
{
    protected $table = 'observaciones';

    // Esta tabla sólo tiene created_at (ver migración)
    public $timestamps = false;

    protected $fillable = [
        'tarea_id',
        'user_id',
        'contenido',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────────────

    public function tarea()
    {
        return $this->belongsTo(Tarea::class, 'tarea_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
