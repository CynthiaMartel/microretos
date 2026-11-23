<?php

namespace App\Mail;

use App\Models\SolicitudReto;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * «Ya puedes trabajar tus retos» (SolicitudRetoService::autorizar): DuaLab los ha pasado a la
 * biblioteca del centro del docente, que tiene cuenta. Cada enlace abre el reto en la app, con
 * «Trabajar este reto». Las respuestas van a info@ con la referencia en el asunto.
 */
class SolicitudRetoAutorizadosMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    /** @param array<int, array{titulo: string, enlace: string}> $retos */
    public function __construct(
        public SolicitudReto $solicitud,
        public User $autor,
        public array $retos,
        public ?string $mensaje = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ya puedes trabajar tus retos en DuaLab · ' . $this->solicitud->referencia(),
            replyTo: [new Address(config('solicitudes_reto.responder_a.email'), config('solicitudes_reto.responder_a.nombre'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitudes-reto.retos-autorizados',
            text: 'emails.solicitudes-reto.retos-autorizados-texto',
            with: [
                'docente' => $this->solicitud->nombre_docente,
                'retos'   => $this->retos,
                'nota'    => $this->mensaje, // no 'mensaje': Laravel reserva $message en las vistas de correo
            ],
        );
    }
}
