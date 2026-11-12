<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook del frontoffice (EnviarFormularioAWebhook): el cuerpo llega firmado con HMAC-SHA256
 * en la cabecera X-DuaLab-Firma con el secreto compartido (config captacion.frontoffice_webhook_secreto).
 * Sin secreto configurado no se acepta nada: el endpoint es público y sin sesión.
 */
class VerificarFirmaFrontoffice
{
    private const LONGITUD_MINIMA_SECRETO = 16; // mismo mínimo que exige el panel del frontoffice

    public function handle(Request $request, Closure $next): Response
    {
        $secreto = (string) config('captacion.frontoffice_webhook_secreto');
        if (strlen($secreto) < self::LONGITUD_MINIMA_SECRETO) {
            return response()->json(['error' => 'Integración no configurada.'], 503);
        }

        $firma = (string) $request->header('X-DuaLab-Firma', '');
        if ($firma === '' || !hash_equals(hash_hmac('sha256', $request->getContent(), $secreto), $firma)) {
            Log::warning('Webhook del frontoffice con firma no válida.', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Firma no válida.'], 401);
        }

        return $next($request);
    }
}
