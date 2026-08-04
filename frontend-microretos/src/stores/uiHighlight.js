import { defineStore } from 'pinia'
import { ref } from 'vue'

// Permite que un componente (p. ej. la ficha de un reto) resalte temporalmente
// un item del SidePanel al pasar el ratón por un enlace de texto, para guiar
// al usuario hacia dónde continuar un flujo (ej. "edita esto en Generar Proyecto").
export const useUiHighlightStore = defineStore('uiHighlight', () => {
  const highlightedNavItem = ref(null)

  function setHighlight(key) {
    highlightedNavItem.value = key
  }

  function clearHighlight(key) {
    if (!key || highlightedNavItem.value === key) {
      highlightedNavItem.value = null
    }
  }

  return { highlightedNavItem, setHighlight, clearHighlight }
})
