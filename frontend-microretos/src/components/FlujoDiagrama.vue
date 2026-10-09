<!-- Flujo explicativo por fases (config/navegacion.js → SECCIONES[x].flujo). Es una explicación,
     no navegación: pasos numerados sobre una línea discontinua, sin cards ni hover.
     En móvil la línea es vertical (timeline); desde lg, horizontal con las fases en fila.
     Si una fase tiene `concepto`, su nombre se destaca: al pasar el cursor muestra la pregunta
     ("¿Qué es un reto?") y al pulsarlo abre un toast con la definición (ConceptoClave).
     Lo usan SeccionHub.vue y ComoFuncionaModal.vue. -->
<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import ConceptoClave from './ConceptoClave.vue'

const props = defineProps({
  // Array de fases: { nombre, texto, borde, halo, conector?, concepto?, pasos: [{ titulo, desc, conector? }] }
  fases: { type: Array, required: true },
})

// Fases con numeración continua de pasos (1, 2 | 3, 4)
const fases = computed(() => {
  let n = 0
  return props.fases.map(fase => ({ ...fase, pasos: fase.pasos.map(p => ({ ...p, n: ++n })) }))
})

const totalPasos = computed(() => fases.value.reduce((t, f) => t + f.pasos.length, 0))

// Texto de la línea que sale de un paso: el suyo, o el de la fase si es su último paso
const conectorDe = (fase, fi, pi) =>
  pi < fase.pasos.length - 1 ? fase.pasos[pi].conector : (fi < fases.value.length - 1 ? fase.conector : null)

// ── Toast con la definición del concepto ──────────────────────────────────────
const TOAST_MS = 10000
const conceptoAbierto = ref(null)
let temporizador = null

function cerrarConcepto() {
  clearTimeout(temporizador)
  conceptoAbierto.value = null
  document.removeEventListener('keydown', onTecla)
}
function onTecla(e) { if (e.key === 'Escape') cerrarConcepto() }
function abrirConcepto(concepto) {
  clearTimeout(temporizador)
  conceptoAbierto.value = concepto
  temporizador = setTimeout(cerrarConcepto, TOAST_MS)
  document.addEventListener('keydown', onTecla)
}
onBeforeUnmount(cerrarConcepto)
</script>

<template>
  <!-- En horizontal, cada fase ocupa en proporción a sus pasos (fr = nº de pasos) y comparte
       filas (subgrid) para que las etiquetas de distinta altura no desalineen la línea de pasos -->
  <div class="flex flex-col px-1 sm:px-2 lg:grid lg:grid-rows-[auto_auto]"
       :style="{ gridTemplateColumns: fases.map(f => `${f.pasos.length}fr`).join(' ') }">
    <div v-for="(fase, fi) in fases" :key="fase.nombre" class="lg:row-span-2 lg:grid lg:grid-rows-subgrid">
      <!-- Etiqueta de fase con su regla, a modo de llave sobre sus pasos -->
      <div class="mb-3 flex items-end gap-2 lg:pr-6" :class="fase.texto">
        <!-- Con concepto: nombre destacado, tooltip con la pregunta y toast al pulsar -->
        <span v-if="fase.concepto" class="group relative">
          <button type="button"
                  class="flex items-center gap-1.5 rounded-md font-heading text-lg font-black uppercase tracking-wider underline decoration-current/40 decoration-dashed decoration-2 underline-offset-4 transition hover:decoration-current focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current/40 sm:text-xl"
                  :aria-label="fase.concepto.pregunta" @click="abrirConcepto(fase.concepto)">
            {{ fase.nombre }}
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-current/15 text-[11px] no-underline" aria-hidden="true">?</span>
          </button>
          <span role="tooltip"
                class="pointer-events-none absolute bottom-full left-0 z-20 mb-2 whitespace-nowrap rounded-lg bg-azul-noche px-2.5 py-1 text-xs font-semibold normal-case tracking-normal text-white opacity-0 shadow-md transition group-hover:opacity-100 group-focus-within:opacity-100">
            {{ fase.concepto.pregunta }}
          </span>
        </span>
        <span v-else class="text-[11px] font-black uppercase tracking-widest">{{ fase.nombre }}</span>
        <span class="mb-1.5 h-px flex-1 bg-current opacity-25" />
      </div>

      <ol class="flex flex-col lg:flex-row">
        <li v-for="(paso, pi) in fase.pasos" :key="paso.titulo" class="flex flex-1 gap-3 lg:block">
          <!-- Marcador: número + línea hasta el siguiente paso -->
          <div class="flex flex-col items-center lg:flex-row">
            <!-- Número con halo del color de su fase -->
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 bg-white font-heading text-sm font-bold ring-4"
                  :class="[fase.borde, fase.texto, fase.halo]">{{ paso.n }}</span>
            <span v-if="paso.n < totalPasos"
                  class="relative my-2 w-0 flex-1 border-l-2 border-dashed border-gray-300 lg:mx-3 lg:my-0 lg:h-0 lg:border-l-0 lg:border-t-2">
              <span v-if="conectorDe(fase, fi, pi)"
                    class="absolute bottom-1.5 left-1/2 hidden -translate-x-1/2 whitespace-nowrap text-[11px] italic text-gray-500 lg:block">
                {{ conectorDe(fase, fi, pi) }}
              </span>
            </span>
          </div>
          <!-- Texto del paso -->
          <div class="min-w-0 pb-5 pt-1 lg:pb-0 lg:pr-6 lg:pt-3">
            <p class="text-sm font-semibold leading-snug">{{ paso.titulo }}</p>
            <p class="mt-1 text-xs leading-snug text-gray-500">{{ paso.desc }}</p>
            <p v-if="conectorDe(fase, fi, pi)" class="mt-2 text-[11px] italic text-gray-500 lg:hidden">↓ {{ conectorDe(fase, fi, pi) }}</p>
          </div>
        </li>
      </ol>
    </div>

  <!-- Toast con la definición: abajo y centrado (el de sesión de App.vue va abajo a la derecha).
       Dentro del nodo raíz para que el componente siga recibiendo `class` de quien lo usa -->
  <Teleport to="body">
    <Transition enter-from-class="translate-y-3 opacity-0" leave-to-class="translate-y-3 opacity-0"
                enter-active-class="transition duration-200 ease-out" leave-active-class="transition duration-150 ease-in">
      <div v-if="conceptoAbierto" role="status" aria-live="polite"
           class="fixed bottom-4 left-1/2 z-[60] w-[min(30rem,calc(100vw-2rem))] -translate-x-1/2 rounded-2xl bg-white shadow-xl">
        <ConceptoClave class="pr-10" :color="conceptoAbierto.color" :segmentos="conceptoAbierto.segmentos" />
        <button type="button" class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full text-gray-400 hover:bg-black/5 hover:text-gray-700"
                aria-label="Cerrar" @click="cerrarConcepto">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </Transition>
  </Teleport>
  </div>
</template>
