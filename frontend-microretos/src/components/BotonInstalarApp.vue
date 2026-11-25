<!-- «Instalar app» de cada herramienta. Diálogo nativo en Chrome/Edge; en Safari y Firefox,
     instrucciones (useInstalarApp + InstalarAppModal). No se pinta si la app ya está instalada.
     · variante 'panel' (app DuaLab, comercial, Central): fila a todo el ancho al pie del panel
       lateral, fija fuera de la zona con scroll (antes, al final de la lista, obligaba a hacer
       scroll; luego pasó a la barra superior, que en móvil iba sin sitio).
     · variante 'barra' (Nexo, sin panel lateral): en móvil solo el icono (círculo); en sm+, con texto.
     Los atributos (class, data-tour…) van al botón, no al modal. -->
<script setup>
import { ref } from 'vue'
import { useInstalarApp } from '../composables/useInstalarApp.js'
import InstalarAppModal from './InstalarAppModal.vue'

defineOptions({ inheritAttrs: false })

defineProps({
  nombre:     { type: String, default: 'DuaLab' },
  rutaInicio: { type: String, default: '/login' },
  // 'oscuro': fondo azul noche (barras y paneles) · 'claro': fondo blanco (Nexo)
  tono:       { type: String, default: 'oscuro' },
  variante:   { type: String, default: 'barra' },
})

const { puedeInstalar, instalar, plataforma } = useInstalarApp()
const instrucciones = ref(false)
async function abrir() {
  if (!(await instalar())) instrucciones.value = true
}
</script>

<template>
  <button v-if="puedeInstalar && variante === 'panel'" v-bind="$attrs" type="button" @click="abrir" :title="`Instalar ${nombre} como app`"
          class="flex min-h-11 w-full items-center gap-2.5 rounded-2xl border border-white/15 bg-white/5 px-3 text-left text-sm font-bold text-white/80 transition-colors hover:bg-white/10 hover:text-white">
    <svg class="h-[17px] w-[17px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14" />
    </svg>
    <span class="min-w-0 flex-1 truncate">Instalar app</span>
  </button>
  <button v-else-if="puedeInstalar" v-bind="$attrs" type="button" @click="abrir" :title="`Instalar ${nombre} como app`" :aria-label="`Instalar ${nombre} como app`"
          class="inline-flex h-9 min-w-9 shrink-0 items-center justify-center gap-1.5 rounded-full border px-2 text-sm font-semibold transition-colors sm:px-3"
          :class="tono === 'claro' ? 'border-borde bg-white text-azul-noche hover:bg-gray-50' : 'border-white/20 text-white hover:bg-white/10'">
    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14" />
    </svg>
    <span class="hidden sm:inline">Instalar app</span>
  </button>
  <InstalarAppModal :abierto="instrucciones" :plataforma="plataforma" :nombre="nombre" :ruta-inicio="rutaInicio" @cerrar="instrucciones = false" />
</template>
