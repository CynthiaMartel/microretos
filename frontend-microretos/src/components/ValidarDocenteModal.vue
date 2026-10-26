<!-- Confirmación «Validar como docente» desde la ficha del proyecto. Sobre ModalTaller
     (accesible: foco atrapado, Escape, retorno del foco) y con los tokens de diseño.
     Misma interfaz que antes: props visible / loading, eventos confirm / cancel.
     Dentro del modal «Proyecto en propuesta» esta confirmación va integrada
     (ModalPropuestaValidacion), no usa este componente. -->
<script setup>
import ModalTaller from './ModalTaller.vue'

defineProps({
  visible: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <ModalTaller :abierto="visible" titulo="Validar como docente" ancho="w-[min(28rem,calc(100vw-2rem))]"
               @cerrar="emit('cancel')">
    <template #cabecera="{ idTitulo }">
      <div class="pl-10 text-center">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-empresas/15 text-empresas-dark" aria-hidden="true">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </span>
        <h2 :id="idTitulo" class="mt-3 text-xl font-bold text-azul-noche">Validar como docente</h2>
        <p class="mt-1 text-sm text-texto-secundario">¿Confirmas que este proyecto cumple los criterios pedagógicos?</p>
      </div>
    </template>

    <p class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
      <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      Es independiente de la validación de la empresa: el proyecto quedará como «Validado · Docente» y la empresa podrá seguir respondiendo.
    </p>

    <template #pie>
      <div class="fila-pasos">
        <button type="button" @click="emit('cancel')" class="btn-paso-atras">Cancelar</button>
        <button type="button" @click="emit('confirm')" :disabled="loading" class="btn-paso">
          {{ loading ? 'Validando…' : 'Confirmar validación' }}
        </button>
      </div>
    </template>
  </ModalTaller>
</template>
