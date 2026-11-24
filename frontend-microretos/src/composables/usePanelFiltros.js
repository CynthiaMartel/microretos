import { ref, onMounted, onBeforeUnmount } from 'vue'

// «Filtros rápidos» de los exploradores: visible en escritorio (lg+) y plegado en móvil y tablet;
// «Más filtros» lo alterna. Al cruzar el ancho de escritorio (girar la tablet, redimensionar) se
// vuelve a abrir o plegar solo.
export function usePanelFiltros() {
  const mq = typeof window === 'undefined' ? null : window.matchMedia('(min-width: 1024px)')
  const panelFiltrosAbierto = ref(!mq || mq.matches)
  const alCambiarAncho = (e) => { panelFiltrosAbierto.value = e.matches }
  onMounted(() => mq?.addEventListener('change', alCambiarAncho))
  onBeforeUnmount(() => mq?.removeEventListener('change', alCambiarAncho))
  return panelFiltrosAbierto
}
