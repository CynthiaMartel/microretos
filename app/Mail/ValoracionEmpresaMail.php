<?php

namespace App\Mail;

use App\Models\EncuentroValoracionEmpresa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * «Vuestro reto ya tiene soluciones»: DuaLab, desde Central (Empresas → «Enviar soluciones a las
 * empresas»), envía a la empresa el enlace para ver las soluciones de los grupos y valorar el reto,
 * sin cuenta. Sale por info@ (CorreoInfoService); las respuestas vuelven a info@.
 */
class ValoracionEmpresaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(
        public EncuentroValoracionEmpresa $valoracion,
        public string $enlace,
        public string $empresa,
        public string $reto,
        public ?string $mensaje = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Las soluciones de vuestro reto · {$this->reto}",
            replyTo: [new Address(config('solicitudes_reto.responder_a.email'), config('solicitudes_reto.responder_a.nombre'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.valoracion-empresa.solicitud',
            text: 'emails.valoracion-empresa.solicitud-texto',
            with: [
                'empresa' => $this->empresa,
                'reto'    => $this->reto,
                'enlace'  => $this->enlace,
                'nota'    => $this->mensaje, // no 'mensaje': Laravel reserva $message en las vistas de correo
                'caduca'  => $this->valoracion->token_expira_en?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            ],
        );
    }
}
