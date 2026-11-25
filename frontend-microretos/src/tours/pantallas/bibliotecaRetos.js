// Explorador de retos (/retos → BibliotecaMicroretos.vue). La empresa también entra aquí (sin
// generar ni trabajar retos), así que los textos cambian con el rol.
export default {
  rutas: ['biblioteca'],
  titulo: 'Explorador de retos',
  pasos: ({ auth }) => {
    const empresa = !!auth?.isEmpresa
    return [
      { el: 'bib-cabecera', titulo: 'Explorador de retos',
        texto: empresa
          ? 'Aquí están los retos que se han creado a partir de las necesidades de tu empresa y de otras. Búscalos, fíltralos y ábrelos para ver el detalle.'
          : 'Es el punto de partida del Taller de ideas: todos los retos de tu centro. Busca uno que encaje con tu ciclo, ábrelo y, si te convence, trabájalo con tu alumnado.' },
      { el: 'bib-filtros', titulo: 'Busca y filtra',
        texto: 'Escribe una palabra del título, la empresa, el ciclo o la pregunta del reto. Debajo puedes acotar por ciclo, módulo, nivel, dificultad, empresa o sector, y arriba elegir el curso académico.' },
      { el: 'bib-generar', titulo: 'Generar con IA',
        texto: '¿No encuentras el reto que buscas? Créalo a medida: eliges la empresa, los módulos y los RA y CE, y la IA lo redacta por ti.' },
      { el: 'bib-mas-filtros', titulo: 'Más filtros',
        texto: 'Abre los filtros rápidos: familia profesional y tipo de reto (empresa real, empresa ficticia o información simulada).' },
      { el: 'bib-filtros-rapidos', titulo: 'Filtros rápidos',
        texto: 'Marca una o varias familias y tipos de reto. El número de cada opción te dice cuántos retos hay. «Limpiar» quita todos los filtros de golpe.' },
      { el: 'bib-resultados', titulo: 'Resultados',
        texto: 'Cuántos retos encajan con tus filtros. Aquí también cambias el orden y eliges verlos en cuadrícula o en lista. Pulsa la ✕ de una etiqueta para quitar ese filtro.' },
      { el: 'bib-pdf', titulo: 'Descargar en PDF',
        texto: 'Descarga en un solo PDF todos los retos que ves con los filtros actuales, por ejemplo para revisarlos en papel o compartirlos con tu departamento.' },
      { el: 'bib-otros-cursos', titulo: 'Hay más en otros cursos',
        texto: 'Con estos mismos filtros también hay retos en otro curso académico. Pulsa para verlos sin perder el resto de filtros.' },
      { el: 'bib-tarjeta', titulo: 'Cada reto',
        texto: 'De un vistazo: dificultad, si la empresa es real o ficticia, la pregunta del reto, la familia y los módulos, el curso y la empresa que lo plantea.' },
      { el: 'bib-ver-reto', titulo: 'Ver reto',
        texto: 'Abre la ficha completa del reto: la empresa, el contexto, qué se pide al alumnado y los RA y CE que trabaja.' },
      { el: 'bib-menu', titulo: 'Más acciones',
        texto: empresa
          ? 'Desde aquí descargas el reto en PDF.'
          : 'Aquí está «Trabajar este reto», que abre el Ajuste del reto con este reto ya elegido. También puedes descargarlo en PDF o eliminarlo.' },
      { el: 'bib-paginacion', titulo: 'Más páginas',
        texto: 'Si hay muchos retos, se reparten en páginas. Muévete entre ellas desde aquí.' },
      ...(empresa ? [] : [{ titulo: '¿Y ahora?',
        texto: 'Cuando encuentres tu reto, pulsa «Trabajar este reto». En el Ajuste del reto lo adaptas a tu curso, tus módulos, RA y CE y objetivos, y lo conviertes en tu reto ajustado.' }]),
    ]
  },
}
