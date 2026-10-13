<!-- Ruta: /retos (name: biblioteca). Antes vivía en /biblioteca — ver router/index.js. -->
<script setup>
import { formatearFecha } from '../utils/fechas.js';
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import MigasNavegacion from '../components/MigasNavegacion.vue'
import { migasFamilia } from '../utils/migasFamilia.js'
import { modulosDeReto, modulosDisponibles as listarModulos } from '../utils/modulos.js'
import { ref, computed, onMounted, nextTick, watch } from 'vue';
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
import { usePdfExport } from '../composables/usePdfExport.js';
import { iconoFamilia, colorFamilia } from '../utils/familiaIconos.js';
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
const familiaSeleccionada = ref(null);

const authStore = useAuthStore();
const { theme } = useRoleTheme();
const { descargarPDF, descargarPDFGrupo } = usePdfExport();

const paletteExtra = {
  centros:          {
    hoverBorderText: 'hover:border-centros hover:text-centros',
    hoverText: 'hover:text-centros',
    groupHoverText: 'group-hover:text-centros',
    hoverBorderBg5Text: 'hover:border-centros hover:bg-centros/5 hover:text-centros',
    hoverBorder: 'hover:border-centros',
    hoverBorder40: 'hover:border-centros/40',
    hoverBorder50: 'hover:border-centros/50',
    groupHoverBorder40: 'group-hover:border-centros/40',
    hoverBg15: 'hover:bg-centros/15',
    focusInput: 'focus:border-centros focus:ring-2 focus:ring-centros/10',
    groupHoverBorder30: 'group-hover:border-centros/30',
    groupHoverBorder: 'group-hover:border-centros',
    groupHoverBorder20: 'group-hover:border-centros/20',
    groupHoverBg10: 'group-hover:bg-centros/10',
    groupHoverBg: 'group-hover:bg-centros',
  },
  empresas:         {
    hoverBorderText: 'hover:border-empresas hover:text-empresas',
    hoverText: 'hover:text-empresas',
    groupHoverText: 'group-hover:text-empresas',
    hoverBorderBg5Text: 'hover:border-empresas hover:bg-empresas/5 hover:text-empresas',
    hoverBorder: 'hover:border-empresas',
    hoverBorder40: 'hover:border-empresas/40',
    hoverBorder50: 'hover:border-empresas/50',
    groupHoverBorder40: 'group-hover:border-empresas/40',
    hoverBg15: 'hover:bg-empresas/15',
    focusInput: 'focus:border-empresas focus:ring-2 focus:ring-empresas/10',
    groupHoverBorder30: 'group-hover:border-empresas/30',
    groupHoverBorder: 'group-hover:border-empresas',
    groupHoverBorder20: 'group-hover:border-empresas/20',
    groupHoverBg10: 'group-hover:bg-empresas/10',
    groupHoverBg: 'group-hover:bg-empresas',
  },
  administraciones: {
    hoverBorderText: 'hover:border-administraciones hover:text-administraciones',
    hoverText: 'hover:text-administraciones',
    groupHoverText: 'group-hover:text-administraciones',
    hoverBorderBg5Text: 'hover:border-administraciones hover:bg-administraciones/5 hover:text-administraciones',
    hoverBorder: 'hover:border-administraciones',
    hoverBorder40: 'hover:border-administraciones/40',
    hoverBorder50: 'hover:border-administraciones/50',
    groupHoverBorder40: 'group-hover:border-administraciones/40',
    hoverBg15: 'hover:bg-administraciones/15',
    focusInput: 'focus:border-administraciones focus:ring-2 focus:ring-administraciones/10',
    groupHoverBorder30: 'group-hover:border-administraciones/30',
    groupHoverBorder: 'group-hover:border-administraciones',
    groupHoverBorder20: 'group-hover:border-administraciones/20',
    groupHoverBg10: 'group-hover:bg-administraciones/10',
    groupHoverBg: 'group-hover:bg-administraciones',
  },
  primary:          {
    hoverBorderText: 'hover:border-primary-600 hover:text-primary-700',
    hoverText: 'hover:text-primary-700',
    groupHoverText: 'group-hover:text-primary-700',
    hoverBorderBg5Text: 'hover:border-primary-600 hover:bg-primary-600/5 hover:text-primary-700',
    hoverBorder: 'hover:border-primary-600',
    hoverBorder40: 'hover:border-primary-600/40',
    hoverBorder50: 'hover:border-primary-600/50',
    groupHoverBorder40: 'group-hover:border-primary-600/40',
    hoverBg15: 'hover:bg-primary-600/15',
    focusInput: 'focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10',
    groupHoverBorder30: 'group-hover:border-primary-600/30',
    groupHoverBorder: 'group-hover:border-primary-600',
    groupHoverBorder20: 'group-hover:border-primary-600/20',
    groupHoverBg10: 'group-hover:bg-primary-600/10',
    groupHoverBg: 'group-hover:bg-primary-600',
  },
}

// Estado de generación de PDF de grupo (evita dobles clics y muestra feedback)
const generandoPDFGrupo = ref(false);

// ── FILTROS ──────────────────────────────────────────────
const filtroCentro      = ref('');
const filtroCiclo       = ref('');
const filtroModulo      = ref('');        // nombre de módulo (dentro de familia + ciclo)
const filtroNivel       = ref('');        // '' | 'Bajo' | 'Medio' | 'Alto'
const filtroCurso       = ref('');        // '' | '1' | '2'
const filtroInfoSimulada = ref('');       // '' | 'real' | 'simulada'  → es_simulado
const filtroEmpresaTipo  = ref('');       // '' | 'real' | 'ficticia'  → empresa_es_simulada
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

const ciclosDisponibles = computed(() => {
  let d = microretosCurso.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  return [...new Set(d.map(m => m.ciclo).filter(Boolean))].sort();
});

// Módulos de los retos de la familia (y del ciclo, si hay uno elegido)
const modulosDisponibles = computed(() => {
  let d = microretosCurso.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  if (filtroCiclo.value)  d = d.filter(m => m.ciclo === filtroCiclo.value);
  return listarModulos(d, modulosDeReto);
});
// El módulo depende del ciclo: al cambiar de ciclo se quita
watch(filtroCiclo, () => { filtroModulo.value = ''; });

const cursosDisponibles = computed(() => {
  let d = microretosCurso.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  // 'ambos_cursos' se muestra aparte (botón fijo), no mezclado en el orden numérico 1º/2º.
  return [...new Set(d.map(m => m.curso).filter(v => v != null && v !== 'ambos_cursos'))].sort((a, b) => a - b);
});

// Un reto "ambos cursos" vale para 1º y 2º a la vez (cruza módulos de los dos años).
const hayRetosAmbosCursos = computed(() => {
  let d = microretosCurso.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  return d.some(m => m.curso === 'ambos_cursos');
});

// Conteo por nivel dentro de familia+centro (sin aplicar otros filtros activos)
const conteoNiveles = computed(() => {
  const r = { Bajo: 0, Medio: 0, Alto: 0, total: 0 };
  if (!familiaSeleccionada.value) return r;
  let d = microretosCurso.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  d.forEach(m => { r.total++; if (m.nivel_grupo && m.nivel_grupo in r) r[m.nivel_grupo]++; });
  return r;
});

// Conteo por tipo de empresa (real vs ficticia)
const conteoEmpresaTipo = computed(() => {
  const r = { real: 0, ficticia: 0, total: 0 };
  if (!familiaSeleccionada.value) return r;
  let d = microretosCurso.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  d.forEach(m => {
    r.total++;
    if (m.empresa_es_simulada) r.ficticia++; else r.real++;
  });
  return r;
});

// Conteo por tipo de información (real vs simulada con IA)
const conteoInfoSimulada = computed(() => {
  const r = { real: 0, simulada: 0, total: 0 };
  if (!familiaSeleccionada.value) return r;
  let d = microretosCurso.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  d.forEach(m => {
    r.total++;
    if (m.es_simulado) r.simulada++; else r.real++;
  });
  return r;
});

const microretosFiltrados = computed(() => {
  const q = busqueda.value.toLowerCase().trim();
  return microretosCurso.value.filter(reto => {
    const centro = reto.centro_educativo || reto.centro;
    const infoOk = filtroInfoSimulada.value === ''
      ? true
      : filtroInfoSimulada.value === 'simulada'
        ? reto.es_simulado === true
        : reto.es_simulado !== true;
    const empresaOk = filtroEmpresaTipo.value === ''
      ? true
      : filtroEmpresaTipo.value === 'ficticia'
        ? reto.empresa_es_simulada === true
        : reto.empresa_es_simulada !== true;
    return (
      reto.familia === familiaSeleccionada.value &&
      (filtroCentro.value === '' || centro === filtroCentro.value) &&
      (filtroCiclo.value  === '' || reto.ciclo === filtroCiclo.value) &&
      (filtroModulo.value === '' || modulosDeReto(reto).includes(filtroModulo.value)) &&
      (filtroNivel.value  === '' || reto.nivel_grupo === filtroNivel.value) &&
      (filtroCurso.value  === ''
        || String(reto.curso) === filtroCurso.value
        // 2º puede con todo: su propio curso, el de 1º (ya cursado) y los transversales.
        || (filtroCurso.value === '2' && (reto.curso === 'ambos_cursos' || String(reto.curso) === '1'))
      ) &&
      infoOk && empresaOk &&
      (!q || [reto.titulo, reto.pregunta_reto, reto.empresa_nombre, reto.ciclo]
        .some(f => f && f.toLowerCase().includes(q)))
    );
  });
});

// Migas comunes a las vistas por familia: Familias › <familia> › <ciclo> › <módulo>
const migas = computed(() => migasFamilia({
  raiz:    { label: 'Familias', to: { name: 'biblioteca' } },
  familia: { label: familiaSeleccionada.value, accion: () => { filtroCiclo.value = ''; filtroModulo.value = ''; } },
  ciclo:   filtroCiclo.value ? { label: filtroCiclo.value, accion: () => { filtroModulo.value = ''; } } : null,
  modulo:  filtroModulo.value ? { label: filtroModulo.value } : null,
}));

const hayFiltrosActivos = computed(() =>
  !!(filtroCiclo.value || filtroModulo.value || filtroNivel.value || filtroCurso.value || filtroInfoSimulada.value || filtroEmpresaTipo.value || busqueda.value)
);

// Opciones de los filtros del panel (FiltroSegmentado admite count por opción)
const opcionesDificultad = computed(() => [
  { value: '',      label: 'Todos', count: conteoNiveles.value.total },
  { value: 'Bajo',  label: 'Bajo',  count: conteoNiveles.value.Bajo },
  { value: 'Medio', label: 'Medio', count: conteoNiveles.value.Medio },
  { value: 'Alto',  label: 'Alto',  count: conteoNiveles.value.Alto },
]);
const opcionesEmpresaTipo = computed(() => [
  { value: '',         label: 'Todas',    count: conteoEmpresaTipo.value.total },
  { value: 'real',     label: 'Real',     count: conteoEmpresaTipo.value.real },
  { value: 'ficticia', label: 'Ficticia', count: conteoEmpresaTipo.value.ficticia },
]);
const opcionesInfoSimulada = computed(() => [
  { value: '',         label: 'Toda',     count: conteoInfoSimulada.value.total },
  { value: 'real',     label: 'Real',     count: conteoInfoSimulada.value.real },
  { value: 'simulada', label: 'Simulada', count: conteoInfoSimulada.value.simulada },
]);
const opcionesCurso = computed(() => [
  { value: '', label: 'Todos' },
  ...cursosDisponibles.value.map(c => ({ value: String(c), label: `${c}º` })),
  ...(hayRetosAmbosCursos.value ? [{ value: 'ambos_cursos', label: 'Ambos cursos' }] : []),
]);

const conteoPorFamilia = computed(() => {
  const mapa = {};
  const d = filtroCentro.value
    ? microretosCurso.value.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value)
    : microretosCurso.value;
  d.forEach(m => { if (m.familia) mapa[m.familia] = (mapa[m.familia] || 0) + 1; });
  return mapa;
});

// Familias con mayor peso institucional: siempre arriba, mismo tamaño que el resto
const FAMILIAS_DESTACADAS = ['Comercio y Marketing', 'Administración y Gestión', 'Informática y Comunicaciones'];
// Nº de familias adicionales visibles antes de agruparlas tras "Ver más familias"
const FAMILIAS_VISIBLES_EXTRA = 8;

const familiasResto = computed(() =>
  familias.value.filter(f => !FAMILIAS_DESTACADAS.includes(f.nombre))
);

const familiasVisibles = computed(() => {
  const destacadas = FAMILIAS_DESTACADAS
    .map(nombre => familias.value.find(f => f.nombre === nombre))
    .filter(Boolean);
  return [...destacadas, ...familiasResto.value.slice(0, FAMILIAS_VISIBLES_EXTRA)];
});

const familiasOcultas = computed(() => familiasResto.value.slice(FAMILIAS_VISIBLES_EXTRA));

const nivelClase = (nivel) => ({
  Bajo:  `${theme.value.bg5} ${theme.value.border20} ${theme.value.text}`,
  Medio: 'bg-[#F59E0B]/10 border-[#F59E0B]/20 text-[#F59E0B]',
  Alto:  'bg-[#D64545]/10 border-[#D64545]/20 text-[#D64545]',
}[nivel] || 'bg-gray-100 border-gray-200 text-gray-500');

// ── CICLO DE VIDA ─────────────────────────────────────────
const cargarDatos = async () => {
  cargando.value = true;
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

// La familia abierta vive en la URL (?familia=…): entrar en ella es un paso del historial,
// así la flecha "volver" de la cabecera y el atrás del navegador regresan a las familias.
watch(() => route.query.familia, (familia) => {
  const nombre = familia ? String(familia) : null;
  if (nombre === familiaSeleccionada.value) return;
  familiaSeleccionada.value = nombre;
  resetFiltrosDetalle();
}, { immediate: true });

// ── ACCIONES ──────────────────────────────────────────────
const resetFiltrosDetalle = () => {
  filtroCiclo.value = '';
  filtroModulo.value = '';
  filtroNivel.value = '';
  filtroCurso.value = '';
  filtroInfoSimulada.value = '';
  filtroEmpresaTipo.value = '';
  busqueda.value = '';
};

const seleccionarCentro = (centro) => {
  filtroCentro.value = centro;
  resetFiltrosDetalle();
  if (familiaSeleccionada.value) router.replace({ name: 'biblioteca' });
};

const verMasFamilias = () => {
  router.push({ name: 'mas-familias', query: filtroCentro.value ? { centro: filtroCentro.value } : {} });
};

const seleccionarFamilia = (nombre) => {
  router.push({ name: 'biblioteca', query: { familia: nombre } });
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

// Nivel 2: descarga todos los microretos de una familia en el centro seleccionado
const descargarGrupoFamilia = (familiaName) => {
  let retos = microretosCurso.value.filter(m => m.familia === familiaName);
  if (filtroCentro.value)
    retos = retos.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  if (!retos.length) return;
  _lanzarGeneracion(() => {
    const titulo    = filtroCentro.value ? `${familiaName} – ${filtroCentro.value}` : familiaName;
    const subtitulo = `${retos.length} micro-reto${retos.length !== 1 ? 's' : ''}`;
    descargarPDFGrupo(retos, titulo, subtitulo);
  });
};

// Nivel 3: descarga exactamente los microretos visibles (respeta todos los filtros activos)
const descargarGrupoFiltrado = () => {
  const retos = microretosFiltrados.value;
  if (!retos.length) return;
  _lanzarGeneracion(() => {
    let titulo = familiaSeleccionada.value || 'Retos';
    if (filtroCentro.value) titulo += ` – ${filtroCentro.value}`;
    const partes = [];
    if (filtroCiclo.value)        partes.push(`Ciclo: ${filtroCiclo.value}`);
    if (filtroNivel.value)        partes.push(`Nivel: ${filtroNivel.value}`);
    if (filtroCurso.value)        partes.push(`${filtroCurso.value}º curso`);
    if (filtroEmpresaTipo.value)  partes.push(`Empresa ${filtroEmpresaTipo.value}`);
    if (filtroInfoSimulada.value) partes.push(`Info ${filtroInfoSimulada.value}`);
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

// Escaparate público (dualab.es/info.dualab.es) — opt-in manual por reto.
const togglePublicoEnCurso = ref(new Set());
const toggleVisiblePublico = async (reto) => {
  if (togglePublicoEnCurso.value.has(reto.id)) return;
  togglePublicoEnCurso.value.add(reto.id);
  try {
    const { data } = await api.patch(`/microretos/${reto.id}/visible-publico`);
    reto.visible_publico = data.visible_publico;
    mostrarSnack(
      data.visible_publico ? 'Reto visible en el escaparate público.' : 'Reto oculto del escaparate público.',
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
  <div class="min-h-screen p-4 md:p-12 font-sans text-[#1F2937] relative overflow-hidden pt-16 md:pt-16">

    <div
      class="absolute top-[-10%] left-1/2 transform -translate-x-1/2 w-[800px] h-[500px] blur-[120px] rounded-full pointer-events-none transition-opacity duration-1000"
      :class="[theme.bg, isLoaded ? 'opacity-10' : 'opacity-0']">
    </div>

    <div class="max-w-7xl mx-auto relative z-10">

      <!-- Cabecera de sección (mismo estilo que Retos y proyectos) -->
      <CabeceraSeccion titulo="Explorador de" destacado="Retos" :color="theme.text"
                       :volver="familiaSeleccionada ? { name: 'biblioteca' } : (authStore.isEmpresa ? null : '/seccion/retos-proyectos')"
                       subtitulo="Encuentra, filtra y reutiliza los desafíos generados por la Inteligencia Artificial para tus alumnos."
                       class="mb-6">
        <div v-if="authStore.isSuperAdmin" class="flex flex-wrap items-center gap-2">
          <!-- Descarga de todos los retos del centro: solo superadmin -->
          <button
            v-if="filtroCentro && countCentroActual > 0 && !cargando"
            type="button"
            @click="descargarGrupoCentro"
            :disabled="generandoPDFGrupo"
            class="accion-secundaria disabled:cursor-wait disabled:opacity-60"
            :title="`Descargar todos los retos de ${filtroCentro}`">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {{ generandoPDFGrupo ? 'Generando...' : `PDF centro (${countCentroActual})` }}
          </button>

          <!-- Acceso directo a la papelera — la papelera de "Base de datos" es solo superadmin;
               admin gestiona su propia papelera de cuentas desde /usuarios, no desde aquí. -->
          <button
            type="button"
            @click="router.push({ name: 'papelera' })"
            class="accion-icono"
            title="Ver elementos eliminados en la papelera"
            aria-label="Papelera">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
          </button>
        </div>
      </CabeceraSeccion>

      <!-- CARGANDO -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-20">
        <svg class="animate-spin w-12 h-12 mb-4" :class="theme.text" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z" />
        </svg>
        <p class="font-black tracking-widest uppercase text-sm animate-pulse" :class="theme.text">Cargando Biblioteca...</p>
      </div>

      <template v-else>

        <!-- ========================================== -->
        <!-- FILTROS (mismo sitio en ambas capas)       -->
        <!-- Capa familias: curso + centro. Capa retos: + buscador y filtros del reto. -->
        <!-- ========================================== -->
        <FiltrosPanel
          :busqueda="familiaSeleccionada ? busqueda : null"
          @update:busqueda="v => (busqueda = v)"
          placeholder="Título, empresa, ciclo, pregunta del reto…"
          :resultados="familiaSeleccionada ? microretosFiltrados.length : countCentroActual"
          :unidad="['reto', 'retos']"
          :hay-activos="!!familiaSeleccionada && hayFiltrosActivos"
          @limpiar="limpiarFiltros"
          class="mb-6">
          <template #curso>
            <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
          </template>

          <!-- Centro: superadmin y empresa eligen; docente / admin docente ven solo el suyo -->
          <FiltroSelect v-if="!esCentroRestringido"
            label="Centro educativo"
            :model-value="filtroCentro"
            @update:model-value="seleccionarCentro"
            :opciones="centrosDisponibles"
            todos="Todos los centros"
            title="Filtra retos del centro de formación profesional seleccionado." />
          <div v-else class="min-w-0 max-w-full">
            <p class="mb-1 text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">Centro educativo</p>
            <p class="flex h-9 min-w-0 items-center rounded-xl border border-gray-200 bg-gray-50 px-3 text-xs font-bold text-azul-noche">
              <span class="truncate">{{ authStore.userCentroNombre || filtroCentro }}</span>
            </p>
          </div>

          <template v-if="familiaSeleccionada">
            <FiltroSelect
              label="Ciclo formativo"
              v-model="filtroCiclo"
              :opciones="ciclosDisponibles"
              todos="Todos los ciclos"
              title="Filtra por ciclo formativo al que está orientado el reto." />
            <FiltroSelect v-if="modulosDisponibles.length"
              label="Módulo"
              v-model="filtroModulo"
              :opciones="modulosDisponibles"
              todos="Todos los módulos"
              title="Filtra por módulo profesional. Los retos de varios módulos aparecen en cada uno." />
            <FiltroSegmentado
              label="Nivel"
              v-model="filtroCurso"
              :opciones="opcionesCurso"
              title="Nivel del ciclo: 1º (primer año) o 2º (segundo año). Ambos cursos: posibilidad 1º y 2º. El curso académico se elige arriba." />
            <FiltroSegmentado
              label="Dificultad"
              v-model="filtroNivel"
              :opciones="opcionesDificultad"
              title="Dificultad del reto: Bajo (básico/introductorio), Medio (aplicado), Alto (avanzado)." />
            <FiltroSegmentado
              label="Empresa"
              v-model="filtroEmpresaTipo"
              :opciones="opcionesEmpresaTipo"
              title="Empresa real (existente) o ficticia (inventada para la práctica docente)." />
            <FiltroSegmentado
              label="Información del reto"
              v-model="filtroInfoSimulada"
              :opciones="opcionesInfoSimulada"
              title="Datos del reto reales (de empresa existente) o generados por IA para simulación." />
          </template>
        </FiltrosPanel>

        <!-- ================================ -->
        <!-- CAPA 1: TARJETAS DE FAMILIAS     -->
        <!-- ================================ -->
        <Transition name="slide-up" mode="out-in">
          <div v-if="!familiaSeleccionada" key="familias"
            class="transition-all duration-1000 delay-500 ease-out transform"
            :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">

            <p class="text-center text-gray-400 text-sm font-bold uppercase tracking-widest mb-8">
              Selecciona una familia profesional para explorar sus micro-retos
            </p>

            <div v-if="familiasVisibles.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 auto-rows-fr gap-6">
              <div
                v-for="familia in familiasVisibles"
                :key="familia.nombre"
                @click="seleccionarFamilia(familia.nombre)"
                @keydown.enter.prevent="seleccionarFamilia(familia.nombre)"
                @keydown.space.prevent="seleccionarFamilia(familia.nombre)"
                role="button"
                tabindex="0"
                class="group relative rounded-[1.5rem] overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 bg-white text-left focus:outline-none focus:ring-2 cursor-pointer"
                :class="theme.ring">

                <div :class="['relative h-44 overflow-hidden bg-gradient-to-br flex items-center justify-center', colorFamilia(familia.nombre).bg]">
                  <svg :class="['w-16 h-16 group-hover:scale-110 transition-transform duration-300', colorFamilia(familia.nombre).icon]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-for="d in iconoFamilia(familia.nombre)" :key="d"
                      stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="d" />
                  </svg>
                  <div
                    v-if="conteoPorFamilia[familia.nombre]"
                    class="absolute top-3 right-3 text-white text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full shadow"
                    :class="theme.bg">
                    {{ conteoPorFamilia[familia.nombre] }} reto{{ conteoPorFamilia[familia.nombre] !== 1 ? 's' : '' }}
                  </div>
                </div>

                <div class="p-5 flex items-end justify-between gap-2">
                  <div class="min-w-0">
                    <h3 class="font-black text-azul-noche text-base leading-tight mb-3 transition-colors line-clamp-2"
                      :class="paletteExtra[theme.key].groupHoverText">
                      {{ familia.nombre }}
                    </h3>
                    <div class="flex items-center gap-2 text-xs font-black uppercase tracking-widest" :class="theme.text">
                      <span>Explorar</span>
                      <svg class="w-3.5 h-3.5 transform group-hover:translate-x-1 transition-transform"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                      </svg>
                    </div>
                  </div>

                  <!-- Botón de descarga PDF de toda la familia (nivel 2) -->
                  <button
                    @click.stop="descargarGrupoFamilia(familia.nombre)"
                    :disabled="generandoPDFGrupo || !conteoPorFamilia[familia.nombre]"
                    class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center border transition-all duration-200"
                    :class="generandoPDFGrupo
                      ? 'border-gray-100 text-gray-300 bg-gray-50 cursor-wait'
                      : ['border-gray-200 text-gray-400 bg-white', paletteExtra[theme.key].hoverBorderBg5Text]"
                    :title="`Descargar PDF de ${familia.nombre} (${conteoPorFamilia[familia.nombre] || 0} retos)`">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Tarjeta "ver más familias": agrupa el resto, homogénea con las demás -->
              <button
                v-if="familiasOcultas.length > 0"
                @click="verMasFamilias"
                class="group relative rounded-[1.5rem] overflow-hidden border-2 border-dashed border-gray-200 transition-all duration-300 bg-gray-50 hover:bg-white text-left focus:outline-none focus:ring-2 flex flex-col items-center justify-center gap-3 min-h-[15.5rem] p-6 text-center"
                :class="[paletteExtra[theme.key].hoverBorder40, theme.ring]">
                <div class="w-14 h-14 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm"
                  :class="paletteExtra[theme.key].groupHoverBorder40">
                  <svg class="w-6 h-6" :class="theme.text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-black text-azul-noche text-base leading-tight">Ver más familias</h3>
                  <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">
                    +{{ familiasOcultas.length }} familia{{ familiasOcultas.length !== 1 ? 's' : '' }}
                  </p>
                </div>
              </button>
            </div>

            <div v-else class="text-center py-20 bg-white rounded-[2rem] border border-dashed border-gray-300 shadow-sm">
              <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-5 border border-gray-100 shadow-inner">
                <svg class="w-10 h-10" :class="theme.text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 class="text-azul-noche font-black text-2xl mb-2">Sin micro-retos para este centro</h3>
              <p class="text-gray-500 text-sm max-w-md mx-auto">Este centro todavía no tiene micro-retos generados.</p>
            </div>

          </div>
        </Transition>

        <!-- ================================ -->
        <!-- CAPA 2: MICRORETOS DE LA FAMILIA -->
        <!-- ================================ -->
        <Transition name="slide-up" mode="out-in">
          <div v-if="familiaSeleccionada" key="microretos">

            <MigasNavegacion :items="migas.items" :actual="migas.actual" class="mb-8">
              <div class="flex items-center gap-3">
                <!-- Botón de descarga del grupo filtrado actual (nivel 3) -->
                <button
                  v-if="microretosFiltrados.length > 0"
                  @click="descargarGrupoFiltrado"
                  :disabled="generandoPDFGrupo"
                  class="flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest border transition-all duration-200"
                  :class="generandoPDFGrupo
                    ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-wait'
                    : [theme.border20, theme.text, theme.bgHover, 'hover:text-white', paletteExtra[theme.key].hoverBorder]"
                  :title="hayFiltrosActivos ? 'Descargar retos con los filtros actuales' : 'Descargar todos los retos de esta familia'">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  {{ generandoPDFGrupo ? 'Generando...' : `Descargar ${microretosFiltrados.length}` }}
                </button>
              </div>
            </MigasNavegacion>

            <!-- ── GRID DE MICRORETOS ───────────────────────────── -->
            <div v-if="microretosFiltrados.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 auto-rows-fr gap-6">
              <div v-for="reto in microretosFiltrados" :key="reto.id"
                class="bg-white rounded-[1.5rem] border border-gray-100 hover:shadow-[0_20px_40px_rgba(0,0,0,0.06)] shadow-sm transition-all duration-300 flex flex-col group overflow-hidden transform hover:-translate-y-1"
                :class="paletteExtra[theme.key].hoverBorder40">

                <div class="p-5 pb-0 flex items-start justify-between gap-2">
                  <div v-if="!authStore.isEmpresa" class="flex items-center gap-1.5 shrink-0">
                    <!-- Papelera -->
                    <button
                      @click.prevent="abrirModalEliminar(reto)"
                      class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm transition-all duration-300 hover:bg-red-50 hover:border-red-300 active:scale-95"
                      title="Eliminar reto">
                      <svg class="w-4 h-4 text-gray-400 hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>

                    <!-- Visibilidad en escaparate público -->
                    <button
                      @click.prevent="toggleVisiblePublico(reto)"
                      :disabled="togglePublicoEnCurso.has(reto.id)"
                      class="w-8 h-8 rounded-full bg-white border flex items-center justify-center shadow-sm transition-all duration-300 active:scale-95 disabled:opacity-50"
                      :class="reto.visible_publico
                        ? 'border-emerald-300 hover:bg-emerald-50'
                        : 'border-gray-200 hover:bg-gray-50'"
                      :title="reto.visible_publico ? 'Visible en el escaparate público — clic para ocultar' : 'Oculto del escaparate público — clic para publicar'">
                      <svg v-if="reto.visible_publico" class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      <svg v-else class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                    </button>
                  </div>

                  <!-- Badges derecha -->
                  <div class="flex flex-wrap justify-end gap-1.5 min-w-0">
                    <span class="border px-2.5 py-0.5 text-[9px] font-black uppercase tracking-widest rounded-full whitespace-nowrap"
                      :class="nivelClase(reto.nivel_grupo)">
                      Nivel: {{ reto.nivel_grupo || 'N/D' }}
                    </span>
                    <span
                      class="px-2.5 py-0.5 text-[8px] font-black uppercase tracking-widest rounded-full whitespace-nowrap border"
                      :class="reto.empresa_es_simulada
                        ? 'bg-[#F59E0B]/10 border-[#F59E0B]/20 text-[#F59E0B]'
                        : [theme.bg5, theme.border20, theme.text]">
                      {{ reto.empresa_es_simulada ? 'Empresa ficticia' : 'Empresa real' }}
                    </span>
                    <span v-if="reto.es_simulado"
                      class="bg-rose-50 border border-rose-200 text-rose-700 px-2.5 py-0.5 text-[8px] font-black uppercase tracking-widest rounded-full whitespace-nowrap">
                      Info simulada
                    </span>
                  </div>
                </div>

                <div class="px-7 pb-7 pt-4 flex-1 flex flex-col">
                  <h3 class="text-azul-noche font-black text-xl leading-tight mb-4 transition-colors line-clamp-2"
                    :class="paletteExtra[theme.key].groupHoverText" :title="reto.titulo">
                    {{ reto.titulo }}
                  </h3>
                  <div class="flex flex-col gap-2 mb-5 border-l-2 border-gray-100 pl-3 transition-colors"
                    :class="paletteExtra[theme.key].groupHoverBorder30">
                    <p class="text-[#1F2937] text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                      <svg class="w-4 h-4 shrink-0" :class="theme.text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                      <span class="truncate">{{ reto.empresa_nombre }}</span>
                    </p>
                    <p class="text-gray-500 text-[10px] font-bold uppercase tracking-wider flex items-center gap-2">
                      <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                      </svg>
                      <span class="truncate">{{ reto.centro_educativo || 'Centro ND' }}</span>
                    </p>
                    <p v-if="reto.creado_en || reto.created_at" class="text-gray-400 text-[10px] font-bold uppercase tracking-wider">
                      Creado el {{ formatearFecha(reto.creado_en || reto.created_at) }}
                    </p>
                    <p v-if="reto.diagnostico_modificado_en_reto" class="text-amber-600 text-[10px] font-bold uppercase tracking-wider"
                       title="Se generó con cambios en el diagnóstico que no se guardaron en la empresa">
                      Diagnóstico ajustado para este reto
                    </p>
                    <p v-if="reto.diagnostico_desactualizado" class="text-amber-600 text-[10px] font-bold uppercase tracking-wider"
                       title="La empresa ha actualizado su diagnóstico después de crear este reto">
                      ⚠ Diagnóstico de la empresa actualizado después
                    </p>
                  </div>
                  <p class="text-gray-600 text-sm leading-relaxed line-clamp-3 mb-6 flex-1">
                    {{ reto.pregunta_reto }}
                  </p>
                  <div class="mt-auto flex flex-wrap items-center gap-2">
                    <span class="inline-block bg-gray-50 text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-medium truncate max-w-full shadow-sm" :title="reto.ciclo">
                      {{ reto.ciclo }}
                    </span>
                    <span v-if="reto.curso" class="inline-block bg-gray-50 text-gray-500 border border-gray-200 px-2.5 py-1.5 rounded-lg text-xs font-bold shadow-sm shrink-0"
                          :title="reto.curso === 'ambos_cursos' ? 'Ambos Cursos: posibilidad 1º y 2º' : ''">
                      {{ reto.curso === 'ambos_cursos' ? 'Ambos Cursos' : reto.curso + 'º curso' }}
                    </span>
                  </div>
                </div>

                <div class="flex border-t border-gray-100 transition-colors duration-300"
                  :class="paletteExtra[theme.key].groupHoverBorder">
                  <button @click.stop="descargarPDF(reto)"
                    class="flex items-center justify-center gap-1.5 px-4 py-4 bg-gray-50 text-gray-400 font-black text-[10px] uppercase tracking-widest transition-all duration-300 border-r border-gray-100 shrink-0"
                    :class="[paletteExtra[theme.key].groupHoverBg10, paletteExtra[theme.key].groupHoverText, paletteExtra[theme.key].groupHoverBorder20]"
                    title="Descargar PDF">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    PDF
                  </button>
                  <button @click="irADetalle(reto)"
                    class="flex-1 bg-gray-50 text-gray-500 group-hover:text-white font-black text-xs uppercase tracking-widest py-4 transition-all duration-300 flex items-center justify-center gap-2"
                    :class="paletteExtra[theme.key].groupHoverBg">
                    Ver Ficha Técnica
                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-20 bg-white rounded-[2rem] border border-dashed border-gray-300 shadow-sm">
              <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-5 border border-gray-100 shadow-inner">
                <svg class="w-10 h-10" :class="theme.text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 class="text-azul-noche font-black text-2xl mb-2">No hay resultados</h3>
              <p class="text-gray-500 text-sm max-w-md mx-auto">Prueba a limpiar los filtros o genera nuevos retos en el Estudio interactivo.</p>
              <button @click="limpiarFiltros"
                class="mt-6 px-6 py-2 bg-white border border-gray-200 text-[#1F2937] rounded-full text-xs font-bold uppercase tracking-widest transition-colors shadow-sm"
                :class="paletteExtra[theme.key].hoverBorderText">
                Restablecer Búsqueda
              </button>
            </div>

          </div>
        </Transition>

      </template>
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
      class="fixed bottom-6 right-6 z-[60] flex items-center gap-3
             px-5 py-3.5 rounded-2xl shadow-xl text-sm font-bold
             max-w-sm bg-[#1F2937] text-white border border-[#333333]"
    >
      <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      <span class="flex-1">{{ snackbar.mensaje }}</span>
      <button
        v-if="snackbar.accion"
        @click="snackbar.accion.fn(); snackbar.visible = false"
        class="ml-1 shrink-0 px-3 py-1.5 rounded-xl bg-amber-400 text-[#1F2937] text-[10px] font-black uppercase tracking-widest hover:bg-amber-300 transition-all"
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
