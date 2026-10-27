<!-- Título de los resultados de las bibliotecas (Retos, Encuentros, Proyectos, Mis grupos).
     Sin niveles elegidos: solo el número de resultados (slot). Con familia (una sola), ciclo o
     módulo elegidos: arriba los niveles en píldoras iguales (icono, «Etiqueta: valor» y ✕), el
     último resaltado; debajo, ese último valor como título y el número de resultados.
     Cada nivel: { etiqueta, valor, subir(), quitar() } — `subir` vuelve a ese nivel (quita los de
     debajo) y `quitar` lo borra. La vista decide qué depende de qué. Ver docs/GUIA_DISENO.md §7. -->
<script setup>
import { computed } from 'vue'
import { iconoFiltro } from '../config/iconosFiltro.js'

const props = defineProps({
  niveles:  { type: Array, default: () => [] },
  cargando: { type: Boolean, default: false },
})
const conNiveles = computed(() => props.niveles.length > 0 && !props.cargando)
const ultimo = computed(() => props.niveles.at(-1))
</script>

<template>
  <!-- Con niveles ocupa toda la fila (también en escritorio): lo que venga después en la barra
       de resultados (PDF, ordenar, vista) baja a la fila siguiente y el título no se estrecha -->
  <div class="min-w-0 flex-1" :class="{ 'basis-full': conNiveles }">
    <template v-if="conNiveles">
      <nav aria-label="Niveles elegidos" class="mb-1.5 flex min-w-0 flex-wrap items-center gap-1 text-xs">
        <!-- Cada «›» va pegado a su píldora: al saltar de línea no queda suelto al principio -->
        <span v-for="(n, i) in niveles" :key="n.etiqueta" class="inline-flex min-w-0 max-w-full items-center gap-1">
          <svg v-if="i > 0" class="h-3.5 w-3.5 shrink-0 text-texto-secundario" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          <span class="inline-flex min-h-8 min-w-0 max-w-full items-center rounded-full border"
                :class="i === niveles.length - 1 ? 'border-rol-fuerte/30 bg-rol/10' : 'border-borde bg-white'">
            <button type="button" @click="n.subir()" :disabled="i === niveles.length - 1"
                    :title="i === niveles.length - 1 ? n.valor : `Volver a ${n.valor}`"
                    class="inline-flex min-w-0 items-center gap-1.5 rounded-l-full py-1 pl-2.5 pr-1 text-left enabled:hover:underline disabled:cursor-default">
              <svg class="h-3.5 w-3.5 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="iconoFiltro(n.etiqueta)" /></svg>
              <span class="shrink-0 text-texto-secundario">{{ n.etiqueta }}:</span>
              <span class="min-w-0 truncate font-semibold" :class="i === niveles.length - 1 ? 'text-rol-fuerte' : 'text-azul-noche'">{{ n.valor }}</span>
            </button>
            <button type="button" @click="n.quitar()" :aria-label="`Quitar filtro ${n.etiqueta}: ${n.valor}`" title="Quitar este filtro"
                    class="relative mr-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-texto-secundario before:absolute before:-inset-1 before:content-[''] hover:bg-white hover:text-azul-noche">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </span>
        </span>
      </nav>
      <h2 class="min-w-0 break-words font-heading text-xl font-bold leading-tight text-azul-noche sm:text-2xl">{{ ultimo.valor }}</h2>
    </template>
    <p :class="conNiveles ? 'mt-0.5 text-sm text-texto-secundario' : 'text-base font-semibold text-azul-noche sm:text-lg'" aria-live="polite">
      <slot />
    </p>
    <slot name="extra" />
  </div>
</template>
