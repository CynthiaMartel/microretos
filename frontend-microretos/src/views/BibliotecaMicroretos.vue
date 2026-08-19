<!-- Ruta: /retos (name: biblioteca). Antes vivía en /biblioteca — ver router/index.js. -->
<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth'
import api from '../api.js';
import LoginModal from '../components/LoginModal.vue';
import EliminarMicrorretoModal from '../components/EliminarMicrorretoModal.vue';
import { usePdfExport } from '../composables/usePdfExport.js';

const router = useRouter();
const isLoaded = ref(false);
const microretos = ref([]);
const centros = ref([]);
const cargando = ref(true);
const familias = ref([]);
const showLogin = ref(false);
const accionPendiente = ref(null);
const familiaSeleccionada = ref(null);

const authStore = useAuthStore();
const { descargarPDF, descargarPDFGrupo } = usePdfExport();

// Estado de generación de PDF de grupo (evita dobles clics y muestra feedback)
const generandoPDFGrupo = ref(false);

// ── FILTROS ──────────────────────────────────────────────
const filtroCentro      = ref('');
const filtroCiclo       = ref('');
const filtroNivel       = ref('');        // '' | 'Bajo' | 'Medio' | 'Alto'
const filtroCurso       = ref('');        // '' | '1' | '2'
const filtroInfoSimulada = ref('');       // '' | 'real' | 'simulada'  → es_simulado
const filtroEmpresaTipo  = ref('');       // '' | 'real' | 'ficticia'  → empresa_es_simulada
const busqueda          = ref('');
const empresaFiltroAbierto = ref(false);
const infoFiltroAbierto    = ref(false);

// Docentes y admins docentes solo pueden ver su propio centro
const esCentroRestringido = computed(() => authStore.isDocente || authStore.isAdmin);

const centrosDisponibles = computed(() => centros.value.map(c => c.nombre).sort());

// Total de microretos del centro seleccionado (para mostrar en el botón de descarga)
const countCentroActual = computed(() => {
  if (!filtroCentro.value) return microretos.value.length;
  return microretos.value.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value).length;
});

const ciclosDisponibles = computed(() => {
  let d = microretos.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  return [...new Set(d.map(m => m.ciclo).filter(Boolean))].sort();
});

const cursosDisponibles = computed(() => {
  let d = microretos.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  // 'ambos_cursos' se muestra aparte (botón fijo), no mezclado en el orden numérico 1º/2º.
  return [...new Set(d.map(m => m.curso).filter(v => v != null && v !== 'ambos_cursos'))].sort((a, b) => a - b);
});

// Un reto "ambos cursos" vale para 1º y 2º a la vez (cruza módulos de los dos años).
const hayRetosAmbosCursos = computed(() => {
  let d = microretos.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  return d.some(m => m.curso === 'ambos_cursos');
});

// Conteo por nivel dentro de familia+centro (sin aplicar otros filtros activos)
const conteoNiveles = computed(() => {
  const r = { Bajo: 0, Medio: 0, Alto: 0, total: 0 };
  if (!familiaSeleccionada.value) return r;
  let d = microretos.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  d.forEach(m => { r.total++; if (m.nivel_grupo && m.nivel_grupo in r) r[m.nivel_grupo]++; });
  return r;
});

// Conteo por tipo de empresa (real vs ficticia)
const conteoEmpresaTipo = computed(() => {
  const r = { real: 0, ficticia: 0, total: 0 };
  if (!familiaSeleccionada.value) return r;
  let d = microretos.value.filter(m => m.familia === familiaSeleccionada.value);
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
  let d = microretos.value.filter(m => m.familia === familiaSeleccionada.value);
  if (filtroCentro.value) d = d.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value);
  d.forEach(m => {
    r.total++;
    if (m.es_simulado) r.simulada++; else r.real++;
  });
  return r;
});

const microretosFiltrados = computed(() => {
  const q = busqueda.value.toLowerCase().trim();
  return microretos.value.filter(reto => {
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

const hayFiltrosActivos = computed(() =>
  !!(filtroCiclo.value || filtroNivel.value || filtroCurso.value || filtroInfoSimulada.value || filtroEmpresaTipo.value || busqueda.value)
);

const conteoPorFamilia = computed(() => {
  const mapa = {};
  const d = filtroCentro.value
    ? microretos.value.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value)
    : microretos.value;
  d.forEach(m => { if (m.familia) mapa[m.familia] = (mapa[m.familia] || 0) + 1; });
  return mapa;
});

const familiasFiltradas = computed(() => {
  if (!filtroCentro.value) return familias.value;
  return familias.value.filter(f => (conteoPorFamilia.value[f.nombre] || 0) > 0);
});

const nivelClase = (nivel) => ({
  Bajo:  'bg-[#00A859]/10 border-[#00A859]/20 text-[#00A859]',
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

// ── ACCIONES ──────────────────────────────────────────────
const resetFiltrosDetalle = () => {
  filtroCiclo.value = '';
  filtroNivel.value = '';
  filtroCurso.value = '';
  filtroInfoSimulada.value = '';
  filtroEmpresaTipo.value = '';
  busqueda.value = '';
};

const seleccionarCentro = (centro) => {
  filtroCentro.value = centro;
  familiaSeleccionada.value = null;
  resetFiltrosDetalle();
};

const seleccionarFamilia = (nombre) => {
  familiaSeleccionada.value = nombre;
  resetFiltrosDetalle();
};

const volverAFamilias = () => {
  familiaSeleccionada.value = null;
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
    ? microretos.value.filter(m => (m.centro_educativo || m.centro) === filtroCentro.value)
    : microretos.value;
  if (!retos.length) return;
  _lanzarGeneracion(() => {
    const titulo    = filtroCentro.value || 'Todos los centros';
    const subtitulo = `${retos.length} micro-reto${retos.length !== 1 ? 's' : ''}`;
    descargarPDFGrupo(retos, titulo, subtitulo);
  });
};

// Nivel 2: descarga todos los microretos de una familia en el centro seleccionado
const descargarGrupoFamilia = (familiaName) => {
  let retos = microretos.value.filter(m => m.familia === familiaName);
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
  <div class="min-h-screen p-4 md:p-12 font-sans text-[#1F2937] relative overflow-hidden pt-12 md:pt-12">

    <div
      class="absolute top-[-10%] left-1/2 transform -translate-x-1/2 w-[800px] h-[500px] bg-[#99CC33] blur-[120px] rounded-full pointer-events-none transition-opacity duration-1000"
      :class="isLoaded ? 'opacity-10' : 'opacity-0'">
    </div>

    <div class="max-w-7xl mx-auto relative z-10">

      <!-- HEADER -->
      <header class="mb-10 text-center flex flex-col items-center">
        <div
          class="inline-flex items-center mb-8 bg-[#1F2937] py-3 sm:py-4 pr-6 sm:pr-10 pl-4 sm:pl-6 rounded-[3rem] shadow-lg border border-[#333333] transition-all duration-1000 ease-out transform"
          :class="isLoaded ? 'translate-y-0 opacity-100' : '-translate-y-10 opacity-0'">
          <img src="../assets/logo.png" alt="Logo DuaLab"
            class="h-20 sm:h-32 md:h-40 w-auto object-contain -mr-3 sm:-mr-4 md:-mr-8 relative z-10" />
          <span class="font-black text-2xl sm:text-4xl md:text-5xl tracking-tighter uppercase text-white italic relative z-20">
            Dua<span class="text-[#00A859]">Lab</span>
            <span class="text-[#99CC33] not-italic text-sm sm:text-lg md:text-xl ml-1">Library</span>
          </span>
        </div>
        <h1
          class="text-4xl md:text-5xl font-black tracking-tight mb-4 text-[#121212] transition-all duration-1000 delay-150 ease-out transform"
          :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
          Explorador de
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">Retos</span>
        </h1>
        <p class="text-gray-500 max-w-2xl mx-auto text-base md:text-lg leading-relaxed font-medium transition-all duration-1000 delay-300 ease-out transform"
          :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
          Encuentra, filtra y reutiliza los desafíos generados por la Inteligencia Artificial para tus alumnos.
        </p>
      </header>

      <!-- CARGANDO -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-20">
        <svg class="animate-spin w-12 h-12 text-[#00A859] mb-4" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z" />
        </svg>
        <p class="text-[#00A859] font-black tracking-widest uppercase text-sm animate-pulse">Cargando Biblioteca...</p>
      </div>

      <template v-else>

        <!-- ========================================== -->
        <!-- SELECTOR DE CENTRO (visible en ambas capas) -->
        <!-- ========================================== -->
        <div
          class="mb-8 transition-all duration-1000 delay-400 ease-out transform"
          :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
          <div class="bg-white/90 backdrop-blur-md rounded-[2rem] p-5 border border-gray-100 shadow-[0_20px_50px_rgb(0,0,0,0.04)]">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
              <label class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 flex items-center gap-2 whitespace-nowrap">
                <svg class="w-3.5 h-3.5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Centro Educativo
                <span class="tooltip-wrap">
                  <svg class="w-3 h-3 text-gray-400 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg>
                  <span class="tooltip-text">Filtra retos del centro de formación profesional seleccionado.</span>
                </span>
              </label>
              <div class="flex flex-wrap items-center gap-2 flex-1">
                <!-- Superadmin y empresa: selector completo de centros -->
                <template v-if="!esCentroRestringido">
                  <button
                    v-for="centro in centrosDisponibles"
                    :key="centro"
                    @click="seleccionarCentro(centro)"
                    class="px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest transition-all duration-200 border"
                    :class="filtroCentro === centro
                      ? 'bg-[#00A859] text-white border-[#00A859] shadow-md'
                      : 'bg-gray-50 text-gray-500 border-gray-200 hover:border-[#00A859] hover:text-[#00A859]'">
                    {{ centro }}
                  </button>
                </template>
                <!-- Docente / Admin docente: solo badge de su propio centro -->
                <span v-else
                  class="px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest bg-[#00A859] text-white border border-[#00A859] shadow-md">
                  {{ authStore.userCentroNombre || filtroCentro }}
                </span>

                <!-- Descarga de todos los microretos del centro: solo superadmin -->
                <button
                  v-if="authStore.isSuperAdmin && filtroCentro && countCentroActual > 0 && !cargando"
                  @click="descargarGrupoCentro"
                  :disabled="generandoPDFGrupo"
                  class="flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-black uppercase tracking-widest border transition-all duration-200"
                  :class="generandoPDFGrupo
                    ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-wait'
                    : 'border-[#00A859]/40 text-[#00A859] hover:bg-[#00A859] hover:text-white hover:border-[#00A859]'"
                  :title="`Descargar todos los retos de ${filtroCentro}`">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  {{ generandoPDFGrupo ? 'Generando...' : `PDF centro (${countCentroActual})` }}
                </button>

                <!-- Acceso directo a la papelera — la papelera de "Base de datos" es solo superadmin;
                     admin gestiona su propia papelera de cuentas desde /usuarios, no desde aquí. -->
                <button
                  v-if="authStore.isSuperAdmin"
                  @click="router.push({ name: 'papelera' })"
                  class="flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-black uppercase tracking-widest border border-gray-200 text-gray-500 bg-gray-50 hover:border-amber-300 hover:text-amber-600 hover:bg-amber-50 transition-all duration-200"
                  title="Ver elementos eliminados en la papelera">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                  Papelera
                </button>
              </div>
            </div>
          </div>
        </div>

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

            <div v-if="familiasFiltradas.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              <!-- Tarjeta de familia: div en lugar de button para poder anidar el botón de descarga -->
              <div
                v-for="familia in familiasFiltradas"
                :key="familia.nombre"
                @click="seleccionarFamilia(familia.nombre)"
                @keydown.enter.prevent="seleccionarFamilia(familia.nombre)"
                @keydown.space.prevent="seleccionarFamilia(familia.nombre)"
                role="button"
                tabindex="0"
                class="group relative rounded-[1.5rem] overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 bg-white text-left focus:outline-none focus:ring-2 focus:ring-[#00A859]/40 cursor-pointer">

                <div class="relative h-44 overflow-hidden">
                  <img
                    v-if="familia.imagen_url"
                    :src="familia.imagen_url"
                    :alt="familia.nombre"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                  <div
                    v-else
                    class="w-full h-full bg-gradient-to-br from-[#00A859]/10 via-[#99CC33]/10 to-gray-100 flex items-center justify-center">
                    <svg class="w-16 h-16 text-[#00A859]/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M12 14l9-5-9-5-9 5 9 5zm0 7V14m0 0l-6.16-3.422M12 21a11.952 11.952 0 01-5.835-6.578" />
                    </svg>
                  </div>
                  <div class="absolute top-3 right-3 bg-[#00A859] text-white text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full shadow">
                    {{ conteoPorFamilia[familia.nombre] || 0 }} reto{{ (conteoPorFamilia[familia.nombre] || 0) !== 1 ? 's' : '' }}
                  </div>
                </div>

                <div class="p-5 flex items-end justify-between gap-2">
                  <div class="min-w-0">
                    <h3 class="font-black text-[#1F2937] text-base leading-tight mb-3 group-hover:text-[#00A859] transition-colors line-clamp-2">
                      {{ familia.nombre }}
                    </h3>
                    <div class="flex items-center gap-2 text-[#00A859] text-xs font-black uppercase tracking-widest">
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
                      : 'border-gray-200 text-gray-400 bg-white hover:border-[#00A859] hover:bg-[#00A859]/5 hover:text-[#00A859]'"
                    :title="`Descargar PDF de ${familia.nombre} (${conteoPorFamilia[familia.nombre] || 0} retos)`">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-20 bg-white rounded-[2rem] border border-dashed border-gray-300 shadow-sm">
              <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-5 border border-gray-100 shadow-inner">
                <svg class="w-10 h-10 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 class="text-[#1F2937] font-black text-2xl mb-2">Sin micro-retos para este centro</h3>
              <p class="text-gray-500 text-sm max-w-md mx-auto">Este centro todavía no tiene micro-retos generados.</p>
            </div>

          </div>
        </Transition>

        <!-- ================================ -->
        <!-- CAPA 2: MICRORETOS DE LA FAMILIA -->
        <!-- ================================ -->
        <Transition name="slide-up" mode="out-in">
          <div v-if="familiaSeleccionada" key="microretos">

            <!-- Breadcrumb -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
              <div class="flex items-center gap-4">
                <button @click="volverAFamilias"
                  class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-gray-500 hover:text-[#00A859] transition-colors group">
                  <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                  </svg>
                  Todas las familias
                </button>
                <span class="text-gray-200">|</span>
                <h2 class="text-xl font-black text-[#1F2937]">{{ familiaSeleccionada }}</h2>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 font-bold">
                  {{ microretosFiltrados.length }} reto{{ microretosFiltrados.length !== 1 ? 's' : '' }}
                </span>

                <!-- Botón de descarga del grupo filtrado actual (nivel 3) -->
                <button
                  v-if="microretosFiltrados.length > 0"
                  @click="descargarGrupoFiltrado"
                  :disabled="generandoPDFGrupo"
                  class="flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest border transition-all duration-200"
                  :class="generandoPDFGrupo
                    ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-wait'
                    : 'border-[#00A859]/30 text-[#00A859] hover:bg-[#00A859] hover:text-white hover:border-[#00A859]'"
                  :title="hayFiltrosActivos ? 'Descargar retos con los filtros actuales' : 'Descargar todos los retos de esta familia'">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  {{ generandoPDFGrupo ? 'Generando...' : `Descargar ${microretosFiltrados.length}` }}
                </button>
              </div>
            </div>

            <!-- ── CARDS DE DIFICULTAD ───────────────────────────── -->
            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3 px-1 flex items-center gap-1.5">
              Nivel del reto
              <span class="tooltip-wrap">
                <svg class="w-3 h-3 text-gray-400 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg>
                <span class="tooltip-text">Dificultad del reto: Bajo (básico/introductorio), Medio (aplicado), Alto (avanzado).</span>
              </span>
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">

              <!-- TODOS -->
              <button
                @click="filtroNivel = ''"
                class="relative p-4 rounded-2xl border-2 text-left transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-400/30"
                :class="filtroNivel === ''
                  ? 'bg-[#1F2937] border-[#1F2937] shadow-lg scale-[1.02]'
                  : 'bg-white border-gray-100 hover:border-gray-300 hover:shadow-md'">
                <div class="flex items-end justify-between mb-3">
                  <div class="flex items-end gap-0.5">
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:8px"
                      :class="filtroNivel === '' ? 'bg-white' : 'bg-gray-300'"></span>
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:12px"
                      :class="filtroNivel === '' ? 'bg-white' : 'bg-gray-300'"></span>
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:16px"
                      :class="filtroNivel === '' ? 'bg-white' : 'bg-gray-300'"></span>
                  </div>
                  <span class="text-2xl font-black leading-none"
                    :class="filtroNivel === '' ? 'text-white' : 'text-gray-400'">
                    {{ conteoNiveles.total }}
                  </span>
                </div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] leading-none"
                  :class="filtroNivel === '' ? 'text-white' : 'text-gray-600'">Todos</p>
                <p class="text-[9px] mt-1 leading-none"
                  :class="filtroNivel === '' ? 'text-white/60' : 'text-gray-400'">los niveles</p>
              </button>

              <!-- BAJO -->
              <button
                @click="filtroNivel = filtroNivel === 'Bajo' ? '' : 'Bajo'"
                class="relative p-4 rounded-2xl border-2 text-left transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#00A859]/30"
                :class="filtroNivel === 'Bajo'
                  ? 'bg-[#00A859] border-[#00A859] shadow-lg scale-[1.02]'
                  : 'bg-white border-gray-100 hover:border-[#00A859]/50 hover:shadow-md'">
                <div class="flex items-end justify-between mb-3">
                  <div class="flex items-end gap-0.5">
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:8px"
                      :class="filtroNivel === 'Bajo' ? 'bg-white' : 'bg-[#00A859]'"></span>
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:12px"
                      :class="filtroNivel === 'Bajo' ? 'bg-white/30' : 'bg-gray-200'"></span>
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:16px"
                      :class="filtroNivel === 'Bajo' ? 'bg-white/30' : 'bg-gray-200'"></span>
                  </div>
                  <span class="text-2xl font-black leading-none"
                    :class="filtroNivel === 'Bajo' ? 'text-white' : 'text-[#00A859]'">
                    {{ conteoNiveles.Bajo }}
                  </span>
                </div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] leading-none"
                  :class="filtroNivel === 'Bajo' ? 'text-white' : 'text-gray-600'">Bajo</p>
                <p class="text-[9px] mt-1 leading-none"
                  :class="filtroNivel === 'Bajo' ? 'text-white/60' : 'text-gray-400'">accesible</p>
              </button>

              <!-- MEDIO -->
              <button
                @click="filtroNivel = filtroNivel === 'Medio' ? '' : 'Medio'"
                class="relative p-4 rounded-2xl border-2 text-left transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#F59E0B]/30"
                :class="filtroNivel === 'Medio'
                  ? 'bg-[#F59E0B] border-[#F59E0B] shadow-lg scale-[1.02]'
                  : 'bg-white border-gray-100 hover:border-[#F59E0B]/50 hover:shadow-md'">
                <div class="flex items-end justify-between mb-3">
                  <div class="flex items-end gap-0.5">
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:8px"
                      :class="filtroNivel === 'Medio' ? 'bg-white' : 'bg-[#F59E0B]'"></span>
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:12px"
                      :class="filtroNivel === 'Medio' ? 'bg-white' : 'bg-[#F59E0B]'"></span>
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:16px"
                      :class="filtroNivel === 'Medio' ? 'bg-white/30' : 'bg-gray-200'"></span>
                  </div>
                  <span class="text-2xl font-black leading-none"
                    :class="filtroNivel === 'Medio' ? 'text-white' : 'text-[#F59E0B]'">
                    {{ conteoNiveles.Medio }}
                  </span>
                </div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] leading-none"
                  :class="filtroNivel === 'Medio' ? 'text-white' : 'text-gray-600'">Medio</p>
                <p class="text-[9px] mt-1 leading-none"
                  :class="filtroNivel === 'Medio' ? 'text-white/60' : 'text-gray-400'">retador</p>
              </button>

              <!-- ALTO -->
              <button
                @click="filtroNivel = filtroNivel === 'Alto' ? '' : 'Alto'"
                class="relative p-4 rounded-2xl border-2 text-left transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#D64545]/30"
                :class="filtroNivel === 'Alto'
                  ? 'bg-[#D64545] border-[#D64545] shadow-lg scale-[1.02]'
                  : 'bg-white border-gray-100 hover:border-[#D64545]/50 hover:shadow-md'">
                <div class="flex items-end justify-between mb-3">
                  <div class="flex items-end gap-0.5">
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:8px"
                      :class="filtroNivel === 'Alto' ? 'bg-white' : 'bg-[#D64545]'"></span>
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:12px"
                      :class="filtroNivel === 'Alto' ? 'bg-white' : 'bg-[#D64545]'"></span>
                    <span class="w-1.5 rounded-sm inline-block transition-colors" style="height:16px"
                      :class="filtroNivel === 'Alto' ? 'bg-white' : 'bg-[#D64545]'"></span>
                  </div>
                  <span class="text-2xl font-black leading-none"
                    :class="filtroNivel === 'Alto' ? 'text-white' : 'text-[#D64545]'">
                    {{ conteoNiveles.Alto }}
                  </span>
                </div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] leading-none"
                  :class="filtroNivel === 'Alto' ? 'text-white' : 'text-gray-600'">Alto</p>
                <p class="text-[9px] mt-1 leading-none"
                  :class="filtroNivel === 'Alto' ? 'text-white/60' : 'text-gray-400'">exigente</p>
              </button>

            </div>

            <!-- ── CARDS TIPO EMPRESA + TIPO INFORMACIÓN ─────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

              <!-- GRUPO: EMPRESA -->
              <div class="bg-[#00A859]/10 rounded-2xl border border-[#00A859]/30">
                <button @click="empresaFiltroAbierto = !empresaFiltroAbierto"
                  class="w-full flex items-center justify-between px-3 py-2.5 hover:bg-[#00A859]/15 transition-colors">
                  <div class="flex items-center gap-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-[#00A859]">Empresa</p>
                    <span class="tooltip-wrap" @click.stop>
                      <svg class="w-3 h-3 text-[#00A859]/60 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg>
                      <span class="tooltip-text">Empresa real (existente) o ficticia (inventada para la práctica docente).</span>
                    </span>
                    <span v-if="!empresaFiltroAbierto && filtroEmpresaTipo"
                      class="text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                      :class="filtroEmpresaTipo === 'ficticia' ? 'bg-[#F59E0B]/10 text-[#F59E0B]' : 'bg-[#00A859]/10 text-[#00A859]'">
                      {{ filtroEmpresaTipo === 'ficticia' ? 'Ficticia' : 'Real' }}
                    </span>
                  </div>
                  <svg class="w-3.5 h-3.5 text-[#00A859]/70 transition-transform duration-200"
                    :class="empresaFiltroAbierto ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <Transition name="filter-collapse">
                  <div v-if="empresaFiltroAbierto" class="px-3 pb-3">
                <div class="grid grid-cols-3 gap-2">

                  <button
                    @click="filtroEmpresaTipo = ''"
                    class="relative p-3 rounded-xl border-2 text-left transition-all duration-200 focus:outline-none"
                    :class="filtroEmpresaTipo === ''
                      ? 'bg-[#1F2937] border-[#1F2937] shadow-md scale-[1.02]'
                      : 'bg-white border-gray-100 hover:border-gray-300 hover:shadow-sm'">
                    <div class="flex items-end justify-between mb-1.5">
                      <svg class="w-4 h-4" :class="filtroEmpresaTipo === '' ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                      </svg>
                      <span class="text-xl font-black leading-none" :class="filtroEmpresaTipo === '' ? 'text-white' : 'text-gray-400'">{{ conteoEmpresaTipo.total }}</span>
                    </div>
                    <p class="text-[9px] font-black uppercase tracking-[0.15em] leading-none" :class="filtroEmpresaTipo === '' ? 'text-white' : 'text-gray-600'">Todas</p>
                  </button>

                  <button
                    @click="filtroEmpresaTipo = filtroEmpresaTipo === 'real' ? '' : 'real'"
                    class="relative p-3 rounded-xl border-2 text-left transition-all duration-200 focus:outline-none"
                    :class="filtroEmpresaTipo === 'real'
                      ? 'bg-[#00A859] border-[#00A859] shadow-md scale-[1.02]'
                      : 'bg-white border-gray-100 hover:border-[#00A859]/50 hover:shadow-sm'">
                    <div class="flex items-end justify-between mb-1.5">
                      <svg class="w-4 h-4" :class="filtroEmpresaTipo === 'real' ? 'text-white' : 'text-[#00A859]'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1z"/>
                      </svg>
                      <span class="text-xl font-black leading-none" :class="filtroEmpresaTipo === 'real' ? 'text-white' : 'text-[#00A859]'">{{ conteoEmpresaTipo.real }}</span>
                    </div>
                    <p class="text-[9px] font-black uppercase tracking-[0.15em] leading-none" :class="filtroEmpresaTipo === 'real' ? 'text-white' : 'text-gray-600'">Real</p>
                    <p class="text-[8px] mt-0.5 leading-none" :class="filtroEmpresaTipo === 'real' ? 'text-white/60' : 'text-gray-400'">empresa real</p>
                  </button>

                  <button
                    @click="filtroEmpresaTipo = filtroEmpresaTipo === 'ficticia' ? '' : 'ficticia'"
                    class="relative p-3 rounded-xl border-2 text-left transition-all duration-200 focus:outline-none"
                    :class="filtroEmpresaTipo === 'ficticia'
                      ? 'bg-[#F59E0B] border-[#F59E0B] shadow-md scale-[1.02]'
                      : 'bg-white border-gray-100 hover:border-[#F59E0B]/50 hover:shadow-sm'">
                    <div class="flex items-end justify-between mb-1.5">
                      <svg class="w-4 h-4" :class="filtroEmpresaTipo === 'ficticia' ? 'text-white' : 'text-[#F59E0B]'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>
                      </svg>
                      <span class="text-xl font-black leading-none" :class="filtroEmpresaTipo === 'ficticia' ? 'text-white' : 'text-[#F59E0B]'">{{ conteoEmpresaTipo.ficticia }}</span>
                    </div>
                    <p class="text-[9px] font-black uppercase tracking-[0.15em] leading-none" :class="filtroEmpresaTipo === 'ficticia' ? 'text-white' : 'text-gray-600'">Ficticia</p>
                    <p class="text-[8px] mt-0.5 leading-none" :class="filtroEmpresaTipo === 'ficticia' ? 'text-white/60' : 'text-gray-400'">inventada</p>
                  </button>

                </div>
                  </div>
                </Transition>
              </div>

              <!-- GRUPO: INFORMACIÓN -->
              <div class="bg-[#6366F1]/10 rounded-2xl border border-[#6366F1]/30">
                <button @click="infoFiltroAbierto = !infoFiltroAbierto"
                  class="w-full flex items-center justify-between px-3 py-2.5 hover:bg-[#6366F1]/15 transition-colors">
                  <div class="flex items-center gap-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-[#6366F1]">Información del reto</p>
                    <span class="tooltip-wrap" @click.stop>
                      <svg class="w-3 h-3 text-[#6366F1]/60 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg>
                      <span class="tooltip-text">Datos del reto reales (de empresa existente) o generados por IA para simulación.</span>
                    </span>
                    <span v-if="!infoFiltroAbierto && filtroInfoSimulada"
                      class="text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                      :class="filtroInfoSimulada === 'simulada' ? 'bg-[#6366F1]/10 text-[#6366F1]' : 'bg-[#00A859]/10 text-[#00A859]'">
                      {{ filtroInfoSimulada === 'simulada' ? 'Simulada' : 'Real' }}
                    </span>
                  </div>
                  <svg class="w-3.5 h-3.5 text-[#6366F1]/70 transition-transform duration-200"
                    :class="infoFiltroAbierto ? 'rotate-180' : ''"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <Transition name="filter-collapse">
                  <div v-if="infoFiltroAbierto" class="px-3 pb-3">
                <div class="grid grid-cols-3 gap-2">

                  <button
                    @click="filtroInfoSimulada = ''"
                    class="relative p-3 rounded-xl border-2 text-left transition-all duration-200 focus:outline-none"
                    :class="filtroInfoSimulada === ''
                      ? 'bg-[#1F2937] border-[#1F2937] shadow-md scale-[1.02]'
                      : 'bg-white border-gray-100 hover:border-gray-300 hover:shadow-sm'">
                    <div class="flex items-end justify-between mb-1.5">
                      <svg class="w-4 h-4" :class="filtroInfoSimulada === '' ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                      </svg>
                      <span class="text-xl font-black leading-none" :class="filtroInfoSimulada === '' ? 'text-white' : 'text-gray-400'">{{ conteoInfoSimulada.total }}</span>
                    </div>
                    <p class="text-[9px] font-black uppercase tracking-[0.15em] leading-none" :class="filtroInfoSimulada === '' ? 'text-white' : 'text-gray-600'">Toda</p>
                  </button>

                  <button
                    @click="filtroInfoSimulada = filtroInfoSimulada === 'real' ? '' : 'real'"
                    class="relative p-3 rounded-xl border-2 text-left transition-all duration-200 focus:outline-none"
                    :class="filtroInfoSimulada === 'real'
                      ? 'bg-[#00A859] border-[#00A859] shadow-md scale-[1.02]'
                      : 'bg-white border-gray-100 hover:border-[#00A859]/50 hover:shadow-sm'">
                    <div class="flex items-end justify-between mb-1.5">
                      <svg class="w-4 h-4" :class="filtroInfoSimulada === 'real' ? 'text-white' : 'text-[#00A859]'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      <span class="text-xl font-black leading-none" :class="filtroInfoSimulada === 'real' ? 'text-white' : 'text-[#00A859]'">{{ conteoInfoSimulada.real }}</span>
                    </div>
                    <p class="text-[9px] font-black uppercase tracking-[0.15em] leading-none" :class="filtroInfoSimulada === 'real' ? 'text-white' : 'text-gray-600'">Real</p>
                    <p class="text-[8px] mt-0.5 leading-none" :class="filtroInfoSimulada === 'real' ? 'text-white/60' : 'text-gray-400'">datos reales</p>
                  </button>

                  <button
                    @click="filtroInfoSimulada = filtroInfoSimulada === 'simulada' ? '' : 'simulada'"
                    class="relative p-3 rounded-xl border-2 text-left transition-all duration-200 focus:outline-none"
                    :class="filtroInfoSimulada === 'simulada'
                      ? 'bg-[#6366F1] border-[#6366F1] shadow-md scale-[1.02]'
                      : 'bg-white border-gray-100 hover:border-[#6366F1]/50 hover:shadow-sm'">
                    <div class="flex items-end justify-between mb-1.5">
                      <svg class="w-4 h-4" :class="filtroInfoSimulada === 'simulada' ? 'text-white' : 'text-[#6366F1]'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                      </svg>
                      <span class="text-xl font-black leading-none" :class="filtroInfoSimulada === 'simulada' ? 'text-white' : 'text-[#6366F1]'">{{ conteoInfoSimulada.simulada }}</span>
                    </div>
                    <p class="text-[9px] font-black uppercase tracking-[0.15em] leading-none" :class="filtroInfoSimulada === 'simulada' ? 'text-white' : 'text-gray-600'">Simulada</p>
                    <p class="text-[8px] mt-0.5 leading-none" :class="filtroInfoSimulada === 'simulada' ? 'text-white/60' : 'text-gray-400'">generada IA</p>
                  </button>

                </div>
                  </div>
                </Transition>
              </div>

            </div>

            <!-- ── BARRA DE FILTROS ──────────────────────────────── -->
            <section class="bg-white/90 backdrop-blur-md rounded-[2rem] p-5 border border-gray-100 shadow-[0_20px_50px_rgb(0,0,0,0.04)] mb-8">

              <!-- Búsqueda -->
              <div class="mb-4">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 ml-1 mb-2 flex items-center gap-1.5">
                  Búsqueda por palabras clave
                  <span class="tooltip-wrap">
                    <svg class="w-3 h-3 text-gray-400 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg>
                    <span class="tooltip-text">Busca en título, empresa, ciclo formativo y pregunta del reto.</span>
                  </span>
                </label>
                <div class="relative">
                  <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <input
                    v-model="busqueda"
                    type="text"
                    placeholder="Título, empresa, ciclo, pregunta del reto..."
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-10 py-3.5 text-sm font-medium text-[#1F2937] placeholder-gray-400 focus:bg-white focus:border-[#00A859] focus:ring-2 focus:ring-[#00A859]/10 outline-none transition-all"
                  />
                  <Transition name="fade">
                    <button v-if="busqueda" @click="busqueda = ''"
                      class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300 text-gray-500 transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </Transition>
                </div>
              </div>

              <!-- Ciclo + Curso -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div>
                  <label class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 ml-1 mb-2 flex items-center gap-1.5">
                    Ciclo Formativo
                    <span class="tooltip-wrap">
                      <svg class="w-3 h-3 text-gray-400 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg>
                      <span class="tooltip-text">Filtra por ciclo formativo al que está orientado el reto.</span>
                    </span>
                  </label>
                  <select v-model="filtroCiclo"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3.5 text-sm font-bold text-[#1F2937] focus:bg-white focus:border-[#00A859] focus:ring-2 focus:ring-[#00A859]/10 outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="ciclosDisponibles.length === 0">
                    <option value="">Todos los ciclos...</option>
                    <option v-for="ciclo in ciclosDisponibles" :key="ciclo" :value="ciclo">{{ ciclo }}</option>
                  </select>
                </div>

                <div>
                  <label class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 ml-1 mb-2 flex items-center gap-1.5">
                    Curso
                    <span class="tooltip-wrap">
                      <svg class="w-3 h-3 text-gray-400 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-4m0-4h.01"/></svg>
                      <span class="tooltip-text">Filtra por el año del ciclo: 1º (primer año) o 2º (segundo año).</span>
                    </span>
                  </label>
                  <div class="flex gap-2">
                    <button
                      @click="filtroCurso = ''"
                      class="flex-1 py-3.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all border"
                      :class="filtroCurso === ''
                        ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-sm'
                        : 'bg-gray-50 text-gray-500 border-gray-200 hover:border-gray-400 hover:text-[#1F2937]'">
                      Todos
                    </button>
                    <button
                      v-for="curso in cursosDisponibles"
                      :key="curso"
                      @click="filtroCurso = filtroCurso === String(curso) ? '' : String(curso)"
                      class="flex-1 py-3.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all border"
                      :class="filtroCurso === String(curso)
                        ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-sm'
                        : 'bg-gray-50 text-gray-500 border-gray-200 hover:border-gray-400 hover:text-[#1F2937]'">
                      {{ curso }}º
                    </button>
                    <button
                      v-if="hayRetosAmbosCursos"
                      @click="filtroCurso = filtroCurso === 'ambos_cursos' ? '' : 'ambos_cursos'"
                      title="Ambos Cursos: posibilidad 1º y 2º"
                      class="flex-1 py-3.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all border"
                      :class="filtroCurso === 'ambos_cursos'
                        ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-sm'
                        : 'bg-gray-50 text-gray-500 border-gray-200 hover:border-gray-400 hover:text-[#1F2937]'">
                      Ambos Cursos
                    </button>
                  </div>
                </div>

              </div>

              <!-- Chips de filtros activos -->
              <Transition name="fade">
                <div v-if="hayFiltrosActivos" class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                  <span class="text-[9px] font-black uppercase tracking-widest text-gray-400">Activos:</span>

                  <span v-if="filtroEmpresaTipo"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
                    :class="filtroEmpresaTipo === 'ficticia' ? 'bg-[#F59E0B]/10 text-[#F59E0B]' : 'bg-[#00A859]/10 text-[#00A859]'">
                    {{ filtroEmpresaTipo === 'ficticia' ? 'Empresa Ficticia' : 'Empresa Real' }}
                    <button @click="filtroEmpresaTipo = ''" class="ml-0.5 hover:opacity-70 transition-opacity">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </span>

                  <span v-if="filtroInfoSimulada"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
                    :class="filtroInfoSimulada === 'simulada' ? 'bg-[#6366F1]/10 text-[#6366F1]' : 'bg-[#00A859]/10 text-[#00A859]'">
                    {{ filtroInfoSimulada === 'simulada' ? 'Info Simulada' : 'Info Real' }}
                    <button @click="filtroInfoSimulada = ''" class="ml-0.5 hover:opacity-70 transition-opacity">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </span>

                  <span v-if="filtroNivel"
                    class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider">
                    Nivel {{ filtroNivel }}
                    <button @click="filtroNivel = ''" class="ml-0.5 text-gray-400 hover:text-gray-700 transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </span>

                  <span v-if="filtroCurso"
                    class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider">
                    Curso {{ filtroCurso }}º
                    <button @click="filtroCurso = ''" class="ml-0.5 text-gray-400 hover:text-gray-700 transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </span>

                  <span v-if="filtroCiclo"
                    class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider max-w-[220px]">
                    <span class="truncate">{{ filtroCiclo }}</span>
                    <button @click="filtroCiclo = ''" class="ml-0.5 text-gray-400 hover:text-gray-700 transition-colors shrink-0">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </span>

                  <span v-if="busqueda"
                    class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full text-[10px] font-black tracking-wider max-w-[200px]">
                    <svg class="w-3 h-3 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span class="truncate italic">"{{ busqueda }}"</span>
                    <button @click="busqueda = ''" class="ml-0.5 text-gray-400 hover:text-gray-700 transition-colors shrink-0">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </span>

                  <button @click="limpiarFiltros"
                    class="ml-auto text-[10px] font-black uppercase tracking-widest text-red-400 hover:text-red-600 transition-colors flex items-center gap-1 shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Limpiar todo
                  </button>
                </div>
              </Transition>

            </section>

            <!-- ── GRID DE MICRORETOS ───────────────────────────── -->
            <div v-if="microretosFiltrados.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div v-for="reto in microretosFiltrados" :key="reto.id"
                class="bg-white rounded-[1.5rem] border border-gray-100 hover:border-[#00A859]/40 hover:shadow-[0_20px_40px_rgba(0,0,0,0.06)] shadow-sm transition-all duration-300 flex flex-col group overflow-hidden transform hover:-translate-y-1">

                <div class="p-5 pb-0 flex items-start justify-between gap-2">
                  <!-- Papelera -->
                  <button v-if="!authStore.isEmpresa"
                    @click.prevent="abrirModalEliminar(reto)"
                    class="shrink-0 w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center shadow-sm transition-all duration-300 hover:bg-red-50 hover:border-red-300 active:scale-95"
                    title="Eliminar reto">
                    <svg class="w-4 h-4 text-gray-400 hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>

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
                        : 'bg-[#00A859]/10 border-[#00A859]/20 text-[#00A859]'">
                      {{ reto.empresa_es_simulada ? 'Empresa ficticia' : 'Empresa real' }}
                    </span>
                    <span v-if="reto.es_simulado"
                      class="bg-rose-50 border border-rose-200 text-rose-700 px-2.5 py-0.5 text-[8px] font-black uppercase tracking-widest rounded-full whitespace-nowrap">
                      Info simulada
                    </span>
                  </div>
                </div>

                <div class="px-7 pb-7 pt-4 flex-1 flex flex-col">
                  <h3 class="text-[#1F2937] font-black text-xl leading-tight mb-4 group-hover:text-[#00A859] transition-colors line-clamp-2" :title="reto.titulo">
                    {{ reto.titulo }}
                  </h3>
                  <div class="flex flex-col gap-2 mb-5 border-l-2 border-gray-100 group-hover:border-[#00A859]/30 pl-3 transition-colors">
                    <p class="text-[#1F2937] text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                      <svg class="w-4 h-4 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                <div class="flex border-t border-gray-100 group-hover:border-[#00A859] transition-colors duration-300">
                  <button @click.stop="descargarPDF(reto)"
                    class="flex items-center justify-center gap-1.5 px-4 py-4 bg-gray-50 group-hover:bg-[#00A859]/10 text-gray-400 group-hover:text-[#00A859] font-black text-[10px] uppercase tracking-widest transition-all duration-300 border-r border-gray-100 group-hover:border-[#00A859]/20 shrink-0"
                    title="Descargar PDF">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    PDF
                  </button>
                  <button @click="irADetalle(reto)"
                    class="flex-1 bg-gray-50 group-hover:bg-[#00A859] text-gray-500 group-hover:text-white font-black text-xs uppercase tracking-widest py-4 transition-all duration-300 flex items-center justify-center gap-2">
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
                <svg class="w-10 h-10 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 class="text-[#1F2937] font-black text-2xl mb-2">No hay resultados</h3>
              <p class="text-gray-500 text-sm max-w-md mx-auto">Prueba a limpiar los filtros o genera nuevos retos en el Estudio interactivo.</p>
              <button @click="limpiarFiltros"
                class="mt-6 px-6 py-2 bg-white border border-gray-200 hover:border-[#00A859] hover:text-[#00A859] text-[#1F2937] rounded-full text-xs font-bold uppercase tracking-widest transition-colors shadow-sm">
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
::-webkit-scrollbar-thumb:hover { background: #00A859; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.snack-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.snack-leave-active { transition: all 0.2s ease-in; }
.snack-enter-from   { opacity: 0; transform: translateY(12px); }
.snack-leave-to     { opacity: 0; transform: translateY(8px); }

.slide-up-enter-active { transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-leave-active { transition: all 0.2s ease-in; }
.slide-up-enter-from { opacity: 0; transform: translateY(20px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-10px); }

.filter-collapse-enter-active { transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
.filter-collapse-leave-active { transition: all 0.15s ease-in; }
.filter-collapse-enter-from, .filter-collapse-leave-to { opacity: 0; transform: translateY(-6px); }

.tooltip-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
}
.tooltip-text {
  position: absolute;
  bottom: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%);
  background: #1F2937;
  color: #fff;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.4;
  padding: 6px 10px;
  border-radius: 8px;
  width: max-content;
  max-width: 210px;
  white-space: normal;
  text-align: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease;
  z-index: 30;
  text-transform: none;
  letter-spacing: 0.01em;
}
.tooltip-wrap:hover .tooltip-text { opacity: 1; }
.tooltip-text::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #1F2937;
}
</style>
