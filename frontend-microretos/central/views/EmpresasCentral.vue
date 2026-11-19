<!-- Contactos → Empresas: todas las empresas con los centros a los que están asociadas, como en la App
     DuaLab, para saber de quién es cada una y con quién hablar. Solo lectura: editar o borrar sigue en
     Base de datos (superadmin). Backend: ConsultaCentralController::empresas. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import FiltroSelect from '../../src/components/FiltroSelect.vue'
import { getEmpresasCentral } from '../../src/services/consultaCentralService.js'
import { getCentros, getFamilias } from '../../src/services/datosFPService.js'

const route = useRoute()
const router = useRouter()

const buscar = ref(route.query.buscar ?? '')
const centro = ref(Number(route.query.centro) || '')
const familia = ref(Number(route.query.familia) || '')
const tipo = ref(route.query.tipo ?? '')
const pagina = ref(Number(route.query.page) || 1)

const items = ref([])
const meta = ref(null)
const cargando = ref(true)
const error = ref('')
const abierta = ref(null)

const centros = ref([])
const familias = ref([])
onMounted(async () => {
  const [c, f] = await Promise.allSettled([getCentros(), getFamilias()])
  if (c.status === 'fulfilled') centros.value = c.value.data.map(x => ({ value: x.id, label: x.nombre }))
  if (f.status === 'fulfilled') familias.value = f.value.data.map(x => ({ value: x.id, label: x.nombre }))
})

let peticion = 0
async function cargar() {
  const id = ++peticion
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getEmpresasCentral({
      buscar: buscar.value.trim() || undefined, centro_id: centro.value || undefined,
      familia_id: familia.value || undefined, tipo: tipo.value || undefined, page: pagina.value,
    })
    if (id !== peticion) return
    items.value = data.data
    meta.value = data.meta
  } catch (e) {
    if (id !== peticion) return
    items.value = []
    error.value = e.response?.data?.message || 'No se han podido cargar las empresas.'
  } finally {
    if (id === peticion) cargando.value = false
  }
}

function sincronizarUrl() {
  router.replace({ query: {
    ...(buscar.value.trim() && { buscar: buscar.value.trim() }), ...(centro.value && { centro: centro.value }),
    ...(familia.value && { familia: familia.value }), ...(tipo.value && { tipo: tipo.value }),
    ...(pagina.value > 1 && { page: pagina.value }),
  } })
}
let espera
watch(buscar, () => { clearTimeout(espera); espera = setTimeout(() => { pagina.value = 1; sincronizarUrl(); cargar() }, 350) })
watch([centro, familia, tipo], () => { pagina.value = 1; sincronizarUrl(); cargar() })
function irAPagina(n) { pagina.value = n; sincronizarUrl(); cargar() }
onMounted(cargar)

const opcionesTipo = [{ value: '', label: 'Todas' }, { value: 'real', label: 'Reales' }, { value: 'ficticia', label: 'Ficticias' }]
const hayFiltros = computed(() => !!(buscar.value.trim() || centro.value || familia.value || tipo.value))
function limpiar() { buscar.value = ''; centro.value = ''; familia.value = ''; tipo.value = '' }
const lugar = (e) => [e.municipio, e.provincia].filter(Boolean).join(', ')
const contacto = (e) => [
  { t: 'Persona de contacto', v: [e.persona_contacto, e.posicion_contacto].filter(Boolean).join(' · ') },
  { t: 'Email de contacto', v: e.email_contacto, href: e.email_contacto && `mailto:${e.email_contacto}` },
  { t: 'Email general', v: e.email_general, href: e.email_general && `mailto:${e.email_general}` },
  { t: 'Teléfono', v: e.telefono, href: e.telefono && `tel:${e.telefono.replace(/\s/g, '')}` },
  { t: 'Web', v: e.web, href: e.web && (/^https?:\/\//.test(e.web) ? e.web : `https://${e.web}`), externo: true },
  { t: 'Estado del contacto', v: e.estado_contacto },
  { t: 'Actividad', v: e.actividad },
  { t: 'Tamaño', v: e.tamano },
].filter(x => x.v)
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Contactos" titulo="Empresas" color="text-rol"
                       :volver="{ name: 'empresas-centros' }" volver-label="Empresas y centros"
                       subtitulo="Las empresas de DuaLab y los centros a los que están asociadas. Solo consulta: los cambios se hacen en Base de datos." />

      <FiltrosPanel v-model:busqueda="buscar" placeholder="Buscar por nombre, razón social, sector o municipio…"
                    :resultados="meta?.total ?? null" :unidad="['empresa', 'empresas']"
                    :hay-activos="hayFiltros" @limpiar="limpiar">
        <FiltroSegmentado v-model="tipo" label="Tipo" :opciones="opcionesTipo" />
        <FiltroSelect v-model="centro" label="Centro" :opciones="centros" todos="Todos los centros" />
        <FiltroSelect v-model="familia" label="Familia" :opciones="familias" todos="Todas las familias" />
      </FiltrosPanel>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-if="cargando && !items.length" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!error && !items.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        {{ hayFiltros ? 'No hay empresas con estos filtros.' : 'Todavía no hay empresas.' }}
      </p>

      <ul v-else class="space-y-2" :class="cargando && 'opacity-60'" :aria-busy="cargando">
        <li v-for="e in items" :key="e.id" class="rounded-2xl border border-borde bg-white shadow-sm">
          <button type="button" class="flex w-full flex-col gap-2 p-4 text-left sm:flex-row sm:items-center sm:gap-4 sm:p-5"
                  :aria-expanded="abierta === e.id" @click="abierta = abierta === e.id ? null : e.id">
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="flex flex-wrap items-center gap-2">
                <span class="min-w-0 break-words font-heading text-base font-bold">{{ e.nombre }}</span>
                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                      :class="e.es_simulada ? 'bg-gray-100 text-texto-secundario' : 'bg-empresas/10 text-empresas-dark'">{{ e.es_simulada ? 'Ficticia' : 'Real' }}</span>
                <span v-if="e.es_catalogo" class="rounded-full bg-rol/10 px-2.5 py-0.5 text-[11px] font-bold text-rol-fuerte">Catálogo DuaLab</span>
              </span>
              <span class="mt-0.5 min-w-0 break-words text-sm text-texto-secundario">{{ [e.sector, lugar(e)].filter(Boolean).join(' · ') || 'Sin sector' }}</span>
              <span class="mt-2 flex flex-wrap gap-1.5">
                <span v-for="c in e.centros" :key="c" class="max-w-full truncate rounded-full bg-centros/10 px-2.5 py-0.5 text-xs font-bold text-centros">{{ c }}</span>
                <span v-if="!e.centros.length" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-texto-secundario">Sin centro</span>
                <span v-for="f in e.familias" :key="f" class="max-w-full truncate rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-texto-secundario">{{ f }}</span>
              </span>
            </span>
            <span class="flex shrink-0 items-center gap-3 sm:flex-col sm:items-end">
              <span class="text-xs font-semibold text-texto-secundario">{{ e.retos }} {{ e.retos === 1 ? 'reto' : 'retos' }}</span>
              <svg class="h-4 w-4 text-texto-secundario transition-transform" :class="abierta === e.id && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </span>
          </button>
          <dl v-if="abierta === e.id" class="grid grid-cols-1 gap-x-6 gap-y-3 border-t border-borde px-4 py-4 text-sm sm:grid-cols-2 sm:px-5 lg:grid-cols-3">
            <div v-if="e.razon_social" class="min-w-0"><dt class="text-xs font-semibold text-texto-secundario">Razón social</dt><dd class="break-words">{{ e.razon_social }}</dd></div>
            <div v-for="c in contacto(e)" :key="c.t" class="min-w-0">
              <dt class="text-xs font-semibold text-texto-secundario">{{ c.t }}</dt>
              <dd class="break-words">
                <a v-if="c.href" :href="c.href" class="font-semibold text-rol-fuerte underline-offset-2 hover:underline"
                   v-bind="c.externo ? { target: '_blank', rel: 'noopener noreferrer' } : {}">{{ c.v }}</a>
                <template v-else>{{ c.v }}</template>
              </dd>
            </div>
            <p v-if="!e.razon_social && !contacto(e).length" class="text-texto-secundario">Sin datos de contacto.</p>
          </dl>
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
