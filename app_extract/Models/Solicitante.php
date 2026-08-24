<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitante extends Model
{
    protected $table = 'solicitantes';

    protected $fillable = [
        'nombre',
        'documento',
        'email',
        'telefono',
    ];

    public function casos()
    {
        return $this->hasMany(Caso::class, 'solicitante_id');
    }
}
