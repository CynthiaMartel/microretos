<!-- Paso «Entrevista»: las preguntas del diagnóstico de una en una (cómodo en tablet o
     móvil delante del empresario). Se guarda todo junto al terminar la última pregunta.
     Con conexión: dictado por voz (gratis del navegador o de pago) e ideas de la IA por si la
     empresa se queda en blanco (solo se insertan si la empresa las confirma). -->
<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { guardarDiagnostico } from '../offline/entrevistasLocales.js'
import { sugerirRespuestasCaptada } from '../../src/services/comercialService.js'
import { PREGUNTAS_DIAGNOSTICO, COLUMNA_DE } from '../../src/config/preguntasDiagnostico.js'
import { dictadoGratisDisponible, dictadoIaDisponible, anadirDictado } from '../composables/useDictado.js'
import PreguntaDiagnostico from './PreguntaDiagnostico.vue'
import IdeasIA from './IdeasIA.vue'

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
// Preguntas cuya respuesta partió de una idea de la IA que la empresa confirmó
const conIa = ref([...(props.empresa.respuestas_ia ?? [])])

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
const respuestasActuales = () => Object.fromEntries(PREGUNTAS_DIAGNOSTICO.map(p => [p.campo, textoDe(p) || null]))

const enLinea = ref(navigator.onLine)
const alCambiarRed = () => { enLinea.value = navigator.onLine }
onMounted(() => { window.addEventListener('online', alCambiarRed); window.addEventListener('offline', alCambiarRed) })
onUnmounted(() => { window.removeEventListener('online', alCambiarRed); window.removeEventListener('offline', alCambiarRed) })

// ── Dictado: gratis (navegador) o de pago (IA). Se recuerda en la tablet (preferencia de UI) ──
const CLAVE_MODO = 'comercial:dictado'
const modosDictado = [
  dictadoGratisDisponible && { valor: 'navegador', texto: 'Gratis' },
  dictadoIaDisponible && { valor: 'ia', texto: 'Alta calidad · de pago' },
].filter(Boolean)
let modoGuardado = null
try { modoGuardado = localStorage.getItem(CLAVE_MODO) } catch { /* sin almacenamiento */ }
const modoDictado = ref(modosDictado.some(m => m.valor === modoGuardado) ? modoGuardado : modosDictado[0]?.valor ?? null)
function elegirModo(valor) {
  modoDictado.value = valor
  try { localStorage.setItem(CLAVE_MODO, valor) } catch { /* sin almacenamiento */ }
}

// ── Ideas de la IA: se piden las de las siete preguntas de una vez y se reutilizan ──
const verIdeas      = ref(false)
const ideas         = ref(null)
const cargandoIdeas = ref(false)
const errorIdeas    = ref('')
// Entrevista creada sin conexión (aún no está en DuaLab): la IA no tiene su ficha
const puedeIdeas = computed(() => enLinea.value && !props.empresa._local && !!props.empresa.id)

async function pedirIdeas() {
  cargandoIdeas.value = true
  errorIdeas.value = ''
  try {
    ideas.value = (await sugerirRespuestasCaptada(props.empresa.id, respuestasActuales())).data.data
  } catch (err) {
    errorIdeas.value = err.response?.status === 429
      ? 'Has pedido muchas ideas seguidas: espera un minuto.'
      : err.response?.data?.error || 'No se han podido traer ideas. Comprueba la conexión.'
  } finally {
    cargandoIdeas.value = false
  }
}
function alternarIdeas() {
  verIdeas.value = !verIdeas.value
  if (verIdeas.value && !ideas.value && !cargandoIdeas.value) pedirIdeas()
}

function marcarConIa(campo) {
  if (!conIa.value.includes(campo)) conIa.value.push(campo)
}
function insertarIdea(texto) {
  const p = pregunta.value
  if (p.opciones) otra[p.campo] = anadirDictado(otra[p.campo], texto)
  else respuestas[p.campo] = anadirDictado(respuestas[p.campo], texto)
  marcarConIa(p.campo)
}
function marcarOpcion(opcion) {
  const p = pregunta.value
  if (!seleccion[p.campo].includes(opcion)) seleccion[p.campo] = [...seleccion[p.campo], opcion]
  marcarConIa(p.campo)
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
    const payload = respuestasActuales()
    // Solo cuenta como «con IA» si la respuesta sigue teniendo texto
    payload.respuestasIa = conIa.value.filter(c => payload[c])
    // Sin conexión se guarda en la tablet y se envía después
    emit('guardada', await guardarDiagnostico(props.empresa, payload))
  } catch (err) {
    const errores = err.response?.data?.errors
    error.value = (errores && Object.values(errores)[0]?.[0]) || err.response?.data?.error || err.message || 'No se ha podido guardar la entrevista.'
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <section class="tarjeta sm:p-8">
      <!-- Progreso de preguntas -->
      <div class="mb-4 flex items-center gap-3">
        <span class="shrink-0 text-sm font-semibold text-texto-secundario">Pregunta {{ indice + 1 }} de {{ PREGUNTAS_DIAGNOSTICO.length }}</span>
        <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
          <div class="h-full rounded-full bg-rol transition-all" :style="{ width: `${((indice + 1) / PREGUNTAS_DIAGNOSTICO.length) * 100}%` }" />
        </div>
      </div>

      <!-- Herramientas: modo de dictado e ideas de la IA (con conexión) -->
      <div v-if="enLinea && (modosDictado.length || puedeIdeas)" class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div v-if="modosDictado.length > 1" class="flex flex-wrap items-center gap-2">
          <span class="text-xs font-semibold text-texto-secundario">Dictado</span>
          <div class="inline-flex rounded-full bg-gray-100 p-1" role="radiogroup" aria-label="Modo de dictado">
            <button v-for="m in modosDictado" :key="m.valor" type="button" role="radio" :aria-checked="modoDictado === m.valor"
                    class="min-h-9 rounded-full px-3 text-xs font-semibold transition"
                    :class="modoDictado === m.valor ? 'bg-white text-azul-noche shadow-sm' : 'text-texto-secundario hover:text-azul-noche'"
                    @click="elegirModo(m.valor)">{{ m.texto }}</button>
          </div>
        </div>
        <p v-else-if="modosDictado.length === 1" class="text-xs text-texto-secundario">
          Dictado: {{ modosDictado[0].valor === 'ia' ? 'alta calidad · de pago (este navegador no tiene el gratis)' : 'gratis, del navegador' }}
        </p>
        <span v-else />

        <button v-if="puedeIdeas" type="button" class="accion-secundaria" :aria-expanded="verIdeas" @click="alternarIdeas">
          <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 21h4M12 3a6 6 0 00-3.5 10.9c.6.4 1 1.1 1 1.8V16h5v-.3c0-.7.4-1.4 1-1.8A6 6 0 0012 3z" /></svg>
          {{ verIdeas ? 'Ocultar ideas' : 'Ideas de la IA' }}
        </button>
      </div>

      <PreguntaDiagnostico :key="pregunta.campo" :pregunta="pregunta" :modo-dictado="enLinea ? modoDictado : null"
                           :opciones-sugeridas="verIdeas ? (ideas?.[pregunta.campo]?.opciones ?? []) : []"
                           v-model:texto="respuestas[pregunta.campo]" v-model:seleccion="seleccion[pregunta.campo]" v-model:otra="otra[pregunta.campo]">
        <template #ideas>
          <IdeasIA v-if="verIdeas && puedeIdeas" :ideas="ideas?.[pregunta.campo] ?? null" :cargando="cargandoIdeas" :error="errorIdeas"
                   :marcadas="seleccion[pregunta.campo] ?? []" @actualizar="pedirIdeas" @insertar="insertarIdea" @marcar="marcarOpcion" />
        </template>
      </PreguntaDiagnostico>

      <p v-if="conIa.includes(pregunta.campo)" class="mt-3 text-xs text-texto-secundario">
        Respuesta a partir de una idea de la IA confirmada por la empresa (DuaLab lo verá al validarla).
      </p>
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
