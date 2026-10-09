<!-- Cabecera de las vistas de sección (hubs, Calendario, Notificaciones…): mismo degradado
     y cinta de colores del logo que la bienvenida del panel docente (InicioDocente.vue). -->
<script setup>
defineProps({
  titulo:    { type: String, required: true },
  destacado: { type: String, default: '' },
  color:     { type: String, default: 'text-centros' },
  subtitulo: { type: String, default: '' },
})
</script>

<template>
  <section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#E4EEF9] via-[#EDF3FA] to-transparent">
    <div class="relative z-10 flex flex-wrap items-end justify-between gap-3 p-5 sm:p-6">
      <div class="min-w-0">
        <h1 class="font-heading text-2xl font-bold tracking-tight text-azul-noche sm:text-3xl">
          {{ titulo }} <span v-if="destacado" :class="color">{{ destacado }}</span>
        </h1>
        <p v-if="subtitulo" class="mt-2 max-w-2xl text-sm text-gray-600">{{ subtitulo }}</p>
      </div>
      <slot />
    </div>
    <span aria-hidden="true" class="absolute inset-x-0 bottom-0 flex h-1">
      <span class="flex-1 bg-centros" /><span class="flex-1 bg-empresas" /><span class="flex-1 bg-administraciones" /><span class="flex-1 bg-alumnos" />
    </span>
  </section>
</template>
