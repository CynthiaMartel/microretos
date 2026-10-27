<script setup>
// Biblioteca de proyectos: un solo explorador (mismo esquema que la Biblioteca de retos).
// Pestañas «Completados | En proceso» arriba (por defecto Completados); barra de filtros común a las dos (buscador,
// ciclo, módulo, nivel) y, en «Filtros rápidos», familia + el filtro propio de cada pestaña:
// Estado en «En proceso» y Validado por en «Completados». /proyectos/terminados redirige aquí.
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { modulosDeProyecto, modulosDisponibles as listarModulos } from '../utils/modulos.js'
import { useRouter, useRoute } from 'vue-router';
import api from '../api.js';
import BienvenidaStartupDayModal from '../components/BienvenidaStartupDayModal.vue';
import EliminarProyectoModal from '../components/EliminarProyectoModal.vue';
import ProyectoCard from '../components/ProyectoCard.vue';
import RecordatorioPropuestaFlotante from '../components/RecordatorioPropuestaFlotante.vue';
import { useUIState } from '../composables/useUIState.js';
import { usePaginacion } from '../composables/usePaginacion.js';
import { useAuthStore } from '../stores/auth.js';
import { useCursoAcademicoStore, cursoDeProyecto } from '../stores/cursoAcademico.js';
import SelectorCurso from '../components/SelectorCurso.vue';
import { useRoleTheme } from '../composables/useRoleTheme.js';
import FiltrosPanel from '../components/FiltrosPanel.vue';
import FiltroSegmentado from '../components/FiltroSegmentado.vue';
import FiltroSelect from '../components/FiltroSelect.vue';
import TituloResultados from '../components/TituloResultados.vue';

const router = useRouter();
const route  = useRoute();
const { tourActivo } = useUIState();
const authStore = useAuthStore();
const { theme } = useRoleTheme();

// ── Datos ───────────────────────────────────────────────────────────────────
const proyectos = ref([]);
const cargando  = ref(true);
const errorCarga = ref('');

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
  { ref: 'refFiltros',  seccion: 'filtros',   texto: 'Elige qué quieres ver: «Completados» (proyectos ya terminados por los grupos, se abre por defecto) o «En proceso» (borradores, propuestas pendientes, validados y archivados). El número indica cuántos hay con los filtros actuales.' },
  { ref: 'refBusqueda', seccion: 'busqueda',  texto: 'Busca por título, empresa o centro y filtra por ciclo, módulo y nivel. En «Más filtros» tienes la familia profesional y el estado (en proceso) o quién lo validó (completados).' },
  { ref: 'refGrid',     seccion: 'grid',      texto: 'Aquí aparecen los proyectos. Cada tarjeta muestra su estado con un color, el curso, los módulos y la empresa. Pulsa en una tarjeta para ver el detalle completo.' },
  { ref: 'refBtnNuevo', seccion: 'btn-nuevo', texto: 'Pulsa aquí para crear un nuevo proyecto en el Taller de Ideas. Necesitarás haber registrado previamente un encuentro para poder vincularlo al reto correspondiente.' },
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
    window.addEventListener('resize', onScrollGuia, { passive: true });
    nextTick(scrollYRecalcular);
  } else {
    window.removeEventListener('scroll', onScrollGuia);
    window.removeEventListener('resize', onScrollGuia);
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
    modoGuia.value = true;
    pasoGuia.value = 1;
  }
  // 'trabajar' → se queda en la vista
}

async function cargarProyectos() {
  cargando.value = true;
  errorCarga.value = '';
  try {
    const res = await api.get('/startup/proyectos');
    proyectos.value = res.data;
  } catch {
    errorCarga.value = 'No se han podido cargar los proyectos. Comprueba la conexión e inténtalo de nuevo.';
  } finally {
    cargando.value = false;
  }
}

onMounted(() => {
  normalizarUrlAntigua();
  cargarProyectos();
  // Auto-disparo de la bienvenida desactivado — reactivar con guiaBienvenida.value = true.
});

onUnmounted(() => {
  tourActivo.value = false;
  window.removeEventListener('scroll', onScrollGuia);
  window.removeEventListener('resize', onScrollGuia);
});

// ── Curso académico compartido (store): todo lo que se lista y cuenta sale de aquí ──
const cursoStore     = useCursoAcademicoStore();
const proyectosCurso = computed(() => proyectos.value.filter(p => cursoStore.coincide(cursoDeProyecto(p))));
const cursosConDatos = computed(() => [...new Set(proyectos.value.map(cursoDeProyecto))]);

// ── Pestaña: «Completados» (por defecto, lo que luce el trabajo hecho) | «En proceso»
// (en la URL: ?ver=proceso). ?ver=completados también vale (enlaces y redirección antiguos).
const ambito = computed(() => (route.query.ver === 'proceso' ? 'proceso' : 'completados'));
const esCompletado = p => p.estado === 'completado';
async function elegirAmbito(valor) {
  if (valor === ambito.value) return;
  // Cada pestaña tiene su filtro propio: al cambiar se quita el de la otra. Primero la URL
  // y después los filtros, para que la sincronización filtros → URL no la pise.
  await router.replace({ query: { ...route.query, ver: valor === 'proceso' ? 'proceso' : undefined, estado: undefined } });
  filtroEstados.value = [];
  filtroValidador.value = [];
}

// ── Filtros ─────────────────────────────────────────────────────────────────
// Proyectos sin familia (antiguos): nunca se ocultan, tienen su propia casilla.
const SIN_FAMILIA = '__sin_familia__';
// 'Agraria' oculta como opción de filtro a petición de Cynthia (2026-09-28); sus
// proyectos siguen apareciendo sin filtro de familia.
const FAMILIAS_OCULTAS = ['Agraria'];

const aLista = v => (v == null ? [] : (Array.isArray(v) ? v : [v]).map(String).filter(Boolean));

// Enlaces antiguos: ?filtro=<estado>, ?capa=estados|detalle|todos&familia=…&estado=…
// → la pestaña y los filtros equivalentes. Se calcula antes de crear los filtros (así nacen
// ya marcados) y al montar se deja la URL limpia.
const queryAntigua = (() => {
  const q = route.query;
  if (!q.filtro && !q.capa) return null;
  const estado = String(q.filtro || q.estado || '');
  const query = { familia: q.familia || undefined };
  // Sin estado o con uno en proceso → pestaña «En proceso» (era lo que mostraban esos enlaces)
  if (estado !== 'completado') query.ver = 'proceso';
  if (estado && estado !== 'todos' && estado !== 'completado') query.estado = estado;
  return query;
})();
function normalizarUrlAntigua() {
  if (queryAntigua) router.replace({ query: queryAntigua });
}
const busqueda        = ref('');
const filtroFamilias  = ref(aLista(route.query.familia));
const filtroCiclo     = ref('');
const filtroModulo    = ref('');
const filtroCurso     = ref('');
const filtroEstados   = ref(aLista((queryAntigua || route.query).estado).filter(e => e !== 'completado'));
const filtroValidador = ref([]);
const orden           = ref('recientes');  // 'recientes' | 'antiguos' | 'titulo' | 'nivel'
const familiasExpandidas = ref(false);
// «Filtros rápidos»: visible de entrada en escritorio y plegado en móvil; «Más filtros» lo alterna.
const panelFiltrosAbierto = ref(typeof window === 'undefined' || window.innerWidth >= 1024);
const columnasProyectos = computed(() => panelFiltrosAbierto.value
  ? 'md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3'
  : 'md:grid-cols-2 xl:grid-cols-3');

// Familia y estado viven en la URL: al volver de la ficha de un proyecto (atrás del
// navegador) siguen marcados.
watch(() => route.query.familia, (v) => {
  const lista = aLista(v);
  if (lista.join('|') !== filtroFamilias.value.join('|')) filtroFamilias.value = lista;
});
watch([filtroFamilias, filtroEstados], ([familias, estados]) => {
  if (aLista(route.query.familia).join('|') === familias.join('|') && aLista(route.query.estado).join('|') === estados.join('|')) return;
  router.replace({ query: { ...route.query, familia: familias.length ? familias : undefined, estado: estados.length ? estados : undefined } });
});
// Dependencias: el módulo y el nivel dependen del ciclo; el ciclo, de la familia.
watch(filtroFamilias, () => { filtroCiclo.value = ''; filtroModulo.value = ''; filtroCurso.value = ''; });
watch(filtroCiclo, () => { filtroModulo.value = ''; filtroCurso.value = ''; });

const coincideFamilias = p => !filtroFamilias.value.length
  || filtroFamilias.value.includes(p.familia_nombre || SIN_FAMILIA);

// Filtros comunes a las dos pestañas (barra superior + familia)
function aplicarComunes(lista) {
  let out = lista.filter(coincideFamilias);
  if (filtroCiclo.value) out = out.filter(p => p.ciclo_nombre === filtroCiclo.value);
  if (filtroModulo.value) out = out.filter(p => modulosDeProyecto(p).includes(filtroModulo.value));
  if (filtroCurso.value) out = out.filter(p => String(p.curso) === String(filtroCurso.value));
  const q = busqueda.value.trim().toLowerCase();
  if (q) {
    out = out.filter(p =>
      p.titulo?.toLowerCase().includes(q) ||
      p.empresa_nombre?.toLowerCase().includes(q) ||
      p.centro_nombre?.toLowerCase().includes(q)
    );
  }
  return out;
}

// Estado (solo «En proceso»). Sin ninguno marcado se ve todo lo vivo: los archivados
// solo aparecen al marcar «Archivados».
const ESTADOS_PROCESO = [
  { key: 'validado',   label: 'Validados',             punto: 'bg-emerald-500' },
  { key: 'propuesta',  label: 'Pendientes de validar', punto: 'bg-violet-500' },
  { key: 'en_edicion', label: 'En edición',            punto: 'bg-amber-500' },
  { key: 'archivado',  label: 'Archivados',            punto: 'bg-gray-400' },
];
const ETIQUETA_ESTADO = Object.fromEntries(ESTADOS_PROCESO.map(e => [e.key, e.label]));
const pasaEstado = p => filtroEstados.value.length ? filtroEstados.value.includes(p.estado) : p.estado !== 'archivado';

// Validado por (solo «Completados»): un proyecto solo llega a completado desde validado y
// conserva empresa_validado / docente_validado; si lo validaron ambos sale en los dos.
const VALIDADORES = [
  { key: 'empresa', label: 'Empresa', prueba: p => !!p.empresa_validado, icono: 'bg-empresas/10 text-empresas-dark' },
  { key: 'docente', label: 'Docente', prueba: p => !!p.docente_validado, icono: 'bg-centros/10 text-centros' },
];
const pasaValidador = p => !filtroValidador.value.length
  || VALIDADORES.some(v => filtroValidador.value.includes(v.key) && v.prueba(p));

const enProcesoComunes   = computed(() => aplicarComunes(proyectosCurso.value.filter(p => !esCompletado(p))));
const completadosComunes = computed(() => aplicarComunes(proyectosCurso.value.filter(esCompletado)));
const listaEnProceso     = computed(() => enProcesoComunes.value.filter(pasaEstado));
const listaCompletados   = computed(() => completadosComunes.value.filter(pasaValidador));
const proyectosFiltrados = computed(() => ambito.value === 'completados' ? listaCompletados.value : listaEnProceso.value);

// Contadores del lateral: respetan los demás filtros, no el propio grupo
const conteoEstados = computed(() => Object.fromEntries(ESTADOS_PROCESO.map(e =>
  [e.key, enProcesoComunes.value.filter(p => p.estado === e.key).length])));
const conteoValidadores = computed(() => Object.fromEntries(VALIDADORES.map(v =>
  [v.key, completadosComunes.value.filter(v.prueba).length])));

// Base de cada pestaña sin filtros: alimenta las familias y las opciones de la barra
const baseAmbito = computed(() => proyectosCurso.value.filter(p => (ambito.value === 'completados') === esCompletado(p)));
const familiasPanel = computed(() => {
  const mapa = new Map();
  baseAmbito.value.forEach(p => {
    const clave = p.familia_nombre || SIN_FAMILIA;
    if (FAMILIAS_OCULTAS.includes(clave)) return;
    mapa.set(clave, (mapa.get(clave) || 0) + 1);
  });
  return [...mapa.entries()]
    .map(([nombre, n]) => ({ nombre, n, label: nombre === SIN_FAMILIA ? 'Sin familia asignada' : nombre }))
    .sort((a, b) => (a.nombre === SIN_FAMILIA) - (b.nombre === SIN_FAMILIA) || b.n - a.n || a.label.localeCompare(b.label));
});
const familiasPanelVisibles = computed(() => {
  const todas = familiasPanel.value;
  if (familiasExpandidas.value || todas.length <= 6) return todas;
  // Las marcadas siempre a la vista aunque no estén entre las 6 primeras.
  return [...todas.slice(0, 6), ...todas.slice(6).filter(f => filtroFamilias.value.includes(f.nombre))];
});
const etiquetaFamilia = nombre => (nombre === SIN_FAMILIA ? 'Sin familia asignada' : nombre);

const baseFamilia = computed(() => baseAmbito.value.filter(coincideFamilias));
const ciclosDisponibles = computed(() =>
  [...new Set(baseFamilia.value.map(p => p.ciclo_nombre).filter(Boolean))].sort((a, b) => a.localeCompare(b)));
const baseCiclo = computed(() => filtroCiclo.value ? baseFamilia.value.filter(p => p.ciclo_nombre === filtroCiclo.value) : baseFamilia.value);
const modulosDisponibles = computed(() => listarModulos(baseCiclo.value, modulosDeProyecto));
const cursosDisponibles = computed(() =>
  [...new Set(baseCiclo.value.map(p => p.curso).filter(v => v != null && v !== ''))].sort((a, b) => String(a).localeCompare(String(b))));
// 'ambos_cursos' = vale para 1º y 2º (como «Ambos» en la Biblioteca de retos)
const etiquetaNivel = cu => (cu === 'ambos_cursos' ? 'Ambos' : `${cu}º`);
const opcionesNivel = computed(() => [{ value: '', label: 'Todos' }, ...cursosDisponibles.value.map(cu => ({ value: cu, label: etiquetaNivel(cu) }))]);

// Niveles elegidos para el título de los resultados (TituloResultados): una sola familia →
// ciclo → módulo. Quitar un nivel quita también los que dependen de él (watches de arriba).
const nivelesTitulo = computed(() => {
  const niveles = [];
  if (filtroFamilias.value.length === 1) {
    niveles.push({ etiqueta: 'Familia profesional', valor: etiquetaFamilia(filtroFamilias.value[0]),
      subir: () => { filtroCiclo.value = ''; filtroModulo.value = ''; }, quitar: () => { filtroFamilias.value = []; } });
  }
  if (filtroCiclo.value) niveles.push({ etiqueta: 'Ciclo formativo', valor: filtroCiclo.value, subir: () => { filtroModulo.value = ''; }, quitar: () => { filtroCiclo.value = ''; } });
  if (filtroModulo.value) niveles.push({ etiqueta: 'Módulo', valor: filtroModulo.value, subir: () => {}, quitar: () => { filtroModulo.value = ''; } });
  return niveles;
});
const familiaEnTitulo = computed(() => nivelesTitulo.value[0]?.etiqueta === 'Familia profesional');

// (En la plantilla los refs llegan desenvueltos: por eso una función por filtro.)
const alternar = (lista, valor) => lista.includes(valor) ? lista.filter(v => v !== valor) : [...lista, valor];
function alternarFamilia(nombre) { filtroFamilias.value = alternar(filtroFamilias.value, nombre); }
function alternarEstado(key) { filtroEstados.value = alternar(filtroEstados.value, key); }
function alternarValidador(key) { filtroValidador.value = alternar(filtroValidador.value, key); }

const filtroPropioActivo = computed(() => ambito.value === 'completados' ? filtroValidador.value.length > 0 : filtroEstados.value.length > 0);
const hayFiltrosActivos = computed(() => !!(busqueda.value.trim() || filtroFamilias.value.length || filtroCiclo.value
  || filtroModulo.value || filtroCurso.value || filtroPropioActivo.value));
function limpiarFiltros() {
  busqueda.value = '';
  filtroFamilias.value = [];
  filtroCiclo.value = '';
  filtroModulo.value = '';
  filtroCurso.value = '';
  filtroEstados.value = [];
  filtroValidador.value = [];
}

// ── Orden y paginación (12 por página, ver POR_PAGINA) ──────
const fechaProyecto = p => new Date(p.updated_at || p.created_at || 0).getTime();
const proyectosOrdenados = computed(() => {
  const lista = [...proyectosFiltrados.value];
  if (orden.value === 'titulo') return lista.sort((a, b) => (a.titulo || '').localeCompare(b.titulo || '', 'es'));
  if (orden.value === 'nivel') return lista.sort((a, b) => String(a.curso ?? '').localeCompare(String(b.curso ?? ''), 'es', { numeric: true }));
  return lista.sort((a, b) => orden.value === 'antiguos' ? fechaProyecto(a) - fechaProyecto(b) : fechaProyecto(b) - fechaProyecto(a));
});
// 12 por página: se reparte entero en 1, 2, 3 o 4 columnas (con 9, a 2 columnas quedaba
// una tarjeta sola en la última fila y un hueco a su derecha)
const POR_PAGINA = 12;
const { pagina, totalPaginas, itemsPagina: proyectosPagina } = usePaginacion(proyectosOrdenados, POR_PAGINA);
watch([proyectosFiltrados, orden, ambito], () => { pagina.value = 0; });
const paginasVisibles = computed(() => {
  const n = totalPaginas.value, a = pagina.value;
  const ini = Math.max(0, Math.min(a - 2, n - 5)), fin = Math.min(n, ini + 5);
  return Array.from({ length: fin - ini }, (_, i) => ini + i);
});
function irAPagina(i) {
  pagina.value = i;
  document.getElementById('resultados-proyectos')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

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
  <!-- Explorador de proyectos (mismo esquema que biblioteca_retos.png): cabecera + pestañas
       En proceso | Completados + filtros + «Filtros rápidos» + tarjetas con paginación. -->
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">

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
              <span class="text-[9px] font-black uppercase tracking-widest text-amber-400">Biblioteca de proyectos · Guía</span>
              <span class="text-[9px] font-bold text-white/40">{{ pasoGuia }} / {{ guiaPasosData.length }}</span>
            </div>
            <p class="text-xs text-white/80 leading-relaxed mb-3">{{ pasoActual?.texto }}</p>
            <div class="flex items-center justify-between gap-2">
              <button @click="cerrarGuia"
                      class="-ml-3 inline-flex h-9 items-center rounded-full px-3 text-[10px] font-bold text-white/30 hover:text-white/60 transition-colors">
                Cerrar
              </button>
              <div class="flex gap-2">
                <button v-if="pasoGuia > 1" @click="retrocederPaso"
                        class="inline-flex h-9 items-center px-3 py-1.5 rounded-full bg-white/10 text-white text-[11px] font-black
                               hover:bg-white/20 transition-all">
                  ← Ant.
                </button>
                <button @click="avanzarPaso"
                        class="inline-flex h-9 items-center px-3 py-1.5 rounded-full text-white text-[11px] font-black
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

    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Biblioteca de" destacado="Proyectos" color="text-centros"
                       :volver="authStore.isEmpresa ? null : '/seccion/retos-proyectos'"
                       subtitulo="Aquí se trabajan los retos para convertirlos en propuestas y, tras su validación, en proyectos de empresa.">
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
          <!-- Crear proyecto (acción principal; paso del tour: refBtnNuevo) -->
          <button v-if="!authStore.isEmpresa" ref="refBtnNuevo" type="button"
                  @click="router.push({ name: 'startup-day-crear' })"
                  :class="{
                    'tour-active': pasoRefActivo === 'refBtnNuevo',
                    'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'btn-nuevo'
                  }"
                  class="accion-principal">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
            </svg>
            Crear proyecto
          </button>
        </div>
      </CabeceraSeccion>

      <!-- Pestañas + recordatorio propuesta ≠ proyecto en la misma fila (el recordatorio a la
           derecha; en móvil, debajo). empty:hidden quita su hueco al cerrarlo. -->
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <!-- ═══ Pestañas: Completados | En proceso (paso del tour: refFiltros) ═══
             Es la primera decisión, por eso va fuera de la barra de filtros. El número
             cuenta lo que se vería en cada pestaña con los filtros actuales. -->
        <div ref="refFiltros"
             :class="{
               'tour-active': pasoRefActivo === 'refFiltros',
               'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'filtros'
             }"
             class="flex max-w-full shrink-0 rounded-full sm:w-fit">
          <div class="flex w-full gap-1 rounded-full border border-borde bg-white p-1 shadow-sm" role="group" aria-label="Qué proyectos ver">
            <button v-for="t in [
                      { v: 'completados', label: 'Completados', n: listaCompletados.length, icono: 'M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3' },
                      { v: 'proceso', label: 'En proceso', n: listaEnProceso.length, icono: 'M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
                    ]" :key="t.v" type="button" @click="elegirAmbito(t.v)" :aria-pressed="ambito === t.v"
                    class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-full px-2 text-sm font-semibold transition-colors sm:flex-none sm:gap-2 sm:px-5"
                    :class="ambito === t.v ? 'bg-rol-fuerte text-white shadow-sm' : 'text-texto-secundario hover:bg-gray-50 hover:text-azul-noche'">
              <svg class="hidden h-4 w-4 shrink-0 min-[400px]:block" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="t.icono" /></svg>
              <span class="truncate">{{ t.label }}</span>
              <span class="rounded-full px-2 py-0.5 text-xs font-bold"
                    :class="ambito === t.v ? 'bg-white/20 text-white' : 'bg-gray-100 text-azul-noche'">{{ cargando ? '…' : t.n }}</span>
            </button>
          </div>
        </div>
        <div class="min-w-0 empty:hidden lg:flex lg:justify-end">
          <RecordatorioPropuestaFlotante />
        </div>
      </div>

      <!-- Filtros principales (FiltrosPanel común; paso del tour: refBusqueda) -->
      <div ref="refBusqueda"
           :class="{
             'tour-active': pasoRefActivo === 'refBusqueda',
             'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'busqueda'
           }"
           class="rounded-2xl">
        <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar por título, empresa o centro…">
          <template #curso>
            <div class="flex flex-wrap items-center gap-2">
              <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
              <!-- «Más filtros» en la fila del buscador (en la de filtros, con 4 o más, bajaba solo a otra fila) -->
              <button type="button" @click="panelFiltrosAbierto = !panelFiltrosAbierto" :aria-expanded="panelFiltrosAbierto"
                      aria-controls="filtros-rapidos" :title="panelFiltrosAbierto ? 'Ocultar los filtros rápidos' : 'Mostrar los filtros rápidos'"
                      aria-label="Más filtros"
                      class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-full border px-5 text-sm font-semibold transition-colors"
                      :class="panelFiltrosAbierto ? 'border-rol-fuerte/30 bg-rol/10 text-rol-fuerte' : 'border-rol-fuerte/30 bg-white text-rol-fuerte hover:bg-rol-fuerte/5'">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                      <span>Más filtros</span>
                      <span v-if="!panelFiltrosAbierto && (filtroFamilias.length || filtroPropioActivo)"
                      class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rol-fuerte px-1 text-[11px] font-bold text-white">
                      {{ filtroFamilias.length + (ambito === 'completados' ? filtroValidador.length : filtroEstados.length) }}
                      </span>
                      </button>
            </div>
          </template>

          <FiltroSelect label="Ciclo formativo" v-model="filtroCiclo" :opciones="ciclosDisponibles" todos="Todos los ciclos"
            title="Filtra por el ciclo formativo al que va dirigido el proyecto." />
          <FiltroSelect v-if="modulosDisponibles.length" label="Módulo" v-model="filtroModulo" :opciones="modulosDisponibles" todos="Todos los módulos"
            title="Filtra por módulo profesional. Los proyectos de varios módulos aparecen en cada uno." />
          <FiltroSegmentado v-if="cursosDisponibles.length" label="Nivel" v-model="filtroCurso" :opciones="opcionesNivel"
            title="Nivel del ciclo: 1º (primer año) o 2º (segundo año). Ambos: proyectos que valen para 1º y 2º." />
        </FiltrosPanel>
      </div>

      <!-- Error de carga -->
      <div v-if="errorCarga && !cargando" class="flex flex-col items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center" role="alert">
        <p class="min-w-0 flex-1 text-sm font-medium text-red-700">{{ errorCarga }}</p>
        <button type="button" @click="cargarProyectos" class="inline-flex h-10 items-center rounded-full bg-white px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Reintentar</button>
      </div>

      <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        <!-- ═══ Filtros rápidos (panel lateral; en < lg se abre con «Más filtros») ═══
             Familia en las dos pestañas + un solo grupo propio de cada una. -->
        <aside id="filtros-rapidos" aria-label="Filtros rápidos"
               class="w-full shrink-0 rounded-2xl border border-borde bg-white p-4 shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:w-64 xl:w-72"
               :class="panelFiltrosAbierto ? 'block' : 'hidden'">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-azul-noche">Filtros rápidos</h2>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros"
                    class="inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">Limpiar</button>
          </div>

          <!-- En proceso: Estado (sin marcar = todo lo vivo; los archivados, solo si se marcan) -->
          <div v-if="ambito === 'proceso'" class="border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-estado">
            <p id="titulo-filtro-estado" class="mb-1.5 text-sm font-semibold text-azul-noche">Estado</p>
            <label v-for="e in ESTADOS_PROCESO" :key="e.key"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroEstados.includes(e.key)" @change="alternarEstado(e.key)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="e.punto" aria-hidden="true" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ e.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoEstados[e.key] }}</span>
            </label>
            <p v-if="!filtroEstados.includes('archivado') && conteoEstados.archivado" class="px-1 pt-1 text-xs leading-snug text-texto-secundario">
              Los archivados no se muestran salvo que marques «Archivados».
            </p>
          </div>

          <!-- Completados: quién lo validó -->
          <div v-else class="border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-validador">
            <p id="titulo-filtro-validador" class="mb-1.5 text-sm font-semibold text-azul-noche">Validado por</p>
            <label v-for="v in VALIDADORES" :key="v.key"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroValidador.includes(v.key)" @change="alternarValidador(v.key)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg" :class="v.icono" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              </span>
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ v.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoValidadores[v.key] }}</span>
            </label>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-familia">
            <p id="titulo-filtro-familia" class="mb-1.5 text-sm font-semibold text-azul-noche">Familia profesional</p>
            <label v-for="f in familiasPanelVisibles" :key="f.nombre"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroFamilias.includes(f.nombre)" @change="alternarFamilia(f.nombre)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche" :title="f.label">{{ f.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ f.n }}</span>
            </label>
            <p v-if="!familiasPanel.length && !cargando" class="px-1 py-1 text-sm text-texto-secundario">Sin proyectos en este curso.</p>
            <button v-if="familiasPanel.length > 6" type="button" @click="familiasExpandidas = !familiasExpandidas"
                    class="mt-1 inline-flex h-9 items-center rounded-full px-1 text-sm font-semibold text-rol-fuerte hover:underline">
              {{ familiasExpandidas ? 'Ver menos' : `Ver todas (${familiasPanel.length})` }}
            </button>
          </div>
        </aside>

        <!-- ═══ Resultados (paso del tour: refGrid) ═══ -->
        <section id="resultados-proyectos" ref="refGrid" class="min-w-0 flex-1 scroll-mt-24 rounded-2xl" aria-label="Proyectos"
                 :class="{
                   'tour-active': pasoRefActivo === 'refGrid',
                   'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'grid'
                 }">
          <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <TituloResultados :niveles="nivelesTitulo" :cargando="cargando">
              <template v-if="cargando">Cargando proyectos…</template>
              <template v-else>
                {{ proyectosFiltrados.length }}
                {{ ambito === 'completados'
                     ? (proyectosFiltrados.length === 1 ? 'proyecto completado' : 'proyectos completados')
                     : (proyectosFiltrados.length === 1 ? 'proyecto en proceso' : 'proyectos en proceso') }}
              </template>
            </TituloResultados>
            <!-- Chips de filtros activos del lateral -->
            <div v-if="(filtroFamilias.length && !familiaEnTitulo) || filtroPropioActivo" class="flex w-full flex-wrap gap-1.5 sm:order-last">
              <template v-if="ambito === 'proceso'">
                <button v-for="e in filtroEstados" :key="'e-' + e" type="button" @click="alternarEstado(e)"
                        class="inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200"
                        :aria-label="`Quitar filtro ${ETIQUETA_ESTADO[e] || e}`">
                  <span class="h-2 w-2 rounded-full" :class="ESTADOS_PROCESO.find(x => x.key === e)?.punto || 'bg-gray-400'" aria-hidden="true" />
                  {{ ETIQUETA_ESTADO[e] || e }}<span aria-hidden="true">✕</span>
                </button>
              </template>
              <template v-else>
                <button v-for="v in filtroValidador" :key="'v-' + v" type="button" @click="alternarValidador(v)"
                        class="inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200"
                        :aria-label="`Quitar filtro validado por ${v}`">
                  Validado por {{ v === 'empresa' ? 'empresa' : 'docente' }}<span aria-hidden="true">✕</span>
                </button>
              </template>
              <button v-for="f in (familiaEnTitulo ? [] : filtroFamilias)" :key="'f-' + f" type="button" @click="alternarFamilia(f)"
                      class="inline-flex h-9 max-w-full items-center gap-1.5 rounded-full bg-rol/10 px-3 text-xs font-semibold text-rol-fuerte hover:bg-rol/15"
                      :aria-label="`Quitar filtro ${etiquetaFamilia(f)}`">
                <span class="truncate">{{ etiquetaFamilia(f) }}</span><span aria-hidden="true">✕</span>
              </button>
            </div>
            <label class="flex items-center gap-2 text-sm text-texto-secundario">
              <span class="hidden sm:inline">Ordenar por</span>
              <select v-model="orden" class="h-9 rounded-xl px-3 text-sm text-azul-noche" aria-label="Ordenar proyectos">
                <option value="recientes">Actividad más reciente</option>
                <option value="antiguos">Actividad más antigua</option>
                <option value="titulo">Título (A-Z)</option>
                <option value="nivel">Nivel</option>
              </select>
            </label>
          </div>

          <!-- Carga: skeleton con la forma de las tarjetas -->
          <div v-if="cargando" class="grid grid-cols-1 gap-4" :class="columnasProyectos" aria-busy="true">
            <div v-for="n in 6" :key="n" class="animate-pulse space-y-3 rounded-[1.5rem] border border-borde bg-white p-5">
              <div class="h-4 w-1/3 rounded-full bg-gray-200" /><div class="h-4 w-4/5 rounded-full bg-gray-200" />
              <div class="h-16 rounded-xl bg-gray-100" /><div class="h-3 w-2/3 rounded-full bg-gray-200" />
            </div>
            <span class="sr-only">Cargando proyectos…</span>
          </div>

          <!-- Vacío -->
          <div v-else-if="!proyectosFiltrados.length && !errorCarga"
               class="rounded-2xl border border-dashed border-borde bg-white px-4 py-16 text-center shadow-sm">
            <span class="mx-auto mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-rol/10 text-rol-fuerte" aria-hidden="true">
              <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path :d="ambito === 'completados' ? 'M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3' : 'M12 2L2 7l10 5 10-5-10-5zm0 10l-10-5m10 5l10-5m-10 5v10'" />
              </svg>
            </span>
            <h3 class="mb-1 text-lg font-bold text-azul-noche">
              <template v-if="hayFiltrosActivos">Sin resultados con estos filtros</template>
              <template v-else-if="ambito === 'completados'">Todavía no hay proyectos completados</template>
              <template v-else>Todavía no hay proyectos en proceso</template>
            </h3>
            <p class="mx-auto max-w-md text-sm text-texto-secundario">
              <template v-if="hayFiltrosActivos">Prueba a quitar algún filtro.</template>
              <template v-else-if="ambito === 'completados'">Aparecerán aquí cuando los grupos terminen los proyectos validados.</template>
              <template v-else>Crea tu primer proyecto en el Taller de Ideas.</template>
            </p>
            <div class="mt-5 flex flex-wrap justify-center gap-2">
              <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros" class="accion-secundaria">Quitar filtros</button>
              <button v-else-if="ambito === 'proceso' && !authStore.isEmpresa" type="button"
                      @click="router.push({ name: 'startup-day-crear' })" class="accion-principal">Crear proyecto</button>
            </div>
          </div>

          <!-- Tarjetas -->
          <div v-else class="grid grid-cols-1 items-start gap-4" :class="columnasProyectos">
            <ProyectoCard
              v-for="p in proyectosPagina" :key="p.uuid"
              :proyecto="p"
              :resaltar-editar="filtroEstados.length === 1 && filtroEstados[0] === 'en_edicion'"
              :modulo-activo="filtroModulo"
              @eliminar="abrirModalEliminar"
            />
          </div>

          <!-- Paginación -->
          <div v-if="!cargando && totalPaginas > 1" class="mt-5 flex flex-col flex-wrap items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-texto-secundario">
              Mostrando {{ proyectosPagina.length }} de {{ proyectosFiltrados.length }} proyectos
            </p>
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación de proyectos">
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
      class="fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 z-[60] flex items-center gap-3
             px-5 py-3.5 rounded-2xl shadow-xl text-sm font-bold
             max-w-[calc(100vw-2rem)] sm:max-w-sm bg-[#1a2332] text-white border border-white/10"
    >
      <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      <span class="flex-1 min-w-0 break-words">{{ snackbar.mensaje }}</span>
      <button
        v-if="snackbar.accion"
        @click="snackbar.accion.fn(); snackbar.visible = false"
        class="ml-1 shrink-0 inline-flex h-9 items-center px-3 py-1.5 rounded-full bg-amber-400 text-[#1a2332] text-[10px] font-black uppercase tracking-widest hover:bg-amber-300 transition-all"
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
/* Los botones resaltados por el tour conservan su forma de píldora */
.accion-principal.tour-active,
.accion-secundaria.tour-active {
  border-radius: 9999px;
}

.tour-seccion-blur {
  filter: blur(2px);
  opacity: 0.4;
  pointer-events: none;
  transition: filter 0.3s ease, opacity 0.3s ease;
}
</style>
