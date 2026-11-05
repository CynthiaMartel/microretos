<script setup>
/**
 * Apartado «Captación comercial» de Notificaciones (solo superadmin). Dos tareas:
 *  1. Empresas entrevistadas y firmadas por un comercial que esperan centro: se colocan en
 *     uno o varios y desde ese momento las ven los docentes de esos centros.
 *  2. Retos que el comercial generó delante de la empresa (borradores): se publican en los
 *     centros de la empresa o se descartan. Hasta entonces no aparecen en ninguna biblioteca.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Modal from './Modal.vue'
import {
  getEmpresasCaptadas, asignarCentrosEmpresa, getBorradoresPendientes, getBorradorCaptacion,
  publicarBorrador, descartarBorrador, getImagenFirma, getCentrosCaptacion,
} from '../services/captacionService.js'

const emit = defineEmits(['cambio'])

const ESTADOS = [
  { key: 'pendiente_centro', label: 'Pendientes de centro' },
  { key: 'asignada',         label: 'Ya colocadas' },
]
const estado = ref('pendiente_centro')

const empresas  = ref([])
const borradores = ref([])
const centros   = ref([])
const cargando  = ref(false)
const error     = ref('')
const aviso     = ref('')

const mensajeError = (e, porDefecto) => {
  const errores = e.response?.data?.errors
  return (errores && Object.values(errores)[0]?.[0]) || e.response?.data?.error || e.response?.data?.message || porDefecto
}

async function cargarEmpresas() {
  const { data } = await getEmpresasCaptadas(estado.value)
  empresas.value = data.data
}
async function cargarBorradores() {
  const { data } = await getBorradoresPendientes()
  borradores.value = data.data
}
async function cargarTodo() {
  cargando.value = true
  error.value = ''
  try {
    await Promise.all([cargarEmpresas(), cargarBorradores()])
  } catch {
    error.value = 'No se ha podido cargar la captación comercial.'
  } finally {
    cargando.value = false
  }
}
onMounted(async () => {
  cargarTodo()
  try { centros.value = (await getCentrosCaptacion()).data } catch { /* se avisa al abrir el selector */ }
})

async function cambiarEstado(key) {
  estado.value = key
  try { await cargarEmpresas() } catch { error.value = 'No se han podido cargar las empresas.' }
}

const fecha = (iso) => iso ? new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

// ── Colocar en centros ────────────────────────────────────────────────────────
const empresaColocando = ref(null)
const seleccionCentros = ref([])
const buscaCentro      = ref('')
const guardandoCentros = ref(false)

const centrosFiltrados = computed(() => {
  const q = buscaCentro.value.trim().toLowerCase()
  return q ? centros.value.filter(c => c.nombre.toLowerCase().includes(q)) : centros.value
})

function abrirColocar(empresa) {
  empresaColocando.value = empresa
  seleccionCentros.value = (empresa.centros || []).map(c => c.id)
  buscaCentro.value = ''
}
async function guardarCentros() {
  if (!seleccionCentros.value.length) return
  guardandoCentros.value = true
  try {
    await asignarCentrosEmpresa(empresaColocando.value.id, seleccionCentros.value)
    aviso.value = `«${empresaColocando.value.nombre_comercial}» ya está disponible en ${seleccionCentros.value.length === 1 ? '1 centro' : seleccionCentros.value.length + ' centros'}.`
    empresaColocando.value = null
    await cargarTodo()
    emit('cambio')
  } catch (e) {
    error.value = mensajeError(e, 'No se ha podido colocar la empresa.')
  } finally {
    guardandoCentros.value = false
  }
}

// ── Firma (imagen privada, se pide como blob) ─────────────────────────────────
const firmaUrl     = ref(null)
const firmaEmpresa = ref(null)
async function verFirma(empresa) {
  try {
    const { data } = await getImagenFirma(empresa.firma.uuid)
    cerrarFirma()
    firmaUrl.value = URL.createObjectURL(data)
    firmaEmpresa.value = empresa
  } catch {
    error.value = 'No se ha podido cargar la firma.'
  }
}
function cerrarFirma() {
  if (firmaUrl.value) URL.revokeObjectURL(firmaUrl.value)
  firmaUrl.value = null
  firmaEmpresa.value = null
}
onUnmounted(cerrarFirma)

const NOMBRE_CONSENTIMIENTO = { aviso_legal: 'Aviso legal', info_rgpd: 'Protección de datos' }
const nombreConsentimiento = (clave) => NOMBRE_CONSENTIMIENTO[clave] ?? clave.replace('cesion_', 'Cesión ')

// ── Validar retos de comercial ────────────────────────────────────────────────
const detalle          = ref(null)   // { borrador, centros_empresa, centros_sin_ciclo }
const centrosPublicar  = ref([])
const publicando       = ref(false)

async function abrirBorrador(b) {
  try {
    const { data } = await getBorradorCaptacion(b.uuid)
    detalle.value = data
    centrosPublicar.value = data.centros_empresa.map(c => c.id)
  } catch {
    error.value = 'No se ha podido abrir el reto.'
  }
}
async function publicar() {
  publicando.value = true
  try {
    const { data } = await publicarBorrador(detalle.value.borrador.uuid, centrosPublicar.value)
    aviso.value = data.message
    detalle.value = null
    await cargarTodo()
    emit('cambio')
  } catch (e) {
    error.value = mensajeError(e, 'No se ha podido publicar el reto.')
  } finally {
    publicando.value = false
  }
}
async function descartar() {
  if (!confirm('El reto se descartará y no se publicará en ningún centro. ¿Continuar?')) return
  publicando.value = true
  try {
    await descartarBorrador(detalle.value.borrador.uuid)
    aviso.value = 'Reto descartado.'
    detalle.value = null
    await cargarTodo()
    emit('cambio')
  } catch (e) {
    error.value = mensajeError(e, 'No se ha podido descartar el reto.')
  } finally {
    publicando.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <p v-if="error" role="alert" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    <p v-if="aviso" role="status" class="rounded-xl bg-empresas/10 px-3 py-2 text-sm font-semibold text-empresas-dark">{{ aviso }}</p>

    <!-- 1. Empresas captadas -->
    <article class="card p-4 sm:p-5">
      <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="font-heading text-lg font-bold">Empresas captadas por comerciales</h2>
        <div class="inline-flex w-fit gap-1 rounded-full bg-gray-100 p-1" role="group" aria-label="Qué empresas ver">
          <button v-for="e in ESTADOS" :key="e.key" type="button" :aria-pressed="estado === e.key" @click="cambiarEstado(e.key)"
                  class="inline-flex h-9 items-center rounded-full px-4 text-sm font-semibold transition"
                  :class="estado === e.key ? 'bg-white text-azul-noche shadow-sm' : 'text-gray-600 hover:text-azul-noche'">
            {{ e.label }}
          </button>
        </div>
      </div>

      <div v-if="cargando && !empresas.length" class="space-y-2"><div v-for="n in 2" :key="n" class="h-24 animate-pulse rounded-xl bg-gray-100" /></div>
      <p v-else-if="!empresas.length" class="py-8 text-center text-sm text-gray-500">
        {{ estado === 'pendiente_centro' ? 'No hay empresas esperando centro.' : 'Todavía no se ha colocado ninguna empresa captada.' }}
      </p>
      <ul v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <li v-for="e in empresas" :key="e.id" class="flex flex-col gap-3 rounded-xl border border-borde p-4">
          <div class="min-w-0">
            <p class="break-words font-bold text-azul-noche">{{ e.nombre_comercial }}</p>
            <p class="text-sm text-texto-secundario">
              {{ [e.sector, e.municipio].filter(Boolean).join(' · ') || 'Sin sector' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
              Entrevista de {{ e.comercial || 'comercial' }} · firmada el {{ fecha(e.firmada_at) }}
              <template v-if="e.firma"> por {{ e.firma.firmante_nombre }}<template v-if="e.firma.firmante_cargo"> ({{ e.firma.firmante_cargo }})</template></template>
            </p>
          </div>

          <div v-if="e.familias?.length" class="flex flex-wrap gap-1.5">
            <span v-for="f in e.familias" :key="f.nombre" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ f.nombre }}</span>
          </div>

          <p v-if="e.datos_incompletos?.length" role="note"
             class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" /></svg>
            <span>Datos incompletos: falta {{ e.datos_incompletos.join(', ') }}.</span>
          </p>

          <div v-if="e.firma?.consentimientos" class="flex flex-wrap gap-1.5 text-xs">
            <span v-for="c in e.firma.consentimientos" :key="c.clave"
                  class="rounded-full px-2.5 py-0.5 font-semibold"
                  :class="c.aceptado ? 'bg-empresas/10 text-empresas-dark' : 'bg-red-50 text-red-700'">
              {{ nombreConsentimiento(c.clave) }}: {{ c.aceptado ? 'sí' : 'no' }}
            </span>
          </div>

          <p v-if="e.centros?.length" class="text-sm text-texto-secundario">
            <span class="font-semibold text-azul-noche">Centros:</span> {{ e.centros.map(c => c.nombre).join(', ') }}
          </p>
          <p v-if="e.borradores_pendientes" class="text-sm font-semibold text-amber-800">
            {{ e.borradores_pendientes }} {{ e.borradores_pendientes === 1 ? 'reto pendiente' : 'retos pendientes' }} de validar
          </p>

          <div class="mt-auto flex flex-wrap gap-2">
            <button type="button" class="accion-principal" @click="abrirColocar(e)">
              {{ e.centros?.length ? 'Cambiar centros' : 'Colocar en centros' }}
            </button>
            <button v-if="e.firma" type="button" class="accion-secundaria" @click="verFirma(e)">Ver firma</button>
          </div>
        </li>
      </ul>
    </article>

    <!-- 2. Retos de comerciales pendientes de validar -->
    <article class="card p-4 sm:p-5">
      <h2 class="mb-3 font-heading text-lg font-bold">Retos pendientes de validar</h2>
      <div v-if="cargando && !borradores.length" class="space-y-2"><div v-for="n in 2" :key="n" class="h-16 animate-pulse rounded-xl bg-gray-100" /></div>
      <p v-else-if="!borradores.length" class="py-8 text-center text-sm text-gray-500">No hay retos de comerciales pendientes.</p>
      <ul v-else class="divide-y divide-gray-100">
        <li v-for="b in borradores" :key="b.uuid">
          <button type="button" class="flex w-full flex-col gap-1 rounded-xl px-2 py-3 text-left transition hover:bg-gray-50 sm:flex-row sm:items-center sm:gap-3" @click="abrirBorrador(b)">
            <span class="min-w-0 flex-1">
              <span class="block break-words text-sm font-bold">{{ b.reto.titulo }}</span>
              <span class="block text-sm text-gray-500">{{ b.empresa?.nombre_comercial }} · {{ b.familia }}</span>
            </span>
            <span class="w-fit shrink-0 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
              Creado por comercial · pendiente de validar para publicar
            </span>
          </button>
        </li>
      </ul>
    </article>

    <!-- Modal: colocar en centros -->
    <Modal :visible="!!empresaColocando" max-width="max-w-lg" @cerrar="empresaColocando = null">
      <div v-if="empresaColocando" class="flex min-h-0 flex-col gap-4 p-6 sm:p-8">
        <div class="pr-10">
          <h2 class="font-heading text-xl font-bold text-azul-noche">Colocar en centros</h2>
          <p class="mt-1 break-words text-sm text-texto-secundario">«{{ empresaColocando.nombre_comercial }}» quedará disponible para los docentes de los centros que marques.</p>
        </div>
        <input v-model="buscaCentro" type="search" placeholder="Buscar centro…" aria-label="Buscar centro"
               class="h-11 w-full rounded-xl border border-borde-campo px-4 text-sm outline-none focus:border-rol-fuerte" />
        <ul class="min-h-0 max-h-[45dvh] space-y-1 overflow-y-auto">
          <li v-for="c in centrosFiltrados" :key="c.id">
            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-gray-50">
              <input v-model="seleccionCentros" type="checkbox" :value="c.id" class="h-5 w-5 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 break-words text-sm font-semibold">{{ c.nombre }}</span>
            </label>
          </li>
          <li v-if="!centrosFiltrados.length" class="px-3 py-4 text-sm text-gray-500">No hay centros con ese nombre.</li>
        </ul>
        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="empresaColocando = null">Cancelar</button>
          <button type="button" class="btn-paso" :disabled="!seleccionCentros.length || guardandoCentros" @click="guardarCentros">
            {{ guardandoCentros ? 'Guardando…' : `Colocar (${seleccionCentros.length})` }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Modal: firma -->
    <Modal :visible="!!firmaUrl" max-width="max-w-lg" @cerrar="cerrarFirma">
      <div v-if="firmaEmpresa" class="flex flex-col gap-3 p-6 sm:p-8">
        <h2 class="pr-10 font-heading text-xl font-bold text-azul-noche">Firma de {{ firmaEmpresa.firma.firmante_nombre }}</h2>
        <img :src="firmaUrl" alt="Firma del empresario" class="w-full rounded-xl border border-borde bg-white" />
        <p class="break-all text-xs text-gray-500">Huella del documento firmado (SHA-256): {{ firmaEmpresa.firma.documento_sha256 }}</p>
      </div>
    </Modal>

    <!-- Modal: revisar y publicar reto -->
    <Modal :visible="!!detalle" max-width="max-w-2xl" @cerrar="detalle = null">
      <div v-if="detalle" class="flex min-h-0 flex-col">
        <div class="border-b border-gray-100 p-6 pr-16 sm:p-8 sm:pr-16">
          <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Creado por comercial · pendiente de validar para publicar</span>
          <h2 class="mt-2 break-words font-heading text-xl font-bold text-azul-noche">{{ detalle.borrador.reto.titulo }}</h2>
          <p class="mt-1 break-words text-sm text-texto-secundario">{{ detalle.borrador.reto.subtitulo }}</p>
        </div>
        <div class="min-h-0 space-y-4 overflow-y-auto p-6 text-sm sm:p-8">
          <p class="text-gray-500">{{ detalle.borrador.empresa?.nombre_comercial }} · {{ detalle.borrador.familia }} · {{ detalle.borrador.reto.ciclo }}</p>
          <p v-if="detalle.borrador.reto.pregunta_reto" class="rounded-xl bg-gray-50 p-3 font-semibold text-azul-noche">{{ detalle.borrador.reto.pregunta_reto }}</p>
          <div v-if="detalle.borrador.reto.prototipos?.length">
            <p class="mb-1 font-bold">Prototipos</p>
            <ul class="list-disc space-y-1 pl-5 text-gray-600"><li v-for="(p, i) in detalle.borrador.reto.prototipos" :key="i">{{ p }}</li></ul>
          </div>
          <div v-if="detalle.borrador.reto.evaluacion_oficial?.length">
            <p class="mb-1 font-bold">Resultados de aprendizaje</p>
            <ul class="space-y-1 text-gray-600">
              <li v-for="(ev, i) in detalle.borrador.reto.evaluacion_oficial" :key="i" class="break-words">
                <span class="font-semibold">{{ ev.modulo }}</span><template v-if="ev.ra"> — {{ ev.ra }}</template>
              </li>
            </ul>
          </div>

          <div class="border-t border-gray-100 pt-4">
            <p class="mb-2 font-bold">Publicar en</p>
            <p v-if="!detalle.centros_empresa.length" class="rounded-xl bg-amber-50 px-3 py-2 text-amber-800">
              La empresa aún no está en ningún centro. Colócala primero (arriba) y vuelve a abrir el reto.
            </p>
            <ul v-else class="space-y-1">
              <li v-for="c in detalle.centros_empresa" :key="c.id">
                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-gray-50">
                  <input v-model="centrosPublicar" type="checkbox" :value="c.id" class="h-5 w-5 shrink-0 accent-rol-fuerte" />
                  <span class="min-w-0 break-words font-semibold">{{ c.nombre }}</span>
                </label>
              </li>
            </ul>
            <p v-if="detalle.centros_sin_ciclo.length" class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-amber-800">
              El ciclo de este reto no se imparte en: {{ detalle.centros_sin_ciclo.join(', ') }}.
            </p>
          </div>
        </div>
        <div class="fila-pasos border-t border-gray-100 p-4 sm:px-8">
          <button type="button" class="btn-paso-atras" :disabled="publicando" @click="descartar">Descartar</button>
          <button type="button" class="btn-paso" :disabled="publicando || !centrosPublicar.length" @click="publicar">
            {{ publicando ? 'Publicando…' : 'Publicar' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<style scoped>
@reference "../style.css";

.card { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
</style>
