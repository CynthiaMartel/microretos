<script setup>
import { ref, computed, watch } from 'vue'
import api from '../api.js'
import { usePdfExport } from '../composables/usePdfExport.js'

const props = defineProps({
  microretoId: { type: [String, Number], default: null },
  // Acceso público alternativo (workspace de alumnado): ficha del reto de un equipo por su token,
  // sin necesitar sesión Sanctum. Tiene prioridad sobre microretoId si ambos llegan informados.
  token: { type: String, default: null },
})
const emit = defineEmits(['close'])

const abierto  = computed(() => Boolean(props.microretoId || props.token))
const reto     = ref(null)
const cargando = ref(false)
const error    = ref(false)

const { descargarPDF } = usePdfExport()

// Carga cuando cambia el ID o el token
watch([() => props.microretoId, () => props.token], async ([id, token]) => {
  if (!id && !token) return
  reto.value    = null
  error.value   = false
  cargando.value = true
  try {
    const url  = token ? `/equipo/${token}/reto` : `/microretos/${id}`
    const res  = await api.get(url)
    reto.value = res.data
  } catch (e) {
    console.error('Error cargando microreto en modal:', e)
    error.value = true
  } finally {
    cargando.value = false
  }
}, { immediate: true })

const imagenFondo = computed(() => {
  if (!reto.value?.familia) return null
  const slug = reto.value.familia
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
  const base = import.meta.env.VITE_API_URL.replace(/\/api$/, '')
  return `${base}/familias/${slug}.webp`
})

function cerrar() {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="microreto-modal">
      <div v-if="abierto"
           class="fixed inset-0 z-[60] flex items-start justify-center p-4 overflow-y-auto">

        <!-- Backdrop -->
        <div @click="cerrar"
             class="fixed inset-0 bg-black/50 backdrop-blur-sm" />

        <!-- Panel scrollable -->
        <div class="relative z-10 w-full max-w-4xl my-6 bg-[#F8FAFC] rounded-[2rem]
                    shadow-2xl border border-gray-200 overflow-hidden font-sans text-[#1F2937]">

          <!-- ── Barra superior fija con botón cerrar ── -->
          <div class="sticky top-0 z-20 bg-white/95 backdrop-blur-md border-b border-gray-100
                      px-6 py-3 flex items-center justify-between gap-4">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">
              Ficha del reto
            </p>
            <div class="flex items-center gap-2">
              <button v-if="reto" @click="descargarPDF(reto)"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                             bg-gray-50 border border-gray-200 text-[10px] font-black uppercase
                             tracking-widest text-gray-500 hover:border-[#00A859] hover:text-[#00A859]
                             transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                PDF
              </button>
              <button @click="cerrar"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                             bg-[#1F2937] text-white text-[10px] font-black uppercase tracking-widest
                             hover:bg-black transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Cerrar
              </button>
            </div>
          </div>

          <!-- ── Cargando ── -->
          <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
            <svg class="animate-spin w-10 h-10 text-[#00A859] mb-4" viewBox="0 0 24 24">
              <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
            </svg>
            <p class="text-[#00A859] font-black tracking-widest uppercase text-sm animate-pulse">
              Cargando ficha...
            </p>
          </div>

          <!-- ── Error ── -->
          <div v-else-if="error" class="text-center py-24">
            <div class="w-14 h-14 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3
                         L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </div>
            <p class="font-black text-lg text-[#1F2937] mb-1">No se pudo cargar el reto</p>
            <p class="text-sm text-gray-400">Comprueba tu conexión e inténtalo de nuevo.</p>
          </div>

          <!-- ── Ficha ── -->
          <template v-else-if="reto">

            <!-- BLOQUE PRINCIPAL -->
            <div class="bg-white border-b border-gray-100 shadow-sm">

              <!-- Cabecera con imagen de fondo -->
              <div class="relative bg-gray-50 overflow-hidden">
                <div class="absolute inset-0 z-0 pointer-events-none">
                  <div class="absolute inset-0 bg-gradient-to-r from-gray-50 via-gray-50/95 to-transparent z-10"/>
                  <div class="absolute inset-0 bg-gradient-to-t from-gray-50 via-transparent to-transparent z-10"/>
                  <img v-if="imagenFondo" :src="imagenFondo" alt=""
                       class="w-full h-full object-cover object-right opacity-30 mix-blend-multiply"/>
                </div>

                <div class="relative z-10 px-6 py-8 md:px-12 md:pt-10 md:pb-8 max-w-3xl">
                  <p class="text-[#00A859] font-bold text-[10px] tracking-[0.2em] uppercase mb-3
                            flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                               a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    DuaLab · Ficha de Reto
                  </p>
                  <h1 class="text-2xl md:text-4xl font-black text-[#1F2937] tracking-tight leading-tight mb-2">
                    {{ reto.titulo }}
                  </h1>
                  <h2 class="text-sm md:text-base text-gray-500 font-medium leading-relaxed mb-6">
                    {{ reto.subtitulo }}
                  </h2>

                  <div class="flex flex-wrap gap-2">
                    <span class="flex items-center gap-1.5 px-3 py-1.5 bg-[#1F2937] text-white
                                 rounded-lg text-[10px] font-bold uppercase tracking-wider shadow-sm">
                      <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5
                                 m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0
                                 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                      </svg>
                      {{ reto.empresa_nombre }}
                    </span>
                    <span v-if="reto.familia"
                          class="flex items-center gap-1.5 px-3 py-1.5 bg-white/80 border border-gray-200
                                 text-[#1F2937] rounded-lg text-[10px] font-bold uppercase tracking-wider">
                      {{ reto.familia }}
                    </span>
                    <span v-if="reto.ciclo"
                          class="flex items-center gap-1.5 px-3 py-1.5 bg-[#00A859]/10 border
                                 border-[#00A859]/20 text-[#00A859] rounded-lg text-[10px] font-bold
                                 uppercase tracking-wider">
                      {{ reto.ciclo }}
                    </span>
                    <span v-if="reto.curso"
                          class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 border border-indigo-200
                                 text-indigo-700 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                      {{ reto.curso }}º Curso
                    </span>
                    <span v-if="reto.nivel_grupo"
                          class="flex items-center gap-1.5 px-3 py-1.5 bg-white/80 border border-gray-200
                                 text-gray-500 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                      Nivel {{ reto.nivel_grupo }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Cuerpo de la ficha -->
              <div class="px-6 py-8 md:px-12 md:py-10 space-y-10">

                <!-- Quién es / Día a día -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">
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
                            p-6 rounded-r-2xl shadow-sm border-y border-r border-gray-100">
                  <h3 class="text-[#00A859] font-black uppercase text-[10px] tracking-[0.2em] mb-3">
                    Pregunta del Reto
                  </h3>
                  <p class="text-lg md:text-xl font-bold text-[#1F2937] leading-snug">
                    {{ reto.pregunta_reto }}
                  </p>
                </div>

                <!-- Qué necesitan / Limitaciones -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">
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

                <!-- Prototipos / ODS -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">
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

                <!-- RA / CE -->
                <div v-if="reto.evaluacion_oficial?.length" class="pt-2">
                  <h3 class="flex items-center gap-2 text-[#1F2937] font-bold uppercase text-xs
                             tracking-widest border-b-2 border-gray-200 pb-2 mb-6">
                    <svg class="w-5 h-5 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0
                               0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0
                               01.665-6.479L12 14z"/>
                    </svg>
                    RA/CE Seleccionados
                  </h3>
                  <div class="space-y-4">
                    <div v-for="evalObj in reto.evaluacion_oficial" :key="evalObj.modulo"
                         class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
                      <p class="text-[10px] uppercase font-bold text-gray-400 mb-0.5">Módulo</p>
                      <p class="font-black text-[#1F2937] text-base mb-4">{{ evalObj.modulo }}</p>
                      <div class="mb-4">
                        <p class="text-[10px] uppercase font-bold text-[#00A859] mb-1">Resultado de Aprendizaje</p>
                        <p class="text-sm font-semibold text-gray-700 bg-gray-50 p-3 rounded-xl border border-gray-100">
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
                      <div v-if="evalObj.aplicacion" class="mt-3 pt-3 border-t border-gray-100">
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
                        class="text-sm text-gray-700 bg-gray-50 border border-gray-200 p-4 rounded-xl">
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
                 class="relative mx-0 bg-gray-50 border-t border-gray-200
                        px-6 py-8 md:px-12 md:py-10">
              <div class="absolute top-0 right-0 bg-white border-b border-l border-gray-200
                          text-gray-400 px-4 py-1.5 font-black text-[9px] tracking-widest uppercase
                          rounded-bl-2xl">
                Uso Exclusivo Docente
              </div>
              <h2 class="text-lg md:text-xl font-black text-[#1F2937] mb-1 mt-2 flex items-center gap-3">
                <svg class="w-5 h-5 text-yellow-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0
                           110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                Guía de Implementación
              </h2>
              <p class="text-gray-500 text-sm mb-6">
                Recomendaciones pedagógicas para dinamizar el reto.
              </p>
              <div class="grid grid-cols-1 gap-4">
                <div v-for="(tip, i) in reto.tips_profesorado" :key="i"
                     class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                  <div class="text-sm text-gray-700 leading-relaxed">
                    <template v-if="tip.includes(':')">
                      <strong class="text-[#00A859] flex items-center gap-1.5 mb-2
                                     uppercase tracking-wider text-[10px]">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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

            <!-- Pie del modal -->
            <div class="px-6 py-5 md:px-12 bg-white border-t border-gray-100
                        flex items-center justify-end">
              <button @click="cerrar"
                      class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl
                             bg-[#1F2937] text-white text-xs font-black uppercase tracking-widest
                             hover:bg-black transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Cerrar y volver
              </button>
            </div>

          </template>
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

.microreto-modal-enter-active,
.microreto-modal-leave-active {
  transition: opacity 250ms ease;
}
.microreto-modal-enter-active .relative,
.microreto-modal-leave-active .relative {
  transition: transform 250ms ease, opacity 250ms ease;
}
.microreto-modal-enter-from,
.microreto-modal-leave-to {
  opacity: 0;
}
.microreto-modal-enter-from .relative {
  transform: scale(0.97) translateY(12px);
  opacity: 0;
}
.microreto-modal-leave-to .relative {
  transform: scale(0.98);
  opacity: 0;
}
</style>
