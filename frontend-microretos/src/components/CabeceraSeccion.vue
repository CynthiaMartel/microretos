<!-- Cabecera común de todas las vistas (regla de diseño, docs/GUIA_DISENO.md §7; referencia
     biblioteca_retos.png): sin fondo, antetítulo con la sección, título grande con la palabra
     destacada, subtítulo, cinta corta de los 4 colores de marca y halos de marca a la derecha,
     detrás del contenido. Las acciones de la vista van en el slot (a la derecha).
     El antetítulo, si no se pasa, sale de la sección del menú lateral en la que se está. -->
<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BotonVolver from './BotonVolver.vue'
import HalosMarca from './HalosMarca.vue'
import { GRUPOS_NAV } from '../config/navegacion.js'

const props = defineProps({
  titulo:    { type: String, required: true },
  destacado: { type: String, default: '' },
  color:     { type: String, default: 'text-centros' },
  subtitulo: { type: String, default: '' },
  // Línea pequeña sobre el título (p. ej. «Taller de Ideas · Paso 3 de 7»). Sin ella, el nombre
  // de la sección del menú lateral activa; '' explícito no se puede forzar: usar `sinAntetitulo`.
  antetitulo: { type: String, default: '' },
  sinAntetitulo: { type: Boolean, default: false },
  // Halos de marca a la derecha (por defecto sí). Desactivar solo si chocan con algo concreto.
  halos:     { type: Boolean, default: true },
  // Ruta de respaldo de la flecha "volver" (ver BotonVolver). Sin ella no hay flecha:
  // las secciones del panel lateral son el primer nivel y no tienen a dónde volver.
  volver:    { type: [String, Object], default: null },
  volverLabel: { type: String, default: 'Volver' },
  // Compatibilidad con usos anteriores: la cabecera ya es siempre así (no hacen nada).
  plano:     { type: Boolean, default: false },
  cinta:     { type: Boolean, default: true },
})

const route = useRoute()
const seccionActiva = computed(() =>
  GRUPOS_NAV.flatMap(g => g.items).find(i => i.activa?.(route.path))?.label || '')
const antetituloFinal = computed(() =>
  props.sinAntetitulo ? '' : (props.antetitulo || seccionActiva.value))
</script>

<template>
  <section class="relative">
    <!-- Halos de marca: decorativos, difuminados hacia la izquierda y abajo para no restar lectura.
         El recorte y el redondeo van en su propia capa: si los llevara la sección, la esquina
         redondeada cortaría el antetítulo o la flecha volver (texto pegado al borde izquierdo). -->
    <div v-if="halos" class="pointer-events-none absolute inset-0 hidden overflow-hidden rounded-2xl sm:block" aria-hidden="true">
      <HalosMarca
                  class="absolute -top-6 right-0 h-[calc(100%+3rem)] w-[62%] opacity-40 [mask-image:linear-gradient(to_right,transparent,black_35%),linear-gradient(to_bottom,black_55%,transparent)] [mask-composite:intersect] [-webkit-mask-image:linear-gradient(to_right,transparent,black_35%),linear-gradient(to_bottom,black_55%,transparent)] [-webkit-mask-composite:source-in]" />
    </div>
    <div class="relative z-10 flex flex-wrap items-start justify-between gap-3 py-1 sm:flex-nowrap">
      <div class="min-w-0 flex-1 basis-full sm:basis-0">
        <BotonVolver v-if="volver" :fallback="volver" :label="volverLabel" class="-mt-1 mb-2" />
        <p v-if="antetituloFinal" class="mb-1 text-xs font-bold uppercase tracking-[0.15em] text-texto-secundario">{{ antetituloFinal }}</p>
        <h1 class="font-heading text-2xl font-bold tracking-tight text-azul-noche sm:text-4xl">
          {{ titulo }} <span v-if="destacado" :class="color">{{ destacado }}</span>
        </h1>
        <p v-if="subtitulo" class="mt-2 max-w-2xl text-sm text-texto-secundario sm:text-base">{{ subtitulo }}</p>
        <span v-if="cinta" aria-hidden="true" class="mt-3 flex h-1 w-full max-w-md gap-1.5">
          <span class="flex-1 rounded-full bg-centros" /><span class="flex-1 rounded-full bg-empresas" /><span class="flex-1 rounded-full bg-alumnos" /><span class="flex-1 rounded-full bg-administraciones" />
        </span>
      </div>
      <slot />
    </div>
  </section>
</template>
