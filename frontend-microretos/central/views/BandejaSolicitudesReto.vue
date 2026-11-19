<!-- Primer contacto → «Solicitudes de retos»: DuaLab envía a un docente un enlace para que cuente qué
     quiere trabajar, y aquí se siguen hasta tener retos y encuentro. El enlace sale por correo y
     además se puede copiar para pasarlo por otro canal. Backend: SolicitudRetoController. -->
<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import VistaPreviaSolicitud from '../components/VistaPreviaSolicitud.vue'
import EliminarSolicitudModal from '../components/EliminarSolicitudModal.vue'
import { useAuthStore, ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB } from '../../src/stores/auth.js'
import { listarSolicitudes, crearSolicitud } from '../../src/services/solicitudRetoService.js'
import { FASES_SOLICITUD, fechaHora, textoSesion, faltaEncuentro } from '../primerContacto.js'

const route = useRoute()
const router = useRouter()

const estado = ref(route.query.estado ?? '')
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
    const { data } = await listarSolicitudes({
      estado: estado.value || undefined, buscar: buscar.value.trim() || undefined, page: pagina.value,
    })
    if (id !== peticion) return
    items.value = data.data
    meta.value = data.meta
    resumen.value = data.resumen
  } catch (e) {
    if (id !== peticion) return
    items.value = []
    error.value = e.response?.data?.error || 'No se han podido cargar las solicitudes.'
  } finally {
    if (id === peticion) cargando.value = false
  }
}

function sincronizarUrl() {
  router.replace({ query: {
    ...(estado.value && { estado: estado.value }), ...(buscar.value.trim() && { buscar: buscar.value.trim() }),
    ...(pagina.value > 1 && { page: pagina.value }),
  } })
}

let espera
watch(buscar, () => { clearTimeout(espera); espera = setTimeout(() => { pagina.value = 1; sincronizarUrl(); cargar() }, 350) })
watch(estado, () => { pagina.value = 1; sincronizarUrl(); cargar() })
function irAPagina(n) { pagina.value = n; sincronizarUrl(); cargar() }
onMounted(cargar)

const opcionesEstado = computed(() => {
  const n = resumen.value?.por_estado ?? {}
  return [
    { value: '', label: 'Todas' },
    { value: 'pendiente', label: 'Esperando al docente', count: n.pendiente },
    { value: 'respondida', label: 'Respondidas', count: n.respondida },
    { value: 'descartada', label: 'Descartadas', count: n.descartada },
  ]
})
const hayFiltros = computed(() => !!(estado.value || buscar.value.trim()))
function limpiar() { estado.value = ''; buscar.value = '' }

const viendoComoDocente = ref(false)
const datosVistaPrevia = ref({})
const auth = useAuthStore()
// Gestionan las solicitudes (empresa, generar, publicar, papelera): superadmin y Admin Central DuaLab
const gestiona = computed(() => [ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB].includes(auth.userRole))

// ── Menú «⋯» de cada tarjeta (como en las tarjetas de propuestas) ──
// Uno abierto a la vez; se cierra al pulsar fuera o con Escape (el foco vuelve a su botón).
const menuAbierto = ref(null)   // id de la solicitud
function cerrarMenu(devolverFoco = false) {
  const id = menuAbierto.value
  menuAbierto.value = null
  if (devolverFoco && id) document.getElementById(`menu-solicitud-${id}`)?.focus()
}
function alPulsarFuera(e) { if (!e.target.closest('[data-menu-solicitud]')) cerrarMenu() }
function alPulsarTecla(e) { if (e.key === 'Escape') cerrarMenu(true) }
watch(menuAbierto, (id, anterior) => {
  if (!!id === !!anterior) return
  const metodo = id ? 'addEventListener' : 'removeEventListener'
  document[metodo]('click', alPulsarFuera, true)
  document[metodo]('keydown', alPulsarTecla)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', alPulsarFuera, true)
  document.removeEventListener('keydown', alPulsarTecla)
})
function verComoDocente(s) {
  cerrarMenu()
  datosVistaPrevia.value = { nombreDocente: s.nombre_docente, centroNombre: s.centro_nombre }
  viendoComoDocente.value = true
}

// ── Borrado seguro (a la papelera) ──
const aEliminar = ref(null)
const avisoLista = ref('')
function pedirEliminar(s) { cerrarMenu(); aEliminar.value = s }
function eliminada() {
  avisoLista.value = `La solicitud de ${aEliminar.value.nombre_docente} está en la papelera. Puedes restaurarla en Red DuaLab → Papelera.`
  aEliminar.value = null
  cargar()
}

// ── Enviar una solicitud nueva ──────────────────────────────────────────────
const creando = ref(false)
const nueva = ref({ nombre_docente: '', email_docente: '', centro_nombre: '', notas: '' })
const errores = ref({})
const enviando = ref(false)
const enlace = ref('')
const copiado = ref(false)

function abrirNueva() {
  nueva.value = { nombre_docente: '', email_docente: '', centro_nombre: '', notas: '' }
  errores.value = {}; enlace.value = ''; copiado.value = false
  creando.value = true
}

async function enviarNueva() {
  if (enviando.value) return
  enviando.value = true
  errores.value = {}
  try {
    const { data } = await crearSolicitud({ ...nueva.value, notas: nueva.value.notas.trim() || null })
    enlace.value = data.enlace
    cargar()
  } catch (e) {
    errores.value = e.response?.status === 422 ? e.response.data.errors : { general: ['No se ha podido enviar. Inténtalo de nuevo.'] }
  } finally {
    enviando.value = false
  }
}

async function copiar() {
  try {
    await navigator.clipboard.writeText(enlace.value)
    copiado.value = true
  } catch {
    copiado.value = false
  }
}
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Primer contacto" titulo="Solicitudes de" destacado="retos" color="text-rol"
                       :volver="{ name: 'inicio' }" volver-label="Espacios"
                       subtitulo="Envía a un docente el formulario para que cuente qué quiere trabajar; con sus respuestas generamos los retos.">
        <button type="button" class="accion-secundaria" @click="datosVistaPrevia = {}; viendoComoDocente = true">Ver cómo lo ve el docente</button>
        <button type="button" class="accion-principal" @click="abrirNueva">Enviar solicitud</button>
      </CabeceraSeccion>

      <FiltrosPanel v-model:busqueda="buscar" placeholder="Buscar por docente, email o centro…"
                    :resultados="meta?.total ?? null" :unidad="['solicitud', 'solicitudes']"
                    :hay-activos="hayFiltros" @limpiar="limpiar">
        <FiltroSegmentado v-model="estado" label="Estado" :opciones="opcionesEstado" />
      </FiltrosPanel>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-if="avisoLista" class="rounded-2xl border border-borde bg-white px-4 py-3 text-sm font-semibold text-texto-secundario" role="status">{{ avisoLista }}</p>
      <p v-if="cargando && !items.length" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!error && !items.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        {{ hayFiltros ? 'No hay solicitudes con estos filtros.' : 'Aún no has enviado ninguna solicitud. Pulsa «Enviar solicitud» para empezar.' }}
      </p>

      <ul v-else class="space-y-2" :class="cargando && 'opacity-60'" :aria-busy="cargando">
        <li v-for="s in items" :key="s.id" class="relative" :class="{ 'z-10': menuAbierto === s.id }">
          <RouterLink :to="{ name: 'solicitud-reto-ficha', params: { id: s.id }, query: route.query }"
                      class="flex flex-col gap-2 rounded-2xl border bg-white p-4 shadow-sm transition hover:border-rol/40 sm:flex-row sm:items-center sm:gap-4 sm:p-5"
                      :style="gestiona ? 'padding-right: 3.75rem' : null"
                      :class="s.fase === 'respondida' ? 'border-alumnos/40' : 'border-borde'">
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="min-w-0 truncate font-heading text-base font-bold">{{ s.nombre_docente }}</span>
              <span class="mt-0.5 min-w-0 truncate text-sm text-texto-secundario">{{ s.centro_nombre }} · {{ s.email_docente }}</span>
              <span class="mt-2 flex flex-wrap gap-1.5">
                <span v-if="s.sesion?.fecha" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-texto-secundario">Sesión {{ textoSesion(s.sesion) }}</span>
                <span v-if="faltaEncuentro(s)" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">Falta encuentro</span>
                <span v-if="s.num_retos" class="rounded-full bg-centros/10 px-2.5 py-0.5 text-xs font-bold text-centros">{{ s.num_retos }} {{ s.num_retos === 1 ? 'reto' : 'retos' }}</span>
                <span v-if="s.empresa" class="min-w-0 truncate rounded-full bg-empresas/10 px-2.5 py-0.5 text-xs font-bold text-empresas-dark">{{ s.empresa.nombre }}</span>
              </span>
            </span>
            <span class="flex shrink-0 items-center justify-between gap-3 sm:flex-col sm:items-end sm:justify-center">
              <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="FASES_SOLICITUD[s.fase]?.clases">{{ FASES_SOLICITUD[s.fase]?.texto ?? s.fase }}</span>
              <span class="text-xs text-texto-secundario">{{ fechaHora(s.respondido_at || s.created_at) }}</span>
            </span>
          </RouterLink>
          <div v-if="gestiona" data-menu-solicitud class="absolute right-3 top-3 sm:top-1/2 sm:-translate-y-1/2">
            <button :id="`menu-solicitud-${s.id}`" type="button" @click="menuAbierto = menuAbierto === s.id ? null : s.id"
                    :aria-expanded="menuAbierto === s.id" aria-haspopup="menu" :aria-label="`Más acciones de la solicitud de ${s.nombre_docente}`"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde bg-white text-texto-secundario transition-colors hover:bg-gray-50"
                    :class="{ 'bg-gray-50': menuAbierto === s.id }">
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
            </button>
            <div v-if="menuAbierto === s.id" role="menu"
                 class="absolute right-0 top-full z-30 mt-1 w-[min(15rem,calc(100vw-3rem))] rounded-xl border border-borde bg-white py-1 shadow-lg">
              <RouterLink role="menuitem" :to="{ name: 'solicitud-reto-ficha', params: { id: s.id }, query: route.query }"
                          class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Abrir la ficha</RouterLink>
              <button type="button" role="menuitem" @click="verComoDocente(s)"
                      class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Ver cómo lo ve el docente</button>
              <button type="button" role="menuitem" @click="pedirEliminar(s)"
                      class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Mover a la papelera</button>
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

    <VistaPreviaSolicitud :abierto="viendoComoDocente" :datos="datosVistaPrevia" @cerrar="viendoComoDocente = false" />
    <EliminarSolicitudModal :visible="!!aEliminar" :solicitud="aEliminar" @cerrar="aEliminar = null" @eliminada="eliminada" />

    <!-- Modal: enviar solicitud -->
    <Teleport to="body">
      <div v-if="creando" class="fixed inset-0 z-[60] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4"
           @keydown.esc="creando = false" @click.self="creando = false">
        <section role="dialog" aria-modal="true" aria-labelledby="nueva-solicitud-titulo"
                 class="flex max-h-[92dvh] w-full max-w-xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:rounded-[2rem]">
          <header class="flex items-center gap-3 border-b border-borde px-4 py-3 sm:px-6 sm:py-4">
            <h2 id="nueva-solicitud-titulo" class="min-w-0 flex-1 truncate font-heading text-lg font-bold">{{ enlace ? 'Solicitud enviada' : 'Enviar solicitud de retos' }}</h2>
            <button type="button" class="accion-icono" aria-label="Cerrar" @click="creando = false">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </header>

          <div v-if="enlace" class="space-y-3 overflow-y-auto px-4 py-5 sm:px-6">
            <p class="text-sm">Le hemos enviado el correo a <strong class="break-all">{{ nueva.email_docente }}</strong>. Si prefieres pasárselo por otro canal, copia el enlace: es personal y caduca en 21 días.</p>
            <p class="break-all rounded-xl bg-[#F8FAFC] px-3 py-2 font-mono text-xs">{{ enlace }}</p>
            <div class="flex flex-wrap justify-end gap-2">
              <button type="button" class="accion-secundaria" @click="copiar">{{ copiado ? 'Copiado' : 'Copiar enlace' }}</button>
              <button type="button" class="accion-principal" @click="creando = false">Hecho</button>
            </div>
          </div>

          <form v-else class="space-y-3 overflow-y-auto px-4 py-5 sm:px-6" novalidate @submit.prevent="enviarNueva">
            <p class="text-xs text-texto-secundario">El docente recibirá un correo con el enlace al formulario (no necesita cuenta). La empresa la eliges tú después.</p>
            <div v-for="campo in [
              { k: 'nombre_docente', t: 'Nombre del docente', tipo: 'text', max: 150 },
              { k: 'email_docente', t: 'Email', tipo: 'email', max: 255 },
              { k: 'centro_nombre', t: 'Centro educativo', tipo: 'text', max: 255 },
            ]" :key="campo.k">
              <label :for="'ns-' + campo.k" class="field-label">{{ campo.t }}</label>
              <input :id="'ns-' + campo.k" v-model="nueva[campo.k]" :type="campo.tipo" :maxlength="campo.max" class="field-input" :aria-invalid="!!errores[campo.k]" required />
              <p v-if="errores[campo.k]" class="mt-1 text-xs text-red-600">{{ errores[campo.k][0] }}</p>
            </div>
            <div>
              <label for="ns-notas" class="field-label">Notas internas (opcional)</label>
              <textarea id="ns-notas" v-model="nueva.notas" maxlength="2000" rows="2" class="field-input" placeholder="Solo las ve DuaLab"></textarea>
            </div>
            <p v-if="errores.general" class="text-sm font-semibold text-red-600" role="alert">{{ errores.general[0] }}</p>
            <div class="flex justify-end">
              <button type="submit" class="accion-principal" :disabled="enviando || !nueva.nombre_docente.trim() || !nueva.email_docente.trim() || !nueva.centro_nombre.trim()">
                {{ enviando ? 'Enviando…' : 'Enviar' }}
              </button>
            </div>
          </form>
        </section>
      </div>
    </Teleport>
  </div>
</template>
