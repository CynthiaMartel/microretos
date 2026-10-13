<!-- Tarjeta de filtros común a todas las vistas con listados. Siempre el mismo orden, para que
     el usuario aprenda dónde está cada cosa:
       fila 1 · buscador (ancho) + selector de curso académico fijo a la derecha (slot #curso)
       fila 2 · filtros propios de la vista (slot por defecto: FiltroSegmentado / FiltroSelect)
       pie    · "N resultados" a la izquierda y "Limpiar filtros" a la derecha (solo si hay activos)
     v-model:busqueda (opcional; sin él no hay buscador) · `resultados`: número o null -->
<script setup>
const busqueda = defineModel('busqueda', { type: String, default: null })

defineProps({
  placeholder: { type: String, default: 'Buscar…' },
  resultados:  { type: Number, default: null },
  // texto del contador: singular y plural
  unidad:      { type: Array, default: () => ['resultado', 'resultados'] },
  hayActivos:  { type: Boolean, default: false },
})
defineEmits(['limpiar'])
</script>

<template>
  <section class="space-y-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5" aria-label="Filtros">
    <div v-if="busqueda !== null || $slots.curso" class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <div v-if="busqueda !== null" class="relative min-w-0 flex-1">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
        </svg>
        <input v-model="busqueda" type="search" :placeholder="placeholder" :aria-label="placeholder"
               class="h-10 w-full rounded-xl border border-gray-200 bg-gray-50 pl-9 pr-9 text-sm text-azul-noche placeholder-gray-400 transition-colors focus:border-centros focus:bg-white focus:outline-none" />
        <button v-if="busqueda" type="button" @click="busqueda = ''" aria-label="Borrar búsqueda"
                class="absolute right-1 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-gray-400 hover:text-gray-600">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div v-if="$slots.curso" class="shrink-0 sm:ml-auto"><slot name="curso" /></div>
    </div>

    <div v-if="$slots.default" class="flex flex-wrap items-end gap-x-4 gap-y-3">
      <slot />
    </div>

    <div v-if="resultados !== null || hayActivos"
         class="flex min-h-9 items-center justify-between gap-3 border-t border-gray-100 pt-3">
      <span v-if="resultados !== null" class="text-xs font-semibold text-gray-500">
        {{ resultados }} {{ resultados === 1 ? unidad[0] : unidad[1] }}
      </span>
      <button v-if="hayActivos" type="button" @click="$emit('limpiar')"
              class="ml-auto inline-flex min-h-9 items-center gap-1.5 text-xs font-black uppercase tracking-wider text-gray-500 transition-colors hover:text-red-500">
        Limpiar filtros
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </section>
</template>
