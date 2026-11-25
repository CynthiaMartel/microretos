<!-- Biblioteca de planes de proyecto (etapa 2, P4): los planes que desarrolla en la empresa, durante
     las prácticas, el alumnado seleccionado, y sus cronogramas (encuentros de tipo plan). La ficha y
     la edición de cada plan son las de la propuesta (/proyectos/:uuid). -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import ProyectoCard from '../components/ProyectoCard.vue'
import EliminarProyectoModal from '../components/EliminarProyectoModal.vue'
import { getPlanes, getCronogramas } from '../services/planService.js'
import { crearCodigoAcceso, actualizarEncuentro } from '../services/encuentroService.js'
import PlanificacionSesiones from '../components/PlanificacionSesiones.vue'
import FiltrosPanel from '../components/FiltrosPanel.vue'
import FiltroSegmentado from '../components/FiltroSegmentado.vue'
import FiltroSelect from '../components/FiltroSelect.vue'
import SelectorCurso from '../components/SelectorCurso.vue'
import TituloResultados from '../components/TituloResultados.vue'
import { modulosDeProyecto, modulosDisponibles as listarModulos } from '../utils/modulos.js'
import { usePaginacion } from '../composables/usePaginacion.js'
import { useFiltroEmpresaSector } from '../composables/useFiltroEmpresaSector.js'
import { useCursoAcademicoStore, cursoDeProyecto } from '../stores/cursoAcademico.js'
import { usePanelFiltros } from '../composables/usePanelFiltros.js'

const router = useRouter()
const planes = ref([])
const cronogramas = ref([])
const cargando = ref(true)
const error = ref('')
const generando = ref(null)

onMounted(async () => {
  try {
    const [p, c] = await Promise.all([getPlanes(), getCronogramas()])
    planes.value = p.data
    cronogramas.value = c.data
  } catch {
    error.value = 'No se han podido cargar los planes. Recarga la página.'
  } finally {
    cargando.value = false
  }
})

const fecha = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
const conGrupos = (c) => (c.equipos || []).length > 0

// Igual que en la biblioteca de encuentros: crea los grupos del reparto y sus códigos de acceso
async function generarGrupos(c) {
  generando.value = c.id
  error.value = ''
  try {
    await crearCodigoAcceso(c.id)
    cronogramas.value = (await getCronogramas()).data
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'No se pudieron generar los grupos.'
  } finally {
    generando.value = null
  }
}

// Eliminar un plan (menú «⋯» de la tarjeta): mismo modal que la biblioteca de propuestas
const planAEliminar = ref(null)
function onPlanEliminado(eliminado) {
  planes.value = planes.value.filter(p => p.uuid !== (eliminado?.uuid ?? planAEliminar.value?.uuid))
  planAEliminar.value = null
}

// Editar los hitos de un cronograma ya creado (fechas reales, en orden): la fecha fin pasa a ser la del último
const editandoHitos = ref(null)       // id del cronograma en edición
const hitosEdicion = ref([])
const guardandoHitos = ref(false)
function editarHitos(c) {
  editandoHitos.value = c.id
  hitosEdicion.value = (c.planificacion_sesiones || []).map(h => ({ fases: [...(h.fases || [])], fecha: h.fecha || '' }))
}
const hitosValidos = computed(() => hitosEdicion.value.length > 0 && hitosEdicion.value.every((h, i) =>
  (h.fases || []).length && h.fecha && (i === 0 || h.fecha >= hitosEdicion.value[i - 1].fecha)))
async function guardarHitos(c) {
  if (!hitosValidos.value || guardandoHitos.value) return
  guardandoHitos.value = true
  error.value = ''
  try {
    const fechaFin = hitosEdicion.value.map(h => h.fecha).sort().at(-1)
    const { data } = await actualizarEncuentro(c.id, { planificacion_sesiones: hitosEdicion.value, fecha_fin: fechaFin })
    cronogramas.value = cronogramas.value.map(x => x.id === c.id ? { ...x, ...data } : x)
    editandoHitos.value = null
  } catch (e) {
    const errores = e.response?.data?.errors
    error.value = (errores && Object.values(errores)[0]?.[0]) || e.response?.data?.message || 'No se pudieron guardar los hitos.'
  } finally {
    guardandoHitos.value = false
  }
}

const planDe = (c) => planes.value.find(p => p.uuid === c.microproyecto_uuid)
const totalHitos = (c) => (c.planificacion_sesiones || []).length
const ordenados = computed(() => [...cronogramas.value].sort((a, b) => (b.fecha || '').localeCompare(a.fecha || '')))

// ── Filtros (mismo esquema que la Biblioteca de propuestas): buscador + curso académico +
// ciclo/módulo/nivel arriba; estado, cronograma y familia en «Filtros rápidos» ──────────────
const cursoStore     = useCursoAcademicoStore()
const planesCurso    = computed(() => planes.value.filter(p => cursoStore.coincide(cursoDeProyecto(p))))
const cursosConDatos = computed(() => [...new Set(planes.value.map(cursoDeProyecto))])

const SIN_FAMILIA = '__sin_familia__'
const busqueda       = ref('')
const filtroFamilias = ref([])
const filtroCiclo    = ref('')
const filtroModulo   = ref('')
const filtroCurso    = ref('')
const filtroEstados  = ref([])
const filtroCronograma = ref([])   // 'con' | 'sin'
const filtroEmpresa  = ref('')
const filtroSector   = ref('')     // sector de la empresa
const orden          = ref('recientes')
const familiasExpandidas = ref(false)
// «Filtros rápidos»: abierto en escritorio, plegado en móvil; se reajusta al cambiar el ancho
const panelFiltrosAbierto = usePanelFiltros()
const columnasPlanes = computed(() => panelFiltrosAbierto.value
  ? 'md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3'
  : 'md:grid-cols-2 xl:grid-cols-3')

// Dependencias: el módulo y el nivel dependen del ciclo; el ciclo, de la familia
watch(filtroFamilias, () => { filtroCiclo.value = ''; filtroModulo.value = ''; filtroCurso.value = '' })
watch(filtroCiclo, () => { filtroModulo.value = ''; filtroCurso.value = '' })

const ESTADOS = [
  { key: 'validado',   label: 'Validados',             punto: 'bg-emerald-500' },
  { key: 'propuesta',  label: 'Sin validar', punto: 'bg-violet-500' },
  { key: 'en_edicion', label: 'En edición',            punto: 'bg-amber-500' },
  { key: 'completado', label: 'Completados',           punto: 'bg-sky-500' },
  { key: 'archivado',  label: 'Archivados',            punto: 'bg-gray-400' },
]
const ETIQUETA_ESTADO = Object.fromEntries(ESTADOS.map(e => [e.key, e.label]))
// Sin ninguno marcado se ve todo lo vivo: los archivados solo al marcar «Archivados»
const pasaEstado = p => filtroEstados.value.length ? filtroEstados.value.includes(p.estado) : p.estado !== 'archivado'

const tieneCronograma = p => cronogramas.value.some(c => c.microproyecto_uuid === p.uuid)
const CRONOGRAMA = [
  { key: 'con', label: 'Con cronograma', prueba: tieneCronograma },
  { key: 'sin', label: 'Sin cronograma', prueba: p => !tieneCronograma(p) },
]
const pasaCronograma = p => !filtroCronograma.value.length
  || CRONOGRAMA.some(x => filtroCronograma.value.includes(x.key) && x.prueba(p))

const coincideFamilias = p => !filtroFamilias.value.length || filtroFamilias.value.includes(p.familia_nombre || SIN_FAMILIA)
const etiquetaFamilia = nombre => (nombre === SIN_FAMILIA ? 'Sin familia asignada' : nombre)

function aplicarComunes(lista) {
  let out = lista.filter(coincideFamilias)
  if (filtroCiclo.value) out = out.filter(p => p.ciclo_nombre === filtroCiclo.value)
  if (filtroModulo.value) out = out.filter(p => modulosDeProyecto(p).includes(filtroModulo.value))
  if (filtroCurso.value) out = out.filter(p => String(p.curso) === String(filtroCurso.value))
  if (filtroEmpresa.value || filtroSector.value) out = out.filter(cumpleEmpresaSector)
  const q = busqueda.value.trim().toLowerCase()
  if (q) out = out.filter(p => [p.titulo, p.empresa_nombre, p.centro_nombre].some(t => t?.toLowerCase().includes(q)))
  return out
}
const planesComunes   = computed(() => aplicarComunes(planesCurso.value))
const planesFiltrados = computed(() => planesComunes.value.filter(p => pasaEstado(p) && pasaCronograma(p)))

// Contadores del lateral: respetan los demás filtros, no el propio grupo
const conteoEstados = computed(() => Object.fromEntries(ESTADOS.map(e =>
  [e.key, planesComunes.value.filter(p => pasaCronograma(p) && p.estado === e.key).length])))
const conteoCronograma = computed(() => Object.fromEntries(CRONOGRAMA.map(x =>
  [x.key, planesComunes.value.filter(p => pasaEstado(p) && x.prueba(p)).length])))

const familiasPanel = computed(() => {
  const mapa = new Map()
  planesCurso.value.forEach(p => {
    const clave = p.familia_nombre || SIN_FAMILIA
    mapa.set(clave, (mapa.get(clave) || 0) + 1)
  })
  return [...mapa.entries()]
    .map(([nombre, n]) => ({ nombre, n, label: etiquetaFamilia(nombre) }))
    .sort((a, b) => (a.nombre === SIN_FAMILIA) - (b.nombre === SIN_FAMILIA) || b.n - a.n || a.label.localeCompare(b.label))
})
const familiasPanelVisibles = computed(() => {
  const todas = familiasPanel.value
  if (familiasExpandidas.value || todas.length <= 6) return todas
  return [...todas.slice(0, 6), ...todas.slice(6).filter(f => filtroFamilias.value.includes(f.nombre))]
})

const baseFamilia = computed(() => planesCurso.value.filter(coincideFamilias))
const ciclosDisponibles = computed(() =>
  [...new Set(baseFamilia.value.map(p => p.ciclo_nombre).filter(Boolean))].sort((a, b) => a.localeCompare(b)))
const baseCiclo = computed(() => filtroCiclo.value ? baseFamilia.value.filter(p => p.ciclo_nombre === filtroCiclo.value) : baseFamilia.value)
const modulosDisponibles = computed(() => listarModulos(baseCiclo.value, modulosDeProyecto))
const cursosDisponibles = computed(() =>
  [...new Set(baseCiclo.value.map(p => p.curso).filter(v => v != null && v !== ''))].sort((a, b) => String(a).localeCompare(String(b))))
const etiquetaNivel = cu => (cu === 'ambos_cursos' ? 'Ambos' : `${cu}º`)
const opcionesNivel = computed(() => [{ value: '', label: 'Todos' }, ...cursosDisponibles.value.map(cu => ({ value: cu, label: etiquetaNivel(cu) }))])
// Empresa y Sector (juntos en la barra): cada uno acota las opciones del otro
const { empresasDisponibles, sectoresDisponibles, cumpleEmpresaSector } = useFiltroEmpresaSector(baseFamilia, {
  empresa: filtroEmpresa, sector: filtroSector,
})

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

const alternar = (lista, valor) => lista.includes(valor) ? lista.filter(v => v !== valor) : [...lista, valor]
function alternarFamilia(nombre) { filtroFamilias.value = alternar(filtroFamilias.value, nombre) }
function alternarEstado(key) { filtroEstados.value = alternar(filtroEstados.value, key) }
function alternarCronograma(key) { filtroCronograma.value = alternar(filtroCronograma.value, key) }

const filtrosLateral = computed(() => filtroFamilias.value.length + filtroEstados.value.length + filtroCronograma.value.length)
const hayFiltrosActivos = computed(() => !!(busqueda.value.trim() || filtroCiclo.value || filtroModulo.value
  || filtroCurso.value || filtroEmpresa.value || filtroSector.value || filtrosLateral.value))
function limpiarFiltros() {
  busqueda.value = ''
  filtroFamilias.value = []
  filtroCiclo.value = ''
  filtroModulo.value = ''
  filtroCurso.value = ''
  filtroEmpresa.value = ''
  filtroSector.value = ''
  filtroEstados.value = []
  filtroCronograma.value = []
}

// Los cronogramas siguen a los filtros: solo los de planes que se ven (sin plan conocido, solo sin filtros)
const cronogramasFiltrados = computed(() => {
  const visibles = new Set(planesFiltrados.value.map(p => p.uuid))
  return ordenados.value.filter(c => planDe(c) ? visibles.has(c.microproyecto_uuid) : !hayFiltrosActivos.value)
})

// ── Orden y paginación (12 por página, como en la Biblioteca de propuestas) ──
const fechaPlan = p => new Date(p.updated_at || p.created_at || 0).getTime()
const planesOrdenados = computed(() => {
  const lista = [...planesFiltrados.value]
  if (orden.value === 'titulo') return lista.sort((a, b) => (a.titulo || '').localeCompare(b.titulo || '', 'es'))
  return lista.sort((a, b) => orden.value === 'antiguos' ? fechaPlan(a) - fechaPlan(b) : fechaPlan(b) - fechaPlan(a))
})
const { pagina, totalPaginas, itemsPagina: planesPagina } = usePaginacion(planesOrdenados, 12)
watch([planesFiltrados, orden], () => { pagina.value = 0 })
const paginasVisibles = computed(() => {
  const n = totalPaginas.value, a = pagina.value
  const ini = Math.max(0, Math.min(a - 2, n - 5)), fin = Math.min(n, ini + 5)
  return Array.from({ length: fin - ini }, (_, i) => ini + i)
})
function irAPagina(i) {
  pagina.value = i
  document.getElementById('resultados-planes')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion data-tour="pp-cabecera" antetitulo="Plan de proyecto" titulo="Biblioteca de" destacado="planes" color="text-centros"
                       :volver="'/seccion/planes-proyecto'"
                       subtitulo="Los planes que desarrolla en la empresa, durante las prácticas, el alumnado seleccionado.">
        <button type="button" data-tour="pp-crear" class="accion-principal" @click="router.push({ name: 'planes-crear' })">Crear plan</button>
      </CabeceraSeccion>

      <!-- Filtros principales (FiltrosPanel común): buscador + curso + «Más filtros» · ciclo, módulo, nivel -->
      <FiltrosPanel data-tour="pp-filtros" separadores v-model:busqueda="busqueda" placeholder="Buscar por título, empresa o centro…">
        <template #curso>
          <div class="flex flex-wrap items-center gap-2">
            <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
            <button type="button" data-tour="pp-mas-filtros" @click="panelFiltrosAbierto = !panelFiltrosAbierto" :aria-expanded="panelFiltrosAbierto"
                    aria-controls="filtros-rapidos" :title="panelFiltrosAbierto ? 'Ocultar los filtros rápidos' : 'Mostrar los filtros rápidos'"
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
          title="Filtra por el ciclo formativo del plan." />
        <FiltroSelect v-if="modulosDisponibles.length" label="Módulo" v-model="filtroModulo" :opciones="modulosDisponibles" todos="Todos los módulos"
          title="Filtra por módulo profesional. Los planes de varios módulos aparecen en cada uno." />
        <FiltroSegmentado v-if="cursosDisponibles.length" label="Nivel" v-model="filtroCurso" :opciones="opcionesNivel"
          title="Nivel del ciclo: 1º (primer año) o 2º (segundo año)." />
        <FiltroSelect label="Empresa" v-model="filtroEmpresa" :opciones="empresasDisponibles" todos="Todas las empresas"
          title="Filtra por la empresa donde se desarrolla el plan." />
        <FiltroSelect label="Sector de la empresa" v-model="filtroSector" :opciones="sectoresDisponibles" todos="Todos los sectores"
          title="Filtra por el sector de la empresa. Puede no coincidir con la familia del plan." />
      </FiltrosPanel>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>

      <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        <!-- ═══ Filtros rápidos (panel lateral; en < lg se abre con «Más filtros») ═══ -->
        <aside id="filtros-rapidos" data-tour="pp-filtros-rapidos" aria-label="Filtros rápidos"
               class="w-full shrink-0 rounded-2xl border border-borde bg-white p-4 shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:w-64 xl:w-72"
               :class="panelFiltrosAbierto ? 'block' : 'hidden'">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-azul-noche">Filtros rápidos</h2>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros"
                    class="inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">Limpiar</button>
          </div>

          <div class="border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-estado">
            <p id="titulo-filtro-estado" class="mb-1.5 text-sm font-semibold text-azul-noche">Estado</p>
            <label v-for="e in ESTADOS" :key="e.key"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroEstados.includes(e.key)" @change="alternarEstado(e.key)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="e.punto" aria-hidden="true" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ e.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoEstados[e.key] }}</span>
            </label>
            <p v-if="!filtroEstados.includes('archivado') && conteoEstados.archivado" class="px-1 pt-1 text-xs leading-snug text-texto-secundario">
              Los archivados no se muestran salvo que marques «Archivados».
            </p>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-cronograma">
            <p id="titulo-filtro-cronograma" class="mb-1.5 text-sm font-semibold text-azul-noche">Cronograma</p>
            <label v-for="x in CRONOGRAMA" :key="x.key"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroCronograma.includes(x.key)" @change="alternarCronograma(x.key)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ x.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoCronograma[x.key] }}</span>
            </label>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-familia">
            <p id="titulo-filtro-familia" class="mb-1.5 text-sm font-semibold text-azul-noche">Familia profesional</p>
            <label v-for="f in familiasPanelVisibles" :key="f.nombre"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroFamilias.includes(f.nombre)" @change="alternarFamilia(f.nombre)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche" :title="f.label">{{ f.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ f.n }}</span>
            </label>
            <p v-if="!familiasPanel.length && !cargando" class="px-1 py-1 text-sm text-texto-secundario">Sin planes en este curso.</p>
            <button v-if="familiasPanel.length > 6" type="button" @click="familiasExpandidas = !familiasExpandidas"
                    class="mt-1 inline-flex h-9 items-center rounded-full px-1 text-sm font-semibold text-rol-fuerte hover:underline">
              {{ familiasExpandidas ? 'Ver menos' : `Ver todas (${familiasPanel.length})` }}
            </button>
          </div>
        </aside>

      <div class="min-w-0 flex-1 space-y-6">
      <p v-if="cargando" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>

      <template v-else>
        <!-- Cronogramas -->
        <section data-tour="pp-cronogramas" aria-labelledby="titulo-cronogramas">
          <h2 id="titulo-cronogramas" class="mb-3 font-heading text-lg font-bold">Cronogramas en marcha</h2>
          <p v-if="!cronogramasFiltrados.length" class="rounded-2xl border border-borde bg-white px-4 py-6 text-center text-sm text-texto-secundario">
            {{ ordenados.length ? 'Ningún cronograma de los planes que cumplen estos filtros.' : 'Aún no hay cronogramas. Crea uno desde la ficha de un plan validado.' }}
          </p>
          <ul v-else class="grid grid-cols-1 gap-3" :class="columnasPlanes">
            <li v-for="(c, i) in cronogramasFiltrados" :key="c.id" :data-tour="i === 0 ? 'pp-cronograma' : undefined" class="min-w-0 rounded-2xl border border-borde bg-white p-4 shadow-sm"
                :class="editandoHitos === c.id && 'col-span-full'">
              <p class="break-words font-bold">{{ c.proyecto_titulo || 'Plan sin título' }}</p>
              <p class="mt-0.5 text-sm text-texto-secundario">
                {{ fecha(c.fecha) }}<template v-if="c.fecha_fin"> → {{ fecha(c.fecha_fin) }}</template>
                · {{ totalHitos(c) }} {{ totalHitos(c) === 1 ? 'hito' : 'hitos' }} · {{ (c.alumnados || []).length }} {{ (c.alumnados || []).length === 1 ? 'alumno/a' : 'alumnos' }}
                <template v-if="planDe(c)?.horas_estimadas"> · {{ planDe(c).horas_estimadas }} h</template>
              </p>
              <div class="mt-3 flex flex-wrap gap-2">
                <template v-if="conGrupos(c)">
                  <button type="button" class="accion-secundaria" @click="router.push({ name: 'mis-grupos-detalle', params: { id: c.id } })">Detalle de grupos</button>
                  <button v-if="c.microproyecto_uuid" type="button" class="accion-secundaria"
                          @click="router.push({ name: 'pantalla-acceso', params: { uuid: c.microproyecto_uuid } })">Dar acceso</button>
                </template>
                <button v-else-if="c.puede_editar" type="button" class="accion-principal" :disabled="generando === c.id" @click="generarGrupos(c)">
                  {{ generando === c.id ? 'Generando…' : 'Generar grupos y códigos' }}
                </button>
                <button v-if="c.puede_editar && editandoHitos !== c.id" type="button" class="accion-secundaria" @click="editarHitos(c)">Editar hitos</button>
              </div>
              <!-- Edición de hitos (fechas reales, en orden) -->
              <div v-if="editandoHitos === c.id" class="mt-3 rounded-xl border border-borde p-3">
                <PlanificacionSesiones v-model="hitosEdicion" unidad="hito" unidades="hitos" con-fecha :fecha-minima="c.fecha" />
                <div class="mt-3 flex flex-wrap gap-2">
                  <button type="button" class="accion-principal" :disabled="!hitosValidos || guardandoHitos" @click="guardarHitos(c)">
                    {{ guardandoHitos ? 'Guardando…' : 'Guardar hitos' }}
                  </button>
                  <button type="button" class="accion-secundaria" @click="editandoHitos = null">Cancelar</button>
                </div>
              </div>
            </li>
          </ul>
        </section>

        <!-- Planes -->
        <section id="resultados-planes" class="scroll-mt-24" aria-label="Planes">
          <div data-tour="pp-resultados" class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <TituloResultados :niveles="nivelesTitulo">
              {{ planesFiltrados.length }} {{ planesFiltrados.length === 1 ? 'plan' : 'planes' }}
            </TituloResultados>
            <!-- Chips de filtros activos del lateral -->
            <div v-if="(filtroFamilias.length && !familiaEnTitulo) || filtroEstados.length || filtroCronograma.length"
                 class="flex w-full flex-wrap gap-1.5 sm:order-last">
              <button v-for="e in filtroEstados" :key="'e-' + e" type="button" @click="alternarEstado(e)"
                      class="inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200"
                      :aria-label="`Quitar filtro ${ETIQUETA_ESTADO[e] || e}`">
                <span class="h-2 w-2 rounded-full" :class="ESTADOS.find(x => x.key === e)?.punto || 'bg-gray-400'" aria-hidden="true" />
                {{ ETIQUETA_ESTADO[e] || e }}<span aria-hidden="true">✕</span>
              </button>
              <button v-for="x in filtroCronograma" :key="'c-' + x" type="button" @click="alternarCronograma(x)"
                      class="inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200"
                      :aria-label="`Quitar filtro ${CRONOGRAMA.find(y => y.key === x)?.label}`">
                {{ CRONOGRAMA.find(y => y.key === x)?.label }}<span aria-hidden="true">✕</span>
              </button>
              <button v-for="f in (familiaEnTitulo ? [] : filtroFamilias)" :key="'f-' + f" type="button" @click="alternarFamilia(f)"
                      class="inline-flex h-9 max-w-full items-center gap-1.5 rounded-full bg-rol/10 px-3 text-xs font-semibold text-rol-fuerte hover:bg-rol/15"
                      :aria-label="`Quitar filtro ${etiquetaFamilia(f)}`">
                <span class="truncate">{{ etiquetaFamilia(f) }}</span><span aria-hidden="true">✕</span>
              </button>
            </div>
            <label class="flex items-center gap-2 text-sm text-texto-secundario">
              <span class="hidden sm:inline">Ordenar por</span>
              <select v-model="orden" class="h-9 rounded-xl px-3 text-sm text-azul-noche" aria-label="Ordenar planes">
                <option value="recientes">Actividad más reciente</option>
                <option value="antiguos">Actividad más antigua</option>
                <option value="titulo">Título (A-Z)</option>
              </select>
            </label>
          </div>

          <div v-if="!planesFiltrados.length" class="rounded-2xl border border-dashed border-borde bg-white px-4 py-12 text-center shadow-sm">
            <h3 class="mb-1 text-lg font-bold text-azul-noche">
              {{ hayFiltrosActivos ? 'Sin resultados con estos filtros' : 'Aún no hay planes' }}
            </h3>
            <p class="mx-auto max-w-md text-sm text-texto-secundario">
              {{ hayFiltrosActivos ? 'Prueba a quitar algún filtro.' : 'Crea el primero a partir de un reto ajustado completado o de otro reto.' }}
            </p>
            <div class="mt-5 flex flex-wrap justify-center gap-2">
              <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros" class="accion-secundaria">Quitar filtros</button>
              <button v-else type="button" class="accion-principal" @click="router.push({ name: 'planes-crear' })">Crear plan</button>
            </div>
          </div>
          <div v-else class="grid grid-cols-1 items-start gap-4" :class="columnasPlanes">
            <ProyectoCard v-for="(p, i) in planesPagina" :key="p.uuid" :data-tour="i === 0 ? 'pp-tarjeta' : undefined" :proyecto="p" :modulo-activo="filtroModulo" @eliminar="planAEliminar = $event" />
          </div>

          <!-- Paginación -->
          <div v-if="totalPaginas > 1" class="mt-5 flex flex-col flex-wrap items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-texto-secundario">Mostrando {{ planesPagina.length }} de {{ planesFiltrados.length }} planes</p>
            <nav data-tour="pp-paginacion" class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación de planes">
              <button type="button" @click="irAPagina(pagina - 1)" :disabled="pagina === 0" aria-label="Página anterior"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">‹</button>
              <button v-for="i in paginasVisibles" :key="i" type="button" @click="irAPagina(i)"
                      :aria-current="i === pagina ? 'page' : undefined" :aria-label="`Página ${i + 1}`"
                      class="inline-flex h-9 min-w-9 items-center justify-center rounded-full px-2 text-sm font-semibold"
                      :class="i === pagina ? 'bg-rol-fuerte text-white' : 'text-azul-noche hover:bg-gray-100'">{{ i + 1 }}</button>
              <button type="button" @click="irAPagina(pagina + 1)" :disabled="pagina >= totalPaginas - 1" aria-label="Página siguiente"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">›</button>
            </nav>
          </div>
        </section>
      </template>
      </div>
      </div>
    </div>
    <EliminarProyectoModal :visible="!!planAEliminar" :proyecto="planAEliminar"
                           @proyecto-eliminado="onPlanEliminado" @cerrar="planAEliminar = null" />
  </div>
</template>
