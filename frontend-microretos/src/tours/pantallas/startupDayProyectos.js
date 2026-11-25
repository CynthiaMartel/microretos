// Mis retos ajustados (/proyectos → StartupDayProyectos.vue). Dos pestañas (?ver=completados por
// defecto, ?ver=proceso) con filtros propios; la empresa también entra, sin crear ni editar.
export default {
  rutas: ['startup-day'],
  titulo: 'Mis retos ajustados',
  pasos: ({ route, auth }) => {
    const empresa = !!auth?.isEmpresa
    const proceso = route?.query?.ver === 'proceso'
    return [
      { el: 'sdp-cabecera', titulo: 'Mis retos ajustados',
        texto: empresa
          ? 'Aquí ves los retos ajustados que los docentes han preparado a partir de tus retos y en qué punto está cada uno.'
          : 'Aquí están tus retos ajustados: los retos que has adaptado a tu curso, módulos, RA y CE. Desde aquí los validas y los llevas al aula en un encuentro creativo.' },
      { el: 'sdp-trabajar', titulo: 'Trabajar un reto',
        texto: 'Abre el Ajuste del reto: eliges un reto de la biblioteca y lo adaptas a tu alumnado en cuatro pasos. Al terminar tendrás un reto ajustado nuevo.' },
      { el: 'sdp-ayuda', titulo: 'Ayuda',
        texto: 'Si dudas por dónde empezar, aquí tienes las opciones más habituales de esta pantalla.' },
      { el: 'sdp-papelera', titulo: 'Papelera',
        texto: 'Los retos ajustados que se han movido a la papelera. Desde ahí se pueden recuperar.' },
      { el: 'sdp-pestanas', titulo: 'En proceso o completados',
        texto: '«En proceso» son los que estás preparando o ya has validado; «Completados», los que los grupos ya han terminado. El número te dice cuántos hay en cada pestaña con los filtros actuales.' },
      { el: 'sdp-recordatorio', titulo: 'Recuerda validar',
        texto: 'Un reto ajustado solo se puede llevar al aula en un encuentro cuando lo has validado.' },
      { el: 'sdp-filtros', titulo: 'Busca y filtra',
        texto: 'Busca por título, empresa o centro y elige el curso académico. Debajo puedes acotar por de quién es, ciclo, módulo, nivel, empresa o sector.' },
      { el: 'sdp-mas-filtros', titulo: 'Más filtros',
        texto: 'Abre los filtros rápidos del lateral. El número te recuerda cuántos tienes marcados.' },
      { el: 'sdp-filtros-rapidos', titulo: 'Filtros rápidos',
        texto: proceso
          ? 'Filtra por estado (validados, sin validar, en edición o archivados) y por familia profesional. Los archivados solo aparecen si los marcas. «Limpiar» lo quita todo.'
          : 'Filtra por quién lo validó y por familia profesional. «Limpiar» quita todos los filtros de golpe.' },
      { el: 'sdp-resultados', titulo: 'Resultados',
        texto: 'Cuántos retos ajustados encajan con tus filtros y el orden en que se muestran. Pulsa la ✕ de una etiqueta para quitar ese filtro.' },
      { el: 'sdp-otros-cursos', titulo: 'Hay más en otros cursos',
        texto: 'Con estos filtros también hay retos ajustados en otro curso académico. Pulsa para verlos sin perder el resto de filtros.' },
      { el: 'sdp-tarjeta', titulo: 'Cada reto ajustado',
        texto: empresa
          ? 'Su estado, el curso y los módulos, y la fecha. Pulsa la tarjeta o «Ver reto ajustado» para abrir su ficha; en «⋯» puedes descargarlo en PDF.'
          : 'Su estado, en qué paso del ajuste está, el curso y los módulos. Pulsa la tarjeta para abrir su ficha, «Editar» para seguir ajustándolo y «⋯» para descargarlo en PDF o moverlo a la papelera.' },
      { el: 'sdp-vacio', titulo: 'Todavía no hay ninguno',
        texto: proceso
          ? 'Cuando trabajes un reto aparecerá aquí. Si tienes filtros puestos, prueba a quitarlos.'
          : 'Aquí aparecerán los retos ajustados cuando los grupos los terminen. Mira también la pestaña «En proceso».' },
      { el: 'sdp-paginacion', titulo: 'Más páginas',
        texto: 'Si hay muchos retos ajustados, se reparten en páginas. Muévete entre ellas desde aquí.' },
      ...(empresa ? [] : [{ titulo: '¿Y ahora?',
        texto: 'Abre un reto ajustado sin validar, revísalo y valídalo. Desde su ficha podrás crear el encuentro creativo para llevarlo al aula con tus grupos.' }]),
    ]
  },
}
