// Noticias (/noticias/:tipo → NoticiasListado.vue): listado completo de «Noticias DUALAB» o
// «Novedades plataforma DUALAB», al que se llega con «Ver todas» desde el panel de inicio.
export default {
  rutas: ['noticias-listado'],
  titulo: ({ route }) => (route.params.tipo === 'plataforma' ? 'Novedades de la plataforma' : 'Noticias DUALAB'),
  pasos: ({ route }) => {
    const plataforma = route.params.tipo === 'plataforma'
    return [
      { el: 'not-cabecera', titulo: plataforma ? 'Novedades de la plataforma' : 'Noticias DUALAB',
        texto: plataforma
          ? 'Aquí tienes todas las novedades de la plataforma: funciones nuevas, guías y recursos. Con la flecha vuelves a tu panel de inicio.'
          : 'Aquí tienes todas las noticias de DUALAB: convocatorias, formación y comunidad. Con la flecha vuelves a tu panel de inicio.' },
      { el: 'not-lista', titulo: 'Todas las noticias',
        texto: 'En tu panel de inicio solo ves las más recientes; aquí aparecen todas, de la más nueva a la más antigua.' },
      { el: 'not-tarjeta', titulo: 'Cada noticia',
        texto: 'Arriba, la categoría (convocatoria, formación, guía…) y el titular; debajo, un resumen breve.' },
      { el: 'not-vacio', titulo: 'Sin noticias todavía',
        texto: 'Aún no se ha publicado nada en esta sección. Vuelve más adelante.' },
      { titulo: '¿Y ahora?',
        texto: 'Vuelve a tu panel de inicio para seguir con tus retos ajustados y encuentros.' },
    ]
  },
}
