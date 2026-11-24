// Navegación principal del docente: entradas del SidePanel y contenido de las
// secciones "hub" (/seccion/:seccion), que agrupan en cards las herramientas de
// cada área. El panel lateral solo lleva a la sección; dentro se elige la herramienta.
//
// Las rutas reales (/retos, /proyectos, /encuentros…) no cambian: los hubs son una
// capa de entrada encima, así que enlaces y marcadores antiguos siguen funcionando.

// Trazos SVG (viewBox 24×24, stroke) — mismos que usaba el panel para cada herramienta
export const ICONOS_NAV = {
  inicio:       'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
  rayo:         'M13 10V3L4 14h7v7l9-11h-7z',
  libro:        'M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 22v-15A2.5 2.5 0 016.5 2zM9 7h6M9 11h6',
  masCirculo:   'M22 12a10 10 0 11-20 0 10 10 0 0120 0zM12 8v8M8 12h8',
  capas:        'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
  portapapeles: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M10 3h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V4a1 1 0 011-1zM9 12l2 2 4-4',
  grafica:      'M3 3v18h18M7 15l4-6 4 4 5-8',
  alumnado:     'M22 10L12 5 2 10l10 5 10-5zM6 12v5c3 2 9 2 12 0v-5',
  candado:      'M5 11h14a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7a2 2 0 012-2zM7 11V7a5 5 0 0110 0v4',
  entrar:       'M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3',
  documento:    'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z',
  empresa:      'M3 21h18M5 21V7l7-4 7 4v14M9 9h1M14 9h1M9 13h1M14 13h1M9 17h1M14 17h1',
  evaluacion:   'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11',
  diagnostico:  'M22 12h-4l-3 9L9 3l-3 9H2',
  trofeo:       'M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3',
  calendario:   'M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zM16 2v4M8 2v4M3 10h18',
  campana:      'M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0',
  recursos:     'M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2zM22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z',
  info:         'M12 22a10 10 0 100-20 10 10 0 000 20zM12 16v-4M12 8h.01',
  glosario:     'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5',
  codigo:       'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4',
  ojo:          'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8zM12 15a3 3 0 100-6 3 3 0 000 6z',
}

// ── Entradas del SidePanel (docente, admin y superadmin) ──────────────────────
// `ruta`: destino al pulsar · `routeName`: nombre de ruta para canAccess()
// `activa(path)`: qué rutas marcan la entrada como activa (incluye las herramientas
//   que cuelgan de su hub, para que el panel indique dónde estás)
// `color`: variante de nav-item--active-* (colores de sección de la marca)
// `proximamente`: se muestra deshabilitada hasta que exista la pantalla
const esPantallaAcceso = (p) => p === '/pantalla-acceso' || /^\/proyectos\/[^/]+\/pantalla-acceso$/.test(p)

export const GRUPOS_NAV = [
  {
    titulo: null,
    items: [
      { key: 'inicio', label: 'Inicio', icon: 'inicio', ruta: '/panel-docente', routeName: 'inicio-docente', color: 'docente',
        tip: 'Panel de inicio para docentes',
        activa: (p) => p.startsWith('/panel-docente') },
    ],
  },
  {
    // Orden del recorrido: taller de ideas (retos y retos ajustados, encuentros) │ plan de proyecto │ alumnado y
    // empresas. Sin títulos de etapa (decisión de Cynthia 2026-11-17): los bloques se separan con una línea
    // discreta (`separadorAntes`); la explicación de las etapas va en Recursos → ¿Qué es DUALAB?.
    titulo: 'Gestión académica',
    items: [
      { key: 'retos-proyectos', label: 'Retos', icon: 'capas', ruta: '/seccion/retos-proyectos', routeName: 'seccion', color: 'docente',
        tip: 'Genera retos, trabájalos con tu alumnado y consulta tus retos ajustados',
        activa: (p) => p === '/seccion/retos-proyectos' || p.startsWith('/retos')
          || (p.startsWith('/proyectos') && !esPantallaAcceso(p)) },
      // Antes había dos entradas, «Encuentros» y «Mis grupos»: el seguimiento de los grupos es
      // ahora una pestaña de la biblioteca de encuentros (/encuentros?ver=seguimiento).
      // /mis-grupos/:id es el Detalle de grupos de un encuentro: también es de esta sección.
      { key: 'encuentros-grupos', label: 'Encuentros y grupos', icon: 'portapapeles', ruta: '/seccion/encuentros-grupos', routeName: 'seccion', color: 'docente',
        tip: 'Crea encuentros con el alumnado, gestiónalos, sigue el avance de sus grupos y evalúalos',
        // /evaluacion/diagnosticos (Evaluación curricular y diagnósticos) es de esta sección: es el paso
        // siguiente al seguimiento de grupos. En Alumnado queda una tarjeta de atajo.
        activa: (p) => p === '/seccion/encuentros-grupos' || p.startsWith('/encuentros') || p.startsWith('/mis-grupos') || p.startsWith('/evaluacion') },
      { key: 'planes-proyecto', separadorAntes: true, label: 'Planes de proyecto', icon: 'trofeo', ruta: '/seccion/planes-proyecto', routeName: 'seccion', color: 'docente',
        tip: 'Planes que el alumnado seleccionado desarrolla en la empresa durante las prácticas, y sus cronogramas',
        activa: (p) => p === '/seccion/planes-proyecto' || (p.startsWith('/planes') && !p.startsWith('/planes/agenda')) },
      { key: 'alumnado', separadorAntes: true, label: 'Alumnado', icon: 'alumnado', ruta: '/seccion/alumnado', routeName: 'seccion', color: 'alumnos',
        tip: 'Listado del alumnado y acceso al encuentro',
        activa: (p) => p === '/seccion/alumnado' || p.startsWith('/alumnado') || esPantallaAcceso(p) || p.startsWith('/unirse') || p.startsWith('/workspace-proyecto') },
      { key: 'empresas', label: 'Empresas', icon: 'empresa', ruta: '/seccion/empresas', routeName: 'seccion', color: 'empresas',
        tip: 'Directorio de empresas colaboradoras',
        activa: (p) => p === '/seccion/empresas' || p.startsWith('/empresas') },
    ],
  },
  {
    titulo: 'Organización',
    items: [
      { key: 'calendario', label: 'Calendario', icon: 'calendario', ruta: '/calendario', routeName: 'calendario', color: 'docente',
        tip: 'Tu día a día: encuentros, hitos de los planes, notas y tareas',
        activa: (p) => p.startsWith('/calendario') },
      // Acceso diario a la agenda (también está en el hub Planes de proyecto): se usa cada día durante
      // las prácticas, como el calendario. Calendario = qué tengo cada día; agenda = cómo avanza el alumnado.
      { key: 'agenda-practicas', label: 'Agenda de prácticas', icon: 'grafica', ruta: '/planes/agenda', routeName: 'agenda-practicas', color: 'docente',
        tip: 'Cómo avanza el alumnado en las empresas: fase de cada grupo, retrasos según los hitos y horas anotadas',
        activa: (p) => p.startsWith('/planes/agenda') },
      { key: 'notificaciones', label: 'Notificaciones', icon: 'campana', ruta: '/notificaciones', routeName: 'notificaciones', color: 'docente',
        badge: 'notificaciones',
        tip: 'Avisos de empresas, grupos e invitaciones a encuentros',
        activa: (p) => p.startsWith('/notificaciones') },
    ],
  },
  {
    titulo: 'Ayuda',
    items: [
      { key: 'recursos', label: 'Recursos', icon: 'recursos', ruta: '/seccion/recursos', routeName: 'seccion', color: 'docente',
        tip: 'Cómo funciona DUALAB, glosario y guías',
        activa: (p) => p === '/seccion/recursos' },
    ],
  },
]

// ── Secciones hub ─────────────────────────────────────────────────────────────
// Cada card: `ruta` (navega) o `accion` (abre un modal: 'comoFunciona' | 'creditos').
// `routeName` filtra por rol con canAccess(); sin él, la card se muestra a todos los
// roles que pueden entrar en la sección. `tile`: color de marca del icono.
// `ancho`: acción destacada a toda la fila — la card entera es el botón (tintada con el
// color del tile y una flecha a la derecha), sin CTA aparte que parezca otra acción.
// `integrada`: mismo estilo que `ancho` pero ocupando solo su columna.
//
// `bloqueSecundario` (opcional): card con texto a la izquierda y botones pequeños a la
// derecha. `separador`: texto entre botones cuando son alternativas ("o");
// `destacarPrimera`: la primera opción va resaltada (vía directa); `icon`: junto al título.
export const SECCIONES = {
  // «Retos» (antes «Retos y propuestas», 2026-11-23). La propuesta pasa a ser el «ajuste del
  // reto»: el docente hace suyo un reto (curso, currículo, objetivos) y lo valida.
  'retos-proyectos': {
    titulo: 'Tus', destacado: 'retos', color: 'text-centros',
    subtitulo: 'Genera o elige un reto, ajústalo a tu programación y llévalo al aula.',
    // Qué es ajustar un reto (mismo papel que los `conceptos` de Encuentros y Empresas)
    conceptos: [
      { color: 'empresas', segmentos: [
        { t: 'Al ' }, { t: 'AJUSTAR UN RETO', b: true },
        { t: ' lo adaptas curricularmente a tu clase: curso, módulos, RA y CE, metodología y objetivos. Cuando lo validas, queda listo para llevarlo al aula. En ' },
        { t: 'Mis retos ajustados', ruta: '/proyectos', routeName: 'startup-day', tip: 'Ir a mis retos ajustados' },
        { t: ' tienes tu biblioteca de retos ajustados curricularmente: los que preparas, los validados y los que tus grupos ya han completado.' },
      ] },
    ],
    cards: [
      { titulo: 'Trabajar un reto', desc: 'Elige un reto y ajústalo a tu programación: curso, módulos, RA y CE y objetivos.',
        integrada: true,
        ruta: '/proyectos/crear', routeName: 'startup-day-crear', icon: 'masCirculo', tile: 'bg-centros' },
      { titulo: 'Mis retos ajustados', desc: 'Tu biblioteca de retos ajustados curricularmente: en edición, sin validar, validados y completados.',
        integrada: true,
        ruta: '/proyectos', routeName: 'startup-day', icon: 'capas', tile: 'bg-empresas' },
    ],
    // Bloque secundario: de dónde sale el reto. El generador va primero y destacado; la
    // biblioteca, en pequeño (`compacta`). Desde los dos se puede pulsar «Trabajar este reto».
    bloqueSecundario: {
      separador: 'o', destacarPrimera: true,
      titulo: '¿De qué reto partes?',
      texto: 'Todo empieza en un reto: la necesidad real de una empresa convertida en una pregunta para tu alumnado. Genera uno a medida o elige uno de la biblioteca; desde los dos puedes pulsar «Trabajar este reto». También puedes elegirlo directamente en «Trabajar un reto».',
      cards: [
        { titulo: 'Generar reto', etiqueta: 'Para personalizar',
          desc: 'Crea un reto a medida con IA: eliges la empresa, los módulos y los RA y CE.',
          ruta: '/retos/crear', routeName: 'microretos', icon: 'rayo', tile: 'bg-centros' },
        { titulo: 'Biblioteca de retos', etiqueta: 'Consultar', compacta: true,
          desc: 'Los retos de tu centro: consúltalos y elige el que quieras trabajar.',
          ruta: '/retos', routeName: 'biblioteca', icon: 'libro', tile: 'bg-azul-noche' },
      ],
    },
    // Flujo explicativo (no navega): del reto al reto ajustado y validado, agrupado por fases.
    // Se dibuja como diagrama (línea con pasos numerados), no como cards clicables.
    // `conector`: texto sobre la línea que sale del paso hacia el siguiente; el último
    // paso de una fase usa el `conector` de la fase (paso a la fase siguiente).
    // `concepto` (opcional): qué es lo que se trabaja en la fase, mismos textos que
    // ComoFuncionaModal.vue. El nombre de la fase se destaca y, al pulsarlo, abre un toast
    // con la definición (ConceptoClave); `pregunta` es el texto del tooltip al pasar el cursor.
    flujo: {
      titulo: 'Mira cómo funciona',
      fases: [
        { nombre: 'Retos', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15', conector: 'Trabájalo',
          concepto: { color: 'centros', pregunta: '¿Qué es un reto?', segmentos: [
            { t: 'Un ' }, { t: 'RETO', b: true }, { t: ' es la necesidad real de una empresa, transformada (con ayuda de la IA) en una ' },
            { t: 'pregunta', b: true }, { t: ' que el alumnado deberá responder.' },
            { br: true },
            { t: 'Puedes elegir un reto en la ' },
            { t: 'biblioteca de retos', ruta: '/retos', routeName: 'biblioteca', tip: 'Ir a la biblioteca de retos' },
            { t: ' o ' },
            { t: 'generar uno nuevo', ruta: '/retos/crear', routeName: 'microretos', tip: 'Ir a generar reto' },
            { t: '.' },
          ] },
          pasos: [
            { titulo: 'Genera un reto a medida (opcional)', conector: '¿Te gusta?',
              desc: 'Solo si quieres personalizarlo: la IA convierte la necesidad de una empresa en un reto con los criterios del ciclo.' },
            { titulo: 'Elige el reto',
              desc: 'En la biblioteca o en el generador, pulsa «Trabajar este reto».' },
          ] },
        { nombre: 'Ajuste del reto', texto: 'text-empresas-dark', borde: 'border-empresas', halo: 'ring-empresas/20', conector: null,
          concepto: { color: 'centros', pregunta: '¿Qué es ajustar un reto?', segmentos: [
            { t: 'Al ' }, { t: 'AJUSTAR UN RETO', b: true },
            { t: ' lo haces tuyo: eliges el curso, los módulos, los RA y CE y los objetivos con los que tu alumnado lo trabajará. La empresa y el diseño del reto se copian solos.' },
            { br: true },
            { t: 'Cuando lo validas, lo llevas al aula en un ' }, { t: 'encuentro creativo', b: true },
            { t: ', donde cada grupo trabaja su ' }, { t: 'solución', b: true },
            { t: ' por fases. Al final, la empresa valora las soluciones.' },
            { br: true },
            { t: 'Consulta tus ' },
            { t: 'retos ajustados', ruta: '/proyectos', routeName: 'startup-day', tip: 'Ir a mis retos ajustados' },
            { t: ' o ' },
            { t: 'trabaja un reto', ruta: '/proyectos/crear', routeName: 'startup-day-crear', tip: 'Ir a trabajar un reto' },
            { t: '.' },
          ] },
          pasos: [
            { titulo: 'Ajústalo', conector: 'Revísalo',
              desc: 'Curso, módulos, RA y CE, metodología y objetivos, con ayuda de la IA.' },
            { titulo: 'Valídalo y llévalo al aula',
              desc: 'Al validarlo confirmas que lo quieres trabajar: ya puedes crear el encuentro.' },
          ] },
      ],
    },
  },
  // Etapa 2: plan de proyecto en la empresa (P4)
  'planes-proyecto': {
    titulo: 'Plan de', destacado: 'proyecto', color: 'text-centros',
    subtitulo: 'El trabajo ampliado que el alumnado seleccionado desarrolla en la empresa durante sus prácticas.',
    conceptos: [
      { color: 'empresas', segmentos: [
        { t: 'Un ' }, { t: 'PLAN DE PROYECTO', b: true },
        { t: ' amplía un reto ajustado del taller (u otro reto) para trabajarlo en la empresa durante las prácticas, con su ' },
        { t: 'cronograma', b: true }, { t: ' de hitos. Su alumnado es el que DuaLab selecciona entre los más destacados de los encuentros creativos.' },
        { br: true },
        { t: 'Consulta tus planes en la ' },
        { t: 'biblioteca de planes', ruta: '/planes', routeName: 'planes', tip: 'Ir a la biblioteca de planes' },
        { t: ' o ' },
        { t: 'crea uno nuevo', ruta: '/planes/crear', routeName: 'planes-crear', tip: 'Ir a crear plan' },
        { t: '.' },
      ] },
    ],
    cards: [
      { titulo: 'Crear plan', desc: 'Amplía un reto ajustado del taller o parte de otro reto.',
        ruta: '/planes/crear', routeName: 'planes-crear', icon: 'masCirculo', tile: 'bg-centros', integrada: true },
      { titulo: 'Biblioteca de planes', desc: 'Tus planes y sus cronogramas: grupos, códigos y seguimiento.',
        ruta: '/planes', routeName: 'planes', icon: 'libro', tile: 'bg-centros', integrada: true },
      { titulo: 'Agenda de prácticas', desc: 'Cómo avanza en la empresa el alumnado de tus planes: hitos, fases y retrasos.',
        ruta: '/planes/agenda', routeName: 'agenda-practicas', icon: 'calendario', tile: 'bg-centros', integrada: true },
    ],
    flujo: {
      titulo: 'Mira cómo funciona',
      fases: [
        { nombre: 'Selección', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15', conector: 'Crea el plan',
          pasos: [
            { titulo: 'DuaLab elige al alumnado', desc: 'Entre los más destacados de los encuentros creativos, con la valoración de la empresa.' },
          ] },
        { nombre: 'Plan', texto: 'text-empresas-dark', borde: 'border-empresas', halo: 'ring-empresas/20', conector: 'Organiza las prácticas',
          pasos: [
            { titulo: 'Crea y valida el plan', conector: 'Validado',
              desc: 'Amplía un reto ajustado o parte de otro reto, y valídalo.' },
            { titulo: 'Crea el cronograma', desc: 'Fechas, hitos y alumnado del plan, de uno o varios encuentros.' },
          ] },
        { nombre: 'En la empresa', texto: 'text-alumnos-dark', borde: 'border-alumnos', halo: 'ring-alumnos/15',
          pasos: [
            { titulo: 'Trabajan por fases', desc: 'Mismo workspace por fases, durante las prácticas, y su seguimiento.' },
          ] },
      ],
    },
  },
  // Antes /seccion/encuentros (redirige aquí, ver router/index.js)
  'encuentros-grupos': {
    // Azul como su entrada del menú lateral (decisión 2026-10-21); el naranja queda para
    // lo que es del alumnado (fase «En el aula» del diagrama).
    titulo: 'Encuentros y', destacado: 'grupos', color: 'text-centros',
    subtitulo: 'Organiza las sesiones de trabajo con tu alumnado, gestiona las que ya has creado y sigue el avance de sus grupos.',
    // Qué es un encuentro — "el cuándo" (mismo texto que ComoFuncionaModal.vue)
    conceptos: [
      { color: 'centros', segmentos: [
        { t: 'Un ' }, { t: 'ENCUENTRO', b: true }, { t: ' es el ' }, { t: 'cuándo', b: true },
        { t: ': la fecha y los grupos con los que ese reto ajustado se trabaja en el aula. A partir de ahí, cada grupo avanza por fases en su ' },
        { t: 'workspace', b: true }, { t: '.' },
        { br: true },
        { t: 'Gestiona los encuentros que ya has creado en la ' },
        { t: 'biblioteca de encuentros', ruta: '/encuentros', routeName: 'encuentros-registrados', tip: 'Ir a la biblioteca de encuentros' },
        { t: ', sigue cómo avanza cada grupo en el ' },
        { t: 'seguimiento de grupos', ruta: '/encuentros?ver=seguimiento', routeName: 'encuentros-registrados', tip: 'Ir al seguimiento de grupos' },
        { t: ' o ' },
        { t: 'crea un nuevo encuentro', ruta: '/encuentros/crear', routeName: 'dashboard-docente', tip: 'Ir a crear encuentro' },
        { t: '.' },
      ] },
    ],
    cards: [
      { titulo: 'Crear encuentro', desc: 'Organiza un encuentro de trabajo con retos o proyectos.',
        ruta: '/encuentros/crear', routeName: 'dashboard-docente', icon: 'portapapeles', tile: 'bg-centros', integrada: true },
      { titulo: 'Biblioteca de encuentros', desc: 'Consulta y gestiona tus encuentros: fechas, códigos del alumnado y grupos.',
        ruta: '/encuentros', routeName: 'encuentros-registrados', icon: 'libro', tile: 'bg-centros', integrada: true },
    ],
    // Aparte, como el bloque secundario de Retos: el seguimiento es otro momento (con los grupos ya trabajando)
    bloqueSecundario: {
      titulo: 'Cuando tus grupos ya trabajan',
      texto: 'Sigue cómo avanza cada grupo de tus encuentros, fase a fase, y evalúa lo que han aprendido.',
      cards: [
        { titulo: 'Seguimiento de grupos', desc: 'El avance de cada grupo, fase a fase.',
          ruta: '/encuentros?ver=seguimiento', routeName: 'encuentros-registrados', icon: 'grafica', tile: 'bg-alumnos' },
        { titulo: 'Evaluación curricular y diagnósticos', desc: 'Evalúa los RA y CE de cada grupo y genera o consulta su diagnóstico final.',
          ruta: '/evaluacion/diagnosticos', routeName: 'biblioteca-diagnosticos', icon: 'diagnostico', tile: 'bg-administraciones' },
      ],
    },
    flujo: {
      titulo: 'Mira cómo funciona',
      fases: [
        { nombre: 'Preparar', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15', conector: 'Genera el código',
          pasos: [
            { titulo: 'Crea el encuentro', conector: 'Guárdalo',
              desc: 'Asocia un reto ajustado y validado, pon la fecha y reparte al alumnado en grupos.' },
            { titulo: 'Consúltalo en la biblioteca',
              desc: 'Desde la biblioteca de encuentros generas el código del alumnado y lo compartes con otros docentes.' },
          ] },
        { nombre: 'En el aula', texto: 'text-alumnos-dark', borde: 'border-alumnos', halo: 'ring-alumnos/15',
          pasos: [
            { titulo: 'Da acceso al alumnado', conector: 'Trabajan por fases',
              desc: 'Proyecta la pantalla de acceso: cada grupo entra en su workspace con su QR.' },
            { titulo: 'Sigue a tus grupos', conector: 'Cuando entreguen',
              desc: 'En Seguimiento de grupos ves el avance de cada grupo, fase a fase.' },
            { titulo: 'Pide la valoración',
              desc: 'Cuando tus grupos entregan, DuaLab envía a la empresa un enlace para que valore el reto con sus soluciones.' },
          ] },
      ],
    },
  },
  alumnado: {
    titulo: 'Acceso del', destacado: 'alumnado', color: 'text-alumnos',
    subtitulo: 'El alumnado entra con el QR o el código del encuentro, elige su grupo y trabaja en su espacio.',
    // Bloque «Dar acceso al encuentro» (AccesoRapidoEncuentros) bajo las tarjetas: los encuentros en
    // curso hoy y, dentro, «Dar acceso a otro encuentro» (/pantalla-acceso)
    accesoRapido: true,
    cards: [
      { titulo: 'Listado de alumnado', desc: 'Cada alumno con sus retos, encuentros, grupo, fase y nota final.',
        ruta: '/alumnado/listado', routeName: 'alumnado-listado', icon: 'alumnado', tile: 'bg-alumnos' },
      { titulo: 'Evaluación curricular y diagnósticos', desc: 'Evalúa los RA y CE de cada grupo y genera o consulta su diagnóstico final.',
        ruta: '/evaluacion/diagnosticos', routeName: 'biblioteca-diagnosticos', icon: 'diagnostico', tile: 'bg-administraciones' },
    ],
    // Pantallas del propio alumnado: el docente solo las abre para ver qué ve su alumnado
    bloqueSecundario: {
      icon: 'ojo',
      titulo: 'Cómo lo ve el alumnado',
      texto: 'Estas son las pantallas que usa tu alumnado desde su dispositivo. Ábrelas solo para ver lo que ven ellos: no necesitas usarlas como docente.',
      cards: [
        { titulo: 'Unirse a un grupo', etiqueta: 'Primera vez',
          desc: 'El alumno elige su clase y su grupo (en su pantalla aparece como «equipo»).',
          ruta: '/unirse', icon: 'entrar', tile: 'bg-azul-noche' },
        { titulo: 'Retomar workspace', etiqueta: 'Otro día',
          desc: 'El grupo introduce su código para volver a su flujo de trabajo.',
          ruta: '/workspace-proyecto', icon: 'documento', tile: 'bg-azul-noche' },
      ],
    },
    flujo: {
      titulo: 'Mira cómo entra el alumnado',
      fases: [
        { nombre: 'Docente', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15', conector: 'Escanean el QR',
          pasos: [
            { titulo: 'Proyecta el acceso',
              desc: 'Abre la pantalla de acceso del encuentro: un QR y un código por grupo.' },
          ] },
        { nombre: 'Alumnado', texto: 'text-alumnos-dark', borde: 'border-alumnos', halo: 'ring-alumnos/15',
          pasos: [
            { titulo: 'Se une a su grupo', conector: 'Entra al workspace',
              desc: 'La primera vez escanea el QR, o escribe el código en /unirse, y elige su grupo.' },
            { titulo: 'Trabaja por fases', conector: 'Otro día',
              desc: 'Avanza por fases desde F0 Inicio del equipo hasta F4 Presentación.' },
            { titulo: 'Retoma su workspace',
              desc: 'Vuelve con el código de su grupo, sin necesidad de cuenta.' },
          ] },
      ],
    },
  },
  empresas: {
    titulo: 'Empresas', destacado: 'colaboradoras', color: 'text-empresas',
    subtitulo: 'Consulta y contacta con las empresas de tu centro; al final de cada reto, valoran las soluciones de tus grupos.',
    // Qué papel tiene la empresa (mismo rol que describe ComoFuncionaModal.vue)
    conceptos: [
      { color: 'empresas', segmentos: [
        { t: 'Una ' }, { t: 'EMPRESA COLABORADORA', b: true },
        { t: ' aporta la necesidad real de la que nace el reto y, al terminar, ' }, { t: 'valora', b: true },
        { t: ' el reto con las soluciones de tus grupos y dice si le interesaría seguir con un proyecto en la empresa. No necesita cuenta: responde desde el enlace que le envía DuaLab.' },
        { br: true },
        { t: 'Consulta sus datos de contacto en el ' },
        { t: 'directorio de empresas', ruta: '/empresas', routeName: 'empresas', tip: 'Ir al directorio de empresas' },
        { t: '.' },
      ] },
    ],
    cards: [
      { titulo: 'Directorio de empresas', desc: 'Datos y contacto de las empresas (requiere contraseña especial).',
        cta: 'Abrir directorio', principal: true,
        ruta: '/empresas', routeName: 'empresas', icon: 'candado', tile: 'bg-empresas' },
    ],
    // Valoración al final (ValoracionEmpresaController, P3): la empresa ya no valida al inicio.
    flujo: {
      titulo: 'Mira cómo funciona',
      fases: [
        { nombre: 'Docente', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15', conector: 'Le llega un enlace',
          pasos: [
            { titulo: 'Lleva el reto al aula', conector: 'Entregan',
              desc: 'Lo validas tú y lo trabajas con tus grupos en un encuentro creativo.' },
            { titulo: 'DuaLab envía las soluciones',
              desc: 'Cuando los grupos han entregado, DuaLab le hace llegar a la empresa el enlace para valorarlas.' },
          ] },
        { nombre: 'Empresa', texto: 'text-empresas-dark', borde: 'border-empresas', halo: 'ring-empresas/20', conector: 'Si le interesa',
          pasos: [
            { titulo: 'Valora el reto y las soluciones',
              desc: 'Sin cuenta, desde su enlace, e indica si le interesaría seguir con un proyecto en la empresa.' },
          ] },
        { nombre: 'Proyecto', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15',
          pasos: [
            { titulo: 'DuaLab elige al alumnado',
              desc: 'Entre el más destacado, para el plan de proyecto durante las prácticas.' },
          ] },
      ],
    },
  },
  recursos: {
    titulo: 'Recursos', destacado: '', color: 'text-centros',
    subtitulo: 'Todo lo que necesitas para entender y sacar partido a DUALAB.',
    cards: [
      { titulo: '¿Qué es DUALAB?', desc: 'Cómo funciona la plataforma, paso a paso.',
        accion: 'comoFunciona', icon: 'info', tile: 'bg-centros' },
      { titulo: 'Glosario', desc: 'Retos, retos ajustados, encuentros, RA, CE… explicados.',
        proximamente: true, icon: 'glosario', tile: 'bg-centros' },
      { titulo: 'Guías y plantillas', desc: 'Material de apoyo para preparar tus encuentros.',
        proximamente: true, icon: 'libro', tile: 'bg-empresas' },
      { titulo: 'Acerca de', desc: 'El equipo que ha desarrollado DUALAB Studio Tool.',
        accion: 'creditos', icon: 'codigo', tile: 'bg-azul-noche' },
    ],
    // Las dos etapas de DUALAB (ver CLAUDE.md «Etapas»): lo usan este hub y el modal «¿Qué es DUALAB?»
    flujo: {
      titulo: 'El recorrido completo',
      subtitulo: 'Dos etapas: el taller de ideas en el aula y el plan de proyecto en las prácticas en la empresa.',
      fases: [
        { nombre: '1 · Taller de ideas', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15', conector: 'Al terminar',
          pasos: [
            { titulo: 'Elige o genera el reto', conector: 'Ajústalo',
              desc: 'La necesidad real de una empresa, convertida en reto con IA.' },
            { titulo: 'Ajusta y valida el reto', conector: 'Al aula',
              desc: 'Su ajuste curricular a tu clase; lo validas cuando lo quieres trabajar.' },
            { titulo: 'Encuentro creativo', conector: 'Entregan',
              desc: 'En el aula, cada grupo trabaja su solución por fases en su workspace.' },
            { titulo: 'La empresa valora', desc: 'Valora el reto con las soluciones de los grupos y su interés en un proyecto.' },
          ] },
        { nombre: 'Selección', texto: 'text-administraciones-dark', borde: 'border-administraciones', halo: 'ring-administraciones/15', conector: 'A la empresa',
          pasos: [
            { titulo: 'DuaLab elige al alumnado', desc: 'Los más destacados pasan a las prácticas en la empresa.' },
          ] },
        { nombre: '2 · Plan de proyecto', texto: 'text-empresas-dark', borde: 'border-empresas', halo: 'ring-empresas/20',
          pasos: [
            { titulo: 'Crea el plan y su cronograma', conector: 'En prácticas',
              desc: 'Amplía el reto ajustado (u otro reto), con hitos fechados y su alumnado.' },
            { titulo: 'Seguimiento en la agenda', desc: 'Avance por fases, retrasos y horas, del docente y de DuaLab.' },
          ] },
      ],
    },
  },
}
