<!-- Ruta: /retos/:id (name: detalle-microreto). Antes vivía en /biblioteca/:id — ver router/index.js. -->
<script setup>
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { useVolver } from '../composables/useVolver.js'
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import QRCode from 'qrcode';
import api from '../api.js';
import { usePdfExport } from '../composables/usePdfExport.js';
import { useAuthStore } from '../stores/auth.js';
import { useRoleTheme } from '../composables/useRoleTheme.js';
import LoginModal from '../components/LoginModal.vue';
import FichaReto from '../components/FichaReto.vue';
import { queryRedirect } from '../utils/redirectLogin.js';

const route   = useRoute();
const router  = useRouter();
const { theme } = useRoleTheme();

const paletteExtra = {
  centros:          { hoverBorderText: 'hover:border-centros hover:text-centros', hoverText: 'hover:text-centros' },
  empresas:         { hoverBorderText: 'hover:border-empresas hover:text-empresas', hoverText: 'hover:text-empresas' },
  administraciones: { hoverBorderText: 'hover:border-administraciones hover:text-administraciones', hoverText: 'hover:text-administraciones' },
  primary:          { hoverBorderText: 'hover:border-primary-600 hover:text-primary-700', hoverText: 'hover:text-primary-700' },
}
const reto    = ref(null);
const cargando = ref(true);
const error   = ref(false);
const isLoaded = ref(false);

const authStore = useAuthStore();

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.replace({ path: '/', query: queryRedirect(route.fullPath) })
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

const volverAtras = useVolver()
const volver = () => volverAtras({ name: 'biblioteca' })

// Abre el ajuste del reto con este reto ya elegido (antes iba a «Crear encuentro», que
// ignoraba el reto y exigía una propuesta validada)
const trabajarMicroreto = () => {
  const id = reto.value?.uuid || reto.value?.id
  router.push({ name: 'startup-day-crear', query: { reto: id } })
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
  <div class="min-h-screen font-sans text-[#1F2937] pt-16 md:pt-16">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                opacity-5 blur-[120px] rounded-full pointer-events-none z-0" :class="theme.bg" />

    <div class="relative z-10 mx-auto max-w-[90rem] px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion data-tour="det-cabecera" titulo="Ficha del" destacado="reto" :color="theme.text"
                       :volver="{ name: 'biblioteca' }"
                       subtitulo="Consulta el reto completo, descárgalo o empieza a trabajarlo con tu alumnado."
                       class="mb-6" />

      <!-- ── Acciones del reto (descargar, QR, trabajar) ── -->
      <div data-tour="det-acciones" class="mb-8 flex flex-wrap items-center gap-2 transition-all duration-700 ease-out"
           :class="isLoaded ? 'translate-y-0 opacity-100' : '-translate-y-4 opacity-0'">

        <button v-if="reto" data-tour="det-pdf" @click="handleDescargarPDF"
                class="inline-flex items-center gap-2 px-5 py-2.5
                       bg-white border border-gray-200 rounded-full
                       text-xs font-black uppercase tracking-widest text-[#1F2937]
                       shadow-sm
                       transition-all active:scale-95" :class="paletteExtra[theme.key].hoverBorderText">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Descargar PDF
        </button>

        <!-- Botón QR — oculto para empresa -->
        <button v-if="reto && authStore.isAuthenticated && !authStore.isEmpresa" data-tour="det-qr" @click="abrirQR"
                class="inline-flex items-center gap-2 px-5 py-2.5
                       bg-white border border-gray-200 rounded-full
                       text-xs font-black uppercase tracking-widest text-[#1F2937]
                       shadow-sm
                       transition-all active:scale-95" :class="paletteExtra[theme.key].hoverBorderText">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5
                     4h2a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1zm12 0h2a1 1 0 011
                     1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1V5a1 1 0 011-1zM5 16h2a1 1 0 011 1v2a1 1 0
                     01-1 1H5a1 1 0 01-1-1v-2a1 1 0 011-1z"/>
          </svg>
          Generar QR
        </button>

        <button v-if="reto && authStore.isAuthenticated && !authStore.isEmpresa" data-tour="det-trabajar" @click="trabajarMicroreto"
                class="btn-trabajar-reto inline-flex items-center gap-2 px-5 py-2.5
                       rounded-full
                       text-xs font-black uppercase tracking-widest text-white
                       shadow-sm
                       transition-all active:scale-95" :class="[theme.bg, theme.border, theme.bgHover]">
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
        <svg class="animate-spin w-12 h-12 mb-4" :class="theme.text" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="font-black tracking-widest uppercase text-sm animate-pulse" :class="theme.text">
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
        <h3 class="text-azul-noche font-black text-2xl mb-2">No se pudo cargar el reto</h3>
        <p class="text-gray-500 text-sm mb-6">Comprueba tu conexión o vuelve a intentarlo.</p>
        <button @click="volver"
                class="px-6 py-2.5 bg-white border border-gray-200
                       text-[#1F2937] rounded-full text-xs font-black
                       uppercase tracking-widest transition-all shadow-sm"
                :class="paletteExtra[theme.key].hoverBorderText">
          Volver a la Biblioteca
        </button>
      </div>

      <!-- ── FICHA TÉCNICA ── -->
      <template v-else-if="reto">

        <FichaReto data-tour="det-ficha" :reto="reto" :theme="theme" :visible="isLoaded" @trabajar="trabajarMicroreto" />

        <!-- Descargar (pie) -->
        <div class="flex flex-wrap justify-center gap-4 mt-10 pb-8">

          <button @click="descargarPDF(reto)"
                  class="inline-flex items-center gap-2 px-8 py-4 border-2
                         rounded-full text-xs font-black uppercase tracking-widest text-white
                         shadow-sm transition-all
                         hover:-translate-y-0.5 active:scale-95" :class="[theme.bg, theme.border, theme.bgHover]">
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
              <p class="text-[10px] font-bold uppercase tracking-[0.2em] mb-0.5" :class="theme.text">
                Acceso Alumnado
              </p>
              <h3 class="text-lg font-black text-azul-noche">QR del Reto</h3>
            </div>
            <button @click="showQrModal = false" aria-label="Cerrar"
                    class="h-9 w-9 shrink-0 flex items-center justify-center rounded-full
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
              <svg class="animate-spin w-10 h-10" :class="theme.text" viewBox="0 0 24 24">
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
                               text-white rounded-full
                               text-xs font-black uppercase tracking-widest
                               transition-all active:scale-95 disabled:opacity-50"
                        :class="[theme.bg, theme.bgHover]">
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
                        :class="urlCopiada ? theme.text : ['text-gray-400', paletteExtra[theme.key].hoverText]"
                        class="shrink-0 -my-2.5 -mr-2 inline-flex h-9 w-9 items-center justify-center rounded-full transition-colors hover:bg-gray-100"
                        :title="urlCopiada ? 'Copiado' : 'Copiar URL'"
                        :aria-label="urlCopiada ? 'Copiado' : 'Copiar URL'">
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
.btn-trabajar-reto:hover {
  box-shadow: 0 0 0 3px v-bind('theme.hexSoft');
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