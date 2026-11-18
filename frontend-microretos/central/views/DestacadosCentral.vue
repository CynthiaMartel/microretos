<!-- DuaLab Central → Alumnado destacado (P3b): DuaLab, como empresa intermediaria, elige al
     alumnado más destacado de los encuentros creativos para las prácticas. Se marca por alias (en
     la plataforma no hay nombres reales): después DuaLab pregunta al docente quién es cada alias y
     envía los datos a la empresa fuera de la plataforma. El docente del encuentro ve la marca. -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import FiltroSelect from '../../src/components/FiltroSelect.vue'
import { getEncuentrosDestacados, marcarDestacado } from '../../src/services/destacadoService.js'
import { crearPlan } from '../../src/services/planService.js'
import { nivelCurso } from '../../src/utils/formatCurso.js'

const encuentros = ref([])
const cargando = ref(true)
const error = ref('')
const guardando = ref(new Set())

// Se cargan una vez (como mucho 100, primero los valorados por la empresa) y se filtran aquí, al momento
async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    encuentros.value = (await getEncuentrosDestacados()).data
  } catch {
    error.value = 'No se han podido cargar los encuentros. Recarga la página.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

// ── Filtros (mismo panel que la Biblioteca de retos) ──
const busqueda = ref('')
const filtroCentro = ref('')
const filtroCiclo = ref('')
const filtroNivel = ref('')
const filtroValoracion = ref('')   // '' | 'valorados' | 'sin_valorar'
const filtroDestacados = ref('')   // '' | 'con' | 'sin'

const destacadosDe = (e) => e.equipos.reduce((n, g) => n + g.miembros.filter(m => m.destacado).length, 0)
const unicos = (lista) => [...new Set(lista.filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'))
const centros = computed(() => unicos(encuentros.value.map(e => e.centro_educativo)))
const ciclos = computed(() => unicos(encuentros.value.filter(e => !filtroCentro.value || e.centro_educativo === filtroCentro.value).map(e => e.ciclo_formativo)))
const OPCIONES_NIVEL = [{ value: '', label: 'Todos' }, { value: '1', label: '1º' }, { value: '2', label: '2º' }]
const OPCIONES_VALORACION = [{ value: '', label: 'Todos' }, { value: 'valorados', label: 'Valorados' }, { value: 'sin_valorar', label: 'Sin valorar' }]
const OPCIONES_DESTACADOS = [{ value: '', label: 'Todos' }, { value: 'con', label: 'Con destacados' }, { value: 'sin', label: 'Sin destacados' }]

const norm = (t) => String(t ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
const visibles = computed(() => {
  const q = norm(busqueda.value.trim())
  return encuentros.value.filter(e =>
    (!filtroCentro.value || e.centro_educativo === filtroCentro.value)
    && (!filtroCiclo.value || e.ciclo_formativo === filtroCiclo.value)
    && (!filtroNivel.value || nivelCurso(e.curso) === filtroNivel.value)
    && (!filtroValoracion.value || (filtroValoracion.value === 'valorados') === !!e.valoracion)
    && (!filtroDestacados.value || (filtroDestacados.value === 'con') === destacadosDe(e) > 0)
    && (!q || [e.propuesta.titulo, e.propuesta.empresa, e.centro_educativo, e.docente, e.ciclo_formativo,
      ...e.equipos.flatMap(g => g.miembros.map(m => m.alias))].some(t => norm(t).includes(q))))
})
const totalDestacados = computed(() => visibles.value.reduce((n, e) => n + destacadosDe(e), 0))
const hayFiltros = computed(() => !!(busqueda.value.trim() || filtroCentro.value || filtroCiclo.value || filtroNivel.value || filtroValoracion.value || filtroDestacados.value))
function limpiar() {
  busqueda.value = ''; filtroCentro.value = ''; filtroCiclo.value = ''; filtroNivel.value = ''; filtroValoracion.value = ''; filtroDestacados.value = ''
}

async function alternar(m) {
  if (guardando.value.has(m.id)) return
  guardando.value = new Set([...guardando.value, m.id])
  const anterior = m.destacado
  m.destacado = !anterior // optimista; se revierte si falla
  try {
    m.destacado = (await marcarDestacado(m.id, m.destacado)).data.destacado
  } catch {
    m.destacado = anterior
    error.value = 'No se pudo guardar la marca. Inténtalo de nuevo.'
  } finally {
    const s = new Set(guardando.value); s.delete(m.id); guardando.value = s
  }
}

// Plan de proyecto (P4c): DuaLab lo crea a partir de la propuesta del encuentro; queda a nombre
// del docente de esa propuesta, que lo completa y le crea el cronograma en DuaLab
const creandoPlan = ref(null)
const planesCreados = ref({})   // encuentroId → título del plan creado
async function crearPlanDe(e) {
  if (!e.propuesta.uuid || creandoPlan.value) return
  creandoPlan.value = e.id
  error.value = ''
  try {
    const { data } = await crearPlan({ origen_uuid: e.propuesta.uuid })
    planesCreados.value = { ...planesCreados.value, [e.id]: data.titulo }
  } catch (err) {
    const errores = err.response?.data?.errors
    error.value = (errores && Object.values(errores)[0]?.[0]) || err.response?.data?.message || 'No se pudo crear el plan.'
  } finally {
    creandoPlan.value = null
  }
}

const fecha = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Alumnado" destacado="destacado" color="text-rol"
                       :volver="{ name: 'practicas-inicio' }" volver-label="Seguimiento de prácticas"
                       subtitulo="Elige, por alias, al alumnado más destacado de los encuentros creativos para las prácticas. Después pide al docente quién es cada alias." />

      <!-- Filtros: mismo panel que la Biblioteca de retos -->
      <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar por propuesta, empresa, centro, docente o alias…"
                    :resultados="cargando ? null : visibles.length" :unidad="['encuentro', 'encuentros']"
                    :hay-activos="hayFiltros" @limpiar="limpiar">
        <FiltroSelect label="Centro educativo" v-model="filtroCentro" :opciones="centros" todos="Todos los centros" />
        <FiltroSelect label="Ciclo formativo" v-model="filtroCiclo" :opciones="ciclos" todos="Todos los ciclos" />
        <FiltroSegmentado label="Nivel" v-model="filtroNivel" :opciones="OPCIONES_NIVEL" />
        <FiltroSegmentado label="Valoración de la empresa" v-model="filtroValoracion" :opciones="OPCIONES_VALORACION"
                          icono="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11 5.52.44c.5.04.7.66.32.98l-4.2 3.6 1.28 5.38a.56.56 0 01-.84.61L12 16.73l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.38-4.2-3.6a.56.56 0 01.32-.98l5.52-.44 2.13-5.11z" />
        <FiltroSegmentado label="Destacados" v-model="filtroDestacados" :opciones="OPCIONES_DESTACADOS"
                          icono="M5 3v18l7-5 7 5V3a1 1 0 00-1-1H6a1 1 0 00-1 1z" />
      </FiltrosPanel>

      <p v-if="!cargando && visibles.length" class="text-sm text-texto-secundario" aria-live="polite">
        <strong class="font-semibold text-azul-noche">{{ totalDestacados }}</strong> {{ totalDestacados === 1 ? 'alias destacado' : 'alias destacados' }} en estos encuentros.
      </p>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <ul v-if="cargando" class="grid grid-cols-1 gap-4 xl:grid-cols-2" aria-label="Cargando">
        <li v-for="n in 4" :key="n" class="h-56 animate-pulse rounded-2xl border border-borde bg-white" />
      </ul>
      <p v-else-if="!visibles.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        No hay encuentros con estos filtros.
      </p>

      <!-- Tarjetas: mismo lenguaje que las de la app (chips, título, meta y pie con la empresa) -->
      <ul v-else class="grid grid-cols-1 items-start gap-4 xl:grid-cols-2">
        <li v-for="e in visibles" :key="e.id"
            class="flex min-w-0 flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm transition-shadow hover:shadow-md sm:p-5">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="inline-flex items-center rounded-full bg-rol/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-rol-fuerte">
              {{ e.curso }} · Clase {{ e.grupo }}
            </span>
            <span v-if="e.valoracion" class="inline-flex items-center gap-1 rounded-full bg-empresas/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-empresas-dark"
                  :title="e.valoracion.interes_plan_practicas ? `Interés en un plan en prácticas: ${e.valoracion.interes_plan_practicas}` : ''">
              ★ Empresa {{ e.valoracion.global }}/5
            </span>
            <span v-else class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-texto-secundario">Sin valorar</span>
            <span v-if="e.valoracion?.interes_plan_practicas" class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-azul-noche">
              Plan en prácticas: {{ e.valoracion.interes_plan_practicas }}
            </span>
            <span v-if="destacadosDe(e)" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-amber-800">
              ★ {{ destacadosDe(e) }} {{ destacadosDe(e) === 1 ? 'destacado' : 'destacados' }}
            </span>
          </div>

          <h3 class="mt-2 line-clamp-2 font-heading text-base font-bold leading-snug text-azul-noche" :title="e.propuesta.titulo">
            {{ e.propuesta.titulo || 'Sin propuesta' }}
          </h3>
          <p class="mt-1 text-sm leading-snug text-texto-secundario">
            {{ e.centro_educativo || 'Sin centro' }}<template v-if="e.ciclo_formativo"> · {{ e.ciclo_formativo }}</template>
          </p>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-texto-secundario">
            <span v-if="e.docente" class="inline-flex items-center gap-1.5">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 20c0-4 3.6-7 8-7s8 3 8 7" /></svg>
              {{ e.docente }}
            </span>
            <span v-if="e.fecha" class="inline-flex items-center gap-1.5">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              {{ fecha(e.fecha) }}
            </span>
          </div>

          <p v-if="e.valoracion?.comentarios" class="mt-3 whitespace-pre-line break-words rounded-xl bg-[#F8FAFC] px-3 py-2 text-sm italic text-azul-noche">«{{ e.valoracion.comentarios }}»</p>

          <!-- Grupos: se marca por alias (★) -->
          <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            <div v-for="g in e.equipos" :key="g.id" class="min-w-0 rounded-xl border border-borde bg-[#F8FAFC] p-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold">{{ g.nombre }}</p>
                <span class="text-xs text-texto-secundario">{{ g.fases_completadas }}/5 fases<template v-if="g.entrega"> · entregó</template></span>
              </div>
              <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-200" role="progressbar" :aria-valuenow="g.fases_completadas" aria-valuemin="0" aria-valuemax="5" :aria-label="`Fases completadas por ${g.nombre}`">
                <div class="h-full rounded-full bg-rol" :style="{ width: (g.fases_completadas / 5 * 100) + '%' }" />
              </div>
              <p v-if="g.entrega?.descripcion" class="mt-1.5 line-clamp-2 break-words text-xs text-texto-secundario">{{ g.entrega.descripcion }}</p>
              <a v-if="g.entrega?.url" :href="g.entrega.url" target="_blank" rel="noopener noreferrer nofollow"
                 class="mt-1 inline-flex min-h-9 items-center text-xs font-semibold text-rol-fuerte underline">Ver entregable<span class="sr-only"> (se abre en otra pestaña)</span></a>
              <ul class="mt-2 flex flex-wrap gap-1.5" :aria-label="`Alumnado de ${g.nombre}`">
                <li v-for="m in g.miembros" :key="m.id" class="min-w-0 max-w-full">
                  <button type="button" @click="alternar(m)" :aria-pressed="m.destacado" :disabled="guardando.has(m.id)"
                          :title="m.destacado ? 'Quitar de destacados' : 'Marcar como destacado para prácticas'"
                          class="inline-flex min-h-9 max-w-full items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors disabled:opacity-60"
                          :class="m.destacado ? 'border-amber-400 bg-amber-50 text-amber-900' : 'border-borde bg-white hover:bg-gray-50'">
                    <span aria-hidden="true">{{ m.destacado ? '★' : '☆' }}</span>
                    <span class="truncate">{{ m.alias }}</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>

          <!-- Pie: empresa y acción -->
          <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-borde pt-3">
            <span class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-empresas/10 text-empresas-dark sm:inline-flex" aria-hidden="true">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </span>
            <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ e.propuesta.empresa || 'Sin empresa' }}</span>
            <p v-if="planesCreados[e.id]" class="w-full text-xs font-semibold text-empresas-dark sm:w-auto" role="status">
              Plan creado: lo completa {{ e.docente || 'el docente' }} en DuaLab.
            </p>
            <button v-else-if="e.propuesta.uuid" type="button" @click="crearPlanDe(e)" :disabled="creandoPlan === e.id"
                    class="accion-secundaria disabled:opacity-60">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
              {{ creandoPlan === e.id ? 'Creando…' : 'Crear plan de proyecto' }}
            </button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
