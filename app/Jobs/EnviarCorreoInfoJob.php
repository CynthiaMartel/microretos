<?php

namespace App\Jobs;

use App\Exceptions\FrontofficeNoDisponible;
use App\Services\FrontofficeBandejaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Correo de DuaLab que sale desde info@ por la web (API firmada de Central, como lo que se escribe
 * en «Correo (info@)»): así queda en Enviados de info@ y en la bandeja de salida, y la respuesta
 * llega al mismo buzón. Va por cola para no hacer esperar a quien lo pide si la web tarda o no
 * responde; se reintenta. Lo usa CorreoInfoService.
 */
class EnviarCorreoInfoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public string $para,
        public string $asunto,
        public string $texto,
        public string $html,
        public string $usuario,
    ) {}

    public function handle(FrontofficeBandejaService $web): void
    {
        $r = $web->enviarCorreo([
            'tipo' => 'nuevo', 'para' => [$this->para], 'asunto' => $this->asunto,
            'texto' => $this->texto, 'html' => $this->html,
        ], $this->usuario);

        // 422: la web lo rechaza (límite diario, dirección no válida…): reintentar no lo arregla
        if ($r->status() === 422) {
            Log::warning('Correo por info@ rechazado por la web.', ['asunto' => $this->asunto, 'errores' => array_keys($r->json('errors') ?? [])]);
            $this->fail(new RuntimeException('La web ha rechazado el correo.'));

            return;
        }
        if (!$r->successful()) {
            throw new FrontofficeNoDisponible('La web no ha aceptado el correo (HTTP ' . $r->status() . ').');
        }
    }

    public function failed(?Throwable $e): void
    {
        // Sin el email del destinatario en el registro (datos personales)
        Log::warning('Correo por info@ sin enviar tras los reintentos.', ['asunto' => $this->asunto, 'error' => $e ? class_basename($e) : null]);
    }
}
