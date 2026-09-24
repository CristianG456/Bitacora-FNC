<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaNoHabil extends Model
{
    public const TIPO_FESTIVO = 'festivo';

    public const TIPO_INSTITUCIONAL = 'institucional';

    protected $table = 'dias_no_habiles';

    protected $fillable = [
        'fecha',
        'nombre',
        'tipo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'activo' => 'boolean',
        ];
    }
}
