<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caso extends Model
{
    protected $table = 'casos';

    protected $fillable = [
        'tipo_documento_id',
        'subtipo_documento_id',
        'descripcion',
        'nombre_solicitante',
        'documento_solicitante',
        'enlace_google_drive',
    ];
}