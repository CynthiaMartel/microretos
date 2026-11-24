<!-- «Ver cómo lo ve el docente»: el formulario de solicitud de retos tal cual lo abre el docente
     (SolicitudRetoDocente.vue en modo vista previa), dentro de un modal. Se puede recorrer, pero no
     guarda nada ni usa ningún enlace. Con `retos`, la pantalla de retos que abrirá con el aviso. -->
<script setup>
import { onBeforeUnmount, watch } from 'vue'
import SolicitudRetoDocente from '../../src/views/SolicitudRetoDocente.vue'

const props = defineProps({
  abierto: { type: Boolean, default: false },
  // { nombreDocente, centroNombre }: con los de la solicitud, si se abre desde su ficha
  datos: { type: Object, default: () => ({}) },
  // Fichas de los retos marcados: muestra cómo le llegarán con «Avisar al docente»
  retos: { type: Array, default: null },
})
const emit = defineEmits(['cerrar'])

// Sin scroll de la página de fondo mientras está abierto
watch(() => props.abierto, (v) => { document.body.style.overflow = v ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = '' })
</script>

<template>
  <Teleport to="body">
    <div v-if="abierto" class="fixed inset-0 z-[70] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4"
         @keydown.esc="emit('cerrar')" @click.self="emit('cerrar')">
      <section role="dialog" aria-modal="true" aria-labelledby="vista-previa-titulo"
               class="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:rounded-[2rem]"
               :class="retos?.length ? 'max-w-6xl' : 'max-w-3xl'">
        <header class="flex items-center gap-3 border-b border-borde px-4 py-3 sm:px-6 sm:py-4">
          <div class="min-w-0 flex-1">
            <h2 id="vista-previa-titulo" class="truncate font-heading text-lg font-bold">Así lo ve el docente</h2>
            <p class="text-xs text-texto-secundario">{{ retos?.length
              ? 'Vista previa: así verá los retos marcados al abrir el enlace del aviso. No se le envía nada.'
              : 'Vista previa: puedes recorrer los pasos, pero no se guarda nada.' }}</p>
          </div>
          <button type="button" class="accion-icono" aria-label="Cerrar" @click="emit('cerrar')">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto">
          <SolicitudRetoDocente vista-previa :datos-previa="datos" :retos-previa="retos" />
        </div>
      </section>
    </div>
  </Teleport>
</template>
