<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter, useRoute, onBeforeRouteUpdate } from 'vue-router';
import api from '../api.js';
import BienvenidaStartupDayModal from '../components/BienvenidaStartupDayModal.vue';
import EliminarProyectoModal from '../components/EliminarProyectoModal.vue';
import ProyectoCard from '../components/ProyectoCard.vue';
import { useUIState } from '../composables/useUIState.js';
import { useAuthStore } from '../stores/auth.js';

const router = useRouter();
const route  = useRoute();
const { tourActivo } = useUIState();
const authStore = useAuthStore();

// ── Datos ───────────────────────────────────────────────────────────────────
const proyectos    = ref([]);
const cargando     = ref(true);
const busqueda     = ref('');
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
  { ref: 'refBtnGuia',  seccion: null,        texto: 'Pulsa este botón en cualquier momento para volver a ver esta guía y repasar el funcionamiento de la sección.' },
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
    modoGuia.value = true;
    pasoGuia.value = 1;
  }
  // 'trabajar' → se queda en la vista
}

onMounted(async () => {
  setTimeout(() => { isLoaded.value = true; }, 80);
  if (route.query.filtro === 'completado') {
    router.replace({ name: 'proyectos-terminados' });
  } else if (route.query.filtro) {
    filtroEstado.value = String(route.query.filtro);
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
const proyectosVisibles = computed(() => proyectos.value.filter(p => p.estado !== 'completado'));
const totalCompletados   = computed(() => proyectos.value.filter(p => p.estado === 'completado').length);

const filtroOpciones = ['validado', 'propuesta', 'en_edicion', 'archivado', 'todos'];
const filtroLabels   = { todos: 'Todos', en_edicion: 'En edición', propuesta: 'Pendiente validar', validado: 'Validados', archivado: 'Archivado' };

const conteosPorEstado = computed(() => ({
  validado:   proyectosVisibles.value.filter(p => p.estado === 'validado').length,
  propuesta:  proyectosVisibles.value.filter(p => p.estado === 'propuesta').length,
  en_edicion: proyectosVisibles.value.filter(p => p.estado === 'en_edicion').length,
  archivado:  proyectosVisibles.value.filter(p => p.estado === 'archivado').length,
  todos:      proyectosVisibles.value.length,
}));

const proyectosFiltrados = computed(() => {
  let lista = proyectosVisibles.value;
  if (filtroEstado.value !== 'todos') {
    lista = lista.filter(p => p.estado === filtroEstado.value);
  }
  if (busqueda.value.trim()) {
    const q = busqueda.value.toLowerCase();
    lista = lista.filter(p =>
      p.titulo?.toLowerCase().includes(q) ||
      p.empresa_nombre?.toLowerCase().includes(q) ||
      p.centro_nombre?.toLowerCase().includes(q)
    );
  }
  return lista;
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
  <div class="min-h-screen p-4 md:p-10 font-sans text-[#1F2937] pt-12 md:pt-12">

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
                        class="px-3 py-1.5 rounded-xl bg-[#00A859] text-white text-[11px] font-black
                               hover:bg-[#00A859]/80 transition-all">
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
                bg-[#99CC33] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <div class="relative z-10 max-w-6xl mx-auto"
         :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
         style="transition: opacity 0.4s ease, transform 0.4s ease">

      <!-- Cabecera -->
      <header class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h1 class="text-3xl md:text-4xl font-black tracking-tight text-[#121212]">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">Propuestas-Proyecto</span>
          </h1>
          <p class="text-gray-500 text-sm mt-1">
            Aquí se trabajan los retos para convertirlos en propuestas y, tras su validación, en proyectos de empresa.
          </p>
          <div class="mt-3 flex flex-wrap gap-2">
            <!-- Botón Guía -->
            <button ref="refBtnGuia"
                    @click="modoGuia = true; pasoGuia = 1"
                    :class="{ 'tour-active': pasoRefActivo === 'refBtnGuia' }"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full
                           bg-blue-500/10 border border-blue-500/20 text-blue-500
                           text-[10px] font-black uppercase tracking-widest
                           hover:bg-blue-500/20 transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Guía
            </button>
            <!-- Botón Proyectos Completados -->
            <button
              @click="router.push({ name: 'proyectos-terminados' })"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-full
                     bg-sky-500/10 border border-sky-500/20 text-sky-600
                     text-[10px] font-black uppercase tracking-widest
                     hover:bg-sky-500/20 transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              Ver completados
              <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] rounded-full
                           bg-sky-500/15 text-sky-600 text-[9px] font-black">{{ totalCompletados }}</span>
            </button>
            <!-- Botón Papelera — la papelera de "Base de datos" es solo superadmin -->
            <button
              v-if="authStore.isSuperAdmin"
              @click="router.push({ name: 'papelera' })"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-full
                     bg-amber-400/10 border border-amber-400/20 text-amber-600
                     text-[10px] font-black uppercase tracking-widest
                     hover:bg-amber-400/20 transition-all"
              title="Ver proyectos eliminados">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
              Papelera
            </button>
          </div>
        </div>

        <!-- Nuevo microproyecto -->
        <button
          v-if="!authStore.isEmpresa"
          ref="refBtnNuevo"
          @click="router.push({ name: 'startup-day-crear' })"
          :class="{
            'tour-active': pasoRefActivo === 'refBtnNuevo',
            'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'btn-nuevo'
          }"
          class="inline-flex items-center gap-2 px-5 py-2.5
                 bg-[#00A859] text-white rounded-full
                 text-xs font-black uppercase tracking-widest shadow-sm
                 hover:bg-[#00A859]/90 hover:shadow-[0_0_0_3px_rgba(0,168,89,0.2)]
                 transition-all active:scale-95 shrink-0"
        >
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
          </svg>
          Nueva propuesta
        </button>
      </header>

      <!-- Filtros -->
      <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-6">

        <!-- Búsqueda -->
        <div ref="refBusqueda"
             :class="{
               'tour-active': pasoRefActivo === 'refBusqueda',
               'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'busqueda'
             }"
             class="relative w-full lg:flex-1 lg:min-w-[240px]">
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
                   focus:outline-none focus:border-[#00A859] transition-colors"
          />
        </div>

        <!-- Filtros estado -->
        <div ref="refFiltros"
             :class="{
               'tour-active': pasoRefActivo === 'refFiltros',
               'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'filtros'
             }"
             class="flex flex-wrap gap-2 lg:justify-end lg:min-w-0">
          <button v-for="op in filtroOpciones" :key="op"
                  @click="filtroEstado = op"
                  :class="[
                    'inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest border transition-all',
                    filtroEstado === op
                      ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-md'
                      : 'bg-white text-gray-500 border-gray-200 hover:border-[#00A859] hover:text-[#00A859]'
                  ]">
            {{ filtroLabels[op] }}
            <span :class="[
              'inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] rounded-full text-[9px] font-black transition-all',
              filtroEstado === op
                ? 'bg-white/20 text-white'
                : 'bg-gray-100 text-gray-500 group-hover:bg-[#00A859]/10'
            ]">{{ conteosPorEstado[op] }}</span>
          </button>
          <button
            @click="router.push({ name: 'proyectos-terminados' })"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest border transition-all
                   bg-white text-sky-600 border-sky-200 hover:border-sky-400 hover:bg-sky-50">
            Ver completados
            <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] rounded-full
                         text-[9px] font-black bg-sky-100 text-sky-600">{{ totalCompletados }}</span>
          </button>
        </div>

      </div>

      <!-- Grid wrapper (ref para el tour) -->
      <div ref="refGrid"
           :class="{
             'tour-active': pasoRefActivo === 'refGrid',
             'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'grid'
           }">

        <!-- Cargando -->
        <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
          <svg class="animate-spin w-12 h-12 text-[#00A859] mb-4" viewBox="0 0 24 24">
            <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
          </svg>
          <p class="text-[#00A859] font-black tracking-widest uppercase text-sm animate-pulse">Cargando...</p>
        </div>

        <!-- Vacío -->
        <div v-else-if="proyectosFiltrados.length === 0"
             class="text-center py-24 bg-white rounded-[2rem] border border-dashed border-gray-200 shadow-sm">
          <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-5
                      border border-gray-100 shadow-inner">
            <svg class="w-10 h-10 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 2L2 7l10 5 10-5-10-5zm0 10l-10-5m10 5l10-5m-10 5v10"/>
            </svg>
          </div>
          <h3 class="text-[#1F2937] font-black text-xl mb-2">
            {{ busqueda || filtroEstado !== 'todos' ? 'Sin resultados' : 'Todavía no hay propuestas ni proyectos' }}
          </h3>
          <p class="text-gray-400 text-sm mb-6">
            {{ busqueda || filtroEstado !== 'todos' ? 'Prueba con otros filtros' : 'Crea tu primera propuesta StartUp Day' }}
          </p>
          <button v-if="!busqueda && filtroEstado === 'todos' && !authStore.isEmpresa"
                  @click="router.push({ name: 'startup-day-crear' })"
                  class="inline-flex items-center gap-2 px-6 py-3 bg-[#00A859] text-white rounded-full
                         text-xs font-black uppercase tracking-widest shadow-sm hover:bg-[#00A859]/90
                         transition-all active:scale-95">
            Crear el primero
          </button>
        </div>

        <!-- Grid de tarjetas -->
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <ProyectoCard
            v-for="p in proyectosFiltrados" :key="p.uuid"
            :proyecto="p"
            @eliminar="abrirModalEliminar"
          />
        </div>

      </div><!-- /refGrid -->

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
  box-shadow: 0 0 0 3px #00A859, 0 0 0 8px rgba(0,168,89,0.15);
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
