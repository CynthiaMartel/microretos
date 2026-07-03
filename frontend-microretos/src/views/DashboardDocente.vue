<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter, onBeforeRouteUpdate } from 'vue-router'
import api from '../api.js'
import MicroretoModal from '../components/MicroretoModal.vue'
import EliminarSesionModal from '../components/EliminarSesionModal.vue'
import { useUIState } from '../composables/useUIState.js'
import { useAuthStore } from '../stores/auth.js'

const { tourActivo } = useUIState()
const authStore = useAuthStore()

const router = useRouter()

// ─── Formulario de sesión ──────────────────────────────────────────────────────
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
  proyecto_titulo:  '',
  proyecto_uuid:    '',
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
  let base = todosMisProyectos.value
  if (filtroProyCurso.value)  base = base.filter(p => p.curso === filtroProyCurso.value)
  if (filtroProyCiclo.value)  base = base.filter(p => p.ciclo_nombre === filtroProyCiclo.value)
  if (filtroProyEstado.value === 'en_edicion') base = base.filter(p => p.estado === 'borrador' || p.estado === 'en_edicion')
  else if (filtroProyEstado.value === 'propuesta') base = base.filter(p => (p.estado === 'publicado' || p.estado === 'propuesta') && !p.empresa_validado)
  else if (filtroProyEstado.value === 'validado')  base = base.filter(p => !!p.empresa_validado || p.estado === 'validado')
  else if (filtroProyEstado.value === 'archivado') base = base.filter(p => p.estado === 'archivado')
  else if (filtroProyEstado.value) base = base.filter(p => p.estado === filtroProyEstado.value)
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
  form.value.proyecto_uuid     = p.uuid
  form.value.proyecto_titulo   = p.titulo
  mostrarBuscadorProy.value    = false
}

function limpiarProyecto() {
  proyectoSeleccionado.value = null
  form.value.proyecto_uuid   = ''
  form.value.proyecto_titulo = ''
}

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

// ─── Modal Ver Proyecto ───────────────────────────────────────────────────────
const modalVerProyecto = ref(false)
const proyectoModal    = ref(null)
const cargandoModal    = ref(false)

async function abrirModalProyecto(uuid) {
  modalVerProyecto.value = true
  cargandoModal.value    = true
  proyectoModal.value    = null
  try {
    const res = await api.get(`/startup/proyectos/${uuid}`)
    proyectoModal.value = res.data
  } catch {
    /* no crítico */
  } finally {
    cargandoModal.value = false
  }
}

function cerrarModalProyecto() {
  modalVerProyecto.value = false
  proyectoModal.value    = null
}

function getBadgeModal(p) {
  if (!p) return { label: '—', cls: 'bg-gray-100 border-gray-200 text-gray-400', dot: 'bg-gray-300' }
  const e = p.estado
  if (e === 'borrador' || e === 'en_edicion')
    return { label: 'En edición',  cls: 'bg-amber-50 border-amber-200 text-amber-700',    dot: 'bg-amber-400' }
  if (e === 'archivado')
    return { label: 'Archivado',   cls: 'bg-gray-100 border-gray-200 text-gray-400',       dot: 'bg-gray-400' }
  if (e === 'validado') {
    if (p.empresa_validado && p.docente_validado)
      return { label: 'Validado · Completo', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
    if (p.empresa_validado)
      return { label: 'Validado · Empresa', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
    if (p.docente_validado)
      return { label: 'Validado · Docente', cls: 'bg-emerald-50 border-emerald-300 text-emerald-700', dot: 'bg-emerald-500' }
    return { label: 'Validado', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
  }
  if (p.empresa_no_valida_aun)
    return { label: 'No validar aún', cls: 'bg-red-50 border-red-300 text-red-700',         dot: 'bg-red-400' }
  if (p.enviado_a_empresa_mail)
    return { label: 'Enviado · Esperando', cls: 'bg-blue-50 border-blue-200 text-blue-700', dot: 'bg-blue-400' }
  return { label: 'Pendiente validar', cls: 'bg-violet-50 border-violet-300 text-violet-700', dot: 'bg-violet-400' }
}

// ─── Alumnado por equipos ─────────────────────────────────────────────────────
const nuevoAlumnadoNombre = ref('')
const nuevoAlumnadoEquipo = ref(1)

function addAlumnadoSesion() {
  const nombre = nuevoAlumnadoNombre.value.trim()
  if (!nombre) return
  form.value.alumnados.push({ nombre, equipo_num: nuevoAlumnadoEquipo.value })
  nuevoAlumnadoNombre.value = ''
}

function removeAlumnadoSesion(i) {
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

const guardando  = ref(false)
const guardadoOk = ref(false)

// ─── Historial de sesiones (API) ─────────────────────────────────────────────
const sesiones         = ref([])
const cargandoSesiones = ref(false)

async function cargarSesiones() {
  cargandoSesiones.value = true
  try {
    const res = await api.get('/sesiones')
    sesiones.value = res.data
  } catch (e) {
    console.error('Error cargando sesiones:', e)
  } finally {
    cargandoSesiones.value = false
  }
}

// ─── Opciones para datalist (desde historial de sesiones) ────────────────────
const centrosParaAutocompletar = computed(() => {
  const set = new Set()
  sesiones.value.forEach(s => { if (s.centro_educativo) set.add(s.centro_educativo) })
  return [...set].sort()
})

const ciclosParaAutocompletar = computed(() => {
  const set = new Set()
  sesiones.value.forEach(s => { if (s.ciclo_formativo) set.add(s.ciclo_formativo) })
  return [...set].sort()
})

onMounted(async () => {
  await cargarSesiones()

  // Migración silenciosa desde localStorage (una sola vez)
  try {
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
      await api.post('/sesiones/lote', { sesiones: payload })
      localStorage.removeItem(LEGACY_KEY)
      await cargarSesiones()
    }
  } catch (e) {
    console.error('Error migrando sesiones desde localStorage:', e)
  }
})

onUnmounted(() => {
  tourActivo.value = false
  window.removeEventListener('scroll', onScrollGuia)
})

// ─── Guardar sesión ───────────────────────────────────────────────────────────
async function guardarSesion() {
  if (!form.value.fecha) return
  guardando.value = true
  try {
    const payload = {
      ...form.value,
      num_alumnos: form.value.num_alumnos !== '' ? Number(form.value.num_alumnos) : null,
    }
    const res = await api.post('/sesiones', payload)
    sesiones.value = [res.data, ...sesiones.value]
    guardadoOk.value = true
    setTimeout(() => { guardadoOk.value = false }, 2500)
  } catch (e) {
    console.error('Error guardando sesión:', e)
  } finally {
    guardando.value = false
  }
}

// ─── Eliminar sesión (modal dos fases) ───────────────────────────────────────
const modalEliminarVisible = ref(false)
const sesionAEliminar      = ref(null)
const snackbar = ref({ visible: false, mensaje: '', accion: null })
let   snackTimer = null

function mostrarSnack(mensaje, accion = null) {
  clearTimeout(snackTimer)
  snackbar.value = { visible: true, mensaje, accion }
  snackTimer = setTimeout(() => { snackbar.value.visible = false }, 5000)
}

function abrirModalEliminar(sesion) {
  sesionAEliminar.value = sesion
  modalEliminarVisible.value = true
}

function cerrarModalEliminar() {
  modalEliminarVisible.value = false
  sesionAEliminar.value = null
}

function onSesionEliminada({ id, titulo }) {
  sesiones.value = sesiones.value.filter(s => s.id !== id)
  cerrarSesionModal()
  cerrarModalEliminar()
  mostrarSnack(
    `"${titulo}" movida a la papelera.`,
    { label: 'Ir a la papelera', fn: () => router.push({ name: 'papelera' }) }
  )
}

// ─── Filtros y paginación del panel de sesiones ───────────────────────────────
const filtroSes      = ref({ fecha: '', titulo: '', curso: '', grupo: '' })
const paginaSesiones = ref(1)
const SESIONES_POR_PAGINA = 5

const sesionesFiltradas = computed(() => {
  let lista = [...sesiones.value]
  if (filtroSes.value.fecha)
    lista = lista.filter(s => s.fecha === filtroSes.value.fecha)
  if (filtroSes.value.titulo.trim()) {
    const q = filtroSes.value.titulo.trim().toLowerCase()
    lista = lista.filter(s => (s.microreto_titulo || '').toLowerCase().includes(q))
  }
  if (filtroSes.value.curso) lista = lista.filter(s => s.curso === filtroSes.value.curso)
  if (filtroSes.value.grupo) lista = lista.filter(s => s.grupo === filtroSes.value.grupo)
  return lista
})

const sesionesVisibles = computed(() => {
  const start = (paginaSesiones.value - 1) * SESIONES_POR_PAGINA
  return sesionesFiltradas.value.slice(start, start + SESIONES_POR_PAGINA)
})

const totalPaginasSes = computed(() =>
  Math.ceil(sesionesFiltradas.value.length / SESIONES_POR_PAGINA)
)

watch(filtroSes, () => { paginaSesiones.value = 1 }, { deep: true })

// ─── Tour guía paso a paso ────────────────────────────────────────────────────
const modoGuia       = ref(false)
const pasoGuia       = ref(1)

const guiaPasosData = [
  { ref: 'refCampoFecha',    seccion: 'datos', texto: 'Indica la fecha en la que se realizó la sesión.' },
  { ref: 'refCampoCentro',   seccion: 'datos', texto: 'Indica el centro educativo.' },
  { ref: 'refCampoAlumnado', seccion: 'datos', texto: 'Indica la información del alumnado: curso, grupo y número de alumnos.' },
  { ref: 'refCampoEquipos',  seccion: 'datos', texto: 'Configura los equipos y añade el alumnado asignado a cada uno.' },
  { ref: 'refCampoNotas',    seccion: 'datos', texto: 'Indica cualquier nota relevante sobre la sesión.' },
  { ref: 'refBtnGuardar',    seccion: 'datos', texto: 'Pulsa guardar sesión para registrar la información.' },
  { ref: 'refSesiones',      seccion: 'sesiones', texto: 'Quedará disponible para crear el proyecto y comenzar el taller de ideas, o consultar esta info más adelante.' },
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
const refSesiones      = ref(null)
const refBtnGuia       = ref(null)

const tourRefs = { refCampoFecha, refCampoCentro, refCampoAlumnado, refCampoEquipos, refCampoNotas, refBtnGuardar, refSesiones, refBtnGuia }

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

// ─── Modal de detalle de sesión ───────────────────────────────────────────────
const sesionAbierta = ref(null)

function verSesion(s) {
  sesionAbierta.value = s
}

function cerrarSesionModal() {
  sesionAbierta.value = null
}

const formularioValido = computed(() => form.value.fecha !== '')

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
              Sesiones <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">DuaLab</span>
            </h1>
            <p class="text-gray-500 text-sm mt-1">Registra y consulta tus sesiones de trabajo con retos.</p>
          </div>

          <!-- Stats chips -->
          <div class="flex flex-wrap gap-3 items-center">
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-2xl border border-gray-100 shadow-sm">
              <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                         M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
              <span class="font-black text-xl text-[#1F2937]">{{ sesiones.length }}</span>
              <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">sesiones</span>
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
                    <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Nueva sesión</span>
                  </div>
                  <h2 class="text-xl font-black text-[#1F2937] tracking-tight leading-tight">Creación de sesión</h2>
                  <p class="text-xs text-gray-500 mt-0.5 font-medium">Selecciona un reto y registra los datos del grupo</p>
                </div>
              </div>
            </div>
          </Transition>

          <!-- ══ PROYECTO ASOCIADO ═══════════════════════════════════════════ -->
          <div class="overflow-hidden border-b border-gray-100">
            <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
              <p class="text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">
                Proyecto asociado
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
                Buscar proyecto
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
                  <button @click="abrirModalProyecto(proyectoSeleccionado.uuid)"
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
                    <option value="">Todos los estados</option>
                    <option value="en_edicion">En edición</option>
                    <option value="propuesta">Pendiente validar</option>
                    <option value="validado">Validado</option>
                    <option value="archivado">Archivado</option>
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
                    <button @click.stop="abrirModalProyecto(p.uuid)"
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
                    Opcional — asocia un proyecto existente a esta sesión para tenerlo
                    vinculado desde el registro. Los proyectos se crean en
                    <button @click="router.push({ name: 'startup-day-crear' })"
                            class="underline font-black hover:text-[#5a7a00] transition-colors">
                      Startup Day
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
                  Buscar proyecto
                </button>
              </div>

            </div>
          </div>

          <!-- ══ DATOS DE LA SESIÓN ══════════════════════════════════════════ -->
          <div :class="{ 'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'datos' }">
            <div class="px-6 py-4 border-b border-gray-50">
              <p class="text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">
                Datos de la sesión
              </p>
            </div>
            <div class="px-6 py-5 space-y-4">

              <div ref="refCampoFecha" :class="{ 'tour-active': tourTargetActivo === 'refCampoFecha' }"
                   class="rounded-xl transition-all">
                <label class="field-label">Fecha de trabajo <span class="text-red-400">*</span></label>
                <input v-model="form.fecha" type="date" class="field-input" />
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
                    <label class="field-label">Curso</label>
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
                    <label class="field-label">Grupo</label>
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
                  <label class="field-label">Número de equipos</label>
                  <div class="flex items-center gap-2 mt-1">
                    <button @click="form.num_equipos = Math.max(1, (form.num_equipos || 3) - 1)"
                            class="w-8 h-8 rounded-xl bg-gray-100 border border-gray-200
                                   text-gray-600 font-black text-sm hover:bg-gray-200 transition-all">−</button>
                    <span class="w-8 text-center text-base font-black text-[#1F2937]">{{ form.num_equipos || 3 }}</span>
                    <button @click="form.num_equipos = Math.min(20, (form.num_equipos || 3) + 1)"
                            class="w-8 h-8 rounded-xl bg-gray-100 border border-gray-200
                                   text-gray-600 font-black text-sm hover:bg-gray-200 transition-all">+</button>
                    <span class="text-[10px] text-gray-400 font-medium ml-1">equipos · se crearán al generar el código</span>
                  </div>
                </div>

                <!-- Alumnado por equipos — siempre visible -->
                <div ref="refCampoEquipos" class="space-y-3 rounded-xl transition-all"
                     :class="{ 'tour-active': tourTargetActivo === 'refCampoEquipos' }">

                  <label class="field-label">
                    Alumnado por equipos
                    <span class="text-gray-300 font-normal normal-case ml-1">(opcional)</span>
                  </label>

                  <!-- Formulario añadir alumno/a -->
                  <div class="flex flex-wrap gap-2">
                    <input v-model="nuevoAlumnadoNombre" type="text" placeholder="Nombre del alumno/a"
                           class="field-input flex-1 min-w-32 !text-sm"
                           @keyup.enter="addAlumnadoSesion" />
                    <select v-model="nuevoAlumnadoEquipo"
                            class="field-input !w-auto pr-8 cursor-pointer !text-sm">
                      <option v-for="n in (form.num_equipos || 3)" :key="n" :value="n">
                        Equipo {{ n }}
                      </option>
                    </select>
                    <button @click="addAlumnadoSesion"
                            class="shrink-0 px-3 py-2 bg-[#00A859] text-white rounded-xl
                                   text-xs font-black hover:bg-[#00A859]/90 transition-all">
                      + Añadir
                    </button>
                  </div>

                  <!-- Grid de equipos -->
                  <div class="grid grid-cols-2 gap-2">
                    <div v-for="n in (form.num_equipos || 3)" :key="n"
                         class="rounded-xl border border-gray-100 bg-gray-50/50 p-3">
                      <div class="flex items-center gap-1.5 mb-2">
                        <span class="w-5 h-5 rounded-full bg-[#00A859]/10 flex items-center justify-center
                                     text-[9px] font-black text-[#00A859] flex-shrink-0">{{ n }}</span>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 flex-1 truncate">
                          Equipo {{ n }}
                        </p>
                        <span class="text-[9px] text-gray-400 flex-shrink-0">
                          {{ alumnadosDeEquipo(n).length }}
                        </span>
                      </div>
                      <div v-if="alumnadosDeEquipo(n).length" class="space-y-1">
                        <div v-for="a in alumnadosDeEquipo(n)" :key="a._i"
                             class="flex items-center gap-1 text-xs">
                          <span class="flex-1 truncate font-medium text-[#1F2937]">{{ a.nombre }}</span>
                          <button @click="removeAlumnadoSesion(a._i)"
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
                        <button @click="removeAlumnadoSesion(a._i)"
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

            <div ref="refBtnGuardar"
                 class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-4 transition-all"
                 :class="{ 'tour-active': tourTargetActivo === 'refBtnGuardar' }">
              <p class="text-[10px] text-gray-400 font-medium">
                La sesión se guarda en la base de datos.
              </p>
              <button @click="guardarSesion"
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
                {{ guardadoOk ? '¡Guardado!' : guardando ? 'Guardando...' : 'Guardar sesión' }}
              </button>
            </div>
          </div>

          </div><!-- /card creación -->
        </div>

        <!-- ─── COLUMNA DERECHA: Historial ──────────────────────────────── -->
        <div class="lg:col-span-2 transition-all"
             :class="{ 'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'sesiones' }">

          <div ref="refSesiones"
               class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm overflow-hidden sticky top-[4.5rem]"
               :class="{ 'tour-active': tourTargetActivo === 'refSesiones' }">

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
                    Resumen de sesiones
                  </h2>
                </div>
                <span class="flex-shrink-0 text-[10px] font-black bg-blue-100 text-blue-600
                             px-2.5 py-1 rounded-full uppercase tracking-widest">
                  {{ sesiones.length }}
                </span>
              </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="px-5 py-3 border-b border-gray-100">
              <!-- Botón Ver sesiones — prominente -->
              <button @click="router.push('/dashboard/sesiones')"
                      class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                             bg-[#00A859] text-white
                             text-[10px] font-black uppercase tracking-widest
                             hover:bg-[#00A859]/90 transition-all shadow-sm mb-2">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Ver todas las sesiones
              </button>
              <button @click="router.push({ name: 'startup-day-crear' })"
                      class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl
                             bg-[#99CC33]/10 border border-[#99CC33]/20 text-[#5a7a00]
                             text-[9px] font-black uppercase tracking-widest
                             hover:bg-[#99CC33]/20 transition-all">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo proyecto
              </button>
            </div>

            <!-- Filtros compactos -->
            <div v-if="sesiones.length > 0" class="px-4 py-3 border-b border-gray-50 space-y-2">
              <!-- Búsqueda por título -->
              <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-gray-300 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <input v-model="filtroSes.titulo" type="text" placeholder="Buscar sesión..."
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

            <!-- Cargando sesiones -->
            <div v-if="cargandoSesiones" class="px-5 py-10 flex justify-center">
              <svg class="animate-spin w-5 h-5 text-[#00A859]" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
            </div>

            <!-- Estado vacío -->
            <div v-else-if="sesiones.length === 0" class="px-5 py-10 text-center">
              <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-100
                          flex items-center justify-center mx-auto mb-3">
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                </svg>
              </div>
              <p class="text-xs text-gray-400 font-medium leading-relaxed">
                Aún no hay sesiones.<br>¡Registra la primera!
              </p>
            </div>

            <!-- Sin resultados tras filtrar -->
            <div v-else-if="!cargandoSesiones && sesionesFiltradas.length === 0" class="px-5 py-8 text-center">
              <p class="text-xs text-gray-400 font-medium">Sin resultados para esos filtros.</p>
            </div>

            <!-- Lista de sesiones (miniaturas) -->
            <ul v-else-if="!cargandoSesiones" class="divide-y divide-gray-50">
              <li v-for="s in sesionesVisibles" :key="s.id"
                  class="px-4 py-3 hover:bg-gray-50/60 transition-colors group cursor-pointer"
                  @click="verSesion(s)">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-[#1F2937] leading-snug truncate
                              group-hover:text-[#00A859] transition-colors">
                      {{ s.microreto_titulo || '(sin título)' }}
                    </p>
                    <p class="text-[10px] text-[#00A859] font-bold mt-0.5">
                      {{ formatFecha(s.fecha) }}
                    </p>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <span v-if="s.curso"       class="tag tag-green">{{ s.curso }}</span>
                      <span v-if="s.grupo"       class="tag tag-lime">Gr. {{ s.grupo }}</span>
                      <span v-if="s.num_alumnos" class="tag tag-gray">{{ s.num_alumnos }} al.</span>
                    </div>
                  </div>
                  <div class="flex flex-col items-end gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-all">
                    <button @click.stop="abrirModalEliminar(s)"
                            class="p-1 rounded-lg hover:bg-red-50 text-gray-300 hover:text-red-400 transition-all"
                            title="Eliminar">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </li>
            </ul>

            <!-- Paginación -->
            <div v-if="totalPaginasSes > 1"
                 class="px-4 py-3 border-t border-gray-50 flex items-center justify-between gap-2">
              <button @click="paginaSesiones--"
                      :disabled="paginaSesiones === 1"
                      class="p-1.5 rounded-lg border border-gray-200 text-gray-400
                             hover:border-[#00A859] hover:text-[#00A859]
                             disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
              </button>
              <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                {{ paginaSesiones }} / {{ totalPaginasSes }}
              </span>
              <button @click="paginaSesiones++"
                      :disabled="paginaSesiones === totalPaginasSes"
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
  <!-- MODAL DETALLE DE SESIÓN                                                -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="sesionAbierta"
           class="fixed inset-0 z-50 flex items-center justify-center p-4">

        <!-- Backdrop -->
        <div @click="cerrarSesionModal()"
             class="absolute inset-0 bg-black/40 backdrop-blur-sm" />

        <!-- Panel -->
        <div class="relative bg-white rounded-[1.75rem] shadow-2xl max-w-lg w-full overflow-hidden
                    border border-gray-100">

          <!-- Cabecera -->
          <div class="px-7 pt-7 pb-5 border-b border-gray-100">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2 h-2 rounded-full bg-[#00A859] flex-shrink-0" />
                  <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#00A859]">
                    Sesión · {{ formatFecha(sesionAbierta.fecha) }}
                  </p>
                </div>
                <h2 class="text-lg font-black text-[#1F2937] leading-snug">
                  {{ sesionAbierta.microreto_titulo || '(sin título)' }}
                </h2>
              </div>
              <button @click="cerrarSesionModal()"
                      class="flex-shrink-0 p-2 rounded-xl hover:bg-gray-100 text-gray-400
                             hover:text-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Tags rápidos -->
            <div class="flex flex-wrap gap-1.5 mt-3">
              <span v-if="sesionAbierta.curso"           class="tag tag-green">{{ sesionAbierta.curso }}</span>
              <span v-if="sesionAbierta.grupo"           class="tag tag-lime">Grupo {{ sesionAbierta.grupo }}</span>
              <span v-if="sesionAbierta.num_alumnos"     class="tag tag-gray">{{ sesionAbierta.num_alumnos }} alumnos</span>
            </div>
          </div>

          <!-- Cuerpo -->
          <div class="px-7 py-5 space-y-4">

            <!-- Grid de datos -->
            <div class="grid grid-cols-2 gap-3">
              <div v-if="sesionAbierta.centro_educativo"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Centro</p>
                <p class="text-sm font-bold text-[#1F2937] leading-snug">{{ sesionAbierta.centro_educativo }}</p>
              </div>
              <div v-if="sesionAbierta.ciclo_formativo"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Ciclo</p>
                <p class="text-sm font-bold text-[#1F2937] leading-snug">{{ sesionAbierta.ciclo_formativo }}</p>
              </div>
              <div v-if="sesionAbierta.fecha"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Fecha</p>
                <p class="text-sm font-bold text-[#1F2937]">{{ formatFecha(sesionAbierta.fecha) }}</p>
              </div>
              <div v-if="sesionAbierta.num_alumnos"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-gray-100">
                <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-0.5">Alumnos</p>
                <p class="text-sm font-bold text-[#1F2937]">{{ sesionAbierta.num_alumnos }}</p>
              </div>
            </div>

            <!-- Notas -->
            <div v-if="sesionAbierta.notas"
                 class="p-4 rounded-xl bg-[#F8FAFC] border border-gray-100">
              <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Notas adicionales</p>
              <p class="text-sm text-[#1F2937] leading-relaxed">{{ sesionAbierta.notas }}</p>
            </div>

          </div>

          <!-- Pie -->
          <div class="px-7 py-4 bg-[#F8FAFC] border-t border-gray-100 flex items-center justify-between gap-4">
            <button @click="abrirModalEliminar(sesionAbierta)"
                    class="text-xs text-gray-400 hover:text-red-400 font-black uppercase
                           tracking-widest transition-colors">
              Eliminar sesión
            </button>
            <div v-if="sesionAbierta.microreto_id" class="flex items-center gap-2">
              <button @click="abrirMicroretoModal(sesionAbierta.microreto_id); cerrarSesionModal()"
                      class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl
                             bg-white border border-gray-200 text-gray-500
                             text-xs font-black uppercase tracking-widest
                             hover:border-[#00A859] hover:text-[#00A859] transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                </svg>
                Ver ficha
              </button>
              <button @click="router.push({ name: 'startup-day-crear', query: { microreto_id: sesionAbierta.microreto_id, sesion_id: sesionAbierta.id } }); cerrarSesionModal()"
                      class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#99CC33] text-white
                             text-xs font-black uppercase tracking-widest hover:bg-[#99CC33]/90
                             transition-all shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Crear proyecto
              </button>
            </div>
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

  <!-- Modal eliminar sesión -->
  <EliminarSesionModal
    :visible="modalEliminarVisible"
    :sesion="sesionAEliminar"
    @sesion-eliminada="onSesionEliminada"
    @cerrar="cerrarModalEliminar"
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

  <!-- ══ MODAL VER PROYECTO ══════════════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="modalVerProyecto"
           class="fixed inset-0 z-[80] flex items-center justify-center p-4"
           @click.self="cerrarModalProyecto">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cerrarModalProyecto" />

        <div class="relative z-10 bg-white rounded-3xl shadow-2xl w-full max-w-2xl
                    max-h-[90vh] flex flex-col overflow-hidden">

          <!-- Cabecera -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-7 h-7 rounded-xl bg-[#99CC33]/15 border border-[#99CC33]/20
                          flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5 text-[#5a7a00]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <p v-if="proyectoModal" class="font-black text-[#1F2937] text-sm truncate">
                {{ proyectoModal.titulo }}
              </p>
              <p v-else class="text-sm text-gray-400 font-medium">Cargando proyecto…</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <!-- Abrir en Startup Day -->
              <button v-if="proyectoModal"
                      @click="router.push({ name: 'startup-day-detalle', params: { uuid: proyectoModal.uuid } }); cerrarModalProyecto()"
                      class="px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200
                             text-[10px] font-black uppercase tracking-widest text-gray-500
                             hover:border-[#99CC33] hover:text-[#5a7a00] transition-all flex items-center gap-1.5">
                Startup Day
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
              </button>
              <button @click="cerrarModalProyecto"
                      class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center
                             text-gray-400 hover:bg-gray-200 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Contenido -->
          <div class="flex-1 overflow-y-auto">

            <!-- Spinner -->
            <div v-if="cargandoModal" class="flex justify-center items-center py-20">
              <svg class="animate-spin w-8 h-8 text-[#99CC33]" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
            </div>

            <template v-else-if="proyectoModal">

              <!-- Badge estado + meta -->
              <div class="px-6 pt-5 pb-4 border-b border-gray-50 space-y-3">
                <span :class="['inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full border', getBadgeModal(proyectoModal).cls]">
                  <span :class="['w-1.5 h-1.5 rounded-full', getBadgeModal(proyectoModal).dot]" />
                  {{ getBadgeModal(proyectoModal).label }}
                </span>

                <!-- Sub-estado propuesta -->
                <div v-if="(proyectoModal.estado === 'publicado' || proyectoModal.estado === 'propuesta') && !proyectoModal.empresa_validado"
                     class="space-y-2">
                  <div v-if="proyectoModal.empresa_validado"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-[#00A859]/8 border border-[#00A859]/20">
                    <svg class="w-4 h-4 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <p class="text-xs font-black text-[#00A859]">La empresa validó el proyecto</p>
                  </div>
                  <div v-else-if="proyectoModal.empresa_no_valida_aun"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-red-50 border border-red-200">
                    <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-xs font-black text-red-700">Empresa contestó: No validar aún</p>
                  </div>
                  <div v-else-if="proyectoModal.enviado_a_empresa_mail"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-blue-50 border border-blue-200">
                    <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-xs font-black text-blue-700">Enlace enviado · Pendiente de respuesta</p>
                  </div>
                  <div v-else
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-violet-50 border border-violet-200">
                    <svg class="w-4 h-4 text-violet-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-xs font-black text-violet-700">Propuesta pendiente de enviar a empresa</p>
                  </div>
                </div>

                <!-- Meta chips -->
                <div class="flex flex-wrap gap-1.5">
                  <span v-if="proyectoModal.empresa_nombre" class="tag tag-gray">
                    <svg class="w-3 h-3 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    {{ proyectoModal.empresa_nombre }}
                  </span>
                  <span v-if="proyectoModal.centro_nombre" class="tag tag-gray">{{ proyectoModal.centro_nombre }}</span>
                  <span v-if="proyectoModal.ciclo_nombre"  class="tag tag-gray">{{ proyectoModal.ciclo_nombre }}</span>
                  <span v-if="proyectoModal.curso"         class="tag tag-lime">{{ proyectoModal.curso }}</span>
                </div>
              </div>

              <!-- Secciones del proyecto -->
              <div class="px-6 py-5 space-y-4">

                <!-- El reto -->
                <div v-if="proyectoModal.diseno_reto?.descripcion || proyectoModal.diseno_reto?.pregunta_reto"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">El reto</p>
                  <p v-if="proyectoModal.diseno_reto.pregunta_reto"
                     class="text-sm font-bold text-[#5a7a00] italic mb-2">
                    "{{ proyectoModal.diseno_reto.pregunta_reto }}"
                  </p>
                  <p v-if="proyectoModal.diseno_reto.descripcion"
                     class="text-sm text-gray-600 leading-relaxed line-clamp-4">
                    {{ proyectoModal.diseno_reto.descripcion }}
                  </p>
                </div>

                <!-- Equipo -->
                <div v-if="proyectoModal.equipo?.alumnos?.length"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                    Equipo ({{ proyectoModal.equipo.alumnos.length }})
                  </p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="a in proyectoModal.equipo.alumnos" :key="a.nombre"
                          class="text-xs bg-white border border-gray-200 px-2.5 py-1 rounded-full text-gray-600">
                      {{ a.nombre }}<span v-if="a.rol" class="text-gray-400"> · {{ a.rol }}</span>
                    </span>
                  </div>
                </div>

                <!-- Módulos -->
                <div v-if="proyectoModal.modulos_seleccionados?.length"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                    Módulos ({{ proyectoModal.modulos_seleccionados.length }})
                  </p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="m in proyectoModal.modulos_seleccionados" :key="m.id"
                          class="text-xs bg-[#00A859]/8 border border-[#00A859]/15 text-[#00A859]
                                 px-2.5 py-1 rounded-full">
                      {{ m.nombre }}
                    </span>
                  </div>
                </div>

                <!-- Objetivos -->
                <div v-if="proyectoModal.objetivos?.lista?.length"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Objetivos</p>
                  <ul class="space-y-1">
                    <li v-for="obj in proyectoModal.objetivos.lista.slice(0, 5)" :key="obj"
                        class="flex items-start gap-2 text-sm text-gray-600">
                      <span class="text-[#99CC33] shrink-0 mt-0.5 font-bold">›</span> {{ obj }}
                    </li>
                    <li v-if="proyectoModal.objetivos.lista.length > 5"
                        class="text-xs text-gray-400 pl-4">
                      + {{ proyectoModal.objetivos.lista.length - 5 }} más…
                    </li>
                  </ul>
                </div>

                <!-- Fases -->
                <div v-if="proyectoModal.diseno_microproyecto?.fases?.length"
                     class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                    Fases ({{ proyectoModal.diseno_microproyecto.fases.length }})
                  </p>
                  <ol class="space-y-1.5">
                    <li v-for="(f, i) in proyectoModal.diseno_microproyecto.fases.slice(0, 4)" :key="i"
                        class="flex items-center gap-2.5 text-sm">
                      <span class="w-5 h-5 rounded-full bg-[#99CC33]/15 text-[#5a7a00] font-black text-[10px]
                                   flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                      <span class="font-bold text-[#1F2937]">{{ f.nombre }}</span>
                      <span v-if="f.duracion" class="text-gray-400 text-xs">· {{ f.duracion }}</span>
                    </li>
                    <li v-if="proyectoModal.diseno_microproyecto.fases.length > 4"
                        class="text-xs text-gray-400 pl-7">
                      + {{ proyectoModal.diseno_microproyecto.fases.length - 4 }} más…
                    </li>
                  </ol>
                </div>

                <!-- Sin datos -->
                <div v-if="!proyectoModal.diseno_reto?.descripcion && !proyectoModal.equipo?.alumnos?.length
                            && !proyectoModal.modulos_seleccionados?.length && !proyectoModal.objetivos?.lista?.length"
                     class="text-center py-6 text-gray-400">
                  <p class="text-xs">Este proyecto aún no tiene secciones rellenas.</p>
                  <button @click="router.push({ name: 'startup-day-editar', params: { uuid: proyectoModal.uuid } }); cerrarModalProyecto()"
                          class="mt-3 text-xs font-black text-[#5a7a00] underline hover:no-underline">
                    Ir a editar →
                  </button>
                </div>

              </div>
            </template>

            <!-- Error carga -->
            <div v-else-if="!cargandoModal" class="flex flex-col items-center justify-center py-20 text-gray-400">
              <p class="text-sm">No se pudo cargar el proyecto.</p>
            </div>
          </div>

          <!-- Pie del modal -->
          <div v-if="proyectoModal" class="px-6 py-4 border-t border-gray-100 shrink-0 flex gap-3">
            <button @click="cerrarModalProyecto"
                    class="flex-1 py-2.5 rounded-full bg-gray-100 text-[#1F2937]
                           text-xs font-black uppercase tracking-widest
                           hover:bg-gray-200 transition-all">
              Cerrar
            </button>
            <button @click="router.push({ name: 'startup-day-detalle', params: { uuid: proyectoModal.uuid } }); cerrarModalProyecto()"
                    class="flex-1 py-2.5 rounded-full bg-[#99CC33] text-white
                           text-xs font-black uppercase tracking-widest shadow-sm
                           hover:bg-[#88bb22] transition-all">
              Ver en Startup Day
            </button>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>

</template>

<style scoped>
/* ── Animación de entrada del banner "Creación de sesión" ── */
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
