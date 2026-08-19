<script setup>
defineProps({
  show:        { type: Boolean, required: true },
  titulo:      { type: String,  default: '¿Quieres activar la guía-tour?' },
  descripcion: { type: String,  default: 'Explora la sección con una guía paso a paso que te muestra cada función.' },
})
const emit = defineEmits(['activar', 'omitir'])
</script>

<template>
  <Transition name="tp-fade">
    <div v-if="show"
         @click.self="emit('omitir')"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="relative bg-white border border-gray-200 rounded-[2rem] shadow-2xl max-w-sm w-full p-8">

        <!-- Icono + título -->
        <div class="flex flex-col items-center text-center gap-4 mb-6">
          <div class="w-14 h-14 rounded-2xl bg-blue-50 border border-blue-200
                      flex items-center justify-center shrink-0">
            <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
          </div>
          <div>
            <h2 class="text-lg font-black tracking-tight text-[#121212] leading-tight">{{ titulo }}</h2>
            <p class="text-xs text-gray-500 leading-relaxed mt-1">{{ descripcion }}</p>
          </div>
        </div>

        <!-- Acciones -->
        <div class="space-y-3">
          <button @click="emit('activar')"
                  class="w-full flex items-center justify-center gap-2 py-3 px-5 rounded-2xl
                         bg-blue-500 hover:bg-blue-600 active:scale-[.98]
                         text-white font-black text-sm transition-all duration-150 shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Sí, activar guía
          </button>

          <button @click="emit('omitir')"
                  class="w-full text-center text-[11px] font-bold text-gray-400
                         hover:text-gray-600 transition-colors py-1">
            Entrar directamente
          </button>
        </div>

      </div>
    </div>
  </Transition>
</template>

<style scoped>
.tp-fade-enter-active, .tp-fade-leave-active { transition: opacity 200ms ease; }
.tp-fade-enter-from, .tp-fade-leave-to { opacity: 0; }
</style>
