<script setup>
/**
 * AvisoEmpresaBorrada.vue
 * Marca discreta en un reto cuya empresa está en la papelera o eliminada definitivamente
 * (empresa_estado del backend: activa | papelera | eliminada). Al pulsarla abre un modal
 * pequeño que explica que el reto sigue disponible. Con empresa activa no pinta nada.
 */
import { ref, computed } from 'vue'

const props = defineProps({
  estado: { type: String, default: null },
  // 'sm' en tarjetas, 'md' en fichas
  tamano: { type: String, default: 'sm' },
})

const abierto = ref(false)
const enPapelera = computed(() => props.estado === 'papelera')
const visible = computed(() => props.estado === 'papelera' || props.estado === 'eliminada')
</script>

<template>
  <template v-if="visible">
    <button
      type="button"
      @click.stop.prevent="abierto = true"
      class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 text-gray-500 font-bold uppercase tracking-wider whitespace-nowrap transition-colors hover:border-gray-300 hover:text-gray-700"
      :class="tamano === 'md' ? 'min-h-[36px] px-3 text-[10px] md:text-xs' : 'relative min-h-[28px] px-2.5 text-[8px] before:absolute before:-inset-1'"
      :aria-label="enPapelera ? 'Empresa en la papelera: más información' : 'Empresa eliminada: más información'"
    >
      <svg class="shrink-0" :class="tamano === 'md' ? 'w-3.5 h-3.5' : 'w-3 h-3'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      {{ enPapelera ? 'Empresa en la papelera' : 'Empresa eliminada' }}
    </button>

    <Teleport to="body">
      <div v-if="abierto" class="fixed inset-0 z-[200] flex items-center justify-center p-4" role="dialog" aria-modal="true"
        @keydown.esc="abierto = false">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="abierto = false" />
        <div class="relative w-full max-w-sm max-h-[85vh] overflow-y-auto rounded-2xl bg-white p-5 sm:p-6 shadow-2xl">
          <div class="flex items-start gap-3">
            <div class="w-9 h-9 shrink-0 rounded-xl bg-gray-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-black text-azul-noche">
                {{ enPapelera ? 'La empresa está en la papelera' : 'La empresa se ha eliminado' }}
              </h3>
              <p class="mt-1 text-sm text-gray-600 leading-relaxed">
                El reto sigue disponible y puedes usarlo con normalidad. Los datos de la empresa que
                muestra son los que tenía cuando se creó el reto.
              </p>
            </div>
          </div>

          <ul class="mt-4 space-y-2 text-sm text-gray-600 leading-relaxed">
            <li v-if="enPapelera" class="flex gap-2">
              <span class="text-gray-400">•</span>
              <span>Si se borró por error, pide a DuaLab que la restaure. Se borrará definitivamente pasados unos días.</span>
            </li>
            <li v-else class="flex gap-2">
              <span class="text-gray-400">•</span>
              <span>Ya no se puede recuperar.</span>
            </li>
            <li class="flex gap-2">
              <span class="text-gray-400">•</span>
              <span>{{ enPapelera ? 'Mientras esté en la papelera no se pueden crear retos nuevos con ella.' : 'No se pueden crear retos nuevos con ella.' }}</span>
            </li>
          </ul>

          <button type="button" @click="abierto = false"
            class="mt-5 w-full min-h-[40px] rounded-full bg-azul-noche text-white text-sm font-bold hover:opacity-90 transition-opacity">
            Entendido
          </button>
        </div>
      </div>
    </Teleport>
  </template>
</template>
