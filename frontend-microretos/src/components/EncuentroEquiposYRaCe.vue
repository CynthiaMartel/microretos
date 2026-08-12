<script setup>
defineProps({
  numEquipos: { type: Number, default: 0 },
  alumnadosDelEquipo: { type: Function, required: true }, // (n) => string[]
  cargandoRaCe: { type: Boolean, default: false },
  raCeBlocks: { type: Array, default: () => [] },
  modulosExpandidos: { type: Set, default: () => new Set() },
})
defineEmits(['toggle-modulo'])
</script>

<template>
  <!-- Equipos y alumnado -->
  <div v-if="numEquipos" class="space-y-2">
    <p class="text-[9px] font-black uppercase tracking-wider text-gray-400">
      Equipos <span class="text-gray-300">·</span> {{ numEquipos }}
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
      <div v-for="n in numEquipos" :key="n"
           class="p-3 rounded-xl bg-gradient-to-br from-[#F8FAFC] to-white border border-gray-100">
        <div class="flex items-center gap-1.5 mb-2">
          <span class="w-5 h-5 rounded-full bg-[#00A859]/10 text-[#00A859] font-black text-[9px]
                       flex items-center justify-center shrink-0">{{ n }}</span>
          <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">
            Equipo {{ n }}
          </p>
        </div>
        <div v-if="alumnadosDelEquipo(n).length" class="flex flex-wrap gap-1">
          <span v-for="nombre in alumnadosDelEquipo(n)" :key="nombre"
                class="text-[11px] font-semibold text-[#1F2937] bg-white border border-gray-200
                       rounded-full px-2 py-0.5">
            {{ nombre }}
          </span>
        </div>
        <p v-else class="text-[10px] text-gray-300 italic">Sin alumnos</p>
      </div>
    </div>
  </div>

  <!-- Módulos trabajados con sus RA/CE -->
  <div v-if="cargandoRaCe" class="flex justify-center py-4">
    <svg class="animate-spin w-4 h-4 text-[#00A859]" viewBox="0 0 24 24">
      <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
    </svg>
  </div>
  <div v-else-if="raCeBlocks.length" class="space-y-2">
    <p class="text-[9px] font-black uppercase tracking-wider text-gray-400">Módulos Trabajados - RA/CE</p>
    <div v-for="block in raCeBlocks" :key="block.modulo"
         class="rounded-xl bg-[#F8FAFC] border border-gray-100 overflow-hidden">
      <button type="button" @click="$emit('toggle-modulo', block.modulo)"
              class="w-full flex items-center justify-between gap-2 px-3.5 py-2.5 text-left">
        <p class="text-sm font-bold text-[#1F2937]">{{ block.modulo }}</p>
        <svg :class="['w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0', modulosExpandidos.has(block.modulo) ? 'rotate-180' : '']"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>
      <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1"
                  leave-active-class="transition-all duration-150 ease-in" leave-to-class="opacity-0 -translate-y-1">
        <div v-if="modulosExpandidos.has(block.modulo)" class="px-3.5 pb-3.5 space-y-3">
          <div v-for="(item, i) in block.items" :key="i" :class="{ 'pt-3 border-t border-gray-100': i > 0 }">
            <p class="text-[9px] uppercase font-bold text-[#00A859] mb-1">Resultado de Aprendizaje</p>
            <p class="text-sm font-semibold text-[#1F2937] mb-2">{{ item.ra }}</p>
            <template v-if="item.ce?.length">
              <p class="text-[9px] uppercase font-bold text-gray-400 mb-1">Criterios de Evaluación</p>
              <ul class="space-y-1 pl-1">
                <li v-for="(ce, j) in item.ce" :key="j" class="flex items-start gap-2 text-xs text-gray-500">
                  <span class="text-amber-400 shrink-0 font-bold mt-0.5">•</span>{{ ce }}
                </li>
              </ul>
            </template>
            <p v-if="item.aplicacion" class="text-xs text-gray-500 italic mt-2">
              <span class="font-bold not-italic text-[#1F2937]">Aplicación: </span>{{ item.aplicacion }}
            </p>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>
