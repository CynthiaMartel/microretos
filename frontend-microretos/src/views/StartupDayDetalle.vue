<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';
import { useMicroproyectoPdfExport } from '../composables/useMicroproyectoPdfExport.js';
import { useAuthStore } from '../stores/auth.js';
import ValidarDocenteModal from '../components/ValidarDocenteModal.vue';

const route     = useRoute();
const router    = useRouter();
const authStore = useAuthStore();
const proyecto = ref(null);
const cargando = ref(true);
const error    = ref(false);
const isLoaded = ref(false);
const urlCopiada = ref(false);

// ── Recursos adjuntos ───────────────────────────────────────────────────────
const recursosAbierto    = ref(false);
const videos             = ref([]);
const documentos         = ref([]);
const modalRecurso       = ref(null);
const modalBorradorAviso  = ref(false);
const modalPropuestaAviso = ref(false);
const urlCopiadaModal     = ref(false);

// ── Validación docente ─────────────────────────────────────────────────────
const modalValidarDocente  = ref(false);
const validandoDocente     = ref(false);

async function validarComoDocente() {
  if (validandoDocente.value) return;
  validandoDocente.value = true;
  try {
    await api.post(`/startup/proyectos/${proyecto.value.uuid}/validar-docente`, { decision: 'validar' });
    proyecto.value.docente_validado = true;
    proyecto.value.estado = 'validado';
    modalValidarDocente.value = false;
    modalPropuestaAviso.value = false;
  } catch { /* no crítico */ } finally {
    validandoDocente.value = false;
  }
}

// ── Confirmación envío enlace empresa ──────────────────────────────────────
const modalConfirmEnvio   = ref(false);
const confirmEnvioTexto   = ref('');
const infoEmpresaAbierta  = ref(false);
const confirmEnvioValido  = computed(() =>
  confirmEnvioTexto.value.trim().toLowerCase() === 'enviar'
);

function abrirConfirmEnvio() {
  confirmEnvioTexto.value = '';
  infoEmpresaAbierta.value = false;
  modalConfirmEnvio.value = true;
}

async function confirmarEnvio() {
  if (!confirmEnvioValido.value) return;
  modalConfirmEnvio.value = false;
  if (proyecto.value?.uuid) {
    try {
      await api.put(`/startup/proyectos/${proyecto.value.uuid}`, { enviado_a_empresa_mail: true });
      proyecto.value.enviado_a_empresa_mail = true;
    } catch { /* no crítico */ }
  }
}

async function copiarUrlModal() {
  await navigator.clipboard.writeText(landingUrl.value);
  urlCopiadaModal.value = true;
  setTimeout(() => { urlCopiadaModal.value = false; }, 2500);
}

function abrirRecurso(item) {
  const filename = (item.filename || '').toLowerCase();
  let tipo = 'otro';
  if (item.resource_type === 'video' || /\.(mp4|mov|avi|webm|mkv)$/.test(filename)) tipo = 'video';
  else if (item.resource_type === 'image' || /\.(jpg|jpeg|png|gif|webp|svg)$/.test(filename)) tipo = 'imagen';
  else if (/\.pdf$/.test(filename)) tipo = 'pdf';
  modalRecurso.value = { url: item.url, label: item.label || item.filename, tipo };
}

onMounted(async () => {
  setTimeout(() => { isLoaded.value = true; }, 80);
  try {
    const [proyRes, recRes] = await Promise.all([
      api.get(`/startup/proyectos/${route.params.uuid}`),
      api.get('/upload/recursos', { params: { microproyecto: route.params.uuid } }),
    ]);
    proyecto.value       = proyRes.data;
    videos.value         = recRes.data.videos    || [];
    documentos.value     = recRes.data.documentos || [];
    if (proyRes.data.estado === 'en_edicion') modalBorradorAviso.value = true;
    if (proyRes.data.estado === 'propuesta') modalPropuestaAviso.value = true;
  } catch {
    error.value = true;
  } finally {
    cargando.value = false;
  }
});

// ── Badge de estado principal (cubre todos los sub-estados) ────────────────
function getEstadoBadge(p) {
  if (!p) return { label: 'En edición', cls: 'bg-amber-50 border-amber-200 text-amber-700', dot: 'bg-amber-400' };
  if (p.estado === 'en_edicion')
    return { label: 'En edición', cls: 'bg-amber-50 border-amber-200 text-amber-700', dot: 'bg-amber-400' };
  if (p.estado === 'archivado')
    return { label: 'Archivado', cls: 'bg-gray-100 border-gray-200 text-gray-400', dot: 'bg-gray-400' };
  if (p.estado === 'validado') {
    if (p.empresa_validado && p.docente_validado)
      return { label: 'Validado · Completo', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' };
    if (p.empresa_validado)
      return { label: 'Validado · Empresa', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' };
    if (p.docente_validado)
      return { label: 'Validado · Docente', cls: 'bg-emerald-50 border-emerald-300 text-emerald-700', dot: 'bg-emerald-500' };
    return { label: 'Validado', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' };
  }
  // propuesta: distinguir sub-estados
  if (p.empresa_no_valida_aun)
    return { label: 'Propuesta · No validar aún', cls: 'bg-red-50 border-red-300 text-red-700', dot: 'bg-red-400' };
  if (p.enviado_a_empresa_mail)
    return { label: 'Propuesta · Enviada, esperando', cls: 'bg-blue-50 border-blue-200 text-blue-700', dot: 'bg-blue-400' };
  return { label: 'Propuesta · Pendiente enviar', cls: 'bg-violet-50 border-violet-300 text-violet-700', dot: 'bg-violet-400' };
}

// Mantener compat con modal-borrador (que comprueba estado directo)
function getEstadoKey(p) {
  if (!p) return 'en_edicion';
  if (p.estado === 'en_edicion') return 'en_edicion';
  if (p.estado === 'archivado')  return 'archivado';
  return (p.estado === 'validado' || p.empresa_validado) ? 'proyecto' : 'propuesta';
}

const landingUrl = computed(() => {
  if (!proyecto.value?.token_empresa) return '';
  const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
  const base = isLocal ? window.location.origin : 'https://dualab.es';
  return `${base}/startup/landing/${proyecto.value.token_empresa}`;
});

async function copiarUrl() {
  await navigator.clipboard.writeText(landingUrl.value);
  urlCopiada.value = true;
  setTimeout(() => { urlCopiada.value = false; }, 2000);
}

const { descargarPDF } = useMicroproyectoPdfExport();

const raCeBlocks = computed(() => {
  const texto = proyecto.value?.ra_ce
  if (!texto?.trim()) return []
  return texto.split('\n\n').map(block => {
    const lines = block.split('\n')
    const modulo = lines[0]?.replace(/^\[|\]$/g, '').trim() || ''
    const ra     = lines[1]?.replace(/^RA:\s*/, '').trim() || ''
    const ces    = lines.slice(3).map(l => l.replace(/^\s*•\s*/, '').trim()).filter(Boolean)
    return { modulo, ra, ces }
  }).filter(b => b.modulo)
})

async function archivar() {
  if (!confirm('¿Archivar este proyecto?')) return;
  await api.put(`/startup/proyectos/${proyecto.value.uuid}`, { estado: 'archivado' });
  proyecto.value.estado = 'archivado';
}
</script>

<template>
  <div class="min-h-screen p-4 md:p-10 font-sans text-[#1F2937] pt-12 md:pt-12">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[700px] h-[400px]
                bg-[#99CC33] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <div class="relative z-10 max-w-4xl mx-auto"
         :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
         style="transition: opacity 0.4s ease, transform 0.4s ease">

      <!-- Cargando -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
        <svg class="animate-spin w-12 h-12 text-[#00A859] mb-4" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="text-[#00A859] font-black tracking-widest uppercase text-sm animate-pulse">Cargando...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-32">
        <p class="text-gray-400 text-sm mb-4">No se pudo cargar el proyecto.</p>
        <button @click="router.push({ name: 'startup-day' })"
                class="text-[#00A859] text-sm font-bold hover:underline">← Volver a la lista</button>
      </div>

      <template v-else-if="proyecto">

        <!-- Barra de navegación y acciones (fuera del cuaderno) -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <div class="flex flex-wrap items-center gap-2">
            <button @click="router.push({ name: 'startup-day' })"
                    class="w-8 h-8 rounded-full bg-white border border-gray-200 shadow-sm flex items-center justify-center
                           text-gray-400 hover:text-[#00A859] hover:border-[#00A859]/30 transition-all shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
            </button>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full
                        bg-[#00A859]/10 border border-[#00A859]/20 shrink-0">
              <span class="w-2 h-2 rounded-full bg-[#00A859]" />
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">StartUp Day</span>
            </div>
            <!-- Badge estado principal -->
            <span :class="['inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full border shrink-0', getEstadoBadge(proyecto).cls]">
              <span :class="['w-1.5 h-1.5 rounded-full shrink-0', getEstadoBadge(proyecto).dot]" />
              {{ getEstadoBadge(proyecto).label }}
            </span>
            <!-- Alerta "empresa no valida aún" -->
            <span v-if="proyecto.empresa_no_valida_aun && !proyecto.empresa_validado"
                  class="inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest
                         px-2.5 py-1 rounded-full border bg-red-50 border-red-300 text-red-700 shrink-0">
              <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Empresa contestó: No validar aún
            </span>
          </div>

          <div class="flex gap-2 shrink-0">
            <button
              @click="descargarPDF(proyecto)"
              class="px-4 py-2 bg-[#00A859] rounded-full text-xs font-black
                     uppercase tracking-widest text-white shadow-sm
                     hover:bg-[#009048] transition-all flex items-center gap-1.5"
              title="Descargar ficha PDF"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                      d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/>
              </svg>
              PDF
            </button>
            <button
              v-if="!authStore.isEmpresa && ['propuesta','validado'].includes(proyecto.estado) && !proyecto.docente_validado"
              @click="modalValidarDocente = true"
              class="px-4 py-2 bg-emerald-50 border border-emerald-200 rounded-full text-xs font-black
                     uppercase tracking-widest text-emerald-700 shadow-sm
                     hover:bg-emerald-100 transition-all flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
              Validar
            </button>
            <button
              v-if="!authStore.isEmpresa"
              @click="router.push({ name: 'startup-day-editar', params: { uuid: proyecto.uuid } })"
              class="px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-black
                     uppercase tracking-widest text-gray-500 shadow-sm
                     hover:border-[#00A859] hover:text-[#00A859] transition-all"
            >
              Editar
            </button>
            <button v-if="!['archivado', 'validado'].includes(proyecto.estado) && !authStore.isEmpresa"
                    @click="archivar"
                    class="px-4 py-2 bg-white border border-gray-200 rounded-full text-xs font-black
                           uppercase tracking-widest text-gray-400 shadow-sm
                           hover:border-red-200 hover:text-red-400 transition-all">
              Archivar
            </button>
          </div>
        </div>

        <!-- ══ HOJA DE CUADERNO ═══════════════════════════════════════════════ -->
        <div class="notebook-page">
          <div class="notebook-margin" aria-hidden="true" />
          <div class="notebook-holes" aria-hidden="true">
            <div class="notebook-hole" />
            <div class="notebook-hole" />
            <div class="notebook-hole" />
            <div class="notebook-hole" />
            <div class="notebook-hole" />
          </div>

          <!-- Título del proyecto -->
          <h1 class="text-2xl md:text-3xl font-black tracking-tight text-[#121212] mb-5 leading-tight">
            {{ proyecto.titulo }}
          </h1>

          <!-- ── META HEADER: empresa / centro educativo / ciclo formativo ── -->
          <div v-if="proyecto.empresa_nombre || proyecto.centro_nombre || proyecto.ciclo_nombre"
               class="meta-band">

            <div v-if="proyecto.empresa_nombre" class="meta-cell">
              <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200
                          flex items-center justify-center shrink-0">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-amber-600"
                     style="width:1.1rem;height:1.1rem">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
              </div>
              <div>
                <p class="meta-label">Empresa</p>
                <p class="meta-value">{{ proyecto.empresa_nombre }}</p>
              </div>
            </div>

            <div v-if="proyecto.centro_nombre" class="meta-cell">
              <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200
                          flex items-center justify-center shrink-0">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-blue-500"
                     style="width:1.1rem;height:1.1rem">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
                </svg>
              </div>
              <div>
                <p class="meta-label">Centro educativo</p>
                <p class="meta-value">{{ proyecto.centro_nombre }}</p>
              </div>
            </div>

            <div v-if="proyecto.ciclo_nombre" class="meta-cell">
              <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200
                          flex items-center justify-center shrink-0">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-[#00A859]"
                     style="width:1.1rem;height:1.1rem">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
              </div>
              <div>
                <p class="meta-label">Ciclo formativo</p>
                <p class="meta-value">{{ proyecto.ciclo_nombre }}</p>
              </div>
            </div>

          </div><!-- /meta-band -->

        <!-- ── Panel de estado de envío / respuesta empresa (si propuesta o validado) ── -->
        <div v-if="proyecto.estado === 'propuesta' || proyecto.estado === 'validado'" class="mb-6 space-y-3">

          <!-- Bloque enlace -->
          <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Enlace de validación empresa</p>
            <div class="flex items-center gap-2">
              <p class="flex-1 text-xs text-gray-400 truncate font-mono bg-gray-50 border border-gray-100
                         rounded-xl px-3 py-2 min-w-0">{{ landingUrl }}</p>
              <button @click="copiarUrl"
                      :class="['shrink-0 px-3 py-2 rounded-xl text-xs font-bold border transition-all',
                                urlCopiada
                                  ? 'bg-[#00A859]/10 text-[#00A859] border-[#00A859]/20'
                                  : 'bg-white text-gray-500 border-gray-200 hover:border-[#00A859] hover:text-[#00A859]']">
                {{ urlCopiada ? '¡Copiado!' : 'Copiar' }}
              </button>
            </div>
          </div>

          <!-- Estado: Validado por empresa ✅ -->
          <div v-if="proyecto.empresa_validado"
               class="flex items-center gap-3 px-4 py-3.5 rounded-2xl
                      bg-[#00A859]/8 border border-[#00A859]/25 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-[#00A859]/15 border border-[#00A859]/25
                        flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-black text-[#00A859]">La empresa ha validado el proyecto</p>
              <p class="text-[10px] text-[#00A859]/70 mt-0.5">
                {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre }}
                respondió con validación positiva.
              </p>
            </div>
          </div>

          <!-- Estado: Empresa contestó "No validar aún" 🔴 -->
          <div v-else-if="proyecto.empresa_no_valida_aun"
               class="flex items-start gap-3 px-4 py-3.5 rounded-2xl
                      bg-red-50 border border-red-300 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-red-100 border border-red-200
                        flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-black text-red-700">La empresa contestó: "No validar aún"</p>
              <p class="text-[10px] text-red-500 mt-0.5">
                {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre }} revisó el proyecto
                pero indicó que aún no puede validarlo. Contacta con la empresa para resolver las dudas.
              </p>
            </div>
          </div>

          <!-- Estado: Propuesta SÍ enviada por mail, pendiente de respuesta 🔵 -->
          <div v-else-if="proyecto.enviado_a_empresa_mail"
               class="flex items-center gap-3 px-4 py-3.5 rounded-2xl
                      bg-blue-50 border border-blue-200 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-blue-100 border border-blue-200
                        flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-black text-blue-700">Enlace enviado a la empresa · Pendiente de respuesta</p>
              <p class="text-[10px] text-blue-500 mt-0.5 truncate">
                Esperando que {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre || 'la empresa' }} acceda y responda.
              </p>
            </div>
            <button v-if="!authStore.isEmpresa"
                    @click="abrirConfirmEnvio"
                    class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold border
                           bg-white text-blue-600 border-blue-200 hover:bg-blue-50 transition-all">
              Reenviar
            </button>
          </div>

          <!-- Estado: Propuesta NO enviada por mail aún 🟣 -->
          <div v-else
               class="flex items-center gap-3 px-4 py-3.5 rounded-2xl
                      bg-violet-50 border border-violet-300 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-white border border-violet-200
                        flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-black text-violet-700">Propuesta pendiente de enviar a empresa</p>
              <p class="text-[10px] text-gray-400 mt-0.5">
                El enlace está generado pero aún no se ha confirmado el envío a
                {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre || 'la empresa' }}.
              </p>
            </div>
            <button v-if="!authStore.isEmpresa"
                    @click="abrirConfirmEnvio"
                    class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold border
                           bg-violet-100 text-violet-700 border-violet-300 hover:bg-violet-200 transition-all">
              Enviar
            </button>
          </div>

          <!-- Validación docente ✅ -->
          <div v-if="proyecto.docente_validado"
               class="flex items-center gap-3 px-4 py-3.5 rounded-2xl
                      bg-emerald-50 border border-emerald-200 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-emerald-100 border border-emerald-200
                        flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-black text-emerald-700">Validado por docente</p>
              <p class="text-[10px] text-emerald-600/70 mt-0.5">Validación pedagógica aprobada por el docente responsable.</p>
            </div>
          </div>
          <!-- Sin validación docente aún -->
          <div v-else-if="!authStore.isEmpresa"
               class="flex items-center gap-3 px-4 py-3.5 rounded-2xl
                      bg-gray-50 border border-gray-100 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-white border border-gray-200
                        flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-black text-gray-500">Pendiente de validación docente</p>
              <p class="text-[10px] text-gray-400 mt-0.5">Puedes validar el proyecto directamente como docente.</p>
            </div>
            <button @click="modalValidarDocente = true"
                    class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold border
                           bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 transition-all">
              Validar
            </button>
          </div>

        </div>

        <!-- Secciones del proyecto -->
        <div class="grid gap-4 sm:grid-cols-2">

          <!-- Empresa -->
          <div v-if="proyecto.datos_empresa?.nombre" class="card-section">
            <p class="section-label">Empresa</p>
            <p class="text-sm font-bold text-[#1F2937] mb-1">{{ proyecto.datos_empresa.nombre }}</p>
            <div class="space-y-0.5 text-xs text-gray-400">
              <p v-if="proyecto.datos_empresa.sector">{{ proyecto.datos_empresa.sector }}</p>
              <p v-if="proyecto.datos_empresa.persona_contacto">{{ proyecto.datos_empresa.persona_contacto }}</p>
              <p v-if="proyecto.datos_empresa.email">{{ proyecto.datos_empresa.email }}</p>
              <p v-if="proyecto.datos_empresa.descripcion" class="mt-2 leading-relaxed text-gray-500">
                {{ proyecto.datos_empresa.descripcion }}
              </p>
            </div>
          </div>

          <!-- Docente responsable -->
          <div v-if="proyecto.datos_centro?.docente_nombre" class="card-section">
            <p class="section-label">Docente responsable</p>
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-[#00A859]/10 border border-[#00A859]/20
                          flex items-center justify-center shrink-0 text-[#00A859] font-black text-sm">
                {{ proyecto.datos_centro.docente_nombre.charAt(0).toUpperCase() }}
              </div>
              <div>
                <p class="text-sm font-bold text-[#1F2937]">{{ proyecto.datos_centro.docente_nombre }}</p>
                <a v-if="proyecto.datos_centro.docente_email"
                   :href="`mailto:${proyecto.datos_centro.docente_email}`"
                   class="text-xs text-[#00A859] hover:underline">
                  {{ proyecto.datos_centro.docente_email }}
                </a>
              </div>
            </div>
            <div v-if="proyecto.datos_centro.nombre" class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-400">
              {{ proyecto.datos_centro.nombre }}<span v-if="proyecto.datos_centro.municipio"> · {{ proyecto.datos_centro.municipio }}</span>
            </div>
          </div>

          <!-- Equipo -->
          <div v-if="proyecto.equipo?.alumnos?.length" class="card-section">
            <p class="section-label">Equipo ({{ proyecto.equipo.alumnos.length }} personas)</p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="a in proyecto.equipo.alumnos" :key="a.nombre"
                    class="text-xs bg-gray-50 border border-gray-200 px-2.5 py-1 rounded-full text-gray-600">
                {{ a.nombre }}<span v-if="a.rol" class="text-gray-400"> · {{ a.rol }}</span>
              </span>
            </div>
          </div>

          <!-- El reto -->
          <div v-if="proyecto.diseno_reto?.descripcion" class="card-section sm:col-span-2">
            <p class="section-label">El reto</p>
            <p v-if="proyecto.diseno_reto.pregunta_reto"
               class="text-sm font-bold text-[#00A859] mb-2 italic">
              "{{ proyecto.diseno_reto.pregunta_reto }}"
            </p>
            <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.diseno_reto.descripcion }}</p>
            <div v-if="proyecto.diseno_reto.entregables" class="mt-3 pt-3 border-t border-gray-100">
              <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Entregables</p>
              <p class="text-xs text-gray-500">{{ proyecto.diseno_reto.entregables }}</p>
            </div>
          </div>

          <!-- Módulos -->
          <div v-if="proyecto.modulos_seleccionados?.length" class="card-section">
            <p class="section-label">Módulos ({{ proyecto.modulos_seleccionados.length }})</p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="m in proyecto.modulos_seleccionados" :key="m.id"
                    class="text-xs bg-[#00A859]/8 border border-[#00A859]/15 text-[#00A859] px-2.5 py-1 rounded-full">
                {{ m.nombre }}
              </span>
            </div>
          </div>

          <!-- RA/CE -->
          <div v-if="raCeBlocks.length" class="card-section sm:col-span-2">
            <p class="section-label">Resultados de Aprendizaje y Criterios de Evaluación</p>
            <div class="space-y-4">
              <div v-for="(block, i) in raCeBlocks" :key="i" class="border border-gray-100 rounded-xl p-3.5">
                <p class="text-[10px] font-black uppercase tracking-widest text-[#00A859] mb-1">{{ block.modulo }}</p>
                <p class="text-sm font-semibold text-[#1F2937] mb-2">{{ block.ra }}</p>
                <ul v-if="block.ces.length" class="space-y-1 pl-1">
                  <li v-for="(ce, j) in block.ces" :key="j"
                      class="flex items-start gap-2 text-xs text-gray-500">
                    <span class="text-amber-400 shrink-0 font-bold mt-0.5">•</span>{{ ce }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <!-- Si ra_ce es texto libre sin formato estructurado -->
          <div v-else-if="proyecto.ra_ce?.trim()" class="card-section sm:col-span-2">
            <p class="section-label">Resultados de Aprendizaje y Criterios de Evaluación</p>
            <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{{ proyecto.ra_ce }}</p>
          </div>

          <!-- Fases -->
          <div v-if="proyecto.diseno_microproyecto?.fases?.length" class="card-section">
            <p class="section-label">Fases del proyecto</p>
            <ol class="space-y-2.5">
              <li v-for="(f, i) in proyecto.diseno_microproyecto.fases" :key="i"
                  class="flex items-start gap-2.5 text-sm">
                <span class="w-5 h-5 rounded-full bg-[#00A859]/10 text-[#00A859] font-black text-[10px]
                             flex items-center justify-center shrink-0 mt-0.5">{{ i + 1 }}</span>
                <div>
                  <p class="font-bold text-[#1F2937]">{{ f.nombre }}
                    <span v-if="f.duracion" class="text-gray-400 font-normal text-xs"> · {{ f.duracion }}</span>
                  </p>
                  <p v-if="f.descripcion" class="text-gray-400 text-xs mt-0.5">{{ f.descripcion }}</p>
                </div>
              </li>
            </ol>
          </div>

          <!-- Objetivos -->
          <div v-if="proyecto.objetivos?.lista?.length" class="card-section">
            <p class="section-label">Objetivos</p>
            <ul class="space-y-1.5">
              <li v-for="obj in proyecto.objetivos.lista" :key="obj"
                  class="flex items-start gap-2 text-sm text-gray-600">
                <span class="text-[#00A859] shrink-0 mt-0.5 font-bold">›</span> {{ obj }}
              </li>
            </ul>
          </div>

          <!-- KPIs -->
          <div v-if="proyecto.kpis?.lista?.length" class="card-section">
            <p class="section-label">KPIs</p>
            <ul class="space-y-1.5">
              <li v-for="kpi in proyecto.kpis.lista" :key="kpi"
                  class="flex items-start gap-2 text-sm text-gray-600">
                <span class="text-[#00A859] shrink-0 mt-0.5">✓</span> {{ kpi }}
              </li>
            </ul>
          </div>

          <!-- (feedback empresa — movido debajo del grid) -->

          <!-- Resumen -->
          <div v-if="proyecto.resumen?.texto" class="card-section sm:col-span-2">
            <p class="section-label">Resumen ejecutivo</p>
            <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.resumen.texto }}</p>
          </div>

          <!-- ── Recursos adjuntos ───────────────────────────────────────── -->
          <div v-if="videos.length || documentos.length" class="sm:col-span-2">
            <button
              @click="recursosAbierto = !recursosAbierto"
              class="w-full flex items-center justify-between px-5 py-4
                     bg-white border border-gray-100 rounded-[1.5rem] shadow-sm
                     hover:border-[#00A859]/30 transition-all"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20
                            flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                  </svg>
                </div>
                <div class="text-left">
                  <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Recursos adjuntos</p>
                  <p class="text-xs text-gray-500 mt-0.5">
                    {{ videos.length > 0 ? `${videos.length} vídeo${videos.length > 1 ? 's' : ''}` : '' }}
                    {{ videos.length && documentos.length ? ' · ' : '' }}
                    {{ documentos.length > 0 ? `${documentos.length} documento${documentos.length > 1 ? 's' : ''}` : '' }}
                  </p>
                </div>
              </div>
              <svg :class="['w-4 h-4 text-gray-400 transition-transform duration-200', recursosAbierto ? 'rotate-180' : '']"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <!-- Panel desplegable -->
            <Transition
              enter-active-class="transition-all duration-200 ease-out"
              enter-from-class="opacity-0 -translate-y-1"
              leave-active-class="transition-all duration-150 ease-in"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-if="recursosAbierto"
                   class="mt-2 bg-white border border-gray-100 rounded-[1.5rem] shadow-sm p-5 space-y-5">

                <!-- Vídeos -->
                <div v-if="videos.length">
                  <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-3">Vídeos</p>
                  <div class="space-y-2">
                    <div v-for="(v, i) in videos" :key="i"
                         class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-100
                                hover:border-blue-200 hover:bg-blue-50/40 transition-colors group/vid">
                      <button @click="abrirRecurso(v)"
                              class="w-7 h-7 rounded-lg bg-blue-50 shrink-0 flex items-center justify-center
                                     group-hover/vid:bg-blue-100 transition-colors">
                        <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                        </svg>
                      </button>
                      <button @click="abrirRecurso(v)" class="flex-1 min-w-0 text-left">
                        <p class="text-xs font-bold text-gray-700 truncate group-hover/vid:text-blue-600 transition-colors">
                          {{ v.label || v.filename }}
                        </p>
                        <p class="text-[9px] text-blue-400/80 truncate">Cloudinary · {{ v.filename }}</p>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Documentos, imágenes, etc. -->
                <div v-if="documentos.length">
                  <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-3">Documentos, imágenes, etc...</p>
                  <div class="space-y-2">
                    <div v-for="(d, i) in documentos" :key="i"
                         class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-100
                                hover:border-[#00A859]/30 hover:bg-[#00A859]/5 transition-colors group/doc">
                      <button @click="abrirRecurso(d)"
                              class="w-7 h-7 rounded-lg bg-[#00A859]/10 shrink-0 flex items-center justify-center
                                     group-hover/doc:bg-[#00A859]/20 transition-colors">
                        <svg class="w-3.5 h-3.5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                      </button>
                      <button @click="abrirRecurso(d)" class="flex-1 min-w-0 text-left">
                        <p class="text-xs font-bold text-gray-700 truncate group-hover/doc:text-[#00A859] transition-colors">
                          {{ d.label || d.filename }}
                        </p>
                        <p class="text-[9px] text-blue-400/80 truncate">Cloudinary · {{ d.filename }}</p>
                      </button>
                    </div>
                  </div>
                </div>

              </div>
            </Transition>
          </div>

        </div><!-- /grid -->

        <!-- ══ FEEDBACK DE LA EMPRESA ══════════════════════════════════════ -->
        <div v-if="proyecto.validacion_empresa?.respuestas" class="mt-6">

          <!-- Cabecera del bloque -->
          <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                   :class="proyecto.empresa_validado
                     ? 'bg-[#00A859]/10 border border-[#00A859]/20'
                     : 'bg-red-50 border border-red-200'">
                <svg class="w-5 h-5"
                     :class="proyecto.empresa_validado ? 'text-[#00A859]' : 'text-red-500'"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="proyecto.empresa_validado"
                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M5 13l4 4L19 7"/>
                  <path v-else
                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
              </div>
              <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Feedback de la empresa</p>
                <p class="text-sm font-black text-[#121212]">
                  {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre || 'Empresa' }}
                </p>
              </div>
            </div>
            <!-- Badge decisión -->
            <span v-if="proyecto.empresa_validado"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border
                         text-[10px] font-black uppercase tracking-widest
                         bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
              Decisión: Validó la propuesta
            </span>
            <span v-else-if="proyecto.empresa_no_valida_aun"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border
                         text-[10px] font-black uppercase tracking-widest
                         bg-red-50 border-red-300 text-red-700">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Decisión: No validar aún
            </span>
          </div>

          <!-- Preguntas y respuestas -->
          <div class="bg-white border rounded-[1.5rem] shadow-sm overflow-hidden"
               :class="proyecto.empresa_validado
                 ? 'border-[#00A859]/20'
                 : proyecto.empresa_no_valida_aun
                   ? 'border-red-200'
                   : 'border-gray-100'">

            <div class="grid sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
              <div v-for="(val, key) in proyecto.validacion_empresa.respuestas" :key="key"
                   class="px-5 py-4 flex items-start gap-4">
                <!-- Icono respuesta -->
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                     :class="val === 'Sí'
                       ? 'bg-[#00A859]/10 border border-[#00A859]/20'
                       : val === 'No'
                         ? 'bg-red-50 border border-red-200'
                         : 'bg-amber-50 border border-amber-200'">
                  <svg class="w-4 h-4"
                       :class="val === 'Sí' ? 'text-[#00A859]' : val === 'No' ? 'text-red-500' : 'text-amber-500'"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="val === 'Sí'"
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    <path v-else-if="val === 'No'"
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    <path v-else
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-0.5">
                    {{ key === 'reto_comprensible'   ? '¿El reto es comprensible y realista?'
                     : key === 'objetivos_alineados' ? '¿Los objetivos se alinean con la empresa?'
                     : key === 'equipo_adecuado'     ? '¿El perfil del equipo es adecuado?'
                     : key === 'viabilidad'           ? '¿El proyecto es viable en la empresa?'
                     : key.replace(/_/g, ' ') }}
                  </p>
                  <p class="text-base font-black"
                     :class="val === 'Sí' ? 'text-[#00A859]' : val === 'No' ? 'text-red-500' : 'text-amber-600'">
                    {{ val }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Comentarios -->
            <div v-if="proyecto.validacion_empresa.comentarios"
                 class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-start gap-3">
              <svg class="w-4 h-4 text-gray-300 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
              </svg>
              <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Comentarios adicionales</p>
                <p class="text-sm text-gray-600 leading-relaxed italic">
                  "{{ proyecto.validacion_empresa.comentarios }}"
                </p>
              </div>
            </div>

          </div>
        </div>
        <!-- ══ FIN FEEDBACK EMPRESA ══════════════════════════════════════════ -->

        </div><!-- /notebook-page -->

      </template>
    </div>
  </div>

  <!-- ══ MODAL VISOR RECURSOS ═════════════════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalRecurso"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @click.self="modalRecurso = null">

      <div class="absolute inset-0 bg-black/70 backdrop-blur-md" @click="modalRecurso = null" />

      <div class="relative z-10 bg-white rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden
                  flex flex-col max-h-[90vh]">

        <!-- Cabecera -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
          <p class="text-sm font-black text-[#121212] truncate pr-4">{{ modalRecurso.label }}</p>
          <button @click="modalRecurso = null"
                  class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center
                         text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-all shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Contenido según tipo -->
        <div class="flex-1 overflow-auto bg-gray-950 flex items-center justify-center min-h-[300px]">

          <video v-if="modalRecurso.tipo === 'video'"
                 :src="modalRecurso.url" controls autoplay
                 class="w-full max-h-[70vh] object-contain" />

          <img v-else-if="modalRecurso.tipo === 'imagen'"
               :src="modalRecurso.url" :alt="modalRecurso.label"
               class="max-w-full max-h-[70vh] object-contain" />

          <iframe v-else-if="modalRecurso.tipo === 'pdf'"
                  :src="modalRecurso.url"
                  class="w-full h-[70vh] border-0" />

          <div v-else class="flex flex-col items-center gap-4 p-10 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-800 flex items-center justify-center">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
            </div>
            <p class="text-gray-400 text-sm">Este tipo de archivo no se puede previsualizar</p>
            <a :href="modalRecurso.url" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#00A859] text-white
                      rounded-full text-xs font-black uppercase tracking-widest
                      hover:bg-[#00A859]/90 transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
              </svg>
              Abrir en nueva pestaña
            </a>
          </div>

        </div>
      </div>
    </div>
  </Transition>

  <!-- ══ MODAL AVISO PROPUESTA ═══════════════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalPropuestaAviso"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @click.self="modalPropuestaAviso = false">

      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="modalPropuestaAviso = false" />

      <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8 overflow-y-auto max-h-[90vh]">

        <!-- Icono -->
        <div class="w-14 h-14 rounded-2xl bg-[#00A859]/10 border border-[#00A859]/20
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
        </div>

        <!-- Badge estado -->
        <div class="flex justify-center mb-4">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full
                       bg-[#00A859]/10 border border-[#00A859]/20 text-[#00A859]
                       text-[10px] font-black uppercase tracking-widest">
            <span class="w-1.5 h-1.5 rounded-full bg-[#00A859] animate-pulse" />
            Propuesta · Pendiente de validar
          </span>
        </div>

        <h3 class="text-xl font-black text-[#121212] text-center mb-2">
          Proyecto en propuesta
        </h3>
        <p class="text-sm text-gray-500 text-center mb-6 leading-relaxed">
          Este proyecto puede validarse a través de la empresa, directamente como docente, o por ambas vías.
        </p>

        <!-- Vía A: Validación empresa -->
        <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4 mb-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">
            Vía A · Validación empresa
          </p>
          <div class="flex items-center gap-2 mb-3">
            <p class="flex-1 text-xs text-gray-400 truncate font-mono bg-white border border-gray-200
                       rounded-xl px-3 py-2 min-w-0">
              {{ landingUrl || '—' }}
            </p>
            <button @click="copiarUrlModal"
                    :class="['shrink-0 px-3 py-2 rounded-xl text-xs font-bold border transition-all',
                             urlCopiadaModal
                               ? 'bg-[#00A859]/10 text-[#00A859] border-[#00A859]/20'
                               : 'bg-white text-gray-500 border-gray-200 hover:border-[#00A859] hover:text-[#00A859]']">
              {{ urlCopiadaModal ? '¡Copiado!' : 'Copiar' }}
            </button>
          </div>

          <!-- Info empresa desplegable -->
          <div v-if="proyecto && proyecto.empresa_id" class="mb-3">
            <button @click="infoEmpresaAbierta = !infoEmpresaAbierta"
                    class="w-full flex items-center justify-between px-3 py-2 rounded-xl
                           bg-white border border-gray-200 text-xs font-bold text-gray-600
                           hover:border-[#00A859]/40 hover:text-[#00A859] transition-all">
              <span class="flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre || 'Ver info de la empresa' }}
              </span>
              <svg class="w-3.5 h-3.5 transition-transform duration-200 text-gray-400 shrink-0"
                   :class="infoEmpresaAbierta ? 'rotate-180' : ''"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <Transition
              enter-active-class="transition-all duration-200 ease-out overflow-hidden"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-96"
              leave-active-class="transition-all duration-150 ease-in overflow-hidden"
              leave-from-class="opacity-100 max-h-96"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-if="infoEmpresaAbierta"
                   class="mt-2 px-3 py-3 bg-white border border-gray-200 rounded-xl space-y-1.5">
                <p v-if="proyecto.datos_empresa?.sector"
                   class="text-[10px] text-gray-400">
                  <span class="font-black uppercase tracking-wider">Sector:</span>
                  {{ proyecto.datos_empresa.sector }}
                </p>
                <p v-if="proyecto.datos_empresa?.persona_contacto"
                   class="text-[10px] text-gray-400">
                  <span class="font-black uppercase tracking-wider">Contacto:</span>
                  {{ proyecto.datos_empresa.persona_contacto }}
                </p>
                <p v-if="proyecto.datos_empresa?.email"
                   class="text-[10px] text-gray-400">
                  <span class="font-black uppercase tracking-wider">Email:</span>
                  {{ proyecto.datos_empresa.email }}
                </p>
                <p v-if="proyecto.datos_empresa?.descripcion"
                   class="text-[10px] text-gray-500 leading-relaxed pt-1 border-t border-gray-100">
                  {{ proyecto.datos_empresa.descripcion }}
                </p>
              </div>
            </Transition>
          </div>

          <!-- Botones: enviar + directorio -->
          <div v-if="!authStore.isEmpresa" class="flex flex-col gap-2">
            <button v-if="proyecto && proyecto.empresa_id"
                    @click="abrirConfirmEnvio"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5
                           bg-amber-50 border border-amber-200 text-amber-700 rounded-xl
                           text-xs font-bold hover:bg-amber-100 transition-all">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              Enviar enlace a la empresa
            </button>
            <button @click="router.push({ name: 'empresas' }); modalPropuestaAviso = false"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5
                           bg-white border border-gray-200 text-gray-500 rounded-xl
                           text-xs font-bold hover:border-[#00A859]/40 hover:text-[#00A859] transition-all">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              Ir al directorio de empresas
            </button>
          </div>
        </div>

        <!-- Vía B: Validación docente -->
        <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 mb-6">
          <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600 mb-3">
            Vía B · Validación docente
          </p>
          <p class="text-xs text-gray-600 leading-relaxed mb-3">
            Puedes validar el proyecto directamente sin esperar a la empresa.
            <span class="text-amber-600 font-bold">Esto no sustituye la validación empresa</span>
            — ambas son independientes y complementarias.
          </p>
          <button v-if="!proyecto?.docente_validado"
                  @click="modalValidarDocente = true; modalPropuestaAviso = false"
                  class="w-full flex items-center justify-center gap-2 px-4 py-2.5
                         bg-emerald-600 text-white rounded-xl
                         text-xs font-bold hover:bg-emerald-700 transition-all">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            Validar como docente
          </button>
          <div v-else class="flex items-center gap-2 px-3 py-2 bg-emerald-100 rounded-xl">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-xs font-bold text-emerald-700">Ya has validado este proyecto como docente</p>
          </div>
        </div>

        <!-- Botones -->
        <div class="flex gap-3">
          <button
            @click="modalPropuestaAviso = false"
            class="flex-1 inline-flex items-center justify-center
                   px-5 py-3 bg-gray-100 text-[#1F2937] rounded-full
                   text-xs font-black uppercase tracking-widest
                   hover:bg-gray-200 transition-all active:scale-95"
          >
            Entendido
          </button>
          <button
            v-if="!authStore.isEmpresa"
            @click="router.push({ name: 'startup-day-editar', params: { uuid: proyecto.uuid } })"
            class="flex-1 inline-flex items-center justify-center gap-2
                   px-5 py-3 bg-[#00A859] text-white rounded-full
                   text-xs font-black uppercase tracking-widest shadow-sm
                   hover:bg-[#00A859]/90 transition-all active:scale-95"
          >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Ir a editar
          </button>
        </div>

      </div>
    </div>
  </Transition>

  <!-- ══ MODAL CONFIRMACIÓN ENVÍO ENLACE ══════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalConfirmEnvio"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4"
         @click.self="modalConfirmEnvio = false">

      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="modalConfirmEnvio = false" />

      <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 overflow-y-auto max-h-[90vh]">

        <!-- Icono -->
        <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-200
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>

        <h3 class="text-xl font-black text-[#121212] text-center mb-1">
          Confirmar envío
        </h3>
        <p class="text-sm text-gray-500 text-center mb-5 leading-relaxed">
          Estás a punto de abrir el panel de envío del enlace de validación para:
        </p>

        <!-- Tarjeta empresa -->
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-5">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 rounded-xl bg-[#00A859]/10 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
            </div>
            <p class="font-black text-sm text-[#121212]">
              {{ proyecto?.empresa_nombre || proyecto?.datos_empresa?.nombre || '—' }}
            </p>
          </div>
          <div class="space-y-1 pl-10">
            <p v-if="proyecto?.datos_empresa?.sector" class="text-[11px] text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-[10px]">Sector:</span>
              {{ proyecto.datos_empresa.sector }}
            </p>
            <p v-if="proyecto?.datos_empresa?.persona_contacto" class="text-[11px] text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-[10px]">Contacto:</span>
              {{ proyecto.datos_empresa.persona_contacto }}
            </p>
            <p v-if="proyecto?.datos_empresa?.email" class="text-[11px] text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-[10px]">Email:</span>
              {{ proyecto.datos_empresa.email }}
            </p>
          </div>
        </div>

        <!-- Campo de seguridad: escribe "enviar" -->
        <div class="mb-5">
          <label class="block text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">
            Escribe <span class="font-black text-[#1F2937] normal-case tracking-normal">enviar</span> para confirmar
          </label>
          <input
            v-model="confirmEnvioTexto"
            type="text"
            placeholder="Escribe enviar…"
            autocomplete="off"
            @keydown.enter.prevent="confirmEnvioValido && confirmarEnvio()"
            class="w-full border rounded-xl px-4 py-2.5 text-sm outline-none transition-all"
            :class="confirmEnvioTexto && !confirmEnvioValido
              ? 'border-red-300 bg-red-50 text-red-700 focus:ring-2 focus:ring-red-200'
              : confirmEnvioTexto && confirmEnvioValido
                ? 'border-[#00A859]/50 bg-[#00A859]/5 text-[#00A859] focus:ring-2 focus:ring-[#00A859]/20'
                : 'border-gray-200 bg-white focus:border-gray-400 focus:ring-2 focus:ring-gray-100'"
          />
          <p v-if="confirmEnvioTexto && !confirmEnvioValido"
             class="mt-1.5 text-[10px] text-red-500 font-semibold">
            Escribe exactamente: enviar
          </p>
          <p v-if="confirmEnvioTexto && confirmEnvioValido"
             class="mt-1.5 text-[10px] text-[#00A859] font-semibold">
            Confirmado. Ya puedes continuar.
          </p>
        </div>

        <!-- Botones principales -->
        <div class="flex gap-3 mb-3">
          <button
            @click="modalConfirmEnvio = false"
            class="flex-1 inline-flex items-center justify-center
                   px-4 py-2.5 bg-gray-100 text-[#1F2937] rounded-full
                   text-xs font-black uppercase tracking-widest
                   hover:bg-gray-200 transition-all active:scale-95"
          >
            Cancelar
          </button>
          <button
            @click="confirmarEnvio"
            :disabled="!confirmEnvioValido"
            class="flex-1 inline-flex items-center justify-center gap-2
                   px-4 py-2.5 rounded-full text-xs font-black uppercase tracking-widest
                   transition-all active:scale-95"
            :class="confirmEnvioValido
              ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-sm'
              : 'bg-gray-100 text-gray-300 cursor-not-allowed'"
          >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Confirmar y enviar
          </button>
        </div>

        <!-- Botón directorio de empresas -->
        <button
          @click="router.push({ name: 'empresas' }); modalConfirmEnvio = false; modalPropuestaAviso = false"
          class="w-full inline-flex items-center justify-center gap-2
                 px-4 py-2.5 bg-white border border-gray-200 text-gray-500 rounded-full
                 text-xs font-bold hover:border-[#00A859]/40 hover:text-[#00A859] transition-all"
        >
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
          Ir al directorio de empresas
        </button>

      </div>
    </div>
  </Transition>

  <!-- ══ MODAL VALIDAR DOCENTE ════════════════════════════════════════════════ -->
  <ValidarDocenteModal
    :visible="modalValidarDocente"
    :loading="validandoDocente"
    @confirm="validarComoDocente"
    @cancel="modalValidarDocente = false"
  />

  <!-- ══ MODAL AVISO BORRADOR ════════════════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalBorradorAviso"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @click.self="modalBorradorAviso = false">

      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="modalBorradorAviso = false" />

      <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8">

        <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-100
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </div>

        <h3 class="text-xl font-black text-[#121212] text-center mb-4">
          Proyecto en edición
        </h3>

        <p class="text-sm text-gray-600 leading-relaxed text-center">
          Este proyecto está marcado como <strong>En edición</strong> porque se guardó así para seguir construyéndolo.
          Cuando esté listo para enviarlo a la empresa, entra en <strong>Editar</strong> y en el desplegable
          <strong>Estado del proyecto</strong> selecciona <strong>Propuesta</strong> para generar el enlace de validación.
        </p>

        <div class="flex gap-3 mt-7">
          <button
            @click="modalBorradorAviso = false"
            class="flex-1 inline-flex items-center justify-center
                   px-5 py-3 bg-gray-100 text-[#1F2937] rounded-full
                   text-xs font-black uppercase tracking-widest
                   hover:bg-gray-200 transition-all active:scale-95"
          >
            Entendido
          </button>
          <button
            v-if="!authStore.isEmpresa"
            @click="router.push({ name: 'startup-day-editar', params: { uuid: proyecto.uuid } })"
            class="flex-1 inline-flex items-center justify-center gap-2
                   px-5 py-3 bg-[#00A859] text-white rounded-full
                   text-xs font-black uppercase tracking-widest shadow-sm
                   hover:bg-[#00A859]/90 transition-all active:scale-95"
          >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Ir a editar
          </button>
        </div>

      </div>
    </div>
  </Transition>

</template>

<style scoped>
@reference "../style.css";

/* ── Secciones de contenido ────────────────────────────────────────────── */
.card-section {
  @apply bg-white border border-gray-200 rounded-2xl shadow-sm p-5 space-y-3;
}
.section-label {
  @apply text-[10px] font-black uppercase tracking-[0.2em] text-gray-400;
}

/* ── Hoja de cuaderno ──────────────────────────────────────────────────── */
.notebook-page {
  position: relative;
  overflow: hidden;
  background-color: #fefef8;
  background-image:
    /* Zona del lomo (izquierda, gris claro) */
    linear-gradient(90deg, #eef1f5 0, #eef1f5 3.75rem, transparent 3.75rem),
    /* Líneas horizontales azul pálido */
    repeating-linear-gradient(
      transparent,
      transparent 31px,
      #c8d9f0 31px,
      #c8d9f0 32px
    );
  padding: 2rem 1.75rem 2.5rem 5.5rem;
  border-radius: 0.75rem;
  border: 1px solid #dde3ed;
  box-shadow:
    0 4px 24px -4px rgba(0, 0, 0, 0.08),
    0 1px 4px rgba(0, 0, 0, 0.04),
    2px 0 0 0 #d1dae8 inset;
  margin-bottom: 2rem;
}

/* Línea roja de margen */
.notebook-margin {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 4.3rem;
  width: 1.5px;
  background: #fca5a5;
  pointer-events: none;
}

/* Agujeros del espiral */
.notebook-holes {
  position: absolute;
  left: 1.25rem;
  top: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  padding: 1.75rem 0;
  pointer-events: none;
  z-index: 2;
}

.notebook-hole {
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 50%;
  background: white;
  border: 1.5px solid #c4cdd9;
  box-shadow: inset 0 1px 3px #b8c2cc;
}

/* ── Meta-header: empresa / centro / ciclo ─────────────────────────────── */
.meta-band {
  display: flex;
  flex-wrap: wrap;
  border-radius: 0.875rem;
  margin-bottom: 1.75rem;
  overflow: hidden;
  border: 1px solid #dde3ed;
  background: linear-gradient(135deg, #f8fafd 0%, #f1f5fb 100%);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
}

.meta-cell {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  flex: 1;
  min-width: 145px;
  border-right: 1px solid #dde3ed;
}

.meta-cell:last-child {
  border-right: none;
}

.meta-label {
  display: block;
  font-size: 0.625rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  color: #9ca3af;
  margin-bottom: 0.125rem;
}

.meta-value {
  display: block;
  font-size: 0.875rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1.35;
}

@media (max-width: 640px) {
  .notebook-page {
    padding: 1.5rem 1rem 2rem 4.5rem;
  }
  .meta-cell {
    flex: 1 0 100%;
    border-right: none;
    border-bottom: 1px solid #dde3ed;
  }
  .meta-cell:last-child {
    border-bottom: none;
  }
}
</style>
