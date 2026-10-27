<!-- Ruta: /evaluacion/diagnosticos (name: biblioteca-diagnosticos). Todos los diagnósticos
     finales de los grupos del docente, en un solo sitio. Usa el endpoint que ya alimenta
     Mis grupos (/encuentros/mis-grupos) y el mismo modal de MisGruposDetalle.vue.
     Mismo esquema que las demás bibliotecas (docs/GUIA_DISENO.md §7): barra de filtros común,
     «Filtros rápidos» a la izquierda, título de resultados con niveles, tarjetas con pie
     (botón principal + «⋯») y paginación. Una tarjeta por ENCUENTRO (título = su proyecto) con
     sus grupos diagnosticados dentro, para no mezclar «Grupo 1» de encuentros distintos. -->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMisGrupos } from '../services/encuentroService.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import DiagnosticoModal from '../components/DiagnosticoModal.vue'
import FiltrosPanel from '../components/FiltrosPanel.vue'
import FiltroSegmentado from '../components/FiltroSegmentado.vue'
import FiltroSelect from '../components/FiltroSelect.vue'
import SelectorCurso from '../components/SelectorCurso.vue'
import TituloResultados from '../components/TituloResultados.vue'
import FamiliaModulos from '../components/FamiliaModulos.vue'
import ChipDocente from '../components/ChipDocente.vue'
import MiniaturaReto from '../components/MiniaturaReto.vue'
import { useDiagnosticoPdfExport } from '../composables/useDiagnosticoPdfExport.js'
import { usePaginacion } from '../composables/usePaginacion.js'
import { useCursoAcademicoStore, cursoDeEncuentro } from '../stores/cursoAcademico.js'
import { formatCurso } from '../utils/formatCurso.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

const router = useRouter()

const grupos   = ref([])
const cargando = ref(true)
const error    = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getMisGrupos()
    grupos.value = data
  } catch {
    error.value = 'No se han podido cargar los diagnósticos.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

// Una tarjeta por encuentro con, dentro, sus grupos que ya tienen diagnóstico final
// (opción A: así no se mezclan decenas de «Grupo 1» de encuentros distintos)
const cursoStore = useCursoAcademicoStore()
const todos = computed(() => grupos.value
  .map(g => ({ encuentro: g.encuentro, totalGrupos: g.equipos.length, diags: g.equipos.filter(e => e.diagnostico_final) }))
  .filter(x => x.diags.length))
const cursosConDatos = computed(() => [...new Set(todos.value.map(x => cursoDeEncuentro(x.encuentro)))])
const delCurso = computed(() => todos.value.filter(x => cursoStore.coincide(cursoDeEncuentro(x.encuentro))))

// La API manda la nota como texto («8.00»): a número para poder hacer medias y ordenar
const notaFinal = (equipo) => { const v = equipo.fases?.[4]?.nota_docente; if (v === null || v === undefined || v === '') return null; const n = Number(v); return Number.isFinite(n) ? n : null }
const fecha = (isoStr) => isoStr ? new Date(isoStr).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
const formatNota = (n) => (n === null || n === undefined) ? '' : Number(n).toLocaleString('es-ES', { maximumFractionDigits: 2 })

const SIN_FAMILIA = '__sin_familia__'
const familiaDe = (x) => x.encuentro.familia_nombre || x.diags[0]?.proyecto?.familia || SIN_FAMILIA
const etiquetaFamilia = (f) => (f === SIN_FAMILIA ? 'Sin familia asignada' : f)
const modulosDe = (x) => x.encuentro.modulos ?? []
const esMio = (x) => x.encuentro.es_propietario !== false
const tituloEncuentro = (x) => x.encuentro.proyecto_titulo || x.diags[0]?.proyecto?.titulo || (x.encuentro.grupo && `Clase ${x.encuentro.grupo}`) || 'Encuentro'
const notaMedia = (x) => { const n = x.diags.map(notaFinal).filter(v => v !== null); return n.length ? n.reduce((t, v) => t + v, 0) / n.length : null }
const fechaEncuentro = (x) => { const f = x.encuentro.fecha; if (!f) return ''; const d = new Date(String(f).slice(0, 10) + 'T12:00:00'); return isNaN(d) ? '' : d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) }
const miembrosTexto = (e) => (e.miembros || []).map(m => m.alias).join(', ')

// Tramos de la nota final (fase 4)
const TRAMOS = [
  { value: 'sobresaliente', label: 'Sobresaliente (9-10)', prueba: n => n !== null && n >= 9 },
  { value: 'notable',       label: 'Notable (7-8,9)',      prueba: n => n !== null && n >= 7 && n < 9 },
  { value: 'bien',          label: 'Bien (6-6,9)',         prueba: n => n !== null && n >= 6 && n < 7 },
  { value: 'suficiente',    label: 'Suficiente (5-5,9)',   prueba: n => n !== null && n >= 5 && n < 6 },
  { value: 'insuficiente',  label: 'Insuficiente (< 5)',   prueba: n => n !== null && n < 5 },
  { value: 'sin_nota',      label: 'Sin nota final',       prueba: n => n === null },
]

// ── Filtros ─────────────────────────────────────────────────────────────────
const busqueda        = ref('')
const filtroFamilias  = ref([])
const filtroCiclo     = ref('')
const filtroModulo    = ref('')
const filtroCurso     = ref('')
const filtroClase     = ref('')
const filtroTramos    = ref([])
const filtroPropiedad = ref('')   // '' | 'mios' | 'compartidos'
const orden           = ref('recientes') // 'recientes' | 'antiguos' | 'nota' | 'titulo'
const familiasExpandidas = ref(false)
const panelFiltrosAbierto = ref(typeof window === 'undefined' || window.innerWidth >= 1024)

watch(filtroFamilias, () => { filtroCiclo.value = ''; filtroModulo.value = ''; filtroCurso.value = '' })
watch(filtroCiclo, () => { filtroModulo.value = ''; filtroCurso.value = '' })

const coincideFamilias = (x) => !filtroFamilias.value.length || filtroFamilias.value.includes(familiaDe(x))
function coincideBusqueda(x) {
  const q = busqueda.value.trim().toLowerCase()
  if (!q) return true
  return [tituloEncuentro(x), x.encuentro.ciclo_formativo, x.encuentro.grupo && `clase ${x.encuentro.grupo}`, ...modulosDe(x),
    ...x.diags.flatMap(e => [nombreGrupo(e), ...(e.miembros || []).map(m => m.alias)])]
    .filter(Boolean).join(' ').toLowerCase().includes(q)
}
// Con tramos de nota elegidos, cada tarjeta enseña solo los grupos que caen en ellos
const pasaTramo = (e) => !filtroTramos.value.length || TRAMOS.some(t => filtroTramos.value.includes(t.value) && t.prueba(notaFinal(e)))
function aplicar(lista, { sinTramos = false, sinPropiedad = false } = {}) {
  return lista.filter(x =>
    coincideFamilias(x) &&
    (!filtroCiclo.value  || x.encuentro.ciclo_formativo === filtroCiclo.value) &&
    (!filtroModulo.value || modulosDe(x).includes(filtroModulo.value)) &&
    (!filtroCurso.value  || String(x.encuentro.curso) === String(filtroCurso.value)) &&
    (!filtroClase.value  || x.encuentro.grupo === filtroClase.value) &&
    (sinTramos || x.diags.some(pasaTramo)) &&
    (sinPropiedad || !filtroPropiedad.value || (filtroPropiedad.value === 'mios') === esMio(x)) &&
    coincideBusqueda(x))
}
const diagsVisibles = (x) => x.diags.filter(pasaTramo)
// Desplegable de grupos: por defecto solo el primero
const gruposAbiertos = ref(new Set())
function alternarGrupos(x) {
  const s = new Set(gruposAbiertos.value)
  s.has(claveDe(x)) ? s.delete(claveDe(x)) : s.add(claveDe(x))
  gruposAbiertos.value = s
}
const gruposMostrados = (x) => gruposAbiertos.value.has(claveDe(x)) ? diagsVisibles(x) : diagsVisibles(x).slice(0, 1)
const filtrados = computed(() => aplicar(delCurso.value))
const totalDiagnosticos = computed(() => filtrados.value.reduce((t, x) => t + diagsVisibles(x).length, 0))

// Opciones de la barra (familia → ciclo → módulo/nivel/clase)
const baseFamilia = computed(() => delCurso.value.filter(coincideFamilias))
const ciclosDisponibles = computed(() =>
  [...new Set(baseFamilia.value.map(x => x.encuentro.ciclo_formativo).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const baseCiclo = computed(() => filtroCiclo.value ? baseFamilia.value.filter(x => x.encuentro.ciclo_formativo === filtroCiclo.value) : baseFamilia.value)
const modulosDisponibles = computed(() => [...new Set(baseCiclo.value.flatMap(modulosDe).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const cursosDisponibles = computed(() => [...new Set(baseCiclo.value.map(x => x.encuentro.curso).filter(Boolean))].sort())
const opcionesNivel = computed(() => [{ value: '', label: 'Todos' }, ...cursosDisponibles.value.map(c => ({ value: c, label: formatCurso(c) }))])
const clasesDisponibles = computed(() => [...new Set(baseCiclo.value.map(x => x.encuentro.grupo).filter(Boolean))].sort())
const opcionesClase = computed(() => [{ value: '', label: 'Todas' }, ...clasesDisponibles.value.map(c => ({ value: c, label: c }))])

// Lateral: contadores (cada grupo sin su propio filtro)
const conteoTramos = computed(() => {
  const base = aplicar(delCurso.value, { sinTramos: true })
  // Cuenta diagnósticos (grupos) en cada tramo
  return Object.fromEntries(TRAMOS.map(t => [t.value, base.reduce((n, x) => n + x.diags.filter(e => t.prueba(notaFinal(e))).length, 0)]))
})
const conteoPropiedad = computed(() => {
  const base = aplicar(delCurso.value, { sinPropiedad: true })
  return { '': base.length, mios: base.filter(esMio).length, compartidos: base.filter(d => !esMio(d)).length }
})
const hayCompartidos = computed(() => conteoPropiedad.value.compartidos > 0)
const familiasPanel = computed(() => {
  const mapa = new Map()
  delCurso.value.forEach(x => { const k = familiaDe(x); mapa.set(k, (mapa.get(k) || 0) + 1) })
  return [...mapa.entries()].map(([nombre, n]) => ({ nombre, n, label: etiquetaFamilia(nombre) }))
    .sort((a, b) => (a.nombre === SIN_FAMILIA) - (b.nombre === SIN_FAMILIA) || b.n - a.n || a.label.localeCompare(b.label, 'es'))
})
const familiasPanelVisibles = computed(() => {
  const todas = familiasPanel.value
  if (familiasExpandidas.value || todas.length <= 6) return todas
  return [...todas.slice(0, 6), ...todas.slice(6).filter(f => filtroFamilias.value.includes(f.nombre))]
})

const alternar = (lista, valor) => lista.includes(valor) ? lista.filter(v => v !== valor) : [...lista, valor]
function alternarFamilia(nombre) { filtroFamilias.value = alternar(filtroFamilias.value, nombre) }
function alternarTramo(valor) { filtroTramos.value = alternar(filtroTramos.value, valor) }

// Niveles elegidos para el título de los resultados (una familia → ciclo → módulo)
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

const filtrosLateral = computed(() => filtroFamilias.value.length + filtroTramos.value.length + (filtroPropiedad.value ? 1 : 0))
const hayFiltrosActivos = computed(() => !!(busqueda.value.trim() || filtroCiclo.value || filtroModulo.value || filtroCurso.value || filtroClase.value || filtrosLateral.value))
function limpiarFiltros() {
  busqueda.value = ''; filtroFamilias.value = []; filtroCiclo.value = ''; filtroModulo.value = ''
  filtroCurso.value = ''; filtroClase.value = ''; filtroTramos.value = []; filtroPropiedad.value = ''
}

// ── Orden y paginación (12 por página, ver POR_PAGINA) ──────
const fechaDiag = (x) => Math.max(...x.diags.map(e => new Date(e.diagnostico_generado_en || 0).getTime()))
const ordenados = computed(() => [...filtrados.value].sort((a, b) =>
  orden.value === 'antiguos' ? fechaDiag(a) - fechaDiag(b)
  : orden.value === 'nota' ? (notaMedia(b) ?? -1) - (notaMedia(a) ?? -1)
  : orden.value === 'titulo' ? tituloEncuentro(a).localeCompare(tituloEncuentro(b), 'es', { numeric: true })
  : fechaDiag(b) - fechaDiag(a)))
// 12 por página: se reparte entero en 1, 2, 3 o 4 columnas (con 9, a 2 columnas quedaba
// una tarjeta sola en la última fila y un hueco a su derecha)
const POR_PAGINA = 12
const { pagina, totalPaginas, itemsPagina } = usePaginacion(ordenados, POR_PAGINA)
watch([filtrados, orden], () => { pagina.value = 0 })
const paginasVisibles = computed(() => {
  const n = totalPaginas.value, a = pagina.value
  const ini = Math.max(0, Math.min(a - 2, n - 5)), fin = Math.min(n, ini + 5)
  return Array.from({ length: fin - ini }, (_, i) => ini + i)
})
function irAPagina(i) {
  pagina.value = i
  document.getElementById('resultados-diagnosticos')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
const columnas = computed(() => panelFiltrosAbierto.value ? 'md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3' : 'md:grid-cols-2 xl:grid-cols-3')

// Modal de diagnóstico (mismo que en Mis grupos)
const abierto = ref(null)

// ── Menú «⋯» de cada tarjeta ─────────────────────────────────────────────────
// Cierre con clic fuera o Escape (un overlay fixed quedaría por debajo del menú)
const menuDiag = ref(null)
const claveDe = (x) => x.encuentro.id
function cerrarFuera(e) { if (!e.target.closest?.('[data-menu-raiz]')) menuDiag.value = null }
function cerrarEsc(e) { if (e.key === 'Escape') menuDiag.value = null }
watch(menuDiag, (v) => {
  const m = v ? 'addEventListener' : 'removeEventListener'
  document[m]('click', cerrarFuera, true)
  document[m]('keydown', cerrarEsc)
})
onUnmounted(() => {
  document.removeEventListener('click', cerrarFuera, true)
  document.removeEventListener('keydown', cerrarEsc)
})
const { descargarPDF } = useDiagnosticoPdfExport()
const irAEncuentro = (x) => router.push({ name: 'mis-grupos-detalle', params: { id: x.encuentro.id } })
const irAProyecto  = (x) => { const uuid = x.diags[0]?.proyecto?.uuid; if (uuid) router.push({ name: 'startup-day-detalle', params: { uuid } }) }
function descargarTodos(x) { diagsVisibles(x).forEach(e => descargarPDF({ equipo: e, encuentro: x.encuentro })) }
</script>

<template>
  <!-- Explorador de diagnósticos (mismo esquema que las demás bibliotecas) -->
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Biblioteca de" destacado="diagnósticos" color="text-administraciones" volver="/seccion/alumnado"
                       subtitulo="Los diagnósticos finales de tus grupos: fortalezas, áreas de mejora y valoración de RA y CE de cada proyecto terminado." />

      <!-- Filtros principales (FiltrosPanel común) -->
      <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar por grupo, proyecto, ciclo o módulo…">
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

        <FiltroSelect label="Ciclo formativo" v-model="filtroCiclo" :opciones="ciclosDisponibles" todos="Todos los ciclos" />
        <FiltroSelect v-if="modulosDisponibles.length" label="Módulo" v-model="filtroModulo" :opciones="modulosDisponibles" todos="Todos los módulos" />
        <FiltroSegmentado v-if="cursosDisponibles.length" label="Nivel" v-model="filtroCurso" :opciones="opcionesNivel" />
        <FiltroSegmentado v-if="clasesDisponibles.length" label="Clase" v-model="filtroClase" :opciones="opcionesClase" />
      </FiltrosPanel>

      <!-- Error de carga -->
      <div v-if="error && !cargando" class="flex flex-col items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center" role="alert">
        <p class="min-w-0 flex-1 text-sm font-medium text-red-700">{{ error }}</p>
        <button type="button" @click="cargar" class="inline-flex h-10 items-center rounded-full bg-white px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Reintentar</button>
      </div>

      <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        <!-- ═══ Filtros rápidos ═══ -->
        <aside id="filtros-rapidos" aria-label="Filtros rápidos"
               class="w-full shrink-0 rounded-2xl border border-borde bg-white p-4 shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:w-64 lg:overflow-y-auto xl:w-72"
               :class="panelFiltrosAbierto ? 'block' : 'hidden'">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-azul-noche">Filtros rápidos</h2>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros"
                    class="inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">Limpiar</button>
          </div>

          <div class="border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-nota">
            <p id="titulo-filtro-nota" class="mb-1.5 text-sm font-semibold text-azul-noche">Nota final</p>
            <label v-for="t in TRAMOS" v-show="conteoTramos[t.value] || filtroTramos.includes(t.value)" :key="t.value"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroTramos.includes(t.value)" @change="alternarTramo(t.value)" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ t.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoTramos[t.value] }}</span>
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
            <p v-if="!familiasPanel.length && !cargando" class="px-1 py-1 text-sm text-texto-secundario">Sin diagnósticos en este curso.</p>
            <button v-if="familiasPanel.length > 6" type="button" @click="familiasExpandidas = !familiasExpandidas"
                    class="mt-1 inline-flex h-9 items-center rounded-full px-1 text-sm font-semibold text-rol-fuerte hover:underline">
              {{ familiasExpandidas ? 'Ver menos' : `Ver todas (${familiasPanel.length})` }}
            </button>
          </div>

          <div v-if="hayCompartidos" class="mt-3 border-t border-borde pt-3" role="radiogroup" aria-labelledby="titulo-filtro-propiedad">
            <p id="titulo-filtro-propiedad" class="mb-1.5 text-sm font-semibold text-azul-noche">De quién es</p>
            <label v-for="o in [{ v: '', label: 'Todos' }, { v: 'mios', label: 'Míos' }, { v: 'compartidos', label: 'Compartidos conmigo' }]" :key="o.v || 'todos'"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="radio" name="diag-filtro-propiedad" :value="o.v" v-model="filtroPropiedad" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ o.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoPropiedad[o.v] }}</span>
            </label>
          </div>
        </aside>

        <!-- ═══ Resultados ═══ -->
        <section id="resultados-diagnosticos" class="min-w-0 flex-1 scroll-mt-24" aria-label="Diagnósticos">
          <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <TituloResultados :niveles="nivelesTitulo" :cargando="cargando">
              <template v-if="cargando">Cargando diagnósticos…</template>
              <template v-else>{{ filtrados.length }} {{ filtrados.length === 1 ? 'encuentro' : 'encuentros' }} <span class="font-normal text-texto-secundario">· {{ totalDiagnosticos }} {{ totalDiagnosticos === 1 ? 'diagnóstico' : 'diagnósticos' }}</span></template>
            </TituloResultados>
            <div v-if="filtroTramos.length || filtroPropiedad || (filtroFamilias.length && !familiaEnTitulo)" class="flex w-full flex-wrap gap-1.5 sm:order-last">
              <button v-for="t in filtroTramos" :key="'t-' + t" type="button" @click="alternarTramo(t)" class="chip-filtro"
                      :aria-label="`Quitar filtro ${TRAMOS.find(x => x.value === t)?.label}`">
                {{ TRAMOS.find(x => x.value === t)?.label }}<span aria-hidden="true">✕</span>
              </button>
              <button v-if="filtroPropiedad" type="button" @click="filtroPropiedad = ''" class="chip-filtro" aria-label="Quitar filtro de quién es">
                {{ filtroPropiedad === 'mios' ? 'Míos' : 'Compartidos conmigo' }}<span aria-hidden="true">✕</span>
              </button>
              <button v-for="f in (familiaEnTitulo ? [] : filtroFamilias)" :key="'f-' + f" type="button" @click="alternarFamilia(f)"
                      class="inline-flex h-9 max-w-full items-center gap-1.5 rounded-full bg-rol/10 px-3 text-xs font-semibold text-rol-fuerte hover:bg-rol/15"
                      :aria-label="`Quitar filtro ${etiquetaFamilia(f)}`">
                <span class="truncate">{{ etiquetaFamilia(f) }}</span><span aria-hidden="true">✕</span>
              </button>
            </div>
            <label class="flex items-center gap-2 text-sm text-texto-secundario">
              <span class="hidden sm:inline">Ordenar por</span>
              <select v-model="orden" class="h-9 rounded-xl px-3 text-sm text-azul-noche" aria-label="Ordenar diagnósticos">
                <option value="recientes">Más recientes</option>
                <option value="antiguos">Más antiguos</option>
                <option value="nota">Nota media más alta</option>
                <option value="titulo">Proyecto (A-Z)</option>
              </select>
            </label>
          </div>

          <!-- Carga -->
          <div v-if="cargando" class="grid grid-cols-1 gap-4" :class="columnas" aria-busy="true">
            <div v-for="n in 6" :key="n" class="h-48 animate-pulse rounded-2xl border border-borde bg-white" />
            <span class="sr-only">Cargando diagnósticos…</span>
          </div>

          <!-- Vacíos -->
          <div v-else-if="!error && !todos.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <h3 class="text-lg font-bold text-azul-noche">Todavía no hay diagnósticos finales</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">Se generan desde Mis grupos cuando un grupo termina su proyecto.</p>
            <button type="button" class="accion-secundaria mt-4" @click="router.push('/mis-grupos')">Ir a Mis grupos</button>
          </div>
          <div v-else-if="!error && !filtrados.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <h3 class="text-lg font-bold text-azul-noche">Ningún encuentro con diagnósticos coincide con estos filtros</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">Prueba a quitar algún filtro.</p>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros" class="accion-secundaria mt-4">Limpiar filtros</button>
          </div>

          <!-- Tarjetas -->
          <!-- Sin items-start: las tarjetas de cada fila se estiran a la misma altura y el pie queda abajo -->
          <ul v-else-if="!error" class="grid grid-cols-1 gap-4" :class="columnas">
            <li v-for="x in itemsPagina" :key="claveDe(x)"
                class="relative flex min-w-0 flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
                :class="{ 'z-10': menuDiag === claveDe(x) }">
              <!-- Cabecera: el encuentro (título de su proyecto) y su nota media -->
              <div class="flex min-w-0 gap-3">
                <!-- Miniatura solo con sitio de sobra: en tarjetas estrechas quitaba ancho al título -->
                <MiniaturaReto class="!hidden h-16 w-16 shrink-0 2xl:!flex" />
                <div class="min-w-0 flex-1">
                  <h3 class="line-clamp-2 min-h-11 font-heading text-base font-bold leading-snug text-azul-noche" :title="tituloEncuentro(x)">{{ tituloEncuentro(x) }}</h3>
                  <p class="mt-0.5 truncate text-sm text-texto-secundario">
                    <template v-if="x.encuentro.grupo">Clase {{ x.encuentro.grupo }} · </template><template v-if="x.encuentro.curso">{{ formatCurso(x.encuentro.curso) }} curso · </template>{{ fechaEncuentro(x) || 'Sin fecha' }}
                  </p>
                </div>
                <span v-if="notaMedia(x) !== null"
                      class="flex shrink-0 flex-col items-center self-start rounded-xl bg-administraciones/10 px-3 py-1.5 text-administraciones-dark"
                      :title="x.diags.length > 1 ? 'Nota media de los grupos con diagnóstico' : 'Nota final del grupo'">
                  <span class="text-xs font-semibold leading-none">{{ x.diags.length > 1 ? 'Nota media' : 'Nota final' }}</span>
                  <span class="mt-0.5 font-heading text-lg font-bold leading-none">{{ formatNota(notaMedia(x)) }}<span class="text-xs font-semibold">/10</span></span>
                </span>
              </div>

              <!-- Hueco fijo para el chip del docente: con o sin él, las tarjetas miden lo mismo -->
              <div class="mt-2 flex min-h-6 items-start">
                <ChipDocente v-if="!esMio(x) && x.encuentro.propietario_nombre"
                             :nombre="x.encuentro.propietario_nombre" :solo-lectura="x.encuentro.puede_editar === false" />
              </div>

              <!-- Alto mínimo de familia + módulos (dos líneas), haya o no módulos -->
              <FamiliaModulos class="mt-3 min-h-[4.125rem]" :familia="etiquetaFamilia(familiaDe(x))" :modulos="modulosDe(x)" :modulo-activo="filtroModulo" />

              <!-- Grupos del encuentro con diagnóstico: uno por fila -->
              <ul class="mt-3 divide-y divide-borde rounded-xl border border-borde" :aria-label="`Diagnósticos de ${tituloEncuentro(x)}`">
                <li v-for="e in gruposMostrados(x)" :key="e.id" class="flex min-w-0 flex-col gap-2 p-3 sm:flex-row sm:items-center">
                  <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                      <span class="font-semibold text-azul-noche">{{ nombreGrupo(e) }}</span>
                      <span v-if="notaFinal(e) !== null" class="rounded-full bg-administraciones/10 px-2 py-0.5 text-xs font-semibold text-administraciones-dark">
                        Nota final {{ formatNota(notaFinal(e)) }}/10
                      </span>
                    </p>
                    <p class="min-h-4 truncate text-xs text-texto-secundario" :title="miembrosTexto(e)">{{ miembrosTexto(e) }}</p>
                    <p class="mt-1 line-clamp-2 min-h-[2.75em] text-sm leading-snug text-azul-noche">{{ e.diagnostico_final.resumen }}</p>
                  </div>
                  <button type="button" @click="abierto = { equipo: e, encuentro: x.encuentro }"
                          class="inline-flex h-9 shrink-0 items-center gap-1.5 self-start rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5 sm:self-center"
                          :aria-label="`Ver el diagnóstico de ${nombreGrupo(e)}`">
                    Ver diagnóstico
                  </button>
                </li>
              </ul>

              <!-- Hueco fijo (también sin botón) para que todas las tarjetas plegadas midan lo mismo -->
              <div class="mt-2 flex min-h-9 items-start">
                <!-- Más de un grupo: se ve el primero y el resto, desplegable (todas las tarjetas igual de altas) -->
                <button v-if="diagsVisibles(x).length > 1" type="button" @click="alternarGrupos(x)"
                        :aria-expanded="gruposAbiertos.has(claveDe(x))"
                        class="inline-flex h-9 items-center gap-1.5 self-start rounded-full border border-rol-fuerte/30 bg-white px-3 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                  <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': gruposAbiertos.has(claveDe(x)) }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                  {{ gruposAbiertos.has(claveDe(x)) ? 'Ocultar los demás grupos' : `Ver ${diagsVisibles(x).length - 1} ${diagsVisibles(x).length - 1 === 1 ? 'grupo más' : 'grupos más'}` }}
                </button>
              </div>

              <span class="min-h-3 flex-1" aria-hidden="true" />
              <!-- Pie (siempre abajo): cuántos grupos tienen diagnóstico + «Ver encuentro» y «⋯» -->
              <div class="flex flex-wrap items-center gap-2 border-t border-borde pt-3">
                <!-- Una sola línea (si saltara a dos, esa tarjeta sería más alta que las demás) -->
                <span class="min-w-0 flex-1 truncate text-xs text-texto-secundario"
                      :title="`${x.diags.length} de ${x.totalGrupos} ${x.totalGrupos === 1 ? 'grupo' : 'grupos'} con diagnóstico`">{{ x.diags.length }}/{{ x.totalGrupos }} con diagnóstico</span>
                <button type="button" @click="irAEncuentro(x)"
                        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                  Ver encuentro
                </button>
                <div class="relative shrink-0" data-menu-raiz>
                  <button type="button" @click="menuDiag = menuDiag === claveDe(x) ? null : claveDe(x)"
                          :aria-expanded="menuDiag === claveDe(x)" aria-haspopup="menu" :aria-label="`Más acciones de «${tituloEncuentro(x)}»`"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde text-texto-secundario hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                  </button>
                  <div v-if="menuDiag === claveDe(x)" role="menu"
                       class="absolute bottom-full right-0 z-30 mb-1 w-[min(16rem,calc(100vw-3rem))] rounded-xl border border-borde bg-white py-1 shadow-lg">
                    <button type="button" role="menuitem" @click="menuDiag = null; descargarTodos(x)"
                            class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">
                      {{ diagsVisibles(x).length > 1 ? `Descargar los PDF (${diagsVisibles(x).length})` : 'Descargar PDF' }}
                    </button>
                    <button v-if="x.diags[0]?.proyecto?.uuid" type="button" role="menuitem" @click="menuDiag = null; irAProyecto(x)"
                            class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Ficha del proyecto</button>
                  </div>
                </div>
              </div>
            </li>
          </ul>

          <!-- Paginación -->
          <div v-if="!cargando && totalPaginas > 1" class="mt-5 flex flex-col flex-wrap items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-texto-secundario">Mostrando {{ itemsPagina.length }} de {{ filtrados.length }} encuentros</p>
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación de diagnósticos">
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

    <DiagnosticoModal :equipo="abierto?.equipo ?? null" :encuentro="abierto?.encuentro ?? null" @close="abierto = null" />
  </div>
</template>

<style scoped>
@reference "../style.css";

.chip-filtro { @apply inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200; }
</style>
