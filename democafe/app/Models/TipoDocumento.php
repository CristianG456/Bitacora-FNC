<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'tipo_documento';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion'
    ];

    public function subtipos()
    {
        return $this->hasMany(SubtipoDocumento::class);
    }
}

