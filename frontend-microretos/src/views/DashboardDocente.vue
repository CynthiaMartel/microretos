<!-- Ruta: /encuentros/crear (name: dashboard-docente). El nombre del archivo quedó del naming antiguo (/dashboard) tras el rename de URLs — ver router/index.js. -->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api.js'
import { crearCodigoAcceso } from '../services/encuentroService.js'
import MicroretoModal from '../components/MicroretoModal.vue'
import ProyectoFichaModal from '../components/ProyectoFichaModal.vue'
import TallerPasos from '../components/TallerPasos.vue'
import MiniaturaReto from '../components/MiniaturaReto.vue'
import EncuentroResumen from '../components/EncuentroResumen.vue'
import RegenerarAliasButton from '../components/RegenerarAliasButton.vue'
import AvisoSinNombresReales from '../components/AvisoSinNombresReales.vue'
import PlanificacionSesiones from '../components/PlanificacionSesiones.vue'
import { generarAliasAleatorio, errorNombrePila } from '../utils/alias.js'
import { formatCurso, nivelCurso, CURSO_ENCUENTRO } from '../utils/formatCurso.js'
import { useGuia } from '../composables/useGuia.js'
import { useAuthStore } from '../stores/auth.js'
import { fechaFinEstimada as calcularFechaFinEstimada, duracionPorFase, FASES_PROYECTO } from '../config/fasesProyecto.js'

const { recorrerPantalla } = useGuia()
const authStore = useAuthStore()
const isLoaded = ref(false)
onMounted(() => { setTimeout(() => { isLoaded.value = true }, 80) })

const router = useRouter()
const route  = useRoute()

// ─── Formulario de encuentro ──────────────────────────────────────────────────────
const form = ref({
  fecha:            new Date().toISOString().slice(0, 10),
  // Vacía = la estimada según la planificación; el docente puede alargarla (nunca acortarla)
  fecha_fin:        '',
  centro_educativo: authStore.userCentroNombre || '',
  ciclo_formativo:  '',
  curso:            '',
  grupo:            '',
  num_alumnos:      '',
  num_equipos:      3,
  alumnados:        [],
  notas:            '',
  microproyecto_id: null,
  // Sesiones del encuentro y sus fases: se rellena con las de la propuesta y se ajusta aquí
  planificacion_sesiones: [],
})

// Por defecto, una sola sesión con todas las fases; el docente añade más si las necesita
// (antes se copiaba el calendario de la propuesta, que ya no se edita en el ajuste del reto).
const planificacionInicial = () => [{ fases: FASES_PROYECTO.map(f => f.num) }]

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

// Todas las que pasan los filtros; la lista muestra como mucho 60 (contador «60 de N»)
const proyectosFiltradosTodos = computed(() => {
  // Solo proyectos ya validados (al menos por el docente) pueden asociarse a un
  // encuentro — una propuesta sin validar, un borrador o un archivado no tiene
  // sentido programarlo en clase (ver StoreEncuentroRequest, mismo criterio en backend).
  let base = todosMisProyectos.value.filter(p => p.estado === 'validado' || p.estado === 'completado')
  // Las propuestas guardan '1' | '2' | 'ambos_cursos' (antes se comparaba con '1º'/'2º' y casi nunca coincidía)
  if (filtroProyCurso.value)  base = base.filter(p => nivelCurso(p.curso) === filtroProyCurso.value)
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
  return base
})
const proyectosFiltrados = computed(() => proyectosFiltradosTodos.value.slice(0, 60))
const totalProyectosFiltrados = computed(() => proyectosFiltradosTodos.value.length)

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

// Flujo continuo desde el ajuste del reto (?desde=ajuste&microproyecto=<uuid>&fecha=hoy|otro):
// el reto ajustado llega elegido; «Ahora no» vuelve a su ficha sin guardar nada.
const desdeAjuste = route.query.desde === 'ajuste'
function salirSinEncuentro() {
  const uuid = proyectoSeleccionado.value?.uuid || route.query.microproyecto
  if (desdeAjuste && uuid) router.push({ name: 'startup-day-detalle', params: { uuid } })
  else router.push('/encuentros')
}

function seleccionarProyecto(p) {
  proyectoSeleccionado.value   = p
  form.value.microproyecto_id  = p.id
  form.value.planificacion_sesiones = planificacionInicial(p)
  if (p.ciclo_nombre) form.value.ciclo_formativo = p.ciclo_nombre
  // Curso del encuentro = el de la propuesta (1º, 2º o ambos), ya marcado en el formulario
  if (nivelCurso(p.curso)) form.value.curso   = CURSO_ENCUENTRO[nivelCurso(p.curso)]
  aplicarSesionSugerida(p.sesion_sugerida)
  mostrarBuscadorProy.value    = false
}

// Reto de una solicitud de docente: la fecha y el horario que propuso se sugieren aquí (el
// encuentro guarda la fecha; el horario va a las notas). Todo se puede cambiar.
const sesionSugerida = ref(null)
const textoHorario = (s) => s?.hora_inicio ? `${s.hora_inicio}${s.hora_fin ? '–' + s.hora_fin : ''}${s.duracion ? ` (${s.duracion})` : ''}` : ''
function aplicarSesionSugerida(s) {
  sesionSugerida.value = s || null
  if (!s?.fecha) return
  if (s.fecha >= new Date().toISOString().slice(0, 10)) form.value.fecha = s.fecha
  const horario = textoHorario(s)
  if (horario && !form.value.notas.includes(horario)) {
    form.value.notas = [`Sesión propuesta por el docente: ${horario}.`, form.value.notas].filter(Boolean).join('\n')
  }
}

function limpiarProyecto() {
  proyectoSeleccionado.value   = null
  sesionSugerida.value         = null
  form.value.microproyecto_id  = null
  form.value.planificacion_sesiones = []
  form.value.fecha_fin         = ''
  form.value.ciclo_formativo   = ''
  form.value.curso             = ''
  // El reparto de equipos se hizo pensando en el proyecto anterior — no tiene
  // sentido mantenerlo al asociar uno distinto.
  form.value.num_equipos       = 3
  form.value.alumnados         = []
}

// Nº de sesiones planificadas en este encuentro — el backend calcula la fecha_fin real
// con la misma heurística centralizada (fasesProyecto.js / Encuentro::fechaFinSugerida).
const totalClasesProyectoSeleccionado = computed(() => form.value.planificacion_sesiones.length)
const planificacionValida = computed(() =>
  form.value.planificacion_sesiones.length > 0
  && form.value.planificacion_sesiones.every(s => (s.fases || []).length > 0))

const fechaFinEstimada = computed(() => {
  return calcularFechaFinEstimada(form.value.fecha, totalClasesProyectoSeleccionado.value)
})
// La que se guarda: la elegida por el docente o, si no ha tocado el campo, la estimada
const fechaFin = computed(() => form.value.fecha_fin || fechaFinEstimada.value)
// Si cambian la fecha de inicio o las sesiones y la elegida queda antes de la mínima, vuelve a la estimada
watch(fechaFinEstimada, (min) => {
  if (form.value.fecha_fin && min && form.value.fecha_fin < min) form.value.fecha_fin = ''
})

function estadoProyectoBadge(proyectoOEstado) {
  const estado          = typeof proyectoOEstado === 'object' ? proyectoOEstado?.estado : proyectoOEstado

  if (estado === 'borrador' || estado === 'en_edicion')
    return { label: 'En edición',  cls: 'bg-amber-100 text-amber-600' }
  if (estado === 'archivado')
    return { label: 'Archivado',   cls: 'bg-red-50 text-red-400' }
  if (estado === 'validado')
    return { label: 'Validado', cls: 'bg-empresas/10 text-empresas-dark' }
  if (estado === 'completado')
    return { label: 'Completado', cls: 'bg-sky-50 text-sky-700' }
  if (estado === 'propuesta')
    return { label: 'Sin validar', cls: 'bg-violet-50 text-violet-600' }
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

// El número de alumnos sale de los que hay en los grupos; el tope es el del backend (200)
const limiteAlumnados = computed(() => 200)

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
    formatFecha(e.fecha), e.proyecto_titulo || 'Sin reto ajustado',
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

// «Pegar una lista»: un nombre de pila por línea (o separados por comas); cada uno va al grupo
// con menos alumnado. Los que no son válidos se quedan en el cuadro para corregirlos.
const pegandoLista = ref(false)
const listaNombres = ref('')
const mensajeLista = ref('')
function anadirListaNombres() {
  const nombres = listaNombres.value.split(/[\n,;]+/).map(n => n.trim()).filter(Boolean)
  const grupos = Math.max(1, Number(form.value.num_equipos) || 1)
  const cuenta = (n) => form.value.alumnados.filter(a => a.equipo_num === n).length
  const pendientes = []
  let anadidos = 0
  for (const nombre of nombres) {
    if (limiteAlcanzado.value || errorNombrePila(nombre)) { pendientes.push(nombre); continue }
    let destino = 1
    for (let n = 2; n <= grupos; n++) if (cuenta(n) < cuenta(destino)) destino = n
    form.value.alumnados.push({ equipo_num: destino, alias: generarAliasAleatorio(nombre, aliasUsados.value) })
    anadidos++
  }
  listaNombres.value = pendientes.join('\n')
  mensajeLista.value = [
    anadidos ? `${anadidos} ${anadidos === 1 ? 'añadido' : 'añadidos'} y repartidos en ${grupos} grupos.` : '',
    pendientes.length ? `${pendientes.length} sin añadir: solo el nombre de pila, sin apellidos${limiteAlcanzado.value ? ', o se ha llegado al máximo' : ''}.` : '',
  ].filter(Boolean).join(' ')
  if (!pendientes.length) pegandoLista.value = false
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
const encuentroCreado = ref(null)

// «Proyectar QR y códigos» al terminar: crea los grupos y el código de la clase (si aún no
// los tiene) y abre la pantalla de acceso de este encuentro, sin pasar por la biblioteca.
const abriendoProyeccion = ref(false)
const errorProyeccion    = ref('')
async function proyectarAcceso() {
  const e = encuentroCreado.value
  if (!e) return
  abriendoProyeccion.value = true
  errorProyeccion.value = ''
  try {
    if (!e.codigo_clase) await crearCodigoAcceso(e.id)
    router.push({ name: 'pantalla-acceso', params: { uuid: e.microproyecto_uuid }, query: { encuentro: e.id } })
  } catch (err) {
    errorProyeccion.value = err.response?.data?.error || 'No se pudo crear el código de acceso. Inténtalo desde la biblioteca de encuentros.'
  } finally {
    abriendoProyeccion.value = false
  }
}

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
  if (route.query.microproyecto_id || route.query.microproyecto) {
    try {
      const res = await api.get('/startup/proyectos')
      todosMisProyectos.value = res.data
      proyectosCargados.value = true
      const p = todosMisProyectos.value.find(pr => route.query.microproyecto
        ? pr.uuid === route.query.microproyecto : String(pr.id) === String(route.query.microproyecto_id))
      if (p) seleccionarProyecto(p)
      // «Otro día»: sin fecha, para que la elija; «Empezar hoy» deja la de hoy (por defecto)
      if (desdeAjuste && route.query.fecha === 'otro') form.value.fecha = ''
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
})

// ─── Guardar encuentro ─────────────────────────────────────────────────────────
async function guardarEncuentro() {
  if (!formularioValido.value) return
  guardando.value = true
  errorGuardado.value = ''
  try {
    const payload = {
      ...form.value,
      num_alumnos: form.value.alumnados.length || null,
    }
    const res = await api.post('/encuentros', payload)
    encuentros.value = [res.data, ...encuentros.value]
    encuentroCreado.value = res.data
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

const formularioValido = computed(() => equiposRepartidos.value && planificacionValida.value)

const requisitosFaltantes = computed(() => {
  const faltantes = []
  if (!proyectoAsociado.value) {
    faltantes.push('Asocia un reto ajustado al encuentro.')
    return faltantes
  }
  if (!form.value.fecha) faltantes.push('Establece la fecha de inicio.')
  if (!form.value.curso) faltantes.push('Indica el curso del encuentro.')
  if (!form.value.grupo) faltantes.push('Indica la clase del encuentro.')
  if (!planificacionValida.value) faltantes.push('Planifica las sesiones: cada una con al menos una fase.')
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
  if (!proyectoAsociado.value) f.push('Asocia un reto ajustado al encuentro.')
  if (!form.value.fecha) f.push('Establece la fecha de inicio.')
  if (!form.value.curso) f.push('Indica el curso del encuentro.')
  if (!form.value.grupo) f.push('Indica la clase del encuentro.')
  if (proyectoAsociado.value && !planificacionValida.value) f.push('Planifica las sesiones: cada una con al menos una fase.')
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
// «Cambiar reto ajustado» vacía también grupos y alumnado: se pide confirmación y se guarda una
// copia en memoria para poder volver atrás («Restaurar reto ajustado anterior»).
const confirmandoCambioProyecto = ref(false)
const proyectoAnterior          = ref(null)   // { proyecto, form, sesionSugerida }
function cambiarProyecto() {
  proyectoAnterior.value = {
    proyecto:       proyectoSeleccionado.value,
    form:           JSON.parse(JSON.stringify(form.value)),
    sesionSugerida: sesionSugerida.value,
  }
  confirmandoCambioProyecto.value = false
  limpiarProyecto()
  abrirBuscadorProy()
}
function restaurarProyectoAnterior() {
  const copia = proyectoAnterior.value
  if (!copia) return
  form.value                 = JSON.parse(JSON.stringify(copia.form))
  proyectoSeleccionado.value = copia.proyecto
  sesionSugerida.value       = copia.sesionSugerida
  mostrarBuscadorProy.value  = false
  proyectoAnterior.value     = null
}
function crearOtroEncuentro() {
  limpiarProyecto()
  proyectoAnterior.value = null
  form.value.fecha  = new Date().toISOString().slice(0, 10)
  form.value.grupo  = ''
  form.value.num_alumnos = ''
  form.value.notas  = ''
  guardadoOk.value  = false
  encuentroCreado.value = null
  errorProyeccion.value = ''
  pasoEncMax.value  = 1
  pasoEnc.value     = 1
}

function alumnosTexto(n) { return n ? `${n} ${n === 1 ? 'alumno' : 'alumnos'}` : '' }

// Columna derecha: ficha clave/valor con lo que se va rellenando.
const datosResumenEncuentro = computed(() => [
  { icono: 'centro',     label: 'Centro educativo', valor: authStore.userCentroNombre || form.value.centro_educativo },
  { icono: 'clase',      label: 'Curso y clase',    valor: [form.value.curso && `${form.value.curso} curso`, form.value.grupo && `Clase ${form.value.grupo}`].filter(Boolean).join(' · ') },
  { icono: 'calendario', label: 'Fecha de inicio', valor: proyectoAsociado.value && form.value.fecha ? formatFecha(form.value.fecha) : '' },
  { icono: 'reloj',      label: form.value.fecha_fin ? 'Fecha de fin' : 'Fecha fin estimada', valor: proyectoAsociado.value && fechaFin.value ? formatFecha(fechaFin.value) : '' },
  { icono: 'alumnado',   label: 'Alumnado',         valor: alumnosTexto(form.value.alumnados.length || Number(form.value.num_alumnos) || 0) },
  { icono: 'grupos',     label: 'Grupos',           valor: pasoEncMax.value >= 2 ? `${form.value.num_equipos || 3} grupos` : '' },
].filter(d => d.valor))

const CONSEJO_PASO_ENC = {
  1: 'Verifica que el reto ajustado y los datos sean correctos. Podrás modificar la información del encuentro más adelante.',
  2: 'Usa solo alias (nombre de pila + animal + color): nunca nombres reales completos del alumnado.',
  3: 'Revisa el resumen. Al guardar, los grupos quedarán listos para generar su código de acceso.',
  4: 'En la biblioteca de encuentros generarás el código de acceso y, en «Seguimiento de grupos», seguirás el avance de cada grupo.',
}
const antesDeContinuarEnc = computed(() => {
  if (intentoAvanzar.value && faltantesPaso.value.length && pasoEnc.value < 4)
    return { tipo: 'aviso', titulo: 'Falta completar', texto: faltantesPaso.value[0] }
  return { tipo: 'info', titulo: pasoEnc.value === 4 ? 'Siguiente paso' : 'Antes de continuar', texto: CONSEJO_PASO_ENC[pasoEnc.value] }
})

// Paso 3: resumen para confirmar.
const confirmacionEncuentro = computed(() => [
  { label: 'Reto ajustado',    valor: proyectoSeleccionado.value?.titulo || '—', ancho: true },
  { label: 'Fecha de inicio', valor: formatFecha(form.value.fecha) || '—' },
  { label: form.value.fecha_fin ? 'Fecha de fin' : 'Fecha fin estimada', valor: fechaFin.value ? formatFecha(fechaFin.value) : '—' },
  { label: 'Sesiones planificadas', valor: String(form.value.planificacion_sesiones.length) },
  { label: 'Centro educativo', valor: authStore.userCentroNombre || form.value.centro_educativo || '—' },
  { label: 'Ciclo formativo',  valor: form.value.ciclo_formativo || '—' },
  { label: 'Curso y clase',    valor: `${form.value.curso || '—'} · Clase ${form.value.grupo || '—'}` },
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

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[min(600px,100vw)] h-[400px]
                bg-rol-fuerte opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <!-- Asistente de 4 pasos + columna derecha (diseño crear_encuentro.png) -->
    <div class="relative z-10 mx-auto max-w-[90rem] px-4 pb-10 pt-5 sm:px-6 lg:px-5 xl:px-8">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-5 xl:gap-8">
      <div class="min-w-0 flex-1 space-y-5">

      <CabeceraSeccion data-tour="enc-cabecera" plano :titulo="desdeAjuste ? 'Llevar al' : 'Crear'" :destacado="desdeAjuste ? 'aula' : 'encuentro'" color="text-rol-fuerte"
                       :antetitulo="desdeAjuste ? 'Ajuste del reto · Encuentro' : undefined"
                       volver="/encuentros" volver-label="Volver a encuentros"
                       :subtitulo="desdeAjuste ? 'Pon la fecha, la clase y el alumnado: al terminar proyectas el QR para los grupos.' : 'Selecciona un reto y registra los datos de la clase.'">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Solo icono por debajo de 2xl: deja sitio al título «Crear encuentro» en una línea -->
          <RouterLink to="/encuentros" data-tour="enc-ver-encuentros" class="accion-secundaria max-2xl:w-10 max-2xl:px-0" title="Ver todos tus encuentros"
                      aria-label="Ver todos tus encuentros">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <span class="max-2xl:sr-only">Ver encuentros</span>
          </RouterLink>
          <!-- Ayuda: abre la guía de esta pantalla (la misma que Guía → «Esta pantalla» de la barra superior) -->
          <button type="button" @click="recorrerPantalla"
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

      <TallerPasos data-tour="enc-pasos" variante="linea" etiqueta="Pasos para crear el encuentro"
                   :pasos="pasosEncuentro" :actual="pasoEnc" :maximo="pasoEncMax" :puede-ir="puedeIrAPasoEnc"
                   @ir="pasoEnc = $event" />

      <!-- ═══ PASO 1 · DATOS GENERALES ═══ -->
      <template v-if="pasoEnc === 1">

        <!-- Reto y proyecto asociado -->
        <section data-tour="enc-reto" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-proyecto-asociado">
          <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="flex min-w-0 flex-1 items-start gap-3 sm:gap-4">
              <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol-fuerte text-white sm:h-12 sm:w-12" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
              </span>
              <div class="min-w-0">
                <h2 id="titulo-proyecto-asociado" class="text-lg font-bold text-azul-noche">Reto ajustado</h2>
                <p class="mt-0.5 text-sm text-texto-secundario">Selecciona el reto ajustado validado que se trabajará en este encuentro.</p>
              </div>
            </div>
            <div v-if="(proyectoSeleccionado && !mostrarBuscadorProy && !confirmandoCambioProyecto) || proyectoAnterior"
                 class="flex shrink-0 flex-wrap gap-2 self-start">
              <button v-if="proyectoAnterior" type="button" @click="restaurarProyectoAnterior"
                      :title="`Volver a «${proyectoAnterior.proyecto?.titulo || ''}» con sus datos y grupos`"
                      class="inline-flex h-10 items-center justify-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-800 transition-colors hover:bg-amber-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                <span class="sm:hidden">Restaurar anterior</span><span class="max-sm:hidden">Restaurar reto ajustado anterior</span>
              </button>
              <button v-if="proyectoSeleccionado && !mostrarBuscadorProy && !confirmandoCambioProyecto" type="button" @click="confirmandoCambioProyecto = true"
                      class="inline-flex h-10 items-center justify-center gap-2 rounded-full border border-rol-fuerte/30 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Cambiar reto ajustado
              </button>
            </div>
          </div>

          <div v-if="confirmandoCambioProyecto && proyectoSeleccionado" role="alertdialog" aria-labelledby="aviso-cambio-proyecto"
               class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p id="aviso-cambio-proyecto" class="text-sm font-bold text-amber-900">¿Seguro que quieres cambiar de reto ajustado?</p>
            <p class="mt-1 text-sm leading-relaxed text-amber-800">
              Se quitará «{{ proyectoSeleccionado.titulo }}» de este encuentro junto con su planificación de sesiones, los grupos y el alumnado que hayas añadido.
              Si te equivocas, podrás volver con «Restaurar reto ajustado anterior» mientras no salgas de esta pantalla.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <button type="button" @click="cambiarProyecto" class="accion-principal">Sí, cambiar</button>
              <button type="button" @click="confirmandoCambioProyecto = false" class="accion-secundaria">Cancelar</button>
            </div>
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
                           placeholder="Buscar por título o empresa"
                           class="field-input !pl-9 !text-xs" />
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
                      <button v-for="op in [{ val: '', label: 'Todos' }, { val: '1', label: '1º' }, { val: '2', label: '2º' }, { val: 'ambos', label: 'Ambos' }]"
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
                    <span v-if="cargandoProyectos">Cargando retos ajustados...</span>
                    <span v-else>
                      <template v-if="totalProyectosFiltrados > proyectosFiltrados.length">{{ proyectosFiltrados.length }} de </template>{{ totalProyectosFiltrados }}
                      {{ totalProyectosFiltrados === 1 ? 'reto ajustado' : 'retos ajustados' }}
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
                        <span v-if="nivelCurso(p.curso)" class="tag tag-lime">{{ nivelCurso(p.curso) === 'ambos' ? 'Ambos cursos' : nivelCurso(p.curso) + 'º' }}</span>
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
                  <p class="text-xs font-medium">Sin retos ajustados para esos filtros</p>
                </div>

              </div>

          <!-- Estado inicial — sin selector abierto ni proyecto -->
          <div v-else class="space-y-3">
            <p class="flex items-start gap-2 rounded-xl bg-rol/5 px-3 py-2.5 text-sm text-azul-noche">
              <svg class="mt-0.5 h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>
                Solo se pueden asociar retos ajustados que hayas validado (o ya completados). Se crean desde
                <button type="button" @click="router.push({ name: 'startup-day-crear' })"
                        class="font-semibold text-rol-fuerte underline hover:no-underline">Trabajar un reto</button>.
              </span>
            </p>
            <button type="button" data-tour="enc-buscar-reto" @click="abrirBuscadorProy"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-rol-fuerte px-5 text-sm font-semibold text-white shadow-sm hover:bg-rol-fuerte/90">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
              Buscar reto ajustado
            </button>
          </div>
        </section>

        <!-- Datos del encuentro -->
        <section data-tour="enc-datos" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-datos-encuentro">
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
            <div class="min-w-0 rounded-xl transition-all">
              <label for="enc-fecha" class="field-label">Fecha de inicio <span class="text-red-600" aria-hidden="true">*</span></label>
              <input id="enc-fecha" v-model="form.fecha" type="date" class="field-input" :disabled="!proyectoAsociado" required />
              <p v-if="sesionSugerida?.fecha" class="mt-1.5 rounded-lg bg-alumnos/10 px-2.5 py-1.5 text-xs font-semibold text-alumnos-dark">
                El docente propuso el {{ new Date(sesionSugerida.fecha + 'T00:00:00').toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' }) }}<template v-if="sesionSugerida.hora_inicio">, {{ textoHorario(sesionSugerida) }}</template>.
              </p>
              <p v-if="!proyectoAsociado" class="mt-1.5 text-xs font-medium text-amber-800">
                Asocia primero un reto ajustado para poder establecer las fechas.
              </p>
            </div>

            <!-- Fecha de fin: parte de la estimada (nº de sesiones de la planificación) y se puede
                 alargar; no acortar, el backend no acepta una anterior a la estimada -->
            <div class="min-w-0">
              <label for="enc-fecha-fin" class="field-label">Fecha de fin</label>
              <input id="enc-fecha-fin" type="date" class="field-input"
                     :value="fechaFin || ''" :min="fechaFinEstimada || form.fecha || undefined"
                     :disabled="!proyectoAsociado || !form.fecha"
                     @change="form.fecha_fin = ($event.target.value && $event.target.value !== fechaFinEstimada) ? $event.target.value : ''" />
              <p v-if="proyectoAsociado && fechaFinEstimada" class="mt-1.5 text-xs text-texto-secundario">
                <template v-if="!form.fecha_fin">
                  Estimada según {{ totalClasesProyectoSeleccionado }} {{ totalClasesProyectoSeleccionado === 1 ? 'sesión' : 'sesiones' }} de la planificación de abajo. Puedes alargarla.
                </template>
                <template v-else>
                  La estimada era el {{ formatFecha(fechaFinEstimada) }}.
                  <button type="button" @click="form.fecha_fin = ''" class="font-semibold text-rol-fuerte underline-offset-2 hover:underline">Usar la estimada</button>
                </template>
              </p>
            </div>

            <div class="min-w-0 rounded-xl transition-all">
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

            <!-- Ciclo: el del reto ajustado, de solo lectura (los módulos y RA/CE del encuentro salen
                 de ese ciclo; cambiarlo aquí los descuadraría). El backend también lo fija. -->
            <div class="min-w-0">
              <label for="enc-ciclo" class="field-label">Ciclo formativo</label>
              <div v-if="proyectoSeleccionado?.ciclo_nombre" id="enc-ciclo" aria-readonly="true"
                   class="field-input cursor-default select-none bg-gray-50 text-texto-secundario break-words"
                   title="Es el ciclo del reto ajustado: para otro ciclo, cambia de reto ajustado">
                {{ form.ciclo_formativo }}
              </div>
              <template v-else>
                <input id="enc-ciclo" v-model="form.ciclo_formativo" type="text" list="ciclos-datalist"
                       placeholder="Ej. CFGM Sistemas Microinformáticos" class="field-input" />
                <datalist id="ciclos-datalist">
                  <option v-for="c in ciclosParaAutocompletar" :key="c" :value="c" />
                </datalist>
              </template>
            </div>

            <fieldset class="min-w-0">
              <legend class="field-label">Curso <span class="text-red-600" aria-hidden="true">*</span></legend>
              <div class="grid grid-cols-3 gap-2">
                <button v-for="c in ['1º', '2º', '1º y 2º']" :key="c" type="button" @click="form.curso = c"
                        :aria-pressed="form.curso === c"
                        class="h-10 rounded-full border text-sm font-semibold transition-colors"
                        :class="form.curso === c
                          ? 'border-rol-fuerte bg-rol-fuerte text-white shadow-sm'
                          : 'border-borde bg-white text-azul-noche hover:border-rol-fuerte/40 hover:text-rol-fuerte'">
                  {{ c === '1º y 2º' ? 'Ambos' : c }}
                </button>
              </div>
            </fieldset>
            <fieldset data-tour="enc-clase" class="min-w-0 rounded-xl transition-all">
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
          </div>
        </section>

        <!-- Planificación de sesiones: qué fases se trabajan en cada sesión de este encuentro -->
        <section v-if="proyectoAsociado" data-tour="enc-planificacion" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-planificacion">
          <div class="mb-5 flex items-start gap-3 sm:gap-4">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol-fuerte text-white sm:h-12 sm:w-12" aria-hidden="true">
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </span>
            <div class="min-w-0">
              <h2 id="titulo-planificacion" class="text-lg font-bold text-azul-noche">Planificación de sesiones</h2>
              <p class="mt-0.5 text-sm text-texto-secundario">Por defecto, una sesión con todas las fases: añade más si lo vais a trabajar en varias. De aquí sale la fecha fin estimada.</p>
            </div>
          </div>
          <PlanificacionSesiones v-model="form.planificacion_sesiones" />
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
                <div data-tour="enc-num-grupos">
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
                  Establece la fecha de inicio para repartir el alumnado en grupos.
                </p>

                <!-- Alumnado por equipos — siempre visible -->
                <div data-tour="enc-alumnado" class="space-y-3 rounded-xl transition-all">

                  <label class="field-label">
                    Alumnado por grupos <span class="text-red-400">*</span>
                    <span v-if="form.alumnados.length" class="ml-2 text-xs font-semibold"
                          :class="limiteAlcanzado ? 'text-red-400' : 'text-texto-secundario'">
                      {{ alumnosTexto(form.alumnados.length) }}
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

                  <!-- Pegar una lista de nombres: se reparten solos en los grupos -->
                  <div>
                    <button v-if="!pegandoLista" data-tour="enc-pegar-lista" type="button" @click="pegandoLista = true; mensajeLista = ''"
                            :disabled="!fechaEstablecida || limiteAlcanzado"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-full border border-rol/30 bg-white px-3 py-2
                                   text-xs font-bold text-rol-fuerte transition-all hover:bg-rol/5
                                   disabled:opacity-40 disabled:cursor-not-allowed">
                      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h10"/>
                      </svg>
                      Pegar una lista de nombres
                    </button>
                    <div v-else class="space-y-2 rounded-xl border border-rol/20 bg-rol/5 p-3">
                      <label for="lista-nombres" class="block text-xs font-bold uppercase tracking-widest text-rol-fuerte">Un nombre de pila por línea</label>
                      <textarea id="lista-nombres" v-model="listaNombres" rows="5" class="field-input resize-y !text-sm"
                                placeholder="Lucía&#10;Hugo&#10;Martina" />
                      <p class="text-xs text-texto-secundario">Se reparten solos entre los {{ form.num_equipos || 1 }} grupos y a cada uno se le añade un animal y un color.</p>
                      <div class="flex flex-wrap gap-2">
                        <button type="button" @click="anadirListaNombres" :disabled="!listaNombres.trim()"
                                class="inline-flex min-h-9 items-center rounded-full bg-rol-fuerte px-4 text-xs font-bold text-white disabled:opacity-40">
                          Añadir y repartir
                        </button>
                        <button type="button" @click="pegandoLista = false" class="inline-flex min-h-9 items-center rounded-full px-3 text-xs font-bold text-texto-secundario">Cancelar</button>
                      </div>
                    </div>
                    <p v-if="mensajeLista" class="mt-1.5 text-xs font-semibold text-azul-noche" role="status">{{ mensajeLista }}</p>
                  </div>

                  <!-- Copiar alumnado de un encuentro anterior del mismo docente -->
                  <div v-if="encuentrosParaCopiar.length">
                    <button v-if="!copiandoAlumnado" data-tour="enc-copiar-alumnado" type="button" @click="copiandoAlumnado = true; mensajeCopiaAlumnado = ''"
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
                  <div data-tour="enc-grupos" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
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

        <dl data-tour="enc-confirmacion" class="grid grid-cols-1 gap-x-6 gap-y-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2">
          <div v-for="d in confirmacionEncuentro" :key="d.label" class="min-w-0" :class="d.ancho ? 'sm:col-span-2' : ''">
            <dt class="text-xs font-semibold uppercase tracking-wide text-texto-secundario">{{ d.label }}</dt>
            <dd class="mt-0.5 break-words text-sm font-semibold text-azul-noche">{{ d.valor }}</dd>
          </div>
        </dl>
        <button type="button" @click="pasoEnc = 2"
                class="mt-2 inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">
          Revisar grupos y alumnado
        </button>

        <div data-tour="enc-notas" class="mt-4 rounded-xl transition-all">
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
          Ya tienes los grupos listos. Proyecta el QR y los códigos para que el alumnado entre en su grupo; en «Seguimiento de grupos» seguirás su avance.
        </p>
        <!-- 1) Empezar ya: proyectar el acceso del alumnado -->
        <div v-if="encuentroCreado?.microproyecto_uuid" data-tour="enc-proyectar" class="mx-auto mt-6 max-w-md rounded-2xl border border-rol/25 bg-rol/5 p-4 sm:p-5">
          <p class="font-heading text-lg font-bold text-azul-noche">¿Empezáis ya?</p>
          <p class="mt-1 text-sm text-texto-secundario">Proyecta el QR y los códigos en clase: cada grupo entra con el suyo.</p>
          <button type="button" @click="proyectarAcceso" :disabled="abriendoProyeccion"
                  class="accion-principal mt-3 disabled:opacity-50">
            {{ abriendoProyeccion ? 'Preparando…' : 'Proyectar QR y códigos' }}
          </button>
        </div>
        <!-- 2) Seguir a los grupos; 3) el resto, más discreto -->
        <div class="mt-4 flex flex-col items-center gap-3">
          <RouterLink :to="{ name: 'encuentros-registrados', query: { ver: 'seguimiento' } }" data-tour="enc-ir-seguimiento" class="accion-secundaria">Ir al seguimiento de grupos</RouterLink>
          <div class="flex flex-wrap justify-center gap-2 border-t border-borde pt-3">
            <RouterLink to="/encuentros" class="accion-secundaria">Ver encuentros</RouterLink>
            <button type="button" @click="crearOtroEncuentro" class="accion-secundaria">Crear otro encuentro</button>
          </div>
        </div>
        <p v-if="errorProyeccion" class="mx-auto mt-3 max-w-md text-sm font-semibold text-red-700" role="alert">{{ errorProyeccion }}</p>
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
      <div v-if="pasoEnc < 4" data-tour="enc-botones" class="fila-pasos rounded-xl transition-all">
        <button v-if="pasoEnc === 1" type="button" @click="salirSinEncuentro" class="btn-paso-atras">{{ desdeAjuste ? 'Ahora no' : 'Cancelar' }}</button>
        <button v-else type="button" @click="pasoEnc--" class="btn-paso-atras">← Anterior</button>
        <button v-if="pasoEnc < 3" type="button" @click="siguientePasoEnc" class="btn-paso">Siguiente →</button>
        <button v-else type="button" @click="guardarEncuentro" :disabled="guardando" class="btn-paso">
          <svg v-if="guardando" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
          {{ guardando ? 'Guardando…' : 'Guardar encuentro' }}
        </button>
      </div>

      </div><!-- /columna principal -->

      <EncuentroResumen data-tour="enc-resumen"
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
