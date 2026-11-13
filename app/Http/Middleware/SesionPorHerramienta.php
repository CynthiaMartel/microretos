<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige la cookie de sesión y la de CSRF según la herramienta que hace la petición
 * (config/session.php → herramientas). Global y antes de StartSession: el nombre de
 * la cookie tiene que estar decidido cuando se arranca la sesión.
 *
 * El origen sale de Referer/Origin, igual que en Sanctum (EnsureFrontendRequestsAreStateful).
 * Un cliente fuera del navegador puede falsearlo, pero solo cambia qué cookie se lee: no da
 * acceso a ninguna sesión que no tenga ya, y el CSRF y los middleware de rol siguen igual.
 */
class SesionPorHerramienta
{
    public function handle(Request $request, Closure $next): Response
    {
        $herramienta = $this->herramienta($request);

        if ($herramienta !== null) {
            $cookie = config('session.cookie').'-'.$herramienta;
            config([
                'session.cookie'      => $cookie,
                'session.xsrf_cookie' => 'XSRF-TOKEN-'.$herramienta,
            ]);
            // Por si el driver de sesión ya se creó con el nombre anterior
            app('session')->driver()->setName($cookie);
        }

        return $next($request);
    }

    private function herramienta(Request $request): ?string
    {
        $url = $request->headers->get('referer') ?: $request->headers->get('origin');
        if (! is_string($url) || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host)) {
            return null;
        }
        $port = parse_url($url, PHP_URL_PORT);
        $origen = strtolower($host).($port ? ':'.$port : '');

        foreach ((array) config('session.herramientas', []) as $herramienta => $origenes) {
            if (in_array($origen, (array) $origenes, true)) {
                return (string) $herramienta;
            }
        }

        return null;
    }
}
