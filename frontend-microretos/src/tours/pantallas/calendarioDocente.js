// Calendario del docente (/calendario → CalendarioDocente.vue): encuentros, hitos de planes, notas y tareas.
export default {
  rutas: ['calendario'],
  titulo: 'Mi calendario',
  pasos: [
    { el: 'cal-cabecera', titulo: 'Mi calendario',
      texto: 'Tu organización del día a día: los encuentros creativos, los hitos de tus planes de proyecto, tus notas y tus tareas pendientes, todo en un sitio.' },
    { el: 'cal-nuevo', titulo: 'Nuevo encuentro',
      texto: 'Programa un encuentro creativo con tu alumnado a partir de un reto ajustado validado. Aparecerá en el calendario en sus fechas.' },
    { el: 'cal-mes', titulo: 'El mes',
      texto: 'Muévete entre meses con las flechas. Cada día muestra sus encuentros, hitos y notas; en el móvil, como puntos de color. Pulsa un día para ver su detalle.' },
    { el: 'cal-leyenda', titulo: 'Colores',
      texto: 'Azul, encuentro creativo; verde, plan de prácticas; rombo, hito de un plan; naranja, nota. «Hoy» te devuelve al día de hoy.' },
    { el: 'cal-dia', titulo: 'El día elegido',
      texto: 'Lo que tienes ese día, agrupado por módulo. Pulsa un encuentro para resaltar todos sus días en el calendario y la flecha para abrir su detalle de grupos. Un hito te lleva a la Agenda de prácticas.' },
    { el: 'cal-nota-dia', titulo: 'Apunta en ese día',
      texto: 'Escribe una nota y pulsa «Añadir»: queda en el calendario en el día elegido. Luego puedes quitarle la fecha o borrarla.' },
    { el: 'cal-tareas', titulo: 'Tareas pendientes',
      texto: 'Las mismas que en tu panel: las que te propone la plataforma y las que añades tú. Márcalas como hechas con el interruptor; pulsa una automática para ir a resolverla.' },
    { el: 'cal-notas', titulo: 'Mis notas',
      texto: 'Las próximas notas con fecha (pulsa una para verla en el calendario) y tus notas sueltas, sin fecha. Escribe abajo y pulsa «Guardar nota».' },
  ],
}
