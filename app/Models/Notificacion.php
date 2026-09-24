<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'caso_id',
        'tarea_id',
        'solicitud_correccion_id',
        'mensaje_id',
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

    public function caso()
    {
        return $this->belongsTo(Caso::class, 'caso_id');
    }

    public function tarea()
    {
        return $this->belongsTo(Tarea::class, 'tarea_id');
    }

    public function solicitudCorreccion()
    {
        return $this->belongsTo(SolicitudCorreccionTarea::class, 'solicitud_correccion_id');
    }

    public function mensajeRelacionado()
    {
        return $this->belongsTo(Mensaje::class, 'mensaje_id');
    }

    // ─── Helper: crear notificación desde cualquier parte ──────────

    public static function enviar(
        int    $userId,
        string $titulo,
        string $mensaje,
        string $tipo = 'info',
        ?int $casoId = null,
        ?int $mensajeId = null,
        ?int $tareaId = null,
        ?int $solicitudCorreccionId = null,
    ): self {
        $notificacion = self::create([
            'user_id'    => $userId,
            'caso_id'    => $casoId,
            'tarea_id'   => $tareaId,
            'solicitud_correccion_id' => $solicitudCorreccionId,
            'mensaje_id' => $mensajeId,
            'tipo'       => $tipo,
            'titulo'     => $titulo,
            'mensaje'    => $mensaje,
            'leido'      => false,
            'created_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::afterCommit(function () use ($userId, $titulo, $mensaje) {
            try {
                $user = User::find($userId);
                if ($user && $user->email) {
                    Mail::raw($mensaje, function ($msg) use ($user, $titulo) {
                        $msg->to($user->email)
                            ->subject($titulo);
                    });
                }
            } catch (\Exception $e) {
                // Ignorar errores de correo para no romper la app
            }
        });

        return $notificacion;
    }
}
