<!-- Modal "Ver diagnóstico" de un equipo — se abre una vez generado el diagnóstico
     final IA (equipo.diagnostico_final). Sigue el mismo esqueleto que ProyectoFichaModal.vue
     (Teleport + Transition + overlay + header sticky con botón PDF), pero recibe el equipo
     ya cargado como prop en vez de volver a pedirlo a la API — el padre (MisGruposDetalle.vue)
     ya lo tiene en memoria. z-[55]: por debajo de MicroretoModal (z-60) y ProyectoFichaModal
     (z-80), para que las fichas adjuntas que se abren desde aquí queden siempre por encima. -->
<script setup>
import { ref, computed } from 'vue'
import MicroretoModal from './MicroretoModal.vue'
import ProyectoFichaModal from './ProyectoFichaModal.vue'
import { useDiagnosticoPdfExport } from '../composables/useDiagnosticoPdfExport.js'
import { FASES_PROYECTO } from '../config/fasesProyecto.js'
import { formatCurso } from '../utils/formatCurso.js'

const props = defineProps({
  equipo:    { type: Object, default: null },
  encuentro: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const abierto = computed(() => Boolean(props.equipo))

const microretoModalId  = ref(null)
const proyectoModalUuid = ref(null)

const { descargarPDF } = useDiagnosticoPdfExport()

function cerrar() {
  emit('close')
}

function descargar() {
  if (props.equipo) descargarPDF({ equipo: props.equipo, encuentro: props.encuentro })
}

const NIVEL_LABELS = {
  no_alcanzado: 'No alcanzado',
  en_proceso:   'En proceso',
  alcanzado:    'Alcanzado',
  superado:     'Superado',
}
const NIVEL_CLASES = {
  no_alcanzado: 'bg-red-100 text-red-700',
  en_proceso:   'bg-amber-100 text-amber-700',
  alcanzado:    'bg-emerald-100 text-emerald-700',
  superado:     'bg-emerald-600 text-white',
}

// Agrupa evaluacion_oficial (array plano {modulo, ra, ce, aplicacion}) por módulo,
// igual que ProyectoFichaModal.vue, añadiendo el nivel evaluado por el docente en
// F4 para cada RA (si ya se evaluó) — misma coincidencia por texto exacto de RA que
// usa MisGruposDetalle.vue en initEvaluacionForm().
const raCeBlocks = computed(() => {
  const entradas = props.equipo?.proyecto?.evaluacion_oficial
  if (!Array.isArray(entradas) || !entradas.length) return []
  const evaluados = props.equipo?.fases?.[4]?.datos?.evaluacion_docente?.ras || []
  const mapa = new Map()
  for (const e of entradas) {
    const nombre = e.modulo || 'Sin módulo'
    if (!mapa.has(nombre)) mapa.set(nombre, [])
    mapa.get(nombre).push({ ...e, nivel: evaluados.find(ev => ev.ra === e.ra)?.nivel || null })
  }
  return [...mapa.entries()].map(([modulo, items]) => ({ modulo, items }))
})

// Nota final: la fase de Presentación (F4) es donde se cierra la evaluación curricular
// del proyecto — no hay un campo de nota agregada aparte, así que esta es "la" nota final.
const notaFinal    = computed(() => props.equipo?.fases?.[4]?.nota_docente ?? null)
const notasPorFase = computed(() =>
  (props.equipo?.fases || []).filter(f => f?.numero_fase !== 4 && f?.nota_docente !== null && f?.nota_docente !== undefined)
)
function labelFase(numero) {
  return FASES_PROYECTO.find(f => f.num === numero)?.label ?? `Fase ${numero}`
}
</script>

<template>
  <Teleport to="body">
    <Transition name="diagnostico-modal">
      <div v-if="abierto" class="fixed inset-0 z-[55] flex items-start justify-center p-4 overflow-y-auto">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cerrar" />

        <div class="relative z-10 bg-[#f4f6fa] rounded-3xl shadow-2xl w-full max-w-3xl my-4
                    max-h-[92vh] flex flex-col overflow-hidden">

          <!-- Barra superior sticky -->
          <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-gray-100 shrink-0 gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full
                          bg-[#00A859]/10 border border-[#00A859]/20 shrink-0">
                <span class="w-2 h-2 rounded-full bg-[#00A859]" />
                <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Diagnóstico final</span>
              </div>
              <p class="font-black text-[#1F2937] text-sm truncate">{{ equipo?.nombre }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button @click="descargar"
                      class="px-3 py-1.5 rounded-xl bg-[#00A859] text-[10px] font-black uppercase tracking-widest
                             text-white shadow-sm hover:bg-[#009048] transition-all flex items-center gap-1.5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/>
                </svg>
                PDF
              </button>
              <button @click="cerrar"
                      class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center
                             text-gray-400 hover:bg-gray-200 transition-all shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Contenido -->
          <div v-if="equipo" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5">

            <!-- Contexto: curso, grupo, ciclo, centro, proyecto -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-2">
              <p class="text-base font-black text-[#121212]">{{ equipo.proyecto?.titulo || 'Sin proyecto asociado' }}</p>
              <div class="flex flex-wrap gap-1.5">
                <span v-if="encuentro?.curso" class="px-2.5 py-1 rounded-full bg-[#00A859]/10 text-[#00A859] text-[10px] font-black">
                  {{ formatCurso(encuentro.curso) }} curso
                </span>
                <span v-if="encuentro?.grupo" class="px-2.5 py-1 rounded-full bg-gray-900 text-white text-[10px] font-black">
                  Grupo {{ encuentro.grupo }}
                </span>
                <span v-if="encuentro?.ciclo_formativo" class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-[10px] font-semibold">
                  {{ encuentro.ciclo_formativo }}
                </span>
                <span v-if="encuentro?.centro_educativo" class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-[10px] font-semibold">
                  {{ encuentro.centro_educativo }}
                </span>
                <span v-if="equipo.proyecto?.familia" class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-[10px] font-semibold">
                  {{ equipo.proyecto.familia }}
                </span>
              </div>
              <!-- Miembros del equipo -->
              <div v-if="equipo.miembros?.length" class="flex flex-wrap gap-1.5 pt-1">
                <span v-for="m in equipo.miembros" :key="m.id"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-50 border border-gray-100 text-gray-600 text-[10px] font-semibold">
                  {{ m.nombre }}<span v-if="m.rol" class="text-gray-400"> · {{ m.rol }}</span>
                </span>
              </div>
              <!-- Fichas adjuntas -->
              <div class="flex flex-wrap gap-2 pt-2">
                <button v-if="equipo.proyecto?.microreto_id"
                        @click="microretoModalId = equipo.proyecto.microreto_id"
                        class="px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200 text-[10px] font-black
                               uppercase tracking-wider text-gray-500 hover:border-[#00A859] hover:text-[#00A859] transition-all">
                  📎 Ficha del reto
                </button>
                <button v-if="equipo.proyecto?.uuid"
                        @click="proyectoModalUuid = equipo.proyecto.uuid"
                        class="px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200 text-[10px] font-black
                               uppercase tracking-wider text-gray-500 hover:border-[#00A859] hover:text-[#00A859] transition-all">
                  📎 Ficha del proyecto
                </button>
              </div>
            </div>

            <!-- Diagnóstico IA -->
            <div v-if="equipo.diagnostico_final" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-3">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Diagnóstico</p>
              <p class="text-sm text-[#1F2937] leading-relaxed">{{ equipo.diagnostico_final.resumen }}</p>

              <div v-if="equipo.diagnostico_final.fortalezas?.length" class="space-y-1">
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Fortalezas</p>
                <ul class="space-y-1">
                  <li v-for="(f, i) in equipo.diagnostico_final.fortalezas" :key="i"
                      class="text-xs text-[#1F2937] leading-relaxed border-l-2 border-emerald-300 pl-2.5">{{ f }}</li>
                </ul>
              </div>

              <div v-if="equipo.diagnostico_final.areas_mejora?.length" class="space-y-1">
                <p class="text-[10px] font-black uppercase tracking-wider text-amber-600">Áreas de mejora</p>
                <ul class="space-y-1">
                  <li v-for="(a, i) in equipo.diagnostico_final.areas_mejora" :key="i"
                      class="text-xs text-[#1F2937] leading-relaxed border-l-2 border-amber-300 pl-2.5">{{ a }}</li>
                </ul>
              </div>

              <div v-if="equipo.diagnostico_final.valoracion_ra_ce" class="space-y-1">
                <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Valoración RA/CE</p>
                <p class="text-xs text-[#1F2937] leading-relaxed">{{ equipo.diagnostico_final.valoracion_ra_ce }}</p>
              </div>

              <p v-if="equipo.diagnostico_final.conclusion" class="text-xs font-semibold text-[#1F2937] italic">
                {{ equipo.diagnostico_final.conclusion }}
              </p>

              <p v-if="equipo.diagnostico_generado_en" class="text-[10px] text-gray-400">
                Generado el {{ new Date(equipo.diagnostico_generado_en).toLocaleString('es-ES') }}
              </p>
            </div>

            <!-- RA/CE y módulos trabajados -->
            <div v-if="raCeBlocks.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-4">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                Resultados de aprendizaje y criterios de evaluación trabajados
              </p>
              <div v-for="bloque in raCeBlocks" :key="bloque.modulo" class="space-y-3">
                <p class="text-xs font-black text-[#1F2937] bg-gray-50 border-l-2 border-gray-900 rounded-lg px-2.5 py-1.5">
                  {{ bloque.modulo }}
                </p>
                <div v-for="(item, i) in bloque.items" :key="i" class="pl-2.5 space-y-1.5">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold text-[#1F2937] leading-relaxed">{{ item.ra }}</p>
                    <span v-if="item.nivel" :class="['shrink-0 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider', NIVEL_CLASES[item.nivel]]">
                      {{ NIVEL_LABELS[item.nivel] }}
                    </span>
                  </div>
                  <ul v-if="item.ce?.length" class="space-y-1">
                    <li v-for="(ce, j) in item.ce" :key="j" class="text-[11px] text-gray-500 leading-relaxed pl-3 border-l border-gray-200">
                      {{ ce }}
                    </li>
                  </ul>
                  <p v-if="item.aplicacion" class="text-[11px] text-gray-500 italic">{{ item.aplicacion }}</p>
                </div>
              </div>
            </div>

            <!-- Notas -->
            <div v-if="notasPorFase.length || notaFinal !== null" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-2">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Notas</p>
              <div v-for="f in notasPorFase" :key="f.numero_fase" class="flex items-center justify-between text-sm">
                <span class="text-gray-500">{{ labelFase(f.numero_fase) }}</span>
                <span class="font-black text-[#1F2937]">{{ f.nota_docente }}</span>
              </div>
              <div v-if="notaFinal !== null" class="flex items-center justify-between pt-2 border-t border-gray-100">
                <span class="text-sm font-black text-[#1F2937]">Nota final</span>
                <span class="text-lg font-black text-[#00A859]">{{ notaFinal }} / 10</span>
              </div>
            </div>

          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <MicroretoModal :microreto-id="microretoModalId" @close="microretoModalId = null" />
  <ProyectoFichaModal :proyecto-uuid="proyectoModalUuid" @close="proyectoModalUuid = null" />
</template>

<style scoped>
.diagnostico-modal-enter-active,
.diagnostico-modal-leave-active {
  transition: opacity 0.15s ease;
}
.diagnostico-modal-enter-from,
.diagnostico-modal-leave-to {
  opacity: 0;
}
</style>
