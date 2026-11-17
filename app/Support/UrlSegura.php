<?php

namespace App\Support;

/**
 * URLs que escribe el alumnado a mano (entregables, prototipos): solo se devuelven si son
 * http(s), para que nunca llegue al navegador un enlace javascript: u otro esquema peligroso.
 */
final class UrlSegura
{
    public static function http(?string $url): ?string
    {
        if (!$url) return null;
        $url = trim($url);
        $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($esquema, ['http', 'https'], true) ? $url : null;
    }
}
