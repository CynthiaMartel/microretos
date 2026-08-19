<script setup>
import { ref, computed, onMounted, onActivated, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api.js'
import AccesoEmpresasModal from '../components/AccesoEmpresasModal.vue'

const router = useRouter()

// ─── Estado de acceso ─────────────────────────────────────────────────────────
const desbloqueado = ref(sessionStorage.getItem('empresas_module_unlocked') === 'true')

async function onDesbloqueado() {
  desbloqueado.value = true
  await cargarDatos()
}

function onCerrarAcceso() {
  router.go(-1)
}

// ─── Datos ────────────────────────────────────────────────────────────────────
const empresas   = ref([])
const familias   = ref([])
const proyectos  = ref([])
const cargando   = ref(false)
const isLoaded   = ref(false)

// ─── Filtros ──────────────────────────────────────────────────────────────────
const busqueda        = ref('')
const filtroFamilia   = ref('')
const filtroEstado    = ref('')
const filtroProvincia = ref('')
const filtroSector    = ref('')
const filtroCentro    = ref('')

// ─── UI ───────────────────────────────────────────────────────────────────────
const empresaExpandida = ref(null)
const panelActivo      = ref('')

// ─── Modal bienvenida ─────────────────────────────────────────────────────────
// Auto-disparo desactivado — reactivar poniendo mostrarBienvenida.value = true si se necesita de nuevo.
const mostrarBienvenida = ref(false)
const mostrarEjemplo    = ref(false)
const modoEjemplo       = ref('')   // 'contactar' | 'validar'

const empresaEjemplo = {
  id: -1,
  nombre_comercial: 'Herrería Canaria Artesanal SL',
  razon_social: 'Herrería Canaria Artesanal Sociedad Limitada',
  cif: 'B38901234',
  sector: 'Fabricación y metal',
  municipio: 'Santa Cruz de Tenerife',
  provincia: 'Santa Cruz de Tenerife',
  email_general: 'info@herreriacanaria.es',
  email_contacto: 'formacion@herreriacanaria.es',
  persona_contacto: 'Carlos Medina',
  posicion_contacto: 'Responsable de formación',
  estado_contacto: 'Pendiente',
  telefono: '922 456 789',
  actividad: 'Fabricación de estructuras metálicas y forja artesanal',
  familias: [{ id: 2, nombre: 'Fabricación Mecánica' }],
}

function abrirBienvenida(modo) {
  mostrarBienvenida.value = false
  if (modo === 'contactar' || modo === 'validar') {
    modoEjemplo.value = modo
    mostrarEjemplo.value = true
  }
}

// ─── Formulario email ─────────────────────────────────────────────────────────
const emailForm = ref({ remitente: 'info@viaoptima.es', asunto: '', mensaje: '' })
const enviandoEmail = ref(false)
const emailOk       = ref(false)
const emailError    = ref('')

// ─── Formulario validación ────────────────────────────────────────────────────
const validForm = ref({ remitente: 'info@viaoptima.es', proyecto_uuid: '', mensaje: '' })
const enviandoValidacion = ref(false)
const validacionOk       = ref(false)
const validacionError    = ref('')

// ─── Cargar datos ─────────────────────────────────────────────────────────────
onMounted(async () => {
  setTimeout(() => { isLoaded.value = true }, 60)
  if (!desbloqueado.value) return
  await cargarDatos()
})

// `onActivated` cubre KeepAlive; el watcher cubre re-navegación a la misma ruta (SidePanel añade ?_t=)
onActivated(() => {
  if (!desbloqueado.value) return
  if (route.query.empresa_id) {
    aplicarQueryParams()
  }
})

const route = useRoute()
watch(() => route.fullPath, () => {
  if (!desbloqueado.value) return
  if (route.query.empresa_id) {
    aplicarQueryParams()
  }
})

// Auto-expande empresa y abre panel de validación cuando se llega desde el wizard
watch(empresas, (list) => {
  if (!list.length || !route.query.empresa_id) return
  aplicarQueryParams()
})

function aplicarQueryParams() {
  const empresa = empresas.value.find(e => String(e.id) === String(route.query.empresa_id))
  if (!empresa) return
  mostrarBienvenida.value = false
  empresaExpandida.value = empresa
  emailOk.value = false
  emailError.value = ''
  validacionOk.value = false
  validacionError.value = ''
  emailForm.value = { remitente: 'info@viaoptima.es', asunto: '', mensaje: '' }
  validForm.value = {
    remitente: 'info@viaoptima.es',
    proyecto_uuid: route.query.proyecto_uuid || '',
    mensaje: '',
  }
  if (route.query.panel === 'validacion') panelActivo.value = 'validacion'
}

async function cargarDatos() {
  cargando.value = true
  try {
    const [empRes, famRes, proRes] = await Promise.all([
      api.get('/empresas'),
      api.get('/familias'),
      api.get('/startup/proyectos'),
    ])
    empresas.value  = empRes.data
    familias.value  = famRes.data
    proyectos.value = proRes.data.filter(p => p.estado === 'propuesta' || p.estado === 'validado')
    // Inicializar todos los centros cerrados por defecto
    centrosCerrados.value = new Set(empRes.data.map(e => e.centro_educativo || SIN_CENTRO))
  } finally {
    cargando.value = false
  }
}

// ─── Computed: empresas filtradas ─────────────────────────────────────────────
const empresasFiltradas = computed(() => {
  return empresas.value
    .filter(e => {
      const q = busqueda.value.toLowerCase()
      const matchNombre  = !q ||
        (e.nombre_comercial || '').toLowerCase().includes(q) ||
        (e.razon_social || '').toLowerCase().includes(q)
      const matchFamilia = !filtroFamilia.value ||
        (e.familias || []).some(f => String(f.id) === filtroFamilia.value)
      const matchEstado  = !filtroEstado.value   || e.estado_contacto === filtroEstado.value
      const matchProv    = !filtroProvincia.value || e.provincia === filtroProvincia.value
      const matchSector  = !filtroSector.value   || e.sector === filtroSector.value
      const matchCentro  = !filtroCentro.value   || e.centro_educativo === filtroCentro.value
      return matchNombre && matchFamilia && matchEstado && matchProv && matchSector && matchCentro
    })
    .sort((a, b) => (a.nombre_comercial || '').localeCompare(b.nombre_comercial || '', 'es'))
})

// ─── Computed: valores únicos para filtros ────────────────────────────────────
const provincias = computed(() => {
  const set = new Set(empresas.value.map(e => e.provincia).filter(Boolean))
  return [...set].sort((a, b) => a.localeCompare(b, 'es'))
})
const sectores = computed(() => {
  const set = new Set(empresas.value.map(e => e.sector).filter(Boolean))
  return [...set].sort((a, b) => a.localeCompare(b, 'es'))
})
const centros = computed(() => {
  const set = new Set(empresas.value.map(e => e.centro_educativo).filter(Boolean))
  return [...set].sort((a, b) => a.localeCompare(b, 'es'))
})

const estadosContacto = ['Contactado', 'Pendiente', 'Sin contactar', 'Reunión fijada', 'No interesado', 'Activo']

// ─── Interacción empresas ─────────────────────────────────────────────────────
function toggleEmpresa(empresa) {
  if (empresaExpandida.value?.id === empresa.id) {
    empresaExpandida.value = null
    panelActivo.value = ''
  } else {
    empresaExpandida.value = empresa
    panelActivo.value = ''
    emailOk.value = false
    emailError.value = ''
    validacionOk.value = false
    validacionError.value = ''
    emailForm.value = { remitente: 'info@viaoptima.es', asunto: '', mensaje: '' }
    validForm.value = { remitente: 'info@viaoptima.es', proyecto_uuid: '', mensaje: '' }
  }
}

function abrirPanel(panel) {
  panelActivo.value = panelActivo.value === panel ? '' : panel
}

// ─── Enviar email ─────────────────────────────────────────────────────────────
async function enviarEmail() {
  enviandoEmail.value = true
  emailError.value = ''
  emailOk.value = false
  try {
    await api.post(`/empresas/${empresaExpandida.value.id}/contactar`, emailForm.value)
    emailOk.value = true
    emailForm.value.asunto = ''
    emailForm.value.mensaje = ''
  } catch (e) {
    emailError.value = e.response?.data?.message || 'Error al enviar el correo.'
  } finally {
    enviandoEmail.value = false
  }
}

// ─── Enviar enlace de validación ──────────────────────────────────────────────
async function enviarValidacionEmail() {
  enviandoValidacion.value = true
  validacionError.value = ''
  validacionOk.value = false
  try {
    await api.post(`/empresas/${empresaExpandida.value.id}/enviar-validacion`, validForm.value)
    validacionOk.value = true
    validForm.value.proyecto_uuid = ''
    validForm.value.mensaje = ''
  } catch (e) {
    validacionError.value = e.response?.data?.message || 'Error al enviar el enlace.'
  } finally {
    enviandoValidacion.value = false
  }
}

// ─── helpers ──────────────────────────────────────────────────────────────────
const estadoColor = {
  'Activo':         'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Contactado':     'bg-blue-50 text-blue-700 border-blue-200',
  'Reunión fijada': 'bg-amber-50 text-amber-700 border-amber-200',
  'Pendiente':      'bg-orange-50 text-orange-700 border-orange-200',
  'Sin contactar':  'bg-gray-100 text-gray-500 border-gray-200',
  'No interesado':  'bg-red-50 text-red-700 border-red-200',
}
function badgeEstado(e) {
  return estadoColor[e] || 'bg-gray-100 text-gray-500 border-gray-200'
}

function limpiarFiltros() {
  busqueda.value        = ''
  filtroFamilia.value   = ''
  filtroEstado.value    = ''
  filtroProvincia.value = ''
  filtroSector.value    = ''
  filtroCentro.value    = ''
}

// ─── Acordeón de centros ──────────────────────────────────────────────────────
const centrosCerrados = ref(new Set())

function toggleCentro(nombre) {
  const s = new Set(centrosCerrados.value)
  s.has(nombre) ? s.delete(nombre) : s.add(nombre)
  centrosCerrados.value = s
}

const SIN_CENTRO = '— Sin centro asignado —'

const empresasPorCentro = computed(() => {
  const mapa = {}
  for (const e of empresasFiltradas.value) {
    const key = e.centro_educativo || SIN_CENTRO
    if (!mapa[key]) mapa[key] = []
    mapa[key].push(e)
  }
  return mapa
})

const centrosOrdenados = computed(() => {
  const keys = Object.keys(empresasPorCentro.value)
  return keys
    .filter(k => k !== SIN_CENTRO)
    .sort((a, b) => a.localeCompare(b, 'es'))
    .concat(keys.includes(SIN_CENTRO) ? [SIN_CENTRO] : [])
})

// ─── Estadísticas de contacto ─────────────────────────────────────────────────
const estadisticasContacto = computed(() => {
  const counts = {}
  for (const e of empresas.value) {
    const est = e.estado_contacto || 'Sin contactar'
    counts[est] = (counts[est] || 0) + 1
  }
  return counts
})

const totalActivas     = computed(() => estadisticasContacto.value['Activo'] || 0)
const totalContactadas = computed(() => estadisticasContacto.value['Contactado'] || 0)
const totalReunion     = computed(() => estadisticasContacto.value['Reunión fijada'] || 0)


</script>

<template>
  <div class="min-h-screen font-sans text-[#1F2937] pt-12 md:pt-12">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 right-0 w-150 h-100
                bg-[#99CC33] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <!-- ══════════════════════════════════════════════════════
         GATE: Modal oscuro de contraseña
    ═════════════════════════════════════════════════════════ -->
    <AccesoEmpresasModal v-if="!desbloqueado" @desbloqueado="onDesbloqueado" @cerrar="onCerrarAcceso" />

    <!-- ══════════════════════════════════════════════════════
         MODAL BIENVENIDA: ¿Qué necesitas?
    ═════════════════════════════════════════════════════════ -->
    <Transition name="bv-fade">
      <div v-if="desbloqueado && mostrarBienvenida"
           class="fixed inset-0 z-[9998] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <div class="relative bg-white border border-gray-200 rounded-[2rem]
                    shadow-2xl max-w-md w-full p-8">

          <!-- Cabecera -->
          <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-[#00A859]/10 border border-[#00A859]/20
                        flex items-center justify-center shrink-0">
              <svg class="w-6 h-6 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22" stroke-width="2"/>
              </svg>
            </div>
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-[#00A859] mb-0.5">Directorio de empresas</p>
              <h2 class="text-xl font-black tracking-tight text-[#121212]">¿Qué necesitas?</h2>
            </div>
          </div>

          <!-- Opciones -->
          <div class="space-y-3 mb-6">

            <button @click="abrirBienvenida('contactar')"
                    class="w-full flex items-start gap-4 p-4 rounded-2xl border border-gray-200
                           bg-gray-50 hover:bg-[#00A859]/8 hover:border-[#00A859]/30
                           transition-all duration-200 text-left group">
              <div class="w-9 h-9 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20
                          flex items-center justify-center shrink-0 mt-0.5
                          group-hover:bg-[#00A859]/20 transition-colors">
                <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <p class="font-black text-[#1F2937] text-sm mb-0.5">Contactar empresa</p>
                <p class="text-xs text-gray-500 leading-relaxed">Enviar un email directo a la empresa.</p>
              </div>
            </button>

            <button @click="abrirBienvenida('validar')"
                    class="w-full flex items-start gap-4 p-4 rounded-2xl border border-gray-200
                           bg-gray-50 hover:bg-amber-50 hover:border-amber-200
                           transition-all duration-200 text-left group">
              <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200
                          flex items-center justify-center shrink-0 mt-0.5
                          group-hover:bg-amber-100 transition-colors">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <div>
                <p class="font-black text-[#1F2937] text-sm mb-0.5">Enviar validación</p>
                <p class="text-xs text-gray-500 leading-relaxed">Compartir enlace de propuesta con la empresa.</p>
              </div>
            </button>

            <button @click="abrirBienvenida('listar')"
                    class="w-full flex items-start gap-4 p-4 rounded-2xl border border-gray-200
                           bg-gray-50 hover:bg-blue-50 hover:border-blue-200
                           transition-all duration-200 text-left group">
              <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200
                          flex items-center justify-center shrink-0 mt-0.5
                          group-hover:bg-blue-100 transition-colors">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 10h16M4 14h16M4 18h7"/>
                </svg>
              </div>
              <div>
                <p class="font-black text-[#1F2937] text-sm mb-0.5">Ver listado de empresas</p>
                <p class="text-xs text-gray-500 leading-relaxed">Explorar el directorio completo con filtros.</p>
              </div>
            </button>

          </div>

          <button @click="abrirBienvenida('listar')"
                  class="w-full text-center text-[10px] font-bold text-gray-400
                         hover:text-gray-600 transition-colors py-1">
            Saltar
          </button>
        </div>
      </div>
    </Transition>

    <!-- ══════════════════════════════════════════════════════
         MODAL EJEMPLO: Muestra dónde está la acción
    ═════════════════════════════════════════════════════════ -->
    <Transition name="bv-fade">
      <div v-if="mostrarEjemplo"
           class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
           @click.self="mostrarEjemplo = false">
        <div class="relative bg-white rounded-[2rem] shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">

          <!-- Header sticky -->
          <div class="sticky top-0 bg-white/95 backdrop-blur-sm border-b border-gray-100
                      px-6 py-4 rounded-t-[2rem] flex items-center justify-between z-10">
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Empresa de ejemplo</p>
              <p class="text-sm font-black text-[#121212]">
                Así se ve
                {{ modoEjemplo === 'contactar' ? 'el formulario de contacto' : 'el formulario de validación' }}
              </p>
            </div>
            <button @click="mostrarEjemplo = false"
                    class="w-8 h-8 rounded-xl bg-gray-100 hover:bg-gray-200
                           flex items-center justify-center transition-colors">
              <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Toast informativa -->
          <div class="mx-6 mt-4">
            <div :class="[
              'flex items-start gap-3 p-4 rounded-2xl border',
              modoEjemplo === 'contactar'
                ? 'bg-[#00A859]/8 border-[#00A859]/25 text-[#00A859]'
                : 'bg-amber-50 border-amber-200 text-amber-700'
            ]">
              <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <div>
                <p class="font-bold text-xs mb-0.5">¡Aquí está el botón!</p>
                <p class="text-xs opacity-80 leading-relaxed">
                  <template v-if="modoEjemplo === 'contactar'">
                    Busca la empresa en el listado, despliégala y pulsa
                    <strong>«Enviar email»</strong> para contactarla directamente.
                  </template>
                  <template v-else>
                    Busca la empresa en el listado, despliégala y pulsa
                    <strong>«Enviar validación»</strong> para compartir el enlace de la propuesta.
                  </template>
                </p>
              </div>
            </div>
          </div>

          <!-- Empresa ejemplo -->
          <div class="mx-6 mt-4 rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden bg-white">

            <!-- Cabecera empresa -->
            <div class="flex items-center gap-4 px-5 py-4">
              <div class="w-9 h-9 rounded-xl bg-[#00A859]/10 border border-[#00A859]/15
                          flex items-center justify-center shrink-0 font-black text-sm text-[#00A859]">
                T
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-bold text-sm text-[#121212]">{{ empresaEjemplo.nombre_comercial }}</span>
                  <span :class="['text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full border',
                                 badgeEstado(empresaEjemplo.estado_contacto)]">
                    {{ empresaEjemplo.estado_contacto }}
                  </span>
                  <span class="text-[9px] font-black text-gray-400 px-2 py-0.5 rounded-full
                               border border-dashed border-gray-200">
                    Ejemplo
                  </span>
                </div>
                <div class="flex items-center gap-3 mt-0.5 flex-wrap">
                  <span class="text-[10px] text-gray-400">{{ empresaEjemplo.sector }}</span>
                  <span class="text-[10px] text-gray-400">
                    {{ empresaEjemplo.municipio }}, {{ empresaEjemplo.provincia }}
                  </span>
                  <span class="text-[10px] text-[#00A859]">{{ empresaEjemplo.email_contacto }}</span>
                </div>
              </div>
            </div>

            <!-- Panel expandido -->
            <div class="border-t border-gray-100">
              <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">

                <!-- Datos -->
                <div class="space-y-4">
                  <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Datos de la empresa</p>
                  <div class="space-y-2">
                    <div class="flex justify-between gap-3">
                      <span class="text-[10px] text-gray-400">Razón social</span>
                      <span class="text-[10px] text-gray-700 text-right">{{ empresaEjemplo.razon_social }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-[10px] text-gray-400">CIF</span>
                      <span class="text-[10px] text-gray-700">{{ empresaEjemplo.cif }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-[10px] text-gray-400">Teléfono</span>
                      <span class="text-[10px] text-[#00A859]">{{ empresaEjemplo.telefono }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-[10px] text-gray-400">Email general</span>
                      <span class="text-[10px] text-[#00A859]">{{ empresaEjemplo.email_general }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-[10px] text-gray-400">Persona de contacto</span>
                      <span class="text-[10px] text-gray-700">
                        {{ empresaEjemplo.persona_contacto }}
                        <span class="text-gray-400"> · {{ empresaEjemplo.posicion_contacto }}</span>
                      </span>
                    </div>
                    <div class="flex justify-between gap-3">
                      <span class="text-[10px] text-gray-400">Actividad</span>
                      <span class="text-[10px] text-gray-700 text-right max-w-[60%]">
                        {{ empresaEjemplo.actividad }}
                      </span>
                    </div>
                  </div>
                  <div class="pt-3 border-t border-gray-100">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">Familias profesionales</p>
                    <div class="flex flex-wrap gap-1.5">
                      <span v-for="f in empresaEjemplo.familias" :key="f.id"
                            class="text-[9px] px-2 py-0.5 rounded-full bg-[#00A859]/10
                                   border border-[#00A859]/20 text-[#00A859] font-medium">
                        {{ f.nombre }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Acciones con botones destacados -->
                <div class="space-y-3">
                  <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Acciones</p>

                  <div class="grid grid-cols-2 gap-2 items-end">

                    <!-- Botón Enviar email -->
                    <div class="flex flex-col items-stretch gap-1">
                      <div v-if="modoEjemplo === 'contactar'"
                           class="flex flex-col items-center gap-0.5 animate-bounce">
                        <span class="text-[9px] font-black text-[#00A859] uppercase tracking-wider">pulsa aquí</span>
                        <svg class="w-4 h-4 text-[#00A859]" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M12 20l-8-8h5V4h6v8h5z"/>
                        </svg>
                      </div>
                      <button :class="[
                        'flex items-center justify-center gap-2 py-2.5 px-3 rounded-2xl border text-xs font-bold transition-all',
                        modoEjemplo === 'contactar'
                          ? 'bg-[#00A859]/8 border-[#00A859]/25 text-[#00A859] ring-2 ring-[#00A859]/40 ring-offset-1'
                          : 'bg-gray-50 border-gray-200 text-gray-600'
                      ]">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Enviar email
                      </button>
                    </div>

                    <!-- Botón Enviar validación -->
                    <div class="flex flex-col items-stretch gap-1">
                      <div v-if="modoEjemplo === 'validar'"
                           class="flex flex-col items-center gap-0.5 animate-bounce">
                        <span class="text-[9px] font-black text-amber-600 uppercase tracking-wider">pulsa aquí</span>
                        <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M12 20l-8-8h5V4h6v8h5z"/>
                        </svg>
                      </div>
                      <button :class="[
                        'flex items-center justify-center gap-2 py-2.5 px-3 rounded-2xl border text-xs font-bold transition-all',
                        modoEjemplo === 'validar'
                          ? 'bg-amber-50 border-amber-200 text-amber-700 ring-2 ring-amber-300/60 ring-offset-1'
                          : 'bg-gray-50 border-gray-200 text-gray-600'
                      ]">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Enviar validación
                      </button>
                    </div>

                  </div>

                  <p class="text-[10px] text-gray-400 leading-relaxed pt-1">
                    <template v-if="modoEjemplo === 'contactar'">
                      Al pulsar «Enviar email» se abre un formulario para escribir asunto y mensaje.
                      El correo se envía a la dirección registrada de la empresa.
                    </template>
                    <template v-else>
                      Al pulsar «Enviar validación» podrás seleccionar una propuesta publicada
                      y enviar su enlace a la empresa para que la valide.
                    </template>
                  </p>
                </div>

              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-5">
            <button @click="mostrarEjemplo = false"
                    class="w-full py-3 rounded-full bg-[#00A859] text-white text-xs font-black
                           uppercase tracking-widest transition-all hover:bg-[#009950]">
              Entendido, ir al listado
            </button>
          </div>

        </div>
      </div>
    </Transition>

    <!-- ══════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
    ═════════════════════════════════════════════════════════ -->
    <div
      v-if="desbloqueado"
      class="relative z-10 max-w-5xl mx-auto px-6 py-10 pl-24"
      :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
      style="transition: opacity 0.4s ease, transform 0.4s ease"
    >

      <!-- Cabecera -->
      <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-10 h-10 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20
                      flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke-width="1.5"/>
              <polyline points="9 22 9 12 15 12 15 22" stroke-width="1.5"/>
            </svg>
          </div>
          <div>
            <h1 class="text-2xl font-black tracking-tight text-[#121212]">
              Directorio de <span class="text-[#00A859]">Empresas</span>
            </h1>
            <p class="text-gray-400 text-xs">{{ empresas.length }} empresas en la base de datos</p>
          </div>
        </div>
        <button @click="mostrarBienvenida = true"
                class="mt-2 text-[10px] font-bold text-gray-400 hover:text-[#00A859]
                       transition-colors flex items-center gap-1.5">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          ¿Qué necesitas? Ver guía
        </button>
      </div>

      <!-- Stats chips -->
      <div v-if="!cargando && empresas.length"
           class="mb-5 flex flex-wrap gap-3 items-center">
        <div class="flex flex-wrap gap-2 items-center
                    bg-white/70 rounded-[1.4rem] border border-gray-100 shadow-sm
                    px-2 py-1.5">

          <!-- Total empresas -->
          <div class="flex items-center gap-2 px-3 py-1.5 bg-white rounded-2xl border border-gray-100 shadow-sm">
            <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
            </svg>
            <span class="font-black text-xl text-[#1F2937]">{{ empresas.length }}</span>
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">empresas</span>
          </div>

          <!-- Total centros -->
          <div class="flex items-center gap-2 px-3 py-1.5 bg-white rounded-2xl border border-gray-100 shadow-sm">
            <svg class="w-4 h-4 text-[#99CC33]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
            </svg>
            <span class="font-black text-xl text-[#1F2937]">{{ centrosOrdenados.length }}</span>
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">centros</span>
          </div>

          <!-- Activas -->
          <div v-if="totalActivas > 0"
               class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 rounded-2xl border border-emerald-100 shadow-sm">
            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="font-black text-xl text-emerald-700">{{ totalActivas }}</span>
            <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">activas</span>
          </div>

          <!-- Contactadas -->
          <div v-if="totalContactadas > 0"
               class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 rounded-2xl border border-blue-100 shadow-sm">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span class="font-black text-xl text-blue-700">{{ totalContactadas }}</span>
            <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider">contactadas</span>
          </div>

          <!-- Reunión fijada -->
          <div v-if="totalReunion > 0"
               class="flex items-center gap-2 px-3 py-1.5 bg-amber-50 rounded-2xl border border-amber-100 shadow-sm">
            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="font-black text-xl text-amber-700">{{ totalReunion }}</span>
            <span class="text-xs font-semibold text-amber-600 uppercase tracking-wider">reunión</span>
          </div>

          <!-- Filtrado activo -->
          <div v-if="empresasFiltradas.length !== empresas.length"
               class="flex items-center gap-2 px-3 py-1.5 bg-violet-50 rounded-2xl border border-violet-100 shadow-sm">
            <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span class="font-black text-xl text-violet-700">{{ empresasFiltradas.length }}</span>
            <span class="text-xs font-semibold text-violet-600 uppercase tracking-wider">mostrando</span>
          </div>

        </div>
      </div>

      <!-- Filtros -->
      <div class="mb-5 rounded-[2rem] bg-white border border-gray-100 shadow-sm p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

          <!-- Búsqueda -->
          <div class="relative lg:col-span-2">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300 pointer-events-none"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8" stroke-width="2"/>
              <path d="M21 21l-4.35-4.35" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <input
              v-model="busqueda"
              type="text"
              placeholder="Buscar empresa por nombre..."
              class="w-full pl-9 pr-4 py-2.5 bg-white border border-gray-200 rounded-2xl
                     text-sm text-[#1F2937] placeholder-gray-400 shadow-sm
                     focus:outline-none focus:border-[#00A859] transition-colors"
            />
          </div>

          <select v-model="filtroFamilia"
                  class="px-3 py-2.5 bg-white border border-gray-200 rounded-2xl shadow-sm
                         text-sm text-[#1F2937] focus:outline-none focus:border-[#00A859] transition-colors">
            <option value="">Todas las familias</option>
            <option v-for="f in familias" :key="f.id" :value="String(f.id)">{{ f.nombre }}</option>
          </select>

          <select v-model="filtroCentro"
                  class="px-3 py-2.5 bg-white border border-gray-200 rounded-2xl shadow-sm
                         text-sm text-[#1F2937] focus:outline-none focus:border-[#00A859] transition-colors">
            <option value="">Todos los centros</option>
            <option v-for="c in centros" :key="c" :value="c">{{ c }}</option>
          </select>

          <select v-model="filtroEstado"
                  class="px-3 py-2.5 bg-white border border-gray-200 rounded-2xl shadow-sm
                         text-sm text-[#1F2937] focus:outline-none focus:border-[#00A859] transition-colors">
            <option value="">Todos los estados</option>
            <option v-for="e in estadosContacto" :key="e" :value="e">{{ e }}</option>
          </select>

          <select v-model="filtroProvincia"
                  class="px-3 py-2.5 bg-white border border-gray-200 rounded-2xl shadow-sm
                         text-sm text-[#1F2937] focus:outline-none focus:border-[#00A859] transition-colors">
            <option value="">Todas las provincias</option>
            <option v-for="p in provincias" :key="p" :value="p">{{ p }}</option>
          </select>

          <select v-model="filtroSector"
                  class="px-3 py-2.5 bg-white border border-gray-200 rounded-2xl shadow-sm
                         text-sm text-[#1F2937] focus:outline-none focus:border-[#00A859] transition-colors">
            <option value="">Todos los sectores</option>
            <option v-for="s in sectores" :key="s" :value="s">{{ s }}</option>
          </select>

        </div>

        <div class="flex items-center justify-between mt-3">
          <p class="text-[10px] text-gray-400">
            {{ empresasFiltradas.length }} empresa{{ empresasFiltradas.length !== 1 ? 's' : '' }}
            en {{ centrosOrdenados.length }} centro{{ centrosOrdenados.length !== 1 ? 's' : '' }}
          </p>
          <button
            v-if="busqueda || filtroFamilia || filtroEstado || filtroProvincia || filtroSector || filtroCentro"
            @click="limpiarFiltros"
            class="text-[10px] font-bold text-gray-400 hover:text-[#00A859] transition-colors uppercase tracking-widest"
          >
            Limpiar filtros
          </button>
        </div>
      </div>

      <!-- Cargando -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-24">
        <svg class="animate-spin w-10 h-10 text-[#00A859] mb-3" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="text-gray-400 text-xs uppercase tracking-widest">Cargando empresas...</p>
      </div>

      <!-- Lista agrupada por centro -->
      <div v-else-if="centrosOrdenados.length" class="space-y-3">
        <div v-for="centro in centrosOrdenados" :key="centro">

          <!-- Cabecera del centro (desplegable) -->
          <div class="bg-white rounded-[1.75rem] border shadow-sm overflow-hidden transition-all duration-300"
               :class="!centrosCerrados.has(centro) ? 'border-[#00A859]/25 shadow-md' : 'border-gray-100'">

            <button @click="toggleCentro(centro)"
                    class="w-full flex items-center gap-4 px-6 py-5 text-left hover:bg-gray-50/70 transition-colors duration-150">
              <!-- Icono centro -->
              <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 transition-all duration-200"
                   :class="!centrosCerrados.has(centro) ? 'bg-[#1F2937]' : 'bg-gray-100'">
                <svg class="w-5 h-5 transition-colors duration-200"
                     :class="!centrosCerrados.has(centro) ? 'text-[#99CC33]' : 'text-gray-400'"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                </svg>
              </div>
              <!-- Info -->
              <div class="flex-1 min-w-0">
                <h2 class="font-black text-base truncate"
                    :class="centro === SIN_CENTRO ? 'text-gray-400 italic' : 'text-[#1F2937]'">
                  {{ centro }}
                </h2>
                <p class="text-xs text-gray-400 font-medium mt-0.5 flex flex-wrap items-center gap-x-1">
                  <span>
                    {{ empresasPorCentro[centro].length }}
                    {{ empresasPorCentro[centro].length !== 1 ? 'empresas' : 'empresa' }}
                  </span>
                  <template v-if="empresasPorCentro[centro].filter(e => e.estado_contacto === 'Activo').length">
                    <span class="text-gray-300">·</span>
                    <span class="text-emerald-500 font-semibold">
                      {{ empresasPorCentro[centro].filter(e => e.estado_contacto === 'Activo').length }}
                      activa{{ empresasPorCentro[centro].filter(e => e.estado_contacto === 'Activo').length > 1 ? 's' : '' }}
                    </span>
                  </template>
                  <template v-if="empresasPorCentro[centro].filter(e => e.estado_contacto === 'Reunión fijada').length">
                    <span class="text-gray-300">·</span>
                    <span class="text-amber-500 font-semibold">
                      {{ empresasPorCentro[centro].filter(e => e.estado_contacto === 'Reunión fijada').length }}
                      reunión{{ empresasPorCentro[centro].filter(e => e.estado_contacto === 'Reunión fijada').length > 1 ? 'es' : '' }}
                    </span>
                  </template>
                  <template v-if="empresasPorCentro[centro].filter(e => e.estado_contacto === 'Contactado').length">
                    <span class="text-gray-300">·</span>
                    <span class="text-blue-400 font-semibold">
                      {{ empresasPorCentro[centro].filter(e => e.estado_contacto === 'Contactado').length }}
                      contactada{{ empresasPorCentro[centro].filter(e => e.estado_contacto === 'Contactado').length > 1 ? 's' : '' }}
                    </span>
                  </template>
                </p>
              </div>
              <!-- Chevron -->
              <svg class="w-5 h-5 text-gray-400 transition-transform duration-300 shrink-0"
                   :class="!centrosCerrados.has(centro) ? 'rotate-180' : ''"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <!-- Empresas del centro -->
            <Transition name="slide-down">
              <div v-if="!centrosCerrados.has(centro)"
                   class="border-t border-gray-100 px-3 pb-3 pt-2 space-y-2">
                <div
                  v-for="empresa in empresasPorCentro[centro]"
                  :key="empresa.id"
                  class="rounded-2xl border overflow-hidden transition-all duration-200 bg-white"
                  :class="empresaExpandida?.id === empresa.id
                    ? 'border-[#00A859]/20 shadow-md'
                    : 'border-gray-100 shadow-sm hover:border-gray-200 hover:shadow'"
                >
                  <button @click="toggleEmpresa(empresa)"
                          class="w-full flex items-center gap-4 px-5 py-4 text-left">
                    <div class="w-9 h-9 rounded-xl bg-[#00A859]/10 border border-[#00A859]/15
                                flex items-center justify-center shrink-0 font-black text-sm text-[#00A859] select-none">
                      {{ (empresa.nombre_comercial || '?')[0].toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-sm text-[#121212] truncate">{{ empresa.nombre_comercial }}</span>
                        <span v-if="empresa.estado_contacto"
                              :class="['text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full border',
                                       badgeEstado(empresa.estado_contacto)]">
                          {{ empresa.estado_contacto }}
                        </span>
                      </div>
                      <div class="flex items-center gap-3 mt-0.5 flex-wrap">
                        <span v-if="empresa.sector" class="text-[10px] text-gray-400">{{ empresa.sector }}</span>
                        <span v-if="empresa.municipio" class="text-[10px] text-gray-400">
                          {{ empresa.municipio }}<span v-if="empresa.provincia">, {{ empresa.provincia }}</span>
                        </span>
                        <span v-if="empresa.email_general || empresa.email_contacto"
                              class="text-[10px] text-[#00A859]">
                          {{ empresa.email_contacto || empresa.email_general }}
                        </span>
                      </div>
                    </div>
                    <svg class="w-4 h-4 text-gray-300 transition-transform duration-200 shrink-0"
                         :class="empresaExpandida?.id === empresa.id ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </button>

                  <Transition name="slide-down">
                    <div v-if="empresaExpandida?.id === empresa.id" class="border-t border-gray-100">
                      <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">

                        <!-- Ficha de datos -->
                        <div class="space-y-4">
                          <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Datos de la empresa</p>
                          <div class="space-y-2">
                            <template v-if="empresa.razon_social">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Razón social</span>
                                <span class="text-[10px] text-gray-700 text-right">{{ empresa.razon_social }}</span>
                              </div>
                            </template>
                            <template v-if="empresa.cif">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">CIF</span>
                                <span class="text-[10px] text-gray-700">{{ empresa.cif }}</span>
                              </div>
                            </template>
                            <template v-if="empresa.telefono">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Teléfono</span>
                                <a :href="`tel:${empresa.telefono}`" class="text-[10px] text-[#00A859] hover:underline">
                                  {{ empresa.telefono }}
                                </a>
                              </div>
                            </template>
                            <template v-if="empresa.email_general">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Email general</span>
                                <a :href="`mailto:${empresa.email_general}`"
                                   class="text-[10px] text-[#00A859] hover:underline truncate">
                                  {{ empresa.email_general }}
                                </a>
                              </div>
                            </template>
                            <template v-if="empresa.web">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Web</span>
                                <a :href="empresa.web.startsWith('http') ? empresa.web : 'https://'+empresa.web"
                                   target="_blank" rel="noopener"
                                   class="text-[10px] text-[#00A859] hover:underline truncate">
                                  {{ empresa.web }}
                                </a>
                              </div>
                            </template>
                            <template v-if="empresa.persona_contacto">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Persona de contacto</span>
                                <span class="text-[10px] text-gray-700">
                                  {{ empresa.persona_contacto }}
                                  <span v-if="empresa.posicion_contacto" class="text-gray-400">
                                    · {{ empresa.posicion_contacto }}
                                  </span>
                                </span>
                              </div>
                            </template>
                            <template v-if="empresa.email_contacto">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Email contacto</span>
                                <a :href="`mailto:${empresa.email_contacto}`"
                                   class="text-[10px] text-[#00A859] hover:underline truncate">
                                  {{ empresa.email_contacto }}
                                </a>
                              </div>
                            </template>
                            <template v-if="empresa.actividad">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Actividad</span>
                                <span class="text-[10px] text-gray-700 text-right max-w-[60%]">{{ empresa.actividad }}</span>
                              </div>
                            </template>
                            <template v-if="empresa.tamano">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Tamaño</span>
                                <span class="text-[10px] text-gray-700">{{ empresa.tamano }}</span>
                              </div>
                            </template>
                            <template v-if="empresa.horario_atencion">
                              <div class="flex justify-between gap-3">
                                <span class="text-[10px] text-gray-400">Horario</span>
                                <span class="text-[10px] text-gray-700 text-right">{{ empresa.horario_atencion }}</span>
                              </div>
                            </template>
                          </div>

                          <div v-if="empresa.direccion || empresa.municipio" class="pt-3 border-t border-gray-100">
                            <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">Dirección</p>
                            <p class="text-[10px] text-gray-500 leading-relaxed">
                              <span v-if="empresa.direccion">
                                {{ empresa.direccion }}
                                <span v-if="empresa.numero"> {{ empresa.numero }}</span>
                                <span v-if="empresa.otros_direccion">, {{ empresa.otros_direccion }}</span>
                              </span>
                              <br v-if="empresa.direccion && (empresa.municipio || empresa.codigo_postal)"/>
                              <span v-if="empresa.codigo_postal">{{ empresa.codigo_postal }} </span>
                              <span v-if="empresa.municipio">{{ empresa.municipio }}</span>
                              <span v-if="empresa.provincia">, {{ empresa.provincia }}</span>
                            </p>
                          </div>

                          <div v-if="empresa.familias?.length" class="pt-3 border-t border-gray-100">
                            <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">
                              Familias profesionales
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                              <span v-for="f in empresa.familias" :key="f.id"
                                    class="text-[9px] px-2 py-0.5 rounded-full bg-[#00A859]/10
                                           border border-[#00A859]/20 text-[#00A859] font-medium">
                                {{ f.nombre }}
                              </span>
                            </div>
                          </div>
                        </div>

                        <!-- Acciones -->
                        <div class="space-y-3">
                          <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Acciones</p>

                          <div class="grid grid-cols-2 gap-2">
                            <button
                              @click="abrirPanel('email')"
                              :class="[
                                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-2xl border text-xs font-bold transition-all',
                                panelActivo === 'email'
                                  ? 'bg-[#00A859]/8 border-[#00A859]/25 text-[#00A859]'
                                  : 'bg-gray-50 border-gray-200 text-gray-600 hover:border-gray-300 hover:text-[#1F2937]'
                              ]"
                            >
                              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                              </svg>
                              Enviar email
                            </button>

                            <button
                              @click="abrirPanel('validacion')"
                              :class="[
                                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-2xl border text-xs font-bold transition-all',
                                panelActivo === 'validacion'
                                  ? 'bg-amber-50 border-amber-200 text-amber-700'
                                  : 'bg-gray-50 border-gray-200 text-gray-600 hover:border-gray-300 hover:text-[#1F2937]'
                              ]"
                            >
                              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                              </svg>
                              Enviar validación
                            </button>
                          </div>

                          <!-- Formulario email -->
                          <Transition name="slide-down">
                            <div v-if="panelActivo === 'email'"
                                 class="rounded-2xl bg-[#00A859]/5 border border-[#00A859]/15 p-4 space-y-3">
                              <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859]">Enviar correo</p>
                              <div v-if="emailOk"
                                   class="flex items-center gap-2 p-3 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20">
                                <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <p class="text-xs font-bold text-[#00A859]">Correo enviado correctamente.</p>
                              </div>
                              <template v-if="!emailOk">
                                <div>
                                  <label class="block text-[9px] text-gray-500 uppercase tracking-wider mb-1">Remitente (tu email)</label>
                                  <input v-model="emailForm.remitente" type="email"
                                         class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2
                                                text-xs text-[#1F2937] placeholder-gray-400 shadow-sm
                                                focus:outline-none focus:border-[#00A859] transition-colors"/>
                                </div>
                                <div>
                                  <label class="block text-[9px] text-gray-500 uppercase tracking-wider mb-1">Destinatario</label>
                                  <p class="text-xs text-gray-600 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl shadow-sm">
                                    {{ empresa.email_contacto || empresa.email_general || 'Sin email registrado' }}
                                  </p>
                                </div>
                                <div>
                                  <label class="block text-[9px] text-gray-500 uppercase tracking-wider mb-1">Asunto</label>
                                  <input v-model="emailForm.asunto" type="text" placeholder="Asunto del correo..."
                                         class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2
                                                text-xs text-[#1F2937] placeholder-gray-400 shadow-sm
                                                focus:outline-none focus:border-[#00A859] transition-colors"/>
                                </div>
                                <div>
                                  <label class="block text-[9px] text-gray-500 uppercase tracking-wider mb-1">Mensaje</label>
                                  <textarea v-model="emailForm.mensaje" rows="4" placeholder="Escribe tu mensaje aquí..."
                                            class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2
                                                   text-xs text-[#1F2937] placeholder-gray-400 shadow-sm resize-none
                                                   focus:outline-none focus:border-[#00A859] transition-colors"/>
                                </div>
                                <p v-if="emailError" class="text-xs text-red-500 font-medium">{{ emailError }}</p>
                                <button @click="enviarEmail"
                                        :disabled="enviandoEmail || !emailForm.asunto || !emailForm.mensaje ||
                                                   !(empresa.email_contacto || empresa.email_general)"
                                        class="w-full py-2.5 rounded-full bg-[#00A859] text-white text-xs font-black
                                               uppercase tracking-widest transition-all shadow-sm
                                               hover:bg-[#009950] disabled:opacity-40 disabled:cursor-not-allowed">
                                  {{ enviandoEmail ? 'Enviando...' : 'Enviar correo' }}
                                </button>
                                <p v-if="!(empresa.email_contacto || empresa.email_general)"
                                   class="text-[10px] text-amber-600 text-center">
                                  Esta empresa no tiene email registrado.
                                </p>
                              </template>
                            </div>
                          </Transition>

                          <!-- Formulario validación -->
                          <Transition name="slide-down">
                            <div v-if="panelActivo === 'validacion'"
                                 class="rounded-2xl bg-amber-50 border border-amber-200 p-4 space-y-3">
                              <p class="text-[9px] font-black uppercase tracking-widest text-amber-700">
                                Enviar enlace de validación
                              </p>
                              <div v-if="validacionOk"
                                   class="flex items-center gap-2 p-3 rounded-xl bg-amber-100 border border-amber-200">
                                <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <p class="text-xs font-bold text-amber-800">Enlace enviado correctamente.</p>
                              </div>
                              <template v-if="!validacionOk">
                                <div>
                                  <label class="block text-[9px] text-gray-500 uppercase tracking-wider mb-1">Remitente (tu email)</label>
                                  <input v-model="validForm.remitente" type="email"
                                         class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2
                                                text-xs text-[#1F2937] placeholder-gray-400 shadow-sm
                                                focus:outline-none focus:border-amber-400 transition-colors"/>
                                </div>
                                <div>
                                  <label class="block text-[9px] text-gray-500 uppercase tracking-wider mb-1">Propuesta a validar</label>
                                  <select v-model="validForm.proyecto_uuid"
                                          class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2
                                                 text-xs text-[#1F2937] shadow-sm
                                                 focus:outline-none focus:border-amber-400 transition-colors">
                                    <option value="">Selecciona una propuesta publicada...</option>
                                    <option v-for="p in proyectos" :key="p.uuid" :value="p.uuid">
                                      {{ p.titulo }}<template v-if="p.empresa_nombre"> · {{ p.empresa_nombre }}</template>
                                    </option>
                                  </select>
                                  <p v-if="proyectos.length === 0" class="text-[10px] text-gray-400 mt-1">
                                    No hay propuestas publicadas. Publícalas en el StartUp Day.
                                  </p>
                                </div>
                                <div>
                                  <label class="block text-[9px] text-gray-500 uppercase tracking-wider mb-1">
                                    Mensaje adicional <span class="normal-case font-normal">(opcional)</span>
                                  </label>
                                  <textarea v-model="validForm.mensaje" rows="3"
                                            placeholder="Añade contexto o instrucciones adicionales para la empresa..."
                                            class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2
                                                   text-xs text-[#1F2937] placeholder-gray-400 shadow-sm resize-none
                                                   focus:outline-none focus:border-amber-400 transition-colors"/>
                                </div>
                                <p v-if="validacionError" class="text-xs text-red-500 font-medium">{{ validacionError }}</p>
                                <button @click="enviarValidacionEmail"
                                        :disabled="enviandoValidacion || !validForm.proyecto_uuid ||
                                                   !(empresa.email_contacto || empresa.email_general)"
                                        class="w-full py-2.5 rounded-full bg-amber-500 text-white text-xs font-black
                                               uppercase tracking-widest transition-all shadow-sm
                                               hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed">
                                  {{ enviandoValidacion ? 'Enviando...' : 'Enviar enlace de validación' }}
                                </button>
                                <p v-if="!(empresa.email_contacto || empresa.email_general)"
                                   class="text-[10px] text-amber-600 text-center">
                                  Esta empresa no tiene email registrado.
                                </p>
                              </template>
                            </div>
                          </Transition>

                        </div>
                      </div>
                    </div>
                  </Transition>

                </div>
              </div>
            </Transition>

          </div>
        </div>
      </div>

      <!-- Sin resultados -->
      <div v-else-if="!cargando"
           class="text-center py-24 rounded-[2rem] border border-dashed border-gray-200 bg-white shadow-sm">
        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22" stroke-width="1.5"/>
          </svg>
        </div>
        <p class="text-gray-400 text-sm">No se han encontrado empresas con estos filtros.</p>
        <button @click="limpiarFiltros"
                class="mt-3 text-xs font-bold text-[#00A859] hover:text-[#009950] transition-colors">
          Limpiar filtros
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

.bv-fade-enter-active, .bv-fade-leave-active { transition: opacity 200ms ease; }
.bv-fade-enter-from, .bv-fade-leave-to       { opacity: 0; }

.slide-down-enter-active { transition: all 0.22s ease; overflow: hidden; }
.slide-down-leave-active { transition: all 0.18s ease; overflow: hidden; }
.slide-down-enter-from, .slide-down-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(-6px);
}
.slide-down-enter-to, .slide-down-leave-from {
  max-height: 6000px;
}

select option { background: white; color: #1F2937; }
</style>
