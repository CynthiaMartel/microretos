<!-- Flujo explicativo por fases (config/navegacion.js → SECCIONES[x].flujo). Es una explicación,
     no navegación: pasos numerados sobre una línea discontinua, sin cards ni hover.
     En móvil la línea es vertical (timeline); desde lg, horizontal con las fases en fila.
     Lo usan SeccionHub.vue y ComoFuncionaModal.vue. -->
<script setup>
import { computed } from 'vue'
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
</script>

<template>
  <!-- En horizontal, cada fase ocupa en proporción a sus pasos (fr = nº de pasos) y comparte
       filas (subgrid) para que los conceptos de distinta altura no desalineen la línea de pasos -->
  <div class="flex flex-col px-1 sm:px-2 lg:grid lg:grid-rows-[auto_auto_auto]"
       :style="{ gridTemplateColumns: fases.map(f => `${f.pasos.length}fr`).join(' ') }">
    <div v-for="(fase, fi) in fases" :key="fase.nombre" class="lg:row-span-3 lg:grid lg:grid-rows-subgrid">
      <!-- Etiqueta de fase con su regla, a modo de llave sobre sus pasos -->
      <div class="mb-3 flex items-center gap-2 lg:pr-6" :class="fase.texto">
        <span class="text-[11px] font-black uppercase tracking-widest">{{ fase.nombre }}</span>
        <span class="h-px flex-1 bg-current opacity-25" />
      </div>

      <!-- Qué es lo que se trabaja en la fase (reto, propuesta/proyecto…). Contenedor siempre
           presente para mantener las tres filas del subgrid aunque la fase no tenga concepto -->
      <div class="lg:pr-6">
        <ConceptoClave v-if="fase.concepto" class="mb-4 lg:mb-5" :color="fase.concepto.color" :segmentos="fase.concepto.segmentos" />
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
  </div>
</template>
