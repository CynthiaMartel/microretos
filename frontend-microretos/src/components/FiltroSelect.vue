<!-- Filtro de lista larga o variable (familia, ciclo, centro, empresa…) dentro de FiltrosPanel.
     `opciones`: strings o [{ value, label }] · `todos`: texto de la opción vacía ('' = sin filtro). -->
<script setup>
import { computed } from 'vue'

const modelo = defineModel({ type: [String, Number], default: '' })

const props = defineProps({
  label:    { type: String, required: true },
  opciones: { type: Array, required: true },
  todos:    { type: String, default: 'Todos' },
})

const normalizadas = computed(() =>
  props.opciones.map(o => (typeof o === 'object' ? o : { value: o, label: o }))
)
</script>

<template>
  <label class="block min-w-0 max-w-full">
    <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">{{ label }}</span>
    <select v-model="modelo" :disabled="!normalizadas.length"
            class="h-9 w-full max-w-full rounded-xl border border-gray-200 bg-gray-50 px-3 pr-8 text-xs font-bold text-azul-noche transition-colors focus:border-centros focus:bg-white focus:outline-none disabled:opacity-50 sm:w-auto sm:min-w-44 sm:max-w-64">
      <option value="">{{ todos }}</option>
      <option v-for="o in normalizadas" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
    </select>
  </label>
</template>
