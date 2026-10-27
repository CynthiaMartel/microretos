<!-- Ruta: /retos (name: biblioteca). Antes vivía en /biblioteca — ver router/index.js. -->
<script setup>
import { formatearFecha } from '../utils/fechas.js';
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import AvisoEmpresaBorrada from '../components/AvisoEmpresaBorrada.vue';
import MiniaturaReto from '../components/MiniaturaReto.vue'
import { iconoFiltro } from '../config/iconosFiltro.js';
import { usePaginacion } from '../composables/usePaginacion.js'
import { modulosDeReto, modulosDisponibles as listarModulos } from '../utils/modulos.js'
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth'
import api from '../api.js';
import LoginModal from '../components/LoginModal.vue';
import EliminarMicrorretoModal from '../components/EliminarMicrorretoModal.vue';
import { useCursoAcademicoStore, cursoDeReto } from '../stores/cursoAcademico.js';
import SelectorCurso from '../components/SelectorCurso.vue';
import FiltrosPanel from '../components/FiltrosPanel.vue';
import FiltroSegmentado from '../components/FiltroSegmentado.vue';
import FiltroSelect from '../components/FiltroSelect.vue';
import TituloResultados from '../components/TituloResultados.vue';
import FamiliaModulos from '../components/FamiliaModulos.vue';
import { usePdfExport } from '../composables/usePdfExport.js';
import { useRoleTheme } from '../composables/useRoleTheme.js';

const router = useRouter();
const route = useRoute();
const isLoaded = ref(false);
const microretos = ref([]);
const centros = ref([]);
const cargando = ref(true);
const familias = ref([]);
const showLogin = ref(false);
const accionPendiente = ref(null);
// Explorador de retos (diseño biblioteca_retos.png): la familia es un filtro más (varias a la
// vez) del panel «Filtros rápidos». Va en la URL (?familia=…, se puede repetir).
const filtroFamilias = ref([]);


const authStore = useAuthStore();
const { theme } = useRoleTheme();
const { descargarPDF, descargarPDFGrupo } = usePdfExport();


// Estado de generación de PDF de grupo (evita dobles clics y muestra feedback)
const generandoPDFGrupo = ref(false);

// ── FILTROS ──────────────────────────────────────────────
const filtroCentro      = ref('');
const filtroCiclo       = ref('');
const filtroModulo      = ref('');        // nombre de módulo (dentro de familia + ciclo)
const filtroNivel       = ref('');        // '' | 'Bajo' | 'Medio' | 'Alto'
const filtroCurso       = ref('');        // '' | '1' | '2'
// Tipo de reto (varios a la vez): 'real' (empresa real) · 'ficticia' (empresa ficticia) ·
// 'simulada' (información simulada con IA). Se cumplen si el reto encaja en alguno.
const filtroTipos       = ref([]);
const filtroEmpresa     = ref('');
const orden             = ref('recientes');  // 'recientes' | 'antiguos' | 'titulo'
// «Filtros rápidos»: visible de entrada en escritorio y plegado en móvil; «Más filtros» lo alterna.
const panelFiltrosAbierto = ref(typeof window === 'undefined' || window.innerWidth >= 1024);
// Columnas de tarjetas según el ancho que deja el panel lateral (abierto en lg+ le quita ~18rem).
const columnasRetos = computed(() => panelFiltrosAbierto.value
  ? 'md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3'
  : 'md:grid-cols-2 xl:grid-cols-3');
const familiasExpandidas  = ref(false);
const errorCarga        = ref('');
const menuReto          = ref(null);         // id del reto con el menú «⋯» abierto
// Cierre de los menús de las tarjetas al pulsar fuera: un overlay fixed no sirve, porque queda
// por encima del menú (el contenedor de la vista es su propio contexto de apilamiento) y se
// tragaba los clics en las opciones. Cuenta como «dentro» todo lo marcado con data-menu-raiz.
function cerrarMenusAlPulsarFuera(e) {
  if (!e.target.closest?.('[data-menu-raiz]')) { menuReto.value = null }
}
watch(() => !!menuReto.value, (abierto) => {
  document[abierto ? 'addEventListener' : 'removeEventListener']('click', cerrarMenusAlPulsarFuera, true)
});
onUnmounted(() => document.removeEventListener('click', cerrarMenusAlPulsarFuera, true));

const VISTA_KEY = 'dualab_retos_vista';
const vista = ref((() => { try { return localStorage.getItem(VISTA_KEY) || 'cuadricula' } catch { return 'cuadricula' } })());
watch(vista, v => { try { localStorage.setItem(VISTA_KEY, v) } catch { /* sin almacenamiento */ } });
const busqueda          = ref('');

// Docentes y admins docentes solo pueden ver su propio centro
const esCentroRestringido = computed(() => authStore.isDocente || authStore.isAdmin);

const centrosDisponibles = computed(() => centros.value.map(c => c.nombre).sort());

// Total de microretos del centro seleccionado (para mostrar en el botón de descarga)
// Curso académico compartido (store): conteos, filtros, listado y PDFs salen de esta lista.
// Las bajas siguen trabajando sobre `microretos` (la lista completa).
const cursoStore      = useCursoAcademicoStore();
const microretosCurso = computed(() => microretos.value.filter(r => cursoStore.coincide(cursoDeReto(r))));
const cursosConDatos  = computed(() => [...new Set(microretos.value.map(cursoDeReto))]);

const countCentroActual = computed(() => {
  if (!filtroCentro.value) return microretosCurso.value.length;
  return microretosCurso.value.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value).length;
});

// Base para opciones y conteos: curso académico + centro (+ familias elegidas).
const baseCentro = computed(() => filtroCentro.value
  ? microretosCurso.value.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value)
  : microretosCurso.value);
const baseFamilias = computed(() => filtroFamilias.value.length
  ? baseCentro.value.filter(m => filtroFamilias.value.includes(m.familia))
  : baseCentro.value);

const ciclosDisponibles = computed(() => {
  let d = baseFamilias.value;
  return [...new Set(d.map(m => m.ciclo).filter(Boolean))].sort();
});

// Módulos de los retos de la familia (y del ciclo, si hay uno elegido)
const modulosDisponibles = computed(() => {
  let d = baseFamilias.value;
  if (filtroCiclo.value)  d = d.filter(m => m.ciclo === filtroCiclo.value);
  return listarModulos(d, modulosDeReto);
});
// El módulo depende del ciclo: al cambiar de ciclo se quita
watch(filtroCiclo, () => { filtroModulo.value = ''; });

const cursosDisponibles = computed(() => {
  let d = baseFamilias.value;
  // 'ambos_cursos' se muestra aparte (botón fijo), no mezclado en el orden numérico 1º/2º.
  return [...new Set(d.map(m => m.curso).filter(v => v != null && v !== 'ambos_cursos'))].sort((a, b) => a - b);
});

// Un reto "ambos cursos" vale para 1º y 2º a la vez (cruza módulos de los dos años).
const hayRetosAmbosCursos = computed(() => {
  let d = baseFamilias.value;
  return d.some(m => m.curso === 'ambos_cursos');
});

// Conteo por nivel dentro de familia+centro (sin aplicar otros filtros activos)
const conteoNiveles = computed(() => {
  const r = { Bajo: 0, Medio: 0, Alto: 0, total: 0 };
  let d = baseFamilias.value;
  d.forEach(m => { r.total++; if (m.nivel_grupo && m.nivel_grupo in r) r[m.nivel_grupo]++; });
  return r;
});

const microretosFiltrados = computed(() => {
  const q = busqueda.value.toLowerCase().trim();
  return microretosCurso.value.filter(reto => {
    const centro = reto.centro_educativo || reto.centro;
    const t = filtroTipos.value;
    const tipoOk = !t.length
      || (t.includes('real') && reto.empresa_es_simulada !== true)
      || (t.includes('ficticia') && reto.empresa_es_simulada === true)
      || (t.includes('simulada') && reto.es_simulado === true);
    return (
      (!filtroFamilias.value.length || filtroFamilias.value.includes(reto.familia)) &&
      (!filtroEmpresa.value || reto.empresa_nombre === filtroEmpresa.value) &&
      (filtroCentro.value === '' || centro === filtroCentro.value) &&
      (filtroCiclo.value  === '' || reto.ciclo === filtroCiclo.value) &&
      (filtroModulo.value === '' || modulosDeReto(reto).includes(filtroModulo.value)) &&
      (filtroNivel.value  === '' || reto.nivel_grupo === filtroNivel.value) &&
      (filtroCurso.value  === ''
        || String(reto.curso) === filtroCurso.value
        // 2º puede con todo: su propio curso, el de 1º (ya cursado) y los transversales.
        || (filtroCurso.value === '2' && (reto.curso === 'ambos_cursos' || String(reto.curso) === '1'))
      ) &&
      tipoOk &&
      (!q || [reto.titulo, reto.pregunta_reto, reto.empresa_nombre, reto.ciclo]
        .some(f => f && f.toLowerCase().includes(q)))
    );
  });
});

const hayFiltrosActivos = computed(() =>
  !!(filtroFamilias.value.length || filtroTipos.value.length || filtroEmpresa.value
     || filtroCiclo.value || filtroModulo.value || filtroNivel.value || filtroCurso.value || busqueda.value)
);

// Opciones de los filtros del panel (FiltroSegmentado admite count por opción)
const opcionesDificultad = computed(() => [
  { value: '',      label: 'Todos', count: conteoNiveles.value.total },
  { value: 'Bajo',  label: 'Bajo',  count: conteoNiveles.value.Bajo },
  { value: 'Medio', label: 'Medio', count: conteoNiveles.value.Medio },
  { value: 'Alto',  label: 'Alto',  count: conteoNiveles.value.Alto },
]);
// Dificultad como desplegable (diseño): la opción vacía la pone FiltroSelect («Todas»).
const opcionesDificultadSelect = computed(() => opcionesDificultad.value
  .filter(o => o.value).map(o => ({ value: o.value, label: `${o.label} (${o.count})` })));
const opcionesCurso = computed(() => [
  { value: '', label: 'Todos' },
  ...cursosDisponibles.value.map(c => ({ value: String(c), label: `${c}º` })),
  ...(hayRetosAmbosCursos.value ? [{ value: 'ambos_cursos', label: 'Ambos' }] : []),
]);

const conteoPorFamilia = computed(() => {
  const mapa = {};
  baseCentro.value.forEach(m => { if (m.familia) mapa[m.familia] = (mapa[m.familia] || 0) + 1; });
  return mapa;
});
// Panel «Filtros rápidos»: familias con retos (más numerosas primero), 6 visibles + «Ver todas».
const familiasPanel = computed(() =>
  Object.entries(conteoPorFamilia.value).map(([nombre, n]) => ({ nombre, n })).sort((a, b) => b.n - a.n || a.nombre.localeCompare(b.nombre)));
const familiasPanelVisibles = computed(() => {
  const todas = familiasPanel.value;
  if (familiasExpandidas.value || todas.length <= 6) return todas;
  // Las marcadas siempre a la vista aunque no estén entre las 6 primeras.
  return [...todas.slice(0, 6), ...todas.slice(6).filter(f => filtroFamilias.value.includes(f.nombre))];
});
const conteoTipos = computed(() => ({
  real:     baseFamilias.value.filter(m => m.empresa_es_simulada !== true).length,
  ficticia: baseFamilias.value.filter(m => m.empresa_es_simulada === true).length,
  simulada: baseFamilias.value.filter(m => m.es_simulado === true).length,
}));
const empresasDisponibles = computed(() =>
  [...new Set(baseFamilias.value.map(m => m.empresa_nombre).filter(Boolean))].sort((a, b) => a.localeCompare(b)));
// (En la plantilla los refs llegan desenvueltos: por eso una función por filtro.)
const alternar = (lista, valor) => lista.includes(valor) ? lista.filter(v => v !== valor) : [...lista, valor];
function alternarFamilia(nombre) { filtroFamilias.value = alternar(filtroFamilias.value, nombre); }
function alternarTipo(tipo) { filtroTipos.value = alternar(filtroTipos.value, tipo); }

// Orden y paginación (12 por página, ver POR_PAGINA)
const fechaReto = r => new Date(r.creado_en || r.created_at || 0).getTime();
const retosOrdenados = computed(() => {
  const lista = [...microretosFiltrados.value];
  if (orden.value === 'titulo') return lista.sort((a, b) => (a.titulo || '').localeCompare(b.titulo || ''));
  return lista.sort((a, b) => orden.value === 'antiguos' ? fechaReto(a) - fechaReto(b) : fechaReto(b) - fechaReto(a));
});
// 12 por página: se reparte entero en 1, 2, 3 o 4 columnas (con 9, a 2 columnas quedaba
// una tarjeta sola en la última fila y un hueco a su derecha)
const POR_PAGINA = 12;
const { pagina, totalPaginas, itemsPagina: retosPagina } = usePaginacion(retosOrdenados, POR_PAGINA);
watch([microretosFiltrados, orden], () => { pagina.value = 0; });
const paginasVisibles = computed(() => {
  const n = totalPaginas.value, a = pagina.value;
  const ini = Math.max(0, Math.min(a - 2, n - 5)), fin = Math.min(n, ini + 5);
  return Array.from({ length: fin - ini }, (_, i) => ini + i);
});
function irAPagina(i) {
  pagina.value = i;
  document.getElementById('resultados-retos')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}


// Dificultad del reto con colores de los tokens (texto ≥ 4,5:1 sobre su fondo)
const nivelClase = (nivel) => ({
  Bajo:  'bg-empresas/10 text-empresas-dark',
  Medio: 'bg-amber-50 text-amber-800',
  Alto:  'bg-alumnos/15 text-alumnos-dark',
}[nivel] || 'bg-gray-100 text-gray-700');

// ── CICLO DE VIDA ─────────────────────────────────────────
const cargarDatos = async () => {
  cargando.value = true;
  errorCarga.value = '';
  try {
    const [resMicroretos, resFamilias, resCentros] = await Promise.all([
      api.get('/microretos'),
      api.get('/familias'),
      api.get('/centros'),
    ]);
    microretos.value = resMicroretos.data
      .filter(m => {
        const centro = m.centro_educativo || m.centro;
        return centro && centro !== 'Centro Desconocido' && m.familia && m.familia !== 'Familia Desconocida';
      })
      .map(m => ({
        ...m,
        nivel_grupo: m.nivel_grupo === 'Básico' ? 'Bajo' : m.nivel_grupo,
      }));
    familias.value = resFamilias.data.map(f =>
      typeof f === 'string' ? { nombre: f, imagen_url: null } : f
    );
    centros.value = resCentros.data;
    await nextTick();
    if (esCentroRestringido.value && authStore.userCentroNombre) {
      filtroCentro.value = authStore.userCentroNombre;
    } else if (centrosDisponibles.value.length > 0) {
      filtroCentro.value = centrosDisponibles.value[0];
    }
  } catch (error) {
    if (error.response?.status === 401) {
      accionPendiente.value = { tipo: 'cargar' };
      showLogin.value = true;
    } else {
      console.error('Error al cargar la biblioteca:', error);
      errorCarga.value = 'No se pudieron cargar los retos. Comprueba la conexión e inténtalo de nuevo.';
    }
  } finally {
    cargando.value = false;
  }
};

onMounted(async () => {
  setTimeout(() => { isLoaded.value = true; }, 100);
  if (!authStore.isAuthenticated) {
    accionPendiente.value = { tipo: 'cargar' };
    showLogin.value = true;
    cargando.value = false;
    return;
  }
  await cargarDatos();
});

// Niveles elegidos para el título de los resultados (TituloResultados): una sola familia →
// ciclo → módulo. Quitar un nivel quita también los que dependen de él.
const nivelesTitulo = computed(() => {
  const niveles = [];
  if (filtroFamilias.value.length === 1) {
    niveles.push({ etiqueta: 'Familia profesional', valor: filtroFamilias.value[0],
      subir: () => { filtroCiclo.value = ''; filtroModulo.value = ''; },
      quitar: () => { filtroFamilias.value = []; filtroCiclo.value = ''; filtroModulo.value = ''; } });
  }
  if (filtroCiclo.value) {
    niveles.push({ etiqueta: 'Ciclo formativo', valor: filtroCiclo.value,
      subir: () => { filtroModulo.value = ''; }, quitar: () => { filtroCiclo.value = ''; } });
  }
  if (filtroModulo.value) {
    niveles.push({ etiqueta: 'Módulo', valor: filtroModulo.value, subir: () => {}, quitar: () => { filtroModulo.value = ''; } });
  }
  return niveles;
});
const familiaEnTitulo = computed(() => nivelesTitulo.value[0]?.etiqueta === 'Familia profesional');

// Las familias marcadas viven en la URL (?familia=…, se puede repetir): un enlace con
// ?familia=X (p. ej. desde fuera) abre el explorador ya filtrado.
watch(() => route.query.familia, (familia) => {
  const lista = familia ? (Array.isArray(familia) ? familia : [familia]).map(String) : [];
  if (lista.join('|') !== filtroFamilias.value.join('|')) filtroFamilias.value = lista;
}, { immediate: true });
watch(filtroFamilias, (lista) => {
  const actual = route.query.familia ? [].concat(route.query.familia).map(String) : [];
  if (lista.join('|') === actual.join('|')) return;
  router.replace({ query: { ...route.query, familia: lista.length ? lista : undefined } });
});

// ── ACCIONES ──────────────────────────────────────────────
const resetFiltrosDetalle = () => {
  filtroFamilias.value = [];
  filtroTipos.value = [];
  filtroEmpresa.value = '';
  filtroCiclo.value = '';
  filtroModulo.value = '';
  filtroNivel.value = '';
  filtroCurso.value = '';
  busqueda.value = '';
};

const seleccionarCentro = (centro) => {
  filtroCentro.value = centro;
  resetFiltrosDetalle();
};

const limpiarFiltros = () => resetFiltrosDetalle();

const estaAutenticado = () => authStore.isAuthenticated;

const irADetalle = (reto) => {
  if (!estaAutenticado()) {
    accionPendiente.value = { tipo: 'ver', payload: reto };
    showLogin.value = true;
    return;
  }
  router.push({ name: 'detalle-microreto', params: { id: reto.uuid || reto.id } });
};

const onLoginSuccess = async () => {
  if (!accionPendiente.value) return;
  const { tipo, payload } = accionPendiente.value;
  accionPendiente.value = null;
  if (tipo === 'cargar') { await cargarDatos(); }
  else if (tipo === 'eliminar') { retoAEliminar.value = payload; modalVisible.value = true; }
  else if (tipo === 'ver') router.push({ name: 'detalle-microreto', params: { id: payload.uuid || payload.id } });
};

// ── DESCARGA PDF DE GRUPO ─────────────────────────────────────────────────────

// Ayudante interno: espera a que Vue renderice el estado "Generando...",
// ejecuta la generación (síncrona) y libera el flag al terminar.
const _lanzarGeneracion = (fn) => {
  generandoPDFGrupo.value = true;
  nextTick(() => {
    setTimeout(() => {
      try { fn(); } finally { generandoPDFGrupo.value = false; }
    }, 50);
  });
};

// Nivel 1: descarga TODOS los microretos del centro seleccionado
const descargarGrupoCentro = () => {
  const retos = filtroCentro.value
    ? microretosCurso.value.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value)
    : microretosCurso.value;
  if (!retos.length) return;
  _lanzarGeneracion(() => {
    const titulo    = filtroCentro.value || 'Todos los centros';
    const subtitulo = `${retos.length} micro-reto${retos.length !== 1 ? 's' : ''}`;
    descargarPDFGrupo(retos, titulo, subtitulo);
  });
};

// Nivel 3: descarga exactamente los microretos visibles (respeta todos los filtros activos)
const descargarGrupoFiltrado = () => {
  const retos = microretosFiltrados.value;
  if (!retos.length) return;
  _lanzarGeneracion(() => {
    let titulo = filtroFamilias.value.length ? filtroFamilias.value.join(', ') : 'Retos';
    if (filtroCentro.value) titulo += ` – ${filtroCentro.value}`;
    const partes = [];
    if (filtroCiclo.value)        partes.push(`Ciclo: ${filtroCiclo.value}`);
    if (filtroNivel.value)        partes.push(`Nivel: ${filtroNivel.value}`);
    if (filtroCurso.value)        partes.push(`${filtroCurso.value}º curso`);
    if (filtroTipos.value.length) partes.push(`Tipo: ${filtroTipos.value.join(', ')}`);
    if (filtroEmpresa.value)      partes.push(`Empresa: ${filtroEmpresa.value}`);
    const subtitulo = `${retos.length} micro-reto${retos.length !== 1 ? 's' : ''}${partes.length ? ' · ' + partes.join(' · ') : ''}`;
    descargarPDFGrupo(retos, titulo, subtitulo);
  });
};

const modalVisible   = ref(false);
const retoAEliminar  = ref(null);

const abrirModalEliminar = (reto) => {
  if (!estaAutenticado()) {
    accionPendiente.value = { tipo: 'eliminar', payload: reto };
    showLogin.value = true;
    return;
  }
  retoAEliminar.value = reto;
  modalVisible.value = true;
};

const cancelarEliminar = () => { modalVisible.value = false; retoAEliminar.value = null; };

// Escaparate de la web pública (dualab.es/info.dualab.es) — opt-in manual por reto, solo
// superadmin (MicroretoPolicy::publicarEscaparate).
const togglePublicoEnCurso = ref(new Set());
const toggleVisiblePublico = async (reto) => {
  if (togglePublicoEnCurso.value.has(reto.id)) return;
  togglePublicoEnCurso.value.add(reto.id);
  try {
    const { data } = await api.patch(`/microretos/${reto.id}/visible-publico`);
    reto.visible_publico = data.visible_publico;
    mostrarSnack(
      data.visible_publico ? 'Reto publicado en la web pública.' : 'Reto oculto de la web pública.',
      'ok',
    );
  } catch {
    mostrarSnack('No se pudo actualizar la visibilidad pública.', 'error');
  } finally {
    togglePublicoEnCurso.value.delete(reto.id);
  }
};

const onRetoEliminado = ({ id, titulo }) => {
  microretos.value = microretos.value.filter(m => m.id !== id);
  cancelarEliminar();
  // La papelera de "Base de datos" es solo superadmin — para el resto de roles,
  // el mensaje se queda sin acción de "ir a" (ya no tienen esa ruta disponible).
  mostrarSnack(
    `"${titulo}" movido a la papelera.`,
    'ok',
    authStore.isSuperAdmin ? { label: 'Ir a la papelera', fn: () => router.push({ name: 'papelera' }) } : null,
  );
};

// ── SNACKBAR ─────────────────────────────────────────────
const snackbar = ref({ visible: false, mensaje: '', tipo: 'ok', accion: null });
function mostrarSnack(mensaje, tipo = 'ok', accion = null) {
  snackbar.value = { visible: true, mensaje, tipo, accion };
  setTimeout(() => { snackbar.value.visible = false; }, 5000);
}
</script>

<template>
  <!-- Explorador de retos (diseño biblioteca_retos.png): cabecera + filtros + panel «Filtros
       rápidos» a la izquierda + resultados en cuadrícula o lista con paginación. -->
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche" @keydown.esc="menuReto = null">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion plano cinta halos antetitulo="Retos y proyectos" titulo="Explorador de" destacado="Retos" color="text-centros"
                       :volver="authStore.isEmpresa ? null : '/seccion/retos-proyectos'"
                       subtitulo="Encuentra, filtra y reutiliza los desafíos generados por la Inteligencia Artificial para tus alumnos.">
        <div class="flex flex-wrap items-start gap-2">
          <!-- Tarjeta «Retos con IA» (solo escritorio): lleva al generador -->
          <RouterLink v-if="!authStore.isEmpresa" :to="{ name: 'microretos' }"
                      class="hidden max-w-xs items-center gap-3 rounded-2xl border border-borde bg-white/90 p-4 shadow-sm transition-colors hover:border-rol/40 xl:flex">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte" aria-hidden="true">
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            </span>
            <span class="min-w-0">
              <span class="block text-sm font-bold text-azul-noche">Retos con IA</span>
              <span class="block text-xs leading-snug text-texto-secundario">Genera retos adaptados a tus ciclos, módulos y objetivos.</span>
            </span>
          </RouterLink>
          <template v-if="authStore.isSuperAdmin">
            <!-- Descarga de todos los retos del centro: solo superadmin -->
            <button v-if="filtroCentro && countCentroActual > 0 && !cargando" type="button"
                    @click="descargarGrupoCentro" :disabled="generandoPDFGrupo"
                    class="accion-secundaria disabled:cursor-wait disabled:opacity-60"
                    :title="`Descargar todos los retos de ${filtroCentro}`">
              <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/></svg>
              {{ generandoPDFGrupo ? 'Generando…' : `PDF centro (${countCentroActual})` }}
            </button>
            <!-- Papelera de «Base de datos»: solo superadmin -->
            <button type="button" @click="router.push({ name: 'papelera' })" class="accion-icono"
                    title="Ver elementos eliminados en la papelera" aria-label="Papelera">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </template>
        </div>
      </CabeceraSeccion>

      <!-- Filtros principales (FiltrosPanel común): buscador + curso + Generar · fila de filtros -->
      <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar por título, empresa, ciclo o pregunta del reto…">
        <template #curso>
          <div class="flex flex-wrap items-center gap-2">
            <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
            <RouterLink v-if="!authStore.isEmpresa" :to="{ name: 'microretos' }" class="accion-principal">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
              Generar con IA
            </RouterLink>
            <!-- «Más filtros» en la fila del buscador (en la de filtros, con 4 o más, bajaba solo a otra fila) -->
            <button type="button" @click="panelFiltrosAbierto = !panelFiltrosAbierto" :aria-expanded="panelFiltrosAbierto"
                    aria-controls="filtros-rapidos" :title="panelFiltrosAbierto ? 'Ocultar los filtros rápidos' : 'Mostrar los filtros rápidos'"
                    :aria-label="'Más filtros'"
                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-full border px-5 text-sm font-semibold transition-colors"
                    :class="panelFiltrosAbierto ? 'border-rol-fuerte/30 bg-rol/10 text-rol-fuerte' : 'border-rol-fuerte/30 bg-white text-rol-fuerte hover:bg-rol-fuerte/5'">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    <span>Más filtros</span>
                    </button>
          </div>
        </template>

        <!-- Centro: superadmin y empresa eligen; docente / admin docente ven solo el suyo -->
        <FiltroSelect v-if="!esCentroRestringido" label="Centro educativo"
          :model-value="filtroCentro" @update:model-value="seleccionarCentro"
          :opciones="centrosDisponibles" todos="Todos los centros"
          title="Filtra retos del centro de formación profesional seleccionado." />
        <div v-else class="flex min-w-0 max-w-full items-end gap-2.5">
          <span aria-hidden="true" class="mb-px inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="iconoFiltro('Centro educativo')" /></svg>
          </span>
          <div class="min-w-0">
            <p class="mb-1 text-xs font-semibold text-azul-noche">Centro educativo</p>
            <p class="flex h-9 min-w-0 items-center rounded-xl border border-borde bg-gray-50 px-3 text-sm font-semibold text-azul-noche">
              <span class="truncate">{{ authStore.userCentroNombre || filtroCentro }}</span>
            </p>
          </div>
        </div>

        <FiltroSelect label="Ciclo formativo" v-model="filtroCiclo" :opciones="ciclosDisponibles" todos="Todos los ciclos"
          title="Filtra por ciclo formativo al que está orientado el reto." />
        <FiltroSelect v-if="modulosDisponibles.length" label="Módulo" v-model="filtroModulo" :opciones="modulosDisponibles" todos="Todos los módulos"
          title="Filtra por módulo profesional. Los retos de varios módulos aparecen en cada uno." />
        <FiltroSegmentado label="Nivel" v-model="filtroCurso" :opciones="opcionesCurso"
          title="Nivel del ciclo: 1º (primer año) o 2º (segundo año). Ambos cursos: vale para 1º y 2º." />
        <FiltroSelect label="Dificultad" v-model="filtroNivel" :opciones="opcionesDificultadSelect" todos="Todas"
          title="Dificultad del reto: Bajo (introductorio), Medio (aplicado), Alto (avanzado)." />
      </FiltrosPanel>

      <!-- Error de carga -->
      <div v-if="errorCarga && !cargando" class="flex flex-col items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center" role="alert">
        <p class="min-w-0 flex-1 text-sm font-medium text-red-700">{{ errorCarga }}</p>
        <button type="button" @click="cargarDatos" class="inline-flex h-10 items-center rounded-full bg-white px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Reintentar</button>
      </div>

      <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        <!-- ═══ Filtros rápidos (panel lateral; en < lg se abre con «Más filtros») ═══ -->
        <aside id="filtros-rapidos" aria-label="Filtros rápidos"
               class="w-full shrink-0 rounded-2xl border border-borde bg-white p-4 shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:w-64 xl:w-72"
               :class="panelFiltrosAbierto ? 'block' : 'hidden'">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-azul-noche">Filtros rápidos</h2>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros"
                    class="inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">Limpiar</button>
          </div>

          <div class="border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-familia">
            <p id="titulo-filtro-familia" class="mb-1.5 text-sm font-semibold text-azul-noche">Familia profesional</p>
            <label v-for="f in familiasPanelVisibles" :key="f.nombre"
                   class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroFamilias.includes(f.nombre)" @change="alternarFamilia(f.nombre)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche" :title="f.nombre">{{ f.nombre }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ f.n }}</span>
            </label>
            <p v-if="!familiasPanel.length && !cargando" class="px-1 py-1 text-sm text-texto-secundario">Sin retos en este curso.</p>
            <button v-if="familiasPanel.length > 6" type="button" @click="familiasExpandidas = !familiasExpandidas"
                    class="mt-1 inline-flex h-9 items-center rounded-full px-1 text-sm font-semibold text-rol-fuerte hover:underline">
              {{ familiasExpandidas ? 'Ver menos' : `Ver todas (${familiasPanel.length})` }}
            </button>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-tipo">
            <p id="titulo-filtro-tipo" class="mb-1.5 text-sm font-semibold text-azul-noche">Tipo de reto</p>
            <label v-for="t in [
                     { v: 'real', label: 'Empresa real', n: conteoTipos.real, cls: 'bg-empresas/10 text-empresas-dark', icono: 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01' },
                     { v: 'ficticia', label: 'Empresa ficticia', n: conteoTipos.ficticia, cls: 'bg-amber-50 text-amber-800', icono: 'M3 21h18M5 21V7l7-4 7 4v14M9.5 10.5a2.5 2.5 0 114 2c-.8.5-1.5 1-1.5 2M12 18h.01' },
                     { v: 'simulada', label: 'Información simulada', n: conteoTipos.simulada, cls: 'bg-gray-100 text-azul-noche', icono: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                   ]" :key="t.v" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroTipos.includes(t.v)" @change="alternarTipo(t.v)"
                     class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg" :class="t.cls" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="t.icono"/></svg>
              </span>
              <span class="min-w-0 flex-1 text-sm text-azul-noche">{{ t.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ t.n }}</span>
            </label>
          </div>

          <details class="group mt-3 border-t border-borde pt-3" :open="!!filtroEmpresa">
            <summary class="flex cursor-pointer list-none items-center justify-between py-1 text-sm font-semibold text-azul-noche">
              Empresa colaboradora
              <svg class="h-4 w-4 text-texto-secundario transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <label for="filtro-empresa" class="sr-only">Empresa colaboradora</label>
            <select id="filtro-empresa" v-model="filtroEmpresa" class="mt-2 h-10 w-full rounded-xl px-3 text-sm">
              <option value="">Todas las empresas</option>
              <option v-for="e in empresasDisponibles" :key="e" :value="e">{{ e }}</option>
            </select>
          </details>
        </aside>

        <!-- ═══ Resultados ═══ -->
        <section id="resultados-retos" class="min-w-0 flex-1 scroll-mt-24" aria-label="Retos">
          <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <TituloResultados :niveles="nivelesTitulo" :cargando="cargando">
              <template v-if="cargando">Cargando retos…</template>
              <template v-else>{{ microretosFiltrados.length }} {{ microretosFiltrados.length === 1 ? 'reto encontrado' : 'retos encontrados' }}</template>
            </TituloResultados>
            <!-- Chips de filtros activos -->
            <div v-if="(filtroFamilias.length && !familiaEnTitulo) || filtroTipos.length" class="flex w-full flex-wrap gap-1.5 sm:order-last">
              <!-- Una sola familia ya está en el título de los resultados: sin chip repetido -->
              <button v-for="f in (familiaEnTitulo ? [] : filtroFamilias)" :key="'f-' + f" type="button" @click="alternarFamilia(f)"
                      class="inline-flex h-9 max-w-full items-center gap-1.5 rounded-full bg-rol/10 px-3 text-xs font-semibold text-rol-fuerte hover:bg-rol/15"
                      :aria-label="`Quitar filtro ${f}`">
                <span class="truncate">{{ f }}</span><span aria-hidden="true">✕</span>
              </button>
              <button v-for="t in filtroTipos" :key="'t-' + t" type="button" @click="alternarTipo(t)"
                      class="inline-flex h-9 items-center gap-1.5 rounded-full bg-gray-100 px-3 text-xs font-semibold text-azul-noche hover:bg-gray-200"
                      :aria-label="`Quitar filtro ${t}`">
                {{ { real: 'Empresa real', ficticia: 'Empresa ficticia', simulada: 'Información simulada' }[t] }}<span aria-hidden="true">✕</span>
              </button>
            </div>
            <button v-if="microretosFiltrados.length && !authStore.isEmpresa" type="button" @click="descargarGrupoFiltrado" :disabled="generandoPDFGrupo"
                    class="inline-flex h-9 items-center gap-1.5 rounded-full border border-rol-fuerte/30 bg-white px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol-fuerte/5 disabled:cursor-wait disabled:opacity-60"
                    :title="hayFiltrosActivos ? 'Descargar los retos con los filtros actuales' : 'Descargar todos los retos listados'">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/></svg>
              {{ generandoPDFGrupo ? 'Generando…' : `PDF (${microretosFiltrados.length})` }}
            </button>
            <label class="flex items-center gap-2 text-sm text-texto-secundario">
              <span class="hidden sm:inline">Ordenar por</span>
              <select v-model="orden" class="h-9 rounded-xl px-3 text-sm text-azul-noche" aria-label="Ordenar retos">
                <option value="recientes">Más recientes</option>
                <option value="antiguos">Más antiguos</option>
                <option value="titulo">Título (A-Z)</option>
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

          <!-- Carga: skeleton con la forma de las tarjetas -->
          <div v-if="cargando" class="grid grid-cols-1 gap-4" :class="columnasRetos" aria-busy="true">
            <div v-for="n in 6" :key="n" class="flex animate-pulse gap-3 rounded-2xl border border-borde bg-white p-4">
              <div class="h-24 w-24 shrink-0 rounded-xl bg-gray-200" />
              <div class="flex-1 space-y-2"><div class="h-3 w-1/3 rounded-full bg-gray-200" /><div class="h-4 w-4/5 rounded-full bg-gray-200" /><div class="h-3 w-full rounded-full bg-gray-200" /><div class="h-3 w-2/3 rounded-full bg-gray-200" /></div>
            </div>
            <span class="sr-only">Cargando retos…</span>
          </div>

          <!-- Vacío -->
          <div v-else-if="!microretosFiltrados.length && !errorCarga"
               class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-rol/10 text-rol-fuerte" aria-hidden="true">
              <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
            </span>
            <h3 class="mt-3 text-lg font-bold text-azul-noche">No hay retos con estos filtros</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">Prueba a quitar algún filtro o genera retos nuevos con IA.</p>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
              <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros" class="accion-secundaria">Limpiar filtros</button>
              <RouterLink v-if="!authStore.isEmpresa" :to="{ name: 'microretos' }" class="accion-principal">Generar con IA</RouterLink>
            </div>
          </div>

          <!-- Tarjetas -->
          <ul v-else-if="microretosFiltrados.length"
              :class="vista === 'cuadricula' ? ['grid grid-cols-1 gap-4', columnasRetos] : 'space-y-3'">
            <li v-for="reto in retosPagina" :key="reto.id"
                class="group flex min-w-0 flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm transition-shadow hover:shadow-md">
              <div class="flex min-w-0 gap-3" :class="vista === 'lista' ? 'sm:items-center' : ''">
                <MiniaturaReto class="flex shrink-0" :class="vista === 'lista' ? 'h-16 w-20' : 'h-20 w-20 sm:h-24 sm:w-24'" />
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold uppercase tracking-wide" :class="nivelClase(reto.nivel_grupo)">
                      Nivel: {{ reto.nivel_grupo || 'N/D' }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold uppercase tracking-wide"
                          :class="reto.empresa_es_simulada ? 'bg-amber-50 text-amber-800' : 'bg-empresas/10 text-empresas-dark'">
                      {{ reto.empresa_es_simulada ? 'Ficticia' : 'Real' }}
                    </span>
                    <span v-if="reto.es_simulado" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-azul-noche"
                          title="Información generada con IA para simulación">
                      Info simulada
                    </span>
                    <AvisoEmpresaBorrada :estado="reto.empresa_estado" />
                  </div>
                  <h3 class="mt-1.5 line-clamp-2 font-heading text-base font-bold leading-snug text-azul-noche" :title="reto.titulo">
                    <button type="button" @click="irADetalle(reto)" class="text-left hover:text-rol-fuerte hover:underline">{{ reto.titulo }}</button>
                  </h3>
                  <p class="mt-1 text-sm leading-snug text-texto-secundario"
                     :class="vista === 'cuadricula' ? 'line-clamp-3' : 'line-clamp-2'">{{ reto.pregunta_reto }}</p>
                </div>
              </div>

              <!-- Familia y módulos diferenciados, con «+N» desplegable (FamiliaModulos, común a las bibliotecas) -->
              <FamiliaModulos class="mt-3" :familia="reto.familia" :modulos="modulosDeReto(reto)" :modulo-activo="filtroModulo" />
              <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-texto-secundario">
                <span v-if="reto.curso" class="inline-flex items-center gap-1.5">
                  {{ reto.curso === 'ambos_cursos' ? '1º y 2º curso' : `${reto.curso}º curso` }}
                </span>
                <span v-if="reto.creado_en || reto.created_at">{{ formatearFecha(reto.creado_en || reto.created_at) }}</span>
              </div>
              <p v-if="reto.diagnostico_desactualizado || reto.interes_desactualizado || reto.diagnostico_modificado_en_reto || reto.interes_modificado_en_reto"
                 class="mt-2 inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800"
                 :title="[reto.diagnostico_modificado_en_reto && 'Diagnóstico ajustado para este reto', reto.diagnostico_desactualizado && 'La empresa actualizó su diagnóstico después', reto.interes_modificado_en_reto && 'Interés ajustado para este reto', reto.interes_desactualizado && 'La empresa cambió su interés después'].filter(Boolean).join(' · ')">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ (reto.diagnostico_desactualizado || reto.interes_desactualizado) ? 'Datos de la empresa actualizados después' : 'Ajustado para este reto' }}
              </p>

              <div class="mt-3 flex items-center gap-2 border-t border-borde pt-3">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-empresas/10 text-empresas-dark" aria-hidden="true">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
                <span class="min-w-0 flex-1 truncate text-sm text-azul-noche" :title="reto.empresa_nombre">{{ reto.empresa_nombre }}</span>
                <button type="button" @click="irADetalle(reto)"
                        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                  Ver reto
                </button>
                <!-- Menú «⋯»: PDF; escaparate y eliminar solo para quien puede gestionar -->
                <div class="relative shrink-0" data-menu-raiz>
                  <button type="button" @click="menuReto = menuReto === reto.id ? null : reto.id"
                          :aria-expanded="menuReto === reto.id" aria-haspopup="menu" :aria-label="`Más acciones de «${reto.titulo}»`"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde text-texto-secundario hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                  </button>
                  <div v-if="menuReto === reto.id" role="menu"
                       class="absolute bottom-full right-0 z-30 mb-1 w-60 rounded-xl border border-borde bg-white py-1 shadow-lg">
                    <button type="button" role="menuitem" @click="menuReto = null; descargarPDF(reto)"
                            class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Descargar PDF</button>
                    <template v-if="!authStore.isEmpresa">
                      <!-- Escaparate de la web pública (frontoffice dualab.es): solo superadmin (lo comprueba
                           el backend, MicroretoPolicy::publicarEscaparate). Mismo aspecto que en Proyectos:
                           turquesa de administraciones, marca «Superadmin» y explicación al pasar el ratón. -->
                      <div v-if="authStore.isSuperAdmin" class="group/escaparate relative">
                        <button type="button" role="menuitem" aria-describedby="ayuda-escaparate-reto"
                                @click="menuReto = null; toggleVisiblePublico(reto)" :disabled="togglePublicoEnCurso.has(reto.id)"
                                class="mx-1 my-0.5 flex w-[calc(100%-0.5rem)] items-center gap-2 rounded-lg bg-administraciones/10 px-3 py-2 text-left text-sm font-semibold text-administraciones-dark hover:bg-administraciones/15 disabled:opacity-50">
                          <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/></svg>
                          <span class="min-w-0 flex-1">{{ reto.visible_publico ? 'Ocultar de la web pública' : 'Publicar en la web pública' }}</span>
                          <span class="shrink-0 rounded-full bg-white/70 px-1.5 py-0.5 text-[11px] font-bold">Superadmin</span>
                        </button>
                        <span id="ayuda-escaparate-reto" role="tooltip"
                              class="pointer-events-none absolute bottom-full right-1 z-40 mb-1 hidden w-60 rounded-xl bg-azul-noche px-3 py-2 text-xs font-normal leading-snug text-white shadow-lg sm:group-hover/escaparate:block sm:group-focus-within/escaparate:block">
                          Escaparate de dualab.es: los retos publicados aparecen en la web pública, visibles para cualquiera sin iniciar sesión. Solo el superadmin puede publicarlos u ocultarlos.
                        </span>
                        <!-- En móvil no hay hover: la explicación va como línea corta bajo la opción -->
                        <p class="px-4 pb-1 text-[11px] leading-snug text-texto-secundario sm:hidden" aria-hidden="true">
                          Visible en dualab.es sin iniciar sesión. Solo el superadmin puede cambiarlo.
                        </p>
                      </div>
                      <button type="button" role="menuitem" @click="menuReto = null; abrirModalEliminar(reto)"
                              class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Eliminar reto</button>
                    </template>
                  </div>
                </div>
              </div>
              <!-- Aviso de que está en la web pública (lo ven todos los que gestionan; solo el superadmin lo cambia) -->
              <p v-if="!authStore.isEmpresa && reto.visible_publico" class="mt-2 inline-flex w-fit items-center gap-1 text-xs font-semibold text-administraciones-dark"
                 title="Publicado en el escaparate de la web pública dualab.es">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Publicado en la web pública
              </p>
            </li>
          </ul>

          <!-- Paginación -->
          <div v-if="!cargando && totalPaginas > 1" class="mt-5 flex flex-col flex-wrap items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-texto-secundario">
              Mostrando {{ retosPagina.length }} de {{ microretosFiltrados.length }} retos
            </p>
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación de retos">
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

  <!-- MODAL ELIMINAR (doble confirmación) -->
  <EliminarMicrorretoModal
    :visible="modalVisible"
    :reto="retoAEliminar"
    @reto-eliminado="onRetoEliminado"
    @cerrar="cancelarEliminar"
  />

  <!-- SNACKBAR -->
  <Transition name="snack">
    <div
      v-if="snackbar.visible"
      class="fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 z-[60] flex items-center gap-3
             px-5 py-3.5 rounded-2xl shadow-xl text-sm font-bold
             max-w-[calc(100vw-2rem)] sm:max-w-sm bg-azul-noche text-white"
    >
      <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      <span class="flex-1 min-w-0 break-words">{{ snackbar.mensaje }}</span>
      <button
        v-if="snackbar.accion"
        @click="snackbar.accion.fn(); snackbar.visible = false"
        class="ml-1 shrink-0 inline-flex h-9 items-center px-3 rounded-full bg-amber-400 text-azul-noche text-xs font-bold uppercase tracking-widest hover:bg-amber-300 transition-all"
      >
        {{ snackbar.accion.label }}
      </button>
    </div>
  </Transition>

  <LoginModal v-model="showLogin" @login-success="onLoginSuccess" />

</template>

<style scoped>
@import "tailwindcss";

::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: #F8FAFC; }
::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: v-bind('theme.hex'); }

.snack-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.snack-leave-active { transition: all 0.2s ease-in; }
.snack-enter-from   { opacity: 0; transform: translateY(12px); }
.snack-leave-to     { opacity: 0; transform: translateY(8px); }

.slide-up-enter-active { transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-leave-active { transition: all 0.2s ease-in; }
.slide-up-enter-from { opacity: 0; transform: translateY(20px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-10px); }

</style>
