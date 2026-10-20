<?php

namespace App\Support;

/**
 * Alias del alumnado: "NombreDePila Animal Color" ("Adrián Águila Amarillo"), al estilo de
 * los apodos de Kahoot. El nombre de pila lo escribe el docente o el alumnado; el animal y
 * el color se eligen al azar.
 *
 * Protección de datos: la plataforma no guarda el nombre real completo, solo este alias.
 * Por eso el nombre de pila es UNA sola palabra (los compuestos van con guion: "María-José")
 * y el resto tiene que ser una combinación de estas dos listas (ver esValido()): así no
 * caben apellidos ni otros datos. El docente lleva fuera de la plataforma la
 * correspondencia alias → alumno/a.
 *
 * Si se cambia una lista o el formato aquí, cambiar también frontend-microretos/src/utils/alias.js.
 */
class AliasGenerator
{
    // Nombre de pila para los alias que no tienen ninguno (datos antiguos)
    public const NOMBRE_POR_DEFECTO = 'Alumno';

    // Una palabra de letras, con guion o apóstrofo internos (María-José, D'Artagnan)
    private const PATRON_NOMBRE = "/^\\p{L}+(?:['-]\\p{L}+)*$/u";

    private const MAX_NOMBRE = 30;

    // Animales como "mote" — válidos para cualquier alumno, sin depender del género.
    public const ANIMALES = [
        'Panda', 'Tigre', 'León', 'Delfín', 'Águila', 'Lobo', 'Ratón', 'Koala',
        'Halcón', 'Pingüino', 'Jaguar', 'Puma', 'Búho', 'Colibrí', 'Nutria',
        'Lince', 'Gacela', 'Cóndor', 'Orca', 'Mapache',
    ];

    // Colores llamativos que acompañan al animal para dar más variedad de combinaciones.
    public const COLORES = [
        'Magenta', 'Azul', 'Coral', 'Verde', 'Amarillo', 'Violeta', 'Turquesa',
        'Naranja', 'Rosa', 'Dorado', 'Plateado', 'Índigo', 'Esmeralda', 'Rubí',
        'Aqua', 'Lima', 'Carmesí', 'Ámbar',
    ];

    /**
     * Alias con ese nombre de pila y un animal + color al azar que no esté en $usados (sin
     * distinguir mayúsculas). Con 360 combinaciones por nombre siempre queda alguna libre;
     * si aun así se agotan, se devuelve una repetida antes que fallar.
     *
     * @param iterable<int, string|null> $usados
     */
    public static function generar(?string $nombrePila, iterable $usados = []): string
    {
        $nombre = self::nombreDePila($nombrePila) ?? self::NOMBRE_POR_DEFECTO;

        $ocupados = [];
        foreach ($usados as $a) {
            $ocupados[self::normalizar($a)] = true;
        }

        $libres = [];
        foreach (self::ANIMALES as $animal) {
            foreach (self::COLORES as $color) {
                $alias = "{$nombre} {$animal} {$color}";
                if (!isset($ocupados[self::normalizar($alias)])) {
                    $libres[] = $alias;
                }
            }
        }

        return $libres
            ? $libres[array_rand($libres)]
            : "{$nombre} " . self::ANIMALES[array_rand(self::ANIMALES)] . ' ' . self::COLORES[array_rand(self::COLORES)];
    }

    /**
     * Nombre de pila a partir de un texto: solo la primera palabra (descarta apellidos),
     * sin caracteres que no sean letras, guion o apóstrofo, con mayúscula inicial.
     * Null si no queda nada válido.
     */
    public static function nombreDePila(?string $texto): ?string
    {
        $palabras = preg_split('/\s+/u', trim((string) $texto)) ?: [];
        $primera = $palabras[0] ?? '';
        $limpia = trim((string) preg_replace("/[^\\p{L}'-]/u", '', $primera), "'-");
        if ($limpia === '' || !preg_match(self::PATRON_NOMBRE, $limpia)) {
            return null;
        }

        return mb_substr(mb_convert_case(mb_strtolower($limpia), MB_CASE_TITLE), 0, self::MAX_NOMBRE);
    }

    /** El alias es exactamente "NombreDePila Animal Color" (no admite apellidos ni texto libre). */
    public static function esValido(?string $alias): bool
    {
        $partes = explode(' ', trim((string) $alias));

        return count($partes) === 3
            && mb_strlen($partes[0]) <= self::MAX_NOMBRE
            && preg_match(self::PATRON_NOMBRE, $partes[0]) === 1
            && in_array($partes[1], self::ANIMALES, true)
            && in_array($partes[2], self::COLORES, true);
    }

    /**
     * Alias libre en la clase: el mismo si es válido y no está en $usados; si choca, otro con
     * el mismo nombre de pila; si no es válido, uno nuevo con el nombre de pila que contenga.
     *
     * @param iterable<int, string|null> $usados
     */
    public static function libre(?string $alias, iterable $usados = []): string
    {
        $usados = is_array($usados) ? $usados : iterator_to_array($usados, false);
        $ocupado = in_array(self::normalizar($alias), array_map([self::class, 'normalizar'], $usados), true);

        if (self::esValido($alias) && !$ocupado) {
            return trim((string) $alias);
        }

        return self::generar(self::nombreDeAlias($alias), $usados);
    }

    /**
     * Nombre de pila contenido en un alias: lo que va antes de "Animal Color". Un alias sin
     * nombre ("Panda Azul", formato antiguo) no tiene, aunque su primera palabra sean letras.
     */
    public static function nombreDeAlias(?string $alias): ?string
    {
        $partes = explode(' ', trim((string) $alias));
        $n = count($partes);
        if ($n >= 2 && in_array($partes[$n - 2], self::ANIMALES, true) && in_array($partes[$n - 1], self::COLORES, true)) {
            return $n >= 3 ? self::nombreDePila($partes[0]) : null;
        }

        return self::nombreDePila($alias);
    }

    public static function normalizar(?string $texto): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $texto)));
    }
}
