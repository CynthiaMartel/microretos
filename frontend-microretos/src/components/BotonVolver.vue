<!-- Flecha "volver" a la vista anterior. Si se llegó navegando dentro de la app vuelve
     atrás en el historial (a la vista exacta de la que se venía: hub, biblioteca…); si se
     entró por enlace directo o recarga no hay vista anterior y lleva a `fallback`, el
     nivel de arriba de esta vista. Mismo estilo que las migas de la biblioteca de proyectos. -->
<script setup>
import { useVolver } from '../composables/useVolver.js'

const props = defineProps({
  fallback: { type: [String, Object], required: true },
  label:    { type: String, default: 'Volver' },
})

const volverAtras = useVolver()
const volver = () => volverAtras(props.fallback)
</script>

<template>
  <button type="button" @click="volver"
          class="group inline-flex min-h-9 items-center gap-2 text-xs font-black uppercase tracking-widest text-gray-500 transition-colors hover:text-centros">
    <svg class="h-4 w-4 shrink-0 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    {{ label }}
  </button>
</template>
