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
        $notificacion = self::create([
            'user_id'    => $userId,
            'tipo'       => $tipo,
            'titulo'     => $titulo,
            'mensaje'    => $mensaje,
            'leido'      => false,
            'created_at' => now(),
        ]);

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

        return $notificacion;
    }
}
