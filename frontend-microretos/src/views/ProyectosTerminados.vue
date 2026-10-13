<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import MigasNavegacion from '../components/MigasNavegacion.vue'
import { migasFamilia } from '../utils/migasFamilia.js'
import { useRouter, useRoute } from 'vue-router';
import api from '../api.js';
import EliminarProyectoModal from '../components/EliminarProyectoModal.vue';
import ProyectoCard from '../components/ProyectoCard.vue';
import RecordatorioPropuestaFlotante from '../components/RecordatorioPropuestaFlotante.vue';
import BienvenidaStartupDayModal from '../components/BienvenidaStartupDayModal.vue';
import { useAuthStore } from '../stores/auth.js';
import { useCursoAcademicoStore, cursoDeProyecto } from '../stores/cursoAcademico.js';
import SelectorCurso from '../components/SelectorCurso.vue';
import { useRoleTheme } from '../composables/useRoleTheme.js';
import { useFiltrosProyectos, SIN_FAMILIA } from '../composables/useFiltrosProyectos.js';
import FiltrosPanel from '../components/FiltrosPanel.vue';
import FiltroSegmentado from '../components/FiltroSegmentado.vue';
import FiltroSelect from '../components/FiltroSelect.vue';

const router = useRouter();
const route  = useRoute();
const authStore = useAuthStore();
const { theme } = useRoleTheme();

const proyectos = ref([]);
const cargando  = ref(true);
const isLoaded  = ref(false);

onMounted(async () => {
  setTimeout(() => { isLoaded.value = true; }, 80);
  try {
    const res = await api.get('/startup/proyectos');
    proyectos.value = res.data;
  } finally {
    cargando.value = false;
  }
  // Deep-link desde la card de familia de /proyectos ("Ver completados") —
  // preselecciona el chip de familia.
  if (route.query.familia) {
    seleccionarFamilia(String(route.query.familia));
  }
});

function irAPendientes() {
  router.push({ name: 'startup-day', query: filtroFamilia.value ? { capa: 'estados', familia: filtroFamilia.value } : {} });
}

// ─── Modal "¿Qué necesitas?" ──────────────────────────────────────────────────
// Este archivo es solo el histórico de completados, así que 'crear'/'trabajar'/'guia'
// llevan siempre a /proyectos (la biblioteca activa), que es donde vive cada acción.
const guiaBienvenida = ref(false);
function seleccionarOpcionBienvenida(opcion) {
  guiaBienvenida.value = false;
  if (opcion === 'crear') {
    router.push({ name: 'startup-day-crear' });
  } else if (opcion === 'trabajar' || opcion === 'guia') {
    router.push({ name: 'startup-day' });
  }
}

// Curso académico compartido (store): solo los completados del curso elegido
const cursoStore     = useCursoAcademicoStore();
const cursosConDatos = computed(() => [...new Set(proyectos.value.map(cursoDeProyecto))]);
const completados = computed(() => proyectos.value.filter(p => p.estado === 'completado' && cursoStore.coincide(cursoDeProyecto(p))));

// Filtros por familia/ciclo/curso — chips planos (sin capa de familias: esta
// vista ya cuelga de un clic extra desde /proyectos, así que se evita otro).
const {
  busqueda, filtroFamilia, filtroCiclo, filtroModulo, filtroCurso,
  familiasDisponibles, ciclosDisponibles, modulosDisponibles, cursosDisponibles,
  seleccionarFamilia,
  limpiarFiltrosDetalle, hayFiltrosDetalleActivos, aplicarFiltros,
} = useFiltrosProyectos(completados);

// La familia se refleja en la URL (en vez de limpiarla tras aplicarla) para que
// el botón "Atrás" del navegador, al volver desde la ficha de un proyecto,
// restaure la misma URL y el chip siga marcado — si no, al desmontarse esta
// vista se pierde el estado y no hay forma de recuperarlo solo con el historial.
watch(filtroFamilia, (nombre) => {
  router.replace({ name: 'proyectos-terminados', query: nombre ? { familia: nombre } : {} });
});

// Validado por: un proyecto solo llega a 'completado' desde 'validado', y conserva los
// flags empresa_validado / docente_validado. Si lo validaron ambos, aparece en los dos.
const filtroValidador = ref('');
const VALIDADORES = { empresa: p => !!p.empresa_validado, docente: p => !!p.docente_validado };
const opcionesValidador = computed(() => [
  { value: '',        label: 'Todos' },
  { value: 'empresa', label: 'Empresa', count: completados.value.filter(VALIDADORES.empresa).length },
  { value: 'docente', label: 'Docente', count: completados.value.filter(VALIDADORES.docente).length },
]);

const proyectosFiltrados = computed(() => {
  const lista = aplicarFiltros(completados.value);
  return filtroValidador.value ? lista.filter(VALIDADORES[filtroValidador.value]) : lista;
});

const hayFiltrosActivos = computed(() => !!filtroFamilia.value || !!filtroValidador.value || hayFiltrosDetalleActivos.value);

function limpiarFiltros() {
  seleccionarFamilia('');
  filtroValidador.value = '';
  limpiarFiltrosDetalle();
}

// ── Tarjeta de filtros (FiltrosPanel) ─────────────────────────────────────────
// Estado: esta vista solo lista completados; "En proceso" vive en /proyectos, así que
// elegirlo navega allí (con la familia activa) en vez de filtrar aquí.
const opcionesEstado = computed(() => [
  { value: 'completado', label: 'Completados', count: completados.value.length },
  { value: 'proceso',    label: 'En proceso' },
]);
const estadoVista = computed({
  get: () => 'completado',
  set: (v) => { if (v === 'proceso') irAPendientes(); },
});

const opcionesFamilia = computed(() =>
  familiasDisponibles.value.map(f => ({ value: f.nombre, label: `${f.nombre} (${f.count})` }))
);
// Al cambiar familia o ciclo se reinician los filtros dependientes (como hacían los chips)
const familiaSel = computed({
  get: () => filtroFamilia.value,
  set: (f) => { filtroFamilia.value = f; filtroCiclo.value = ''; filtroModulo.value = ''; filtroCurso.value = ''; },
});
const cicloSel = computed({
  get: () => filtroCiclo.value,
  set: (c) => { filtroCiclo.value = c; filtroCurso.value = ''; },
});
// Migas comunes a las vistas por familia (Familias › <familia> › <ciclo> › <módulo>), solo con
// una familia elegida. Aquí la familia es un filtro, no una capa: cada nivel quita los de debajo.
const migas = computed(() => migasFamilia({
  raiz:    { label: 'Familias', accion: () => { familiaSel.value = ''; } },
  familia: { label: filtroFamilia.value === SIN_FAMILIA ? 'Sin familia asignada' : filtroFamilia.value, accion: () => { cicloSel.value = ''; filtroModulo.value = ''; } },
  ciclo:   filtroCiclo.value ? { label: filtroCiclo.value, accion: () => { filtroModulo.value = ''; } } : null,
  modulo:  filtroModulo.value ? { label: filtroModulo.value } : null,
}));

const opcionesNivel = computed(() => [{ value: '', label: 'Todos' }, ...cursosDisponibles.value.map(cu => ({ value: cu, label: `${cu}º` }))]);

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

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[700px] h-[400px]
                bg-sky-400 opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <div class="relative z-10 max-w-6xl mx-auto"
         :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
         style="transition: opacity 0.4s ease, transform 0.4s ease">

      <!-- Cabecera de sección (mismo estilo que Retos y proyectos) + acciones de la vista -->
      <CabeceraSeccion titulo="Proyectos" destacado="Completados" :color="theme.text" volver="/proyectos"
                       subtitulo="Consulta el histórico de proyectos ya finalizados y revisa el trabajo entregado por cada grupo."
                       class="mb-6">
        <div class="flex flex-wrap items-center gap-2">
          <!-- ¿Qué necesitas? — mismo patrón que /proyectos y /encuentros. -->
          <button type="button" @click="guiaBienvenida = true" class="accion-secundaria" title="¿Qué necesitas? Opciones y guía de esta pantalla">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Ayuda
          </button>
        </div>
      </CabeceraSeccion>

      <!-- Recordatorio propuesta ≠ proyecto (se oculta al cerrarlo; empty:hidden quita su hueco) -->
      <div class="mb-6 empty:hidden">
        <RecordatorioPropuestaFlotante />
      </div>

      <!-- ─── Filtros ──────────────────────────────────────────────────────── -->
      <FiltrosPanel v-model:busqueda="busqueda" placeholder="Buscar por título, empresa o centro…"
                    :resultados="proyectosFiltrados.length" :unidad="['proyecto completado', 'proyectos completados']"
                    :hay-activos="hayFiltrosActivos" @limpiar="limpiarFiltros" class="mb-6">
        <template #curso>
          <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
        </template>

        <!-- Estado: "En proceso" navega a /proyectos (ver estadoVista) -->
        <FiltroSegmentado v-model="estadoVista" label="Estado" :opciones="opcionesEstado" />
        <FiltroSegmentado v-model="filtroValidador" label="Validado por" :opciones="opcionesValidador" />
        <FiltroSelect v-if="opcionesFamilia.length" v-model="familiaSel"
                      label="Familia profesional" todos="Todas las familias" :opciones="opcionesFamilia" />
        <FiltroSelect v-if="filtroFamilia && ciclosDisponibles.length" v-model="cicloSel"
                      label="Ciclo formativo" todos="Todos los ciclos" :opciones="ciclosDisponibles" />
        <FiltroSelect v-if="filtroFamilia && modulosDisponibles.length" v-model="filtroModulo"
                      label="Módulo" todos="Todos los módulos" :opciones="modulosDisponibles" />
        <FiltroSegmentado v-if="filtroFamilia && cursosDisponibles.length" v-model="filtroCurso"
                          label="Nivel" :opciones="opcionesNivel" />
      </FiltrosPanel>

      <MigasNavegacion v-if="filtroFamilia && !cargando" :items="migas.items" :actual="migas.actual" class="mb-6" />

      <!-- Cargando -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
        <svg class="animate-spin w-12 h-12 text-sky-500 mb-4" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="text-sky-500 font-black tracking-widest uppercase text-sm animate-pulse">Cargando...</p>
      </div>

      <!-- Vacío -->
      <div v-else-if="proyectosFiltrados.length === 0"
           class="text-center py-24 bg-white rounded-[2rem] border border-dashed border-gray-200 shadow-sm">
        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-5
                    border border-gray-100 shadow-inner">
          <svg class="w-10 h-10 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/>
          </svg>
        </div>
        <h3 class="text-azul-noche font-black text-xl mb-2">
          {{ hayFiltrosActivos ? 'Sin resultados' : 'Todavía no hay proyectos completados' }}
        </h3>
        <p class="text-gray-400 text-sm">
          {{ hayFiltrosActivos ? 'Prueba con otros filtros' : 'Aquí aparecerán los proyectos cuando se marquen como completados' }}
        </p>
      </div>

      <!-- Grid de tarjetas -->
      <div v-else class="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <ProyectoCard
          v-for="p in proyectosFiltrados" :key="p.uuid"
          :proyecto="p"
          @eliminar="abrirModalEliminar"
        />
      </div>

    </div>
  </div>

  <!-- MODAL ¿QUÉ NECESITAS? -->
  <BienvenidaStartupDayModal :show="guiaBienvenida" @seleccionar="seleccionarOpcionBienvenida" />

  <!-- MODAL ELIMINAR PROYECTO -->
  <EliminarProyectoModal
    :visible="modalEliminarVisible"
    :proyecto="proyectoAEliminar"
    @proyecto-eliminado="onProyectoEliminado"
    @cerrar="cerrarModalEliminar"
  />

  <!-- SNACKBAR -->
  <Transition name="pt-snack">
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
.pt-snack-enter-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.pt-snack-leave-active { transition: all 0.2s ease-in; }
.pt-snack-enter-from   { opacity: 0; transform: translateY(12px); }
.pt-snack-leave-to     { opacity: 0; transform: translateY(8px); }
</style>
