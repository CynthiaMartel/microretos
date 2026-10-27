<!-- Ruta: /encuentros/crear (name: dashboard-docente). El nombre del archivo quedó del naming antiguo (/dashboard) tras el rename de URLs — ver router/index.js. -->
<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { useRoute, useRouter, onBeforeRouteUpdate } from 'vue-router'
import api from '../api.js'
import MicroretoModal from '../components/MicroretoModal.vue'
import ProyectoFichaModal from '../components/ProyectoFichaModal.vue'
import TallerPasos from '../components/TallerPasos.vue'
import MiniaturaReto from '../components/MiniaturaReto.vue'
import EncuentroResumen from '../components/EncuentroResumen.vue'
import TourPromptModal from '../components/TourPromptModal.vue'
import RegenerarAliasButton from '../components/RegenerarAliasButton.vue'
import AvisoSinNombresReales from '../components/AvisoSinNombresReales.vue'
import { generarAliasAleatorio, errorNombrePila } from '../utils/alias.js'
import { formatCurso } from '../utils/formatCurso.js'
import { useUIState } from '../composables/useUIState.js'
import { useAuthStore } from '../stores/auth.js'
import { fechaFinEstimada as calcularFechaFinEstimada, duracionPorFase } from '../config/fasesProyecto.js'

const { tourActivo } = useUIState()
const authStore = useAuthStore()
const isLoaded = ref(false)
onMounted(() => { setTimeout(() => { isLoaded.value = true }, 80) })

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
  // Solo proyectos ya validados (al menos por el docente) pueden asociarse a un
  // encuentro — una propuesta sin validar, un borrador o un archivado no tiene
  // sentido programarlo en clase (ver StoreEncuentroRequest, mismo criterio en backend).
  let base = todosMisProyectos.value.filter(p => p.estado === 'validado' || p.estado === 'completado')
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
    if (empresaValidado && docenteValidado) return { label: 'Validado · Completo', cls: 'bg-centros/10 text-centros' }
    if (empresaValidado)  return { label: 'Validado · Empresa',  cls: 'bg-centros/10 text-centros' }
    if (docenteValidado)  return { label: 'Validado · Docente',  cls: 'bg-empresas/10 text-empresas-dark' }
    return { label: 'Validado', cls: 'bg-centros/10 text-centros' }
  }
  if (estado === 'propuesta')
    return { label: 'Propuesta',   cls: 'bg-violet-50 text-violet-600' }
  return { label: estado || '—',   cls: 'bg-gray-100 text-gray-500' }
}

// ─── Alumnado por equipos ─────────────────────────────────────────────────────
const nuevoAlumnadoNombre = ref('')
const errorNuevoAlumnado  = ref('')
const nuevoAlumnadoEquipo = ref(1)   // grupo de destino de «Añadir»: se elige tocando su tarjeta
// Si se reducen los grupos y el seleccionado deja de existir, pasa al último.
watch(() => form.value.num_equipos, (n) => {
  if (nuevoAlumnadoEquipo.value > (n || 3)) nuevoAlumnadoEquipo.value = n || 3
})

const limiteAlumnados = computed(() => {
  const n = Number(form.value.num_alumnos)
  return n > 0 ? n : null
})

const limiteAlcanzado = computed(() =>
  limiteAlumnados.value !== null && form.value.alumnados.length >= limiteAlumnados.value
)

// Protección de datos: del alumno/a solo se pide el nombre de pila (una palabra, sin
// apellidos) y se le añade animal + color: "Adrián Águila Amarillo", único en la clase.
// El docente lo ve y puede cambiar animal y color con el dado antes de crear el encuentro;
// viaja en el snapshot `alumnados` y se aplica al crear los equipo_miembros reales en
// EncuentroController::crearCodigo().
const aliasUsados = computed(() => form.value.alumnados.map(a => a.alias))

function addAlumnadoEncuentro() {
  if (limiteAlcanzado.value) return
  errorNuevoAlumnado.value = errorNombrePila(nuevoAlumnadoNombre.value)
  if (errorNuevoAlumnado.value) return
  form.value.alumnados.push({
    equipo_num: nuevoAlumnadoEquipo.value,
    alias: generarAliasAleatorio(nuevoAlumnadoNombre.value, aliasUsados.value),
  })
  nuevoAlumnadoNombre.value = ''
}

// ─── Copiar alumnado de un encuentro anterior (solo los del propio docente) ──
// Así el mismo alumno conserva su alias entre proyectos y el docente no tiene que volver a
// darlo de alta ni a apuntar otra correspondencia alias → alumno. Los encuentros ya vienen
// cargados en el historial (GET /encuentros, con equipos y miembros); al guardar, el backend
// vuelve a validar los alias (AliasAlumnado).
const copiandoAlumnado     = ref(false)
const encuentroACopiar     = ref('')
const copiarConReparto     = ref(true)
const mensajeCopiaAlumnado = ref('')

function alumnadoDe(encuentro) {
  if (encuentro.equipos?.length) {
    return encuentro.equipos.flatMap(e => (e.miembros || []).map(m => ({ alias: m.alias, equipo_num: e.numero_equipo, rol: m.rol })))
  }
  return (encuentro.alumnados || []).filter(a => a.alias)
}

const encuentrosParaCopiar = computed(() => encuentros.value
  .filter(e => e.es_propietario)
  .map(e => ({ id: e.id, alumnado: alumnadoDe(e), etiqueta: [
    formatFecha(e.fecha), e.proyecto_titulo || 'Sin proyecto',
    [e.curso && formatCurso(e.curso), e.grupo].filter(Boolean).join(' '),
  ].filter(Boolean).join(' · ') }))
  .filter(e => e.alumnado.length))

function copiarAlumnadoDeEncuentro() {
  const origen = encuentrosParaCopiar.value.find(e => e.id === Number(encuentroACopiar.value))
  if (!origen) return
  const grupos = form.value.num_equipos || 3
  const yaEstan = new Set(form.value.alumnados.map(a => String(a.alias).toLowerCase()))
  let copiados = 0, repetidos = 0, sinSitio = 0
  for (const a of origen.alumnado) {
    if (yaEstan.has(String(a.alias).toLowerCase())) { repetidos++; continue }
    if (limiteAlcanzado.value) { sinSitio++; continue }
    // Con reparto: el mismo grupo si existe (si no, el último); sin reparto: grupos equilibrados
    const equipo = copiarConReparto.value ? Math.min(Number(a.equipo_num) || 1, grupos) : (copiados % grupos) + 1
    form.value.alumnados.push({ alias: a.alias, equipo_num: equipo, ...(a.rol ? { rol: a.rol } : {}) })
    yaEstan.add(String(a.alias).toLowerCase())
    copiados++
  }
  mensajeCopiaAlumnado.value = [
    `${copiados} alumno${copiados === 1 ? '' : 's'} copiado${copiados === 1 ? '' : 's'}`,
    repetidos && `${repetidos} ya estaba${repetidos === 1 ? '' : 'n'}`,
    sinSitio && `${sinSitio} sin copiar por el límite de alumnado`,
  ].filter(Boolean).join(' · ') + '. Revisa los grupos antes de guardar.'
  copiandoAlumnado.value = false
  encuentroACopiar.value = ''
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
  window.removeEventListener('resize', onScrollGuia)
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
    pasoEnc.value = 4
    pasoEncMax.value = 4
  } catch (e) {
    console.error('Error guardando encuentro:', e)
    errorGuardado.value = e.response?.data?.message || 'No se pudo guardar el encuentro. Revisa los datos e inténtalo de nuevo.'
  } finally {
    guardando.value = false
  }
}

// ─── Tour guía paso a paso ────────────────────────────────────────────────────
const modoGuia       = ref(false)
const pasoGuia       = ref(1)
const showTourPrompt = ref(false)

function activarTourDesdeModal() { showTourPrompt.value = false; modoGuia.value = true; pasoGuia.value = 1 }
function omitirTourDesdeModal()  { showTourPrompt.value = false }

// `paso`: paso del asistente donde está el elemento (la guía cambia de paso para enseñarlo).
const guiaPasosData = [
  { ref: 'refCampoFecha',    seccion: 'datos', paso: 1, texto: 'Indica la fecha en la que se realizará el encuentro.' },
  { ref: 'refCampoCentro',   seccion: 'datos', paso: 1, texto: 'Indica el centro educativo.' },
  { ref: 'refCampoAlumnado', seccion: 'datos', paso: 1, texto: 'Indica el curso y la clase del alumnado.' },
  { ref: 'refCampoEquipos',  seccion: 'datos', paso: 2, texto: 'Configura los grupos y añade el alumnado asignado a cada uno.' },
  { ref: 'refCampoNotas',    seccion: 'datos', paso: 3, texto: 'Indica cualquier nota relevante sobre el encuentro.' },
  { ref: 'refBtnGuardar',    seccion: 'datos', paso: 3, texto: 'Pulsa «Guardar encuentro» para registrar la información.' },
  { ref: 'refEncuentros',    seccion: 'encuentros', texto: 'Aquí ves el resumen del encuentro mientras lo completas.' },
  { ref: 'refBtnGuia',       seccion: null,    texto: 'Pulsa «Ayuda» cuando quieras para volver a activar la guía.' },
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
  const ref = tourRefs[paso.ref]?.value
  const el = ref?.$el ?? ref
  if (!el?.getBoundingClientRect) return
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
  const ref = tourRefs[paso?.ref]?.value
  const el = ref?.$el ?? ref
  if (el?.scrollIntoView) el.scrollIntoView({ behavior: 'instant', block: 'nearest' })
  requestAnimationFrame(() => requestAnimationFrame(recalcularBocadillo))
}

function onScrollGuia() {
  if (modoGuia.value) requestAnimationFrame(recalcularBocadillo)
}

watch(pasoGuia, async (n) => {
  const pasoDestino = guiaPasosData[n - 1]?.paso
  if (pasoDestino && pasoDestino !== pasoEnc.value && pasoEnc.value < 4) pasoEnc.value = pasoDestino
  await nextTick()
  scrollYRecalcular()
})

watch(modoGuia, async (v) => {
  tourActivo.value = v
  if (v) {
    const pasoDestino = guiaPasosData[pasoGuia.value - 1]?.paso
    if (pasoDestino && pasoEnc.value < 4) pasoEnc.value = pasoDestino
    window.addEventListener('scroll', onScrollGuia, { passive: true })
    window.addEventListener('resize', onScrollGuia, { passive: true })
    await nextTick()
    scrollYRecalcular()
  } else {
    window.removeEventListener('scroll', onScrollGuia)
    window.removeEventListener('resize', onScrollGuia)
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
  if (!form.value.grupo) faltantes.push('Indica la clase del encuentro.')
  if ((form.value.num_equipos || 0) < 1) faltantes.push('Indica el número de grupos.')
  if (form.value.alumnados.length === 0) faltantes.push('Reparte el alumnado en los grupos.')
  else if (alumnadosSinEquipo.value.length > 0) faltantes.push('Hay alumnado sin grupo asignado.')
  return faltantes
})

// ─── Asistente de 4 pasos (diseño crear_encuentro.png) ───────────────────────
// Solo cambia la presentación: los datos y el guardado (POST /encuentros) son los mismos.
const pasosEncuentro = [
  { num: 1, label: 'Datos generales' },
  { num: 2, label: 'Grupo y alumnado' },
  { num: 3, label: 'Confirmación' },
  { num: 4, label: 'Completado' },
]
const pasoEnc        = ref(1)
const pasoEncMax     = ref(1)
const intentoAvanzar = ref(false)
watch(pasoEnc, (v) => {
  if (v > pasoEncMax.value) pasoEncMax.value = v
  intentoAvanzar.value = false
  window.scrollTo({ top: 0, behavior: 'smooth' })
})

const faltantesPaso1 = computed(() => {
  const f = []
  if (!proyectoAsociado.value) f.push('Asocia un proyecto al encuentro.')
  if (!form.value.fecha) f.push('Establece la fecha de trabajo.')
  if (!form.value.curso) f.push('Indica el curso del encuentro.')
  if (!form.value.grupo) f.push('Indica la clase del encuentro.')
  return f
})
const faltantesPaso2 = computed(() => {
  const f = []
  if ((form.value.num_equipos || 0) < 1) f.push('Indica el número de grupos.')
  if (form.value.alumnados.length === 0) f.push('Reparte el alumnado en los grupos.')
  else if (alumnadosSinEquipo.value.length > 0) f.push('Hay alumnado sin grupo asignado.')
  return f
})
const faltantesPaso = computed(() =>
  pasoEnc.value === 1 ? faltantesPaso1.value
  : pasoEnc.value === 2 ? faltantesPaso2.value
  : requisitosFaltantes.value
)
function siguientePasoEnc() {
  intentoAvanzar.value = true
  if (faltantesPaso.value.length) return
  pasoEnc.value++
}
// Se puede volver a cualquier paso ya visitado, y avanzar solo si los anteriores están completos.
function puedeIrAPasoEnc(num) {
  if (pasoEnc.value === 4 || num === 4) return false
  if (num >= 2 && faltantesPaso1.value.length) return false
  if (num >= 3 && faltantesPaso2.value.length) return false
  return true
}
function cambiarProyecto() {
  limpiarProyecto()
  abrirBuscadorProy()
}
function crearOtroEncuentro() {
  limpiarProyecto()
  form.value.fecha  = new Date().toISOString().slice(0, 10)
  form.value.grupo  = ''
  form.value.num_alumnos = ''
  form.value.notas  = ''
  guardadoOk.value  = false
  pasoEncMax.value  = 1
  pasoEnc.value     = 1
}

function alumnosTexto(n) { return n ? `${n} ${n === 1 ? 'alumno' : 'alumnos'}` : '' }

// Columna derecha: ficha clave/valor con lo que se va rellenando.
const datosResumenEncuentro = computed(() => [
  { icono: 'centro',     label: 'Centro educativo', valor: authStore.userCentroNombre || form.value.centro_educativo },
  { icono: 'clase',      label: 'Curso y clase',    valor: [form.value.curso && `${form.value.curso} curso`, form.value.grupo && `Clase ${form.value.grupo}`].filter(Boolean).join(' · ') },
  { icono: 'calendario', label: 'Fecha de trabajo', valor: proyectoAsociado.value && form.value.fecha ? formatFecha(form.value.fecha) : '' },
  { icono: 'reloj',      label: 'Fecha fin estimada', valor: proyectoAsociado.value && fechaFinEstimada.value ? formatFecha(fechaFinEstimada.value) : '' },
  { icono: 'alumnado',   label: 'Alumnado',         valor: alumnosTexto(form.value.alumnados.length || Number(form.value.num_alumnos) || 0) },
  { icono: 'grupos',     label: 'Grupos',           valor: pasoEncMax.value >= 2 ? `${form.value.num_equipos || 3} grupos` : '' },
].filter(d => d.valor))

const CONSEJO_PASO_ENC = {
  1: 'Verifica que el proyecto y los datos sean correctos. Podrás modificar la información del encuentro más adelante.',
  2: 'Usa solo alias (nombre de pila + animal + color): nunca nombres reales completos del alumnado.',
  3: 'Revisa el resumen. Al guardar, los grupos quedarán listos para generar su código de acceso.',
  4: 'Desde «Mis grupos» generarás el código de acceso y seguirás el avance de cada grupo.',
}
const antesDeContinuarEnc = computed(() => {
  if (intentoAvanzar.value && faltantesPaso.value.length && pasoEnc.value < 4)
    return { tipo: 'aviso', titulo: 'Falta completar', texto: faltantesPaso.value[0] }
  return { tipo: 'info', titulo: pasoEnc.value === 4 ? 'Siguiente paso' : 'Antes de continuar', texto: CONSEJO_PASO_ENC[pasoEnc.value] }
})

// Paso 3: resumen para confirmar.
const confirmacionEncuentro = computed(() => [
  { label: 'Proyecto',         valor: proyectoSeleccionado.value?.titulo || '—', ancho: true },
  { label: 'Fecha de trabajo', valor: formatFecha(form.value.fecha) || '—' },
  { label: 'Fecha fin estimada', valor: fechaFinEstimada.value ? formatFecha(fechaFinEstimada.value) : '—' },
  { label: 'Centro educativo', valor: authStore.userCentroNombre || form.value.centro_educativo || '—' },
  { label: 'Ciclo formativo',  valor: form.value.ciclo_formativo || '—' },
  { label: 'Curso y clase',    valor: `${form.value.curso || '—'} · Clase ${form.value.grupo || '—'}` },
  { label: 'Número de alumnos', valor: form.value.num_alumnos || '—' },
  { label: 'Grupos y alumnado', valor: `${form.value.num_equipos || 3} grupos · ${alumnosTexto(form.value.alumnados.length) || '0 alumnos'} con alias`, ancho: true },
])

// Microproyecto.curso es un valor controlado: dígito o 'ambos_cursos'.
function cursoProyecto(c) {
  if (!c) return ''
  return c === 'ambos_cursos' ? '1º y 2º curso' : `${String(c).replace('º', '')}º curso`
}

function formatFecha(isoDate) {
  if (!isoDate) return ''
  const d = new Date(isoDate + 'T12:00:00')
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' })
}
</script>

<template>
  <div class="min-h-screen font-sans text-azul-noche pt-16">

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
          <div class="bg-azul-noche border border-white/15 rounded-2xl shadow-2xl p-4 text-white">
            <!-- Progreso -->
            <div class="flex items-center justify-between mb-2.5">
              <div class="flex gap-1 items-center">
                <span v-for="i in TOTAL_PASOS_GUIA" :key="i"
                      class="h-[3px] rounded-full transition-all duration-300"
                      :class="i <= pasoGuia ? 'bg-rol-fuerte w-5' : 'bg-white/20 w-3'" />
              </div>
              <span class="text-xs font-bold text-white/40">{{ pasoGuia }}/{{ TOTAL_PASOS_GUIA }}</span>
            </div>

            <p class="text-xs text-white/85 leading-relaxed mb-3">{{ guiaPasosData[pasoGuia - 1].texto }}</p>

            <div class="flex items-center gap-2">
              <button @click="avanzarGuia"
                      class="flex-1 py-2.5 rounded-full bg-rol-fuerte text-white
                             text-xs font-bold uppercase tracking-widest
                             hover:bg-rol/90 transition-all">
                {{ pasoGuia < TOTAL_PASOS_GUIA ? 'Siguiente →' : 'Finalizar' }}
              </button>
              <button @click="saltarGuia"
                      class="px-3 py-2.5 rounded-full bg-white/5 border border-white/10
                             text-white/40 text-xs font-bold uppercase tracking-widest
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
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[min(600px,100vw)] h-[400px]
                bg-rol-fuerte opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <!-- Asistente de 4 pasos + columna derecha (diseño crear_encuentro.png) -->
    <div class="relative z-10 mx-auto max-w-[90rem] px-4 pb-10 pt-5 sm:px-6 lg:px-5 xl:px-8">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-5 xl:gap-8">
      <div class="min-w-0 flex-1 space-y-5">

      <CabeceraSeccion plano titulo="Crear" destacado="encuentro" color="text-rol-fuerte"
                       volver="/encuentros" volver-label="Volver a encuentros"
                       subtitulo="Selecciona un reto y registra los datos de la clase.">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Solo icono por debajo de 2xl: deja sitio al título «Crear encuentro» en una línea -->
          <RouterLink to="/encuentros" class="accion-secundaria max-2xl:w-10 max-2xl:px-0" title="Ver todos tus encuentros"
                      aria-label="Ver todos tus encuentros">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <span class="max-2xl:sr-only">Ver encuentros</span>
          </RouterLink>
          <!-- Botón activar guía (paso del tour: refBtnGuia) -->
          <button ref="refBtnGuia" type="button" @click="modoGuia = true; pasoGuia = 1"
                  aria-label="Ayuda: guía paso a paso de esta pantalla"
                  class="accion-secundaria max-sm:w-10 max-sm:px-0" title="Guía paso a paso de esta pantalla">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="max-sm:sr-only">Ayuda</span>
          </button>
        </div>
      </CabeceraSeccion>

      <TallerPasos variante="linea" etiqueta="Pasos para crear el encuentro"
                   :pasos="pasosEncuentro" :actual="pasoEnc" :maximo="pasoEncMax" :puede-ir="puedeIrAPasoEnc"
                   @ir="pasoEnc = $event" />

      <!-- ═══ PASO 1 · DATOS GENERALES ═══ -->
      <template v-if="pasoEnc === 1">

        <!-- Reto y proyecto asociado -->
        <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-proyecto-asociado">
          <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="flex min-w-0 flex-1 items-start gap-3 sm:gap-4">
              <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol-fuerte text-white sm:h-12 sm:w-12" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
              </span>
              <div class="min-w-0">
                <h2 id="titulo-proyecto-asociado" class="text-lg font-bold text-azul-noche">Reto y proyecto asociado</h2>
                <p class="mt-0.5 text-sm text-texto-secundario">Selecciona el proyecto con el que se trabajará en este encuentro.</p>
              </div>
            </div>
            <button v-if="proyectoSeleccionado && !mostrarBuscadorProy" type="button" @click="cambiarProyecto"
                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 self-start rounded-full border border-rol-fuerte/30 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              Cambiar proyecto
            </button>
          </div>

          <!-- Proyecto seleccionado: miniatura + datos -->
          <div v-if="proyectoSeleccionado && !mostrarBuscadorProy"
               class="flex flex-col gap-4 rounded-xl bg-rol/5 p-3 sm:flex-row sm:items-center sm:p-4">
            <MiniaturaReto :src="proyectoSeleccionado.imagen_portada_url || ''" class="h-32 w-full sm:h-28 sm:w-40" />
            <div class="min-w-0 flex-1">
              <p class="font-heading text-base font-bold leading-snug text-azul-noche break-words sm:text-lg">{{ proyectoSeleccionado.titulo }}</p>
              <p v-if="proyectoSeleccionado.diseno_reto?.pregunta_reto" class="mt-1 text-sm italic leading-relaxed text-azul-noche sm:text-base">
                «{{ proyectoSeleccionado.diseno_reto.pregunta_reto }}»
              </p>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <span :class="estadoProyectoBadge(proyectoSeleccionado).cls"
                      class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide">
                  {{ estadoProyectoBadge(proyectoSeleccionado).label }}
                </span>
                <span v-if="proyectoSeleccionado.empresa_nombre"
                      class="inline-flex max-w-full items-center rounded-full bg-white px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-azul-noche">
                  <span class="truncate">{{ proyectoSeleccionado.empresa_nombre }}</span>
                </span>
              </div>
              <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-texto-secundario">
                <span v-if="proyectoSeleccionado.familia_nombre" class="inline-flex min-w-0 items-center gap-1.5">
                  <svg class="h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  <span class="break-words">{{ proyectoSeleccionado.familia_nombre }}</span>
                </span>
                <span v-if="proyectoSeleccionado.curso" class="inline-flex items-center gap-1.5">
                  <svg class="h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  {{ cursoProyecto(proyectoSeleccionado.curso) }}
                </span>
                <span v-if="proyectoSeleccionado.ciclo_nombre" class="inline-flex min-w-0 items-center gap-1.5">
                  <svg class="h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m0-6l6.16-3.422A12.083 12.083 0 0118.82 17.5L12 21l-6.82-3.5a12.08 12.08 0 01.66-6.922L12 14z"/></svg>
                  <span class="break-words">{{ proyectoSeleccionado.ciclo_nombre }}</span>
                </span>
              </div>
            </div>
          </div>

              <!-- Buscador -->
              <div v-else-if="mostrarBuscadorProy" class="space-y-4">

                <!-- Filtros -->
                <div class="flex flex-wrap gap-2">
                  <!-- Búsqueda texto -->
                  <div class="relative flex-1 min-w-40">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-texto-secundario pointer-events-none"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input v-model="busquedaProy" type="text"
                           placeholder="Buscar por título o empresa..."
                           class="field-input pl-9 !text-xs" />
                  </div>

                  <!-- Filtro estado -->
                  <select v-model="filtroProyEstado" class="field-input !w-auto !text-xs cursor-pointer">
                    <option value="">Todos (validado o completado)</option>
                    <option value="validado">Validado</option>
                    <option value="completado">Completado</option>
                  </select>
                </div>

                <!-- Filtros Ciclo + Curso -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                              class="flex-1 inline-flex h-9 items-center justify-center rounded-full text-xs font-bold uppercase tracking-widest
                                     border transition-all"
                              :class="filtroProyCurso === op.val
                                ? 'bg-primary-400 border-primary-400 text-white'
                                : 'bg-gray-50 border-borde text-texto-secundario hover:border-primary-400/40 hover:text-primary-700'">
                        {{ op.label }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Contador + limpiar + cancelar -->
                <div class="flex items-center justify-between">
                  <p class="text-xs text-texto-secundario font-medium">
                    <span v-if="cargandoProyectos">Cargando proyectos...</span>
                    <span v-else>
                      {{ proyectosFiltrados.length }}
                      {{ proyectosFiltrados.length === 1 ? 'proyecto' : 'proyectos' }}
                    </span>
                  </p>
                  <div class="flex gap-3">
                    <button v-if="filtroProyCurso || filtroProyCiclo || filtroProyEstado || busquedaProy"
                            @click="filtroProyCurso = ''; filtroProyCiclo = ''; filtroProyEstado = ''; busquedaProy = ''"
                            class="min-h-9 rounded-full px-2 py-2 text-xs font-bold uppercase tracking-widest text-texto-secundario
                                   hover:text-red-400 transition-colors">
                      Limpiar
                    </button>
                    <button @click="cerrarBuscadorProy"
                            class="min-h-9 rounded-full px-2 py-2 text-xs font-bold uppercase tracking-widest text-texto-secundario
                                   hover:text-texto-secundario transition-colors">
                      Cancelar
                    </button>
                  </div>
                </div>

                <!-- Spinner -->
                <div v-if="cargandoProyectos" class="flex justify-center py-8">
                  <svg class="animate-spin w-6 h-6 text-primary-400" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                  </svg>
                </div>

                <!-- Grid de resultados -->
                <div v-else-if="proyectosFiltrados.length"
                     class="space-y-1.5 max-h-72 overflow-y-auto pr-1 -mr-1">
                  <div v-for="p in proyectosFiltrados" :key="p.uuid"
                       class="flex items-stretch rounded-xl border border-borde bg-gray-50
                              hover:border-primary-400/30 hover:bg-primary-400/5 transition-all group">
                    <!-- Zona seleccionar (clic principal) -->
                    <button @click="seleccionarProyecto(p)"
                            class="flex-1 text-left px-4 py-3 min-w-0">
                      <p class="text-sm font-bold text-azul-noche leading-snug
                                group-hover:text-primary-700 transition-colors line-clamp-2">
                        {{ p.titulo }}
                      </p>
                      <div class="flex flex-wrap gap-1.5 mt-1.5">
                        <span :class="estadoProyectoBadge(p).cls"
                              class="px-2 py-0.5 rounded-full text-xs font-bold uppercase tracking-widest">
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
                            class="shrink-0 flex items-center justify-center px-3 border-l border-borde
                                   text-texto-secundario hover:text-primary-700 hover:bg-primary-400/10
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
                <div v-else class="text-center py-8 text-texto-secundario">
                  <svg class="w-8 h-8 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                  </svg>
                  <p class="text-xs font-medium">Sin proyectos para esos filtros</p>
                </div>

              </div>

          <!-- Estado inicial — sin selector abierto ni proyecto -->
          <div v-else class="space-y-3">
            <p class="flex items-start gap-2 rounded-xl bg-rol/5 px-3 py-2.5 text-sm text-azul-noche">
              <svg class="mt-0.5 h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>
                Solo se pueden asociar proyectos validados. Los proyectos se crean en el
                <button type="button" @click="router.push({ name: 'startup-day-crear' })"
                        class="font-semibold text-rol-fuerte underline hover:no-underline">Taller de Ideas</button>.
              </span>
            </p>
            <button type="button" @click="abrirBuscadorProy"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-rol-fuerte px-5 text-sm font-semibold text-white shadow-sm hover:bg-rol-fuerte/90">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
              Buscar proyecto
            </button>
          </div>
        </section>

        <!-- Datos del encuentro -->
        <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-datos-encuentro"
                 :class="{ 'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'datos' }">
          <div class="mb-5 flex items-start gap-3 sm:gap-4">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol-fuerte text-white sm:h-12 sm:w-12" aria-hidden="true">
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <div class="min-w-0">
              <h2 id="titulo-datos-encuentro" class="text-lg font-bold text-azul-noche">Datos del encuentro</h2>
              <p class="mt-0.5 text-sm text-texto-secundario">Define la información básica de la sesión.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-x-5 gap-y-4 sm:grid-cols-2">
            <div ref="refCampoFecha" :class="{ 'tour-active': tourTargetActivo === 'refCampoFecha' }" class="min-w-0 rounded-xl transition-all">
              <label for="enc-fecha" class="field-label">Fecha de trabajo <span class="text-red-600" aria-hidden="true">*</span></label>
              <input id="enc-fecha" v-model="form.fecha" type="date" class="field-input" :disabled="!proyectoAsociado" required />
              <p v-if="!proyectoAsociado" class="mt-1.5 text-xs font-medium text-amber-800">
                Asocia primero un proyecto para poder establecer la fecha de trabajo.
              </p>
              <p v-else-if="fechaFinEstimada" class="mt-1.5 text-xs text-texto-secundario">
                Fecha fin estimada: <span class="font-semibold text-azul-noche">{{ formatFecha(fechaFinEstimada) }}</span>
                ({{ totalClasesProyectoSeleccionado }} clases). Podrás ajustarla luego en el detalle del encuentro.
              </p>
            </div>

            <div ref="refCampoCentro" :class="{ 'tour-active': tourTargetActivo === 'refCampoCentro' }" class="min-w-0 rounded-xl transition-all">
              <label for="enc-centro" class="field-label">Centro educativo</label>
              <div v-if="authStore.userCentroNombre" id="enc-centro" class="field-input cursor-default select-none bg-gray-50 text-texto-secundario">
                {{ authStore.userCentroNombre }}
              </div>
              <template v-else>
                <input id="enc-centro" v-model="form.centro_educativo" type="text" list="centros-datalist"
                       placeholder="Ej. IES Aguas Nuevas" class="field-input" />
                <datalist id="centros-datalist">
                  <option v-for="c in centrosParaAutocompletar" :key="c" :value="c" />
                </datalist>
              </template>
            </div>

            <div class="min-w-0 sm:col-span-2">
              <label for="enc-ciclo" class="field-label">Ciclo formativo</label>
              <input id="enc-ciclo" v-model="form.ciclo_formativo" type="text" list="ciclos-datalist"
                     placeholder="Ej. CFGM Sistemas Microinformáticos" class="field-input" />
              <datalist id="ciclos-datalist">
                <option v-for="c in ciclosParaAutocompletar" :key="c" :value="c" />
              </datalist>
            </div>

            <fieldset class="min-w-0">
              <legend class="field-label">Curso <span class="text-red-600" aria-hidden="true">*</span></legend>
              <div class="grid grid-cols-2 gap-2">
                <button v-for="c in ['1º', '2º']" :key="c" type="button" @click="form.curso = c"
                        :aria-pressed="form.curso === c"
                        class="h-10 rounded-full border text-sm font-semibold transition-colors"
                        :class="form.curso === c
                          ? 'border-rol-fuerte bg-rol-fuerte text-white shadow-sm'
                          : 'border-borde bg-white text-azul-noche hover:border-rol-fuerte/40 hover:text-rol-fuerte'">
                  {{ c }}
                </button>
              </div>
            </fieldset>
            <fieldset ref="refCampoAlumnado" class="min-w-0 rounded-xl transition-all" :class="{ 'tour-active': tourTargetActivo === 'refCampoAlumnado' }">
              <legend class="field-label">Clase <span class="text-red-600" aria-hidden="true">*</span></legend>
              <div class="grid grid-cols-4 gap-2">
                <button v-for="g in ['A', 'B', 'C', 'D']" :key="g" type="button" @click="form.grupo = g"
                        :aria-pressed="form.grupo === g" :aria-label="`Clase ${g}`"
                        class="h-10 rounded-full border text-sm font-semibold transition-colors"
                        :class="form.grupo === g
                          ? 'border-rol-fuerte bg-rol-fuerte text-white shadow-sm'
                          : 'border-borde bg-white text-azul-noche hover:border-rol-fuerte/40 hover:text-rol-fuerte'">
                  {{ g }}
                </button>
              </div>
            </fieldset>
            <div class="min-w-0">
              <label for="enc-num-alumnos" class="field-label">Número de alumnos</label>
              <input id="enc-num-alumnos" v-model="form.num_alumnos" type="number" min="1" max="99"
                     placeholder="Ej. 24" class="field-input" />
            </div>
          </div>
        </section>
      </template>

      <!-- ═══ PASO 2 · GRUPO Y ALUMNADO ═══ -->
      <section v-else-if="pasoEnc === 2" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-grupo-alumnado">
        <div class="mb-5 flex items-start gap-3 sm:gap-4">
          <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol-fuerte text-white sm:h-12 sm:w-12" aria-hidden="true">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          </span>
          <div class="min-w-0">
            <h2 id="titulo-grupo-alumnado" class="text-lg font-bold text-azul-noche">Grupo y alumnado</h2>
            <p class="mt-0.5 text-sm text-texto-secundario">Indica cuántos grupos habrá y reparte al alumnado (solo con alias).</p>
          </div>
        </div>
        <div class="space-y-4">
                <!-- Número de equipos -->
                <div>
                  <label class="field-label">Número de grupos <span class="text-red-400">*</span></label>
                  <div class="flex items-center gap-2 mt-1">
                    <button @click="form.num_equipos = Math.max(1, (form.num_equipos || 3) - 1)"
                            :disabled="!fechaEstablecida"
                            class="w-9 h-9 rounded-full bg-gray-100 border border-borde
                                   text-texto-secundario font-bold text-sm hover:bg-gray-200 transition-all
                                   disabled:opacity-40 disabled:cursor-not-allowed">−</button>
                    <span class="w-8 text-center text-base font-bold text-azul-noche">{{ form.num_equipos || 3 }}</span>
                    <button @click="form.num_equipos = Math.min(20, (form.num_equipos || 3) + 1)"
                            :disabled="!fechaEstablecida"
                            class="w-9 h-9 rounded-full bg-gray-100 border border-borde
                                   text-texto-secundario font-bold text-sm hover:bg-gray-200 transition-all
                                   disabled:opacity-40 disabled:cursor-not-allowed">+</button>
                    <span class="text-xs text-texto-secundario font-medium ml-1">grupos · se crearán al generar el código</span>
                  </div>
                </div>

                <p v-if="!fechaEstablecida" class="text-xs text-amber-500 font-medium">
                  Establece la fecha de trabajo para repartir el alumnado en grupos.
                </p>

                <!-- Alumnado por equipos — siempre visible -->
                <div ref="refCampoEquipos" class="space-y-3 rounded-xl transition-all"
                     :class="{ 'tour-active': tourTargetActivo === 'refCampoEquipos' }">

                  <label class="field-label">
                    Alumnado por grupos <span class="text-red-400">*</span>
                    <span v-if="limiteAlumnados" class="ml-2 text-xs font-semibold"
                          :class="limiteAlcanzado ? 'text-red-400' : 'text-texto-secundario'">
                      {{ form.alumnados.length }}/{{ limiteAlumnados }}
                    </span>
                  </label>

                  <AvisoSinNombresReales para="docente" />

                  <!-- Formulario añadir alumno/a: nombre de pila; el grupo es la tarjeta seleccionada
                       (se elige tocando la tarjeta del grupo). Animal y color se generan. -->
                  <p class="flex flex-wrap items-center gap-2 text-sm text-texto-secundario" aria-live="polite">
                    Añadiendo a:
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rol-fuerte px-3 py-1 text-sm font-semibold text-white">
                      <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-white/20 text-xs font-bold">{{ nuevoAlumnadoEquipo }}</span>
                      Grupo {{ nuevoAlumnadoEquipo }}
                    </span>
                    <span class="text-xs">· toca otra tarjeta para cambiar de grupo</span>
                  </p>
                  <div class="flex flex-wrap gap-2">
                    <input v-model="nuevoAlumnadoNombre" type="text" maxlength="30"
                           placeholder="Nombre de pila (sin apellidos)" aria-label="Nombre de pila del alumno/a"
                           class="field-input flex-1 min-w-0 basis-40 !text-sm"
                           :disabled="!fechaEstablecida"
                           @input="errorNuevoAlumnado = ''"
                           @keyup.enter="addAlumnadoEncuentro" />
                    <button @click="addAlumnadoEncuentro"
                            :disabled="limiteAlcanzado || !fechaEstablecida"
                            class="inline-flex h-10 shrink-0 items-center rounded-full bg-rol-fuerte px-4 text-sm font-semibold text-white
                                   transition-colors hover:bg-rol-fuerte/90 disabled:cursor-not-allowed disabled:opacity-40">
                      + Añadir a Grupo {{ nuevoAlumnadoEquipo }}
                    </button>
                  </div>
                  <p v-if="errorNuevoAlumnado" class="text-xs font-semibold text-red-500">{{ errorNuevoAlumnado }}</p>

                  <!-- Copiar alumnado de un encuentro anterior del mismo docente -->
                  <div v-if="encuentrosParaCopiar.length">
                    <button v-if="!copiandoAlumnado" type="button" @click="copiandoAlumnado = true; mensajeCopiaAlumnado = ''"
                            :disabled="!fechaEstablecida"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-full border border-rol/30 bg-white px-3 py-2
                                   text-xs font-bold text-rol-fuerte transition-all hover:bg-rol/5
                                   disabled:opacity-40 disabled:cursor-not-allowed">
                      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                      </svg>
                      Copiar alumnado de un encuentro anterior
                    </button>
                    <div v-else class="space-y-2 rounded-xl border border-rol/20 bg-rol/5 p-3">
                      <label class="block text-xs font-bold uppercase tracking-widest text-rol-fuerte" for="encuentro-a-copiar">
                        ¿De qué encuentro copias el alumnado?
                      </label>
                      <select id="encuentro-a-copiar" v-model="encuentroACopiar" class="field-input w-full !text-sm cursor-pointer">
                        <option value="" disabled>Elige uno de tus encuentros…</option>
                        <option v-for="e in encuentrosParaCopiar" :key="e.id" :value="e.id">
                          {{ e.etiqueta }} · {{ e.alumnado.length }} alumnos
                        </option>
                      </select>
                      <label class="flex items-center gap-2 text-xs text-texto-secundario cursor-pointer">
                        <input v-model="copiarConReparto" type="checkbox" class="h-4 w-4 rounded accent-rol-fuerte" />
                        Mantener los mismos grupos (si no, se reparten equilibrados)
                      </label>
                      <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="copiandoAlumnado = false; encuentroACopiar = ''"
                                class="min-h-9 rounded-full px-3 py-2 text-xs font-bold text-texto-secundario hover:text-azul-noche">
                          Cancelar
                        </button>
                        <button type="button" @click="copiarAlumnadoDeEncuentro" :disabled="!encuentroACopiar"
                                class="min-h-9 rounded-full bg-rol-fuerte px-4 py-2 text-xs font-bold text-white transition-all hover:bg-rol/90
                                       disabled:opacity-40 disabled:cursor-not-allowed">
                          Copiar alumnado
                        </button>
                      </div>
                    </div>
                    <p v-if="mensajeCopiaAlumnado" class="mt-1.5 text-xs font-semibold text-rol-fuerte">{{ mensajeCopiaAlumnado }}</p>
                  </div>

                  <!-- Grid de equipos -->
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <!-- Tocar la tarjeta la selecciona como destino de «Añadir». La cabecera es el
                         botón accesible (teclado / lector); el clic en el resto de la tarjeta también
                         vale, salvo en sus propios controles (mover de grupo, dado, quitar). -->
                    <div v-for="n in (form.num_equipos || 3)" :key="n"
                         class="cursor-pointer rounded-xl border p-3 transition-all"
                         :class="Number(nuevoAlumnadoEquipo) === n
                           ? 'border-rol-fuerte bg-rol/5 ring-2 ring-rol/30'
                           : 'border-borde bg-gray-50/50 hover:border-rol/40 hover:bg-rol/5'"
                         @click="e => { if (!e.target.closest('select, button:not([data-elegir-grupo]), input')) nuevoAlumnadoEquipo = n }">
                      <button type="button" data-elegir-grupo @click="nuevoAlumnadoEquipo = n"
                              :aria-pressed="Number(nuevoAlumnadoEquipo) === n"
                              :aria-label="`Grupo ${n}: añadir aquí el alumnado (${alumnadosDeEquipo(n).length} ${alumnadosDeEquipo(n).length === 1 ? 'alumno' : 'alumnos'})`"
                              class="-m-1 mb-1 flex w-[calc(100%+0.5rem)] items-center gap-1.5 rounded-lg p-1 text-left">
                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors"
                              :class="Number(nuevoAlumnadoEquipo) === n ? 'bg-rol-fuerte text-white' : 'bg-rol/10 text-rol-fuerte'">{{ n }}</span>
                        <span class="flex-1 truncate text-xs font-bold uppercase tracking-wide"
                              :class="Number(nuevoAlumnadoEquipo) === n ? 'text-rol-fuerte' : 'text-texto-secundario'">
                          Grupo {{ n }}
                        </span>
                        <span v-if="Number(nuevoAlumnadoEquipo) === n"
                              class="inline-flex shrink-0 items-center gap-1 rounded-full bg-rol-fuerte px-2 py-0.5 text-xs font-bold text-white">
                          <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                          Seleccionado
                        </span>
                        <span class="shrink-0 text-xs text-texto-secundario">{{ alumnadosDeEquipo(n).length }}</span>
                      </button>
                      <div v-if="alumnadosDeEquipo(n).length" class="space-y-1.5">
                        <div v-for="a in alumnadosDeEquipo(n)" :key="a._i" class="flex items-center gap-2 text-xs">
                          <span class="flex-1 min-w-0 truncate font-medium text-azul-noche">{{ a.alias }}</span>
                          <select :value="a.equipo_num" @change="form.alumnados[a._i].equipo_num = Number($event.target.value)"
                                  :aria-label="`Grupo de ${a.alias}`"
                                  class="h-9 shrink-0 cursor-pointer rounded-xl border border-borde bg-white px-1.5 text-xs font-bold text-texto-secundario">
                            <option v-for="g in (form.num_equipos || 3)" :key="g" :value="g">G{{ g }}</option>
                          </select>
                          <RegenerarAliasButton :alias="a.alias" :usados="aliasUsados"
                                                 @generado="alias => (form.alumnados[a._i].alias = alias)" />
                          <button @click="removeAlumnadoEncuentro(a._i)" :aria-label="`Quitar a ${a.alias}`"
                                  class="-my-1.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base font-bold leading-none text-texto-secundario transition-colors hover:bg-red-50 hover:text-red-400">×</button>
                        </div>
                      </div>
                      <p v-else class="text-xs text-texto-secundario italic">Sin alumnos</p>
                    </div>
                  </div>

                  <!-- Sin equipo asignado -->
                  <div v-if="alumnadosSinEquipo.length"
                       class="rounded-xl border border-amber-100 bg-amber-50/50 p-3">
                    <p class="text-xs font-bold uppercase tracking-widest text-amber-500 mb-2">
                      Sin grupo asignado
                    </p>
                    <div class="space-y-1.5">
                      <div v-for="a in alumnadosSinEquipo" :key="a._i" class="flex items-center gap-2 text-xs">
                        <span class="flex-1 min-w-0 truncate font-medium text-amber-700">{{ a.alias }}</span>
                        <select :value="''" @change="form.alumnados[a._i].equipo_num = Number($event.target.value)"
                                :aria-label="`Asignar grupo a ${a.alias}`"
                                class="h-9 shrink-0 cursor-pointer rounded-xl border border-amber-200 bg-white px-1.5 text-xs font-bold text-amber-700">
                          <option value="" disabled>Grupo</option>
                          <option v-for="g in (form.num_equipos || 3)" :key="g" :value="g">G{{ g }}</option>
                        </select>
                        <RegenerarAliasButton :alias="a.alias" :usados="aliasUsados"
                                               @generado="alias => (form.alumnados[a._i].alias = alias)" />
                        <button @click="removeAlumnadoEncuentro(a._i)" :aria-label="`Quitar a ${a.alias}`"
                                class="-my-1.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base font-bold leading-none text-amber-300 transition-colors hover:bg-red-50 hover:text-red-400">×</button>
                      </div>
                    </div>
                  </div>

                </div>
        </div>
      </section>

      <!-- ═══ PASO 3 · CONFIRMACIÓN ═══ -->
      <section v-else-if="pasoEnc === 3" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-confirmacion">
        <div class="mb-5 flex items-start gap-3 sm:gap-4">
          <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol-fuerte text-white sm:h-12 sm:w-12" aria-hidden="true">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </span>
          <div class="min-w-0">
            <h2 id="titulo-confirmacion" class="text-lg font-bold text-azul-noche">Confirmación</h2>
            <p class="mt-0.5 text-sm text-texto-secundario">Revisa los datos antes de guardar el encuentro.</p>
          </div>
        </div>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2">
          <div v-for="d in confirmacionEncuentro" :key="d.label" class="min-w-0" :class="d.ancho ? 'sm:col-span-2' : ''">
            <dt class="text-xs font-semibold uppercase tracking-wide text-texto-secundario">{{ d.label }}</dt>
            <dd class="mt-0.5 break-words text-sm font-semibold text-azul-noche">{{ d.valor }}</dd>
          </div>
        </dl>
        <button type="button" @click="pasoEnc = 2"
                class="mt-2 inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">
          Revisar grupos y alumnado
        </button>

        <div ref="refCampoNotas" class="mt-4 rounded-xl transition-all" :class="{ 'tour-active': tourTargetActivo === 'refCampoNotas' }">
          <label for="enc-notas" class="field-label">Notas adicionales</label>
          <textarea id="enc-notas" v-model="form.notas" rows="3"
                    placeholder="Observaciones, adaptaciones realizadas, valoración… (sin nombres reales del alumnado)"
                    class="field-input resize-none" />
        </div>

        <!-- Cuenta docente — asignada automáticamente al guardar -->
        <div class="mt-4 flex items-center gap-3 rounded-xl border border-rol/15 bg-rol/5 px-4 py-3">
          <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte" aria-hidden="true">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-rol-fuerte">Cuenta docente asociada</p>
            <p class="truncate text-sm font-semibold text-azul-noche">{{ authStore.userName }}</p>
          </div>
          <span class="shrink-0 rounded-full bg-rol/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-rol-fuerte">Auto</span>
        </div>

        <p v-if="errorGuardado" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">
          {{ errorGuardado }}
        </p>
      </section>

      <!-- ═══ PASO 4 · COMPLETADO ═══ -->
      <section v-else class="rounded-2xl border border-borde bg-white p-6 text-center shadow-sm sm:p-10" aria-labelledby="titulo-completado">
        <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-empresas-dark text-white" aria-hidden="true">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
        </span>
        <h2 id="titulo-completado" class="mt-4 text-xl font-bold text-azul-noche sm:text-2xl">Encuentro creado</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-texto-secundario">
          Ya tienes los grupos listos. Desde «Mis grupos» generarás el código de acceso del alumnado y seguirás su avance.
        </p>
        <div class="mt-6 flex flex-wrap justify-center gap-2">
          <RouterLink :to="{ name: 'mis-grupos' }" class="accion-principal">Ir a Mis grupos</RouterLink>
          <RouterLink to="/encuentros" class="accion-secundaria">Ver encuentros</RouterLink>
          <button type="button" @click="crearOtroEncuentro" class="accion-secundaria">Crear otro encuentro</button>
        </div>
      </section>

      <!-- Lo que falta para avanzar (se muestra al intentar seguir) -->
      <div v-if="pasoEnc < 4 && intentoAvanzar && faltantesPaso.length"
           class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3" role="alert">
        <ul class="space-y-1">
          <li v-for="(msg, i) in faltantesPaso" :key="i" class="flex items-start gap-2 text-sm font-medium text-amber-900">
            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-600" aria-hidden="true" /> {{ msg }}
          </li>
        </ul>
      </div>

      <!-- Botones de paso -->
      <div v-if="pasoEnc < 4" ref="refBtnGuardar" class="fila-pasos rounded-xl transition-all"
           :class="{ 'tour-active': tourTargetActivo === 'refBtnGuardar' }">
        <button v-if="pasoEnc === 1" type="button" @click="router.push('/encuentros')" class="btn-paso-atras">Cancelar</button>
        <button v-else type="button" @click="pasoEnc--" class="btn-paso-atras">← Anterior</button>
        <button v-if="pasoEnc < 3" type="button" @click="siguientePasoEnc" class="btn-paso">Siguiente →</button>
        <button v-else type="button" @click="guardarEncuentro" :disabled="guardando" class="btn-paso">
          <svg v-if="guardando" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
          {{ guardando ? 'Guardando…' : 'Guardar encuentro' }}
        </button>
      </div>

      </div><!-- /columna principal -->

      <EncuentroResumen ref="refEncuentros"
                        :proyecto="proyectoSeleccionado"
                        :estado="proyectoSeleccionado ? estadoProyectoBadge(proyectoSeleccionado) : null"
                        :datos="datosResumenEncuentro"
                        :antes="antesDeContinuarEnc"
                        @ver-proyecto="abrirProyectoModal(proyectoSeleccionado.uuid)"
                        @ver-reto="abrirMicroretoModal(proyectoSeleccionado.microreto_id)" />
      </div>
    </div>
  </div>

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

  <!-- Modal: ¿Activar guía-tour? -->
  <TourPromptModal
    :show="showTourPrompt"
    titulo="¿Quieres activar la guía-tour?"
    descripcion="Explora el panel docente con una guía paso a paso que te muestra cada función."
    @activar="activarTourDesdeModal"
    @omitir="omitirTourDesdeModal"
  />

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
  font-size: 0.875rem;
  font-weight: 600;
  color: #17283E; /* azul noche */
  margin-bottom: 0.375rem;
}
.field-input {
  width: 100%;
  background: #fff;
  border: 1px solid var(--color-borde-campo); /* 3:1 con el fondo (WCAG 1.4.11) */
  border-radius: 0.75rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #17283E;
  outline: none;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}
.field-input:focus {
  border-color: var(--color-rol-fuerte);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-rol-fuerte) 15%, transparent);
  background: #fff;
}
.field-input::placeholder { color: #6B7280; font-weight: 400; }
.field-input:disabled { opacity: 0.45; cursor: not-allowed; }
select.field-input { cursor: pointer; }
.tag {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.tag-gray  { background: #F3F4F6; color: #6B7280; }
.tag-green { background: rgba(48,114,170,0.1); color: #3072AA; }
.tag-lime  { background: rgba(107,164,213,0.12); color: #275d8a; }
.tag-amber { background: rgba(251,191,36,0.12); color: #92400e; }

/* Tour: sección activa */
.tour-active {
  box-shadow: 0 0 0 3px #3072AA, 0 0 0 8px rgba(48, 114, 170, 0.2), 0 4px 20px rgba(0,0,0,0.1) !important;
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
