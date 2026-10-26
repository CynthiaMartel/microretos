<!-- Modal "Ver diagnóstico" de un equipo — se abre una vez generado el diagnóstico
     final IA (equipo.diagnostico_final). Sigue el mismo esqueleto que ProyectoFichaModal.vue
     (Teleport + Transition + overlay + header sticky con botón PDF), pero recibe el equipo
     ya cargado como prop en vez de volver a pedirlo a la API — el padre (MisGruposDetalle.vue)
     ya lo tiene en memoria. z-[55]: por debajo de MicroretoModal (z-60) y ProyectoFichaModal
     (z-80), para que las fichas adjuntas que se abren desde aquí queden siempre por encima.
     Accesible como ModalTaller (diálogo, foco atrapado, Escape, retorno del foco), sin usarlo
     directamente porque su z-[70] taparía la ficha del reto. -->
<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue'
import { ROLES_GRUPO } from '../config/rolesGrupo.js'
import MicroretoModal from './MicroretoModal.vue'
import ProyectoFichaModal from './ProyectoFichaModal.vue'
import { useDiagnosticoPdfExport } from '../composables/useDiagnosticoPdfExport.js'
import { FASES_PROYECTO } from '../config/fasesProyecto.js'
import { formatCurso } from '../utils/formatCurso.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

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

// ── Accesibilidad (como ModalTaller, GUIA_DISENO §6.4) ──────────────────────
// role="dialog" + aria-modal + título enlazado; foco inicial dentro, atrapado con Tab, Escape
// cierra y el foco vuelve al botón que lo abrió. Mientras hay abierta una ficha adjunta (reto o
// proyecto, que van por encima) el teclado es de esa ficha: Escape no cierra el diagnóstico.
const panel = ref(null)
const idTitulo = `diagnostico-${Math.random().toString(36).slice(2, 8)}`
const ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
let focoPrevio = null
const fichaAbierta = computed(() => microretoModalId.value !== null || proyectoModalUuid.value !== null)

function alTeclear(e) {
  if (!panel.value || fichaAbierta.value) return
  if (e.key === 'Escape') { e.stopPropagation(); cerrar(); return }
  if (e.key !== 'Tab') return
  const nodos = [...panel.value.querySelectorAll(ENFOCABLES)].filter(n => n.offsetParent !== null)
  if (!nodos.length) return
  const [primero, ultimo] = [nodos[0], nodos[nodos.length - 1]]
  if (!panel.value.contains(document.activeElement)) { e.preventDefault(); primero.focus(); return }
  if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus() }
  else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus() }
}
watch(abierto, async (v, antes) => {
  if (v) {
    focoPrevio = document.activeElement
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', alTeclear)
    await nextTick()
    panel.value?.focus()
  } else if (antes) {
    document.removeEventListener('keydown', alTeclear)
    document.body.style.overflow = ''
    focoPrevio?.focus?.()
  }
}, { immediate: true })
onUnmounted(() => {
  document.removeEventListener('keydown', alTeclear)
  document.body.style.overflow = ''
})

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
  no_alcanzado: 'bg-red-50 text-red-700',
  en_proceso:   'bg-amber-50 text-amber-800',
  alcanzado:    'bg-empresas/10 text-empresas-dark',
  superado:     'bg-empresas text-white',
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
      <div v-if="abierto" class="fixed inset-0 z-[55] flex items-start justify-center overflow-y-auto p-4">

        <div class="absolute inset-0 bg-azul-noche/60 backdrop-blur-sm" @click="cerrar" />

        <div ref="panel" role="dialog" aria-modal="true" :aria-labelledby="idTitulo" tabindex="-1"
             class="relative z-10 my-4 flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-[2rem] bg-[#f4f6fa] shadow-2xl outline-none">

          <!-- Barra superior fija -->
          <div class="flex shrink-0 items-center justify-between gap-3 border-b border-borde bg-white px-5 py-3">
            <div class="flex min-w-0 items-center gap-2.5">
              <!-- En móvil la etiqueta queda en punto de color (el texto, para lectores de pantalla) y el
                   título usa hasta dos líneas: si no, el PDF y la ✕ lo dejaban sin sitio -->
              <span class="inline-flex shrink-0 items-center gap-2 rounded-full border border-administraciones/20 bg-administraciones/10 p-1.5 text-xs font-semibold text-administraciones-dark sm:px-3 sm:py-1">
                <span class="h-2 w-2 rounded-full bg-administraciones" aria-hidden="true" />
                <span class="sr-only sm:not-sr-only">Diagnóstico final</span>
              </span>
              <h2 :id="idTitulo" class="line-clamp-2 min-w-0 text-sm font-bold leading-snug text-azul-noche sm:line-clamp-1">
                {{ equipo && nombreGrupo(equipo) }}<template v-if="equipo?.proyecto?.titulo"> · {{ equipo.proyecto.titulo }}</template>
              </h2>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <button type="button" @click="descargar"
                      class="inline-flex h-9 items-center gap-1.5 rounded-full bg-rol-fuerte px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rol-fuerte/90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/>
                </svg>
                PDF
              </button>
              <button type="button" @click="cerrar" aria-label="Cerrar el diagnóstico"
                      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-texto-secundario transition-colors hover:bg-gray-200 hover:text-azul-noche">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Contenido -->
          <div v-if="equipo" class="flex-1 space-y-4 overflow-y-auto p-4 md:p-6">

            <!-- Contexto: proyecto, curso, clase, ciclo, centro, familia y miembros -->
            <section class="space-y-2 rounded-2xl border border-borde bg-white p-4 shadow-sm">
              <p class="font-heading text-base font-bold text-azul-noche">{{ equipo.proyecto?.titulo || 'Sin proyecto asociado' }}</p>
              <div class="flex flex-wrap gap-1.5 text-xs">
                <span v-if="encuentro?.curso" class="rounded-full bg-administraciones/10 px-2.5 py-1 font-semibold text-administraciones-dark">{{ formatCurso(encuentro.curso) }} curso</span>
                <span v-if="encuentro?.grupo" class="rounded-full bg-azul-noche px-2.5 py-1 font-semibold text-white">Clase {{ encuentro.grupo }}</span>
                <span v-if="encuentro?.ciclo_formativo" class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-azul-noche">{{ encuentro.ciclo_formativo }}</span>
                <span v-if="encuentro?.centro_educativo" class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-azul-noche">{{ encuentro.centro_educativo }}</span>
                <span v-if="equipo.proyecto?.familia" class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-azul-noche">{{ equipo.proyecto.familia }}</span>
              </div>
              <div v-if="equipo.miembros?.length" class="flex flex-wrap gap-1.5 pt-1">
                <!-- En móvil el rol va debajo del nombre (una píldora partida en dos líneas quedaba rara) -->
                <span v-for="m in equipo.miembros" :key="m.id"
                      class="inline-flex max-w-full flex-col rounded-xl border border-borde bg-gray-50 px-2.5 py-1 text-xs font-medium text-azul-noche sm:flex-row sm:items-center sm:gap-1 sm:rounded-full sm:py-0.5">
                  <span class="break-words">{{ m.alias }}</span>
                  <span v-if="m.rol" class="text-texto-secundario"><span class="hidden sm:inline">· </span>{{ ROLES_GRUPO[m.rol]?.label || m.rol }}</span>
                </span>
              </div>
              <div class="flex flex-wrap gap-2 pt-2">
                <button v-if="equipo.proyecto?.microreto_id" type="button" @click="microretoModalId = equipo.proyecto.microreto_id"
                        class="inline-flex h-9 items-center rounded-full border border-rol-fuerte/30 bg-white px-4 text-sm font-semibold text-rol-fuerte hover:bg-rol-fuerte/5">
                  Ficha del reto
                </button>
                <button v-if="equipo.proyecto?.uuid" type="button" @click="proyectoModalUuid = equipo.proyecto.uuid"
                        class="inline-flex h-9 items-center rounded-full border border-rol-fuerte/30 bg-white px-4 text-sm font-semibold text-rol-fuerte hover:bg-rol-fuerte/5">
                  Ficha del proyecto
                </button>
              </div>
            </section>

            <!-- Diagnóstico IA -->
            <section v-if="equipo.diagnostico_final" class="space-y-3 rounded-2xl border border-borde bg-white p-4 shadow-sm">
              <h3 class="text-sm font-semibold text-texto-secundario">Diagnóstico</h3>
              <p class="text-sm leading-relaxed text-azul-noche">{{ equipo.diagnostico_final.resumen }}</p>

              <div v-if="equipo.diagnostico_final.fortalezas?.length" class="space-y-1">
                <h4 class="text-sm font-semibold text-empresas-dark">Fortalezas</h4>
                <ul class="space-y-1">
                  <li v-for="(f, i) in equipo.diagnostico_final.fortalezas" :key="i"
                      class="border-l-2 border-empresas/50 pl-2.5 text-sm leading-relaxed text-azul-noche">{{ f }}</li>
                </ul>
              </div>

              <div v-if="equipo.diagnostico_final.areas_mejora?.length" class="space-y-1">
                <h4 class="text-sm font-semibold text-amber-800">Áreas de mejora</h4>
                <ul class="space-y-1">
                  <li v-for="(a, i) in equipo.diagnostico_final.areas_mejora" :key="i"
                      class="border-l-2 border-amber-300 pl-2.5 text-sm leading-relaxed text-azul-noche">{{ a }}</li>
                </ul>
              </div>

              <div v-if="equipo.diagnostico_final.valoracion_ra_ce" class="space-y-1">
                <h4 class="text-sm font-semibold text-texto-secundario">Valoración RA/CE</h4>
                <p class="text-sm leading-relaxed text-azul-noche">{{ equipo.diagnostico_final.valoracion_ra_ce }}</p>
              </div>

              <p v-if="equipo.diagnostico_final.conclusion" class="text-sm font-semibold italic text-azul-noche">
                {{ equipo.diagnostico_final.conclusion }}
              </p>

              <p v-if="equipo.diagnostico_generado_en" class="text-xs text-texto-secundario">
                Generado por IA el {{ new Date(equipo.diagnostico_generado_en).toLocaleString('es-ES') }}
                <template v-if="equipo.diagnostico_final.editado_en">
                  · editado por docente el {{ new Date(equipo.diagnostico_final.editado_en).toLocaleString('es-ES') }}
                </template>
              </p>
            </section>

            <!-- RA/CE y módulos trabajados -->
            <section v-if="raCeBlocks.length" class="space-y-4 rounded-2xl border border-borde bg-white p-4 shadow-sm">
              <h3 class="text-sm font-semibold text-texto-secundario">Resultados de aprendizaje y criterios de evaluación trabajados</h3>
              <div v-for="bloque in raCeBlocks" :key="bloque.modulo" class="space-y-3">
                <p class="rounded-lg border-l-2 border-azul-noche bg-gray-50 px-2.5 py-1.5 text-sm font-semibold text-azul-noche">
                  {{ bloque.modulo }}
                </p>
                <div v-for="(item, i) in bloque.items" :key="i" class="space-y-1.5 pl-2.5">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium leading-relaxed text-azul-noche">{{ item.ra }}</p>
                    <span v-if="item.nivel" :class="['shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold', NIVEL_CLASES[item.nivel]]">
                      {{ NIVEL_LABELS[item.nivel] }}
                    </span>
                  </div>
                  <ul v-if="item.ce?.length" class="space-y-1">
                    <li v-for="(ce, j) in item.ce" :key="j" class="border-l border-borde pl-3 text-xs leading-relaxed text-texto-secundario">
                      {{ ce }}
                    </li>
                  </ul>
                  <p v-if="item.aplicacion" class="text-xs italic text-texto-secundario">{{ item.aplicacion }}</p>
                </div>
              </div>
            </section>

            <!-- Notas -->
            <section v-if="notasPorFase.length || notaFinal !== null" class="space-y-2 rounded-2xl border border-borde bg-white p-4 shadow-sm">
              <h3 class="text-sm font-semibold text-texto-secundario">Notas</h3>
              <div v-for="f in notasPorFase" :key="f.numero_fase" class="flex items-center justify-between text-sm">
                <span class="text-texto-secundario">{{ labelFase(f.numero_fase) }}</span>
                <span class="font-semibold text-azul-noche">{{ f.nota_docente }}</span>
              </div>
              <div v-if="notaFinal !== null" class="flex items-center justify-between border-t border-borde pt-2">
                <span class="text-sm font-bold text-azul-noche">Nota final</span>
                <span class="font-heading text-lg font-bold text-administraciones-dark">{{ notaFinal }} / 10</span>
              </div>
            </section>

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
