<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TareaVersion extends Model
{
    public $timestamps = false;

    protected $table = 'tarea_versiones';

    protected $fillable = [
        'tarea_id',
        'version',
        'datos_anteriores',
        'datos_nuevos',
        'campos_modificados',
        'corregida_por',
        'solicitud_correccion_id',
        'motivo',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'datos_anteriores' => 'encrypted:array',
            'datos_nuevos' => 'encrypted:array',
            'campos_modificados' => 'encrypted:array',
            'motivo' => 'encrypted',
            'created_at' => 'datetime',
        ];
    }

    public function tarea()
    {
        return $this->belongsTo(Tarea::class);
    }

    public function solicitud()
    {
        return $this->belongsTo(SolicitudCorreccionTarea::class, 'solicitud_correccion_id');
    }

    public function correctora()
    {
        return $this->belongsTo(User::class, 'corregida_por');
    }
}
