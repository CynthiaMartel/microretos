<!-- Evaluación del grupo: evaluación curricular (RA/CE), diagnóstico final IA y nota final, con
     «Editar» en cada apartado y «Generar/Regenerar con IA» si puedeEditar. Mismo aspecto en los tres
     sitios que lo usan: el modal «Evaluación del grupo» (DiagnosticoModal.vue), el Detalle de grupos
     (dentro de la tarjeta de cada grupo, EquipoResolucionCard.vue) y la ficha del reto ajustado
     (EquiposSeguimiento.vue, en solo lectura). Emite los cambios para que el padre los aplique. -->
<script setup>
import { ref, computed, watch } from 'vue'
import { guardarDiagnosticoFinal, generarDiagnosticoFinal } from '../services/equipoService.js'
import { useDiagnosticoPdfExport } from '../composables/useDiagnosticoPdfExport.js'
import { FASES_PROYECTO } from '../config/fasesProyecto.js'
import EvaluacionCurricularForm from './EvaluacionCurricularForm.vue'
import { PencilSquareIcon, SparklesIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  equipo:    { type: Object, required: true },
  encuentro: { type: Object, default: null },   // para el PDF
  // «Editar» en la evaluación y el diagnóstico y «Generar con IA» (no a colaboradores de solo lectura)
  puedeEditar: { type: Boolean, default: false },
  // «Evaluar» de un grupo pendiente: abre con la evaluación ya en edición
  editarEvaluacionAlAbrir: { type: Boolean, default: false },
  // Botón PDF en el diagnóstico (el modal ya lo lleva en su cabecera)
  conPdf: { type: Boolean, default: true },
  // Resalta un momento el diagnóstico (el Detalle de grupos lo activa al llevar el scroll ahí)
  resaltarDiagnostico: { type: Boolean, default: false },
})
// actualizado: { diagnostico_final, diagnostico_generado_en? } para que el padre lo aplique al equipo.
// evaluada: se ha guardado la evaluación; el padre recarga los grupos.
const emit = defineEmits(['actualizado', 'evaluada'])

const { descargarPDF } = useDiagnosticoPdfExport()
function descargar() {
  descargarPDF({ equipo: props.equipo, encuentro: props.encuentro })
}

// ── Evaluación curricular ────────────────────────────────────────────────────
const editandoEvaluacion = ref(false)
const evaluada = computed(() => !!props.equipo?.fases?.[4]?.validado_docente)
const observacionesGenerales = computed(() => props.equipo?.fases?.[4]?.observaciones_docente || '')
function evaluacionGuardada() {
  editandoEvaluacion.value = false
  emit('evaluada')
}

// ── Generar el diagnóstico con IA (solo con las 5 fases completas; el backend también lo exige) ──
const generando = ref(false)
const errorGenerar = ref('')
async function generar() {
  if (generando.value) return
  // Ya existe uno: confirmar antes de sustituirlo (y avisar si el docente lo había retocado)
  if (props.equipo.diagnostico_final && !confirm(props.equipo.diagnostico_final.editado_docente
    ? 'Este diagnóstico tiene cambios manuales del docente. Si la IA lo regenera, esos cambios se perderán. ¿Continuar?'
    : 'Ya existe un diagnóstico final para este grupo. ¿Quieres que la IA lo genere de nuevo? Se sustituirá el actual.')) return
  generando.value = true
  errorGenerar.value = ''
  try {
    const res = await generarDiagnosticoFinal(props.equipo.id)
    emit('actualizado', { diagnostico_final: res.data.diagnostico, diagnostico_generado_en: res.data.generado_en })
  } catch (e) {
    errorGenerar.value = e.response?.data?.error ?? 'No se pudo generar el diagnóstico.'
  } finally {
    generando.value = false
  }
}

// ── Edición manual (mismo formulario y endpoint que el Detalle de grupos) ────
// Fortalezas y áreas de mejora se editan como texto, una por línea.
const editando  = ref(null)   // copia editable del diagnóstico
const guardando = ref(false)
const errorEdicion = ref('')
function editar() {
  const d = props.equipo?.diagnostico_final || {}
  errorEdicion.value = ''
  editando.value = {
    resumen:          d.resumen ?? '',
    fortalezas:       (d.fortalezas ?? []).join('\n'),
    areas_mejora:     (d.areas_mejora ?? []).join('\n'),
    valoracion_ra_ce: d.valoracion_ra_ce ?? '',
    conclusion:       d.conclusion ?? '',
  }
}
const aLista = (texto) => texto.split('\n').map(l => l.trim()).filter(Boolean)
async function guardarEdicion() {
  if (!editando.value || guardando.value || !editando.value.resumen.trim()) return
  guardando.value = true
  errorEdicion.value = ''
  try {
    const f = editando.value
    const res = await guardarDiagnosticoFinal(props.equipo.id, {
      resumen:          f.resumen.trim(),
      fortalezas:       aLista(f.fortalezas),
      areas_mejora:     aLista(f.areas_mejora),
      valoracion_ra_ce: f.valoracion_ra_ce.trim() || null,
      conclusion:       f.conclusion.trim() || null,
    })
    emit('actualizado', { diagnostico_final: res.data.diagnostico })
    editando.value = null
  } catch (e) {
    const errores = e.response?.data?.errors
    errorEdicion.value = e.response?.data?.error ?? (errores ? Object.values(errores).flat()[0] : 'No se pudo guardar el diagnóstico.')
  } finally {
    guardando.value = false
  }
}
// Al abrir o cambiar de grupo se descarta la edición a medias (por id: al recargar los grupos tras
// guardar llega otro objeto del mismo grupo y no debe cerrar lo que se está viendo)
watch(() => props.equipo?.id, () => {
  editando.value = null
  errorGenerar.value = ''
  editandoEvaluacion.value = !!props.equipo && props.editarEvaluacionAlAbrir && props.puedeEditar
}, { immediate: true })


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
const notaFinal    = computed(() => {
  const v = props.equipo?.fases?.[4]?.nota_docente
  if (v === null || v === undefined || v === '') return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
})
const notasPorFase = computed(() =>
  (props.equipo?.fases || []).filter(f => f?.numero_fase !== 4 && f?.nota_docente !== null && f?.nota_docente !== undefined)
)
function labelFase(numero) {
  return FASES_PROYECTO.find(f => f.num === numero)?.label ?? `Fase ${numero}`
}
</script>

<template>
  <div class="min-w-0 space-y-4">
    <!-- 1 · Evaluación curricular (RA/CE): lectura con «Editar», o el formulario -->
    <section :id="`evaluacion-equipo-${equipo.id}`" class="scroll-mt-36 space-y-3 rounded-2xl border border-borde bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
          <h3 class="text-sm font-semibold text-azul-noche">Evaluación curricular</h3>
          <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="evaluada ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'">
            {{ evaluada ? 'Evaluada' : 'Pendiente de evaluar' }}
          </span>
        </div>
        <button v-if="puedeEditar && !editandoEvaluacion" type="button" @click="editandoEvaluacion = true"
                class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5"
                aria-label="Editar la evaluación curricular">
          <PencilSquareIcon class="h-4 w-4 shrink-0" aria-hidden="true" /> Editar
        </button>
      </div>

      <template v-if="editandoEvaluacion">
        <EvaluacionCurricularForm :equipo="equipo" :catalogo="equipo.proyecto?.evaluacion_oficial ?? []"
                                  :puede-editar="puedeEditar" :con-cabecera="false" @guardada="evaluacionGuardada" />
        <button type="button" @click="editandoEvaluacion = false" class="accion-secundaria">Cancelar</button>
      </template>
      <template v-else>
        <p v-if="!evaluada" class="text-sm text-texto-secundario">Este grupo todavía no tiene evaluación curricular.</p>
        <div v-if="raCeBlocks.length" class="space-y-4">
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
    </div>
        <p v-else class="text-sm italic text-texto-secundario">El reto ajustado no tiene RA/CE oficiales asignados todavía.</p>
        <div v-if="observacionesGenerales" class="rounded-xl border border-amber-100 bg-amber-50 p-3">
          <p class="text-xs font-semibold text-amber-800">Observaciones generales</p>
          <p class="mt-1 whitespace-pre-line break-words text-sm text-amber-900">{{ observacionesGenerales }}</p>
        </div>
      </template>
    </section>

    <!-- 2 · Diagnóstico final IA: lectura con «Editar», el formulario o «Generar con IA» -->
    <!-- id + tabindex: destino del scroll/foco de «Ir al diagnóstico» (Detalle de grupos) -->
    <section :id="`diagnostico-equipo-${equipo.id}`" tabindex="-1"
             class="scroll-mt-36 space-y-3 rounded-2xl border bg-white p-4 shadow-sm outline-none transition-shadow duration-500"
             :class="[editando ? 'border-amber-200' : 'border-borde', resaltarDiagnostico ? 'ring-2 ring-emerald-300 ring-offset-4' : '']">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-azul-noche">Diagnóstico final</h3>
        <div v-if="equipo.diagnostico_final && !editando" class="flex flex-wrap items-center gap-2">
          <button v-if="puedeEditar && (equipo.fases_completas ?? 0) === 5" type="button" @click="generar" :disabled="generando"
                  class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-violet-200 bg-violet-50 px-4 text-sm font-semibold text-violet-700 transition-colors hover:bg-violet-100 disabled:opacity-50"
                  aria-label="Regenerar el diagnóstico final con IA">
            <SparklesIcon class="h-4 w-4 shrink-0" aria-hidden="true" /> {{ generando ? 'Redactando…' : 'Regenerar' }}
          </button>
          <button v-if="conPdf" type="button" @click="descargar"
                  class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-borde bg-white px-4 text-sm font-semibold text-azul-noche transition-colors hover:bg-gray-50"
                  aria-label="Descargar el diagnóstico en PDF">
            <ArrowDownTrayIcon class="h-4 w-4 shrink-0" aria-hidden="true" /> PDF
          </button>
        <button v-if="puedeEditar" type="button" @click="editar"
                class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5"
                aria-label="Editar el diagnóstico final">
          <PencilSquareIcon class="h-4 w-4 shrink-0" aria-hidden="true" /> Editar
        </button>
        </div>
      </div>
      <p v-if="errorGenerar && equipo.diagnostico_final" role="alert" class="text-sm font-semibold text-red-600">{{ errorGenerar }}</p>
      <template v-if="editando">
      <p class="text-xs font-semibold text-amber-800">Estás editando el texto que redactó la IA. Al guardar quedará marcado como editado por docente.</p>
      <label class="block space-y-1">
        <span class="text-sm font-semibold text-texto-secundario">Resumen</span>
        <textarea v-model="editando.resumen" rows="4" maxlength="3000" class="field-input resize-y !text-sm" />
      </label>
      <label class="block space-y-1">
        <span class="text-sm font-semibold text-empresas-dark">Fortalezas <span class="font-normal text-texto-secundario">(una por línea)</span></span>
        <textarea v-model="editando.fortalezas" rows="4" class="field-input resize-y !text-sm" />
      </label>
      <label class="block space-y-1">
        <span class="text-sm font-semibold text-amber-800">Áreas de mejora <span class="font-normal text-texto-secundario">(una por línea)</span></span>
        <textarea v-model="editando.areas_mejora" rows="4" class="field-input resize-y !text-sm" />
      </label>
      <label class="block space-y-1">
        <span class="text-sm font-semibold text-texto-secundario">Valoración RA/CE</span>
        <textarea v-model="editando.valoracion_ra_ce" rows="3" maxlength="3000" class="field-input resize-y !text-sm" />
      </label>
      <label class="block space-y-1">
        <span class="text-sm font-semibold text-texto-secundario">Conclusión</span>
        <textarea v-model="editando.conclusion" rows="2" maxlength="1000" class="field-input resize-y !text-sm" />
      </label>
      <p v-if="errorEdicion" role="alert" class="text-sm font-semibold text-red-600">{{ errorEdicion }}</p>
      <div class="flex flex-wrap gap-2">
        <button type="button" @click="guardarEdicion" :disabled="guardando || !editando.resumen.trim()" class="accion-principal disabled:opacity-50">
          {{ guardando ? 'Guardando…' : 'Guardar cambios' }}
        </button>
        <button type="button" @click="editando = null" :disabled="guardando" class="accion-secundaria">Cancelar</button>
      </div>
    </template>
      <template v-else-if="equipo.diagnostico_final">
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
    </template>
      <template v-else>
        <p class="text-sm text-texto-secundario">
          {{ (equipo.fases_completas ?? 0) === 5
            ? 'Todavía no se ha generado. La IA lo redacta a partir de las fases, la evaluación curricular y las reflexiones del grupo.'
            : `Se genera cuando el grupo completa las 5 fases (lleva ${equipo.fases_completas ?? 0}/5).` }}
        </p>
        <p v-if="errorGenerar" role="alert" class="text-sm font-semibold text-red-600">{{ errorGenerar }}</p>
        <button v-if="puedeEditar && (equipo.fases_completas ?? 0) === 5" type="button" @click="generar" :disabled="generando"
                class="inline-flex h-9 items-center gap-1.5 rounded-full border border-violet-200 bg-violet-50 px-4 text-sm font-semibold text-violet-700 transition-colors hover:bg-violet-100 disabled:opacity-50">
          <SparklesIcon class="h-4 w-4 shrink-0" aria-hidden="true" />
          {{ generando ? 'La IA está redactando…' : 'Generar diagnóstico con IA' }}
        </button>
      </template>
    </section>

    <!-- Notas por fase (si las hay) y, al final de todo y en grande, la nota final -->
    <section v-if="notasPorFase.length" class="space-y-2 rounded-2xl border border-borde bg-white p-4 shadow-sm">
      <h3 class="text-sm font-semibold text-texto-secundario">Notas por fase</h3>
      <div v-for="f in notasPorFase" :key="f.numero_fase" class="flex items-center justify-between gap-3 text-sm">
        <span class="min-w-0 text-texto-secundario">{{ labelFase(f.numero_fase) }}</span>
        <span class="shrink-0 font-semibold text-azul-noche">{{ f.nota_docente }}</span>
      </div>
    </section>
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3 shadow-sm sm:px-5 sm:py-4"
             :class="notaFinal !== null ? 'border-administraciones/20 bg-administraciones/5' : 'border-dashed border-gray-200 bg-white'">
      <div class="min-w-0">
        <h3 class="text-xs font-black uppercase tracking-widest" :class="notaFinal !== null ? 'text-administraciones-dark' : 'text-gray-400'">Nota final</h3>
        <p v-if="notaFinal === null" class="mt-0.5 text-xs text-gray-500">Todavía sin nota: se pone en la evaluación curricular.</p>
      </div>
      <p v-if="notaFinal !== null" class="shrink-0 font-heading text-3xl font-bold leading-none text-administraciones-dark sm:text-5xl">
        {{ notaFinal.toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}<span class="text-lg font-semibold sm:text-xl">/10</span>
      </p>
    </section>
  </div>
</template>
