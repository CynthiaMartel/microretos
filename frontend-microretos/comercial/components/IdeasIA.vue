<!-- Ideas de la IA para una pregunta de la entrevista, por si la empresa se queda en blanco.
     Son ayuda para el comercial: preguntas para tirar del hilo y situaciones típicas de su
     sector. Una idea solo pasa a la respuesta si la empresa confirma que es así («Insertar»),
     y queda como texto editable. -->
<script setup>
defineProps({
  ideas:    { type: Object, default: null }, // { preguntas, ejemplos, opciones? }
  cargando: { type: Boolean, default: false },
  error:    { type: String, default: '' },
  // Opciones ya marcadas (para no ofrecer marcarlas otra vez)
  marcadas: { type: Array, default: () => [] },
})
defineEmits(['actualizar', 'insertar', 'marcar'])
</script>

<template>
  <section class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 sm:p-5" aria-label="Ideas de la IA" aria-live="polite">
    <p class="text-sm text-texto-secundario">
      Pregúntale si le pasa algo así. Inserta una idea solo si la empresa te confirma que es así: luego podrás editarla.
    </p>

    <div v-if="cargando" class="mt-4 space-y-2" aria-busy="true">
      <div class="h-4 w-3/4 animate-pulse rounded-full bg-gray-200" />
      <div class="h-4 w-2/3 animate-pulse rounded-full bg-gray-200" />
      <div class="h-10 w-full animate-pulse rounded-xl bg-gray-200" />
    </div>

    <div v-else-if="error" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
      <p role="alert" class="min-w-0 flex-1 break-words text-sm font-medium text-red-700">{{ error }}</p>
      <button type="button" class="accion-secundaria w-fit" @click="$emit('actualizar')">Reintentar</button>
    </div>

    <template v-else-if="ideas">
      <div v-if="ideas.preguntas?.length" class="mt-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-texto-secundario">Para tirar del hilo</h3>
        <ul class="mt-2 space-y-1.5 text-sm">
          <li v-for="p in ideas.preguntas" :key="p" class="flex gap-2">
            <span class="text-rol-fuerte" aria-hidden="true">›</span><span class="min-w-0 break-words">{{ p }}</span>
          </li>
        </ul>
      </div>

      <div v-if="ideas.opciones?.some(o => !marcadas.includes(o))" class="mt-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-texto-secundario">Opciones probables</h3>
        <div class="mt-2 flex flex-wrap gap-2">
          <button v-for="o in ideas.opciones.filter(o => !marcadas.includes(o))" :key="o" type="button"
                  class="chip border-dashed" @click="$emit('marcar', o)">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
            Lo confirma: {{ o }}
          </button>
        </div>
      </div>

      <div v-if="ideas.ejemplos?.length" class="mt-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-texto-secundario">Situaciones típicas de su sector</h3>
        <ul class="mt-2 space-y-2">
          <li v-for="e in ideas.ejemplos" :key="e" class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-3 sm:flex-row sm:items-center">
            <p class="min-w-0 flex-1 break-words text-sm">{{ e }}</p>
            <button type="button" class="accion-secundaria w-fit shrink-0" @click="$emit('insertar', e)">Lo confirma · insertar</button>
          </li>
        </ul>
      </div>

      <p v-if="!ideas.preguntas?.length && !ideas.ejemplos?.length && !ideas.opciones?.length" class="mt-4 text-sm text-texto-secundario">
        La IA no ha propuesto nada para esta pregunta.
      </p>

      <button type="button" class="accion-secundaria mt-4 w-fit" @click="$emit('actualizar')">Actualizar con lo ya contado</button>
    </template>
  </section>
</template>
