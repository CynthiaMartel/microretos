<script setup>
import { ref, onMounted } from 'vue';

// Recordatorio que parpadea al entrar/recargar esta vista, para explicar la diferencia
// entre "propuesta" y "proyecto" validado. Tras el parpadeo queda fijo hasta que el
// usuario lo cierra con la "x".
const visible     = ref(false);
const parpadeando = ref(true);

function onParpadeoFin() {
  parpadeando.value = false;
}
function cerrar() {
  visible.value = false;
}

onMounted(() => {
  visible.value = true;
});
</script>

<template>
  <Transition name="rp-fade">
    <div
      v-if="visible"
      class="inline-flex items-start gap-2 max-w-xs sm:max-w-sm z-20
             px-4 py-2.5 rounded-2xl shadow-md text-xs sm:text-sm leading-snug
             bg-amber-50 border border-amber-300 text-amber-800"
      :class="{ 'rp-parpadeo': parpadeando }"
      @animationend="onParpadeoFin"
    >
      <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span class="flex-1">
        <strong class="font-black">Recuerda:</strong> una propuesta es un proyecto que aún no está validado.
        Para convertir la propuesta en proyecto debes validarlo como docente y/o como empresa.
      </span>
      <button
        @click="cerrar"
        class="shrink-0 -mt-0.5 -mr-1 p-0.5 rounded-full text-amber-500/70 hover:text-amber-700 hover:bg-amber-100 transition-colors"
        title="Cerrar"
        aria-label="Cerrar recordatorio"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </Transition>
</template>

<style scoped>
.rp-fade-enter-active, .rp-fade-leave-active { transition: opacity 200ms ease; }
.rp-fade-enter-from, .rp-fade-leave-to { opacity: 0; }

.rp-parpadeo {
  animation: rp-parpadeo 0.9s ease-in-out 3;
}
@keyframes rp-parpadeo {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.35; }
}
</style>
