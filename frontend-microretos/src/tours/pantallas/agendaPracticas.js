// Agenda de prácticas del docente (/planes/agenda → AgendaPracticasDocente.vue). El contenido está en
// AgendaPracticas.vue, compartido con DuaLab Central: sus bloques se señalan con selectores CSS
// dentro del componente marcado en la vista (agp-agenda), sin tocar el componente.
const A = '[data-tour="agp-agenda"]'

export default {
  rutas: ['agenda-practicas'],
  titulo: 'Agenda de prácticas',
  pasos: [
    { el: 'agp-cabecera', titulo: 'Agenda de prácticas',
      texto: 'El seguimiento del alumnado de tus planes de proyecto mientras está en la empresa: en qué fase va cada grupo, quién va con retraso según los hitos y cuántas horas lleva.' },
    { el: 'agp-ayuda', titulo: '¿Para qué sirve?',
      texto: 'Un resumen rápido de la pantalla. Recuerda: lo que tienes cada día está en el Calendario; aquí ves cómo avanza el alumnado.' },
    { el: `${A} > ul:first-child`, titulo: 'De un vistazo',
      texto: 'Cuántos planes están en curso, cuántos por empezar y cuántos grupos van retrasados respecto a la fase que les toca por fecha.' },
    { el: `${A} > section[aria-label="Filtros"]`, titulo: 'Busca y filtra',
      texto: 'Busca por plan o empresa, filtra por estado (en curso, por empezar, terminados) o quédate solo con los planes que tienen grupos con retraso.' },
    { el: `${A} section[aria-labelledby="titulo-calendario-planes"]`, titulo: 'Calendario de planes',
      texto: 'Una fila por plan, de inicio a fin, con sus hitos (◆) y la línea roja de hoy. Pulsa una fila para ir a la tarjeta de ese plan.' },
    { el: `${A} > ul.space-y-4 > li:first-child`, titulo: 'Cada plan',
      texto: 'Empresa, fechas, horas previstas y estado. Con «Detalle de grupos» ves el trabajo de cada grupo en su workspace.' },
    { el: `${A} > ul.space-y-4 > li:first-child ol`, titulo: 'Hitos',
      texto: 'Los hitos del cronograma con su fecha y las fases que tocan. El hito actual aparece resaltado y debajo ves cuándo es el próximo.' },
    { el: `${A} > ul.space-y-4 > li:first-child ul.grid`, titulo: 'Avance de los grupos',
      texto: 'En qué fase va cada grupo y si está «Al día», «Retrasado» o «Terminado». También ves el alias de cada persona y las horas que lleva anotadas.' },
    { el: `${A} > ul.space-y-4 > li:first-child > div:last-child`, titulo: 'Horas de prácticas',
      texto: 'Despliégalo para ver y anotar las horas de prácticas de cada alumno o alumna, por ejemplo con lo que te indica el tutor de la empresa.' },
  ],
}
