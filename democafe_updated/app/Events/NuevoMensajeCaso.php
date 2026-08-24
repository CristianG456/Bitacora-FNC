<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NuevoMensajeCaso implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $mensaje;
    public $casoId;

    /**
     * Create a new event instance.
     */
    public function __construct($mensaje, $casoId)
    {
        $this->mensaje = $mensaje;
        $this->casoId = $casoId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('caso.' . $this->casoId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NuevoMensajeCaso';
    }
}
