<?php

namespace App\Support;

/**
 * Biblioteca de retos y diagnósticos de DuaLab (paquete LD-14): 30 áreas de diagnóstico, cada
 * una con su reto tipo, y un modelo de madurez de 4 dimensiones puntuadas de 0 a 4.
 * Las áreas y retos se generaron desde DUALAB_BIBLIOTECA_DIAGNOSTICOS.csv y _RETOS.csv:
 * no editar a mano sin revisar el origen.
 *
 * Uso: empresas:diagnosticar-madurez guarda en empresas.diagnostico_madurez el área y las 4
 * puntuaciones; el Generador de Retos las usa para atacar la dimensión más débil y ajustar
 * la ambición del reto, con el reto tipo de la familia como inspiración.
 */
class BibliotecaRetos
{
    /** Área => familias que la trabajan y su reto tipo */
    public const AREAS = [
        'Presencia digital' => [
            'familias'    => ['Comercio y Marketing'],
            'reto'        => 'Plan de mejora de presencia digital',
            'actividades' => 'Inventario de canales, revisión de información pública y propuesta priorizada',
            'entregables' => 'Informe de auditoría y calendario de acciones',
            'indicadores' => 'Visibilidad, coherencia y actualización',
            'duracion'    => '6-12 horas',
        ],
        'Comunicación' => [
            'familias'    => ['Comercio y Marketing'],
            'reto'        => 'Plan editorial multicanal',
            'actividades' => 'Analizar públicos, mensajes, canales y diseñar calendario de contenidos',
            'entregables' => 'Plan editorial de 4 semanas y 6 piezas modelo',
            'indicadores' => 'Adecuación a públicos y consistencia',
            'duracion'    => '6-12 horas',
        ],
        'Clientes' => [
            'familias'    => ['Comercio y Marketing'],
            'reto'        => 'Mapa del recorrido del cliente',
            'actividades' => 'Entrevistas simuladas, puntos de contacto y propuesta de mejoras',
            'entregables' => 'Customer journey y 5 mejoras priorizadas',
            'indicadores' => 'Claridad, utilidad y viabilidad',
            'duracion'    => '6-12 horas',
        ],
        'Ventas' => [
            'familias'    => ['Comercio y Marketing'],
            'reto'        => 'Diseño de embudo de ventas',
            'actividades' => 'Definir etapas, indicadores y seguimiento de oportunidades',
            'entregables' => 'Pipeline y cuadro de indicadores',
            'indicadores' => 'Trazabilidad y métricas',
            'duracion'    => '6-12 horas',
        ],
        'Mercado' => [
            'familias'    => ['Comercio y Marketing'],
            'reto'        => 'Estudio comparativo de competidores',
            'actividades' => 'Identificar competidores, criterios y oportunidades',
            'entregables' => 'Benchmark y propuesta de posicionamiento',
            'indicadores' => 'Fuentes y diferenciación',
            'duracion'    => '6-12 horas',
        ],
        'Fidelización' => [
            'familias'    => ['Comercio y Marketing'],
            'reto'        => 'Programa de fidelización responsable',
            'actividades' => 'Segmentar clientes ficticios y diseñar acciones de retención',
            'entregables' => 'Plan de fidelización y métricas',
            'indicadores' => 'Pertinencia y respeto a privacidad',
            'duracion'    => '6-12 horas',
        ],
        'Comercio electrónico' => [
            'familias'    => ['Comercio y Marketing'],
            'reto'        => 'Prototipo de catálogo digital',
            'actividades' => 'Analizar catálogo, fichas y recorrido de compra',
            'entregables' => 'Catálogo de 10 fichas y wireframe',
            'indicadores' => 'Usabilidad y calidad de fichas',
            'duracion'    => '6-12 horas',
        ],
        'Reputación' => [
            'familias'    => ['Comercio y Marketing'],
            'reto'        => 'Protocolo de respuesta a reseñas',
            'actividades' => 'Clasificar casos ficticios y diseñar respuestas',
            'entregables' => 'Guía de respuestas y protocolo de escalado',
            'indicadores' => 'Tono y resolución',
            'duracion'    => '6-12 horas',
        ],
        'Organización' => [
            'familias'    => ['Administración y Gestión'],
            'reto'        => 'Mapa de procesos administrativos',
            'actividades' => 'Entrevistas y representación de flujos y responsables',
            'entregables' => 'Mapa AS-IS/TO-BE y matriz RACI',
            'indicadores' => 'Claridad y mejora de procesos',
            'duracion'    => '6-12 horas',
        ],
        'Documentación' => [
            'familias'    => ['Administración y Gestión'],
            'reto'        => 'Sistema de archivo y nomenclatura',
            'actividades' => 'Clasificar documentos sintéticos y definir permisos',
            'entregables' => 'Árbol de carpetas y manual de archivo',
            'indicadores' => 'Localización y control de acceso',
            'duracion'    => '6-12 horas',
        ],
        'Facturación' => [
            'familias'    => ['Administración y Gestión'],
            'reto'        => 'Simulador de facturación y cobros',
            'actividades' => 'Diseñar registro de facturas ficticias y alertas',
            'entregables' => 'Plantilla y panel de vencimientos',
            'indicadores' => 'Exactitud y trazabilidad',
            'duracion'    => '6-12 horas',
        ],
        'Tesorería' => [
            'familias'    => ['Administración y Gestión'],
            'reto'        => 'Previsión de caja simplificada',
            'actividades' => 'Modelar cobros, pagos y escenarios ficticios',
            'entregables' => 'Flujo de caja a 12 semanas',
            'indicadores' => 'Consistencia y escenarios',
            'duracion'    => '6-12 horas',
        ],
        'Compras' => [
            'familias'    => ['Administración y Gestión'],
            'reto'        => 'Cuadro de evaluación de proveedores',
            'actividades' => 'Definir criterios, pesos y comparativa ficticia',
            'entregables' => 'Matriz de proveedores y procedimiento',
            'indicadores' => 'Transparencia de criterios',
            'duracion'    => '6-12 horas',
        ],
        'Inventario' => [
            'familias'    => ['Administración y Gestión'],
            'reto'        => 'Control de existencias',
            'actividades' => 'Definir entradas, salidas, mínimos y alertas',
            'entregables' => 'Libro de inventario y guía de uso',
            'indicadores' => 'Integridad de registros',
            'duracion'    => '6-12 horas',
        ],
        'Personal' => [
            'familias'    => ['Administración y Gestión'],
            'reto'        => 'Kit de acogida administrativa',
            'actividades' => 'Diseñar checklist y documentación simulada',
            'entregables' => 'Manual de acogida y checklist',
            'indicadores' => 'Cobertura y claridad',
            'duracion'    => '6-12 horas',
        ],
        'Indicadores' => [
            'familias'    => ['Administración y Gestión'],
            'reto'        => 'Cuadro de mando operativo',
            'actividades' => 'Elegir KPI y crear panel con datos sintéticos',
            'entregables' => 'Dashboard de gestión y diccionario KPI',
            'indicadores' => 'Definiciones y calidad de datos',
            'duracion'    => '6-12 horas',
        ],
        'Equipos' => [
            'familias'    => ['Informática y Comunicaciones'],
            'reto'        => 'Inventario de activos TI',
            'actividades' => 'Clasificar equipos ficticios y necesidades de mantenimiento',
            'entregables' => 'Inventario y calendario preventivo',
            'indicadores' => 'Cobertura y priorización',
            'duracion'    => '10-20 horas',
        ],
        'Ciberhigiene' => [
            'familias'    => ['Informática y Comunicaciones'],
            'reto'        => 'Campaña interna de seguridad digital',
            'actividades' => 'Evaluar prácticas simuladas y elaborar formación sin explotar sistemas',
            'entregables' => 'Checklist y microcurso de buenas prácticas',
            'indicadores' => 'Aplicabilidad y seguridad',
            'duracion'    => '10-20 horas',
        ],
        'Copias de seguridad' => [
            'familias'    => ['Informática y Comunicaciones'],
            'reto'        => 'Plan de copias de seguridad',
            'actividades' => 'Definir criticidad, copias y simulacro con datos de prueba',
            'entregables' => 'Plan de backup y registro de prueba',
            'indicadores' => 'Recuperabilidad documentada',
            'duracion'    => '10-20 horas',
        ],
        'Datos' => [
            'familias'    => ['Informática y Comunicaciones'],
            'reto'        => 'Depuración de base de datos sintética',
            'actividades' => 'Detectar duplicados, normalizar campos y documentar reglas',
            'entregables' => 'Dataset limpio y reglas de validación',
            'indicadores' => 'Duplicados y completitud',
            'duracion'    => '10-20 horas',
        ],
        'Web' => [
            'familias'    => ['Informática y Comunicaciones'],
            'reto'        => 'Prototipo web accesible',
            'actividades' => 'Revisar navegación, contraste y formularios en entorno de prueba',
            'entregables' => 'Prototipo y checklist WCAG orientativa',
            'indicadores' => 'Accesibilidad y pruebas',
            'duracion'    => '10-20 horas',
        ],
        'Automatización' => [
            'familias'    => ['Informática y Comunicaciones'],
            'reto'        => 'Automatización de flujo administrativo',
            'actividades' => 'Modelar flujo y construir automatización con datos ficticios',
            'entregables' => 'Diagrama, prototipo y pruebas',
            'indicadores' => 'Tiempo ahorrado y errores',
            'duracion'    => '10-20 horas',
        ],
        'Soporte' => [
            'familias'    => ['Informática y Comunicaciones'],
            'reto'        => 'Sistema básico de tickets',
            'actividades' => 'Clasificar incidencias simuladas y diseñar seguimiento',
            'entregables' => 'Tablero de tickets y SLA propuestos',
            'indicadores' => 'Trazabilidad y resolución',
            'duracion'    => '10-20 horas',
        ],
        'Integraciones' => [
            'familias'    => ['Informática y Comunicaciones'],
            'reto'        => 'Diseño de integración entre sistemas',
            'actividades' => 'Definir campos, eventos y API simulada',
            'entregables' => 'Especificación y prueba con mock API',
            'indicadores' => 'Consistencia y manejo de errores',
            'duracion'    => '10-20 horas',
        ],
        'Transformación digital básica' => [
            'familias'    => ['Comercio y Marketing', 'Administración y Gestión', 'Informática y Comunicaciones'],
            'reto'        => 'Auditoría 360 y hoja de ruta',
            'actividades' => 'Diagnosticar canales, procesos y herramientas; priorizar 6 mejoras',
            'entregables' => 'Informe 360, roadmap y matriz de responsables',
            'indicadores' => 'Utilidad, integración y documentación',
            'duracion'    => '18-30 horas',
        ],
        'Captación y gestión de clientes' => [
            'familias'    => ['Comercio y Marketing', 'Administración y Gestión', 'Informática y Comunicaciones'],
            'reto'        => 'Diseño de CRM y captación',
            'actividades' => 'Diseñar captación, registro, permisos y seguimiento en entorno de prueba',
            'entregables' => 'CRM ficticio, embudo y protocolo',
            'indicadores' => 'Utilidad, integración y documentación',
            'duracion'    => '18-30 horas',
        ],
        'Venta y atención' => [
            'familias'    => ['Comercio y Marketing', 'Administración y Gestión', 'Informática y Comunicaciones'],
            'reto'        => 'Prototipo de canal de venta digital',
            'actividades' => 'Definir catálogo, operaciones y prototipo web sin pagos reales',
            'entregables' => 'Catálogo, proceso operativo y prototipo',
            'indicadores' => 'Utilidad, integración y documentación',
            'duracion'    => '18-30 horas',
        ],
        'Medición' => [
            'familias'    => ['Comercio y Marketing', 'Administración y Gestión', 'Informática y Comunicaciones'],
            'reto'        => 'Dashboard de indicadores empresariales',
            'actividades' => 'Definir KPI de marketing, gestión y tecnología con dataset sintético',
            'entregables' => 'Dashboard y ficha de indicadores',
            'indicadores' => 'Utilidad, integración y documentación',
            'duracion'    => '18-30 horas',
        ],
        'Operativa' => [
            'familias'    => ['Comercio y Marketing', 'Administración y Gestión', 'Informática y Comunicaciones'],
            'reto'        => 'Digitalización del ciclo de pedidos',
            'actividades' => 'Modelar solicitud, confirmación, seguimiento y postventa',
            'entregables' => 'Flujo, plantillas y automatización demo',
            'indicadores' => 'Utilidad, integración y documentación',
            'duracion'    => '18-30 horas',
        ],
        'Sostenibilidad' => [
            'familias'    => ['Comercio y Marketing', 'Administración y Gestión', 'Informática y Comunicaciones'],
            'reto'        => 'Registro de mejoras sostenibles',
            'actividades' => 'Definir indicadores de consumo, documentación y comunicación responsable',
            'entregables' => 'Panel sintético y propuesta de comunicación',
            'indicadores' => 'Utilidad, integración y documentación',
            'duracion'    => '18-30 horas',
        ],
    ];

    // Las 4 preguntas del diagnóstico de la biblioteca, una por dimensión
    public const DIMENSIONES = [
        'procedimiento' => 'Procedimiento documentado',
        'herramientas'  => 'Herramientas digitales para gestionarla',
        'medicion'      => 'Medición periódica del rendimiento',
        'responsables'  => 'Responsables y formación',
    ];

    public const ESCALA = [0 => 'inexistente', 1 => 'inicial', 2 => 'parcial', 3 => 'implantado', 4 => 'medido'];

    // Qué tipo de entregable resuelve cada carencia
    public const ENTREGABLE_POR_DIMENSION = [
        'procedimiento' => 'procedimiento, manual o mapa del proceso',
        'herramientas'  => 'plantilla, registro o prototipo de herramienta sencilla',
        'medicion'      => 'indicadores y cuadro de mando',
        'responsables'  => 'reparto de responsabilidades (matriz RACI), guía de formación o acogida',
    ];

    // Ambición del reto según el nivel de madurez del área (adaptaciones de la biblioteca)
    public const AMBICION = [
        'Bajo'  => 'crear una primera versión sencilla, con plantillas y acompañamiento',
        'Medio' => 'estandarizar el proceso y medir resultados',
        'Alto'  => 'optimizar, integrar y probar mejoras con indicadores',
    ];

    // Límites de la biblioteca para cualquier reto (protección de datos y alcance)
    public const LIMITES = 'Trabaja solo con datos ficticios o anonimizados y evidencias no sensibles; los prototipos se prueban en entorno de prueba, sin despliegue en producción ni pagos reales; el reto no incluye decisiones jurídicas, fiscales ni contables, ni pruebas de ciberseguridad sobre sistemas reales.';

    /** Nivel según la suma de las 4 puntuaciones (0-16), como interpreta la biblioteca */
    public static function nivel(int $total): string
    {
        return $total <= 5 ? 'Bajo' : ($total <= 11 ? 'Medio' : 'Alto');
    }

    /**
     * Diagnóstico bien formado: área conocida y las 4 dimensiones con un entero de 0 a 4.
     *
     * @param  mixed  $diag
     */
    public static function esValido($diag): bool
    {
        if (!is_array($diag) || !array_key_exists($diag['area'] ?? null, self::AREAS)) return false;
        foreach (array_keys(self::DIMENSIONES) as $dim) {
            if (!is_int($diag[$dim] ?? null) || $diag[$dim] < 0 || $diag[$dim] > 4) return false;
        }
        return true;
    }

    /**
     * Áreas que trabaja una familia (incluidas las compartidas entre familias).
     *
     * @return array<string, array{familias: list<string>, reto: string, actividades: string, entregables: string, indicadores: string, duracion: string}>
     */
    public static function areasDeFamilia(string $familia): array
    {
        return array_filter(self::AREAS, fn ($a) => in_array($familia, $a['familias'], true));
    }

    /** @return list<string> familias que cubre la biblioteca */
    public static function familias(): array
    {
        return array_values(array_unique(array_merge(...array_column(self::AREAS, 'familias'))));
    }
}
