<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso al buzón de soporte con la consulta recibida. */
class SoporteAvisoMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public const TIPOS = [
        'acceso'   => 'No puedo iniciar sesión',
        'error'    => 'Algo no funciona',
        'consulta' => 'Duda sobre la plataforma',
        'otro'     => 'Otro',
    ];

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public array $solicitud) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.soporte.address'), config('mail.soporte.name')),
            subject: 'Soporte · ' . self::TIPOS[$this->solicitud['tipo']] . ' · ' . $this->solicitud['nombre'],
            // "Responder" contesta directamente a quien envió el formulario
            replyTo: [new Address($this->solicitud['email'], $this->solicitud['nombre'])],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.soporte.aviso',
            text: 'emails.soporte.aviso-texto',
            with: [
                'tipo'  => self::TIPOS[$this->solicitud['tipo']],
                'fecha' => now()->timezone('Atlantic/Canary')->format('d/m/Y H:i'),
            ],
        );
    }
}
