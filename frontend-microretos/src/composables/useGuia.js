import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useTour } from './useTour.js'
import { pasosMenu } from '../tours/menu.js'
import { tourDeRuta, pasosDe, tituloDe } from '../tours/index.js'

// Acciones del botón «Guía» (TopBar en sm+, menú de usuario en móvil): recorrido del menú lateral
// y recorrido de la pantalla actual.
export function useGuia() {
  const route = useRoute()
  const auth = useAuthStore()
  const { iniciar } = useTour()

  const tourPantalla = computed(() => tourDeRuta(route))

  function recorrerMenu() {
    iniciar(pasosMenu(auth), 'Menú')
  }

  function recorrerPantalla() {
    const t = tourPantalla.value
    if (!t) return
    const ctx = { route, auth }
    iniciar(pasosDe(t, ctx), tituloDe(t, ctx))
  }

  return { tourPantalla, recorrerMenu, recorrerPantalla }
}
