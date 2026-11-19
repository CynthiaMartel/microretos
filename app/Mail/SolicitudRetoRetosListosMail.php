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
 * «Tus retos están listos» (SolicitudRetoService::avisar): lo envía DuaLab a mano cuando da por
 * buenos los retos. El enlace abre la solicitud con esos retos en solo lectura, sin cuenta. Las
 * respuestas van a info@ con la referencia en el asunto, como las de la invitación.
 */
class SolicitudRetoRetosListosMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    /** @param array<int, string> $titulos */
    public function __construct(
        public SolicitudReto $solicitud,
        public User $autor,
        public string $enlace,
        public array $titulos,
        public ?string $mensaje = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tus retos de DuaLab están listos · ' . $this->solicitud->referencia(),
            replyTo: [new Address(config('solicitudes_reto.responder_a.email'), config('solicitudes_reto.responder_a.nombre'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitudes-reto.retos-listos',
            text: 'emails.solicitudes-reto.retos-listos-texto',
            with: [
                'docente'   => $this->solicitud->nombre_docente,
                'enlace'    => $this->enlace,
                'retos'     => $this->titulos,
                'nota'      => $this->mensaje, // no 'mensaje': Laravel reserva $message en las vistas de correo
                'caduca'    => $this->solicitud->expira_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            ],
        );
    }
}
