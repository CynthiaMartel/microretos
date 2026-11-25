import { ref, computed } from 'vue'

// Tour guiado (botón «Guía» de la TopBar): estado compartido entre la TopBar, que lo lanza, y
// TourGuiado.vue (montado en App.vue), que lo pinta. Los pasos de cada pantalla están en src/tours/.
//
// Paso: { el, titulo, texto, panel?, lado? }
//   el     → valor de data-tour del elemento que se señala (o un selector CSS si empieza por . # [).
//            Sin `el`, el paso sale centrado, sin señalar nada (bienvenida, cierre).
//            Si el elemento no está en pantalla (otro rol, lista vacía, pestaña cerrada) el paso se salta.
//   panel  → el elemento está en el menú lateral: en móvil/tablet se abre el cajón para verlo.
//   lado   → preferencia de posición del bocadillo: 'abajo' | 'arriba' | 'derecha' | 'izquierda'.
const activo = ref(false)
const titulo = ref('')
const pasos = ref([])
const indice = ref(0)

const paso = computed(() => pasos.value[indice.value] ?? null)

// Solo los pasos cuyo elemento está en la pantalla (el cajón del menú lateral está montado aunque
// esté cerrado en móvil, así que sus entradas cuentan). Devuelve false si no queda ninguno.
function iniciar(lista, nombre = '') {
  const visibles = (lista ?? []).filter(p => !p.el || elementoPaso(p))
  if (!visibles.length) return false
  pasos.value = visibles
  titulo.value = nombre
  indice.value = 0
  activo.value = true
  return true
}

function cerrar() {
  activo.value = false
  pasos.value = []
  indice.value = 0
}

export function selectorPaso(el) {
  if (!el) return null
  return /^[.#[]/.test(el) ? el : `[data-tour="${el.replace(/["\\]/g, '\\$&')}"]`
}

// Primer elemento visible (con tamaño) que encaja con el paso: el mismo data-tour puede repetirse
// (p. ej. una vista con versión móvil y de escritorio) y solo uno se ve
export function elementoPaso(p) {
  const sel = selectorPaso(p?.el)
  if (!sel) return null
  return [...document.querySelectorAll(sel)].find(n => {
    const r = n.getBoundingClientRect()
    return r.width > 0 && r.height > 0
  }) ?? null
}

export function useTour() {
  return { activo, titulo, pasos, indice, paso, iniciar, cerrar }
}
