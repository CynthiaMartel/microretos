<?php

namespace App\Mail;

use App\Models\SolicitudReto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso a quien creó la solicitud: el docente la ha enviado (SolicitudRetoService::recibir).
 * Solo el nombre del docente, su centro y el enlace a la ficha; las respuestas se leen en Central.
 */
class SolicitudRetoRespondidaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public SolicitudReto $solicitud, public string $destinatario) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->solicitud->nombre_docente} ha enviado su solicitud de retos · DuaLab");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitudes-reto.respondida',
            text: 'emails.solicitudes-reto.respondida-texto',
            with: [
                'destinatario' => $this->destinatario,
                'docente'      => $this->solicitud->nombre_docente,
                'centro'       => $this->solicitud->centro_nombre,
                'enlace'       => rtrim((string) config('solicitudes_reto.url_central'), '/') . '/primer-contacto/solicitudes-reto/' . $this->solicitud->id,
            ],
        );
    }
}
