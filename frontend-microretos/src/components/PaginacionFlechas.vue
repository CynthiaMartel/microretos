<!-- Controles compactos ‹ 1 / N › para listas paginadas en cliente (usePaginacion).
     Solo se muestran si hay más de una página. -->
<script setup>
const pagina = defineModel({ type: Number, required: true })
defineProps({
  total: { type: Number, required: true },
  etiqueta: { type: String, default: 'Paginación' },
})
</script>

<template>
  <nav v-if="total > 1" class="flex items-center justify-center gap-2" :aria-label="etiqueta">
    <button type="button" class="flecha" :disabled="pagina === 0" aria-label="Página anterior" @click="pagina--">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <span class="text-xs text-gray-500">{{ pagina + 1 }} / {{ total }}</span>
    <button type="button" class="flecha" :disabled="pagina >= total - 1" aria-label="Página siguiente" @click="pagina++">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </button>
  </nav>
</template>

<style scoped>
@reference "../style.css";

.flecha { @apply flex h-7 w-7 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-centros disabled:opacity-30 disabled:hover:bg-transparent; }
</style>
