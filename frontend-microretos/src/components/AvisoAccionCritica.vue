<!-- Aviso dentro de los modales de las acciones críticas de Central (validar o descartar empresas y
     centros, publicar retos, papelera…): queda en el Historial con el nombre de quien la hace. -->
<script setup>
defineProps({
  texto: { type: String, default: '' },
})
</script>

<template>
  <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3" role="note">
    <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <p class="text-xs text-red-800">
      <strong class="block text-sm">Acción crítica</strong>
      {{ texto || 'Cambia datos que usan los centros. Queda registrada en el Historial de Central con tu nombre y la fecha: revísalo bien antes de confirmar.' }}
    </p>
  </div>
</template>
