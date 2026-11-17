<?php

namespace App\Mail;

use App\Models\EmpresaCaptacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al comercial: la empresa ha completado y firmado la entrevista que le envió por correo.
 * Solo el nombre de la empresa y el enlace a la entrevista; las respuestas se leen en la app.
 */
class FormularioEmpresaRespondidoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public EmpresaCaptacion $captacion, public string $comercial) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->captacion->nombre_comercial} ha completado y firmado la entrevista · DuaLab");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.captacion.formulario-respondido',
            text: 'emails.captacion.formulario-respondido-texto',
            with: [
                'comercial' => $this->comercial,
                'empresa'   => $this->captacion->nombre_comercial,
                'enlace'    => rtrim((string) config('captacion.url_app_comercial'), '/') . '/entrevista/' . $this->captacion->id,
            ],
        );
    }
}
