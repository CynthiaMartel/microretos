<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Impide que la app se incruste en iframes de otros dominios (clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');

        // Desactiva el sniffing de tipo MIME que puede ejecutar archivos como scripts
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Controla qué información de referencia se envía en las peticiones salientes
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Las respuestas de la API son JSON — no necesitan cargar ningún recurso externo
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        // Evita que ventanas abiertas desde esta app puedan manipular el contexto de navegación
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Las respuestas de la API solo deben ser leídas desde el mismo sitio (SPA en mismo dominio)
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        // Fuerza HTTPS durante 1 año e incluye subdominios (activo solo en producción)
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Política de permisos: desactiva APIs sensibles que la app no necesita
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        return $response;
    }
}
