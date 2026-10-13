<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import MigasNavegacion from '../components/MigasNavegacion.vue'
import { migasFamilia } from '../utils/migasFamilia.js'
import { modulosDeProyecto } from '../utils/modulos.js'
import { useRouter, useRoute, onBeforeRouteUpdate } from 'vue-router';
import api from '../api.js';
import BienvenidaStartupDayModal from '../components/BienvenidaStartupDayModal.vue';
import EliminarProyectoModal from '../components/EliminarProyectoModal.vue';
import ProyectoCard from '../components/ProyectoCard.vue';
import RecordatorioPropuestaFlotante from '../components/RecordatorioPropuestaFlotante.vue';
import { useUIState } from '../composables/useUIState.js';
import { useAuthStore } from '../stores/auth.js';
import { useCursoAcademicoStore, cursoDeProyecto } from '../stores/cursoAcademico.js';
import SelectorCurso from '../components/SelectorCurso.vue';
import { useRoleTheme } from '../composables/useRoleTheme.js';
import { useFiltrosProyectos, SIN_FAMILIA } from '../composables/useFiltrosProyectos.js';
import FamiliaProyectosCard from '../components/FamiliaProyectosCard.vue';
import FiltrosPanel from '../components/FiltrosPanel.vue';
import FiltroSegmentado from '../components/FiltroSegmentado.vue';
import FiltroSelect from '../components/FiltroSelect.vue';

const router = useRouter();
const route  = useRoute();
const { tourActivo } = useUIState();
const authStore = useAuthStore();
const { theme } = useRoleTheme();

// ── Datos ───────────────────────────────────────────────────────────────────
const proyectos    = ref([]);
const cargando     = ref(true);
const filtroEstado = ref('todos');
const isLoaded     = ref(false);

// ── Modal bienvenida ────────────────────────────────────────────────────────
const guiaBienvenida = ref(false);

// ── Tour guiado ─────────────────────────────────────────────────────────────
const modoGuia = ref(false);
const pasoGuia = ref(1);

const refBusqueda = ref(null);
const refFiltros  = ref(null);
const refGrid     = ref(null);
const refBtnNuevo = ref(null);
const refBtnGuia  = ref(null);

const tourRefs = { refBusqueda, refFiltros, refGrid, refBtnNuevo, refBtnGuia };

const guiaPasosDataBase = [
  { ref: 'refBusqueda', seccion: 'busqueda',  texto: 'Usa el buscador para encontrar propuestas y proyectos por título, empresa o centro educativo. La búsqueda filtra en tiempo real a medida que escribes.' },
  { ref: 'refFiltros',  seccion: 'filtros',   texto: 'Filtra por estado: Validados (proyectos aprobados por empresa), Pendiente validar (propuestas enviadas, esperando respuesta), En edición (borradores), Archivados o Todos. Puedes combinar filtro y buscador a la vez.' },
  { ref: 'refGrid',     seccion: 'grid',      texto: 'Aquí aparecen las propuestas y proyectos registrados. Cada tarjeta muestra título, empresa, ciclo y estado. Pulsa en una tarjeta para ver el detalle completo.' },
  { ref: 'refBtnNuevo', seccion: 'btn-nuevo', texto: 'Pulsa aquí para crear una nueva propuesta StartUp Day. Necesitarás haber registrado previamente un encuentro en el Dashboard Docente para poder vincularla al reto correspondiente.' },
  { ref: 'refBtnGuia',  seccion: null,        texto: 'Pulsa «Ayuda» en cualquier momento para ver qué puedes hacer aquí o volver a esta guía.' },
];

const guiaPasosData = authStore.isEmpresa
  ? guiaPasosDataBase.filter(p => p.ref !== 'refBtnNuevo')
  : guiaPasosDataBase;

const pasoActual    = computed(() => guiaPasosData[pasoGuia.value - 1]);
const seccionActiva = computed(() => modoGuia.value ? (pasoActual.value?.seccion ?? null) : null);
const pasoRefActivo = computed(() => modoGuia.value ? (pasoActual.value?.ref ?? null) : null);

const bocadilloPos = ref({ top: 60, left: 16, width: 300, dir: 'top', arrowLeft: 150 });

function recalcularBocadillo() {
  const el = tourRefs[pasoActual.value?.ref]?.value;
  if (!el) return;
  const rect      = el.getBoundingClientRect();
  const WIN_W     = window.innerWidth;
  const WIN_H     = window.innerHeight;
  const TOOLTIP_W = Math.min(300, WIN_W - 32);
  const TOOLTIP_H = 150;
  const GAP       = 12;

  const visibleTop    = Math.max(0, rect.top);
  const visibleBottom = Math.min(WIN_H, rect.bottom);
  const centerX       = rect.left + rect.width / 2;

  const spaceBelow = WIN_H - visibleBottom - GAP;
  const spaceAbove = visibleTop - GAP;
  const dir = spaceBelow >= TOOLTIP_H + GAP ? 'top' : spaceAbove >= TOOLTIP_H + GAP ? 'bottom' : 'top';

  let tooltipTop = dir === 'top' ? visibleBottom + GAP : visibleTop - TOOLTIP_H - GAP;
  tooltipTop = Math.max(10, Math.min(tooltipTop, WIN_H - TOOLTIP_H - 10));

  let tooltipLeft = centerX - TOOLTIP_W / 2;
  tooltipLeft = Math.max(16, Math.min(tooltipLeft, WIN_W - TOOLTIP_W - 16));

  const arrowLeft = Math.max(16, Math.min(centerX - tooltipLeft, TOOLTIP_W - 16));

  bocadilloPos.value = { top: tooltipTop, left: tooltipLeft, width: TOOLTIP_W, dir, arrowLeft };
}

function scrollYRecalcular() {
  const el = tourRefs[pasoActual.value?.ref]?.value;
  if (el) el.scrollIntoView({ behavior: 'instant', block: 'nearest' });
  requestAnimationFrame(() => requestAnimationFrame(recalcularBocadillo));
}

function onScrollGuia() {
  if (modoGuia.value) requestAnimationFrame(recalcularBocadillo);
}

watch(pasoGuia, () => { if (modoGuia.value) nextTick(scrollYRecalcular); });
watch(modoGuia, (val) => {
  tourActivo.value = val;
  if (val) {
    window.addEventListener('scroll', onScrollGuia, { passive: true });
    nextTick(scrollYRecalcular);
  } else {
    window.removeEventListener('scroll', onScrollGuia);
  }
});

function avanzarPaso() {
  if (pasoGuia.value < guiaPasosData.length) {
    pasoGuia.value++;
  } else {
    modoGuia.value = false;
    pasoGuia.value = 1;
  }
}
function retrocederPaso() { if (pasoGuia.value > 1) pasoGuia.value--; }
function cerrarGuia() { modoGuia.value = false; pasoGuia.value = 1; }

function seleccionarOpcionBienvenida(opcion) {
  guiaBienvenida.value = false;
  if (opcion === 'crear') {
    router.push({ name: 'startup-day-crear' });
  } else if (opcion === 'guia') {
    // La guía señala búsqueda/filtros de estado, que solo existen en la capa de detalle.
    abrirGuiaEnDetalle();
  }
  // 'trabajar' → se queda en la vista
}

onMounted(async () => {
  setTimeout(() => { isLoaded.value = true; }, 80);
  if (route.query.filtro === 'completado') {
    router.replace({ name: 'proyectos-terminados' });
  } else if (route.query.filtro) {
    // Enlaces antiguos ?filtro=<estado>: detalle de todas las familias con ese estado
    router.replace({ name: 'startup-day', query: { capa: 'detalle', estado: String(route.query.filtro) } });
  } else if (route.query.familia && !route.query.capa) {
    // Enlaces antiguos ?familia=<nombre> (sin capa): preview de estados de esa familia
    router.replace({ name: 'startup-day', query: { capa: 'estados', familia: String(route.query.familia) } });
  }
  try {
    const res = await api.get('/startup/proyectos');
    proyectos.value = res.data;
  } finally {
    cargando.value = false;
  }
  await nextTick();
  // Auto-disparo desactivado — reactivar poniendo guiaBienvenida.value = true si se necesita de nuevo.
});

onUnmounted(() => {
  tourActivo.value = false;
  window.removeEventListener('scroll', onScrollGuia);
});

onBeforeRouteUpdate(async () => {
  modoGuia.value = false;
  pasoGuia.value = 1;
  await nextTick();
});

// Los proyectos "completado" tienen su propia vista (Proyectos Completados) — aquí no se listan.
// Curso académico compartido (store): todo lo que se lista y cuenta sale de esta lista.
// Las altas/bajas siguen trabajando sobre `proyectos` (la lista completa).
const cursoStore     = useCursoAcademicoStore();
const proyectosCurso = computed(() => proyectos.value.filter(p => cursoStore.coincide(cursoDeProyecto(p))));
const cursosConDatos = computed(() => [...new Set(proyectos.value.map(cursoDeProyecto))]);

const proyectosVisibles = computed(() => proyectosCurso.value.filter(p => p.estado !== 'completado'));
const totalCompletados   = computed(() => proyectosCurso.value.filter(p => p.estado === 'completado').length);

// Filtros por familia/ciclo/curso — mismo patrón que la Biblioteca de Retos.
// Base = TODOS los proyectos (incluidos completados): las cards de familia y
// sus ciclos/cursos disponibles no deben depender de si hay o no trabajo en curso.
const {
  busqueda, filtroFamilia, filtroCiclo, filtroModulo, filtroCurso,
  familiasDisponibles, countSinFamilia, ciclosDisponibles, modulosDisponibles, cursosDisponibles,
  coincideFamilia,
  volverAFamilias, limpiarFiltrosDetalle, hayFiltrosDetalleActivos, aplicarFiltros,
} = useFiltrosProyectos(proyectosCurso);

// 'familias' (capa 1) → 'estados' (preview: al pulsar "En proceso" se elige a dónde ir —
// validados, pendientes…) → 'detalle' (una familia, o "todos" en proceso vía deep-link,
// con chips de estado ya marcados — excluye completados) → 'todos' (todo mezclado sin
// filtrar por estado, entrada directa desde la card "Todos los proyectos").
//
// Las capas viven en la URL (?capa=estados|detalle|todos&familia=…&estado=…): cada paso
// queda en el historial, así la flecha "volver" de la cabecera y el atrás del navegador
// suben de capa en vez de salir de la vista, y cada capa se puede enlazar.
const CAPAS = ['estados', 'detalle', 'todos'];
const vista = computed(() => (CAPAS.includes(route.query.capa) ? route.query.capa : 'familias'));

function queryCapa(capa, { familia = '', estado = '' } = {}) {
  const query = { capa };
  if (familia) query.familia = familia;
  if (estado) query.estado = estado;
  return { name: 'startup-day', query };
}
function irAEstados(nombreOSentinela) {
  router.push(queryCapa('estados', { familia: nombreOSentinela }));
}
function irACompletadosFamilia(nombreOSentinela) {
  router.push({ name: 'proyectos-terminados', query: nombreOSentinela ? { familia: nombreOSentinela } : {} });
}
function irATodosSinFiltrar() {
  router.push(queryCapa('todos'));
}
// La guía se abre en la capa de detalle. onBeforeRouteUpdate la cierra al cambiar de
// capa, así que se activa cuando la navegación ya ha terminado.
async function abrirGuiaEnDetalle() {
  if (vista.value !== 'detalle') await router.push(queryCapa('detalle', { familia: filtroFamilia.value }));
  modoGuia.value = true;
  pasoGuia.value = 1;
}

// URL → estado de la capa (familia y estado elegidos)
watch(() => [vista.value, route.query.familia, route.query.estado], ([capa, familia, estado]) => {
  if (capa === 'familias' || capa === 'todos') {
    if (filtroFamilia.value) volverAFamilias();
    return;
  }
  const fam = familia ? String(familia) : '';
  if (fam !== filtroFamilia.value) {
    filtroFamilia.value = fam;
    filtroCiclo.value = '';
    filtroCurso.value = '';
  }
  if (capa === 'detalle') filtroEstado.value = estado ? String(estado) : 'todos';
}, { immediate: true });

// Los chips de estado del detalle filtran sin crear un paso nuevo: se refleja en la URL con
// replace para que, al volver desde un proyecto, el detalle conserve el estado marcado.
watch(filtroEstado, (estado) => {
  if (vista.value !== 'detalle' || (route.query.estado || 'todos') === estado) return;
  router.replace(queryCapa('detalle', { familia: filtroFamilia.value, estado: estado === 'todos' ? '' : estado }));
});

// Flecha "volver" de la cabecera cuando no hay vista anterior (enlace directo): la capa de arriba
const volverCabecera = computed(() => {
  if (vista.value === 'familias') return authStore.isEmpresa ? null : '/seccion/retos-proyectos';
  if (vista.value === 'detalle' && filtroFamilia.value) return queryCapa('estados', { familia: filtroFamilia.value });
  return { name: 'startup-day' };
});

// Migas "dónde estás" de cada capa — comunes a las vistas por familia:
// Familias › <familia> › <ciclo> › <módulo> › <estado>
const RUTA_FAMILIAS = { name: 'startup-day' };
const migas = computed(() => {
  const raiz = { label: 'Familias', to: RUTA_FAMILIAS };
  if (vista.value === 'estados') return migasFamilia({ raiz, familia: { label: nombreFamiliaActual.value } });
  if (vista.value === 'todos') {
    return migasFamilia({
      raiz,
      familia: { label: 'Todos los proyectos', accion: () => { cicloTodos.value = ''; } },
      ciclo:   filtroCicloTodos.value ? { label: filtroCicloTodos.value } : null,
    });
  }
  return migasFamilia({
    raiz,
    familia: filtroFamilia.value ? { label: nombreFamiliaActual.value, to: queryCapa('estados', { familia: filtroFamilia.value }) } : null,
    // Pulsar el ciclo quita el módulo y el estado marcados; pulsar el módulo, solo el estado
    ciclo:   filtroCiclo.value ? { label: filtroCiclo.value, accion: () => { filtroModulo.value = ''; filtroEstado.value = 'todos'; } } : null,
    modulo:  filtroModulo.value ? { label: filtroModulo.value, accion: () => { filtroEstado.value = 'todos'; } } : null,
    ultimo:  filtroEstado.value === 'todos' ? 'Todos en proceso' : (filtroLabels[filtroEstado.value] || nombreFamiliaActual.value),
  });
});

const ESTADO_SEGMENTOS = [
  { key: 'validado',   label: 'validados',   colorClass: 'bg-emerald-500' },
  { key: 'propuesta',  label: 'pendientes',  colorClass: 'bg-violet-500' },
  { key: 'en_edicion', label: 'en edición',  colorClass: 'bg-amber-500' },
  { key: 'archivado',  label: 'archivados',  colorClass: 'bg-gray-400' },
  { key: 'completado', label: 'completados', colorClass: 'bg-sky-500' },
];
function segmentosPorEstado(lista) {
  return ESTADO_SEGMENTOS.map(s => ({ ...s, count: lista.filter(p => p.estado === s.key).length }));
}

const familiaCards = computed(() => {
  const construirCard = (key, nombre, lista, total) => {
    const countCompletados = lista.filter(p => p.estado === 'completado').length;
    return { key, nombre, total, segments: segmentosPorEstado(lista), countCompletados, countEnProceso: total - countCompletados };
  };
  const cards = familiasDisponibles.value.map(f =>
    construirCard(f.nombre, f.nombre, proyectosCurso.value.filter(p => p.familia_nombre === f.nombre), f.count)
  );
  if (countSinFamilia.value > 0) {
    cards.push(construirCard(SIN_FAMILIA, 'Sin familia asignada', proyectosCurso.value.filter(p => !p.familia_nombre), countSinFamilia.value));
  }
  return cards;
});

// ── Vista "todos" (sin filtrar por estado): búsqueda + ciclo/curso propios,
// no ligados a una familia, y orden por fecha (por defecto) o curso ──────────
const ordenTodos       = ref('fecha');
const filtroCicloTodos = ref('');
const filtroCursoTodos = ref('');

const ciclosDisponiblesTodos = computed(() =>
  [...new Set(proyectosCurso.value.map(p => p.ciclo_nombre).filter(Boolean))].sort()
);
const cursosDisponiblesTodos = computed(() =>
  [...new Set(proyectosCurso.value.map(p => p.curso).filter(v => v != null))].sort((a, b) => String(a).localeCompare(String(b)))
);

// Ciclo "todos": al cambiarlo se reinicia el nivel (como hacía el chip)
const cicloTodos = computed({
  get: () => filtroCicloTodos.value,
  set: (c) => { filtroCicloTodos.value = c; filtroCursoTodos.value = ''; },
});
const OPCIONES_ORDEN = [{ value: 'fecha', label: 'Fecha' }, { value: 'curso', label: 'Nivel' }];

const hayFiltrosTodosActivos = computed(() => !!(filtroCicloTodos.value || filtroCursoTodos.value || busqueda.value));

function limpiarFiltrosTodos() {
  busqueda.value = '';
  filtroCicloTodos.value = '';
  filtroCursoTodos.value = '';
  ordenTodos.value = 'fecha';
}

const proyectosTodosFiltrados = computed(() => {
  let lista = proyectosCurso.value;
  if (filtroCicloTodos.value) lista = lista.filter(p => p.ciclo_nombre === filtroCicloTodos.value);
  if (filtroCursoTodos.value) lista = lista.filter(p => String(p.curso) === String(filtroCursoTodos.value));
  if (busqueda.value.trim()) {
    const q = busqueda.value.toLowerCase();
    lista = lista.filter(p =>
      p.titulo?.toLowerCase().includes(q) ||
      p.empresa_nombre?.toLowerCase().includes(q) ||
      p.centro_nombre?.toLowerCase().includes(q)
    );
  }
  // La API ya devuelve orderByDesc('updated_at') — "fecha" no necesita reordenar.
  if (ordenTodos.value === 'curso') {
    lista = [...lista].sort((a, b) => String(a.curso ?? '').localeCompare(String(b.curso ?? ''), 'es', { numeric: true }));
  }
  return lista;
});

// Preview de estados (capa 'estados'): cards como las de las secciones hub. Cada una abre
// el detalle con su chip ya marcado; `terminados` lleva a /proyectos/terminados.
// `color` = mismo color que el segmento de ESTADO_SEGMENTOS, para que casen con la barra.
const BOTONES_ESTADO = [
  { key: 'validado',   label: 'Validados',             desc: 'Proyectos aprobados, listos para trabajarse en el aula.', color: 'bg-emerald-500',
    icono: 'M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'propuesta',  label: 'Pendientes de validar', desc: 'Propuestas enviadas que esperan la validación de la empresa o tuya.', color: 'bg-violet-500',
    icono: 'M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'terminados', label: 'Proyectos terminados',  desc: 'Finalizados por los grupos, con su resultado.', color: 'bg-sky-500',
    icono: 'M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3' },
  { key: 'en_edicion', label: 'En edición',            desc: 'Borradores que todavía no has enviado.', color: 'bg-amber-500',
    icono: 'M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z' },
  { key: 'archivado',  label: 'Archivados',            desc: 'Fuera de uso, pero siguen consultables.', color: 'bg-gray-400',
    icono: 'M21 8v13H3V8M1 3h22v5H1zM10 12h4' },
  { key: 'todos',      label: 'Todos',                 desc: 'Todo lo que está en proceso, en cualquier estado.', color: 'bg-azul-noche',
    icono: 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5' },
];

function elegirEstado(key) {
  if (key === 'terminados') return irACompletadosFamilia(filtroFamilia.value);
  router.push(queryCapa('detalle', { familia: filtroFamilia.value, estado: key === 'todos' ? '' : key }));
}
const contadorEstado = (key) => key === 'terminados' ? totalCompletadosFamiliaActual.value : conteosPorEstado.value[key];

const nombreFamiliaActual = computed(() =>
  filtroFamilia.value === '' ? 'Todos los proyectos' : (filtroFamilia.value === SIN_FAMILIA ? 'Sin familia asignada' : filtroFamilia.value)
);

const filtroOpciones = ['validado', 'propuesta', 'en_edicion', 'archivado', 'todos'];
const filtroLabels   = { todos: 'Todos', en_edicion: 'En edición', propuesta: 'Pendiente validar', validado: 'Validados', archivado: 'Archivado' };

// Base para los contadores de estado: respeta familia/ciclo/módulo/curso (no busqueda ni el
// propio estado), igual que Biblioteca calcula conteoNiveles dentro de familia+centro.
const proyectosPorDetalle = computed(() => {
  let lista = proyectosVisibles.value.filter(coincideFamilia);
  if (filtroCiclo.value) lista = lista.filter(p => p.ciclo_nombre === filtroCiclo.value);
  if (filtroModulo.value) lista = lista.filter(p => modulosDeProyecto(p).includes(filtroModulo.value));
  if (filtroCurso.value) lista = lista.filter(p => String(p.curso) === String(filtroCurso.value));
  return lista;
});

const conteosPorEstado = computed(() => ({
  validado:   proyectosPorDetalle.value.filter(p => p.estado === 'validado').length,
  propuesta:  proyectosPorDetalle.value.filter(p => p.estado === 'propuesta').length,
  en_edicion: proyectosPorDetalle.value.filter(p => p.estado === 'en_edicion').length,
  archivado:  proyectosPorDetalle.value.filter(p => p.estado === 'archivado').length,
  todos:      proyectosPorDetalle.value.length,
}));

// Contextual al filtro de familia activo en la capa "detalle" — el botón "Ver
// completados" de esa capa lleva el mismo recorte a /proyectos/terminados.
const totalCompletadosFamiliaActual = computed(() =>
  proyectosCurso.value.filter(coincideFamilia).filter(p => p.estado === 'completado').length
);

const proyectosFiltrados = computed(() => {
  let lista = proyectosVisibles.value;
  if (filtroEstado.value !== 'todos') {
    lista = lista.filter(p => p.estado === filtroEstado.value);
  }
  return aplicarFiltros(lista);
});

// ── Tarjeta de filtros (FiltrosPanel) — una sola para todas las capas ────────
// 'familias' y 'estados' solo muestran el selector de curso académico (sin buscador ni pie);
// 'detalle' y 'todos' añaden buscador, filtros propios y contador de resultados.
const conBuscador = computed(() => vista.value === 'detalle' || vista.value === 'todos');
const resultadosPanel = computed(() =>
  vista.value === 'detalle' ? proyectosFiltrados.value.length
  : vista.value === 'todos' ? proyectosTodosFiltrados.value.length
  : null
);
const hayActivosPanel = computed(() =>
  vista.value === 'detalle' ? hayFiltrosDetalleActivos.value
  : vista.value === 'todos' ? hayFiltrosTodosActivos.value
  : false
);
function limpiarPanel() {
  if (vista.value === 'detalle') limpiarFiltrosDetalle();
  else if (vista.value === 'todos') limpiarFiltrosTodos();
}

const opcionesNivel = (cursos) => [{ value: '', label: 'Todos' }, ...cursos.map(cu => ({ value: cu, label: `${cu}º` }))];

// Estado (detalle): "Todos" primero, con contador por opción
// "Completados" es un estado más del filtro, aunque viva en su propia vista
// (/proyectos/terminados): elegirlo navega allí con la misma familia.
const COMPLETADOS = 'completado';
const opcionesEstado = computed(() => [
  ...['todos', ...filtroOpciones.filter(o => o !== 'todos')]
    .map(op => ({ value: op, label: filtroLabels[op], count: conteosPorEstado.value[op] })),
  { value: COMPLETADOS, label: 'Completados', count: totalCompletadosFamiliaActual.value },
]);
function elegirEstadoFiltro(estado) {
  if (estado === COMPLETADOS) irACompletadosFamilia(filtroFamilia.value);
  else filtroEstado.value = estado;
}

// Familia (detalle): la familia vive en la URL (migas, flecha volver), así que el select no
// toca filtroFamilia directamente: navega con replace manteniendo capa y estado, y el watch
// URL → estado hace el resto (incluido reiniciar ciclo/nivel).
const opcionesFamiliaDetalle = computed(() => {
  const ops = familiasDisponibles.value.map(f => ({ value: f.nombre, label: f.nombre }));
  if (countSinFamilia.value > 0) ops.push({ value: SIN_FAMILIA, label: 'Sin familia asignada' });
  if (filtroFamilia.value && !ops.some(o => o.value === filtroFamilia.value)) {
    ops.unshift({ value: filtroFamilia.value, label: nombreFamiliaActual.value });
  }
  return ops;
});
const familiaDetalle = computed({
  get: () => filtroFamilia.value,
  set: (fam) => router.replace(queryCapa('detalle', { familia: fam, estado: filtroEstado.value === 'todos' ? '' : filtroEstado.value })),
});
// Ciclo (detalle): al cambiarlo se reinicia el nivel (como hacía seleccionarCiclo)
const cicloDetalle = computed({
  get: () => filtroCiclo.value,
  set: (c) => { filtroCiclo.value = c; filtroCurso.value = ''; },
});

// ── Modal eliminar ──────────────────────────────────────────────────────────
const modalEliminarVisible = ref(false);
const proyectoAEliminar    = ref(null);

function abrirModalEliminar(proyecto) {
  proyectoAEliminar.value   = proyecto;
  modalEliminarVisible.value = true;
}

function cerrarModalEliminar() {
  modalEliminarVisible.value = false;
  proyectoAEliminar.value    = null;
}

function onProyectoEliminado({ uuid, titulo }) {
  proyectos.value = proyectos.value.filter(p => p.uuid !== uuid);
  cerrarModalEliminar();
  // La papelera de "Base de datos" es solo superadmin — el resto de roles ya no tiene esa ruta.
  mostrarSnack(
    `"${titulo}" movido a la papelera.`,
    authStore.isSuperAdmin ? { label: 'Ir a la papelera', fn: () => router.push({ name: 'papelera' }) } : null,
  );
}

// ── Snackbar ────────────────────────────────────────────────────────────────
const snackbar = ref({ visible: false, mensaje: '', accion: null });
function mostrarSnack(mensaje, accion = null) {
  snackbar.value = { visible: true, mensaje, accion };
  setTimeout(() => { snackbar.value.visible = false; }, 5000);
}

</script>

<template>
  <div class="min-h-screen p-4 md:p-10 font-sans text-[#1F2937] pt-16 md:pt-16">

    <!-- Modal bienvenida -->
    <BienvenidaStartupDayModal :show="guiaBienvenida" @seleccionar="seleccionarOpcionBienvenida" />

    <!-- ══ TOUR BOCADILLO ══════════════════════════════════════════════════════ -->
    <Transition name="sp-fade">
      <div v-if="modoGuia" class="fixed inset-0 z-[9990] pointer-events-none">
        <!-- Backdrop bloqueante transparente — bloquea interacción sin oscurecer el elemento activo -->
        <div class="absolute inset-0 pointer-events-auto" />

        <div class="absolute pointer-events-auto"
             :style="{ top: bocadilloPos.top + 'px', left: bocadilloPos.left + 'px', width: bocadilloPos.width + 'px', zIndex: 9992 }">

          <!-- Flecha arriba (bocadillo debajo del elemento) -->
          <div v-if="bocadilloPos.dir === 'top'"
               class="absolute bg-[#1a2332] border-l border-t border-white/10 w-3 h-3 rotate-45 -top-1.5"
               :style="{ left: (bocadilloPos.arrowLeft - 6) + 'px' }" />

          <!-- Bocadillo -->
          <div class="bg-[#1a2332] border border-white/10 rounded-2xl p-4 shadow-2xl">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[9px] font-black uppercase tracking-widest text-amber-400">Startup Day · Guía</span>
              <span class="text-[9px] font-bold text-white/40">{{ pasoGuia }} / {{ guiaPasosData.length }}</span>
            </div>
            <p class="text-xs text-white/80 leading-relaxed mb-3">{{ pasoActual?.texto }}</p>
            <div class="flex items-center justify-between gap-2">
              <button @click="cerrarGuia"
                      class="text-[10px] font-bold text-white/30 hover:text-white/60 transition-colors">
                Cerrar
              </button>
              <div class="flex gap-2">
                <button v-if="pasoGuia > 1" @click="retrocederPaso"
                        class="px-3 py-1.5 rounded-xl bg-white/10 text-white text-[11px] font-black
                               hover:bg-white/20 transition-all">
                  ← Ant.
                </button>
                <button @click="avanzarPaso"
                        class="px-3 py-1.5 rounded-xl text-white text-[11px] font-black
                               transition-all" :class="[theme.bg, theme.bgHover]">
                  {{ pasoGuia < guiaPasosData.length ? 'Siguiente →' : 'Finalizar' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Flecha abajo (bocadillo encima del elemento) -->
          <div v-if="bocadilloPos.dir === 'bottom'"
               class="absolute bg-[#1a2332] border-r border-b border-white/10 w-3 h-3 rotate-45 -bottom-1.5"
               :style="{ left: (bocadilloPos.arrowLeft - 6) + 'px' }" />
        </div>
      </div>
    </Transition>

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[700px] h-[400px]
                opacity-5 blur-[120px] rounded-full pointer-events-none z-0" :class="theme.bg" />

    <div class="relative z-10 max-w-6xl mx-auto"
         :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
         style="transition: opacity 0.4s ease, transform 0.4s ease">

      <!-- Cabecera de sección (mismo estilo que Retos y proyectos) + acciones de la vista -->
      <CabeceraSeccion titulo="Biblioteca de" destacado="Proyectos" :color="theme.text" :volver="volverCabecera"
                       subtitulo="Aquí se trabajan los retos para convertirlos en propuestas y, tras su validación, en proyectos de empresa."
                       class="mb-6">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Ayuda: mismo botón y sitio en todas las vistas. Abre "¿Qué necesitas?", que
               incluye la opción de la guía (paso del tour: refBtnGuia) -->
          <button ref="refBtnGuia" type="button" @click="guiaBienvenida = true"
                  :class="{ 'tour-active': pasoRefActivo === 'refBtnGuia' }"
                  class="accion-secundaria" title="¿Qué necesitas? Opciones y guía de esta pantalla">
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
                  title="Ver proyectos eliminados en la papelera"
                  aria-label="Ver proyectos eliminados en la papelera">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
          </button>
          <!-- Nueva propuesta (acción principal; paso del tour: refBtnNuevo) -->
          <button
            v-if="!authStore.isEmpresa"
            ref="refBtnNuevo"
            type="button"
            @click="router.push({ name: 'startup-day-crear' })"
            :class="{
              'tour-active': pasoRefActivo === 'refBtnNuevo',
              'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'btn-nuevo'
            }"
            class="accion-principal"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
            </svg>
            Nueva propuesta
          </button>
        </div>
      </CabeceraSeccion>

      <!-- Recordatorio propuesta ≠ proyecto (se oculta al cerrarlo; empty:hidden quita su hueco) -->
      <div class="mb-6 empty:hidden">
        <RecordatorioPropuestaFlotante />
      </div>

      <!-- ─── Filtros: una sola tarjeta para todas las capas ───────────────────
           familias/estados → solo el selector de curso académico (mismo sitio visual)
           detalle/todos    → buscador + filtros propios + contador
           El wrapper es el paso "refBusqueda" del tour (resalta la tarjeta); el estado
           tiene su propio paso "refFiltros" dentro. -->
      <div ref="refBusqueda"
           :class="{
             'tour-active': pasoRefActivo === 'refBusqueda',
             'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'busqueda' && seccionActiva !== 'filtros'
           }"
           class="mb-6 rounded-2xl">
        <FiltrosPanel :busqueda="conBuscador ? busqueda : null" @update:busqueda="busqueda = $event"
                      placeholder="Buscar por título, empresa o centro…"
                      :resultados="resultadosPanel" :unidad="['proyecto', 'proyectos']"
                      :hay-activos="hayActivosPanel" @limpiar="limpiarPanel">
          <template #curso>
            <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
          </template>

          <!-- Detalle: estado (con contador) · familia · ciclo · nivel -->
          <template v-if="vista === 'detalle'" #default>
            <div ref="refFiltros"
                 :class="{
                   'tour-active': pasoRefActivo === 'refFiltros',
                   'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'filtros'
                 }"
                 class="min-w-0 max-w-full rounded-xl">
              <FiltroSegmentado :model-value="filtroEstado" @update:model-value="elegirEstadoFiltro" label="Estado" :opciones="opcionesEstado" />
            </div>
            <FiltroSelect v-if="opcionesFamiliaDetalle.length" v-model="familiaDetalle"
                          label="Familia profesional" todos="Todas las familias" :opciones="opcionesFamiliaDetalle" />
            <FiltroSelect v-if="ciclosDisponibles.length" v-model="cicloDetalle"
                          label="Ciclo formativo" todos="Todos los ciclos" :opciones="ciclosDisponibles" />
            <FiltroSelect v-if="modulosDisponibles.length" v-model="filtroModulo"
                          label="Módulo" todos="Todos los módulos" :opciones="modulosDisponibles" />
            <FiltroSegmentado v-if="cursosDisponibles.length" v-model="filtroCurso"
                              label="Nivel" :opciones="opcionesNivel(cursosDisponibles)" />
          </template>

          <!-- Todos: orden · ciclo · nivel (todas las familias mezcladas) -->
          <template v-else-if="vista === 'todos'" #default>
            <div class="min-w-0 max-w-full">
              <FiltroSegmentado v-model="ordenTodos" label="Ordenar por" :opciones="OPCIONES_ORDEN" />
            </div>
            <FiltroSelect v-if="ciclosDisponiblesTodos.length" v-model="cicloTodos"
                          label="Ciclo formativo" todos="Todos los ciclos" :opciones="ciclosDisponiblesTodos" />
            <FiltroSegmentado v-if="cursosDisponiblesTodos.length" v-model="filtroCursoTodos"
                              label="Nivel" :opciones="opcionesNivel(cursosDisponiblesTodos)" />
            <p class="basis-full text-[11px] font-semibold text-gray-400">
              {{ ordenTodos === 'fecha' ? 'Ordenados por fecha de última actividad, más recientes primero.' : 'Ordenados por curso.' }}
            </p>
          </template>
        </FiltrosPanel>
      </div>

      <!-- Cargando -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
        <svg class="animate-spin w-12 h-12 mb-4" :class="theme.text" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="font-black tracking-widest uppercase text-sm animate-pulse" :class="theme.text">Cargando...</p>
      </div>

      <template v-else>

        <!-- ══ CAPA 1: FAMILIAS (vista por defecto) ══════════════════════════ -->
        <div v-if="vista === 'familias'">
          <p class="text-center text-gray-400 text-sm font-bold uppercase tracking-widest mb-6">
            Selecciona una familia profesional o entra directamente a todos los proyectos
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <FamiliaProyectosCard
              dashed
              nombre="Todos los proyectos"
              :total="proyectos.length"
              :segments="segmentosPorEstado(proyectos)"
              @click="irATodosSinFiltrar"
            />
            <FamiliaProyectosCard
              v-for="f in familiaCards" :key="f.key"
              :nombre="f.nombre" :total="f.total" :segments="f.segments"
              :count-en-proceso="f.countEnProceso" :count-completados="f.countCompletados"
              @ver-proceso="irAEstados(f.key)"
              @ver-completados="irACompletadosFamilia(f.key)"
            />
          </div>
        </div>

        <!-- ══ CAPA 1b: PREVIEW DE ESTADOS ("En proceso" → elegir a dónde ir) ══ -->
        <div v-else-if="vista === 'estados'">

          <MigasNavegacion :items="migas.items" :actual="migas.actual" class="mb-6" />

          <p class="text-center text-gray-400 text-sm font-bold uppercase tracking-widest mb-6">
            ¿Qué proyectos quieres ver?
          </p>

          <!-- Mismo lenguaje que las cards de las secciones hub (SeccionHub.vue) -->
          <div class="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <button v-for="b in BOTONES_ESTADO" :key="b.key" type="button"
                    @click="elegirEstado(b.key)"
                    class="group flex min-w-0 items-start gap-4 rounded-2xl bg-white p-5 text-left shadow-sm ring-1 ring-gray-200/70 transition hover:-translate-y-0.5 hover:shadow-md">
              <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm transition group-hover:scale-105" :class="b.color">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                  <path :d="b.icono" />
                </svg>
              </span>
              <span class="min-w-0 flex-1">
                <span class="flex items-start justify-between gap-2">
                  <span class="font-heading text-lg font-bold leading-snug text-azul-noche">{{ b.label }}</span>
                  <span class="shrink-0 text-2xl font-black leading-none text-gray-300 transition group-hover:text-azul-noche">{{ contadorEstado(b.key) }}</span>
                </span>
                <span class="mt-1 block text-sm leading-snug text-gray-500">{{ b.desc }}</span>
              </span>
            </button>
          </div>

        </div><!-- /capa 1b -->

        <!-- ══ CAPA 2: DETALLE (búsqueda + estado + ciclo/curso + grid) ═══════ -->
        <div v-else-if="vista === 'detalle'">

          <MigasNavegacion :items="migas.items" :actual="migas.actual" class="mb-6" />

          <!-- Grid wrapper (ref para el tour) -->
          <div ref="refGrid"
               :class="{
                 'tour-active': pasoRefActivo === 'refGrid',
                 'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'grid'
               }">

            <!-- Vacío -->
            <div v-if="proyectosFiltrados.length === 0"
                 class="text-center py-24 bg-white rounded-[2rem] border border-dashed border-gray-200 shadow-sm">
              <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-5
                          border border-gray-100 shadow-inner">
                <svg class="w-10 h-10" :class="theme.text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 2L2 7l10 5 10-5-10-5zm0 10l-10-5m10 5l10-5m-10 5v10"/>
                </svg>
              </div>
              <h3 class="text-azul-noche font-black text-xl mb-2">
                {{ hayFiltrosDetalleActivos || filtroEstado !== 'todos' ? 'Sin resultados' : 'Todavía no hay propuestas ni proyectos' }}
              </h3>
              <p class="text-gray-400 text-sm mb-6">
                {{ hayFiltrosDetalleActivos || filtroEstado !== 'todos' ? 'Prueba con otros filtros' : 'Crea tu primera propuesta StartUp Day' }}
              </p>
              <button v-if="!hayFiltrosDetalleActivos && filtroEstado === 'todos' && !authStore.isEmpresa"
                      @click="router.push({ name: 'startup-day-crear' })"
                      class="inline-flex items-center gap-2 px-6 py-3 text-white rounded-full
                             text-xs font-black uppercase tracking-widest shadow-sm
                             transition-all active:scale-95" :class="[theme.bg, theme.bgHover]">
                Crear el primero
              </button>
            </div>

            <!-- Grid de tarjetas -->
            <div v-else class="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <ProyectoCard
                v-for="p in proyectosFiltrados" :key="p.uuid"
                :proyecto="p"
                :resaltar-editar="filtroEstado === 'en_edicion'"
                @eliminar="abrirModalEliminar"
              />
            </div>

          </div><!-- /grid wrapper -->

        </div><!-- /capa 2 -->

        <!-- ══ CAPA 3: TODOS SIN FILTRAR (mezcla cualquier estado, orden fecha/curso) ══ -->
        <div v-else-if="vista === 'todos'">

          <MigasNavegacion :items="migas.items" :actual="migas.actual" class="mb-6" />

          <!-- Vacío -->
          <div v-if="proyectosTodosFiltrados.length === 0"
               class="text-center py-24 bg-white rounded-[2rem] border border-dashed border-gray-200 shadow-sm">
            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-5
                        border border-gray-100 shadow-inner">
              <svg class="w-10 h-10" :class="theme.text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 2L2 7l10 5 10-5-10-5zm0 10l-10-5m10 5l10-5m-10 5v10"/>
              </svg>
            </div>
            <h3 class="text-azul-noche font-black text-xl mb-2">
              {{ hayFiltrosTodosActivos ? 'Sin resultados' : 'Todavía no hay propuestas ni proyectos' }}
            </h3>
            <p class="text-gray-400 text-sm">
              {{ hayFiltrosTodosActivos ? 'Prueba con otros filtros' : 'Crea tu primera propuesta StartUp Day' }}
            </p>
          </div>

          <!-- Grid de tarjetas -->
          <div v-else class="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <ProyectoCard
              v-for="p in proyectosTodosFiltrados" :key="p.uuid"
              :proyecto="p"
              @eliminar="abrirModalEliminar"
            />
          </div>

        </div><!-- /capa 3 -->

      </template>

    </div>
  </div>

  <!-- MODAL ELIMINAR PROYECTO -->
  <EliminarProyectoModal
    :visible="modalEliminarVisible"
    :proyecto="proyectoAEliminar"
    @proyecto-eliminado="onProyectoEliminado"
    @cerrar="cerrarModalEliminar"
  />

  <!-- SNACKBAR -->
  <Transition name="sp-snack">
    <div
      v-if="snackbar.visible"
      class="fixed bottom-6 right-6 z-[60] flex items-center gap-3
             px-5 py-3.5 rounded-2xl shadow-xl text-sm font-bold
             max-w-sm bg-[#1a2332] text-white border border-white/10"
    >
      <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      <span class="flex-1">{{ snackbar.mensaje }}</span>
      <button
        v-if="snackbar.accion"
        @click="snackbar.accion.fn(); snackbar.visible = false"
        class="ml-1 shrink-0 px-3 py-1.5 rounded-xl bg-amber-400 text-[#1a2332] text-[10px] font-black uppercase tracking-widest hover:bg-amber-300 transition-all"
      >
        {{ snackbar.accion.label }}
      </button>
    </div>
  </Transition>
</template>

<style scoped>
.sp-fade-enter-active, .sp-fade-leave-active { transition: opacity 200ms ease; }
.sp-fade-enter-from, .sp-fade-leave-to { opacity: 0; }

.sp-snack-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.sp-snack-leave-active { transition: all 0.2s ease-in; }
.sp-snack-enter-from   { opacity: 0; transform: translateY(12px); }
.sp-snack-leave-to     { opacity: 0; transform: translateY(8px); }

.tour-active {
  box-shadow: 0 0 0 3px v-bind('theme.hex'), 0 0 0 8px v-bind('theme.hexSoft');
  border-radius: 1rem;
  transition: box-shadow 0.3s ease;
}

.tour-seccion-blur {
  filter: blur(2px);
  opacity: 0.4;
  pointer-events: none;
  transition: filter 0.3s ease, opacity 0.3s ease;
}
</style>
