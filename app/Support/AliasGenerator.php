<?php

namespace App\Support;

/**
 * Genera un alias de exhibición ("Nombre Animal") a partir del nombre real de un
 * alumno/a, al estilo del generador de apodos aleatorios de Kahoot (nombre + palabra
 * llamativa, sin implicar dato personal). Determinista por nombre + posición para la
 * asignación inicial; el botón "generar otro" del frontend elige uno al azar sobre
 * esta misma lista sin pasar por el backend.
 */
class AliasGenerator
{
    // Animales como "mote" — igual de válido detrás de cualquier nombre de pila, sin
    // depender del género del alumno.
    public const ANIMALES = [
        'Panda', 'Tigre', 'León', 'Delfín', 'Águila', 'Lobo', 'Zorro', 'Koala',
        'Halcón', 'Pingüino', 'Jaguar', 'Puma', 'Búho', 'Colibrí', 'Nutria',
        'Lince', 'Gacela', 'Cóndor', 'Orca', 'Mapache',
    ];

    /**
     * @param string $nombreCompleto Nombre real tal cual lo introdujo el docente/alumnado.
     * @param int $posicion Índice del miembro dentro de su equipo, para evitar que dos
     *                       compañeros con el mismo nombre de pila reciban el mismo animal.
     */
    public static function generar(string $nombreCompleto, int $posicion = 0): string
    {
        $primerNombre = trim(explode(' ', trim($nombreCompleto))[0] ?? '') ?: 'Alumno';
        $indice = (crc32(mb_strtolower($primerNombre)) + $posicion) % count(self::ANIMALES);

        return $primerNombre . ' ' . self::ANIMALES[$indice];
    }
}
