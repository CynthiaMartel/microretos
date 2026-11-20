<script setup>
/**
 * Apartado «Captación comercial» de Notificaciones (solo superadmin). Dos tareas:
 *  1. Empresas captadas (entrevista del comercial o formulario web) pendientes de validar: al
 *     validarlas se crean en Base de datos y se colocan en uno o varios centros, y desde ese
 *     momento las ven los docentes de esos centros. O se descartan (duplicada, spam…).
 *  2. Retos que el comercial generó delante de la empresa (borradores): con la empresa ya
 *     validada, se publican en sus centros o se descartan. Hasta entonces no salen en ninguna biblioteca.
 */
import AvisoAccionCritica from './AvisoAccionCritica.vue'
import MoverPapeleraModal from './MoverPapeleraModal.vue'
import { useAuthStore, ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB } from '../stores/auth.js'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Modal from './Modal.vue'
import {
  getEmpresasCaptadas, validarEmpresaCaptada, descartarEmpresaCaptada, getCoincidenciasEmpresa, getBorradoresPendientes, getBorradorCaptacion,
  publicarBorrador, descartarBorrador, getImagenFirma, getCentrosCaptacion, eliminarEmpresaCaptada, eliminarBorrador,
} from '../services/captacionService.js'
import { PREGUNTAS_DIAGNOSTICO, COLUMNA_DE } from '../config/preguntasDiagnostico.js'

const emit = defineEmits(['cambio'])

// «En curso»: la comercial aún la está entrevistando (captación en borrador)
const ESTADOS = [
  { key: 'borrador',             label: 'En curso' },
  { key: 'pendiente_validacion', label: 'Pendientes' },
  { key: 'validada',             label: 'Validadas' },
  { key: 'descartada',           label: 'Descartadas' },
]
const VACIO = {
  borrador:             'No hay entrevistas en curso.',
  pendiente_validacion: 'No hay empresas pendientes de validar.',
  validada:             'Todavía no se ha validado ninguna empresa captada.',
  descartada:           'No hay empresas descartadas.',
}
const estado = ref('pendiente_validacion')

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
// Retos: los de empresas por validar o validadas, o los de entrevistas en curso
const retosEnCurso = ref(false)
async function cargarBorradores() {
  const { data } = await getBorradoresPendientes(retosEnCurso.value)
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

async function verRetos(enCurso) {
  retosEnCurso.value = enCurso
  try { await cargarBorradores() } catch { error.value = 'No se han podido cargar los retos.' }
}

// ── Mover a la papelera (superadmin y Admin Central DuaLab) ──────────────────
const auth = useAuthStore()
const puedeBorrar = computed(() => [ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB].includes(auth.userRole))
const aBorrar = ref(null)    // { titulo, nombre, texto, borrar: () => Promise }
const borrando = ref(false)
function pedirBorrarEmpresa(e) {
  aBorrar.value = { titulo: 'Borrar empresa captada', nombre: e.nombre_comercial,
    texto: 'Con ella se van sus retos en borrador.' + (e.estado === 'validada' ? ' La empresa ya creada en Base de datos no se toca.' : ''),
    borrar: () => eliminarEmpresaCaptada(e.id) }
}
function pedirBorrarReto(b) {
  aBorrar.value = { titulo: 'Borrar reto en borrador', nombre: b.reto.titulo, texto: 'No se publicará en ningún centro.', borrar: () => eliminarBorrador(b.uuid) }
}
async function confirmarBorrar() {
  borrando.value = true
  try {
    const { data } = await aBorrar.value.borrar()
    aviso.value = data.message
    aBorrar.value = null
    detalle.value = null
    await cargarTodo()
    emit('cambio')
  } catch (e) {
    error.value = mensajeError(e, 'No se ha podido mover a la papelera.')
    aBorrar.value = null
  } finally {
    borrando.value = false
  }
}

async function cambiarEstado(key) {
  estado.value = key
  try { await cargarEmpresas() } catch { error.value = 'No se han podido cargar las empresas.' }
}

const fecha = (iso) => iso ? new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

// ── Validar y colocar en centros ──────────────────────────────────────────────
const empresaColocando = ref(null)
const seleccionCentros = ref([])
const buscaCentro      = ref('')
const guardandoCentros = ref(false)

const centrosFiltrados = computed(() => {
  const q = buscaCentro.value.trim().toLowerCase()
  return q ? centros.value.filter(c => c.nombre.toLowerCase().includes(q)) : centros.value
})

// Fusionar: si la misma empresa ya está en Base de datos (llegó por otra vía, o la creó un
// centro), se elige aquí y no se duplica; solo se le completa lo que le falte.
const fusionarCon      = ref(null)   // id de la empresa existente, o null = crear nueva
const coincidencias    = ref([])
const buscaEmpresa     = ref('')
const buscandoEmpresa  = ref(false)
async function buscarCoincidencias() {
  buscandoEmpresa.value = true
  try {
    coincidencias.value = (await getCoincidenciasEmpresa(empresaColocando.value.id, buscaEmpresa.value.trim())).data.data
  } catch {
    coincidencias.value = []
  } finally {
    buscandoEmpresa.value = false
  }
}

function abrirColocar(empresa) {
  empresaColocando.value = empresa
  seleccionCentros.value = (empresa.centros || []).map(c => c.id)
  buscaCentro.value = ''
  fusionarCon.value = null
  buscaEmpresa.value = ''
  coincidencias.value = []
  if (empresa.estado !== 'validada') buscarCoincidencias()
}
async function guardarCentros() {
  if (!seleccionCentros.value.length) return
  guardandoCentros.value = true
  try {
    const nueva = empresaColocando.value.estado !== 'validada'
    await validarEmpresaCaptada(empresaColocando.value.id, seleccionCentros.value, nueva ? fusionarCon.value : null)
    const enCentros = seleccionCentros.value.length === 1 ? '1 centro' : `${seleccionCentros.value.length} centros`
    aviso.value = nueva && fusionarCon.value
      ? `Fusionada con la empresa que ya estaba en Base de datos; disponible en ${enCentros}.`
      : nueva
      ? `«${empresaColocando.value.nombre_comercial}» ya está en Base de datos y disponible en ${enCentros}.`
      : `«${empresaColocando.value.nombre_comercial}» ahora está disponible en ${enCentros}.`
    empresaColocando.value = null
    await cargarTodo()
    emit('cambio')
  } catch (e) {
    error.value = mensajeError(e, 'No se ha podido validar la empresa.')
  } finally {
    guardandoCentros.value = false
  }
}

// ── Descartar (queda registrada con su motivo, no se borra) ───────────────────
const empresaDescartando = ref(null)
const motivoDescarte     = ref('')
const descartando        = ref(false)
function abrirDescartar(empresa) {
  empresaDescartando.value = empresa
  motivoDescarte.value = ''
}
async function confirmarDescarte() {
  descartando.value = true
  try {
    await descartarEmpresaCaptada(empresaDescartando.value.id, motivoDescarte.value.trim())
    aviso.value = `«${empresaDescartando.value.nombre_comercial}» descartada.`
    empresaDescartando.value = null
    await cargarTodo()
    emit('cambio')
  } catch (e) {
    error.value = mensajeError(e, 'No se ha podido descartar la empresa.')
  } finally {
    descartando.value = false
  }
}

// ── Entrevista: lo que contó la empresa ───────────────────────────────────────
// Respuestas que partieron de una idea de la IA que la empresa confirmó al comercial, y si
// respondió ella misma el formulario que se le envió por correo.
const respuestasDe = (e) => PREGUNTAS_DIAGNOSTICO
  .map(p => ({ campo: p.campo, pregunta: p.pregunta, respuesta: e[COLUMNA_DE[p.campo]], ia: (e.respuestas_ia ?? []).includes(p.campo) }))
  .filter(r => r.respuesta)
const resumenEntrevista = (e) => {
  const n = respuestasDe(e).length
  if (!n) return ''
  const partes = [`${n} de ${PREGUNTAS_DIAGNOSTICO.length} preguntas respondidas`]
  if (e.respuestas_ia?.length) partes.push(`${e.respuestas_ia.length} a partir de ideas de la IA`)
  if (e.formulario?.respondido_at) partes.push('respondida por la empresa por correo')
  return partes.join(' · ')
}
const empresaEntrevista = ref(null)

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
        <h2 class="font-heading text-lg font-bold">Empresas captadas</h2>
        <!-- En 360px las tres pestañas casi no caben: se estrechan y, si aun así no caben, el carril se desplaza -->
        <div class="inline-flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-gray-100 p-1" role="group" aria-label="Qué empresas ver">
          <button v-for="e in ESTADOS" :key="e.key" type="button" :aria-pressed="estado === e.key" @click="cambiarEstado(e.key)"
                  class="inline-flex h-9 shrink-0 items-center rounded-full px-2 text-sm font-semibold transition sm:px-4"
                  :class="estado === e.key ? 'bg-white text-azul-noche shadow-sm' : 'text-gray-600 hover:text-azul-noche'">
            {{ e.label }}
          </button>
        </div>
      </div>

      <div v-if="cargando && !empresas.length" class="space-y-2"><div v-for="n in 2" :key="n" class="h-24 animate-pulse rounded-xl bg-gray-100" /></div>
      <p v-else-if="!empresas.length" class="py-8 text-center text-sm text-gray-500">
        {{ VACIO[estado] }}
      </p>
      <ul v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <li v-for="e in empresas" :key="e.id" class="flex flex-col gap-3 rounded-xl border border-borde p-4">
          <div class="min-w-0">
            <span v-if="e.origen" class="mb-1 inline-flex w-fit rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ e.origen.nombre }}</span>
            <p class="break-words font-bold text-azul-noche">{{ e.nombre_comercial }}</p>
            <p class="text-sm text-texto-secundario">
              {{ [e.sector, e.municipio].filter(Boolean).join(' · ') || 'Sin sector' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
              <template v-if="e.comercial">Entrevista de {{ e.comercial }} · </template>{{ e.firmada_at ? `firmada el ${fecha(e.firmada_at)}` : `recibida el ${fecha(e.created_at)}` }}
              <template v-if="e.firma"> por {{ e.firma.firmante_nombre }}<template v-if="e.firma.firmante_cargo"> ({{ e.firma.firmante_cargo }})</template></template>
            </p>
          </div>

          <p v-if="e.notas" class="whitespace-pre-line break-words rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600">{{ e.notas }}</p>
          <p v-if="e.origen?.clave === 'formulario'" class="text-xs text-texto-secundario">
            Contacto: {{ [e.persona_contacto, e.email_general, e.telefono].filter(Boolean).join(' · ') }}
          </p>

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
              {{ c.nombre }}: {{ c.aceptado ? 'sí' : 'no' }}
            </span>
          </div>

          <p v-if="resumenEntrevista(e)" class="text-xs text-texto-secundario">
            <span class="font-semibold text-azul-noche">Entrevista:</span> {{ resumenEntrevista(e) }}
          </p>

          <p v-if="e.centros?.length" class="text-sm text-texto-secundario">
            <span class="font-semibold text-azul-noche">Centros:</span> {{ e.centros.map(c => c.nombre).join(', ') }}
          </p>
          <p v-if="e.estado === 'descartada' && e.motivo_descarte" class="break-words text-sm text-texto-secundario">
            <span class="font-semibold text-azul-noche">Motivo:</span> {{ e.motivo_descarte }}
          </p>
          <p v-if="e.borradores_pendientes" class="text-sm font-semibold text-amber-800">
            {{ e.borradores_pendientes }} {{ e.borradores_pendientes === 1 ? 'reto pendiente' : 'retos pendientes' }} de validar
          </p>

          <div class="mt-auto flex flex-wrap gap-2">
            <button v-if="e.estado === 'pendiente_validacion' || e.estado === 'validada'" type="button" class="accion-principal" @click="abrirColocar(e)">
              {{ e.estado === 'validada' ? 'Cambiar centros' : 'Validar y colocar en centros' }}
            </button>
            <button v-if="e.estado === 'pendiente_validacion'" type="button" class="accion-secundaria" @click="abrirDescartar(e)">Descartar</button>
            <button v-if="respuestasDe(e).length" type="button" class="accion-secundaria" @click="empresaEntrevista = e">Ver entrevista</button>
            <button v-if="e.firma" type="button" class="accion-secundaria" @click="verFirma(e)">Ver firma</button>
            <button v-if="puedeBorrar" type="button" class="accion-secundaria !text-red-700" @click="pedirBorrarEmpresa(e)">Borrar</button>
          </div>
        </li>
      </ul>
    </article>

    <!-- 2. Retos de comerciales pendientes de validar -->
    <article class="card p-4 sm:p-5">
      <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="font-heading text-lg font-bold">Retos de la comercial</h2>
        <div class="inline-flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-gray-100 p-1" role="group" aria-label="Qué retos ver">
          <button v-for="o in [{ v: false, t: 'Por validar' }, { v: true, t: 'En curso' }]" :key="o.t" type="button" :aria-pressed="retosEnCurso === o.v" @click="verRetos(o.v)"
                  class="inline-flex h-9 shrink-0 items-center rounded-full px-3 text-sm font-semibold transition sm:px-4"
                  :class="retosEnCurso === o.v ? 'bg-white text-azul-noche shadow-sm' : 'text-gray-600 hover:text-azul-noche'">{{ o.t }}</button>
        </div>
      </div>
      <div v-if="cargando && !borradores.length" class="space-y-2"><div v-for="n in 2" :key="n" class="h-16 animate-pulse rounded-xl bg-gray-100" /></div>
      <p v-else-if="!borradores.length" class="py-8 text-center text-sm text-gray-500">
        {{ retosEnCurso ? 'No hay retos de entrevistas en curso.' : 'No hay retos de comerciales pendientes.' }}
      </p>
      <ul v-else class="divide-y divide-gray-100">
        <li v-for="b in borradores" :key="b.uuid" class="flex items-center gap-2">
          <button type="button" class="flex min-w-0 flex-1 flex-col gap-1 rounded-xl px-2 py-3 text-left transition hover:bg-gray-50 sm:flex-row sm:items-center sm:gap-3" @click="abrirBorrador(b)">
            <span class="min-w-0 flex-1">
              <span class="block break-words text-sm font-bold">{{ b.reto.titulo }}</span>
              <span class="block text-sm text-gray-500">{{ b.captacion?.nombre_comercial }} · {{ b.familia }}</span>
            </span>
            <!-- Sin shrink-0 y corta: en tablet no debe estrechar el título del reto -->
            <span class="w-fit rounded-full px-2.5 py-0.5 text-xs font-semibold sm:whitespace-nowrap"
                  :class="retosEnCurso ? 'bg-gray-100 text-texto-secundario' : 'bg-amber-50 text-amber-800'">
              {{ retosEnCurso ? 'Entrevista en curso' : 'Pendiente de validar' }}
            </span>
          </button>
          <button v-if="puedeBorrar" type="button" class="accion-icono shrink-0 !text-red-700" :aria-label="`Borrar el reto ${b.reto.titulo}`" @click="pedirBorrarReto(b)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12.1A2 2 0 0116.1 21H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          </button>
        </li>
      </ul>
    </article>

    <!-- Modal: colocar en centros -->
    <Modal :visible="!!empresaColocando" max-width="max-w-lg" @cerrar="empresaColocando = null">
      <div v-if="empresaColocando" class="flex max-h-[90dvh] min-h-0 flex-col gap-4 overflow-y-auto p-6 sm:p-8">
        <div class="pr-10">
          <h2 class="font-heading text-xl font-bold text-azul-noche">{{ empresaColocando.estado === 'validada' ? 'Cambiar centros' : 'Validar empresa' }}</h2>
          <p class="mt-1 break-words text-sm text-texto-secundario">
            <template v-if="empresaColocando.estado !== 'validada'">«{{ empresaColocando.nombre_comercial }}» se dará de alta en Base de datos como empresa real y </template>
            <template v-else>«{{ empresaColocando.nombre_comercial }}» </template>quedará disponible para los docentes de los centros que marques.
          </p>
        </div>
        <!-- ¿Ya está en Base de datos? (solo al validar) -->
        <fieldset v-if="empresaColocando.estado !== 'validada'" class="space-y-2">
          <legend class="mb-1 text-sm font-bold text-azul-noche">¿Ya está en Base de datos?</legend>
          <p class="text-xs text-texto-secundario">Si es la misma empresa, elígela: no se duplica, solo se le completa lo que le falte.</p>
          <form class="flex gap-2" @submit.prevent="buscarCoincidencias">
            <input v-model="buscaEmpresa" type="search" placeholder="Buscar otra empresa…" aria-label="Buscar empresa en Base de datos"
                   class="h-11 min-w-0 flex-1 rounded-xl border border-borde-campo px-4 text-sm outline-none focus:border-rol-fuerte" />
            <button type="submit" class="accion-secundaria shrink-0" :disabled="buscandoEmpresa">Buscar</button>
          </form>
          <ul class="max-h-[28dvh] space-y-1 overflow-y-auto">
            <li>
              <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-gray-50">
                <input v-model="fusionarCon" type="radio" :value="null" class="h-5 w-5 shrink-0 accent-rol-fuerte" />
                <span class="min-w-0 break-words text-sm font-semibold">Es nueva: crear «{{ empresaColocando.nombre_comercial }}»</span>
              </label>
            </li>
            <li v-for="c in coincidencias" :key="c.id">
              <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-gray-50">
                <input v-model="fusionarCon" type="radio" :value="c.id" class="h-5 w-5 shrink-0 accent-rol-fuerte" />
                <span class="min-w-0">
                  <span class="block break-words text-sm font-semibold">Es «{{ c.nombre_comercial }}»</span>
                  <span class="block break-words text-xs text-texto-secundario">{{ [c.cif, c.municipio, c.centro].filter(Boolean).join(' · ') || 'Sin más datos' }}</span>
                </span>
              </label>
            </li>
            <li v-if="!buscandoEmpresa && !coincidencias.length" class="px-3 py-2 text-xs text-gray-500">No hay empresas parecidas en Base de datos.</li>
          </ul>
        </fieldset>

        <p class="text-sm font-bold text-azul-noche">Centros en los que queda disponible</p>
        <input v-model="buscaCentro" type="search" placeholder="Buscar centro…" aria-label="Buscar centro"
               class="h-11 w-full rounded-xl border border-borde-campo px-4 text-sm outline-none focus:border-rol-fuerte" />
        <ul class="min-h-0 max-h-[30dvh] space-y-1 overflow-y-auto">
          <li v-for="c in centrosFiltrados" :key="c.id">
            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-gray-50">
              <input v-model="seleccionCentros" type="checkbox" :value="c.id" class="h-5 w-5 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 break-words text-sm font-semibold">{{ c.nombre }}</span>
            </label>
          </li>
          <li v-if="!centrosFiltrados.length" class="px-3 py-4 text-sm text-gray-500">No hay centros con ese nombre.</li>
        </ul>
        <AvisoAccionCritica />
        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="empresaColocando = null">Cancelar</button>
          <button type="button" class="btn-paso" :disabled="!seleccionCentros.length || guardandoCentros" @click="guardarCentros">
            {{ guardandoCentros ? 'Guardando…' : `${empresaColocando.estado === 'validada' ? 'Guardar' : 'Validar'} (${seleccionCentros.length})` }}
          </button>
        </div>
      </div>
    </Modal>

    <MoverPapeleraModal :item="aBorrar" :ocupado="borrando" @cerrar="aBorrar = null" @confirmar="confirmarBorrar" />

    <!-- Modal: descartar -->
    <Modal :visible="!!empresaDescartando" max-width="max-w-lg" @cerrar="empresaDescartando = null">
      <div v-if="empresaDescartando" class="flex max-h-[90dvh] min-h-0 flex-col gap-4 overflow-y-auto p-6 sm:p-8">
        <div class="pr-10">
          <h2 class="font-heading text-xl font-bold text-azul-noche">Descartar empresa</h2>
          <p class="mt-1 break-words text-sm text-texto-secundario">«{{ empresaDescartando.nombre_comercial }}» no pasará a Base de datos y sus retos en borrador no se publicarán. Queda registrada en «Descartadas».</p>
        </div>
        <div>
          <label for="pcc-motivo" class="label-style">Motivo <span class="normal-case tracking-normal">(opcional)</span></label>
          <textarea id="pcc-motivo" v-model="motivoDescarte" rows="3" maxlength="500" class="input-style" placeholder="Ej.: duplicada de una empresa que ya está en Base de datos" />
        </div>
        <AvisoAccionCritica />
        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="empresaDescartando = null">Cancelar</button>
          <button type="button" class="btn-paso" :disabled="descartando" @click="confirmarDescarte">{{ descartando ? 'Descartando…' : 'Descartar' }}</button>
        </div>
      </div>
    </Modal>

    <!-- Modal: entrevista (lo que contó la empresa) -->
    <Modal :visible="!!empresaEntrevista" max-width="max-w-2xl" @cerrar="empresaEntrevista = null">
      <div v-if="empresaEntrevista" class="flex max-h-[90dvh] min-h-0 flex-col">
        <div class="border-b border-gray-100 p-6 pr-16 sm:p-8 sm:pr-16">
          <h2 class="break-words font-heading text-xl font-bold text-azul-noche">Entrevista · {{ empresaEntrevista.nombre_comercial }}</h2>
          <p class="mt-1 text-sm text-texto-secundario">
            <template v-if="empresaEntrevista.formulario?.respondido_at">La empresa respondió el formulario por correo el {{ fecha(empresaEntrevista.formulario.respondido_at) }}<template v-if="empresaEntrevista.comercial">; después la completó {{ empresaEntrevista.comercial }}</template>.</template>
            <template v-else-if="empresaEntrevista.comercial">Entrevista en persona de {{ empresaEntrevista.comercial }}.</template>
          </p>
          <p v-if="empresaEntrevista.respuestas_ia?.length" class="mt-2 text-xs text-texto-secundario">
            Las marcadas con «Idea de la IA» partieron de una sugerencia que la empresa confirmó al comercial: revísalas con más cuidado.
          </p>
        </div>
        <dl class="min-h-0 space-y-4 overflow-y-auto p-6 text-sm sm:p-8">
          <div v-for="r in respuestasDe(empresaEntrevista)" :key="r.campo">
            <dt class="font-semibold text-azul-noche">
              {{ r.pregunta }}
              <span v-if="r.ia" class="ml-1 inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">Idea de la IA</span>
            </dt>
            <dd class="mt-1 whitespace-pre-line break-words text-gray-600">{{ r.respuesta }}</dd>
          </div>
        </dl>
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
          <p class="text-gray-500">{{ detalle.borrador.captacion?.nombre_comercial }} · {{ detalle.borrador.familia }} · {{ detalle.borrador.reto.ciclo }}</p>
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
              {{ detalle.borrador.captacion?.empresa_id
                ? 'La empresa aún no está en ningún centro. Cambia sus centros (arriba) y vuelve a abrir el reto.'
                : 'La empresa aún no está validada. Valídala y colócala en centros (arriba) y vuelve a abrir el reto.' }}
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
        <div class="px-4 pt-4 sm:px-8"><AvisoAccionCritica texto="Publicar crea el reto en la biblioteca de cada centro elegido; descartarlo lo deja fuera. Queda en el Historial de Central con tu nombre y la fecha." /></div>
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
