<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumentoSolicitante extends Model
{
    protected $table = 'tipos_documento_solicitante';

    protected $fillable = ['nombre', 'codigo', 'aplica_a', 'activo', 'orden'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function scopeCompatiblesCon($query, string $tipoSolicitante)
    {
        $aplicaA = $tipoSolicitante === 'empresa' ? 'juridica' : 'natural';

        return $query->where('activo', true)
            ->whereIn('aplica_a', [$aplicaA, 'ambos']);
    }
}
