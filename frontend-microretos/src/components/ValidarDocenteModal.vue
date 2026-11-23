<!-- Confirmación de la validación docente («Validar propuesta»), en el asistente y en la ficha.
     Validar = el docente la ha revisado (módulos, RA/CE, objetivos) y la quiere trabajar; la
     empresa ya no valida al inicio (la valora al final). Sobre ModalTaller (accesible: foco
     atrapado, Escape, retorno del foco). Props visible / loading / error, eventos confirm / cancel. -->
<script setup>
import ModalTaller from './ModalTaller.vue'

defineProps({
  visible: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  error:   { type: String, default: '' },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <ModalTaller :abierto="visible" titulo="Validar reto ajustado" ancho="w-[min(28rem,calc(100vw-2rem))]"
               @cerrar="emit('cancel')">
    <template #cabecera="{ idTitulo }">
      <div class="pl-10 text-center">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-empresas/15 text-empresas-dark" aria-hidden="true">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </span>
        <h2 :id="idTitulo" class="mt-3 text-xl font-bold text-azul-noche">Validar reto ajustado</h2>
        <p class="mt-1 text-sm text-texto-secundario">¿Has revisado los módulos, los RA/CE y los objetivos, y quieres trabajarlo con tu alumnado?</p>
      </div>
    </template>

    <p class="flex items-start gap-2.5 rounded-xl border border-rol/20 bg-rol/5 px-3 py-2.5 text-sm text-azul-noche">
      <svg class="mt-0.5 h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Quedará «Validado» y podrás llevarlo al aula en un encuentro creativo. La empresa no lo valida ahora: lo valorará al final, con las soluciones de los grupos.
    </p>
    <p v-if="error" class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm font-medium text-red-700" role="alert">{{ error }}</p>

    <template #pie>
      <div class="fila-pasos">
        <button type="button" @click="emit('cancel')" class="btn-paso-atras">Cancelar</button>
        <button type="button" @click="emit('confirm')" :disabled="loading" class="btn-paso">
          <template v-if="loading">Validando…</template>
          <template v-else><span class="sm:hidden">Confirmar</span><span class="hidden sm:inline">Confirmar validación</span></template>
        </button>
      </div>
    </template>
  </ModalTaller>
</template>
