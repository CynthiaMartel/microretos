<script setup>
defineProps({
  // [{ modulo: string, ras: [{ descripcion: string, criterios: string[] }] }]
  items: { type: Array, required: true },
  // Cuando es true, muestra un botón para quitar cada CE — emite remove-ce(modIdx, raIdx, ceIdx)
  editable: { type: Boolean, default: false },
})
defineEmits(['remove-ce'])
</script>

<template>
  <div class="space-y-2">
    <div v-for="(mod, modIdx) in items" :key="mod.modulo"
         class="rounded-2xl border border-gray-100 overflow-hidden">

      <!-- Cabecera módulo -->
      <div class="flex items-center gap-2 px-4 py-3 bg-gray-50/80 border-b border-gray-100">
        <span class="text-[10px] font-black uppercase tracking-widest
                     bg-indigo-100/60 text-indigo-500 px-2 py-0.5 rounded-full shrink-0">MF</span>
        <span class="flex-1 text-xs font-bold text-gray-700">{{ mod.modulo }}</span>
        <span class="text-[10px] text-gray-400">{{ mod.ras.length }} RA</span>
      </div>

      <!-- Resultados de Aprendizaje -->
      <div class="divide-y divide-gray-50">
        <div v-for="(ra, raIdx) in mod.ras" :key="raIdx" class="px-4 py-3 bg-[#00A859]/4">
          <div class="flex items-start gap-2.5">
            <span class="text-[9px] font-black uppercase tracking-widest text-[#00A859]
                         bg-[#00A859]/10 px-2 py-0.5 rounded-full shrink-0 mt-0.5">RA</span>
            <p class="flex-1 text-[11px] font-semibold text-gray-700 leading-snug">{{ ra.descripcion }}</p>
          </div>

          <!-- Criterios de Evaluación -->
          <div v-if="ra.criterios.length" class="mt-2.5 pl-1">
            <div class="flex items-center gap-1.5 mb-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
              <span class="text-[9px] font-black uppercase tracking-widest text-amber-600">
                Criterios de Evaluación
              </span>
            </div>
            <div class="space-y-1.5 pl-2">
              <p v-for="(ce, j) in ra.criterios" :key="j"
                 class="flex items-start gap-2 text-[10px] text-gray-600 leading-snug">
                <span class="flex-1"><span class="font-bold text-amber-500 mr-1">{{ j + 1 }}.</span>{{ ce }}</span>
                <button v-if="editable" type="button" @click="$emit('remove-ce', modIdx, raIdx, j)"
                        title="Quitar de este proyecto"
                        class="shrink-0 text-gray-400 hover:text-red-500 transition-colors">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
