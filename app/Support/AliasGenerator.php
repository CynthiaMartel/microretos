<?php

namespace App\Support;

/**
 * Genera un alias de exhibición ("Nombre Animal Color") a partir del nombre real de un
 * alumno/a, al estilo del generador de apodos aleatorios de Kahoot (nombre + palabra
 * llamativa, sin implicar dato personal). Determinista por nombre + posición para la
 * asignación inicial; el botón "generar otro" del frontend elige uno al azar sobre
 * estas mismas listas sin pasar por el backend.
 */
class AliasGenerator
{
    // Animales como "mote" — igual de válido detrás de cualquier nombre de pila, sin
    // depender del género del alumno.
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
     * @param string $nombreCompleto Nombre real tal cual lo introdujo el docente/alumnado.
     * @param int $posicion Índice del miembro dentro de su equipo, para evitar que dos
     *                       compañeros con el mismo nombre de pila reciban la misma
     *                       combinación de animal y color.
     */
    public static function generar(string $nombreCompleto, int $posicion = 0): string
    {
        $primerNombre = trim(explode(' ', trim($nombreCompleto))[0] ?? '') ?: 'Alumno';
        $base = mb_strtolower($primerNombre);

        $indiceAnimal = (crc32($base) + $posicion) % count(self::ANIMALES);
        $indiceColor = (crc32($base . '|color') + $posicion) % count(self::COLORES);

        return $primerNombre . ' ' . self::ANIMALES[$indiceAnimal] . ' ' . self::COLORES[$indiceColor];
    }
}
