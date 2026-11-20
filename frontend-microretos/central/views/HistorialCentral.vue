<!-- Red DuaLab → Historial: qué se ha hecho en Central, quién y cuándo (validar o descartar empresas,
     papelera, cuentas, solicitudes, retos generados y publicados, correos enviados desde info@, que se
     abren con «Ver correo»). Las acciones críticas van marcadas.
     Solo lectura. Superadmin y Admin Central DuaLab. Backend: ActividadCentralController. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import EnvioCorreoModal from '../components/EnvioCorreoModal.vue'
import { getHistorial, SECCIONES_HISTORIAL } from '../../src/services/historialCentralService.js'
import { fechaHora } from '../primerContacto.js'

const route = useRoute()
const router = useRouter()

const seccion = ref(route.query.seccion ?? '')
const criticas = ref(route.query.criticas === '1')
const buscar = ref(route.query.buscar ?? '')
const pagina = ref(Number(route.query.page) || 1)

const items = ref([])
const envioAbierto = ref(null) // id del envío de correo con el detalle abierto
const meta = ref(null)
const cargando = ref(true)
const error = ref('')

let peticion = 0
async function cargar() {
  const id = ++peticion
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getHistorial({
      seccion: seccion.value || undefined, criticas: criticas.value ? 1 : undefined,
      buscar: buscar.value.trim() || undefined, page: pagina.value,
    })
    if (id !== peticion) return
    items.value = data.data
    meta.value = data.meta
  } catch (e) {
    if (id !== peticion) return
    items.value = []
    error.value = e.response?.data?.message || 'No se ha podido cargar el historial.'
  } finally {
    if (id === peticion) cargando.value = false
  }
}

function sincronizarUrl() {
  router.replace({ query: {
    ...(seccion.value && { seccion: seccion.value }), ...(criticas.value && { criticas: '1' }),
    ...(buscar.value.trim() && { buscar: buscar.value.trim() }), ...(pagina.value > 1 && { page: pagina.value }),
  } })
}
let espera
watch(buscar, () => { clearTimeout(espera); espera = setTimeout(() => { pagina.value = 1; sincronizarUrl(); cargar() }, 350) })
watch([seccion, criticas], () => { pagina.value = 1; sincronizarUrl(); cargar() })
function irAPagina(n) { pagina.value = n; sincronizarUrl(); cargar() }
onMounted(cargar)

const opcionesSeccion = [{ value: '', label: 'Todo' }, ...SECCIONES_HISTORIAL.map(s => ({ value: s.valor, label: s.texto }))]
const textoSeccion = (v) => SECCIONES_HISTORIAL.find(s => s.valor === v)?.texto ?? v
const hayFiltros = computed(() => !!(seccion.value || criticas.value || buscar.value.trim()))
function limpiar() { seccion.value = ''; criticas.value = false; buscar.value = '' }
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Red DuaLab" titulo="Historial de" destacado="actividad" color="text-rol"
                       :volver="{ name: 'red-inicio' }" volver-label="Red DuaLab"
                       subtitulo="Qué se ha hecho en Central, quién lo hizo y cuándo. Las acciones críticas, como validar empresas, publicar retos o borrar, van marcadas. Los correos enviados se abren con «Ver correo»." />

      <FiltrosPanel v-model:busqueda="buscar" placeholder="Buscar por persona o por lo que se hizo…"
                    :resultados="meta?.total ?? null" :unidad="['acción', 'acciones']"
                    :hay-activos="hayFiltros" @limpiar="limpiar">
        <FiltroSegmentado v-model="seccion" label="Sección" :opciones="opcionesSeccion" />
        <label class="inline-flex min-h-9 items-center gap-2 rounded-full border border-borde bg-white px-4 text-sm font-semibold">
          <input v-model="criticas" type="checkbox" class="h-4 w-4 accent-red-600" />
          Solo críticas
        </label>
      </FiltrosPanel>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-if="cargando && !items.length" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!error && !items.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        {{ hayFiltros ? 'No hay acciones con estos filtros.' : 'Todavía no hay acciones registradas.' }}
      </p>

      <ol v-else class="space-y-2" :class="cargando && 'opacity-60'" :aria-busy="cargando">
        <li v-for="h in items" :key="h.id"
            class="flex flex-col gap-2 rounded-2xl border bg-white p-4 shadow-sm sm:flex-row sm:items-start sm:gap-4"
            :class="h.critica ? 'border-red-200' : 'border-borde'">
          <div class="min-w-0 flex-1">
            <p class="break-words text-sm">{{ h.descripcion }}</p>
            <p class="mt-1 text-xs text-texto-secundario">
              <span class="font-semibold text-azul-noche">{{ h.quien }}</span><template v-if="h.rol"> · {{ h.rol }}</template>
            </p>
          </div>
          <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end">
            <span v-if="h.critica" class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700">Crítica</span>
            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-texto-secundario">{{ textoSeccion(h.seccion) }}</span>
            <time class="text-xs text-texto-secundario" :datetime="h.fecha">{{ fechaHora(h.fecha) }}</time>
            <button v-if="h.envio_id" type="button" class="accion-secundaria !min-h-9 !px-4 !text-xs" @click="envioAbierto = h.envio_id">Ver correo</button>
          </div>
        </li>
      </ol>

      <EnvioCorreoModal :envio-id="envioAbierto" @cerrar="envioAbierto = null" />

      <nav v-if="meta && meta.last_page > 1" class="flex flex-wrap items-center justify-center gap-2" aria-label="Páginas">
        <button type="button" class="accion-secundaria" :disabled="pagina <= 1 || cargando" @click="irAPagina(pagina - 1)">Anterior</button>
        <span class="text-sm text-texto-secundario">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button type="button" class="accion-secundaria" :disabled="pagina >= meta.last_page || cargando" @click="irAPagina(pagina + 1)">Siguiente</button>
      </nav>
    </div>
  </div>
</template>
