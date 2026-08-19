<!-- Ruta: /retos/:id (name: detalle-microreto). Antes vivía en /biblioteca/:id — ver router/index.js. -->
<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import QRCode from 'qrcode';
import api from '../api.js';
import { usePdfExport } from '../composables/usePdfExport.js';
import { useAuthStore } from '../stores/auth.js';
import { useUiHighlightStore } from '../stores/uiHighlight.js';
import LoginModal from '../components/LoginModal.vue';

const route   = useRoute();
const router  = useRouter();
const uiHighlight = useUiHighlightStore();
const reto    = ref(null);
const cargando = ref(true);
const error   = ref(false);
const isLoaded = ref(false);

const authStore = useAuthStore();

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.replace({ path: '/', query: { redirect: route.fullPath } })
    return
  }
  setTimeout(() => { isLoaded.value = true; }, 100);
  try {
    const res = await api.get(`/microretos/${route.params.id}`);
    reto.value = res.data;
  } catch (e) {
    console.error('Error al cargar el microreto:', e);
    error.value = true;
  } finally {
    cargando.value = false;
  }
});

const volver = () => router.push({ name: 'biblioteca' })

const trabajarMicroreto = () => {
  const id = reto.value?.uuid || reto.value?.id
  router.push({ name: 'dashboard-docente', query: { microreto_id: id } })
};


const { descargarPDF } = usePdfExport();

const showLoginModal = ref(false);

function handleDescargarPDF() {
  if (authStore.isAuthenticated) {
    descargarPDF(reto.value);
  } else {
    showLoginModal.value = true;
  }
}

// --- IMAGEN DE FONDO ---
const imagenFondo = computed(() => {
  if (!reto.value || !reto.value.familia) return null;
  const slugFamilia = reto.value.familia
    .toLowerCase()
    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '');
  const baseUrl = import.meta.env.VITE_API_URL.replace(/\/api$/, '');
  return `${baseUrl}/familias/${slugFamilia}.webp`;
});

// Datos crudos de la empresa (lo que se recogió antes de que la IA los resumiera) —
// deliberadamente sin datos de contacto: eso lo filtra MicroretoFichaResource en el backend.
const tieneDatosRecogidos = computed(() => {
  const e = reto.value?.empresa
  if (!e) return false
  return !!(e.dia_a_normal || e.friccion_area || e.friccion_problema
    || e.consecuencias || e.restricciones || e.lo_que_no_quieren)
})

// --- QR TEMPORAL ---
const showQrModal  = ref(false);
const qrCargando   = ref(false);  // consultando token existente (GET)
const qrCreando    = ref(false);  // creando token nuevo (POST)
const qrRevocar    = ref(false);  // revocando (DELETE)
const qrToken      = ref(null);
const qrExpira     = ref(null);
const qrUrl        = ref('');
const qrCanvas     = ref(null);
const urlCopiada   = ref(false);
const qrError      = ref('');

const qrExpiraFormateado = computed(() => {
  if (!qrExpira.value) return '';
  return new Date(qrExpira.value).toLocaleString('es-ES', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
});

function buildQrUrl(token) {
  const base = import.meta.env.VITE_APP_BASE_URL || window.location.origin;
  return `${base}/reto/${token}`;
}

async function pintarQR() {
  await nextTick(); // esperar a que Vue renderice el <canvas>
  await QRCode.toCanvas(qrCanvas.value, qrUrl.value, {
    width: 240,
    margin: 2,
    color: { dark: '#1F2937', light: '#FFFFFF' },
  });
}

// Abre el modal y consulta si ya hay un token activo. NO crea ninguno.
async function abrirQR() {
  if (!authStore.isAuthenticated) { showLoginModal.value = true; return; }
  qrError.value = '';
  showQrModal.value = true;

  // Si ya tenemos el token en memoria, solo repintamos el canvas
  if (qrToken.value) {
    await pintarQR();
    return;
  }

  qrCargando.value = true;
  try {
    const res = await api.get(`/microretos/${route.params.id}/token`);
    if (res.data.token) {
      qrToken.value    = res.data.token;
      qrExpira.value   = res.data.expires_at;
      qrUrl.value      = buildQrUrl(res.data.token);
      qrCargando.value = false;
      await pintarQR();
    } else {
      // No hay token activo → mostrar botón "Crear acceso QR"
      qrCargando.value = false;
    }
  } catch (e) {
    console.error('Error consultando token:', e);
    qrError.value    = 'Error al consultar el acceso. Inténtalo de nuevo.';
    qrCargando.value = false;
  }
}

// Crea un token nuevo. Solo se llama cuando no hay ninguno activo.
async function crearQR() {
  qrCreando.value = true;
  qrError.value   = '';
  try {
    const res = await api.post(`/microretos/${route.params.id}/token`);
    qrToken.value    = res.data.token;
    qrExpira.value   = res.data.expires_at;
    qrUrl.value      = buildQrUrl(res.data.token);
    qrCreando.value  = false;
    await pintarQR();
  } catch (e) {
    console.error('Error creando QR:', e);
    qrError.value   = 'No se pudo crear el acceso. Inténtalo de nuevo.';
    qrCreando.value = false;
  }
}

// Revoca el token: lo elimina en BD y limpia el estado local por completo.
async function revocarQR() {
  qrRevocar.value = true;
  qrError.value   = '';
  try {
    await api.delete(`/microretos/${route.params.id}/token`);
    qrToken.value     = null;
    qrExpira.value    = null;
    qrUrl.value       = '';
    urlCopiada.value  = false;
    showQrModal.value = false;
  } catch (e) {
    console.error('Error revocando token:', e);
    qrError.value = 'No se pudo revocar el acceso. Inténtalo de nuevo.';
  } finally {
    qrRevocar.value = false;
  }
}

async function copiarUrl() {
  try {
    await navigator.clipboard.writeText(qrUrl.value);
    urlCopiada.value = true;
    setTimeout(() => { urlCopiada.value = false; }, 2000);
  } catch (e) {
    console.error('No se pudo copiar:', e);
  }
}
</script>

<template>
  <div class="min-h-screen font-sans text-[#1F2937] pt-12 md:pt-12">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                bg-[#99CC33] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <div class="relative z-10 max-w-5xl mx-auto px-4 py-8 md:px-8 md:py-12">

      <!-- ── Botón volver + descargar (cabecera) ── -->
      <div class="mb-8 flex flex-wrap items-center gap-2 transition-all duration-700 ease-out"
           :class="isLoaded ? 'translate-y-0 opacity-100' : '-translate-y-4 opacity-0'">
        <button @click="volver"
                class="inline-flex items-center gap-2 px-5 py-2.5
                       bg-white border border-gray-200 rounded-full
                       text-xs font-black uppercase tracking-widest text-[#1F2937]
                       shadow-sm hover:border-[#00A859] hover:text-[#00A859]
                       transition-all active:scale-95">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                  d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
          Volver a la Biblioteca
        </button>

        <button v-if="reto" @click="handleDescargarPDF"
                class="inline-flex items-center gap-2 px-5 py-2.5
                       bg-white border border-gray-200 rounded-full
                       text-xs font-black uppercase tracking-widest text-[#1F2937]
                       shadow-sm hover:border-[#00A859] hover:text-[#00A859]
                       transition-all active:scale-95">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Descargar PDF
        </button>

        <!-- Botón QR — oculto para empresa -->
        <button v-if="reto && authStore.isAuthenticated && !authStore.isEmpresa" @click="abrirQR"
                class="inline-flex items-center gap-2 px-5 py-2.5
                       bg-white border border-gray-200 rounded-full
                       text-xs font-black uppercase tracking-widest text-[#1F2937]
                       shadow-sm hover:border-[#00A859] hover:text-[#00A859]
                       transition-all active:scale-95">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5
                     4h2a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1zm12 0h2a1 1 0 011
                     1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1V5a1 1 0 011-1zM5 16h2a1 1 0 011 1v2a1 1 0
                     01-1 1H5a1 1 0 01-1-1v-2a1 1 0 011-1z"/>
          </svg>
          Generar QR
        </button>

        <button v-if="reto && authStore.isAuthenticated && !authStore.isEmpresa" @click="trabajarMicroreto"
                class="inline-flex items-center gap-2 px-5 py-2.5
                       bg-[#00A859] border border-[#00A859] rounded-full
                       text-xs font-black uppercase tracking-widest text-white
                       shadow-sm hover:bg-[#00A859]/90 hover:shadow-[0_0_0_3px_rgba(0,168,89,0.2)]
                       transition-all active:scale-95">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                     M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2
                     m-6 9l2 2 4-4"/>
          </svg>
          Trabajar este reto
        </button>

      </div>

      <!-- ── CARGANDO ── -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
        <svg class="animate-spin w-12 h-12 text-[#00A859] mb-4" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="text-[#00A859] font-black tracking-widest uppercase text-sm animate-pulse">
          Cargando ficha técnica...
        </p>
      </div>

      <!-- ── ERROR ── -->
      <div v-else-if="error"
           class="text-center py-32 bg-white rounded-[2rem]
                  border border-dashed border-red-200 shadow-sm">
        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-5">
          <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3
                     L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <h3 class="text-[#1F2937] font-black text-2xl mb-2">No se pudo cargar el reto</h3>
        <p class="text-gray-500 text-sm mb-6">Comprueba tu conexión o vuelve a intentarlo.</p>
        <button @click="volver"
                class="px-6 py-2.5 bg-white border border-gray-200
                       hover:border-[#00A859] hover:text-[#00A859]
                       text-[#1F2937] rounded-full text-xs font-black
                       uppercase tracking-widest transition-all shadow-sm">
          Volver a la Biblioteca
        </button>
      </div>

      <!-- ── FICHA TÉCNICA ── -->
      <template v-else-if="reto">

        <!-- BLOQUE PRINCIPAL -->
        <div class="bg-white rounded-[2rem] shadow-[0_20px_50px_rgb(0,0,0,0.06)]
                    overflow-hidden border border-gray-100
                    transition-all duration-700 delay-100 ease-out"
             :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'">

          <!-- ── Cabecera ── -->
          <div class="relative bg-gray-50 border-b border-gray-100 overflow-hidden">
            
            <div class="absolute inset-0 z-0 pointer-events-none">
              <div class="absolute inset-0 bg-gradient-to-r from-gray-50 via-gray-50/95 to-transparent z-10"></div>
              <div class="absolute inset-0 bg-gradient-to-t from-gray-50 via-transparent to-transparent z-10"></div>
              
              <img
                v-if="imagenFondo"
                :src="imagenFondo"
                alt=""
                class="w-full h-full object-cover object-right opacity-30 md:opacity-40 mix-blend-multiply transition-opacity duration-1000"
                :class="isLoaded ? 'opacity-30 md:opacity-40' : 'opacity-0'"
              />
            </div>

            <div class="relative z-10 px-6 py-8 md:px-14 md:pt-12 md:pb-10 max-w-4xl">

              <p class="text-[#00A859] font-bold text-[10px] tracking-[0.2em] uppercase mb-4
                        flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                           a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                DuaLab · Ficha de Reto
              </p>

              <h1 class="text-2xl sm:text-3xl md:text-5xl font-black text-[#1F2937]
                         tracking-tight leading-tight mb-3">
                {{ reto.titulo }}
              </h1>
              <h2 class="text-base md:text-xl text-gray-500 font-medium leading-relaxed mb-8">
                {{ reto.subtitulo }}
              </h2>

              <div class="flex flex-wrap gap-2 md:gap-3">
                <span class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-[#1F2937] text-white rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider shadow-sm">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5
                             m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0
                             011-1h2a1 1 0 011 1v5m-4 0h4"/>
                  </svg>
                  {{ reto.empresa_nombre }}
                </span>

                <span v-if="reto.empresa_es_simulada != null"
                      :class="reto.empresa_es_simulada
                        ? 'bg-amber-50 border-amber-200 text-amber-700'
                        : 'bg-emerald-50 border-emerald-200 text-emerald-700'"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             border rounded-lg text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  <svg v-if="reto.empresa_es_simulada" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3
                             m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374
                             3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                  </svg>
                  <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  {{ reto.empresa_es_simulada ? 'Empresa ficticia' : 'Empresa real' }}
                </span>

                <span v-if="reto.es_simulado"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-rose-50 border border-rose-200 text-rose-700 rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                  Info simulada
                </span>

                <span v-if="reto.familia"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-sky-50 border border-sky-200 text-sky-700 rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2-2H5a2 2 0 01-2-2v-6
                             a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5
                             a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                  </svg>
                  {{ reto.familia }}
                </span>

                <span v-if="reto.nivel_grupo"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-violet-50 border border-violet-200 text-violet-700 rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0
                             002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2
                             a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2
                             2h-2a2 2 0 01-2-2z"/>
                  </svg>
                  Nivel: {{ reto.nivel_grupo }}
                </span>

                <span v-if="reto.ciclo"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-teal-50 border border-teal-200 text-teal-700 rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 14l9-5-9-5-9 5 9 5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0
                             0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0
                             01.665-6.479L12 14z"/>
                  </svg>
                  {{ reto.ciclo }}
                </span>

                <span v-if="reto.curso"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-blue-50 border border-blue-200 text-blue-700 rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13
                             C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477
                             14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247
                             18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                  </svg>
                  {{ reto.curso === 'ambos_cursos' ? 'Ambos Cursos: 1º y 2º' : reto.curso + 'º Curso' }}
                </span>

                <span v-if="reto.multimodulo"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                  </svg>
                  Multi-módulo
                </span>

                <span v-if="reto.empresa?.sector"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-gray-50 border border-gray-200 text-gray-600 rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  {{ reto.empresa.sector }}
                </span>
                <span v-if="reto.empresa?.tamano"
                      class="flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2
                             bg-gray-50 border border-gray-200 text-gray-600 rounded-lg
                             text-[10px] md:text-xs font-bold uppercase tracking-wider">
                  {{ reto.empresa.tamano }}
                </span>
              </div>
            </div>
          </div>

          <!-- ── Cuerpo ── -->
          <div class="px-6 py-8 md:px-14 md:py-12 space-y-10 md:space-y-14">

            <!-- Datos recogidos de la empresa: diagnóstico crudo, tal cual lo aportó el
                 docente/empresa — es la materia prima que la IA resume debajo. -->
            <div v-if="tieneDatosRecogidos"
                 class="bg-gradient-to-br from-orange-50 to-white border-2 border-orange-200
                        rounded-2xl p-5 md:p-7 shadow-sm">
              <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-xl bg-orange-500 flex items-center justify-center shrink-0 shadow-sm">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                             a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="text-orange-700 font-black uppercase text-xs md:text-sm tracking-[0.15em]">
                    Datos recogidos de la empresa
                  </h3>
                  <p class="text-[11px] text-orange-400 font-medium">Diagnóstico original, sin resumir por IA</p>
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div v-if="reto.empresa.dia_a_normal" class="bg-white/70 rounded-xl p-4 border border-orange-100">
                  <p class="text-[10px] font-black uppercase tracking-wider text-orange-500 mb-1">Su día a día</p>
                  <p class="text-sm text-gray-700 leading-relaxed">{{ reto.empresa.dia_a_normal }}</p>
                </div>
                <div v-if="reto.empresa.friccion_area || reto.empresa.friccion_problema" class="bg-white/70 rounded-xl p-4 border border-orange-100">
                  <p class="text-[10px] font-black uppercase tracking-wider text-orange-500 mb-1">Fricciones de la empresa</p>
                  <p v-if="reto.empresa.friccion_area" class="text-sm text-gray-700 leading-relaxed">{{ reto.empresa.friccion_area }}</p>
                  <p v-if="reto.empresa.friccion_problema" class="text-sm text-gray-700 leading-relaxed mt-1">{{ reto.empresa.friccion_problema }}</p>
                </div>
                <div v-if="reto.empresa.consecuencias" class="bg-white/70 rounded-xl p-4 border border-orange-100">
                  <p class="text-[10px] font-black uppercase tracking-wider text-orange-500 mb-1">Consecuencias</p>
                  <p class="text-sm text-gray-700 leading-relaxed">{{ reto.empresa.consecuencias }}</p>
                </div>
                <div v-if="reto.empresa.restricciones" class="bg-white/70 rounded-xl p-4 border border-orange-100">
                  <p class="text-[10px] font-black uppercase tracking-wider text-orange-500 mb-1">Restricciones</p>
                  <p class="text-sm text-gray-700 leading-relaxed">{{ reto.empresa.restricciones }}</p>
                </div>
                <div v-if="reto.empresa.lo_que_no_quieren" class="bg-white/70 rounded-xl p-4 border border-orange-100 md:col-span-2">
                  <p class="text-[10px] font-black uppercase tracking-wider text-orange-500 mb-1">Lo que no quieren</p>
                  <p class="text-sm text-gray-700 leading-relaxed">{{ reto.empresa.lo_que_no_quieren }}</p>
                </div>
              </div>
            </div>

            <!-- Resumen de diagnóstico: la lectura de la IA a partir de los datos de arriba. -->
            <div class="space-y-10 md:space-y-14">
              <h3 class="flex items-center gap-2 text-[#1F2937] font-bold uppercase text-xs
                         tracking-widest border-b-2 border-gray-200 pb-2">
                <svg class="w-5 h-5 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                           a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Resumen de diagnóstico
              </h3>

            <!-- Quién es / Día a día -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-8 lg:gap-10">
              <div v-if="reto.quien_es">
                <h3 class="section-title text-[#00A859]">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0
                             00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0
                             100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001
                             3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                  </svg>
                  ¿Quién es {{ reto.empresa_nombre }}?
                </h3>
                <p class="text-gray-600 text-sm leading-relaxed">{{ reto.quien_es }}</p>
              </div>
              <div v-if="reto.dia_a_dia">
                <h3 class="section-title text-[#00A859]">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Su día a día
                </h3>
                <p class="text-gray-600 text-sm leading-relaxed">{{ reto.dia_a_dia }}</p>
              </div>
            </div>

            <!-- Dificultades -->
            <div v-if="reto.dificultades?.length">
              <h3 class="section-title text-yellow-600">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3
                           L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Dificultades
              </h3>
              <ul class="space-y-2 pl-1">
                <li v-for="(item, i) in reto.dificultades" :key="i"
                    class="flex items-start gap-3 text-sm text-gray-700">
                  <span class="text-yellow-500 font-black mt-0.5 shrink-0">•</span>
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>

            <!-- Pregunta del reto -->
            <div class="bg-gradient-to-r from-gray-50 to-white border-l-4 border-[#00A859]
                        p-6 md:p-8 rounded-r-2xl shadow-sm border-y border-r border-gray-100">
              <h3 class="text-[#00A859] font-black uppercase text-[10px] tracking-[0.2em] mb-3">
                Pregunta del Reto
              </h3>
              <p class="text-lg md:text-2xl font-bold text-[#1F2937] leading-snug">
                {{ reto.pregunta_reto }}
              </p>
            </div>

            <!-- Qué necesitan / Limitaciones -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-8 lg:gap-10">
              <div v-if="reto.que_necesitan?.length">
                <h3 class="section-title text-[#00A859]">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0
                             00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  Qué necesitan
                </h3>
                <ul class="space-y-2 pl-1">
                  <li v-for="(item, i) in reto.que_necesitan" :key="i"
                      class="flex items-start gap-3 text-sm text-gray-700">
                    <span class="text-[#00A859] font-black mt-0.5 shrink-0">•</span>
                    <span>{{ item }}</span>
                  </li>
                </ul>
              </div>
              <div v-if="reto.limitaciones?.length">
                <h3 class="section-title text-red-500">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0
                             015.636 5.636m12.728 12.728L5.636 5.636"/>
                  </svg>
                  Limitaciones
                </h3>
                <ul class="space-y-2 pl-1">
                  <li v-for="(item, i) in reto.limitaciones" :key="i"
                      class="flex items-start gap-3 text-sm text-gray-700">
                    <span class="text-red-500 font-black mt-0.5 shrink-0">•</span>
                    <span>{{ item }}</span>
                  </li>
                </ul>
              </div>
            </div>
            </div>

            <!-- Prototipos / ODS -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-8 lg:gap-10">
              <div v-if="reto.prototipos?.length">
                <h3 class="section-title text-[#00A859]">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                  </svg>
                  Ejemplos de Prototipos
                </h3>
                <ul class="space-y-2 pl-1">
                  <li v-for="(item, i) in reto.prototipos" :key="i"
                      class="flex items-start gap-3 text-sm text-gray-700">
                    <span class="text-[#00A859] font-black mt-0.5 shrink-0">•</span>
                    <span>{{ item }}</span>
                  </li>
                </ul>
              </div>
              <div v-if="reto.ods_sugeridos?.length">
                <h3 class="section-title text-blue-600">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 104 0 2 2 0 012-2h1.064
                             M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  ODS Sugeridos
                </h3>
                <ul class="space-y-2 pl-1">
                  <li v-for="ods in reto.ods_sugeridos" :key="ods"
                      class="text-sm font-semibold text-[#1F2937]">{{ ods }}</li>
                </ul>
              </div>
            </div>

            <!-- Soft skills -->
            <div v-if="reto.soft_skills?.length">
              <h3 class="flex items-center gap-2 text-purple-600 font-black text-[11px]
                         uppercase tracking-[0.15em] mb-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283
                           -.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283
                           .356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Soft skills
              </h3>
              <div class="flex flex-wrap gap-2">
                <span v-for="(skill, i) in reto.soft_skills" :key="i"
                      class="px-3 py-1 bg-purple-50 border border-purple-200 text-purple-700
                             rounded-full text-xs font-semibold">
                  {{ skill }}
                </span>
              </div>
            </div>

            <!-- RA / CE -->
            <div v-if="reto.evaluacion_oficial?.length" class="pt-2">
              <h3 class="flex items-center gap-2 text-[#1F2937] font-bold uppercase text-xs
                         tracking-widest border-b-2 border-gray-200 pb-2 mb-6">
                <svg class="w-5 h-5 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 14l9-5-9-5-9 5 9 5z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0
                           0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0
                           01.665-6.479L12 14z"/>
                </svg>
                Módulos + RA/CE seleccionados
              </h3>
              <div class="flex items-start gap-2 text-amber-700 bg-amber-50 border border-amber-200
                          rounded-2xl px-4 py-3 mb-6">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs font-semibold leading-relaxed">
                  Módulos, RA y CE que la IA considera que más encajan con este reto. Podrás cambiarlos al crear
                  el proyecto en
                  <span class="underline underline-offset-2 decoration-amber-400 cursor-pointer hover:text-amber-900"
                        @mouseenter="uiHighlight.setHighlight('generar-proyecto')"
                        @mouseleave="uiHighlight.clearHighlight('generar-proyecto')"
                        @click="router.push('/proyectos/crear')">2. Taller de Ideas → Generar Proyecto</span>
                  (menú izquierdo).
                  Cubre un mínimo de 3 módulos distintos (salvo que se forzaran módulos concretos)<template v-if="reto.curso === 'ambos_cursos'">, incluyendo al menos uno de 1º y uno de 2º</template>.
                </p>
              </div>
              <div class="space-y-4 md:space-y-6">
                <div v-for="evalObj in reto.evaluacion_oficial" :key="evalObj.modulo"
                     class="bg-white border border-gray-200 p-5 md:p-6 rounded-2xl shadow-sm">
                  <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
                    <div>
                      <p class="text-[10px] uppercase font-bold text-gray-400 mb-1">Módulo</p>
                      <p class="font-black text-[#1F2937] text-base md:text-lg">{{ evalObj.modulo }}</p>
                    </div>
                    <span v-if="evalObj.curso" class="shrink-0 px-3 py-1 bg-blue-50 border border-blue-200
                                text-blue-700 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                      {{ evalObj.curso }}º Curso
                    </span>
                  </div>
                  <div class="mb-4">
                    <p class="text-[10px] uppercase font-bold text-[#00A859] mb-1 flex items-center gap-1">
                      <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/>
                      </svg>
                      Resultado de Aprendizaje
                    </p>
                    <p class="text-sm font-semibold text-gray-700 bg-gray-50 p-4 rounded-xl border border-gray-100">
                      {{ evalObj.ra }}
                    </p>
                  </div>
                  <div class="mb-4">
                    <p class="text-[10px] uppercase font-bold text-gray-500 mb-2">Criterios de Evaluación</p>
                    <ul class="space-y-1.5">
                      <li v-for="(ce, i) in evalObj.ce" :key="i"
                          class="text-sm text-gray-600 flex items-start gap-2">
                        <span class="text-[#00A859] font-bold mt-0.5 shrink-0">✓</span>
                        <span>{{ ce }}</span>
                      </li>
                    </ul>
                  </div>
                  <div v-if="evalObj.aplicacion" class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-sm text-gray-500 italic">
                      <span class="font-bold not-italic text-[#1F2937]">Aplicación: </span>
                      {{ evalObj.aplicacion }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Variantes -->
            <div v-if="reto.variantes?.length" class="pt-2">
              <h3 class="section-title text-[#00A859]">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
                Variantes
              </h3>
              <ul class="space-y-3">
                <li v-for="(varItem, i) in reto.variantes" :key="i"
                    class="text-sm text-gray-700 bg-gray-50 border border-gray-200 p-4 rounded-xl shadow-sm">
                  <template v-if="varItem.includes(':')">
                    <strong class="text-[#1F2937] block mb-1">{{ varItem.split(':')[0] }}</strong>
                    <span>{{ varItem.substring(varItem.indexOf(':') + 1).trim() }}</span>
                  </template>
                  <template v-else>{{ varItem }}</template>
                </li>
              </ul>
            </div>

          </div>
        </div>

        <!-- GUÍA DOCENTE -->
        <div v-if="reto.tips_profesorado?.length"
             class="relative mt-10 bg-gray-50 rounded-[2rem] shadow-sm
                    border border-gray-200 px-6 py-8 md:px-14 md:py-12
                    transition-all duration-700 delay-200 ease-out"
             :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'">

          <div class="absolute top-0 right-0 bg-white border-b border-l border-gray-200
                      text-gray-400 px-5 py-2 font-black text-[9px] tracking-widest uppercase
                      rounded-bl-2xl shadow-sm">
            Uso Exclusivo Docente
          </div>

          <h2 class="text-xl md:text-2xl font-black text-[#1F2937] mb-1 mt-2 flex items-center gap-3">
            <svg class="w-6 h-6 text-yellow-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0
                       110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
            </svg>
            Guía de Implementación
          </h2>
          <p class="text-gray-500 text-sm mb-8">
            Recomendaciones pedagógicas para dinamizar el reto.
          </p>

          <div class="grid grid-cols-1 gap-4 md:gap-6">
            <div v-for="(tip, i) in reto.tips_profesorado" :key="i"
                 class="bg-white p-5 md:p-6 rounded-2xl border border-gray-100 shadow-sm">
              <div class="text-sm text-gray-700 leading-relaxed">
                <template v-if="tip.includes(':')">
                  <strong class="text-[#00A859] flex items-center gap-1.5 mb-2
                                 uppercase tracking-wider text-[10px]">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="i===0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857
                               M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002
                               5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0
                               014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ tip.split(':')[0] }}
                  </strong>
                  <span class="block text-gray-600">
                    {{ tip.substring(tip.indexOf(':') + 1).trim() }}
                  </span>
                </template>
                <template v-else>
                  <span class="text-gray-600">{{ tip }}</span>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Botones volver + descargar pie -->
        <div class="flex flex-wrap justify-center gap-4 mt-10 pb-8">
          <button @click="volver"
                  class="inline-flex items-center gap-2 px-8 py-4 bg-white border-2 border-gray-200
                         rounded-full text-xs font-black uppercase tracking-widest text-[#1F2937]
                         shadow-sm hover:border-[#00A859] hover:text-[#00A859] transition-all
                         hover:-translate-y-0.5 active:scale-95">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a la Biblioteca
          </button>

          <button @click="descargarPDF(reto)"
                  class="inline-flex items-center gap-2 px-8 py-4 bg-[#00A859] border-2 border-[#00A859]
                         rounded-full text-xs font-black uppercase tracking-widest text-white
                         shadow-sm hover:bg-[#008f4a] hover:border-[#008f4a] transition-all
                         hover:-translate-y-0.5 active:scale-95">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Descargar PDF
          </button>
        </div>

      </template>
    </div>
  </div>

  <LoginModal
    v-model="showLoginModal"
    @login-success="descargarPDF(reto)"
  />

  <!-- ── MODAL QR ── -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showQrModal"
           class="fixed inset-0 z-50 flex items-center justify-center p-4"
           @click.self="showQrModal = false">

        <!-- Fondo oscuro -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showQrModal = false" />

        <!-- Panel -->
        <div class="relative z-10 w-full max-w-sm bg-white rounded-[2rem] shadow-2xl
                    overflow-hidden border border-gray-100">

          <!-- Cabecera -->
          <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#00A859] mb-0.5">
                Acceso Alumnado
              </p>
              <h3 class="text-lg font-black text-[#1F2937]">QR del Reto</h3>
            </div>
            <button @click="showQrModal = false"
                    class="w-8 h-8 flex items-center justify-center rounded-full
                           bg-gray-100 hover:bg-gray-200 text-gray-500 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                      d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Cuerpo -->
          <div class="px-6 py-6 flex flex-col items-center gap-5">

            <!-- Estado 1: Consultando si existe token -->
            <div v-if="qrCargando" class="py-10">
              <svg class="animate-spin w-10 h-10 text-[#00A859]" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
            </div>

            <!-- Estado 2: Sin token activo → invita a crear -->
            <template v-else-if="!qrToken">
              <div class="flex flex-col items-center gap-3 py-6 text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-50 border border-gray-200
                            flex items-center justify-center mb-1">
                  <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4
                             m12 0h.01M5 4h2a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z
                             m12 0h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1V5a1 1 0 011-1z
                             M5 16h2a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2a1 1 0 011-1z"/>
                  </svg>
                </div>
                <p class="text-sm font-semibold text-[#1F2937]">No hay ningún acceso activo</p>
                <p class="text-[11px] text-gray-400 leading-relaxed max-w-[220px]">
                  Crea un código QR temporal para que el alumnado acceda a este reto.
                </p>

                <!-- Error -->
                <p v-if="qrError" class="text-[11px] text-red-500 font-semibold">{{ qrError }}</p>

                <button @click="crearQR"
                        :disabled="qrCreando"
                        class="mt-2 flex items-center gap-2 px-6 py-3
                               bg-[#00A859] text-white rounded-full
                               text-xs font-black uppercase tracking-widest
                               hover:bg-[#008f4a] transition-all active:scale-95 disabled:opacity-50">
                  <svg v-if="!qrCreando" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  <svg v-else class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                  </svg>
                  {{ qrCreando ? 'Creando...' : 'Crear acceso QR' }}
                </button>
              </div>
            </template>

            <!-- Estado 3: Token activo → muestra QR -->
            <template v-else>
              <!-- Canvas del QR -->
              <div class="p-3 bg-white border-2 border-gray-100 rounded-2xl shadow-sm">
                <canvas ref="qrCanvas" class="block rounded-xl" />
              </div>

              <!-- Expiración -->
              <div class="flex items-center gap-2 text-amber-700 bg-amber-50
                          border border-amber-200 rounded-full px-4 py-1.5 text-xs font-semibold">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Caduca el {{ qrExpiraFormateado }}
              </div>

              <!-- URL con botón copiar -->
              <div class="w-full bg-gray-50 rounded-xl border border-gray-200 flex items-center
                          gap-2 px-4 py-2.5 text-xs text-gray-500 font-mono">
                <span class="flex-1 truncate">{{ qrUrl }}</span>
                <button @click="copiarUrl"
                        :class="urlCopiada ? 'text-[#00A859]' : 'text-gray-400 hover:text-[#00A859]'"
                        class="shrink-0 transition-colors"
                        :title="urlCopiada ? 'Copiado' : 'Copiar URL'">
                  <svg v-if="!urlCopiada" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2
                             m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  </svg>
                </button>
              </div>

              <!-- Info -->
              <p class="text-[11px] text-gray-400 text-center leading-relaxed px-2">
                El alumnado escanea este QR desde su dispositivo sin iniciar sesión.
                El acceso caduca automáticamente a las 48h.
              </p>

              <!-- Error al revocar -->
              <p v-if="qrError" class="text-[11px] text-red-500 font-semibold text-center">{{ qrError }}</p>

              <!-- Revocar -->
              <button @click="revocarQR"
                      :disabled="qrRevocar"
                      class="w-full flex items-center justify-center gap-2
                             px-5 py-3 rounded-full border border-red-200
                             text-red-500 text-xs font-black uppercase tracking-widest
                             hover:bg-red-50 transition-all active:scale-95 disabled:opacity-50">
                <svg v-if="!qrRevocar" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0
                           015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                <svg v-else class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                </svg>
                {{ qrRevocar ? 'Revocando...' : 'Revocar acceso' }}
              </button>
            </template>

          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  border-bottom: 1px solid #f3f4f6;
  padding-bottom: 8px;
  margin-bottom: 14px;
}

/* Transición del modal QR */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-active .relative,
.modal-fade-leave-active .relative {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>