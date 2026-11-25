// Tours guiados de cada pantalla (botón «Guía» → «Esta pantalla»). Un archivo por pantalla en
// ./pantallas/, que exporta por defecto:
//
//   export default {
//     rutas: ['biblioteca'],              // nombres de ruta (router/index.js) en los que se ofrece
//     titulo: 'Biblioteca de retos',      // antetítulo del bocadillo
//     pasos: [ { el, titulo, texto, panel?, lado? }, … ]   // o (ctx) => [...] con ctx = { route, auth }
//   }
//
// `el` es el valor de un atributo data-tour="…" de la vista (ver useTour.js). Los pasos cuyo
// elemento no está en pantalla se saltan solos (otro rol, lista vacía…), así que una pantalla puede
// describir todo lo que puede llegar a mostrar.
const modulos = import.meta.glob('./pantallas/*.js', { eager: true })
const TOURS = Object.values(modulos).map(m => m.default).filter(Boolean)

export function tourDeRuta(route) {
  return TOURS.find(t => t.rutas.includes(route.name)) ?? null
}

export function pasosDe(tour, ctx) {
  return typeof tour.pasos === 'function' ? tour.pasos(ctx) : tour.pasos
}

export function tituloDe(tour, ctx) {
  return typeof tour.titulo === 'function' ? tour.titulo(ctx) : tour.titulo
}
