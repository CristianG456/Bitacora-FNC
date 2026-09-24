<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCorreccionTarea extends Model
{
    protected $table = 'solicitudes_correccion_tarea';

    protected $fillable = [
        'tarea_id',
        'solicitante_user_id',
        'motivo',
        'estado',
        'activa',
        'revisada_por',
        'motivo_rechazo',
        'aprobada_en',
        'rechazada_en',
        'utilizada_en',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'motivo' => 'encrypted',
            'motivo_rechazo' => 'encrypted',
            'aprobada_en' => 'datetime',
            'rechazada_en' => 'datetime',
            'utilizada_en' => 'datetime',
        ];
    }

    public function tarea()
    {
        return $this->belongsTo(Tarea::class);
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    public function revisora()
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    public function version()
    {
        return $this->hasOne(TareaVersion::class, 'solicitud_correccion_id');
    }
}
