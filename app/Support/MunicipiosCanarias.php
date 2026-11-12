<?php

namespace App\Support;

/**
 * Provincias y municipios de Canarias: los mismos que el formulario de centros del frontoffice
 * (FRONTOFFICE/…/frontend/src/data/contacto.js) y frontend-microretos/src/config/municipiosCanarias.js.
 * Si cambia una lista, cambiar las tres.
 */
final class MunicipiosCanarias
{
    public const PROVINCIAS = ['Las Palmas', 'Santa Cruz de Tenerife'];

    public const MUNICIPIOS = [
        'Las Palmas' => [
            'Agaete', 'Agüimes', 'Antigua', 'Arrecife', 'Artenara', 'Arucas', 'Betancuria', 'Firgas',
            'Gáldar', 'Haría', 'Ingenio', 'La Aldea de San Nicolás', 'La Oliva', 'Las Palmas de Gran Canaria',
            'Mogán', 'Moya', 'Pájara', 'Puerto del Rosario', 'San Bartolomé', 'San Bartolomé de Tirajana',
            'Santa Brígida', 'Santa Lucía de Tirajana', 'Santa María de Guía', 'Teguise', 'Tejeda', 'Telde',
            'Teror', 'Tías', 'Tinajo', 'Tuineje', 'Valleseco', 'Valsequillo de Gran Canaria',
            'Vega de San Mateo', 'Yaiza',
        ],
        'Santa Cruz de Tenerife' => [
            'Adeje', 'Agulo', 'Alajeró', 'Arafo', 'Arico', 'Arona', 'Barlovento', 'Breña Alta', 'Breña Baja',
            'Buenavista del Norte', 'Candelaria', 'El Paso', 'El Pinar de El Hierro', 'El Rosario', 'El Sauzal',
            'El Tanque', 'Fasnia', 'Frontera', 'Fuencaliente de La Palma', 'Garachico', 'Garafía',
            'Granadilla de Abona', 'Guía de Isora', 'Güímar', 'Hermigua', 'Icod de los Vinos', 'La Guancha',
            'La Matanza de Acentejo', 'La Orotava', 'La Victoria de Acentejo', 'Los Llanos de Aridane',
            'Los Realejos', 'Los Silos', 'Puerto de la Cruz', 'Puntagorda', 'Puntallana', 'San Andrés y Sauces',
            'San Cristóbal de La Laguna', 'San Juan de la Rambla', 'San Miguel de Abona',
            'San Sebastián de La Gomera', 'Santa Cruz de La Palma', 'Santa Cruz de Tenerife', 'Santa Úrsula',
            'Santiago del Teide', 'Tacoronte', 'Tazacorte', 'Tegueste', 'Tijarafe', 'Valle Gran Rey',
            'Vallehermoso', 'Valverde', 'Vilaflor de Chasna', 'Villa de Mazo',
        ],
    ];
}
