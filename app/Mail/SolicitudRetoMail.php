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
 * Invitación al docente a contar qué quiere trabajar para que DuaLab le prepare los retos
 * (SolicitudRetoService::enviar). Mismo formato que FormularioEmpresaMail. Si responde, la respuesta
 * va a info@ con la referencia en el asunto (Central → «Respuestas de solicitudes»).
 */
class SolicitudRetoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public SolicitudReto $solicitud, public User $autor, public string $enlace) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cuéntanos qué quieres trabajar y te preparamos los retos · DuaLab · ' . $this->solicitud->referencia(),
            replyTo: [new Address(config('solicitudes_reto.responder_a.email'), config('solicitudes_reto.responder_a.nombre'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitudes-reto.invitacion',
            text: 'emails.solicitudes-reto.invitacion-texto',
            with: [
                'docente'   => $this->solicitud->nombre_docente,
                'enlace'    => $this->enlace,
                'caduca'    => $this->solicitud->expira_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            ],
        );
    }
}
