<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../api.js';
import EliminarProyectoModal from '../components/EliminarProyectoModal.vue';
import ProyectoCard from '../components/ProyectoCard.vue';
import RecordatorioPropuestaFlotante from '../components/RecordatorioPropuestaFlotante.vue';
import BienvenidaStartupDayModal from '../components/BienvenidaStartupDayModal.vue';
import { useAuthStore } from '../stores/auth.js';
import { useRoleTheme } from '../composables/useRoleTheme.js';
import { useFiltrosProyectos } from '../composables/useFiltrosProyectos.js';

const router = useRouter();
const route  = useRoute();
const authStore = useAuthStore();
const { theme } = useRoleTheme();

const paletteExtra = {
  centros:          { focusBorder: 'focus:border-centros', hoverBorderText: 'hover:border-centros hover:text-centros' },
  empresas:         { focusBorder: 'focus:border-empresas', hoverBorderText: 'hover:border-empresas hover:text-empresas' },
  administraciones: { focusBorder: 'focus:border-administraciones', hoverBorderText: 'hover:border-administraciones hover:text-administraciones' },
  primary:          { focusBorder: 'focus:border-primary-600', hoverBorderText: 'hover:border-primary-600 hover:text-primary-700' },
};

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
  // preselecciona el chip de familia y limpia la query.
  if (route.query.familia) {
    seleccionarFamilia(String(route.query.familia));
    router.replace({ name: 'proyectos-terminados' });
  }
});

function irAPendientes() {
  router.push({ name: 'startup-day' });
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

const completados = computed(() => proyectos.value.filter(p => p.estado === 'completado'));

// Filtros por familia/ciclo/curso — chips planos (sin capa de familias: esta
// vista ya cuelga de un clic extra desde /proyectos, así que se evita otro).
const {
  busqueda, filtroFamilia, filtroCiclo, filtroCurso,
  familiasDisponibles, ciclosDisponibles, cursosDisponibles,
  seleccionarFamilia, seleccionarCiclo, seleccionarCurso,
  limpiarFiltrosDetalle, hayFiltrosDetalleActivos, aplicarFiltros,
} = useFiltrosProyectos(completados);

const proyectosFiltrados = computed(() => aplicarFiltros(completados.value));

const hayFiltrosActivos = computed(() => !!filtroFamilia.value || hayFiltrosDetalleActivos.value);

function limpiarFiltros() {
  seleccionarFamilia('');
  limpiarFiltrosDetalle();
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

      <!-- HEADER -->
      <header class="mb-10 text-center flex flex-col items-center">
        <div
          class="inline-flex items-center mb-8 bg-[#1F2937] py-3 sm:py-4 pr-6 sm:pr-10 pl-4 sm:pl-6 rounded-[3rem] shadow-lg border border-[#333333] transition-all duration-1000 ease-out transform"
          :class="isLoaded ? 'translate-y-0 opacity-100' : '-translate-y-10 opacity-0'">
          <img src="../assets/logo_colores.png" alt="Logo DuaLab"
            class="h-20 sm:h-32 md:h-40 w-auto object-contain relative z-10 mr-2 sm:mr-3 md:mr-5" />
          <span class="font-black text-2xl sm:text-4xl md:text-5xl tracking-tighter uppercase text-white italic relative z-20">
            Dua<span class="text-centros-light">Lab</span>
            <span class="not-italic text-sm sm:text-lg md:text-xl ml-1 text-centros-light">Proyectos</span>
          </span>
        </div>
        <h1
          class="text-4xl md:text-5xl font-black tracking-tight mb-4 text-[#121212] transition-all duration-1000 delay-150 ease-out transform"
          :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
          Proyectos <span :class="theme.text">Completados</span>
        </h1>
        <p class="text-gray-500 max-w-2xl mx-auto text-base md:text-lg leading-relaxed font-medium transition-all duration-1000 delay-300 ease-out transform"
          :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
          Consulta el histórico de proyectos ya finalizados y revisa el trabajo entregado por cada equipo.
        </p>
      </header>

      <!-- Acciones -->
      <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <RecordatorioPropuestaFlotante />
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <!-- Botón ¿Qué necesitas? — mismo patrón que /proyectos y /encuentros. -->
            <button @click="guiaBienvenida = true"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full
                           bg-centros/10 border border-centros/20 text-centros
                           text-[10px] font-black uppercase tracking-widest
                           hover:bg-centros/20 transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              ¿Qué necesitas?
            </button>
            <button
              @click="router.push({ name: 'startup-day' })"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-full
                     bg-gray-100 border border-gray-200 text-gray-500
                     text-[10px] font-black uppercase tracking-widest
                     hover:bg-gray-200 transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
              Ver el resto de proyectos
            </button>
          </div>
        </div>
      </div>

      <!-- Filtros -->
      <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-5">

        <!-- Búsqueda -->
        <div class="relative w-full lg:flex-1 lg:min-w-[240px]">
          <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
          </svg>
          <input
            v-model="busqueda" type="text"
            placeholder="Buscar por título, empresa o centro..."
            class="w-full bg-white border border-gray-200 rounded-2xl pl-10 pr-4 py-3
                   text-sm text-[#1F2937] placeholder-gray-400 shadow-sm
                   focus:outline-none transition-colors"
            :class="paletteExtra[theme.key].focusBorder"
          />
        </div>

        <!-- Filtros: completados (activo por defecto) / pendientes -->
        <div class="flex flex-wrap gap-2 lg:justify-end lg:min-w-0">
          <button
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-black
                   uppercase tracking-widest border transition-all
                   bg-[#1F2937] text-white border-[#1F2937] shadow-md">
            Completados
            <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] rounded-full
                         bg-white/20 text-white text-[9px] font-black">{{ completados.length }}</span>
          </button>
          <button
            @click="irAPendientes"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-black
                   uppercase tracking-widest border transition-all
                   bg-white text-gray-500 border-gray-200"
            :class="paletteExtra[theme.key].hoverBorderText">
            En proceso
          </button>
        </div>

      </div>

      <!-- Separador: distingue el filtro de estado (arriba) del filtro por taxonomía académica (abajo) -->
      <div v-if="familiasDisponibles.length > 0" class="border-t border-gray-100 mb-5"></div>

      <!-- Filtros por familia / ciclo / curso -->
      <div v-if="familiasDisponibles.length > 0" class="flex flex-col gap-2 mb-6">
        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 -mt-1">Filtrar por familia profesional</p>
        <div class="flex flex-wrap items-center gap-2">
          <button v-for="f in familiasDisponibles" :key="f.nombre"
                  @click="seleccionarFamilia(f.nombre)"
                  :class="[
                    'inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[11px] font-black uppercase tracking-wide border transition-all',
                    filtroFamilia === f.nombre
                      ? [theme.bg, theme.border, 'text-white shadow-sm']
                      : ['bg-white text-gray-500 border-gray-200', paletteExtra[theme.key].hoverBorderText]
                  ]">
            {{ f.nombre }}
            <span :class="[
              'inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] rounded-full text-[9px] font-black',
              filtroFamilia === f.nombre ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'
            ]">{{ f.count }}</span>
          </button>
          <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[11px] font-bold
                         text-gray-400 hover:text-gray-600 transition-colors">
            Limpiar filtros ✕
          </button>
        </div>

        <div v-if="filtroFamilia && ciclosDisponibles.length > 0" class="flex flex-wrap items-center gap-2">
          <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 mr-1">Ciclo</span>
          <button v-for="c in ciclosDisponibles" :key="c"
                  @click="seleccionarCiclo(c)"
                  :class="[
                    'px-3 py-1 rounded-full text-[10px] font-bold border transition-all',
                    filtroCiclo === c
                      ? 'bg-[#1F2937] text-white border-[#1F2937]'
                      : ['bg-white text-gray-500 border-gray-200', paletteExtra[theme.key].hoverBorderText]
                  ]">
            {{ c }}
          </button>
        </div>

        <div v-if="filtroFamilia && cursosDisponibles.length > 0" class="flex flex-wrap items-center gap-2">
          <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 mr-1">Curso</span>
          <button v-for="cu in cursosDisponibles" :key="cu"
                  @click="seleccionarCurso(cu)"
                  :class="[
                    'px-3 py-1 rounded-full text-[10px] font-bold border transition-all',
                    filtroCurso === cu
                      ? 'bg-[#1F2937] text-white border-[#1F2937]'
                      : ['bg-white text-gray-500 border-gray-200', paletteExtra[theme.key].hoverBorderText]
                  ]">
            {{ cu }}º
          </button>
        </div>
      </div>

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
        <h3 class="text-[#1F2937] font-black text-xl mb-2">
          {{ hayFiltrosActivos ? 'Sin resultados' : 'Todavía no hay proyectos completados' }}
        </h3>
        <p class="text-gray-400 text-sm">
          {{ hayFiltrosActivos ? 'Prueba con otros filtros' : 'Aquí aparecerán los proyectos cuando se marquen como completados' }}
        </p>
      </div>

      <!-- Grid de tarjetas -->
      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
