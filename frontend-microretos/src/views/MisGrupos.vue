<!-- Ruta: /mis-grupos (name: mis-grupos). Antes /mis-grupos, y antes /dashboard/mis-grupos — ver
     router/index.js. En las vistas del docente los equipos de alumnado se llaman "grupos" y la letra
     del encuentro (Encuentro.grupo) se muestra como "Clase"; los datos vienen de GET /encuentros/mis-grupos. -->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { getMisGrupos } from '../services/encuentroService.js'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'
import { formatCurso } from '../utils/formatCurso.js'
import TituloResultados from '../components/TituloResultados.vue'
import FamiliaModulos from '../components/FamiliaModulos.vue'
import RolGrupo from '../components/RolGrupo.vue'
import ChipDocente from '../components/ChipDocente.vue'
import { useCursoAcademicoStore, cursoDeEncuentro } from '../stores/cursoAcademico.js'
import SelectorCurso from '../components/SelectorCurso.vue'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import FiltrosPanel from '../components/FiltrosPanel.vue'
import FiltroSegmentado from '../components/FiltroSegmentado.vue'
import FiltroSelect from '../components/FiltroSelect.vue'
import { usePaginacion } from '../composables/usePaginacion.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

const router = useRouter()
const route  = useRoute()


const cargando = ref(true)
const error    = ref('')
const grupos   = ref([])

// Sets (no un único id) porque el filtro por estado puede necesitar desplegar
// varios encuentros/equipos a la vez, no solo el último que el usuario clicó.
const encuentrosAbiertos = ref(new Set())
const equiposAbiertos    = ref(new Set())

// ── Copiar código (acceso workspace / desbloqueo IA) ────────────────────────
const codigoCopiado = ref(null)
async function copiarCodigo(codigo) {
  try {
    await navigator.clipboard.writeText(codigo)
    codigoCopiado.value = codigo
    setTimeout(() => { if (codigoCopiado.value === codigo) codigoCopiado.value = null }, 2500)
  } catch { /* clipboard no disponible */ }
}


async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const res = await getMisGrupos()
    grupos.value = res.data
  } catch (e) {
    error.value = 'Error al cargar tus grupos.'
  } finally {
    cargando.value = false
  }
}

function toggleEncuentro(id) {
  if (encuentrosAbiertos.value.has(id)) encuentrosAbiertos.value.delete(id)
  else encuentrosAbiertos.value.add(id)
}

function toggleEquipo(id) {
  if (equiposAbiertos.value.has(id)) equiposAbiertos.value.delete(id)
  else equiposAbiertos.value.add(id)
}

function progresoPct(equipo) {
  return progresoPonderado(equipo.fases)
}

function formatoFecha(fecha) {
  if (!fecha) return ''
  return new Date(fecha).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Estado del chip de fase (color + texto) — misma jerarquía que MisGruposDetalle, pero
// aquí solo es un resumen: el detalle real vive en "Detalle equipos" (MisGruposDetalle.vue).
function estadoFase(equipo, faseNum) {
  if (equipo.fases[faseNum]?.validado_docente) return { label: 'Validado', cls: 'bg-emerald-500 text-white' }
  if (equipo.fases[faseNum]?.completada)       return { label: 'Completa', cls: 'bg-centros/20 text-centros' }
  if (equipo.fase_actual === faseNum)          return { label: 'En curso', cls: 'bg-blue-100 text-blue-600 ring-1 ring-blue-300' }
  return { label: 'Pendiente', cls: 'bg-gray-100 text-gray-400' }
}

// Estado global de un equipo — mismo criterio que los contadores de arriba.
function estadoEquipo(equipo) {
  if (equipo.fases_completas === 5) return 'completado'
  if (equipo.fase_actual === 0 && equipo.fases_completas === 0) return 'sin_iniciar'
  return 'en_curso'
}

// `borde`: franja lateral de la fila del equipo, para distinguir el estado de un vistazo
// antes de desplegarla.
function estadoBadge(equipo) {
  const estado = estadoEquipo(equipo)
  if (estado === 'completado')  return { label: '✓ Completado', cls: 'bg-emerald-100 text-emerald-700', borde: 'border-l-emerald-500' }
  if (estado === 'sin_iniciar') return { label: 'Sin iniciar', cls: 'bg-gray-100 text-gray-500', borde: 'border-l-gray-300' }
  const fa = equipo.fase_actual
  return { label: `En curso · F${fa} ${FASES_PROYECTO[fa]?.label ?? ''}`, cls: 'bg-blue-100 text-blue-700', borde: 'border-l-blue-500' }
}

// Resumen por encuentro para su cabecera (visible con el desplegable cerrado). Sobre
// equiposDeGrupo para que respete la pestaña de estado activa, igual que "N equipo(s)".
function resumenGrupo(g) {
  const eqs = equiposDeGrupo(g)
  return {
    completados:  eqs.filter(e => estadoEquipo(e) === 'completado').length,
    enCurso:      eqs.filter(e => estadoEquipo(e) === 'en_curso').length,
    sinIniciar:   eqs.filter(e => estadoEquipo(e) === 'sin_iniciar').length,
    diagnosticos: eqs.filter(e => e.diagnostico_final).length,
  }
}

// Lleva al detalle del encuentro directamente a la sección de diagnóstico de ese equipo
// (MisGruposDetalle lee ?equipo=&ver=diagnostico al cargar).
// ── Menú «⋯» de cada encuentro ────────────────────────────────────────────────
const menuGrupo = ref(null)   // id del encuentro con el menú abierto
const tituloEncuentro = (g) => g.encuentro.proyecto_titulo || g.equipos[0]?.proyecto?.titulo || (g.encuentro.grupo && `Clase ${g.encuentro.grupo}`) || 'Sin nombre'
const primerDiagnostico = (g) => equiposDeGrupo(g).find(e => e.diagnostico_final) || null
function desplegarTodo(g) {
  encuentrosAbiertos.value = new Set([...encuentrosAbiertos.value, g.encuentro.id])
  equiposAbiertos.value = new Set([...equiposAbiertos.value, ...equiposDeGrupo(g).map(e => e.id)])
}
// Cierre al pulsar fuera o con Escape (un overlay fixed quedaría por debajo del menú)
function cerrarMenuFuera(e) { if (!e.target.closest?.('[data-menu-raiz]')) menuGrupo.value = null }
function cerrarMenuEsc(e) { if (e.key === 'Escape') menuGrupo.value = null }
watch(menuGrupo, (abierto) => {
  const m = abierto ? 'addEventListener' : 'removeEventListener'
  document[m]('click', cerrarMenuFuera, true)
  document[m]('keydown', cerrarMenuEsc)
})
onUnmounted(() => {
  document.removeEventListener('click', cerrarMenuFuera, true)
  document.removeEventListener('keydown', cerrarMenuEsc)
})

function irADiagnostico(g, equipo) {
  router.push({ name: 'mis-grupos-detalle', params: { id: g.encuentro.id }, query: { equipo: equipo.id, ver: 'diagnostico' } })
}

// Curso académico compartido (store): por la fecha del encuentro de cada grupo
const cursoStore     = useCursoAcademicoStore()
const gruposCurso    = computed(() => grupos.value.filter(g => cursoStore.coincide(cursoDeEncuentro(g.encuentro))))
const cursosConDatos = computed(() => [...new Set(grupos.value.map(g => cursoDeEncuentro(g.encuentro)))])

// Un grupo (encuentro) se considera "completado" solo si TODOS sus equipos han
// terminado las 5 fases — con un solo equipo a medias, el grupo sigue "en progreso".
// Se usa para separar visualmente ambos bloques en la lista; el filtro Estado mira el
// estado de cada EQUIPO — un encuentro puede tener equipos completados y otros a medias.
function grupoCompletado(g) {
  return g.equipos.length > 0 && g.equipos.every(e => e.fases_completas === 5)
}

function equipoCumpleEstado(equipo) {
  return !filtroEstado.value || estadoEquipo(equipo) === filtroEstado.value
}

// Equipos de un grupo a mostrar: todos, salvo que haya un estado elegido, en cuyo caso
// solo los que lo cumplen (así "Completados" no lista también los que siguen a medias).
function equiposDeGrupo(g) {
  return filtroEstado.value ? g.equipos.filter(equipoCumpleEstado) : g.equipos
}

// ── Explorador (mismo esquema que Retos, Encuentros y Proyectos) ──────────────
// Sin capas de cards de familia y módulo: barra de filtros (buscador, curso académico,
// ciclo, módulo, nivel, «Más filtros») + lateral «Filtros rápidos» (estado de los grupos,
// familia, seguimiento y de quién es) + lista de encuentros con paginación.
const SIN_FAMILIA = '__sin_familia__'
const familiaDe = (g) => g.encuentro.familia_nombre || g.equipos.find(e => e.proyecto?.familia)?.proyecto.familia || SIN_FAMILIA
const etiquetaFamilia = (f) => (f === SIN_FAMILIA ? 'Sin familia asignada' : f)
const modulosDe = (g) => g.encuentro.modulos ?? []

const aLista = v => (v == null ? [] : (Array.isArray(v) ? v : [v]).map(String).filter(Boolean))
const busqueda        = ref('')
const filtroFamilias  = ref(aLista(route.query.familia))
const filtroCiclo     = ref('')
const filtroModulo    = ref(route.query.modulo && !['Multimódulo', 'Sin módulo'].includes(String(route.query.modulo)) ? String(route.query.modulo) : '')
const filtroCurso     = ref('')
const filtroEstado    = ref('') // '' | 'en_curso' | 'sin_iniciar' | 'completado' (estado de cada grupo)
const filtroSeguimiento = ref([]) // 'diagnostico' | 'sin_empezar'
const filtroPropiedad = ref('')   // '' | 'mios' | 'compartidos'
const orden           = ref('recientes') // 'recientes' | 'antiguos' | 'avance'
const familiasExpandidas = ref(false)
// «Filtros rápidos»: visible de entrada en escritorio y plegado en móvil; «Más filtros» lo alterna.
const panelFiltrosAbierto = ref(typeof window === 'undefined' || window.innerWidth >= 1024)

// La familia vive en la URL (?familia=…, varias): al volver del detalle sigue marcada.
// Los enlaces antiguos con ?modulo= se convierten en el filtro Módulo y se limpian.
watch(() => route.query.familia, (v) => {
  const lista = aLista(v)
  if (lista.join('|') !== filtroFamilias.value.join('|')) filtroFamilias.value = lista
})
watch(filtroFamilias, (lista) => {
  if (aLista(route.query.familia).join('|') === lista.join('|') && !route.query.modulo) return
  router.replace({ query: { ...route.query, familia: lista.length ? lista : undefined, modulo: undefined } })
})
if (route.query.modulo) router.replace({ query: { ...route.query, modulo: undefined } })
// Dependencias: el módulo y el nivel dependen del ciclo; el ciclo, de la familia.
watch(filtroFamilias, () => { filtroCiclo.value = ''; filtroModulo.value = ''; filtroCurso.value = '' })
watch(filtroCiclo, () => { filtroModulo.value = ''; filtroCurso.value = '' })

const coincideFamilias = g => !filtroFamilias.value.length || filtroFamilias.value.includes(familiaDe(g))
const tieneDiagnostico = g => g.equipos.some(e => e.diagnostico_final)
const tieneSinEmpezar  = g => g.equipos.some(e => estadoEquipo(e) === 'sin_iniciar')
const esMio            = g => g.encuentro.es_propietario !== false

function coincideBusqueda(g) {
  const q = busqueda.value.toLowerCase().trim()
  if (!q) return true
  const enTexto = [g.encuentro.grupo && `clase ${g.encuentro.grupo}`, g.encuentro.ciclo_formativo, g.encuentro.proyecto_titulo, ...modulosDe(g)]
    .filter(Boolean).some(t => t.toLowerCase().includes(q))
  const enEquipos = g.equipos.some(e =>
    [nombreGrupo(e), e.proyecto?.titulo, e.proyecto?.familia].filter(Boolean).some(t => t.toLowerCase().includes(q)))
  return enTexto || enEquipos
}

// Filtros de la barra + familia (comunes); el lateral añade estado, seguimiento y propiedad.
// Cada contador del lateral se calcula sin su propio grupo, como en las otras bibliotecas.
function aplicar(lista, { sinEstado = false, sinSeguimiento = false, sinPropiedad = false } = {}) {
  return lista.filter(g => {
    if (!coincideFamilias(g)) return false
    if (filtroCiclo.value && g.encuentro.ciclo_formativo !== filtroCiclo.value) return false
    if (filtroModulo.value && !modulosDe(g).includes(filtroModulo.value)) return false
    if (filtroCurso.value && g.encuentro.curso !== filtroCurso.value) return false
    if (!sinEstado && filtroEstado.value && !g.equipos.some(equipoCumpleEstado)) return false
    if (!sinSeguimiento && filtroSeguimiento.value.includes('diagnostico') && !tieneDiagnostico(g)) return false
    if (!sinSeguimiento && filtroSeguimiento.value.includes('sin_empezar') && !tieneSinEmpezar(g)) return false
    if (!sinPropiedad && filtroPropiedad.value && (filtroPropiedad.value === 'mios') !== esMio(g)) return false
    return coincideBusqueda(g)
  })
}
const gruposFiltrados = computed(() => aplicar(gruposCurso.value))

// Opciones de la barra (dependientes: familia → ciclo → módulo/nivel)
const baseFamilia = computed(() => gruposCurso.value.filter(coincideFamilias))
const ciclosDisponibles = computed(() =>
  [...new Set(baseFamilia.value.map(g => g.encuentro.ciclo_formativo).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const baseCiclo = computed(() => filtroCiclo.value ? baseFamilia.value.filter(g => g.encuentro.ciclo_formativo === filtroCiclo.value) : baseFamilia.value)
const modulosDisponibles = computed(() =>
  [...new Set(baseCiclo.value.flatMap(modulosDe).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const cursosDisponibles = computed(() =>
  [...new Set(baseCiclo.value.map(g => g.encuentro.curso).filter(Boolean))].sort())
const opcionesNivel = computed(() => [
  { value: '', label: 'Todos' },
  ...cursosDisponibles.value.map(c => ({ value: c, label: formatCurso(c) })),
])

// Lateral: contadores
const ESTADOS = [
  { value: '',            label: 'Todos',       punto: 'bg-gray-300' },
  { value: 'en_curso',    label: 'En curso',    punto: 'bg-centros' },
  { value: 'sin_iniciar', label: 'Sin iniciar', punto: 'bg-amber-500' },
  { value: 'completado',  label: 'Completados', punto: 'bg-empresas' },
]
const conteoEstados = computed(() => {
  const base = aplicar(gruposCurso.value, { sinEstado: true }).flatMap(g => g.equipos)
  return {
    '': base.length,
    en_curso: base.filter(e => estadoEquipo(e) === 'en_curso').length,
    sin_iniciar: base.filter(e => estadoEquipo(e) === 'sin_iniciar').length,
    completado: base.filter(e => estadoEquipo(e) === 'completado').length,
  }
})
const conteoSeguimiento = computed(() => {
  const base = aplicar(gruposCurso.value, { sinSeguimiento: true })
  return { diagnostico: base.filter(tieneDiagnostico).length, sin_empezar: base.filter(tieneSinEmpezar).length }
})
const conteoPropiedad = computed(() => {
  const base = aplicar(gruposCurso.value, { sinPropiedad: true })
  return { '': base.length, mios: base.filter(esMio).length, compartidos: base.filter(g => !esMio(g)).length }
})
const familiasPanel = computed(() => {
  const mapa = new Map()
  gruposCurso.value.forEach(g => { const k = familiaDe(g); mapa.set(k, (mapa.get(k) || 0) + 1) })
  return [...mapa.entries()].map(([nombre, n]) => ({ nombre, n, label: etiquetaFamilia(nombre) }))
    .sort((a, b) => (a.nombre === SIN_FAMILIA) - (b.nombre === SIN_FAMILIA) || b.n - a.n || a.label.localeCompare(b.label, 'es'))
})
const familiasPanelVisibles = computed(() => {
  const todas = familiasPanel.value
  if (familiasExpandidas.value || todas.length <= 6) return todas
  return [...todas.slice(0, 6), ...todas.slice(6).filter(f => filtroFamilias.value.includes(f.nombre))]
})
const hayPropios = computed(() => conteoPropiedad.value.compartidos > 0)

// (En la plantilla los refs llegan desenvueltos: por eso una función por filtro.)
const alternar = (lista, valor) => lista.includes(valor) ? lista.filter(v => v !== valor) : [...lista, valor]
function alternarFamilia(nombre) { filtroFamilias.value = alternar(filtroFamilias.value, nombre) }
function alternarSeguimiento(clave) { filtroSeguimiento.value = alternar(filtroSeguimiento.value, clave) }
const ETIQUETA_SEGUIMIENTO = { diagnostico: 'Con diagnóstico listo', sin_empezar: 'Con grupos sin empezar' }

const filtrosLateral = computed(() => filtroFamilias.value.length + filtroSeguimiento.value.length + (filtroEstado.value ? 1 : 0) + (filtroPropiedad.value ? 1 : 0))
const hayFiltrosActivos = computed(() => !!(busqueda.value.trim() || filtroCiclo.value || filtroModulo.value || filtroCurso.value || filtrosLateral.value))
function limpiarFiltros() {
  busqueda.value = ''
  filtroFamilias.value = []
  filtroCiclo.value = ''
  filtroModulo.value = ''
  filtroCurso.value = ''
  filtroEstado.value = ''
  filtroSeguimiento.value = []
  filtroPropiedad.value = ''
}

// Orden + separación en dos bloques (en progreso / completados), y paginación de 10.
const avanceMedio = g => g.equipos.length ? g.equipos.reduce((t, e) => t + progresoPct(e), 0) / g.equipos.length : 0
const fechaGrupo  = g => new Date(g.encuentro.fecha || 0).getTime()
const gruposOrdenados = computed(() => {
  const lista = [...gruposFiltrados.value].sort((a, b) =>
    orden.value === 'avance' ? avanceMedio(b) - avanceMedio(a)
    : orden.value === 'antiguos' ? fechaGrupo(a) - fechaGrupo(b)
    : fechaGrupo(b) - fechaGrupo(a))
  // Con un estado elegido no se separa: la lista ya es de un solo tipo
  return filtroEstado.value ? lista : [...lista.filter(g => !grupoCompletado(g)), ...lista.filter(grupoCompletado)]
})
const numEnProgreso  = computed(() => gruposFiltrados.value.filter(g => !grupoCompletado(g)).length)
const numCompletados = computed(() => gruposFiltrados.value.length - numEnProgreso.value)
const POR_PAGINA = 10
const { pagina, totalPaginas, itemsPagina: gruposPagina } = usePaginacion(gruposOrdenados, POR_PAGINA)
watch([gruposFiltrados, orden], () => { pagina.value = 0 })
const paginasVisibles = computed(() => {
  const n = totalPaginas.value, a = pagina.value
  const ini = Math.max(0, Math.min(a - 2, n - 5)), fin = Math.min(n, ini + 5)
  return Array.from({ length: fin - ini }, (_, i) => ini + i)
})
function irAPagina(i) {
  pagina.value = i
  document.getElementById('resultados-grupos')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
// Cabecera de bloque ("En progreso" / "Completados") antes del primer encuentro de cada tipo en la página
const muestraCabeceraBloque = (idx) => !filtroEstado.value
  && (idx === 0 || grupoCompletado(gruposPagina.value[idx - 1]) !== grupoCompletado(gruposPagina.value[idx]))

// Título de los resultados: los niveles elegidos, de general a concreto (una familia → ciclo →
// módulo). El último es el título; los anteriores van encima como migas, cada uno con su icono
// y su etiqueta, para que no parezcan parte del título. `subir` vuelve a ese nivel (quita los de
// debajo) y `quitar` borra solo el último.
const nivelesTitulo = computed(() => {
  const niveles = []
  if (filtroFamilias.value.length === 1) {
    niveles.push({ etiqueta: 'Familia profesional', valor: etiquetaFamilia(filtroFamilias.value[0]),
      subir: () => { filtroCiclo.value = ''; filtroModulo.value = '' }, quitar: () => { filtroFamilias.value = [] } })
  }
  if (filtroCiclo.value) {
    niveles.push({ etiqueta: 'Ciclo formativo', valor: filtroCiclo.value,
      subir: () => { filtroModulo.value = '' }, quitar: () => { filtroCiclo.value = '' } })
  }
  if (filtroModulo.value) {
    niveles.push({ etiqueta: 'Módulo', valor: filtroModulo.value, subir: () => {}, quitar: () => { filtroModulo.value = '' } })
  }
  return niveles
})

// Equipos visibles (para el resumen de resultados)
const totalEquipos = computed(() => gruposFiltrados.value.flatMap(equiposDeGrupo).length)

// Al elegir un estado, desplegar automáticamente los encuentros y equipos que lo cumplen —
// si no, habría que abrir cada acordeón a mano para ver cuál de sus equipos lo cumple.
watch([filtroEstado, gruposFiltrados], ([estado, gruposVisibles]) => {
  if (!estado) {
    encuentrosAbiertos.value = new Set()
    equiposAbiertos.value = new Set()
    return
  }
  const encIds = new Set()
  const eqIds  = new Set()
  gruposVisibles.forEach(g => {
    const coinciden = g.equipos.filter(equipoCumpleEstado)
    if (coinciden.length) {
      encIds.add(g.encuentro.id)
      coinciden.forEach(e => eqIds.add(e.id))
    }
  })
  encuentrosAbiertos.value = encIds
  equiposAbiertos.value = eqIds
})

onMounted(cargar)
</script>

<template>
  <!-- Explorador de grupos (mismo esquema que Retos, Encuentros y Proyectos): cabecera + filtros +
       «Filtros rápidos» a la izquierda + encuentros desplegables con sus grupos y paginación. -->
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Seguimiento de" destacado="grupos"
                       subtitulo="Revisa el avance de todos tus grupos activos, encuentro a encuentro: en qué fase está cada uno, lo que entrega y su diagnóstico final." />

      <!-- Filtros principales (FiltrosPanel común) -->
      <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar por proyecto, clase, ciclo, módulo o grupo…">
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
          title="Filtra por módulo profesional. Los encuentros de varios módulos aparecen en cada uno." />
        <!-- Nivel: Encuentro.curso es texto libre; con pocos valores (lo normal: 1º/2º) segmentado -->
        <template v-if="cursosDisponibles.length">
          <FiltroSegmentado v-if="cursosDisponibles.length <= 4" v-model="filtroCurso" label="Nivel" :opciones="opcionesNivel" />
          <FiltroSelect v-else v-model="filtroCurso" label="Nivel" todos="Todos los niveles" :opciones="opcionesNivel.slice(1)" />
        </template>
      </FiltrosPanel>

      <!-- Error de carga -->
      <div v-if="error && !cargando" class="flex flex-col items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center" role="alert">
        <p class="min-w-0 flex-1 text-sm font-medium text-red-700">{{ error }}</p>
        <button type="button" @click="cargar" class="inline-flex h-10 items-center rounded-full bg-white px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Reintentar</button>
      </div>

      <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        <!-- ═══ Filtros rápidos (panel lateral; en < lg se abre con «Más filtros») ═══ -->
        <aside id="filtros-rapidos" aria-label="Filtros rápidos"
               class="w-full shrink-0 rounded-2xl border border-borde bg-white p-4 shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:w-64 xl:w-72"
               :class="panelFiltrosAbierto ? 'block' : 'hidden'">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-azul-noche">Filtros rápidos</h2>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros"
                    class="inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">Limpiar</button>
          </div>

          <!-- Estado de cada grupo de alumnado (cuenta grupos, no encuentros) -->
          <div class="border-t border-borde pt-3" role="radiogroup" aria-labelledby="titulo-filtro-estado">
            <p id="titulo-filtro-estado" class="mb-1.5 text-sm font-semibold text-azul-noche">Estado de los grupos</p>
            <label v-for="e in ESTADOS" :key="e.value || 'todos'"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="radio" name="filtro-estado" :value="e.value" v-model="filtroEstado" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="e.punto" aria-hidden="true" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ e.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoEstados[e.value] }}</span>
            </label>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-seguimiento">
            <p id="titulo-filtro-seguimiento" class="mb-1.5 text-sm font-semibold text-azul-noche">Seguimiento</p>
            <label v-for="k in ['diagnostico', 'sin_empezar']" :key="k"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroSeguimiento.includes(k)" @change="alternarSeguimiento(k)" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ ETIQUETA_SEGUIMIENTO[k] }}</span>
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
            <p v-if="!familiasPanel.length && !cargando" class="px-1 py-1 text-sm text-texto-secundario">Sin encuentros en este curso.</p>
            <button v-if="familiasPanel.length > 6" type="button" @click="familiasExpandidas = !familiasExpandidas"
                    class="mt-1 inline-flex h-9 items-center rounded-full px-1 text-sm font-semibold text-rol-fuerte hover:underline">
              {{ familiasExpandidas ? 'Ver menos' : `Ver todas (${familiasPanel.length})` }}
            </button>
          </div>

          <!-- De quién es: solo si hay encuentros de compañeros que me han invitado -->
          <div v-if="hayPropios" class="mt-3 border-t border-borde pt-3" role="radiogroup" aria-labelledby="titulo-filtro-propiedad">
            <p id="titulo-filtro-propiedad" class="mb-1.5 text-sm font-semibold text-azul-noche">De quién es</p>
            <label v-for="o in [{ v: '', label: 'Todos' }, { v: 'mios', label: 'Míos' }, { v: 'compartidos', label: 'Compartidos conmigo' }]" :key="o.v || 'todos'"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="radio" name="filtro-propiedad" :value="o.v" v-model="filtroPropiedad" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ o.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoPropiedad[o.v] }}</span>
            </label>
          </div>
        </aside>

        <!-- ═══ Resultados ═══ -->
        <section id="resultados-grupos" class="min-w-0 flex-1 scroll-mt-24 space-y-3" aria-label="Encuentros con grupos">
          <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <!-- Título de los resultados con los niveles elegidos (TituloResultados, común a las bibliotecas) -->
            <TituloResultados :niveles="nivelesTitulo" :cargando="cargando">
              <template v-if="cargando">Cargando grupos…</template>
              <template v-else>
                {{ gruposFiltrados.length }} {{ gruposFiltrados.length === 1 ? 'encuentro' : 'encuentros' }}
                <span class="font-normal text-texto-secundario">· {{ totalEquipos }} {{ totalEquipos === 1 ? 'grupo' : 'grupos' }}</span>
              </template>
            </TituloResultados>
            <!-- Chips de filtros activos del lateral -->
            <div v-if="filtrosLateral && !(nivelesTitulo[0]?.etiqueta === 'Familia profesional' && filtrosLateral === 1)" class="flex w-full flex-wrap gap-1.5 sm:order-last">
              <button v-if="filtroEstado" type="button" @click="filtroEstado = ''"
                      class="inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200"
                      :aria-label="`Quitar filtro ${ESTADOS.find(e => e.value === filtroEstado)?.label}`">
                <span class="h-2 w-2 rounded-full" :class="ESTADOS.find(e => e.value === filtroEstado)?.punto" aria-hidden="true" />
                {{ ESTADOS.find(e => e.value === filtroEstado)?.label }}<span aria-hidden="true">✕</span>
              </button>
              <button v-for="k in filtroSeguimiento" :key="'s-' + k" type="button" @click="alternarSeguimiento(k)"
                      class="inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200"
                      :aria-label="`Quitar filtro ${ETIQUETA_SEGUIMIENTO[k]}`">
                {{ ETIQUETA_SEGUIMIENTO[k] }}<span aria-hidden="true">✕</span>
              </button>
              <button v-if="filtroPropiedad" type="button" @click="filtroPropiedad = ''"
                      class="inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200"
                      aria-label="Quitar filtro de quién es">
                {{ filtroPropiedad === 'mios' ? 'Míos' : 'Compartidos conmigo' }}<span aria-hidden="true">✕</span>
              </button>
              <!-- Con una sola familia ya está en el título de los resultados: sin chip repetido -->
              <button v-for="f in (nivelesTitulo[0]?.etiqueta === 'Familia profesional' ? [] : filtroFamilias)" :key="'f-' + f" type="button" @click="alternarFamilia(f)"
                      class="inline-flex h-9 max-w-full items-center gap-1.5 rounded-full bg-rol/10 px-3 text-xs font-semibold text-rol-fuerte hover:bg-rol/15"
                      :aria-label="`Quitar filtro ${etiquetaFamilia(f)}`">
                <span class="truncate">{{ etiquetaFamilia(f) }}</span><span aria-hidden="true">✕</span>
              </button>
            </div>
            <label class="flex items-center gap-2 text-sm text-texto-secundario">
              <span class="hidden sm:inline">Ordenar por</span>
              <select v-model="orden" class="h-9 rounded-xl px-3 text-sm text-azul-noche" aria-label="Ordenar encuentros">
                <option value="recientes">Más recientes</option>
                <option value="antiguos">Más antiguos</option>
                <option value="avance">Más avance</option>
              </select>
            </label>
          </div>

          <!-- Carga -->
          <div v-if="cargando" class="space-y-3" aria-busy="true">
            <div v-for="n in 4" :key="n" class="animate-pulse space-y-2 rounded-2xl border border-borde bg-white p-5">
              <div class="h-4 w-1/2 rounded-full bg-gray-200" /><div class="h-3 w-1/3 rounded-full bg-gray-200" /><div class="h-3 w-2/3 rounded-full bg-gray-100" />
            </div>
            <span class="sr-only">Cargando grupos…</span>
          </div>

          <!-- Vacíos -->
          <div v-else-if="!error && !grupos.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <h3 class="text-lg font-bold text-azul-noche">Todavía no tienes encuentros con grupos</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">Cuando registres un encuentro y crees sus grupos, aquí verás su avance.</p>
          </div>
          <div v-else-if="!error && !gruposFiltrados.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <h3 class="text-lg font-bold text-azul-noche">Ningún encuentro coincide con estos filtros</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">Prueba a quitar algún filtro.</p>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros" class="accion-secundaria mt-4">Limpiar filtros</button>
          </div>

        <template v-for="(g, idx) in (cargando ? [] : gruposPagina)" :key="g.encuentro.id">
          <!-- Cabecera de sección — separación explícita entre "en progreso" y "completados",
               no solo un contador arriba. Se pinta una sola vez, al cambiar de grupo. -->
          <h3 v-if="muestraCabeceraBloque(idx)" class="flex items-center gap-2 pt-2 text-sm font-semibold text-azul-noche">
            <span class="h-2.5 w-2.5 rounded-full" :class="grupoCompletado(g) ? 'bg-empresas' : 'bg-centros'" aria-hidden="true" />
            {{ grupoCompletado(g) ? `Completados (${numCompletados})` : `En progreso (${numEnProgreso})` }}
          </h3>

          <!-- Sin overflow-hidden: el menú «⋯» no debe recortarse. Con el menú abierto, por encima
               de la tarjeta siguiente. -->
          <div class="relative rounded-2xl border border-borde bg-white shadow-sm" :class="{ 'z-10': menuGrupo === g.encuentro.id }">

          <div class="px-4 pb-3 pt-4 sm:px-5">
            <!-- La información también despliega los grupos (atajo del ratón; el botón accesible está en el pie) -->
            <div class="min-w-0 cursor-pointer" @click="toggleEncuentro(g.encuentro.id)">
              <p class="font-heading text-base font-bold leading-snug text-azul-noche">
                {{ g.encuentro.proyecto_titulo || g.equipos[0]?.proyecto?.titulo || (g.encuentro.grupo && `Clase ${g.encuentro.grupo}`) || 'Sin nombre' }}
                <span v-if="g.encuentro.fecha" class="font-sans text-sm font-medium text-texto-secundario">· {{ formatoFecha(g.encuentro.fecha) }}</span>
              </p>
              <p class="mt-0.5 text-sm text-texto-secundario">{{ g.encuentro.ciclo_formativo }} · {{ equiposDeGrupo(g).length }} {{ equiposDeGrupo(g).length === 1 ? 'grupo' : 'grupos' }}<template v-if="g.encuentro.grupo"> · Clase {{ g.encuentro.grupo }}</template></p>
              <!-- Familia y módulos diferenciados, con «+N» desplegable (FamiliaModulos, común a las bibliotecas) -->
              <FamiliaModulos class="mt-2" :familia="etiquetaFamilia(familiaDe(g))" :modulos="modulosDe(g)" :modulo-activo="filtroModulo" />
              <!-- Estado de sus equipos sin tener que desplegar -->
              <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span v-if="resumenGrupo(g).completados"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-empresas/10 text-empresas-dark text-xs font-semibold">
                  ✓ {{ resumenGrupo(g).completados }} completado{{ resumenGrupo(g).completados === 1 ? '' : 's' }}
                </span>
                <span v-if="resumenGrupo(g).enCurso"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-centros/10 text-centros text-xs font-semibold">
                  <span class="w-1.5 h-1.5 rounded-full bg-centros"></span>
                  {{ resumenGrupo(g).enCurso }} en curso
                </span>
                <span v-if="resumenGrupo(g).sinIniciar"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-texto-secundario text-xs font-semibold">
                  {{ resumenGrupo(g).sinIniciar }} sin iniciar
                </span>
                <span v-if="resumenGrupo(g).diagnosticos"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white border border-empresas/40 text-empresas-dark text-xs font-semibold">
                  📊 {{ resumenGrupo(g).diagnosticos }} diagnóstico{{ resumenGrupo(g).diagnosticos === 1 ? '' : 's' }} disponible{{ resumenGrupo(g).diagnosticos === 1 ? '' : 's' }}
                </span>
              </div>
              <!-- Encuentro de un compañero que me ha dado permiso como colaborador -->
              <ChipDocente v-if="g.encuentro.es_propietario === false && g.encuentro.propietario_nombre" class="mt-1.5"
                           :nombre="g.encuentro.propietario_nombre" :solo-lectura="!g.encuentro.puede_editar" />
            </div>
          </div>

          <!-- Pie de la tarjeta: desplegar los grupos a la izquierda; «Detalle de grupos» y «⋯» a la derecha -->
          <div class="flex flex-wrap items-center gap-2 border-t border-borde px-4 py-2.5 sm:px-5">
            <button type="button" @click="toggleEncuentro(g.encuentro.id)" :aria-expanded="encuentrosAbiertos.has(g.encuentro.id)"
                    class="inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-azul-noche hover:bg-gray-100">
              <svg :class="['h-4 w-4 text-texto-secundario transition-transform', encuentrosAbiertos.has(g.encuentro.id) ? 'rotate-180' : '']"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
              {{ encuentrosAbiertos.has(g.encuentro.id) ? 'Ocultar los grupos' : `Ver los grupos (${equiposDeGrupo(g).length})` }}
            </button>
            <div class="ml-auto flex items-center gap-2">
              <!-- Acceso principal al trabajo real de los grupos: a la derecha del pie, como el
                   botón principal de las tarjetas de las otras bibliotecas -->
              <button type="button" @click="router.push({ name: 'mis-grupos-detalle', params: { id: g.encuentro.id } })"
                      class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-rol-fuerte px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rol-fuerte/90">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Detalle de grupos
              </button>
              <!-- Menú «⋯»: el resto de acciones del encuentro -->
              <div class="relative shrink-0" data-menu-raiz>
                <button type="button" @click="menuGrupo = menuGrupo === g.encuentro.id ? null : g.encuentro.id"
                        :aria-expanded="menuGrupo === g.encuentro.id" aria-haspopup="menu"
                        :aria-label="`Más acciones de «${tituloEncuentro(g)}»`"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde text-texto-secundario transition-colors hover:bg-gray-50"
                        :class="{ 'bg-gray-50': menuGrupo === g.encuentro.id }">
                  <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                </button>
                <div v-if="menuGrupo === g.encuentro.id" role="menu"
                     class="absolute right-0 top-full z-30 mt-1 w-[min(17rem,calc(100vw-3rem))] rounded-xl border border-borde bg-white py-1 shadow-lg">
                  <button type="button" role="menuitem" @click="menuGrupo = null; toggleEncuentro(g.encuentro.id)"
                          class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">
                    {{ encuentrosAbiertos.has(g.encuentro.id) ? 'Ocultar los grupos' : `Mostrar los grupos (${equiposDeGrupo(g).length})` }}
                  </button>
                  <button v-if="equiposDeGrupo(g).length > 1" type="button" role="menuitem" @click="menuGrupo = null; desplegarTodo(g)"
                          class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Desplegar el resumen de todos</button>
                  <button v-if="primerDiagnostico(g)" type="button" role="menuitem" @click="menuGrupo = null; irADiagnostico(g, primerDiagnostico(g))"
                          class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Ver diagnósticos ({{ resumenGrupo(g).diagnosticos }})</button>
                  <template v-if="g.encuentro.codigo_clase || g.encuentro.codigo_ia">
                    <div class="my-1 border-t border-borde" role="separator" />
                    <button v-if="g.encuentro.codigo_clase" type="button" role="menuitem" @click="menuGrupo = null; copiarCodigo(g.encuentro.codigo_clase)"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">
                      <span class="min-w-0 flex-1">Copiar código de acceso al alumnado</span>
                      <span class="shrink-0 text-xs font-bold tracking-wider text-texto-secundario">{{ g.encuentro.codigo_clase }}</span>
                    </button>
                    <button v-if="g.encuentro.codigo_ia" type="button" role="menuitem" @click="menuGrupo = null; copiarCodigo(g.encuentro.codigo_ia)"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">
                      <span class="min-w-0 flex-1">Copiar código sugerencia IA</span>
                      <span class="shrink-0 text-xs font-bold tracking-wider text-alumnos-dark">{{ g.encuentro.codigo_ia }}</span>
                    </button>
                  </template>
                </div>
              </div>
            </div>
          </div>

          <div v-if="encuentrosAbiertos.has(g.encuentro.id)" class="border-t border-borde px-5 py-4 space-y-3">
            <div v-for="equipo in equiposDeGrupo(g)" :key="equipo.id"
                 :class="['rounded-xl border border-borde border-l-4 overflow-hidden', estadoBadge(equipo).borde]">

              <button @click="toggleEquipo(equipo.id)"
                      class="w-full px-4 py-3 flex items-center gap-3 hover:bg-gray-50 transition-colors text-left">
                <div class="shrink-0 w-10 h-10 relative">
                  <svg class="w-10 h-10 -rotate-90" viewBox="0 0 48 48">
                    <circle cx="24" cy="24" r="20" fill="none" stroke="#F3F4F6" stroke-width="4"/>
                    <circle cx="24" cy="24" r="20" fill="none" stroke="#3072AA" stroke-width="4"
                            :stroke-dasharray="`${progresoPct(equipo) * 1.257} 125.7`" stroke-linecap="round"/>
                  </svg>
                  <span class="absolute inset-0 flex items-center justify-center text-[11px] font-semibold text-centros">
                    {{ progresoPct(equipo) }}%
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <p class="font-bold text-sm text-azul-noche">{{ nombreGrupo(equipo) }}</p>
                    <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', estadoBadge(equipo).cls]">
                      {{ estadoBadge(equipo).label }}
                    </span>
                    <span v-if="equipo.fases_completas === 5"
                          :class="['px-2 py-0.5 rounded-full text-xs font-semibold',
                                   equipo.diagnostico_final ? 'bg-white border border-empresas/40 text-empresas-dark' : 'bg-amber-100 text-amber-700']">
                      {{ equipo.diagnostico_final ? '📊 Diagnóstico listo' : 'Diagnóstico pendiente' }}
                    </span>
                  </div>
                  <p v-if="equipo.proyecto" class="text-[11px] font-semibold text-texto-secundario truncate">
                    {{ equipo.proyecto.titulo }}
                    <span v-if="equipo.proyecto.familia" class="text-texto-secundario">· {{ equipo.proyecto.familia }}</span>
                  </p>
                  <div class="flex flex-wrap gap-1 mt-1">
                    <!-- Dentro del botón de la fila no cabe otro botón: el rol, sin «i» (explicación en el title) -->
                    <span v-for="m in equipo.miembros" :key="m.id"
                          class="inline-flex max-w-full items-center gap-1 rounded-full bg-gray-100 py-0.5 pl-2 pr-0.5 text-xs font-semibold text-azul-noche">
                      {{ m.alias }}
                      <RolGrupo v-if="m.rol" :rol="m.rol" :ayuda="false" />
                    </span>
                  </div>
                </div>
                <svg :class="['w-3.5 h-3.5 text-texto-secundario shrink-0 transition-transform', equiposAbiertos.has(equipo.id) ? 'rotate-180' : '']"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>

              <div v-if="equiposAbiertos.has(equipo.id)" class="px-4 pb-4 border-t border-borde pt-3 space-y-3">
                <!-- Aviso destacado de diagnóstico — lo primero del desplegable cuando el equipo
                     ha terminado, con acceso directo a su sección en el detalle del encuentro. -->
                <div v-if="equipo.fases_completas === 5"
                     class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 rounded-2xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50 to-white">
                  <div class="flex items-start gap-3 flex-1 min-w-0">
                    <span class="shrink-0 w-9 h-9 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-base">📊</span>
                    <div class="min-w-0">
                      <p class="text-sm font-black text-emerald-800">Aquí puedes ver el diagnóstico del grupo</p>
                      <p class="text-[11px] text-emerald-700/80 leading-snug">
                        {{ equipo.diagnostico_final
                            ? 'La IA ya lo ha redactado a partir de lo trabajado. Puedes revisarlo, editarlo o descargarlo en PDF.'
                            : 'El grupo ha completado las 5 fases. Genera su diagnóstico final con IA a partir de lo trabajado.' }}
                      </p>
                    </div>
                  </div>
                  <button @click="irADiagnostico(g, equipo)"
                          :class="['shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition-colors',
                                   equipo.diagnostico_final
                                     ? 'bg-emerald-500 text-white hover:bg-emerald-600 shadow-md shadow-emerald-200'
                                     : 'bg-white border border-empresas/40 text-empresas-dark hover:bg-emerald-50']">
                    {{ equipo.diagnostico_final ? 'Ver diagnóstico' : 'Ir a generar diagnóstico' }} →
                  </button>
                </div>

                <!-- Deja claro que esto es solo un resumen de estado, no el contenido de las
                     fases — para eso hay que entrar en "Ver detalle de equipos". -->
                <div class="flex items-start gap-2 px-3 py-2 rounded-xl bg-blue-50 border border-blue-100">
                  <span class="text-sm leading-none shrink-0">ℹ️</span>
                  <p class="text-[11px] text-blue-700 leading-snug">
                    <span class="font-black">Resumen.</span> Haz clic en "Ver detalle de grupos" para más información.
                  </p>
                </div>

                <!-- Resumen de fases — solo estado, sin contenido: el detalle vive en "Detalle equipos" -->
                <div class="flex flex-wrap gap-1.5">
                  <div v-for="f in FASES_PROYECTO" :key="f.num" :title="f.label"
                       :class="['flex items-center gap-1.5 px-2 py-1 rounded-full text-[11px] font-semibold', estadoFase(equipo, f.num).cls]">
                    <span class="text-xs leading-none">{{ f.icono }}</span>
                    <span class="uppercase tracking-wider">F{{ f.num }}</span>
                    <span class="normal-case font-bold">· {{ estadoFase(equipo, f.num).label }}</span>
                  </div>
                </div>

                <div v-if="equipo.codigo_acceso" class="flex flex-wrap items-center gap-1.5">
                  <span class="text-[11px] font-semibold uppercase tracking-widest text-texto-secundario">Código de acceso del grupo</span>
                  <!-- Botón con zona táctil de 36px; la píldora visible conserva su tamaño -->
                  <button type="button" @click.stop="copiarCodigo(equipo.codigo_acceso)"
                          :title="codigoCopiado === equipo.codigo_acceso ? '¡Copiado!' : 'Copiar código de acceso del grupo'"
                          :aria-label="codigoCopiado === equipo.codigo_acceso ? '¡Copiado!' : `Copiar código de acceso del grupo ${equipo.codigo_acceso}`"
                          class="inline-flex min-h-9 items-center rounded-full cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-centros/40">
                    <span class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-centros/10 border border-centros/20">
                      <span class="w-1 h-1 rounded-full bg-centros shrink-0"></span>
                      <span class="text-xs font-semibold tracking-wider text-centros">{{ equipo.codigo_acceso }}</span>
                    </span>
                  </button>
                </div>

                <p v-if="equipo.reflexiones.length" class="text-[11px] font-semibold uppercase tracking-widest text-texto-secundario">
                  {{ equipo.reflexiones.length }} reflexión(es) registrada(s)
                </p>

                <button @click="router.push({ name: 'mis-grupos-detalle', params: { id: g.encuentro.id } })"
                        class="w-full inline-flex items-center justify-center gap-1.5 py-3 rounded-full bg-centros text-white shadow-md shadow-centros/20
                               hover:bg-centros/90 hover:shadow-lg transition-all text-xs font-black uppercase tracking-wider">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  Ver detalle de grupos →
                </button>
              </div>
            </div>
          </div>
          </div>
        </template>

          <!-- Paginación -->
          <div v-if="!cargando && totalPaginas > 1" class="flex flex-col flex-wrap items-center justify-between gap-3 pt-2 sm:flex-row">
            <p class="text-sm text-texto-secundario">Mostrando {{ gruposPagina.length }} de {{ gruposFiltrados.length }} encuentros</p>
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación de encuentros">
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

    <!-- Aviso al copiar un código -->
    <Transition enter-from-class="opacity-0 translate-y-2" leave-to-class="opacity-0" enter-active-class="transition" leave-active-class="transition">
      <p v-if="codigoCopiado" role="status"
         class="fixed bottom-14 right-4 z-[60] max-w-[calc(100vw-2rem)] rounded-2xl bg-azul-noche px-4 py-3 text-sm font-semibold text-white shadow-xl">
        Código {{ codigoCopiado }} copiado
      </p>
    </Transition>
  </div>
</template>
