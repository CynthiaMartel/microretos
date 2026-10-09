<!-- Ruta: /alumnado/listado (name: alumnado-listado). Listado del alumnado participante,
     alumno a alumno: en qué proyectos y encuentros ha participado, con qué grupo, en qué
     fase va y qué nota sacó al terminar. Es la única vista por ALUMNO — Mis grupos y la
     Biblioteca de diagnósticos son por grupo, así que el trabajo del workspace, la
     validación de fases y el diagnóstico no se repiten aquí: se enlazan.

     No existe entidad "alumno" en BD (solo miembros de grupo con nombre cifrado), así que
     un alumno se identifica por nombre + ciclo + curso + clase del encuentro: dos alumnos
     homónimos de la misma clase aparecerían juntos. -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getParticipacionesAlumnado } from '../services/alumnadoService.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import SelectorCurso from '../components/SelectorCurso.vue'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'
import { useCursoAcademicoStore, cursoDeEncuentro } from '../stores/cursoAcademico.js'
import { formatCurso } from '../utils/formatCurso.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

const router = useRouter()

const participaciones = ref([])
const cargando = ref(true)
const error    = ref('')

onMounted(async () => {
  try {
    const { data } = await getParticipacionesAlumnado()
    participaciones.value = data
  } catch {
    error.value = 'No se ha podido cargar el listado de alumnado.'
  } finally {
    cargando.value = false
  }
})

const ROLES = {
  portavoz:      { label: 'Portavoz',      cls: 'bg-blue-100 text-blue-700' },
  tiempos:       { label: 'Tiempos',       cls: 'bg-amber-100 text-amber-700' },
  documentacion: { label: 'Documentación', cls: 'bg-violet-100 text-violet-700' },
  foco:          { label: 'Foco',          cls: 'bg-emerald-100 text-emerald-700' },
}

const NIVELES_RA = [
  { key: 'superado',      label: 'Superado',      cls: 'bg-emerald-600 text-white' },
  { key: 'alcanzado',     label: 'Alcanzado',     cls: 'bg-emerald-100 text-emerald-700' },
  { key: 'en_proceso',    label: 'En proceso',    cls: 'bg-amber-100 text-amber-700' },
  { key: 'no_alcanzado',  label: 'No alcanzado',  cls: 'bg-red-100 text-red-700' },
]

// ── Helpers ─────────────────────────────────────────────────────────────────
const norm = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim()
const fecha = (iso) => iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
const iniciales = (nombre) => String(nombre ?? '').trim().split(/\s+/).slice(0, 2).map(p => p[0]?.toUpperCase() ?? '').join('') || '?'
const formatNota = (n) => (n === null || n === undefined) ? '' : Number(n).toLocaleString('es-ES', { maximumFractionDigits: 2 })

// Mismo criterio que Mis grupos: completado = las 5 fases del grupo completas
function estadoDe(p) {
  if (p.equipo.fases_completas === 5) return 'completado'
  if (p.equipo.fase_actual === 0 && p.equipo.fases_completas === 0) return 'sin_iniciar'
  return 'en_curso'
}
function estadoBadge(p) {
  const e = estadoDe(p)
  if (e === 'completado')  return { label: '✓ Completado', cls: 'bg-emerald-100 text-emerald-700' }
  if (e === 'sin_iniciar') return { label: 'Sin iniciar', cls: 'bg-gray-100 text-gray-500' }
  const fa = p.equipo.fase_actual
  return { label: `En curso · F${fa} ${FASES_PROYECTO[fa]?.label ?? ''}`, cls: 'bg-blue-100 text-blue-700' }
}
function estadoFase(p, n) {
  const f = p.equipo.fases[n]
  if (f?.validado_docente) return 'bg-emerald-500 text-white'
  if (f?.completada)       return 'bg-centros/20 text-centros'
  if (p.equipo.fase_actual === n) return 'bg-blue-100 text-blue-600 ring-1 ring-blue-300'
  return 'bg-gray-100 text-gray-400'
}
const progreso = (p) => progresoPonderado(p.equipo.fases)

// ── Filtros ─────────────────────────────────────────────────────────────────
const cursoStore     = useCursoAcademicoStore()
const cursosConDatos = computed(() => [...new Set(participaciones.value.map(p => cursoDeEncuentro(p.encuentro)))])
const delCurso       = computed(() => participaciones.value.filter(p => cursoStore.coincide(cursoDeEncuentro(p.encuentro))))

const busqueda      = ref('')
const filtroEstado  = ref('')   // '' | 'en_curso' | 'completado' | 'sin_iniciar'
const filtroProyecto = ref('')
const filtroClase   = ref('')
const fechaDesde    = ref('')
const fechaHasta    = ref('')
const orden         = ref('nombre') // 'nombre' | 'nota' | 'reciente'

const claseDe = (p) => [p.encuentro.ciclo_formativo, p.encuentro.curso && `${formatCurso(p.encuentro.curso)} curso`, p.encuentro.grupo && `Clase ${p.encuentro.grupo}`].filter(Boolean).join(' · ')

const proyectosDisponibles = computed(() =>
  [...new Map(delCurso.value.filter(p => p.proyecto).map(p => [p.proyecto.uuid, p.proyecto.titulo])).entries()]
    .sort((a, b) => a[1].localeCompare(b[1], 'es')))
const clasesDisponibles = computed(() =>
  [...new Set(delCurso.value.map(claseDe).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))

const hayFiltros = computed(() => !!(busqueda.value || filtroEstado.value || filtroProyecto.value || filtroClase.value || fechaDesde.value || fechaHasta.value))
function limpiarFiltros() {
  busqueda.value = ''; filtroEstado.value = ''; filtroProyecto.value = ''
  filtroClase.value = ''; fechaDesde.value = ''; fechaHasta.value = ''
}

// Filtros que actúan sobre cada participación (proyecto, clase, fechas del encuentro, estado);
// la búsqueda por nombre se aplica después, sobre el alumno agrupado.
const participacionesFiltradas = computed(() => delCurso.value.filter(p =>
  (!filtroProyecto.value || p.proyecto?.uuid === filtroProyecto.value) &&
  (!filtroClase.value    || claseDe(p) === filtroClase.value) &&
  (!fechaDesde.value     || (p.encuentro.fecha_fin || p.encuentro.fecha || '') >= fechaDesde.value) &&
  (!fechaHasta.value     || (p.encuentro.fecha || '') <= fechaHasta.value) &&
  (!filtroEstado.value   || estadoDe(p) === filtroEstado.value)
))

// ── Agrupación por alumno ───────────────────────────────────────────────────
const claveAlumno = (p) => [p.nombre, p.encuentro.ciclo_formativo, p.encuentro.curso, p.encuentro.grupo].map(norm).join('|')

function agrupar(lista) {
  const mapa = new Map()
  for (const p of lista) {
    const k = claveAlumno(p)
    if (!mapa.has(k)) mapa.set(k, { clave: k, nombre: p.nombre, alias: new Set(), clase: claseDe(p), participaciones: [] })
    const a = mapa.get(k)
    if (p.alias) a.alias.add(p.alias)
    a.participaciones.push(p)
  }
  return [...mapa.values()].map(a => {
    a.participaciones.sort((x, y) => (y.encuentro.fecha || '').localeCompare(x.encuentro.fecha || ''))
    const notas = a.participaciones.filter(p => estadoDe(p) === 'completado' && p.equipo.nota_final !== null).map(p => p.equipo.nota_final)
    return {
      ...a,
      alias: [...a.alias],
      reciente: a.participaciones[0],
      completados: a.participaciones.filter(p => estadoDe(p) === 'completado').length,
      notaMedia: notas.length ? notas.reduce((t, n) => t + n, 0) / notas.length : null,
    }
  })
}

const alumnos = computed(() => {
  const q = norm(busqueda.value)
  const lista = agrupar(participacionesFiltradas.value)
    .filter(a => !q || norm(a.nombre).includes(q) || a.alias.some(al => norm(al).includes(q)))
  const porNombre = (a, b) => a.nombre.localeCompare(b.nombre, 'es')
  if (orden.value === 'nota')     return lista.sort((a, b) => (b.notaMedia ?? -1) - (a.notaMedia ?? -1) || porNombre(a, b))
  if (orden.value === 'reciente') return lista.sort((a, b) => (b.reciente.encuentro.fecha || '').localeCompare(a.reciente.encuentro.fecha || '') || porNombre(a, b))
  return lista.sort(porNombre)
})

// Contadores sobre el recorte visible
const resumen = computed(() => {
  const ps = alumnos.value.flatMap(a => a.participaciones)
  const notas = alumnos.value.map(a => a.notaMedia).filter(n => n !== null)
  return {
    alumnos:     alumnos.value.length,
    enCurso:     ps.filter(p => estadoDe(p) === 'en_curso').length,
    completados: ps.filter(p => estadoDe(p) === 'completado').length,
    notaMedia:   notas.length ? notas.reduce((t, n) => t + n, 0) / notas.length : null,
  }
})

// Render incremental: un centro puede tener cientos de alumnos
const PASO = 40
const limite = ref(PASO)
watch([busqueda, filtroEstado, filtroProyecto, filtroClase, fechaDesde, fechaHasta, orden, () => cursoStore.curso], () => { limite.value = PASO })
const visibles = computed(() => alumnos.value.slice(0, limite.value))

const abiertos = ref(new Set())
function toggle(clave) {
  const s = new Set(abiertos.value)
  s.has(clave) ? s.delete(clave) : s.add(clave)
  abiertos.value = s
}

// Enlaces a lo que ya existe (no se duplica aquí)
const irAGrupo       = (p) => router.push({ name: 'mis-grupos-detalle', params: { id: p.encuentro.id } })
const irADiagnostico = (p) => router.push({ name: 'mis-grupos-detalle', params: { id: p.encuentro.id }, query: { equipo: p.equipo.id, ver: 'diagnostico' } })
const irAProyecto    = (p) => router.push({ name: 'startup-day-detalle', params: { uuid: p.proyecto.uuid } })
</script>

<template>
  <div class="min-h-screen bg-[#F3F6FA] font-sans text-azul-noche pt-16">
    <div class="mx-auto max-w-[1200px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Listado de" destacado="alumnado" color="text-alumnos"
                       subtitulo="Cada alumno con los proyectos y encuentros en los que ha participado, su grupo, en qué fase va y la nota final de los proyectos terminados.">
        <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
      </CabeceraSeccion>

      <!-- Contadores -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card p-4 text-center">
          <p class="font-heading text-2xl font-bold">{{ resumen.alumnos }}</p>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Alumnos</p>
        </div>
        <div class="card p-4 text-center">
          <p class="font-heading text-2xl font-bold text-blue-600">{{ resumen.enCurso }}</p>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Proyectos en curso</p>
        </div>
        <div class="card p-4 text-center">
          <p class="font-heading text-2xl font-bold text-emerald-600">{{ resumen.completados }}</p>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Proyectos completados</p>
        </div>
        <div class="card p-4 text-center">
          <p class="font-heading text-2xl font-bold text-[#0F7273]">{{ resumen.notaMedia !== null ? formatNota(resumen.notaMedia) : '—' }}</p>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Nota media</p>
        </div>
      </div>

      <!-- Búsqueda y filtros -->
      <article class="card space-y-3 p-4">
        <div class="relative">
          <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <label class="sr-only" for="al-buscar">Buscar alumno</label>
          <input id="al-buscar" v-model="busqueda" type="search" placeholder="Buscar alumno por nombre o alias…"
                 class="h-10 w-full rounded-lg border border-gray-200 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-alumnos/40" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="flex overflow-hidden rounded-lg border border-gray-200" role="group" aria-label="Estado del proyecto">
            <button v-for="e in [{ v: '', l: 'Todos' }, { v: 'en_curso', l: 'En curso' }, { v: 'completado', l: 'Completados' }, { v: 'sin_iniciar', l: 'Sin iniciar' }]" :key="e.v"
                    type="button" @click="filtroEstado = e.v"
                    :class="['border-l border-gray-200 px-3 py-2 text-xs font-bold first:border-l-0 transition-colors',
                             filtroEstado === e.v ? 'bg-azul-noche text-white' : 'bg-white text-gray-500 hover:text-azul-noche']">
              {{ e.l }}
            </button>
          </div>

          <label class="sr-only" for="al-proyecto">Proyecto</label>
          <select id="al-proyecto" v-model="filtroProyecto" :disabled="!proyectosDisponibles.length" class="campo max-w-[16rem]">
            <option value="">Todos los proyectos</option>
            <option v-for="[uuid, titulo] in proyectosDisponibles" :key="uuid" :value="uuid">{{ titulo }}</option>
          </select>

          <label class="sr-only" for="al-clase">Clase</label>
          <select id="al-clase" v-model="filtroClase" :disabled="!clasesDisponibles.length" class="campo max-w-[16rem]">
            <option value="">Todas las clases</option>
            <option v-for="c in clasesDisponibles" :key="c" :value="c">{{ c }}</option>
          </select>

          <label class="sr-only" for="al-orden">Ordenar</label>
          <select id="al-orden" v-model="orden" class="campo">
            <option value="nombre">Orden: nombre</option>
            <option value="nota">Orden: nota media</option>
            <option value="reciente">Orden: encuentro más reciente</option>
          </select>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500">
          <span>Encuentros entre</span>
          <label class="sr-only" for="al-desde">Desde</label>
          <input id="al-desde" v-model="fechaDesde" type="date" class="campo" />
          <span>y</span>
          <label class="sr-only" for="al-hasta">Hasta</label>
          <input id="al-hasta" v-model="fechaHasta" type="date" class="campo" />
          <button v-if="hayFiltros" type="button" @click="limpiarFiltros" class="ml-auto font-bold text-gray-400 hover:text-gray-600">
            Limpiar filtros ✕
          </button>
        </div>
      </article>

      <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

      <div v-if="cargando" class="space-y-2"><div v-for="n in 6" :key="n" class="h-16 animate-pulse rounded-xl bg-white" /></div>

      <div v-else-if="!error && !participaciones.length" class="card px-6 py-12 text-center text-sm text-gray-500">
        Todavía no hay alumnado en tus encuentros.
        <span class="mt-1 block">El alumnado aparece aquí al repartirlo en grupos al <button class="link" @click="router.push('/encuentros/crear')">crear un encuentro</button>.</span>
      </div>

      <p v-else-if="!error && !alumnos.length" class="card px-6 py-12 text-center text-sm text-gray-500">
        Ningún alumno coincide con la búsqueda o los filtros.
      </p>

      <ul v-else-if="!error" class="space-y-2">
        <li v-for="a in visibles" :key="a.clave" class="card overflow-hidden">
          <!-- Fila del alumno -->
          <button type="button" @click="toggle(a.clave)" :aria-expanded="abiertos.has(a.clave)"
                  class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-alumnos/15 text-sm font-bold text-alumnos-dark">
              {{ iniciales(a.nombre) }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate font-semibold">
                {{ a.nombre }}
                <span v-if="a.alias.length" class="font-normal text-gray-400">· {{ a.alias.join(', ') }}</span>
              </span>
              <span class="block truncate text-xs text-gray-500">{{ a.clase || 'Sin clase' }}</span>
              <span class="mt-1 flex flex-wrap items-center gap-1.5 sm:hidden">
                <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', estadoBadge(a.reciente).cls]">{{ estadoBadge(a.reciente).label }}</span>
              </span>
            </span>

            <span class="hidden min-w-0 max-w-[16rem] flex-col items-end gap-1 sm:flex">
              <span class="max-w-full truncate text-xs font-semibold text-gray-600">{{ a.reciente.proyecto?.titulo || 'Sin proyecto' }}</span>
              <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', estadoBadge(a.reciente).cls]">{{ estadoBadge(a.reciente).label }}</span>
            </span>

            <span class="hidden w-20 shrink-0 text-center md:block" :title="`${a.participaciones.length} proyecto(s), ${a.completados} completado(s)`">
              <span class="block font-heading text-lg font-bold">{{ a.completados }}/{{ a.participaciones.length }}</span>
              <span class="block text-[10px] uppercase tracking-wider text-gray-400">completados</span>
            </span>

            <span class="w-14 shrink-0 text-center">
              <span v-if="a.notaMedia !== null" class="block rounded-lg bg-administraciones/10 px-2 py-1 font-heading text-sm font-bold text-[#0F7273]"
                    :title="a.completados > 1 ? 'Nota media de sus proyectos completados' : 'Nota final'">{{ formatNota(a.notaMedia) }}</span>
              <span v-else class="block text-xs text-gray-300" title="Sin nota final todavía">—</span>
            </span>

            <svg :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform', abiertos.has(a.clave) ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <!-- Participaciones: una por encuentro/proyecto -->
          <div v-if="abiertos.has(a.clave)" class="space-y-3 border-t border-gray-100 bg-gray-50/60 p-4">
            <section v-for="p in a.participaciones" :key="p.id" class="rounded-xl bg-white p-4 ring-1 ring-gray-200/70">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-semibold leading-snug">{{ p.proyecto?.titulo || 'Sin proyecto asociado' }}</p>
                  <p class="text-xs text-gray-500">
                    <span v-if="p.proyecto?.familia">{{ p.proyecto.familia }} · </span>
                    Encuentro {{ fecha(p.encuentro.fecha) || 'sin fecha' }}<template v-if="p.encuentro.fecha_fin && p.encuentro.fecha_fin !== p.encuentro.fecha"> → {{ fecha(p.encuentro.fecha_fin) }}</template>
                  </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                  <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', estadoBadge(p).cls]">{{ estadoBadge(p).label }}</span>
                  <span v-if="estadoDe(p) === 'completado'"
                        :class="['rounded-lg px-2.5 py-1 font-heading text-base font-bold', p.equipo.nota_final !== null ? 'bg-administraciones/10 text-[#0F7273]' : 'bg-amber-50 text-xs text-amber-700']">
                    {{ p.equipo.nota_final !== null ? formatNota(p.equipo.nota_final) : 'Sin nota' }}
                  </span>
                </div>
              </div>

              <!-- Grupo y compañeros -->
              <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px]">
                <span class="rounded-full bg-gray-900 px-2 py-0.5 font-semibold text-white">{{ nombreGrupo(p.equipo) }}</span>
                <span v-if="p.rol && ROLES[p.rol]" :class="['rounded-full px-2 py-0.5 font-bold', ROLES[p.rol].cls]">{{ ROLES[p.rol].label }}</span>
                <span v-if="p.equipo.companeros.length" class="text-gray-400">con</span>
                <span v-for="c in p.equipo.companeros" :key="c.id" class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-600">{{ c.nombre }}</span>
              </div>

              <!-- Fases del workspace (estado + nota de fase si la hay) -->
              <div class="mt-3">
                <div class="mb-1 flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                  <span>Fases del workspace</span><span>{{ progreso(p) }}%</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <span v-for="f in FASES_PROYECTO" :key="f.num"
                        :title="`${f.label}${p.equipo.fases[f.num]?.fecha_completada ? ' · completada el ' + new Date(p.equipo.fases[f.num].fecha_completada).toLocaleDateString('es-ES') : ''}`"
                        :class="['inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[10px] font-bold', estadoFase(p, f.num)]">
                    <span aria-hidden="true">{{ f.icono }}</span>F{{ f.num }}
                    <span class="hidden font-semibold lg:inline">{{ f.label }}</span>
                    <span v-if="p.equipo.fases[f.num]?.nota_docente !== null && p.equipo.fases[f.num]?.nota_docente !== undefined && f.num !== 4"
                          class="rounded bg-white/60 px-1">{{ formatNota(p.equipo.fases[f.num].nota_docente) }}</span>
                  </span>
                </div>
              </div>

              <!-- Evaluación final, reflexión y tareas -->
              <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-3">
                <div class="rounded-lg bg-gray-50 px-3 py-2">
                  <dt class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Evaluación de RA</dt>
                  <dd class="mt-1 flex flex-wrap gap-1">
                    <template v-if="Object.keys(p.equipo.niveles_ra || {}).length">
                      <span v-for="n in NIVELES_RA.filter(n => p.equipo.niveles_ra[n.key])" :key="n.key"
                            :class="['rounded-full px-1.5 py-0.5 text-[10px] font-bold', n.cls]">{{ p.equipo.niveles_ra[n.key] }} {{ n.label.toLowerCase() }}</span>
                    </template>
                    <span v-else class="text-gray-400">Pendiente</span>
                  </dd>
                </div>
                <div class="rounded-lg bg-gray-50 px-3 py-2">
                  <dt class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Reflexión individual</dt>
                  <dd class="mt-1 font-semibold" :class="p.reflexion_individual ? 'text-emerald-600' : 'text-gray-400'">
                    {{ p.reflexion_individual ? '✓ Entregada' : 'No entregada' }}
                  </dd>
                </div>
                <div class="rounded-lg bg-gray-50 px-3 py-2">
                  <dt class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Tareas a su cargo</dt>
                  <dd class="mt-1 font-semibold">
                    <template v-if="p.tareas.asignadas">{{ p.tareas.realizadas }}/{{ p.tareas.asignadas }} realizadas</template>
                    <span v-else class="text-gray-400">Ninguna asignada</span>
                  </dd>
                </div>
              </dl>

              <p v-if="p.equipo.observaciones_docente" class="mt-2 rounded-lg bg-administraciones/5 px-3 py-2 text-xs text-gray-600">
                <span class="font-semibold text-[#0F7273]">Observaciones del docente:</span> {{ p.equipo.observaciones_docente }}
              </p>

              <div v-if="p.fortalezas.length || p.puntos_mejora.length" class="mt-2 flex flex-wrap gap-1 text-[10px]">
                <span v-for="f in p.fortalezas" :key="'f' + f" class="rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">+ {{ f }}</span>
                <span v-for="m in p.puntos_mejora" :key="'m' + m" class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700">↗ {{ m }}</span>
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="accion" @click="irAGrupo(p)">Ver trabajo del grupo →</button>
                <button v-if="p.equipo.tiene_diagnostico" type="button" class="accion" @click="irADiagnostico(p)">Ver diagnóstico</button>
                <button v-if="p.proyecto" type="button" class="accion" @click="irAProyecto(p)">Ficha del proyecto</button>
              </div>
            </section>
          </div>
        </li>
      </ul>

      <div v-if="!cargando && alumnos.length > limite" class="text-center">
        <button type="button" class="accion" @click="limite += PASO">Mostrar más ({{ alumnos.length - limite }} restantes)</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.card   { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
.link   { @apply font-semibold text-centros hover:underline; }
.campo  { @apply h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-alumnos/40 disabled:opacity-50; }
.accion { @apply inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-bold text-azul-noche transition-colors hover:border-alumnos hover:text-alumnos-dark; }
</style>
