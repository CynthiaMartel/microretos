<!-- DuaLab Central → Seguimiento de prácticas → Planes de proyecto: todos los planes de todos los
     centros, para validarlos y crearles el cronograma sin salir de Central. Un plan recién creado
     queda «en edición» hasta que el docente completa su asistente en la app DuaLab; entonces queda
     pendiente de validar (lo puede validar DuaLab, el docente o la empresa). Ya validado, se le
     crea el cronograma (CronogramaPlan, la misma pantalla que en la app) y aparece en la agenda. -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import FiltroSelect from '../../src/components/FiltroSelect.vue'
import { getPlanes, getCronogramas, validarPlan } from '../../src/services/planService.js'

const router = useRouter()
const planes = ref([])
const cronogramas = ref([])
const cargando = ref(true)
const error = ref('')
const aviso = ref('')
const validando = ref(null)

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const [p, c] = await Promise.all([getPlanes(), getCronogramas()])
    planes.value = p.data
    cronogramas.value = c.data
  } catch {
    error.value = 'No se han podido cargar los planes. Recarga la página.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

const cronogramaDe = (p) => cronogramas.value.find(c => c.microproyecto_uuid === p.uuid) ?? null
// Estado para Central: en edición (lo completa el docente) · pendiente de validar · validado
const ESTADOS = {
  en_edicion: { texto: 'En edición', clases: 'bg-gray-100 text-texto-secundario' },
  propuesta: { texto: 'Sin validar', clases: 'bg-amber-50 text-amber-800' },
  validado: { texto: 'Validado', clases: 'bg-emerald-50 text-emerald-800' },
}
const estadoDe = (p) => (ESTADOS[p.estado] ? p.estado : 'en_edicion')
const docenteDe = (p) => p.datos_centro?.docente_nombre || ''

// ── Filtros ──
const busqueda = ref('')
const filtroEstado = ref('')
const filtroCronograma = ref('')
const filtroCentro = ref('')
const contar = (fn) => planes.value.filter(fn).length
const OPCIONES_ESTADO = computed(() => [
  { value: '', label: 'Todos' },
  { value: 'propuesta', label: 'Sin validar', count: contar(p => estadoDe(p) === 'propuesta') },
  { value: 'validado', label: 'Validados', count: contar(p => estadoDe(p) === 'validado') },
  { value: 'en_edicion', label: 'En edición', count: contar(p => estadoDe(p) === 'en_edicion') },
])
const OPCIONES_CRONOGRAMA = [{ value: '', label: 'Todos' }, { value: 'con', label: 'Con cronograma' }, { value: 'sin', label: 'Sin cronograma' }]
const centros = computed(() => [...new Set(planes.value.map(p => p.centro_nombre).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const norm = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const visibles = computed(() => {
  const q = norm(busqueda.value.trim())
  return planes.value.filter(p =>
    (!filtroEstado.value || estadoDe(p) === filtroEstado.value)
    && (!filtroCronograma.value || (filtroCronograma.value === 'con') === !!cronogramaDe(p))
    && (!filtroCentro.value || p.centro_nombre === filtroCentro.value)
    && (!q || [p.titulo, p.empresa_nombre, p.centro_nombre, p.ciclo_nombre, docenteDe(p)].some(t => norm(t).includes(q))))
})
const hayFiltros = computed(() => !!(busqueda.value.trim() || filtroEstado.value || filtroCronograma.value || filtroCentro.value))
function limpiar() { busqueda.value = ''; filtroEstado.value = ''; filtroCronograma.value = ''; filtroCentro.value = '' }

async function validar(p) {
  if (validando.value) return
  validando.value = p.uuid
  error.value = ''
  aviso.value = ''
  try {
    await validarPlan(p.uuid, 'validar')
    p.estado = 'validado'
    p.docente_validado = true
    aviso.value = `«${p.titulo}» validado. Ya puedes crearle el cronograma.`
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'No se ha podido validar el plan.'
  } finally {
    validando.value = null
  }
}

const fecha = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Planes de" destacado="proyecto" color="text-rol"
                       :volver="{ name: 'practicas-inicio' }" volver-label="Seguimiento de prácticas"
                       subtitulo="Los planes que el alumnado seleccionado desarrolla en la empresa: valídalos y créales el cronograma para que aparezcan en la agenda.">
        <button type="button" class="accion-principal" @click="router.push({ name: 'destacados' })">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
          Crear desde destacados
        </button>
      </CabeceraSeccion>

      <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar por plan, empresa, centro, ciclo o docente…"
                    :resultados="cargando ? null : visibles.length" :unidad="['plan', 'planes']"
                    :hay-activos="hayFiltros" @limpiar="limpiar">
        <FiltroSegmentado label="Estado" v-model="filtroEstado" :opciones="OPCIONES_ESTADO" />
        <FiltroSegmentado label="Cronograma" v-model="filtroCronograma" :opciones="OPCIONES_CRONOGRAMA"
                          icono="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        <FiltroSelect label="Centro educativo" v-model="filtroCentro" :opciones="centros" todos="Todos los centros" />
      </FiltrosPanel>

      <p v-if="aviso" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{{ aviso }}</p>
      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <ul v-if="cargando" class="grid grid-cols-1 gap-4 lg:grid-cols-2" aria-label="Cargando">
        <li v-for="n in 4" :key="n" class="h-48 animate-pulse rounded-2xl border border-borde bg-white" />
      </ul>
      <div v-else-if="!visibles.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        <p>{{ planes.length ? 'No hay planes con estos filtros.' : 'Todavía no hay planes de proyecto.' }}</p>
        <p v-if="!planes.length" class="mt-1">Se crean desde Alumnado destacado o, en la app DuaLab, desde Planes de proyecto.</p>
      </div>

      <ul v-else class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
        <li v-for="p in visibles" :key="p.uuid"
            class="flex min-w-0 flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm transition-shadow hover:shadow-md sm:p-5">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold uppercase tracking-wide" :class="ESTADOS[estadoDe(p)].clases">
              {{ ESTADOS[estadoDe(p)].texto }}
            </span>
            <span v-if="cronogramaDe(p)" class="inline-flex items-center rounded-full bg-rol/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-rol-fuerte">Con cronograma</span>
            <span v-if="p.curso" class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-azul-noche">{{ p.curso }}º curso</span>
          </div>
          <h3 class="mt-2 line-clamp-2 font-heading text-base font-bold leading-snug text-azul-noche" :title="p.titulo">{{ p.titulo || 'Plan sin título' }}</h3>
          <p class="mt-1 text-sm leading-snug text-texto-secundario">
            {{ p.centro_nombre || 'Sin centro' }}<template v-if="p.ciclo_nombre"> · {{ p.ciclo_nombre }}</template>
          </p>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-texto-secundario">
            <span v-if="docenteDe(p)" class="inline-flex items-center gap-1.5">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 20c0-4 3.6-7 8-7s8 3 8 7" /></svg>
              {{ docenteDe(p) }}
            </span>
            <span v-if="p.horas_estimadas">{{ p.horas_estimadas }} h previstas por alumno/a</span>
            <span v-if="cronogramaDe(p)">{{ fecha(cronogramaDe(p).fecha) }} → {{ fecha(cronogramaDe(p).fecha_fin) }}</span>
          </div>
          <p v-if="p.expectativas_empresa" class="mt-3 line-clamp-3 break-words rounded-xl bg-[#F8FAFC] px-3 py-2 text-sm text-azul-noche">
            <span class="font-semibold">Qué espera la empresa:</span> {{ p.expectativas_empresa }}
          </p>

          <!-- Pie: empresa y lo que toca hacer según el estado -->
          <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-borde pt-3">
            <span class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-empresas/10 text-empresas-dark sm:inline-flex" aria-hidden="true">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </span>
            <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ p.empresa_nombre || 'Sin empresa' }}</span>

            <p v-if="estadoDe(p) === 'en_edicion'" class="w-full text-xs font-semibold text-texto-secundario sm:w-auto">
              Lo completa {{ docenteDe(p) || 'el docente' }} en DuaLab; después se valida aquí.
            </p>
            <button v-else-if="estadoDe(p) === 'propuesta'" type="button" class="accion-principal disabled:opacity-60"
                    :disabled="validando === p.uuid" @click="validar(p)">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              {{ validando === p.uuid ? 'Validando…' : 'Validar' }}
            </button>
            <RouterLink v-else-if="cronogramaDe(p)" :to="{ name: 'agenda' }" class="accion-secundaria">Ver en la agenda</RouterLink>
            <RouterLink v-else :to="{ name: 'plan-cronograma', params: { uuid: p.uuid } }" class="accion-principal">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              Crear cronograma
            </RouterLink>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
