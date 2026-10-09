<!-- Panel "Historial · Resumen de encuentros": cabecera, accesos rápidos, filtros compactos
     y lista paginada de miniaturas. Se usa en /encuentros/crear (DashboardDocente) y en
     /seccion/encuentros (SeccionHub). Solo el contenido: la card contenedora la pone cada vista. -->
<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  encuentros: { type: Array, default: () => [] },
  cargando:   { type: Boolean, default: false },
})
const emit = defineEmits(['ver', 'eliminar'])

const router = useRouter()

// ─── Filtros y paginación ────────────────────────────────────────────────────
const filtroSes             = ref({ fecha: '', titulo: '', curso: '', grupo: '' })
const paginaEncuentros      = ref(1)
const ENCUENTROS_POR_PAGINA = 5

const encuentrosFiltrados = computed(() => {
  let lista = [...props.encuentros]
  if (filtroSes.value.fecha)
    lista = lista.filter(s => s.fecha === filtroSes.value.fecha)
  if (filtroSes.value.titulo.trim()) {
    const q = filtroSes.value.titulo.trim().toLowerCase()
    lista = lista.filter(s => (s.proyecto_titulo || '').toLowerCase().includes(q))
  }
  if (filtroSes.value.curso) lista = lista.filter(s => s.curso === filtroSes.value.curso)
  if (filtroSes.value.grupo) lista = lista.filter(s => s.grupo === filtroSes.value.grupo)
  return lista.sort((a, b) => (a.fecha < b.fecha ? 1 : a.fecha > b.fecha ? -1 : 0))
})

const encuentrosVisibles = computed(() => {
  const start = (paginaEncuentros.value - 1) * ENCUENTROS_POR_PAGINA
  return encuentrosFiltrados.value.slice(start, start + ENCUENTROS_POR_PAGINA)
})

const totalPaginasSes = computed(() =>
  Math.ceil(encuentrosFiltrados.value.length / ENCUENTROS_POR_PAGINA)
)

watch(filtroSes, () => { paginaEncuentros.value = 1 }, { deep: true })
// Si se elimina el último encuentro de la última página, no quedarse en una página vacía
watch(totalPaginasSes, (t) => { if (paginaEncuentros.value > Math.max(t, 1)) paginaEncuentros.value = Math.max(t, 1) })

function alumnadosDeEquipoEn(encuentro, n) {
  const equipo = (encuentro?.equipos || []).find(e => e.numero_equipo === n)
  if (equipo) {
    return equipo.miembros.map(m => m.alias ? `${m.nombre} (${m.alias})` : m.nombre)
  }
  // Encuentros sin equipos cargados todavía (o antiguos): snapshot plano, sin alias.
  return (encuentro?.alumnados || [])
    .filter(a => a.equipo_num === n)
    .map(a => a.nombre)
}

function formatFecha(isoDate) {
  if (!isoDate) return ''
  const d = new Date(isoDate + 'T12:00:00')
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' })
}
</script>

<template>
  <!-- @container: en una columna estrecha (crear encuentro) se ve compacto; a ancho
       completo (hub /seccion/encuentros) acciones y filtros se reparten en fila -->
  <div class="@container">
    <!-- Cabecera — banner azul -->
    <div class="relative overflow-hidden border-b border-blue-100
                bg-gradient-to-br from-blue-50 via-blue-50/60 to-indigo-50/40 px-5 py-4">
      <!-- Fondo decorativo -->
      <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full
                  bg-blue-100 blur-2xl pointer-events-none" />
      <div class="relative flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-blue-500 flex items-center justify-center shadow-sm flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                     M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-0.5">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-400" />
            <span class="text-[10px] font-black uppercase tracking-widest text-blue-500">Historial</span>
          </div>
          <h2 class="text-base font-black text-azul-noche tracking-tight leading-tight truncate">
            Resumen de encuentros
          </h2>
        </div>
        <span class="flex-shrink-0 text-[10px] font-black bg-blue-100 text-blue-600
                     px-2.5 py-1 rounded-full uppercase tracking-widest">
          {{ encuentros.length }}
        </span>
      </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="px-5 py-3 border-b border-gray-100
                @2xl:flex @2xl:items-center @2xl:gap-2">
      <!-- Botón Ver encuentros — prominente -->
      <button @click="router.push('/encuentros')"
              class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                     bg-centros text-white
                     text-[10px] font-black uppercase tracking-widest
                     hover:bg-centros/90 transition-all shadow-sm mb-2
                     @2xl:mb-0 @2xl:flex-1">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
        </svg>
        Ver todos los encuentros
      </button>
      <!-- name 'mis-grupos' (antes 'mis-equipos') — ver router/index.js -->
      <button @click="router.push({ name: 'mis-grupos' })"
              class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                     bg-violet-50 border border-violet-200 text-violet-700
                     text-[10px] font-black uppercase tracking-widest
                     hover:bg-violet-100 transition-all mb-2
                     @2xl:mb-0 @2xl:flex-1">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 3c0-1.1-.9-2-2-2h-8c-1.1 0-2 .9-2 2v1h12v-1z"/>
        </svg>
        Mis grupos — seguimiento
      </button>
      <button @click="router.push({ name: 'startup-day-crear' })"
              class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl
                     bg-primary-400/10 border border-primary-400/20 text-primary-700
                     text-[9px] font-black uppercase tracking-widest
                     hover:bg-primary-400/20 transition-all
                     @2xl:w-auto @2xl:py-2.5">
        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nueva propuesta
      </button>
    </div>

    <!-- Filtros compactos -->
    <div v-if="encuentros.length > 0" class="px-4 py-3 border-b border-gray-50 space-y-2
                @2xl:space-y-0 @2xl:grid @2xl:grid-cols-[minmax(0,1.5fr)_minmax(0,0.8fr)_minmax(0,1.4fr)] @2xl:gap-3 @2xl:items-end">
      <!-- Búsqueda por título -->
      <div class="relative">
        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-gray-300 pointer-events-none"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input v-model="filtroSes.titulo" type="text" placeholder="Buscar encuentro..."
               class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-7 pr-3 py-1.5
                      text-xs font-medium text-gray-700 placeholder-gray-300
                      focus:outline-none focus:border-centros/50 focus:ring-1 focus:ring-centros/20" />
      </div>
      <!-- Fecha -->
      <input v-model="filtroSes.fecha" type="date"
             class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5
                    text-xs font-medium text-gray-600
                    focus:outline-none focus:border-centros/50 focus:ring-1 focus:ring-centros/20" />
      <!-- Curso + Grupo -->
      <div class="grid grid-cols-2 gap-2">
        <div>
          <p class="text-[8px] font-black uppercase tracking-widest text-gray-400 mb-1">Curso</p>
          <div class="flex gap-1">
            <button v-for="c in ['', '1º', '2º']" :key="'fc'+c"
                    @click="filtroSes.curso = c"
                    class="flex-1 py-1 rounded-lg text-[8px] font-black uppercase border transition-all"
                    :class="filtroSes.curso === c
                      ? 'bg-centros border-centros text-white'
                      : 'bg-gray-50 border-gray-200 text-gray-400 hover:border-centros/40'">
              {{ c === '' ? '·' : c }}
            </button>
          </div>
        </div>
        <div>
          <p class="text-[8px] font-black uppercase tracking-widest text-gray-400 mb-1">Clase</p>
          <div class="flex gap-0.5">
            <button v-for="g in ['', 'A', 'B', 'C', 'D']" :key="'fg'+g"
                    @click="filtroSes.grupo = g"
                    class="flex-1 py-1 rounded-md text-[7px] font-black uppercase border transition-all"
                    :class="filtroSes.grupo === g
                      ? 'bg-primary-400 border-primary-400 text-white'
                      : 'bg-gray-50 border-gray-200 text-gray-400 hover:border-primary-400/40'">
              {{ g === '' ? '·' : g }}
            </button>
          </div>
        </div>
      </div>
      <!-- Limpiar filtros -->
      <div v-if="filtroSes.titulo || filtroSes.fecha || filtroSes.curso || filtroSes.grupo"
           class="flex justify-end @2xl:col-span-3">
        <button @click="filtroSes = { fecha: '', titulo: '', curso: '', grupo: '' }"
                class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-red-400 transition-colors">
          Limpiar filtros
        </button>
      </div>
    </div>

    <!-- Cargando encuentros -->
    <div v-if="cargandoEncuentros" class="px-5 py-10 flex justify-center">
      <svg class="animate-spin w-5 h-5 text-centros" viewBox="0 0 24 24">
        <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
      </svg>
    </div>

    <!-- Estado vacío -->
    <div v-else-if="encuentros.length === 0" class="px-5 py-10 text-center">
      <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-100
                  flex items-center justify-center mx-auto mb-3">
        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
        </svg>
      </div>
      <p class="text-xs text-gray-400 font-medium leading-relaxed">
        Aún no hay encuentros.<br>¡Registra el primero!
      </p>
    </div>

    <!-- Sin resultados tras filtrar -->
    <div v-else-if="!cargandoEncuentros && encuentrosFiltrados.length === 0" class="px-5 py-8 text-center">
      <p class="text-xs text-gray-400 font-medium">Sin resultados para esos filtros.</p>
    </div>

    <!-- Lista de encuentros (miniaturas) -->
    <ul v-else-if="!cargandoEncuentros" class="divide-y divide-gray-50">
      <li v-for="s in encuentrosVisibles" :key="s.id"
          class="px-4 py-3 hover:bg-gray-50/60 transition-colors group cursor-pointer"
          @click="emit('ver', s)">
        <div class="flex items-start justify-between gap-2">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-black text-[#1F2937] leading-snug truncate
                      group-hover:text-centros transition-colors">
              {{ s.proyecto_titulo || '(sin título)' }}
            </p>
            <p class="text-[10px] text-centros font-bold mt-0.5">
              {{ formatFecha(s.fecha) }}
            </p>
            <div class="flex flex-wrap gap-1 mt-1">
              <span v-if="s.curso"       class="tag tag-green">{{ s.curso }}</span>
              <span v-if="s.grupo"       class="tag tag-lime">Clase {{ s.grupo }}</span>
              <span v-if="s.num_alumnos" class="tag tag-gray">{{ s.num_alumnos }} al.</span>
              <span v-if="s.num_equipos" class="tag tag-gray">{{ s.num_equipos }} gr.</span>
            </div>
            <div v-if="s.num_equipos" class="space-y-0.5 mt-1">
              <p v-for="n in s.num_equipos" :key="n"
                 class="text-[9px] text-gray-400 truncate">
                <span class="font-black text-centros">Gr.{{ n }}</span>
                {{ alumnadosDeEquipoEn(s, n).join(', ') || 'Sin alumnos' }}
              </p>
            </div>
          </div>
          <div class="flex flex-col items-end gap-1 flex-shrink-0">
            <button @click.stop="emit('eliminar', s)"
                    class="p-1 rounded-lg hover:bg-red-50 text-gray-300 hover:text-red-400 transition-all"
                    title="Eliminar">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </button>
          </div>
        </div>
      </li>
    </ul>

    <!-- Paginación -->
    <div v-if="totalPaginasSes > 1"
         class="px-4 py-3 border-t border-gray-50 flex items-center justify-between gap-2">
      <button @click="paginaEncuentros--"
              :disabled="paginaEncuentros === 1"
              class="p-1.5 rounded-lg border border-gray-200 text-gray-400
                     hover:border-centros hover:text-centros
                     disabled:opacity-30 disabled:cursor-not-allowed transition-all">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
        {{ paginaEncuentros }} / {{ totalPaginasSes }}
      </span>
      <button @click="paginaEncuentros++"
              :disabled="paginaEncuentros === totalPaginasSes"
              class="p-1.5 rounded-lg border border-gray-200 text-gray-400
                     hover:border-centros hover:text-centros
                     disabled:opacity-30 disabled:cursor-not-allowed transition-all">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
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
.tag-green { background: rgba(48,114,170,0.1); color: #3072AA; }
.tag-lime  { background: rgba(107,164,213,0.12); color: #275d8a; }
</style>
