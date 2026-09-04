<!-- Ruta: /panel-docente (name: inicio-docente). Antes vivía en /inicio-docente — ver router/index.js. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import api from '../api.js'
import CatalogoBoeModal from '../components/CatalogoBoeModal.vue'
import { noticiasDualab, novedadesPlataforma } from '../data/noticiasMock.js'

const router    = useRouter()
const authStore = useAuthStore()
const isLoaded  = ref(false)

// ── Datos ─────────────────────────────────────────────────────────────────────
const encuentros          = ref([])
const proyectos         = ref([])
const cargandoEncuentros  = ref(true)
const cargandoProyectos = ref(true)

// ── Listas derivadas ──────────────────────────────────────────────────────────
const ultimosEncuentros = computed(() =>
  [...encuentros.value]
    .filter(s => s.fecha)
    .sort((a, b) => _pf(b.fecha) - _pf(a.fecha))
    .slice(0, 3)
)

const ultimosProyectosEnCurso = computed(() =>
  proyectos.value
    .filter(p => p.estado === 'validado')
    .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
    .slice(0, 4)
)

const proyectosValidados = computed(() =>
  proyectos.value.filter(p => p.estado === 'validado')
)

const proyectosPendientes = computed(() =>
  proyectos.value.filter(p => p.estado === 'propuesta' && !p.empresa_validado)
)

const proyectosEnEdicion = computed(() =>
  proyectos.value.filter(p => p.estado === 'en_edicion')
)

const hayProyectos = computed(() =>
  proyectosValidados.value.length > 0 ||
  proyectosPendientes.value.length > 0 ||
  proyectosEnEdicion.value.length > 0
)

// ── Donut progreso proyectos ───────────────────────────────────────────────────
const DONUT_C = 2 * Math.PI * 38 // circunferencia con r=38

const donutSegmentos = computed(() => {
  const all = proyectos.value
  if (!all.length) return []

  const total = all.length
  const grupos = [
    { label: 'Validados',                      valor: all.filter(p => p.estado === 'validado').length,                                                                      color: '#00A859' },
    { label: 'Esperando respuesta de empresa', valor: all.filter(p => p.enviado_a_empresa_mail && p.estado === 'propuesta' && !p.empresa_no_valida_aun).length,            color: '#3B82F6' },
    { label: 'Pendiente de enviar a empresa',  valor: all.filter(p => p.estado === 'propuesta' && !p.enviado_a_empresa_mail && !p.empresa_no_valida_aun).length,           color: '#6366F1' },
    { label: 'Respuesta de empresa a revisar', valor: all.filter(p => p.empresa_no_valida_aun && p.estado === 'propuesta').length,                                         color: '#F59E0B' },
    { label: 'En edición',                     valor: all.filter(p => p.estado === 'en_edicion').length,                                                                   color: '#D1D5DB' },
  ].filter(g => g.valor > 0)

  let acum = 0
  return grupos.map(g => {
    const dash = (g.valor / total) * DONUT_C
    const rotacion = (acum / total) * 360
    acum += g.valor
    return { ...g, dash, gap: DONUT_C - dash, rotacion }
  })
})

const _pf = (s) => s ? (s.includes('T') ? new Date(s) : new Date(s + 'T12:00:00')) : null

function formatFecha(isoDate) {
  if (!isoDate) return ''
  const d = _pf(isoDate)
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' })
}

const primerNombre = computed(() => {
  const n = authStore.userName || ''
  return n.split(' ')[0] || n
})

const userCentroNombre = computed(() => authStore.userCentroNombre || '')
const userCentroImg    = computed(() => authStore.userCentroImg || '')

// Preview de las noticias — el listado completo vive en NoticiasListado.vue,
// ambas vistas comparten la misma fuente de datos (ver src/data/noticiasMock.js).
const previewNoticiasDualab = noticiasDualab.slice(0, 3)
const previewNovedadesPlataforma = novedadesPlataforma.slice(0, 3)

function abrirNoticias(tipo) {
  router.push({ name: 'noticias-listado', params: { tipo } })
}

// ── Carga ──────────────────────────────────────────────────────────────────────
onMounted(() => {
  setTimeout(() => { isLoaded.value = true }, 100)
  cargarEncuentros()
  cargarProyectos()
})

async function cargarEncuentros() {
  try {
    const { data } = await api.get('/encuentros')
    encuentros.value = data
  } catch { /* silencioso */ } finally {
    cargandoEncuentros.value = false
  }
}

async function cargarProyectos() {
  try {
    const { data } = await api.get('/startup/proyectos')
    proyectos.value = data
  } catch { /* silencioso */ } finally {
    cargandoProyectos.value = false
  }
}

// ── Navegación ─────────────────────────────────────────────────────────────────
const irA = (path) => router.push(path)
const irAStartupFiltrado = (filtro) => router.push({ path: '/proyectos', query: { filtro } })

// ── Calendario ─────────────────────────────────────────────────────────────────
const calendarDate = ref(new Date())

const calendarMonthLabel = computed(() =>
  calendarDate.value.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })
)

const calendarDays = computed(() => {
  const year  = calendarDate.value.getFullYear()
  const month = calendarDate.value.getMonth()
  const firstWeekday = new Date(year, month, 1).getDay()
  const daysInMonth  = new Date(year, month + 1, 0).getDate()
  const offset = (firstWeekday + 6) % 7 // semana empieza en lunes
  const days = Array(offset).fill(null)
  for (let d = 1; d <= daysInMonth; d++) days.push(d)
  return days
})

const _today = new Date()
const isToday = (day) =>
  day === _today.getDate() &&
  calendarDate.value.getMonth() === _today.getMonth() &&
  calendarDate.value.getFullYear() === _today.getFullYear()

function prevMonth() {
  const d = new Date(calendarDate.value); d.setDate(1); d.setMonth(d.getMonth() - 1)
  calendarDate.value = d
}
function nextMonth() {
  const d = new Date(calendarDate.value); d.setDate(1); d.setMonth(d.getMonth() + 1)
  calendarDate.value = d
}

// ── Eventos del calendario ─────────────────────────────────────────────────────
const eventos       = ref([])
const selectedDate  = ref(null)
const newEventText  = ref('')
const newEventColor = ref('#00A859')
const eventColors   = ['#00A859', '#99CC33', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6']

function selectCalDay(day) {
  if (!day) return
  const m = calendarDate.value.getMonth()
  const y = calendarDate.value.getFullYear()
  if (selectedDate.value?.day === day && selectedDate.value?.month === m && selectedDate.value?.year === y) {
    selectedDate.value = null
  } else {
    selectedDate.value = { day, month: m, year: y }
    newEventText.value = ''
  }
}

function addEvento() {
  if (!newEventText.value.trim() || !selectedDate.value) return
  eventos.value.push({ ...selectedDate.value, text: newEventText.value.trim(), color: newEventColor.value })
  newEventText.value = ''
  selectedDate.value = null
}

function removeEvento(i) { eventos.value.splice(i, 1) }

const eventosDelMes = computed(() =>
  eventos.value.filter(e =>
    e.month === calendarDate.value.getMonth() && e.year === calendarDate.value.getFullYear()
  )
)
const dayEvents = (day) =>
  eventos.value.filter(e =>
    e.day === day && e.month === calendarDate.value.getMonth() && e.year === calendarDate.value.getFullYear()
  )
const isDaySelected = (day) =>
  selectedDate.value?.day === day &&
  selectedDate.value?.month === calendarDate.value.getMonth() &&
  selectedDate.value?.year  === calendarDate.value.getFullYear()

// ── Encuentros en el calendario ──────────────────────────────────────────────────
const encuentrosDelDia = (day) => {
  if (!day) return []
  const y = calendarDate.value.getFullYear()
  const m = calendarDate.value.getMonth()
  return encuentros.value.filter(s => {
    if (!s.fecha) return false
    const d = _pf(s.fecha)
    return d.getFullYear() === y && d.getMonth() === m && d.getDate() === day
  })
}

const encuentrosDelMes = computed(() =>
  encuentros.value
    .filter(s => {
      if (!s.fecha) return false
      const d = _pf(s.fecha)
      return d.getFullYear() === calendarDate.value.getFullYear() &&
             d.getMonth()    === calendarDate.value.getMonth()
    })
    .sort((a, b) => _pf(a.fecha) - _pf(b.fecha))
)

// Día del calendario sobre el que está el cursor
const hoveredDay = ref(null)

const hexToRgba = (hex, alpha = 0.13) => {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r},${g},${b},${alpha})`
}

// Comprueba si un encuentro debe destacarse porque su día coincide con hoveredDay
// y el calendario está en el mismo mes/año que el encuentro
const isSessionHovered = (s) => {
  if (hoveredDay.value === null || !s.fecha) return false
  const d = _pf(s.fecha)
  return d.getDate() === hoveredDay.value &&
         d.getMonth() === calendarDate.value.getMonth() &&
         d.getFullYear() === calendarDate.value.getFullYear()
}

const setHoveredFromSession = (s) => {
  hoveredDay.value = s.fecha ? _pf(s.fecha).getDate() : null
}

// Color de fondo transparente de la celda del día según sus eventos
const dayAccentBg = (day) => {
  if (!day) return {}
  const ses = encuentrosDelDia(day)
  if (ses.length) return { backgroundColor: 'rgba(59,130,246,0.10)' }
  const evs = dayEvents(day)
  if (evs.length) return { backgroundColor: hexToRgba(evs[0].color, 0.12) }
  return { backgroundColor: 'rgba(243,244,246,1)' } // gray-50 neutro
}

// Lista combinada encuentros + eventos personales del mes, ordenada por día
const todoDelMes = computed(() => {
  const evs = eventosDelMes.value.map(e => ({ tipo: 'evento', dia: e.day, label: e.text, color: e.color, _ref: e }))
  const ses = encuentrosDelMes.value.map(s => {
    const dia = _pf(s.fecha).getDate()
    return { tipo: 'encuentro', dia, label: s.microreto_titulo || 'Encuentro', sub: [s.ciclo_formativo, s.grupo].filter(Boolean).join(' · '), _ref: s }
  })
  return [...evs, ...ses].sort((a, b) => a.dia - b.dia)
})

// ── Alertas inteligentes ───────────────────────────────────────────────────────
const alertas = computed(() => {
  if (cargandoProyectos.value && cargandoEncuentros.value) return []

  const now = Date.now()
  const diasDesde = (iso) => Math.floor((now - new Date(iso).getTime()) / 86_400_000)
  const lista = []

  // Proyectos en edición estancados +14 días
  const estancados = proyectos.value.filter(p =>
    p.estado === 'en_edicion' && p.updated_at && diasDesde(p.updated_at) >= 14
  )
  if (estancados.length)
    lista.push({
      nivel: 'warning',
      texto: `${estancados.length} proyecto${estancados.length > 1 ? 's llevan' : ' lleva'} más de 14 días en edición sin actualizar`,
      ruta: '/proyectos',
    })

  // Propuestas enviadas a empresa sin validar +10 días
  const sinRespuesta = proyectos.value.filter(p =>
    p.estado === 'propuesta' && !p.empresa_validado &&
    p.enviado_a_empresa_mail && p.updated_at && diasDesde(p.updated_at) >= 10
  )
  if (sinRespuesta.length)
    lista.push({
      nivel: 'warning',
      texto: `${sinRespuesta.length} empresa${sinRespuesta.length > 1 ? 's llevan' : ' lleva'} más de 10 días sin responder`,
      ruta: '/proyectos',
    })

  // Empresa respondió "aún no puede validar"
  const noAun = proyectos.value.filter(p => p.empresa_no_valida_aun)
  if (noAun.length)
    lista.push({
      nivel: 'info',
      texto: `${noAun.length} empresa${noAun.length > 1 ? 's indicaron' : ' indicó'} que aún no puede${noAun.length > 1 ? 'n' : ''} validar`,
      ruta: '/proyectos',
    })

  // Sin encuentros este mes (solo si hay historial de encuentros)
  if (encuentros.value.length > 0 && !cargandoEncuentros.value) {
    const hoy = new Date()
    const hayEsteMes = encuentros.value.some(s => {
      if (!s.fecha) return false
      const d = _pf(s.fecha)
      return d.getMonth() === hoy.getMonth() && d.getFullYear() === hoy.getFullYear()
    })
    if (!hayEsteMes)
      lista.push({
        nivel: 'info',
        texto: 'No has registrado ningún encuentro este mes',
        ruta: '/encuentros/crear',
      })
  }

  // Proyectos validados esta semana (positivo)
  const validadosRecientes = proyectos.value.filter(p =>
    p.estado === 'validado' && p.updated_at && diasDesde(p.updated_at) <= 7
  )
  if (validadosRecientes.length)
    lista.push({
      nivel: 'success',
      texto: `${validadosRecientes.length} proyecto${validadosRecientes.length > 1 ? 's validados' : ' validado'} esta semana`,
      ruta: '/proyectos',
    })

  return lista
})

// ── Modal catálogo BOE ─────────────────────────────────────────────────────────
const mostrarCatalogoBoe = ref(false)

// ── Miniaturas demo para proyectos en curso ────────────────────────────────────
const proyectoImgs = [
  'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=120&q=80',
  'https://images.unsplash.com/photo-1507925921958-8a62f3d1a50d?auto=format&fit=crop&w=120&q=80',
  'https://images.unsplash.com/photo-1611532736597-de2d4265fba3?auto=format&fit=crop&w=120&q=80',
  'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=120&q=80',
]

// ── Notas personales (localStorage) ────────────────────────────────────────────
const notas     = ref(JSON.parse(localStorage.getItem('docente_notas') || '[]'))
const nuevaNota = ref('')

function addNota() {
  if (!nuevaNota.value.trim()) return
  const updated = [...notas.value, { id: Date.now(), text: nuevaNota.value.trim() }]
  notas.value    = updated
  nuevaNota.value = ''
  localStorage.setItem('docente_notas', JSON.stringify(updated))
}

function removeNota(id) {
  const updated = notas.value.filter(n => n.id !== id)
  notas.value   = updated
  localStorage.setItem('docente_notas', JSON.stringify(updated))
}
</script>

<template>
  <div class="min-h-screen font-sans text-[#1F2937] pt-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

      <!-- ══ Cabecera bienvenida ══════════════════════════════════════════════════ -->
      <div class="relative overflow-hidden bg-[#1F2937] rounded-2xl p-4 sm:p-5 mb-4
                  transition-all duration-700"
           :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'">

        <div class="absolute top-0 right-0 w-48 h-48 bg-[#00A859]/10 rounded-full
                    translate-x-1/3 -translate-y-1/3 blur-[60px] pointer-events-none"></div>

        <div class="relative z-10 flex items-center justify-between gap-3">
          <div>
            <div class="inline-flex items-center gap-1.5 bg-[#00A859]/15 border border-[#00A859]/25
                        rounded-full px-2.5 py-0.5 mb-2">
              <span class="w-1.5 h-1.5 rounded-full bg-[#99CC33] animate-pulse shrink-0"></span>
              <span class="text-[#99CC33] text-[9px] font-black uppercase tracking-widest">Perfil docente</span>
            </div>
            <h1 class="text-lg sm:text-xl font-black text-white tracking-tight">
              Bienvenido/a, <span class="text-[#99CC33]">{{ primerNombre }}</span>
            </h1>
            <div v-if="userCentroNombre" class="flex items-center gap-1.5 mt-1">
              <svg class="w-3.5 h-3.5 text-white/40 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              <span class="text-white/55 text-xs font-semibold">{{ userCentroNombre }}</span>
            </div>
            <p class="text-white/35 text-xs mt-0.5 font-medium">Panel de control · DuaLab</p>
          </div>

          <div class="hidden sm:flex w-11 h-11 rounded-xl bg-[#00A859]/15 border border-[#00A859]/25
                      items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 14l9-5-9-5-9 5 9 5z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- ══ Contadores de proyectos ════════════════════════════════════════════════ -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6 transition-all duration-700 delay-75"
           :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'">

        <!-- Proyectos validados -->
        <button @click="irAStartupFiltrado('proyecto')"
                class="group bg-white border border-gray-100 rounded-2xl px-4 py-3 text-left
                       hover:border-[#00A859]/30 hover:shadow-sm transition-all duration-200">
          <div v-if="!cargandoProyectos" class="text-2xl font-black text-[#00A859] tabular-nums leading-none mb-1">
            {{ proyectosValidados.length }}
          </div>
          <div v-else class="h-7 w-8 bg-gray-100 rounded-lg animate-pulse mb-1"></div>
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-tight">Validados</p>
          <p class="text-[9px] font-black text-[#00A859]/60 uppercase tracking-widest mt-1
                    group-hover:text-[#00A859] transition-colors">Ver todos →</p>
        </button>

        <!-- Pendientes de validar -->
        <button @click="irAStartupFiltrado('propuesta')"
                class="group bg-white border border-gray-100 rounded-2xl px-4 py-3 text-left
                       hover:border-amber-300/50 hover:shadow-sm transition-all duration-200">
          <div v-if="!cargandoProyectos" class="text-2xl font-black text-amber-500 tabular-nums leading-none mb-1">
            {{ proyectosPendientes.length }}
          </div>
          <div v-else class="h-7 w-8 bg-gray-100 rounded-lg animate-pulse mb-1"></div>
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-tight">Pendientes</p>
          <p class="text-[9px] font-black text-amber-400/60 uppercase tracking-widest mt-1
                    group-hover:text-amber-500 transition-colors">Ver todos →</p>
        </button>

        <!-- Total proyectos -->
        <button @click="irAStartupFiltrado('todos')"
                class="group bg-white border border-gray-100 rounded-2xl px-4 py-3 text-left
                       hover:border-blue-300/50 hover:shadow-sm transition-all duration-200">
          <div v-if="!cargandoProyectos" class="text-2xl font-black text-blue-500 tabular-nums leading-none mb-1">
            {{ proyectos.length }}
          </div>
          <div v-else class="h-7 w-8 bg-gray-100 rounded-lg animate-pulse mb-1"></div>
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-tight">Total proyectos</p>
          <p class="text-[9px] font-black text-blue-400/60 uppercase tracking-widest mt-1
                    group-hover:text-blue-500 transition-colors">Ver todos →</p>
        </button>

        <!-- Total de encuentros -->
        <button @click="irA('/encuentros')"
                class="group bg-white border border-gray-100 rounded-2xl px-4 py-3 text-left
                       hover:border-purple-300/50 hover:shadow-sm transition-all duration-200">
          <div v-if="!cargandoEncuentros" class="text-2xl font-black text-purple-500 tabular-nums leading-none mb-1">
            {{ encuentros.length }}
          </div>
          <div v-else class="h-7 w-8 bg-gray-100 rounded-lg animate-pulse mb-1"></div>
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-tight">Encuentros</p>
          <p class="text-[9px] font-black text-purple-400/60 uppercase tracking-widest mt-1
                    group-hover:text-purple-500 transition-colors">Ver encuentros →</p>
        </button>

      </div>

      <!-- ── Fila superior: izq (Calendario) | der (Mis encuentros · Agenda · Notas) ── -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start
                  transition-all duration-700 delay-150"
           :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'">

        <!-- ── Columna izquierda (Mis encuentros · Agenda · Notas → orden visual derecha) ── -->
        <div class="flex flex-col gap-4 lg:order-2">

        <!-- Mis encuentros -->
        <section class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
          <div class="bg-[#374151] px-4 py-3 flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-blue-400/20 border border-blue-400/25
                        flex items-center justify-center shrink-0">
              <svg class="w-3.5 h-3.5 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                     M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
            </div>
            <h3 class="text-white font-black text-sm truncate min-w-0">Mis encuentros</h3>
          </div>

          <div v-if="cargandoEncuentros" class="p-6 flex justify-center">
            <svg class="animate-spin w-5 h-5 text-[#00A859]" viewBox="0 0 24 24">
              <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
            </svg>
          </div>

          <div v-else-if="encuentros.length === 0" class="px-5 py-8 text-center">
            <div class="w-10 h-10 rounded-full bg-gray-50 border border-gray-100
                        flex items-center justify-center mx-auto mb-3">
              <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                     M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
            </div>
            <p class="text-xs text-gray-400 font-medium mb-3">Aún no hay encuentros registrados.</p>
            <button @click="irA('/encuentros/crear')"
                    class="text-[10px] font-black uppercase tracking-widest text-[#00A859]
                           hover:text-[#00A859]/70 transition-colors">
              Registrar primer encuentro →
            </button>
          </div>

          <template v-else>
            <ul class="divide-y divide-gray-50">
              <li v-for="s in ultimosEncuentros" :key="s.id"
                  class="px-4 py-3 transition-all duration-150 group cursor-pointer"
                  :class="isSessionHovered(s) ? 'bg-blue-50 ring-inset ring-1 ring-blue-100' : 'hover:bg-gray-50/60'"
                  @mouseenter="setHoveredFromSession(s)"
                  @mouseleave="hoveredDay = null"
                  @click="router.push({ path: '/encuentros', query: { id: s.id } })">
                <div class="flex items-start gap-2">
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-black leading-snug truncate transition-colors"
                       :class="isSessionHovered(s) ? 'text-blue-700' : 'text-[#1F2937] group-hover:text-[#00A859]'">
                      {{ s.microreto_titulo || '(sin título)' }}
                    </p>
                    <p class="text-[10px] font-bold mt-0.5 transition-colors"
                       :class="isSessionHovered(s) ? 'text-blue-500' : 'text-[#00A859]'">
                      {{ formatFecha(s.fecha) }}
                    </p>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <span v-if="s.curso"
                            class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wide
                                   bg-[#00A859]/10 text-[#00A859]">
                        {{ s.curso }}
                      </span>
                      <span v-if="s.grupo"
                            class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wide
                                   bg-[#99CC33]/12 text-[#5a7a00]">
                        Gr. {{ s.grupo }}
                      </span>
                      <span v-if="s.num_alumnos"
                            class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wide
                                   bg-gray-100 text-gray-500">
                        {{ s.num_alumnos }} al.
                      </span>
                    </div>
                  </div>
                  <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 transition-colors"
                       :class="isSessionHovered(s) ? 'text-blue-400' : 'text-gray-300 group-hover:text-[#00A859]'"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </div>
              </li>
            </ul>
            <div class="px-4 py-3 border-t border-gray-50 bg-blue-50/50">
              <button @click="irA('/encuentros')"
                      class="w-full flex items-center justify-center gap-1.5 text-[10px] font-black
                             uppercase tracking-widest text-blue-500 hover:text-blue-600
                             transition-colors py-0.5">
                Ver todos los encuentros
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </template>
        </section>

        <!-- Alertas inteligentes -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span class="text-sm font-semibold text-gray-700">Alertas</span>
              <span v-if="alertas.length" class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">{{ alertas.length }}</span>
            </div>
          </div>

          <!-- Sin alertas -->
          <div v-if="alertas.length === 0 && !cargandoProyectos && !cargandoEncuentros"
               class="flex flex-col items-center justify-center py-6 px-4 gap-2 text-center">
            <div class="w-8 h-8 rounded-full bg-green-50 flex items-center justify-center">
              <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <p class="text-xs font-medium text-gray-500">Todo en orden</p>
          </div>

          <!-- Cargando -->
          <div v-else-if="cargandoProyectos || cargandoEncuentros" class="px-5 py-4 space-y-2">
            <div v-for="n in 2" :key="n" class="h-3 rounded bg-gray-100 animate-pulse" :class="n === 2 ? 'w-2/3' : 'w-full'" />
          </div>

          <!-- Lista de alertas -->
          <ul v-else class="divide-y divide-gray-50">
            <li v-for="(a, i) in alertas" :key="i"
                class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50/60 transition-colors cursor-pointer group"
                @click="irA(a.ruta)">
              <!-- Icono nivel -->
              <div class="mt-0.5 shrink-0 w-6 h-6 rounded-full flex items-center justify-center"
                   :class="{
                     'bg-amber-50': a.nivel === 'warning',
                     'bg-blue-50':  a.nivel === 'info',
                     'bg-green-50': a.nivel === 'success',
                   }">
                <!-- warning -->
                <svg v-if="a.nivel === 'warning'" class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <!-- info -->
                <svg v-else-if="a.nivel === 'info'" class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                </svg>
                <!-- success -->
                <svg v-else-if="a.nivel === 'success'" class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>

              <p class="text-xs text-gray-600 leading-snug flex-1 pt-1">{{ a.texto }}</p>

              <!-- Flecha hover -->
              <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-gray-400 mt-1 shrink-0 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
              </svg>
            </li>
          </ul>
        </div>

        <!-- Panel Notas -->
        <div class="relative pt-5">
          <div class="absolute top-0 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center">
            <div class="flex gap-3 mb-0">
              <div class="w-[2px] h-5 bg-gradient-to-b from-gray-300 to-gray-500 rounded-full shadow-sm"></div>
              <div class="w-[2px] h-5 bg-gradient-to-b from-gray-300 to-gray-500 rounded-full shadow-sm"></div>
            </div>
            <div class="w-10 h-[13px] bg-gradient-to-b from-gray-400 to-gray-600 rounded-[3px]
                        shadow-lg -mt-px flex items-center justify-center">
              <div class="w-5 h-[3px] bg-white/20 rounded-full"></div>
            </div>
          </div>
          <div class="bg-[#FFFDE7] border border-amber-200/60 rounded-xl shadow-lg overflow-hidden flex flex-col"
               style="background-image: repeating-linear-gradient(transparent, transparent 27px, #fde68a55 27px, #fde68a55 28px); background-position: 0 40px;">
            <div class="px-5 pt-8 pb-2.5 border-b border-amber-200/50">
              <h3 class="text-[10px] font-black uppercase tracking-widest text-amber-800/50 text-center">Mis notas</h3>
            </div>
            <div class="flex-1 px-4 py-3 space-y-0 overflow-y-auto max-h-[252px]">
              <div v-if="notas.length === 0" class="text-center py-6">
                <p class="text-[11px] text-amber-800/30 font-medium italic">Escribe tu primera nota…</p>
              </div>
              <div v-for="nota in notas" :key="nota.id"
                   class="group flex items-start gap-2 border-b border-amber-200/40 py-2 last:border-0">
                <span class="text-amber-400/60 text-[10px] font-black shrink-0 mt-0.5 select-none">—</span>
                <p class="flex-1 text-xs text-amber-900/80 font-medium leading-snug">{{ nota.text }}</p>
                <button @click="removeNota(nota.id)"
                        class="opacity-0 group-hover:opacity-100 text-amber-300 hover:text-red-400
                               transition-all text-sm font-black shrink-0 leading-none">×</button>
              </div>
            </div>
            <div class="border-t border-amber-200/50 px-4 py-3 bg-amber-50/60">
              <div class="flex items-center gap-2">
                <input v-model="nuevaNota" type="text" placeholder="Nueva nota…" @keyup.enter="addNota"
                       class="flex-1 text-xs font-medium text-amber-900/80 bg-transparent
                              border-0 border-b border-amber-300/50 outline-none pb-1
                              placeholder-amber-800/25 focus:border-amber-500 transition-colors"/>
                <button @click="addNota"
                        class="w-6 h-6 rounded-full bg-amber-400/30 hover:bg-amber-400/60
                               text-amber-700 font-black text-sm flex items-center justify-center
                               transition-colors shrink-0 leading-none">+</button>
              </div>
            </div>
          </div>
        </div>

        </div><!-- /columna izquierda -->

        <!-- ── Columna derecha: Calendario + Acciones rápidas (→ orden visual izquierda) ── -->
        <div class="flex flex-col gap-4 lg:order-1">

        <!-- Calendario -->
        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
          <div class="bg-[#374151] px-5 py-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-xl bg-[#00A859]/20 border border-[#00A859]/25
                          flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <h3 class="text-white font-black text-sm capitalize">{{ calendarMonthLabel }}</h3>
            </div>
            <div class="flex items-center gap-1">
              <button @click="prevMonth"
                      class="w-6 h-6 rounded-lg flex items-center justify-center
                             text-white/60 hover:text-white hover:bg-white/10 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                </svg>
              </button>
              <button @click="nextMonth"
                      class="w-6 h-6 rounded-lg flex items-center justify-center
                             text-white/60 hover:text-white hover:bg-white/10 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="px-3 pt-3 pb-2">
            <div class="grid grid-cols-7 mb-1">
              <div v-for="d in ['L','M','X','J','V','S','D']" :key="d"
                   class="text-center text-[9px] font-black uppercase tracking-widest text-gray-300 pb-1">
                {{ d }}
              </div>
            </div>
            <div class="grid grid-cols-7 gap-px">
              <div v-for="(day, i) in calendarDays" :key="i"
                   class="min-h-[52px] flex flex-col rounded-lg overflow-hidden transition-all duration-150"
                   :class="day ? 'cursor-pointer' : ''"
                   :style="hoveredDay === day ? dayAccentBg(day) : {}"
                   @click="selectCalDay(day)"
                   @mouseenter="hoveredDay = day || null"
                   @mouseleave="hoveredDay = null">
                <div v-if="day" class="px-1 pt-1">
                  <span :class="[
                    'w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold',
                    isToday(day) ? 'bg-[#00A859] text-white' : '',
                    isDaySelected(day) ? 'ring-2 ring-[#00A859] ring-offset-1' : '',
                    !isToday(day) ? 'text-gray-600' : ''
                  ]">{{ day }}</span>
                </div>
                <div v-if="day" class="flex-1 px-0.5 pb-0.5 space-y-px mt-0.5">
                  <div v-for="(ev, ei) in dayEvents(day)" :key="'ev-'+ei"
                       class="rounded px-1 py-px text-[8px] font-bold text-white leading-tight truncate"
                       :style="{ backgroundColor: ev.color }">
                    {{ ev.text }}
                  </div>
                  <div v-for="(s, si) in encuentrosDelDia(day)" :key="'s-'+si"
                       class="rounded px-1 py-px text-[8px] font-bold text-white leading-tight truncate bg-blue-500">
                    {{ s.microreto_titulo || 'Encuentro' }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="selectedDate" class="mx-3 mb-3 mt-1 p-3 rounded-xl bg-gray-50 border border-gray-100">
            <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">
              Evento el día {{ selectedDate.day }}
            </p>
            <input v-model="newEventText" type="text" placeholder="Nombre del evento…"
                   @keyup.enter="addEvento"
                   class="w-full text-xs font-medium text-[#1F2937] bg-white border border-gray-200
                          rounded-lg px-3 py-2 mb-2 outline-none focus:border-[#00A859] transition-colors"/>
            <div class="flex items-center gap-2 mb-2">
              <button v-for="c in eventColors" :key="c" @click="newEventColor = c"
                      class="w-5 h-5 rounded-full border-2 transition-all"
                      :style="{ backgroundColor: c }"
                      :class="newEventColor === c ? 'border-[#1F2937] scale-110' : 'border-transparent'">
              </button>
            </div>
            <div class="flex gap-2">
              <button @click="addEvento"
                      class="flex-1 text-[10px] font-black uppercase tracking-widest text-white
                             bg-[#00A859] hover:bg-[#007a40] rounded-lg py-1.5 transition-colors">
                Añadir
              </button>
              <button @click="selectedDate = null"
                      class="text-[10px] font-black uppercase tracking-widest text-gray-400
                             hover:text-gray-600 px-3 transition-colors">
                Cancelar
              </button>
            </div>
          </div>
          <!-- Agenda integrada -->
          <div class="border-t border-gray-100 px-4 pt-3 pb-1">
            <p class="text-[9px] font-black uppercase tracking-widest text-blue-400/80 mb-2 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-blue-400 inline-block"></span>
              Encuentros este mes
            </p>
            <div v-if="encuentrosDelMes.length === 0"
                 class="text-[10px] text-gray-300 font-medium text-center py-1.5">
              Sin encuentros este mes
            </div>
            <ul v-else class="space-y-0.5">
              <li v-for="s in encuentrosDelMes" :key="s.id"
                  class="flex items-start gap-2 rounded-lg px-2 -mx-2 py-1 transition-colors duration-150 cursor-pointer"
                  :class="hoveredDay === _pf(s.fecha).getDate()
                    ? 'bg-blue-50 ring-1 ring-blue-200' : 'hover:bg-gray-50'"
                  @mouseenter="setHoveredFromSession(s)"
                  @mouseleave="hoveredDay = null"
                  @click="router.push({ path: '/encuentros', query: { id: s.id } })">
                <div class="w-2 h-2 rounded shrink-0 mt-1 transition-colors duration-150"
                     :class="hoveredDay === _pf(s.fecha).getDate()
                       ? 'bg-blue-600' : 'bg-blue-500'"></div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-bold truncate transition-colors duration-150"
                     :class="hoveredDay === _pf(s.fecha).getDate()
                       ? 'text-blue-700' : 'text-[#1F2937]'">
                    <span class="font-bold" :class="hoveredDay === _pf(s.fecha).getDate()
                      ? 'text-blue-400' : 'text-gray-400'">
                      {{ _pf(s.fecha).getDate() }} —
                    </span>
                    {{ s.microreto_titulo || 'Encuentro' }}
                  </p>
                  <p v-if="s.ciclo_formativo || s.grupo" class="text-[9px] text-gray-400 font-medium truncate">
                    {{ [s.ciclo_formativo, s.grupo].filter(Boolean).join(' · ') }}
                  </p>
                </div>
              </li>
            </ul>
          </div>
          <div class="border-t border-gray-100 px-4 pt-3 pb-3">
            <p class="text-[9px] font-black uppercase tracking-widest text-gray-300 mb-2 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-gray-300 inline-block"></span>
              Otros eventos
            </p>
            <div v-if="eventosDelMes.length === 0"
                 class="text-[10px] text-gray-300 font-medium text-center py-1.5">
              Sin eventos · Toca un día para añadir
            </div>
            <ul v-else class="space-y-0.5">
              <li v-for="(ev, i) in eventosDelMes" :key="i"
                  class="flex items-center gap-2 group rounded-lg px-2 -mx-2 py-1 transition-all duration-150"
                  :style="hoveredDay === ev.day
                    ? { backgroundColor: hexToRgba(ev.color, 0.10), boxShadow: `0 0 0 1px ${hexToRgba(ev.color, 0.35)}` }
                    : {}">
                <div class="w-2 h-2 rounded shrink-0 transition-transform duration-150"
                     :class="hoveredDay === ev.day ? 'scale-125' : ''"
                     :style="{ backgroundColor: ev.color }"></div>
                <span class="flex-1 text-xs font-medium truncate transition-colors duration-150"
                      :class="hoveredDay === ev.day ? 'font-bold' : ''"
                      :style="hoveredDay === ev.day ? { color: ev.color } : {}">
                  <span class="font-bold"
                        :style="hoveredDay === ev.day ? { color: hexToRgba(ev.color, 0.6) } : {}"
                        :class="hoveredDay !== ev.day ? 'text-gray-400' : ''">{{ ev.day }} —</span>
                  {{ ev.text }}
                </span>
                <button @click="removeEvento(eventos.indexOf(ev))"
                        class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-400
                               transition-all text-xs font-black shrink-0">×</button>
              </li>
            </ul>
          </div>
        </div><!-- /calendario + agenda -->

        <!-- Donut progreso proyectos -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
          <p class="text-xs font-semibold text-gray-500 mb-4">Progreso de proyectos</p>

          <!-- Skeleton -->
          <div v-if="cargandoProyectos" class="flex items-center gap-5">
            <div class="w-24 h-24 rounded-full bg-gray-100 animate-pulse shrink-0" />
            <div class="flex-1 space-y-2">
              <div v-for="n in 3" :key="n" class="h-3 rounded bg-gray-100 animate-pulse" :class="n === 3 ? 'w-2/3' : 'w-full'" />
            </div>
          </div>

          <!-- Sin proyectos -->
          <div v-else-if="!proyectos.length"
               class="flex flex-col items-center justify-center py-2 gap-1 text-center">
            <p class="text-xs text-gray-400">Aún no hay proyectos</p>
          </div>

          <!-- Donut + leyenda -->
          <div v-else class="flex items-center gap-5">
            <!-- SVG donut -->
            <div class="relative shrink-0 w-24 h-24">
              <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                <!-- track -->
                <circle cx="50" cy="50" r="38" fill="none" stroke="#F3F4F6" stroke-width="14" />
                <!-- segmentos -->
                <circle v-for="(seg, i) in donutSegmentos" :key="i"
                  cx="50" cy="50" r="38" fill="none"
                  :stroke="seg.color"
                  stroke-width="13"
                  :stroke-dasharray="`${seg.dash} ${DONUT_C - seg.dash}`"
                  :transform="`rotate(${seg.rotacion}, 50, 50)`"
                  stroke-linecap="butt"
                />
              </svg>
              <!-- Total centrado -->
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-xl font-black text-gray-800 leading-none">{{ proyectos.length }}</span>
                <span class="text-[9px] text-gray-400 font-medium mt-0.5">proyectos</span>
              </div>
            </div>

            <!-- Leyenda -->
            <ul class="flex-1 space-y-2 min-w-0">
              <li v-for="seg in donutSegmentos" :key="seg.label"
                  class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: seg.color }" />
                <span class="text-xs text-gray-500 truncate flex-1">{{ seg.label }}</span>
                <span class="text-xs font-bold text-gray-700 tabular-nums shrink-0">{{ seg.valor }}</span>
              </li>
            </ul>
          </div>
        </div>

        </div><!-- /columna derecha -->

      </div><!-- /fila superior -->

      <!-- ── Mis proyectos (izq) · Recursos (der) ────────────────────────────── -->
      <section class="mt-4 transition-all duration-700 delay-[200ms]"
               :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'">

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_272px] gap-6 items-start">

          <!-- ═ IZQUIERDA — Mis proyectos ══════════════════════════════════════ -->
          <div>
            <div class="flex items-center gap-3 mb-3">
              <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 shrink-0">Mis proyectos</span>
              <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Cargando -->
            <div v-if="cargandoProyectos"
                 class="bg-white border border-gray-100 rounded-2xl p-6 flex justify-center">
              <svg class="animate-spin w-5 h-5 text-[#00A859]" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
            </div>

            <!-- Sin proyectos -->
            <div v-else-if="!hayProyectos"
                 class="bg-white border border-gray-100 rounded-2xl px-5 py-8 text-center">
              <div class="w-10 h-10 rounded-full bg-gray-50 border border-gray-100
                          flex items-center justify-center mx-auto mb-3">
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2
                       m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
              </div>
              <p class="text-xs text-gray-400 font-medium mb-3">Aún no hay proyectos creados.</p>
              <button @click="irA('/proyectos')"
                      class="text-[10px] font-black uppercase tracking-widest text-orange-500
                             hover:text-orange-400 transition-colors">
                Ir a Startup Day →
              </button>
            </div>

            <!-- Con proyectos -->
            <div v-else class="flex flex-col gap-4">

              <!-- Proyectos validados -->
              <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
                <div class="bg-[#374151] px-4 py-3 flex items-center gap-3">
                  <div class="w-7 h-7 rounded-lg bg-[#00A859]/20 border border-[#00A859]/25
                              flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <h3 class="text-white font-black text-sm">Proyectos validados</h3>
                </div>

                <div v-if="ultimosProyectosEnCurso.length === 0" class="px-5 py-8 text-center">
                  <div class="w-10 h-10 rounded-full bg-gray-50 border border-gray-100
                              flex items-center justify-center mx-auto mb-3">
                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <p class="text-xs text-gray-400 font-medium mb-3">Aún no hay proyectos validados.</p>
                  <button @click="irA('/proyectos')"
                          class="text-[10px] font-black uppercase tracking-widest text-[#00A859]
                                 hover:text-[#00A859]/70 transition-colors">
                    Ver proyectos →
                  </button>
                </div>

                <template v-else>
                  <ul class="divide-y divide-gray-50">
                    <li v-for="(p, idx) in ultimosProyectosEnCurso" :key="p.id"
                        class="px-4 py-3 hover:bg-gray-50/60 transition-colors group cursor-pointer"
                        @click="irA('/proyectos/' + p.uuid)">
                      <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-xl overflow-hidden shrink-0 border border-gray-100 shadow-sm">
                          <img :src="proyectoImgs[idx % proyectoImgs.length]"
                               :alt="p.titulo" class="w-full h-full object-cover" loading="lazy" />
                        </div>
                        <div class="flex-1 min-w-0">
                          <p class="text-xs font-black text-[#1F2937] leading-snug truncate
                                    group-hover:text-[#00A859] transition-colors">
                            {{ p.titulo || '(sin título)' }}
                          </p>
                          <p v-if="p.empresa_nombre" class="text-[10px] text-[#00A859] font-bold mt-0.5 truncate">
                            {{ p.empresa_nombre }}
                          </p>
                          <div class="flex flex-wrap gap-1 mt-1">
                            <span v-if="p.ciclo_nombre"
                                  class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wide
                                         bg-[#00A859]/10 text-[#00A859]">
                              {{ p.ciclo_nombre }}
                            </span>
                            <span v-if="p.curso"
                                  class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wide
                                         bg-gray-100 text-gray-500">
                              {{ p.curso }}
                            </span>
                          </div>
                        </div>
                        <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-[#00A859] shrink-0 mt-0.5 transition-colors"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                      </div>
                    </li>
                  </ul>
                  <div class="px-4 py-3 border-t border-gray-50 bg-[#00A859]/5">
                    <button @click="irAStartupFiltrado('proyecto')"
                            class="w-full flex items-center justify-center gap-1.5 text-[10px] font-black
                                   uppercase tracking-widest text-[#00A859] hover:text-[#007a40]
                                   transition-colors py-0.5">
                      Ver todos los proyectos validados
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                      </svg>
                    </button>
                  </div>
                </template>
              </div>

              <!-- Grid: Pendientes + En edición como tarjetas separadas -->
              <div v-if="proyectosPendientes.length > 0 || proyectosEnEdicion.length > 0"
                   class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <!-- Pendientes -->
                <div v-if="proyectosPendientes.length > 0"
                     class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
                  <div class="px-4 py-2.5 bg-[#374151] flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 shrink-0"></span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-white/70">Pendientes de validar</span>
                  </div>
                  <ul class="space-y-2 px-4 py-3">
                    <li v-for="p in proyectosPendientes.slice(0, 4)" :key="p.id"
                        class="flex items-center gap-2.5 cursor-pointer group"
                        @click="irA('/proyectos/' + p.uuid)">
                      <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-[#1F2937] truncate group-hover:text-orange-600 transition-colors">
                          {{ p.titulo || 'Sin título' }}
                        </p>
                        <p v-if="p.empresa_nombre" class="text-[10px] text-gray-400 font-medium truncate">
                          {{ p.empresa_nombre }}
                        </p>
                      </div>
                      <span class="text-[8px] font-black px-2 py-0.5 rounded-full
                                   bg-orange-50 text-orange-600 border border-orange-200 shrink-0">···</span>
                    </li>
                  </ul>
                </div>

                <!-- En edición -->
                <div v-if="proyectosEnEdicion.length > 0"
                     class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
                  <div class="px-4 py-2.5 bg-[#374151] flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 shrink-0"></span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-white/70">En edición</span>
                  </div>
                  <ul class="space-y-2 px-4 py-3">
                    <li v-for="p in proyectosEnEdicion.slice(0, 4)" :key="p.id"
                        class="flex items-center gap-2.5 cursor-pointer group"
                        @click="irA('/proyectos/' + p.uuid)">
                      <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-[#1F2937] truncate group-hover:text-gray-600 transition-colors">
                          {{ p.titulo || 'Sin título' }}
                        </p>
                        <p v-if="p.empresa_nombre" class="text-[10px] text-gray-400 font-medium truncate">
                          {{ p.empresa_nombre }}
                        </p>
                      </div>
                      <span class="text-[8px] font-black px-2 py-0.5 rounded-full
                                   bg-gray-100 text-gray-500 border border-gray-200 shrink-0">✎</span>
                    </li>
                  </ul>
                </div>

              </div>

              <!-- Ver todos -->
              <div class="bg-white border border-gray-100 rounded-xl px-4 py-3
                          bg-gradient-to-r from-[#00A859]/8 via-[#00A859]/4 to-transparent">
                <button @click="irAStartupFiltrado('todos')"
                        class="w-full flex items-center justify-center gap-1.5 text-[10px] font-black
                               uppercase tracking-widest text-[#00A859] hover:text-[#007a40]
                               transition-colors py-0.5">
                  Ver todos los proyectos
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>
              </div>

            </div>
          </div><!-- /LEFT -->

          <!-- ═ DERECHA — Recursos ══════════════════════════════════════════════ -->
          <div>
            <div class="flex items-center gap-3 mb-3">
              <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 shrink-0">Recursos</span>
              <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            <!-- Grid acciones rápidas -->
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
              <div class="bg-[#374151] px-4 py-3 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-[#00A859]/20 border border-[#00A859]/25
                            flex items-center justify-center shrink-0">
                  <svg class="w-3.5 h-3.5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                  </svg>
                </div>
                <h3 class="text-white font-black text-sm">Acciones rápidas</h3>
              </div>
              <div class="p-3 grid grid-cols-2 gap-2">
                <button @click="irA('/retos/crear')"
                  class="group flex items-center gap-2 p-3 rounded-xl text-left
                         bg-[#00A859]/5 border border-[#00A859]/15
                         hover:bg-[#00A859]/12 hover:border-[#00A859]/35 hover:shadow-sm transition-all duration-200">
                  <div class="w-7 h-7 rounded-lg bg-[#00A859]/15 border border-[#00A859]/20
                              flex items-center justify-center shrink-0 group-hover:bg-[#00A859]/25 transition-colors">
                    <svg class="w-3.5 h-3.5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                  </div>
                  <span class="text-xs font-black text-[#1F2937] group-hover:text-[#00A859] transition-colors leading-tight">Nuevo reto</span>
                </button>
                <button @click="irA('/proyectos/crear')"
                  class="group flex items-center gap-2 p-3 rounded-xl text-left
                         bg-orange-400/5 border border-orange-400/15
                         hover:bg-orange-400/10 hover:border-orange-400/35 hover:shadow-sm transition-all duration-200">
                  <div class="w-7 h-7 rounded-lg bg-orange-100/70 border border-orange-200/60
                              flex items-center justify-center shrink-0 group-hover:bg-orange-100 transition-colors">
                    <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                  </div>
                  <span class="text-xs font-black text-[#1F2937] group-hover:text-orange-600 transition-colors leading-tight">Nuevo proyecto</span>
                </button>
                <button @click="irA('/retos')"
                  class="group flex items-center gap-2 p-3 rounded-xl text-left
                         bg-[#99CC33]/5 border border-[#99CC33]/20
                         hover:bg-[#99CC33]/12 hover:border-[#99CC33]/40 hover:shadow-sm transition-all duration-200">
                  <div class="w-7 h-7 rounded-lg bg-[#99CC33]/15 border border-[#99CC33]/25
                              flex items-center justify-center shrink-0 group-hover:bg-[#99CC33]/25 transition-colors">
                    <svg class="w-3.5 h-3.5 text-[#6EA820]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 22v-15A2.5 2.5 0 016.5 2z"/>
                    </svg>
                  </div>
                  <span class="text-xs font-black text-[#1F2937] group-hover:text-[#6EA820] transition-colors leading-tight">Biblioteca</span>
                </button>
                <button @click="irA('/mi-usuario')"
                  class="group flex items-center gap-2 p-3 rounded-xl text-left
                         bg-gray-50 border border-gray-100
                         hover:border-gray-300/60 hover:shadow-sm transition-all duration-200">
                  <div class="w-7 h-7 rounded-lg bg-gray-100 border border-gray-200
                              flex items-center justify-center shrink-0 group-hover:bg-gray-200 transition-colors">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <circle cx="12" cy="8" r="4" stroke-width="2"/>
                      <path stroke-width="2" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                  </div>
                  <span class="text-xs font-black text-[#1F2937] group-hover:text-gray-600 transition-colors leading-tight">Mi cuenta</span>
                </button>
              </div>
            </div>

            <!-- Recursos docente -->
            <div class="mt-4 bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
              <div class="bg-[#374151] px-4 py-3 flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-400/20 border border-indigo-400/25
                            flex items-center justify-center shrink-0">
                  <svg class="w-3.5 h-3.5 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2
                         m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                  </svg>
                </div>
                <h3 class="text-white font-black text-sm">Recursos docente</h3>
              </div>
              <div class="p-3 flex flex-col gap-2">

                <button @click="irA('/guia')"
                  class="group flex items-center gap-3 p-3 rounded-xl text-left
                         bg-blue-50/60 border border-blue-100
                         hover:bg-blue-50 hover:border-blue-200 hover:shadow-sm transition-all duration-200">
                  <div class="w-9 h-9 rounded-xl bg-blue-100 border border-blue-200
                              flex items-center justify-center shrink-0 group-hover:bg-blue-200 transition-colors">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18
                           7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13
                           C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-[#1F2937] group-hover:text-blue-700 transition-colors leading-tight">Guía didáctica</p>
                    <p class="text-[10px] text-gray-400 font-medium mt-0.5">Manual de uso DuaLab</p>
                  </div>
                  <svg class="w-3.5 h-3.5 text-blue-300 group-hover:text-blue-500 shrink-0 transition-colors"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>

                <button @click="irA('/retos')"
                  class="group flex items-center gap-3 p-3 rounded-xl text-left
                         bg-[#99CC33]/5 border border-[#99CC33]/20
                         hover:bg-[#99CC33]/12 hover:border-[#99CC33]/40 hover:shadow-sm transition-all duration-200">
                  <div class="w-9 h-9 rounded-xl bg-[#99CC33]/15 border border-[#99CC33]/25
                              flex items-center justify-center shrink-0 group-hover:bg-[#99CC33]/25 transition-colors">
                    <svg class="w-4 h-4 text-[#6EA820]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 22v-15A2.5 2.5 0 016.5 2z"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-[#1F2937] group-hover:text-[#6EA820] transition-colors leading-tight">Recursos</p>
                    <p class="text-[10px] text-gray-400 font-medium mt-0.5">Biblioteca de retos y materiales</p>
                  </div>
                  <svg class="w-3.5 h-3.5 text-[#99CC33]/50 group-hover:text-[#6EA820] shrink-0 transition-colors"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>

                <button @click="mostrarCatalogoBoe = true"
                  class="group flex items-center gap-3 p-3 rounded-xl text-left
                         bg-indigo-50/60 border border-indigo-100
                         hover:bg-indigo-50 hover:border-indigo-200 hover:shadow-sm transition-all duration-200">
                  <div class="w-9 h-9 rounded-xl bg-indigo-100 border border-indigo-200
                              flex items-center justify-center shrink-0 group-hover:bg-indigo-200 transition-colors">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414
                           a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-[#1F2937] group-hover:text-indigo-700 transition-colors leading-tight">Ciclos BOE</p>
                    <p class="text-[10px] text-gray-400 font-medium mt-0.5">Familias · Módulos · RA · CE</p>
                  </div>
                  <svg class="w-3.5 h-3.5 text-indigo-300 group-hover:text-indigo-500 shrink-0 transition-colors"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>

              </div>
            </div>


          </div><!-- /RIGHT -->

        </div>

      </section>

      <!-- ── Herramientas + Mi cuenta ────────────────────────────────────────── -->
      <section class="mt-4 transition-all duration-700 delay-[300ms]"
               :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'">

        <div class="flex items-center gap-3 mb-3">
          <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 shrink-0">
            Herramientas
          </span>
          <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Retos -->
          <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
            <div class="bg-[#374151] px-5 py-4 flex items-center gap-3">
              <div class="w-8 h-8 rounded-xl bg-[#00A859]/20 border border-[#00A859]/25
                          flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
              </div>
              <h3 class="text-white font-black text-sm">Retos</h3>
            </div>
            <div class="p-3 space-y-2">
              <button @click="irA('/retos/crear')"
                class="group w-full flex items-center gap-3 p-3.5 rounded-xl text-left
                       bg-[#00A859]/5 border border-[#00A859]/15
                       hover:bg-[#00A859]/12 hover:border-[#00A859]/35 hover:shadow-sm
                       transition-all duration-200">
                <div class="w-10 h-10 rounded-xl bg-[#00A859]/15 border border-[#00A859]/20
                            flex items-center justify-center shrink-0
                            group-hover:bg-[#00A859]/25 transition-colors duration-200">
                  <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[#1F2937] font-black text-sm leading-tight">Generador de retos</p>
                  <p class="text-gray-400 text-xs mt-0.5 font-medium">Crea retos con IA para tu alumnado</p>
                </div>
                <svg class="w-4 h-4 text-[#00A859]/30 shrink-0 group-hover:text-[#00A859]/70
                            group-hover:translate-x-0.5 transition-all duration-200"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
              </button>

              <button @click="irA('/retos')"
                class="group w-full flex items-center gap-3 p-3.5 rounded-xl text-left
                       bg-[#99CC33]/5 border border-[#99CC33]/15
                       hover:bg-[#99CC33]/12 hover:border-[#99CC33]/35 hover:shadow-sm
                       transition-all duration-200">
                <div class="w-10 h-10 rounded-xl bg-[#99CC33]/15 border border-[#99CC33]/20
                            flex items-center justify-center shrink-0
                            group-hover:bg-[#99CC33]/25 transition-colors duration-200">
                  <svg class="w-5 h-5 text-[#6EA820]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 22v-15A2.5 2.5 0 016.5 2z"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[#1F2937] font-black text-sm leading-tight">Biblioteca de retos</p>
                  <p class="text-gray-400 text-xs mt-0.5 font-medium">Consulta y comparte retos con QR</p>
                </div>
                <svg class="w-4 h-4 text-[#99CC33]/40 shrink-0 group-hover:text-[#6EA820]/70
                            group-hover:translate-x-0.5 transition-all duration-200"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Taller de Ideas -->
          <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
            <div class="bg-[#374151] px-5 py-4 flex items-center gap-3">
              <div class="w-8 h-8 rounded-xl bg-amber-400/20 border border-amber-400/25
                          flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
              </div>
              <h3 class="text-white font-black text-sm">Taller de Ideas</h3>
            </div>
            <div class="p-3 space-y-2">
              <button @click="irA('/encuentros/crear')"
                class="group w-full flex items-center gap-3 p-3.5 rounded-xl text-left
                       bg-amber-400/5 border border-amber-400/15
                       hover:bg-amber-400/10 hover:border-amber-400/35 hover:shadow-sm
                       transition-all duration-200">
                <div class="w-10 h-10 rounded-xl bg-amber-400/15 border border-amber-400/20
                            flex items-center justify-center shrink-0
                            group-hover:bg-amber-400/25 transition-colors duration-200">
                  <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                         M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[#1F2937] font-black text-sm leading-tight">Consultar / Crear encuentros</p>
                  <p class="text-gray-400 text-xs mt-0.5 font-medium">Registra encuentros de trabajo con retos</p>
                </div>
                <svg class="w-4 h-4 text-amber-400/30 shrink-0 group-hover:text-amber-500/70
                            group-hover:translate-x-0.5 transition-all duration-200"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
              </button>

              <button @click="irA('/proyectos')"
                class="group w-full flex items-center gap-3 p-3.5 rounded-xl text-left
                       bg-orange-400/5 border border-orange-400/15
                       hover:bg-orange-400/10 hover:border-orange-400/35 hover:shadow-sm
                       transition-all duration-200">
                <div class="w-10 h-10 rounded-xl bg-orange-400/15 border border-orange-400/20
                            flex items-center justify-center shrink-0
                            group-hover:bg-orange-400/25 transition-colors duration-200">
                  <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2
                         m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[#1F2937] font-black text-sm leading-tight">Ver proyectos</p>
                  <p class="text-gray-400 text-xs mt-0.5 font-medium">Gestiona los proyectos del Taller de Ideas</p>
                </div>
                <svg class="w-4 h-4 text-orange-400/30 shrink-0 group-hover:text-orange-500/70
                            group-hover:translate-x-0.5 transition-all duration-200"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </div>

        </div>
      </section>

      <!-- ── Noticias ──────────────────────────────────────────────────────────── -->
      <div class="mt-4 space-y-5 transition-all duration-700 delay-[400ms]"
           :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'">

        <!-- Novedades plataforma DuaLab — Pinterest -->
        <div class="group cursor-pointer rounded-2xl p-3 -m-1 border border-indigo-100
                    bg-indigo-50/40 hover:bg-indigo-50/70 transition-colors duration-200"
             role="button" tabindex="0"
             @click="abrirNoticias('plataforma')"
             @keydown.enter="abrirNoticias('plataforma')"
             @keydown.space.prevent="abrirNoticias('plataforma')">
          <div class="flex items-center gap-3 mb-3 w-full text-left">
            <span class="w-1.5 h-1.5 rounded-full bg-gradient-to-r from-[#00A859] to-[#99CC33] shrink-0"></span>
            <span class="text-[10px] font-black uppercase tracking-widest shrink-0
                         text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">
              Novedades plataforma DuaLab
            </span>
            <div class="flex-1 h-px bg-[#00A859]/20 group-hover:bg-[#00A859]/40 transition-colors duration-200"></div>
            <svg class="w-3.5 h-3.5 text-[#00A859]/40 shrink-0 group-hover:text-[#00A859]
                        group-hover:translate-x-0.5 transition-all duration-200"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
          </div>
          <div class="columns-2 gap-3 space-y-3">
            <div v-for="novedad in previewNovedadesPlataforma" :key="novedad.id"
                 class="break-inside-avoid rounded-2xl overflow-hidden shadow-sm border border-white/50">
              <div class="relative overflow-hidden" :class="novedad.alturaClase">
                <img :src="novedad.imagen" :alt="novedad.alt" class="w-full h-full object-cover" loading="lazy" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent
                            flex flex-col justify-end p-3">
                  <span class="text-[8px] font-black uppercase tracking-widest text-white/75 mb-1">{{ novedad.categoria }}</span>
                  <p class="text-white font-black text-xs leading-snug drop-shadow-sm">
                    {{ novedad.titulo }}
                  </p>
                </div>
              </div>
              <div class="bg-white px-3 py-2">
                <p class="text-[10px] text-gray-400 font-medium">{{ novedad.subtitulo }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Noticias DuaLab — Pinterest -->
        <div class="group cursor-pointer rounded-2xl p-3 -m-1 border border-orange-100
                    bg-orange-50/40 hover:bg-orange-50/70 transition-colors duration-200"
             role="button" tabindex="0"
             @click="abrirNoticias('dualab')"
             @keydown.enter="abrirNoticias('dualab')"
             @keydown.space.prevent="abrirNoticias('dualab')">
          <div class="flex items-center gap-3 mb-3 w-full text-left">
            <span class="w-1.5 h-1.5 rounded-full bg-gradient-to-r from-[#00A859] to-[#99CC33] shrink-0"></span>
            <span class="text-[10px] font-black uppercase tracking-widest shrink-0
                         text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">
              Noticias DuaLab
            </span>
            <div class="flex-1 h-px bg-[#00A859]/20 group-hover:bg-[#00A859]/40 transition-colors duration-200"></div>
            <svg class="w-3.5 h-3.5 text-[#00A859]/40 shrink-0 group-hover:text-[#00A859]
                        group-hover:translate-x-0.5 transition-all duration-200"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
          </div>
          <div class="columns-2 gap-3 space-y-3">
            <div v-for="noticia in previewNoticiasDualab" :key="noticia.id"
                 class="break-inside-avoid rounded-2xl overflow-hidden shadow-sm border border-white/50">
              <div class="relative overflow-hidden" :class="noticia.alturaClase">
                <img :src="noticia.imagen" :alt="noticia.alt" class="w-full h-full object-cover" loading="lazy" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent
                            flex flex-col justify-end p-3">
                  <span class="text-[8px] font-black uppercase tracking-widest text-white/75 mb-1">{{ noticia.categoria }}</span>
                  <p class="text-white font-black text-xs leading-snug drop-shadow-sm">
                    {{ noticia.titulo }}
                  </p>
                </div>
              </div>
              <div class="bg-white px-3 py-2">
                <p class="text-[10px] text-gray-400 font-medium">{{ noticia.subtitulo }}</p>
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>

  <!-- ── Modal catálogo BOE ─────────────────────────────────────────────────── -->
  <CatalogoBoeModal v-model:show="mostrarCatalogoBoe" />
</template>

