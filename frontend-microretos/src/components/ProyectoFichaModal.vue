<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'
import { useMicroproyectoPdfExport } from '../composables/useMicroproyectoPdfExport.js'
import { duracionPorFase, FASES_PROYECTO, COLOR_MAP_FASES } from '../config/fasesProyecto.js'

const props = defineProps({
  proyectoUuid: { type: String, default: null },
})
const emit = defineEmits(['close'])

const router = useRouter()

const abierto  = computed(() => Boolean(props.proyectoUuid))
const proyecto = ref(null)
const cargando = ref(false)
const error    = ref(false)

const videos     = ref([])
const documentos = ref([])
const recursosAbierto = ref(false)
const modalRecurso    = ref(null)

const raCeAbierto  = ref(false)
const urlCopiada   = ref(false)

watch(() => props.proyectoUuid, async (uuid) => {
  if (!uuid) return
  proyecto.value  = null
  videos.value    = []
  documentos.value = []
  error.value     = false
  cargando.value  = true
  try {
    const [proyRes, recRes] = await Promise.all([
      api.get(`/startup/proyectos/${uuid}`),
      api.get('/upload/recursos', { params: { microproyecto: uuid } }),
    ])
    proyecto.value    = proyRes.data
    videos.value      = recRes.data.videos     || []
    documentos.value  = recRes.data.documentos || []
  } catch (e) {
    console.error('Error cargando proyecto en modal:', e)
    error.value = true
  } finally {
    cargando.value = false
  }
}, { immediate: true })

// Agrupa evaluacion_oficial (array plano {modulo, ra, ce, aplicacion}) por módulo
// para el bloque único de RA/CE, igual que StartupDayDetalle.vue agrupa su texto libre.
const raCeBlocks = computed(() => {
  const entradas = proyecto.value?.evaluacion_oficial
  if (!Array.isArray(entradas) || !entradas.length) return []
  const mapa = new Map()
  for (const e of entradas) {
    const nombre = e.modulo || 'Sin módulo'
    if (!mapa.has(nombre)) mapa.set(nombre, [])
    mapa.get(nombre).push(e)
  }
  return [...mapa.entries()].map(([modulo, items]) => ({ modulo, items }))
})

const landingUrl = computed(() => {
  if (!proyecto.value?.token_empresa) return ''
  const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
  const base = isLocal ? window.location.origin : 'https://dualab.es'
  return `${base}/startup/landing/${proyecto.value.token_empresa}`
})

async function copiarUrl() {
  await navigator.clipboard.writeText(landingUrl.value)
  urlCopiada.value = true
  setTimeout(() => { urlCopiada.value = false }, 2000)
}

function abrirRecurso(item) {
  const filename = (item.filename || '').toLowerCase()
  let tipo = 'otro'
  if (item.resource_type === 'video' || /\.(mp4|mov|avi|webm|mkv)$/.test(filename)) tipo = 'video'
  else if (item.resource_type === 'image' || /\.(jpg|jpeg|png|gif|webp|svg)$/.test(filename)) tipo = 'imagen'
  else if (/\.pdf$/.test(filename)) tipo = 'pdf'
  modalRecurso.value = { url: item.url, label: item.label || item.filename, tipo }
}

function getEstadoBadge(p) {
  if (!p) return { label: '—', cls: 'bg-gray-100 border-gray-200 text-gray-400', dot: 'bg-gray-300' }
  if (p.estado === 'en_edicion')
    return { label: 'En edición', cls: 'bg-amber-50 border-amber-200 text-amber-700', dot: 'bg-amber-400' }
  if (p.estado === 'archivado')
    return { label: 'Archivado', cls: 'bg-gray-100 border-gray-200 text-gray-400', dot: 'bg-gray-400' }
  if (p.estado === 'validado') {
    if (p.empresa_validado && p.docente_validado)
      return { label: 'Validado · Completo', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
    if (p.empresa_validado)
      return { label: 'Validado · Empresa', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
    if (p.docente_validado)
      return { label: 'Validado · Docente', cls: 'bg-emerald-50 border-emerald-300 text-emerald-700', dot: 'bg-emerald-500' }
    return { label: 'Validado', cls: 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]', dot: 'bg-[#00A859]' }
  }
  if (p.empresa_no_valida_aun)
    return { label: 'Propuesta · No validar aún', cls: 'bg-red-50 border-red-300 text-red-700', dot: 'bg-red-400' }
  if (p.enviado_a_empresa_mail)
    return { label: 'Propuesta · Enviada, esperando', cls: 'bg-blue-50 border-blue-200 text-blue-700', dot: 'bg-blue-400' }
  return { label: 'Propuesta · Pendiente enviar', cls: 'bg-violet-50 border-violet-300 text-violet-700', dot: 'bg-violet-400' }
}

const { descargarPDF } = useMicroproyectoPdfExport()

function cerrar() {
  emit('close')
}

function irAPaginaCompleta() {
  router.push({ name: 'startup-day-detalle', params: { uuid: proyecto.value.uuid } })
  cerrar()
}
</script>

<template>
  <Teleport to="body">
    <Transition name="proyecto-ficha-modal">
      <div v-if="abierto"
           class="fixed inset-0 z-[80] flex items-start justify-center p-4 overflow-y-auto">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cerrar" />

        <div class="relative z-10 bg-[#f4f6fa] rounded-3xl shadow-2xl w-full max-w-4xl my-4
                    max-h-[92vh] flex flex-col overflow-hidden">

          <!-- Barra superior sticky -->
          <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full
                          bg-[#00A859]/10 border border-[#00A859]/20 shrink-0">
                <span class="w-2 h-2 rounded-full bg-[#00A859]" />
                <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">StartUp Day</span>
              </div>
              <span v-if="proyecto"
                    :class="['inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full border shrink-0', getEstadoBadge(proyecto).cls]">
                <span :class="['w-1.5 h-1.5 rounded-full shrink-0', getEstadoBadge(proyecto).dot]" />
                {{ getEstadoBadge(proyecto).label }}
              </span>
              <p v-if="proyecto" class="font-black text-[#1F2937] text-sm truncate">{{ proyecto.titulo }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button v-if="proyecto"
                      @click="descargarPDF(proyecto)"
                      class="px-3 py-1.5 rounded-xl bg-[#00A859] text-[10px] font-black uppercase tracking-widest
                             text-white shadow-sm hover:bg-[#009048] transition-all flex items-center gap-1.5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/>
                </svg>
                PDF
              </button>
              <button v-if="proyecto" @click="irAPaginaCompleta"
                      class="px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200
                             text-[10px] font-black uppercase tracking-widest text-gray-500
                             hover:border-[#00A859] hover:text-[#5a7a00] transition-all flex items-center gap-1.5">
                Página completa
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
              </button>
              <button @click="cerrar"
                      class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center
                             text-gray-400 hover:bg-gray-200 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Contenido -->
          <div class="flex-1 overflow-y-auto p-4 md:p-8">

            <!-- Spinner -->
            <div v-if="cargando" class="flex flex-col items-center justify-center py-24">
              <svg class="animate-spin w-10 h-10 text-[#00A859] mb-3" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
              <p class="text-[#00A859] font-black tracking-widest uppercase text-xs">Cargando...</p>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="text-center py-24 text-gray-400">
              <p class="text-sm">No se pudo cargar el proyecto.</p>
            </div>

            <template v-else-if="proyecto">

              <!-- ══ HOJA DE CUADERNO ═══════════════════════════════════════ -->
              <div class="notebook-page">
                <div class="notebook-margin" aria-hidden="true" />
                <div class="notebook-holes" aria-hidden="true">
                  <div class="notebook-hole" /><div class="notebook-hole" /><div class="notebook-hole" />
                  <div class="notebook-hole" /><div class="notebook-hole" />
                </div>

                <h1 class="text-xl md:text-2xl font-black tracking-tight text-[#121212] mb-5 leading-tight">
                  {{ proyecto.titulo }}
                </h1>

                <!-- Meta-band -->
                <div v-if="proyecto.empresa_nombre || proyecto.centro_nombre || proyecto.ciclo_nombre" class="meta-band">
                  <div v-if="proyecto.empresa_nombre" class="meta-cell">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center shrink-0">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-amber-600" style="width:1.1rem;height:1.1rem">
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
                    <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center shrink-0">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-blue-500" style="width:1.1rem;height:1.1rem">
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
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center shrink-0">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-[#00A859]" style="width:1.1rem;height:1.1rem">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                      </svg>
                    </div>
                    <div>
                      <p class="meta-label">Ciclo formativo</p>
                      <p class="meta-value">{{ proyecto.ciclo_nombre }}</p>
                    </div>
                  </div>
                </div>

                <!-- Panel de estado (solo lectura) -->
                <div v-if="proyecto.estado === 'propuesta' || proyecto.estado === 'validado'" class="mb-6 space-y-3">
                  <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Enlace de validación empresa</p>
                    <div class="flex items-center gap-2">
                      <p class="flex-1 text-xs text-gray-400 truncate font-mono bg-gray-50 border border-gray-100 rounded-xl px-3 py-2 min-w-0">{{ landingUrl }}</p>
                      <button @click="copiarUrl"
                              :class="['shrink-0 px-3 py-2 rounded-xl text-xs font-bold border transition-all',
                                        urlCopiada ? 'bg-[#00A859]/10 text-[#00A859] border-[#00A859]/20' : 'bg-white text-gray-500 border-gray-200 hover:border-[#00A859] hover:text-[#00A859]']">
                        {{ urlCopiada ? '¡Copiado!' : 'Copiar' }}
                      </button>
                    </div>
                  </div>

                  <div v-if="proyecto.empresa_validado"
                       class="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-[#00A859]/8 border border-[#00A859]/25 shadow-sm">
                    <div class="w-9 h-9 rounded-xl bg-[#00A859]/15 border border-[#00A859]/25 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                    </div>
                    <div>
                      <p class="text-sm font-black text-[#00A859]">La empresa ha validado el proyecto</p>
                      <p class="text-[10px] text-[#00A859]/70 mt-0.5">
                        {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre }} respondió con validación positiva.
                      </p>
                    </div>
                  </div>
                  <div v-else-if="proyecto.empresa_no_valida_aun"
                       class="flex items-start gap-3 px-4 py-3.5 rounded-2xl bg-red-50 border border-red-300 shadow-sm">
                    <div class="w-9 h-9 rounded-xl bg-red-100 border border-red-200 flex items-center justify-center shrink-0 mt-0.5">
                      <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </div>
                    <div>
                      <p class="text-sm font-black text-red-700">La empresa contestó: "No validar aún"</p>
                      <p class="text-[10px] text-red-500 mt-0.5">
                        {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre }} revisó el proyecto pero indicó que aún no puede validarlo.
                      </p>
                    </div>
                  </div>
                  <div v-else-if="proyecto.enviado_a_empresa_mail"
                       class="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-blue-50 border border-blue-200 shadow-sm">
                    <div class="w-9 h-9 rounded-xl bg-blue-100 border border-blue-200 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-black text-blue-700">Enlace enviado a la empresa · Pendiente de respuesta</p>
                      <p class="text-[10px] text-blue-500 mt-0.5 truncate">
                        Esperando que {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre || 'la empresa' }} acceda y responda.
                      </p>
                    </div>
                  </div>
                  <div v-else class="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-violet-50 border border-violet-300 shadow-sm">
                    <div class="w-9 h-9 rounded-xl bg-white border border-violet-200 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-black text-violet-700">Propuesta pendiente de enviar a empresa</p>
                      <p class="text-[10px] text-gray-400 mt-0.5">
                        El enlace está generado pero aún no se ha confirmado el envío a
                        {{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre || 'la empresa' }}.
                      </p>
                    </div>
                  </div>

                  <div v-if="proyecto.docente_validado"
                       class="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 shadow-sm">
                    <div class="w-9 h-9 rounded-xl bg-emerald-100 border border-emerald-200 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-black text-emerald-700">Validado por docente</p>
                      <p class="text-[10px] text-emerald-600/70 mt-0.5">Validación pedagógica aprobada por el docente responsable.</p>
                    </div>
                  </div>
                  <div v-else class="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-gray-50 border border-gray-100 shadow-sm">
                    <div class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-black text-gray-500">Pendiente de validación docente</p>
                      <p class="text-[10px] text-gray-400 mt-0.5">Gestiona el envío y la validación desde la página completa del proyecto.</p>
                    </div>
                  </div>
                </div>

                <!-- ═══ Empresa ═══ -->
                <p class="group-header">Empresa</p>
                <div class="grid gap-4 sm:grid-cols-2 mb-6">
                  <div v-if="proyecto.datos_empresa?.nombre" class="card-section">
                    <p class="section-label">Empresa</p>
                    <p class="text-sm font-bold text-[#1F2937] mb-1">{{ proyecto.datos_empresa.nombre }}</p>
                    <div class="space-y-0.5 text-xs text-gray-400">
                      <p v-if="proyecto.datos_empresa.sector">{{ proyecto.datos_empresa.sector }}</p>
                      <p v-if="proyecto.datos_empresa.persona_contacto">{{ proyecto.datos_empresa.persona_contacto }}</p>
                      <p v-if="proyecto.datos_empresa.email">{{ proyecto.datos_empresa.email }}</p>
                      <p v-if="proyecto.datos_empresa.descripcion" class="mt-2 leading-relaxed text-gray-500">{{ proyecto.datos_empresa.descripcion }}</p>
                    </div>
                  </div>

                  <div v-if="proyecto.datos_centro?.docente_nombre" class="card-section">
                    <p class="section-label">Docente responsable</p>
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-[#00A859]/10 border border-[#00A859]/20 flex items-center justify-center shrink-0 text-[#00A859] font-black text-sm">
                        {{ proyecto.datos_centro.docente_nombre.charAt(0).toUpperCase() }}
                      </div>
                      <div>
                        <p class="text-sm font-bold text-[#1F2937]">{{ proyecto.datos_centro.docente_nombre }}</p>
                        <a v-if="proyecto.datos_centro.docente_email" :href="`mailto:${proyecto.datos_centro.docente_email}`" class="text-xs text-[#00A859] hover:underline">
                          {{ proyecto.datos_centro.docente_email }}
                        </a>
                      </div>
                    </div>
                    <div v-if="proyecto.datos_centro.nombre" class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-400">
                      {{ proyecto.datos_centro.nombre }}<span v-if="proyecto.datos_centro.municipio"> · {{ proyecto.datos_centro.municipio }}</span>
                    </div>
                  </div>

                  <div v-if="proyecto.equipo?.alumnos?.length" class="card-section sm:col-span-2">
                    <p class="section-label">Equipo ({{ proyecto.equipo.alumnos.length }} personas)</p>
                    <div class="flex flex-wrap gap-1.5">
                      <span v-for="a in proyecto.equipo.alumnos" :key="a.nombre"
                            class="text-xs bg-gray-50 border border-gray-200 px-2.5 py-1 rounded-full text-gray-600">
                        {{ a.nombre }}<span v-if="a.rol" class="text-gray-400"> · {{ a.rol }}</span>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- ═══ Currículo ═══ -->
                <p v-if="proyecto.modulos_seleccionados?.length || raCeBlocks.length" class="group-header">Currículo</p>
                <div v-if="proyecto.modulos_seleccionados?.length || raCeBlocks.length" class="grid gap-4 sm:grid-cols-2 mb-6">
                  <div v-if="proyecto.modulos_seleccionados?.length" class="card-section sm:col-span-2">
                    <p class="section-label">Módulos ({{ proyecto.modulos_seleccionados.length }})</p>
                    <div class="flex flex-wrap gap-1.5">
                      <span v-for="m in proyecto.modulos_seleccionados" :key="m.id"
                            class="text-xs bg-[#00A859]/8 border border-[#00A859]/15 text-[#00A859] px-2.5 py-1 rounded-full">
                        {{ m.nombre }}
                      </span>
                    </div>
                  </div>

                  <div v-if="raCeBlocks.length" class="card-section sm:col-span-2">
                    <button @click="raCeAbierto = !raCeAbierto" type="button" class="w-full flex items-center justify-between text-left">
                      <p class="section-label">Resultados de Aprendizaje y Criterios de Evaluación</p>
                      <svg :class="['w-4 h-4 text-gray-400 transition-transform duration-200 shrink-0', raCeAbierto ? 'rotate-180' : '']"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                      </svg>
                    </button>
                    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1"
                                leave-active-class="transition-all duration-150 ease-in" leave-to-class="opacity-0 -translate-y-1">
                      <div v-if="raCeAbierto" class="pt-3 space-y-4">
                        <div v-for="block in raCeBlocks" :key="block.modulo" class="border border-gray-100 rounded-xl p-3.5">
                          <p class="text-[10px] font-black uppercase tracking-widest text-[#00A859] mb-2">{{ block.modulo }}</p>
                          <div v-for="(item, i) in block.items" :key="i" :class="{ 'mt-3 pt-3 border-t border-gray-50': i > 0 }">
                            <p class="text-sm font-semibold text-[#1F2937] mb-1.5">{{ item.ra }}</p>
                            <ul v-if="item.ce?.length" class="space-y-1 pl-1">
                              <li v-for="(ce, j) in item.ce" :key="j" class="flex items-start gap-2 text-xs text-gray-500">
                                <span class="text-amber-400 shrink-0 font-bold mt-0.5">•</span>{{ ce }}
                              </li>
                            </ul>
                            <p v-if="item.aplicacion" class="text-xs text-gray-500 italic mt-2">
                              <span class="font-bold not-italic text-[#1F2937]">Aplicación: </span>{{ item.aplicacion }}
                            </p>
                          </div>
                        </div>
                      </div>
                    </Transition>
                  </div>
                </div>

                <!-- ═══ El reto ═══ -->
                <p v-if="proyecto.diseno_reto?.descripcion || proyecto.fundamentacion?.contexto" class="group-header">El reto</p>
                <div v-if="proyecto.diseno_reto?.descripcion || proyecto.fundamentacion?.contexto" class="grid gap-4 sm:grid-cols-2 mb-6">
                  <div v-if="proyecto.fundamentacion?.contexto || proyecto.fundamentacion?.justificacion || proyecto.fundamentacion?.innovacion"
                       class="card-section sm:col-span-2">
                    <p class="section-label">Fundamentación</p>
                    <div v-if="proyecto.fundamentacion.contexto" class="mb-3">
                      <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Contexto de partida</p>
                      <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.fundamentacion.contexto }}</p>
                    </div>
                    <div v-if="proyecto.fundamentacion.justificacion" class="mb-3">
                      <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Justificación pedagógica</p>
                      <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.fundamentacion.justificacion }}</p>
                    </div>
                    <div v-if="proyecto.fundamentacion.innovacion">
                      <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Elemento innovador</p>
                      <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.fundamentacion.innovacion }}</p>
                    </div>
                  </div>

                  <div v-if="proyecto.diseno_reto?.descripcion" class="card-section sm:col-span-2">
                    <p class="section-label">Diseño del reto</p>
                    <p v-if="proyecto.diseno_reto.pregunta_reto" class="text-sm font-bold text-[#00A859] mb-2 italic">
                      "{{ proyecto.diseno_reto.pregunta_reto }}"
                    </p>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.diseno_reto.descripcion }}</p>
                    <div v-if="proyecto.diseno_reto.restricciones" class="mt-3 pt-3 border-t border-gray-100">
                      <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Restricciones</p>
                      <p class="text-xs text-gray-500">{{ proyecto.diseno_reto.restricciones }}</p>
                    </div>
                    <div v-if="proyecto.diseno_reto.entregables" class="mt-3 pt-3 border-t border-gray-100">
                      <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Entregables</p>
                      <p class="text-xs text-gray-500">{{ proyecto.diseno_reto.entregables }}</p>
                    </div>
                  </div>
                </div>

                <!-- ═══ Propuesta ═══ -->
                <p v-if="proyecto.diseno_microproyecto?.fases?.length || proyecto.diseno_microproyecto?.metodologia" class="group-header">Propuesta</p>
                <div v-if="proyecto.diseno_microproyecto?.fases?.length || proyecto.diseno_microproyecto?.metodologia" class="grid gap-4 sm:grid-cols-2 mb-6">
                  <div v-if="proyecto.diseno_microproyecto?.fases?.length" class="card-section">
                    <p class="section-label">Fases del proyecto</p>
                    <ol class="space-y-2.5">
                      <li v-for="(f, i) in proyecto.diseno_microproyecto.fases" :key="i" class="flex items-start gap-2.5 text-sm">
                        <span class="w-5 h-5 rounded-full bg-[#00A859]/10 text-[#00A859] font-black text-[10px] flex items-center justify-center shrink-0 mt-0.5">{{ i + 1 }}</span>
                        <div>
                          <p class="font-bold text-[#1F2937]">{{ f.nombre }}
                            <span v-if="duracionPorFase(proyecto.diseno_microproyecto.clases, i)" class="text-gray-400 font-normal text-xs">
                              · {{ duracionPorFase(proyecto.diseno_microproyecto.clases, i) }} sesión(es)
                            </span>
                          </p>
                          <p v-if="f.descripcion" class="text-gray-400 text-xs mt-0.5">{{ f.descripcion }}</p>
                        </div>
                      </li>
                    </ol>
                  </div>

                  <div v-if="proyecto.diseno_microproyecto?.clases?.length" class="card-section">
                    <p class="section-label">Calendario de sesiones ({{ proyecto.diseno_microproyecto.clases.length }})</p>
                    <ol class="space-y-1.5">
                      <li v-for="(c, i) in proyecto.diseno_microproyecto.clases" :key="i" class="text-sm text-gray-600">
                        <span class="font-bold text-[#1F2937]">Sesión {{ i + 1 }}:</span>
                        {{ (c.fases || []).map(n => proyecto.diseno_microproyecto.fases?.[n]?.nombre).filter(Boolean).join(' + ') || 'Sin fase asignada' }}
                      </li>
                    </ol>
                  </div>

                  <div v-if="proyecto.diseno_microproyecto?.clases?.length" class="card-section sm:col-span-2">
                    <p class="section-label">Cronograma — hitos por fase</p>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-2">
                      <div v-for="f in FASES_PROYECTO" :key="f.num" class="rounded-2xl border p-3" :class="COLOR_MAP_FASES[f.color]">
                        <div class="flex items-center gap-1.5 mb-1">
                          <span class="text-base leading-none">{{ f.icono }}</span>
                          <p class="font-black text-xs text-[#1F2937]">{{ f.label }}</p>
                        </div>
                        <p class="text-[9px] font-bold uppercase tracking-wide opacity-70">
                          {{ duracionPorFase(proyecto.diseno_microproyecto.clases, f.num) }} sesión(es)
                        </p>
                        <p class="text-xs text-gray-600 leading-snug mt-1">🎯 {{ f.desc }}</p>
                      </div>
                    </div>
                  </div>

                  <div v-if="proyecto.diseno_microproyecto?.metodologia" class="card-section sm:col-span-2">
                    <p class="section-label">Metodología</p>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.diseno_microproyecto.metodologia }}</p>
                  </div>
                </div>

                <!-- ═══ Objetivos ═══ -->
                <p v-if="proyecto.objetivos?.lista?.length || proyecto.kpis?.lista?.length" class="group-header">Objetivos</p>
                <div v-if="proyecto.objetivos?.lista?.length || proyecto.kpis?.lista?.length" class="grid gap-4 sm:grid-cols-2 mb-6">
                  <div v-if="proyecto.objetivos?.lista?.length" class="card-section">
                    <p class="section-label">Objetivos</p>
                    <ul class="space-y-1.5">
                      <li v-for="obj in proyecto.objetivos.lista" :key="obj" class="flex items-start gap-2 text-sm text-gray-600">
                        <span class="text-[#00A859] shrink-0 mt-0.5 font-bold">›</span> {{ obj }}
                      </li>
                    </ul>
                  </div>
                  <div v-if="proyecto.kpis?.lista?.length" class="card-section">
                    <p class="section-label">KPIs ({{ proyecto.kpis.lista.length }})</p>
                    <ul class="space-y-1.5 pt-1">
                      <li v-for="kpi in proyecto.kpis.lista" :key="kpi" class="flex items-start gap-2 text-sm text-gray-600">
                        <span class="text-[#00A859] shrink-0 mt-0.5">✓</span> {{ kpi }}
                      </li>
                    </ul>
                  </div>
                </div>

                <!-- ═══ Publicar ═══ -->
                <p v-if="proyecto.resumen?.texto" class="group-header">Publicar</p>
                <div class="grid gap-4 sm:grid-cols-2">
                  <div v-if="proyecto.resumen?.texto" class="card-section sm:col-span-2">
                    <p class="section-label">Resumen ejecutivo</p>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.resumen.texto }}</p>
                  </div>

                  <div v-if="videos.length || documentos.length" class="sm:col-span-2">
                    <button @click="recursosAbierto = !recursosAbierto"
                            class="w-full flex items-center justify-between px-5 py-4 bg-white border border-gray-100
                                   rounded-[1.5rem] shadow-sm hover:border-[#00A859]/30 transition-all">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20 flex items-center justify-center shrink-0">
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
                    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1"
                                leave-active-class="transition-all duration-150 ease-in" leave-to-class="opacity-0 -translate-y-1">
                      <div v-if="recursosAbierto" class="mt-2 bg-white border border-gray-100 rounded-[1.5rem] shadow-sm p-5 space-y-5">
                        <div v-if="videos.length">
                          <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-3">Vídeos</p>
                          <div class="space-y-2">
                            <div v-for="(v, i) in videos" :key="i"
                                 class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-100
                                        hover:border-blue-200 hover:bg-blue-50/40 transition-colors group/vid">
                              <button @click="abrirRecurso(v)" class="w-7 h-7 rounded-lg bg-blue-50 shrink-0 flex items-center justify-center group-hover/vid:bg-blue-100 transition-colors">
                                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                                </svg>
                              </button>
                              <button @click="abrirRecurso(v)" class="flex-1 min-w-0 text-left">
                                <p class="text-xs font-bold text-gray-700 truncate group-hover/vid:text-blue-600 transition-colors">{{ v.label || v.filename }}</p>
                                <p class="text-[9px] text-blue-400/80 truncate">Cloudinary · {{ v.filename }}</p>
                              </button>
                            </div>
                          </div>
                        </div>
                        <div v-if="documentos.length">
                          <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-3">Documentos, imágenes, etc...</p>
                          <div class="space-y-2">
                            <div v-for="(d, i) in documentos" :key="i"
                                 class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-100
                                        hover:border-[#00A859]/30 hover:bg-[#00A859]/5 transition-colors group/doc">
                              <button @click="abrirRecurso(d)" class="w-7 h-7 rounded-lg bg-[#00A859]/10 shrink-0 flex items-center justify-center group-hover/doc:bg-[#00A859]/20 transition-colors">
                                <svg class="w-3.5 h-3.5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                              </button>
                              <button @click="abrirRecurso(d)" class="flex-1 min-w-0 text-left">
                                <p class="text-xs font-bold text-gray-700 truncate group-hover/doc:text-[#00A859] transition-colors">{{ d.label || d.filename }}</p>
                                <p class="text-[9px] text-blue-400/80 truncate">Cloudinary · {{ d.filename }}</p>
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </Transition>
                  </div>
                </div>

                <!-- ══ FEEDBACK DE LA EMPRESA ══ -->
                <div v-if="proyecto.validacion_empresa?.respuestas" class="mt-6">
                  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                           :class="proyecto.empresa_validado ? 'bg-[#00A859]/10 border border-[#00A859]/20' : 'bg-red-50 border border-red-200'">
                        <svg class="w-5 h-5" :class="proyecto.empresa_validado ? 'text-[#00A859]' : 'text-red-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path v-if="proyecto.empresa_validado" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                      </div>
                      <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Feedback de la empresa</p>
                        <p class="text-sm font-black text-[#121212]">{{ proyecto.empresa_nombre || proyecto.datos_empresa?.nombre || 'Empresa' }}</p>
                      </div>
                    </div>
                    <span v-if="proyecto.empresa_validado"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-[10px] font-black uppercase tracking-widest
                                 bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                      Decisión: Validó la propuesta
                    </span>
                    <span v-else-if="proyecto.empresa_no_valida_aun"
                          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-[10px] font-black uppercase tracking-widest
                                 bg-red-50 border-red-300 text-red-700">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      Decisión: No validar aún
                    </span>
                  </div>

                  <div class="bg-white border rounded-[1.5rem] shadow-sm overflow-hidden"
                       :class="proyecto.empresa_validado ? 'border-[#00A859]/20' : proyecto.empresa_no_valida_aun ? 'border-red-200' : 'border-gray-100'">
                    <div class="grid sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                      <div v-for="(val, key) in proyecto.validacion_empresa.respuestas" :key="key" class="px-5 py-4 flex items-start gap-4">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                             :class="val === 'Sí' ? 'bg-[#00A859]/10 border border-[#00A859]/20' : val === 'No' ? 'bg-red-50 border border-red-200' : 'bg-amber-50 border border-amber-200'">
                          <svg class="w-4 h-4" :class="val === 'Sí' ? 'text-[#00A859]' : val === 'No' ? 'text-red-500' : 'text-amber-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path v-if="val === 'Sí'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            <path v-else-if="val === 'No'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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
                          <p class="text-base font-black" :class="val === 'Sí' ? 'text-[#00A859]' : val === 'No' ? 'text-red-500' : 'text-amber-600'">{{ val }}</p>
                        </div>
                      </div>
                    </div>
                    <div v-if="proyecto.validacion_empresa.comentarios" class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-start gap-3">
                      <svg class="w-4 h-4 text-gray-300 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                      </svg>
                      <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Comentarios adicionales</p>
                        <p class="text-sm text-gray-600 leading-relaxed italic">"{{ proyecto.validacion_empresa.comentarios }}"</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="!proyecto.datos_empresa?.nombre && !proyecto.equipo?.alumnos?.length && !raCeBlocks.length
                            && !proyecto.diseno_reto?.descripcion && !proyecto.objetivos?.lista?.length"
                     class="text-center py-6 text-gray-400">
                  <p class="text-xs">Este proyecto aún no tiene secciones rellenas.</p>
                </div>

              </div><!-- /notebook-page -->
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- ══ MODAL VISOR DE RECURSOS (sobre el modal de ficha) ══ -->
  <Teleport to="body">
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0"
                leave-active-class="transition-all duration-150 ease-in" leave-to-class="opacity-0">
      <div v-if="modalRecurso" class="fixed inset-0 z-[90] flex items-center justify-center p-4 bg-gray-950/90"
           @click.self="modalRecurso = null">
        <div class="relative max-w-4xl w-full max-h-[85vh] flex flex-col">
          <div class="flex items-center justify-between mb-3">
            <p class="text-white text-sm font-bold truncate">{{ modalRecurso.label }}</p>
            <button @click="modalRecurso = null" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white hover:bg-white/20 transition-all shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <video v-if="modalRecurso.tipo === 'video'" :src="modalRecurso.url" controls autoplay class="w-full max-h-[75vh] rounded-xl bg-black" />
          <img v-else-if="modalRecurso.tipo === 'imagen'" :src="modalRecurso.url" class="w-full max-h-[75vh] object-contain rounded-xl" />
          <iframe v-else-if="modalRecurso.tipo === 'pdf'" :src="modalRecurso.url" class="w-full h-[75vh] rounded-xl bg-white" />
          <div v-else class="bg-white rounded-xl p-8 text-center">
            <a :href="modalRecurso.url" target="_blank" rel="noopener" class="text-[#00A859] font-bold text-sm hover:underline">Abrir en nueva pestaña →</a>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
@reference "../style.css";

.card-section {
  @apply bg-white border border-gray-200 rounded-2xl shadow-sm p-5 space-y-3;
}
.section-label {
  @apply text-[10px] font-black uppercase tracking-[0.2em] text-gray-400;
}
.group-header {
  @apply text-xs font-black uppercase tracking-[0.25em] text-[#00A859] mb-3 pl-3 border-l-4 border-[#00A859]/40;
}

.notebook-page {
  position: relative;
  overflow: hidden;
  background-color: #fefef8;
  background-image:
    linear-gradient(90deg, #eef1f5 0, #eef1f5 3.75rem, transparent 3.75rem),
    repeating-linear-gradient(transparent, transparent 31px, #c8d9f0 31px, #c8d9f0 32px);
  padding: 2rem 1.75rem 2.5rem 5.5rem;
  border-radius: 0.75rem;
  border: 1px solid #dde3ed;
  box-shadow: 0 4px 24px -4px rgba(0, 0, 0, 0.08), 0 1px 4px rgba(0, 0, 0, 0.04), 2px 0 0 0 #d1dae8 inset;
}

.notebook-margin {
  position: absolute;
  top: 0; bottom: 0; left: 4.3rem;
  width: 1.5px;
  background: #fca5a5;
  pointer-events: none;
}

.notebook-holes {
  position: absolute;
  left: 1.25rem; top: 0; bottom: 0;
  display: flex; flex-direction: column; justify-content: space-around;
  padding: 1.75rem 0;
  pointer-events: none;
  z-index: 2;
}

.notebook-hole {
  width: 1.1rem; height: 1.1rem;
  border-radius: 50%;
  background: white;
  border: 1.5px solid #c4cdd9;
  box-shadow: inset 0 1px 3px #b8c2cc;
}

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
.meta-cell:last-child { border-right: none; }

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
  .notebook-page { padding: 1.5rem 1rem 2rem 4.5rem; }
  .meta-cell { flex: 1 0 100%; border-right: none; border-bottom: 1px solid #dde3ed; }
  .meta-cell:last-child { border-bottom: none; }
}

.proyecto-ficha-modal-enter-active,
.proyecto-ficha-modal-leave-active { transition: opacity 200ms ease; }
.proyecto-ficha-modal-enter-active .relative,
.proyecto-ficha-modal-leave-active .relative { transition: transform 200ms ease, opacity 200ms ease; }
.proyecto-ficha-modal-enter-from,
.proyecto-ficha-modal-leave-to { opacity: 0; }
.proyecto-ficha-modal-enter-from .relative { transform: scale(0.97) translateY(12px); opacity: 0; }
.proyecto-ficha-modal-leave-to .relative { transform: scale(0.98); opacity: 0; }
</style>
