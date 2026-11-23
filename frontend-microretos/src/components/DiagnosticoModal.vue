<!-- Modal «Evaluación del grupo»: une la evaluación curricular (RA/CE, observaciones y nota
     final) y el diagnóstico final IA de un equipo en un solo sitio, con «Editar» en cada apartado
     (si puedeEditar) y «Generar con IA» cuando el grupo ha completado las 5 fases. Lo abren la
     Biblioteca de evaluación curricular y diagnósticos y el Detalle de grupos. Sigue el mismo esqueleto que ProyectoFichaModal.vue
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
import { formatCurso } from '../utils/formatCurso.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'
import EvaluacionGrupoPanel from './EvaluacionGrupoPanel.vue'

const props = defineProps({
  equipo:    { type: Object, default: null },
  encuentro: { type: Object, default: null },
  // «Editar» en la evaluación y el diagnóstico y «Generar con IA» (no a colaboradores de solo lectura)
  puedeEditar: { type: Boolean, default: false },
  // «Evaluar» de un grupo pendiente: el modal abre con la evaluación ya en edición
  editarEvaluacionAlAbrir: { type: Boolean, default: false },
})
// actualizado: { diagnostico_final, diagnostico_generado_en? } para que el padre lo aplique al equipo.
// evaluada: se ha guardado la evaluación; el padre recarga los grupos.
const emit = defineEmits(['close', 'actualizado', 'evaluada'])

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
                <span class="sr-only sm:not-sr-only">Evaluación del grupo</span>
              </span>
              <h2 :id="idTitulo" class="line-clamp-2 min-w-0 text-sm font-bold leading-snug text-azul-noche sm:line-clamp-1">
                {{ equipo && nombreGrupo(equipo) }}<template v-if="equipo?.proyecto?.titulo"> · {{ equipo.proyecto.titulo }}</template>
              </h2>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <!-- PDF del diagnóstico: solo cuando existe -->
              <button v-if="equipo?.diagnostico_final" type="button" @click="descargar" aria-label="Descargar el diagnóstico en PDF"
                      class="inline-flex h-9 items-center gap-1.5 rounded-full bg-rol-fuerte px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rol-fuerte/90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/>
                </svg>
                PDF
              </button>
              <button type="button" @click="cerrar" aria-label="Cerrar la evaluación del grupo"
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
              <p class="font-heading text-base font-bold text-azul-noche">{{ equipo.proyecto?.titulo || 'Sin reto ajustado asociado' }}</p>
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
                  Ficha del reto original
                </button>
                <button v-if="equipo.proyecto?.uuid" type="button" @click="proyectoModalUuid = equipo.proyecto.uuid"
                        class="inline-flex h-9 items-center rounded-full border border-rol-fuerte/30 bg-white px-4 text-sm font-semibold text-rol-fuerte hover:bg-rol-fuerte/5">
                  Ficha del reto ajustado
                </button>
              </div>
            </section>

            <!-- Evaluación curricular, diagnóstico final y nota final (mismo panel que en el Detalle de grupos) -->
            <EvaluacionGrupoPanel :equipo="equipo" :encuentro="encuentro" :puede-editar="puedeEditar"
                                  :editar-evaluacion-al-abrir="editarEvaluacionAlAbrir" :con-pdf="false"
                                  @actualizado="d => emit('actualizado', d)" @evaluada="emit('evaluada')" />

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
