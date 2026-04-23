<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoProceso extends Model
{
    protected $table = 'tipos_proceso';

    protected $fillable = [
        'nombre',
        'codigo',
    ];

    public function subtipos()
    {
        return $this->hasMany(SubtipoProceso::class, 'tipo_id', 'id');
    }
}