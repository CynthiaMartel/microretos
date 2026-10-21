<?php

namespace App\Services;

use App\Mail\SoporteAvisoMail;
use App\Mail\SoporteConfirmacionMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SoporteService
{
    // Sin tabla propia: la consulta solo viaja por email (encolado, con reintentos;
    // si falla del todo queda en failed_jobs).
    /** @param array<string, mixed> $data */
    public function enviar(array $data): void
    {
        $solicitud = [
            'nombre'  => trim(strip_tags($data['nombre'])),
            'email'   => Str::lower($data['email']),
            'tipo'    => $data['tipo'],
            'mensaje' => trim(strip_tags($data['mensaje'])),
        ];

        Mail::mailer('soporte')->to(config('mail.soporte.address'))->queue(new SoporteAvisoMail($solicitud));
        Mail::mailer('soporte')->to($solicitud['email'])->queue(new SoporteConfirmacionMail($solicitud['nombre'], $solicitud['tipo']));

        // Sin PII: ni email ni nombre ni el mensaje
        Log::info('Consulta de soporte recibida', ['tipo' => $solicitud['tipo']]);
    }
}
