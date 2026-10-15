<!-- Enlace a algo que aún no existe: en vez de navegar, muestra "Próximamente" al pasar
     el ratón, con foco de teclado o al tocar (en táctil no hay hover) -->
<script setup>
import { ref, onBeforeUnmount } from 'vue'

const abierto = ref(false)
let timer = null
function avisar() {
  abierto.value = true
  clearTimeout(timer)
  timer = setTimeout(() => { abierto.value = false }, 2000)
}
onBeforeUnmount(() => clearTimeout(timer))

const id = `proximamente-${Math.random().toString(36).slice(2, 9)}`
</script>

<template>
  <span class="relative group inline-flex">
    <button type="button" class="cursor-default" :aria-describedby="id" @click="avisar">
      <slot />
    </button>
    <span :id="id" role="tooltip"
          class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1 whitespace-nowrap rounded-md bg-azul-noche px-2 py-1 text-[11px] font-semibold text-white shadow-lg transition-opacity
                 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100"
          :class="abierto ? 'opacity-100' : ''">
      Próximamente
    </span>
  </span>
</template>
