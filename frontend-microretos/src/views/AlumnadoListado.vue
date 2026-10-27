<!-- Ruta: /alumnado/listado (name: alumnado-listado). Listado del alumnado participante,
     alumno a alumno: en qué proyectos y encuentros ha participado, con qué grupo, en qué
     fase va y qué nota sacó al terminar. Es la única vista por ALUMNO — Mis grupos y la
     Biblioteca de diagnósticos son por grupo, así que el trabajo del workspace, la
     validación de fases y el diagnóstico no se repiten aquí: se enlazan.

     Mismo esquema que las demás bibliotecas (docs/GUIA_DISENO.md §7): barra de filtros común,
     «Filtros rápidos» a la izquierda, título de resultados con niveles y paginación.

     Protección de datos: la plataforma no guarda nombres reales, solo el alias del alumno/a
     ("NombreDePila Animal Color", único en su encuentro). No existe entidad "alumno" en BD (solo miembros
     de grupo), así que un alumno se identifica por alias + centro + ciclo + curso + clase:
     si el docente reutiliza el mismo alias en otro encuentro de esa clase, se agrupan. -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getParticipacionesAlumnado } from '../services/alumnadoService.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import SelectorCurso from '../components/SelectorCurso.vue'
import FiltrosPanel from '../components/FiltrosPanel.vue'
import FiltroSegmentado from '../components/FiltroSegmentado.vue'
import FiltroSelect from '../components/FiltroSelect.vue'
import TituloResultados from '../components/TituloResultados.vue'
import FamiliaModulos from '../components/FamiliaModulos.vue'
import RolGrupo from '../components/RolGrupo.vue'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'
import { useCursoAcademicoStore, cursoDeEncuentro } from '../stores/cursoAcademico.js'
import { usePaginacion } from '../composables/usePaginacion.js'
import { formatCurso } from '../utils/formatCurso.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

const router = useRouter()

const participaciones = ref([])
const cargando = ref(true)
const error    = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getParticipacionesAlumnado()
    participaciones.value = data
  } catch {
    error.value = 'No se ha podido cargar el listado de alumnado.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)


const NIVELES_RA = [
  { key: 'superado',      label: 'Superado',      cls: 'bg-empresas text-white' },
  { key: 'alcanzado',     label: 'Alcanzado',     cls: 'bg-empresas/10 text-empresas-dark' },
  { key: 'en_proceso',    label: 'En proceso',    cls: 'bg-amber-50 text-amber-800' },
  { key: 'no_alcanzado',  label: 'No alcanzado',  cls: 'bg-red-50 text-red-700' },
]

// ── Helpers ─────────────────────────────────────────────────────────────────
const norm = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim()
const fecha = (iso) => iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
const iniciales = (alias) => String(alias ?? '').trim().split(/\s+/).slice(0, 2).map(p => p[0]?.toUpperCase() ?? '').join('') || '?'
const formatNota = (n) => (n === null || n === undefined) ? '' : Number(n).toLocaleString('es-ES', { maximumFractionDigits: 2 })

// Mismo criterio que Mis grupos: completado = las 5 fases del grupo completas
function estadoDe(p) {
  if (p.equipo.fases_completas === 5) return 'completado'
  if (p.equipo.fase_actual === 0 && p.equipo.fases_completas === 0) return 'sin_iniciar'
  return 'en_curso'
}
function estadoBadge(p) {
  const e = estadoDe(p)
  if (e === 'completado')  return { label: '✓ Completado', cls: 'bg-empresas/10 text-empresas-dark' }
  if (e === 'sin_iniciar') return { label: 'Sin iniciar', cls: 'bg-amber-50 text-amber-800' }
  const fa = p.equipo.fase_actual
  return { label: `En curso · F${fa} ${FASES_PROYECTO[fa]?.label ?? ''}`, cls: 'bg-centros/10 text-centros' }
}
function estadoFase(p, n) {
  const f = p.equipo.fases[n]
  if (f?.validado_docente) return 'bg-empresas text-white'
  if (f?.completada)       return 'bg-centros/15 text-centros'
  if (p.equipo.fase_actual === n) return 'bg-white text-centros ring-1 ring-centros/40'
  return 'bg-gray-100 text-texto-secundario'
}
const progreso = (p) => progresoPonderado(p.equipo.fases)

// ── Filtros ─────────────────────────────────────────────────────────────────
const cursoStore     = useCursoAcademicoStore()
const cursosConDatos = computed(() => [...new Set(participaciones.value.map(p => cursoDeEncuentro(p.encuentro)))])
const delCurso       = computed(() => participaciones.value.filter(p => cursoStore.coincide(cursoDeEncuentro(p.encuentro))))

const SIN_FAMILIA = '__sin_familia__'
const familiaDe = (p) => p.proyecto?.familia || SIN_FAMILIA
const etiquetaFamilia = (f) => (f === SIN_FAMILIA ? 'Sin familia asignada' : f)
const modulosDe = (p) => p.proyecto?.modulos ?? []

const busqueda        = ref('')
const filtroFamilias  = ref([])
const filtroCiclo     = ref('')
const filtroModulo    = ref('')
const filtroCurso     = ref('')   // nivel (1º, 2º…) del encuentro
const filtroClase     = ref('')   // letra de la clase (Encuentro.grupo)
const filtroEstado    = ref('')   // '' | 'en_curso' | 'sin_iniciar' | 'completado'
const filtroSeguimiento = ref([]) // 'con_nota' | 'sin_reflexion' | 'con_diagnostico'
const filtroProyecto  = ref('')
const fechaDesde      = ref('')
const fechaHasta      = ref('')
const orden           = ref('nombre') // 'nombre' | 'nota' | 'reciente'
const familiasExpandidas = ref(false)
// «Filtros rápidos»: visible de entrada en escritorio y plegado en móvil; «Más filtros» lo alterna.
const panelFiltrosAbierto = ref(typeof window === 'undefined' || window.innerWidth >= 1024)

// Dependencias: el ciclo depende de la familia; el módulo y el nivel, del ciclo.
watch(filtroFamilias, () => { filtroCiclo.value = ''; filtroModulo.value = ''; filtroCurso.value = '' })
watch(filtroCiclo, () => { filtroModulo.value = ''; filtroCurso.value = '' })

const claseDe = (p) => [p.encuentro.ciclo_formativo, p.encuentro.curso && `${formatCurso(p.encuentro.curso)} curso`, p.encuentro.grupo && `Clase ${p.encuentro.grupo}`].filter(Boolean).join(' · ')
const coincideFamilias = (p) => !filtroFamilias.value.length || filtroFamilias.value.includes(familiaDe(p))

const SEGUIMIENTO = {
  con_nota:        { label: 'Con nota final',            prueba: p => estadoDe(p) === 'completado' && p.equipo.nota_final !== null },
  con_diagnostico: { label: 'Con diagnóstico del grupo', prueba: p => !!p.equipo.tiene_diagnostico },
  sin_reflexion:   { label: 'Sin reflexión individual',  prueba: p => !p.reflexion_individual },
}

// Filtros por participación; cada contador del lateral se calcula sin su propio grupo.
function aplicar(lista, { sinEstado = false, sinSeguimiento = false } = {}) {
  return lista.filter(p =>
    coincideFamilias(p) &&
    (!filtroCiclo.value    || p.encuentro.ciclo_formativo === filtroCiclo.value) &&
    (!filtroModulo.value   || modulosDe(p).includes(filtroModulo.value)) &&
    (!filtroCurso.value    || String(p.encuentro.curso) === String(filtroCurso.value)) &&
    (!filtroClase.value    || p.encuentro.grupo === filtroClase.value) &&
    (!filtroProyecto.value || p.proyecto?.uuid === filtroProyecto.value) &&
    (!fechaDesde.value     || (p.encuentro.fecha_fin || p.encuentro.fecha || '') >= fechaDesde.value) &&
    (!fechaHasta.value     || (p.encuentro.fecha || '') <= fechaHasta.value) &&
    (sinEstado || !filtroEstado.value || estadoDe(p) === filtroEstado.value) &&
    (sinSeguimiento || filtroSeguimiento.value.every(k => SEGUIMIENTO[k].prueba(p)))
  )
}
const participacionesFiltradas = computed(() => aplicar(delCurso.value))

// Opciones de la barra (dependientes: familia → ciclo → módulo/nivel)
const baseFamilia = computed(() => delCurso.value.filter(coincideFamilias))
const ciclosDisponibles = computed(() =>
  [...new Set(baseFamilia.value.map(p => p.encuentro.ciclo_formativo).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const baseCiclo = computed(() => filtroCiclo.value ? baseFamilia.value.filter(p => p.encuentro.ciclo_formativo === filtroCiclo.value) : baseFamilia.value)
const modulosDisponibles = computed(() =>
  [...new Set(baseCiclo.value.flatMap(modulosDe).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const cursosDisponibles = computed(() => [...new Set(baseCiclo.value.map(p => p.encuentro.curso).filter(Boolean))].sort())
const opcionesNivel = computed(() => [{ value: '', label: 'Todos' }, ...cursosDisponibles.value.map(c => ({ value: c, label: formatCurso(c) }))])
const clasesDisponibles = computed(() => [...new Set(baseCiclo.value.map(p => p.encuentro.grupo).filter(Boolean))].sort())
const opcionesClase = computed(() => [{ value: '', label: 'Todas' }, ...clasesDisponibles.value.map(c => ({ value: c, label: c }))])
const proyectosDisponibles = computed(() =>
  [...new Map(baseCiclo.value.filter(p => p.proyecto).map(p => [p.proyecto.uuid, p.proyecto.titulo])).entries()]
    .sort((a, b) => a[1].localeCompare(b[1], 'es')))

// ── Agrupación por alumno ───────────────────────────────────────────────────
const claveAlumno = (p) => [p.alias, p.encuentro.centro_educativo, p.encuentro.ciclo_formativo, p.encuentro.curso, p.encuentro.grupo].map(norm).join('|')

function agrupar(lista) {
  const mapa = new Map()
  for (const p of lista) {
    const k = claveAlumno(p)
    if (!mapa.has(k)) mapa.set(k, { clave: k, alias: p.alias, clase: claseDe(p), participaciones: [] })
    mapa.get(k).participaciones.push(p)
  }
  return [...mapa.values()].map(a => {
    a.participaciones.sort((x, y) => (y.encuentro.fecha || '').localeCompare(x.encuentro.fecha || ''))
    const notas = a.participaciones.filter(p => estadoDe(p) === 'completado' && p.equipo.nota_final !== null).map(p => p.equipo.nota_final)
    return {
      ...a,
      reciente: a.participaciones[0],
      completados: a.participaciones.filter(p => estadoDe(p) === 'completado').length,
      notaMedia: notas.length ? notas.reduce((t, n) => t + n, 0) / notas.length : null,
    }
  })
}
const coincideAlias = (a) => { const q = norm(busqueda.value); return !q || norm(a.alias).includes(q) }

const alumnos = computed(() => {
  const lista = agrupar(participacionesFiltradas.value).filter(coincideAlias)
  const porNombre = (a, b) => String(a.alias ?? '').localeCompare(String(b.alias ?? ''), 'es')
  if (orden.value === 'nota')     return lista.sort((a, b) => (b.notaMedia ?? -1) - (a.notaMedia ?? -1) || porNombre(a, b))
  if (orden.value === 'reciente') return lista.sort((a, b) => (b.reciente.encuentro.fecha || '').localeCompare(a.reciente.encuentro.fecha || '') || porNombre(a, b))
  return lista.sort(porNombre)
})

// Lateral: contadores de alumnos (no de participaciones)
const ESTADOS = [
  { value: '',            label: 'Todos',       punto: 'bg-gray-300' },
  { value: 'en_curso',    label: 'En curso',    punto: 'bg-centros' },
  { value: 'sin_iniciar', label: 'Sin iniciar', punto: 'bg-amber-500' },
  { value: 'completado',  label: 'Completados', punto: 'bg-empresas' },
]
const cuentaAlumnos = (lista) => agrupar(lista).filter(coincideAlias).length
const conteoEstados = computed(() => {
  const base = aplicar(delCurso.value, { sinEstado: true })
  return Object.fromEntries(ESTADOS.map(e => [e.value, cuentaAlumnos(e.value ? base.filter(p => estadoDe(p) === e.value) : base)]))
})
const conteoSeguimiento = computed(() => {
  const base = aplicar(delCurso.value, { sinSeguimiento: true })
  return Object.fromEntries(Object.entries(SEGUIMIENTO).map(([k, s]) => [k, cuentaAlumnos(base.filter(s.prueba))]))
})
const familiasPanel = computed(() => {
  const mapa = new Map()
  agrupar(delCurso.value).forEach(a => {
    new Set(a.participaciones.map(familiaDe)).forEach(f => mapa.set(f, (mapa.get(f) || 0) + 1))
  })
  return [...mapa.entries()].map(([nombre, n]) => ({ nombre, n, label: etiquetaFamilia(nombre) }))
    .sort((a, b) => (a.nombre === SIN_FAMILIA) - (b.nombre === SIN_FAMILIA) || b.n - a.n || a.label.localeCompare(b.label, 'es'))
})
const familiasPanelVisibles = computed(() => {
  const todas = familiasPanel.value
  if (familiasExpandidas.value || todas.length <= 6) return todas
  return [...todas.slice(0, 6), ...todas.slice(6).filter(f => filtroFamilias.value.includes(f.nombre))]
})

// (En la plantilla los refs llegan desenvueltos: por eso una función por filtro.)
const alternar = (lista, valor) => lista.includes(valor) ? lista.filter(v => v !== valor) : [...lista, valor]
function alternarFamilia(nombre) { filtroFamilias.value = alternar(filtroFamilias.value, nombre) }
function alternarSeguimiento(clave) { filtroSeguimiento.value = alternar(filtroSeguimiento.value, clave) }

// Niveles elegidos para el título de los resultados (TituloResultados): una sola familia →
// ciclo → módulo. Quitar un nivel quita también los que dependen de él (watches de arriba).
const nivelesTitulo = computed(() => {
  const niveles = []
  if (filtroFamilias.value.length === 1) {
    niveles.push({ etiqueta: 'Familia profesional', valor: etiquetaFamilia(filtroFamilias.value[0]),
      subir: () => { filtroCiclo.value = ''; filtroModulo.value = '' }, quitar: () => { filtroFamilias.value = [] } })
  }
  if (filtroCiclo.value) niveles.push({ etiqueta: 'Ciclo formativo', valor: filtroCiclo.value, subir: () => { filtroModulo.value = '' }, quitar: () => { filtroCiclo.value = '' } })
  if (filtroModulo.value) niveles.push({ etiqueta: 'Módulo', valor: filtroModulo.value, subir: () => {}, quitar: () => { filtroModulo.value = '' } })
  return niveles
})
const familiaEnTitulo = computed(() => nivelesTitulo.value[0]?.etiqueta === 'Familia profesional')

const hayFechas = computed(() => !!(fechaDesde.value || fechaHasta.value))
const filtrosLateral = computed(() => filtroFamilias.value.length + filtroSeguimiento.value.length
  + (filtroEstado.value ? 1 : 0) + (filtroProyecto.value ? 1 : 0) + (hayFechas.value ? 1 : 0))
const hayFiltros = computed(() => !!(busqueda.value.trim() || filtroCiclo.value || filtroModulo.value || filtroCurso.value
  || filtroClase.value || filtrosLateral.value))
function limpiarFiltros() {
  busqueda.value = ''; filtroFamilias.value = []; filtroCiclo.value = ''; filtroModulo.value = ''
  filtroCurso.value = ''; filtroClase.value = ''; filtroEstado.value = ''; filtroSeguimiento.value = []
  filtroProyecto.value = ''; fechaDesde.value = ''; fechaHasta.value = ''
}

// ── Paginación (20 alumnos por página: un centro puede tener cientos) ─────────
const POR_PAGINA = 20
const { pagina, totalPaginas, itemsPagina: visibles } = usePaginacion(alumnos, POR_PAGINA)
watch([alumnos, orden], () => { pagina.value = 0 })
const paginasVisibles = computed(() => {
  const n = totalPaginas.value, a = pagina.value
  const ini = Math.max(0, Math.min(a - 2, n - 5)), fin = Math.min(n, ini + 5)
  return Array.from({ length: fin - ini }, (_, i) => ini + i)
})
function irAPagina(i) {
  pagina.value = i
  document.getElementById('resultados-alumnado')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

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
  <!-- Explorador de alumnado (mismo esquema que Retos, Encuentros, Proyectos y Mis grupos) -->
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Listado de" destacado="alumnado" color="text-alumnos" volver="/seccion/alumnado"
                       subtitulo="Cada alumno con los proyectos y encuentros en los que ha participado, su grupo, en qué fase va y la nota final de los proyectos terminados." />

      <!-- Filtros principales (FiltrosPanel común) -->
      <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar alumno por alias…">
        <template #curso>
          <div class="flex flex-wrap items-center gap-2">
            <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
            <!-- «Más filtros» en la fila del buscador (en la de filtros, con 4 o más, bajaba solo a otra fila) -->
            <button type="button" @click="panelFiltrosAbierto = !panelFiltrosAbierto" :aria-expanded="panelFiltrosAbierto"
                    aria-controls="filtros-rapidos" :title="panelFiltrosAbierto ? 'Ocultar los filtros rápidos' : 'Mostrar los filtros rápidos'"
                    aria-label="Más filtros"
                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-full border px-5 text-sm font-semibold transition-colors"
                    :class="panelFiltrosAbierto ? 'border-rol-fuerte/30 bg-rol/10 text-rol-fuerte' : 'border-rol-fuerte/30 bg-white text-rol-fuerte hover:bg-rol-fuerte/5'">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    <span>Más filtros</span>
                    <span v-if="!panelFiltrosAbierto && filtrosLateral"
                    class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rol-fuerte px-1 text-[11px] font-bold text-white">{{ filtrosLateral }}</span>
                    </button>
          </div>
        </template>

        <FiltroSelect label="Ciclo formativo" v-model="filtroCiclo" :opciones="ciclosDisponibles" todos="Todos los ciclos"
          title="Filtra por el ciclo formativo del encuentro." />
        <FiltroSelect v-if="modulosDisponibles.length" label="Módulo" v-model="filtroModulo" :opciones="modulosDisponibles" todos="Todos los módulos"
          title="Filtra por módulo profesional del proyecto. Los proyectos de varios módulos aparecen en cada uno." />
        <FiltroSegmentado v-if="cursosDisponibles.length" label="Nivel" v-model="filtroCurso" :opciones="opcionesNivel" />
        <FiltroSegmentado v-if="clasesDisponibles.length" label="Clase" v-model="filtroClase" :opciones="opcionesClase" />
      </FiltrosPanel>

      <!-- Error de carga -->
      <div v-if="error && !cargando" class="flex flex-col items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center" role="alert">
        <p class="min-w-0 flex-1 text-sm font-medium text-red-700">{{ error }}</p>
        <button type="button" @click="cargar" class="inline-flex h-10 items-center rounded-full bg-white px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Reintentar</button>
      </div>

      <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        <!-- ═══ Filtros rápidos (panel lateral; en < lg se abre con «Más filtros») ═══ -->
        <aside id="filtros-rapidos" aria-label="Filtros rápidos"
               class="w-full shrink-0 rounded-2xl border border-borde bg-white p-4 shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:w-64 lg:overflow-y-auto xl:w-72"
               :class="panelFiltrosAbierto ? 'block' : 'hidden'">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-azul-noche">Filtros rápidos</h2>
            <button v-if="hayFiltros" type="button" @click="limpiarFiltros"
                    class="inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">Limpiar</button>
          </div>

          <!-- Estado del proyecto del alumno (cuenta alumnos) -->
          <div class="border-t border-borde pt-3" role="radiogroup" aria-labelledby="titulo-filtro-estado">
            <p id="titulo-filtro-estado" class="mb-1.5 text-sm font-semibold text-azul-noche">Estado del proyecto</p>
            <label v-for="e in ESTADOS" :key="e.value || 'todos'"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="radio" name="al-filtro-estado" :value="e.value" v-model="filtroEstado" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="e.punto" aria-hidden="true" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ e.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoEstados[e.value] }}</span>
            </label>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-seguimiento">
            <p id="titulo-filtro-seguimiento" class="mb-1.5 text-sm font-semibold text-azul-noche">Seguimiento</p>
            <label v-for="(s, k) in SEGUIMIENTO" :key="k"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroSeguimiento.includes(k)" @change="alternarSeguimiento(k)" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ s.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoSeguimiento[k] }}</span>
            </label>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-familia">
            <p id="titulo-filtro-familia" class="mb-1.5 text-sm font-semibold text-azul-noche">Familia profesional</p>
            <label v-for="f in familiasPanelVisibles" :key="f.nombre"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroFamilias.includes(f.nombre)" @change="alternarFamilia(f.nombre)" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche" :title="f.label">{{ f.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ f.n }}</span>
            </label>
            <p v-if="!familiasPanel.length && !cargando" class="px-1 py-1 text-sm text-texto-secundario">Sin alumnado en este curso.</p>
            <button v-if="familiasPanel.length > 6" type="button" @click="familiasExpandidas = !familiasExpandidas"
                    class="mt-1 inline-flex h-9 items-center rounded-full px-1 text-sm font-semibold text-rol-fuerte hover:underline">
              {{ familiasExpandidas ? 'Ver menos' : `Ver todas (${familiasPanel.length})` }}
            </button>
          </div>

          <details v-if="proyectosDisponibles.length" class="group mt-3 border-t border-borde pt-3" :open="!!filtroProyecto">
            <summary class="flex cursor-pointer list-none items-center justify-between py-1 text-sm font-semibold text-azul-noche">
              Proyecto
              <svg class="h-4 w-4 text-texto-secundario transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <label for="al-filtro-proyecto" class="sr-only">Proyecto</label>
            <select id="al-filtro-proyecto" v-model="filtroProyecto" class="mt-2 h-10 w-full rounded-xl px-3 text-sm">
              <option value="">Todos los proyectos</option>
              <option v-for="[uuid, titulo] in proyectosDisponibles" :key="uuid" :value="uuid">{{ titulo }}</option>
            </select>
          </details>

          <details class="group mt-3 border-t border-borde pt-3" :open="hayFechas">
            <summary class="flex cursor-pointer list-none items-center justify-between py-1 text-sm font-semibold text-azul-noche">
              Fechas del encuentro
              <svg class="h-4 w-4 text-texto-secundario transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <div class="mt-2 grid gap-2">
              <label class="text-xs font-semibold text-texto-secundario">Desde
                <input v-model="fechaDesde" type="date" class="mt-1 h-10 w-full rounded-xl px-3 text-sm text-azul-noche" />
              </label>
              <label class="text-xs font-semibold text-texto-secundario">Hasta
                <input v-model="fechaHasta" type="date" class="mt-1 h-10 w-full rounded-xl px-3 text-sm text-azul-noche" />
              </label>
            </div>
          </details>
        </aside>

        <!-- ═══ Resultados ═══ -->
        <section id="resultados-alumnado" class="min-w-0 flex-1 scroll-mt-24 space-y-3" aria-label="Alumnado">
          <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <TituloResultados :niveles="nivelesTitulo" :cargando="cargando">
              <template v-if="cargando">Cargando alumnado…</template>
              <template v-else>{{ alumnos.length }} {{ alumnos.length === 1 ? 'alumno' : 'alumnos' }}</template>
            </TituloResultados>
            <!-- Chips de filtros activos del lateral -->
            <div v-if="filtroEstado || filtroSeguimiento.length || filtroProyecto || hayFechas || (filtroFamilias.length && !familiaEnTitulo)"
                 class="flex w-full flex-wrap gap-1.5 sm:order-last">
              <button v-if="filtroEstado" type="button" @click="filtroEstado = ''" class="chip-filtro"
                      :aria-label="`Quitar filtro ${ESTADOS.find(e => e.value === filtroEstado)?.label}`">
                <span class="h-2 w-2 rounded-full" :class="ESTADOS.find(e => e.value === filtroEstado)?.punto" aria-hidden="true" />
                {{ ESTADOS.find(e => e.value === filtroEstado)?.label }}<span aria-hidden="true">✕</span>
              </button>
              <button v-for="k in filtroSeguimiento" :key="'s-' + k" type="button" @click="alternarSeguimiento(k)" class="chip-filtro"
                      :aria-label="`Quitar filtro ${SEGUIMIENTO[k].label}`">
                {{ SEGUIMIENTO[k].label }}<span aria-hidden="true">✕</span>
              </button>
              <button v-if="filtroProyecto" type="button" @click="filtroProyecto = ''" class="chip-filtro max-w-full" aria-label="Quitar filtro de proyecto">
                <span class="truncate">{{ proyectosDisponibles.find(([u]) => u === filtroProyecto)?.[1] || 'Proyecto' }}</span><span aria-hidden="true">✕</span>
              </button>
              <button v-if="hayFechas" type="button" @click="fechaDesde = ''; fechaHasta = ''" class="chip-filtro" aria-label="Quitar filtro de fechas">
                {{ fechaDesde ? fecha(fechaDesde) : '…' }} – {{ fechaHasta ? fecha(fechaHasta) : '…' }}<span aria-hidden="true">✕</span>
              </button>
              <button v-for="f in (familiaEnTitulo ? [] : filtroFamilias)" :key="'f-' + f" type="button" @click="alternarFamilia(f)"
                      class="inline-flex h-9 max-w-full items-center gap-1.5 rounded-full bg-rol/10 px-3 text-xs font-semibold text-rol-fuerte hover:bg-rol/15"
                      :aria-label="`Quitar filtro ${etiquetaFamilia(f)}`">
                <span class="truncate">{{ etiquetaFamilia(f) }}</span><span aria-hidden="true">✕</span>
              </button>
            </div>
            <label class="flex items-center gap-2 text-sm text-texto-secundario">
              <span class="hidden sm:inline">Ordenar por</span>
              <select v-model="orden" class="h-9 rounded-xl px-3 text-sm text-azul-noche" aria-label="Ordenar alumnado">
                <option value="nombre">Alias (A-Z)</option>
                <option value="nota">Nota media</option>
                <option value="reciente">Más reciente</option>
              </select>
            </label>
          </div>

          <!-- Carga -->
          <div v-if="cargando" class="space-y-2" aria-busy="true">
            <div v-for="n in 6" :key="n" class="h-16 animate-pulse rounded-2xl border border-borde bg-white" />
            <span class="sr-only">Cargando alumnado…</span>
          </div>

          <!-- Vacíos -->
          <div v-else-if="!error && !participaciones.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <h3 class="text-lg font-bold text-azul-noche">Todavía no hay alumnado en tus encuentros</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">El alumnado aparece aquí al repartirlo en grupos al crear un encuentro.</p>
            <button type="button" class="accion-secundaria mt-4" @click="router.push('/encuentros/crear')">Crear un encuentro</button>
          </div>
          <div v-else-if="!error && !alumnos.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <h3 class="text-lg font-bold text-azul-noche">Ningún alumno coincide con estos filtros</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">Prueba a quitar algún filtro.</p>
            <button v-if="hayFiltros" type="button" @click="limpiarFiltros" class="accion-secundaria mt-4">Limpiar filtros</button>
          </div>

          <ul v-else-if="!error" class="space-y-2">
            <li v-for="a in visibles" :key="a.clave" class="overflow-hidden rounded-2xl border border-borde bg-white shadow-sm">
              <!-- Fila del alumno -->
              <button type="button" @click="toggle(a.clave)" :aria-expanded="abiertos.has(a.clave)"
                      class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-alumnos/15 text-sm font-bold text-alumnos-dark" aria-hidden="true">
                  {{ iniciales(a.alias) }}
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-semibold text-azul-noche">{{ a.alias }}</span>
                  <span class="block truncate text-sm text-texto-secundario">{{ a.clase || 'Sin clase' }}</span>
                  <span class="mt-1 flex flex-wrap items-center gap-1.5 sm:hidden">
                    <span :class="['max-w-full truncate rounded-full px-2 py-0.5 text-xs font-semibold', estadoBadge(a.reciente).cls]">{{ estadoBadge(a.reciente).label }}</span>
                  </span>
                </span>

                <span class="hidden min-w-0 max-w-[16rem] flex-col items-end gap-1 sm:flex">
                  <span class="max-w-full truncate text-sm font-semibold text-azul-noche">{{ a.reciente.proyecto?.titulo || 'Sin proyecto' }}</span>
                  <span :class="['max-w-full truncate rounded-full px-2 py-0.5 text-xs font-semibold', estadoBadge(a.reciente).cls]">{{ estadoBadge(a.reciente).label }}</span>
                </span>

                <span class="hidden w-20 shrink-0 text-center md:block" :title="`${a.participaciones.length} proyecto(s), ${a.completados} completado(s)`">
                  <span class="block font-heading text-lg font-bold text-azul-noche">{{ a.completados }}/{{ a.participaciones.length }}</span>
                  <span class="block text-xs text-texto-secundario">completados</span>
                </span>

                <span class="w-14 shrink-0 text-center">
                  <span v-if="a.notaMedia !== null" class="block rounded-full bg-administraciones/10 px-2 py-1 font-heading text-sm font-bold text-administraciones-dark"
                        :title="a.completados > 1 ? 'Nota media de sus proyectos completados' : 'Nota final'">{{ formatNota(a.notaMedia) }}</span>
                  <span v-else class="block text-sm text-texto-secundario" title="Sin nota final todavía">—</span>
                </span>

                <svg :class="['h-4 w-4 shrink-0 text-texto-secundario transition-transform', abiertos.has(a.clave) ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>

              <!-- Participaciones: una por encuentro/proyecto -->
              <div v-if="abiertos.has(a.clave)" class="space-y-3 border-t border-borde bg-gray-50 p-3 sm:p-4">
                <section v-for="p in a.participaciones" :key="p.id" class="rounded-xl border border-borde bg-white p-4">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="font-heading font-bold leading-snug text-azul-noche">{{ p.proyecto?.titulo || 'Sin proyecto asociado' }}</p>
                      <p class="mt-0.5 text-sm text-texto-secundario">
                        Encuentro {{ fecha(p.encuentro.fecha) || 'sin fecha' }}<template v-if="p.encuentro.fecha_fin && p.encuentro.fecha_fin !== p.encuentro.fecha"> → {{ fecha(p.encuentro.fecha_fin) }}</template>
                      </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                      <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', estadoBadge(p).cls]">{{ estadoBadge(p).label }}</span>
                      <span v-if="estadoDe(p) === 'completado'"
                            :class="['rounded-full px-2.5 py-1 font-heading font-bold', p.equipo.nota_final !== null ? 'bg-administraciones/10 text-base text-administraciones-dark' : 'bg-amber-50 text-xs text-amber-800']">
                        {{ p.equipo.nota_final !== null ? formatNota(p.equipo.nota_final) : 'Sin nota' }}
                      </span>
                    </div>
                  </div>

                  <!-- Familia y módulos (FamiliaModulos, común a las bibliotecas) -->
                  <FamiliaModulos class="mt-3" :familia="p.proyecto?.familia || ''" :modulos="modulosDe(p)" :modulo-activo="filtroModulo" />

                  <!-- Grupo, rol en el grupo y compañeros -->
                  <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="rounded-full bg-azul-noche px-2 py-0.5 font-semibold text-white">{{ nombreGrupo(p.equipo) }}</span>
                    <!-- Rol en el grupo, con «i» que lo explica (también al pulsarla en móvil) -->
                    <RolGrupo v-if="p.rol" :rol="p.rol" con-prefijo />
                    <span v-if="p.equipo.companeros.length" class="text-texto-secundario">con</span>
                    <span v-for="c in p.equipo.companeros" :key="c.id" class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-azul-noche">{{ c.alias }}</span>
                  </div>

                  <!-- Fases del workspace (estado + nota de fase si la hay) -->
                  <div class="mt-3">
                    <div class="mb-1 flex items-center justify-between text-xs font-semibold text-texto-secundario">
                      <span>Fases del espacio de trabajo</span><span>{{ progreso(p) }}%</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                      <span v-for="f in FASES_PROYECTO" :key="f.num"
                            :title="`${f.label}${p.equipo.fases[f.num]?.fecha_completada ? ' · completada el ' + new Date(p.equipo.fases[f.num].fecha_completada).toLocaleDateString('es-ES') : ''}`"
                            :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold', estadoFase(p, f.num)]">
                        <span aria-hidden="true">{{ f.icono }}</span>F{{ f.num }}
                        <span class="hidden font-medium lg:inline">{{ f.label }}</span>
                        <span v-if="p.equipo.fases[f.num]?.nota_docente !== null && p.equipo.fases[f.num]?.nota_docente !== undefined && f.num !== 4"
                              class="rounded-full bg-white/70 px-1">{{ formatNota(p.equipo.fases[f.num].nota_docente) }}</span>
                      </span>
                    </div>
                  </div>

                  <!-- Evaluación final, reflexión y tareas -->
                  <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                      <dt class="text-xs font-semibold text-texto-secundario">Evaluación de RA</dt>
                      <dd class="mt-1 flex flex-wrap gap-1">
                        <template v-if="Object.keys(p.equipo.niveles_ra || {}).length">
                          <span v-for="n in NIVELES_RA.filter(n => p.equipo.niveles_ra[n.key])" :key="n.key"
                                :class="['rounded-full px-1.5 py-0.5 text-xs font-semibold', n.cls]">{{ p.equipo.niveles_ra[n.key] }} {{ n.label.toLowerCase() }}</span>
                        </template>
                        <span v-else class="text-texto-secundario">Pendiente</span>
                      </dd>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                      <dt class="text-xs font-semibold text-texto-secundario">Reflexión individual</dt>
                      <dd class="mt-1 font-semibold" :class="p.reflexion_individual ? 'text-empresas-dark' : 'text-texto-secundario'">
                        {{ p.reflexion_individual ? '✓ Entregada' : 'No entregada' }}
                      </dd>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                      <dt class="text-xs font-semibold text-texto-secundario">Tareas a su cargo</dt>
                      <dd class="mt-1 font-semibold text-azul-noche">
                        <template v-if="p.tareas.asignadas">{{ p.tareas.realizadas }}/{{ p.tareas.asignadas }} realizadas</template>
                        <span v-else class="text-texto-secundario">Ninguna asignada</span>
                      </dd>
                    </div>
                  </dl>

                  <p v-if="p.equipo.observaciones_docente" class="mt-2 rounded-xl bg-administraciones/5 px-3 py-2 text-sm text-azul-noche">
                    <span class="font-semibold text-administraciones-dark">Observaciones del docente:</span> {{ p.equipo.observaciones_docente }}
                  </p>

                  <div v-if="p.fortalezas.length || p.puntos_mejora.length" class="mt-2 flex flex-wrap gap-1 text-xs">
                    <span v-for="f in p.fortalezas" :key="'f' + f" class="rounded-full bg-empresas/10 px-2 py-0.5 font-semibold text-empresas-dark">+ {{ f }}</span>
                    <span v-for="m in p.puntos_mejora" :key="'m' + m" class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-800">↗ {{ m }}</span>
                  </div>

                  <!-- Acciones: la principal (trabajo del grupo) en el color del rol; el resto, secundarias -->
                  <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-borde pt-3">
                    <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-full bg-rol-fuerte px-4 text-sm font-semibold text-white shadow-sm hover:bg-rol-fuerte/90" @click="irAGrupo(p)">
                      Ver trabajo del grupo
                    </button>
                    <button v-if="p.equipo.tiene_diagnostico" type="button" class="accion" @click="irADiagnostico(p)">Ver diagnóstico</button>
                    <button v-if="p.proyecto" type="button" class="accion" @click="irAProyecto(p)">Ficha del proyecto</button>
                  </div>
                </section>
              </div>
            </li>
          </ul>

          <!-- Paginación -->
          <div v-if="!cargando && totalPaginas > 1" class="flex flex-col flex-wrap items-center justify-between gap-3 pt-2 sm:flex-row">
            <p class="text-sm text-texto-secundario">Mostrando {{ visibles.length }} de {{ alumnos.length }} alumnos</p>
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación del alumnado">
              <button type="button" @click="irAPagina(0)" :disabled="pagina === 0" aria-label="Primera página"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">«</button>
              <button type="button" @click="irAPagina(pagina - 1)" :disabled="pagina === 0" aria-label="Página anterior"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">‹</button>
              <button v-for="i in paginasVisibles" :key="i" type="button" @click="irAPagina(i)"
                      :aria-current="i === pagina ? 'page' : undefined" :aria-label="`Página ${i + 1}`"
                      class="inline-flex h-9 min-w-9 items-center justify-center rounded-full px-2 text-sm font-semibold"
                      :class="i === pagina ? 'bg-rol-fuerte text-white' : 'text-azul-noche hover:bg-gray-100'">{{ i + 1 }}</button>
              <button type="button" @click="irAPagina(pagina + 1)" :disabled="pagina >= totalPaginas - 1" aria-label="Página siguiente"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">›</button>
              <button type="button" @click="irAPagina(totalPaginas - 1)" :disabled="pagina >= totalPaginas - 1" aria-label="Última página"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">»</button>
            </nav>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.accion      { @apply inline-flex h-9 items-center rounded-full border border-rol-fuerte/30 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5; }
.chip-filtro { @apply inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200; }
</style>
