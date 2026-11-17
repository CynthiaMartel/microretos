<!-- «Instalar app» en la barra superior de cada herramienta (app DuaLab, comercial, Central, Nexo):
     antes estaba al final del panel lateral y obligaba a hacer scroll. Diálogo nativo en
     Chrome/Edge; en Safari y Firefox, instrucciones (useInstalarApp + InstalarAppModal). No se
     pinta si la app ya está instalada. En móvil, solo el icono (círculo); en sm+, con texto. -->
<script setup>
import { ref } from 'vue'
import { useInstalarApp } from '../composables/useInstalarApp.js'
import InstalarAppModal from './InstalarAppModal.vue'

defineProps({
  nombre:     { type: String, default: 'DuaLab' },
  rutaInicio: { type: String, default: '/login' },
  // 'oscuro': barra azul noche (app, comercial, Central) · 'claro': fondo blanco (Nexo)
  tono:       { type: String, default: 'oscuro' },
})

const { puedeInstalar, instalar, plataforma } = useInstalarApp()
const instrucciones = ref(false)
async function abrir() {
  if (!(await instalar())) instrucciones.value = true
}
</script>

<template>
  <button v-if="puedeInstalar" type="button" @click="abrir" :title="`Instalar ${nombre} como app`" :aria-label="`Instalar ${nombre} como app`"
          class="inline-flex h-9 min-w-9 shrink-0 items-center justify-center gap-1.5 rounded-full border px-2 text-sm font-semibold transition-colors sm:px-3"
          :class="tono === 'claro' ? 'border-borde bg-white text-azul-noche hover:bg-gray-50' : 'border-white/20 text-white hover:bg-white/10'">
    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14" />
    </svg>
    <span class="hidden sm:inline">Instalar app</span>
  </button>
  <InstalarAppModal :abierto="instrucciones" :plataforma="plataforma" :nombre="nombre" :ruta-inicio="rutaInicio" @cerrar="instrucciones = false" />
</template>
