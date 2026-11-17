<script setup>
/**
 * Centros educativos captados (apartado «Captación comercial» de Notificaciones, solo
 * superadmin): llegan por la entrevista del comercial o por el formulario de centros de la web.
 * Validar = darlo de alta en DuaLab (centro_educativo) o enlazarlo con el que ya existe;
 * descartar = queda registrado con su motivo. Va junto a PanelCaptacionComercial (empresas).
 */
import { ref, onMounted } from 'vue'
import Modal from './Modal.vue'
import {
  getCentrosCaptados, validarCentroCaptado, descartarCentroCaptado, getCoincidenciasCentro,
} from '../services/captacionService.js'

const emit = defineEmits(['cambio'])

const ESTADOS = [
  { key: 'pendiente_validacion', label: 'Pendientes' },
  { key: 'validada',             label: 'Validados' },
  { key: 'descartada',           label: 'Descartados' },
]
const VACIO = {
  pendiente_validacion: 'No hay centros pendientes de validar.',
  validada:             'Todavía no se ha validado ningún centro captado.',
  descartada:           'No hay centros descartados.',
}
const ACCION = { registro: 'Quiere darse de alta', info: 'Pide más información', demo: 'Pide una demo' }

const estado   = ref('pendiente_validacion')
const centros  = ref([])
const cargando = ref(false)
const error    = ref('')
const aviso    = ref('')

const mensajeError = (e, porDefecto) => {
  const errores = e.response?.data?.errors
  return (errores && Object.values(errores)[0]?.[0]) || e.response?.data?.error || e.response?.data?.message || porDefecto
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    centros.value = (await getCentrosCaptados(estado.value)).data.data
  } catch {
    error.value = 'No se han podido cargar los centros captados.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

function cambiarEstado(key) {
  estado.value = key
  cargar()
}

const fecha = (iso) => iso ? new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

// ── Validar: alta nueva o enlace con un centro que ya está en DuaLab ─────────────
const validando      = ref(null)
const enlazarCon     = ref(null)   // id de centro_educativo, o null = dar de alta
const coincidencias  = ref([])
const buscaCentro    = ref('')
const buscando       = ref(false)
const guardando      = ref(false)

async function buscar() {
  buscando.value = true
  try {
    coincidencias.value = (await getCoincidenciasCentro(validando.value.id, buscaCentro.value.trim())).data.data
  } catch {
    coincidencias.value = []
  } finally {
    buscando.value = false
  }
}
function abrirValidar(c) {
  validando.value = c
  enlazarCon.value = null
  buscaCentro.value = ''
  coincidencias.value = []
  buscar()
}
async function confirmarValidar() {
  guardando.value = true
  try {
    await validarCentroCaptado(validando.value.id, enlazarCon.value)
    aviso.value = enlazarCon.value
      ? `«${validando.value.centro_nombre}» enlazado con el centro que ya estaba en DuaLab.`
      : `«${validando.value.centro_nombre}» dado de alta en DuaLab.`
    validando.value = null
    await cargar()
    emit('cambio')
  } catch (e) {
    error.value = mensajeError(e, 'No se ha podido validar el centro.')
  } finally {
    guardando.value = false
  }
}

// ── Descartar ─────────────────────────────────────────────────────────────────
const descartando = ref(null)
const motivo      = ref('')
function abrirDescartar(c) {
  descartando.value = c
  motivo.value = ''
}
async function confirmarDescarte() {
  guardando.value = true
  try {
    await descartarCentroCaptado(descartando.value.id, motivo.value.trim())
    aviso.value = `«${descartando.value.centro_nombre}» descartado.`
    descartando.value = null
    await cargar()
    emit('cambio')
  } catch (e) {
    error.value = mensajeError(e, 'No se ha podido descartar el centro.')
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <article class="card p-4 sm:p-5">
    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="font-heading text-lg font-bold">Centros educativos captados</h2>
      <!-- En 360px las tres pestañas casi no caben: se estrechan y, si aun así no caben, el carril se desplaza -->
      <div class="inline-flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-gray-100 p-1" role="group" aria-label="Qué centros ver">
        <button v-for="e in ESTADOS" :key="e.key" type="button" :aria-pressed="estado === e.key" @click="cambiarEstado(e.key)"
                class="inline-flex h-9 shrink-0 items-center rounded-full px-2 text-sm font-semibold transition sm:px-4"
                :class="estado === e.key ? 'bg-white text-azul-noche shadow-sm' : 'text-gray-600 hover:text-azul-noche'">
          {{ e.label }}
        </button>
      </div>
    </div>

    <p v-if="error" role="alert" class="mb-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    <p v-if="aviso" role="status" class="mb-3 rounded-xl bg-centros/10 px-3 py-2 text-sm font-semibold text-centros">{{ aviso }}</p>

    <div v-if="cargando && !centros.length" class="space-y-2"><div v-for="n in 2" :key="n" class="h-24 animate-pulse rounded-xl bg-gray-100" /></div>
    <p v-else-if="!centros.length" class="py-8 text-center text-sm text-gray-500">{{ VACIO[estado] }}</p>
    <ul v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
      <li v-for="c in centros" :key="c.id" class="flex flex-col gap-3 rounded-xl border border-borde p-4">
        <div class="min-w-0">
          <div class="mb-1 flex flex-wrap gap-1.5">
            <span v-if="c.origen" class="inline-flex w-fit rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ c.origen.nombre }}</span>
            <span v-if="c.accion" class="inline-flex w-fit rounded-full bg-centros/10 px-2.5 py-0.5 text-xs font-semibold text-centros">{{ ACCION[c.accion] }}</span>
          </div>
          <p class="break-words font-bold text-azul-noche">{{ c.centro_nombre }}</p>
          <p class="text-sm text-texto-secundario">{{ [c.municipio, c.provincia].filter(Boolean).join(' · ') }}</p>
          <p class="mt-1 text-xs text-gray-500">
            <template v-if="c.comercial">Entrevista de {{ c.comercial }} · </template>recibido el {{ fecha(c.created_at) }}
          </p>
        </div>

        <p class="break-words text-sm text-texto-secundario">
          <span class="font-semibold text-azul-noche">Contacto:</span>
          {{ [`${c.contacto_nombre} ${c.contacto_apellidos ?? ''}`.trim(), c.contacto_cargo].filter(Boolean).join(', ') }}
          <span class="block">{{ [c.contacto_email, c.contacto_telefono].filter(Boolean).join(' · ') }}</span>
        </p>

        <div v-if="c.familias?.length" class="flex flex-wrap gap-1.5">
          <span v-for="f in c.familias" :key="f" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ f }}</span>
        </div>

        <p v-if="c.comentarios" class="whitespace-pre-line break-words rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600">{{ c.comentarios }}</p>

        <div v-if="c.consentimientos?.length" class="flex flex-wrap gap-1.5 text-xs">
          <span v-for="k in c.consentimientos" :key="k.clave" class="rounded-full px-2.5 py-0.5 font-semibold"
                :class="k.aceptado ? 'bg-empresas/10 text-empresas-dark' : 'bg-red-50 text-red-700'">
            {{ k.nombre }}: {{ k.aceptado ? 'sí' : 'no' }}
          </span>
        </div>
        <p v-else class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">
          Sin aceptación registrada: el formulario web solo muestra un texto informativo de privacidad.
        </p>

        <p v-if="c.centro_educativo" class="text-sm text-texto-secundario">
          <span class="font-semibold text-azul-noche">En DuaLab como:</span> {{ c.centro_educativo.nombre }}
        </p>
        <p v-if="c.estado === 'descartada' && c.motivo_descarte" class="break-words text-sm text-texto-secundario">
          <span class="font-semibold text-azul-noche">Motivo:</span> {{ c.motivo_descarte }}
        </p>

        <div v-if="c.estado === 'pendiente_validacion'" class="mt-auto flex flex-wrap gap-2">
          <button type="button" class="accion-principal" @click="abrirValidar(c)">Validar y dar de alta</button>
          <button type="button" class="accion-secundaria" @click="abrirDescartar(c)">Descartar</button>
        </div>
      </li>
    </ul>

    <!-- Modal: validar -->
    <Modal :visible="!!validando" max-width="max-w-lg" @cerrar="validando = null">
      <div v-if="validando" class="flex max-h-[90dvh] min-h-0 flex-col gap-4 overflow-y-auto p-6 sm:p-8">
        <div class="pr-10">
          <h2 class="font-heading text-xl font-bold text-azul-noche">Validar centro</h2>
          <p class="mt-1 break-words text-sm text-texto-secundario">
            Si «{{ validando.centro_nombre }}» ya está en DuaLab, elígelo para no duplicarlo. Si no, se dará de alta con su nombre y municipio.
          </p>
        </div>
        <form class="flex gap-2" @submit.prevent="buscar">
          <input v-model="buscaCentro" type="search" placeholder="Buscar otro centro…" aria-label="Buscar centro en DuaLab"
                 class="h-11 min-w-0 flex-1 rounded-xl border border-borde-campo px-4 text-sm outline-none focus:border-rol-fuerte" />
          <button type="submit" class="accion-secundaria shrink-0" :disabled="buscando">Buscar</button>
        </form>
        <ul class="max-h-[40dvh] space-y-1 overflow-y-auto">
          <li>
            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-gray-50">
              <input v-model="enlazarCon" type="radio" :value="null" class="h-5 w-5 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 break-words text-sm font-semibold">Es nuevo: dar de alta «{{ validando.centro_nombre }}»</span>
            </label>
          </li>
          <li v-for="c in coincidencias" :key="c.id">
            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-3 py-2 hover:bg-gray-50">
              <input v-model="enlazarCon" type="radio" :value="c.id" class="h-5 w-5 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0">
                <span class="block break-words text-sm font-semibold">Es «{{ c.nombre }}»</span>
                <span v-if="c.municipio" class="block text-xs text-texto-secundario">{{ c.municipio }}</span>
              </span>
            </label>
          </li>
          <li v-if="!buscando && !coincidencias.length" class="px-3 py-2 text-xs text-gray-500">No hay centros parecidos en DuaLab.</li>
        </ul>
        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="validando = null">Cancelar</button>
          <button type="button" class="btn-paso" :disabled="guardando" @click="confirmarValidar">
            {{ guardando ? 'Guardando…' : (enlazarCon ? 'Enlazar' : 'Dar de alta') }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Modal: descartar -->
    <Modal :visible="!!descartando" max-width="max-w-lg" @cerrar="descartando = null">
      <div v-if="descartando" class="flex flex-col gap-4 p-6 sm:p-8">
        <div class="pr-10">
          <h2 class="font-heading text-xl font-bold text-azul-noche">Descartar centro</h2>
          <p class="mt-1 break-words text-sm text-texto-secundario">«{{ descartando.centro_nombre }}» no se dará de alta. Queda registrado en «Descartados».</p>
        </div>
        <div>
          <label for="pcc2-motivo" class="label-style">Motivo <span class="normal-case tracking-normal">(opcional)</span></label>
          <textarea id="pcc2-motivo" v-model="motivo" rows="3" maxlength="500" class="input-style" placeholder="Ej.: duplicado de una solicitud anterior" />
        </div>
        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="descartando = null">Cancelar</button>
          <button type="button" class="btn-paso" :disabled="guardando" @click="confirmarDescarte">{{ guardando ? 'Descartando…' : 'Descartar' }}</button>
        </div>
      </div>
    </Modal>
  </article>
</template>

<style scoped>
@reference "../style.css";

.card { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
</style>
