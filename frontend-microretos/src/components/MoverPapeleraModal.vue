<!-- Confirmación de «Mover a la papelera» (Captaciones: empresas, centros y retos en borrador). Acción
     crítica: se avisa y queda en el Historial de Central; restaurar o borrar del todo es de la Papelera. -->
<script setup>
import Modal from './Modal.vue'
import AvisoAccionCritica from './AvisoAccionCritica.vue'

defineProps({
  item:      { type: Object, default: null },   // { titulo, nombre, texto } o null (cerrado)
  ocupado:   { type: Boolean, default: false },
})
defineEmits(['cerrar', 'confirmar'])
</script>

<template>
  <Modal :visible="!!item" max-width="max-w-lg" @cerrar="$emit('cerrar')">
    <div v-if="item" class="flex max-h-[90dvh] min-h-0 flex-col gap-4 overflow-y-auto p-6 sm:p-8">
      <div class="pr-10">
        <h2 class="font-heading text-xl font-bold text-azul-noche">{{ item.titulo }}</h2>
        <p class="mt-1 break-words text-sm text-texto-secundario">
          «{{ item.nombre }}» irá a la papelera. {{ item.texto }} Solo el superadmin puede restaurarlo o borrarlo del todo.
        </p>
      </div>
      <AvisoAccionCritica />
      <div class="fila-pasos">
        <button type="button" class="btn-paso-atras" :disabled="ocupado" @click="$emit('cerrar')">Cancelar</button>
        <button type="button" class="btn-paso" :disabled="ocupado" @click="$emit('confirmar')"><template v-if="ocupado">Moviendo…</template><template v-else><span class="sm:hidden">Mover</span><span class="hidden sm:inline">Mover a la papelera</span></template></button>
      </div>
    </div>
  </Modal>
</template>
