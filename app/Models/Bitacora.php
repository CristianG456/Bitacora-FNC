<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    protected $table = 'bitacoras';

    // Sólo tiene created_at (inmutable, nunca actualizar)
    public $timestamps = false;

    protected $fillable = [
        'caso_id',
        'user_id',
        'modulo',
        'accion',
        'entidad_id',
        'usuario_afectado',
        'descripcion',
        'metadata',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'descripcion' => 'encrypted',
        'metadata'    => 'encrypted:array',
        'created_at'  => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────────────

    public function caso()
    {
        return $this->belongsTo(Caso::class, 'caso_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuarioAfectado()
    {
        return $this->belongsTo(User::class, 'usuario_afectado');
    }

    // ─── Helper: registro desde cualquier parte ─────────────────────

    /**
     * Registra un evento en la bitácora.
     *
     * @param string      $modulo           Ej: 'Tareas', 'Casos'
     * @param string      $accion           Ej: 'Crear', 'Actualizar', 'Eliminar'
     * @param string      $descripcion      Texto descriptivo del evento
     * @param int|null    $casoId           ID del caso relacionado
     * @param int|null    $entidadId        ID de la entidad afectada
     * @param int|null    $usuarioAfectado  ID del usuario afectado
     * @param array|null  $metadata         Datos adicionales en JSON
     */
    public static function registrar(
        string  $modulo,
        string  $accion,
        string  $descripcion,
        ?int    $casoId          = null,
        ?int    $entidadId       = null,
        ?int    $usuarioAfectado = null,
        ?array  $metadata        = null
    ): self {
        $request = request();

        return self::create([
            'caso_id'          => $casoId,
            'user_id'          => auth()->id(),
            'modulo'           => $modulo,
            'accion'           => $accion,
            'entidad_id'       => $entidadId,
            'usuario_afectado' => $usuarioAfectado,
            'descripcion'      => $descripcion,
            'metadata'         => $metadata,
            'ip'               => $request?->ip(),
            'user_agent'       => $request?->userAgent(),
            'created_at'       => now(),
        ]);
    }
}
