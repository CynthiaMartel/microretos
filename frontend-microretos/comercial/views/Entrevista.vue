<!-- Asistente de la entrevista: Antes de empezar → Empresa → Entrevista → Retos → Firma.
     Cada paso guarda en el servidor al avanzar, así que si se corta la conexión o se cierra
     la app se continúa desde «Mis entrevistas». Firmada, la entrevista solo se consulta. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getEmpresaCaptada, getBorradoresCaptada } from '../../src/services/comercialService.js'
import { PREGUNTAS_DIAGNOSTICO, COLUMNA_DE } from '../../src/config/preguntasDiagnostico.js'
import PasoEmpresa from '../components/PasoEmpresa.vue'
import PasoDiagnostico from '../components/PasoDiagnostico.vue'
import PasoRetos from '../components/PasoRetos.vue'
import PasoFirma from '../components/PasoFirma.vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import TallerPasos from '../../src/components/TallerPasos.vue'

const props = defineProps({
  id: { type: String, default: null },
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
const cargando   = ref(!!props.id)
const error      = ref('')
const borradores = ref([])

const firmada = computed(() => empresa.value && empresa.value.estado_captacion !== 'borrador')

onMounted(async () => {
  if (!props.id) return
  try {
    empresa.value = (await getEmpresaCaptada(props.id)).data.data
    if (firmada.value) {
      borradores.value = (await getBorradoresCaptada(props.id)).data.data
    } else {
      // Se retoma donde se quedó: sin diagnóstico, en la entrevista; con él, en los retos
      paso.value = empresa.value.friccion_problema ? 3 : 2
      maximo.value = paso.value
    }
  } catch {
    error.value = 'No se ha podido abrir esta entrevista.'
  } finally {
    cargando.value = false
  }
})

const irA = (n) => {
  paso.value = n
  maximo.value = Math.max(maximo.value, n)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function empresaGuardada(datos, siguiente) {
  empresa.value = datos
  // La URL pasa a la de esta empresa: al recargar se retoma la misma entrevista
  if (!props.id) router.replace({ name: 'entrevista', params: { id: datos.id } })
  irA(siguiente)
}

async function alFirmar(datos) {
  empresa.value = datos
  try { borradores.value = (await getBorradoresCaptada(datos.id)).data.data } catch { /* solo es el resumen */ }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const respuestas = computed(() => PREGUNTAS_DIAGNOSTICO
  .map(p => ({ pregunta: p.pregunta, respuesta: empresa.value?.[COLUMNA_DE[p.campo]] }))
  .filter(r => r.respuesta))
// TallerPasos numera desde 1; sin empresa guardada solo se puede ir al inicio o a «Empresa»
const pasosIndicador = PASOS.map((p, i) => ({ num: i + 1, label: p.titulo }))
const puedeIr = (num) => num <= 2 || !!empresa.value

const fecha = (iso) => iso ? new Date(iso).toLocaleString('es-ES', { dateStyle: 'long', timeStyle: 'short' }) : ''
</script>

<template>
  <!-- Centrado, como Mis entrevistas: app de una sola tarea, sin menú lateral -->
  <div class="relative mx-auto max-w-4xl space-y-6 px-4 pb-10 pt-[5.25rem] sm:px-6 lg:px-8">
    <CabeceraSeccion :antetitulo="firmada ? 'Equipo comercial · Entrevista firmada' : `Equipo comercial · Paso ${paso + 1} de ${PASOS.length}`"
                     :titulo="empresa?.nombre_comercial || 'Nueva'" :destacado="empresa ? '' : 'entrevista'" color="text-empresas-dark"
                     :volver="{ name: 'mis-entrevistas' }" volver-label="Mis entrevistas" />

    <div>
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
        <p class="mt-3 font-heading text-xl font-bold">Entrevista completada</p>
        <p class="mt-2 text-sm text-texto-secundario">
          Entrevista firmada<template v-if="empresa.firma"> por {{ empresa.firma.firmante_nombre }}</template> el {{ fecha(empresa.firmada_at) }}.
        </p>
        <p class="mt-2 text-sm text-texto-secundario">
          {{ empresa.estado_captacion === 'asignada'
            ? 'DuaLab ya la ha puesto a disposición de los centros.'
            : 'DuaLab la colocará en los centros que correspondan y revisará los retos antes de publicarlos.' }}
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
      <PasoRetos v-else-if="paso === 3" :empresa="empresa" @atras="irA(2)" @siguiente="irA(4)" />
      <PasoFirma v-else-if="paso === 4" :empresa="empresa" @atras="irA(3)" @firmada="alFirmar" />
    </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../src/style.css";

.numero { @apply flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rol/15 text-xs font-bold text-rol-fuerte; }
</style>
