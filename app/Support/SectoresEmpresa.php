<?php

namespace App\Support;

/**
 * Lista cerrada de sectores de empresa. El sector es la categoría general de la empresa
 * (una sola); lo concreto que hace va en `actividad`, en texto libre, y las áreas con las
 * que quiere trabajar van en sus familias (con su interés). Lista propia en vez de las
 * secciones CNAE porque "Industria manufacturera" junta familias de FP distintas.
 *
 * Copia en frontend-microretos/src/config/sectoresEmpresa.js: cambiar las dos a la vez.
 */
final class SectoresEmpresa
{
    public const LISTA = [
        'Tecnología e informática',
        'Consultoría y servicios a empresas',
        'Marketing, publicidad y diseño',
        'Comercio y distribución',
        'Industria y fabricación',
        'Alimentación y agroalimentario',
        'Agricultura, pesca y jardinería',
        'Construcción e inmobiliaria',
        'Energía e instalaciones',
        'Automoción',
        'Transporte y logística',
        'Hostelería, restauración y turismo',
        'Salud, estética y bienestar',
        'Servicios sociales y tercer sector',
        'Cultura, deporte y ocio',
        'Medio ambiente',
    ];

    public static function esValido(?string $sector): bool
    {
        return in_array($sector, self::LISTA, true);
    }
}
