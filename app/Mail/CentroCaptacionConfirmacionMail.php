<?php

namespace App\Mail;

use App\Models\CentroCaptacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmación al centro entrevistado por un comercial: mismo texto que la que envía el
 * formulario de centros del frontoffice. Nunca repite los comentarios libres.
 */
class CentroCaptacionConfirmacionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public const ACCIONES = [
        'registro' => 'Alta en DuaLab',
        'info'     => 'Más información',
        'demo'     => 'Solicitud de demo',
    ];

    public function __construct(public CentroCaptacion $captacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hemos recibido tu solicitud · DuaLab');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.captacion.centro-confirmacion',
            text: 'emails.captacion.centro-confirmacion-texto',
            with: [
                'nombre' => $this->captacion->contacto_nombre,
                'accion' => $this->captacion->accion,
                'textoAccion' => self::ACCIONES[$this->captacion->accion] ?? null,
                'centro' => $this->captacion->centro_nombre,
            ],
        );
    }
}
