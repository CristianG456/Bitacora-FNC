<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubtipoDocumento extends Model
{
    protected $table = 'subtipo_documento';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'tipo_documento_id'
    ];

    public function tipo()
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
