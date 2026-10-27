<script setup>
/**
 * SelectorFamiliasEmpresa.vue
 * Selección múltiple de familias profesionales para una empresa (alta y edición en
 * InsertModifyEmpresa). Una empresa es una sola ficha aunque trabaje con varias
 * familias; en el Generador de Retos se elige después para cuál es cada reto.
 *
 * v-model: array de nombres de familia. `opciones` admite strings o { id, nombre }.
 * v-model:intereses (opcional): { [familia]: texto } — para qué quiere la empresa al
 * alumnado de cada familia; el Generador de Retos lo usa para plantear el reto.
 */
import { computed } from 'vue'
import { ejemploInteres } from '../config/ejemplosInteresFamilia.js'

const props = defineProps({
  modelValue:  { type: Array,   default: () => [] },
  opciones:    { type: Array,   default: () => [] },
  disabled:    { type: Boolean, default: false },
  error:       { type: Boolean, default: false },
  textoVacio:  { type: String,  default: 'No hay familias disponibles' },
  // null = sin campos de interés (p. ej. donde la ficha no los carga)
  intereses:   { type: Object,  default: null },
})

const emit = defineEmits(['update:modelValue', 'update:intereses'])

function cambiarInteres(nombre, texto) {
  emit('update:intereses', { ...props.intereses, [nombre]: texto })
}

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

    <!-- Interés por familia: qué departamento o necesidad de la empresa trabaja ese alumnado -->
    <div v-if="intereses && !disabled && modelValue.length" class="mt-3 space-y-2">
      <div class="flex items-center gap-1.5">
        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">
          ¿Para qué quiere la empresa a este alumnado? <span class="font-semibold normal-case tracking-normal text-gray-400">(opcional)</span>
        </p>
        <!-- ⓘ: hover y foco (táctil/teclado) -->
        <span class="sfe-info" tabindex="0" aria-describedby="sfe-tip-interes">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span id="sfe-tip-interes" role="tooltip" class="sfe-tip">
            Son datos que enriquecen la generación del reto: la IA sabrá qué departamento o necesidad
            concreta de la empresa trabajará este alumnado y planteará un reto más realista.
          </span>
        </span>
      </div>
      <label v-for="nombre in modelValue" :key="nombre" class="block">
        <span class="block text-[11px] font-bold text-[#3072AA] break-words">{{ nombre }}</span>
        <!-- textarea de 2 filas: el ejemplo se lee entero sin cortar; title = el ejemplo al pasar el cursor -->
        <textarea
          :value="intereses[nombre] ?? ''"
          @input="cambiarInteres(nombre, $event.target.value)"
          maxlength="500"
          rows="2"
          class="sfe-input"
          :placeholder="`Ej: ${ejemploInteres(nombre)}`"
          :title="`Ej: ${ejemploInteres(nombre)}`"
        ></textarea>
      </label>
    </div>
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
.sfe-input {
  width: 100%; min-width: 0; margin-top: .2rem; padding: .5rem .7rem; border-radius: .7rem;
  font-size: .78rem; color: #1F2937; background: #fff; border: 1.5px solid #BAD5EC; outline: none;
}
.sfe-input:focus  { border-color: #3072AA; }
textarea.sfe-input { resize: vertical; line-height: 1.35; }
.sfe-info {
  position: relative; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
  width: 1.1rem; height: 1.1rem; border-radius: 9999px; background: #E5EEF6; color: #3072AA; cursor: help;
}
/* Zona táctil ampliada (~36px) sin cambiar el tamaño visual */
.sfe-info::before { content: ''; position: absolute; inset: -.5rem; border-radius: 9999px; }
.sfe-tip {
  display: none; position: absolute; z-index: 30; top: calc(100% + .4rem); right: 0;
  width: min(16rem, calc(100vw - 3rem)); padding: .6rem .75rem; border-radius: .75rem;
  background: #1a2332; color: #fff; font-size: 11px; font-weight: 500; line-height: 1.45;
  text-transform: none; letter-spacing: normal; box-shadow: 0 20px 40px rgba(0,0,0,.25); pointer-events: none;
}
.sfe-info:hover .sfe-tip, .sfe-info:focus .sfe-tip { display: block; }
@media (min-width: 640px) { .sfe-tip { right: auto; left: 0; } }
</style>
