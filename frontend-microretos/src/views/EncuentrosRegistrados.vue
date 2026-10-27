<!-- Ruta: /encuentros (name: encuentros-registrados). Antes vivía en /dashboard/encuentros — ver router/index.js. -->
<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import MiniaturaReto from '../components/MiniaturaReto.vue'
import TituloResultados from '../components/TituloResultados.vue'
import FamiliaModulos from '../components/FamiliaModulos.vue'
import ChipDocente from '../components/ChipDocente.vue'
import { usePaginacion } from '../composables/usePaginacion.js'
import { modulosDeEncuentro, modulosDisponibles } from '../utils/modulos.js'
import { useRouter, useRoute } from 'vue-router'
import MicroretoModal from '../components/MicroretoModal.vue'
import ProyectoFichaModal from '../components/ProyectoFichaModal.vue'
import EliminarEncuentroModal from '../components/EliminarEncuentroModal.vue'
import BienvenidaModal from '../components/BienvenidaModal_DashboardDocente.vue'
import ReestructurarEquipoModal from '../components/ReestructurarEquipoModal.vue'
import CompartirEncuentroModal from '../components/CompartirEncuentroModal.vue'
import EncuentroEquiposYRaCe from '../components/EncuentroEquiposYRaCe.vue'
import { useCursoAcademicoStore, cursoDeEncuentro } from '../stores/cursoAcademico.js'
import SelectorCurso from '../components/SelectorCurso.vue'
import FiltrosPanel from '../components/FiltrosPanel.vue'
import FiltroSegmentado from '../components/FiltroSegmentado.vue'
import FiltroSelect from '../components/FiltroSelect.vue'
import api from '../api.js'
import { useRaCeEncuentro } from '../composables/useRaCeEncuentro.js'
import { useAuthStore } from '../stores/auth.js'
import { FASES_PROYECTO } from '../config/fasesProyecto.js'
import { formatCurso } from '../utils/formatCurso.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

// Grupos (equipos de alumnado) de un encuentro para la card. Usa los equipos reales si
// vienen cargados; si no (encuentros antiguos), el reparto plano de `alumnados`.
const FASE_LABEL = Object.fromEntries(FASES_PROYECTO.map(f => [f.num, f.label]))
function gruposDe(s) {
  if (s.equipos?.length) {
    return [...s.equipos]
      .sort((a, b) => (a.numero_equipo ?? 0) - (b.numero_equipo ?? 0))
      .map(e => ({
        clave:   e.id,
        nombre:  nombreGrupo(e),
        alumnos: e.miembros?.length || 0,
        fase:    FASE_LABEL[e.fase_actual] ?? null,
      }))
  }
  const porNum = new Map()
  ;(s.alumnados || []).forEach(a => { if (a.equipo_num) porNum.set(a.equipo_num, (porNum.get(a.equipo_num) || 0) + 1) })
  return [...porNum.entries()].sort((a, b) => a[0] - b[0])
    .map(([n, alumnos]) => ({ clave: 'n' + n, nombre: `Grupo ${n}`, alumnos, fase: null }))
}

const router  = useRouter()
const route   = useRoute()
const authStore = useAuthStore()
const cargando = ref(false)
const isLoaded  = ref(false)
onMounted(() => { setTimeout(() => { isLoaded.value = true }, 80) })

// ─── Modal "¿Qué necesitas?" ──────────────────────────────────────────────────
const guiaBienvenida = ref(false)

function seleccionarOpcionBienvenida(opcion) {
  if (opcion === 'crear') {
    guiaBienvenida.value = false
    router.push('/encuentros/crear')
  } else {
    guiaBienvenida.value = false
  }
}

// ─── Datos de encuentros ────────────────────────────────────────────────────────
const encuentros = ref([])

async function cargarEncuentros() {
  cargando.value = true
  try {
    const res = await api.get('/encuentros')
    encuentros.value = res.data
  } catch (e) {
    console.error('Error cargando encuentros:', e)
    encuentros.value = []
  } finally {
    cargando.value = false
  }
}

onMounted(async () => {
  await cargarEncuentros()
  const idParam = route.query.id
  if (idParam) {
    const s = encuentros.value.find(s => String(s.id) === String(idParam))
    if (s) verEncuentro(s)
  }
  // Auto-disparo desactivado — reactivar poniendo guiaBienvenida.value = true si se necesita de nuevo.
})

// ── Modal eliminar encuentro ─────────────────────────────────────────────────────
const modalEliminarVisible = ref(false)
const encuentroAEliminar      = ref(null)

function abrirModalEliminar(encuentro) {
  encuentroAEliminar.value      = encuentro
  modalEliminarVisible.value = true
}

function cerrarModalEliminar() {
  modalEliminarVisible.value = false
  encuentroAEliminar.value      = null
}

function onEncuentroEliminado({ id, titulo }) {
  encuentros.value = encuentros.value.filter(s => s.id !== id)
  if (encuentroAbierto.value?.id === id) encuentroAbierto.value = null
  cerrarModalEliminar()
  // La papelera de "Base de datos" es solo superadmin — el resto de roles ya no tiene esa ruta.
  mostrarSnack(
    `Encuentro "${titulo || 'sin título'}" movido a la papelera.`,
    authStore.isSuperAdmin ? { label: 'Ir a la papelera', fn: () => router.push({ name: 'papelera' }) } : null,
  )
}

// ── Snackbar ──────────────────────────────────────────────────────────────────
const snackbar = ref({ visible: false, mensaje: '', accion: null })
function mostrarSnack(mensaje, accion = null) {
  snackbar.value = { visible: true, mensaje, accion }
  setTimeout(() => { snackbar.value.visible = false }, 5000)
}

// ─── Filtros ──────────────────────────────────────────────────────────────────
// Biblioteca de encuentros con el mismo esquema que el explorador de retos: filtros arriba,
// «Filtros rápidos» a la izquierda (familias —varias—, acceso, propiedad, fechas) y resultados.
const filtros = ref({
  titulo:    '',
  desde:     '',
  hasta:     '',
  curso:     '',
  grupo:     '',
  familias:  [],
  ciclo:     '',
  modulo:    '',
  centro:    '',
  acceso:    '',   // '' | 'con' (ya tiene código del alumnado) | 'sin'
  propiedad: '',   // '' | 'mios' | 'compartidos'
})
const FILTROS_VACIOS = { titulo: '', desde: '', hasta: '', curso: '', grupo: '', familias: [], ciclo: '', modulo: '', centro: '', acceso: '', propiedad: '' }

// Encuentros cuyo proyecto no tiene familia: opción propia en el filtro
const SIN_FAMILIA = '__sin_familia__'
const coincideFamilia = (s) => !filtros.value.familias.length
  || filtros.value.familias.includes(s.familia_nombre || SIN_FAMILIA)
const coincideCiclo = (s) => !filtros.value.ciclo
  || (s.ciclo_formativo || '').toLowerCase().includes(filtros.value.ciclo.toLowerCase())

// Familia → ciclo → módulo: cada nivel reinicia los que dependen de él
watch(() => filtros.value.familias.join('|'), () => { filtros.value.ciclo = ''; filtros.value.modulo = '' })

// Niveles elegidos para el título de los resultados (TituloResultados): una sola familia →
// ciclo → módulo. Quitar un nivel quita también los que dependen de él (watches de arriba).
const nivelesTitulo = computed(() => {
  const f = filtros.value
  const niveles = []
  if (f.familias.length === 1) {
    niveles.push({ etiqueta: 'Familia profesional', valor: nombreFamilia(f.familias[0]),
      subir: () => { f.ciclo = ''; f.modulo = '' }, quitar: () => { f.familias = [] } })
  }
  if (f.ciclo) niveles.push({ etiqueta: 'Ciclo formativo', valor: f.ciclo, subir: () => { f.modulo = '' }, quitar: () => { f.ciclo = '' } })
  if (f.modulo) niveles.push({ etiqueta: 'Módulo', valor: f.modulo, subir: () => {}, quitar: () => { f.modulo = '' } })
  return niveles
})
const familiaEnTitulo = computed(() => nivelesTitulo.value[0]?.etiqueta === 'Familia profesional')
watch(() => filtros.value.ciclo, () => { filtros.value.modulo = '' })

// Curso académico compartido (store): filtros, listado y estadísticas salen de esta lista
const cursoStore      = useCursoAcademicoStore()
const encuentrosCurso = computed(() => encuentros.value.filter(e => cursoStore.coincide(cursoDeEncuentro(e))))
const cursosConDatos  = computed(() => [...new Set(encuentros.value.map(cursoDeEncuentro))])

const encuentrosUnicos = computed(() => {
  const centros = [...new Set(encuentrosCurso.value.map(s => s.centro_educativo).filter(Boolean))].sort()
  const nombresFamilia = [...new Set(encuentrosCurso.value.map(s => s.familia_nombre).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'))
  const familias = nombresFamilia.map(f => ({ value: f, label: f }))
  if (encuentrosCurso.value.some(s => !s.familia_nombre)) familias.push({ value: SIN_FAMILIA, label: 'Sin familia asignada' })
  const deFamilia = encuentrosCurso.value.filter(coincideFamilia)
  const ciclos  = [...new Set(deFamilia.map(s => s.ciclo_formativo).filter(Boolean))].sort()
  // Módulos solo con una familia elegida (como en las bibliotecas), acotados al ciclo si lo hay
  const modulos = filtros.value.familias.length ? modulosDisponibles(deFamilia.filter(coincideCiclo), modulosDeEncuentro) : []
  // Recuento por familia para el panel «Filtros rápidos» (curso académico + centro)
  const base = filtros.value.centro ? encuentrosCurso.value.filter(s => s.centro_educativo === filtros.value.centro) : encuentrosCurso.value
  const conteo = {}
  base.forEach(s => { const k = s.familia_nombre || SIN_FAMILIA; conteo[k] = (conteo[k] || 0) + 1 })
  const familiasPanel = familias.map(f => ({ ...f, n: conteo[f.value] || 0 })).sort((a, b) => b.n - a.n)
  const acceso = { con: base.filter(s => s.codigo_clase).length, sin: base.filter(s => !s.codigo_clase).length }
  const propiedad = { mios: base.filter(s => s.es_propietario).length, compartidos: base.filter(s => !s.es_propietario).length }
  return { centros, familias, ciclos, modulos, familiasPanel, acceso, propiedad }
})

const encuentrosFiltrados = computed(() => {
  let lista = [...encuentrosCurso.value].sort((a, b) => (a.fecha < b.fecha ? 1 : a.fecha > b.fecha ? -1 : 0))

  if (filtros.value.titulo.trim()) {
    const q = filtros.value.titulo.trim().toLowerCase()
    lista = lista.filter(s => (s.proyecto_titulo || '').toLowerCase().includes(q))
  }
  if (filtros.value.desde)
    lista = lista.filter(s => s.fecha >= filtros.value.desde)
  if (filtros.value.hasta)
    lista = lista.filter(s => s.fecha <= filtros.value.hasta)
  if (filtros.value.curso)
    lista = lista.filter(s => s.curso === filtros.value.curso)
  if (filtros.value.grupo)
    lista = lista.filter(s => s.grupo === filtros.value.grupo)
  if (filtros.value.familias.length)
    lista = lista.filter(coincideFamilia)
  if (filtros.value.ciclo)
    lista = lista.filter(coincideCiclo)
  if (filtros.value.modulo)
    lista = lista.filter(s => modulosDeEncuentro(s).includes(filtros.value.modulo))
  if (filtros.value.centro)
    lista = lista.filter(s => s.centro_educativo === filtros.value.centro)
  if (filtros.value.acceso)
    lista = lista.filter(s => filtros.value.acceso === 'con' ? !!s.codigo_clase : !s.codigo_clase)
  if (filtros.value.propiedad)
    lista = lista.filter(s => filtros.value.propiedad === 'mios' ? s.es_propietario : !s.es_propietario)
  if (orden.value === 'antiguos') lista.reverse()
  else if (orden.value === 'titulo') lista.sort((a, b) => (a.proyecto_titulo || '').localeCompare(b.proyecto_titulo || '', 'es'))

  return lista
})

const OPCIONES_NIVEL = [{ value: '', label: 'Todos' }, { value: '1º', label: '1º' }, { value: '2º', label: '2º' }]
const OPCIONES_CLASE = [{ value: '', label: 'Todas' }, ...['A', 'B', 'C', 'D'].map(g => ({ value: g, label: g }))]

const hayFiltrosActivos = computed(() =>
  Object.values(filtros.value).some(v => Array.isArray(v) ? v.length : v !== '')
)
function alternarFamilia(valor) {
  const l = filtros.value.familias
  filtros.value.familias = l.includes(valor) ? l.filter(v => v !== valor) : [...l, valor]
}
const nombreFamilia = (v) => v === SIN_FAMILIA ? 'Sin familia asignada' : v

// Orden, vista, panel lateral y paginación (mismo comportamiento que el explorador de retos)
const orden = ref('recientes')   // 'recientes' | 'antiguos' | 'titulo'
const VISTA_KEY = 'dualab_encuentros_vista'
const vista = ref((() => { try { return localStorage.getItem(VISTA_KEY) || 'cuadricula' } catch { return 'cuadricula' } })())
watch(vista, v => { try { localStorage.setItem(VISTA_KEY, v) } catch { /* sin almacenamiento */ } })
const panelFiltrosAbierto = ref(typeof window === 'undefined' || window.innerWidth >= 1024)
const columnasEncuentros = computed(() => panelFiltrosAbierto.value
  ? 'md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3'
  : 'md:grid-cols-2 xl:grid-cols-3')
const familiasExpandidas = ref(false)
const familiasPanelVisibles = computed(() => {
  const todas = encuentrosUnicos.value.familiasPanel
  return familiasExpandidas.value || todas.length <= 6 ? todas
    : [...todas.slice(0, 6), ...todas.slice(6).filter(f => filtros.value.familias.includes(f.value))]
})
const hayVariosCentros = computed(() => encuentrosUnicos.value.centros.length > 1)
// 12 por página: se reparte entero en 1, 2, 3 o 4 columnas (con 9, a 2 columnas quedaba
// una tarjeta sola en la última fila y un hueco a su derecha)
const POR_PAGINA = 12
const { pagina, totalPaginas, itemsPagina: encuentrosPagina } = usePaginacion(encuentrosFiltrados, POR_PAGINA)
watch(encuentrosFiltrados, () => { pagina.value = 0 })
const paginasVisibles = computed(() => {
  const n = totalPaginas.value, a = pagina.value
  const ini = Math.max(0, Math.min(a - 2, n - 5)), fin = Math.min(n, ini + 5)
  return Array.from({ length: fin - ini }, (_, i) => ini + i)
})
function irAPagina(i) {
  pagina.value = i
  document.getElementById('resultados-encuentros')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
const menuEncuentro = ref(null)   // id del encuentro con el menú «⋯» abierto
const menuLlave     = ref(null)   // id del encuentro con el menú de la llave (códigos) abierto
const ayudaIa       = ref(null)   // id del encuentro con la explicación del código IA desplegada (táctil)
watch(menuLlave, () => { ayudaIa.value = null })
// Cierre de los menús de las tarjetas al pulsar fuera: un overlay fixed no sirve, porque queda
// por encima del menú (el contenedor de la vista es su propio contexto de apilamiento) y se
// tragaba los clics en las opciones. Cuenta como «dentro» todo lo marcado con data-menu-raiz.
function cerrarMenusAlPulsarFuera(e) {
  if (!e.target.closest?.('[data-menu-raiz]')) { menuEncuentro.value = null; menuLlave.value = null }
}
watch(() => !!(menuEncuentro.value || menuLlave.value), (abierto) => {
  document[abierto ? 'addEventListener' : 'removeEventListener']('click', cerrarMenusAlPulsarFuera, true)
})
onUnmounted(() => document.removeEventListener('click', cerrarMenusAlPulsarFuera, true))


function limpiarFiltros() {
  filtros.value = { ...FILTROS_VACIOS }
}

// ─── Modal encuentro ─────────────────────────────────────────────────────────────
const encuentroAbierto = ref(null)

const { cargandoRaCe, modulosExpandidos, raCeBlocksEncuentro, toggleModulo, cargarRaCe } = useRaCeEncuentro()

async function verEncuentro(s) {
  encuentroAbierto.value = s
  await cargarRaCe(s.microproyecto_uuid)
}

function cerrarEncuentro()   { encuentroAbierto.value = null; editandoFechaFin.value = false }

const seccionAccesoAlumnado = ref(null)

function irAAccesoAlumnado() {
  seccionAccesoAlumnado.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function alumnadosDelEquipoAbierto(n) {
  const equipo = (encuentroAbierto.value?.equipos || []).find(e => e.numero_equipo === n)
  if (equipo) {
    return equipo.miembros.map(m => ({ alias: m.alias }))
  }
  // Encuentros sin equipos cargados todavía: snapshot plano del reparto (solo alias).
  return (encuentroAbierto.value?.alumnados || [])
    .filter(a => a.equipo_num === n && a.alias)
    .map(a => ({ alias: a.alias }))
}

// ─── Reestructurar equipo ──────────────────────────────────────────────────────
const reestructurando = ref(null)

function abrirReestructurar(s) { reestructurando.value = s }

// ── Modal compartir encuentro ────────────────────────────────────────────────────
const compartiendo = ref(null)
function abrirCompartir(s) { compartiendo.value = s }
function cerrarCompartir()  { compartiendo.value = null }
function cerrarReestructurar() { reestructurando.value = null }

function onEquipoReestructurado({ id, num_equipos, alumnados, equipos }) {
  encuentros.value = encuentros.value.map(s =>
    s.id === id ? { ...s, num_equipos, alumnados, equipos } : s
  )
  if (encuentroAbierto.value?.id === id) {
    encuentroAbierto.value = { ...encuentroAbierto.value, num_equipos, alumnados, equipos }
  }
  reestructurando.value = null
  mostrarSnack('Reparto de grupos actualizado.')
}

// El docente puede regenerar el alias de un miembro sin pasar por "Guardar reparto"
// (ver ReestructurarEquipoModal) — se refleja aquí al instante para que la ficha del
// encuentro abierta no se quede con el valor antiguo hasta recargar.
function onAliasActualizado({ miembroId, alias }) {
  const conAliasActualizado = (lista) => lista.map(s => ({
    ...s,
    equipos: (s.equipos || []).map(e => ({
      ...e,
      miembros: (e.miembros || []).map(m => m.id === miembroId ? { ...m, alias } : m),
    })),
  }))
  encuentros.value = conAliasActualizado(encuentros.value)
  if (encuentroAbierto.value) {
    encuentroAbierto.value = conAliasActualizado([encuentroAbierto.value])[0]
  }
}

// ─── Editar fecha_fin del encuentro ───────────────────────────────────────────
const editandoFechaFin  = ref(false)
const fechaFinInput     = ref('')
const guardandoFechaFin = ref(false)
const errorFechaFin     = ref('')

function empezarEditarFechaFin() {
  fechaFinInput.value    = encuentroAbierto.value?.fecha_fin || ''
  errorFechaFin.value    = ''
  editandoFechaFin.value = true
}

async function guardarFechaFin() {
  guardandoFechaFin.value = true
  errorFechaFin.value     = ''
  try {
    const res = await api.put(`/encuentros/${encuentroAbierto.value.id}`, { fecha_fin: fechaFinInput.value || null })
    encuentroAbierto.value = { ...encuentroAbierto.value, fecha_fin: res.data.fecha_fin }
    encuentros.value = encuentros.value.map(s => s.id === encuentroAbierto.value.id ? { ...s, fecha_fin: res.data.fecha_fin } : s)
    editandoFechaFin.value = false
  } catch (e) {
    errorFechaFin.value = e.response?.data?.errors?.fecha_fin?.[0] || e.response?.data?.message || 'No se pudo guardar la fecha fin.'
  } finally {
    guardandoFechaFin.value = false
  }
}

// ─── Modal ficha microreto ────────────────────────────────────────────────────
const microretoModalId = ref(null)

function abrirMicroretoModal(id) { microretoModalId.value = id }
function cerrarMicroretoModal()  { microretoModalId.value = null }

// ─── Modal ficha de proyecto (card de encuentro) ──────────────────────────────
const proyectoFichaUuid = ref(null)

function abrirProyectoFicha(uuid) { proyectoFichaUuid.value = uuid }
function cerrarProyectoFicha()    { proyectoFichaUuid.value = null }

// ─── Código de acceso para alumnado ──────────────────────────────────────────
const creandoCodigo = ref({})
const errorCodigo   = ref({})

async function crearCodigo(encuentro) {
  creandoCodigo.value = { ...creandoCodigo.value, [encuentro.id]: true }
  errorCodigo.value   = { ...errorCodigo.value,   [encuentro.id]: '' }
  try {
    const res = await api.post(`/encuentros/${encuentro.id}/crear-codigo`)
    const nuevoCodigo = res.data.codigo_clase
    encuentros.value = encuentros.value.map(s =>
      s.id === encuentro.id ? { ...s, codigo_clase: nuevoCodigo } : s
    )
    if (encuentroAbierto.value?.id === encuentro.id) {
      encuentroAbierto.value = { ...encuentroAbierto.value, codigo_clase: nuevoCodigo }
    }
    mostrarSnack(`Código ${nuevoCodigo} creado. El alumnado puede acceder desde /unirse`)
  } catch (e) {
    errorCodigo.value = { ...errorCodigo.value, [encuentro.id]: e.response?.data?.error || 'Error al crear el código.' }
  } finally {
    creandoCodigo.value = { ...creandoCodigo.value, [encuentro.id]: false }
  }
}

// ─── Código de desbloqueo IA del workspace ──────────────────────────────────
const creandoCodigoIa = ref({})
const errorCodigoIa   = ref({})

async function crearCodigoIa(encuentro) {
  creandoCodigoIa.value = { ...creandoCodigoIa.value, [encuentro.id]: true }
  errorCodigoIa.value   = { ...errorCodigoIa.value,   [encuentro.id]: '' }
  try {
    const res = await api.post(`/encuentros/${encuentro.id}/codigo-ia`)
    const nuevoCodigo = res.data.codigo_ia
    encuentros.value = encuentros.value.map(s =>
      s.id === encuentro.id ? { ...s, codigo_ia: nuevoCodigo } : s
    )
    if (encuentroAbierto.value?.id === encuentro.id) {
      encuentroAbierto.value = { ...encuentroAbierto.value, codigo_ia: nuevoCodigo }
    }
    mostrarSnack(`Código IA ${nuevoCodigo} generado.`)
  } catch (e) {
    errorCodigoIa.value = { ...errorCodigoIa.value, [encuentro.id]: e.response?.data?.error || 'Error al generar el código.' }
  } finally {
    creandoCodigoIa.value = { ...creandoCodigoIa.value, [encuentro.id]: false }
  }
}

async function copiarCodigo(codigo) {
  try {
    await navigator.clipboard.writeText(codigo)
    mostrarSnack(`Código ${codigo} copiado.`)
  } catch {
    mostrarSnack(`Código: ${codigo}`)
  }
}

// ─── Stats ────────────────────────────────────────────────────────────────────
const stats = computed(() => {
  const microretos = new Set(encuentrosCurso.value.map(s => s.microreto_id).filter(Boolean))
  const centros    = new Set(encuentrosCurso.value.map(s => s.centro_educativo).filter(Boolean))
  return {
    total:      encuentrosCurso.value.length,
    microretos: microretos.size,
    centros:    centros.size,
  }
})

// ─── Utils ────────────────────────────────────────────────────────────────────
function formatFecha(isoDate) {
  if (!isoDate) return ''
  const d = new Date(isoDate + 'T12:00:00')
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="min-h-screen font-sans text-azul-noche pt-16 md:pt-16">

    <!-- Modal ¿Qué necesitas? -->
    <BienvenidaModal :show="guiaBienvenida" @seleccionar="seleccionarOpcionBienvenida" />

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                bg-rol-fuerte opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8" @keydown.esc="menuEncuentro = null; menuLlave = null">

      <!-- Cabecera de sección (mismo estilo que Retos y proyectos) + acciones de la vista -->
      <CabeceraSeccion titulo="Encuentros" destacado="registrados" color="text-centros" volver="/seccion/encuentros"
                       subtitulo="Consulta y filtra todo el historial de encuentros de trabajo con retos."
                       class="mb-6">
        <div class="flex flex-wrap items-center gap-2">
          <!-- ¿Qué necesitas? — abre el modal de opciones (guiaBienvenida) -->
          <button type="button" @click="guiaBienvenida = true" class="accion-secundaria" title="¿Qué necesitas? Opciones y guía de esta pantalla">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Ayuda
          </button>
          <!-- Papelera — la papelera de "Base de datos" es solo superadmin -->
          <button v-if="authStore.isSuperAdmin" type="button"
                  @click="router.push({ name: 'papelera' })"
                  class="accion-icono"
                  title="Ver encuentros eliminados en la papelera"
                  aria-label="Ver encuentros eliminados en la papelera">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
          </button>
          <!-- Nuevo encuentro (acción principal) -->
          <button type="button" @click="router.push('/encuentros/crear')" class="accion-principal"
                  title="Ir al formulario para registrar un nuevo encuentro">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
            </svg>
            Nuevo encuentro
          </button>
        </div>
      </CabeceraSeccion>

      <!-- ─── Filtros (mismo panel que el explorador de retos) ─────────────── -->
      <FiltrosPanel separadores v-model:busqueda="filtros.titulo" placeholder="Buscar por título del proyecto…">
        <template #curso>
          <div class="flex flex-wrap items-center gap-2">
            <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
            <!-- «Más filtros» en la fila del buscador (en la de filtros, con 4 o más, bajaba solo a otra fila) -->
            <button type="button" @click="panelFiltrosAbierto = !panelFiltrosAbierto" :aria-expanded="panelFiltrosAbierto"
                    aria-controls="filtros-rapidos-encuentros" aria-label="Más filtros"
                    :title="panelFiltrosAbierto ? 'Ocultar los filtros rápidos' : 'Mostrar los filtros rápidos'"
                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-full border px-5 text-sm font-semibold transition-colors"
                    :class="panelFiltrosAbierto ? 'border-rol-fuerte/30 bg-rol/10 text-rol-fuerte' : 'border-rol-fuerte/30 bg-white text-rol-fuerte hover:bg-rol-fuerte/5'">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    <span>Más filtros</span>
                    </button>
          </div>
        </template>

        <FiltroSelect v-if="encuentrosUnicos.centros.length > 1 || authStore.isSuperAdmin" v-model="filtros.centro"
                      label="Centro educativo" todos="Todos los centros" :opciones="encuentrosUnicos.centros" />
        <!-- Ciclo: ciclos presentes en el curso (el filtro compara por "contiene") -->
        <FiltroSelect v-model="filtros.ciclo" label="Ciclo formativo" todos="Todos los ciclos" :opciones="encuentrosUnicos.ciclos" />
        <!-- Módulo: con alguna familia marcada; un encuentro multimódulo aparece en cada uno -->
        <FiltroSelect v-if="encuentrosUnicos.modulos.length" v-model="filtros.modulo" label="Módulo"
                      todos="Todos los módulos" :opciones="encuentrosUnicos.modulos" />
        <FiltroSegmentado v-model="filtros.curso" label="Nivel" :opciones="OPCIONES_NIVEL" />
        <FiltroSegmentado v-model="filtros.grupo" label="Clase" :opciones="OPCIONES_CLASE" />
      </FiltrosPanel>

      <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        <!-- ═══ Filtros rápidos ═══ -->
        <aside id="filtros-rapidos-encuentros" aria-label="Filtros rápidos"
               class="w-full shrink-0 rounded-2xl border border-borde bg-white p-4 shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:w-64 xl:w-72"
               :class="panelFiltrosAbierto ? 'block' : 'hidden'">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-azul-noche">Filtros rápidos</h2>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros"
                    class="inline-flex h-9 items-center rounded-full px-2 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">Limpiar</button>
          </div>

          <div class="border-t border-borde pt-3" role="group" aria-labelledby="enc-filtro-familia">
            <p id="enc-filtro-familia" class="mb-1.5 text-sm font-semibold text-azul-noche">Familia profesional</p>
            <label v-for="f in familiasPanelVisibles" :key="f.value"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtros.familias.includes(f.value)" @change="alternarFamilia(f.value)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche" :title="f.label">{{ f.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ f.n }}</span>
            </label>
            <p v-if="!encuentrosUnicos.familiasPanel.length && !cargando" class="px-1 py-1 text-sm text-texto-secundario">Sin encuentros en este curso.</p>
            <button v-if="encuentrosUnicos.familiasPanel.length > 6" type="button" @click="familiasExpandidas = !familiasExpandidas"
                    class="mt-1 inline-flex h-9 items-center rounded-full px-1 text-sm font-semibold text-rol-fuerte hover:underline">
              {{ familiasExpandidas ? 'Ver menos' : `Ver todas (${encuentrosUnicos.familiasPanel.length})` }}
            </button>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="radiogroup" aria-labelledby="enc-filtro-acceso">
            <p id="enc-filtro-acceso" class="mb-1.5 text-sm font-semibold text-azul-noche">Acceso del alumnado</p>
            <label v-for="o in [
                     { v: '', label: 'Todos', n: encuentrosUnicos.acceso.con + encuentrosUnicos.acceso.sin, cls: 'bg-gray-100 text-azul-noche', icono: 'M4 6h16M4 12h16M4 18h16' },
                     { v: 'con', label: 'Con código de acceso', n: encuentrosUnicos.acceso.con, cls: 'bg-empresas/10 text-empresas-dark', icono: 'M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z' },
                     { v: 'sin', label: 'Sin código todavía', n: encuentrosUnicos.acceso.sin, cls: 'bg-amber-50 text-amber-800', icono: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
                   ]" :key="'a' + o.v" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="radio" name="enc-acceso" :value="o.v" v-model="filtros.acceso" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg" :class="o.cls" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="o.icono"/></svg>
              </span>
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ o.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ o.n }}</span>
            </label>
          </div>

          <div v-if="encuentrosUnicos.propiedad.compartidos" class="mt-3 border-t border-borde pt-3" role="radiogroup" aria-labelledby="enc-filtro-propiedad">
            <p id="enc-filtro-propiedad" class="mb-1.5 text-sm font-semibold text-azul-noche">De quién es</p>
            <label v-for="o in [
                     { v: '', label: 'Todos', n: encuentrosUnicos.propiedad.mios + encuentrosUnicos.propiedad.compartidos },
                     { v: 'mios', label: 'Míos', n: encuentrosUnicos.propiedad.mios },
                     { v: 'compartidos', label: 'Compartidos conmigo', n: encuentrosUnicos.propiedad.compartidos },
                   ]" :key="'p' + o.v" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="radio" name="enc-propiedad" :value="o.v" v-model="filtros.propiedad" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ o.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ o.n }}</span>
            </label>
          </div>

          <details class="group mt-3 border-t border-borde pt-3" :open="!!(filtros.desde || filtros.hasta)">
            <summary class="flex cursor-pointer list-none items-center justify-between py-1 text-sm font-semibold text-azul-noche">
              Fechas
              <svg class="h-4 w-4 text-texto-secundario transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <div class="mt-2 grid grid-cols-2 gap-2 lg:grid-cols-1 xl:grid-cols-2">
              <label class="block min-w-0">
                <span class="mb-1 block text-xs font-semibold text-azul-noche">Desde</span>
                <input v-model="filtros.desde" type="date" class="h-10 w-full rounded-xl px-2 text-sm" />
              </label>
              <label class="block min-w-0">
                <span class="mb-1 block text-xs font-semibold text-azul-noche">Hasta</span>
                <input v-model="filtros.hasta" type="date" class="h-10 w-full rounded-xl px-2 text-sm" />
              </label>
            </div>
          </details>
        </aside>

        <!-- ═══ Resultados ═══ -->
        <section id="resultados-encuentros" class="min-w-0 flex-1 scroll-mt-24" aria-label="Encuentros">
          <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <TituloResultados :niveles="nivelesTitulo" :cargando="cargando">
              <template v-if="cargando">Cargando encuentros…</template>
              <template v-else>{{ encuentrosFiltrados.length }} {{ encuentrosFiltrados.length === 1 ? 'encuentro encontrado' : 'encuentros encontrados' }}</template>
              <template #extra>
                <p v-if="stats.total && !cargando" class="text-xs text-texto-secundario">
                  {{ stats.microretos }} {{ stats.microretos === 1 ? 'reto distinto trabajado' : 'retos distintos trabajados' }} en el curso
                </p>
              </template>
            </TituloResultados>
            <div v-if="filtros.familias.length && !familiaEnTitulo" class="flex w-full flex-wrap gap-1.5 sm:order-last">
              <button v-for="f in filtros.familias" :key="f" type="button" @click="alternarFamilia(f)"
                      class="inline-flex h-9 max-w-full items-center gap-1.5 rounded-full bg-rol/10 px-3 text-xs font-semibold text-rol-fuerte hover:bg-rol/15"
                      :aria-label="`Quitar filtro ${nombreFamilia(f)}`">
                <span class="truncate">{{ nombreFamilia(f) }}</span><span aria-hidden="true">✕</span>
              </button>
            </div>
            <label class="flex items-center gap-2 text-sm text-texto-secundario">
              <span class="hidden sm:inline">Ordenar por</span>
              <select v-model="orden" class="h-9 rounded-xl px-3 text-sm text-azul-noche" aria-label="Ordenar encuentros">
                <option value="recientes">Más recientes</option>
                <option value="antiguos">Más antiguos</option>
                <option value="titulo">Proyecto (A-Z)</option>
              </select>
            </label>
            <div class="flex rounded-full bg-gray-100 p-1" role="group" aria-label="Vista de los resultados">
              <button type="button" @click="vista = 'cuadricula'" :aria-pressed="vista === 'cuadricula'" aria-label="Vista en cuadrícula" title="Cuadrícula"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full transition-colors"
                      :class="vista === 'cuadricula' ? 'bg-rol-fuerte text-white shadow-sm' : 'text-texto-secundario hover:text-azul-noche'">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
              </button>
              <button type="button" @click="vista = 'lista'" :aria-pressed="vista === 'lista'" aria-label="Vista en lista" title="Lista"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full transition-colors"
                      :class="vista === 'lista' ? 'bg-rol-fuerte text-white shadow-sm' : 'text-texto-secundario hover:text-azul-noche'">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
              </button>
            </div>
          </div>

          <!-- Carga: skeleton -->
          <div v-if="cargando" class="grid grid-cols-1 gap-4" :class="columnasEncuentros" aria-busy="true">
            <div v-for="n in 6" :key="n" class="flex animate-pulse gap-3 rounded-2xl border border-borde bg-white p-4">
              <div class="h-20 w-20 shrink-0 rounded-xl bg-gray-200" />
              <div class="flex-1 space-y-2"><div class="h-3 w-1/3 rounded-full bg-gray-200" /><div class="h-4 w-4/5 rounded-full bg-gray-200" /><div class="h-3 w-2/3 rounded-full bg-gray-200" /></div>
            </div>
            <span class="sr-only">Cargando encuentros…</span>
          </div>

          <!-- Sin encuentros todavía -->
          <div v-else-if="encuentros.length === 0" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-rol/10 text-rol-fuerte" aria-hidden="true">
              <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <h3 class="mt-3 text-lg font-bold text-azul-noche">Aún no hay encuentros</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">Crea el primero a partir de un proyecto validado.</p>
            <button type="button" @click="router.push('/encuentros/crear')" class="accion-principal mt-4">Nuevo encuentro</button>
          </div>

          <!-- Sin resultados con filtros -->
          <div v-else-if="encuentrosFiltrados.length === 0" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <h3 class="text-lg font-bold text-azul-noche">No hay encuentros con estos filtros</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">Prueba a quitar algún filtro.</p>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros" class="accion-secundaria mt-4">Limpiar filtros</button>
          </div>

          <!-- Tarjetas -->
          <ul v-else :class="vista === 'cuadricula' ? ['grid grid-cols-1 gap-4', columnasEncuentros] : 'space-y-3'">
            <li v-for="s in encuentrosPagina" :key="s.id"
                class="group flex min-w-0 flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm transition-shadow hover:shadow-md">
              <div class="flex min-w-0 gap-3">
                <MiniaturaReto class="flex shrink-0" :class="vista === 'lista' ? 'h-16 w-20' : 'h-20 w-20'" />
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span v-if="s.curso" class="inline-flex items-center rounded-full bg-rol/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-rol-fuerte">{{ formatCurso(s.curso) }} curso</span>
                    <span v-if="s.grupo" class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-azul-noche">Clase {{ s.grupo }}</span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold uppercase tracking-wide"
                          :class="s.codigo_clase ? 'bg-empresas/10 text-empresas-dark' : 'bg-amber-50 text-amber-800'">
                      {{ s.codigo_clase ? 'Acceso dado' : 'Sin código' }}
                    </span>
                    <ChipDocente v-if="!s.es_propietario && s.propietario_nombre" :nombre="s.propietario_nombre" :solo-lectura="!s.puede_editar" />
                  </div>
                  <h3 class="mt-1.5 line-clamp-2 font-heading text-base font-bold leading-snug text-azul-noche" :title="s.proyecto_titulo">
                    <button type="button" @click="verEncuentro(s)" class="text-left hover:text-rol-fuerte hover:underline">{{ s.proyecto_titulo || '(sin título)' }}</button>
                  </h3>

                </div>
              </div>

              <!-- Familia y módulos diferenciados, con «+N» desplegable (FamiliaModulos, común a las bibliotecas) -->
              <FamiliaModulos class="mt-3" :familia="s.familia_nombre || 'Sin familia asignada'" :modulos="modulosDeEncuentro(s)" :modulo-activo="filtros.modulo" />
              <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-texto-secundario">
                <span class="inline-flex items-center gap-1.5">
                  <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  {{ formatFecha(s.fecha) }}
                </span>
                <span v-if="s.num_alumnos" class="inline-flex items-center gap-1.5">
                  <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  {{ s.num_alumnos }} alumnos
                </span>
                <span v-if="s.ciclo_formativo" class="inline-flex min-w-0 items-center gap-1.5">
                  <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422A12.083 12.083 0 0118.82 17.5L12 21l-6.82-3.5a12.08 12.08 0 01.66-6.922L12 14z"/></svg>
                  <span class="truncate">{{ s.ciclo_formativo }}</span>
                </span>
                <span v-if="hayVariosCentros && s.centro_educativo" class="inline-flex min-w-0 items-center gap-1.5">
                  <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01"/></svg>
                  <span class="truncate">{{ s.centro_educativo }}</span>
                </span>
              </div>

              <!-- Grupos con su fase -->
              <ul v-if="gruposDe(s).length" class="mt-2 flex flex-wrap gap-1.5" :aria-label="`${gruposDe(s).length} grupos`">
                <li v-for="g in gruposDe(s).slice(0, 4)" :key="g.clave"
                    class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-borde bg-gray-50 px-2 py-0.5 text-xs">
                  <span class="font-semibold text-azul-noche">{{ g.nombre }}</span>
                  <span v-if="g.fase" class="truncate text-alumnos-dark">· {{ g.fase }}</span>
                </li>
                <li v-if="gruposDe(s).length > 4" class="inline-flex items-center px-1 text-xs text-texto-secundario">+{{ gruposDe(s).length - 4 }}</li>
              </ul>

              <div class="mt-auto pt-3">
                <div class="relative flex items-center gap-2 border-t border-borde pt-3">
                  <!-- Hueco flexible: los grupos ya se ven en sus etiquetas, arriba -->
                  <span class="min-w-0 flex-1" aria-hidden="true" />
                  <button type="button" @click="verEncuentro(s)"
                          class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    Ver encuentro
                  </button>
                  <!-- Llave: códigos del alumnado (acceso al espacio de trabajo y sugerencia IA),
                       para copiarlos o generarlos. El punto verde indica que ya hay acceso dado.
                       El menú se alinea con el borde derecho de la tarjeta (la fila es la referencia),
                       no con la llave, para que en móvil no se salga por la izquierda. -->
                  <div class="shrink-0" data-menu-raiz>
                    <button type="button" @click="menuEncuentro = null; menuLlave = menuLlave === s.id ? null : s.id"
                            :aria-expanded="menuLlave === s.id" aria-haspopup="menu" :aria-label="`Códigos del alumnado de «${s.proyecto_titulo || 'encuentro'}»`"
                            title="Códigos del alumnado"
                            class="relative inline-flex h-9 w-9 items-center justify-center rounded-full border transition-colors"
                            :class="menuLlave === s.id ? 'border-rol-fuerte/40 bg-rol/10 text-rol-fuerte' : 'border-borde text-texto-secundario hover:bg-gray-50'">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                      <span v-if="s.codigo_clase" class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-empresas ring-2 ring-white" aria-hidden="true" />
                    </button>
                    <div v-if="menuLlave === s.id" role="menu" :aria-label="`Códigos del alumnado de «${s.proyecto_titulo || 'encuentro'}»`"
                         class="absolute bottom-full right-0 z-30 mb-1 w-[min(18rem,calc(100vw-3rem))] rounded-xl border border-borde bg-white p-1.5 shadow-lg">
                      <!-- Atajo: proyectar en clase el QR y el código (pantalla de acceso) -->
                      <button v-if="s.codigo_clase && s.microproyecto_uuid" type="button" role="menuitem"
                              @click="menuLlave = null; router.push({ name: 'pantalla-acceso', params: { uuid: s.microproyecto_uuid } })"
                              class="flex w-full items-center gap-2 rounded-lg bg-rol/5 px-2.5 py-2 text-left text-sm font-semibold text-rol-fuerte hover:bg-rol/10">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Proyectar pantalla de acceso
                      </button>
                      <div v-if="s.codigo_clase && s.microproyecto_uuid" class="my-1.5 border-t border-borde" role="separator" />
                      <!-- Código de acceso al alumnado -->
                      <p class="px-2.5 pb-1 pt-1.5 text-xs font-semibold text-texto-secundario">Código de acceso al alumnado</p>
                      <button v-if="s.codigo_clase" type="button" role="menuitem" @click="menuLlave = null; copiarCodigo(s.codigo_clase)"
                              class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-gray-50">
                        <span class="min-w-0 flex-1 truncate font-bold tracking-[0.15em] text-azul-noche">{{ s.codigo_clase }}</span>
                        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-rol-fuerte">
                          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                          Copiar
                        </span>
                      </button>
                      <button v-else-if="s.puede_editar && s.num_equipos" type="button" role="menuitem" @click="crearCodigo(s)" :disabled="!!creandoCodigo[s.id]"
                              class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-semibold text-rol-fuerte hover:bg-rol/5 disabled:opacity-60">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2.5" d="M12 5v14M5 12h14"/></svg>
                        {{ creandoCodigo[s.id] ? 'Creando…' : 'Generar código de acceso' }}
                      </button>
                      <p v-else class="px-2.5 py-1.5 text-xs leading-snug text-texto-secundario">
                        {{ !s.num_equipos ? 'Crea los grupos del encuentro para poder dar acceso.' : 'Todavía no hay código (solo lectura).' }}
                      </p>
                      <p v-if="errorCodigo[s.id]" class="px-2.5 pb-1 text-xs text-red-700" role="alert">{{ errorCodigo[s.id] }}</p>

                      <div class="my-1.5 border-t border-borde" role="separator" />

                      <!-- Código sugerencia IA, con explicación al pasar el ratón o al pulsar la «i» -->
                      <div class="flex items-center gap-1 px-2.5 pt-0.5">
                        <p class="text-xs font-semibold text-texto-secundario">Código sugerencia IA al alumnado</p>
                        <span class="group/ayuda relative">
                          <button type="button" @click="ayudaIa = ayudaIa === s.id ? null : s.id"
                                  :aria-expanded="ayudaIa === s.id" aria-label="¿Qué es el código de sugerencia IA?"
                                  class="relative inline-flex h-7 w-7 items-center justify-center rounded-full text-texto-secundario before:absolute before:-inset-1 before:content-[''] hover:bg-gray-100 hover:text-rol-fuerte">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                          </button>
                          <span role="tooltip"
                                class="pointer-events-none absolute right-0 top-full z-40 mt-1 hidden w-60 rounded-xl bg-azul-noche px-3 py-2 text-xs leading-snug text-white shadow-lg group-hover/ayuda:block group-focus-within/ayuda:block"
                                :class="{ '!block': ayudaIa === s.id }">
                            En el espacio de trabajo de los grupos, los botones «Sugerir con IA» están bloqueados hasta que el alumnado escribe este código. Compártelo cuando quieras que puedan usar la IA.
                          </span>
                        </span>
                      </div>
                      <button v-if="s.codigo_ia" type="button" role="menuitem" @click="menuLlave = null; copiarCodigo(s.codigo_ia)"
                              class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-gray-50">
                        <span class="min-w-0 flex-1 truncate font-bold tracking-[0.15em] text-alumnos-dark">{{ s.codigo_ia }}</span>
                        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-rol-fuerte">
                          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                          Copiar
                        </span>
                      </button>
                      <button v-else-if="s.puede_editar && s.num_equipos" type="button" role="menuitem" @click="crearCodigoIa(s)" :disabled="!!creandoCodigoIa[s.id]"
                              class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-semibold text-rol-fuerte hover:bg-rol/5 disabled:opacity-60">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        {{ creandoCodigoIa[s.id] ? 'Generando…' : 'Generar código sugerencia IA' }}
                      </button>
                      <p v-else class="px-2.5 py-1.5 text-xs leading-snug text-texto-secundario">
                        {{ !s.num_equipos ? 'Crea los grupos del encuentro para poder generarlo.' : 'Todavía no hay código (solo lectura).' }}
                      </p>
                      <p v-if="errorCodigoIa[s.id]" class="px-2.5 pb-1 text-xs text-red-700" role="alert">{{ errorCodigoIa[s.id] }}</p>
                    </div>
                  </div>
                  <div class="relative shrink-0" data-menu-raiz>
                    <button type="button" @click="menuLlave = null; menuEncuentro = menuEncuentro === s.id ? null : s.id"
                            :aria-expanded="menuEncuentro === s.id" aria-haspopup="menu" :aria-label="`Más acciones de «${s.proyecto_titulo || 'encuentro'}»`"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde text-texto-secundario hover:bg-gray-50">
                      <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                    </button>
                    <div v-if="menuEncuentro === s.id" role="menu"
                         class="absolute bottom-full right-0 z-30 mb-1 w-56 rounded-xl border border-borde bg-white py-1 shadow-lg">
                      <button v-if="s.puede_editar" type="button" role="menuitem" @click="menuEncuentro = null; abrirReestructurar(s)"
                              class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Editar grupos</button>
                      <button v-if="s.es_propietario" type="button" role="menuitem" @click="menuEncuentro = null; abrirCompartir(s)"
                              class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Compartir con otro docente</button>
                      <button v-if="s.microreto_id" type="button" role="menuitem" @click="menuEncuentro = null; abrirMicroretoModal(s.microreto_id)"
                              class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Ficha del reto</button>
                      <button v-if="s.microproyecto_uuid" type="button" role="menuitem" @click="menuEncuentro = null; abrirProyectoFicha(s.microproyecto_uuid)"
                              class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Ficha del proyecto</button>
                      <button v-if="s.puede_editar" type="button" role="menuitem" @click="menuEncuentro = null; abrirModalEliminar(s)"
                              class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Eliminar encuentro</button>
                    </div>
                  </div>
                </div>
              </div>
            </li>
          </ul>

          <!-- Paginación -->
          <div v-if="!cargando && totalPaginas > 1" class="mt-5 flex flex-col flex-wrap items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-texto-secundario">Mostrando {{ encuentrosPagina.length }} de {{ encuentrosFiltrados.length }} encuentros</p>
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación de encuentros">
              <button type="button" @click="irAPagina(0)" :disabled="pagina === 0" aria-label="Primera página"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">«</button>
              <button type="button" @click="irAPagina(pagina - 1)" :disabled="pagina === 0" aria-label="Página anterior"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">‹</button>
              <button v-for="i in paginasVisibles" :key="i" type="button" @click="irAPagina(i)"
                      :aria-current="i === pagina ? 'page' : undefined" :aria-label="`Página ${i + 1}`"
                      class="inline-flex h-9 min-w-9 items-center justify-center rounded-full px-2 text-sm font-semibold"
                      :class="i === pagina ? 'bg-rol-fuerte text-white' : 'text-azul-noche hover:bg-gray-100'">{{ i + 1 }}</button>
              <button type="button" @click="irAPagina(pagina + 1)" :disabled="pagina >= totalPaginas - 1" aria-label="Página siguiente"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">›</button>
              <button type="button" @click="irAPagina(totalPaginas - 1)" :disabled="pagina >= totalPaginas - 1" aria-label="Última página"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">»</button>
            </nav>
          </div>
        </section>
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
        <div @click="cerrarEncuentro"
             class="absolute inset-0 bg-black/40 backdrop-blur-sm" />

        <div class="relative bg-white rounded-[1.75rem] shadow-2xl max-w-2xl w-full max-h-[92vh]
                    overflow-hidden border border-borde flex flex-col">
          <!-- Cabecera -->
          <div class="px-5 sm:px-7 pt-6 sm:pt-7 pb-5 border-b border-borde flex-shrink-0">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                  <span class="w-2 h-2 rounded-full bg-rol-fuerte flex-shrink-0" />
                  <p class="text-xs font-bold uppercase tracking-[0.18em] text-rol-fuerte">
                    Encuentro
                  </p>
                </div>
                <h2 class="text-lg font-bold text-azul-noche leading-snug">
                  {{ encuentroAbierto.proyecto_titulo || '(sin título)' }}
                </h2>
              </div>
              <button @click="cerrarEncuentro"
                      class="flex-shrink-0 inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-gray-100 text-texto-secundario
                             hover:text-texto-secundario transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="flex flex-wrap gap-1.5 mt-3">
              <span v-if="encuentroAbierto.curso"       class="tag tag-green">{{ encuentroAbierto.curso }}</span>
              <span v-if="encuentroAbierto.fecha"       class="tag tag-gray">{{ formatFecha(encuentroAbierto.fecha) }}</span>
              <span v-if="encuentroAbierto.grupo"       class="tag tag-lime">Clase {{ encuentroAbierto.grupo }}</span>
              <span v-if="encuentroAbierto.num_alumnos" class="tag tag-gray">{{ encuentroAbierto.num_alumnos }} alumnos</span>
            </div>
            <button @click="irAAccesoAlumnado"
                    class="mt-3 w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-full
                           bg-rol/10 border border-rol/20 text-rol-fuerte
                           text-xs font-bold uppercase tracking-widest hover:bg-rol/20 transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              Dar acceso al alumnado
            </button>
          </div>

          <!-- Contenido con scroll -->
          <div class="flex-1 overflow-y-auto min-h-0">

          <!-- Cuerpo -->
          <div class="px-5 sm:px-7 py-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div v-if="encuentroAbierto.centro_educativo"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-borde">
                <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario mb-0.5">Centro</p>
                <p class="text-sm font-bold text-azul-noche leading-snug">{{ encuentroAbierto.centro_educativo }}</p>
              </div>
              <div v-if="encuentroAbierto.ciclo_formativo"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-borde">
                <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario mb-0.5">Ciclo</p>
                <p class="text-sm font-bold text-azul-noche leading-snug">{{ encuentroAbierto.ciclo_formativo }}</p>
              </div>
              <div v-if="encuentroAbierto.fecha"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-borde">
                <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario mb-0.5">Fecha</p>
                <p class="text-sm font-bold text-azul-noche">{{ formatFecha(encuentroAbierto.fecha) }}</p>
              </div>
              <div v-if="encuentroAbierto.microproyecto_uuid"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-borde">
                <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario mb-0.5">Fecha fin</p>
                <template v-if="!editandoFechaFin">
                  <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-bold text-azul-noche">{{ encuentroAbierto.fecha_fin ? formatFecha(encuentroAbierto.fecha_fin) : 'Sin definir' }}</p>
                    <button @click="empezarEditarFechaFin" class="inline-flex min-h-9 items-center rounded-full px-2 text-xs font-bold uppercase text-rol-fuerte hover:underline shrink-0">Editar</button>
                  </div>
                </template>
                <template v-else>
                  <input v-model="fechaFinInput" type="date" class="field-input !py-1.5 text-sm" />
                  <div class="flex items-center gap-2 mt-1.5">
                    <button @click="guardarFechaFin" :disabled="guardandoFechaFin"
                            class="inline-flex min-h-9 items-center rounded-full px-2 text-xs font-bold uppercase text-rol-fuerte disabled:opacity-50">{{ guardandoFechaFin ? 'Guardando…' : 'Guardar' }}</button>
                    <button @click="editandoFechaFin = false" class="inline-flex min-h-9 items-center rounded-full px-2 text-xs font-bold uppercase text-texto-secundario">Cancelar</button>
                  </div>
                  <p v-if="errorFechaFin" class="text-xs text-red-500 mt-1">{{ errorFechaFin }}</p>
                </template>
              </div>
              <div v-if="encuentroAbierto.num_alumnos"
                   class="p-3.5 rounded-xl bg-[#F8FAFC] border border-borde">
                <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario mb-0.5">Alumnos</p>
                <p class="text-sm font-bold text-azul-noche">{{ encuentroAbierto.num_alumnos }}</p>
              </div>
            </div>
            <div v-if="encuentroAbierto.notas"
                 class="p-4 rounded-xl bg-[#F8FAFC] border border-borde">
              <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario mb-1.5">Notas adicionales</p>
              <p class="text-sm text-azul-noche leading-relaxed">{{ encuentroAbierto.notas }}</p>
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

          <!-- Acceso del alumnado (destacada — incluye el desbloqueo de IA) -->
          <div ref="seccionAccesoAlumnado"
               class="mx-5 sm:mx-7 my-4 p-4 sm:p-5 rounded-2xl bg-rol/5 border-2 border-rol/20">
          <p class="text-xs font-bold uppercase tracking-wider text-rol-fuerte mb-3">Acceso del alumnado</p>
            <div v-if="encuentroAbierto.codigo_clase"
                 class="flex flex-wrap items-center gap-3 p-3 rounded-2xl bg-rol/5 border border-rol/15">
              <span class="w-2 h-2 rounded-full bg-rol-fuerte animate-pulse shrink-0"></span>
              <span class="min-w-0 break-all text-lg font-bold tracking-[0.2em] text-azul-noche flex-1">{{ encuentroAbierto.codigo_clase }}</span>
              <div class="flex shrink-0 items-center gap-3">
              <button @click="copiarCodigo(encuentroAbierto.codigo_clase)"
                      class="flex min-h-9 items-center gap-1.5 px-3 py-1.5 rounded-full bg-rol-fuerte text-white
                             text-xs font-bold uppercase tracking-widest hover:bg-rol/90 transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Copiar
              </button>
              <button v-if="encuentroAbierto.puede_editar"
                      @click="crearCodigo(encuentroAbierto)" :disabled="creandoCodigo[encuentroAbierto.id]"
                      title="Vuelve a generar grupos desde cero — bloqueado si ya hay progreso real"
                      class="inline-flex min-h-9 items-center px-3 py-1.5 rounded-full bg-gray-100 border border-borde
                             text-xs font-bold uppercase tracking-widest text-texto-secundario
                             hover:bg-gray-200 transition-all disabled:opacity-50">
                Regen.
              </button>
              </div>
            </div>
            <div v-if="encuentroAbierto.puede_editar" class="mt-2">
              <button @click="abrirReestructurar(encuentroAbierto)"
                      class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-full
                             bg-white border border-rol/30 text-rol-fuerte
                             text-xs font-bold uppercase tracking-widest hover:bg-rol/5 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Editar grupo
              </button>
            </div>
            <div v-if="!encuentroAbierto.codigo_clase && encuentroAbierto.puede_editar">
              <button @click="crearCodigo(encuentroAbierto)" :disabled="creandoCodigo[encuentroAbierto.id]"
                      class="w-full flex items-center justify-center gap-2 py-3 rounded-full
                             border border-dashed border-rol/30 text-rol-fuerte text-xs font-bold
                             uppercase tracking-widest hover:bg-rol/5 transition-all disabled:opacity-50">
                <svg class="w-4 h-4" :class="creandoCodigo[encuentroAbierto.id] ? 'animate-spin' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="!creandoCodigo[encuentroAbierto.id]" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                  <path v-else fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                </svg>
                {{ creandoCodigo[encuentroAbierto.id] ? 'Creando...' : 'Generar código workspace alumnado' }}
              </button>
              <p v-if="errorCodigo[encuentroAbierto.id]" class="text-xs text-red-400 mt-1.5 text-center">
                {{ errorCodigo[encuentroAbierto.id] }}
              </p>
            </div>

            <!-- Código de desbloqueo de "Sugerir con IA" en el workspace -->
            <div class="mt-4 pt-4 border-t border-rol/15">
            <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario mb-3">
              ✨ Desbloqueo IA del workspace
            </p>
            <p class="text-xs text-texto-secundario mb-3 leading-relaxed">
              Los botones "Sugerir con IA" del workspace de los grupos permanecen bloqueados hasta que
              introducen este código. Compártelo cuando quieras habilitarlos.
            </p>
            <div v-if="encuentroAbierto.codigo_ia"
                 class="flex flex-wrap items-center gap-3 p-3 rounded-2xl bg-orange-50 border border-orange-200">
              <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse shrink-0"></span>
              <span class="min-w-0 break-all text-lg font-bold tracking-[0.2em] text-orange-700 flex-1">{{ encuentroAbierto.codigo_ia }}</span>
              <div class="flex shrink-0 items-center gap-3">
              <button @click="copiarCodigo(encuentroAbierto.codigo_ia)"
                      class="flex min-h-9 items-center gap-1.5 px-3 py-1.5 rounded-full bg-orange-600 text-white
                             text-xs font-bold uppercase tracking-widest hover:bg-orange-700 transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Copiar
              </button>
              <button v-if="encuentroAbierto.puede_editar"
                      @click="crearCodigoIa(encuentroAbierto)" :disabled="creandoCodigoIa[encuentroAbierto.id]"
                      title="Genera un código nuevo — los grupos que ya lo desbloquearon siguen desbloqueados"
                      class="inline-flex min-h-9 items-center px-3 py-1.5 rounded-full bg-gray-100 border border-borde
                             text-xs font-bold uppercase tracking-widest text-texto-secundario
                             hover:bg-gray-200 transition-all disabled:opacity-50">
                Regen.
              </button>
              </div>
            </div>
            <button v-else-if="encuentroAbierto.puede_editar" @click="crearCodigoIa(encuentroAbierto)" :disabled="creandoCodigoIa[encuentroAbierto.id]"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-full
                           border border-dashed border-orange-300 text-orange-600 text-xs font-bold
                           uppercase tracking-widest hover:bg-orange-50 transition-all disabled:opacity-50">
              <svg class="w-4 h-4" :class="creandoCodigoIa[encuentroAbierto.id] ? 'animate-spin' : ''"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="!creandoCodigoIa[encuentroAbierto.id]" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                <path v-else fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
              {{ creandoCodigoIa[encuentroAbierto.id] ? 'Generando...' : 'Generar código sugerencia IA alumnado' }}
            </button>
            <p v-if="errorCodigoIa[encuentroAbierto.id]" class="text-xs text-red-400 mt-1.5 text-center">
              {{ errorCodigoIa[encuentroAbierto.id] }}
            </p>
          </div>

          </div>
          </div>
          <!-- /Contenido con scroll -->

          <!-- Pie -->
          <div class="px-5 sm:px-7 py-4 bg-[#F8FAFC] border-t border-borde flex-shrink-0 space-y-3">

            <!-- Acciones (mismo estilo que la card de encuentro) -->
            <div class="grid grid-cols-2 gap-1.5">
              <button v-if="encuentroAbierto.puede_editar"
                      @click="abrirReestructurar(encuentroAbierto)"
                      class="inline-flex min-h-9 items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-full
                             border border-rol/30 bg-white text-rol-fuerte hover:bg-rol/5 transition-all
                             text-xs font-bold uppercase tracking-wide">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Editar grupo
              </button>
              <button v-if="encuentroAbierto.es_propietario"
                      @click="abrirCompartir(encuentroAbierto)"
                      class="inline-flex min-h-9 items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-full
                             bg-amber-50 text-amber-600 hover:bg-amber-100 transition-all
                             text-xs font-bold uppercase tracking-wide">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
                Compartir
              </button>
              <button v-if="encuentroAbierto.microreto_id"
                      @click="abrirMicroretoModal(encuentroAbierto.microreto_id)"
                      class="inline-flex min-h-9 items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-full
                             bg-rol/10 text-rol-fuerte hover:bg-rol/20 transition-all
                             text-xs font-bold uppercase tracking-wide">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                           a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Ficha del reto
              </button>
              <button v-if="encuentroAbierto.microproyecto_uuid"
                      @click="abrirProyectoFicha(encuentroAbierto.microproyecto_uuid)"
                      class="inline-flex min-h-9 items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-full
                             bg-rol/5 text-rol-fuerte hover:bg-rol/10 transition-all
                             text-xs font-bold uppercase tracking-wide">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                </svg>
                Ficha del proyecto
              </button>
            </div>
            <div v-if="encuentroAbierto.puede_editar" class="flex justify-end">
              <button @click="abrirModalEliminar(encuentroAbierto)"
                      class="inline-flex min-h-9 items-center gap-1.5 px-2.5 py-1.5 rounded-full
                             hover:bg-red-50 text-texto-secundario hover:text-red-500 transition-all
                             text-xs font-bold uppercase tracking-wide">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Eliminar
              </button>
            </div>

            <!-- Acciones principales -->
            <div class="flex flex-wrap items-center gap-2 border-t border-borde pt-3">
              <button v-if="encuentroAbierto.microproyecto_uuid"
                      @click="router.push({ name: 'mis-grupos-detalle', params: { id: encuentroAbierto.id } }); cerrarEncuentro()"
                      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full border border-rol/20
                             bg-rol/5 text-azul-noche text-xs font-bold uppercase tracking-widest
                             hover:bg-rol/10 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                </svg>
                Workspace
              </button>
              <button v-if="encuentroAbierto.codigo_clase && encuentroAbierto.microproyecto_uuid"
                      @click="router.push({ name: 'pantalla-acceso', params: { uuid: encuentroAbierto.microproyecto_uuid } }); cerrarEncuentro()"
                      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full border border-rol/30
                             bg-white text-rol-fuerte text-xs font-bold uppercase tracking-widest
                             hover:bg-rol/5 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4v1m6 11h2m-6 0h-2m-6 0H4m2-11H4m10.828 6.828a4 4 0 10-5.656-5.656 4 4 0 005.656 5.656zM7 20l1.5-1.5M17 20l-1.5-1.5"/>
                </svg>
                Pantalla de acceso
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Modal ficha de microreto -->
  <MicroretoModal :microreto-id="microretoModalId" @close="cerrarMicroretoModal" />

  <!-- Modal ficha de proyecto (botón "Ficha del proyecto" de la card) -->
  <ProyectoFichaModal :proyecto-uuid="proyectoFichaUuid" @close="cerrarProyectoFicha" />

  <!-- Modal eliminar encuentro -->
  <EliminarEncuentroModal
    :visible="modalEliminarVisible"
    :encuentro="encuentroAEliminar"
    @encuentro-eliminado="onEncuentroEliminado"
    @cerrar="cerrarModalEliminar"
  />

  <!-- Modal reestructurar equipo -->
  <ReestructurarEquipoModal
    :visible="!!reestructurando"
    :encuentro="reestructurando"
    @actualizado="onEquipoReestructurado"
    @alias-actualizado="onAliasActualizado"
    @cerrar="cerrarReestructurar"
  />

  <!-- Modal compartir encuentro -->
  <CompartirEncuentroModal
    :visible="!!compartiendo"
    :encuentro="compartiendo"
    @cerrar="cerrarCompartir"
  />

  <!-- Snackbar -->
  <Transition name="ses-snack">
    <div
      v-if="snackbar.visible"
      class="fixed bottom-14 left-4 right-4 sm:left-auto sm:right-6 z-[60] flex items-center gap-3
             px-5 py-3.5 rounded-2xl shadow-xl text-sm font-bold
             max-w-[calc(100vw-2rem)] sm:max-w-sm bg-white text-azul-noche border border-borde shadow-lg"
    >
      <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      <span class="min-w-0 flex-1 break-words">{{ snackbar.mensaje }}</span>
      <button
        v-if="snackbar.accion"
        @click="snackbar.accion.fn(); snackbar.visible = false"
        class="ml-1 shrink-0 px-3 py-1.5 rounded-full bg-amber-400 text-azul-noche text-xs font-bold uppercase tracking-widest hover:bg-amber-300 transition-all"
      >
        {{ snackbar.accion.label }}
      </button>
    </div>
  </Transition>

</template>

<style scoped>
.field-input {
  width: 100%;
  background: #F9FAFB;
  border: 1px solid #E5E7EB;
  border-radius: 0.75rem;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #17283E;
  outline: none;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}
.field-input:focus {
  border-color: #3072AA;
  box-shadow: 0 0 0 3px rgba(48, 114, 170, 0.12);
  background: #fff;
}
.field-input::placeholder { color: #D1D5DB; font-weight: 400; }
select.field-input { cursor: pointer; }
.tag {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.tag-gray  { background: #F3F4F6; color: #6B7280; }
.tag-green { background: rgba(48,114,170,0.1); color: #3072AA; }
.tag-lime  { background: rgba(107,164,213,0.12); color: #275d8a; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 200ms ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .relative { transform: scale(0.95) translateY(8px); opacity: 0; }

.ses-snack-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.ses-snack-leave-active { transition: all 0.2s ease-in; }
.ses-snack-enter-from   { opacity: 0; transform: translateY(12px); }
.ses-snack-leave-to     { opacity: 0; transform: translateY(8px); }
</style>
