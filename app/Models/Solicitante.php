<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitante extends Model
{
    protected $table = 'solicitantes';

    protected $fillable = [
        'nombre',
        'documento',
        'tipo_solicitante',
        'tipo_documento_solicitante_id',
        'email',
        'telefono',
    ];

    public function casos()
    {
        return $this->hasMany(Caso::class, 'solicitante_id');
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumentoSolicitante::class, 'tipo_documento_solicitante_id');
    }
}
