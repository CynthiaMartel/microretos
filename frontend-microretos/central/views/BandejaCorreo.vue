<!-- Correo de info@ en DuaLab Central («Primer contacto»): recibidos y enviados. Los correos siguen en la
     web (copia del buzón que sincroniza su programador cada 1–2 min, 4 meses como mucho) y salen desde
     info@ por la web; Central los trae por la API firmada (PrimerContactoCorreoController). -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import RedactarCorreo from '../components/RedactarCorreo.vue'
import { getCorreos, reintentarEnvio, descartarEnvio } from '../../src/services/primerContactoService.js'
import { fechaHora } from '../primerContacto.js'

const route = useRoute()
const router = useRouter()

const carpeta = ref(route.query.carpeta === 'enviados' ? 'enviados' : 'recibidos')
const estado = ref(route.query.estado ?? '')
const buscar = ref(route.query.buscar ?? '')
const pagina = ref(Number(route.query.page) || 1)

const items = ref([])
const meta = ref(null)
const resumen = ref(null)
const salida = ref([])
const remitentes = ref({})
const cargando = ref(true)
const error = ref('')
const aviso = ref('')
const redactando = ref(false)

let peticion = 0
async function cargar() {
  const id = ++peticion
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getCorreos({
      carpeta: carpeta.value, estado: carpeta.value === 'recibidos' ? (estado.value || undefined) : undefined,
      buscar: buscar.value.trim() || undefined, page: pagina.value,
    })
    if (id !== peticion) return
    items.value = data.data
    meta.value = data.meta
    resumen.value = data.resumen
    salida.value = data.salida
    remitentes.value = data.remitentes ?? {}
  } catch (e) {
    if (id !== peticion) return
    items.value = []
    error.value = e.response?.data?.error || 'No se ha podido cargar el correo.'
  } finally {
    if (id === peticion) cargando.value = false
  }
}

function sincronizarUrl() {
  router.replace({ query: {
    ...(carpeta.value === 'enviados' && { carpeta: 'enviados' }), ...(estado.value && { estado: estado.value }),
    ...(buscar.value.trim() && { buscar: buscar.value.trim() }), ...(pagina.value > 1 && { page: pagina.value }),
  } })
}
let espera
watch(buscar, () => { clearTimeout(espera); espera = setTimeout(() => { pagina.value = 1; sincronizarUrl(); cargar() }, 350) })
watch([carpeta, estado], () => { pagina.value = 1; sincronizarUrl(); cargar() })
function irAPagina(n) { pagina.value = n; sincronizarUrl(); cargar() }
onMounted(cargar)

const opcionesCarpeta = [{ value: 'recibidos', label: 'Recibidos' }, { value: 'enviados', label: 'Enviados' }]
const opcionesEstado = computed(() => {
  const n = resumen.value?.por_estado ?? {}
  return [
    { value: '', label: 'Sin archivar', count: (n.nuevo ?? 0) + (n.leido ?? 0) },
    { value: 'nuevo', label: 'No leídos', count: n.nuevo },
    { value: 'archivado', label: 'Archivados', count: n.archivado },
  ]
})
const hayFiltros = computed(() => !!(estado.value || buscar.value.trim()))
function limpiar() { estado.value = ''; buscar.value = '' }

const remitente = (c) => (carpeta.value === 'enviados'
  ? `Para: ${(c.para ?? []).map(d => d.nombre || d.email).join(', ') || '—'}`
  : (c.de_nombre || c.de_email || 'Sin remitente'))
const conocido = (c) => remitentes.value[(c.de_email || '').toLowerCase()]

async function accionEnvio(e, accion) {
  aviso.value = ''
  try {
    if (accion === 'reintentar') await reintentarEnvio(e.id)
    else await descartarEnvio(e.id)
    aviso.value = accion === 'reintentar' ? 'Se vuelve a intentar el envío.' : 'Envío descartado.'
    cargar()
  } catch (err) {
    aviso.value = err.response?.data?.message || err.response?.data?.error || 'No se ha podido completar la acción.'
  }
}
function enviado() {
  redactando.value = false
  aviso.value = 'Enviando… Aparecerá en Enviados en un par de minutos.'
  cargar()
}
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Primer contacto" titulo="Correo" :destacado="resumen?.buzon?.email || 'info@'" color="text-rol"
                       :volver="{ name: 'red-inicio' }" volver-label="Red DuaLab"
                       subtitulo="El buzón de info@dualab.es: leer, responder y reenviar sin abrir el webmail.">
        <button type="button" class="accion-principal" @click="redactando = true">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
          Redactar
        </button>
      </CabeceraSeccion>

      <!-- Avisos del buzón: sin configurar, error de sincronización, retención -->
      <p v-if="resumen && !resumen.buzon.configurado" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
        El buzón no está configurado en la web: no llegan correos nuevos.
      </p>
      <p v-else-if="resumen?.sincronizado?.error" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
        La última sincronización con el buzón falló. Lo nuevo puede tardar en aparecer.
      </p>
      <p v-if="resumen?.retencion?.meses" class="text-xs text-texto-secundario">
        Aquí se guardan los últimos {{ resumen.retencion.meses }} meses<template v-if="resumen.sincronizado?.en"> · última sincronización: {{ fechaHora(resumen.sincronizado.en) }}</template>.
        <template v-if="resumen.retencion.webmail">Lo anterior está en el <a :href="resumen.retencion.webmail" target="_blank" rel="noopener" class="font-semibold text-rol-fuerte underline-offset-2 hover:underline">webmail<span class="sr-only"> (se abre en otra pestaña)</span></a>.</template>
      </p>

      <!-- Bandeja de salida: lo que aún no ha salido o ha fallado -->
      <section v-if="salida.length" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 sm:p-5" aria-labelledby="titulo-salida">
        <h2 id="titulo-salida" class="mb-2 font-heading text-base font-bold">Bandeja de salida</h2>
        <ul class="space-y-2">
          <li v-for="e in salida" :key="e.id" class="flex flex-col gap-2 rounded-xl bg-white px-3 py-2 sm:flex-row sm:items-center">
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-semibold">{{ e.asunto || 'Sin asunto' }}</span>
              <span class="block truncate text-xs text-texto-secundario">Para: {{ e.para.join(', ') }}</span>
              <span v-if="e.estado === 'error'" class="block break-words text-xs font-semibold text-red-700">No se ha enviado<template v-if="e.error">: {{ e.error }}</template></span>
              <span v-else class="block text-xs text-texto-secundario">Enviando…</span>
            </span>
            <span v-if="e.estado === 'error'" class="flex shrink-0 flex-wrap gap-2">
              <button type="button" class="accion-secundaria" @click="accionEnvio(e, 'reintentar')">Reintentar</button>
              <button type="button" class="accion-secundaria" @click="accionEnvio(e, 'descartar')">Descartar</button>
            </span>
          </li>
        </ul>
      </section>

      <p v-if="aviso" class="rounded-2xl border border-borde bg-white px-4 py-3 text-sm font-semibold" role="status">{{ aviso }}</p>

      <FiltrosPanel v-model:busqueda="buscar" placeholder="Buscar por asunto o remitente…"
                    :resultados="meta?.total ?? null" :unidad="['correo', 'correos']" :hay-activos="hayFiltros" @limpiar="limpiar">
        <FiltroSegmentado v-model="carpeta" label="Carpeta" :opciones="opcionesCarpeta" />
        <FiltroSegmentado v-if="carpeta === 'recibidos'" v-model="estado" label="Estado" :opciones="opcionesEstado" />
      </FiltrosPanel>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-if="cargando && !items.length" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!error && !items.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        No hay correos con estos filtros.
      </p>

      <ul v-else class="space-y-2" :class="cargando && 'opacity-60'" :aria-busy="cargando">
        <li v-for="c in items" :key="c.id">
          <RouterLink :to="{ name: 'correo-ficha', params: { id: c.id }, query: route.query }"
                      class="flex flex-col gap-1.5 rounded-2xl border bg-white p-4 shadow-sm transition hover:border-rol/40 sm:flex-row sm:items-center sm:gap-4"
                      :class="c.estado === 'nuevo' ? 'border-alumnos/40' : 'border-borde'">
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="flex min-w-0 items-center gap-2">
                <span v-if="c.estado === 'nuevo'" class="h-2 w-2 shrink-0 rounded-full bg-alumnos" aria-hidden="true" />
                <span class="min-w-0 truncate text-sm" :class="c.estado === 'nuevo' ? 'font-black' : 'font-semibold'">{{ remitente(c) }}</span>
                <span v-if="conocido(c)" class="shrink-0 rounded-full bg-rol/10 px-2 py-0.5 text-[11px] font-bold text-rol-fuerte">{{ conocido(c).tipo_nombre }}</span>
              </span>
              <span class="mt-0.5 min-w-0 truncate font-heading text-base" :class="c.estado === 'nuevo' ? 'font-bold' : 'font-semibold'">{{ c.asunto || '(Sin asunto)' }}</span>
              <span v-if="c.extracto" class="min-w-0 truncate text-sm text-texto-secundario">{{ c.extracto }}</span>
            </span>
            <span class="flex shrink-0 items-center gap-2 text-xs text-texto-secundario sm:flex-col sm:items-end">
              <svg v-if="c.tiene_adjuntos" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-label="Con adjuntos" role="img"><path stroke-linecap="round" stroke-linejoin="round" d="M15.17 7l-6.59 6.59a2 2 0 102.83 2.83l6.41-6.59a4 4 0 00-5.66-5.66l-6.4 6.58a6 6 0 108.48 8.49L20.5 13" /></svg>
              {{ fechaHora(c.fecha) }}
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

    <RedactarCorreo :abierto="redactando" modo="nuevo" :remitente="resumen?.buzon?.email || 'info@dualab.es'"
                    @cerrar="redactando = false" @enviado="enviado" />
  </div>
</template>
