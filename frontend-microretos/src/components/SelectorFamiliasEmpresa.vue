<script setup>
/**
 * SelectorFamiliasEmpresa.vue
 * Selección múltiple de familias profesionales para una empresa (alta y edición en
 * InsertModifyEmpresa). Una empresa es una sola ficha aunque trabaje con varias
 * familias; en el Generador de Retos se elige después para cuál es cada reto.
 *
 * v-model: array de nombres de familia. `opciones` admite strings o { id, nombre }.
 */
import { computed } from 'vue'

const props = defineProps({
  modelValue:  { type: Array,   default: () => [] },
  opciones:    { type: Array,   default: () => [] },
  disabled:    { type: Boolean, default: false },
  error:       { type: Boolean, default: false },
  textoVacio:  { type: String,  default: 'No hay familias disponibles' },
})

const emit = defineEmits(['update:modelValue'])

const nombres = computed(() => props.opciones.map(f => f.nombre ?? f))

function alternar(nombre) {
  if (props.disabled) return
  emit('update:modelValue', props.modelValue.includes(nombre)
    ? props.modelValue.filter(n => n !== nombre)
    : [...props.modelValue, nombre])
}
</script>

<template>
  <div class="sfe-box" :class="{ 'sfe-box-err': error, 'sfe-box-off': disabled }">
    <p v-if="disabled || !nombres.length" class="text-xs font-semibold text-gray-400">{{ textoVacio }}</p>
    <div v-else class="flex flex-wrap gap-1.5">
      <button
        v-for="nombre in nombres" :key="nombre"
        type="button"
        :aria-pressed="modelValue.includes(nombre)"
        @click="alternar(nombre)"
        class="sfe-chip"
        :class="{ 'sfe-chip-on': modelValue.includes(nombre) }"
      >
        <svg v-if="modelValue.includes(nombre)" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
        </svg>
        {{ nombre }}
      </button>
    </div>
    <p v-if="!disabled && modelValue.length > 1" class="mt-2 text-[10px] font-bold text-[#3072AA]">
      {{ modelValue.length }} familias · en el generador elegirás para cuál es cada reto
    </p>
  </div>
</template>

<style scoped>
.sfe-box {
  width: 100%; border: 2px solid #BAD5EC; border-radius: 1rem; padding: .7rem .8rem;
  background: #F6FAFC; transition: all .2s;
}
.sfe-box:focus-within { border-color: #3072AA; box-shadow: 0 0 0 4px rgba(48,114,170,.12); }
.sfe-box-err  { border-color: #fca5a5 !important; background: #fff5f5 !important; }
.sfe-box-off  { opacity: .7; }
.sfe-chip {
  display: inline-flex; align-items: center; gap: .3rem; padding: .3rem .7rem; border-radius: 9999px;
  font-size: .72rem; font-weight: 700; color: #3072AA; background: #fff; border: 1.5px solid #BAD5EC;
  transition: all .15s;
}
.sfe-chip:hover   { border-color: #3072AA; }
.sfe-chip-on      { background: #3072AA; border-color: #3072AA; color: #fff; }
</style>
