<!-- Paso «Entrevista»: las preguntas del diagnóstico de una en una (cómodo en tablet o
     móvil delante del empresario). Se guarda todo junto al terminar la última pregunta. -->
<script setup>
import { ref, reactive, computed } from 'vue'
import { guardarDiagnosticoCaptada } from '../../src/services/comercialService.js'
import { PREGUNTAS_DIAGNOSTICO, COLUMNA_DE } from '../../src/config/preguntasDiagnostico.js'

const props = defineProps({
  empresa: { type: Object, required: true },
})
const emit = defineEmits(['guardada', 'atras'])

// Las preguntas con opciones se guardan como texto unido por ", ": se separan para marcar los
// chips, y lo que no es una opción conocida vuelve al campo «Otra».
const respuestas = reactive({})
const seleccion  = reactive({})
const otra       = reactive({})
for (const p of PREGUNTAS_DIAGNOSTICO) {
  const guardado = props.empresa[COLUMNA_DE[p.campo]] ?? ''
  if (p.opciones) {
    const partes = guardado ? guardado.split(', ').map(s => s.trim()).filter(Boolean) : []
    seleccion[p.campo] = partes.filter(s => p.opciones.includes(s))
    otra[p.campo] = partes.filter(s => !p.opciones.includes(s)).join(', ')
  } else {
    respuestas[p.campo] = guardado
  }
}

const indice    = ref(0)
const pregunta  = computed(() => PREGUNTAS_DIAGNOSTICO[indice.value])
const esUltima  = computed(() => indice.value === PREGUNTAS_DIAGNOSTICO.length - 1)
const error     = ref('')
const guardando = ref(false)

const textoDe = (p) => p.opciones
  ? [...seleccion[p.campo], otra[p.campo].trim()].filter(Boolean).join(', ')
  : (respuestas[p.campo] ?? '').trim()

const respondida = computed(() => !pregunta.value.obligatorio || textoDe(pregunta.value) !== '')
const largo = computed(() => textoDe(pregunta.value).length)

function alternar(opcion) {
  const lista = seleccion[pregunta.value.campo]
  const i = lista.indexOf(opcion)
  i >= 0 ? lista.splice(i, 1) : lista.push(opcion)
}

function anterior() {
  error.value = ''
  if (indice.value === 0) emit('atras')
  else indice.value--
}

async function siguiente() {
  error.value = ''
  if (!respondida.value) {
    error.value = 'Esta pregunta es obligatoria.'
    return
  }
  if (largo.value > pregunta.value.max) {
    error.value = `La respuesta es demasiado larga (máximo ${pregunta.value.max} caracteres).`
    return
  }
  if (!esUltima.value) {
    indice.value++
    return
  }

  guardando.value = true
  try {
    const payload = Object.fromEntries(PREGUNTAS_DIAGNOSTICO.map(p => [p.campo, textoDe(p) || null]))
    const { data } = await guardarDiagnosticoCaptada(props.empresa.id, payload)
    emit('guardada', data.data)
  } catch (err) {
    const errores = err.response?.data?.errors
    error.value = (errores && Object.values(errores)[0]?.[0]) || err.response?.data?.error || 'No se ha podido guardar la entrevista.'
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <section class="tarjeta sm:p-8">
      <!-- Progreso de preguntas -->
      <div class="mb-6 flex items-center gap-3">
        <span class="shrink-0 text-sm font-semibold text-texto-secundario">Pregunta {{ indice + 1 }} de {{ PREGUNTAS_DIAGNOSTICO.length }}</span>
        <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
          <div class="h-full rounded-full bg-rol transition-all" :style="{ width: `${((indice + 1) / PREGUNTAS_DIAGNOSTICO.length) * 100}%` }" />
        </div>
      </div>

      <label :for="`pd-${pregunta.campo}`" class="block font-heading text-xl font-bold text-azul-noche sm:text-2xl">
        {{ pregunta.pregunta }}<span v-if="pregunta.obligatorio" class="text-rol-fuerte"> *</span>
      </label>
      <p v-if="pregunta.ayuda" class="mt-2 text-sm text-texto-secundario">{{ pregunta.ayuda }}</p>

      <div class="mt-5">
        <template v-if="pregunta.opciones">
          <div class="flex flex-wrap gap-2" role="group" :aria-label="pregunta.pregunta">
            <button v-for="o in pregunta.opciones" :key="o" type="button" :aria-pressed="seleccion[pregunta.campo].includes(o)"
                    class="chip min-h-11" :class="seleccion[pregunta.campo].includes(o) && 'chip-activo'"
                    @click="alternar(o)"><svg v-if="seleccion[pregunta.campo].includes(o)" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>{{ o }}</button>
          </div>
          <label :for="`pd-${pregunta.campo}`" class="label-style mt-5">{{ pregunta.otra }}</label>
          <input :id="`pd-${pregunta.campo}`" v-model="otra[pregunta.campo]" class="input-style" />
        </template>
        <textarea v-else :id="`pd-${pregunta.campo}`" v-model="respuestas[pregunta.campo]" rows="6" class="input-style" />
        <p class="mt-1.5 text-right text-xs" :class="largo > pregunta.max ? 'font-semibold text-red-600' : 'text-gray-400'">
          {{ largo }} / {{ pregunta.max }}
        </p>
      </div>
    </section>

    <div v-if="error" role="alert" class="aviso-error"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="min-w-0 break-words">{{ error }}</span></div>

    <div class="fila-pasos">
      <button type="button" class="btn-paso-atras" @click="anterior">{{ indice === 0 ? 'Volver' : 'Anterior' }}</button>
      <button type="button" class="btn-paso" :disabled="guardando" @click="siguiente">
        {{ guardando ? 'Guardando…' : esUltima ? 'Guardar entrevista' : 'Siguiente' }}
      </button>
    </div>
  </div>
</template>

