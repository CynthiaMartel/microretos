<script setup>
defineProps({
  visible: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="visible"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @click.self="emit('cancel')">

      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('cancel')" />

      <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-8">

        <!-- Icono -->
        <div class="w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-200
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
        </div>

        <h3 class="text-xl font-black text-[#121212] text-center mb-2">Validar como docente</h3>
        <p class="text-sm text-gray-500 text-center mb-5 leading-relaxed">
          ¿Confirmas que este proyecto cumple los criterios pedagógicos?
        </p>

        <!-- Aviso independencia -->
        <div class="flex items-start gap-3 p-3 bg-amber-50 border border-amber-200 rounded-2xl mb-6">
          <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p class="text-xs text-amber-700 leading-relaxed">
            Esta validación es independiente de la de la empresa. El proyecto quedará marcado como
            <strong>Validado · Docente</strong> pero podrás seguir esperando la respuesta de la empresa.
          </p>
        </div>

        <div class="flex gap-3">
          <button @click="emit('cancel')"
                  class="flex-1 px-5 py-3 bg-gray-100 text-[#1F2937] rounded-full
                         text-xs font-black uppercase tracking-widest
                         hover:bg-gray-200 transition-all active:scale-95">
            Cancelar
          </button>
          <button @click="emit('confirm')"
                  :disabled="loading"
                  class="flex-1 px-5 py-3 bg-emerald-600 text-white rounded-full
                         text-xs font-black uppercase tracking-widest shadow-sm
                         hover:bg-emerald-700 transition-all active:scale-95
                         disabled:opacity-60 disabled:cursor-not-allowed">
            {{ loading ? 'Validando...' : 'Confirmar' }}
          </button>
        </div>

      </div>
    </div>
  </Transition>
</template>
