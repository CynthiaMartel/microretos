<script setup>
/**
 * Modal.vue
 * Contenedor genérico de modal: overlay + tarjeta con transición, cierre por click fuera,
 * botón X opcional y límite de alto con scroll interno (max-h-[90vh]) para que el contenido
 * nunca quede cortado en viewports bajos.
 *
 * El contenido (cabecera fija + cuerpo scrollable, o un único bloque) lo decide quien lo usa
 * a través del slot por defecto — este componente solo resuelve el "chrome" común.
 *
 * Props:
 *   visible    Boolean            — controla si el modal se muestra
 *   maxWidth   String  'max-w-md' — clase Tailwind de ancho máximo de la tarjeta
 *   zIndex     Number  9000       — z-index del overlay (para apilar modales, p.ej. uno de éxito sobre otro)
 *   closable   Boolean true       — si es false, oculta la X y desactiva el cierre al hacer click fuera
 *   cardClass  String  ''         — clases extra para la tarjeta (padding, text-align, overflow-y-auto...)
 *
 * Emits:
 *   @cerrar
 */
defineProps({
  visible:   { type: Boolean, default: false },
  maxWidth:  { type: String,  default: 'max-w-md' },
  zIndex:    { type: [Number, String], default: 9000 },
  closable:  { type: Boolean, default: true },
  cardClass: { type: String,  default: '' },
})
defineEmits(['cerrar'])
</script>

<template>
  <Transition name="modal-overlay">
    <div v-if="visible"
         class="fixed inset-0 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
         :style="{ zIndex }"
         @click.self="closable && $emit('cerrar')">
      <Transition name="modal-scale">
        <div v-if="visible"
             class="relative bg-white border border-gray-200 rounded-[1.75rem] shadow-2xl w-full flex flex-col max-h-[90vh]"
             :class="[maxWidth, cardClass]">
          <button v-if="closable" type="button" @click="$emit('cerrar')"
            class="absolute top-4 right-4 w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200
                   flex items-center justify-center text-gray-400 hover:text-gray-600 transition-all z-10">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
          <slot />
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<style scoped>
.modal-overlay-enter-active, .modal-overlay-leave-active { transition: opacity 0.25s ease; }
.modal-overlay-enter-from,   .modal-overlay-leave-to      { opacity: 0; }

.modal-scale-enter-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-scale-leave-active { transition: all 0.2s ease; }
.modal-scale-enter-from   { opacity: 0; transform: scale(0.92) translateY(10px); }
.modal-scale-leave-to     { opacity: 0; transform: scale(0.96); }
</style>
