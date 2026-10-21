<!-- Cabecera de las vistas de sección (hubs, Calendario, Notificaciones…): mismo degradado
     y cinta de colores del logo que la bienvenida del panel docente (InicioDocente.vue). -->
<script setup>
import BotonVolver from './BotonVolver.vue'

defineProps({
  titulo:    { type: String, required: true },
  destacado: { type: String, default: '' },
  color:     { type: String, default: 'text-centros' },
  subtitulo: { type: String, default: '' },
  // Línea pequeña sobre el título (p. ej. «Taller de Ideas · Paso 3 de 7»). Opcional.
  antetitulo: { type: String, default: '' },
  // Sin fondo ni cinta de colores, título más grande: cabecera de asistentes a página
  // completa (Taller de Ideas, diseño crear_encuentro.png).
  plano:     { type: Boolean, default: false },
  // Ruta de respaldo de la flecha "volver" (ver BotonVolver). Sin ella no hay flecha:
  // las secciones del panel lateral son el primer nivel y no tienen a dónde volver.
  volver:    { type: [String, Object], default: null },
  volverLabel: { type: String, default: 'Volver' },
})
</script>

<template>
  <section class="relative overflow-hidden"
           :class="plano ? '' : 'rounded-2xl bg-gradient-to-r from-[#E4EEF9] via-[#EDF3FA] to-transparent'">
    <div class="relative z-10 flex flex-wrap justify-between gap-3"
         :class="plano ? 'items-start py-1 sm:flex-nowrap' : 'items-end p-5 sm:p-6'">
      <div class="min-w-0" :class="plano ? 'flex-1' : ''">
        <BotonVolver v-if="volver" :fallback="volver" :label="volverLabel" class="-mt-1 mb-2" />
        <p v-if="antetitulo" class="mb-1 text-xs font-bold uppercase tracking-[0.15em] text-gray-600">{{ antetitulo }}</p>
        <h1 class="font-heading text-2xl font-bold tracking-tight text-azul-noche"
            :class="plano ? 'sm:text-4xl' : 'sm:text-3xl'">
          {{ titulo }} <span v-if="destacado" :class="color">{{ destacado }}</span>
        </h1>
        <p v-if="subtitulo" class="mt-2 max-w-2xl text-gray-600" :class="plano ? 'text-sm sm:text-base' : 'text-sm'">{{ subtitulo }}</p>
      </div>
      <slot />
    </div>
    <span v-if="!plano" aria-hidden="true" class="absolute inset-x-0 bottom-0 flex h-1">
      <span class="flex-1 bg-centros" /><span class="flex-1 bg-empresas" /><span class="flex-1 bg-administraciones" /><span class="flex-1 bg-alumnos" />
    </span>
  </section>
</template>
