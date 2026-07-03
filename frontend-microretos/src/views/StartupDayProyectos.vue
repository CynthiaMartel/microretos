<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter, useRoute, onBeforeRouteUpdate } from 'vue-router';
import api from '../api.js';
import BienvenidaStartupDayModal from '../components/BienvenidaStartupDayModal.vue';
import EliminarProyectoModal from '../components/EliminarProyectoModal.vue';
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
  { ref: 'refBusqueda', seccion: 'busqueda',  texto: 'Usa el buscador para encontrar proyectos por título, empresa o centro educativo. La búsqueda filtra en tiempo real a medida que escribes.' },
  { ref: 'refFiltros',  seccion: 'filtros',   texto: 'Filtra los proyectos por estado: Validados (aprobados por empresa), Pendiente validar (enviados, esperando respuesta), En edición (borradores), Archivados o Todos. Puedes combinar filtro y buscador a la vez.' },
  { ref: 'refGrid',     seccion: 'grid',      texto: 'Aquí aparecen los proyectos registrados. Cada tarjeta muestra título, empresa, ciclo y estado. Pulsa en una tarjeta para ver el detalle completo.' },
  { ref: 'refBtnNuevo', seccion: 'btn-nuevo', texto: 'Pulsa aquí para crear un nuevo proyecto StartUp Day. Necesitarás haber registrado previamente una sesión en el Dashboard Docente para poder vincularlo al reto correspondiente.' },
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
  if (route.query.filtro) filtroEstado.value = String(route.query.filtro);
  try {
    const res = await api.get('/startup/proyectos');
    proyectos.value = res.data;
  } finally {
    cargando.value = false;
  }
  await nextTick();
  guiaBienvenida.value = true;
});

onUnmounted(() => {
  tourActivo.value = false;
  window.removeEventListener('scroll', onScrollGuia);
});

onBeforeRouteUpdate(async () => {
  modoGuia.value = false;
  pasoGuia.value = 1;
  await nextTick();
  guiaBienvenida.value = true;
});

function getEtiqueta(p) {
  if (p.estado === 'en_edicion') return 'En edición';
  if (p.estado === 'archivado')  return 'Archivado';
  if (p.estado === 'validado') {
    if (p.empresa_validado && p.docente_validado) return 'Validado · Completo';
    if (p.empresa_validado)  return 'Validado · Empresa';
    if (p.docente_validado)  return 'Validado · Docente';
    return 'Validado';
  }
  if (p.empresa_no_valida_aun)    return 'No validar aún';
  if (p.enviado_a_empresa_mail)   return 'Esperando respuesta';
  return 'Pendiente enviar';
}
function getColor(p) {
  if (p.estado === 'en_edicion') return 'bg-amber-50 border-amber-200 text-amber-700';
  if (p.estado === 'archivado')  return 'bg-gray-100 border-gray-200 text-gray-400';
  if (p.estado === 'validado') {
    if (p.docente_validado && !p.empresa_validado) return 'bg-emerald-50 border-emerald-300 text-emerald-700';
    return 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]';
  }
  if (p.empresa_no_valida_aun)   return 'bg-red-50 border-red-300 text-red-700';
  if (p.enviado_a_empresa_mail)  return 'bg-blue-50 border-blue-200 text-blue-700';
  return 'bg-violet-50 border-violet-300 text-violet-700';
}

const filtroOpciones = ['validado', 'propuesta', 'en_edicion', 'archivado', 'todos'];
const filtroLabels   = { todos: 'Todos', en_edicion: 'En edición', propuesta: 'Pendiente validar', validado: 'Validados', archivado: 'Archivado' };

const conteosPorEstado = computed(() => ({
  validado:   proyectos.value.filter(p => p.estado === 'validado').length,
  propuesta:  proyectos.value.filter(p => p.estado === 'propuesta').length,
  en_edicion: proyectos.value.filter(p => p.estado === 'en_edicion').length,
  archivado:  proyectos.value.filter(p => p.estado === 'archivado').length,
  todos:      proyectos.value.length,
}));

const proyectosFiltrados = computed(() => {
  let lista = proyectos.value;
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
  mostrarSnack(`"${titulo}" movido a la papelera.`, { label: 'Ir a la papelera', fn: () => router.push({ name: 'papelera' }) });
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
          <div class="inline-flex items-center gap-2 mb-3 px-3 py-1 rounded-full
                      bg-amber-400/10 border border-amber-400/20">
            <span class="w-2 h-2 rounded-full bg-amber-400" />
            <span class="text-[10px] font-black uppercase tracking-widest text-amber-500">Startup Day · Fase 2</span>
          </div>
          <h1 class="text-3xl md:text-4xl font-black tracking-tight text-[#121212]">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">Proyectos</span>
          </h1>
          <p class="text-gray-500 text-sm mt-1">
            Aquí se trabajan los retos para convertirlos en proyectos de empresa.
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
            <!-- Botón Papelera -->
            <button
              v-if="!authStore.isEmpresa"
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
          Nuevo proyecto
        </button>
      </header>

      <!-- Filtros -->
      <div class="flex flex-col sm:flex-row gap-3 mb-6">

        <!-- Búsqueda -->
        <div ref="refBusqueda"
             :class="{
               'tour-active': pasoRefActivo === 'refBusqueda',
               'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'busqueda'
             }"
             class="relative flex-1">
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
             class="flex flex-wrap gap-2">
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
            {{ busqueda || filtroEstado !== 'todos' ? 'Sin resultados' : 'Todavía no hay proyectos' }}
          </h3>
          <p class="text-gray-400 text-sm mb-6">
            {{ busqueda || filtroEstado !== 'todos' ? 'Prueba con otros filtros' : 'Crea tu primer proyecto StartUp Day' }}
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
          <div
            v-for="p in proyectosFiltrados" :key="p.uuid"
            class="group bg-white border border-gray-100 rounded-[1.5rem] shadow-sm
                   hover:shadow-lg hover:-translate-y-0.5 hover:border-gray-200
                   transition-all duration-300 cursor-pointer flex flex-col"
            @click="router.push({ name: 'startup-day-detalle', params: { uuid: p.uuid } })"
          >
            <div class="p-5 flex-1 flex flex-col gap-3">
              <!-- Estado + paso -->
              <div class="flex items-center justify-between">
                <span :class="['text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full border', getColor(p)]">
                  {{ getEtiqueta(p) }}
                </span>
                <span class="text-[10px] text-gray-400 font-bold">Paso {{ p.paso_actual }}/8</span>
              </div>

              <!-- Título -->
              <h3 class="font-black text-[#1F2937] text-sm leading-snug line-clamp-2
                         group-hover:text-[#00A859] transition-colors">
                {{ p.titulo }}
              </h3>

              <!-- Meta -->
              <div class="space-y-1.5 text-xs text-gray-500 mt-auto">
                <div v-if="p.empresa_nombre" class="flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                  </svg>
                  {{ p.empresa_nombre }}
                </div>
                <div v-if="p.centro_nombre" class="flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                  </svg>
                  {{ p.centro_nombre }}
                </div>
                <div v-if="p.empresa_validado"
                     class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl
                            bg-[#00A859]/10 border border-[#00A859]/30 text-[#00A859]">
                  <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  </svg>
                  <span class="text-[9px] font-black uppercase tracking-wider leading-tight">
                    Validado por empresa
                  </span>
                </div>
              </div>

              <!-- ── Etiquetas de sub-estado en miniatura ───────────────── -->
              <div v-if="p.estado === 'propuesta' && !p.empresa_validado"
                   class="flex flex-col gap-1.5 mt-1">

                <!-- Propuesta NO enviada por mail aún -->
                <div v-if="!p.enviado_a_empresa_mail && !p.empresa_no_valida_aun"
                     class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl
                            bg-violet-50 border border-violet-300 text-violet-700">
                  <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span class="text-[9px] font-black uppercase tracking-wider leading-tight">
                    Pendiente de enviar a empresa
                  </span>
                </div>

                <!-- Enviada por mail, esperando respuesta -->
                <div v-if="p.enviado_a_empresa_mail && !p.empresa_no_valida_aun"
                     class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl
                            bg-blue-50 border border-blue-200 text-blue-600">
                  <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                  <span class="text-[9px] font-black uppercase tracking-wider leading-tight">
                    Enviado a empresa · Sin respuesta
                  </span>
                </div>

                <!-- Empresa contestó "No validar aún" — requiere atención -->
                <div v-if="p.empresa_no_valida_aun"
                     class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl
                            bg-red-50 border border-red-300 text-red-700">
                  <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span class="text-[9px] font-black uppercase tracking-wider leading-tight">
                    Empresa: "No validar aún" · Revisar
                  </span>
                </div>

              </div>
              <!-- ── Fin etiquetas de sub-estado ────────────────────────── -->
            </div>

            <!-- Acciones -->
            <div v-if="!authStore.isEmpresa" class="px-5 pb-4 flex gap-2 border-t border-gray-50 pt-3" @click.stop>
              <button
                @click="router.push({ name: 'startup-day-editar', params: { uuid: p.uuid } })"
                class="flex-1 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs font-black
                       uppercase tracking-widest text-gray-500
                       hover:border-[#00A859] hover:text-[#00A859] hover:bg-[#00A859]/5
                       transition-all"
              >
                Editar
              </button>
              <button
                @click="abrirModalEliminar(p)"
                class="py-2 px-3 rounded-xl bg-gray-50 border border-gray-200 text-red-400
                       hover:bg-red-50 hover:border-red-200 transition-all"
                title="Mover a papelera"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>
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
