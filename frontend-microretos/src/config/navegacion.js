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
    titulo: 'Gestión académica',
    items: [
      { key: 'retos-proyectos', label: 'Retos y proyectos', icon: 'capas', ruta: '/seccion/retos-proyectos', routeName: 'seccion', color: 'docente',
        tip: 'Genera retos y proyectos, y consulta sus bibliotecas',
        activa: (p) => p === '/seccion/retos-proyectos' || p.startsWith('/retos')
          || (p.startsWith('/proyectos') && !p.startsWith('/proyectos/terminados') && !esPantallaAcceso(p)) },
      { key: 'encuentros', label: 'Encuentros', icon: 'portapapeles', ruta: '/seccion/encuentros', routeName: 'seccion', color: 'alumnos',
        tip: 'Crea encuentros con el alumnado y consulta los registrados',
        activa: (p) => p === '/seccion/encuentros' || p.startsWith('/encuentros') },
      { key: 'mis-grupos', label: 'Mis grupos', icon: 'grafica', ruta: '/mis-grupos', routeName: 'mis-grupos', color: 'docente',
        tip: 'Seguimiento del avance de todos tus grupos de alumnado',
        activa: (p) => p.startsWith('/mis-grupos') },
      { key: 'evaluacion', label: 'Evaluación', icon: 'evaluacion', ruta: '/seccion/evaluacion', routeName: 'seccion', color: 'docente',
        tip: 'Proyectos terminados, diagnósticos y validación docente',
        activa: (p) => p === '/seccion/evaluacion' || p.startsWith('/proyectos/terminados') || p.startsWith('/evaluacion') },
      { key: 'alumnado', label: 'Alumnado', icon: 'alumnado', ruta: '/seccion/alumnado', routeName: 'seccion', color: 'alumnos',
        tip: 'Da acceso al alumnado, consulta su listado y accede a su espacio de trabajo',
        activa: (p) => p === '/seccion/alumnado' || p.startsWith('/alumnado') || esPantallaAcceso(p) || p.startsWith('/unirse') || p.startsWith('/workspace-proyecto') },
      { key: 'empresas', label: 'Empresas', icon: 'empresa', ruta: '/seccion/empresas', routeName: 'seccion', color: 'empresas',
        tip: 'Directorio de empresas y envío de propuestas',
        activa: (p) => p === '/seccion/empresas' || p.startsWith('/empresas') },
    ],
  },
  {
    titulo: 'Organización',
    items: [
      { key: 'calendario', label: 'Calendario', icon: 'calendario', ruta: '/calendario', routeName: 'calendario', color: 'docente',
        tip: 'Calendario de encuentros, tareas pendientes y notas',
        activa: (p) => p.startsWith('/calendario') },
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
        tip: 'Cómo funciona DuaLab, glosario y guías',
        activa: (p) => p === '/seccion/recursos' },
    ],
  },
]

// ── Secciones hub ─────────────────────────────────────────────────────────────
// Cada card: `ruta` (navega) o `accion` (abre un modal: 'comoFunciona' | 'creditos').
// `routeName` filtra por rol con canAccess(); sin él, la card se muestra a todos los
// roles que pueden entrar en la sección. `tile`: color de marca del icono.
export const SECCIONES = {
  'retos-proyectos': {
    titulo: 'Retos y', destacado: 'proyectos', color: 'text-centros',
    subtitulo: 'Crea proyectos para tu alumnado y gestiona los que ya tienes en marcha.',
    cards: [
      { titulo: 'Generar proyecto', desc: 'Crea una propuesta de proyecto a partir de un reto y envíala a la empresa.',
        cta: 'Crear proyecto', principal: true,
        ruta: '/proyectos/crear', routeName: 'startup-day-crear', icon: 'masCirculo', tile: 'bg-centros' },
      { titulo: 'Biblioteca de proyectos', desc: 'Tus propuestas y proyectos: en edición, pendientes de validar, en marcha y terminados.',
        cta: 'Ver biblioteca',
        ruta: '/proyectos', routeName: 'startup-day', icon: 'capas', tile: 'bg-empresas' },
    ],
    // Bloque secundario: de qué reto parte el proyecto. La biblioteca va primero porque es
    // la vía directa; el generador es opcional, para personalizar lo que se trabaja.
    pasoAtras: {
      titulo: '¿De qué reto parte tu proyecto?',
      texto: 'Todo proyecto nace de un reto: la necesidad real de una empresa convertida en una pregunta para tu alumnado. Tenemos una biblioteca con retos disponibles, pero si quieres crear tu reto personalizado utiliza nuestro generador.',
      cards: [
        { titulo: 'Biblioteca de retos', etiqueta: 'Listos para usar',
          desc: 'Elige entre los retos que ya existen para tu familia profesional y empieza tu proyecto directamente.',
          ruta: '/retos', routeName: 'biblioteca', icon: 'libro', tile: 'bg-centros' },
        { titulo: 'Generador de retos', etiqueta: 'Para personalizar',
          desc: 'Si quieres trabajar algo concreto, crea un reto a medida: eliges la empresa, los módulos y los RA y CE.',
          ruta: '/retos/crear', routeName: 'microretos', icon: 'rayo', tile: 'bg-azul-noche' },
      ],
    },
    // Flujo explicativo (no navega): del reto al proyecto validado, agrupado por fases.
    // Se dibuja como diagrama (línea con pasos numerados), no como cards clicables.
    // `conector`: texto sobre la línea que sale del paso hacia el siguiente; el último
    // paso de una fase usa el `conector` de la fase (paso a la fase siguiente).
    // `concepto` (opcional): qué es lo que se trabaja en la fase, mismos textos que
    // ComoFuncionaModal.vue; se pinta con ConceptoClave sobre los pasos de la fase.
    flujo: {
      titulo: 'Mira cómo funciona',
      fases: [
        { nombre: 'Retos', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15', conector: 'Elige el reto',
          concepto: { color: 'centros', segmentos: [
            { t: 'Un ' }, { t: 'RETO', b: true }, { t: ' es la necesidad real de una empresa, transformada (con ayuda de la IA) en una ' },
            { t: 'pregunta', b: true }, { t: ' que el alumnado deberá responder.' },
          ] },
          pasos: [
            { titulo: 'Crea un reto a medida (opcional)', conector: '¿Te gusta?',
              desc: 'Solo si quieres personalizarlo: la IA convierte la necesidad de una empresa en un reto con los criterios del ciclo.' },
            { titulo: 'Biblioteca de retos',
              desc: 'Aquí están los retos ya disponibles y los que guardes tú: elige el que quieras trabajar.' },
          ] },
        { nombre: 'Proyectos', texto: 'text-empresas-dark', borde: 'border-empresas', halo: 'ring-empresas/20', conector: null,
          concepto: { color: 'empresas', segmentos: [
            { t: 'Una ' }, { t: 'PROPUESTA', b: true }, { t: ' es la concreción curricular del reto que hace el docente. Al validarla la empresa y el propio docente, pasa a ser ' },
            { t: 'PROYECTO', b: true }, { t: ': la respuesta que elaborará el alumnado, con sus fases, entregables y evaluación.' },
          ] },
          pasos: [
            { titulo: 'Crea un proyecto', conector: 'Guárdalo',
              desc: 'Selecciona el reto a trabajar y concrétalo en una propuesta para tu alumnado.' },
            { titulo: 'Consúltalo y valídalo',
              desc: 'En la biblioteca de proyectos: cuando la empresa o tú lo validáis, pasa a ser proyecto.' },
          ] },
      ],
    },
  },
  encuentros: {
    titulo: 'Encuentros con el', destacado: 'alumnado', color: 'text-alumnos',
    subtitulo: 'Organiza las sesiones de trabajo con tus grupos y revisa las que ya has creado.',
    // Qué es un encuentro — "el cuándo" (mismo texto que ComoFuncionaModal.vue)
    conceptos: [
      { color: 'alumnos', segmentos: [
        { t: 'Un ' }, { t: 'ENCUENTRO', b: true }, { t: ' es el ' }, { t: 'cuándo', b: true },
        { t: ': la fecha y los grupos con los que ese proyecto se trabaja en el aula. A partir de ahí, cada grupo avanza el proyecto por fases en su ' },
        { t: 'workspace', b: true }, { t: '.' },
      ] },
    ],
    cards: [
      { titulo: 'Crear encuentro', desc: 'Organiza un encuentro de trabajo con retos o proyectos.',
        ruta: '/encuentros/crear', routeName: 'dashboard-docente', icon: 'portapapeles', tile: 'bg-alumnos' },
      { titulo: 'Biblioteca de encuentros', desc: 'Consulta todos los encuentros registrados.',
        ruta: '/encuentros', routeName: 'encuentros-registrados', icon: 'libro', tile: 'bg-alumnos' },
    ],
    // Panel "Historial · Resumen de encuentros" (el mismo que en /encuentros/crear) bajo las cards
    historialEncuentros: true,
    flujo: {
      titulo: 'Mira cómo funciona',
      fases: [
        { nombre: 'Preparar', texto: 'text-alumnos-dark', borde: 'border-alumnos', halo: 'ring-alumnos/15', conector: 'Genera el código',
          pasos: [
            { titulo: 'Crea el encuentro', conector: 'Guárdalo',
              desc: 'Asocia un proyecto validado, pon la fecha y reparte al alumnado en grupos.' },
            { titulo: 'Consúltalo en la biblioteca',
              desc: 'Desde la biblioteca de encuentros generas el código del alumnado y lo compartes con otros docentes.' },
          ] },
        { nombre: 'En el aula', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15',
          pasos: [
            { titulo: 'Da acceso al alumnado', conector: 'Trabajan por fases',
              desc: 'Proyecta la pantalla de acceso: cada grupo entra en su workspace con su QR.' },
            { titulo: 'Sigue a tus grupos',
              desc: 'En Mis grupos ves el avance de cada grupo, fase a fase.' },
          ] },
      ],
    },
  },
  alumnado: {
    titulo: 'Acceso del', destacado: 'alumnado', color: 'text-alumnos',
    subtitulo: 'El alumnado entra con el QR o el código del encuentro, elige su grupo y trabaja en su espacio.',
    cards: [
      { titulo: 'Listado de alumnado', desc: 'Cada alumno con sus proyectos, encuentros, grupo, fase y nota final.',
        ruta: '/alumnado/listado', routeName: 'alumnado-listado', icon: 'alumnado', tile: 'bg-alumnos' },
      { titulo: 'Dar acceso al encuentro', desc: 'Elige un encuentro y proyecta su QR y código para el alumnado.',
        ruta: '/pantalla-acceso', routeName: 'pantalla-acceso-lista', icon: 'candado', tile: 'bg-alumnos' },
      { titulo: 'Unirse a un grupo', desc: 'Primera vez: el alumno elige su clase y su grupo (en su pantalla aparece como «equipo»).',
        ruta: '/unirse', icon: 'entrar', tile: 'bg-azul-noche' },
      { titulo: 'Retomar workspace', desc: 'El grupo introduce su código para volver a su flujo de trabajo.',
        ruta: '/workspace-proyecto', icon: 'documento', tile: 'bg-azul-noche' },
    ],
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
              desc: 'Avanza el proyecto desde F0 Inicio del equipo hasta F4 Presentación.' },
            { titulo: 'Retoma su workspace',
              desc: 'Vuelve con el código de su grupo, sin necesidad de cuenta.' },
          ] },
      ],
    },
  },
  empresas: {
    titulo: 'Empresas', destacado: 'colaboradoras', color: 'text-empresas',
    subtitulo: 'Consulta y contacta con las empresas de tu centro y envíales tus propuestas de proyecto.',
    cards: [
      { titulo: 'Propuestas a empresas', desc: 'Envía tus propuestas y sigue qué empresas han respondido.',
        cta: 'Ver propuestas', principal: true,
        ruta: '/empresas/propuestas', routeName: 'propuestas-empresas', icon: 'documento', tile: 'bg-empresas' },
      { titulo: 'Directorio de empresas', desc: 'Datos y contacto de las empresas (requiere contraseña especial).',
        cta: 'Abrir directorio',
        ruta: '/empresas', routeName: 'empresas', icon: 'candado', tile: 'bg-empresas' },
    ],
  },
  evaluacion: {
    titulo: 'Evaluación', destacado: '', color: 'text-centros',
    subtitulo: 'Revisa los proyectos terminados, sus diagnósticos y las propuestas que esperan tu validación.',
    cards: [
      { titulo: 'Pendientes de validar', desc: 'Propuestas de proyecto que esperan la validación docente.',
        ruta: { path: '/proyectos', query: { filtro: 'propuesta' } }, routeName: 'startup-day', icon: 'evaluacion', tile: 'bg-centros' },
      { titulo: 'Proyectos terminados', desc: 'Proyectos finalizados por los grupos, con su resultado.',
        ruta: '/proyectos/terminados', routeName: 'startup-day', icon: 'trofeo', tile: 'bg-administraciones' },
      { titulo: 'Biblioteca de diagnósticos', desc: 'Diagnósticos finales de los proyectos terminados.',
        ruta: '/evaluacion/diagnosticos', routeName: 'biblioteca-diagnosticos', icon: 'diagnostico', tile: 'bg-administraciones' },
    ],
    flujo: {
      titulo: 'Mira cómo funciona',
      fases: [
        { nombre: 'Propuesta', texto: 'text-empresas-dark', borde: 'border-empresas', halo: 'ring-empresas/20', conector: 'Se trabaja en el aula',
          pasos: [
            { titulo: 'Valida la propuesta',
              desc: 'La empresa desde su enlace, o tú como docente: la propuesta pasa a ser proyecto.' },
          ] },
        { nombre: 'Grupos', texto: 'text-alumnos-dark', borde: 'border-alumnos', halo: 'ring-alumnos/15', conector: '5 fases completas',
          pasos: [
            { titulo: 'Los grupos completan las fases',
              desc: 'Cada grupo avanza y cierra sus 5 fases en su workspace.' },
          ] },
        { nombre: 'Cierre', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15',
          pasos: [
            { titulo: 'Evalúa y genera el diagnóstico', conector: 'Márcalo completado',
              desc: 'Valora los RA/CE de cada grupo y genera su diagnóstico final con IA.' },
            { titulo: 'Proyecto terminado',
              desc: 'Queda en Proyectos terminados, y sus diagnósticos en la biblioteca de diagnósticos.' },
          ] },
      ],
    },
  },
  recursos: {
    titulo: 'Recursos', destacado: '', color: 'text-centros',
    subtitulo: 'Todo lo que necesitas para entender y sacar partido a DuaLab.',
    cards: [
      { titulo: '¿Qué es DuaLab?', desc: 'Cómo funciona la plataforma, paso a paso.',
        accion: 'comoFunciona', icon: 'info', tile: 'bg-centros' },
      { titulo: 'Glosario', desc: 'Retos, proyectos, encuentros, RA, CE… explicados.',
        proximamente: true, icon: 'glosario', tile: 'bg-centros' },
      { titulo: 'Guías y plantillas', desc: 'Material de apoyo para preparar tus encuentros.',
        proximamente: true, icon: 'libro', tile: 'bg-empresas' },
      { titulo: 'Acerca de', desc: 'El equipo que ha desarrollado DuaLab Studio Tool.',
        accion: 'creditos', icon: 'codigo', tile: 'bg-azul-noche' },
    ],
    flujo: {
      titulo: 'El recorrido completo',
      fases: [
        { nombre: 'Retos', texto: 'text-centros', borde: 'border-centros', halo: 'ring-centros/15', conector: 'Elige el reto',
          pasos: [
            { titulo: 'Crea un reto', desc: 'La necesidad real de una empresa, convertida en reto con IA.' },
          ] },
        { nombre: 'Proyectos', texto: 'text-empresas-dark', borde: 'border-empresas', halo: 'ring-empresas/20', conector: 'Asócialo',
          pasos: [
            { titulo: 'Crea y valida el proyecto', desc: 'Concretas el reto en una propuesta; la empresa o tú la validáis.' },
          ] },
        { nombre: 'Encuentros', texto: 'text-alumnos-dark', borde: 'border-alumnos', halo: 'ring-alumnos/15', conector: 'Trabajan en el aula',
          pasos: [
            { titulo: 'Organiza el encuentro', desc: 'Fecha, grupos y acceso del alumnado con su QR.' },
          ] },
        { nombre: 'Evaluación', texto: 'text-azul-noche', borde: 'border-administraciones', halo: 'ring-administraciones/15',
          pasos: [
            { titulo: 'Sigue y evalúa', desc: 'Avance por fases, evaluación RA/CE y diagnóstico final con IA.' },
          ] },
      ],
    },
  },
}
