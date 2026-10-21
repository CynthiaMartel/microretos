<!-- Modal accesible del Taller de Ideas (GUIA_DISENO §6.4): role="dialog", aria-modal,
     título enlazado, foco inicial dentro, foco atrapado con Tab, cierre con Escape / ✕ /
     clic fuera, y el foco vuelve al botón que lo abrió. Cuerpo con scroll propio
     (la página de detrás no se mueve) y botón «Volver arriba» cuando el cuerpo baja.
     Slots: `barra` (fija bajo el título: buscador, contadores…), default (cuerpo), `pie`. -->
<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue'

const props = defineProps({
  abierto:   { type: Boolean, required: true },
  titulo:    { type: String, required: true },
  subtitulo: { type: String, default: '' },
  ancho:     { type: String, default: 'w-[min(56rem,calc(100vw-2rem))]' },
})
const emit = defineEmits(['cerrar'])

const panel = ref(null)
const cuerpo = ref(null)
const verArriba = ref(false)
const idTitulo = `modal-taller-${Math.random().toString(36).slice(2, 8)}`
let focoPrevio = null

const ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

watch(() => props.abierto, async (v) => {
  if (v) {
    focoPrevio = document.activeElement
    document.body.style.overflow = 'hidden'
    await nextTick()
    const primero = panel.value?.querySelector('[autofocus]') || panel.value?.querySelector(ENFOCABLES)
    ;(primero || panel.value)?.focus()
  } else {
    document.body.style.overflow = ''
    verArriba.value = false
    focoPrevio?.focus?.()
  }
})
onUnmounted(() => { document.body.style.overflow = '' })

function alTeclear(e) {
  if (e.key === 'Escape') { e.stopPropagation(); emit('cerrar'); return }
  if (e.key !== 'Tab') return
  const nodos = [...panel.value.querySelectorAll(ENFOCABLES)].filter(n => n.offsetParent !== null)
  if (!nodos.length) return
  const [primero, ultimo] = [nodos[0], nodos[nodos.length - 1]]
  if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus() }
  else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus() }
}

function alDesplazar() { verArriba.value = (cuerpo.value?.scrollTop || 0) > 400 }
function subir() { cuerpo.value?.scrollTo({ top: 0, behavior: 'smooth' }) }
defineExpose({ subir })
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition-opacity duration-150" enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-100" leave-to-class="opacity-0">
      <div v-if="abierto" class="fixed inset-0 z-[70] flex items-center justify-center bg-azul-noche/60 p-4 backdrop-blur-sm"
           @mousedown.self="emit('cerrar')">
        <div ref="panel" role="dialog" aria-modal="true" :aria-labelledby="idTitulo" tabindex="-1"
             class="relative flex max-h-[min(90dvh,56rem)] flex-col overflow-hidden rounded-[2rem] bg-white shadow-2xl outline-none"
             :class="ancho" @keydown="alTeclear">

          <header class="flex items-start gap-3 border-b border-borde px-5 pb-4 pt-5 sm:px-6">
            <div class="min-w-0 flex-1">
              <h2 :id="idTitulo" class="text-lg font-bold text-azul-noche sm:text-xl">{{ titulo }}</h2>
              <p v-if="subtitulo" class="mt-0.5 text-sm text-texto-secundario">{{ subtitulo }}</p>
            </div>
            <button type="button" @click="emit('cerrar')"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100"
                    aria-label="Cerrar">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </header>

          <div v-if="$slots.barra" class="border-b border-borde bg-gray-50/70 px-5 py-3 sm:px-6">
            <slot name="barra" />
          </div>

          <div ref="cuerpo" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6" @scroll.passive="alDesplazar">
            <slot />
          </div>

          <footer v-if="$slots.pie" class="border-t border-borde px-5 py-3 sm:px-6">
            <slot name="pie" />
          </footer>

          <button v-if="verArriba" type="button" @click="subir"
                  class="absolute bottom-20 right-5 inline-flex h-11 w-11 items-center justify-center rounded-full bg-azul-noche text-white shadow-lg hover:bg-azul-noche/90"
                  aria-label="Volver arriba de la lista" title="Volver arriba">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
