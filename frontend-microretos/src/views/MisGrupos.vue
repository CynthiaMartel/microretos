<!-- Ruta: /mis-equipos (name: mis-equipos). Antes /mis-grupos, y antes /dashboard/mis-grupos — ver
     router/index.js. El componente sigue llamándose MisGrupos.vue (no renombrado a propósito, para
     no ampliar el diff): la ruta pasó a "equipos" porque "grupo" ya significa la clase/curso del
     encuentro (Encuentro.grupo, ej. "2ºB"), y esta vista sigue el progreso de los EQUIPOS de
     alumnado — el endpoint de backend GET /encuentros/mis-grupos tampoco se ha tocado. -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'
import { formatCurso } from '../utils/formatCurso.js'

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
    const res = await api.get('/encuentros/mis-grupos')
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
  if (equipo.fases[faseNum]?.completada)       return { label: 'Completa', cls: 'bg-[#00A859]/20 text-[#00A859]' }
  if (equipo.fase_actual === faseNum)          return { label: 'En curso', cls: 'bg-blue-100 text-blue-600 ring-1 ring-blue-300' }
  return { label: 'Pendiente', cls: 'bg-gray-100 text-gray-400' }
}

function estadoBadge(equipo) {
  if (equipo.fases_completas === 5) return { label: 'Completado', cls: 'bg-emerald-100 text-emerald-700' }
  if (equipo.fase_actual === 0 && equipo.fases_completas === 0) return { label: 'Sin iniciar', cls: 'bg-gray-100 text-gray-500' }
  return { label: `${FASES_PROYECTO[equipo.fase_actual]?.label ?? ''}`, cls: 'bg-blue-100 text-blue-700' }
}

// Grupos con al menos un equipo que no ha avanzado (para destacarlos como alerta)
const gruposConAlerta = computed(() =>
  grupos.value.filter(g => g.equipos.some(e => e.fase_actual === 0 && e.fases_completas === 0))
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
  [...new Set(grupos.value.map(g => g.encuentro.curso).filter(Boolean))].sort()
)
const familiasDisponibles = computed(() =>
  [...new Set(grupos.value.flatMap(g => g.equipos.map(e => e.proyecto?.familia)).filter(Boolean))].sort()
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
  return grupos.value.filter(g => {
    if (filtroCurso.value   && g.encuentro.curso !== filtroCurso.value) return false
    if (filtroFamilia.value && !g.equipos.some(e => e.proyecto?.familia === filtroFamilia.value)) return false
    if (filtroEstado.value  && !g.equipos.some(equipoCumpleEstado)) return false
    if (!q) return true
    const enTexto = [g.encuentro.grupo, g.encuentro.ciclo_formativo]
      .filter(Boolean)
      .some(t => t.toLowerCase().includes(q))
    const enEquipos = g.equipos.some(e =>
      [e.nombre, e.proyecto?.titulo, e.proyecto?.familia].filter(Boolean).some(t => t.toLowerCase().includes(q))
    )
    return enTexto || enEquipos
  })
})

// Separación explícita en dos secciones — no solo un contador, la lista también
// se agrupa visualmente por estado. gruposOrdenados concatena ambas para recorrerlas
// en un único v-for y pintar la cabecera de sección solo al cambiar de grupo.
const gruposEnProgreso  = computed(() => gruposFiltrados.value.filter(g => !grupoCompletado(g)))
const gruposCompletados = computed(() => gruposFiltrados.value.filter(g => grupoCompletado(g)))
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
  <div class="min-h-screen bg-[#F8FAFC] pt-12">
    <!-- top-12 (no top-0): la TopBar global es fixed h-12 con z-50 — con top-0 esta
         cabecera propia quedaba pegada al viewport y desaparecía detrás de aquella. -->
    <div class="sticky top-12 z-20 bg-white/90 backdrop-blur-sm border-b border-gray-100 px-4 py-3 flex items-center gap-3">
      <button @click="router.back()"
              class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <p class="text-xs font-black uppercase tracking-widest text-[#00A859]">Mis grupos</p>
        <p class="text-sm font-bold text-[#121212]">Seguimiento de todos tus equipos activos</p>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-6 space-y-4">

      <div v-if="cargando" class="flex items-center justify-center py-24">
        <div class="w-8 h-8 border-2 border-[#00A859] border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error" class="rounded-3xl bg-red-50 border border-red-200 p-8 text-center text-red-600 text-sm font-semibold">
        {{ error }}
      </div>

      <template v-else>
        <div v-if="!grupos.length" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
          <p class="text-gray-400 text-sm">Todavía no tienes encuentros con equipos creados.</p>
        </div>

        <template v-else>
          <!-- Contadores -->
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div class="text-center">
                <p class="text-2xl font-black text-[#121212]">{{ gruposFiltrados.length }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Grupos</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-[#00A859]">{{ totalEquipos }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Equipos</p>
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
                     placeholder="Buscar por grupo, proyecto, ciclo o equipo..."
                     class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-10 py-2.5 text-sm font-medium
                            text-[#1F2937] placeholder-gray-400 focus:bg-white focus:border-[#00A859]
                            focus:ring-2 focus:ring-[#00A859]/10 outline-none transition-all"/>
              <button v-if="busqueda" @click="busqueda = ''"
                      class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center
                             rounded-full bg-gray-200 hover:bg-gray-300 text-gray-500 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
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
                             focus:bg-white focus:border-[#00A859] outline-none transition-all disabled:opacity-50">
                <option value="">Todos los cursos</option>
                <option v-for="c in cursosDisponibles" :key="c" :value="c">{{ formatCurso(c) }} curso</option>
              </select>
              <select v-model="filtroFamilia" :disabled="!familiasDisponibles.length"
                      class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-[#1F2937]
                             focus:bg-white focus:border-[#00A859] outline-none transition-all disabled:opacity-50">
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
          {{ gruposConAlerta.length }} grupo(s) con equipos que todavía no han empezado (Fase 0 sin completar).
        </p>

        <div v-if="grupos.length && !gruposFiltrados.length" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
          <p class="text-gray-400 text-sm">Ningún grupo coincide con los filtros aplicados.</p>
        </div>

        <!-- Encabezado de la lista: cada tarjeta abre el detalle del trabajo real que el
             equipo ha hecho en su workspace (F0-F4), no es solo un listado de nombres. -->
        <p v-if="gruposFiltrados.length" class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 pt-1">
          Trabajo en el workspace de los equipos
        </p>

        <template v-for="(g, idx) in gruposOrdenados" :key="g.encuentro.id">
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
                {{ g.encuentro.grupo || g.equipos[0]?.proyecto?.titulo || 'Sin nombre' }}
                <span v-if="g.encuentro.fecha" class="font-bold text-gray-400">· {{ formatoFecha(g.encuentro.fecha) }}</span>
              </p>
              <p class="text-xs text-gray-400">{{ g.encuentro.ciclo_formativo }} · {{ equiposDeGrupo(g).length }} equipo(s)</p>
              <div v-if="g.encuentro.codigo_clase || g.encuentro.codigo_ia" class="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span v-if="g.encuentro.codigo_clase" @click.stop="copiarCodigo(g.encuentro.codigo_clase)"
                      :title="codigoCopiado === g.encuentro.codigo_clase ? '¡Copiado!' : 'Copiar código workspace alumnado'"
                      class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#00A859]/10 border border-[#00A859]/20 cursor-pointer">
                  <span class="w-1 h-1 rounded-full bg-[#00A859] shrink-0"></span>
                  <span class="text-[10px] font-black tracking-wider text-[#00A859]">{{ g.encuentro.codigo_clase }}</span>
                </span>
                <span v-if="g.encuentro.codigo_ia" @click.stop="copiarCodigo(g.encuentro.codigo_ia)"
                      :title="codigoCopiado === g.encuentro.codigo_ia ? '¡Copiado!' : 'Copiar código sugerencia IA alumnado'"
                      class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-orange-50 border border-orange-200 cursor-pointer">
                  <span class="text-[9px] shrink-0">✨</span>
                  <span class="text-[10px] font-black tracking-wider text-orange-600">{{ g.encuentro.codigo_ia }}</span>
                </span>
              </div>
            </div>
            <button @click.stop="router.push({ name: 'mis-equipos-detalle', params: { id: g.encuentro.id } })"
                    class="shrink-0 px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors
                           text-[10px] font-black text-gray-600 uppercase tracking-wider">
              Detalle equipos →
            </button>
            <svg :class="['w-4 h-4 text-gray-400 shrink-0 transition-transform', encuentrosAbiertos.has(g.encuentro.id) ? 'rotate-180' : '']"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <div v-if="encuentrosAbiertos.has(g.encuentro.id)" class="border-t border-gray-100 px-5 py-4 space-y-3">
            <div v-for="equipo in equiposDeGrupo(g)" :key="equipo.id"
                 class="rounded-2xl border border-gray-100 overflow-hidden">

              <button @click="toggleEquipo(equipo.id)"
                      class="w-full px-4 py-3 flex items-center gap-3 hover:bg-gray-50 transition-colors text-left">
                <div class="shrink-0 w-10 h-10 relative">
                  <svg class="w-10 h-10 -rotate-90" viewBox="0 0 48 48">
                    <circle cx="24" cy="24" r="20" fill="none" stroke="#F3F4F6" stroke-width="4"/>
                    <circle cx="24" cy="24" r="20" fill="none" stroke="#00A859" stroke-width="4"
                            :stroke-dasharray="`${progresoPct(equipo) * 1.257} 125.7`" stroke-linecap="round"/>
                  </svg>
                  <span class="absolute inset-0 flex items-center justify-center text-[9px] font-black text-[#00A859]">
                    {{ progresoPct(equipo) }}%
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <p class="font-bold text-sm text-[#121212]">{{ equipo.nombre }}</p>
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black', estadoBadge(equipo).cls]">
                      {{ estadoBadge(equipo).label }}
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
                  <span class="text-[9px] font-black uppercase tracking-widest text-gray-400">Código de acceso del equipo</span>
                  <span @click.stop="copiarCodigo(equipo.codigo_acceso)"
                        :title="codigoCopiado === equipo.codigo_acceso ? '¡Copiado!' : 'Copiar código de acceso del equipo'"
                        class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#00A859]/10 border border-[#00A859]/20 cursor-pointer">
                    <span class="w-1 h-1 rounded-full bg-[#00A859] shrink-0"></span>
                    <span class="text-[10px] font-black tracking-wider text-[#00A859]">{{ equipo.codigo_acceso }}</span>
                  </span>
                </div>

                <p v-if="equipo.reflexiones.length" class="text-[9px] font-black uppercase tracking-widest text-gray-400">
                  {{ equipo.reflexiones.length }} reflexión(es) registrada(s)
                </p>

                <button @click="router.push({ name: 'mis-equipos-detalle', params: { id: g.encuentro.id } })"
                        class="w-full py-2.5 rounded-xl bg-gray-50 hover:bg-gray-100 border border-gray-100
                               transition-colors text-[10px] font-black text-gray-500 uppercase tracking-wider">
                  Ver detalle de equipos →
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
