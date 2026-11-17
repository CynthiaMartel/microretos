<!-- Una pregunta del diagnóstico: enunciado, ayuda, opciones (chips + «Otra») o texto libre,
     contador y dictado por voz. La usan la entrevista del comercial (PasoDiagnostico) y el
     formulario que responde la empresa por correo (FormularioEmpresa). -->
<script setup>
import { computed } from 'vue'
import { useDictado, anadirDictado, dictadoGratisDisponible, dictadoIaDisponible } from '../composables/useDictado.js'

const props = defineProps({
  pregunta: { type: Object, required: true },
  // 'navegador' (gratis), 'ia' (de pago) o null sin dictado
  modoDictado: { type: String, default: null },
  // Opciones que la IA ve probables (solo como pista: no se marcan solas)
  opcionesSugeridas: { type: Array, default: () => [] },
})
const texto     = defineModel('texto', { type: String, default: '' })
const seleccion = defineModel('seleccion', { type: Array, default: () => [] })
const otra      = defineModel('otra', { type: String, default: '' })

const largo = computed(() => (props.pregunta.opciones
  ? [...seleccion.value, otra.value.trim()].filter(Boolean).join(', ')
  : (texto.value ?? '').trim()).length)

function alternar(opcion) {
  seleccion.value = seleccion.value.includes(opcion)
    ? seleccion.value.filter((o) => o !== opcion)
    : [...seleccion.value, opcion]
}

// Lo dictado va al texto libre o, en las preguntas con opciones, a «Otra»
const { estado, parcial, error: errorDictado, alternar: alternarDictado } = useDictado((dictado) => {
  if (props.pregunta.opciones) otra.value = anadirDictado(otra.value, dictado)
  else texto.value = anadirDictado(texto.value, dictado)
})
const puedeDictar = computed(() => props.modoDictado === 'ia' ? dictadoIaDisponible : !!props.modoDictado && dictadoGratisDisponible)
const id = computed(() => `pd-${props.pregunta.campo}`)
</script>

<template>
  <div>
    <label :for="id" class="block font-heading text-xl font-bold text-azul-noche sm:text-2xl">
      {{ pregunta.pregunta }}<span v-if="pregunta.obligatorio" class="text-rol-fuerte"> *</span>
    </label>
    <p v-if="pregunta.ayuda" class="mt-2 text-sm text-texto-secundario">{{ pregunta.ayuda }}</p>

    <slot name="ideas" />

    <div class="mt-5">
      <template v-if="pregunta.opciones">
        <div class="flex flex-wrap gap-2" role="group" :aria-label="pregunta.pregunta">
          <button v-for="o in pregunta.opciones" :key="o" type="button" :aria-pressed="seleccion.includes(o)"
                  class="chip min-h-11" :class="[seleccion.includes(o) && 'chip-activo', !seleccion.includes(o) && opcionesSugeridas.includes(o) && 'chip-sugerido']"
                  @click="alternar(o)">
            <svg v-if="seleccion.includes(o)" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            {{ o }}
            <span v-if="!seleccion.includes(o) && opcionesSugeridas.includes(o)" class="sr-only">(sugerida por la IA)</span>
          </button>
        </div>
        <label :for="id" class="label-style mt-5">{{ pregunta.otra }}</label>
        <input :id="id" v-model="otra" class="input-style" />
      </template>
      <textarea v-else :id="id" v-model="texto" rows="6" class="input-style" />

      <p v-if="parcial" class="mt-2 break-words text-sm italic text-texto-secundario" aria-live="polite">{{ parcial }}…</p>

      <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
        <button v-if="puedeDictar" type="button" class="boton-dictado" :class="estado === 'escuchando' && 'boton-dictado-activo'"
                :disabled="estado === 'procesando'" :aria-pressed="estado === 'escuchando'" @click="alternarDictado(modoDictado)">
          <svg v-if="estado !== 'procesando'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <rect v-if="estado === 'escuchando'" x="7" y="7" width="10" height="10" rx="1.5" />
            <template v-else><rect x="9" y="3" width="6" height="11" rx="3" /><path d="M5 11a7 7 0 0014 0M12 18v3" /></template>
          </svg>
          <span v-else class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true" />
          {{ estado === 'escuchando' ? 'Parar' : estado === 'procesando' ? 'Pasando a texto…' : 'Dictar' }}
        </button>
        <span v-else />
        <span class="text-xs" :class="largo > pregunta.max ? 'font-semibold text-red-600' : 'text-gray-400'">{{ largo }} / {{ pregunta.max }}</span>
      </div>
      <p v-if="errorDictado" role="alert" class="mt-2 break-words text-sm font-medium text-red-700">{{ errorDictado }}</p>
    </div>
  </div>
</template>

<style scoped>
@reference "../../src/style.css";

.chip-sugerido { @apply border-dashed border-rol-fuerte/60 bg-rol/5; }
.boton-dictado { @apply inline-flex min-h-10 items-center gap-2 rounded-full border border-gray-200 bg-white px-4 text-sm font-semibold text-azul-noche transition hover:border-rol-fuerte/50 disabled:cursor-wait disabled:opacity-70; }
.boton-dictado-activo { @apply border-red-300 bg-red-50 text-red-700 hover:border-red-400; }
</style>
