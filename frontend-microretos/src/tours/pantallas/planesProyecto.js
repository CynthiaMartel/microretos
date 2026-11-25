// Biblioteca de planes (/planes → PlanesProyecto.vue): etapa 2, el plan de proyecto en la empresa.
// La tarjeta de cada plan es ProyectoCard (compartida): sus botones se señalan con selectores CSS
// dentro de la primera tarjeta marcada.
export default {
  rutas: ['planes'],
  titulo: 'Biblioteca de planes',
  pasos: [
    { el: 'pp-cabecera', titulo: 'Biblioteca de planes',
      texto: 'Aquí están tus planes de proyecto: el trabajo ampliado que el alumnado seleccionado por DuaLab desarrolla en la empresa durante las prácticas. Es la etapa que sigue al Taller de ideas.' },
    { el: 'pp-crear', titulo: 'Crear plan',
      texto: 'Empieza un plan nuevo ampliando un reto ajustado que ya trabajaste en el taller o partiendo de otro reto.' },
    { el: 'pp-filtros', titulo: 'Busca y filtra',
      texto: 'Busca por título, empresa o centro. Debajo puedes acotar por ciclo, módulo, nivel, empresa o sector, y arriba elegir el curso académico.' },
    { el: 'pp-mas-filtros', titulo: 'Más filtros',
      texto: 'Abre los filtros rápidos: estado del plan, si ya tiene cronograma y familia profesional.' },
    { el: 'pp-filtros-rapidos', titulo: 'Filtros rápidos',
      texto: 'Marca uno o varios estados, «Con cronograma» o «Sin cronograma» y las familias. Los archivados solo aparecen si marcas «Archivados». «Limpiar» quita todos los filtros.' },
    { el: 'pp-cronogramas', titulo: 'Cronogramas en marcha',
      texto: 'Los planes que ya tienen fechas, hitos y alumnado. El cronograma se crea desde la ficha de un plan validado, con «Crear cronograma».' },
    { el: 'pp-cronograma', titulo: 'Cada cronograma',
      texto: 'Con «Generar grupos y códigos» creas los grupos del plan y sus códigos de acceso. Después tendrás «Detalle de grupos» y «Dar acceso». «Editar hitos» te deja cambiar las fechas de cada hito.' },
    { el: 'pp-resultados', titulo: 'Tus planes',
      texto: 'Cuántos planes encajan con tus filtros. Pulsa la ✕ de una etiqueta para quitar ese filtro y cambia aquí el orden de la lista.' },
    { el: 'pp-tarjeta', titulo: 'Cada plan',
      texto: 'Su estado, el título y la empresa. Pulsa la tarjeta para abrir la ficha del plan: desde allí lo validas y creas su cronograma.' },
    { el: '[data-tour="pp-tarjeta"] button[aria-haspopup="menu"]', titulo: 'Más acciones',
      texto: '«Editar» abre el plan en el asistente para completarlo. En este menú ves la ficha, la descargas en PDF o mueves el plan a la papelera.' },
    { el: 'pp-paginacion', titulo: 'Más páginas',
      texto: 'Si tienes muchos planes, se reparten en páginas. Muévete entre ellas desde aquí.' },
    { titulo: '¿Y ahora?',
      texto: 'Cuando el plan esté validado y tenga su cronograma, sigue el avance del alumnado en la empresa desde la «Agenda de prácticas».' },
  ],
}
