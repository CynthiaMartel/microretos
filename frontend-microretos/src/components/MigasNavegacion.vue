<!-- "Dónde estás" dentro de una vista con capas (familias → familia → ciclo/módulo…).
     Solo orienta: la ruta de niveles superiores (cada uno enlaza a su capa) y el nivel actual
     destacado. No lleva flecha: "volver" está siempre en la cabecera (CabeceraSeccion), y como
     las capas viven en la URL esa flecha y el atrás del navegador suben de capa.
     `items`: [{ label, to } | { label, accion }] niveles superiores, de fuera hacia dentro
     (`accion` para niveles que son un filtro, no una ruta: p. ej. quitar el ciclo elegido)
     · `actual`: nivel actual. En vistas por familia, construirlos con utils/migasFamilia.js.
     Slot por defecto: contenido a la derecha del nivel actual (contador, descarga…). -->
<script setup>
defineProps({
  items:  { type: Array, default: () => [] },
  actual: { type: String, required: true },
})
</script>

<template>
  <nav aria-label="Dónde estás" class="flex flex-col gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:px-5">
    <div class="min-w-0 flex-1">
      <ol v-if="items.length" class="flex min-w-0 flex-wrap items-center gap-x-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">
        <li v-for="item in items" :key="item.label" class="flex min-w-0 items-center gap-1.5">
          <RouterLink v-if="item.to" :to="item.to" class="relative inline-flex min-h-7 min-w-0 items-center transition-colors before:absolute before:-inset-1 before:content-[''] hover:text-centros">
            <span class="min-w-0 truncate">{{ item.label }}</span>
          </RouterLink>
          <button v-else type="button" @click="item.accion?.()"
                  class="relative inline-flex min-h-7 min-w-0 items-center uppercase tracking-[0.2em] transition-colors before:absolute before:-inset-1 before:content-[''] hover:text-centros">
            <span class="min-w-0 truncate">{{ item.label }}</span>
          </button>
          <span aria-hidden="true">›</span>
        </li>
      </ol>
      <h2 class="break-words text-lg font-black leading-snug text-azul-noche sm:text-xl" aria-current="location">{{ actual }}</h2>
    </div>
    <slot />
  </nav>
</template>
