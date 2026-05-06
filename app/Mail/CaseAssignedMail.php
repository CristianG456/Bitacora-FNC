<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Caso;
use App\Models\User;

class CaseAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $caso;
    public $user;
    public $tareas;

    public function __construct(Caso $caso, User $user, $tareas = [])
    {
        $this->caso = $caso;
        $this->user = $user;
        $this->tareas = $tareas;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva Asignación de Caso Jurídico: ' . $this->caso->radicado,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.case-assigned',
        );
    }
}
