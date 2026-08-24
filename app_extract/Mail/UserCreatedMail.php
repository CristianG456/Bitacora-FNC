<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $temporalPassword;

    public function __construct(User $user, string $temporalPassword)
    {
        $this->user = $user;
        $this->temporalPassword = $temporalPassword;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenido al Sistema - Creación de Cuenta',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-created',
        );
    }
}
