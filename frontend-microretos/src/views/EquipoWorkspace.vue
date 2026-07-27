<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api.js'
import MicroretoModal from '../components/MicroretoModal.vue'
import {
  MegaphoneIcon,
  ClockIcon,
  EyeIcon,
  DocumentTextIcon,
} from '@heroicons/vue/24/outline'

const verFichaReto = ref(false)

const route  = useRoute()
const router = useRouter()

// Al pulsar "siguiente"/"completar" con campos obligatorios sin rellenar, en vez de bloquear
// el botón sin explicación, se resalta qué falta y se hace scroll al primer bloque incompleto.
function scrollAFaltante(el) {
  el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

// ── Estado global ──────────────────────────────────────────────────────────
const cargando  = ref(true)
const error     = ref(false)
const isLoaded  = ref(false)
const workspace = ref(null)
const faseVista = ref(0)
const guardando = ref(false)
const msgOk     = ref('')

const token = route.params.token

// ── Helpers de acceso a datos ─────────────────────────────────────────────
const equipo      = computed(() => workspace.value?.equipo)
const proyecto    = computed(() => workspace.value?.proyecto)
const diagnostico = computed(() => workspace.value?.diagnostico)
const preguntasF0 = computed(() => workspace.value?.preguntas_f0 ?? [])
const tareas      = computed(() => workspace.value?.tareas ?? [])
const reflexiones = computed(() => workspace.value?.reflexiones ?? [])

function getFase(n) {
  return workspace.value?.fases?.[n] ?? { numero_fase: n, datos: null, completada: false, validado_docente: false }
}

// Duración real (en clases) — nº de clases del calendario que cubren esta fase
function duracionFase(n) {
  const clases = proyecto.value?.diseno_microproyecto?.clases || []
  const count = clases.filter(c => (c.fases || []).includes(n)).length
  return count || null
}

// ── Definición visual de fases ─────────────────────────────────────────────
const fasesConfig = [
  { num: 0, label: 'Inicio del equipo',  shortLabel: 'Inicio',     icono: '👥', color: 'slate',  desc: 'Constitución del equipo',    descLarga: 'Conóceos, estableced roles y acordad cómo vais a trabajar juntos durante el reto. Esta fase no se evalúa, pero es clave para que todo lo demás funcione.' },
  { num: 1, label: 'Análisis del reto',  shortLabel: 'Análisis',   icono: '🔍', color: 'blue',   desc: 'Comprensión del reto',        descLarga: 'Analizad en profundidad el reto planteado por la empresa y definid vuestra propuesta de solución con datos concretos.' },
  { num: 2, label: 'Diseño de solución y desarrollo', shortLabel: 'Diseño',   icono: '💡', color: 'amber',  desc: 'Prototipo, tareas y desarrollo', descLarga: 'Diseñad y construid vuestra solución: definid el prototipo, dividid el trabajo en tareas y avanzad en la construcción. Registrad el progreso de cada tarea.' },
  { num: 3, label: 'Entrega de la solución',          shortLabel: 'Entrega', icono: '🔨', color: 'orange', desc: 'Entrega de la solución',         descLarga: 'Entregad la solución final que proponéis para cubrir la necesidad de la empresa, al docente y a la empresa validadora. Adjuntad el entregable (enlace y/o archivo).' },
  { num: 4, label: 'Presentación',       shortLabel: 'Presenta.',  icono: '🎓', color: 'green',  desc: 'Entrega y reflexión',         descLarga: 'Reflexionad individualmente y en grupo sobre lo aprendido. Es vuestro cierre del proyecto.' },
]

const colorMap = {
  slate:  { activo: 'bg-slate-600  border-slate-400  text-white',  done: 'bg-slate-500/20 border-slate-400/40 text-slate-300', lock: 'bg-white/5 border-white/10 text-white/20' },
  blue:   { activo: 'bg-blue-600   border-blue-400   text-white',  done: 'bg-blue-500/20  border-blue-400/40  text-blue-300',  lock: 'bg-white/5 border-white/10 text-white/20' },
  amber:  { activo: 'bg-amber-500  border-amber-400  text-white',  done: 'bg-amber-500/20 border-amber-400/40 text-amber-300', lock: 'bg-white/5 border-white/10 text-white/20' },
  orange: { activo: 'bg-orange-500 border-orange-400 text-white',  done: 'bg-orange-500/20 border-orange-400/40 text-orange-300', lock: 'bg-white/5 border-white/10 text-white/20' },
  green:  { activo: 'bg-[#00A859] border-[#00A859]   text-white',  done: 'bg-[#00A859]/20 border-[#00A859]/40  text-[#00A859]', lock: 'bg-white/5 border-white/10 text-white/20' },
}

function estadoFaseBtn(n) {
  if (!workspace.value) return 'lock'
  if (faseVista.value === n) return 'activo'
  if (getFase(n).completada)  return 'done'
  if (n <= equipo.value.fase_actual) return 'activo'
  return 'lock'
}

function puedeVerFase(n) {
  if (!equipo.value) return false
  return n <= equipo.value.fase_actual || getFase(n).completada
}

// ── Sidebar helpers ────────────────────────────────────────────────────────
const AVATAR_COLORS = ['#4F7FFA', '#10B981', '#F59E0B', '#8B5CF6', '#06B6D4', '#EC4899', '#EF4444']

function avatarColor(idx) {
  return AVATAR_COLORS[idx % AVATAR_COLORS.length]
}

function initials(name) {
  return (name || '').split(' ').filter(Boolean).map(n => n[0]).join('').slice(0, 2).toUpperCase()
}

function pctFase(n) {
  if (!equipo.value) return null
  const fase = getFase(n)
  if (fase.completada || n < equipo.value.fase_actual) return 100
  if (n === equipo.value.fase_actual) {
    return n === 2 ? progreso.value : null
  }
  return null
}

// ── Carga inicial ──────────────────────────────────────────────────────────
onMounted(async () => {
  setTimeout(() => { isLoaded.value = true }, 60)
  try {
    const res = await api.get(`/equipo/${token}`)
    workspace.value = res.data
    faseVista.value = equipo.value.fase_actual
    localStorage.setItem('dualab_equipo_token', token)
    localStorage.setItem('dualab_equipo_nombre', equipo.value.nombre)
    localStorage.setItem('dualab_proyecto_titulo', proyecto.value.titulo)
  } catch {
    error.value = true
  } finally {
    cargando.value = false
  }
})

// ── Guardar fase ──────────────────────────────────────────────────────────
async function guardarFase(n, datos) {
  guardando.value = true
  try {
    await api.put(`/equipo/${token}/fase/${n}`, { datos })
    workspace.value.fases[n] = { ...getFase(n), datos }
    if (n === 0 && datos.miembros) workspace.value.equipo.miembros = datos.miembros
    mostrarOk('Guardado correctamente')
  } finally {
    guardando.value = false
  }
}

async function completarFase(n) {
  guardando.value = true
  try {
    const res = await api.post(`/equipo/${token}/fase/${n}/completar`)
    workspace.value.fases[n] = { ...getFase(n), completada: true }
    workspace.value.equipo.fase_actual = res.data.fase_actual
    faseVista.value = res.data.fase_actual
    mostrarOk('¡Fase completada! Ahora podéis continuar con la siguiente.')
  } finally {
    guardando.value = false
  }
}

function mostrarOk(msg) {
  msgOk.value = msg
  setTimeout(() => { msgOk.value = '' }, 3000)
}

// ── F0: Formularios de equipo ───────────────────────────────────────────────
const f0 = ref({ contrato_firmado: false, miembros: [] })
const nuevoMiembro = ref({ nombre: '', rol: '' })
const rolesOpciones = ['portavoz', 'tiempos', 'documentacion', 'foco']
const rolesInfo = {
  portavoz: {
    titulo: 'Portavoz',
    icono: MegaphoneIcon,
    card: 'bg-sky-50 border border-sky-200',
    iconoClase: 'text-sky-600',
    tituloClase: 'text-sky-800',
    textoClase: 'text-sky-700',
    resumen: 'Es quien representa al equipo hacia afuera.',
    puntos: [
      'Explica las ideas del grupo.',
      'Se asegura de que lo que se dice refleja lo acordado por todos.',
      'Comunica lo que se decide en equipo.',
    ],
  },
  tiempos: {
    titulo: 'Gestor/a de tiempos',
    icono: ClockIcon,
    card: 'bg-amber-50 border border-amber-200',
    iconoClase: 'text-amber-600',
    tituloClase: 'text-amber-800',
    textoClase: 'text-amber-700',
    resumen: 'Es quien cuida el ritmo de trabajo.',
    puntos: [
      'Controla los tiempos de cada fase.',
      'Lanza avisos claros ("nos quedan cinco minutos").',
      'Ayuda a que el equipo llegue a todo.',
    ],
  },
  foco: {
    titulo: 'Responsable de foco',
    icono: EyeIcon,
    card: 'bg-violet-50 border border-violet-200',
    iconoClase: 'text-violet-600',
    tituloClase: 'text-violet-800',
    textoClase: 'text-violet-700',
    resumen: 'Es quien evita que el equipo se disperse.',
    puntos: [
      'Recuerda constantemente el reto.',
      'Detecta cuando el grupo se va por las ramas.',
      'Hace preguntas como "¿esto responde al problema?".',
    ],
  },
  documentacion: {
    titulo: 'Documentador/a',
    icono: DocumentTextIcon,
    card: 'bg-emerald-50 border border-emerald-200',
    iconoClase: 'text-emerald-600',
    tituloClase: 'text-emerald-800',
    textoClase: 'text-emerald-700',
    resumen: 'Es quien se asegura de que el trabajo queda registrado.',
    puntos: [
      'Escribe las ideas clave del equipo.',
      'Responsable de que se completen plantillas y materiales.',
      'Se coordina para que nada importante se pierda.',
    ],
  },
}
function inicializarF0() {
  const datos = getFase(0).datos ?? {}
  f0.value = {
    contrato_firmado: datos.contrato_firmado ?? false,
    miembros: (datos.miembros ?? []).map(m => ({
      nombre:           m.nombre ?? '',
      rol:              m.rol ?? '',
      fortalezas:       Array.isArray(m.fortalezas) ? m.fortalezas : [],
      puntosMejora:     Array.isArray(m.puntos_mejora) ? m.puntos_mejora : [],
      nuevaFortaleza:   '',
      nuevoPuntoMejora: '',
    })),
  }
}

function addMiembro() {
  if (!nuevoMiembro.value.nombre.trim()) return
  f0.value.miembros.push({
    ...nuevoMiembro.value,
    fortalezas: [],
    puntosMejora: [],
    nuevaFortaleza: '',
    nuevoPuntoMejora: '',
  })
  nuevoMiembro.value = { nombre: '', rol: '' }
}
function removeMiembro(i) { f0.value.miembros.splice(i, 1) }

function addFortaleza(m) {
  const v = m.nuevaFortaleza.trim()
  if (!v) return
  m.fortalezas.push(v)
  m.nuevaFortaleza = ''
}
function addPuntoMejora(m) {
  const v = m.nuevoPuntoMejora.trim()
  if (!v) return
  m.puntosMejora.push(v)
  m.nuevoPuntoMejora = ''
}

const f0Valido = computed(() =>
  f0.value.miembros.length > 0 &&
  f0.value.contrato_firmado
)

function serializarF0() {
  return {
    contrato_firmado: f0.value.contrato_firmado,
    miembros: f0.value.miembros.map(({ nuevaFortaleza, nuevoPuntoMejora, puntosMejora, ...m }) => ({
      ...m,
      puntos_mejora: puntosMejora,
    })),
  }
}
async function guardarF0() { await guardarFase(0, serializarF0()) }
async function completarF0() {
  await guardarFase(0, serializarF0())
  await completarFase(0)
}

const intentoF0 = ref(false)
const f0CardRef = ref(null)
const f0Faltantes = computed(() => [
  { ok: f0.value.miembros.length > 0, label: 'Añadir al menos un integrante del equipo' },
  { ok: f0.value.contrato_firmado,    label: 'Aceptar el contrato de equipo' },
])
function onCompletarF0() {
  if (f0Valido.value) { intentoF0.value = false; completarF0(); return }
  intentoF0.value = true
  scrollAFaltante(f0CardRef.value)
}

// ── F1: Análisis del reto ───────────────────────────────────────────────────
const f1 = ref({ sintesis: [], reto_frase: '', hallazgos: [], propuesta: '', explicacion_propuesta: '' })
const nuevoHallazgo = ref('')
const sugiriendoHallazgo = ref(false)
const errorSugerencia = ref('')
const HALLAZGO_MAXLEN = 280
const hallazgoAbierto = ref(null) // índice del hallazgo desplegado para lectura/edición completa

function inicializarF1() {
  const datos = getFase(1).datos ?? {}
  f1.value = {
    sintesis: datos.sintesis?.length
      ? datos.sintesis
      : preguntasF0.value.map(p => ({ pregunta: p, respuesta: '' })),
    reto_frase: datos.reto_frase ?? '',
    hallazgos:  datos.hallazgos  ?? [],
    propuesta:  datos.propuesta  ?? '',
    explicacion_propuesta: datos.explicacion_propuesta ?? '',
  }
}

function addHallazgo() {
  if (!nuevoHallazgo.value.trim()) return
  f1.value.hallazgos.push(nuevoHallazgo.value.trim().slice(0, HALLAZGO_MAXLEN))
  nuevoHallazgo.value = ''
  hallazgoAbierto.value = f1.value.hallazgos.length - 1
}
function removeHallazgo(i) {
  f1.value.hallazgos.splice(i, 1)
  hallazgoAbierto.value = null
}
function toggleHallazgo(i) {
  hallazgoAbierto.value = hallazgoAbierto.value === i ? null : i
}

async function sugerirHallazgo() {
  sugiriendoHallazgo.value = true
  errorSugerencia.value = ''
  try {
    const existentes = f1.value.hallazgos.filter(h => h.trim())
    const res = await api.post(`/equipo/${token}/fase/1/sugerir-hallazgo`, { existentes })
    f1.value.hallazgos.push(res.data.hallazgo.slice(0, HALLAZGO_MAXLEN))
    hallazgoAbierto.value = f1.value.hallazgos.length - 1
  } catch (e) {
    errorSugerencia.value = e.response?.data?.error ?? 'No se pudo generar la sugerencia.'
  } finally {
    sugiriendoHallazgo.value = false
  }
}

const hallazgosValidos = computed(() => f1.value.hallazgos.filter(h => h.trim()).length >= 4)

const f1Valido = computed(() =>
  f1.value.sintesis.every(s => s.respuesta.trim().length > 0) &&
  f1.value.reto_frase.trim() &&
  hallazgosValidos.value &&
  f1.value.propuesta.trim() &&
  f1.value.explicacion_propuesta.trim()
)

async function guardarF1() { await guardarFase(1, { ...f1.value }) }
async function completarF1() {
  await guardarFase(1, { ...f1.value })
  await completarFase(1)
}

const intentoF1 = ref(false)
const f1SintesisRef     = ref(null)
const f1HallazgosRef    = ref(null)
const f1RetoFraseRef    = ref(null)
const f1PropuestaRef    = ref(null)
const f1ExplicacionRef  = ref(null)
const f1Faltantes = computed(() => [
  { ok: f1.value.sintesis.every(s => s.respuesta.trim().length > 0), label: 'Responder todas las preguntas de síntesis del equipo', ref: f1SintesisRef },
  { ok: hallazgosValidos.value,                                      label: 'Añadir al menos 4 hallazgos clave', ref: f1HallazgosRef },
  { ok: !!f1.value.reto_frase.trim(),                                label: 'Escribir el reto en una frase', ref: f1RetoFraseRef },
  { ok: !!f1.value.propuesta.trim(),                                 label: 'Describir la propuesta inicial de solución', ref: f1PropuestaRef },
  { ok: !!f1.value.explicacion_propuesta.trim(),                     label: 'Explicar la propuesta', ref: f1ExplicacionRef },
])
function onCompletarF1() {
  if (f1Valido.value) { intentoF1.value = false; completarF1(); return }
  intentoF1.value = true
  scrollAFaltante(f1Faltantes.value.find(it => !it.ok)?.ref?.value)
}

// ── F1 Prototipos — archivos Cloudinary ───────────────────────────────────
const prototipos       = ref([])
const subiendoArchivo  = ref(false)
const errorArchivo     = ref('')

// Archivos de prototipo (F2) vs. archivos del entregable final (F3) — misma infraestructura
// de subida a Cloudinary (equipo_prototipos), distinguidos por el campo 'contexto'.
const archivosPrototipo  = computed(() => prototipos.value.filter(p => p.contexto !== 'entregable'))
const archivosEntregable = computed(() => prototipos.value.filter(p => p.contexto === 'entregable'))

function inicializarPrototipos() {
  prototipos.value = workspace.value?.prototipos ?? []
}

function formatBytes(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function iconoMime(mime) {
  if (mime?.startsWith('image/'))  return '🖼️'
  if (mime?.startsWith('video/'))  return '🎬'
  if (mime === 'application/pdf')  return '📄'
  return '📎'
}

async function subirArchivoConContexto(event, contexto, subiendoRef, errorRef) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  const maxMb = 20
  if (file.size > maxMb * 1024 * 1024) {
    errorRef.value = `El archivo supera el límite de ${maxMb} MB.`
    return
  }

  errorRef.value = ''
  subiendoRef.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('contexto', contexto)
    const res = await api.post(`/equipo/${token}/prototipos`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    prototipos.value.push(res.data)
    mostrarOk('Archivo subido correctamente')
  } catch (e) {
    errorRef.value = e.response?.data?.message
      ?? e.response?.data?.errors?.file?.[0]
      ?? 'Error al subir el archivo.'
  } finally {
    subiendoRef.value = false
  }
}
function subirArchivo(event) { return subirArchivoConContexto(event, 'prototipo', subiendoArchivo, errorArchivo) }

async function eliminarPrototipo(prototipo) {
  try {
    await api.delete(`/equipo/${token}/prototipos/${prototipo.id}`)
    const i = prototipos.value.findIndex(p => p.id === prototipo.id)
    if (i !== -1) prototipos.value.splice(i, 1)
  } catch {
    errorArchivo.value = 'No se pudo eliminar el archivo.'
  }
}

const subiendoArchivoEntregable = ref(false)
const errorArchivoEntregable    = ref('')
function subirArchivoEntregable(event) {
  return subirArchivoConContexto(event, 'entregable', subiendoArchivoEntregable, errorArchivoEntregable)
}
async function eliminarArchivoEntregable(prototipo) {
  try {
    await api.delete(`/equipo/${token}/prototipos/${prototipo.id}`)
    const i = prototipos.value.findIndex(p => p.id === prototipo.id)
    if (i !== -1) prototipos.value.splice(i, 1)
  } catch {
    errorArchivoEntregable.value = 'No se pudo eliminar el archivo.'
  }
}

// ── F2: Diseño de solución y desarrollo (prototipo + tareas) ────────────────
const f2 = ref({ tipo_prototipo: '', prototipo_url: '', iteracion: 1 })
const tiposPrototipo = ['Croquis / boceto papel', 'Storyboard / mapa visual', 'Maqueta física', 'Prototipo digital (Figma/Canva/Genially)', 'Diagrama de procesos']

function inicializarF2() {
  const datos = getFase(2).datos ?? {}
  f2.value = {
    tipo_prototipo: datos.tipo_prototipo ?? '',
    prototipo_url:  datos.prototipo_url  ?? '',
    iteracion:      datos.iteracion      ?? 1,
  }
}

async function guardarF2() { await guardarFase(2, { ...f2.value }) }

// Tareas genéricas (proceso de trabajo: buscar info, organizar, lluvia de ideas…) — precargadas,
// sin IA, con su propio formulario de añadir.
const tareasGenericas = computed(() => tareas.value.filter(t => t.tipo !== 'detalle_solucion'))
const nuevaTareaGenerica = ref({ descripcion: '', responsable: '', estado: 'pendiente' })
const cargandoTareaGenerica = ref(false)

async function addTareaGenerica() {
  if (!nuevaTareaGenerica.value.descripcion.trim()) return
  cargandoTareaGenerica.value = true
  try {
    const res = await api.post(`/equipo/${token}/tareas`, { ...nuevaTareaGenerica.value, tipo: 'proceso' })
    workspace.value.tareas.push(res.data)
    nuevaTareaGenerica.value = { descripcion: '', responsable: '', estado: 'pendiente' }
  } finally { cargandoTareaGenerica.value = false }
}

const restableciendoGenericas = ref(false)
async function restablecerTareasGenericas() {
  restableciendoGenericas.value = true
  try {
    const res = await api.post(`/equipo/${token}/fase/2/restablecer-tareas-genericas`)
    workspace.value.tareas.push(...res.data)
    if (!res.data.length) mostrarOk('Ya teníais todas las tareas genéricas precargadas.')
    else mostrarOk('Tareas genéricas restablecidas.')
  } finally {
    restableciendoGenericas.value = false
  }
}

// Tareas más complejas (detallan la propuesta concreta de F1) — sugeridas con IA y también
// añadibles a mano, con su propio formulario.
const tareasComplejas = computed(() => tareas.value.filter(t => t.tipo === 'detalle_solucion'))
const nuevaTareaCompleja = ref({ descripcion: '', responsable: '', estado: 'pendiente' })
const cargandoTareaCompleja = ref(false)
const sugiriendoTareas = ref(false)
const errorSugerenciaTareas = ref('')

async function sugerirTareas() {
  sugiriendoTareas.value = true
  errorSugerenciaTareas.value = ''
  try {
    const res = await api.post(`/equipo/${token}/fase/2/sugerir-tareas`)
    workspace.value.tareas.push(...res.data)
  } catch (e) {
    errorSugerenciaTareas.value = e.response?.data?.error ?? 'No se pudo generar la sugerencia.'
  } finally {
    sugiriendoTareas.value = false
  }
}

async function addTareaCompleja() {
  if (!nuevaTareaCompleja.value.descripcion.trim()) return
  cargandoTareaCompleja.value = true
  try {
    const res = await api.post(`/equipo/${token}/tareas`, { ...nuevaTareaCompleja.value, tipo: 'detalle_solucion' })
    workspace.value.tareas.push(res.data)
    nuevaTareaCompleja.value = { descripcion: '', responsable: '', estado: 'pendiente' }
  } finally { cargandoTareaCompleja.value = false }
}

async function cambiarEstadoTarea(tarea, estado) {
  await api.put(`/equipo/${token}/tareas/${tarea.id}`, { estado })
  tarea.estado = estado
}

async function actualizarResponsableTarea(tarea, responsable) {
  if ((tarea.responsable ?? '') === responsable) return
  await api.put(`/equipo/${token}/tareas/${tarea.id}`, { responsable })
  tarea.responsable = responsable
}

async function eliminarTarea(tarea) {
  await api.delete(`/equipo/${token}/tareas/${tarea.id}`)
  const i = workspace.value.tareas.findIndex(t => t.id === tarea.id)
  if (i !== -1) workspace.value.tareas.splice(i, 1)
}

const progreso = computed(() => {
  if (!tareas.value.length) return 0
  return Math.round(tareas.value.filter(t => t.estado === 'realizado').length / tareas.value.length * 100)
})

const f2Valido = computed(() =>
  !!f2.value.tipo_prototipo &&
  tareas.value.length > 0 &&
  progreso.value === 100
)

async function completarF2() {
  await guardarFase(2, { ...f2.value })
  await completarFase(2)
}

const intentoF2 = ref(false)
const f2TipoPrototipoRef = ref(null)
const f2TareasRef        = ref(null)
const f2Faltantes = computed(() => [
  { ok: !!f2.value.tipo_prototipo,                             label: 'Elegir el tipo de prototipo', ref: f2TipoPrototipoRef },
  { ok: tareas.value.length > 0,                                label: 'Añadir al menos una tarea', ref: f2TareasRef },
  { ok: tareas.value.length === 0 || progreso.value === 100,    label: 'Marcar todas las tareas como realizadas', ref: f2TareasRef },
])
const mostrarModalPasoF3 = ref(false)
function onCompletarF2() {
  if (f2Valido.value) { intentoF2.value = false; mostrarModalPasoF3.value = true; return }
  intentoF2.value = true
  scrollAFaltante(f2Faltantes.value.find(it => !it.ok)?.ref?.value)
}
async function confirmarPasoF3() {
  mostrarModalPasoF3.value = false
  await completarF2()
}

// ── F3: Entrega ────────────────────────────────────────────────────────────
const f3 = ref({ descripcion_entregable: '', url_entregable: '' })

function inicializarF3() {
  const datos = getFase(3).datos ?? {}
  f3.value = {
    descripcion_entregable: datos.descripcion_entregable ?? '',
    url_entregable:         datos.url_entregable         ?? '',
  }
}

const f3Valido = computed(() => f3.value.descripcion_entregable.trim().length > 0)
async function guardarF3() { await guardarFase(3, { ...f3.value }) }
async function completarF3() {
  await guardarFase(3, { ...f3.value })
  await completarFase(3)
}

const intentoF3 = ref(false)
const f3EntregableRef = ref(null)
const f3Faltantes = computed(() => [
  { ok: f3Valido.value, label: 'Describir el entregable', ref: f3EntregableRef },
])
const mostrarModalPasoF4 = ref(false)
function onCompletarF3() {
  if (f3Valido.value) { intentoF3.value = false; mostrarModalPasoF4.value = true; return }
  intentoF3.value = true
  scrollAFaltante(f3EntregableRef.value)
}
async function confirmarPasoF4() {
  mostrarModalPasoF4.value = false
  await completarF3()
}

// ── F4: Exposición a clase ───────────────────────────────────────────────────
const TIPOS_EXPOSICION = ['Role playing', 'PowerPoint / resumen visual', 'Vídeo', 'Otro']

const f4 = ref({
  expone_clase: null, // null = sin decidir todavía; true/false una vez elegido
  organizacion: {
    modo_intervencion: '',  // 'todos' | 'portavoz'
    tipo_exposicion: [],
  },
})

function inicializarF4() {
  const datos = getFase(4).datos ?? {}
  f4.value = {
    expone_clase: datos.expone_clase ?? null,
    organizacion: {
      modo_intervencion: datos.organizacion?.modo_intervencion ?? '',
      tipo_exposicion:   datos.organizacion?.tipo_exposicion   ?? [],
    },
  }
}

async function guardarF4() { await guardarFase(4, { ...f4.value }) }

// ── F4: Reflexiones ────────────────────────────────────────────────────────
const PREGUNTAS_INDIVIDUAL = [
  '¿Qué has aprendido que no sabías antes?',
  '¿Cuál fue la parte más difícil del proyecto?',
  '¿Qué habilidad has practicado más durante el proyecto?',
  '¿Qué cambiarías de tu propio desempeño si repitieras el proyecto?',
  '¿Dónde crees que podrías aplicar lo aprendido en el futuro?',
]
const PREGUNTAS_GRUPAL = [
  '¿Qué ha funcionado bien como equipo?',
  '¿Qué mejoraríais en vuestra forma de trabajar?',
  '¿Qué aplicaríais en el próximo proyecto?',
]

const modoReflexion     = ref('')
const nombreAlumno      = ref('')
const respuestasIndiv   = ref(PREGUNTAS_INDIVIDUAL.map(p => ({ pregunta: p, respuesta: '' })))
const respuestasGrupal  = ref(PREGUNTAS_GRUPAL.map(p => ({ pregunta: p, respuesta: '' })))
const guardandoReflexion = ref(false)
const reflexionEnviada  = ref(false)

const reflexionGrupal = computed(() => reflexiones.value.find(r => r.tipo === 'grupal'))
const misReflexiones  = computed(() => reflexiones.value.filter(r => r.tipo === 'individual'))

async function enviarReflexion(tipo) {
  guardandoReflexion.value = true
  try {
    const res = await api.post(`/equipo/${token}/reflexiones`, {
      tipo,
      autor_nombre: tipo === 'individual' ? nombreAlumno.value : null,
      respuestas:   tipo === 'individual' ? respuestasIndiv.value : respuestasGrupal.value,
    })
    workspace.value.reflexiones.push(res.data)
    reflexionEnviada.value = true
    modoReflexion.value = ''
    mostrarOk('Reflexión guardada correctamente.')
  } finally {
    guardandoReflexion.value = false
  }
}

function abrirReflexion(tipo) {
  modoReflexion.value = tipo
  reflexionEnviada.value = false
  if (tipo === 'individual') {
    nombreAlumno.value = ''
    respuestasIndiv.value = PREGUNTAS_INDIVIDUAL.map(p => ({ pregunta: p, respuesta: '' }))
  } else {
    respuestasGrupal.value = PREGUNTAS_GRUPAL.map(p => ({ pregunta: p, respuesta: '' }))
  }
}

const f4ValidoParaCompletar = computed(() =>
  reflexiones.value.length > 0 && reflexionGrupal.value
)

async function completarF4() {
  await guardarFase(4, { ...f4.value })
  await completarFase(4)
}

const intentoF4 = ref(false)
const f4ReflexionGrupalRef = ref(null)
const f4Faltantes = computed(() => [
  // La reflexión grupal ya implica al menos 1 reflexión — es el único requisito real.
  { ok: !!reflexionGrupal.value, label: 'Añadir la reflexión grupal (portavoz)', ref: f4ReflexionGrupalRef },
])
function onCompletarF4() {
  if (f4ValidoParaCompletar.value) { intentoF4.value = false; completarF4(); return }
  intentoF4.value = true
  scrollAFaltante(f4ReflexionGrupalRef.value)
}

// ── F4: Informe de cierre compilado (solo lectura, a partir de datos ya cargados) ──
const informeCierre = computed(() => ({
  retoFrase:        getFase(1).datos?.reto_frase ?? '',
  hallazgos:         getFase(1).datos?.hallazgos ?? [],
  propuesta:         getFase(1).datos?.propuesta ?? '',
  explicacionPropuesta: getFase(1).datos?.explicacion_propuesta ?? '',
  tipoPrototipo:     getFase(2).datos?.tipo_prototipo ?? '',
  prototipoUrl:      getFase(2).datos?.prototipo_url ?? '',
  tareasTotal:       tareas.value.length,
  tareasRealizadas:  tareas.value.filter(t => t.estado === 'realizado').length,
  entregableDesc:    getFase(3).datos?.descripcion_entregable ?? '',
  entregableUrl:     getFase(3).datos?.url_entregable ?? '',
}))

const NIVEL_LABELS = {
  no_alcanzado: 'No alcanzado',
  en_proceso:   'En proceso',
  alcanzado:    'Alcanzado',
  superado:     'Superado',
}
const NIVEL_COLORS = {
  no_alcanzado: 'bg-red-100 text-red-700',
  en_proceso:   'bg-amber-100 text-amber-700',
  alcanzado:    'bg-[#00A859]/10 text-[#00A859]',
  superado:     'bg-violet-100 text-violet-700',
}
const evaluacionRaCe = computed(() => getFase(4).datos?.evaluacion_docente?.ras ?? [])

function alCambiarFase(n) {
  faseVista.value = n
  if (n === 0) inicializarF0()
  if (n === 1) inicializarF1()
  if (n === 2) inicializarF2()
  if (n === 3) inicializarF3()
  if (n === 4) inicializarF4()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

watch(workspace, (val) => {
  if (val) {
    inicializarF0()
    inicializarF1()
    inicializarF2()
    inicializarF3()
    inicializarF4()
    inicializarPrototipos()
  }
}, { once: true })
</script>

<template>
  <div class="min-h-screen bg-[#F0F4F8] font-sans text-[#1F2937]"
       :class="isLoaded ? 'opacity-100' : 'opacity-0'"
       style="transition: opacity 0.4s ease">

    <!-- Cargando -->
    <div v-if="cargando" class="flex flex-col items-center justify-center min-h-screen">
      <svg class="animate-spin w-10 h-10 text-[#00A859] mb-3" viewBox="0 0 24 24" fill="none">
        <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
      </svg>
      <p class="text-[#00A859] font-black text-xs uppercase tracking-widest animate-pulse">Cargando proyecto...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex flex-col items-center justify-center min-h-screen px-6 text-center">
      <div class="w-16 h-16 rounded-3xl bg-red-50 border border-red-200 flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </div>
      <h2 class="text-lg font-black text-[#1F2937] mb-2">Enlace no válido</h2>
      <p class="text-sm text-gray-500 mb-6">Este enlace no existe o ha caducado. Comprueba el código con tu docente.</p>
      <button @click="router.push({ name: 'unirse-equipo' })"
              class="px-6 py-3 rounded-2xl bg-[#00A859] text-white text-sm font-black uppercase tracking-widest">
        Volver
      </button>
    </div>

    <template v-else-if="workspace">

      <!-- ══ CABECERA OSCURA ══ -->
      <div class="bg-[#0C1220] text-white">
        <div class="max-w-[1380px] mx-auto px-4 sm:px-8 py-4 sm:py-5
                    flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-6">
          <!-- Izquierda: info del microreto -->
          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#00A859] mb-1">
              Microreto activo
            </p>
            <h1 class="text-lg sm:text-xl font-black text-white leading-tight mb-2 sm:mb-3">
              {{ proyecto.titulo }}
            </h1>
            <div class="flex gap-1.5 sm:gap-2 flex-wrap">
              <span v-if="proyecto.empresa_nombre"
                    class="text-[10px] sm:text-[11px] font-bold bg-white/10 text-white px-2.5 py-1 rounded-full">
                {{ proyecto.empresa_nombre }}
              </span>
              <span v-if="proyecto.curso"
                    class="text-[10px] sm:text-[11px] font-bold bg-white/10 text-white px-2.5 py-1 rounded-full">
                {{ proyecto.curso }}
              </span>
            </div>
          </div>
          <!-- Derecha: nombre de equipo + miembros -->
          <div class="sm:text-right min-w-0 sm:max-w-[40%]">
            <p class="text-sm sm:text-base font-black text-[#00A859] truncate">{{ equipo.nombre }}</p>
            <p v-if="equipo.miembros?.length"
               class="text-[11px] text-gray-400 mt-0.5 leading-relaxed sm:leading-normal truncate sm:whitespace-normal">
              {{ equipo.miembros.slice(0, 4).map(m => m.nombre.split(' ')[0]).join(' · ') }}
            </p>
            <p v-if="proyecto.centro_nombre" class="text-[11px] text-gray-500 mt-0.5 truncate">
              {{ proyecto.centro_nombre }}<span v-if="proyecto.curso"> · {{ proyecto.curso }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- ══ BARRA DE FASES ══ -->
      <div class="bg-white border-b border-gray-200 sticky top-0 z-20 shadow-sm">
        <div class="max-w-[1380px] mx-auto">
          <div class="flex overflow-x-auto scrollbar-none">
            <button
              v-for="f in fasesConfig" :key="f.num"
              @click="puedeVerFase(f.num) && alCambiarFase(f.num)"
              class="flex-1 min-w-[72px] sm:min-w-[110px] flex flex-col items-center gap-0.5
                     py-3 sm:py-3.5 px-1.5 sm:px-3 border-b-2 transition-all duration-200"
              :class="[
                faseVista === f.num
                  ? 'border-[#00A859]'
                  : puedeVerFase(f.num)
                    ? 'border-transparent hover:border-gray-200 cursor-pointer'
                    : 'border-transparent cursor-default',
              ]">
              <span class="text-[9px] font-black uppercase tracking-wider"
                    :class="faseVista === f.num ? 'text-[#00A859]' : 'text-gray-400'">
                <template v-if="getFase(f.num).completada">✓ </template>
                F{{ f.num }}
              </span>
              <!-- Label completo en sm+, abreviado en móvil -->
              <span class="hidden sm:block text-[11px] font-bold leading-tight text-center"
                    :class="faseVista === f.num
                      ? 'text-[#00A859]'
                      : puedeVerFase(f.num) ? 'text-gray-600' : 'text-gray-300'">
                {{ f.label }}
              </span>
              <span class="sm:hidden text-[10px] font-bold leading-tight text-center"
                    :class="faseVista === f.num
                      ? 'text-[#00A859]'
                      : puedeVerFase(f.num) ? 'text-gray-600' : 'text-gray-300'">
                {{ f.shortLabel }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Toast éxito -->
      <Transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 -translate-y-2"
                  leave-active-class="transition-all duration-200" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="msgOk"
             class="fixed top-4 left-1/2 -translate-x-1/2 z-50 bg-[#00A859] text-white
                    px-5 py-2.5 rounded-full shadow-lg text-sm font-bold whitespace-nowrap">
          ✓ {{ msgOk }}
        </div>
      </Transition>

      <!-- ══ LAYOUT DOS COLUMNAS ══ -->
      <div class="max-w-[1380px] mx-auto px-3 sm:px-6 py-4 sm:py-6">
        <div class="flex flex-col lg:flex-row gap-4 sm:gap-6 items-start">

          <!-- ─────────────────────────────────────────────── -->
          <!--  CONTENIDO PRINCIPAL (fases)                    -->
          <!-- ─────────────────────────────────────────────── -->
          <div class="flex-1 min-w-0 space-y-4 sm:space-y-5">

            <!-- Tarjeta intro de la fase activa -->
            <div class="bg-[#F0FDF4] border border-[#00A859]/20 rounded-2xl px-5 py-4 flex gap-3 items-start">
              <span class="text-2xl mt-0.5 shrink-0">{{ fasesConfig[faseVista].icono }}</span>
              <div>
                <p class="text-sm font-black text-[#065F46] mb-0.5">
                  {{ fasesConfig[faseVista].label }}
                  <span v-if="duracionFase(faseVista)" class="font-normal text-[#047857]/80"> · {{ duracionFase(faseVista) }} clase(s)</span>
                </p>
                <p class="text-sm text-[#047857] leading-relaxed">{{ fasesConfig[faseVista].descLarga }}</p>
              </div>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 0 — Inicio del equipo                   -->
            <!-- ════════════════════════════════════════════ -->
            <div v-if="faseVista === 0" class="space-y-5">

              <!-- Validado -->
              <div v-if="getFase(0).validado_docente"
                   class="bg-green-50 border border-green-200 rounded-2xl px-4 py-3 flex items-center gap-3">
                <span class="text-green-600 text-lg">✓</span>
                <div>
                  <p class="text-sm font-bold text-green-700">Fase validada por el docente</p>
                  <p v-if="getFase(0).nota_docente" class="text-xs text-green-600">
                    Nota: {{ getFase(0).nota_docente }}/10 · {{ getFase(0).observaciones_docente }}
                  </p>
                </div>
              </div>

              <!-- BLOQUE A: Contrato de equipo -->
              <div ref="f0CardRef" class="bg-white rounded-2xl border shadow-sm p-4 sm:p-5 transition-all"
                   :class="intentoF0 && !f0Valido ? 'border-red-300 ring-2 ring-red-200' : 'border-gray-100'">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-4">A · Contrato de equipo</p>

                <!-- Qué significa cada rol -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                  <div v-for="r in rolesOpciones" :key="r" :class="rolesInfo[r].card" class="rounded-xl px-4 py-3">
                    <div class="flex items-center gap-2 mb-1">
                      <component :is="rolesInfo[r].icono" :class="rolesInfo[r].iconoClase" class="w-5 h-5 shrink-0" />
                      <p :class="rolesInfo[r].tituloClase" class="text-sm font-black uppercase tracking-wider">{{ rolesInfo[r].titulo }}</p>
                    </div>
                    <p :class="rolesInfo[r].textoClase" class="text-sm leading-snug">{{ rolesInfo[r].resumen }}</p>
                    <ul class="mt-1.5 space-y-1 list-disc list-inside">
                      <li v-for="(punto, i) in rolesInfo[r].puntos" :key="i" :class="rolesInfo[r].textoClase" class="text-sm leading-snug">
                        {{ punto }}
                      </li>
                    </ul>
                  </div>
                </div>

                <div class="space-y-2 mb-4">
                  <div v-for="(m, i) in f0.miembros" :key="i"
                       class="bg-slate-50 rounded-xl px-3 py-2.5 space-y-2">
                    <div class="flex items-center gap-2">
                      <span class="flex-1 text-sm font-bold text-[#1F2937]">{{ m.nombre }}</span>
                      <span class="text-[10px] text-slate-500 capitalize bg-white border border-slate-200 px-2 py-0.5 rounded-full">
                        {{ m.rol || 'sin rol' }}
                      </span>
                      <button @click="removeMiembro(i)" class="text-gray-300 hover:text-red-400 transition-colors text-xs font-black">✕</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                      <!-- Puntos fuertes -->
                      <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600 mb-1.5">Puntos fuertes</p>
                        <div class="flex gap-1.5 mb-2">
                          <input v-model="m.nuevaFortaleza" type="text" placeholder="Escribe tus puntos fuertes…" maxlength="40"
                                 class="flex-1 min-w-0 text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white
                                        focus:outline-none focus:border-emerald-400"
                                 @keydown.enter.prevent="addFortaleza(m)" />
                          <button @click="addFortaleza(m)" type="button"
                                  class="shrink-0 px-2.5 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700
                                         text-[10px] font-black uppercase tracking-wider hover:bg-emerald-100">
                            + Añadir
                          </button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                          <span v-for="(pf, pi) in m.fortalezas" :key="pi"
                                class="inline-flex items-center gap-1 bg-emerald-50 border border-emerald-200 text-emerald-700
                                       text-xs font-semibold px-2.5 py-1 rounded-full">
                            {{ pf }}
                            <button @click="m.fortalezas.splice(pi, 1)" type="button"
                                    class="text-emerald-400 hover:text-emerald-700 font-black">✕</button>
                          </span>
                        </div>
                      </div>

                      <!-- Puntos a mejorar -->
                      <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-amber-600 mb-1.5">Puntos a mejorar</p>
                        <div class="flex gap-1.5 mb-2">
                          <input v-model="m.nuevoPuntoMejora" type="text" placeholder="Escribe tus puntos a mejorar…" maxlength="40"
                                 class="flex-1 min-w-0 text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white
                                        focus:outline-none focus:border-amber-400"
                                 @keydown.enter.prevent="addPuntoMejora(m)" />
                          <button @click="addPuntoMejora(m)" type="button"
                                  class="shrink-0 px-2.5 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-700
                                         text-[10px] font-black uppercase tracking-wider hover:bg-amber-100">
                            + Añadir
                          </button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                          <span v-for="(pm, pmi) in m.puntosMejora" :key="pmi"
                                class="inline-flex items-center gap-1 bg-amber-50 border border-amber-200 text-amber-700
                                       text-xs font-semibold px-2.5 py-1 rounded-full">
                            {{ pm }}
                            <button @click="m.puntosMejora.splice(pmi, 1)" type="button"
                                    class="text-amber-400 hover:text-amber-700 font-black">✕</button>
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <p v-if="!f0.miembros.length" class="text-sm text-gray-400 text-center py-3">
                    Añadid los integrantes del equipo
                  </p>
                </div>

                <div class="flex gap-2 flex-wrap">
                  <input v-model="nuevoMiembro.nombre" type="text" placeholder="Nombre del alumno/a"
                         class="flex-1 min-w-32 text-sm border border-gray-200 rounded-xl px-3 py-2
                                focus:outline-none focus:border-slate-400 bg-gray-50"
                         @keydown.enter="addMiembro" />
                  <select v-model="nuevoMiembro.rol"
                          class="text-sm border border-gray-200 rounded-xl px-3 py-2 bg-gray-50
                                 focus:outline-none focus:border-slate-400 capitalize">
                    <option value="">— Rol —</option>
                    <option v-for="r in rolesOpciones" :key="r" :value="r" class="capitalize">{{ r }}</option>
                  </select>
                  <button @click="addMiembro"
                          class="px-4 py-2 rounded-xl bg-slate-600 text-white text-xs font-black uppercase tracking-wider">
                    + Añadir
                  </button>
                </div>

                <label class="mt-4 flex items-center gap-3 cursor-pointer group">
                  <input v-model="f0.contrato_firmado" type="checkbox"
                         class="w-5 h-5 rounded-lg accent-slate-600 cursor-pointer" />
                  <span class="text-sm font-semibold text-[#1F2937] group-hover:text-slate-600 transition-colors">
                    El equipo acepta colaborar con respeto y responsabilidad (contrato de equipo)
                  </span>
                </label>
              </div>

              <!-- Acciones F0 -->
              <div class="flex gap-3 flex-wrap">
                <button @click="guardarF0" :disabled="guardando"
                        class="flex-1 py-3 rounded-2xl bg-slate-100 border border-slate-200
                               text-slate-700 text-sm font-black uppercase tracking-wider
                               hover:bg-slate-200 transition-all disabled:opacity-50">
                  Guardar borrador
                </button>
                <button @click="onCompletarF0" :disabled="guardando"
                        :class="['flex-1 py-3 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                                 f0Valido ? 'bg-slate-600 text-white hover:bg-slate-700' : 'bg-gray-100 text-gray-400 hover:bg-gray-200']">
                  Completar fase ✓
                </button>
              </div>
              <div v-if="intentoF0 && !f0Valido" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 space-y-1">
                <p class="text-[10px] font-black text-red-500 uppercase tracking-wider mb-1">Falta esto para continuar:</p>
                <p v-for="(it, idx) in f0Faltantes.filter(x => !x.ok)" :key="idx" class="text-xs text-red-600 flex items-center gap-1.5">
                  <span>⚠</span> {{ it.label }}
                </p>
              </div>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 1 — Análisis del reto                   -->
            <!-- ════════════════════════════════════════════ -->
            <div v-else-if="faseVista === 1" class="space-y-5">

              <div v-if="getFase(1).validado_docente"
                   class="bg-green-50 border border-green-200 rounded-2xl px-4 py-3 flex items-center gap-3">
                <span class="text-green-600 text-lg">✓</span>
                <div>
                  <p class="text-sm font-bold text-green-700">Fase validada por el docente</p>
                  <p v-if="getFase(1).observaciones_docente" class="text-xs text-green-600">{{ getFase(1).observaciones_docente }}</p>
                </div>
              </div>
              <div v-else-if="getFase(1).datos && !getFase(1).validado_docente && getFase(1).completada"
                   class="bg-blue-50 border border-blue-200 rounded-2xl px-4 py-3">
                <p class="text-sm font-bold text-blue-700">Enviado al docente para validación. Espera su respuesta.</p>
              </div>

              <!-- BLOQUE B: Diagnóstico de empresa -->
              <div v-if="diagnostico" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                  <p class="text-[9px] font-black uppercase tracking-widest text-blue-500">B · Diagnóstico de la empresa</p>
                  <button @click="verFichaReto = true" type="button"
                          class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-200
                                 text-blue-700 text-[10px] font-black uppercase tracking-wider hover:bg-blue-100 transition-all">
                    👁 Ver reto
                  </button>
                </div>
                <p class="text-[11px] text-gray-400 mb-4">
                  Esto es un resumen de la ficha completa del reto que os ha preparado la empresa. Pulsad "Ver reto" para consultarla entera antes de responder las preguntas.
                </p>

                <div class="space-y-3">
                  <div v-if="diagnostico.quien_es" class="bg-blue-50 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">¿Quiénes son?</p>
                    <p class="text-sm text-[#1F2937] leading-relaxed">{{ diagnostico.quien_es }}</p>
                  </div>
                  <div v-if="diagnostico.dia_a_dia" class="bg-blue-50 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">Su día a día</p>
                    <p class="text-sm text-[#1F2937] leading-relaxed">{{ diagnostico.dia_a_dia }}</p>
                  </div>
                  <div v-if="diagnostico.pregunta_reto" class="bg-slate-800 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-[#99CC33] mb-1">El reto que os proponen</p>
                    <p class="text-sm font-bold text-white leading-relaxed">{{ diagnostico.pregunta_reto }}</p>
                  </div>
                  <div v-if="diagnostico.que_necesitan?.length" class="bg-blue-50 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">Qué necesitan</p>
                    <ul class="space-y-1">
                      <li v-for="(n, i) in diagnostico.que_necesitan" :key="i" class="text-sm text-[#1F2937] flex gap-2">
                        <span class="text-blue-400 shrink-0">·</span>{{ n }}
                      </li>
                    </ul>
                  </div>
                  <div v-if="diagnostico.dificultades?.length" class="bg-orange-50 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-orange-400 mb-1">Dificultades</p>
                    <ul class="space-y-1">
                      <li v-for="(d, i) in diagnostico.dificultades" :key="i" class="text-sm text-[#1F2937] flex gap-2">
                        <span class="text-orange-400 shrink-0">·</span>{{ d }}
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- BLOQUE C: Síntesis del equipo -->
              <div ref="f1SintesisRef" class="bg-white rounded-2xl border shadow-sm p-4 sm:p-5 transition-all"
                   :class="intentoF1 && !f1Faltantes[0].ok ? 'border-red-300 ring-2 ring-red-200' : 'border-gray-100'">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">C · Síntesis del equipo</p>
                <p class="text-[11px] text-gray-400 mb-4">Responded juntos a estas preguntas sobre el reto de la empresa.</p>

                <div class="space-y-4">
                  <div v-for="(item, i) in f1.sintesis" :key="i">
                    <label class="block text-xs font-bold text-[#1F2937] mb-1.5">
                      {{ i + 1 }}. {{ item.pregunta }}
                    </label>
                    <textarea v-model="item.respuesta" rows="2"
                              class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                     focus:outline-none focus:border-slate-400 bg-gray-50 resize-none"
                              placeholder="Respuesta del equipo..."/>
                  </div>
                </div>
              </div>

              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 space-y-5">

                <div ref="f1HallazgosRef" class="rounded-xl transition-all"
                     :class="intentoF1 && !hallazgosValidos ? 'ring-2 ring-red-200 border border-red-300 p-3 -m-1' : ''">
                  <div class="flex items-center justify-between gap-2 mb-1.5 flex-wrap">
                    <div class="flex items-center gap-2">
                      <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider">
                        Hallazgos clave <span class="text-red-400">*</span>
                      </label>
                      <span class="shrink-0 px-2.5 py-1 rounded-full text-[11px] font-black tracking-wide"
                            :class="hallazgosValidos ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600 animate-pulse'">
                        {{ f1.hallazgos.filter(h => h.trim()).length }} / 4 mínimo
                      </span>
                    </div>
                    <button @click="sugerirHallazgo" :disabled="sugiriendoHallazgo"
                            class="shrink-0 px-3 py-1.5 rounded-full bg-violet-50 border border-violet-200 text-violet-700
                                   text-[10px] font-black uppercase tracking-wider hover:bg-violet-100 transition-all disabled:opacity-50">
                      {{ sugiriendoHallazgo ? 'Generando…' : '✨ Sugerir con IA' }}
                    </button>
                  </div>
                  <p class="text-[11px] text-gray-500 mb-2">
                    Generad un ejemplo con IA a partir del reto y añadid al menos 3 hallazgos propios más
                    <span class="font-black text-red-500">(mínimo 4 en total, obligatorio para continuar)</span>.
                  </p>
                  <p class="text-[10px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 mb-2 flex items-start gap-1.5">
                    <span class="shrink-0">⚠️</span>
                    <span><strong>Revisa las sugerencias de la IA. Ten criterio propio.</strong></span>
                  </p>
                  <p v-if="errorSugerencia" class="text-xs text-red-500 font-semibold mb-2">{{ errorSugerencia }}</p>
                  <div class="space-y-2 mb-2">
                    <div v-for="(h, i) in f1.hallazgos" :key="i"
                         class="border border-gray-200 rounded-xl bg-gray-50 overflow-hidden">
                      <div class="flex gap-2 items-center px-3 py-2">
                        <span class="text-blue-400 font-black text-sm shrink-0">{{ i + 1 }}.</span>
                        <button @click="toggleHallazgo(i)" type="button"
                                class="flex-1 min-w-0 flex items-center gap-2 text-left">
                          <span class="flex-1 min-w-0 truncate text-sm text-[#1F2937]">
                            {{ h.trim() || `Hallazgo ${i + 1} (vacío)` }}
                          </span>
                          <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform"
                               :class="{ 'rotate-180': hallazgoAbierto === i }"
                               fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                          </svg>
                        </button>
                        <button @click="removeHallazgo(i)" class="text-gray-300 hover:text-red-400 font-black text-xs shrink-0">✕</button>
                      </div>
                      <div v-if="hallazgoAbierto === i" class="px-3 pb-3">
                        <textarea v-model="f1.hallazgos[i]" rows="3" :maxlength="HALLAZGO_MAXLEN"
                                  :placeholder="`Hallazgo ${i + 1}`"
                                  class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white resize-none
                                         focus:outline-none focus:border-blue-400"/>
                        <p class="text-[10px] text-gray-400 text-right mt-1">{{ h.length }} / {{ HALLAZGO_MAXLEN }}</p>
                      </div>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <input v-model="nuevoHallazgo" type="text" placeholder="Añadir hallazgo..." :maxlength="HALLAZGO_MAXLEN"
                           class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                  focus:outline-none focus:border-blue-400 bg-gray-50"
                           @keydown.enter="addHallazgo"/>
                    <button @click="addHallazgo"
                            class="px-4 py-2 rounded-xl bg-blue-500 text-white text-xs font-black">+ Añadir</button>
                  </div>
                  <div class="flex items-center gap-2 mt-1.5">
                    <div class="flex gap-1">
                      <span v-for="n in 4" :key="n" class="w-2.5 h-2.5 rounded-full transition-colors"
                            :class="f1.hallazgos.filter(h => h.trim()).length >= n ? 'bg-emerald-500' : 'bg-red-100'"/>
                    </div>
                    <span class="text-[11px] font-black" :class="hallazgosValidos ? 'text-emerald-600' : 'text-red-500'">
                      {{ f1.hallazgos.filter(h => h.trim()).length }} / 4 mínimo
                    </span>
                  </div>
                </div>

                <div ref="f1RetoFraseRef" class="rounded-xl transition-all"
                     :class="intentoF1 && !f1.reto_frase.trim() ? 'ring-2 ring-red-200 border border-red-300 p-3 -m-1' : ''">
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    El reto en una frase <span class="text-red-400">*</span>
                  </label>
                  <div v-if="diagnostico?.pregunta_reto" class="bg-blue-50 border border-blue-100 rounded-xl px-3.5 py-2.5 mb-2">
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">Pregunta del reto</p>
                    <p class="text-sm font-bold text-blue-900 leading-snug">"{{ diagnostico.pregunta_reto }}"</p>
                  </div>
                  <p v-if="diagnostico?.pregunta_reto" class="text-[11px] text-gray-400 mb-2">
                    Lo que escribáis aquí es vuestra respuesta inicial a esa pregunta.
                  </p>
                  <p v-else class="text-[11px] text-gray-400 mb-2">Responde a la pregunta "¿Cómo podríamos...?" del reto de la empresa.</p>
                  <input v-model="f1.reto_frase" type="text"
                         :placeholder="diagnostico?.pregunta_reto ? `Vuestra respuesta a: ${diagnostico.pregunta_reto}` : '¿Cómo podríamos...?'"
                         class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5
                                focus:outline-none focus:border-blue-400 bg-gray-50 font-semibold"/>
                </div>

                <div ref="f1PropuestaRef" class="rounded-xl transition-all"
                     :class="intentoF1 && (!f1.propuesta.trim() || !f1.explicacion_propuesta.trim()) ? 'ring-2 ring-red-200 border border-red-300 p-3 -m-1' : ''">
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    Propuesta inicial de solución <span class="text-red-400">*</span>
                  </label>
                  <textarea v-model="f1.propuesta" rows="3"
                            placeholder="Describid vuestra solución y en qué se diferencia de lo que existe..."
                            class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                   focus:outline-none focus:border-blue-400 bg-gray-50 resize-none"/>
                  <div ref="f1ExplicacionRef" class="mt-3">
                    <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                      Explicación <span class="text-red-400">*</span>
                    </label>
                    <p class="text-[11px] text-gray-400 mb-2">Explicad por qué creéis que esta propuesta responde al reto.</p>
                    <textarea v-model="f1.explicacion_propuesta" rows="3"
                              placeholder="Explicad el razonamiento detrás de vuestra propuesta..."
                              class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                     focus:outline-none focus:border-blue-400 bg-gray-50 resize-none"/>
                  </div>
                </div>
              </div>

              <div class="flex gap-3">
                <button @click="guardarF1" :disabled="guardando"
                        class="flex-1 py-3 rounded-2xl bg-blue-50 border border-blue-200 text-blue-700
                               text-sm font-black uppercase tracking-wider hover:bg-blue-100 transition-all disabled:opacity-50">
                  Guardar
                </button>
                <button @click="onCompletarF1" :disabled="guardando"
                        :class="['flex-1 py-3 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                                 f1Valido ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-gray-100 text-gray-400 hover:bg-gray-200']">
                  Enviar para validación →
                </button>
              </div>
              <div v-if="intentoF1 && !f1Valido" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 space-y-1">
                <p class="text-[10px] font-black text-red-500 uppercase tracking-wider mb-1">Falta esto para continuar:</p>
                <p v-for="(it, idx) in f1Faltantes.filter(x => !x.ok)" :key="idx" class="text-xs text-red-600 flex items-center gap-1.5">
                  <span>⚠</span> {{ it.label }}
                </p>
              </div>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 2 — Diseño de solución y desarrollo (tareas) -->
            <!-- ════════════════════════════════════════════ -->
            <div v-else-if="faseVista === 2" class="space-y-5">

              <!-- Prototipo: qué es y cómo se representa -->
              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 space-y-5">
                <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
                  <p class="text-[9px] font-black uppercase tracking-widest text-amber-600 mb-1">¿Qué es el prototipo?</p>
                  <p class="text-xs text-amber-800 leading-relaxed">
                    Es una representación inicial de vuestra solución — no tiene que ser perfecta ni funcional del todo.
                    Sirve para explicar la idea y recibir feedback antes de construir la versión final. Puede ser un
                    croquis en papel, un storyboard, una maqueta física, un diseño digital (Figma, Canva, Genially) o un
                    diagrama de procesos.
                  </p>
                </div>

                <div ref="f2TipoPrototipoRef" class="rounded-xl transition-all"
                     :class="intentoF2 && !f2.tipo_prototipo ? 'ring-2 ring-red-200 border border-red-300 p-3 -m-1' : ''">
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-2">
                    Tipo de prototipo <span class="text-red-400">*</span>
                  </label>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label v-for="tipo in tiposPrototipo" :key="tipo"
                           class="flex items-center gap-3 cursor-pointer p-3 rounded-xl border transition-all"
                           :class="f2.tipo_prototipo === tipo ? 'border-amber-400 bg-amber-50' : 'border-gray-100 bg-gray-50 hover:border-amber-200'">
                      <input type="radio" v-model="f2.tipo_prototipo" :value="tipo" class="accent-amber-500"/>
                      <span class="text-sm font-semibold text-[#1F2937]">{{ tipo }}</span>
                    </label>
                  </div>
                </div>

                <!-- Enlace al prototipo + subida de archivos a Cloudinary -->
                <div class="space-y-3">
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider">
                    Enlace al prototipo
                  </label>

                  <!-- URL manual (Figma, Drive, Canva…) -->
                  <input v-model="f2.prototipo_url" type="url"
                         placeholder="https://figma.com/... o https://drive.google.com/..."
                         class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                focus:outline-none focus:border-amber-400 bg-gray-50"/>
                  <p class="text-[10px] text-gray-400">Pega un enlace externo (Figma, Drive, Canva…) o sube archivos directamente.</p>

                  <!-- Subir archivo -->
                  <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer px-4 py-2 rounded-xl
                                  border border-dashed border-amber-300 bg-amber-50 text-amber-700
                                  text-xs font-black uppercase tracking-wider hover:bg-amber-100 transition-all
                                  select-none"
                           :class="subiendoArchivo ? 'opacity-50 pointer-events-none' : ''">
                      <span v-if="subiendoArchivo">Subiendo…</span>
                      <span v-else>📎 Subir archivo</span>
                      <input type="file" class="hidden"
                             accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.gif,.webp,.mp4,.mov,.avi,.mkv,.webm,.zip"
                             :disabled="subiendoArchivo"
                             @change="subirArchivo"/>
                    </label>
                    <p class="text-[10px] text-gray-400 mt-1">PDF, imágenes, vídeos, documentos Office, ZIP — máx. 20 MB</p>
                  </div>

                  <!-- Error de subida -->
                  <p v-if="errorArchivo" class="text-xs text-red-500 font-semibold">{{ errorArchivo }}</p>

                  <!-- Lista de archivos subidos -->
                  <ul v-if="archivosPrototipo.length" class="space-y-2">
                    <li v-for="p in archivosPrototipo" :key="p.id"
                        class="flex items-center gap-3 p-3 bg-white rounded-xl border border-gray-100 shadow-sm">
                      <span class="text-xl shrink-0">{{ iconoMime(p.mime) }}</span>
                      <div class="flex-1 min-w-0">
                        <a :href="p.url" target="_blank" rel="noopener"
                           class="text-sm font-semibold text-amber-700 hover:underline truncate block">
                          {{ p.filename }}
                        </a>
                        <p class="text-[10px] text-gray-400">{{ formatBytes(p.size) }}</p>
                      </div>
                      <button @click="eliminarPrototipo(p)"
                              class="shrink-0 text-gray-300 hover:text-red-400 transition-colors text-lg leading-none"
                              title="Eliminar archivo">×</button>
                    </li>
                  </ul>
                </div>

                <div class="flex items-center gap-4">
                  <label class="text-xs font-black text-[#1F2937] uppercase tracking-wider">Iteración</label>
                  <div class="flex items-center gap-2">
                    <button @click="f2.iteracion = Math.max(1, f2.iteracion - 1)"
                            class="w-8 h-8 rounded-lg bg-gray-100 border border-gray-200 font-black text-gray-500 hover:bg-gray-200 transition-all">−</button>
                    <span class="text-lg font-black text-[#1F2937] w-8 text-center">{{ f2.iteracion }}</span>
                    <button @click="f2.iteracion++"
                            class="w-8 h-8 rounded-lg bg-gray-100 border border-gray-200 font-black text-gray-500 hover:bg-gray-200 transition-all">+</button>
                  </div>
                  <p class="text-xs text-gray-400">¿Es la primera versión o habéis mejorado el prototipo?</p>
                </div>

                <button @click="guardarF2" :disabled="guardando"
                        class="w-full py-2.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-700
                               text-xs font-black uppercase tracking-wider hover:bg-amber-100 transition-all disabled:opacity-50">
                  Guardar prototipo
                </button>
              </div>

              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 sm:px-5 py-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs font-black text-[#1F2937] uppercase tracking-wider">Progreso</span>
                  <span class="text-sm font-black text-amber-600">{{ progreso }}%</span>
                </div>
                <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full transition-all duration-500"
                       :style="{ width: progreso + '%' }"/>
                </div>
                <p class="text-[10px] text-gray-400 mt-2">
                  {{ tareas.filter(t => t.estado === 'realizado').length }} de {{ tareas.length }} tareas realizadas
                </p>
              </div>

              <div ref="f2TareasRef" class="space-y-4 sm:space-y-5 rounded-2xl transition-all"
                   :class="intentoF2 && (tareas.length === 0 || progreso !== 100) ? 'ring-2 ring-red-200 rounded-2xl p-1 -m-1' : ''">

                <!-- Tareas genéricas -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                  <div class="flex items-center justify-between gap-2 mb-1">
                    <p class="text-[9px] font-black uppercase tracking-widest text-amber-500">Tareas genéricas</p>
                    <button @click="restablecerTareasGenericas" :disabled="restableciendoGenericas"
                            title="Vuelve a añadir las tareas genéricas precargadas que hayáis borrado"
                            class="shrink-0 px-3 py-1.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500
                                   text-[10px] font-black uppercase tracking-wider hover:bg-gray-100 transition-all disabled:opacity-50">
                      {{ restableciendoGenericas ? 'Restableciendo…' : '↺ Restablecer precargadas' }}
                    </button>
                  </div>
                  <p class="text-[11px] text-gray-400 mb-4">
                    Proceso de trabajo precargado a partir de la ficha del reto (buscar información, organizar, lluvia de ideas… incluye QA interno antes de entregar) — editadlas y añadid las vuestras.
                  </p>

                  <div class="space-y-2 mb-4">
                    <div v-for="t in tareasGenericas" :key="t.id"
                         class="flex items-center gap-3 p-3 rounded-xl border transition-all"
                         :class="{
                           'border-green-200 bg-green-50': t.estado === 'realizado',
                           'border-amber-200 bg-amber-50': t.estado === 'en_progreso',
                           'border-gray-100 bg-gray-50':   t.estado === 'pendiente',
                         }">
                      <select :value="t.estado" @change="cambiarEstadoTarea(t, $event.target.value)"
                              class="text-[10px] font-black uppercase tracking-wider border-0 bg-transparent
                                     focus:outline-none cursor-pointer"
                              :class="{
                                'text-green-600': t.estado === 'realizado',
                                'text-amber-600': t.estado === 'en_progreso',
                                'text-gray-400':  t.estado === 'pendiente',
                              }">
                        <option value="pendiente">⬜ Pendiente</option>
                        <option value="en_progreso">🔄 En progreso</option>
                        <option value="realizado">✅ Realizado</option>
                      </select>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-[#1F2937] leading-snug flex items-center gap-1.5 flex-wrap"
                           :class="{ 'line-through text-gray-400': t.estado === 'realizado' }">
                          {{ t.descripcion }}
                          <span v-if="t.obligatoria"
                                title="No se puede eliminar. Es control de calidad (QA): repasad el trabajo en equipo antes de darlo por terminado."
                                class="shrink-0 px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[9px] font-black uppercase tracking-wider cursor-help">
                            🔒 Obligatoria
                          </span>
                        </p>
                        <input :value="t.responsable" @change="actualizarResponsableTarea(t, $event.target.value.trim())"
                               type="text" placeholder="+ responsable" maxlength="100"
                               class="mt-0.5 text-[10px] text-gray-500 bg-transparent border-0 border-b border-dashed
                                      border-gray-200 focus:outline-none focus:border-amber-400 px-0 py-0.5 w-32"/>
                      </div>
                      <button v-if="!t.obligatoria" @click="eliminarTarea(t)" class="text-gray-200 hover:text-red-400 transition-colors font-black text-xs shrink-0">✕</button>
                    </div>
                    <p v-if="!tareasGenericas.length" class="text-sm text-gray-400 text-center py-6">
                      Añadid tareas genéricas de trabajo
                    </p>
                  </div>

                  <div class="border-t border-gray-100 pt-4 space-y-2">
                    <div class="flex gap-2">
                      <input v-model="nuevaTareaGenerica.descripcion" type="text"
                             placeholder="Añadir tarea genérica (ej. Buscar más referencias)…"
                             class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                    focus:outline-none focus:border-amber-400 bg-gray-50"
                             @keydown.enter="addTareaGenerica"/>
                    </div>
                    <div class="flex gap-2">
                      <input v-model="nuevaTareaGenerica.responsable" type="text" placeholder="Responsable (nombre)"
                             class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                    focus:outline-none focus:border-amber-400 bg-gray-50"/>
                      <button @click="addTareaGenerica" :disabled="!nuevaTareaGenerica.descripcion.trim() || cargandoTareaGenerica"
                              class="px-4 py-2 rounded-xl bg-amber-500 text-white text-xs font-black
                                     uppercase tracking-wider disabled:opacity-50 hover:bg-amber-600 transition-all">
                        + Tarea
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Tareas más complejas -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                  <div class="flex items-center justify-between mb-1">
                    <p class="text-[9px] font-black uppercase tracking-widest text-violet-500">Tareas más complejas</p>
                    <button @click="sugerirTareas" :disabled="sugiriendoTareas"
                            class="shrink-0 px-3 py-1.5 rounded-full bg-violet-50 border border-violet-200 text-violet-700
                                   text-[10px] font-black uppercase tracking-wider hover:bg-violet-100 transition-all disabled:opacity-50">
                      {{ sugiriendoTareas ? 'Generando…' : '✨ Sugerir con IA' }}
                    </button>
                  </div>
                  <div class="bg-violet-50 border border-violet-200 rounded-xl px-3.5 py-2.5 mb-3">
                    <p class="text-sm font-black text-violet-800 leading-snug">
                      Tareas complejas: sugerencias de soluciones a partir de vuestra propuesta inicial (Fase 1 · Análisis del reto).
                    </p>
                  </div>
                  <p v-if="errorSugerenciaTareas" class="text-xs text-red-500 font-semibold mb-3">{{ errorSugerenciaTareas }}</p>
                  <p class="text-[11px] text-gray-400 mb-2">
                    Un poco más específicas que las genéricas. Generadlas con IA o añadid las vuestras.
                  </p>
                  <p class="text-[10px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 mb-4 flex items-start gap-1.5">
                    <span class="shrink-0">⚠️</span>
                    <span><strong>Revisa las sugerencias de la IA. Ten criterio propio.</strong></span>
                  </p>

                  <div class="space-y-2 mb-4">
                    <div v-for="t in tareasComplejas" :key="t.id"
                         class="flex items-center gap-3 p-3 rounded-xl border transition-all"
                         :class="{
                           'border-green-200 bg-green-50': t.estado === 'realizado',
                           'border-amber-200 bg-amber-50': t.estado === 'en_progreso',
                           'border-gray-100 bg-gray-50':   t.estado === 'pendiente',
                         }">
                      <select :value="t.estado" @change="cambiarEstadoTarea(t, $event.target.value)"
                              class="text-[10px] font-black uppercase tracking-wider border-0 bg-transparent
                                     focus:outline-none cursor-pointer"
                              :class="{
                                'text-green-600': t.estado === 'realizado',
                                'text-amber-600': t.estado === 'en_progreso',
                                'text-gray-400':  t.estado === 'pendiente',
                              }">
                        <option value="pendiente">⬜ Pendiente</option>
                        <option value="en_progreso">🔄 En progreso</option>
                        <option value="realizado">✅ Realizado</option>
                      </select>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-[#1F2937] leading-snug flex items-center gap-1.5 flex-wrap"
                           :class="{ 'line-through text-gray-400': t.estado === 'realizado' }">
                          {{ t.descripcion }}
                          <span v-if="t.obligatoria"
                                title="No se puede eliminar. Es control de calidad (QA): repasad el trabajo en equipo antes de darlo por terminado."
                                class="shrink-0 px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[9px] font-black uppercase tracking-wider cursor-help">
                            🔒 Obligatoria
                          </span>
                        </p>
                        <input :value="t.responsable" @change="actualizarResponsableTarea(t, $event.target.value.trim())"
                               type="text" placeholder="+ responsable" maxlength="100"
                               class="mt-0.5 text-[10px] text-gray-500 bg-transparent border-0 border-b border-dashed
                                      border-gray-200 focus:outline-none focus:border-violet-400 px-0 py-0.5 w-32"/>
                      </div>
                      <button v-if="!t.obligatoria" @click="eliminarTarea(t)" class="text-gray-200 hover:text-red-400 transition-colors font-black text-xs shrink-0">✕</button>
                    </div>
                    <p v-if="!tareasComplejas.length" class="text-sm text-gray-400 text-center py-6">
                      Generad tareas con IA o añadid las vuestras
                    </p>
                  </div>

                  <div class="border-t border-gray-100 pt-4 space-y-2">
                    <div class="flex gap-2">
                      <input v-model="nuevaTareaCompleja.descripcion" type="text"
                             placeholder="Añadir tarea más compleja (ej. Diseñar la pantalla de inicio)…"
                             class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                    focus:outline-none focus:border-violet-400 bg-gray-50"
                             @keydown.enter="addTareaCompleja"/>
                    </div>
                    <div class="flex gap-2">
                      <input v-model="nuevaTareaCompleja.responsable" type="text" placeholder="Responsable (nombre)"
                             class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                    focus:outline-none focus:border-violet-400 bg-gray-50"/>
                      <button @click="addTareaCompleja" :disabled="!nuevaTareaCompleja.descripcion.trim() || cargandoTareaCompleja"
                              class="px-4 py-2 rounded-xl bg-violet-500 text-white text-xs font-black
                                     uppercase tracking-wider disabled:opacity-50 hover:bg-violet-600 transition-all">
                        + Tarea
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <button @click="onCompletarF2" :disabled="guardando"
                      :class="['w-full py-3.5 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                               f2Valido ? 'bg-amber-500 text-white hover:bg-amber-600' : 'bg-gray-100 text-gray-400 hover:bg-gray-200']">
                {{ f2Valido ? 'Todo listo — Ir a Entrega →' : 'Elegid el tipo de prototipo y completad todas las tareas para continuar' }}
              </button>
              <div v-if="intentoF2 && !f2Valido" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 space-y-1">
                <p class="text-[10px] font-black text-red-500 uppercase tracking-wider mb-1">Falta esto para continuar:</p>
                <p v-for="(it, idx) in f2Faltantes.filter(x => !x.ok)" :key="idx" class="text-xs text-red-600 flex items-center gap-1.5">
                  <span>⚠</span> {{ it.label }}
                </p>
              </div>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 3 — Entrega de la solución             -->
            <!-- ════════════════════════════════════════════ -->
            <div v-else-if="faseVista === 3" class="space-y-5">

              <div v-if="getFase(3).validado_docente"
                   class="bg-green-50 border border-green-200 rounded-2xl p-4">
                <p class="text-sm font-bold text-green-700">✓ Entrega validada por el docente</p>
                <p v-if="getFase(3).nota_docente" class="text-xs text-green-600 mt-1">
                  Nota: <strong>{{ getFase(3).nota_docente }}/10</strong>
                  <span v-if="getFase(3).observaciones_docente"> · {{ getFase(3).observaciones_docente }}</span>
                </p>
              </div>
              <div v-else-if="getFase(3).completada"
                   class="bg-orange-50 border border-orange-200 rounded-2xl px-4 py-3">
                <p class="text-sm font-bold text-orange-700">Entrega enviada — esperando validación del docente</p>
              </div>

              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 space-y-4">
                <div class="bg-orange-50 border border-orange-100 rounded-xl px-4 py-3 space-y-3">
                  <div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-orange-600 mb-1.5">Requisitos mínimos del entregable</p>
                    <ul class="space-y-1">
                      <li class="text-xs text-orange-900 flex items-start gap-1.5">
                        <span class="font-black shrink-0">✓</span> Solución clara y bien explicada
                      </li>
                      <li class="text-xs text-orange-900 flex items-start gap-1.5">
                        <span class="font-black shrink-0">✓</span> Justificación: por qué responde a la necesidad de la empresa
                      </li>
                      <li class="text-xs text-orange-900 flex items-start gap-1.5">
                        <span class="font-black shrink-0">✓</span> Qué habéis hecho y cómo lo habéis hecho
                      </li>
                    </ul>
                  </div>
                  <div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-orange-600 mb-1.5">Entrega y ejemplos</p>
                    <p class="text-xs text-orange-900 leading-relaxed">
                      Podéis entregar: un <strong>informe en PDF (siempre obligatorio)</strong> + materiales de apoyo opcionales
                      (vídeo, imágenes, prototipo interactivo…).
                    </p>
                  </div>
                  <p class="text-[11px] text-orange-700 italic leading-relaxed">
                    Ten en cuenta que esto podrá presentarse opcionalmente a vuestros compañeros, si así lo decide el docente o dinamizador.
                  </p>
                </div>

                <div ref="f3EntregableRef" class="rounded-xl transition-all"
                     :class="intentoF3 && !f3Valido ? 'ring-2 ring-red-200 border border-red-300 p-3 -m-1' : ''">
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    Descripción del entregable <span class="text-red-400">*</span>
                  </label>
                  <textarea v-model="f3.descripcion_entregable" rows="4"
                            placeholder="Describid brevemente qué entregáis, cómo funciona y qué problemas resuelve..."
                            class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5
                                   focus:outline-none focus:border-orange-400 bg-gray-50 resize-none"/>
                </div>
                <div class="space-y-3">
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider">
                    Enlace al entregable final
                  </label>
                  <input v-model="f3.url_entregable" type="url"
                         placeholder="https://drive.google.com/... o https://github.com/..."
                         class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                focus:outline-none focus:border-orange-400 bg-gray-50"/>
                  <p class="text-[10px] text-gray-400">Pega un enlace externo (Drive, GitHub, Figma, Canva…) o sube archivos directamente.</p>

                  <!-- Subir archivo -->
                  <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer px-4 py-2 rounded-xl
                                  border border-dashed border-orange-300 bg-orange-50 text-orange-700
                                  text-xs font-black uppercase tracking-wider hover:bg-orange-100 transition-all
                                  select-none"
                           :class="subiendoArchivoEntregable ? 'opacity-50 pointer-events-none' : ''">
                      <span v-if="subiendoArchivoEntregable">Subiendo…</span>
                      <span v-else>📎 Subir archivo</span>
                      <input type="file" class="hidden"
                             accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.gif,.webp,.mp4,.mov,.avi,.mkv,.webm,.zip"
                             :disabled="subiendoArchivoEntregable"
                             @change="subirArchivoEntregable"/>
                    </label>
                    <p class="text-[10px] text-gray-400 mt-1">PDF, imágenes, vídeos, documentos Office, ZIP — máx. 20 MB</p>
                  </div>

                  <!-- Error de subida -->
                  <p v-if="errorArchivoEntregable" class="text-xs text-red-500 font-semibold">{{ errorArchivoEntregable }}</p>

                  <!-- Lista de archivos subidos -->
                  <ul v-if="archivosEntregable.length" class="space-y-2">
                    <li v-for="p in archivosEntregable" :key="p.id"
                        class="flex items-center gap-3 p-3 bg-white rounded-xl border border-gray-100 shadow-sm">
                      <span class="text-xl shrink-0">{{ iconoMime(p.mime) }}</span>
                      <div class="flex-1 min-w-0">
                        <a :href="p.url" target="_blank" rel="noopener"
                           class="text-sm font-semibold text-orange-700 hover:underline truncate block">
                          {{ p.filename }}
                        </a>
                        <p class="text-[10px] text-gray-400">{{ formatBytes(p.size) }}</p>
                      </div>
                      <button @click="eliminarArchivoEntregable(p)"
                              class="shrink-0 text-gray-300 hover:text-red-400 transition-colors text-lg leading-none"
                              title="Eliminar archivo">×</button>
                    </li>
                  </ul>
                </div>
              </div>

              <div class="flex gap-3">
                <button @click="guardarF3" :disabled="guardando"
                        class="flex-1 py-3 rounded-2xl bg-orange-50 border border-orange-200
                               text-orange-700 text-sm font-black uppercase tracking-wider
                               hover:bg-orange-100 transition-all disabled:opacity-50">
                  Guardar
                </button>
                <button @click="onCompletarF3" :disabled="guardando"
                        :class="['flex-1 py-3 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                                 f3Valido ? 'bg-orange-500 text-white hover:bg-orange-600' : 'bg-gray-100 text-gray-400 hover:bg-gray-200']">
                  Enviar entrega →
                </button>
              </div>
              <div v-if="intentoF3 && !f3Valido" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 space-y-1">
                <p class="text-[10px] font-black text-red-500 uppercase tracking-wider mb-1">Falta esto para continuar:</p>
                <p v-for="(it, idx) in f3Faltantes.filter(x => !x.ok)" :key="idx" class="text-xs text-red-600 flex items-center gap-1.5">
                  <span>⚠</span> {{ it.label }}
                </p>
              </div>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 4 — Presentación y reflexión            -->
            <!-- ════════════════════════════════════════════ -->
            <div v-else-if="faseVista === 4" class="space-y-5">

              <!-- Exposición a clase -->
              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 space-y-4">
                <div>
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1">
                    ¿Tu grupo expone a clase?
                  </label>
                  <p class="text-[11px] text-gray-400 mb-2">Consultad al docente o dinamizador.</p>
                  <div class="flex gap-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="radio" :value="true" v-model="f4.expone_clase" @change="guardarF4"
                             class="w-4 h-4 accent-[#00A859] cursor-pointer"/>
                      <span class="text-sm font-semibold text-[#1F2937]">Sí</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="radio" :value="false" v-model="f4.expone_clase" @change="guardarF4"
                             class="w-4 h-4 accent-[#00A859] cursor-pointer"/>
                      <span class="text-sm font-semibold text-[#1F2937]">No</span>
                    </label>
                  </div>
                </div>

                <div v-if="f4.expone_clase === true" class="border-t border-gray-100 pt-4">
                  <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-3">Organización de la exposición</p>

                  <div class="mb-4">
                    <label class="block text-xs font-bold text-[#1F2937] mb-1.5">¿Quién expone?</label>
                    <div class="space-y-1.5">
                      <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" value="todos" v-model="f4.organizacion.modo_intervencion" @change="guardarF4"
                               class="w-4 h-4 accent-[#00A859] cursor-pointer"/>
                        <span class="text-sm text-[#1F2937]">Todo el alumnado expone una parte (tiempos repartidos)</span>
                      </label>
                      <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" value="portavoz" v-model="f4.organizacion.modo_intervencion" @change="guardarF4"
                               class="w-4 h-4 accent-[#00A859] cursor-pointer"/>
                        <span class="text-sm text-[#1F2937]">Solo el portavoz expone</span>
                      </label>
                    </div>
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-[#1F2937] mb-1.5">Tipo de exposición</label>
                    <div class="flex flex-wrap gap-2">
                      <label v-for="tipo in TIPOS_EXPOSICION" :key="tipo"
                             class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border cursor-pointer
                                    text-xs font-semibold transition-all"
                             :class="f4.organizacion.tipo_exposicion.includes(tipo)
                               ? 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]'
                               : 'bg-gray-50 border-gray-200 text-gray-500'">
                        <input type="checkbox" :value="tipo" v-model="f4.organizacion.tipo_exposicion" @change="guardarF4"
                               class="hidden"/>
                        {{ tipo }}
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Formulario de reflexión activo -->
              <div v-if="modoReflexion" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 space-y-4">
                <div class="flex items-center justify-between">
                  <p class="text-sm font-black text-[#121212]">
                    {{ modoReflexion === 'individual' ? '👤 Reflexión individual' : '👥 Reflexión grupal' }}
                  </p>
                  <button @click="modoReflexion = ''"
                          class="text-gray-300 hover:text-gray-500 font-black text-sm">✕</button>
                </div>

                <div v-if="modoReflexion === 'individual'">
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">Tu nombre <span class="text-red-400">*</span></label>
                  <input v-model="nombreAlumno" type="text" placeholder="Escribe tu nombre"
                         class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                focus:outline-none focus:border-green-400 bg-gray-50"/>
                </div>

                <div class="space-y-4">
                  <div v-for="(item, i) in (modoReflexion === 'individual' ? respuestasIndiv : respuestasGrupal)" :key="i">
                    <label class="block text-xs font-bold text-[#1F2937] mb-1.5">
                      {{ i + 1 }}. {{ item.pregunta }}
                    </label>
                    <textarea v-model="item.respuesta" rows="2"
                              class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                     focus:outline-none focus:border-green-400 bg-gray-50 resize-none"
                              placeholder="Tu reflexión..."/>
                  </div>
                </div>

                <button @click="enviarReflexion(modoReflexion)"
                        :disabled="guardandoReflexion || (modoReflexion === 'individual' && !nombreAlumno.trim())"
                        class="w-full py-3.5 rounded-2xl bg-[#00A859] text-white text-sm font-black
                               uppercase tracking-widest hover:bg-[#00A859]/90 transition-all disabled:opacity-50">
                  Enviar reflexión →
                </button>
              </div>

              <template v-else>
                <!-- Reflexión grupal -->
                <div ref="f4ReflexionGrupalRef" class="bg-white rounded-2xl border shadow-sm p-4 sm:p-5 transition-all"
                     :class="intentoF4 && !reflexionGrupal ? 'border-red-300 ring-2 ring-red-200' : 'border-gray-100'">
                  <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-3">Reflexión grupal</p>
                  <div v-if="reflexionGrupal">
                    <div v-for="r in reflexionGrupal.respuestas" :key="r.pregunta" class="mb-3 last:mb-0">
                      <p class="text-[11px] font-bold text-gray-500 mb-0.5">{{ r.pregunta }}</p>
                      <p class="text-sm text-[#1F2937]">{{ r.respuesta }}</p>
                    </div>
                  </div>
                  <button v-else @click="abrirReflexion('grupal')"
                          class="w-full py-3 rounded-2xl border-2 border-dashed border-green-200
                                 text-sm font-black text-[#00A859] hover:bg-green-50 transition-all">
                    + Añadir reflexión grupal (portavoz)
                  </button>
                </div>

                <!-- Reflexiones individuales -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                  <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-3">
                    Reflexiones individuales ({{ misReflexiones.length }})
                  </p>
                  <div v-if="misReflexiones.length" class="space-y-3 mb-3">
                    <div v-for="r in misReflexiones" :key="r.id"
                         class="bg-green-50 rounded-xl px-4 py-3">
                      <p class="text-xs font-black text-[#00A859] mb-1.5">{{ r.autor_nombre }}</p>
                      <div v-for="resp in r.respuestas.slice(0,2)" :key="resp.pregunta" class="mb-1.5 last:mb-0">
                        <p class="text-[10px] text-gray-400">{{ resp.pregunta }}</p>
                        <p class="text-xs text-[#1F2937]">{{ resp.respuesta }}</p>
                      </div>
                    </div>
                  </div>
                  <button @click="abrirReflexion('individual')"
                          class="w-full py-3 rounded-2xl border-2 border-dashed border-green-200
                                 text-sm font-black text-[#00A859] hover:bg-green-50 transition-all">
                    + Añadir mi reflexión individual
                  </button>
                </div>

                <!-- Info evaluación docente -->
                <div v-if="getFase(4).validado_docente" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                  <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-3">Evaluación curricular (RA/CE)</p>
                  <p v-if="getFase(4).nota_docente" class="text-xs text-green-600 mb-2">
                    Nota del proyecto: <strong>{{ getFase(4).nota_docente }}/10</strong>
                  </p>
                  <div v-if="evaluacionRaCe.length" class="space-y-2">
                    <div v-for="(r, i) in evaluacionRaCe" :key="i" class="bg-green-50 rounded-xl px-4 py-3">
                      <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold text-[#1F2937] flex-1">{{ r.ra }}</p>
                        <span :class="['shrink-0 px-2 py-0.5 rounded-full text-[10px] font-black', NIVEL_COLORS[r.nivel]]">
                          {{ NIVEL_LABELS[r.nivel] || r.nivel }}
                        </span>
                      </div>
                      <p v-if="r.observaciones" class="text-[11px] text-gray-500 mt-1">{{ r.observaciones }}</p>
                    </div>
                  </div>
                  <p v-if="getFase(4).observaciones_docente" class="text-xs text-green-600 mt-3">
                    {{ getFase(4).observaciones_docente }}
                  </p>
                </div>
                <div v-else class="bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3">
                  <p class="text-xs text-gray-400 text-center">
                    La evaluación curricular (RA + niveles + nota) la realizará el docente desde su panel.
                  </p>
                </div>

                <button @click="onCompletarF4" :disabled="guardando"
                        :class="['w-full py-3.5 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                                 f4ValidoParaCompletar ? 'bg-[#00A859] text-white hover:bg-[#00A859]/90' : 'bg-gray-100 text-gray-400 hover:bg-gray-200']">
                  {{ f4ValidoParaCompletar ? '✓ Marcar proyecto como completado' : 'Añadid la reflexión grupal para cerrar' }}
                </button>
                <div v-if="intentoF4 && !f4ValidoParaCompletar" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 space-y-1">
                  <p class="text-[10px] font-black text-red-500 uppercase tracking-wider mb-1">Falta esto para continuar:</p>
                  <p v-for="(it, idx) in f4Faltantes.filter(x => !x.ok)" :key="idx" class="text-xs text-red-600 flex items-center gap-1.5">
                    <span>⚠</span> {{ it.label }}
                  </p>
                </div>

                <!-- Informe de cierre compilado -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                  <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-4">Informe de cierre del microproyecto</p>

                  <div class="space-y-4">
                    <div v-if="informeCierre.retoFrase">
                      <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">El reto</p>
                      <p class="text-sm text-[#1F2937] font-semibold">{{ informeCierre.retoFrase }}</p>
                    </div>

                    <div v-if="informeCierre.hallazgos.length">
                      <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Hallazgos clave</p>
                      <ul class="space-y-0.5">
                        <li v-for="(h, i) in informeCierre.hallazgos.filter(x => x.trim())" :key="i" class="text-sm text-[#1F2937] flex gap-2">
                          <span class="text-gray-300 shrink-0">·</span>{{ h }}
                        </li>
                      </ul>
                    </div>

                    <div v-if="informeCierre.propuesta">
                      <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Propuesta inicial de solución</p>
                      <p class="text-sm text-[#1F2937]">{{ informeCierre.propuesta }}</p>
                      <p v-if="informeCierre.explicacionPropuesta" class="text-sm text-gray-500 mt-1">{{ informeCierre.explicacionPropuesta }}</p>
                    </div>

                    <div v-if="informeCierre.tipoPrototipo">
                      <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Prototipo</p>
                      <p class="text-sm text-[#1F2937]">
                        {{ informeCierre.tipoPrototipo }}
                        <a v-if="informeCierre.prototipoUrl" :href="informeCierre.prototipoUrl" target="_blank" rel="noopener"
                           class="text-blue-600 hover:underline ml-1">(enlace)</a>
                      </p>
                      <p v-if="archivosPrototipo.length" class="text-xs text-gray-400 mt-1">{{ archivosPrototipo.length }} archivo(s) adjunto(s)</p>
                    </div>

                    <div>
                      <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Tareas</p>
                      <p class="text-sm text-[#1F2937]">{{ informeCierre.tareasRealizadas }} de {{ informeCierre.tareasTotal }} completadas</p>
                    </div>

                    <div v-if="informeCierre.entregableDesc">
                      <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Entregable final</p>
                      <p class="text-sm text-[#1F2937]">{{ informeCierre.entregableDesc }}</p>
                      <a v-if="informeCierre.entregableUrl" :href="informeCierre.entregableUrl" target="_blank" rel="noopener"
                         class="text-xs text-blue-600 hover:underline">{{ informeCierre.entregableUrl }}</a>
                      <p v-if="archivosEntregable.length" class="text-xs text-gray-400 mt-1">{{ archivosEntregable.length }} archivo(s) adjunto(s)</p>
                    </div>
                  </div>
                </div>
              </template>
            </div>

          </div><!-- /main content -->

          <!-- ─────────────────────────────────────────────── -->
          <!--  SIDEBAR DERECHO                                -->
          <!-- ─────────────────────────────────────────────── -->
          <!-- Separador visual del sidebar solo en móvil -->
          <aside class="w-full lg:w-72 xl:w-80 shrink-0 space-y-4 lg:sticky lg:top-[62px]
                         border-t border-gray-200 pt-4 lg:border-t-0 lg:pt-0">

            <!-- Tarjeta equipo + miembros -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
              <h3 class="text-sm font-black text-[#121212] mb-4">{{ equipo.nombre }}</h3>

              <div v-if="equipo.miembros?.length" class="space-y-3">
                <div v-for="(m, i) in equipo.miembros" :key="i" class="flex items-center gap-3">
                  <div :style="{ backgroundColor: avatarColor(i) }"
                       class="w-9 h-9 rounded-full flex items-center justify-center
                              text-white font-black text-sm shrink-0 select-none">
                    {{ initials(m.nombre) }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-[#1F2937] truncate">{{ m.nombre }}</p>
                    <p class="text-[11px] text-gray-400 capitalize">{{ m.rol || 'Miembro' }}</p>
                  </div>
                </div>
              </div>
              <p v-else class="text-xs text-gray-400 text-center py-2 leading-relaxed">
                Los miembros aparecerán aquí al completar la fase de inicio.
              </p>

              <!-- Centro y docente -->
              <div v-if="proyecto.centro_nombre || proyecto.docente_nombre"
                   class="mt-4 pt-4 border-t border-gray-100 space-y-1">
                <p v-if="proyecto.centro_nombre" class="text-[11px] text-gray-600 font-semibold">
                  {{ proyecto.centro_nombre }}
                  <span v-if="proyecto.curso" class="font-normal text-gray-400"> · {{ proyecto.curso }}</span>
                </p>
                <p v-if="proyecto.docente_nombre" class="text-[11px] text-gray-400">
                  Docente: {{ proyecto.docente_nombre }}
                </p>
              </div>
            </div>

            <!-- Tarjeta progreso general -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
              <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4">
                Progreso general
              </h3>
              <div class="space-y-3">
                <div v-for="f in fasesConfig" :key="f.num" class="flex items-center gap-3">
                  <span class="text-[10px] font-bold text-gray-500 w-[70px] shrink-0 leading-snug">
                    F{{ f.num }} {{ f.shortLabel }}
                  </span>
                  <template v-if="pctFase(f.num) !== null">
                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                      <div class="h-full rounded-full transition-all duration-700"
                           :class="pctFase(f.num) === 100 ? 'bg-[#00A859]' : 'bg-amber-400'"
                           :style="{ width: pctFase(f.num) + '%' }"/>
                    </div>
                    <span class="text-[11px] font-black w-9 text-right shrink-0"
                          :class="pctFase(f.num) === 100 ? 'text-[#00A859]' : 'text-amber-500'">
                      {{ pctFase(f.num) }}%
                    </span>
                  </template>
                  <span v-else class="text-gray-300 text-sm flex-1">—</span>
                </div>
              </div>
            </div>

            <!-- Tarjeta empresa / startup day -->
            <div v-if="proyecto.empresa_nombre" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
              <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">
                Startup Day
              </h3>
              <p class="text-sm text-gray-500 leading-relaxed">
                Empresa validadora:
                <strong class="text-[#1F2937]">{{ proyecto.empresa_nombre }}</strong>
              </p>
            </div>

          </aside><!-- /sidebar -->

        </div>
      </div>

    </template>

    <MicroretoModal :token="verFichaReto ? token : null" @close="verFichaReto = false" />

    <!-- Modal intermedio: aviso antes de pasar de Diseño de solución y desarrollo a Entrega de la solución -->
    <Teleport to="body">
      <Transition enter-active-class="transition-all duration-250" enter-from-class="opacity-0"
                  leave-active-class="transition-all duration-200" leave-to-class="opacity-0">
        <div v-if="mostrarModalPasoF3" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
          <div @click="mostrarModalPasoF3 = false" class="fixed inset-0 bg-black/50 backdrop-blur-sm" />
          <div class="relative z-10 w-full max-w-lg bg-white rounded-[2rem] shadow-2xl border border-gray-200 p-6 sm:p-8">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-orange-500 mb-3">Antes de continuar</p>
            <h2 class="text-xl font-black text-[#1F2937] mb-3 leading-snug">
              Vais a pasar a la fase de Entrega de la solución
            </h2>
            <p class="text-sm text-gray-600 leading-relaxed mb-4">
              La siguiente fase es para la entrega de la solución que proponéis para cubrir la necesidad de la empresa.
            </p>
            <div v-if="diagnostico?.pregunta_reto" class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-4">
              <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">Recordad la necesidad de la empresa</p>
              <p class="text-sm font-bold text-blue-900 leading-snug">"{{ diagnostico.pregunta_reto }}"</p>
            </div>
            <p class="text-sm text-gray-600 leading-relaxed mb-6">
              Antes de pasar a Fase 3, revisad que habéis completado las tareas.
            </p>
            <div class="flex gap-3">
              <button @click="mostrarModalPasoF3 = false"
                      class="flex-1 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-gray-600
                             text-sm font-black uppercase tracking-wider hover:bg-gray-100 transition-all">
                Revisar tareas
              </button>
              <button @click="confirmarPasoF3" :disabled="guardando"
                      class="flex-1 py-3 rounded-2xl bg-orange-500 text-white text-sm font-black uppercase
                             tracking-wider hover:bg-orange-600 transition-all disabled:opacity-50">
                Continuar a Fase 3 →
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Modal intermedio: aviso antes de pasar de Entrega de la solución a Presentación -->
    <Teleport to="body">
      <Transition enter-active-class="transition-all duration-250" enter-from-class="opacity-0"
                  leave-active-class="transition-all duration-200" leave-to-class="opacity-0">
        <div v-if="mostrarModalPasoF4" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
          <div @click="mostrarModalPasoF4 = false" class="fixed inset-0 bg-black/50 backdrop-blur-sm" />
          <div class="relative z-10 w-full max-w-lg bg-white rounded-[2rem] shadow-2xl border border-gray-200 p-6 sm:p-8">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-green-600 mb-3">Antes de continuar</p>
            <h2 class="text-xl font-black text-[#1F2937] mb-3 leading-snug">
              Vais a pasar a la fase de Presentación
            </h2>
            <p class="text-sm text-gray-600 leading-relaxed mb-3">
              La presentación es el broche final del proyecto.
            </p>
            <div class="bg-green-50 border border-green-100 rounded-xl px-4 py-3 mb-4 space-y-1.5">
              <p class="text-sm text-[#1F2937] flex items-start gap-2">
                <span class="text-[#00A859] font-black mt-0.5 shrink-0">•</span>
                Podrá haber una exposición a vuestros compañeros — en forma de role playing, con un PowerPoint resumen, etc.
              </p>
              <p class="text-sm text-[#1F2937] flex items-start gap-2">
                <span class="text-[#00A859] font-black mt-0.5 shrink-0">•</span>
                Habrá una reflexión final, individual y de equipo.
              </p>
            </div>
            <div class="flex gap-3">
              <button @click="mostrarModalPasoF4 = false"
                      class="flex-1 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-gray-600
                             text-sm font-black uppercase tracking-wider hover:bg-gray-100 transition-all">
                Revisar entrega
              </button>
              <button @click="confirmarPasoF4" :disabled="guardando"
                      class="flex-1 py-3 rounded-2xl bg-[#00A859] text-white text-sm font-black uppercase
                             tracking-wider hover:bg-[#00A859]/90 transition-all disabled:opacity-50">
                Continuar a Fase 4 →
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
