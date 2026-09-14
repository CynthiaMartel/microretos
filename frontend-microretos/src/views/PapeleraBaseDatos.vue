<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api.js'

// ─── Estado ───────────────────────────────────────────────────────────────────
const cargando    = ref(true)
const error       = ref('')
const items       = ref({ empresas: [], microretos: [], ciclos: [], familias: [], centros: [], proyectos: [], sesiones: [] })
const total       = ref(0)
const filtroActivo = ref('todos')

// Modal borrado permanente
const modalEliminar  = ref(false)
const itemAEliminar  = ref(null)
const eliminando     = ref(false)
const errorEliminar  = ref('')

// Modal vaciar papelera
const modalVaciar    = ref(false)
const confirmVaciar  = ref('')
const vaciando       = ref(false)
const errorVaciar    = ref('')

// Modal restaurar (feedback visual)
const restaurando    = ref(null) // id del item restaurándose

// ─── Configuración de tipos ───────────────────────────────────────────────────
const TIPOS = {
  empresas: {
    label: 'Empresa',
    labelPlural: 'Empresas',
    color: '#00A859',
    bgLight: 'bg-[#00A859]/10',
    textColor: 'text-[#00A859]',
    borderColor: 'border-[#00A859]/20',
  },
  microretos: {
    label: 'Reto',
    labelPlural: 'Retos',
    color: '#6366f1',
    bgLight: 'bg-indigo-50',
    textColor: 'text-indigo-600',
    borderColor: 'border-indigo-200',
  },
  ciclos: {
    label: 'Ciclo formativo',
    labelPlural: 'Ciclos formativos',
    color: '#f59e0b',
    bgLight: 'bg-amber-50',
    textColor: 'text-amber-600',
    borderColor: 'border-amber-200',
  },
  familias: {
    label: 'Familia profesional',
    labelPlural: 'Familias',
    color: '#99CC33',
    bgLight: 'bg-lime-50',
    textColor: 'text-lime-700',
    borderColor: 'border-lime-200',
  },
  centros: {
    label: 'Centro educativo',
    labelPlural: 'Centros educativos',
    color: '#3b82f6',
    bgLight: 'bg-blue-50',
    textColor: 'text-blue-600',
    borderColor: 'border-blue-200',
  },
  proyectos: {
    label: 'Proyecto',
    labelPlural: 'Proyectos',
    color: '#8b5cf6',
    bgLight: 'bg-violet-50',
    textColor: 'text-violet-600',
    borderColor: 'border-violet-200',
  },
  sesiones: {
    label: 'Sesión',
    labelPlural: 'Sesiones',
    color: '#ec4899',
    bgLight: 'bg-pink-50',
    textColor: 'text-pink-600',
    borderColor: 'border-pink-200',
  },
}

// ─── Computed ─────────────────────────────────────────────────────────────────
const itemsFiltrados = computed(() => {
  if (filtroActivo.value === 'todos') {
    return Object.entries(items.value)
      .flatMap(([tipo, lista]) => lista)
      .sort((a, b) => new Date(b.deleted_at) - new Date(a.deleted_at))
  }
  return items.value[filtroActivo.value] ?? []
})

const contadorPorTipo = computed(() =>
  Object.fromEntries(
    Object.entries(items.value).map(([tipo, lista]) => [tipo, lista.length])
  )
)

// ─── API ──────────────────────────────────────────────────────────────────────
async function cargarPapelera() {
  cargando.value = true
  error.value = ''
  try {
    const res = await api.get('/papelera')
    items.value = { ...items.value, ...res.data.items }
    total.value = res.data.total
  } catch (e) {
    error.value = 'No se pudo cargar la papelera. Comprueba la conexión.'
  } finally {
    cargando.value = false
  }
}

async function restaurar(item) {
  restaurando.value = item.id + item.tipo
  try {
    await api.patch(`/papelera/${item.tipo}/${item.id}/restaurar`)
    items.value[item.tipo] = items.value[item.tipo].filter(i => i.id !== item.id)
    total.value--
  } catch (e) {
    // error silencioso — el item sigue en lista
  } finally {
    restaurando.value = null
  }
}

function abrirModalEliminar(item) {
  itemAEliminar.value = item
  errorEliminar.value = ''
  modalEliminar.value = true
}

function cerrarModalEliminar() {
  modalEliminar.value = false
  itemAEliminar.value = null
  errorEliminar.value = ''
}

async function confirmarEliminar() {
  if (!itemAEliminar.value) return
  eliminando.value = true
  errorEliminar.value = ''
  try {
    const { tipo, id } = itemAEliminar.value
    await api.delete(`/papelera/${tipo}/${id}`)
    items.value[tipo] = items.value[tipo].filter(i => i.id !== id)
    total.value--
    cerrarModalEliminar()
  } catch (e) {
    errorEliminar.value = 'No se pudo eliminar. Inténtalo de nuevo.'
  } finally {
    eliminando.value = false
  }
}

function abrirModalVaciar() {
  confirmVaciar.value = ''
  errorVaciar.value = ''
  modalVaciar.value = true
}

function cerrarModalVaciar() {
  modalVaciar.value = false
  confirmVaciar.value = ''
  errorVaciar.value = ''
}

async function confirmarVaciar() {
  if (confirmVaciar.value !== 'VACIAR') return
  vaciando.value = true
  errorVaciar.value = ''
  try {
    const params = filtroActivo.value !== 'todos' ? `?tipo=${filtroActivo.value}` : ''
    await api.delete(`/papelera${params}`)
    await cargarPapelera()
    cerrarModalVaciar()
  } catch (e) {
    errorVaciar.value = 'No se pudo vaciar la papelera. Inténtalo de nuevo.'
  } finally {
    vaciando.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatearFecha(iso) {
  if (!iso) return '—'
  const fecha = new Date(iso)
  const ahora = new Date()
  const diffMs = ahora - fecha
  const diffDias = Math.floor(diffMs / 86400000)
  const diffHoras = Math.floor(diffMs / 3600000)
  const diffMin = Math.floor(diffMs / 60000)

  if (diffMin < 1)    return 'hace un momento'
  if (diffMin < 60)   return `hace ${diffMin} min`
  if (diffHoras < 24) return `hace ${diffHoras} h`
  if (diffDias === 1) return 'ayer'
  if (diffDias < 30)  return `hace ${diffDias} días`
  return fecha.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(cargarPapelera)
</script>

<template>
  <div class="min-h-screen pb-20 pt-12 md:pt-12">

    <!-- ═══ HEADER ═══════════════════════════════════════════════════════════ -->
    <div class="bg-white border-b border-gray-100 shadow-sm">
      <div class="max-w-4xl mx-auto px-6 py-6 pl-20 lg:pl-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">

          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-red-50 border border-red-100 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <polyline points="3 6 5 6 21 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M10 11v6M14 11v6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div>
              <h1 class="text-xl font-black text-[#1F2937] tracking-tight">Papelera</h1>
              <p class="text-xs text-gray-400 mt-0.5">
                Elementos eliminados de la base de datos — restáuralos o bórralos definitivamente
              </p>
            </div>
          </div>

          <!-- Vaciar papelera -->
          <button
            v-if="total > 0 && !cargando"
            @click="abrirModalVaciar"
            class="flex items-center gap-2 px-4 py-2 rounded-2xl
                   bg-red-50 border border-red-200 text-red-600
                   hover:bg-red-100 hover:border-red-300
                   font-bold text-sm transition-all duration-150"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <polyline points="3 6 5 6 21 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Vaciar papelera
            <span class="ml-1 px-1.5 py-0.5 rounded-full bg-red-200 text-red-700 text-[10px] font-black">
              {{ total }}
            </span>
          </button>

        </div>

        <!-- Tabs de filtro -->
        <div class="flex flex-wrap gap-1.5 mt-5">
          <button
            @click="filtroActivo = 'todos'"
            class="px-3 py-1.5 rounded-full text-xs font-bold transition-all duration-150"
            :class="filtroActivo === 'todos'
              ? 'bg-[#1F2937] text-white'
              : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
          >
            Todos
            <span class="ml-1 opacity-70">{{ total }}</span>
          </button>

          <button
            v-for="(cfg, tipo) in TIPOS"
            :key="tipo"
            @click="filtroActivo = tipo"
            class="px-3 py-1.5 rounded-full text-xs font-bold transition-all duration-150"
            :class="filtroActivo === tipo
              ? 'text-white'
              : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
            :style="filtroActivo === tipo ? { backgroundColor: cfg.color } : {}"
          >
            {{ cfg.labelPlural }}
            <span class="ml-1 opacity-70">{{ contadorPorTipo[tipo] }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ CONTENIDO ════════════════════════════════════════════════════════ -->
    <div class="max-w-4xl mx-auto px-6 py-6 pl-20 lg:pl-6">

      <!-- Estado cargando -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-20 gap-3">
        <svg class="w-8 h-8 text-gray-300 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        <p class="text-sm text-gray-400">Cargando papelera…</p>
      </div>

      <!-- Estado error -->
      <div v-else-if="error" class="rounded-2xl border border-red-100 bg-red-50 p-6 text-center">
        <p class="text-sm font-bold text-red-600">{{ error }}</p>
        <button
          @click="cargarPapelera"
          class="mt-3 px-4 py-2 rounded-xl bg-red-100 text-red-700 text-sm font-bold hover:bg-red-200 transition-colors"
        >
          Reintentar
        </button>
      </div>

      <!-- Estado vacío -->
      <div v-else-if="itemsFiltrados.length === 0" class="flex flex-col items-center justify-center py-20 gap-4">
        <div class="w-16 h-16 rounded-2xl bg-gray-100 border border-gray-200 flex items-center justify-center">
          <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <polyline points="3 6 5 6 21 6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-black text-gray-400 text-sm">Papelera vacía</p>
          <p class="text-xs text-gray-300 mt-1">
            {{ filtroActivo === 'todos'
               ? 'No hay elementos eliminados'
               : `No hay ${TIPOS[filtroActivo]?.labelPlural?.toLowerCase()} eliminados` }}
          </p>
        </div>
      </div>

      <!-- Lista de items -->
      <div v-else class="space-y-2">

        <!-- Item card -->
        <div
          v-for="item in itemsFiltrados"
          :key="item.tipo + item.id"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm
                 flex items-center gap-4 px-5 py-4
                 transition-all duration-150 hover:border-gray-200 hover:shadow"
        >

          <!-- Icono de tipo -->
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
            :class="TIPOS[item.tipo]?.bgLight"
          >
            <!-- Empresa -->
            <svg v-if="item.tipo === 'empresas'"
                 class="w-4.5 h-4.5" :class="TIPOS[item.tipo]?.textColor"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <!-- Microreto -->
            <svg v-else-if="item.tipo === 'microretos'"
                 class="w-4.5 h-4.5" :class="TIPOS[item.tipo]?.textColor"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <!-- Ciclo -->
            <svg v-else-if="item.tipo === 'ciclos'"
                 class="w-4.5 h-4.5" :class="TIPOS[item.tipo]?.textColor"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 14l9-5-9-5-9 5 9 5z"/>
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 14l6.16-3.422A12 12 0 0112 21.5a12 12 0 01-6.16-10.922L12 14z"/>
            </svg>
            <!-- Familia -->
            <svg v-else-if="item.tipo === 'familias'"
                 class="w-4.5 h-4.5" :class="TIPOS[item.tipo]?.textColor"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
            </svg>
            <!-- Centro -->
            <svg v-else-if="item.tipo === 'centros'"
                 class="w-4.5 h-4.5" :class="TIPOS[item.tipo]?.textColor"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <rect x="2" y="7" width="20" height="15" rx="2" stroke-linecap="round"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 21V7a4 4 0 00-8 0v14"/>
              <line x1="12" y1="7" x2="12" y2="21"/>
            </svg>
            <!-- Proyecto -->
            <svg v-else-if="item.tipo === 'proyectos'"
                 class="w-4.5 h-4.5" :class="TIPOS[item.tipo]?.textColor"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
            </svg>
            <!-- Sesión -->
            <svg v-else
                 class="w-4.5 h-4.5" :class="TIPOS[item.tipo]?.textColor"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>

          <!-- Contenido -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="text-sm font-black text-[#1F2937] truncate">{{ item.nombre }}</p>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0"
                :class="[TIPOS[item.tipo]?.bgLight, TIPOS[item.tipo]?.textColor]"
              >
                {{ TIPOS[item.tipo]?.label }}
              </span>
            </div>
            <p class="text-xs text-gray-400 mt-0.5">
              Eliminado {{ formatearFecha(item.deleted_at) }}
            </p>
          </div>

          <!-- Acciones -->
          <div class="flex items-center gap-2 shrink-0">

            <!-- Restaurar -->
            <button
              @click="restaurar(item)"
              :disabled="restaurando === item.id + item.tipo"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                     bg-[#00A859]/10 border border-[#00A859]/20 text-[#00A859]
                     hover:bg-[#00A859]/20 hover:border-[#00A859]/40
                     font-bold text-xs transition-all duration-150
                     disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="restaurando !== item.id + item.tipo"
                   class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <polyline points="1 4 1 10 7 10"/>
                <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
              </svg>
              <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              Restaurar
            </button>

            <!-- Eliminar permanentemente -->
            <button
              @click="abrirModalEliminar(item)"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                     bg-red-50 border border-red-100 text-red-500
                     hover:bg-red-100 hover:border-red-200 hover:text-red-600
                     font-bold text-xs transition-all duration-150"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <polyline points="3 6 5 6 21 6" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Eliminar
            </button>

          </div>
        </div>

      </div>
    </div>

    <!-- ═══ MODAL: Confirmar eliminación permanente ═══════════════════════════ -->
    <Transition name="fade">
      <div
        v-if="modalEliminar"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="cerrarModalEliminar"
      >
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-7">

          <!-- Icono de advertencia -->
          <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-2xl bg-red-50 border border-red-100 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-base font-black text-[#1F2937]">Eliminar permanentemente</h2>
              <p class="text-xs text-gray-400">Esta acción no se puede deshacer</p>
            </div>
          </div>

          <p class="text-sm text-gray-600 mb-1">
            Vas a eliminar definitivamente:
          </p>
          <div class="px-4 py-3 rounded-xl bg-gray-50 border border-gray-100 mb-5">
            <p class="font-black text-[#1F2937] text-sm">{{ itemAEliminar?.nombre }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
              {{ TIPOS[itemAEliminar?.tipo]?.label }}
            </p>
          </div>

          <p class="text-xs text-gray-500 mb-5">
            El elemento y todos sus datos asociados desaparecerán permanentemente de la base de datos.
          </p>

          <div v-if="errorEliminar" class="mb-4 px-3 py-2 rounded-xl bg-red-50 border border-red-100 text-red-600 text-xs font-bold">
            {{ errorEliminar }}
          </div>

          <div class="flex gap-2">
            <button
              @click="cerrarModalEliminar"
              :disabled="eliminando"
              class="flex-1 px-4 py-2.5 rounded-2xl border border-gray-200 text-gray-600
                     font-bold text-sm hover:bg-gray-50 transition-colors disabled:opacity-50"
            >
              Cancelar
            </button>
            <button
              @click="confirmarEliminar"
              :disabled="eliminando"
              class="flex-1 px-4 py-2.5 rounded-2xl bg-red-500 text-white
                     font-black text-sm hover:bg-red-600 transition-colors
                     disabled:opacity-50 disabled:cursor-not-allowed
                     flex items-center justify-center gap-2"
            >
              <svg v-if="eliminando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ eliminando ? 'Eliminando…' : 'Sí, eliminar' }}
            </button>
          </div>

        </div>
      </div>
    </Transition>

    <!-- ═══ MODAL: Vaciar papelera ════════════════════════════════════════════ -->
    <Transition name="fade">
      <div
        v-if="modalVaciar"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="cerrarModalVaciar"
      >
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-7">

          <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-2xl bg-red-100 border border-red-200 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <polyline points="3 6 5 6 21 6" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M10 11v6M14 11v6" stroke-linecap="round"/>
                <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" stroke-linecap="round"/>
              </svg>
            </div>
            <div>
              <h2 class="text-base font-black text-[#1F2937]">Vaciar papelera</h2>
              <p class="text-xs text-gray-400">
                {{ filtroActivo === 'todos'
                   ? `${total} elementos serán eliminados permanentemente`
                   : `${contadorPorTipo[filtroActivo]} ${TIPOS[filtroActivo]?.labelPlural?.toLowerCase()} serán eliminados` }}
              </p>
            </div>
          </div>

          <p class="text-sm text-gray-600 mb-4">
            Esta acción es <strong>irreversible</strong>. Todos los elementos se borrarán definitivamente
            de la base de datos junto con sus datos asociados.
          </p>

          <p class="text-xs text-gray-500 mb-2">
            Escribe <strong class="font-black text-red-600 tracking-widest">VACIAR</strong> para confirmar:
          </p>
          <input
            v-model="confirmVaciar"
            type="text"
            placeholder="VACIAR"
            class="w-full px-4 py-2.5 rounded-xl border text-sm font-black tracking-widest
                   focus:outline-none transition-colors mb-5"
            :class="confirmVaciar === 'VACIAR'
              ? 'border-red-400 bg-red-50 text-red-600 focus:border-red-500'
              : 'border-gray-200 bg-gray-50 text-gray-400 focus:border-gray-300'"
          />

          <div v-if="errorVaciar" class="mb-4 px-3 py-2 rounded-xl bg-red-50 border border-red-100 text-red-600 text-xs font-bold">
            {{ errorVaciar }}
          </div>

          <div class="flex gap-2">
            <button
              @click="cerrarModalVaciar"
              :disabled="vaciando"
              class="flex-1 px-4 py-2.5 rounded-2xl border border-gray-200 text-gray-600
                     font-bold text-sm hover:bg-gray-50 transition-colors disabled:opacity-50"
            >
              Cancelar
            </button>
            <button
              @click="confirmarVaciar"
              :disabled="confirmVaciar !== 'VACIAR' || vaciando"
              class="flex-1 px-4 py-2.5 rounded-2xl bg-red-600 text-white
                     font-black text-sm hover:bg-red-700 transition-colors
                     disabled:opacity-30 disabled:cursor-not-allowed
                     flex items-center justify-center gap-2"
            >
              <svg v-if="vaciando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ vaciando ? 'Vaciando…' : 'Vaciar papelera' }}
            </button>
          </div>

        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
