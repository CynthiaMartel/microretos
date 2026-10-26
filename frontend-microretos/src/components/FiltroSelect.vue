<!-- Filtro de lista larga o variable (familia, ciclo, centro, empresa…) dentro de FiltrosPanel.
     `opciones`: strings o [{ value, label }] · `todos`: texto de la opción vacía ('' = sin filtro). -->
<script setup>
import { computed } from 'vue'
import { iconoFiltro } from '../config/iconosFiltro.js'

const modelo = defineModel({ type: [String, Number], default: '' })

const props = defineProps({
  label:    { type: String, required: true },
  opciones: { type: Array, required: true },
  todos:    { type: String, default: 'Todos' },
  // Icono del cuadradito de la izquierda (regla en todas las vistas, GUIA_DISENO §7). Por defecto
  // sale de la etiqueta (config/iconosFiltro.js); `icono` (atributo d de 24px) lo fuerza.
  icono:    { type: String, default: '' },
})

const normalizadas = computed(() =>
  props.opciones.map(o => (typeof o === 'object' ? o : { value: o, label: o }))
)
</script>

<template>
  <div class="flex min-w-0 max-w-full items-end gap-2">
    <span aria-hidden="true"
          class="mb-px inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="icono || iconoFiltro(label)" /></svg>
    </span>
  <label class="block min-w-0 max-w-full">
    <span class="mb-1 block text-xs font-semibold text-azul-noche">{{ label }}</span>
    <select v-model="modelo" :disabled="!normalizadas.length"
            class="h-9 w-full max-w-full rounded-xl border border-borde bg-white px-3 pr-8 text-sm font-medium text-azul-noche transition-colors focus:border-rol-fuerte focus:outline-none disabled:opacity-50 sm:w-auto sm:min-w-40 sm:max-w-56 [.filtros-separados_&]:lg:min-w-32 [.filtros-separados_&]:lg:max-w-40 [.filtros-separados_&]:lg:pl-2.5 [.filtros-separados_&]:lg:pr-7 [.filtros-separados_&]:lg:text-[13px]">
      <option value="">{{ todos }}</option>
      <option v-for="o in normalizadas" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
    </select>
  </label>
  </div>
</template>
