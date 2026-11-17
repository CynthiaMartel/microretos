<!-- Entrevista por correo: la empresa la completa sin cuenta desde el enlace que le envía el
     comercial (EnviarFormulario). Los mismos pasos que en la tablet, sin «Retos»: Antes de
     empezar (con la protección de datos) → Empresa → Entrevista → Firma. Los pasos «Empresa» y
     «Firma» son los mismos componentes que usa el comercial, guardando por el enlace. Lo hecho
     se guarda al avanzar, así que se puede dejar a medias y seguir con el mismo enlace.
     Sin dictado ni ideas de la IA: lo escribe la propia empresa. -->
<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import {
  getFormularioEmpresa, guardarEmpresaFormulario, guardarRespuestasFormulario, firmarFormularioEmpresa,
} from '../../src/services/formularioEmpresaService.js'
import { PREGUNTAS_DIAGNOSTICO, COLUMNA_DE } from '../../src/config/preguntasDiagnostico.js'
import TallerPasos from '../../src/components/TallerPasos.vue'
import PasoEmpresa from '../components/PasoEmpresa.vue'
import PasoFirma from '../components/PasoFirma.vue'
import PreguntaDiagnostico from '../components/PreguntaDiagnostico.vue'
import logo from '../../src/assets/logo_colores.png'

const props = defineProps({
  token: { type: String, required: true },
})

const PASOS = [
  { num: 1, label: 'Antes de empezar' },
  { num: 2, label: 'Empresa' },
  { num: 3, label: 'Entrevista' },
  { num: 4, label: 'Firma' },
]

const cargando = ref(true)
const cerrado  = ref(null) // { mensaje, motivo } si el enlace no vale
const datos    = ref(null) // { empresa, expira_at, privacidad, textos_legales }
const firmado  = ref(false)
const paso     = ref(0)
const maximo   = ref(0)
const acepta   = ref(false)

const empresa = computed(() => datos.value?.empresa ?? null)
const privacidad = () => ({ aceptaPrivacidad: true, privacidadVersion: datos.value.privacidad.version })

function enlaceCerrado(err) {
  const status = err.response?.status
  if (status !== 404 && status !== 410) return false
  cerrado.value = { mensaje: err.response.data?.error || 'Este enlace ya no es válido.', motivo: err.response.data?.motivo }
  return true
}

// ── Respuestas (paso «Entrevista»): como en PasoDiagnostico, las de opciones se separan en chips + «Otra»
const respuestas = reactive({})
const seleccion  = reactive({})
const otra       = reactive({})
function cargarRespuestas() {
  for (const p of PREGUNTAS_DIAGNOSTICO) {
    const guardado = empresa.value?.[COLUMNA_DE[p.campo]] ?? ''
    if (p.opciones) {
      const partes = guardado ? guardado.split(', ').map(s => s.trim()).filter(Boolean) : []
      seleccion[p.campo] = partes.filter(s => p.opciones.includes(s))
      otra[p.campo] = partes.filter(s => !p.opciones.includes(s)).join(', ')
    } else {
      respuestas[p.campo] = guardado
    }
  }
}
const textoDe = (p) => p.opciones
  ? [...seleccion[p.campo], otra[p.campo].trim()].filter(Boolean).join(', ')
  : (respuestas[p.campo] ?? '').trim()
const respuestasActuales = () => Object.fromEntries(PREGUNTAS_DIAGNOSTICO.map(p => [p.campo, textoDe(p) || null]))

onMounted(async () => {
  try {
    datos.value = (await getFormularioEmpresa(props.token)).data.data
    cargarRespuestas()
    // Lo ya hecho en otra visita se puede revisar desde el indicador de pasos
    const conEmpresa = !!empresa.value.nombre_comercial
    const conRespuestas = PREGUNTAS_DIAGNOSTICO.filter(p => p.obligatorio).every(p => textoDe(p))
    maximo.value = conEmpresa ? (conRespuestas ? 3 : 2) : 0
  } catch (err) {
    if (!enlaceCerrado(err)) cerrado.value = { mensaje: 'No se ha podido abrir la entrevista. Comprueba la conexión y vuelve a intentarlo.', motivo: 'red' }
  } finally {
    cargando.value = false
  }
})

const irA = (n) => {
  paso.value = n
  maximo.value = Math.max(maximo.value, n)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
// Sin aceptar la protección de datos no se pasa del primer paso
const puedeIr = (num) => num === 1 || acepta.value

// ── Paso «Empresa»: PasoEmpresa guarda por el enlace
async function guardarEmpresa(_empresa, payload) {
  datos.value = (await guardarEmpresaFormulario(props.token, { ...payload, ...privacidad() })).data.data
  return datos.value.empresa
}

// ── Paso «Entrevista»: una pregunta por pantalla; se guarda al pasar de pregunta
const indice    = ref(0)
const pregunta  = computed(() => PREGUNTAS_DIAGNOSTICO[indice.value])
const error     = ref('')
const aviso     = ref('')
const guardando = ref(false)
watch(paso, () => { error.value = ''; aviso.value = '' })

function anteriorPregunta() {
  error.value = ''
  if (indice.value === 0) irA(1)
  else indice.value--
}

async function siguientePregunta() {
  error.value = ''
  aviso.value = ''
  const p = pregunta.value
  if (p.obligatorio && !textoDe(p)) { error.value = 'Esta pregunta es obligatoria.'; return }
  if (textoDe(p).length > p.max) { error.value = `La respuesta es demasiado larga (máximo ${p.max} caracteres).`; return }

  guardando.value = true
  try {
    await guardarRespuestasFormulario(props.token, { ...respuestasActuales(), ...privacidad() })
    for (const q of PREGUNTAS_DIAGNOSTICO) datos.value.empresa[COLUMNA_DE[q.campo]] = textoDe(q) || null
  } catch (err) {
    if (enlaceCerrado(err)) return
    // En la última pregunta no se sigue sin guardar: la firma necesita las respuestas en DuaLab
    if (indice.value === PREGUNTAS_DIAGNOSTICO.length - 1) {
      error.value = 'No se han podido guardar tus respuestas. Comprueba la conexión y vuelve a intentarlo.'
      return
    }
    aviso.value = 'No se ha podido guardar ahora mismo. Puedes seguir: se guardará al pasar a la siguiente pregunta.'
  } finally {
    guardando.value = false
  }
  if (indice.value < PREGUNTAS_DIAGNOSTICO.length - 1) indice.value++
  else irA(3)
}

// ── Paso «Firma»: PasoFirma firma por el enlace
async function firmar(_empresa, payload) {
  try {
    await firmarFormularioEmpresa(props.token, payload)
  } catch (err) {
    if (enlaceCerrado(err)) return null
    throw err
  }
  return null
}
function alFirmar() {
  if (cerrado.value) return
  firmado.value = true
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const fecha = (iso) => iso ? new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'long' }) : ''
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6 px-4 pb-16 pt-6 sm:px-6 sm:pt-10">
    <header class="flex items-center gap-3">
      <img :src="logo" alt="" class="h-10 w-auto object-contain" />
      <span class="select-none text-lg font-black uppercase tracking-tighter text-azul-noche">Dua<span class="text-empresas-dark">Lab</span></span>
    </header>

    <div v-if="cargando" class="space-y-3" aria-busy="true">
      <div class="h-10 w-2/3 animate-pulse rounded-xl bg-gray-200" />
      <div class="h-64 animate-pulse rounded-2xl bg-gray-200" />
    </div>

    <!-- Enlace que no vale: ya firmado, caducado, entrevista completada o inexistente -->
    <section v-else-if="cerrado" class="tarjeta flex flex-col items-center gap-3 py-12 text-center">
      <span class="flex h-14 w-14 items-center justify-center rounded-full"
            :class="['respondido', 'completada'].includes(cerrado.motivo) ? 'bg-empresas/15 text-empresas-dark' : 'bg-gray-100 text-gray-500'" aria-hidden="true">
        <svg v-if="['respondido', 'completada'].includes(cerrado.motivo)" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        <svg v-else class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" /></svg>
      </span>
      <p class="max-w-md break-words font-heading text-lg font-bold">{{ cerrado.mensaje }}</p>
    </section>

    <!-- Firmada -->
    <section v-else-if="firmado" class="tarjeta flex flex-col items-center gap-3 py-12 text-center">
      <span class="flex h-14 w-14 items-center justify-center rounded-full bg-empresas/15 text-empresas-dark" aria-hidden="true">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
      </span>
      <h1 class="font-heading text-2xl font-bold">¡Gracias!</h1>
      <p class="max-w-md text-sm text-texto-secundario">
        Hemos recibido la entrevista firmada. Nuestro equipo la revisará y la persona de DuaLab que te la envió se pondrá en contacto contigo para los siguientes pasos.
      </p>
    </section>

    <template v-else>
      <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-empresas-dark">Entrevista DuaLab</p>
        <h1 class="mt-1 break-words font-heading text-2xl font-bold text-azul-noche sm:text-4xl">{{ empresa.nombre_comercial || 'Colabora con DuaLab' }}</h1>
      </div>

      <TallerPasos :pasos="PASOS" :actual="paso + 1" :maximo="maximo + 1" :puede-ir="puedeIr"
                   etiqueta="Pasos de la entrevista" variante="tarjeta" @ir="irA($event - 1)" />

      <!-- 1. Antes de empezar -->
      <template v-if="paso === 0">
        <section class="tarjeta space-y-4 text-sm sm:p-8">
          <h2 class="font-heading text-xl font-bold">Antes de empezar</h2>
          <p>DuaLab conecta a las empresas con el alumnado de Formación Profesional: con lo que nos cuentes diseñaremos retos reales para que el alumnado trabaje en ellos. La entrevista lleva unos 15 minutos:</p>
          <ol class="space-y-3">
            <li class="flex gap-3"><span class="numero">1</span><span><strong>Datos de tu empresa</strong> y con qué alumnado te gustaría trabajar.</span></li>
            <li class="flex gap-3"><span class="numero">2</span><span><strong>Siete preguntas</strong> sobre vuestro día a día y lo que más trabajo os da.</span></li>
            <li class="flex gap-3"><span class="numero">3</span><span><strong>Firma:</strong> lees las condiciones y la información de protección de datos, decides si autorizas las cesiones y firmas.</span></li>
          </ol>
          <p class="rounded-xl bg-gray-50 px-4 py-3 text-gray-600">
            Lo que escribas se guarda al avanzar: puedes dejarlo y seguir más tarde con el mismo enlace<template v-if="datos.expira_at">, que vale hasta el {{ fecha(datos.expira_at) }}</template>. No escribas datos personales de clientes ni de empleados.
          </p>
          <div>
            <h3 class="font-semibold">{{ datos.privacidad.titulo }}</h3>
            <div class="mt-2 max-h-48 overflow-y-auto whitespace-pre-line break-words rounded-xl border border-gray-200 bg-gray-50 p-4 text-gray-600" tabindex="0">{{ datos.privacidad.texto }}</div>
          </div>
          <label class="flex cursor-pointer items-start gap-3">
            <input v-model="acepta" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 accent-rol-fuerte" />
            <span>He leído la información sobre protección de datos y acepto que DuaLab use lo que escriba para valorar la colaboración y diseñar retos formativos.</span>
          </label>
        </section>
        <div class="fila-pasos">
          <span />
          <button type="button" class="btn-paso" :disabled="!acepta" @click="irA(1)">Empezar</button>
        </div>
      </template>

      <!-- 2. Empresa: el mismo paso que el del comercial -->
      <PasoEmpresa v-else-if="paso === 1" :empresa="empresa" :guardar="guardarEmpresa"
                   @atras="irA(0)" @guardada="irA(2)" />

      <!-- 3. Entrevista: una pregunta por pantalla -->
      <template v-else-if="paso === 2">
        <section class="tarjeta sm:p-8">
          <div class="mb-6 flex items-center gap-3">
            <span class="shrink-0 text-sm font-semibold text-texto-secundario">Pregunta {{ indice + 1 }} de {{ PREGUNTAS_DIAGNOSTICO.length }}</span>
            <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
              <div class="h-full rounded-full bg-rol transition-all" :style="{ width: `${((indice + 1) / PREGUNTAS_DIAGNOSTICO.length) * 100}%` }" />
            </div>
          </div>
          <PreguntaDiagnostico :key="pregunta.campo" :pregunta="pregunta"
                               v-model:texto="respuestas[pregunta.campo]" v-model:seleccion="seleccion[pregunta.campo]" v-model:otra="otra[pregunta.campo]" />
        </section>

        <p v-if="aviso" role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ aviso }}</p>
        <div v-if="error" role="alert" class="aviso-error"><span class="min-w-0 break-words">{{ error }}</span></div>

        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="anteriorPregunta">{{ indice === 0 ? 'Volver' : 'Anterior' }}</button>
          <button type="button" class="btn-paso" :disabled="guardando" @click="siguientePregunta">
            <template v-if="guardando">Guardando…</template>
            <template v-else-if="indice === PREGUNTAS_DIAGNOSTICO.length - 1"><span class="sm:hidden">Siguiente</span><span class="hidden sm:inline">Siguiente: firma</span></template>
            <template v-else>Siguiente</template>
          </button>
        </div>
      </template>

      <!-- 4. Firma: el mismo paso que el del comercial -->
      <PasoFirma v-else-if="paso === 3" :empresa="empresa" :textos-legales="datos.textos_legales" :firmar="firmar"
                 @atras="irA(2)" @firmada="alFirmar" />
    </template>
  </div>
</template>

<style scoped>
@reference "../../src/style.css";

.numero { @apply flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rol/15 text-xs font-bold text-rol-fuerte; }
</style>
