<!-- Tarjeta «Reto y proyecto asociado» (diseño crear_encuentro.png): cabecera con icono,
     título y «Cambiar reto»; dentro, panel con miniatura, título, pregunta reto,
     etiquetas (estado / empresa / empresa ficticia) y datos (familia, curso, ciclo). -->
<script setup>
import MiniaturaReto from './MiniaturaReto.vue'

defineProps({
  titulo:      { type: String, required: true },
  pregunta:    { type: String, default: '' },
  imagen:      { type: String, default: '' },
  empresa:     { type: String, default: '' },
  tipoEmpresa: { type: String, default: '' },   // '' · 'real' · 'ficticia'
  estado:      { type: Object, default: null }, // { label, clase }
  familia:     { type: String, default: '' },
  curso:       { type: String, default: '' },
  ciclo:       { type: String, default: '' },
  subtitulo:   { type: String, default: 'Reto de la biblioteca al que responde esta propuesta.' },
  puedeCambiar: { type: Boolean, default: false },
})
const emit = defineEmits(['cambiar'])
</script>

<template>
  <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-reto-asociado">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start">
      <div class="flex min-w-0 flex-1 items-start gap-3 sm:gap-4">
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol-fuerte text-white sm:h-12 sm:w-12" aria-hidden="true">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </span>
        <div class="min-w-0">
          <h2 id="titulo-reto-asociado" class="text-lg font-bold text-azul-noche">Reto y proyecto asociado</h2>
          <p class="mt-0.5 text-sm text-texto-secundario">{{ subtitulo }}</p>
        </div>
      </div>
      <button v-if="puedeCambiar" type="button" @click="emit('cambiar')"
              class="inline-flex h-10 shrink-0 items-center justify-center gap-2 self-start rounded-full border border-rol-fuerte/30 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Cambiar reto
      </button>
    </div>

    <div class="flex flex-col gap-4 rounded-xl bg-rol/5 p-3 sm:flex-row sm:items-center sm:p-4">
      <MiniaturaReto :src="imagen" class="h-32 w-full sm:h-28 sm:w-40" />
      <div class="min-w-0 flex-1">
        <p class="font-heading text-base font-bold leading-snug text-azul-noche break-words sm:text-lg">{{ titulo }}</p>
        <p v-if="pregunta" class="mt-1 text-sm italic leading-relaxed text-gray-700 sm:text-base">«{{ pregunta }}»</p>

        <div v-if="estado || empresa || tipoEmpresa" class="mt-2 flex flex-wrap gap-1.5">
          <span v-if="estado" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide"
                :class="estado.clase">{{ estado.label }}</span>
          <span v-if="empresa" class="inline-flex max-w-full items-center rounded-full bg-white px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-gray-700">
            <span class="truncate">{{ empresa }}</span>
          </span>
          <span v-if="tipoEmpresa === 'ficticia'"
                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-amber-800">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Empresa ficticia
          </span>
        </div>

        <div v-if="familia || curso || ciclo" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
          <span v-if="familia" class="inline-flex min-w-0 items-center gap-1.5">
            <svg class="h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="break-words">{{ familia }}</span>
          </span>
          <span v-if="curso" class="inline-flex items-center gap-1.5">
            <svg class="h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ curso }}
          </span>
          <span v-if="ciclo" class="inline-flex min-w-0 items-center gap-1.5">
            <svg class="h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m0-6l6.16-3.422A12.083 12.083 0 0118.82 17.5L12 21l-6.82-3.5a12.08 12.08 0 01.66-6.922L12 14z"/></svg>
            <span class="break-words">{{ ciclo }}</span>
          </span>
        </div>
      </div>
    </div>
  </section>
</template>
