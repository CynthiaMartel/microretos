<!-- Chip con el rol de un alumno dentro de su grupo (config/rolesGrupo.js). Con `ayuda` (por
     defecto) lleva al lado una «i» que explica el rol al pasar el ratón, al llegar con el
     teclado o al pulsarla (móvil). Dentro de un botón (p. ej. la fila desplegable de un grupo)
     no puede haber otro botón: ahí se usa sin ayuda y la explicación va en el `title`. -->
<script setup>
import { ref, computed, onBeforeUnmount, watch } from 'vue'
import { rolGrupo } from '../config/rolesGrupo.js'

const props = defineProps({
  rol:    { type: String, required: true },
  ayuda:  { type: Boolean, default: true },
  // Con «Rol:» delante (cuando no está junto al nombre del alumno)
  conPrefijo: { type: Boolean, default: false },
})

const info = computed(() => rolGrupo(props.rol))
const abierta = ref(false)
const raiz = ref(null)
const id = `rol-${Math.random().toString(36).slice(2, 8)}`

function cerrarFuera(e) { if (!raiz.value?.contains(e.target)) abierta.value = false }
// Escape cierra y quita el foco de la «i» (si no, :focus-within la seguiría mostrando)
function cerrarEsc(e) {
  if (e.key !== 'Escape') return
  abierta.value = false
  if (raiz.value?.contains(document.activeElement)) document.activeElement.blur()
}
watch(abierta, (v) => {
  const m = v ? 'addEventListener' : 'removeEventListener'
  document[m]('click', cerrarFuera, true)
  document[m]('keydown', cerrarEsc)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', cerrarFuera, true)
  document.removeEventListener('keydown', cerrarEsc)
})
</script>

<template>
  <span v-if="info" ref="raiz" class="group/rol relative inline-flex max-w-full items-center gap-0.5">
    <span class="inline-flex max-w-full items-center gap-1 truncate rounded-full px-2 py-0.5 text-xs font-semibold"
          :class="info.clase" :title="ayuda ? undefined : `Rol en el grupo: ${info.label}. ${info.ayuda}`">
      <span v-if="conPrefijo" class="font-normal">Rol:</span> {{ info.label }}
    </span>
    <template v-if="ayuda">
      <button type="button" @click.stop="abierta = !abierta" :aria-expanded="abierta" :aria-describedby="id"
              :aria-label="`¿Qué hace el rol ${info.label}?`"
              class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 hover:text-azul-noche">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </button>
      <span :id="id" role="tooltip"
            class="pointer-events-none absolute left-0 top-full z-40 mt-1 hidden w-[min(15rem,calc(100vw-3rem))] rounded-xl bg-azul-noche px-3 py-2 text-xs font-normal leading-snug text-white shadow-lg group-hover/rol:block group-focus-within/rol:block"
            :class="{ '!block': abierta }">
        <span class="font-semibold">{{ info.label }}.</span> {{ info.ayuda }}
      </span>
    </template>
  </span>
</template>
