<!-- Aviso del modo demo del Generador de Retos: explica por qué los campos están bloqueados
     (datos de una empresa de ejemplo) y ofrece la salida. Visible en cada paso, no solo con
     hover: los campos deshabilitados no muestran tooltips fiables y en móvil no hay hover. -->
<script setup>
defineProps({
  // 'pasos': aviso de los pasos 1–3 · 'retos': aviso sobre los retos de ejemplo generados
  contexto: { type: String, default: 'pasos' },
})
defineEmits(['salir'])
</script>

<template>
  <div role="note"
       class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-2xl border border-centros/20 bg-centros/5 px-4 sm:px-5 py-3">
    <div class="flex items-start gap-3 min-w-0 flex-1">
      <svg class="w-5 h-5 mt-0.5 text-centros shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z"/>
      </svg>
      <p v-if="contexto === 'retos'" class="min-w-0 break-words text-sm text-gray-700 leading-relaxed">
        <strong class="font-bold text-centros">Retos de ejemplo de la demo.</strong>
        Están preparados de antemano para enseñar el resultado: no se guardan en tu centro ni gastan IA.
        Para crear y guardar los tuyos, sal de la demo.
      </p>
      <p v-else class="min-w-0 break-words text-sm text-gray-700 leading-relaxed">
        <strong class="font-bold text-centros">Estás viendo una demo.</strong>
        Los datos son de una empresa de ejemplo y están bloqueados para enseñar el proceso tal cual.
        Puedes avanzar por los pasos y generar los retos de ejemplo; no se guarda nada.
      </p>
    </div>
    <button type="button" @click="$emit('salir')"
      class="self-start sm:self-auto shrink-0 inline-flex items-center justify-center gap-2 min-h-[36px] px-4 py-2 rounded-full border border-centros/30 bg-white text-centros text-xs font-bold uppercase tracking-widest shadow-sm transition-colors hover:bg-centros hover:text-white">
      Salir de la demo
    </button>
  </div>
</template>
