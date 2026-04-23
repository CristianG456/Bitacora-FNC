<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubtipoProceso extends Model
{
    protected $table = 'subtipos_proceso';

    protected $fillable = ['tipo_id', 'nombre', 'codigo'];

    public function tipo()
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_id', 'id');
    }
}
