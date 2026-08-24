<?php

namespace App\Events;

use App\Models\Caso;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CasoCreado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $caso;
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Caso $caso)
    {
        $this->caso = $caso;
        
        $this->caso->load('tipo', 'solicitante', 'usuarios');
        
        $nombreSolicitante = $this->caso->solicitante ? $this->caso->solicitante->nombre : 'Desconocido';
        $tipoProceso = $this->caso->tipo ? $this->caso->tipo->nombre : 'Caso';

        $this->message = "¡Se ha creado un nuevo caso ({$caso->radicado}): {$tipoProceso} por {$nombreSolicitante}!";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('casos'),
        ];
    }
    
    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'CasoCreado';
    }
}
