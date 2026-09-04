<!-- Ruta: /encuentros/crear (name: dashboard-docente). El nombre del archivo quedó del naming antiguo (/dashboard) tras el rename de URLs — ver router/index.js. -->
<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter, onBeforeRouteUpdate } from 'vue-router'
import api from '../api.js'
import MicroretoModal from '../components/MicroretoModal.vue'
import ProyectoFichaModal from '../components/ProyectoFichaModal.vue'
import EliminarEncuentroModal from '../components/EliminarEncuentroModal.vue'
import EncuentroEquiposYRaCe from '../components/EncuentroEquiposYRaCe.vue'
import TourPromptModal from '../components/TourPromptModal.vue'
import { useUIState } from '../composables/useUIState.js'
import { useRaCeEncuentro } from '../composables/useRaCeEncuentro.js'
import { useAuthStore } from '../stores/auth.js'
import { fechaFinEstimada as calcularFechaFinEstimada, duracionPorFase } from '../config/fasesProyecto.js'

const { tourActivo } = useUIState()
const authStore = useAuthStore()

const router = useRouter()
const route  = useRoute()

// ─── Formulario de encuentro ──────────────────────────────────────────────────────
const form = ref({
  fecha:            new Date().toISOString().slice(0, 10),
  centro_educativo: authStore.userCentroNombre || '',
  ciclo_formativo:  '',
  curso:            '',
  grupo:            '',
  num_alumnos:      '',
  num_equipos:      3,
  alumnados:        [],
  notas:            '',
  microproyecto_id: null,
})

// ─── Buscador de proyectos ─────────────────────────────────────────────────────
const proyectoSeleccionado  = ref(null)
const mostrarBuscadorProy   = ref(false)
const todosMisProyectos     = ref([])
const cargandoProyectos     = ref(false)
const proyectosCargados     = ref(false)

const filtroProyCurso  = ref('')
const filtroProyCiclo  = ref('')
const filtroProyEstado = ref('')
const busquedaProy     = ref('')

const ciclosDisponiblesProy = computed(() => {
  const set = new Set(todosMisProyectos.value.map(p => p.ciclo_nombre).filter(Boolean))
  return [...set].sort()
})

const proyectosFiltrados = computed(() => {
  // Solo propuestas ya publicadas o proyectos validados pueden asociarse a un
  // encuentro — un borrador o un archivado no tiene sentido programarlo en clase.
  let base = todosMisProyectos.value.filter(p => p.estado === 'propuesta' || p.estado === 'validado')
  if (filtroProyCurso.value)  base = base.filter(p => p.curso === filtroProyCurso.value)
  if (filtroProyCiclo.value)  base = base.filter(p => p.ciclo_nombre === filtroProyCiclo.value)
  if (filtroProyEstado.value) base = base.filter(p => p.estado === filtroProyEstado.value)
  if (busquedaProy.value.trim()) {
    const q = busquedaProy.value.trim().toLowerCase()
    base = base.filter(p =>
      (p.titulo         || '').toLowerCase().includes(q) ||
      (p.empresa_nombre || '').toLowerCase().includes(q) ||
      (p.ciclo_nombre   || '').toLowerCase().includes(q)
    )
  }
  return base.slice(0, 60)
})

async function abrirBuscadorProy() {
  mostrarBuscadorProy.value = true
  if (proyectosCargados.value) return
  cargandoProyectos.value = true
  try {
    const res = await api.get('/startup/proyectos')
    todosMisProyectos.value = res.data
    proyectosCargados.value = true
  } catch (e) {
    console.error('Error cargando proyectos:', e)
  } finally {
    cargandoProyectos.value = false
  }
}

function cerrarBuscadorProy() {
  mostrarBuscadorProy.value = false
}

function seleccionarProyecto(p) {
  proyectoSeleccionado.value   = p
  form.value.microproyecto_id  = p.id
  if (p.ciclo_nombre) form.value.ciclo_formativo = p.ciclo_nombre
  if (p.curso)        form.value.curso           = p.curso
  mostrarBuscadorProy.value    = false
}

function limpiarProyecto() {
  proyectoSeleccionado.value   = null
  form.value.microproyecto_id  = null
  form.value.ciclo_formativo   = ''
  form.value.curso             = ''
  // El reparto de equipos se hizo pensando en el proyecto anterior — no tiene
  // sentido mantenerlo al asociar uno distinto.
  form.value.num_equipos       = 3
  form.value.alumnados         = []
}

// Duración total (en clases) del calendario del proyecto elegido — el backend
// calcula la fecha_fin real con la misma heurística centralizada (fasesProyecto.js).
const totalClasesProyectoSeleccionado = computed(() => {
  return proyectoSeleccionado.value?.diseno_microproyecto?.clases?.length || 0
})

const fechaFinEstimada = computed(() => {
  return calcularFechaFinEstimada(form.value.fecha, totalClasesProyectoSeleccionado.value)
})

function estadoProyectoBadge(proyectoOEstado) {
  const estado          = typeof proyectoOEstado === 'object' ? proyectoOEstado?.estado : proyectoOEstado
  const empresaValidado = typeof proyectoOEstado === 'object' && !!proyectoOEstado?.empresa_validado
  const docenteValidado = typeof proyectoOEstado === 'object' && !!proyectoOEstado?.docente_validado

  if (estado === 'borrador' || estado === 'en_edicion')
    return { label: 'En edición',  cls: 'bg-amber-100 text-amber-600' }
  if (estado === 'archivado')
    return { label: 'Archivado',   cls: 'bg-red-50 text-red-400' }
  if (estado === 'validado') {
    if (empresaValidado && docenteValidado) return { label: 'Validado · Completo', cls: 'bg-[#00A859]/10 text-[#00A859]' }
    if (empresaValidado)  return { label: 'Validado · Empresa',  cls: 'bg-[#00A859]/10 text-[#00A859]' }
    if (docenteValidado)  return { label: 'Validado · Docente',  cls: 'bg-emerald-50 text-emerald-700' }
    return { label: 'Validado', cls: 'bg-[#00A859]/10 text-[#00A859]' }
  }
  if (estado === 'propuesta')
    return { label: 'Propuesta',   cls: 'bg-violet-50 text-violet-600' }
  return { label: estado || '—',   cls: 'bg-gray-100 text-gray-500' }
}

// ─── Alumnado por equipos ─────────────────────────────────────────────────────
const nuevoAlumnadoNombre = ref('')
const nuevoAlumnadoEquipo = ref(1)

const limiteAlumnados = computed(() => {
  const n = Number(form.value.num_alumnos)
  return n > 0 ? n : null
})

const limiteAlcanzado = computed(() =>
  limiteAlumnados.value !== null && form.value.alumnados.length >= limiteAlumnados.value
)

function addAlumnadoEncuentro() {
  const nombre = nuevoAlumnadoNombre.value.trim()
  if (!nombre) return
  if (limiteAlcanzado.value) return
  form.value.alumnados.push({ nombre, equipo_num: nuevoAlumnadoEquipo.value })
  nuevoAlumnadoNombre.value = ''
}

function removeAlumnadoEncuentro(i) {
  form.value.alumnados.splice(i, 1)
}

function alumnadosDeEquipo(n) {
  return form.value.alumnados
    .map((a, i) => ({ ...a, _i: i }))
    .filter(a => a.equipo_num === n)
}

const alumnadosSinEquipo = computed(() =>
  form.value.alumnados
    .map((a, i) => ({ ...a, _i: i }))
    .filter(a => !a.equipo_num || a.equipo_num < 1)
)

const guardando     = ref(false)
const guardadoOk    = ref(false)
const errorGuardado = ref('')

// ─── Historial de encuentros (API) ────────────────────────────────────────────
const encuentros         = ref([])
const cargandoEncuentros = ref(false)

async function cargarEncuentros() {
  cargandoEncuentros.value = true
  try {
    const res = await api.get('/encuentros')
    encuentros.value = res.data
  } catch (e) {
    console.error('Error cargando encuentros:', e)
  } finally {
    cargandoEncuentros.value = false
  }
}

// ─── Opciones para datalist (desde historial de encuentros) ──────────────────
const centrosParaAutocompletar = computed(() => {
  const set = new Set()
  encuentros.value.forEach(s => { if (s.centro_educativo) set.add(s.centro_educativo) })
  return [...set].sort()
})

const ciclosParaAutocompletar = computed(() => {
  const set = new Set()
  encuentros.value.forEach(s => { if (s.ciclo_formativo) set.add(s.ciclo_formativo) })
  return [...set].sort()
})

onMounted(async () => {
  await cargarEncuentros()

  // Preselecciona el proyecto si venimos del botón "Crear encuentro" del detalle
  // de un proyecto validado (StartupDayDetalle.vue).
  if (route.query.microproyecto_id) {
    try {
      const res = await api.get('/startup/proyectos')
      todosMisProyectos.value = res.data
      proyectosCargados.value = true
      const p = todosMisProyectos.value.find(pr => String(pr.id) === String(route.query.microproyecto_id))
      if (p) seleccionarProyecto(p)
    } catch (e) {
      console.error('Error preseleccionando proyecto desde query param:', e)
    }
  }

  // Migración silenciosa desde localStorage (una sola vez)
  try {
    // Clave heredada de cuando los encuentros se llamaban "sesiones" — no renombrar,
    // ya está persistida en navegadores de docentes reales.
    const LEGACY_KEY = 'dualab_sesiones'
    const local = JSON.parse(localStorage.getItem(LEGACY_KEY) || '[]')
    if (local.length > 0) {
      const payload = local.map(s => ({
        microreto_id:     s.microreto_id     || null,
        microreto_titulo: s.microreto_titulo || null,
        fecha:            s.fecha,
        centro_educativo: s.centro_educativo || null,
        ciclo_formativo:  s.ciclo_formativo  || null,
        curso:            s.curso            || null,
        grupo:            s.grupo            || null,
        num_alumnos:      s.num_alumnos ? Number(s.num_alumnos) : null,
        notas:            s.notas            || null,
      }))
      await api.post('/encuentros/lote', { encuentros: payload })
      localStorage.removeItem(LEGACY_KEY)
      await cargarEncuentros()
    }
  } catch (e) {
    console.error('Error migrando encuentros desde localStorage:', e)
  }

  // Tour prompt desactivado temporalmente — reactivar poniendo showTourPrompt.value = true cuando se necesite.
  // await nextTick()
  // showTourPrompt.value = true
})

onUnmounted(() => {
  tourActivo.value = false
  window.removeEventListener('scroll', onScrollGuia)
})

// ─── Guardar encuentro ─────────────────────────────────────────────────────────
async function guardarEncuentro() {
  if (!formularioValido.value) return
  guardando.value = true
  errorGuardado.value = ''
  try {
    const payload = {
      ...form.value,
      num_alumnos: form.value.num_alumnos !== '' ? Number(form.value.num_alumnos) : null,
    }
    const res = await api.post('/encuentros', payload)
    encuentros.value = [res.data, ...encuentros.value]
    guardadoOk.value = true
    setTimeout(() => { guardadoOk.value = false }, 2500)
  } catch (e) {
    console.error('Error guardando encuentro:', e)
    errorGuardado.value = e.response?.data?.message || 'No se pudo guardar el encuentro. Revisa los datos e inténtalo de nuevo.'
  } finally {
    guardando.value = false
  }
}

// ─── Eliminar encuentro (modal dos fases) ────────────────────────────────────
const modalEliminarVisible = ref(false)
const encuentroAEliminar   = ref(null)
const snackbar = ref({ visible: false, mensaje: '', accion: null })
let   snackTimer = null

function mostrarSnack(mensaje, accion = null) {
  clearTimeout(snackTimer)
  snackbar.value = { visible: true, mensaje, accion }
  snackTimer = setTimeout(() => { snackbar.value.visible = false }, 5000)
}

function abrirModalEliminar(encuentro) {
  encuentroAEliminar.value = encuentro
  modalEliminarVisible.value = true
}

function cerrarModalEliminar() {
  modalEliminarVisible.value = false
  encuentroAEliminar.value = null
}

function onEncuentroEliminado({ id, titulo }) {
  encuentros.value = encuentros.value.filter(s => s.id !== id)
  cerrarEncuentroModal()
  cerrarModalEliminar()
  // La papelera de "Base de datos" es solo superadmin — el resto de roles ya no tiene esa ruta.
  mostrarSnack(
    `"${titulo}" movido a la papelera.`,
    authStore.isSuperAdmin ? { label: 'Ir a la papelera', fn: () => router.push({ name: 'papelera' }) } : null
  )
}

// ─── Filtros y paginación del panel de encuentros ────────────────────────────
const filtroSes        = ref({ fecha: '', titulo: '', curso: '', grupo: '' })
const paginaEncuentros  = ref(1)
const ENCUENTROS_POR_PAGINA = 5

const encuentrosFiltrados = computed(() => {
  let lista = [...encuentros.value]
  if (filtroSes.value.fecha)
    lista = lista.filter(s => s.fecha === filtroSes.value.fecha)
  if (filtroSes.value.titulo.trim()) {
    const q = filtroSes.value.titulo.trim().toLowerCase()
    lista = lista.filter(s => (s.proyecto_titulo || '').toLowerCase().includes(q))
  }
  if (filtroSes.value.curso) lista = lista.filter(s => s.curso === filtroSes.value.curso)
  if (filtroSes.value.grupo) lista = lista.filter(s => s.grupo === filtroSes.value.grupo)
  return lista.sort((a, b) => (a.fecha < b.fecha ? 1 : a.fecha > b.fecha ? -1 : 0))
})

const encuentrosVisibles = computed(() => {
  const start = (paginaEncuentros.value - 1) * ENCUENTROS_POR_PAGINA
  return encuentrosFiltrados.value.slice(start, start + ENCUENTROS_POR_PAGINA)
})

const totalPaginasSes = computed(() =>
  Math.ceil(encuentrosFiltrados.value.length / ENCUENTROS_POR_PAGINA)
)

watch(filtroSes, () => { paginaEncuentros.value = 1 }, { deep: true })

// ─── Tour guía paso a paso ────────────────────────────────────────────────────
const modoGuia       = ref(false)
const pasoGuia       = ref(1)
const showTourPrompt = ref(false)

function activarTourDesdeModal() { showTourPrompt.value = false; modoGuia.value = true; pasoGuia.value = 1 }
function omitirTourDesdeModal()  { showTourPrompt.value = false }

const guiaPasosData = [
  { ref: 'refCampoFecha',    seccion: 'datos', texto: 'Indica la fecha en la que se realizó el encuentro.' },
  { ref: 'refCampoCentro',   seccion: 'datos', texto: 'Indica el centro educativo.' },
  { ref: 'refCampoAlumnado', seccion: 'datos', texto: 'Indica la información del alumnado: curso, grupo y número de alumnos.' },
  { ref: 'refCampoEquipos',  seccion: 'datos', texto: 'Configura los equipos y añade el alumnado asignado a cada uno.' },
  { ref: 'refCampoNotas',    seccion: 'datos', texto: 'Indica cualquier nota relevante sobre el encuentro.' },
  { ref: 'refBtnGuardar',    seccion: 'datos', texto: 'Pulsa guardar encuentro para registrar la información.' },
  { ref: 'refEncuentros',    seccion: 'encuentros', texto: 'Quedará disponible para crear el proyecto y comenzar el taller de ideas, o consultar esta info más adelante.' },
  { ref: 'refBtnGuia',       seccion: null,    texto: 'Pincha aquí para volver a activar la guía cuando quieras.' },
]

const TOTAL_PASOS_GUIA = guiaPasosData.length

// Template refs para el tour
const refCampoFecha    = ref(null)
const refCampoCentro   = ref(null)
const refCampoAlumnado = ref(null)
const refCampoEquipos  = ref(null)
const refCampoNotas    = ref(null)
const refBtnGuardar    = ref(null)
const refEncuentros    = ref(null)
const refBtnGuia       = ref(null)

const tourRefs = { refCampoFecha, refCampoCentro, refCampoAlumnado, refCampoEquipos, refCampoNotas, refBtnGuardar, refEncuentros, refBtnGuia }

const tourTargetActivo = computed(() => {
  if (!modoGuia.value) return null
  return guiaPasosData[pasoGuia.value - 1]?.ref ?? null
})

const seccionActiva = computed(() => {
  if (!modoGuia.value) return null
  return guiaPasosData[pasoGuia.value - 1]?.seccion ?? null
})

const bocadilloPos = ref({ top: 0, left: 0, arrowLeft: 130, dir: 'top' })

function recalcularBocadillo() {
  const paso = guiaPasosData[pasoGuia.value - 1]
  if (!paso) return
  const el = tourRefs[paso.ref]?.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const TOOLTIP_W = 272
  const TOOLTIP_H = 155
  const MARGIN    = 12
  const WIN_W = window.innerWidth
  const WIN_H = window.innerHeight

  const visibleTop    = Math.max(0, rect.top)
  const visibleBottom = Math.min(WIN_H, rect.bottom)
  const centerX       = rect.left + rect.width / 2

  let tooltipLeft = centerX - TOOLTIP_W / 2
  tooltipLeft = Math.max(10, Math.min(tooltipLeft, WIN_W - TOOLTIP_W - 10))
  const arrowLeft = Math.max(18, Math.min(centerX - tooltipLeft, TOOLTIP_W - 18))

  let dir, tooltipTop
  if (WIN_H - visibleBottom > TOOLTIP_H + MARGIN) {
    dir = 'top'
    tooltipTop = visibleBottom + MARGIN
  } else if (visibleTop > TOOLTIP_H + MARGIN) {
    dir = 'bottom'
    tooltipTop = visibleTop - MARGIN - TOOLTIP_H
  } else {
    dir = 'top'
    tooltipTop = WIN_H * 0.6
  }
  tooltipTop = Math.max(10, Math.min(tooltipTop, WIN_H - TOOLTIP_H - 10))

  bocadilloPos.value = { top: tooltipTop, left: tooltipLeft, arrowLeft, dir }
}

function avanzarGuia() {
  if (pasoGuia.value < TOTAL_PASOS_GUIA) {
    pasoGuia.value++
  } else {
    modoGuia.value = false
  }
}

function saltarGuia() {
  modoGuia.value = false
}

function scrollYRecalcular() {
  const paso = guiaPasosData[pasoGuia.value - 1]
  const el = tourRefs[paso?.ref]?.value
  if (el) el.scrollIntoView({ behavior: 'instant', block: 'nearest' })
  requestAnimationFrame(() => requestAnimationFrame(recalcularBocadillo))
}

function onScrollGuia() {
  if (modoGuia.value) requestAnimationFrame(recalcularBocadillo)
}

watch(pasoGuia, async () => {
  await nextTick()
  scrollYRecalcular()
})

watch(modoGuia, async (v) => {
  tourActivo.value = v
  if (v) {
    window.addEventListener('scroll', onScrollGuia, { passive: true })
    await nextTick()
    scrollYRecalcular()
  } else {
    window.removeEventListener('scroll', onScrollGuia)
  }
})

onBeforeRouteUpdate(() => {
  modoGuia.value = false
  pasoGuia.value = 1
})

// ─── Modal ficha de microreto ─────────────────────────────────────────────────
const microretoModalId = ref(null)

function abrirMicroretoModal(id) {
  microretoModalId.value = id
}

function cerrarMicroretoModal() {
  microretoModalId.value = null
}

// ─── Modal ficha de proyecto ──────────────────────────────────────────────────
const proyectoModalUuid = ref(null)

function abrirProyectoModal(uuid) {
  proyectoModalUuid.value = uuid
}

function cerrarProyectoModal() {
  proyectoModalUuid.value = null
}

// ─── Modal de detalle de encuentro ────────────────────────────────────────────
const encuentroAbierto = ref(null)
const { cargandoRaCe, modulosExpandidos, raCeBlocksEncuentro, toggleModulo, cargarRaCe } = useRaCeEncuentro()

async function verEncuentro(s) {
  encuentroAbierto.value = s
  await cargarRaCe(s.microproyecto_uuid)
}

function cerrarEncuentroModal() {
  encuentroAbierto.value = null
}

function alumnadosDeEquipoEn(encuentro, n) {
  const equipo = (encuentro?.equipos || []).find(e => e.numero_equipo === n)
  if (equipo) {
    return equipo.miembros.map(m => m.alias ? `${m.nombre} (${m.alias})` : m.nombre)
  }
  // Encuentros sin equipos cargados todavía (o antiguos): snapshot plano, sin alias.
  return (encuentro?.alumnados || [])
    .filter(a => a.equipo_num === n)
    .map(a => a.nombre)
}

function alumnadosDelEquipoAbierto(n) {
  return alumnadosDeEquipoEn(encuentroAbierto.value, n)
}

// El flujo es secuencial: 1) proyecto asociado → 2) fecha de trabajo →
// 3) equipos repartidos → 4) guardar. Cada paso exige el anterior.
const proyectoAsociado  = computed(() => !!form.value.microproyecto_id)
const fechaEstablecida  = computed(() => proyectoAsociado.value && form.value.fecha !== '')
const equiposRepartidos = computed(() =>
  fechaEstablecida.value &&
  !!form.value.curso &&
  !!form.value.grupo &&
  (form.value.num_equipos || 0) >= 1 &&
  form.value.alumnados.length > 0 &&
  alumnadosSinEquipo.value.length === 0
)

const formularioValido = computed(() => equiposRepartidos.value)

const requisitosFaltantes = computed(() => {
  const faltantes = []
  if (!proyectoAsociado.value) {
    faltantes.push('Asocia un proyecto al encuentro.')
    return faltantes
  }
  if (!form.value.fecha) faltantes.push('Establece la fecha de trabajo.')
  if (!form.value.curso) faltantes.push('Indica el curso del encuentro.')
  if (!form.value.grupo) faltantes.push('Indica el grupo del encuentro.')
  if ((form.value.num_equipos || 0) < 1) faltantes.push('Indica el número de equipos.')
  if (form.value.alumnados.length === 0) faltantes.push('Reparte el alumnado en los equipos.')
  else if (alumnadosSinEquipo.value.length > 0) faltantes.push('Hay alumnado sin equipo asignado.')
  return faltantes
})

function formatFecha(isoDate) {
  if (!isoDate) return ''
  const d = new Date(isoDate + 'T12:00:00')
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' })
}
</script>

<template>
  <div class="min-h-screen font-sans text-[#1F2937] pt-12">

    <!-- ══════════ TOUR BOCADILLO - OVERLAY + TOOLTIP ════════════════════════ -->
    <Transition name="sp-fade">
      <div v-if="modoGuia" class="fixed inset-0 z-[9990] pointer-events-none">
        <!-- Backdrop bloqueante transparente — bloquea interacción sin oscurecer el elemento activo -->
        <div class="absolute inset-0 pointer-events-auto" />

        <!-- Bocadillo flotante posicionado sobre el elemento activo -->
        <div class="absolute pointer-events-auto"
             :style="{ top: bocadilloPos.top + 'px', left: bocadilloPos.left + 'px', width: '272px', zIndex: 9992 }">

          <!-- Flecha apuntando arriba (bocadillo debajo del elemento) -->
          <div v-if="bocadilloPos.dir === 'top'"
               class="absolute -top-[10px] w-0 h-0"
               :style="{
                 left: bocadilloPos.arrowLeft + 'px',
                 transform: 'translateX(-50%)',
                 borderLeft: '9px solid transparent',
                 borderRight: '9px solid transparent',
                 borderBottom: '10px solid #1a2332'
               }" />

          <!-- Contenido -->
          <div class="bg-[#1a2332] border border-white/15 rounded-2xl shadow-2xl p-4 text-white">
            <!-- Progreso -->
            <div class="flex items-center justify-between mb-2.5">
              <div class="flex gap-1 items-center">
                <span v-for="i in TOTAL_PASOS_GUIA" :key="i"
                      class="h-[3px] rounded-full transition-all duration-300"
                      :class="i <= pasoGuia ? 'bg-[#00A859] w-5' : 'bg-white/20 w-3'" />
              </div>
              <span class="text-[9px] font-bold text-white/40">{{ pasoGuia }}/{{ TOTAL_PASOS_GUIA }}</span>
            </div>

            <p class="text-[11px] text-white/85 leading-relaxed mb-3">{{ guiaPasosData[pasoGuia - 1].texto }}</p>

            <div class="flex items-center gap-2">
              <button @click="avanzarGuia"
                      class="flex-1 py-1.5 rounded-xl bg-[#00A859] text-white
                             text-[9px] font-black uppercase tracking-widest
                             hover:bg-[#00A859]/90 transition-all">
                {{ pasoGuia < TOTAL_PASOS_GUIA ? 'Siguiente →' : 'Finalizar' }}
              </button>
              <button @click="saltarGuia"
                      class="px-2.5 py-1.5 rounded-xl bg-white/5 border border-white/10
                             text-white/40 text-[9px] font-black uppercase tracking-widest
                             hover:text-white/60 transition-all">
                Saltar
              </button>
            </div>
          </div>

          <!-- Flecha apuntando abajo (bocadillo encima del elemento) -->
          <div v-if="bocadilloPos.dir === 'bottom'"
               class="absolute -bottom-[10px] w-0 h-0"
               :style="{
                 left: bocadilloPos.arrowLeft + 'px',
                 transform: 'translateX(-50%)',
                 borderLeft: '9px solid transparent',
                 borderRight: '9px solid transparent',
                 borderTop: '10px solid #1a2332'
               }" />
        </div>
      </div>
    </Transition>

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                bg-[#00A859] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <div class="relative z-10 max-w-5xl mx-auto px-4 py-8 md:px-8 md:py-12">

      <!-- ─── Cabecera ─────────────────────────────────────────────────────── -->
      <header class="mb-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-5">
          <div>
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full
                        bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="w-2 h-2 rounded-full bg-[#00A859]" />
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Dashboard docente</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black tracking-tight text-[#121212]">
              Encuentros <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">DuaLab</span>
            </h1>
            <p class="text-gray-500 text-sm mt-1">Registra y consulta tus encuentros de trabajo con retos.</p>
          </div>

          <!-- Stats chips -->
          <div class="flex flex-wrap gap-3 items-center">
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-2xl border border-gray-100 shadow-sm">
              <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                         M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
              <span class="font-black text-xl text-[#1F2937]">{{ encuentros.length }}</span>
              <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">encuentros</span>
            </div>

            <!-- Botón activar guía -->
            <button ref="refBtnGuia"
                    @click="modoGuia = true; pasoGuia = 1"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-50 rounded-2xl border border-blue-100
                           shadow-sm text-blue-500 text-xs font-black uppercase tracking-wider
                           hover:bg-blue-100 hover:border-blue-200 transition-all">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Activar Guía
            </button>
          </div>
        </div>
      </header>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- ─── COLUMNA IZQUIERDA: Formulario ───────────────────────────── -->
        <div class="lg:col-span-3">
          <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm overflow-hidden">

          <!-- Header sección izquierda — banner de creación -->
          <Transition name="creation-hero" appear>
            <div class="relative overflow-hidden border-b border-[#00A859]/20
                        bg-gradient-to-br from-[#00A859]/10 via-[#00A859]/5 to-[#99CC33]/8 px-6 py-5">
              <!-- Fondo decorativo -->
              <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full
                          bg-[#00A859]/10 blur-2xl pointer-events-none" />
              <div class="relative flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-[#00A859] flex items-center justify-center shadow-sm flex-shrink-0">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                             M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                  </svg>
                </div>
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full bg-[#00A859]" />
                    <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Nuevo encuentro</span>
                  </div>
                  <h2 class="text-xl font-black text-[#1F2937] tracking-tight leading-tight">Creación de encuentro</h2>
                  <p class="text-xs text-gray-500 mt-0.5 font-medium">Selecciona un reto y registra los datos del grupo</p>
                </div>
              </div>
            </div>
          </Transition>

          <!-- ══ PROPUESTA-PROYECTO ASOCIADO ═══════════════════════════════════ -->
          <div class="overflow-hidden border-b border-gray-100">
            <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
              <p class="text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">
                Propuesta-Proyecto asociado
              </p>
              <button v-if="!mostrarBuscadorProy && !proyectoSeleccionado"
                      @click="abrirBuscadorProy"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                             bg-[#99CC33]/10 border border-[#99CC33]/20 text-[#5a7a00]
                             text-[10px] font-black uppercase tracking-widest
                             hover:bg-[#99CC33]/20 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                Buscar propuesta o proyecto
              </button>
            </div>

            <div class="px-6 py-5">

              <!-- Proyecto seleccionado -->
              <div v-if="proyectoSeleccionado && !mostrarBuscadorProy"
                   class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full bg-[#99CC33] flex-shrink-0" />
                    <p class="text-xs font-black uppercase tracking-widest text-[#5a7a00]">
                      Seleccionado
                    </p>
                  </div>
                  <h3 class="font-black text-[#1F2937] text-base leading-snug">
                    {{ proyectoSeleccionado.titulo }}
                  </h3>
                  <div class="flex flex-wrap gap-1.5 mt-2">
                    <span :class="estadoProyectoBadge(proyectoSeleccionado).cls"
                          class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest">
                      {{ estadoProyectoBadge(proyectoSeleccionado).label }}
                    </span>
                    <span v-if="proyectoSeleccionado.empresa_nombre" class="tag tag-gray">
                      {{ proyectoSeleccionado.empresa_nombre }}
                    </span>
                    <span v-if="proyectoSeleccionado.ciclo_nombre"
                          class="tag tag-gray truncate max-w-[160px]">
                      {{ proyectoSeleccionado.ciclo_nombre }}
                    </span>
                    <span v-if="proyectoSeleccionado.curso" class="tag tag-lime">
                      {{ proyectoSeleccionado.curso }}
                    </span>
                  </div>
                </div>
                <div class="flex flex-col gap-1.5 flex-shrink-0">
                  <button @click="abrirProyectoModal(proyectoSeleccionado.uuid)"
                          class="px-3 py-1.5 rounded-xl bg-[#99CC33]/10 border border-[#99CC33]/20
                                 text-[10px] font-black uppercase tracking-widest text-[#5a7a00]
                                 hover:bg-[#99CC33]/20 transition-all">
                    Ver →
                  </button>
                  <button @click="limpiarProyecto"
                          class="px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200
                                 text-[10px] font-black uppercase tracking-widest text-gray-400
                                 hover:border-[#99CC33] hover:text-[#5a7a00] transition-all">
                    Cambiar
                  </button>
                </div>
              </div>

              <!-- Buscador -->
              <div v-else-if="mostrarBuscadorProy" class="space-y-4">

                <!-- Filtros -->
                <div class="flex flex-wrap gap-2">
                  <!-- Búsqueda texto -->
                  <div class="relative flex-1 min-w-40">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-300 pointer-events-none"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input v-model="busquedaProy" type="text"
                           placeholder="Buscar por título o empresa..."
                           class="field-input pl-9 !text-xs" />
                  </div>

                  <!-- Filtro estado -->
                  <select v-model="filtroProyEstado" class="field-input !w-auto !text-xs cursor-pointer">
                    <option value="">Todos (propuesta o validado)</option>
                    <option value="propuesta">Pendiente validar</option>
                    <option value="validado">Validado</option>
                  </select>
                </div>

                <!-- Filtros Ciclo + Curso -->
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="field-label">Ciclo formativo</label>
                    <select v-model="filtroProyCiclo" class="field-input">
                      <option value="">Todos los ciclos</option>
                      <option v-for="c in ciclosDisponiblesProy" :key="c" :value="c">{{ c }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="field-label">Curso</label>
                    <div class="flex gap-2 mt-1">
                      <button v-for="op in [{ val: '', label: 'Todos' }, { val: '1º', label: '1º' }, { val: '2º', label: '2º' }]"
                              :key="op.val"
                              @click="filtroProyCurso = op.val"
                              class="flex-1 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest
                                     border transition-all"
                              :class="filtroProyCurso === op.val
                                ? 'bg-[#99CC33] border-[#99CC33] text-white'
                                : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-[#99CC33]/40 hover:text-[#5a7a00]'">
                        {{ op.label }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Contador + limpiar + cancelar -->
                <div class="flex items-center justify-between">
                  <p class="text-[10px] text-gray-400 font-medium">
                    <span v-if="cargandoProyectos">Cargando proyectos...</span>
                    <span v-else>
                      {{ proyectosFiltrados.length }}
                      {{ proyectosFiltrados.length === 1 ? 'proyecto' : 'proyectos' }}
                    </span>
                  </p>
                  <div class="flex gap-3">
                    <button v-if="filtroProyCurso || filtroProyCiclo || filtroProyEstado || busquedaProy"
                            @click="filtroProyCurso = ''; filtroProyCiclo = ''; filtroProyEstado = ''; busquedaProy = ''"
                            class="text-[10px] font-black uppercase tracking-widest text-gray-400
                                   hover:text-red-400 transition-colors">
                      Limpiar
                    </button>
                    <button @click="cerrarBuscadorProy"
                            class="text-[10px] font-black uppercase tracking-widest text-gray-400
                                   hover:text-gray-600 transition-colors">
                      Cancelar
                    </button>
                  </div>
                </div>

                <!-- Spinner -->
                <div v-if="cargandoProyectos" class="flex justify-center py-8">
                  <svg class="animate-spin w-6 h-6 text-[#99CC33]" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                  </svg>
                </div>

                <!-- Grid de resultados -->
                <div v-else-if="proyectosFiltrados.length"
                     class="space-y-1.5 max-h-72 overflow-y-auto pr-1 -mr-1">
                  <div v-for="p in proyectosFiltrados" :key="p.uuid"
                       class="flex items-stretch rounded-xl border border-gray-100 bg-gray-50
                              hover:border-[#99CC33]/30 hover:bg-[#99CC33]/5 transition-all group">
                    <!-- Zona seleccionar (clic principal) -->
                    <button @click="seleccionarProyecto(p)"
                            class="flex-1 text-left px-4 py-3 min-w-0">
                      <p class="text-sm font-black text-[#1F2937] leading-snug
                                group-hover:text-[#5a7a00] transition-colors line-clamp-2">
                        {{ p.titulo }}
                      </p>
                      <div class="flex flex-wrap gap-1.5 mt-1.5">
                        <span :class="estadoProyectoBadge(p).cls"
                              class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest">
                          {{ estadoProyectoBadge(p).label }}
                        </span>
                        <span v-if="p.empresa_nombre" class="tag tag-gray">{{ p.empresa_nombre }}</span>
                        <span v-if="p.ciclo_nombre"   class="tag tag-gray truncate max-w-[140px]">{{ p.ciclo_nombre }}</span>
                        <span v-if="p.curso"          class="tag tag-lime">{{ p.curso }}</span>
                      </div>
                    </button>
                    <!-- Botón Ver (abre modal) -->
                    <button @click.stop="abrirProyectoModal(p.uuid)"
                            title="Ver detalle"
                            class="shrink-0 flex items-center justify-center px-3 border-l border-gray-100
                                   text-gray-300 hover:text-[#5a7a00] hover:bg-[#99CC33]/10
                                   transition-all rounded-r-xl">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Sin resultados -->
                <div v-else class="text-center py-8 text-gray-400">
                  <svg class="w-8 h-8 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                  </svg>
                  <p class="text-xs font-medium">Sin proyectos para esos filtros</p>
                </div>

              </div>

              <!-- Estado inicial — sin selector abierto ni proyecto -->
              <div v-else>
                <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                  <svg class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none"
                       stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <p class="text-xs text-gray-500 font-medium leading-relaxed">
                    Asocia un proyecto existente a este encuentro para tenerlo
                    vinculado desde el registro. Los proyectos se crean en
                    <button @click="router.push({ name: 'startup-day-crear' })"
                            class="underline font-black hover:text-[#5a7a00] transition-colors">
                      Generar Proyecto
                    </button>.
                  </p>
                </div>
                <button @click="abrirBuscadorProy"
                        class="mt-3 w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                               border border-[#99CC33]/30 bg-[#99CC33]/5 text-[#5a7a00]
                               text-[10px] font-black uppercase tracking-widest
                               hover:bg-[#99CC33]/10 transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                  </svg>
                  Buscar propuesta o proyecto
                </button>
              </div>

            </div>
          </div>

          <!-- ══ DATOS DEL ENCUENTRO ══════════════════════════════════════════ -->
          <div :class="{ 'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'datos' }">
            <div class="px-6 py-4 border-b border-gray-50">
              <p class="text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">
                Datos del encuentro
              </p>
            </div>
            <div class="px-6 py-5 space-y-4">

              <div ref="refCampoFecha" :class="{ 'tour-active': tourTargetActivo === 'refCampoFecha' }"
                   class="rounded-xl transition-all">
                <label class="field-label">Fecha de trabajo <span class="text-red-400">*</span></label>
                <input v-model="form.fecha" type="date" class="field-input"
                       :disabled="!proyectoAsociado" />
                <p v-if="!proyectoAsociado" class="text-xs text-amber-500 font-medium mt-1.5">
                  Asocia primero un proyecto para poder establecer la fecha de trabajo.
                </p>
                <p v-else-if="fechaFinEstimada" class="text-xs text-gray-500 mt-1.5">
                  Fecha fin estimada: <span class="font-bold text-[#1F2937]">{{ fechaFinEstimada }}</span>
                  ({{ totalClasesProyectoSeleccionado }} clase(s) del proyecto). Podrás ajustarla luego en el detalle del encuentro.
                </p>
              </div>

              <div ref="refCampoCentro" :class="{ 'tour-active': tourTargetActivo === 'refCampoCentro' }"
                   class="rounded-xl transition-all">
                <label class="field-label">Centro educativo</label>
                <template v-if="authStore.userCentroNombre">
                  <div class="field-input bg-gray-50 text-gray-500 cursor-default select-none">
                    {{ authStore.userCentroNombre }}
                  </div>
                </template>
                <template v-else>
                  <input v-model="form.centro_educativo" type="text" list="centros-datalist"
                         placeholder="Ej. IES Aguas Nuevas" class="field-input" />
                  <datalist id="centros-datalist">
                    <option v-for="c in centrosParaAutocompletar" :key="c" :value="c" />
                  </datalist>
                </template>
              </div>

              <div>
                <label class="field-label">Ciclo formativo</label>
                <input v-model="form.ciclo_formativo" type="text" list="ciclos-datalist"
                       placeholder="Ej. CFGM Sistemas Microinformáticos" class="field-input" />
                <datalist id="ciclos-datalist">
                  <option v-for="c in ciclosParaAutocompletar" :key="c" :value="c" />
                </datalist>
              </div>

              <div ref="refCampoAlumnado" class="space-y-4 rounded-xl transition-all"
                   :class="{ 'tour-active': tourTargetActivo === 'refCampoAlumnado' }">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="field-label">Curso <span class="text-red-400">*</span></label>
                    <div class="flex gap-2 mt-1">
                      <button v-for="c in ['1º', '2º']" :key="c"
                              @click="form.curso = c"
                              class="flex-1 py-2 rounded-xl text-xs font-black uppercase tracking-widest
                                     border transition-all"
                              :class="form.curso === c
                                ? 'bg-[#00A859] border-[#00A859] text-white shadow-sm'
                                : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-[#00A859]/40 hover:text-[#00A859]'">
                        {{ c }}
                      </button>
                    </div>
                  </div>
                  <div>
                    <label class="field-label">Grupo <span class="text-red-400">*</span></label>
                    <div class="flex gap-1.5 mt-1 flex-wrap">
                      <button v-for="g in ['A', 'B', 'C', 'D']" :key="g"
                              @click="form.grupo = g"
                              class="flex-1 min-w-[2rem] py-2 rounded-xl text-xs font-black uppercase
                                     border transition-all"
                              :class="form.grupo === g
                                ? 'bg-[#99CC33] border-[#99CC33] text-white shadow-sm'
                                : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-[#99CC33]/40 hover:text-[#99CC33]'">
                        {{ g }}
                      </button>
                    </div>
                  </div>
                </div>
                <div>
                  <label class="field-label">Número de alumnos</label>
                  <input v-model="form.num_alumnos" type="number" min="1" max="99"
                         placeholder="Ej. 24" class="field-input w-32" />
                </div>

                <!-- Número de equipos -->
                <div>
                  <label class="field-label">Número de equipos <span class="text-red-400">*</span></label>
                  <div class="flex items-center gap-2 mt-1">
                    <button @click="form.num_equipos = Math.max(1, (form.num_equipos || 3) - 1)"
                            :disabled="!fechaEstablecida"
                            class="w-8 h-8 rounded-xl bg-gray-100 border border-gray-200
                                   text-gray-600 font-black text-sm hover:bg-gray-200 transition-all
                                   disabled:opacity-40 disabled:cursor-not-allowed">−</button>
                    <span class="w-8 text-center text-base font-black text-[#1F2937]">{{ form.num_equipos || 3 }}</span>
                    <button @click="form.num_equipos = Math.min(20, (form.num_equipos || 3) + 1)"
                            :disabled="!fechaEstablecida"
                            class="w-8 h-8 rounded-xl bg-gray-100 border border-gray-200
                                   text-gray-600 font-black text-sm hover:bg-gray-200 transition-all
                                   disabled:opacity-40 disabled:cursor-not-allowed">+</button>
                    <span class="text-[10px] text-gray-400 font-medium ml-1">equipos · se crearán al generar el código</span>
                  </div>
                </div>

                <p v-if="!fechaEstablecida" class="text-xs text-amber-500 font-medium">
                  Establece la fecha de trabajo para repartir el alumnado en equipos.
                </p>

                <!-- Alumnado por equipos — siempre visible -->
                <div ref="refCampoEquipos" class="space-y-3 rounded-xl transition-all"
                     :class="{ 'tour-active': tourTargetActivo === 'refCampoEquipos' }">

                  <label class="field-label">
                    Alumnado por equipos <span class="text-red-400">*</span>
                    <span v-if="limiteAlumnados" class="ml-2 text-xs font-semibold"
                          :class="limiteAlcanzado ? 'text-red-400' : 'text-gray-400'">
                      {{ form.alumnados.length }}/{{ limiteAlumnados }}
                    </span>
                  </label>

                  <div class="px-3 py-2 bg-emerald-50 rounded-xl border border-emerald-100
                              text-[11px] text-emerald-700 flex items-start gap-1.5">
                    <span class="shrink-0">🔒</span>
                    <span>Creamos un alias automático para cada alumno/a (protección de datos) —
                    el nombre real que escribas aquí no se muestra fuera del equipo ni del panel docente.</span>
                  </div>

                  <!-- Formulario añadir alumno/a -->
                  <div class="flex flex-wrap gap-2">
                    <input v-model="nuevoAlumnadoNombre" type="text" placeholder="Nombre del alumno/a"
                           class="field-input flex-1 min-w-32 !text-sm"
                           :disabled="!fechaEstablecida"
                           @keyup.enter="addAlumnadoEncuentro" />
                    <select v-model="nuevoAlumnadoEquipo"
                            class="field-input !w-auto pr-8 cursor-pointer !text-sm"
                            :disabled="!fechaEstablecida">
                      <option v-for="n in (form.num_equipos || 3)" :key="n" :value="n">
                        Equipo {{ n }}
                      </option>
                    </select>
                    <button @click="addAlumnadoEncuentro"
                            :disabled="limiteAlcanzado || !fechaEstablecida"
                            class="shrink-0 px-3 py-2 bg-[#00A859] text-white rounded-xl
                                   text-xs font-black hover:bg-[#00A859]/90 transition-all
                                   disabled:opacity-40 disabled:cursor-not-allowed">
                      + Añadir
                    </button>
                  </div>

                  <!-- Grid de equipos -->
                  <div class="grid grid-cols-2 gap-2">
                    <div v-for="n in (form.num_equipos || 3)" :key="n"
                         class="rounded-xl border p-3 transition-all"
                         :class="Number(nuevoAlumnadoEquipo) === n
                           ? 'border-[#00A859] bg-[#00A859]/5 ring-2 ring-[#00A859]/30'
                           : 'border-gray-100 bg-gray-50/50'">
                      <div class="flex items-center gap-1.5 mb-2">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center
                                     text-[9px] font-black flex-shrink-0 transition-colors"
                              :class="Number(nuevoAlumnadoEquipo) === n
                                ? 'bg-[#00A859] text-white'
                                : 'bg-[#00A859]/10 text-[#00A859]'">{{ n }}</span>
                        <p class="text-[10px] font-black uppercase tracking-widest flex-1 truncate"
                           :class="Number(nuevoAlumnadoEquipo) === n ? 'text-[#00A859]' : 'text-gray-500'">
                          Equipo {{ n }}
                        </p>
                        <span v-if="Number(nuevoAlumnadoEquipo) === n"
                              class="text-[8px] font-black uppercase tracking-widest text-[#00A859]
                                     bg-[#00A859]/10 rounded-full px-1.5 py-0.5 flex-shrink-0">
                          Aquí
                        </span>
                        <span class="text-[9px] text-gray-400 flex-shrink-0">
                          {{ alumnadosDeEquipo(n).length }}
                        </span>
                      </div>
                      <div v-if="alumnadosDeEquipo(n).length" class="space-y-1">
                        <div v-for="a in alumnadosDeEquipo(n)" :key="a._i"
                             class="flex items-center gap-1 text-xs">
                          <span class="flex-1 truncate font-medium text-[#1F2937]">{{ a.nombre }}</span>
                          <button @click="removeAlumnadoEncuentro(a._i)"
                                  class="text-gray-300 hover:text-red-400 font-black transition-colors
                                         text-sm leading-none flex-shrink-0">×</button>
                        </div>
                      </div>
                      <p v-else class="text-[10px] text-gray-300 italic">Sin alumnos</p>
                    </div>
                  </div>

                  <!-- Sin equipo asignado -->
                  <div v-if="alumnadosSinEquipo.length"
                       class="rounded-xl border border-amber-100 bg-amber-50/50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-500 mb-2">
                      Sin equipo asignado
                    </p>
                    <div class="space-y-1">
                      <div v-for="a in alumnadosSinEquipo" :key="a._i"
                           class="flex items-center gap-1 text-xs">
                        <span class="flex-1 truncate font-medium text-amber-700">{{ a.nombre }}</span>
                        <button @click="removeAlumnadoEncuentro(a._i)"
                                class="text-amber-300 hover:text-red-400 font-black transition-colors
                                       text-sm leading-none flex-shrink-0">×</button>
                      </div>
                    </div>
                  </div>

                </div>
              </div>

              <div ref="refCampoNotas" class="rounded-xl transition-all"
                   :class="{ 'tour-active': tourTargetActivo === 'refCampoNotas' }">
                <label class="field-label">Notas adicionales</label>
                <textarea v-model="form.notas" rows="3"
                          placeholder="Observaciones, adaptaciones realizadas, valoración..."
                          class="field-input resize-none" />
              </div>

              <!-- Campo cuenta docente — asignado automáticamente al guardar -->
              <div class="flex items-center gap-3 px-4 py-3 rounded-xl
                          bg-[#00A859]/5 border border-[#00A859]/15">
                <div class="w-8 h-8 rounded-xl bg-[#00A859]/10 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-0.5">
                    Cuenta docente asociada
                  </p>
                  <p class="text-sm font-bold text-[#1F2937] truncate">{{ authStore.userName }}</p>
                </div>
                <span class="flex-shrink-0 text-[9px] font-black uppercase tracking-widest
                             px-2 py-0.5 rounded-full bg-[#00A859]/10 text-[#00A859]">
                  Auto
                </span>
              </div>

            </div>

            <div v-if="requisitosFaltantes.length || errorGuardado"
                 class="px-6 py-3 bg-amber-50 border-t border-amber-100">
              <ul class="space-y-0.5">
                <li v-for="(msg, i) in requisitosFaltantes" :key="i"
                    class="text-xs font-semibold text-amber-600 flex items-center gap-1.5">
                  <span class="w-1 h-1 rounded-full bg-amber-400 flex-shrink-0" /> {{ msg }}
                </li>
              </ul>
              <p v-if="errorGuardado" class="text-xs font-semibold text-red-500 mt-1">
                {{ errorGuardado }}
              </p>
            </div>

            <div ref="refBtnGuardar"
                 class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-4 transition-all"
                 :class="{ 'tour-active': tourTargetActivo === 'refBtnGuardar' }">
              <p class="text-[10px] text-gray-400 font-medium">
                El encuentro se guarda en la base de datos.
              </p>
              <button @click="guardarEncuentro"
                      :disabled="!formularioValido || guardando"
                      class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl
                             text-xs font-black uppercase tracking-widest
                             transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                      :class="guardadoOk
                        ? 'bg-[#99CC33] text-white'
                        : 'bg-[#00A859] hover:bg-[#00A859]/90 text-white shadow-sm'">
                <svg v-if="guardadoOk" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else-if="guardando" class="w-4 h-4 animate-spin" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3
                           m-4 0V3m0 4l-2-2m2 2l2-2"/>
                </svg>
                {{ guardadoOk ? '¡Guardado!' : guardando ? 'Guardando...' : 'Guardar encuentro' }}
              </button>
            </div>
          </div>

          </div><!-- /card creación -->
        </div>

        <!-- ─── COLUMNA DERECHA: Historial ──────────────────────────────── -->
        <div class="lg:col-span-2 transition-all"
             :class="{ 'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'encuentros' }">

          <div ref="refEncuentros"
               class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm overflow-hidden sticky top-[4.5rem]"
               :class="{ 'tour-active': tourTargetActivo === 'refEncuentros' }">

            <!-- Cabecera — banner azul -->
            <div class="relative overflow-hidden border-b border-blue-100
                        bg-gradient-to-br from-blue-50 via-blue-50/60 to-indigo-50/40 px-5 py-4">
              <!-- Fondo decorativo -->
              <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full
                          bg-blue-100 blur-2xl pointer-events-none" />
              <div class="relative flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-500 flex items-center justify-center shadow-sm flex-shrink-0">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                             M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400" />
                    <span class="text-[10px] font-black uppercase tracking-widest text-blue-500">Historial</span>
                  </div>
                  <h2 class="text-base font-black text-[#1F2937] tracking-tight leading-tight truncate">
                    Resumen de encuentros
                  </h2>
                </div>
                <span class="flex-shrink-0 text-[10px] font-black bg-blue-100 text-blue-600
                             px-2.5 py-1 rounded-full uppercase tracking-widest">
                  {{ encuentros.length }}
                </span>
              </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="px-5 py-3 border-b border-gray-100">
              <!-- Botón Ver encuentros — prominente -->
              <button @click="router.push('/encuentros')"
                      class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                             bg-[#00A859] text-white
                             text-[10px] font-black uppercase tracking-widest
                             hover:bg-[#00A859]/90 transition-all shadow-sm mb-2">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Ver todos los encuentros
              </button>
              <!-- name 'mis-equipos' (antes 'mis-grupos') — ver router/index.js -->
              <button @click="router.push({ name: 'mis-equipos' })"
                      class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                             bg-violet-50 border border-violet-200 text-violet-700
                             text-[10px] font-black uppercase tracking-widest
                             hover:bg-violet-100 transition-all mb-2">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 3c0-1.1-.9-2-2-2h-8c-1.1 0-2 .9-2 2v1h12v-1z"/>
                </svg>
                Mis grupos — seguimiento equipos
              </button>
              <button @click="router.push({ name: 'startup-day-crear' })"
                      class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl
                             bg-[#99CC33]/10 border border-[#99CC33]/20 text-[#5a7a00]
                             text-[9px] font-black uppercase tracking-widest
                             hover:bg-[#99CC33]/20 transition-all">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nueva propuesta
              </button>
            </div>

            <!-- Filtros compactos -->
            <div v-if="encuentros.length > 0" class="px-4 py-3 border-b border-gray-50 space-y-2">
              <!-- Búsqueda por título -->
              <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-gray-300 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <input v-model="filtroSes.titulo" type="text" placeholder="Buscar encuentro..."
                       class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-7 pr-3 py-1.5
                              text-xs font-medium text-gray-700 placeholder-gray-300
                              focus:outline-none focus:border-[#00A859]/50 focus:ring-1 focus:ring-[#00A859]/20" />
              </div>
              <!-- Fecha -->
              <input v-model="filtroSes.fecha" type="date"
                     class="w-full bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5
                            text-xs font-medium text-gray-600
                            focus:outline-none focus:border-[#00A859]/50 focus:ring-1 focus:ring-[#00A859]/20" />
              <!-- Curso + Grupo -->
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <p class="text-[8px] font-black uppercase tracking-widest text-gray-400 mb-1">Curso</p>
                  <div class="flex gap-1">
                    <button v-for="c in ['', '1º', '2º']" :key="'fc'+c"
                            @click="filtroSes.curso = c"
                            class="flex-1 py-1 rounded-lg text-[8px] font-black uppercase border transition-all"
                            :class="filtroSes.curso === c
                              ? 'bg-[#00A859] border-[#00A859] text-white'
                              : 'bg-gray-50 border-gray-200 text-gray-400 hover:border-[#00A859]/40'">
                      {{ c === '' ? '·' : c }}
                    </button>
                  </div>
                </div>
                <div>
                  <p class="text-[8px] font-black uppercase tracking-widest text-gray-400 mb-1">Grupo</p>
                  <div class="flex gap-0.5">
                    <button v-for="g in ['', 'A', 'B', 'C', 'D']" :key="'fg'+g"
                            @click="filtroSes.grupo = g"
                            class="flex-1 py-1 rounded-md text-[7px] font-black uppercase border transition-all"
                            :class="filtroSes.grupo === g
                              ? 'bg-[#99CC33] border-[#99CC33] text-white'
                              : 'bg-gray-50 border-gray-200 text-gray-400 hover:border-[#99CC33]/40'">
                      {{ g === '' ? '·' : g }}
                    </button>
                  </div>
                </div>
              </div>
              <!-- Limpiar filtros -->
              <div v-if="filtroSes.titulo || filtroSes.fecha || filtroSes.curso || filtroSes.grupo"
                   class="flex justify-end">
                <button @click="filtroSes = { fecha: '', titulo: '', curso: '', grupo: '' }"
                        class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-red-400 transition-colors">
                  Limpiar filtros
                </button>
              </div>
            </div>

            <!-- Cargando encuentros -->
            <div v-if="cargandoEncuentros" class="px-5 py-10 flex justify-center">
              <svg class="animate-spin w-5 h-5 text-[#00A859]" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
            </div>

            <!-- Estado vacío -->
            <div v-else-if="encuentros.length === 0" class="px-5 py-10 text-center">
              <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-100
                          flex items-center justify-center mx-auto mb-3">
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                </svg>
              </div>
              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                Aún no hay encuentros.<br>¡Registra el primero!
              </p>
            </div>

            <!-- Sin resultados tras filtrar -->
            <div v-else-if="!cargandoEncuentros && encuentrosFiltrados.length === 0" class="px-5 py-8 text-center">
              <p class="text-xs text-gray-400 font-medium">Sin resultados para esos filtros.</p>
            </div>

            <!-- Lista de encuentros (miniaturas) -->
            <ul v-else-if="!cargandoEncuentros" class="divide-y divide-gray-50">
              <li v-for="s in encuentrosVisibles" :key="s.id"
                  class="px-4 py-3 hover:bg-gray-50/60 transition-colors group cursor-pointer"
                  @click="verEncuentro(s)">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-[#1F2937] leading-snug truncate
                              group-hover:text-[#00A859] transition-colors">
                      {{ s.proyecto_titulo || '(sin título)' }}
                    </p>
                    <p class="text-[10px] text-[#00A859] font-bold mt-0.5">
                      {{ formatFecha(s.fecha) }}
                    </p>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <span v-if="s.curso"       class="tag tag-green">{{ s.curso }}</span>
                      <span v-if="s.grupo"       class="tag tag-lime">Gr. {{ s.grupo }}</span>
                      <span v-if="s.num_alumnos" class="tag tag-gray">{{ s.num_alumnos }} al.</span>
                      <span v-if="s.num_equipos" class="tag tag-gray">{{ s.num_equipos }} eq.</span>
                    </div>
                    <div v-if="s.num_equipos" class="space-y-0.5 mt-1">
                      <p v-for="n in s.num_equipos" :key="n"
                         class="text-[9px] text-gray-400 truncate">
                        <span class="font-black text-[#00A859]">Eq.{{ n }}</span>
                        {{ alumnadosDeEquipoEn(s, n).join(', ') || 'Sin alumnos' }}
                      </p>
                    </div>
                  </div>
                  <div class="flex flex-col items-end gap-1 flex-shrink-0">
                    <button @click.stop="abrirModalEliminar(s)"
                            class="p-1 rounded-lg hover:bg-red-50 text-gray-300 hover:text-red-400 transition-all"
                            title="Eliminar">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </li>
            </ul>

            <!-- Paginación -->
            <div v-if="totalPaginasSes > 1"
                 class="px-4 py-3 border-t border-gray-50 flex items-center justify-between gap-2">
              <button @click="paginaEncuentros--"
                      :disabled="paginaEncuentros === 1"
                      class="p-1.5 rounded-lg border border-gray-200 text-gray-400
                             hover:border-[#00A859] hover:text-[#00A859]
                             disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
              </button>
              <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                {{ paginaEncuentros }} / {{ totalPaginasSes }}
              </span>
              <button @click="paginaEncuentros++"
                      :disabled="paginaEncuentros === totalPaginasSes"
                      class="p-1.5 rounded-lg border border-gray-200 text-gray-400
                             hover:border-[#00A859] hover:text-[#00A859]
                             disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- MODAL DETALLE DE ENCUENTRO                                                -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="encuentroAbierto"
           class="fixed inset-0 z-50 flex items-center justify-center p-4">

        <!-- Backdrop -->
        <div @click="cerrarEncuentroModal()"
             class="absolute inset-0 bg-black/40 backdrop-blur-sm" />

        <!-- Panel -->
        <div class="relative bg-white rounded-[1.75rem] shadow-2xl max-w-2xl w-full max-h-[92vh]
                    overflow-hidden border border-gray-100 flex flex-col">

          <!-- Cabecera -->
          <div class="px-5 sm:px-7 pt-6 sm:pt-7 pb-5 border-b border-gray-100 flex-shrink-0">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2 h-2 rounded-full bg-[#00A859] flex-shrink-0" />
                  <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#00A859]">
                    Encuentro · {{ formatFecha(encuentroAbierto.fecha) }}
                  </p>
                </div>
                <h2 class="text-lg font-black text-[#1F2937] leading-snug">
                  {{ encuentroAbierto.proyecto_titulo || '(sin título)' }}
                </h2>
                <p v-if="encuentroAbierto.centro_educativo"
                   class="text-xs font-semibold text-gray-400 mt-0.5 truncate">
                  {{ encuentroAbierto.centro_educativo }}
                </p>
              </div>
              <button @click="cerrarEncuentroModal()"
                      class="flex-shrink-0 p-2 rounded-xl hover:bg-gray-100 text-gray-400
                             hover:text-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Tags rápidos -->
            <div class="flex flex-wrap gap-1.5 mt-3">
              <span v-if="encuentroAbierto.fecha"           class="tag tag-gray">{{ formatFecha(encuentroAbierto.fecha) }}</span>
              <span v-if="encuentroAbierto.curso"           class="tag tag-green">{{ encuentroAbierto.curso }}</span>
              <span v-if="encuentroAbierto.grupo"           class="tag tag-lime">Grupo {{ encuentroAbierto.grupo }}</span>
              <span v-if="encuentroAbierto.num_alumnos"     class="tag tag-gray">{{ encuentroAbierto.num_alumnos }} alumnos</span>
            </div>
          </div>

          <!-- Contenido con scroll -->
          <div class="flex-1 overflow-y-auto min-h-0">

          <!-- Cuerpo -->
          <div class="px-5 sm:px-7 py-5 space-y-4">

            <!-- Grid de datos -->
            <div v-if="encuentroAbierto.ciclo_formativo" class="grid grid-cols-1 gap-3">
              <div class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Ciclo</p>
                <p class="text-sm font-bold text-[#1F2937] leading-snug">{{ encuentroAbierto.ciclo_formativo }}</p>
              </div>
            </div>

            <!-- Notas -->
            <div v-if="encuentroAbierto.notas"
                 class="p-4 rounded-xl bg-[#F8FAFC] border border-gray-100">
              <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Notas adicionales</p>
              <p class="text-sm text-[#1F2937] leading-relaxed">{{ encuentroAbierto.notas }}</p>
            </div>

            <EncuentroEquiposYRaCe
              :num-equipos="encuentroAbierto.num_equipos"
              :alumnados-del-equipo="alumnadosDelEquipoAbierto"
              :cargando-ra-ce="cargandoRaCe"
              :ra-ce-blocks="raCeBlocksEncuentro"
              :modulos-expandidos="modulosExpandidos"
              @toggle-modulo="toggleModulo"
            />

          </div>

          </div>
          <!-- /Contenido con scroll -->

          <!-- Pie -->
          <div class="px-5 sm:px-7 py-4 bg-[#F8FAFC] border-t border-gray-100 space-y-3 flex-shrink-0">
            <div class="flex flex-wrap items-center gap-2">
              <button v-if="encuentroAbierto.microreto_id"
                      @click="abrirMicroretoModal(encuentroAbierto.microreto_id)"
                      class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl
                             bg-white border border-gray-200 text-gray-500
                             text-xs font-black uppercase tracking-widest
                             hover:border-[#00A859] hover:text-[#00A859] transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                </svg>
                Ver ficha reto
              </button>
              <button v-if="encuentroAbierto.microproyecto_uuid"
                      @click="abrirProyectoModal(encuentroAbierto.microproyecto_uuid)"
                      class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl
                             bg-white border border-gray-200 text-gray-500
                             text-xs font-black uppercase tracking-widest
                             hover:border-[#00A859] hover:text-[#00A859] transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m-4 6h16v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6z"/>
                </svg>
                Ver ficha proyecto
              </button>
              <button @click="router.push({ path: '/encuentros', query: { id: encuentroAbierto.id } }); cerrarEncuentroModal()"
                      class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl
                             bg-white border border-gray-200 text-gray-500
                             text-xs font-black uppercase tracking-widest
                             hover:border-[#00A859] hover:text-[#00A859] transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Ver más detalle de este encuentro
              </button>
              <button @click="router.push('/encuentros'); cerrarEncuentroModal()"
                      class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#00A859] text-white
                             text-xs font-black uppercase tracking-widest hover:bg-[#00A859]/90
                             transition-all shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Ver todos los encuentros
              </button>
            </div>
            <button @click="abrirModalEliminar(encuentroAbierto)"
                    class="text-xs text-gray-400 hover:text-red-400 font-black uppercase
                           tracking-widest transition-colors">
              Eliminar encuentro
            </button>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- MODAL FICHA DE MICRORETO                                               -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <MicroretoModal
    :microreto-id="microretoModalId"
    @close="cerrarMicroretoModal"
  />

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- MODAL FICHA DE PROYECTO                                                 -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <ProyectoFichaModal
    :proyecto-uuid="proyectoModalUuid"
    @close="cerrarProyectoModal"
  />

  <!-- Modal eliminar encuentro -->
  <EliminarEncuentroModal
    :visible="modalEliminarVisible"
    :encuentro="encuentroAEliminar"
    @encuentro-eliminado="onEncuentroEliminado"
    @cerrar="cerrarModalEliminar"
  />

  <!-- Modal: ¿Activar guía-tour? -->
  <TourPromptModal
    :show="showTourPrompt"
    titulo="¿Quieres activar la guía-tour?"
    descripcion="Explora el panel docente con una guía paso a paso que te muestra cada función."
    @activar="activarTourDesdeModal"
    @omitir="omitirTourDesdeModal"
  />

  <!-- Snackbar papelera -->
  <Teleport to="body">
    <Transition name="dd-snack">
      <div v-if="snackbar.visible"
           class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[9999]
                  flex items-center gap-3 px-5 py-3 rounded-2xl shadow-xl
                  bg-[#1F2937] text-white text-sm font-medium">
        <svg v-if="snackbar.accion" class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <svg v-else class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        <span>{{ snackbar.mensaje }}</span>
        <button v-if="snackbar.accion"
                @click="snackbar.accion.fn(); snackbar.visible = false"
                class="ml-2 px-3 py-1 rounded-lg bg-amber-400 text-amber-900
                       text-xs font-black uppercase tracking-wider hover:bg-amber-300 transition-colors">
          {{ snackbar.accion.label }}
        </button>
      </div>
    </Transition>
  </Teleport>

</template>

<style scoped>
/* ── Animación de entrada del banner "Creación de encuentro" ── */
.creation-hero-enter-active {
  transition: opacity 0.45s cubic-bezier(0.16, 1, 0.3, 1),
              transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.creation-hero-enter-from {
  opacity: 0;
  transform: translateY(-10px) scale(0.98);
}

.field-label {
  display: block;
  font-size: 0.6875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #6B7280;
  margin-bottom: 0.375rem;
}
.field-input {
  width: 100%;
  background: #F9FAFB;
  border: 1px solid #E5E7EB;
  border-radius: 0.75rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #1F2937;
  outline: none;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}
.field-input:focus {
  border-color: #00A859;
  box-shadow: 0 0 0 3px rgba(0, 168, 89, 0.12);
  background: #fff;
}
.field-input::placeholder { color: #D1D5DB; font-weight: 400; }
.field-input:disabled { opacity: 0.45; cursor: not-allowed; }
select.field-input { cursor: pointer; }
.tag {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  font-size: 0.625rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.tag-gray  { background: #F3F4F6; color: #6B7280; }
.tag-green { background: rgba(0,168,89,0.1); color: #00A859; }
.tag-lime  { background: rgba(153,204,51,0.12); color: #5a7a00; }
.tag-amber { background: rgba(251,191,36,0.12); color: #92400e; }

/* Tour: sección activa */
.tour-active {
  box-shadow: 0 0 0 3px #00A859, 0 0 0 8px rgba(0, 168, 89, 0.2), 0 4px 20px rgba(0,0,0,0.1) !important;
  border-radius: 0.75rem;
  transition: box-shadow 0.25s ease;
}

/* Tour: secciones inactivas se desvanecen y desenfocadas */
.tour-seccion-blur {
  filter: blur(2px);
  opacity: 0.4;
  pointer-events: none;
  transition: filter 0.3s ease, opacity 0.3s ease;
}

/* sp-fade transition (overlay y bocadillo) */
.sp-fade-enter-active, .sp-fade-leave-active { transition: opacity 200ms ease; }
.sp-fade-enter-from, .sp-fade-leave-to { opacity: 0; }

/* snackbar */
.dd-snack-enter-active, .dd-snack-leave-active { transition: opacity 250ms ease, transform 250ms ease; }
.dd-snack-enter-from, .dd-snack-leave-to { opacity: 0; transform: translateX(-50%) translateY(12px); }

/* Modal animation */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 200ms ease;
}
.modal-fade-enter-active .relative,
.modal-fade-leave-active .relative {
  transition: transform 200ms ease, opacity 200ms ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-from .relative {
  transform: scale(0.95) translateY(8px);
  opacity: 0;
}
.modal-fade-leave-to .relative {
  transform: scale(0.97);
  opacity: 0;
}
</style>
