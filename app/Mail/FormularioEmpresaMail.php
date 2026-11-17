<?php

namespace App\Mail;

use App\Models\EmpresaCaptacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitación a hacer la entrevista por correo (FormularioEmpresaService::enviar): mismo formato
 * que los correos de los formularios de dualab.es (saludo, texto, botón, resumen y pie). Si la
 * empresa responde al correo, le contesta el comercial que se lo envió (Reply-To).
 */
class FormularioEmpresaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public EmpresaCaptacion $captacion, public User $comercial, public string $enlace) {}

    public function envelope(): Envelope
    {
        $empresa = $this->captacion->nombre_comercial;

        return new Envelope(
            subject: $empresa
                ? "Completa la entrevista de {$empresa} para colaborar con DuaLab"
                : 'Completa la entrevista para colaborar con DuaLab',
            replyTo: $this->comercial->email ? [new Address($this->comercial->email, $this->comercial->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.captacion.formulario-empresa',
            text: 'emails.captacion.formulario-empresa-texto',
            with: [
                'contacto'  => $this->captacion->persona_contacto,
                'empresa'   => $this->captacion->nombre_comercial,
                'remitente' => $this->comercial->name, // no 'comercial': la propiedad pública (User) la pisaría en la vista
                'enlace'    => $this->enlace,
                'caduca'    => $this->captacion->formulario_expira_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            ],
        );
    }
}
