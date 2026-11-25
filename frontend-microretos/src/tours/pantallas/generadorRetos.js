// Generador de retos con IA (/retos/crear → GeneradorMicroretos.vue). Es un asistente de 3 pasos
// cuyo paso activo es estado interno (no va en la ruta): se describen los tres y el motor salta
// solos los bloques que no están en pantalla, así que el recorrido explica el paso en el que estés.
// Los resultados (variantes) solo salen tras pulsar «Procesar con IA».
export default {
  rutas: ['microretos'],
  titulo: 'Generador de retos',
  pasos: ({ auth }) => [
    { el: 'gen-cabecera', titulo: 'Generador de retos',
      texto: 'Aquí creas un reto a medida con IA a partir de la necesidad real de una empresa. Es opcional: si ya hay un reto en la biblioteca que te sirve, puedes trabajarlo directamente.' },
    { el: 'gen-solicitudes', titulo: 'Solicitudes del profesorado',
      texto: auth?.isSuperAdmin
        ? 'Lo que han pedido los docentes en el formulario de retos a medida. Elige una petición y el generador se rellena con ella; los retos que guardes van a la recámara.'
        : 'Lo que han pedido los docentes en el formulario. Elige una petición y el generador se rellena con ella.' },
    { el: 'gen-ayuda', titulo: 'Ayuda detallada',
      texto: 'Abre una guía más detallada del generador, campo a campo, con ejemplos de qué escribir.' },
    { el: 'gen-pasos', titulo: 'Tres pasos',
      texto: '1. Datos de la empresa, 2. su diagnóstico y 3. el match académico con tu ciclo. Pulsa un paso ya hecho para volver a él.' },

    // ── Paso 1 · Datos de la empresa ──
    { el: 'gen-centro', titulo: 'Tu centro',
      texto: 'El reto se crea para un centro educativo y solo verás sus empresas. Si tu cuenta pertenece a un centro, ya viene fijado.' },
    { el: 'gen-demo', titulo: 'Demo y Vaciar',
      texto: '«Cargar Demo» rellena el generador con un ejemplo ya preparado de una familia profesional, para ver cómo funciona. «Vaciar» borra todo y empiezas de cero.' },
    { el: 'gen-empresa', titulo: 'Elige la empresa',
      texto: 'Todo reto parte de una empresa y de su necesidad real. Puedes añadir una nueva o elegir una de las que ya tiene tu centro.' },
    { el: 'gen-nueva-empresa', titulo: 'Añadir una empresa',
      texto: '«Generar con IA» inventa una empresa ficticia completa y «Catálogo DUALAB» copia una de nuestras plantillas. Las dos quedan en tu centro como ficticias.' },
    { el: 'gen-empresas-centro', titulo: 'Empresas de tu centro',
      texto: 'Las reales (colaboradoras) y las ficticias que ya tiene tu centro. Filtra por tipo o familia, busca por nombre y pulsa la tarjeta para elegirla.' },

    // ── Paso 2 · Diagnóstico de la empresa ──
    { el: 'gen-diagnostico', titulo: 'Realidad de la empresa',
      texto: 'El diagnóstico: quién es la empresa, su día a día y sus dificultades. Es lo que la IA usará para plantear el problema del reto.' },
    { el: 'gen-diag-ia', titulo: 'Diagnóstico con IA',
      texto: 'Si la empresa no tiene diagnóstico, la IA puede simular la entrevista y rellenar las respuestas. Revísalas antes de seguir.' },
    { el: 'gen-p5', titulo: 'Qué espera del alumnado',
      texto: 'Es obligatoria: qué espera la empresa del alumnado. Orienta el reto hacia algo que de verdad le sea útil.' },
    { el: 'gen-siguiente', titulo: 'Siguiente paso',
      texto: 'Se activa cuando el paso está completo. Al avanzar, el paso anterior se bloquea: para cambiarlo después tendrás que pulsar «Vaciar».' },

    // ── Paso 3 · Match académico ──
    { el: 'gen-familia', titulo: 'Familia profesional',
      texto: 'La familia para la que creas el reto. Debajo ves qué busca la empresa en el alumnado de esa familia.' },
    { el: 'gen-nivel', titulo: 'Exigencia y enfoque',
      texto: 'Elige el nivel de exigencia y, si quieres, qué tipo de reto buscas o qué tareas harán en prácticas: la IA lo tendrá en cuenta.' },
    { el: 'gen-ciclo', titulo: 'Ciclo y curso',
      texto: 'Elige el ciclo formativo y, justo debajo, el curso del alumnado (1º, 2º o ambos).' },
    { el: 'gen-modulos', titulo: 'Módulos, RA y CE',
      texto: 'Con «Elige la IA» (recomendado), la IA escoge los módulos y los RA y CE que mejor encajan. Pasa a «Selección manual» si quieres fijar tú algún módulo o sus RA y CE.' },
    { el: 'gen-cantidad', titulo: 'Cuántas variantes',
      texto: 'Cuántas versiones distintas del reto quieres que genere la IA para elegir la que más te guste.' },
    { el: 'gen-procesar', titulo: 'Procesar con IA',
      texto: 'Genera las variantes. Tarda un poco: verás el progreso en el botón y los avisos a la derecha.' },
    { el: 'gen-galeria', titulo: 'Variantes generadas',
      texto: 'Aquí aparecen los retos generados. Puedes guardarlos uno a uno o todos a la vez; los guardados se añaden a la biblioteca de tu centro.' },
    { el: 'gen-variante', titulo: 'Cada variante',
      texto: 'Pulsa «Ver ficha» para leer el reto completo antes de decidir. «Guardar» lo lleva a la biblioteca.' },
    { el: 'gen-trabajar', titulo: 'Trabajar este reto',
      texto: 'Guarda el reto y abre el Ajuste del reto, donde lo adaptas a tu curso, módulos, RA y CE y objetivos para tener tu reto ajustado.' },

    { el: 'gen-avisos', titulo: 'Avisos',
      texto: 'Aquí se van guardando los avisos del generador (lo que se ha guardado, errores…) para que puedas releerlos.' },
  ],
}
