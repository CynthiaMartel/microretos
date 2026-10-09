<!-- Ruta: /notificaciones (name: notificaciones). Avisos in-app del docente que genera
     el backend (app/Notifications): empresa responde a una propuesta, proyecto pendiente
     de validar, equipo completa una fase e invitación a un encuentro de otro docente. -->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { getNotificaciones, marcarNotificacionLeida, marcarTodasLeidas } from '../services/notificacionService.js'
import { useNotificaciones } from '../composables/useNotificaciones.js'

const router = useRouter()
const { noLeidas, refrescarNoLeidas } = useNotificaciones()

// Presentación por tipo: icono (trazo SVG), color de marca y filtro al que pertenece
const TIPOS = {
  propuesta_validada:        { filtro: 'empresas',   tile: 'bg-empresas',         icon: 'M9 12l2 2 4-4M3 21h18M5 21V7l7-4 7 4v14' },
  propuesta_no_validada_aun: { filtro: 'empresas',   tile: 'bg-alumnos',          icon: 'M12 8v4l2 2M3 21h18M5 21V7l7-4 7 4v14' },
  pendiente_validar:         { filtro: 'validacion', tile: 'bg-centros',          icon: 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11' },
  equipo_fase:               { filtro: 'equipos',    tile: 'bg-administraciones', icon: 'M3 3v18h18M7 15l4-6 4 4 5-8' },
  invitacion_encuentro:      { filtro: 'encuentros', tile: 'bg-azul-noche',       icon: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM19 8v6M22 11h-6' },
}
const tipoDe = (n) => TIPOS[n.tipo] ?? { filtro: 'otros', tile: 'bg-gray-400', icon: 'M12 8v4M12 16h.01M12 22a10 10 0 100-20 10 10 0 000 20z' }

const FILTROS = [
  { key: 'todas',      label: 'Todas' },
  { key: 'no_leidas',  label: 'No leídas' },
  { key: 'empresas',   label: 'Empresas' },
  { key: 'validacion', label: 'Validación' },
  { key: 'equipos',    label: 'Grupos' },
  { key: 'encuentros', label: 'Encuentros' },
]
const filtro = ref('todas')

// ── Carga paginada ("Cargar más") ─────────────────────────────────────────────
const items      = ref([])
const pagina     = ref(0)
const ultima     = ref(1)
const cargando   = ref(false)
const error      = ref('')

async function cargar(reiniciar = false) {
  if (cargando.value) return
  cargando.value = true
  error.value = ''
  try {
    const siguiente = reiniciar ? 1 : pagina.value + 1
    const { data } = await getNotificaciones({
      page: siguiente, per_page: 20,
      ...(filtro.value === 'no_leidas' ? { solo_no_leidas: 1 } : {}),
    })
    items.value  = reiniciar ? data.data : [...items.value, ...data.data]
    pagina.value = data.meta.current_page
    ultima.value = data.meta.last_page
  } catch {
    error.value = 'No se han podido cargar las notificaciones.'
  } finally {
    cargando.value = false
  }
}
onMounted(() => { cargar(true); refrescarNoLeidas({ forzar: true }) })
// "No leídas" filtra en el servidor; el resto de filtros, sobre lo ya cargado
watch(filtro, (nuevo, anterior) => { if (nuevo === 'no_leidas' || anterior === 'no_leidas') cargar(true) })

const visibles = computed(() =>
  ['todas', 'no_leidas'].includes(filtro.value) ? items.value : items.value.filter(n => tipoDe(n).filtro === filtro.value))

// ── Acciones ──────────────────────────────────────────────────────────────────
async function abrir(n) {
  if (!n.leida) {
    n.leida = true
    noLeidas.value = Math.max(0, noLeidas.value - 1)
    marcarNotificacionLeida(n.id).catch(() => { /* se reintentará al volver a abrirla */ })
  }
  if (n.ruta) router.push(n.ruta)
}
async function leerTodas() {
  try {
    await marcarTodasLeidas()
    items.value.forEach(n => { n.leida = true })
    noLeidas.value = 0
  } catch {
    error.value = 'No se han podido marcar como leídas.'
  }
}

const haceCuanto = (isoStr) => {
  const min = Math.floor((Date.now() - new Date(isoStr).getTime()) / 60_000)
  if (min < 1) return 'Ahora'
  if (min < 60) return `Hace ${min} min`
  const h = Math.floor(min / 60)
  if (h < 24) return `Hace ${h} h`
  const d = Math.floor(h / 24)
  if (d < 7) return `Hace ${d} día${d > 1 ? 's' : ''}`
  return new Date(isoStr).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })
}
</script>

<template>
  <div class="min-h-screen bg-[#F3F6FA] font-sans text-azul-noche pt-16">
    <div class="mx-auto max-w-[900px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Notificaciones"
                       subtitulo="Respuestas de empresas, proyectos que esperan tu validación, fases completadas por tus grupos e invitaciones a encuentros.">
        <button v-if="noLeidas" @click="leerTodas"
                class="flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-centros/30 bg-white px-4 text-sm font-semibold text-centros shadow-sm hover:bg-centros/5">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Marcar todas como leídas
        </button>
      </CabeceraSeccion>

      <article class="card p-4">
        <!-- Filtros, mismo estilo de chips que los filtros de proyectos del panel docente -->
        <div class="mb-3 flex flex-wrap gap-1.5" role="tablist" aria-label="Filtrar notificaciones">
          <button v-for="f in FILTROS" :key="f.key" role="tab" :aria-selected="filtro === f.key" @click="filtro = f.key"
                  class="rounded-full px-3 py-1 text-xs font-semibold transition"
                  :class="filtro === f.key ? 'bg-centros text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
            {{ f.label }}<span v-if="f.key === 'no_leidas' && noLeidas"> · {{ noLeidas }}</span>
          </button>
        </div>

        <p v-if="error" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <div v-if="cargando && !items.length" class="space-y-2"><div v-for="n in 4" :key="n" class="h-16 animate-pulse rounded-xl bg-gray-100" /></div>
        <div v-else-if="!visibles.length" class="flex flex-col items-center justify-center py-12 text-center text-sm text-gray-500">
          <svg class="mb-2 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
          {{ filtro === 'no_leidas' ? 'No tienes notificaciones sin leer.' : 'No hay notificaciones por ahora.' }}
        </div>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="n in visibles" :key="n.id">
            <button class="flex w-full items-start gap-3 rounded-xl px-2 py-3 text-left transition hover:bg-gray-50" @click="abrir(n)">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white shadow-sm" :class="tipoDe(n).tile">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="tipoDe(n).icon" /></svg>
              </span>
              <span class="min-w-0 flex-1">
                <span class="flex items-center gap-2">
                  <span class="truncate text-sm" :class="n.leida ? 'font-medium text-gray-700' : 'font-bold'">{{ n.titulo }}</span>
                  <span v-if="!n.leida" class="h-2 w-2 shrink-0 rounded-full bg-alumnos" aria-label="Sin leer" />
                </span>
                <span class="mt-0.5 block text-sm leading-snug text-gray-500">{{ n.mensaje }}</span>
              </span>
              <span class="shrink-0 pt-0.5 text-xs text-gray-400">{{ haceCuanto(n.created_at) }}</span>
            </button>
          </li>
        </ul>

        <div v-if="pagina < ultima" class="mt-3 flex justify-center">
          <button class="link disabled:opacity-50" :disabled="cargando" @click="cargar()">{{ cargando ? 'Cargando…' : 'Cargar más' }}</button>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.card { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
.link { @apply text-sm font-semibold text-centros hover:underline; }
</style>
