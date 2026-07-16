<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import MicroretoModal from '../components/MicroretoModal.vue'
import EliminarEncuentroModal from '../components/EliminarEncuentroModal.vue'
import BienvenidaModal from '../components/BienvenidaModal_DashboardDocente.vue'
import api from '../api.js'
import { duracionPorFase } from '../config/fasesProyecto.js'

const router  = useRouter()
const route   = useRoute()
const cargando = ref(false)

// ─── Modal "¿Qué necesitas?" ──────────────────────────────────────────────────
const guiaBienvenida = ref(false)

function seleccionarOpcionBienvenida(opcion) {
  if (opcion === 'crear') {
    guiaBienvenida.value = false
    router.push('/dashboard')
  } else {
    guiaBienvenida.value = false
  }
}

// ─── Datos de encuentros ────────────────────────────────────────────────────────
const encuentros = ref([])

async function cargarEncuentros() {
  cargando.value = true
  try {
    const res = await api.get('/encuentros')
    encuentros.value = res.data
  } catch (e) {
    console.error('Error cargando encuentros:', e)
    encuentros.value = []
  } finally {
    cargando.value = false
  }
}

onMounted(async () => {
  await cargarEncuentros()
  const idParam = route.query.id
  if (idParam) {
    const s = encuentros.value.find(s => String(s.id) === String(idParam))
    if (s) verEncuentro(s)
  } else {
    guiaBienvenida.value = true
  }
})

// ── Modal eliminar encuentro ─────────────────────────────────────────────────────
const modalEliminarVisible = ref(false)
const encuentroAEliminar      = ref(null)

function abrirModalEliminar(encuentro) {
  encuentroAEliminar.value      = encuentro
  modalEliminarVisible.value = true
}

function cerrarModalEliminar() {
  modalEliminarVisible.value = false
  encuentroAEliminar.value      = null
}

function onEncuentroEliminado({ id, titulo }) {
  encuentros.value = encuentros.value.filter(s => s.id !== id)
  if (encuentroAbierto.value?.id === id) encuentroAbierto.value = null
  cerrarModalEliminar()
  mostrarSnack(`Encuentro "${titulo || 'sin título'}" movido a la papelera.`, {
    label: 'Ir a la papelera',
    fn: () => router.push({ name: 'papelera' }),
  })
}

// ── Snackbar ──────────────────────────────────────────────────────────────────
const snackbar = ref({ visible: false, mensaje: '', accion: null })
function mostrarSnack(mensaje, accion = null) {
  snackbar.value = { visible: true, mensaje, accion }
  setTimeout(() => { snackbar.value.visible = false }, 5000)
}

// ─── Filtros ──────────────────────────────────────────────────────────────────
const filtros = ref({
  titulo:  '',
  desde:   '',
  hasta:   '',
  curso:   '',
  grupo:   '',
  ciclo:   '',
  centro:  '',
})

const encuentrosUnicos = computed(() => {
  const centros = [...new Set(encuentros.value.map(s => s.centro_educativo).filter(Boolean))].sort()
  const ciclos  = [...new Set(encuentros.value.map(s => s.ciclo_formativo).filter(Boolean))].sort()
  return { centros, ciclos }
})

const encuentrosFiltrados = computed(() => {
  let lista = [...encuentros.value].reverse()

  if (filtros.value.titulo.trim()) {
    const q = filtros.value.titulo.trim().toLowerCase()
    lista = lista.filter(s => (s.proyecto_titulo || '').toLowerCase().includes(q))
  }
  if (filtros.value.desde)
    lista = lista.filter(s => s.fecha >= filtros.value.desde)
  if (filtros.value.hasta)
    lista = lista.filter(s => s.fecha <= filtros.value.hasta)
  if (filtros.value.curso)
    lista = lista.filter(s => s.curso === filtros.value.curso)
  if (filtros.value.grupo)
    lista = lista.filter(s => s.grupo === filtros.value.grupo)
  if (filtros.value.ciclo)
    lista = lista.filter(s => (s.ciclo_formativo || '').toLowerCase().includes(filtros.value.ciclo.toLowerCase()))
  if (filtros.value.centro)
    lista = lista.filter(s => s.centro_educativo === filtros.value.centro)

  return lista
})

const hayFiltrosActivos = computed(() =>
  Object.values(filtros.value).some(v => v !== '')
)

function limpiarFiltros() {
  filtros.value = { titulo: '', desde: '', hasta: '', curso: '', grupo: '', ciclo: '', centro: '' }
}

// ─── Acordeón por centro educativo ───────────────────────────────────────────
const centrosExpandidos = ref(new Set())

const encuentrosAgrupados = computed(() => {
  const mapa = {}
  for (const s of encuentrosFiltrados.value) {
    const centro = s.centro_educativo || '— Sin centro asignado —'
    if (!mapa[centro]) mapa[centro] = []
    mapa[centro].push(s)
  }
  return mapa
})

const centrosOrdenados = computed(() =>
  Object.keys(encuentrosAgrupados.value).sort((a, b) => {
    if (a === '— Sin centro asignado —') return 1
    if (b === '— Sin centro asignado —') return -1
    return a.localeCompare(b)
  })
)

function toggleCentro(centro) {
  centrosExpandidos.value.has(centro)
    ? centrosExpandidos.value.delete(centro)
    : centrosExpandidos.value.add(centro)
}

// ─── Modal encuentro ─────────────────────────────────────────────────────────────
const encuentroAbierto = ref(null)

function verEncuentro(s)     { encuentroAbierto.value = s }
function cerrarEncuentro()   { encuentroAbierto.value = null; editandoFechaFin.value = false }

// ─── Editar fecha_fin del encuentro ───────────────────────────────────────────
const editandoFechaFin  = ref(false)
const fechaFinInput     = ref('')
const guardandoFechaFin = ref(false)
const errorFechaFin     = ref('')

function empezarEditarFechaFin() {
  fechaFinInput.value    = encuentroAbierto.value?.fecha_fin || ''
  errorFechaFin.value    = ''
  editandoFechaFin.value = true
}

async function guardarFechaFin() {
  guardandoFechaFin.value = true
  errorFechaFin.value     = ''
  try {
    const res = await api.put(`/encuentros/${encuentroAbierto.value.id}`, { fecha_fin: fechaFinInput.value || null })
    encuentroAbierto.value = { ...encuentroAbierto.value, fecha_fin: res.data.fecha_fin }
    encuentros.value = encuentros.value.map(s => s.id === encuentroAbierto.value.id ? { ...s, fecha_fin: res.data.fecha_fin } : s)
    editandoFechaFin.value = false
  } catch (e) {
    errorFechaFin.value = e.response?.data?.errors?.fecha_fin?.[0] || e.response?.data?.message || 'No se pudo guardar la fecha fin.'
  } finally {
    guardandoFechaFin.value = false
  }
}

// ─── Modal ficha microreto ────────────────────────────────────────────────────
const microretoModalId = ref(null)

function abrirMicroretoModal(id) { microretoModalId.value = id }
function cerrarMicroretoModal()  { microretoModalId.value = null }

// ─── Modal ver proyecto ───────────────────────────────────────────────────────
const modalVerProyecto = ref(false)
const proyectoModal    = ref(null)
const cargandoModal    = ref(false)

async function abrirModalProyecto(uuid) {
  modalVerProyecto.value = true
  cargandoModal.value    = true
  proyectoModal.value    = null
  try {
    const res = await api.get(`/startup/proyectos/${uuid}`)
    proyectoModal.value = res.data
  } catch {
    /* no crítico */
  } finally {
    cargandoModal.value = false
  }
}

function cerrarModalProyecto() {
  modalVerProyecto.value = false
  proyectoModal.value    = null
}

function getBadgeModal(p) {
  if (!p) return { label: '—', cls: 'bg-gray-100 border-gray-200 text-gray-400', dot: 'bg-gray-300' }
  const e = p.estado
  if (e === 'borrador' || e === 'en_edicion')
    return { label: 'En edición',  cls: 'bg-amber-50 border-amber-200 text-amber-700',    dot: 'bg-amber-400' }
  if (e === 'archivado')
    return { label: 'Archivado',   cls: 'bg-gray-100 border-gray-200 text-gray-400',       dot: 'bg-gray-400' }
  if (e === 'validado') {
    if (p.empresa_validado && p.docente_validado)
      return { label: 'Validado · Completo', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
    if (p.empresa_validado)
      return { label: 'Validado · Empresa', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
    if (p.docente_validado)
      return { label: 'Validado · Docente', cls: 'bg-emerald-50 border-emerald-300 text-emerald-700', dot: 'bg-emerald-500' }
    return { label: 'Validado', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
  }
  if (p.empresa_no_valida_aun)
    return { label: 'No validar aún', cls: 'bg-red-50 border-red-300 text-red-700',         dot: 'bg-red-400' }
  if (p.enviado_a_empresa_mail)
    return { label: 'Enviado · Esperando', cls: 'bg-blue-50 border-blue-200 text-blue-700', dot: 'bg-blue-400' }
  return { label: 'Pendiente validar', cls: 'bg-violet-50 border-violet-300 text-violet-700', dot: 'bg-violet-400' }
}

// ─── Crear microproyecto desde encuentro ────────────────────────────────────────
function crearMicroproyecto(encuentroId) {
  router.push({ name: 'startup-day-crear', query: { encuentro_id: encuentroId } })
}

// ─── Código de acceso para alumnado ──────────────────────────────────────────
const creandoCodigo = ref({})
const errorCodigo   = ref({})

async function crearCodigo(encuentro) {
  creandoCodigo.value = { ...creandoCodigo.value, [encuentro.id]: true }
  errorCodigo.value   = { ...errorCodigo.value,   [encuentro.id]: '' }
  try {
    const res = await api.post(`/encuentros/${encuentro.id}/crear-codigo`)
    const nuevoCodigo = res.data.codigo_clase
    encuentros.value = encuentros.value.map(s =>
      s.id === encuentro.id ? { ...s, codigo_clase: nuevoCodigo } : s
    )
    if (encuentroAbierto.value?.id === encuentro.id) {
      encuentroAbierto.value = { ...encuentroAbierto.value, codigo_clase: nuevoCodigo }
    }
    mostrarSnack(`Código ${nuevoCodigo} creado. El alumnado puede acceder desde /unirse`)
  } catch (e) {
    errorCodigo.value = { ...errorCodigo.value, [encuentro.id]: e.response?.data?.error || 'Error al crear el código.' }
  } finally {
    creandoCodigo.value = { ...creandoCodigo.value, [encuentro.id]: false }
  }
}

async function copiarCodigo(codigo) {
  try {
    await navigator.clipboard.writeText(codigo)
    mostrarSnack(`Código ${codigo} copiado.`)
  } catch {
    mostrarSnack(`Código: ${codigo}`)
  }
}

// ─── Stats ────────────────────────────────────────────────────────────────────
const stats = computed(() => {
  const microretos = new Set(encuentros.value.map(s => s.microreto_id).filter(Boolean))
  const centros    = new Set(encuentros.value.map(s => s.centro_educativo).filter(Boolean))
  return {
    total:      encuentros.value.length,
    microretos: microretos.size,
    centros:    centros.size,
  }
})

// ─── Utils ────────────────────────────────────────────────────────────────────
function formatFecha(isoDate) {
  if (!isoDate) return ''
  const d = new Date(isoDate + 'T12:00:00')
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="min-h-screen font-sans text-[#1F2937] pt-12 md:pt-12">

    <!-- Modal ¿Qué necesitas? -->
    <BienvenidaModal :show="guiaBienvenida" @seleccionar="seleccionarOpcionBienvenida" />

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                bg-[#00A859] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <div class="relative z-10 max-w-5xl mx-auto px-4 py-8 md:px-8 md:py-12">

      <!-- ─── Cabecera ─────────────────────────────────────────────────────── -->
      <header class="mb-8">
        <!-- Volver -->
        <button @click="router.push('/dashboard')"
                class="inline-flex items-center gap-2 mb-5 text-[10px] font-black uppercase tracking-widest
                       text-gray-400 hover:text-[#00A859] transition-colors group">
          <svg class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Volver al dashboard
        </button>

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
          <div>
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full
                        bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="w-2 h-2 rounded-full bg-[#00A859]" />
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Dashboard docente</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black tracking-tight text-[#121212]">
              Encuentros <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">registrados</span>
            </h1>
            <p class="text-gray-500 text-sm mt-1">Consulta y filtra todo el historial de encuentros.</p>
          </div>

          <!-- Stats + Acciones -->
          <div class="flex flex-wrap gap-3 items-center">
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-2xl border border-gray-100 shadow-sm">
              <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                         M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
              <span class="font-black text-xl text-[#1F2937]">{{ stats.total }}</span>
              <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">encuentros</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-2xl border border-gray-100 shadow-sm">
              <svg class="w-4 h-4 text-[#99CC33]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
              </svg>
              <span class="font-black text-xl text-[#1F2937]">{{ stats.microretos }}</span>
              <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">retos</span>
            </div>
            <!-- Botón Crear nuevo encuentro -->
            <button
              @click="router.push('/dashboard')"
              class="flex items-center gap-2 px-4 py-2 bg-[#00A859] rounded-2xl border border-[#00A859]
                     shadow-sm text-white text-xs font-black uppercase tracking-wider
                     hover:bg-[#009950] hover:border-[#009950] transition-all"
              title="Ir al formulario para registrar un nuevo encuentro"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
              </svg>
              Nuevo encuentro
            </button>
            <!-- Botón Papelera -->
            <button
              @click="router.push({ name: 'papelera' })"
              class="flex items-center gap-2 px-4 py-2 bg-amber-50 rounded-2xl border border-amber-200
                     shadow-sm text-amber-600 text-xs font-black uppercase tracking-wider
                     hover:bg-amber-100 hover:border-amber-300 transition-all"
              title="Ver encuentros eliminados en la papelera"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
              Papelera
            </button>
          </div>
        </div>
      </header>

      <!-- ─── Filtros ──────────────────────────────────────────────────────── -->
      <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
          <p class="text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">Filtrar encuentros</p>
          <button v-if="hayFiltrosActivos"
                  @click="limpiarFiltros"
                  class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-red-400 transition-colors">
            Limpiar filtros
          </button>
        </div>

        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

          <!-- Búsqueda por título -->
          <div class="lg:col-span-2">
            <label class="field-label">Buscar por título de reto</label>
            <div class="relative">
              <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300 pointer-events-none"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
              </svg>
              <input v-model="filtros.titulo" type="text"
                     placeholder="Buscar por título..."
                     class="field-input pl-10" />
            </div>
          </div>

          <!-- Centro -->
          <div>
            <label class="field-label">Centro educativo</label>
            <select v-model="filtros.centro" class="field-input">
              <option value="">Todos los centros</option>
              <option v-for="c in encuentrosUnicos.centros" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Fecha desde -->
          <div>
            <label class="field-label">Desde</label>
            <input v-model="filtros.desde" type="date" class="field-input" />
          </div>

          <!-- Fecha hasta -->
          <div>
            <label class="field-label">Hasta</label>
            <input v-model="filtros.hasta" type="date" class="field-input" />
          </div>

          <!-- Ciclo -->
          <div>
            <label class="field-label">Ciclo formativo</label>
            <input v-model="filtros.ciclo" type="text"
                   placeholder="Filtrar por ciclo..."
                   class="field-input" />
          </div>

          <!-- Curso -->
          <div>
            <label class="field-label">Curso</label>
            <div class="flex gap-2 mt-1">
              <button v-for="c in ['', '1º', '2º']" :key="c"
                      @click="filtros.curso = c"
                      class="flex-1 py-2 rounded-xl text-xs font-black uppercase tracking-widest border transition-all"
                      :class="filtros.curso === c
                        ? 'bg-[#00A859] border-[#00A859] text-white'
                        : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-[#00A859]/40 hover:text-[#00A859]'">
                {{ c === '' ? 'Todos' : c }}
              </button>
            </div>
          </div>

          <!-- Grupo -->
          <div>
            <label class="field-label">Grupo</label>
            <div class="flex gap-1.5 mt-1">
              <button v-for="g in ['', 'A', 'B', 'C', 'D']" :key="g"
                      @click="filtros.grupo = g"
                      class="flex-1 py-2 rounded-xl text-xs font-black uppercase border transition-all"
                      :class="filtros.grupo === g
                        ? 'bg-[#99CC33] border-[#99CC33] text-white'
                        : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-[#99CC33]/40 hover:text-[#99CC33]'">
                {{ g === '' ? 'Todos' : g }}
              </button>
            </div>
          </div>

        </div>

        <!-- Contador de resultados -->
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-100">
          <p class="text-[10px] font-bold text-gray-400">
            <span v-if="hayFiltrosActivos">
              {{ encuentrosFiltrados.length }} {{ encuentrosFiltrados.length === 1 ? 'encuentro encontrado' : 'encuentros encontrados' }}
              <span class="text-gray-300">de {{ stats.total }} totales</span>
            </span>
            <span v-else>{{ stats.total }} {{ stats.total === 1 ? 'encuentro' : 'encuentros' }} en total</span>
          </p>
        </div>
      </div>

      <!-- ─── Grid de encuentros ──────────────────────────────────────────────── -->

      <!-- Cargando -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-24">
        <svg class="animate-spin w-8 h-8 text-[#00A859] mb-3" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="text-[#00A859] font-black tracking-widest uppercase text-xs animate-pulse">Cargando encuentros…</p>
      </div>

      <!-- Estado vacío global -->
      <div v-else-if="encuentros.length === 0"
           class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm px-8 py-16 text-center">
        <div class="w-16 h-16 rounded-full bg-gray-50 border border-gray-100
                    flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
          </svg>
        </div>
        <p class="text-sm text-gray-400 font-medium mb-4">Aún no hay encuentros registrados.</p>
        <button @click="router.push('/dashboard')"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#00A859] text-white
                       text-xs font-black uppercase tracking-widest hover:bg-[#00A859]/90 transition-all">
          Registrar primer encuentro
        </button>
      </div>

      <!-- Sin resultados con filtros -->
      <div v-else-if="encuentrosFiltrados.length === 0"
           class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm px-8 py-12 text-center">
        <svg class="w-8 h-8 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <p class="text-sm text-gray-400 font-medium mb-3">Sin encuentros para esos filtros.</p>
        <button @click="limpiarFiltros"
                class="text-xs font-black uppercase tracking-widest text-[#00A859] hover:underline">
          Limpiar filtros
        </button>
      </div>

      <!-- Acordeón por centro educativo -->
      <div v-else class="space-y-3">
        <div v-for="centro in centrosOrdenados" :key="centro"
             class="bg-white rounded-[1.75rem] border border-gray-100 shadow-sm overflow-hidden">

          <!-- Cabecera del centro -->
          <button @click="toggleCentro(centro)"
                  class="w-full flex items-center gap-4 px-6 py-5
                         hover:bg-gray-50/80 transition-colors duration-150 text-left">
            <div class="w-10 h-10 rounded-2xl bg-[#1F2937] flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-[#99CC33]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h2 class="font-black text-base text-[#1F2937] truncate">{{ centro }}</h2>
              <p class="text-xs text-gray-400 font-medium mt-0.5">
                {{ encuentrosAgrupados[centro].length }}
                {{ encuentrosAgrupados[centro].length === 1 ? 'encuentro' : 'encuentros' }}
              </p>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-300 shrink-0"
                 :class="centrosExpandidos.has(centro) ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <!-- Encuentros del centro -->
          <div v-if="centrosExpandidos.has(centro)"
               class="border-t border-gray-100 px-5 py-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="s in encuentrosAgrupados[centro]" :key="s.id"
                   class="bg-[#F8FAFC] rounded-[1.25rem] border border-gray-100
                          hover:border-[#00A859]/30 hover:shadow-sm transition-all group cursor-pointer"
                   @click="verEncuentro(s)">

                <!-- Card header -->
                <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-black text-[#1F2937] leading-snug line-clamp-2
                                group-hover:text-[#00A859] transition-colors">
                        {{ s.proyecto_titulo || '(sin título)' }}
                      </p>
                      <p class="text-[10px] font-bold text-[#00A859] mt-1">
                        {{ formatFecha(s.fecha) }}
                      </p>
                    </div>
                    <!-- Acciones en hover -->
                    <div class="flex gap-1 flex-shrink-0">
                      <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
                        <button @click.stop="crearMicroproyecto(s.id)"
                                class="p-1.5 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-100 transition-all"
                                title="Trabajar reto (crear propuesta)">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 17l10 5 10-5"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12l10 5 10-5"/>
                          </svg>
                        </button>
                        <button v-if="s.microreto_id"
                                @click.stop="abrirMicroretoModal(s.microreto_id)"
                                class="p-1.5 rounded-lg bg-[#00A859]/10 text-[#00A859] hover:bg-[#00A859]/20 transition-all"
                                title="Ver ficha del reto">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                                     a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                          </svg>
                        </button>
                      </div>
                      <button @click.stop="abrirModalEliminar(s)"
                              class="p-1.5 rounded-lg hover:bg-red-50 text-gray-300 hover:text-red-400 transition-all"
                              title="Eliminar encuentro">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Card body -->
                <div class="px-5 py-4 space-y-2">
                  <div class="flex flex-wrap gap-1.5">
                    <span v-if="s.curso"       class="tag tag-green">{{ s.curso }}</span>
                    <span v-if="s.grupo"       class="tag tag-lime">Grupo {{ s.grupo }}</span>
                    <span v-if="s.num_alumnos" class="tag tag-gray">{{ s.num_alumnos }} alumnos</span>
                    <span v-if="s.num_equipos" class="tag tag-gray">{{ s.num_equipos }} equipos</span>
                  </div>
                  <div class="space-y-1">
                    <p v-if="s.ciclo_formativo" class="text-[10px] text-gray-500 flex items-center gap-1.5">
                      <svg class="w-3 h-3 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                      </svg>
                      <span class="truncate">{{ s.ciclo_formativo }}</span>
                    </p>
                    <p v-if="s.notas" class="text-[10px] text-gray-400 leading-relaxed line-clamp-2 italic">
                      "{{ s.notas }}"
                    </p>
                  </div>

                  <!-- Código de acceso del alumnado -->
                  <div v-if="s.codigo_clase" @click.stop
                       class="flex items-center gap-2 pt-1">
                    <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-full
                                 bg-[#00A859]/10 border border-[#00A859]/20">
                      <span class="w-1.5 h-1.5 rounded-full bg-[#00A859] animate-pulse shrink-0"></span>
                      <span class="text-[11px] font-black tracking-widest text-[#00A859]">{{ s.codigo_clase }}</span>
                    </span>
                    <button @click.stop="copiarCodigo(s.codigo_clase)"
                            class="p-1 rounded-lg hover:bg-gray-100 text-gray-300 hover:text-gray-500 transition-all"
                            title="Copiar código">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                      </svg>
                    </button>
                  </div>
                  <div v-else-if="s.num_equipos" @click.stop class="pt-1">
                    <button @click.stop="crearCodigo(s)"
                            :disabled="creandoCodigo[s.id]"
                            class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest
                                   text-gray-400 hover:text-[#00A859] transition-colors disabled:opacity-50">
                      <svg class="w-3 h-3" :class="creandoCodigo[s.id] ? 'animate-spin' : ''"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="!creandoCodigo[s.id]" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        <path v-else fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                      </svg>
                      {{ creandoCodigo[s.id] ? 'Creando...' : 'Generar código alumnado' }}
                    </button>
                    <p v-if="errorCodigo[s.id]" class="text-[10px] text-red-400 mt-1">{{ errorCodigo[s.id] }}</p>
                  </div>
                </div>

              </div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- MODAL DETALLE DE ENCUENTRO                                                -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="encuentroAbierto"
           class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click="cerrarEncuentro"
             class="absolute inset-0 bg-black/40 backdrop-blur-sm" />

        <div class="relative bg-white rounded-[1.75rem] shadow-2xl max-w-lg w-full overflow-hidden
                    border border-gray-100">
          <!-- Cabecera -->
          <div class="px-7 pt-7 pb-5 border-b border-gray-100">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2 h-2 rounded-full bg-[#00A859] flex-shrink-0" />
                  <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#00A859]">
                    Encuentro · {{ formatFecha(encuentroAbierto.fecha) }}
                  </p>
                </div>
                <h2 class="text-lg font-black text-[#1F2937] leading-snug">
                  {{ encuentroAbierto.proyecto_titulo || '(sin título)' }}
                </h2>
              </div>
              <button @click="cerrarEncuentro"
                      class="flex-shrink-0 p-2 rounded-xl hover:bg-gray-100 text-gray-400
                             hover:text-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="flex flex-wrap gap-1.5 mt-3">
              <span v-if="encuentroAbierto.curso"       class="tag tag-green">{{ encuentroAbierto.curso }}</span>
              <span v-if="encuentroAbierto.grupo"       class="tag tag-lime">Grupo {{ encuentroAbierto.grupo }}</span>
              <span v-if="encuentroAbierto.num_alumnos" class="tag tag-gray">{{ encuentroAbierto.num_alumnos }} alumnos</span>
            </div>
          </div>

          <!-- Cuerpo -->
          <div class="px-7 py-5 space-y-4">
            <div class="grid grid-cols-2 gap-3">
              <div v-if="encuentroAbierto.centro_educativo"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Centro</p>
                <p class="text-sm font-bold text-[#1F2937] leading-snug">{{ encuentroAbierto.centro_educativo }}</p>
              </div>
              <div v-if="encuentroAbierto.ciclo_formativo"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Ciclo</p>
                <p class="text-sm font-bold text-[#1F2937] leading-snug">{{ encuentroAbierto.ciclo_formativo }}</p>
              </div>
              <div v-if="encuentroAbierto.fecha"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Fecha</p>
                <p class="text-sm font-bold text-[#1F2937]">{{ formatFecha(encuentroAbierto.fecha) }}</p>
              </div>
              <div v-if="encuentroAbierto.microproyecto_uuid"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Fecha fin</p>
                <template v-if="!editandoFechaFin">
                  <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-bold text-[#1F2937]">{{ encuentroAbierto.fecha_fin ? formatFecha(encuentroAbierto.fecha_fin) : 'Sin definir' }}</p>
                    <button @click="empezarEditarFechaFin" class="text-[10px] font-black uppercase text-[#00A859] hover:underline shrink-0">Editar</button>
                  </div>
                </template>
                <template v-else>
                  <input v-model="fechaFinInput" type="date" class="field-input !py-1.5 text-sm" />
                  <div class="flex items-center gap-2 mt-1.5">
                    <button @click="guardarFechaFin" :disabled="guardandoFechaFin"
                            class="text-[10px] font-black uppercase text-[#00A859] disabled:opacity-50">{{ guardandoFechaFin ? 'Guardando…' : 'Guardar' }}</button>
                    <button @click="editandoFechaFin = false" class="text-[10px] font-black uppercase text-gray-400">Cancelar</button>
                  </div>
                  <p v-if="errorFechaFin" class="text-[10px] text-red-500 mt-1">{{ errorFechaFin }}</p>
                </template>
              </div>
              <div v-if="encuentroAbierto.num_alumnos"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Alumnos</p>
                <p class="text-sm font-bold text-[#1F2937]">{{ encuentroAbierto.num_alumnos }}</p>
              </div>
            </div>
            <div v-if="encuentroAbierto.notas"
                 class="p-4 rounded-xl bg-[#F8FAFC] border border-gray-100">
              <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Notas adicionales</p>
              <p class="text-sm text-[#1F2937] leading-relaxed">{{ encuentroAbierto.notas }}</p>
            </div>
          </div>

          <!-- Código de acceso del alumnado en el modal -->
          <div class="px-7 py-4 border-t border-gray-100">
            <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-3">Acceso del alumnado</p>
            <div v-if="encuentroAbierto.codigo_clase"
                 class="flex items-center gap-3 p-3 rounded-2xl bg-[#00A859]/5 border border-[#00A859]/15">
              <span class="w-2 h-2 rounded-full bg-[#00A859] animate-pulse shrink-0"></span>
              <span class="text-lg font-black tracking-[0.2em] text-[#1F2937] flex-1">{{ encuentroAbierto.codigo_clase }}</span>
              <button @click="copiarCodigo(encuentroAbierto.codigo_clase)"
                      class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#00A859] text-white
                             text-[10px] font-black uppercase tracking-widest hover:bg-[#00A859]/90 transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Copiar
              </button>
              <button @click="crearCodigo(encuentroAbierto)" :disabled="creandoCodigo[encuentroAbierto.id]"
                      class="px-3 py-1.5 rounded-xl bg-gray-100 border border-gray-200
                             text-[10px] font-black uppercase tracking-widest text-gray-500
                             hover:bg-gray-200 transition-all disabled:opacity-50">
                Regen.
              </button>
            </div>
            <div v-else>
              <button @click="crearCodigo(encuentroAbierto)" :disabled="creandoCodigo[encuentroAbierto.id]"
                      class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl
                             border border-dashed border-[#00A859]/30 text-[#00A859] text-xs font-black
                             uppercase tracking-widest hover:bg-[#00A859]/5 transition-all disabled:opacity-50">
                <svg class="w-4 h-4" :class="creandoCodigo[encuentroAbierto.id] ? 'animate-spin' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="!creandoCodigo[encuentroAbierto.id]" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                  <path v-else fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                </svg>
                {{ creandoCodigo[encuentroAbierto.id] ? 'Creando...' : 'Generar código para el alumnado' }}
              </button>
              <p v-if="errorCodigo[encuentroAbierto.id]" class="text-xs text-red-400 mt-1.5 text-center">
                {{ errorCodigo[encuentroAbierto.id] }}
              </p>
            </div>
          </div>

          <!-- Pie -->
          <div class="px-7 py-4 bg-[#F8FAFC] border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <button @click="abrirModalEliminar(encuentroAbierto)"
                    class="text-xs text-gray-400 hover:text-red-400 font-black uppercase
                           tracking-widest transition-colors">
              Eliminar encuentro
            </button>
            <div class="flex items-center gap-2">
              <button v-if="encuentroAbierto.microproyecto_uuid"
                      @click="router.push({ name: 'workspace-docente', params: { id: encuentroAbierto.id } }); cerrarEncuentro()"
                      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-blue-200
                             bg-blue-50 text-blue-700 text-xs font-black uppercase tracking-widest
                             hover:bg-blue-100 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                </svg>
                Workspace
              </button>
              <button v-if="encuentroAbierto.microproyecto_uuid"
                      @click="abrirModalProyecto(encuentroAbierto.microproyecto_uuid); cerrarEncuentro()"
                      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200
                             bg-white text-gray-600 text-xs font-black uppercase tracking-widest
                             hover:border-[#00A859]/40 hover:text-[#00A859] transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                </svg>
                Ver propuesta/proyecto
              </button>
              <button @click="crearMicroproyecto(encuentroAbierto.id); cerrarEncuentro()"
                      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#00A859] text-white
                             text-xs font-black uppercase tracking-widest hover:bg-[#00A859]/90
                             transition-all shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 2L2 7l10 5 10-5-10-5z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 17l10 5 10-5"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12l10 5 10-5"/>
                </svg>
                Trabajar reto
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Modal ficha de microreto -->
  <MicroretoModal :microreto-id="microretoModalId" @close="cerrarMicroretoModal" />

  <!-- Modal ver proyecto -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="modalVerProyecto"
           class="fixed inset-0 z-[80] flex items-center justify-center p-4"
           @click.self="cerrarModalProyecto">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cerrarModalProyecto" />

        <div class="relative z-10 bg-white rounded-3xl shadow-2xl w-full max-w-2xl
                    max-h-[90vh] flex flex-col overflow-hidden">

          <!-- Cabecera -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-7 h-7 rounded-xl bg-[#99CC33]/15 border border-[#99CC33]/20
                          flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5 text-[#5a7a00]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <p v-if="proyectoModal" class="font-black text-[#1F2937] text-sm truncate">
                {{ proyectoModal.titulo }}
              </p>
              <p v-else class="text-sm text-gray-400 font-medium">Cargando proyecto…</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button v-if="proyectoModal"
                      @click="router.push({ name: 'startup-day-detalle', params: { uuid: proyectoModal.uuid } }); cerrarModalProyecto()"
                      class="px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200
                             text-[10px] font-black uppercase tracking-widest text-gray-500
                             hover:border-[#99CC33] hover:text-[#5a7a00] transition-all flex items-center gap-1.5">
                Startup Day
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
              </button>
              <button @click="cerrarModalProyecto"
                      class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center
                             text-gray-400 hover:bg-gray-200 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Contenido -->
          <div class="flex-1 overflow-y-auto">

            <!-- Spinner -->
            <div v-if="cargandoModal" class="flex justify-center items-center py-20">
              <svg class="animate-spin w-8 h-8 text-[#99CC33]" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
            </div>

            <template v-else-if="proyectoModal">

              <!-- Badge estado + meta -->
              <div class="px-6 pt-5 pb-4 border-b border-gray-50 space-y-3">
                <span :class="['inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full border', getBadgeModal(proyectoModal).cls]">
                  <span :class="['w-1.5 h-1.5 rounded-full', getBadgeModal(proyectoModal).dot]" />
                  {{ getBadgeModal(proyectoModal).label }}
                </span>

                <div class="flex flex-wrap gap-1.5">
                  <span v-if="proyectoModal.empresa_nombre" class="tag tag-gray">
                    <svg class="w-3 h-3 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    {{ proyectoModal.empresa_nombre }}
                  </span>
                  <span v-if="proyectoModal.centro_nombre" class="tag tag-gray">{{ proyectoModal.centro_nombre }}</span>
                  <span v-if="proyectoModal.ciclo_nombre"  class="tag tag-gray">{{ proyectoModal.ciclo_nombre }}</span>
                  <span v-if="proyectoModal.curso"         class="tag tag-lime">{{ proyectoModal.curso }}</span>
                </div>
              </div>

              <!-- Secciones del proyecto -->
              <div class="px-6 py-5 space-y-4">

                <div v-if="proyectoModal.diseno_reto?.descripcion || proyectoModal.diseno_reto?.pregunta_reto"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">El reto</p>
                  <p v-if="proyectoModal.diseno_reto.pregunta_reto"
                     class="text-sm font-bold text-[#5a7a00] italic mb-2">
                    "{{ proyectoModal.diseno_reto.pregunta_reto }}"
                  </p>
                  <p v-if="proyectoModal.diseno_reto.descripcion"
                     class="text-sm text-gray-600 leading-relaxed line-clamp-4">
                    {{ proyectoModal.diseno_reto.descripcion }}
                  </p>
                </div>

                <div v-if="proyectoModal.equipo?.alumnos?.length"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                    Equipo ({{ proyectoModal.equipo.alumnos.length }})
                  </p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="a in proyectoModal.equipo.alumnos" :key="a.nombre"
                          class="text-xs bg-white border border-gray-200 px-2.5 py-1 rounded-full text-gray-600">
                      {{ a.nombre }}<span v-if="a.rol" class="text-gray-400"> · {{ a.rol }}</span>
                    </span>
                  </div>
                </div>

                <div v-if="proyectoModal.modulos_seleccionados?.length"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                    Módulos ({{ proyectoModal.modulos_seleccionados.length }})
                  </p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="m in proyectoModal.modulos_seleccionados" :key="m.id"
                          class="text-xs bg-[#00A859]/8 border border-[#00A859]/15 text-[#00A859]
                                 px-2.5 py-1 rounded-full">
                      {{ m.nombre }}
                    </span>
                  </div>
                </div>

                <div v-if="proyectoModal.objetivos?.lista?.length"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Objetivos</p>
                  <ul class="space-y-1">
                    <li v-for="obj in proyectoModal.objetivos.lista.slice(0, 5)" :key="obj"
                        class="flex items-start gap-2 text-sm text-gray-600">
                      <span class="text-[#99CC33] shrink-0 mt-0.5 font-bold">›</span> {{ obj }}
                    </li>
                    <li v-if="proyectoModal.objetivos.lista.length > 5"
                        class="text-xs text-gray-400 pl-4">
                      + {{ proyectoModal.objetivos.lista.length - 5 }} más…
                    </li>
                  </ul>
                </div>

                <div v-if="proyectoModal.diseno_microproyecto?.fases?.length"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                    Fases ({{ proyectoModal.diseno_microproyecto.fases.length }})
                  </p>
                  <ol class="space-y-1.5">
                    <li v-for="(f, i) in proyectoModal.diseno_microproyecto.fases.slice(0, 4)" :key="i"
                        class="flex items-center gap-2.5 text-sm">
                      <span class="w-5 h-5 rounded-full bg-[#99CC33]/15 text-[#5a7a00] font-black text-[10px]
                                   flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                      <span class="font-bold text-[#1F2937]">{{ f.nombre }}</span>
                      <span v-if="duracionPorFase(proyectoModal.diseno_microproyecto.clases, i)" class="text-gray-400 text-xs">
                        · {{ duracionPorFase(proyectoModal.diseno_microproyecto.clases, i) }} clase(s)
                      </span>
                    </li>
                    <li v-if="proyectoModal.diseno_microproyecto.fases.length > 4"
                        class="text-xs text-gray-400 pl-7">
                      + {{ proyectoModal.diseno_microproyecto.fases.length - 4 }} más…
                    </li>
                  </ol>
                </div>

                <div v-if="!proyectoModal.diseno_reto?.descripcion && !proyectoModal.equipo?.alumnos?.length
                            && !proyectoModal.modulos_seleccionados?.length && !proyectoModal.objetivos?.lista?.length"
                     class="text-center py-6 text-gray-400">
                  <p class="text-xs">Este proyecto aún no tiene secciones rellenas.</p>
                  <button @click="router.push({ name: 'startup-day-editar', params: { uuid: proyectoModal.uuid } }); cerrarModalProyecto()"
                          class="mt-3 text-xs font-black text-[#5a7a00] underline hover:no-underline">
                    Ir a editar →
                  </button>
                </div>

              </div>
            </template>

            <!-- Error carga -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
              <p class="text-sm">No se pudo cargar el proyecto.</p>
            </div>

          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Modal eliminar encuentro -->
  <EliminarEncuentroModal
    :visible="modalEliminarVisible"
    :encuentro="encuentroAEliminar"
    @encuentro-eliminado="onEncuentroEliminado"
    @cerrar="cerrarModalEliminar"
  />

  <!-- Snackbar -->
  <Transition name="ses-snack">
    <div
      v-if="snackbar.visible"
      class="fixed bottom-6 right-6 z-[60] flex items-center gap-3
             px-5 py-3.5 rounded-2xl shadow-xl text-sm font-bold
             max-w-sm bg-white text-[#1F2937] border border-gray-200 shadow-lg"
    >
      <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      <span class="flex-1">{{ snackbar.mensaje }}</span>
      <button
        v-if="snackbar.accion"
        @click="snackbar.accion.fn(); snackbar.visible = false"
        class="ml-1 shrink-0 px-3 py-1.5 rounded-xl bg-amber-400 text-[#1F2937] text-[10px] font-black uppercase tracking-widest hover:bg-amber-300 transition-all"
      >
        {{ snackbar.accion.label }}
      </button>
    </div>
  </Transition>

</template>

<style scoped>
.field-label {
  display: block;
  font-size: 0.6875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #6B7280;
  margin-bottom: 0.375rem;
}
.field-input {
  width: 100%;
  background: #F9FAFB;
  border: 1px solid #E5E7EB;
  border-radius: 0.75rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #1F2937;
  outline: none;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}
.field-input:focus {
  border-color: #00A859;
  box-shadow: 0 0 0 3px rgba(0, 168, 89, 0.12);
  background: #fff;
}
.field-input::placeholder { color: #D1D5DB; font-weight: 400; }
select.field-input { cursor: pointer; }
.tag {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  font-size: 0.625rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.tag-gray  { background: #F3F4F6; color: #6B7280; }
.tag-green { background: rgba(0,168,89,0.1); color: #00A859; }
.tag-lime  { background: rgba(153,204,51,0.12); color: #5a7a00; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 200ms ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .relative { transform: scale(0.95) translateY(8px); opacity: 0; }

.ses-snack-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.ses-snack-leave-active { transition: all 0.2s ease-in; }
.ses-snack-enter-from   { opacity: 0; transform: translateY(12px); }
.ses-snack-leave-to     { opacity: 0; transform: translateY(8px); }
</style>
