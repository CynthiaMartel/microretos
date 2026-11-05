<!-- Bocadillo "Sin conexión": con el service worker la app abre aunque no haya red, pero
     todo lo que viene de la API falla; así se entiende por qué. Va dentro de la pila de
     bocadillos de App.vue y desaparece solo al volver la conexión. -->
<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const sinConexion = ref(!navigator.onLine)
const actualizar = () => { sinConexion.value = !navigator.onLine }

onMounted(() => {
  window.addEventListener('online', actualizar)
  window.addEventListener('offline', actualizar)
})
onUnmounted(() => {
  window.removeEventListener('online', actualizar)
  window.removeEventListener('offline', actualizar)
})
</script>

<template>
  <Transition name="toast-slide">
    <div v-if="sinConexion" role="status"
         class="pointer-events-auto flex items-start gap-3 rounded-2xl border border-white/15 bg-[#1a2332]/95 p-4 shadow-2xl backdrop-blur-sm">
      <svg class="mt-0.5 h-5 w-5 shrink-0 text-alumnos" fill="none" stroke="currentColor" viewBox="0 0 24 24"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 2l20 20M8.5 16.5a5 5 0 017 0M5 12.9a10 10 0 015.2-2.8M19 12.9a10 10 0 00-2.2-1.6M2 8.8a15 15 0 014.2-2.6M22 8.8A15 15 0 0011 5.1M12 20h.01" />
      </svg>
      <div class="min-w-0">
        <p class="text-sm font-bold text-white">Sin conexión</p>
        <p class="mt-0.5 text-xs text-white/70">
          No se pueden cargar ni guardar datos hasta que vuelva la conexión.
        </p>
      </div>
    </div>
  </Transition>
</template>
