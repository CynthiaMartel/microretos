<!-- Ruta: /mis-grupos (name: mis-grupos). Antes /mis-grupos, y antes /dashboard/mis-grupos — ver
     router/index.js. En las vistas del docente los equipos de alumnado se llaman "grupos" y la letra
     del encuentro (Encuentro.grupo) se muestra como "Clase"; los datos vienen de GET /encuentros/mis-grupos. -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMisGrupos } from '../services/encuentroService.js'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'
import { formatCurso } from '../utils/formatCurso.js'
import CodigoBadgeMini from '../components/CodigoBadgeMini.vue'
import { useCursoAcademicoStore, cursoDeEncuentro } from '../stores/cursoAcademico.js'
import SelectorCurso from '../components/SelectorCurso.vue'
import ConceptoClave from '../components/ConceptoClave.vue'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { ICONOS_NAV } from '../config/navegacion.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

const router = useRouter()


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
    setTimeout(() => { if (codigoCopiado.value === codigo) codigoCopiado.value = null }, 1200)
  } catch { /* clipboard no disponible */ }
}

const ROLES = {
  portavoz:      { label: 'Portavoz',       color: 'bg-blue-100 text-blue-700' },
  tiempos:       { label: 'Tiempos',        color: 'bg-amber-100 text-amber-700' },
  documentacion: { label: 'Documentación',  color: 'bg-violet-100 text-violet-700' },
  foco:          { label: 'Foco',           color: 'bg-emerald-100 text-emerald-700' },
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
function irADiagnostico(g, equipo) {
  router.push({ name: 'mis-grupos-detalle', params: { id: g.encuentro.id }, query: { equipo: equipo.id, ver: 'diagnostico' } })
}

// Grupos con al menos un equipo que no ha avanzado (para destacarlos como alerta)
// Curso académico compartido (store): por la fecha del encuentro de cada grupo
const cursoStore     = useCursoAcademicoStore()
const gruposCurso    = computed(() => grupos.value.filter(g => cursoStore.coincide(cursoDeEncuentro(g.encuentro))))
const cursosConDatos = computed(() => [...new Set(grupos.value.map(g => cursoDeEncuentro(g.encuentro)))])

const gruposConAlerta = computed(() =>
  gruposCurso.value.filter(g => g.equipos.some(e => e.fase_actual === 0 && e.fases_completas === 0))
)

// Un grupo (encuentro) se considera "completado" solo si TODOS sus equipos han
// terminado las 5 fases — con un solo equipo a medias, el grupo sigue "en progreso".
// Se usa solo para la sección "Todos" (separar visualmente ambos bloques); el filtro
// de pestañas (Completados/En progreso) mira el estado de cada EQUIPO, no del grupo
// entero — un encuentro puede tener equipos completados y otros a medias a la vez.
function grupoCompletado(g) {
  return g.equipos.length > 0 && g.equipos.every(e => e.fases_completas === 5)
}

function equipoCumpleEstado(equipo) {
  if (filtroEstado.value === 'completado') return equipo.fases_completas === 5
  if (filtroEstado.value === 'progreso')   return equipo.fases_completas !== 5
  return true
}

// Equipos de un grupo a mostrar: todos, salvo que haya una pestaña de estado activa,
// en cuyo caso solo los que la cumplen (así "Completados" no lista también los que
// siguen a medias dentro del mismo encuentro).
function equiposDeGrupo(g) {
  return filtroEstado.value ? g.equipos.filter(equipoCumpleEstado) : g.equipos
}

// ── Búsqueda y filtros ──────────────────────────────────────────────────────
const busqueda      = ref('')
const filtroCurso   = ref('')
const filtroFamilia = ref('')
const filtroEstado  = ref('') // '' | 'progreso' | 'completado'

const cursosDisponibles = computed(() =>
  [...new Set(gruposCurso.value.map(g => g.encuentro.curso).filter(Boolean))].sort()
)
const familiasDisponibles = computed(() =>
  [...new Set(gruposCurso.value.map(familiaDe))].sort((a, b) => (a === SIN_FAMILIA) - (b === SIN_FAMILIA) || a.localeCompare(b, 'es'))
)

const hayFiltrosActivos = computed(() => !!(busqueda.value || filtroCurso.value || filtroFamilia.value || filtroEstado.value))
function limpiarFiltros() {
  busqueda.value = ''
  filtroCurso.value = ''
  filtroFamilia.value = ''
  filtroEstado.value = ''
}

const gruposFiltrados = computed(() => {
  const q = busqueda.value.toLowerCase().trim()
  return gruposCurso.value.filter(g => {
    if (filtroCurso.value   && g.encuentro.curso !== filtroCurso.value) return false
    if (filtroFamilia.value && familiaDe(g) !== filtroFamilia.value) return false
    if (filtroEstado.value  && !g.equipos.some(equipoCumpleEstado)) return false
    if (!q) return true
    const enTexto = [g.encuentro.grupo, g.encuentro.ciclo_formativo]
      .filter(Boolean)
      .some(t => t.toLowerCase().includes(q))
    const enEquipos = g.equipos.some(e =>
      [nombreGrupo(e), e.proyecto?.titulo, e.proyecto?.familia].filter(Boolean).some(t => t.toLowerCase().includes(q))
    )
    return enTexto || enEquipos
  })
})

// ── Agrupación por familia → módulo ─────────────────────────────────────────
// Los encuentros se localizan entrando por cards: familia profesional (solo si el docente
// tiene encuentros de más de una) → módulo → lista de encuentros. Familia y módulos salen
// del proyecto del encuentro; si el encuentro no trae familia, la de su primer equipo.
// Un encuentro cuyo proyecto trabaja varios módulos va a la card "Multimódulo".
const SIN_FAMILIA = 'Sin familia'
const MULTIMODULO = 'Multimódulo'
const SIN_MODULO  = 'Sin módulo'

const familiaDe = (g) => g.encuentro.familia_nombre || g.equipos.find(e => e.proyecto?.familia)?.proyecto.familia || SIN_FAMILIA
const modulosDe = (g) => g.encuentro.modulos ?? []
const moduloDe  = (g) => {
  const m = modulosDe(g)
  return m.length > 1 ? MULTIMODULO : (m[0] || SIN_MODULO)
}

// Agrupa conservando el orden: alfabético, con los cajones genéricos al final
function agrupar(lista, claveDe, alFinal = []) {
  const mapa = new Map()
  lista.forEach(g => {
    const k = claveDe(g)
    if (!mapa.has(k)) mapa.set(k, [])
    mapa.get(k).push(g)
  })
  const peso = (k) => alFinal.indexOf(k) + 1
  return [...mapa.entries()]
    .map(([nombre, grupos]) => ({ nombre, grupos }))
    .sort((a, b) => peso(a.nombre) - peso(b.nombre) || a.nombre.localeCompare(b.nombre, 'es'))
}

// La capa de familias solo aparece si el docente tiene encuentros de más de una familia
// (sobre todo el curso, no sobre el recorte filtrado, para que no aparezca y desaparezca al buscar)
const variasFamilias = computed(() => new Set(gruposCurso.value.map(familiaDe)).size > 1)

const familiaSel = ref(null)
const moduloSel  = ref(null)

// Elegir una familia en el filtro equivale a entrar en su card (y quitarlo, a volver)
watch(filtroFamilia, (f) => { familiaSel.value = f || null; moduloSel.value = null })

// Con texto de búsqueda se salta la navegación por cards: resultados directos
const buscando = computed(() => !!busqueda.value.trim())

const familias = computed(() => agrupar(gruposFiltrados.value, familiaDe, [SIN_FAMILIA]))
const gruposDeFamilia = computed(() =>
  variasFamilias.value ? gruposFiltrados.value.filter(g => familiaDe(g) === familiaSel.value) : gruposFiltrados.value
)
const modulos = computed(() => agrupar(gruposDeFamilia.value, moduloDe, [MULTIMODULO, SIN_MODULO]))

// 'familias' | 'modulos' | 'encuentros'
const nivel = computed(() => {
  if (buscando.value) return 'encuentros'
  if (variasFamilias.value && !familiaSel.value) return 'familias'
  return moduloSel.value ? 'encuentros' : 'modulos'
})

const gruposLista = computed(() =>
  buscando.value ? gruposFiltrados.value : gruposDeFamilia.value.filter(g => moduloDe(g) === moduloSel.value)
)

function abrirFamilia(nombre) { familiaSel.value = nombre; moduloSel.value = null }
function abrirModulo(nombre)  { moduloSel.value = nombre }
function irAFamilias()        { familiaSel.value = null; moduloSel.value = null }

// Si los filtros dejan vacía la familia o el módulo abiertos, volver al nivel de arriba
watch(familias, (lista) => {
  if (familiaSel.value && !lista.some(f => f.nombre === familiaSel.value)) irAFamilias()
})
watch(modulos, (lista) => {
  if (moduloSel.value && !lista.some(m => m.nombre === moduloSel.value)) moduloSel.value = null
})

// Resumen de una card (familia o módulo): encuentros, equipos por estado y avance medio
function resumenCard(lista) {
  const eqs = lista.flatMap(equiposDeGrupo)
  return {
    encuentros:  lista.length,
    equipos:     eqs.length,
    completados: eqs.filter(e => estadoEquipo(e) === 'completado').length,
    enCurso:     eqs.filter(e => estadoEquipo(e) === 'en_curso').length,
    sinIniciar:  eqs.filter(e => estadoEquipo(e) === 'sin_iniciar').length,
    progreso:    eqs.length ? Math.round(eqs.reduce((t, e) => t + progresoPct(e), 0) / eqs.length) : 0,
  }
}

// Módulos que reúne la card "Multimódulo" (para saber qué hay dentro sin abrirla)
const modulosIncluidos = (lista) => [...new Set(lista.flatMap(modulosDe))]

const estiloModulo = (nombre) =>
  nombre === MULTIMODULO ? { icon: 'capas', tile: 'bg-administraciones' }
  : nombre === SIN_MODULO ? { icon: 'libro', tile: 'bg-gray-400' }
  : { icon: 'libro', tile: 'bg-alumnos' }

// Separación explícita en dos secciones — no solo un contador, la lista también
// se agrupa visualmente por estado. gruposOrdenados concatena ambas para recorrerlas
// en un único v-for y pintar la cabecera de sección solo al cambiar de grupo.
// Sobre gruposLista: los encuentros del módulo abierto (o los resultados de búsqueda).
const gruposEnProgreso  = computed(() => gruposLista.value.filter(g => !grupoCompletado(g)))
const gruposCompletados = computed(() => gruposLista.value.filter(g => grupoCompletado(g)))
const gruposOrdenados   = computed(() => [...gruposEnProgreso.value, ...gruposCompletados.value])

// Contadores — sobre lo que hay visible tras aplicar búsqueda/filtros (y la pestaña
// de estado, vía equiposDeGrupo), para que sirvan también como resumen de "cuántos
// equipos hay en este recorte".
const equiposVisibles    = computed(() => gruposFiltrados.value.flatMap(equiposDeGrupo))
const totalEquipos       = computed(() => equiposVisibles.value.length)
const equiposCompletados = computed(() => equiposVisibles.value.filter(e => e.fases_completas === 5).length)
const equiposSinIniciar  = computed(() => equiposVisibles.value.filter(e => e.fase_actual === 0 && e.fases_completas === 0).length)
const equiposEnProgreso  = computed(() => totalEquipos.value - equiposCompletados.value - equiposSinIniciar.value)

// Al activar una pestaña de estado (Completados / En progreso), desplegar automáticamente
// los encuentros y equipos que la cumplen — si no, el usuario tendría que abrir cada
// acordeón a mano para comprobar cuál de sus equipos es el que cumple el filtro.
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
  <div class="min-h-screen bg-[#F3F6FA] font-sans text-azul-noche pt-16">

    <!-- Cabecera como el resto de vistas del panel docente (degradado + cinta de colores) -->
    <div class="max-w-5xl mx-auto px-4 pt-5">
      <CabeceraSeccion titulo="Seguimiento de" destacado="grupos"
                       subtitulo="Revisa el avance de todos tus grupos activos, encuentro a encuentro." />
    </div>

    <div class="max-w-5xl mx-auto px-4 mt-4 mb-2">
      <ConceptoClave color="centros" :segmentos="[
        { t: 'Seguir a tus ' }, { t: 'GRUPOS', b: true },
        { t: ' es ver, encuentro a encuentro, en qué ' }, { t: 'fase', b: true },
        { t: ' está cada grupo de alumnado, revisar lo que entrega en su workspace y ' }, { t: 'validar sus fases', b: true },
        { t: ' hasta el diagnóstico final.' },
      ]" />
    </div>

    <div class="max-w-5xl mx-auto px-4 py-6 space-y-4">

      <div v-if="cargando" class="flex items-center justify-center py-24">
        <div class="w-8 h-8 border-2 border-centros border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error" class="rounded-3xl bg-red-50 border border-red-200 p-8 text-center text-red-600 text-sm font-semibold">
        {{ error }}
      </div>

      <template v-else>
        <div v-if="!grupos.length" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
          <p class="text-gray-400 text-sm">Todavía no tienes encuentros con grupos creados.</p>
        </div>

        <template v-else>
          <!-- Contadores -->
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div class="text-center">
                <p class="text-2xl font-black text-[#121212]">{{ gruposFiltrados.length }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Encuentros</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-centros">{{ totalEquipos }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Grupos</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-blue-600">{{ equiposEnProgreso }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">En progreso</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-emerald-600">{{ equiposCompletados }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Completados</p>
              </div>
            </div>
          </div>

          <!-- Búsqueda y filtros -->
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-4 space-y-3">
            <div class="relative">
              <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input v-model="busqueda" type="text"
                     placeholder="Buscar por clase, proyecto, ciclo o grupo..."
                     class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-10 py-2.5 text-sm font-medium
                            text-[#1F2937] placeholder-gray-400 focus:bg-white focus:border-centros
                            focus:ring-2 focus:ring-centros/10 outline-none transition-all"/>
              <button v-if="busqueda" @click="busqueda = ''"
                      class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center
                             rounded-full bg-gray-200 hover:bg-gray-300 text-gray-500 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" class="mr-1" />
              <div class="flex rounded-xl border border-gray-200 overflow-hidden shrink-0">
                <button @click="filtroEstado = ''"
                        :class="['px-3 py-2 text-xs font-black uppercase tracking-wider transition-colors',
                                 filtroEstado === '' ? 'bg-[#1F2937] text-white' : 'bg-gray-50 text-gray-500 hover:text-[#1F2937]']">
                  Todos
                </button>
                <button @click="filtroEstado = 'progreso'"
                        :class="['px-3 py-2 text-xs font-black uppercase tracking-wider transition-colors border-l border-gray-200',
                                 filtroEstado === 'progreso' ? 'bg-blue-600 text-white' : 'bg-gray-50 text-gray-500 hover:text-[#1F2937]']">
                  En progreso
                </button>
                <button @click="filtroEstado = 'completado'"
                        :class="['px-3 py-2 text-xs font-black uppercase tracking-wider transition-colors border-l border-gray-200',
                                 filtroEstado === 'completado' ? 'bg-emerald-600 text-white' : 'bg-gray-50 text-gray-500 hover:text-[#1F2937]']">
                  Completados
                </button>
              </div>
              <select v-model="filtroCurso" :disabled="!cursosDisponibles.length"
                      class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-[#1F2937]
                             focus:bg-white focus:border-centros outline-none transition-all disabled:opacity-50">
                <option value="">Todos los niveles</option>
                <option v-for="c in cursosDisponibles" :key="c" :value="c">Nivel {{ formatCurso(c) }}</option>
              </select>
              <select v-model="filtroFamilia" :disabled="!familiasDisponibles.length"
                      class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-[#1F2937]
                             focus:bg-white focus:border-centros outline-none transition-all disabled:opacity-50">
                <option value="">Todas las familias</option>
                <option v-for="f in familiasDisponibles" :key="f" :value="f">{{ f }}</option>
              </select>
              <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                      class="px-3 py-2 text-xs font-black text-gray-500 hover:text-gray-700 uppercase tracking-wider transition-colors">
                Limpiar filtros
              </button>
            </div>
          </div>
        </template>

        <p v-if="gruposConAlerta.length" class="text-xs font-semibold text-amber-600 bg-amber-50 border border-amber-100 rounded-xl px-4 py-2">
          {{ gruposConAlerta.length }} encuentro(s) con grupos que todavía no han empezado (Fase 0 sin completar).
        </p>

        <div v-if="grupos.length && !gruposFiltrados.length" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
          <p class="text-gray-400 text-sm">Ningún encuentro coincide con los filtros aplicados.</p>
        </div>

        <template v-if="gruposFiltrados.length">
          <!-- Migas: dónde estás dentro de familia → módulo (no se muestran en el primer nivel) -->
          <nav v-if="!buscando && nivel !== (variasFamilias ? 'familias' : 'modulos')"
               class="flex flex-wrap items-center gap-1.5 pt-1 text-xs font-bold text-gray-400" aria-label="Agrupación">
            <button v-if="variasFamilias" type="button" class="hover:text-centros" @click="irAFamilias">Todas las familias</button>
            <template v-if="variasFamilias && familiaSel">
              <span aria-hidden="true">›</span>
              <button v-if="moduloSel" type="button" class="hover:text-centros" @click="moduloSel = null">{{ familiaSel }}</button>
              <span v-else class="text-azul-noche">{{ familiaSel }}</span>
            </template>
            <template v-if="!variasFamilias">
              <button type="button" class="hover:text-centros" @click="moduloSel = null">Todos los módulos</button>
            </template>
            <template v-if="moduloSel">
              <span aria-hidden="true">›</span>
              <span class="text-azul-noche">{{ moduloSel }}</span>
            </template>
          </nav>

          <p v-else-if="buscando" class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 pt-1">
            Resultados de la búsqueda
          </p>

          <!-- Nivel 1: familias profesionales (solo con encuentros de más de una familia) -->
          <template v-if="nivel === 'familias'">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 pt-1">Tus encuentros por familia profesional</p>
            <!-- auto-rows-fr + pie con mt-auto: todas las cards miden lo mismo y la barra de avance queda
                 a la misma altura. El título reserva siempre 2 líneas (min-h-[2.75em] = 2 × leading-snug)
                 para que el contador "N encuentros · N grupos" no suba o baje según el largo del nombre. -->
            <div class="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <button v-for="f in familias" :key="f.nombre" type="button" @click="abrirFamilia(f.nombre)"
                      class="group flex min-w-0 flex-col gap-3 rounded-3xl border border-gray-100 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <span class="flex items-start gap-3">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm"
                        :class="f.nombre === SIN_FAMILIA ? 'bg-gray-400' : 'bg-centros'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="ICONOS_NAV.alumnado" /></svg>
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block min-h-[2.75em] font-black leading-snug text-[#121212] line-clamp-2 break-words" :title="f.nombre">{{ f.nombre }}</span>
                    <span class="block text-xs text-gray-400">
                      {{ resumenCard(f.grupos).encuentros }} encuentro{{ resumenCard(f.grupos).encuentros === 1 ? '' : 's' }}
                      · {{ resumenCard(f.grupos).equipos }} grupo{{ resumenCard(f.grupos).equipos === 1 ? '' : 's' }}
                      · {{ agrupar(f.grupos, moduloDe).length }} módulo{{ agrupar(f.grupos, moduloDe).length === 1 ? '' : 's' }}
                    </span>
                  </span>
                  <svg class="h-4 w-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-centros" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </span>
                <!-- Chips + barra juntos al pie: alineados entre cards aunque cambie el texto de arriba -->
                <span class="mt-auto flex flex-col gap-3">
                <span class="flex flex-wrap gap-1.5">
                  <span v-if="resumenCard(f.grupos).enCurso" class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-black text-blue-700">{{ resumenCard(f.grupos).enCurso }} en curso</span>
                  <span v-if="resumenCard(f.grupos).completados" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700">✓ {{ resumenCard(f.grupos).completados }} completado{{ resumenCard(f.grupos).completados === 1 ? '' : 's' }}</span>
                  <span v-if="resumenCard(f.grupos).sinIniciar" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-black text-gray-500">{{ resumenCard(f.grupos).sinIniciar }} sin iniciar</span>
                </span>
                <span class="block h-1.5 shrink-0 overflow-hidden rounded-full bg-gray-100" :title="`Avance medio ${resumenCard(f.grupos).progreso}%`">
                  <span class="block h-full rounded-full bg-centros" :style="{ width: resumenCard(f.grupos).progreso + '%' }" />
                </span>
                </span>
              </button>
            </div>
          </template>

          <!-- Nivel 2: módulos (de la familia abierta, o de todo si solo hay una familia) -->
          <template v-else-if="nivel === 'modulos'">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 pt-1">
              {{ variasFamilias ? `Módulos de ${familiaSel}` : 'Tus encuentros por módulo' }}
            </p>
            <!-- Mismo criterio que las cards de familia: misma altura y barra alineada abajo -->
            <div class="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <button v-for="m in modulos" :key="m.nombre" type="button" @click="abrirModulo(m.nombre)"
                      class="group flex min-w-0 flex-col gap-3 rounded-3xl border border-gray-100 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <span class="flex items-start gap-3">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm" :class="estiloModulo(m.nombre).tile">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="ICONOS_NAV[estiloModulo(m.nombre).icon]" /></svg>
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block min-h-[2.75em] font-black leading-snug text-[#121212] line-clamp-2 break-words" :title="m.nombre">{{ m.nombre }}</span>
                    <span class="block text-xs text-gray-400">
                      {{ resumenCard(m.grupos).encuentros }} encuentro{{ resumenCard(m.grupos).encuentros === 1 ? '' : 's' }}
                      · {{ resumenCard(m.grupos).equipos }} grupo{{ resumenCard(m.grupos).equipos === 1 ? '' : 's' }}
                    </span>
                  </span>
                  <svg class="h-4 w-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-centros" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </span>
                <span v-if="m.nombre === MULTIMODULO" class="line-clamp-2 text-[11px] leading-snug text-gray-500">
                  Combina: {{ modulosIncluidos(m.grupos).join(' · ') }}
                </span>
                <!-- Chips + barra juntos al pie: alineados entre cards aunque cambie el texto de arriba -->
                <span class="mt-auto flex flex-col gap-3">
                <span class="flex flex-wrap gap-1.5">
                  <span v-if="resumenCard(m.grupos).enCurso" class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-black text-blue-700">{{ resumenCard(m.grupos).enCurso }} en curso</span>
                  <span v-if="resumenCard(m.grupos).completados" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700">✓ {{ resumenCard(m.grupos).completados }} completado{{ resumenCard(m.grupos).completados === 1 ? '' : 's' }}</span>
                  <span v-if="resumenCard(m.grupos).sinIniciar" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-black text-gray-500">{{ resumenCard(m.grupos).sinIniciar }} sin iniciar</span>
                </span>
                <span class="block h-1.5 shrink-0 overflow-hidden rounded-full bg-gray-100" :title="`Avance medio ${resumenCard(m.grupos).progreso}%`">
                  <span class="block h-full rounded-full bg-centros" :style="{ width: resumenCard(m.grupos).progreso + '%' }" />
                </span>
                </span>
              </button>
            </div>
          </template>

          <!-- Nivel 3: encuentros del módulo abierto. Cada tarjeta abre el detalle del trabajo
               real que el equipo ha hecho en su workspace (F0-F4), no es solo un listado. -->
          <p v-else class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 pt-1">
            Trabajo en el workspace de los grupos
          </p>
        </template>

        <template v-for="(g, idx) in (nivel === 'encuentros' ? gruposOrdenados : [])" :key="g.encuentro.id">
          <!-- Cabecera de sección — separación explícita entre "en progreso" y "completados",
               no solo un contador arriba. Se pinta una sola vez, al cambiar de grupo. -->
          <p v-if="!filtroEstado && (idx === 0 || grupoCompletado(gruposOrdenados[idx - 1]) !== grupoCompletado(g))"
             class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 pt-2">
            {{ grupoCompletado(g) ? `Completados (${gruposCompletados.length})` : `En progreso (${gruposEnProgreso.length})` }}
          </p>

          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">

          <button @click="toggleEncuentro(g.encuentro.id)"
                  class="w-full px-5 py-4 flex items-center gap-4 hover:bg-gray-50 transition-colors text-left">
            <div class="flex-1 min-w-0">
              <p class="font-black text-[#121212]">
                {{ g.encuentro.proyecto_titulo || g.equipos[0]?.proyecto?.titulo || (g.encuentro.grupo && `Clase ${g.encuentro.grupo}`) || 'Sin nombre' }}
                <span v-if="g.encuentro.fecha" class="font-bold text-gray-400">· {{ formatoFecha(g.encuentro.fecha) }}</span>
              </p>
              <p class="text-xs text-gray-400">{{ g.encuentro.ciclo_formativo }} · {{ equiposDeGrupo(g).length }} grupo(s)</p>
              <!-- Módulos del encuentro (y su familia en los resultados de búsqueda, que mezclan) -->
              <div v-if="modulosDe(g).length || buscando" class="flex flex-wrap items-center gap-1 mt-1">
                <span v-if="buscando && variasFamilias" class="px-2 py-0.5 rounded-full bg-centros/10 text-centros text-[10px] font-bold">{{ familiaDe(g) }}</span>
                <span v-for="m in modulosDe(g)" :key="m"
                      class="px-2 py-0.5 rounded-full bg-alumnos/10 text-alumnos-dark text-[10px] font-bold">{{ m }}</span>
              </div>
              <!-- Estado de sus equipos sin tener que desplegar -->
              <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span v-if="resumenGrupo(g).completados"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black">
                  ✓ {{ resumenGrupo(g).completados }} completado{{ resumenGrupo(g).completados === 1 ? '' : 's' }}
                </span>
                <span v-if="resumenGrupo(g).enCurso"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black">
                  <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                  {{ resumenGrupo(g).enCurso }} en curso
                </span>
                <span v-if="resumenGrupo(g).sinIniciar"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-black">
                  {{ resumenGrupo(g).sinIniciar }} sin iniciar
                </span>
                <span v-if="resumenGrupo(g).diagnosticos"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white border border-emerald-300 text-emerald-700 text-[10px] font-black">
                  📊 {{ resumenGrupo(g).diagnosticos }} diagnóstico{{ resumenGrupo(g).diagnosticos === 1 ? '' : 's' }} disponible{{ resumenGrupo(g).diagnosticos === 1 ? '' : 's' }}
                </span>
              </div>
              <div v-if="g.encuentro.codigo_clase || g.encuentro.codigo_ia" class="flex flex-wrap items-center gap-1.5 mt-1.5" @click.stop>
                <CodigoBadgeMini v-if="g.encuentro.codigo_clase" :code="g.encuentro.codigo_clase" variant="clase" @copiar="copiarCodigo" />
                <CodigoBadgeMini v-if="g.encuentro.codigo_ia" :code="g.encuentro.codigo_ia" variant="ia" @copiar="copiarCodigo" />
              </div>
            </div>
            <!-- Acceso principal al trabajo real de los equipos: color de marca sólido para que
                 no se confunda con el desplegable (que solo muestra un resumen). -->
            <button @click.stop="router.push({ name: 'mis-grupos-detalle', params: { id: g.encuentro.id } })"
                    class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-centros text-white shadow-md shadow-centros/20
                           hover:bg-centros/90 hover:shadow-lg transition-all text-[10px] font-black uppercase tracking-wider">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              Detalle grupos →
            </button>
            <svg :class="['w-4 h-4 text-gray-400 shrink-0 transition-transform', encuentrosAbiertos.has(g.encuentro.id) ? 'rotate-180' : '']"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <div v-if="encuentrosAbiertos.has(g.encuentro.id)" class="border-t border-gray-100 px-5 py-4 space-y-3">
            <div v-for="equipo in equiposDeGrupo(g)" :key="equipo.id"
                 :class="['rounded-2xl border border-gray-100 border-l-4 overflow-hidden', estadoBadge(equipo).borde]">

              <button @click="toggleEquipo(equipo.id)"
                      class="w-full px-4 py-3 flex items-center gap-3 hover:bg-gray-50 transition-colors text-left">
                <div class="shrink-0 w-10 h-10 relative">
                  <svg class="w-10 h-10 -rotate-90" viewBox="0 0 48 48">
                    <circle cx="24" cy="24" r="20" fill="none" stroke="#F3F4F6" stroke-width="4"/>
                    <circle cx="24" cy="24" r="20" fill="none" stroke="#3072AA" stroke-width="4"
                            :stroke-dasharray="`${progresoPct(equipo) * 1.257} 125.7`" stroke-linecap="round"/>
                  </svg>
                  <span class="absolute inset-0 flex items-center justify-center text-[9px] font-black text-centros">
                    {{ progresoPct(equipo) }}%
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <p class="font-bold text-sm text-[#121212]">{{ nombreGrupo(equipo) }}</p>
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black', estadoBadge(equipo).cls]">
                      {{ estadoBadge(equipo).label }}
                    </span>
                    <span v-if="equipo.fases_completas === 5"
                          :class="['px-2 py-0.5 rounded-full text-[10px] font-black',
                                   equipo.diagnostico_final ? 'bg-white border border-emerald-300 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                      {{ equipo.diagnostico_final ? '📊 Diagnóstico listo' : 'Diagnóstico pendiente' }}
                    </span>
                  </div>
                  <p v-if="equipo.proyecto" class="text-[11px] font-semibold text-gray-500 truncate">
                    {{ equipo.proyecto.titulo }}
                    <span v-if="equipo.proyecto.familia" class="text-gray-400">· {{ equipo.proyecto.familia }}</span>
                  </p>
                  <div class="flex flex-wrap gap-1 mt-1">
                    <span v-for="m in equipo.miembros" :key="m.id"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px] font-semibold">
                      {{ m.nombre }}
                      <span v-if="m.rol" :class="['px-1.5 py-px rounded-full text-[9px] font-black', ROLES[m.rol]?.color]">
                        {{ ROLES[m.rol]?.label }}
                      </span>
                    </span>
                  </div>
                </div>
                <svg :class="['w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform', equiposAbiertos.has(equipo.id) ? 'rotate-180' : '']"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>

              <div v-if="equiposAbiertos.has(equipo.id)" class="px-4 pb-4 border-t border-gray-50 pt-3 space-y-3">
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
                          :class="['shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider transition-colors',
                                   equipo.diagnostico_final
                                     ? 'bg-emerald-500 text-white hover:bg-emerald-600 shadow-md shadow-emerald-200'
                                     : 'bg-white border border-emerald-300 text-emerald-700 hover:bg-emerald-50']">
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
                       :class="['flex items-center gap-1.5 px-2 py-1 rounded-lg text-[9px] font-black', estadoFase(equipo, f.num).cls]">
                    <span class="text-xs leading-none">{{ f.icono }}</span>
                    <span class="uppercase tracking-wider">F{{ f.num }}</span>
                    <span class="normal-case font-bold">· {{ estadoFase(equipo, f.num).label }}</span>
                  </div>
                </div>

                <div v-if="equipo.codigo_acceso" class="flex flex-wrap items-center gap-1.5">
                  <span class="text-[9px] font-black uppercase tracking-widest text-gray-400">Código de acceso del grupo</span>
                  <span @click.stop="copiarCodigo(equipo.codigo_acceso)"
                        :title="codigoCopiado === equipo.codigo_acceso ? '¡Copiado!' : 'Copiar código de acceso del grupo'"
                        class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-centros/10 border border-centros/20 cursor-pointer">
                    <span class="w-1 h-1 rounded-full bg-centros shrink-0"></span>
                    <span class="text-[10px] font-black tracking-wider text-centros">{{ equipo.codigo_acceso }}</span>
                  </span>
                </div>

                <p v-if="equipo.reflexiones.length" class="text-[9px] font-black uppercase tracking-widest text-gray-400">
                  {{ equipo.reflexiones.length }} reflexión(es) registrada(s)
                </p>

                <button @click="router.push({ name: 'mis-grupos-detalle', params: { id: g.encuentro.id } })"
                        class="w-full inline-flex items-center justify-center gap-1.5 py-3 rounded-xl bg-centros text-white shadow-md shadow-centros/20
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
      </template>
    </div>
  </div>
</template>
