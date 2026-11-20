<!-- Contactos → Centros: los centros educativos con sus familias y ciclos, cuántas empresas tienen y
     cuántas cuentas de profesorado. Solo lectura, como una vista de Base de datos que no se puede
     modificar (el alta y la edición son del superadmin). «Ver empresas» abre Empresas filtrado por el
     centro. Backend: ConsultaCentralController::centros. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import { getCentrosCentral } from '../../src/services/consultaCentralService.js'

const route = useRoute()
const router = useRouter()

const buscar = ref(route.query.buscar ?? '')
const pagina = ref(Number(route.query.page) || 1)
const items = ref([])
const meta = ref(null)
const cargando = ref(true)
const error = ref('')
const abierto = ref(null)

let peticion = 0
async function cargar() {
  const id = ++peticion
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getCentrosCentral({ buscar: buscar.value.trim() || undefined, page: pagina.value })
    if (id !== peticion) return
    items.value = data.data
    meta.value = data.meta
  } catch (e) {
    if (id !== peticion) return
    items.value = []
    error.value = e.response?.data?.message || 'No se han podido cargar los centros.'
  } finally {
    if (id === peticion) cargando.value = false
  }
}
function sincronizarUrl() {
  router.replace({ query: { ...(buscar.value.trim() && { buscar: buscar.value.trim() }), ...(pagina.value > 1 && { page: pagina.value }) } })
}
let espera
watch(buscar, () => { clearTimeout(espera); espera = setTimeout(() => { pagina.value = 1; sincronizarUrl(); cargar() }, 350) })
function irAPagina(n) { pagina.value = n; sincronizarUrl(); cargar() }
onMounted(cargar)

const hayFiltros = computed(() => !!buscar.value.trim())
const numCiclos = (c) => c.familias.reduce((n, f) => n + f.ciclos.length, 0)
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Contactos" titulo="Centros" destacado="educativos" color="text-rol"
                       :volver="{ name: 'empresas-centros' }" volver-label="Empresas y centros"
                       subtitulo="Los centros de DuaLab con sus ciclos, empresas y profesorado. Solo consulta: los cambios se hacen en Base de datos." />

      <FiltrosPanel v-model:busqueda="buscar" placeholder="Buscar por nombre o municipio…"
                    :resultados="meta?.total ?? null" :unidad="['centro', 'centros']"
                    :hay-activos="hayFiltros" @limpiar="buscar = ''" />

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-if="cargando && !items.length" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!error && !items.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        {{ hayFiltros ? 'No hay centros con esta búsqueda.' : 'Todavía no hay centros.' }}
      </p>

      <ul v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2" :class="cargando && 'opacity-60'" :aria-busy="cargando">
        <li v-for="c in items" :key="c.id" class="flex flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5">
          <div class="flex items-start gap-3">
            <img v-if="c.img" :src="c.img" alt="" class="h-12 w-12 shrink-0 rounded-xl border border-borde object-cover" />
            <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-centros/10 font-heading text-lg font-bold text-centros" aria-hidden="true">{{ c.nombre.charAt(0) }}</span>
            <div class="min-w-0 flex-1">
              <h2 class="break-words font-heading text-base font-bold">{{ c.nombre }}</h2>
              <p class="text-sm text-texto-secundario">{{ c.municipio || 'Sin municipio' }}</p>
            </div>
          </div>
          <!-- Tres cifras cortas: caben en 3 columnas también a 360px (≈92px cada una) -->
          <dl class="mt-4 grid grid-cols-3 gap-1.5 text-center sm:gap-2">
            <div class="rounded-xl bg-[#F8FAFC] px-2 py-2"><dt class="text-[11px] font-semibold text-texto-secundario">Ciclos</dt><dd class="font-heading text-lg font-bold">{{ numCiclos(c) }}</dd></div>
            <div class="rounded-xl bg-[#F8FAFC] px-2 py-2"><dt class="text-[11px] font-semibold text-texto-secundario">Empresas</dt><dd class="font-heading text-lg font-bold">{{ c.empresas }}</dd></div>
            <div class="rounded-xl bg-[#F8FAFC] px-2 py-2"><dt class="text-[11px] font-semibold text-texto-secundario">Profesorado</dt><dd class="font-heading text-lg font-bold">{{ c.docentes }}</dd></div>
          </dl>
          <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <button v-if="c.familias.length" type="button" class="accion-secundaria" :aria-expanded="abierto === c.id"
                    @click="abierto = abierto === c.id ? null : c.id">{{ abierto === c.id ? 'Ocultar ciclos' : 'Ver ciclos' }}</button>
            <span v-else class="text-xs text-texto-secundario">Sin ciclos registrados</span>
            <RouterLink v-if="c.empresas" :to="{ name: 'empresas-central', query: { centro: c.id } }" class="accion-secundaria">Ver empresas</RouterLink>
          </div>
          <div v-if="abierto === c.id" class="mt-3 space-y-3 border-t border-borde pt-3">
            <div v-for="f in c.familias" :key="f.familia">
              <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario">{{ f.familia }}</p>
              <ul class="mt-1 space-y-0.5 text-sm">
                <li v-for="ciclo in f.ciclos" :key="ciclo" class="break-words">{{ ciclo }}</li>
              </ul>
            </div>
          </div>
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
