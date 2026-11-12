<!-- Asistente de la entrevista: Antes de empezar → Empresa → Entrevista → Retos → Firma.
     Cada paso guarda en el servidor al avanzar, así que si se corta la conexión o se cierra
     la app se continúa desde «Mis entrevistas». Firmada, la entrevista solo se consulta.
     Sin conexión, los pasos se guardan en la tablet (offline/entrevistasLocales.js) y la
     entrevista pasa a /entrevista/local/:clave hasta que se envía. -->
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { getEmpresaCaptada, getBorradoresCaptada } from '../../src/services/comercialService.js'
import { PREGUNTAS_DIAGNOSTICO, COLUMNA_DE } from '../../src/config/preguntasDiagnostico.js'
import PasoEmpresa from '../components/PasoEmpresa.vue'
import PasoDiagnostico from '../components/PasoDiagnostico.vue'
import PasoRetos from '../components/PasoRetos.vue'
import PasoFirma from '../components/PasoFirma.vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import TallerPasos from '../../src/components/TallerPasos.vue'
import {
  obtenerLocal, pendienteDeEmpresa, entrevistaAbierta, enviarAhora, sincronizar, descartarFirma,
} from '../offline/entrevistasLocales.js'

const props = defineProps({
  id:    { type: String, default: null },
  clave: { type: String, default: null }, // entrevista guardada en la tablet
})

const router = useRouter()

const PASOS = [
  { key: 'inicio',     titulo: 'Antes de empezar', corto: 'Inicio' },
  { key: 'empresa',    titulo: 'Empresa',          corto: 'Empresa' },
  { key: 'entrevista', titulo: 'Entrevista',       corto: 'Entrevista' },
  { key: 'retos',      titulo: 'Retos',            corto: 'Retos' },
  { key: 'firma',      titulo: 'Firma',            corto: 'Firma' },
]

const empresa    = ref(null)
const paso       = ref(0)
const maximo     = ref(0)   // último paso alcanzado (para volver a uno ya hecho desde el indicador)
const cargando   = ref(!!(props.id || props.clave))
const error      = ref('')
const borradores = ref([])

const firmada = computed(() => empresa.value && empresa.value.estado !== 'borrador')
const enTablet = computed(() => !!empresa.value?._local)

// Se retoma donde se quedó: sin diagnóstico, en la entrevista; con él, en los retos
function retomar() {
  if (firmada.value) return
  paso.value = empresa.value.friccion_problema ? 3 : 2
  maximo.value = paso.value
}

onMounted(async () => {
  if (props.clave) {
    entrevistaAbierta.value = props.clave
    try {
      empresa.value = await obtenerLocal(props.clave)
      if (empresa.value) retomar()
      else error.value = 'Esta entrevista ya se ha enviado a DuaLab: ábrela desde «Empresas».'
    } catch {
      error.value = 'No se ha podido abrir la entrevista guardada en esta tablet.'
    } finally {
      cargando.value = false
    }
    return
  }
  if (!props.id) return
  // Con cambios guardados en la tablet sin enviar, se abre esa versión
  const local = pendienteDeEmpresa(Number(props.id))
  if (local) {
    router.replace({ name: 'entrevista-local', params: { clave: local._local } })
    return
  }
  try {
    empresa.value = (await getEmpresaCaptada(props.id)).data.data
    if (firmada.value) borradores.value = (await getBorradoresCaptada(props.id)).data.data
    else retomar()
  } catch (err) {
    error.value = err.response
      ? 'No se ha podido abrir esta entrevista.'
      : 'Sin conexión: solo se pueden abrir las entrevistas guardadas en esta tablet o empezar una nueva.'
  } finally {
    cargando.value = false
  }
})

// Al salir de una entrevista de la tablet se intenta enviar (ya no se está editando)
onUnmounted(() => {
  if (entrevistaAbierta.value === props.clave) entrevistaAbierta.value = null
  if (props.clave) sincronizar()
})

// Rechazada por DuaLab con la firma ya hecha en la tablet: se quita la firma para corregir y
// volver a firmar (lo firmado tiene que ser lo que se envía)
async function corregir() {
  empresa.value = await descartarFirma(props.clave)
  retomar()
}

// ── Enviar ahora la entrevista de la tablet (al volver la conexión) ──
const enviando = ref(false)
const errorEnvio = ref('')
async function enviar() {
  enviando.value = true
  errorEnvio.value = ''
  try {
    const datos = await enviarAhora(props.clave)
    entrevistaAbierta.value = null
    router.replace({ name: 'entrevista', params: { id: datos.id } })
  } catch (e) {
    errorEnvio.value = e.message
  } finally {
    enviando.value = false
  }
}

const irA = (n) => {
  paso.value = n
  maximo.value = Math.max(maximo.value, n)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// La URL pasa a la de la entrevista guardada (servidor o tablet): al recargar se retoma la
// misma. Cambiar de ruta vuelve a montar la vista, que la abre en el paso que toca.
function pasarARuta(datos) {
  if (datos._local && datos._local !== props.clave) {
    router.replace({ name: 'entrevista-local', params: { clave: datos._local } })
    return true
  }
  if (!datos._local && !props.id) {
    router.replace({ name: 'entrevista', params: { id: datos.id } })
  }
  return false
}

function empresaGuardada(datos, siguiente) {
  empresa.value = datos
  if (pasarARuta(datos)) return
  irA(siguiente)
}

async function alFirmar(datos) {
  empresa.value = datos
  if (pasarARuta(datos)) return
  if (!datos._local) {
    try { borradores.value = (await getBorradoresCaptada(datos.id)).data.data } catch { /* solo es el resumen */ }
  }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const respuestas = computed(() => PREGUNTAS_DIAGNOSTICO
  .map(p => ({ pregunta: p.pregunta, respuesta: empresa.value?.[COLUMNA_DE[p.campo]] }))
  .filter(r => r.respuesta))
// TallerPasos numera desde 1; sin empresa guardada solo se puede ir al inicio o a «Empresa»
const pasosIndicador = PASOS.map((p, i) => ({ num: i + 1, label: p.titulo }))
const puedeIr = (num) => num <= 2 || !!empresa.value
const enLinea = ref(navigator.onLine)
const alCambiarRed = () => { enLinea.value = navigator.onLine }
window.addEventListener('online', alCambiarRed)
window.addEventListener('offline', alCambiarRed)
onUnmounted(() => {
  window.removeEventListener('online', alCambiarRed)
  window.removeEventListener('offline', alCambiarRed)
})

const fecha = (iso) => iso ? new Date(iso).toLocaleString('es-ES', { dateStyle: 'long', timeStyle: 'short' }) : ''
</script>

<template>
  <!-- Centrado, como Mis entrevistas: app de una sola tarea, sin menú lateral -->
  <div class="relative mx-auto max-w-4xl space-y-6 px-4 pb-10 pt-[5.25rem] sm:px-6 lg:px-8">
    <CabeceraSeccion :antetitulo="firmada ? 'Equipo comercial · Entrevista firmada' : `Equipo comercial · Paso ${paso + 1} de ${PASOS.length}`"
                     :titulo="empresa?.nombre_comercial || 'Nueva'" :destacado="empresa ? '' : 'entrevista'" color="text-empresas-dark"
                     :volver="{ name: 'mis-entrevistas' }" volver-label="Mis entrevistas" />

    <div>
    <!-- Guardada en la tablet: aún no está en DuaLab -->
    <div v-if="!cargando && !error && enTablet" role="status"
         class="mb-6 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800" aria-hidden="true">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2" /><path d="M12 18h.01" /></svg>
      </span>
      <div class="min-w-0 flex-1 text-sm">
        <p class="font-semibold text-amber-900">Guardada en esta tablet, sin enviar a DuaLab</p>
        <p class="mt-0.5 break-words text-amber-800">
          {{ empresa._error
            ? `DuaLab no la ha aceptado: ${empresa._error}`
            : (enLinea ? 'Ya hay conexión: envíala ahora o se enviará sola al salir de la entrevista.' : 'Se enviará sola cuando vuelva la conexión. Está cifrada y no se borra hasta enviarse.') }}
        </p>
        <p v-if="errorEnvio" class="mt-1 break-words font-semibold text-red-700">{{ errorEnvio }}</p>
      </div>
      <div class="flex shrink-0 flex-wrap gap-2 self-start sm:self-center">
        <button v-if="empresa._error && firmada" type="button" class="accion-secundaria" @click="corregir">Corregir y volver a firmar</button>
        <button v-if="enLinea" type="button" class="accion-principal" :disabled="enviando" @click="enviar">
          {{ enviando ? 'Enviando…' : 'Enviar ahora' }}
        </button>
      </div>
    </div>

    <div v-if="cargando" class="space-y-3" aria-busy="true">
      <div class="h-16 animate-pulse rounded-2xl bg-gray-200" />
      <div class="h-64 animate-pulse rounded-2xl bg-gray-200" />
    </div>
    <div v-else-if="error" role="alert" class="aviso-error"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="min-w-0 break-words">{{ error }}</span></div>

    <!-- Entrevista firmada: solo consulta -->
    <div v-else-if="firmada" class="space-y-5">
      <section class="tarjeta text-center sm:p-8">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-empresas/15 text-empresas-dark" aria-hidden="true">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        </span>
        <p class="mt-3 font-heading text-xl font-bold">{{ enTablet ? 'Firmada en la tablet' : 'Entrevista completada' }}</p>
        <p class="mt-2 text-sm text-texto-secundario">
          Entrevista firmada<template v-if="empresa.firma"> por {{ empresa.firma.firmante_nombre }}</template> el {{ fecha(empresa.firmada_at) }}.
        </p>
        <p class="mt-2 text-sm text-texto-secundario">
          {{ enTablet
            ? 'La firma se registrará en DuaLab cuando se envíe la entrevista. Después DuaLab la colocará en los centros que correspondan.'
            : empresa.estado === 'validada'
            ? 'DuaLab ya la ha validado y la ha puesto a disposición de los centros.'
            : empresa.estado === 'descartada'
            ? 'DuaLab ha revisado esta entrevista y no la va a usar.'
            : 'DuaLab la validará, la colocará en los centros que correspondan y revisará los retos antes de publicarlos.' }}
        </p>
        <button type="button" class="accion-principal mt-6" @click="router.push({ name: 'mis-entrevistas' })">Volver a mis entrevistas</button>
      </section>

      <section v-if="respuestas.length" class="tarjeta">
        <h2 class="font-heading text-lg font-bold">Lo que contó la empresa</h2>
        <dl class="mt-3 space-y-3 text-sm">
          <div v-for="r in respuestas" :key="r.pregunta">
            <dt class="font-semibold text-azul-noche">{{ r.pregunta }}</dt>
            <dd class="mt-0.5 whitespace-pre-line break-words text-gray-600">{{ r.respuesta }}</dd>
          </div>
        </dl>
      </section>

      <section v-if="borradores.length" class="tarjeta">
        <h2 class="font-heading text-lg font-bold">Retos generados</h2>
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
          <li v-for="b in borradores" :key="b.uuid" class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
            <span class="min-w-0 break-words font-semibold">{{ b.reto.titulo }}</span>
            <span class="w-fit shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="{ pendiente: 'bg-amber-50 text-amber-800', publicado: 'bg-empresas/10 text-empresas-dark', descartado: 'bg-gray-100 text-gray-500' }[b.estado]">
              {{ { pendiente: 'Pendiente de validar', publicado: 'Publicado', descartado: 'Descartado' }[b.estado] }}
            </span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Asistente -->
    <div v-else class="space-y-6">
      <TallerPasos :pasos="pasosIndicador" :actual="paso + 1" :maximo="maximo + 1" :puede-ir="puedeIr"
                   etiqueta="Pasos de la entrevista" variante="tarjeta" @ir="irA($event - 1)" />

      <div v-if="paso === 0" class="space-y-6">
        <section class="tarjeta sm:p-8">
          <h2 class="font-heading text-xl font-bold">Antes de empezar</h2>
          <p class="mt-2 text-sm text-texto-secundario">Cuéntale al empresario cómo va a ir la entrevista (unos 15 minutos):</p>
          <ol class="mt-4 space-y-3 text-sm">
            <li class="flex gap-3"><span class="numero">1</span><span><strong>Datos de la empresa</strong> y con qué alumnado le gustaría trabajar.</span></li>
            <li class="flex gap-3"><span class="numero">2</span><span><strong>Siete preguntas</strong> sobre su día a día y qué le da más trabajo.</span></li>
            <li class="flex gap-3"><span class="numero">3</span><span><strong>Retos en directo:</strong> la IA propone retos para el alumnado con lo que nos ha contado.</span></li>
            <li class="flex gap-3"><span class="numero">4</span><span><strong>Firma:</strong> lee las condiciones y la información de protección de datos, decide si autoriza las cesiones y firma.</span></li>
          </ol>
          <p class="mt-5 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">
            Infórmale de que sus datos solo se usarán para diseñar retos formativos y de que, antes de firmar, podrá leer el texto completo.
          </p>
        </section>
        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="router.push({ name: 'mis-entrevistas' })">Volver</button>
          <button type="button" class="btn-paso" @click="irA(1)">Empezar</button>
        </div>
      </div>

      <PasoEmpresa v-else-if="paso === 1" :empresa="empresa" @atras="irA(0)" @guardada="empresaGuardada($event, 2)" />
      <PasoDiagnostico v-else-if="paso === 2" :empresa="empresa" @atras="irA(1)" @guardada="empresaGuardada($event, 3)" />
      <PasoRetos v-else-if="paso === 3" :empresa="empresa" :en-linea="enLinea" :enviando="enviando"
                 @atras="irA(2)" @siguiente="irA(4)" @enviar="enviar" />
      <PasoFirma v-else-if="paso === 4" :empresa="empresa" @atras="irA(3)" @firmada="alFirmar" />
    </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../src/style.css";

.numero { @apply flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rol/15 text-xs font-bold text-rol-fuerte; }
</style>
