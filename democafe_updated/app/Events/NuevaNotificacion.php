<?php

namespace App\Events;

use App\Models\Notificacion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NuevaNotificacion implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notificacion;

    /**
     * Create a new event instance.
     */
    public function __construct(Notificacion $notificacion)
    {
        $this->notificacion = $notificacion;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->notificacion->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'NuevaNotificacion';
    }

    /**
     * Data to broadcast
     */
    public function broadcastWith(): array
    {
        // Enviar sólo lo necesario al frontend para seguridad y rendimiento
        return [
            'id' => $this->notificacion->id,
            'titulo' => $this->notificacion->titulo,
            'mensaje' => $this->notificacion->mensaje,
            'tipo' => $this->notificacion->tipo,
            'created_at' => $this->notificacion->created_at->toISOString(),
            'metadata' => $this->notificacion->metadata ?? null, // Si existiera a futuro
        ];
    }
}
