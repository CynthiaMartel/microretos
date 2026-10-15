<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Confirmación a quien ha escrito a soporte. Nunca repite el mensaje libre (ver vista). */
class SoporteConfirmacionMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public string $nombre, public string $tipo) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.soporte.address'), config('mail.soporte.name')),
            subject: 'Hemos recibido tu consulta · Soporte DUALAB',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.soporte.confirmacion',
            text: 'emails.soporte.confirmacion-texto',
            with: ['tipoTexto' => SoporteAvisoMail::TIPOS[$this->tipo]],
        );
    }
}
