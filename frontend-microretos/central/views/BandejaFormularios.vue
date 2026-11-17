<!-- Espacio «Primer contacto» de DuaLab Central: los formularios que llegan desde dualab.es.
     Los datos siguen en la web; los trae el backend de la app por la API firmada de la web
     (PrimerContactoController). Abrir uno lleva a su ficha (y lo marca como leído). -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import FiltroSelect from '../../src/components/FiltroSelect.vue'
import { getFormulariosWeb } from '../../src/services/primerContactoService.js'
import { ESTADOS_FORMULARIO, fechaHora, textoCaptacion } from '../primerContacto.js'

const route = useRoute()
const router = useRouter()

// Los filtros viven en la URL: al volver de una ficha se conserva la vista
const estado = ref(route.query.estado ?? '')
const formulario = ref(route.query.formulario ?? '')
const buscar = ref(route.query.buscar ?? '')
const pagina = ref(Number(route.query.page) || 1)

const items = ref([])
const meta = ref(null)
const resumen = ref(null)
const cargando = ref(true)
const error = ref('')

let peticion = 0
async function cargar() {
  const id = ++peticion
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getFormulariosWeb({
      estado: estado.value || undefined, formulario: formulario.value || undefined,
      buscar: buscar.value.trim() || undefined, page: pagina.value,
    })
    if (id !== peticion) return
    items.value = data.data
    meta.value = data.meta
    resumen.value = data.resumen
  } catch (e) {
    if (id !== peticion) return
    items.value = []
    error.value = e.response?.data?.error || 'No se han podido cargar los formularios.'
  } finally {
    if (id === peticion) cargando.value = false
  }
}

function sincronizarUrl() {
  router.replace({ query: {
    ...(estado.value && { estado: estado.value }), ...(formulario.value && { formulario: formulario.value }),
    ...(buscar.value.trim() && { buscar: buscar.value.trim() }), ...(pagina.value > 1 && { page: pagina.value }),
  } })
}

let espera
watch(buscar, () => { clearTimeout(espera); espera = setTimeout(() => { pagina.value = 1; sincronizarUrl(); cargar() }, 350) })
watch([estado, formulario], () => { pagina.value = 1; sincronizarUrl(); cargar() })
function irAPagina(n) { pagina.value = n; sincronizarUrl(); cargar() }
onMounted(cargar)

const opcionesEstado = computed(() => {
  const n = resumen.value?.por_estado ?? {}
  return [
    { value: '', label: 'Sin archivar', count: (n.nuevo ?? 0) + (n.leido ?? 0) },
    { value: 'nuevo', label: 'Nuevos', count: n.nuevo },
    { value: 'leido', label: 'Leídos', count: n.leido },
    { value: 'archivado', label: 'Archivados', count: n.archivado },
  ]
})
const opcionesFormulario = computed(() =>
  (resumen.value?.por_formulario ?? []).map(f => ({ value: f.clave, label: `${f.nombre} (${f.total})` })))
const hayFiltros = computed(() => !!(estado.value || formulario.value || buscar.value.trim()))
function limpiar() { estado.value = ''; formulario.value = ''; buscar.value = '' }

const nombreDe = (f) => [f.nombre, f.apellidos].filter(Boolean).join(' ') || f.email || 'Sin nombre'
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Primer" destacado="contacto" color="text-administraciones"
                       :volver="{ name: 'inicio' }" volver-label="Espacios"
                       subtitulo="Los formularios que llegan desde dualab.es. Ábrelos para leerlos y ver si ya están en captaciones." />

      <FiltrosPanel v-model:busqueda="buscar" placeholder="Buscar por nombre o email…"
                    :resultados="meta?.total ?? null" :unidad="['formulario', 'formularios']"
                    :hay-activos="hayFiltros" @limpiar="limpiar">
        <FiltroSegmentado v-model="estado" label="Estado" :opciones="opcionesEstado" />
        <FiltroSelect v-model="formulario" label="Formulario" todos="Todos los formularios" :opciones="opcionesFormulario" />
      </FiltrosPanel>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-if="cargando && !items.length" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!error && !items.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        No hay formularios con estos filtros.
      </p>

      <ul v-else class="space-y-2" :class="cargando && 'opacity-60'" :aria-busy="cargando">
        <li v-for="f in items" :key="f.id">
          <RouterLink :to="{ name: 'formulario-web', params: { id: f.id }, query: route.query }"
                      class="flex flex-col gap-2 rounded-2xl border bg-white p-4 shadow-sm transition hover:border-administraciones/40 sm:flex-row sm:items-center sm:gap-4 sm:p-5"
                      :class="f.estado === 'nuevo' ? 'border-alumnos/40' : 'border-borde'">
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="flex min-w-0 items-center gap-2">
                <span v-if="f.estado === 'nuevo'" class="h-2 w-2 shrink-0 rounded-full bg-alumnos" aria-hidden="true" />
                <span class="min-w-0 truncate font-heading text-base" :class="f.estado === 'nuevo' ? 'font-black' : 'font-bold'">{{ nombreDe(f) }}</span>
              </span>
              <span class="mt-0.5 min-w-0 truncate text-sm text-texto-secundario">{{ f.email }}</span>
              <span class="mt-2 flex flex-wrap gap-1.5">
                <span class="rounded-full bg-administraciones/10 px-2.5 py-0.5 text-xs font-bold text-administraciones-dark">{{ f.formulario_nombre }}</span>
                <span v-if="f.accion_nombre" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-texto-secundario">{{ f.accion_nombre }}</span>
                <span v-if="f.captacion" class="rounded-full bg-centros/10 px-2.5 py-0.5 text-xs font-bold text-centros">{{ textoCaptacion(f.captacion) }}</span>
              </span>
            </span>
            <span class="flex shrink-0 items-center justify-between gap-3 sm:flex-col sm:items-end sm:justify-center">
              <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="ESTADOS_FORMULARIO[f.estado]?.clases">{{ ESTADOS_FORMULARIO[f.estado]?.texto ?? f.estado }}</span>
              <span class="text-xs text-texto-secundario">{{ fechaHora(f.created_at) }}</span>
            </span>
          </RouterLink>
        </li>
      </ul>

      <nav v-if="meta && meta.last_page > 1" class="flex flex-wrap items-center justify-center gap-2" aria-label="Páginas">
        <button type="button" class="accion-secundaria" :disabled="pagina <= 1 || cargando" @click="irAPagina(pagina - 1)">Anterior</button>
        <span class="text-sm text-texto-secundario">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button type="button" class="accion-secundaria" :disabled="pagina >= meta.last_page || cargando" @click="irAPagina(pagina + 1)">Siguiente</button>
      </nav>
    </div>
  </div>
</template>
