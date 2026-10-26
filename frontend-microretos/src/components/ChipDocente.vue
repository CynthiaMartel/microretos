<!-- Chip del docente propietario de un encuentro compartido contigo (colaboración entre
     docentes): «Docente: Nombre», con icono de compartir y, si no puedes editar, «· solo lectura».
     Mismo chip en Encuentros, Mis grupos y la Biblioteca de diagnósticos. -->
<script setup>
defineProps({
  nombre:      { type: String, required: true },
  soloLectura: { type: Boolean, default: false },
})
</script>

<template>
  <span class="inline-flex min-w-0 max-w-full items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-azul-noche"
        :title="`Encuentro de ${nombre}, compartido contigo${soloLectura ? ' en solo lectura' : ''}`">
    <svg class="h-3.5 w-3.5 shrink-0 text-texto-secundario" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
    <span class="shrink-0 font-normal text-texto-secundario">Docente:</span>
    <span class="truncate">{{ nombre }}</span>
    <span v-if="soloLectura" class="shrink-0 font-normal text-texto-secundario">· solo lectura</span>
  </span>
</template>
