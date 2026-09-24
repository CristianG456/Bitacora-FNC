<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoProceso extends Model
{
    protected $table = 'tipos_proceso';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'activo',
        'ans_dias',
        'ans_tipo_dias',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'ans_dias' => 'integer',
    ];

    public function subtipos()
    {
        return $this->hasMany(SubtipoProceso::class, 'tipo_id', 'id');
    }
}
