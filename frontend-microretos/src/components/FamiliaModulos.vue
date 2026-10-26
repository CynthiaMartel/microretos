<!-- Familia profesional y módulos en las tarjetas de las bibliotecas, cada uno en su línea con
     el mismo icono y color que en los filtros. Familia y módulos con el mismo formato (texto, sin
     chips); de los módulos se ve uno (el del filtro, si lo trabaja, en el color del rol) y un botón
     «+N» que despliega los demás (uno por línea, con un guion delante) dentro de la tarjeta. Los clics no llegan a la
     tarjeta (no la abren). Ver docs/GUIA_DISENO.md §7. -->
<script setup>
import { ref, computed } from 'vue'
import { iconoFiltro } from '../config/iconosFiltro.js'

const props = defineProps({
  familia:      { type: String, default: '' },
  modulos:      { type: Array, default: () => [] },
  moduloActivo: { type: String, default: '' },
})

let siguienteId = 0
const id = `modulos-${++siguienteId}-${Math.random().toString(36).slice(2, 7)}`
const abiertos = ref(false)
const lista = computed(() => [...new Set(props.modulos.filter(Boolean))])
const principal = computed(() =>
  props.moduloActivo && lista.value.includes(props.moduloActivo) ? props.moduloActivo : lista.value[0])
</script>

<template>
  <div v-if="familia || lista.length" class="grid gap-1.5 text-sm">
    <p v-if="familia" class="flex min-w-0 items-center gap-2">
      <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-rol/10 text-rol-fuerte" aria-hidden="true">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="iconoFiltro('Familia')" /></svg>
      </span>
      <span class="w-14 shrink-0 text-xs font-semibold text-texto-secundario">Familia</span>
      <span class="min-w-0 truncate font-semibold text-azul-noche" :title="familia">{{ familia }}</span>
    </p>
    <div v-if="lista.length" class="flex min-w-0 items-start gap-2">
      <!-- Con «+N» la primera línea mide 36px (el botón): icono y etiqueta bajan para quedar centrados con ella -->
      <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-alumnos/10 text-alumnos-dark" :class="{ 'mt-1.5': lista.length > 1 }" aria-hidden="true">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="iconoFiltro('Módulo')" /></svg>
      </span>
      <span class="w-14 shrink-0 text-xs font-semibold text-texto-secundario" :class="lista.length > 1 ? 'pt-2.5' : 'pt-px'">{{ lista.length === 1 ? 'Módulo' : 'Módulos' }}</span>
      <span class="flex min-w-0 flex-1 flex-col gap-1">
        <span class="flex min-w-0 items-center gap-1">
          <span v-if="!abiertos" :title="principal"
                class="min-w-0 truncate font-semibold"
                :class="principal === moduloActivo ? 'text-rol-fuerte' : 'text-azul-noche'">{{ principal }}</span>
          <button v-if="lista.length > 1" type="button" @click.stop="abiertos = !abiertos"
                  :aria-expanded="abiertos" :aria-controls="id"
                  :aria-label="abiertos ? 'Ocultar los módulos' : `Ver los ${lista.length} módulos`"
                  class="inline-flex h-9 shrink-0 items-center gap-1 rounded-full border border-rol-fuerte/30 bg-white px-2.5 text-xs font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
            {{ abiertos ? `${lista.length} módulos` : `+${lista.length - 1}` }}
            <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': abiertos }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
          </button>
        </span>
        <!-- Lista desplegada: mismo texto que el módulo replegado (sin recuadro), un módulo por
             línea con un guion delante para separarlos -->
        <ul v-if="abiertos" :id="id" class="space-y-0.5" @click.stop>
          <li v-for="m in lista" :key="m" class="flex min-w-0 items-start gap-1.5 leading-snug">
            <span class="shrink-0 font-semibold text-texto-secundario" aria-hidden="true">-</span>
            <span class="min-w-0 break-words font-semibold" :class="m === moduloActivo ? 'text-rol-fuerte' : 'text-azul-noche'">{{ m }}</span>
          </li>
        </ul>
      </span>
    </div>
  </div>
</template>
