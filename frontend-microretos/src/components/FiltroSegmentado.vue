<!-- Filtro de pocas opciones fijas (estado, nivel 1º/2º, clase A–D…) dentro de FiltrosPanel.
     Regla: hasta ~5 opciones fijas → segmentado; listas largas o que crecen → FiltroSelect.
     `opciones`: [{ value, label, count? }] — la opción "todos" se pasa como una más (value ''). -->
<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { iconoFiltro } from '../config/iconosFiltro.js'

const modelo = defineModel({ type: [String, Number], default: '' })

const props = defineProps({
  label:    { type: String, required: true },
  opciones: { type: Array, required: true },
  // Icono del cuadradito de la izquierda (regla en todas las vistas, GUIA_DISENO §7). Por defecto
  // sale de la etiqueta (config/iconosFiltro.js); `icono` (atributo d de 24px) lo fuerza.
  icono:    { type: String, default: '' },
})

// En móvil el carril puede no caber: se avisa con un degradado al borde de que hay más
// opciones al deslizar (sin él, las que quedan fuera no se ven).
const carril = ref(null)
const hayMas = ref(false)
function medir() {
  const el = carril.value
  if (el) hayMas.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 1
}
let observador = null
onMounted(() => {
  medir()
  if (typeof ResizeObserver !== 'undefined' && carril.value) {
    observador = new ResizeObserver(medir)
    observador.observe(carril.value)
  }
})
onBeforeUnmount(() => observador?.disconnect())
watch(() => props.opciones, () => nextTick(medir), { deep: true })
</script>

<template>
  <div class="flex min-w-0 max-w-full items-end gap-2">
    <span aria-hidden="true"
          class="mb-px inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="icono || iconoFiltro(label)" /></svg>
    </span>
  <div class="min-w-0 max-w-full" role="group" :aria-label="label">
    <p class="mb-1 text-xs font-semibold text-azul-noche">{{ label }}</p>
    <div class="relative">
    <div ref="carril" @scroll.passive="medir" class="flex max-w-full overflow-x-auto rounded-full border border-borde">
      <button v-for="(op, i) in opciones" :key="String(op.value)" type="button"
              @click="modelo = op.value" :aria-pressed="modelo === op.value"
              :class="['inline-flex h-9 shrink-0 rounded-full items-center gap-1.5 whitespace-nowrap px-3 text-sm font-semibold transition-colors',
                       i > 0 && 'border-l border-borde',
                       modelo === op.value ? 'bg-azul-noche text-white' : 'bg-gray-50 text-texto-secundario hover:text-azul-noche']">
        {{ op.label }}
        <span v-if="op.count != null"
              :class="['rounded-full px-1.5 text-xs', modelo === op.value ? 'bg-white/20' : 'bg-gray-200/70']">{{ op.count }}</span>
      </button>
    </div>
    <span v-if="hayMas" aria-hidden="true"
          class="pointer-events-none absolute inset-y-px right-px w-10 rounded-r-full bg-gradient-to-l from-gray-50 via-gray-50/80 to-transparent" />
    </div>
  </div>
  </div>
</template>
