<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tipo',
        'titulo',
        'mensaje',
        'leido',
        'created_at',
    ];

    protected $casts = [
        'leido'      => 'boolean',
        'created_at' => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────────────

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── Helper: crear notificación desde cualquier parte ──────────

    public static function enviar(
        int    $userId,
        string $titulo,
        string $mensaje,
        string $tipo = 'info'
    ): self {
        return self::create([
            'user_id'    => $userId,
            'tipo'       => $tipo,
            'titulo'     => $titulo,
            'mensaje'    => $mensaje,
            'leido'      => false,
            'created_at' => now(),
        ]);
    }
}
