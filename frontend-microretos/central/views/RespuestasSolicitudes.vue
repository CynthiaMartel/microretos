<!-- Solicitudes de retos → «Correos de solicitudes» (botón de su cabecera): los correos de info@ del profesorado con solicitudes
     de retos, aparte del resto del buzón (el Correo de info@ ya no los muestra). Entran los que llevan la
     referencia en el asunto («Ref. SR-12») y los que llegan de un docente con una solicitud abierta;
     cada uno enlaza a su solicitud. Abrir uno es el Correo de info@ de siempre
     (se lee, se responde y se archiva allí), y al volver se regresa aquí. Backend: SolicitudRetoCorreoController. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import { getRespuestasSolicitudes } from '../../src/services/solicitudRetoService.js'
import { ESTADOS_FORMULARIO, fechaHora } from '../primerContacto.js'

const route = useRoute()
const router = useRouter()

const estado = ref(route.query.estado ?? '')
const pagina = ref(Number(route.query.page) || 1)

const items = ref([])
const meta = ref(null)
const cargando = ref(true)
const error = ref('')

let peticion = 0
async function cargar() {
  const id = ++peticion
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getRespuestasSolicitudes({ estado: estado.value || undefined, page: pagina.value })
    if (id !== peticion) return
    items.value = data.data
    meta.value = data.meta
  } catch (e) {
    if (id !== peticion) return
    items.value = []
    error.value = e.response?.data?.error || 'No se han podido cargar las respuestas.'
  } finally {
    if (id === peticion) cargando.value = false
  }
}

function sincronizarUrl() {
  router.replace({ query: { ...(estado.value && { estado: estado.value }), ...(pagina.value > 1 && { page: pagina.value }) } })
}
watch(estado, () => { pagina.value = 1; sincronizarUrl(); cargar() })
function irAPagina(n) { pagina.value = n; sincronizarUrl(); cargar() }
onMounted(cargar)

// Mismos estados que el Correo de info@ (sin filtro: todo lo que no está archivado)
const opcionesEstado = [
  { value: '', label: 'Sin archivar' },
  { value: 'nuevo', label: 'No leídos' },
  { value: 'leido', label: 'Leídos' },
  { value: 'archivado', label: 'Archivados' },
]
const hayFiltros = computed(() => !!estado.value)
const remitente = (c) => c.de_nombre || c.de_email || 'Sin remitente'
const estadoCorreo = (e) => ({ ...ESTADOS_FORMULARIO[e], texto: e === 'nuevo' ? 'No leído' : ESTADOS_FORMULARIO[e]?.texto })
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Solicitudes de retos" titulo="Correos de" destacado="solicitudes" color="text-rol"
                       :volver="{ name: 'solicitudes-reto' }" volver-label="Solicitudes de retos"
                       subtitulo="Lo que escribe a info@ el profesorado con solicitudes de retos: respuestas a nuestros correos y correos nuevos. Aquí y no en el Correo de info@." />

      <FiltrosPanel :resultados="meta?.total ?? null" :unidad="['respuesta', 'respuestas']"
                    :hay-activos="hayFiltros" @limpiar="estado = ''">
        <FiltroSegmentado v-model="estado" label="Estado" :opciones="opcionesEstado" />
      </FiltrosPanel>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-if="cargando && !items.length" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!error && !items.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        {{ hayFiltros ? 'No hay respuestas con este filtro.' : 'Aún no ha respondido ningún docente. Cuando respondan a los correos de las solicitudes, aparecerán aquí.' }}
      </p>

      <ul v-else class="space-y-2" :class="cargando && 'opacity-60'" :aria-busy="cargando">
        <li v-for="c in items" :key="c.id"
            class="flex flex-col gap-2 rounded-2xl border bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:gap-4 sm:p-5"
            :class="c.estado === 'nuevo' ? 'border-alumnos/40' : 'border-borde'">
          <RouterLink :to="{ name: 'correo-ficha', params: { id: c.id }, query: { desde: 'respuestas', ...route.query } }"
                      class="flex min-w-0 flex-1 flex-col rounded-xl transition hover:opacity-80">
            <span class="flex min-w-0 items-center gap-2">
              <span v-if="c.estado === 'nuevo'" class="h-2 w-2 shrink-0 rounded-full bg-alumnos" aria-hidden="true" />
              <span class="min-w-0 truncate font-heading text-base" :class="c.estado === 'nuevo' ? 'font-black' : 'font-bold'">{{ remitente(c) }}</span>
            </span>
            <span class="mt-0.5 min-w-0 truncate text-sm font-semibold">{{ c.asunto || '(Sin asunto)' }}</span>
            <span v-if="c.extracto" class="mt-0.5 line-clamp-2 break-words text-sm text-texto-secundario">{{ c.extracto }}</span>
          </RouterLink>
          <span class="flex shrink-0 flex-wrap items-center justify-between gap-2 sm:flex-col sm:items-end sm:justify-center">
            <RouterLink v-if="c.solicitud" :to="{ name: 'solicitud-reto-ficha', params: { id: c.solicitud.id } }"
                        class="inline-flex min-h-9 max-w-full items-center rounded-full bg-centros/10 px-3 text-xs font-bold text-centros hover:bg-centros/20 sm:max-w-[16rem]"
                        :title="`Solicitud de ${c.solicitud.nombre_docente} · ${c.solicitud.centro_nombre}`">
              <span class="min-w-0 truncate">Solicitud de {{ c.solicitud.nombre_docente }}</span>
            </RouterLink>
            <span v-else class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-texto-secundario">Solicitud no encontrada</span>
            <span class="flex items-center gap-2">
              <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="estadoCorreo(c.estado).clases">{{ estadoCorreo(c.estado).texto ?? c.estado }}</span>
              <span class="text-xs text-texto-secundario">{{ fechaHora(c.fecha) }}</span>
            </span>
          </span>
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
