<?php

namespace App\Support;

class CodigoLegible
{
    // Sin I, O, S, Z (confundibles al proyectar o escribir a mano)
    private const CHARSET = 'ABCDEFGHJKLMNPQRTUVWXY';

    /**
     * Genera un código legible tipo "XKM-479". $existe recibe el candidato y
     * devuelve true si ya está en uso, para reintentar con otro.
     */
    public static function generar(callable $existe): string
    {
        $intentos = 0;
        do {
            $letras  = self::CHARSET[random_int(0, strlen(self::CHARSET) - 1)]
                     . self::CHARSET[random_int(0, strlen(self::CHARSET) - 1)]
                     . self::CHARSET[random_int(0, strlen(self::CHARSET) - 1)];
            $numeros = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
            $codigo  = $letras . '-' . $numeros;
            if (++$intentos > 200) {
                throw new \RuntimeException('Espacio de códigos agotado.');
            }
        } while ($existe($codigo));

        return $codigo;
    }
}
