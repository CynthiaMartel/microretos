<!-- Ruta: /panel-docente (name: inicio-docente). Antes vivía en /inicio-docente — ver router/index.js.
     Diseño basado en "Aplicación moodboard a dashboard.png" e "idea_dashboard.png".
     Usa endpoints que ya existen (/encuentros, /startup/proyectos y /microretos):
     todas las métricas se derivan en cliente, sin cambios de backend.

     Responsive con container queries (@container) en vez de breakpoints de viewport:
     el SidePanel fijo resta 288px en lg+, así que el ancho útil real no coincide con
     el de la ventana y los breakpoints normales hacían que unas tarjetas tapasen a otras. -->
<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import api from '../api.js'
import CatalogoBoeModal from '../components/CatalogoBoeModal.vue'
import bannerCasoReal from '../assets/banner_caso_real.jpg'
import bienvenidaCasoReal from '../assets/bienvenida_caso_real.jpg'

const router    = useRouter()
const authStore = useAuthStore()

// ── Datos ─────────────────────────────────────────────────────────────────────
const encuentros = ref([])
const proyectos  = ref([])
const retos      = ref([])
const cargando   = ref(true)

onMounted(async () => {
  // /microretos devuelve fichas completas: para elegir el reto destacado bastan los más recientes.
  const [enc, pro, ret] = await Promise.allSettled([
    api.get('/encuentros'),
    api.get('/startup/proyectos'),
    api.get('/microretos', { params: { limit: 60 } }),
  ])
  if (enc.status === 'fulfilled') encuentros.value = enc.value.data
  if (pro.status === 'fulfilled') proyectos.value  = pro.value.data
  if (ret.status === 'fulfilled') retos.value      = ret.value.data
  cargando.value = false
})

const _pf  = (s) => s ? (s.includes('T') ? new Date(s) : new Date(s + 'T12:00:00')) : null
const pad  = (n) => String(n).padStart(2, '0')
const iso  = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`
const hoy  = new Date()
const hoyISO = iso(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())

// ── Curso académico (septiembre → agosto) ─────────────────────────────────────
// El campo `curso` de encuentros/proyectos es el nivel (1º, 2º), no el año
// académico, así que el curso se deduce de la fecha de cada registro.
const cursoDe = (d) => d ? (d.getMonth() >= 8 ? d.getFullYear() : d.getFullYear() - 1) : null
const etiquetaCurso = (y) => `Curso ${y}/${y + 1}`

const cursoActual = cursoDe(hoy)
const cursoSel    = ref(cursoActual)

const cursosDisponibles = computed(() => {
  const set = new Set([cursoActual])
  encuentros.value.forEach(e => { const c = cursoDe(_pf(e.fecha)); if (c) set.add(c) })
  proyectos.value.forEach(p => { const c = cursoDe(fechaProyecto(p)); if (c) set.add(c) })
  return [...set].sort((a, b) => b - a)
})

// Fecha de referencia de un proyecto = la de su encuentro; si no tiene, la de creación.
// Así contadores, gráfica, donut y lista asignan cada proyecto al MISMO curso (antes la
// gráfica iba por encuentro y el donut por created_at, y no cuadraban).
const fechaEncuentroPorId = computed(() => new Map(encuentros.value.map(e => [e.id, e.fecha])))
const fechaProyecto = (p) => _pf(fechaEncuentroPorId.value.get(p.encuentro_id) || p.created_at)

const encCurso   = computed(() => encuentros.value.filter(e => cursoDe(_pf(e.fecha)) === cursoSel.value))
const proCurso   = computed(() => proyectos.value.filter(p => cursoDe(fechaProyecto(p)) === cursoSel.value))

// ── Contadores ────────────────────────────────────────────────────────────────
const kpis = computed(() => [
  { key: 'alumnos',    valor: encCurso.value.reduce((s, e) => s + (Number(e.num_alumnos) || 0), 0), label: 'Alumnos participantes',  tile: 'bg-alumnos',          num: 'text-alumnos-dark',          icon: 'alumnos',   ruta: '/mis-equipos' },
  { key: 'empresas',   valor: new Set(proCurso.value.map(p => p.empresa_id).filter(Boolean)).size,  label: 'Empresas colaboradoras', tile: 'bg-empresas',         num: 'text-empresas-dark',         icon: 'empresas',  ruta: '/empresas' },
  { key: 'encuentros', valor: encCurso.value.length,                                                 label: 'Encuentros realizados',  tile: 'bg-centros',          num: 'text-centros',          icon: 'encuentro', ruta: '/encuentros' },
  { key: 'validados',  valor: proCurso.value.filter(p => ['validado', 'completado'].includes(p.estado)).length, label: 'Proyectos validados', tile: 'bg-administraciones', num: 'text-[#0F7273]', icon: 'proyecto', ruta: '/proyectos' },
])

// ── Impacto acumulado del curso ───────────────────────────────────────────────
// Una sola gráfica con 4 líneas acumuladas en el MISMO eje (como el PNG); eje X de
// septiembre a junio completo. Si el curso tiene datos en julio/agosto, el eje se alarga.
// En el curso en marcha las líneas se detienen en el mes actual (sin meses futuros).
// Escalas: los alumnos suelen ser muchos más que el resto; para que eso no aplaste
// las otras líneas, la leyenda permite ocultar/mostrar cada serie y el eje se reajusta
// a las visibles. Nunca un segundo eje. Mismas fuentes que contadores y donut:
//   · Alumnos en proyectos completados: num_alumnos de encuentros del curso cuyo proyecto está completado
//   · Proyectos completados: esos proyectos, en el mes de su primer encuentro del curso
//   · Empresas colaboradoras: empresas de proyectos del curso, en el mes del primero
//   · Encuentros realizados: encuentros del curso por fecha
const MESES = ['Sep', 'Oct', 'Nov', 'Dic', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago']
const mesIdx = (d) => (d.getMonth() + 4) % 12 // septiembre = 0

const INDICADORES = [
  { key: 'alumnos',     label: 'Alumnos en proyectos completados', color: '#FF8920' },
  { key: 'completados', label: 'Proyectos completados',            color: '#19A7A8' },
  { key: 'empresas',    label: 'Empresas colaboradoras',           color: '#509928' },
  { key: 'encuentros',  label: 'Encuentros realizados',            color: '#3072AA' },
]

const impacto = computed(() => {
  const estadoPorUuid = new Map(proyectos.value.map(p => [p.uuid, p.estado]))
  const mes = Object.fromEntries(INDICADORES.map(ind => [ind.key, Array(12).fill(0)]))

  const completadosVistos = new Set()
  ;[...encCurso.value].sort((a, b) => a.fecha.localeCompare(b.fecha)).forEach(e => {
    const m = mesIdx(_pf(e.fecha))
    mes.encuentros[m]++
    if (e.microproyecto_uuid && estadoPorUuid.get(e.microproyecto_uuid) === 'completado') {
      mes.alumnos[m] += Number(e.num_alumnos) || 0
      if (!completadosVistos.has(e.microproyecto_uuid)) {
        completadosVistos.add(e.microproyecto_uuid)
        mes.completados[m]++
      }
    }
  })
  const empresasVistas = new Set()
  ;[...proCurso.value].sort((a, b) => fechaProyecto(a) - fechaProyecto(b)).forEach(p => {
    if (!p.empresa_id || empresasVistas.has(p.empresa_id)) return
    empresasVistas.add(p.empresa_id)
    mes.empresas[mesIdx(fechaProyecto(p))]++
  })

  // Eje: Sep–Jun siempre; Jul/Ago solo si hay datos en esos meses
  const ultimoConDatos = Math.max(-1, ...INDICADORES.map(ind => mes[ind.key].findLastIndex(v => v > 0)))
  const enCurso = cursoSel.value === cursoActual
  const eje = Math.max(10, ultimoConDatos + 1, enCurso ? mesIdx(hoy) + 1 : 0)
  const n   = enCurso ? mesIdx(hoy) + 1 : eje // meses con línea dibujada

  const acumular = (arr) => { let t = 0; return arr.slice(0, n).map(v => (t += v)) }
  return {
    eje, n, meses: MESES.slice(0, eje),
    series: INDICADORES.map(ind => {
      const acum = acumular(mes[ind.key])
      return { ...ind, acum, delMes: mes[ind.key].slice(0, n), total: acum[n - 1] ?? 0 }
    }),
  }
})
const hayImpacto = computed(() => impacto.value.series.some(s => s.total > 0))

// Series ocultas desde la leyenda (el eje se reajusta a las visibles)
const seriesOcultas = ref([])
const toggleSerie = (key) => {
  seriesOcultas.value = seriesOcultas.value.includes(key)
    ? seriesOcultas.value.filter(k => k !== key)
    : [...seriesOcultas.value, key]
}
const seriesVisibles = computed(() => impacto.value.series.filter(s => !seriesOcultas.value.includes(s.key)))

// El SVG se dibuja al ancho real del contenedor para que el texto de los ejes no encoja
const chartW = ref(560)
let chartRO = null
let chartEl = null
function observarChart(el) {
  if (!el || el === chartEl) return
  chartEl = el
  chartRO?.disconnect()
  chartRO = new ResizeObserver(([entry]) => {
    chartW.value = Math.max(240, Math.round(entry.contentRect.width))
  })
  chartRO.observe(el)
}
onBeforeUnmount(() => chartRO?.disconnect())

const techo = (v) => {
  if (v <= 4) return 4
  const mag = 10 ** Math.floor(Math.log10(v))
  const paso = v / mag <= 2 ? mag / 2 : mag
  return Math.ceil(v / paso) * paso
}
const CH = computed(() => ({ w: chartW.value, h: 200, l: 36, r: 12, t: 12, b: 26 }))
const yMaxImp  = computed(() => techo(Math.max(1, ...seriesVisibles.value.flatMap(s => s.acum))))
const ticksImp = computed(() => [0, 0.25, 0.5, 0.75, 1].map(f => Math.round(yMaxImp.value * f)))
const pasoImp  = computed(() => (CH.value.w - CH.value.l - CH.value.r) / (impacto.value.eje - 1))
const ix = (i) => CH.value.l + i * pasoImp.value
const iy = (v) => CH.value.t + (1 - v / yMaxImp.value) * (CH.value.h - CH.value.t - CH.value.b)
const mostrarMesImp = (i) => pasoImp.value >= 32 || i % 2 === 0

const curvasImpacto = computed(() => seriesVisibles.value.map(serie => {
  const pts = serie.acum.map((v, i) => [ix(i), iy(v)])
  return { ...serie, pts, linea: pts.map(([x, y], i) => `${i ? 'L' : 'M'}${x},${y}`).join(' ') }
}))
// Área suave bajo la serie más alta, como el sombreado del PNG
const areaImpacto = computed(() => {
  const top = [...curvasImpacto.value].sort((a, b) => b.total - a.total)[0]
  if (!top) return null
  const base = iy(0)
  return { color: top.color, d: `M${top.pts[0][0]},${base} ` + top.pts.map(([x, y]) => `L${x},${y}`).join(' ') + ` L${top.pts.at(-1)[0]},${base} Z` }
})

const mesHoverImp = ref(null)
const tooltipLeftImp = computed(() => mesHoverImp.value === null ? 0 : Math.min(Math.max(ix(mesHoverImp.value), 120), chartW.value - 120))

// ── Donut: estado de los proyectos como recorrido ─────────────────────────────
// Los segmentos y la leyenda siguen el orden real del proyecto (del borrador al
// cierre) y cada paso lleva a la biblioteca filtrada. Colores = los 4 corporativos
// del moodboard. En anillo, ningún orden de esos 4 pasa todas las comprobaciones de
// daltonismo: este (naranja → azul → verde → turquesa) es el mejor (verde/turquesa
// quedan en el límite), y se compensa con separadores de 2px y cifra + % en la leyenda.
const R = 54
const CIRC = 2 * Math.PI * R
const donutHover = ref(null) // etiqueta del estado señalado en la leyenda o en el anillo
const filtroRuta = (filtro) => ({ path: '/proyectos', query: { filtro } })
const donut = computed(() => {
  const all = proCurso.value
  const prop = all.filter(p => p.estado === 'propuesta')
  const sinEnviar = prop.filter(p => !p.enviado_a_empresa_mail && !p.empresa_no_valida_aun).length
  const esperando = prop.filter(p => p.enviado_a_empresa_mail && !p.empresa_no_valida_aun).length
  const revisar   = prop.filter(p => p.empresa_no_valida_aun).length
  const grupos = [
    { label: 'En edición',        color: '#FF8920', ruta: filtroRuta('en_edicion'), valor: all.filter(p => p.estado === 'en_edicion').length },
    { label: 'Pendiente validar', color: '#3072AA', ruta: filtroRuta('propuesta'),  valor: prop.length,
      detalle: [esperando && `${esperando} esperando`, sinEnviar && `${sinEnviar} sin enviar`, revisar && `${revisar} a revisar`].filter(Boolean).join(' · ') },
    { label: 'Validados',         color: '#509928', ruta: filtroRuta('validado'),   valor: all.filter(p => p.estado === 'validado').length },
    { label: 'Completados',       color: '#19A7A8', ruta: '/proyectos/terminados',  valor: all.filter(p => p.estado === 'completado').length },
  ]
  const total = grupos.reduce((s, g) => s + g.valor, 0)
  const GAP = grupos.filter(g => g.valor).length > 1 ? 2 : 0 // separador de 2px entre segmentos
  let acum = 0
  const segmentos = grupos.map(g => {
    const len = total ? (g.valor / total) * CIRC : 0
    const seg = { ...g, pct: total ? Math.round((g.valor / total) * 100) : 0, dash: Math.max(len - GAP, 0), offset: -acum }
    acum += len
    return seg
  })
  return { total, segmentos }
})

// ── Reto destacado de la semana ───────────────────────────────────────────────
// Elección determinista, sin backend ni aleatoriedad:
//  1. Candidatos: los 60 retos más recientes que devuelve /microretos (hoy, solo
//     los del propio centro).
//  2. Se descartan los que el docente ya usa en algún proyecto.
//  3. Si quedan retos de las familias en las que el docente tiene proyectos, solo esos.
//  4. Se ordenan por id y se elige el de posición (nº de semana % nº de candidatos).
// Cambia cada lunes; dentro de la semana es el mismo salvo que cambien los candidatos
// (p. ej. el docente usa ese reto en un proyecto → pasa al siguiente).
const retosEnUso = computed(() => new Set(proyectos.value.map(p => p.microreto_id).filter(Boolean)))
const retoDestacado = computed(() => {
  if (!retos.value.length) return null
  // Días desde 1970 (jueves) + 3 → las semanas empiezan en lunes
  const dias     = Math.floor((Date.now() - hoy.getTimezoneOffset() * 60_000) / 86_400_000)
  const semana   = Math.floor((dias + 3) / 7)
  const familias = new Set(proyectos.value.map(p => p.familia_nombre).filter(Boolean))
  const libres   = retos.value.filter(r => !retosEnUso.value.has(r.id))
  const afines   = libres.filter(r => familias.has(r.familia))
  const lista    = [...(afines.length ? afines : libres.length ? libres : retos.value)].sort((a, b) => a.id - b.id)
  return lista[semana % lista.length]
})
const nivelClase = (nivel) => ({
  Bajo:  'bg-centros/5 border-centros/20 text-centros',
  Medio: 'bg-[#F59E0B]/10 border-[#F59E0B]/20 text-[#B45309]',
  Alto:  'bg-[#D64545]/10 border-[#D64545]/20 text-[#D64545]',
}[nivel] || 'bg-gray-100 border-gray-200 text-gray-500') // = BibliotecaMicroretos.vue
const abrirReto = (r) => router.push({ name: 'detalle-microreto', params: { id: r.uuid || r.id } })

// ── Próximos encuentros + mini calendario enlazados ───────────────────────────
// Pasar el ratón (o el foco) por un encuentro de la lista ilumina sus días en el
// calendario; hacer clic lo deja fijado. Al revés, clicar un día del calendario
// ilumina los encuentros de la lista que caen ese día y muestra el detalle.
const proximosEncuentros = computed(() =>
  encuentros.value
    .filter(e => e.fecha && (e.fecha_fin || e.fecha) >= hoyISO)
    .sort((a, b) => a.fecha.localeCompare(b.fecha))
    .slice(0, 2)
)

const cubreDia = (e, diaISO) => e.fecha && diaISO >= e.fecha && diaISO <= (e.fecha_fin || e.fecha)

const hoverEncId = ref(null)
const selEncId   = ref(null)
const selDia     = ref(null) // ISO del día clicado en el calendario
const encActivo  = computed(() => {
  const id = hoverEncId.value ?? selEncId.value
  return id ? encuentros.value.find(e => e.id === id) : null
})

function seleccionarEncuentro(e) {
  selDia.value   = null
  selEncId.value = selEncId.value === e.id ? null : e.id
}

const encDestacado = (e) =>
  encActivo.value?.id === e.id || (selDia.value && cubreDia(e, selDia.value))

// Mes visible: el del encuentro activo si lo hay; si no, el que haya navegado el usuario
const calMes = ref(new Date(hoy.getFullYear(), hoy.getMonth(), 1))
const mesVisible = computed(() => {
  const d = encActivo.value ? _pf(encActivo.value.fecha) : calMes.value
  return { y: d.getFullYear(), m: d.getMonth() }
})
const calLabel = computed(() =>
  new Date(mesVisible.value.y, mesVisible.value.m, 1).toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })
)
function moverMes(delta) {
  const { y, m } = mesVisible.value
  calMes.value     = new Date(y, m + delta, 1)
  selEncId.value   = null
  hoverEncId.value = null
}

const calDias = computed(() => {
  const { y, m } = mesVisible.value
  const offset = (new Date(y, m, 1).getDay() + 6) % 7 // semana empieza en lunes
  const total  = new Date(y, m + 1, 0).getDate()
  // Como en "Mi agenda" (idea_dashboard.png): semanas completas, con los días del mes
  // anterior y siguiente en gris claro y sin interacción.
  const finMesAnterior = new Date(y, m, 0).getDate()
  const celdas = Array.from({ length: offset }, (_, i) => ({ d: finMesAnterior - offset + 1 + i, otroMes: true }))
  for (let d = 1; d <= total; d++) {
    const diaISO = iso(y, m, d)
    celdas.push({
      d, iso: diaISO,
      encs: encuentros.value.filter(e => cubreDia(e, diaISO)),
      hoy: diaISO === hoyISO,
    })
  }
  // Siempre 6 semanas: así el calendario mide lo mismo en todos los meses
  for (let d = 1; celdas.length < 42; d++) celdas.push({ d, otroMes: true })
  return celdas
})

const diaIluminado = (c) =>
  (encActivo.value && cubreDia(encActivo.value, c.iso)) || selDia.value === c.iso

function clicDia(c) {
  if (!c.encs.length) return
  selEncId.value = null
  selDia.value   = selDia.value === c.iso ? null : c.iso
}

const encuentrosSelDia = computed(() =>
  selDia.value ? encuentros.value.filter(e => cubreDia(e, selDia.value)) : []
)

const tituloEnc = (e) => e.proyecto_titulo || e.microreto_titulo || 'Encuentro'
const fechaTile = (isoStr) => {
  const d = _pf(isoStr)
  return { dia: d.getDate(), mes: d.toLocaleDateString('es-ES', { month: 'short' }).replace('.', '').toUpperCase() }
}
const fechaCorta = (isoStr) => _pf(isoStr).toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' })

// ── Proyectos ─────────────────────────────────────────────────────────────────
// Proyectos: mismos filtros que StartupDayProyectos.vue (filtroOpciones/filtroLabels)
// + "Completados", que allí tiene vista propia. Etiquetas y colores = ProyectoCard.vue
// (getEtiqueta/getColor), con el tema docente (centros) para "Validado".
const FILTROS_PROY = [
  { key: 'todos',      label: 'Todos' },
  { key: 'validado',   label: 'Validados' },
  { key: 'propuesta',  label: 'Pendiente validar' },
  { key: 'en_edicion', label: 'En edición' },
  { key: 'completado', label: 'Completados' },
  { key: 'archivado',  label: 'Archivado' },
]
const filtroProy = ref('completado') // por defecto: lo que mejor luce el trabajo hecho
const conteoProy = (key) => key === 'todos' ? proCurso.value.length : proCurso.value.filter(p => p.estado === key).length
const proyectosFiltrados = computed(() =>
  proCurso.value
    .filter(p => filtroProy.value === 'todos' || p.estado === filtroProy.value)
    .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
)

// Paginación de 5 con alto fijo: la tarjeta mide lo mismo con 0, 2 o 5 proyectos
// (la lista siempre reserva 5 filas y el pie de paginación siempre ocupa su sitio).
const PROY_POR_PAGINA = 5
const paginaProy = ref(0)
const totalPaginasProy = computed(() => Math.max(1, Math.ceil(proyectosFiltrados.value.length / PROY_POR_PAGINA)))
const paginaProyActual = computed(() => Math.min(paginaProy.value, totalPaginasProy.value - 1))
const proyectosPagina = computed(() =>
  proyectosFiltrados.value.slice(paginaProyActual.value * PROY_POR_PAGINA, (paginaProyActual.value + 1) * PROY_POR_PAGINA))
const moverPaginaProy = (d) => { paginaProy.value = Math.min(Math.max(paginaProyActual.value + d, 0), totalPaginasProy.value - 1) }
watch([filtroProy, cursoSel], () => { paginaProy.value = 0 })

function etiquetaProyecto(p) {
  if (p.estado === 'en_edicion') return 'En edición'
  if (p.estado === 'archivado')  return 'Archivado'
  if (p.estado === 'completado') return 'Completado'
  if (p.estado === 'validado') {
    if (p.empresa_validado && p.docente_validado) return 'Validado · Completo'
    if (p.empresa_validado) return 'Validado · Empresa'
    if (p.docente_validado) return 'Validado · Docente'
    return 'Validado'
  }
  if (p.empresa_no_valida_aun)  return 'No validar aún'
  if (p.enviado_a_empresa_mail) return 'Esperando respuesta'
  return 'Pendiente enviar'
}
function colorProyecto(p) {
  if (p.estado === 'en_edicion') return 'bg-amber-50 border-amber-200 text-amber-700'
  if (p.estado === 'archivado')  return 'bg-gray-100 border-gray-200 text-gray-400'
  if (p.estado === 'completado') return 'bg-sky-50 border-sky-300 text-sky-700'
  if (p.estado === 'validado') {
    if (p.docente_validado && !p.empresa_validado) return 'bg-emerald-50 border-emerald-300 text-emerald-700'
    return 'bg-centros/5 border-centros/20 text-centros'
  }
  if (p.empresa_no_valida_aun)  return 'bg-red-50 border-red-300 text-red-700'
  if (p.enviado_a_empresa_mail) return 'bg-blue-50 border-blue-200 text-blue-700'
  return 'bg-violet-50 border-violet-300 text-violet-700'
}

function verTodosProyectos() {
  if (filtroProy.value === 'todos') return irA('/proyectos')
  router.push({ path: '/proyectos', query: { filtro: filtroProy.value } })
}

// ── Empresas colaboradoras ────────────────────────────────────────────────────
const AVATAR_COLORES = ['bg-centros', 'bg-empresas', 'bg-alumnos', 'bg-administraciones']
const empresasTop = computed(() => {
  const map = new Map()
  proCurso.value.forEach(p => {
    if (!p.empresa_id) return
    const e = map.get(p.empresa_id) ?? { id: p.empresa_id, nombre: p.empresa_nombre || 'Empresa', familia: p.familia_nombre, proyectos: 0, validados: 0 }
    e.proyectos++
    if (['validado', 'completado'].includes(p.estado)) e.validados++
    map.set(p.empresa_id, e)
  })
  return [...map.values()].sort((a, b) => b.proyectos - a.proyectos).slice(0, 3)
})
const iniciales = (n) => n.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase()

// ── Tareas pendientes = avisos automáticos + notas personales ────────────────
// Las notas comparten la clave de localStorage del panel actual ('docente_notas'),
// así que lo que se apunte aquí aparece también allí y viceversa.
const tareasAuto = computed(() => {
  if (cargando.value) return []
  const now = Date.now()
  const dias = (d) => Math.floor((now - new Date(d).getTime()) / 86_400_000)
  const t = []
  const revisar = proyectos.value.filter(p => p.estado === 'propuesta' && p.empresa_no_valida_aun)
  if (revisar.length) t.push({ id: `revisar:${revisar.length}`, texto: `Revisar la respuesta de ${revisar.length} empresa${revisar.length > 1 ? 's' : ''}`, ruta: '/proyectos', nivel: 'alta' })
  const sinEnviar = proyectos.value.filter(p => p.estado === 'propuesta' && !p.enviado_a_empresa_mail && !p.empresa_no_valida_aun)
  if (sinEnviar.length) t.push({ id: `enviar:${sinEnviar.length}`, texto: `Enviar ${sinEnviar.length} propuesta${sinEnviar.length > 1 ? 's' : ''} a la empresa`, ruta: '/proyectos', nivel: 'media' })
  const estancados = proyectos.value.filter(p => p.estado === 'en_edicion' && p.updated_at && dias(p.updated_at) >= 14)
  if (estancados.length) t.push({ id: `retomar:${estancados.length}`, texto: `Retomar ${estancados.length} proyecto${estancados.length > 1 ? 's' : ''} parado${estancados.length > 1 ? 's' : ''} hace +14 días`, ruta: '/proyectos', nivel: 'media' })
  const sinRespuesta = proyectos.value.filter(p => p.estado === 'propuesta' && p.enviado_a_empresa_mail && !p.empresa_validado && p.updated_at && dias(p.updated_at) >= 10)
  if (sinRespuesta.length) t.push({ id: `recordar:${sinRespuesta.length}`, texto: `Recordar a ${sinRespuesta.length} empresa${sinRespuesta.length > 1 ? 's' : ''} sin responder (+10 días)`, ruta: '/proyectos', nivel: 'media' })
  return t
})

// Tareas automáticas marcadas como hechas: se guarda su id, que incluye el recuento
// (p. ej. "enviar:3"). Si la situación cambia (pasan a ser 4), es una tarea nueva y
// vuelve a salir sin marcar. Solo es una preferencia del navegador del docente.
const leerJSON = (clave, defecto) => { try { return JSON.parse(localStorage.getItem(clave) || 'null') ?? defecto } catch { return defecto } }
const guardarJSON = (clave, valor) => { try { localStorage.setItem(clave, JSON.stringify(valor)) } catch { /* sin almacenamiento */ } }

const autoHechas = ref(leerJSON('docente_tareas_hechas', []))
const autoHecha  = (t) => autoHechas.value.includes(t.id)
function toggleAuto(t) {
  autoHechas.value = autoHecha(t) ? autoHechas.value.filter(id => id !== t.id) : [...autoHechas.value, t.id]
  guardarJSON('docente_tareas_hechas', autoHechas.value)
}

// Notas personales: { id, text, hecha? } — "hecha" es un campo nuevo; el panel actual lo ignora
const notas     = ref(leerJSON('docente_notas', []))
const nuevaNota = ref('')
function guardarNotas(lista) {
  notas.value = lista
  guardarJSON('docente_notas', lista)
}
function addNota() {
  const texto = nuevaNota.value.trim()
  if (!texto) return
  guardarNotas([...notas.value, { id: Date.now(), text: texto }])
  nuevaNota.value = ''
}
const toggleNota = (id) => guardarNotas(notas.value.map(n => n.id === id ? { ...n, hecha: !n.hecha } : n))
const borrarNota = (id) => guardarNotas(notas.value.filter(n => n.id !== id))
// Una sola lista de 3 por página: primero las automáticas pendientes, luego tus notas
// pendientes y al final las hechas (tachadas). Así las automáticas siempre salen arriba.
// Igual que Proyectos: la lista reserva siempre 3 filas y el pie siempre ocupa su sitio,
// así la tarjeta no cambia de tamaño aunque se añadan tareas.
const TAREAS_POR_PAGINA = 3
const paginaTareas = ref(0)
const listaTareas = computed(() => {
  const autos = tareasAuto.value.map(t => ({ key: t.id, tipo: 'auto', t, hecha: autoHecha(t) }))
  const propias = notas.value.map(n => ({ key: 'nota-' + n.id, tipo: 'nota', n, hecha: !!n.hecha }))
  return [...autos.filter(x => !x.hecha), ...propias.filter(x => !x.hecha), ...autos.filter(x => x.hecha), ...propias.filter(x => x.hecha)]
})
const totalPaginasTareas = computed(() => Math.max(1, Math.ceil(listaTareas.value.length / TAREAS_POR_PAGINA)))
const paginaTareasActual = computed(() => Math.min(paginaTareas.value, totalPaginasTareas.value - 1))
const tareasPagina = computed(() =>
  listaTareas.value.slice(paginaTareasActual.value * TAREAS_POR_PAGINA, (paginaTareasActual.value + 1) * TAREAS_POR_PAGINA))
const moverPaginaTareas = (d) => { paginaTareas.value = Math.min(Math.max(paginaTareasActual.value + d, 0), totalPaginasTareas.value - 1) }

const pendientes = computed(() => tareasAuto.value.filter(t => !autoHecha(t)).length + notas.value.filter(n => !n.hecha).length)

// ── Herramientas ──────────────────────────────────────────────────────────────
// Mismo lenguaje visual que los contadores: icono blanco sobre color sólido de marca.
const herramientas = [
  {
    grupo: 'Proyectos',
    items: [
      { titulo: 'Generar proyecto',        desc: 'Crea una propuesta a partir de un reto', ruta: '/proyectos/crear',  tile: 'bg-empresas', icon: 'sp_generar_proyecto' },
      { titulo: 'Biblioteca de proyectos', desc: 'Consulta y gestiona tus proyectos',     ruta: '/proyectos',        tile: 'bg-empresas', icon: 'sp_biblioteca_proyectos' },
    ],
  },
  {
    grupo: 'Encuentros',
    items: [
      { titulo: 'Generar encuentro',        desc: 'Organiza un encuentro de trabajo',    ruta: '/encuentros/crear', tile: 'bg-centros', icon: 'sp_generar_encuentro' },
      { titulo: 'Biblioteca de encuentros', desc: 'Consulta los encuentros registrados', ruta: '/encuentros',       tile: 'bg-centros', icon: 'sp_biblioteca_encuentros' },
    ],
  },
  {
    grupo: 'Equipos',
    items: [
      { titulo: 'Mis equipos', desc: 'Equipos de alumnado y su avance por fases', ruta: '/mis-equipos', tile: 'bg-azul-noche', icon: 'sp_mis_equipos' },
    ],
  },
]

const ICONOS = {
  chispa:  'M13 10V3L4 14h7v7l9-11h-7z',
  mas:     'M12 5v14M5 12h14',
  libro:   'M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z',
  check:   'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
  capas:   'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
  usuario: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 0116 0',
  equipo:  'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
  // Iconos del SidePanel (mismos trazos) para que Herramientas coincida con el menú
  sp_generar_proyecto:     'M22 12a10 10 0 11-20 0 10 10 0 0120 0zM12 8v8M8 12h8',
  sp_biblioteca_proyectos: 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
  sp_generar_encuentro:    'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M10 3h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V4a1 1 0 011-1zM9 12l2 2 4-4',
  sp_biblioteca_encuentros:'M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 22v-15A2.5 2.5 0 016.5 2zM9 7h6M9 11h6',
  sp_mis_equipos:          'M3 3v18h18M7 15l4-6 4 4 5-8',
}

// ── Varios ────────────────────────────────────────────────────────────────────
const primerNombre = computed(() => (authStore.userName || '').split(' ')[0])
const mostrarCatalogoBoe = ref(false)
// Foto real del frontoffice (FRONTOFFICE/.../assets/8_dua.jpg): equipo con las manos juntas
const banner = { imagen: bannerCasoReal, alt: 'Equipo de alumnado y profesorado de FP con las manos juntas' }
// Foto real del frontoffice (FRONTOFFICE/.../assets/1_dua.jpeg): sesión de trabajo con alumnado y empresa
const bienvenida = { imagen: bienvenidaCasoReal, alt: 'Sesión de trabajo de un reto con alumnado de FP y profesionales' }
const irA = (ruta) => router.push(ruta)
</script>

<template>
  <div class="min-h-screen bg-[#F3F6FA] font-sans text-azul-noche pt-16">
    <div class="@container/page mx-auto max-w-[1440px] px-4 py-5 sm:px-6 lg:px-8">




      <!-- Cuadrícula compacta en dos columnas independientes (cada una fluye sin huecos) y una fila final a todo
           el ancho con el banner y los recursos. En pantallas estrechas, una sola columna en este orden. -->
      <div class="grid grid-cols-1 gap-4 @5xl/page:grid-cols-[minmax(0,1fr)_300px]">

        <!-- ══ Columna principal: bienvenida, resumen, gráfica + donut, proyectos + herramientas ══ -->
        <div class="@container/main flex min-w-0 flex-col gap-4">
          <!-- ══ Bienvenida (como idea_dashboard.png), sin recuadro: saludo y botones a la
               izquierda; foto real con cintas de los colores del logo y nota manuscrita a la derecha ══ -->
          <section class="@container relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#E4EEF9] via-[#EDF3FA] to-[#EDF3FA] @2xl/main:min-h-[230px]">
            <!-- En ancho, solo la foto se funde suavemente hacia la izquierda; las cintas de color van
                 encima sin difuminar, para que el azul y el naranja conserven todo su color -->
            <div class="relative h-44 @2xl:absolute @2xl:inset-y-0 @2xl:right-0 @2xl:h-auto @2xl:w-[52%]">
              <img :src="bienvenida.imagen" :alt="bienvenida.alt" class="absolute inset-0 h-full w-full object-cover object-[60%_50%] @2xl:[mask-image:linear-gradient(to_left,black_35%,rgba(0,0,0,0.3))] @2xl:[-webkit-mask-image:linear-gradient(to_left,black_35%,rgba(0,0,0,0.3))]" />
              <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 1000 300" preserveAspectRatio="none">
                <!-- Copia de los halos del banner de "Aplicación moodboard a dashboard.png":
                     izquierda = cinta azul que cae y se afila + cinta naranja encima que cubre la esquina inferior;
                     derecha = cinta verde que baja desde arriba + gran ola turquesa que sube hasta la esquina -->
                <defs>
                  <linearGradient id="bv-azul" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#2F7FD8" /><stop offset="100%" stop-color="#1E5FB8" /></linearGradient>
                  <linearGradient id="bv-azul-claro" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#9BD3F7" /><stop offset="100%" stop-color="#4FA3E8" /></linearGradient>
                  <linearGradient id="bv-naranja" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#FFB45C" /><stop offset="45%" stop-color="#FF8920" /><stop offset="100%" stop-color="#F57A0E" /></linearGradient>
                  <linearGradient id="bv-melocoton" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#FFC48A" stop-opacity="0.85" /><stop offset="100%" stop-color="#FFE6CC" stop-opacity="0.15" /></linearGradient>
                  <linearGradient id="bv-verde" x1="1" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3D8A22" /><stop offset="100%" stop-color="#6EC13F" /></linearGradient>
                  <linearGradient id="bv-turquesa" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#4CC7D6" /><stop offset="100%" stop-color="#19A7A8" /></linearGradient>
                  <linearGradient id="bv-cian" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#9BE7EF" stop-opacity="0.9" /><stop offset="100%" stop-color="#6FD6E3" stop-opacity="0.75" /></linearGradient>
                </defs>
                <!-- Izquierda: azul (debajo) en dos capas, se afila hacia la punta -->
                <path d="M0,0 L40,0 C52,46 70,82 96,118 C60,112 26,108 6,103 C0,70 0,30 0,0 Z" fill="url(#bv-azul-claro)" opacity="0.9" />
                <path d="M40,0 L76,0 C98,46 140,104 222,202 C160,168 120,140 96,118 C70,82 52,46 40,0 Z" fill="url(#bv-azul)" />
                <!-- Izquierda: naranja ENCIMA de la azul, en la línea de las demás cintas; por la izquierda
                     llega hasta la esquina inferior para que no asome la foto, con velo melocotón y brillo -->
                <path d="M240,192 C330,208 435,228 540,256 L560,300 L300,300 C285,262 265,222 240,192 Z" fill="url(#bv-melocoton)" />
                <path d="M0,72 C60,100 150,150 240,192 C275,212 305,238 332,262 L300,300 L0,300 Z" fill="url(#bv-naranja)" />
                <path d="M110,185 C165,220 230,258 300,300 L262,300 C200,265 150,232 110,185 Z" fill="#FFFFFF" opacity="0.35" />
                <!-- Derecha: verde desde arriba, franja clara + cuerpo oscuro, se afila hacia abajo a la izquierda -->
                <path d="M836,0 L870,0 C852,80 822,150 780,206 C800,140 822,70 836,0 Z" fill="#8BD45F" opacity="0.9" />
                <path d="M870,0 L1000,0 L1000,40 C958,100 880,170 780,206 C822,150 852,80 870,0 Z" fill="url(#bv-verde)" />
                <!-- Derecha: ola turquesa que sube desde abajo, con capa cian encima y ola oscura debajo -->
                <path d="M500,300 C620,252 760,200 900,112 C940,86 970,64 1000,46 L1000,68 C900,128 780,222 546,300 Z" fill="url(#bv-cian)" />
                <path d="M546,300 C700,242 800,190 900,128 C940,102 975,82 1000,68 L1000,300 Z" fill="url(#bv-turquesa)" />
                <path d="M600,300 C700,268 790,228 870,178" fill="none" stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="2" vector-effect="non-scaling-stroke" />
                <path d="M720,300 C830,270 925,222 1000,165 L1000,300 Z" fill="#138C9C" opacity="0.55" />
              </svg>
              <div class="absolute inset-x-0 bottom-0 flex h-1.5 @2xl:hidden"><span class="flex-1 bg-centros" /><span class="flex-1 bg-empresas" /><span class="flex-1 bg-administraciones" /><span class="flex-1 bg-alumnos" /></div>
            </div>
            <!-- Nota manuscrita, como en el ejemplo -->
            <div aria-hidden="true" class="pointer-events-none absolute right-6 top-4 z-10 hidden -rotate-6 @3xl:block">
              <p class="font-manuscrita text-2xl leading-6 text-white [text-shadow:0_1px_3px_rgba(23,40,62,0.75)]">Ideas de hoy<br />para el mundo<br />de mañana</p>
              <svg class="ml-10 mt-1 h-8 w-14 -scale-x-100 text-alumnos drop-shadow" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 56 32">
                <path d="M2,4 C10,22 30,28 50,20" /><path d="M42,14 L51,20 L42,26" />
              </svg>
            </div>
            <div class="relative z-10 p-5 @2xl:max-w-[46%]">
              <h1 class="font-heading text-2xl font-bold tracking-tight text-azul-noche sm:text-3xl">¡Hola, {{ primerNombre }}!</h1>
              <p class="mt-1 font-heading text-lg font-bold leading-snug text-azul-noche sm:text-xl">
                Seguimos conectando talento<br class="hidden sm:block" /> con <span class="text-centros">oportunidades reales</span>
              </p>
              <p class="mt-2 text-sm text-gray-600">
                Desde aquí puedes gestionar la Formación Dual<span v-if="authStore.userCentroNombre"> de {{ authStore.userCentroNombre }}</span>,
                impulsar la colaboración con empresas y seguir el progreso de tu alumnado.
              </p>
              <div class="mt-3 flex flex-wrap gap-2">
                <button @click="irA('/proyectos/crear')"
                        class="flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-centros px-4 text-sm font-semibold text-white shadow-md shadow-centros/25 hover:bg-centros/90">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                  Nuevo proyecto
                </button>
                <button @click="irA('/encuentros/crear')"
                        class="flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-centros/30 bg-white px-4 text-sm font-semibold text-centros shadow-sm hover:bg-centros/5">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                  Nuevo encuentro
                </button>
              </div>
            </div>
          </section>

          <!-- Resumen del curso (como "Resumen del centro" en idea_dashboard.png): icono a la izquierda,
               título arriba y cifra debajo; sin porcentajes. Incluye el selector de curso. -->
          <section class="card p-4" aria-labelledby="titulo-resumen">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
              <h2 id="titulo-resumen" class="card-title">Resumen del curso</h2>
              <label class="sr-only" for="curso-sel">Curso académico</label>
              <select id="curso-sel" v-model.number="cursoSel"
                      class="h-9 rounded-lg border border-gray-200 bg-white px-3 pr-8 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-centros/40">
                <option v-for="c in cursosDisponibles" :key="c" :value="c">{{ etiquetaCurso(c) }}</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3 @3xl/main:grid-cols-4">
              <button v-for="k in kpis" :key="k.key" @click="irA(k.ruta)"
                      class="@container min-w-0 rounded-xl p-2.5 text-left ring-1 ring-gray-200/70 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-center gap-3">
                  <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-sm @min-[12rem]:h-12 @min-[12rem]:w-12 @min-[12rem]:rounded-2xl" :class="k.tile">
                    <svg v-if="k.icon === 'alumnos'" class="h-6 w-6 @min-[12rem]:h-7 @min-[12rem]:w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    <svg v-else-if="k.icon === 'empresas'" class="h-6 w-6 @min-[12rem]:h-7 @min-[12rem]:w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M16 9h2a2 2 0 012 2v10M2 21h20M8 7h4M8 11h4M8 15h4"/></svg>
                    <svg v-else-if="k.icon === 'encuentro'" class="h-6 w-6 @min-[12rem]:h-7 @min-[12rem]:w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18M8 15h3"/></svg>
                    <svg v-else class="h-6 w-6 @min-[12rem]:h-7 @min-[12rem]:w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-medium leading-snug text-gray-600 @min-[12rem]:text-sm">{{ k.label }}</p>
                    <p class="mt-1 font-heading text-2xl font-bold leading-none @min-[12rem]:text-2xl" :class="cargando ? 'animate-pulse text-gray-300' : k.num">{{ cargando ? '—' : k.valor }}</p>
                  </div>
                </div>
              </button>
            </div>
          </section>

          <!-- Gráfica (más ancha) | donut -->
          <div class="grid grid-cols-1 gap-4 @3xl/main:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
            <article class="card @container flex h-full min-w-0 flex-col p-4">
              <div class="mb-3">
                <h2 class="card-title">Impacto acumulado del curso</h2>
                <p class="text-sm text-gray-500">{{ etiquetaCurso(cursoSel) }} · evolución de septiembre a junio</p>
              </div>

              <div v-if="cargando" class="h-[190px] animate-pulse rounded-xl bg-gray-100" />
              <div v-else-if="!hayImpacto" class="flex h-[190px] flex-col items-center justify-center rounded-xl bg-gray-50 px-4 text-center text-sm text-gray-500">
                Tu impacto empieza a contar con el primer encuentro del curso.
                <button class="mt-2 font-semibold text-centros" @click="irA('/encuentros/crear')">Registrar un encuentro →</button>
              </div>
              <template v-else>
                <div :ref="observarChart" class="relative w-full" @mouseleave="mesHoverImp = null">
                  <svg :width="CH.w" :height="CH.h" :viewBox="`0 0 ${CH.w} ${CH.h}`" class="block" role="img"
                       :aria-label="`Impacto acumulado del ${etiquetaCurso(cursoSel)}: ` + impacto.series.map(s => `${s.label} ${s.total}`).join(', ')">
                    <defs>
                      <linearGradient v-if="areaImpacto" id="grad-impacto" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" :stop-color="areaImpacto.color" stop-opacity="0.16" />
                        <stop offset="100%" :stop-color="areaImpacto.color" stop-opacity="0" />
                      </linearGradient>
                    </defs>
                    <g v-for="t in ticksImp" :key="t">
                      <line :x1="CH.l" :x2="CH.w - CH.r" :y1="iy(t)" :y2="iy(t)" stroke="#EEF0F3" stroke-width="1" />
                      <text :x="CH.l - 8" :y="iy(t) + 4" text-anchor="end" font-size="11" fill="#9CA3AF">{{ t }}</text>
                    </g>
                    <template v-for="(m, i) in impacto.meses" :key="m">
                      <text v-if="mostrarMesImp(i)" :x="ix(i)" :y="CH.h - 6" text-anchor="middle" font-size="11"
                            :fill="i < impacto.n ? '#6B7280' : '#C4C9D1'">{{ m }}</text>
                    </template>
                    <line v-if="mesHoverImp !== null" :x1="ix(mesHoverImp)" :x2="ix(mesHoverImp)" :y1="CH.t" :y2="iy(0)" stroke="#9CA3AF" stroke-dasharray="3 3" />
                    <path v-if="areaImpacto" :d="areaImpacto.d" fill="url(#grad-impacto)" />
                    <g v-for="c in curvasImpacto" :key="c.key">
                      <path :d="c.linea" fill="none" :stroke="c.color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                      <!-- Un punto por mes, como el PNG; crece el del mes señalado -->
                      <circle v-for="(pt, i) in c.pts" :key="i" :cx="pt[0]" :cy="pt[1]" :r="mesHoverImp === i ? 5 : 3.5"
                              :fill="c.color" stroke="white" stroke-width="1.5" />
                    </g>
                    <rect v-for="(m, i) in impacto.meses.slice(0, impacto.n)" :key="'hit-' + m" :x="ix(i) - pasoImp / 2" y="0"
                          :width="pasoImp" :height="CH.h" fill="transparent" @mouseenter="mesHoverImp = i" />
                  </svg>
                  <div v-if="mesHoverImp !== null"
                       class="pointer-events-none absolute top-1 z-10 w-[230px] -translate-x-1/2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs shadow-lg"
                       :style="{ left: `${tooltipLeftImp}px` }">
                    <p class="mb-1 font-semibold">Hasta {{ impacto.meses[mesHoverImp] }}</p>
                    <p v-for="c in seriesVisibles" :key="c.key" class="flex justify-between gap-3 text-gray-600">
                      <span class="flex min-w-0 items-center gap-1.5"><span class="h-2 w-2 shrink-0 rounded-full" :style="{ background: c.color }" /><span class="truncate">{{ c.label }}</span></span>
                      <strong class="shrink-0 text-azul-noche">{{ c.acum[mesHoverImp] }}</strong>
                    </p>
                  </div>
                </div>
                <!-- Leyenda como el PNG: bajo la gráfica y repartida a lo ancho; pulsar oculta/muestra la línea -->
                <ul class="mt-3 flex flex-wrap justify-between gap-x-4 gap-y-1 px-1 text-xs">
                  <li v-for="c in impacto.series" :key="c.key">
                    <button class="flex items-center gap-1.5 transition"
                            :class="seriesOcultas.includes(c.key) ? 'text-gray-400 line-through' : 'text-gray-600 hover:text-azul-noche'"
                            :aria-pressed="!seriesOcultas.includes(c.key)" :title="seriesOcultas.includes(c.key) ? 'Mostrar línea' : 'Ocultar línea'"
                            @click="toggleSerie(c.key)">
                      <span class="h-2.5 w-2.5 rounded-full" :style="{ background: seriesOcultas.includes(c.key) ? '#D1D5DB' : c.color }" />
                      {{ c.label }}
                    </button>
                  </li>
                </ul>
              </template>
            </article>

            <article class="card @container flex h-full min-w-0 flex-col p-4">
              <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="card-title">Estado de los proyectos</h2>
                <button class="link shrink-0" @click="irA('/proyectos')">Ver todos</button>
              </div>
              <div v-if="cargando" class="h-[200px] animate-pulse rounded-xl bg-gray-100" />
              <div v-else class="flex flex-1 flex-col items-center justify-center gap-4 @min-[17rem]:flex-row">
                <div class="relative h-36 w-36 shrink-0">
                  <svg viewBox="0 0 140 140" class="h-full w-full -rotate-90" role="img" :aria-label="`${donut.total} proyectos por estado`">
                    <circle cx="70" cy="70" :r="R" fill="none" stroke="#F3F4F6" stroke-width="18" />
                    <circle v-for="s in donut.segmentos.filter(x => x.valor)" :key="s.label" cx="70" cy="70" :r="R" fill="none"
                            :stroke="s.color" :stroke-width="donutHover === s.label ? 24 : 18"
                            :stroke-dasharray="`${s.dash} ${CIRC}`" :stroke-dashoffset="s.offset"
                            :opacity="donutHover && donutHover !== s.label ? 0.25 : 1"
                            class="cursor-pointer transition-all duration-200"
                            @mouseenter="donutHover = s.label" @mouseleave="donutHover = null" @click="irA(s.ruta)">
                      <title>{{ s.label }}: {{ s.valor }} ({{ s.pct }}%)</title>
                    </circle>
                  </svg>
                  <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="font-heading text-2xl font-bold">{{ donutHover ? donut.segmentos.find(x => x.label === donutHover).valor : donut.total }}</span>
                    <span class="max-w-[5.5rem] text-center text-[11px] leading-tight text-gray-500">{{ donutHover || 'proyectos' }}</span>
                  </div>
                </div>
                <!-- Leyenda a un lado (como el PNG) = recorrido del proyecto, en orden; cada paso abre la biblioteca filtrada -->
                <ol class="relative w-full min-w-0">
                  <span aria-hidden="true" class="absolute bottom-3 left-[11px] top-3 w-px bg-gray-200" />
                  <li v-for="s in donut.segmentos" :key="s.label">
                    <button class="relative flex w-full items-start gap-2.5 rounded-lg px-1.5 py-1 text-left transition"
                            :class="[!s.valor && 'opacity-50', donutHover === s.label ? 'bg-gray-100' : 'hover:bg-gray-50']"
                            @mouseenter="donutHover = s.label" @mouseleave="donutHover = null"
                            @focus="donutHover = s.label" @blur="donutHover = null" @click="irA(s.ruta)">
                      <span class="relative z-10 mt-1 h-2.5 w-2.5 shrink-0 rounded-full ring-4 ring-white transition-transform"
                            :class="donutHover === s.label && 'scale-150'" :style="{ background: s.color }" />
                      <span class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-medium text-gray-700">{{ s.label }}</span>
                        <span class="block truncate text-[11px] text-gray-500">
                          <strong class="text-azul-noche">{{ s.valor }}</strong> ({{ s.pct }}%)<template v-if="s.detalle"> · {{ s.detalle }}</template>
                        </span>
                      </span>
                    </button>
                  </li>
                </ol>
              </div>
              <button v-if="!cargando && !donut.total" class="mt-3 w-full rounded-lg bg-centros/5 px-3 py-2 text-sm font-semibold text-centros ring-1 ring-centros/15 hover:bg-centros/10"
                      @click="irA('/proyectos/crear')">
                Aún no hay proyectos en este curso · Crear propuesta →
              </button>
            </article>
          </div>

          <!-- Proyectos | herramientas -->
          <div class="grid flex-1 grid-cols-1 gap-4 @3xl/main:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
            <article class="card @container flex h-full min-w-0 flex-col p-4">
              <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="card-title">Proyectos</h2>
                <button class="link shrink-0" @click="verTodosProyectos">Ver todos</button>
              </div>

              <!-- Filtros de estado — los mismos que la biblioteca de proyectos -->
              <div class="-mx-1 mb-3 flex gap-1.5 overflow-x-auto px-1 pb-1" aria-label="Filtrar por estado">
                <button v-for="f in FILTROS_PROY" :key="f.key" :aria-pressed="filtroProy === f.key"
                        @click="filtroProy = f.key"
                        class="flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-3 py-1 text-xs font-semibold transition"
                        :class="filtroProy === f.key
                        ? 'border-centros bg-centros text-white'
                        : 'border-gray-200 bg-white text-gray-600 hover:border-centros/40 hover:text-centros'">
                  {{ f.label }}
                  <span class="rounded-full px-1.5 text-[10px]"
                        :class="filtroProy === f.key ? 'bg-white/20' : 'bg-gray-100 text-gray-500'">{{ conteoProy(f.key) }}</span>
                </button>
              </div>

              <!-- Lista con alto fijo (5 filas): no cambia el tamaño de la tarjeta según cuántos proyectos haya -->
              <div class="h-[324px]">
                <div v-if="cargando" class="space-y-px"><div v-for="n in 5" :key="n" class="my-2 h-12 animate-pulse rounded-lg bg-gray-100" /></div>
                <div v-else-if="!proyectosFiltrados.length" class="flex h-full flex-col items-center justify-center rounded-xl bg-gray-50 px-4 text-center text-sm text-gray-500">
                  {{ filtroProy === 'todos' ? 'Aún no hay proyectos en este curso.' : 'No hay proyectos con este estado en el curso seleccionado.' }}
                  <button class="mt-2 font-semibold text-centros" @click="irA('/proyectos/crear')">Crear propuesta →</button>
                </div>
                <ul v-else class="divide-y divide-gray-100">
                  <li v-for="p in proyectosPagina" :key="p.id" class="h-16">
                    <button class="flex h-full w-full items-center gap-3 rounded-lg text-left hover:bg-gray-50" @click="irA('/proyectos/' + p.uuid)">
                      <img v-if="p.imagen_portada_url" :src="p.imagen_portada_url" alt="" class="h-10 w-12 shrink-0 rounded-lg object-cover" />
                      <div v-else class="flex h-10 w-12 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-centros/15 to-administraciones/15 text-centros">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="ICONOS.capas"/></svg>
                      </div>
                      <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ p.titulo }}</p>
                        <p class="truncate text-xs text-gray-500">{{ [p.empresa_nombre, p.ciclo_nombre, p.curso].filter(Boolean).join(' · ') }}</p>
                        <!-- En tarjetas estrechas la etiqueta baja bajo el título para no taparlo -->
                        <span class="mt-0.5 inline-block rounded-full border px-2 py-px text-[10px] font-bold @md:hidden" :class="colorProyecto(p)">{{ etiquetaProyecto(p) }}</span>
                      </div>
                      <span class="hidden shrink-0 rounded-full border px-2.5 py-0.5 text-[11px] font-bold @md:inline-block" :class="colorProyecto(p)">{{ etiquetaProyecto(p) }}</span>
                    </button>
                  </li>
                </ul>
              </div>
              <!-- Pie fijo: paginación (si hay más de una página) + acceso a la biblioteca -->
              <div class="mt-auto flex h-10 items-center justify-between gap-3 border-t border-gray-100 pt-2 text-xs text-gray-500">
                <div class="flex items-center gap-2">
                  <template v-if="totalPaginasProy > 1">
                    <button class="cal-nav disabled:opacity-30" aria-label="Proyectos anteriores" :disabled="paginaProyActual === 0" @click="moverPaginaProy(-1)">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <span>{{ paginaProyActual + 1 }} / {{ totalPaginasProy }}</span>
                    <button class="cal-nav disabled:opacity-30" aria-label="Proyectos siguientes" :disabled="paginaProyActual === totalPaginasProy - 1" @click="moverPaginaProy(1)">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                  </template>
                  <span v-else-if="proyectosFiltrados.length">{{ proyectosFiltrados.length }} proyecto{{ proyectosFiltrados.length !== 1 ? 's' : '' }}</span>
                </div>
                <button class="font-semibold text-centros hover:underline" @click="verTodosProyectos">Ver en la biblioteca →</button>
              </div>

            </article>

            <!-- Herramientas: junto a Proyectos, botones tipo icono de app; la explicación sale al pasar el cursor -->
            <article class="relative flex h-full min-w-0 flex-col overflow-hidden rounded-2xl bg-gradient-to-br from-[#F6F9FD] to-white p-4 shadow-sm ring-1 ring-gray-200/70">
              <!-- Fondo casi blanco con un toque azul muy suave y una llave de marca de agua apenas visible;
                   barra superior con los colores del logo -->
              <svg aria-hidden="true" class="pointer-events-none absolute -bottom-4 -right-4 h-32 w-32 -rotate-12 text-centros/5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" /></svg>
              <span aria-hidden="true" class="absolute inset-x-0 top-0 flex h-1"><span class="flex-1 bg-centros" /><span class="flex-1 bg-empresas" /><span class="flex-1 bg-administraciones" /><span class="flex-1 bg-alumnos" /></span>
              <h2 class="card-title relative mb-3">Herramientas</h2>
              <div class="relative grid flex-1 auto-rows-fr grid-cols-2 gap-2">
                <template v-for="g in herramientas" :key="g.grupo">
                  <button v-for="h in g.items" :key="h.titulo" @click="irA(h.ruta)" :title="h.desc" :aria-label="`${h.titulo}: ${h.desc}`"
                          class="group flex min-w-0 flex-col items-center justify-center gap-1.5 rounded-xl bg-white p-2 text-center ring-1 ring-gray-200/70 transition hover:-translate-y-0.5 hover:shadow-md"
                          :class="g.items.length === 1 && 'col-span-2 !flex-row !gap-2.5'">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-sm transition group-hover:scale-105" :class="h.tile">
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="ICONOS[h.icon]"/></svg>
                    </span>
                    <span class="min-w-0 text-xs font-semibold leading-tight">{{ h.titulo }}</span>
                  </button>
                </template>
              </div>
            </article>
          </div>
        </div>

        <!-- ══ Columna lateral (compacta): encuentros + calendario, tareas, empresas, reto destacado ══ -->
        <div class="flex min-w-0 flex-col gap-4">
          <!-- Próximos encuentros (los 2 más cercanos) + calendario en la misma tarjeta, compacta -->
          <article class="card flex min-w-0 flex-col p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <h2 class="card-title">Próximos encuentros</h2>
              <button class="link shrink-0" @click="irA('/encuentros')">Ver todos</button>
            </div>

            <!-- Hueco fijo para 2 encuentros: la tarjeta no cambia de alto -->
            <div class="h-[88px]">
              <div v-if="cargando" class="space-y-1.5"><div v-for="n in 2" :key="n" class="h-[41px] animate-pulse rounded-lg bg-gray-100" /></div>
              <div v-else-if="!proximosEncuentros.length" class="flex h-full flex-col items-center justify-center text-center text-sm text-gray-500">
                No tienes encuentros programados.
                <button class="mt-1 font-semibold text-centros" @click="irA('/encuentros/crear')">Programar uno →</button>
              </div>
              <ul v-else class="space-y-1.5">
                <li v-for="e in proximosEncuentros" :key="e.id"
                    class="group flex h-[41px] items-stretch rounded-xl ring-1 transition"
                    :class="encDestacado(e) ? 'bg-centros/8 ring-centros/40 shadow-sm' : 'ring-transparent hover:bg-gray-50'"
                    @mouseenter="hoverEncId = e.id" @mouseleave="hoverEncId = null">
                  <button class="flex min-w-0 flex-1 items-center gap-2.5 px-1 text-left"
                          :aria-pressed="selEncId === e.id"
                          @click="seleccionarEncuentro(e)" @focus="hoverEncId = e.id" @blur="hoverEncId = null">
                    <span class="flex h-8 w-8 shrink-0 flex-col items-center justify-center rounded-lg ring-1 transition"
                          :class="encDestacado(e) ? 'bg-centros text-white ring-centros' : 'bg-centros/8 ring-centros/15'">
                      <span class="font-heading text-sm font-bold leading-none">{{ fechaTile(e.fecha).dia }}</span>
                      <span class="text-[9px] font-semibold leading-tight" :class="encDestacado(e) ? 'text-white/80' : 'text-gray-500'">{{ fechaTile(e.fecha).mes }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="block truncate text-[13px] font-semibold leading-tight">{{ tituloEnc(e) }}</span>
                      <span class="block truncate text-[11px] leading-tight text-gray-500">{{ [e.ciclo_formativo, e.curso, e.grupo].filter(Boolean).join(' · ') }}</span>
                    </span>
                  </button>
                  <button class="flex w-9 shrink-0 items-center justify-center rounded-r-xl text-gray-400 hover:text-centros"
                          :aria-label="`Abrir ${tituloEnc(e)}`" @click="irA('/mis-equipos/' + e.id)">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                  </button>
                </li>
              </ul>
            </div>



            <!-- Mini calendario (misma tarjeta, bajo los encuentros) -->
            <div class="mt-3 border-t border-gray-100 pt-2">
              <div class="mb-1 flex items-center justify-between">
                <button class="cal-nav" aria-label="Mes anterior" @click="moverMes(-1)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <p class="text-sm font-semibold capitalize">{{ calLabel }}</p>
                <button class="cal-nav" aria-label="Mes siguiente" @click="moverMes(1)">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
              </div>
              <div class="grid grid-cols-7 text-center text-[10px] font-semibold uppercase text-gray-400">
                <span v-for="d in ['L', 'M', 'X', 'J', 'V', 'S', 'D']" :key="d" class="py-0.5">{{ d }}</span>
              </div>
              <div class="grid grid-cols-7">
                <template v-for="(c, i) in calDias" :key="i">
                  <span v-if="c.otroMes" class="flex h-5 items-center justify-center text-[11px] text-gray-300">{{ c.d }}</span>
                  <button v-else
                          class="relative mx-auto flex h-5 w-5 items-center justify-center rounded-full text-[10px] transition"
                          :class="diaIluminado(c)
                          ? 'bg-centros font-bold text-white shadow-md shadow-centros/30'
                          : c.hoy
                          ? 'bg-alumnos font-bold text-white'
                          : c.encs.length
                          ? 'font-bold text-centros hover:bg-centros/10'
                          : 'cursor-default text-gray-600'"
                          :disabled="!c.encs.length"
                          :aria-label="c.encs.length ? `${c.d}: ${c.encs.length} encuentro${c.encs.length > 1 ? 's' : ''}` : String(c.d)"
                          @click="clicDia(c)">
                    {{ c.d }}
                  </button>
                </template>
              </div>
              <!-- Hueco fijo: muestra la leyenda o, si se pulsa un día, su detalle (no cambia el alto) -->
              <div class="mt-1 h-8">
                <div v-if="selDia" class="flex h-full items-center gap-2 rounded-lg bg-centros/5 px-2 text-xs">
                  <span class="shrink-0 font-semibold capitalize text-centros">{{ fechaCorta(selDia) }}</span>
                  <button class="min-w-0 flex-1 truncate text-left hover:text-centros" @click="irA('/mis-equipos/' + encuentrosSelDia[0].id)">
                    {{ tituloEnc(encuentrosSelDia[0]) }}<template v-if="encuentrosSelDia.length > 1"> (+{{ encuentrosSelDia.length - 1 }})</template> →
                  </button>
                  <button class="shrink-0 text-gray-400 hover:text-gray-600" aria-label="Cerrar detalle del día" @click="selDia = null">×</button>
                </div>
                <div v-else class="flex h-full flex-wrap items-center justify-center gap-x-4 text-[11px] text-gray-500">
                  <span class="flex items-center gap-1.5"><span class="text-[11px] font-bold leading-none text-centros">14</span>Encuentro</span>
                  <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-alumnos" />Hoy</span>
                  <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-white ring-1 ring-gray-300" />Libre</span>
                </div>
              </div>

            </div>
          </article>

          <article class="card flex min-w-0 flex-col p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
              <h2 class="card-title text-base">Tareas pendientes</h2>
              <div class="flex items-center gap-1">
                <span v-if="pendientes" class="rounded-full bg-alumnos/15 px-2 py-0.5 text-xs font-bold text-alumnos-dark">{{ pendientes }}</span>
                <!-- Aviso de que hay más tareas en las páginas siguientes -->
                <button v-if="paginaTareasActual < totalPaginasTareas - 1"
                        class="flex h-6 w-6 items-center justify-center rounded-full text-alumnos-dark hover:bg-alumnos/10"
                        :aria-label="`Hay más tareas: ir a la página ${paginaTareasActual + 2}`" title="Hay más tareas en la página siguiente"
                        @click="moverPaginaTareas(1)">
                  <svg class="h-4 w-4 animate-pulse" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
              </div>
            </div>
            <!-- Lista con alto fijo (3 filas) -->
            <div class="h-[136px]">
              <ul v-if="listaTareas.length" class="space-y-0.5">
                <li v-for="item in tareasPagina" :key="item.key" class="group flex h-11 items-center gap-2.5 rounded-lg px-1.5 text-sm hover:bg-gray-50">
                  <template v-if="item.tipo === 'auto'">
                    <button role="switch" :aria-checked="item.hecha" :aria-label="`Marcar como hecha: ${item.t.texto}`"
                            class="interruptor !mt-0" :class="item.hecha ? 'bg-empresas' : (item.t.nivel === 'alta' ? 'bg-alumnos/30' : 'bg-gray-200')"
                            @click="toggleAuto(item.t)">
                      <span class="interruptor-bola" :class="item.hecha && 'translate-x-3.5'" />
                    </button>
                    <button class="line-clamp-2 min-w-0 flex-1 text-left leading-snug" :class="item.hecha ? 'text-gray-400 line-through' : 'text-gray-700 hover:text-centros'" @click="irA(item.t.ruta)">
                      {{ item.t.texto }}
                    </button>
                  </template>
                  <template v-else>
                    <button role="switch" :aria-checked="item.hecha" :aria-label="`Marcar como hecha: ${item.n.text}`"
                            class="interruptor !mt-0" :class="item.hecha ? 'bg-empresas' : 'bg-gray-200'" @click="toggleNota(item.n.id)">
                      <span class="interruptor-bola" :class="item.hecha && 'translate-x-3.5'" />
                    </button>
                    <span class="line-clamp-2 min-w-0 flex-1 break-words leading-snug" :class="item.hecha ? 'text-gray-400 line-through' : 'text-gray-700'">{{ item.n.text }}</span>
                    <button class="shrink-0 text-gray-300 opacity-0 transition hover:text-red-500 group-hover:opacity-100 focus:opacity-100"
                            :aria-label="`Borrar tarea: ${item.n.text}`" @click="borrarNota(item.n.id)">×</button>
                  </template>
                </li>
              </ul>
              <p v-else class="flex h-full items-center justify-center text-sm text-gray-500">{{ cargando ? 'Cargando…' : 'Todo al día 🎉' }}</p>
            </div>
            <!-- Pie fijo: paginación siempre en su sitio -->
            <div class="mt-1 flex h-7 items-center justify-center gap-3 text-xs text-gray-500">
              <button class="cal-nav disabled:opacity-30" aria-label="Tareas anteriores" :disabled="paginaTareasActual === 0" @click="moverPaginaTareas(-1)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <span>{{ paginaTareasActual + 1 }} / {{ totalPaginasTareas }}</span>
              <button class="cal-nav disabled:opacity-30" aria-label="Tareas siguientes" :disabled="paginaTareasActual === totalPaginasTareas - 1" @click="moverPaginaTareas(1)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
              </button>
            </div>
            <form class="mt-2 flex gap-2" @submit.prevent="addNota">
              <input v-model="nuevaNota" maxlength="200" placeholder="Añadir tarea personal…"
                     class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-centros/30" />
              <button class="shrink-0 rounded-lg bg-centros/10 px-3 text-sm font-semibold text-centros hover:bg-centros/15" :disabled="!nuevaNota.trim()">Añadir</button>
            </form>
          </article>

          <article class="card flex min-w-0 flex-col p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <h2 class="card-title text-base">Empresas colaboradoras</h2>
              <button class="link shrink-0" @click="irA('/empresas')">Ver todas</button>
            </div>
            <div v-if="cargando" class="space-y-3"><div v-for="n in 3" :key="n" class="h-12 animate-pulse rounded-lg bg-gray-100" /></div>
            <p v-else-if="!empresasTop.length" class="mb-3 py-4 text-center text-sm text-gray-500">Ninguna empresa vinculada a proyectos este curso.</p>
            <ul v-else class="mb-3 divide-y divide-gray-100">
              <li v-for="(e, i) in empresasTop" :key="e.id" class="flex items-center gap-3 py-2">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg font-heading text-xs font-bold text-white" :class="AVATAR_COLORES[i % 4]">
                  {{ iniciales(e.nombre) }}
                </span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold">{{ e.nombre }}</p>
                  <p v-if="e.familia" class="truncate text-xs text-gray-500">{{ e.familia }}</p>
                </div>
                <span class="shrink-0 text-right text-xs leading-snug text-gray-500">
                  {{ e.proyectos }} proyecto{{ e.proyectos > 1 ? 's' : '' }}<br />{{ e.validados }} validado{{ e.validados !== 1 ? 's' : '' }}
                </span>
              </li>
            </ul>
            <button class="flex items-center justify-between gap-2 rounded-xl bg-empresas/5 p-2.5 text-left text-sm ring-1 ring-empresas/15 transition hover:bg-empresas/10"
                    @click="irA('/empresas')">
              <span class="min-w-0"><span class="block font-semibold">Catálogo de empresas</span><span class="block text-xs text-gray-500">Encuentra empresas para tus próximos retos</span></span>
              <span class="shrink-0 font-semibold text-empresas-dark">Ir →</span>
            </button>
          </article>

          <!-- Reto destacado de la semana: tarjeta cuadrada bajo Empresas colaboradoras (ocupa el alto restante) -->
          <article v-if="retoDestacado" class="relative flex min-w-0 flex-1 flex-col overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF1E2] via-[#FFF8F1] to-white p-4 shadow-sm ring-1 ring-alumnos/30">
            <!-- Destacado con el color de retos (naranja) en lugar de la barra del logo: fondo cálido,
                 borde naranja, pastilla y un rayo grande de marca de agua -->
            <svg aria-hidden="true" class="pointer-events-none absolute -right-5 -top-3 h-28 w-28 rotate-12 text-alumnos/10" fill="currentColor" viewBox="0 0 24 24"><path :d="ICONOS.chispa" /></svg>
            <div class="relative flex items-center gap-2.5">
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-alumnos text-white shadow-md shadow-alumnos/30">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="ICONOS.chispa"/></svg>
              </span>
              <span class="inline-flex items-center rounded-full bg-alumnos px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white">
                Reto de la semana
              </span>
            </div>
            <h3 class="relative mt-3 line-clamp-2 font-heading text-base font-bold leading-snug">{{ retoDestacado.titulo }}</h3>
            <p v-if="typeof retoDestacado.pregunta_reto === 'string'" class="mt-1 line-clamp-2 text-xs text-gray-600">{{ retoDestacado.pregunta_reto }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px] text-gray-500">
              <span v-if="retoDestacado.empresa_nombre" class="max-w-full truncate">{{ retoDestacado.empresa_nombre }}</span>
              <span v-if="retoDestacado.familia" class="rounded-full bg-gray-100 px-2 py-0.5">{{ retoDestacado.familia }}</span>
              <span v-if="retoDestacado.nivel_grupo" class="rounded-full border px-2 py-0.5 font-bold" :class="nivelClase(retoDestacado.nivel_grupo)">{{ retoDestacado.nivel_grupo }}</span>
            </div>
            <div class="mt-auto grid grid-cols-2 gap-2 pt-3">
              <button class="rounded-lg bg-alumnos px-3 py-2 text-xs font-semibold text-white hover:bg-alumnos/90" @click="abrirReto(retoDestacado)">Ver reto</button>
              <button class="rounded-lg px-3 py-2 text-xs font-semibold text-alumnos-dark ring-1 ring-alumnos/30 hover:bg-alumnos/5" @click="irA('/retos')">Más retos</button>
            </div>
          </article>
        </div>

        <!-- ══ Fila final (todo el ancho): banner estirado | recursos (alineado con la columna lateral) ══ -->
        <div class="@container/main grid min-w-0 grid-cols-1 gap-4 @5xl/page:col-span-2 @5xl/page:grid-cols-[minmax(0,1fr)_300px]">
          <!-- Banner pequeño de detalle (como "Toda la gestión… en un mismo lugar" en idea_dashboard.png):
               foto pegada al borde izquierdo que se funde hacia la derecha; texto y botón a su derecha -->
          <section class="relative flex h-full min-h-[120px] min-w-0 items-stretch overflow-hidden rounded-2xl bg-gradient-to-r from-white to-[#F3F8FD] shadow-sm ring-1 ring-gray-200/70">
            <!-- La foto se funde con el fondo hacia la derecha (máscara en degradado) -->
            <div class="relative w-[42%] shrink-0 [mask-image:linear-gradient(to_right,black_45%,transparent)] [-webkit-mask-image:linear-gradient(to_right,black_45%,transparent)]">
              <img :src="banner.imagen" :alt="banner.alt" class="absolute inset-0 h-full w-full object-cover object-[50%_60%]" />
              <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 1000 300" preserveAspectRatio="none">
                <!-- Copia de los halos del banner de "Aplicación moodboard a dashboard.png":
                     izquierda = cinta azul que cae y se afila + cinta naranja encima que cubre la esquina inferior;
                     derecha = cinta verde que baja desde arriba + gran ola turquesa que sube hasta la esquina -->
                <defs>
                  <linearGradient id="bn-azul" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#2F7FD8" /><stop offset="100%" stop-color="#1E5FB8" /></linearGradient>
                  <linearGradient id="bn-azul-claro" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#9BD3F7" /><stop offset="100%" stop-color="#4FA3E8" /></linearGradient>
                  <linearGradient id="bn-naranja" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#FFB45C" /><stop offset="45%" stop-color="#FF8920" /><stop offset="100%" stop-color="#F57A0E" /></linearGradient>
                  <linearGradient id="bn-melocoton" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#FFC48A" stop-opacity="0.85" /><stop offset="100%" stop-color="#FFE6CC" stop-opacity="0.15" /></linearGradient>
                  <linearGradient id="bn-verde" x1="1" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3D8A22" /><stop offset="100%" stop-color="#6EC13F" /></linearGradient>
                  <linearGradient id="bn-turquesa" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#4CC7D6" /><stop offset="100%" stop-color="#19A7A8" /></linearGradient>
                  <linearGradient id="bn-cian" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#9BE7EF" stop-opacity="0.9" /><stop offset="100%" stop-color="#6FD6E3" stop-opacity="0.75" /></linearGradient>
                </defs>
                <!-- Izquierda: azul (debajo) en dos capas, se afila hacia la punta -->
                <path d="M0,0 L40,0 C52,46 70,82 96,118 C60,112 26,108 6,103 C0,70 0,30 0,0 Z" fill="url(#bn-azul-claro)" opacity="0.9" />
                <path d="M40,0 L76,0 C98,46 140,104 222,202 C160,168 120,140 96,118 C70,82 52,46 40,0 Z" fill="url(#bn-azul)" />
                <!-- Izquierda: naranja ENCIMA de la azul, en la línea de las demás cintas; por la izquierda
                     llega hasta la esquina inferior para que no asome la foto, con velo melocotón y brillo -->
                <path d="M240,192 C330,208 435,228 540,256 L560,300 L300,300 C285,262 265,222 240,192 Z" fill="url(#bn-melocoton)" />
                <path d="M0,72 C60,100 150,150 240,192 C275,212 305,238 332,262 L300,300 L0,300 Z" fill="url(#bn-naranja)" />
                <path d="M110,185 C165,220 230,258 300,300 L262,300 C200,265 150,232 110,185 Z" fill="#FFFFFF" opacity="0.35" />
                <!-- Derecha: verde desde arriba, franja clara + cuerpo oscuro, se afila hacia abajo a la izquierda -->
                <path d="M836,0 L870,0 C852,80 822,150 780,206 C800,140 822,70 836,0 Z" fill="#8BD45F" opacity="0.9" />
                <path d="M870,0 L1000,0 L1000,40 C958,100 880,170 780,206 C822,150 852,80 870,0 Z" fill="url(#bn-verde)" />
                <!-- Derecha: ola turquesa que sube desde abajo, con capa cian encima y ola oscura debajo -->
                <path d="M500,300 C620,252 760,200 900,112 C940,86 970,64 1000,46 L1000,68 C900,128 780,222 546,300 Z" fill="url(#bn-cian)" />
                <path d="M546,300 C700,242 800,190 900,128 C940,102 975,82 1000,68 L1000,300 Z" fill="url(#bn-turquesa)" />
                <path d="M600,300 C700,268 790,228 870,178" fill="none" stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="2" vector-effect="non-scaling-stroke" />
                <path d="M720,300 C830,270 925,222 1000,165 L1000,300 Z" fill="#138C9C" opacity="0.55" />
              </svg>
            </div>
            <div class="-ml-10 flex min-w-0 flex-1 flex-col justify-center py-3 pr-4">
              <p class="font-heading text-base font-bold leading-snug text-azul-noche">
                Formación, talento, empresa e innovación <span class="text-centros">conectados</span>
              </p>
              <p class="mt-1 line-clamp-2 text-sm text-gray-600">DuaLab impulsa la FP dual con retos reales y colaboración entre todos los actores.</p>
              <button class="mt-2 self-start rounded-full bg-white px-3 py-1 text-xs font-semibold text-centros ring-1 ring-centros/40 hover:bg-centros/5" @click="irA('/noticias/dualab')">
                Novedades de DuaLab →
              </button>
            </div>
          </section>

          <article class="card h-full min-w-0 p-3">
            <h2 class="card-title mb-2 text-base">Recursos destacados</h2>
            <ul class="space-y-1">
              <li>
                <button class="recurso !p-1.5" @click="mostrarCatalogoBoe = true">
                  <span class="recurso-icon !h-8 !w-8 bg-indigo-50 text-indigo-600">BOE</span>
                  <span class="min-w-0"><span class="block font-semibold">Ciclos BOE</span><span class="text-xs text-gray-500">Familias · Módulos · RA · CE</span></span>
                </button>
              </li>
              <li>
                <button class="recurso !p-1.5" @click="irA('/proyectos/terminados')">
                  <span class="recurso-icon !h-8 !w-8 bg-sky-50 text-sky-700">✓</span>
                  <span class="min-w-0"><span class="block font-semibold">Proyectos completados</span><span class="text-xs text-gray-500">Inspiración de otros equipos</span></span>
                </button>
              </li>
            </ul>
          </article>
        </div>
      </div>
    </div>
  </div>

  <CatalogoBoeModal v-model:show="mostrarCatalogoBoe" />
</template>

<style>
/* Fuente manuscrita para la nota "Ideas de hoy…" de la bienvenida (como idea_dashboard.png) */
@import url('https://fonts.googleapis.com/css2?family=Caveat:wght@600&display=swap');
</style>

<style scoped>
@reference "../style.css";

.card         { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
.card-title   { @apply font-heading text-lg font-bold text-azul-noche; }
.link         { @apply text-sm font-semibold text-centros hover:underline; }
.recurso      { @apply flex w-full items-center gap-3 rounded-lg p-2 text-left text-sm hover:bg-gray-50; }
.recurso-icon { @apply flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-xs font-bold; }
.interruptor  { @apply relative mt-0.5 inline-flex h-4 w-7.5 shrink-0 items-center rounded-full p-0.5 transition-colors; }
.interruptor-bola { @apply h-3 w-3 rounded-full bg-white shadow transition-transform; }
.font-manuscrita { font-family: 'Caveat', 'Segoe Script', cursive; font-weight: 600; }
.cal-nav      { @apply flex h-7 w-7 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-centros; }
</style>
