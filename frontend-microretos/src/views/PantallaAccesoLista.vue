<!-- Ruta: /pantalla-acceso (name: pantalla-acceso-lista). Antes vivía en /dashboard/pantalla-acceso — ver router/index.js.
     Lanzador para dar acceso al alumnado en clase (no es otra biblioteca de encuentros: explorar
     es cosa de /encuentros y Mis grupos). Arriba «En curso hoy» (AccesoRapidoEncuentros, también en
     /seccion/alumnado) con el código y «Proyectar»; debajo, el resto en una lista compacta con solo
     buscador, clase y curso académico. -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import FiltrosPanel from '../components/FiltrosPanel.vue'
import FiltroSegmentado from '../components/FiltroSegmentado.vue'
import SelectorCurso from '../components/SelectorCurso.vue'
import AccesoRapidoEncuentros from '../components/AccesoRapidoEncuentros.vue'
import { useCursoAcademicoStore, cursoDeEncuentro } from '../stores/cursoAcademico.js'
import { formatCurso } from '../utils/formatCurso.js'

const router = useRouter()

const cargando   = ref(true)
const error      = ref('')
const encuentros = ref([])

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    encuentros.value = (await api.get('/encuentros')).data
  } catch {
    error.value = 'No se han podido cargar tus encuentros.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

// Solo se puede proyectar lo que ya tiene grupos y código de acceso
const hoy = new Date().toISOString().slice(0, 10)
const dia = (f) => String(f || '').slice(0, 10)
const proyectables = computed(() => encuentros.value.filter(e => e.codigo_clase && e.microproyecto_uuid))
const enCursoHoy = (e) => dia(e.fecha) && dia(e.fecha) <= hoy && dia(e.fecha_fin || e.fecha) >= hoy

// ── «Otros encuentros»: el resto, con tres filtros ──────────────────────────
const cursoStore     = useCursoAcademicoStore()
const cursosConDatos = computed(() => [...new Set(proyectables.value.map(cursoDeEncuentro))])
const busqueda    = ref('')
const filtroClase = ref('')
const titulo = (e) => e.proyecto_titulo || (e.grupo && `Clase ${e.grupo}`) || 'Sin nombre'
const otros = computed(() => proyectables.value
  .filter(e => !enCursoHoy(e) && cursoStore.coincide(cursoDeEncuentro(e)))
  .sort((a, b) => dia(b.fecha).localeCompare(dia(a.fecha))))
const clasesDisponibles = computed(() => [...new Set(otros.value.map(e => e.grupo).filter(Boolean))].sort())
const opcionesClase = computed(() => [{ value: '', label: 'Todas' }, ...clasesDisponibles.value.map(c => ({ value: c, label: c }))])
const filtrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return otros.value.filter(e =>
    (!filtroClase.value || e.grupo === filtroClase.value) &&
    (!q || [titulo(e), e.codigo_clase, e.ciclo_formativo, ...(e.equipos || []).flatMap(eq => (eq.miembros || []).map(m => m.alias))]
      .filter(Boolean).join(' ').toLowerCase().includes(q)))
})
const hayFiltros = computed(() => !!(busqueda.value.trim() || filtroClase.value))
function limpiar() { busqueda.value = ''; filtroClase.value = '' }

// «Ver más» en vez de páginas: se busca uno concreto, normalmente de los más recientes
const PASO = 20
const limite = ref(PASO)
watch([busqueda, filtroClase, () => cursoStore.curso], () => { limite.value = PASO })
const visibles = computed(() => filtrados.value.slice(0, limite.value))

const fecha = (f) => { const d = new Date(dia(f) + 'T12:00:00'); return isNaN(d) ? '' : d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) }
const proyectar = (e) => router.push({ name: 'pantalla-acceso', params: { uuid: e.microproyecto_uuid } })
const aviso = ref('')
async function copiar(codigo) {
  try { await navigator.clipboard.writeText(codigo); aviso.value = `Código ${codigo} copiado` } catch { aviso.value = `Código: ${codigo}` }
  setTimeout(() => { aviso.value = '' }, 2500)
}
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Dar acceso al" destacado="alumnado" color="text-alumnos" volver="/seccion/alumnado"
                       subtitulo="Proyecta en clase el QR y el código del encuentro para que el alumnado entre en su espacio de trabajo." />

      <!-- Error de carga -->
      <div v-if="error && !cargando" class="flex flex-col items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center" role="alert">
        <p class="min-w-0 flex-1 text-sm font-medium text-red-700">{{ error }}</p>
        <button type="button" @click="cargar" class="inline-flex h-10 items-center rounded-full bg-white px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Reintentar</button>
      </div>

      <!-- 1. En curso hoy: lo que normalmente se proyecta -->
      <AccesoRapidoEncuentros v-if="!error" :encuentros="encuentros" :cargando-externo="cargando" />

      <!-- 2. El resto, en lista compacta -->
      <section v-if="!error" class="space-y-3" aria-labelledby="titulo-otros">
        <h2 id="titulo-otros" class="font-heading text-lg font-bold text-azul-noche">
          Otros encuentros <span v-if="!cargando" class="font-sans text-base font-semibold text-texto-secundario">· {{ filtrados.length }}</span>
        </h2>

        <FiltrosPanel v-model:busqueda="busqueda" placeholder="Buscar por proyecto, código o alumno…"
                      :hay-activos="hayFiltros" @limpiar="limpiar">
          <template #curso>
            <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
          </template>
          <FiltroSegmentado v-if="clasesDisponibles.length > 1" label="Clase" v-model="filtroClase" :opciones="opcionesClase" />
        </FiltrosPanel>

        <div v-if="cargando" class="space-y-2" aria-busy="true">
          <div v-for="n in 5" :key="n" class="h-16 animate-pulse rounded-2xl border border-borde bg-white" />
        </div>

        <p v-else-if="!proyectables.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-10 text-center text-sm text-texto-secundario">
          Ningún encuentro tiene grupos ni código todavía. Se crean al registrar un encuentro en
          <RouterLink to="/encuentros" class="font-semibold text-rol-fuerte hover:underline">Encuentros</RouterLink>.
        </p>
        <p v-else-if="!filtrados.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-10 text-center text-sm text-texto-secundario">
          {{ hayFiltros ? 'Ningún encuentro coincide con la búsqueda.' : 'No hay más encuentros en este curso.' }}
        </p>

        <ul v-else class="divide-y divide-borde overflow-hidden rounded-2xl border border-borde bg-white shadow-sm">
          <li v-for="e in visibles" :key="e.id" class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-azul-noche" :title="titulo(e)">{{ titulo(e) }}</p>
              <p class="truncate text-sm text-texto-secundario">
                <template v-if="e.grupo">Clase {{ e.grupo }} · </template><template v-if="e.curso">{{ formatCurso(e.curso) }} curso · </template>{{ fecha(e.fecha) || 'Sin fecha' }}<template v-if="e.es_propietario === false && e.propietario_nombre"> · Docente: {{ e.propietario_nombre }}</template>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span class="rounded-lg bg-alumnos/5 px-2.5 py-1 font-heading text-base font-bold tracking-[0.12em] text-azul-noche" :title="`Código de acceso ${e.codigo_clase}`">{{ e.codigo_clase }}</span>
              <button type="button" @click="copiar(e.codigo_clase)" :aria-label="`Copiar el código ${e.codigo_clase}`" title="Copiar el código"
                      class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-borde bg-white text-texto-secundario hover:text-azul-noche">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              </button>
              <button type="button" @click="proyectar(e)" :aria-label="`Proyectar el acceso de «${titulo(e)}»`"
                      class="ml-auto inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5 sm:ml-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Proyectar
              </button>
            </div>
          </li>
        </ul>

        <div v-if="!cargando && filtrados.length > limite" class="text-center">
          <button type="button" class="accion-secundaria" @click="limite += PASO">Ver más ({{ filtrados.length - limite }})</button>
        </div>
      </section>
    </div>

    <Transition enter-from-class="opacity-0 translate-y-2" leave-to-class="opacity-0" enter-active-class="transition" leave-active-class="transition">
      <p v-if="aviso" role="status"
         class="fixed bottom-14 right-4 z-[60] max-w-[calc(100vw-2rem)] rounded-2xl bg-azul-noche px-4 py-3 text-sm font-semibold text-white shadow-xl">{{ aviso }}</p>
    </Transition>
  </div>
</template>
