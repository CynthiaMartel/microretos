<?php

namespace App\Services;

use App\Jobs\EnviarCorreoInfoJob;
use App\Support\EnvioAlResponder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Correos de DuaLab a personas de fuera que tienen que quedar en el buzón de info@ (p. ej. las
 * solicitudes de retos al profesorado): se maquetan con su Mailable de siempre (HTML y texto) y
 * salen por la web (EnviarCorreoInfoJob), que los guarda en Enviados y recibe las respuestas.
 *
 * Sin la integración con la web configurada (en local, normalmente) salen como antes, por el SMTP
 * de la app.
 */
class CorreoInfoService
{
    public function enviar(Mailable $correo, string $para, string $usuario): void
    {
        if (!$this->webConfigurada()) {
            Mail::to($para)->queue($correo);

            return;
        }

        $contenido = $correo->content();
        // Sin esperar al cron de la cola; si la web no responde, a la cola con sus reintentos
        EnvioAlResponder::despachar(new EnviarCorreoInfoJob(
            $para,
            (string) $correo->envelope()->subject,
            view($contenido->text, $contenido->with)->render(),
            $correo->render(),
            $usuario,
        ));
    }

    private function webConfigurada(): bool
    {
        return (string) config('frontoffice.api_url') !== '' && strlen((string) config('frontoffice.central_secreto')) >= 32;
    }
}
