<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Igual que ValidateCsrfToken, pero la cookie del token se llama como diga
 * session.xsrf_cookie (XSRF-TOKEN-comercial, XSRF-TOKEN-central…; ver SesionPorHerramienta).
 * Con una sola XSRF-TOKEN en .dualab.es, cada herramienta pisaba la de las otras y sus
 * peticiones fallaban con 419. La validación no cambia: el token llega en X-XSRF-TOKEN.
 */
class ValidarCsrfPorHerramienta extends ValidateCsrfToken
{
    protected function newCookie($request, $config)
    {
        $cookie = parent::newCookie($request, $config);
        $nombre = $config['xsrf_cookie'] ?? 'XSRF-TOKEN';

        if ($nombre === $cookie->getName()) {
            return $cookie;
        }

        return new Cookie(
            $nombre,
            $cookie->getValue(),
            $cookie->getExpiresTime(),
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            $cookie->isRaw(),
            $cookie->getSameSite(),
            $cookie->isPartitioned()
        );
    }
}
