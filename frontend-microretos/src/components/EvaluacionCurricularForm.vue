<!-- Evaluación curricular RA/CE de un grupo: nivel por RA, nota opcional y observaciones.
     Se guarda en la F4 (PATCH /startup/equipos/{id}/evaluar) y el diagnóstico final la usa.
     La usan el Detalle de grupos (MisGruposDetalle.vue, dentro de la tarjeta del grupo) y la
     Biblioteca de evaluación curricular y diagnósticos (BibliotecaDiagnosticos.vue, en modal).
     Sin permiso de edición (colaborador de solo lectura) se ve pero no se cambia.
     Con `lecturaSiEvaluada` (Detalle de grupos), un grupo ya evaluado se ve en lectura con
     «Editar evaluación», igual que en el modal «Evaluación del grupo». -->
<script setup>
import { ref, computed, watch } from 'vue'
import { evaluarEquipo } from '../services/equipoService.js'
import { ClipboardDocumentCheckIcon, PencilSquareIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  equipo:      { type: Object, required: true },
  catalogo:    { type: Array, default: () => [] },   // evaluacion_oficial del reto ajustado
  puedeEditar: { type: Boolean, default: false },
  // En el modal de la biblioteca el título ya va en la cabecera del modal
  conCabecera: { type: Boolean, default: true },
  // false: solo lectura (niveles, nota y observaciones guardados); el modal pone «Editar» aparte
  editable:    { type: Boolean, default: true },
  // true: si ya está evaluada, empieza en lectura con su propio botón «Editar evaluación»
  lecturaSiEvaluada: { type: Boolean, default: false },
})
const emit = defineEmits(['guardada'])

const NIVEL_CLASES = {
  no_alcanzado: 'bg-red-50 text-red-700',
  en_proceso:   'bg-amber-50 text-amber-800',
  alcanzado:    'bg-empresas/10 text-empresas-dark',
  superado:     'bg-empresas text-white',
}
const NIVEL_OPCIONES = [
  { value: 'no_alcanzado', label: 'No alcanzado' },
  { value: 'en_proceso',   label: 'En proceso' },
  { value: 'alcanzado',    label: 'Alcanzado' },
  { value: 'superado',     label: 'Superado' },
]

const form      = ref(null)
const editandoInterno = ref(false)
const guardando = ref(false)
const error     = ref('')

function iniciar() {
  const f4 = props.equipo.fases?.[4]
  const existentes = f4?.datos?.evaluacion_docente?.ras ?? []
  form.value = {
    ras: (props.catalogo ?? []).map(item => {
      const previa = existentes.find(e => e.ra === item.ra)
      return { ra: item.ra, nivel: previa?.nivel ?? '', observaciones: previa?.observaciones ?? '' }
    }),
    nota_docente:          f4?.nota_docente ?? null,
    observaciones_docente: f4?.observaciones_docente ?? '',
  }
  error.value = ''
  editandoInterno.value = false
}
// Al recargar los grupos tras guardar llega un equipo nuevo: se vuelve a leer lo guardado
// (el catálogo se compara por contenido: un `?? []` del padre crea un array nuevo en cada render
// y, comparado por referencia, borraría lo que se está escribiendo)
watch([() => props.equipo, () => JSON.stringify(props.catalogo ?? [])], iniciar, { immediate: true })

const evaluada = computed(() => !!props.equipo.fases?.[4]?.validado_docente)
const puedeEnviar = computed(() => form.value?.ras.some(r => r.nivel))
const etiquetaNivel = (v) => NIVEL_OPCIONES.find(o => o.value === v)?.label ?? 'Sin valorar'
const enLectura = computed(() => !props.editable || (props.lecturaSiEvaluada && evaluada.value && !editandoInterno.value))
const tieneNota = computed(() => form.value && form.value.nota_docente !== null && form.value.nota_docente !== '')
// Descarta lo escrito y vuelve a lo guardado
function cancelarEdicion() {
  iniciar()
}

async function enviar() {
  if (!puedeEnviar.value || guardando.value) return
  guardando.value = true
  error.value = ''
  try {
    await evaluarEquipo(props.equipo.id, {
      evaluacion: {
        ras:           form.value.ras.filter(r => r.nivel),
        nota_opcional: form.value.nota_docente,
      },
      nota_docente:          form.value.nota_docente,
      observaciones_docente: form.value.observaciones_docente,
    })
    editandoInterno.value = false
    emit('guardada')
  } catch (e) {
    error.value = e.response?.data?.error ?? e.response?.data?.message ?? 'No se pudo guardar la evaluación.'
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div v-if="form" class="min-w-0 space-y-3" :class="conCabecera ? 'rounded-2xl border-2 border-centros/30 bg-centros/5 p-4 sm:p-5' : ''">
    <div v-if="conCabecera" class="flex flex-wrap items-start gap-x-3 gap-y-2">
      <span class="shrink-0 w-9 h-9 rounded-xl bg-centros text-white flex items-center justify-center" aria-hidden="true"><ClipboardDocumentCheckIcon class="h-5 w-5" /></span>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-black text-centros">Evaluación curricular (RA/CE)</p>
        <p class="text-[11px] text-gray-500 leading-snug">
          Valora el nivel alcanzado en cada resultado de aprendizaje. Se usa para el diagnóstico final.
        </p>
      </div>
      <!-- En móvil la etiqueta (y «Editar») bajan a su línea, alineadas con el texto (pl = icono + gap) -->
      <div class="basis-full sm:basis-auto shrink-0 pl-12 sm:pl-0 flex flex-wrap items-center gap-2">
        <span :class="['inline-block px-2 py-0.5 rounded-full text-[10px] font-black',
                       evaluada ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
          {{ evaluada ? 'Evaluada' : 'Pendiente de evaluar' }}
        </span>
        <button v-if="lecturaSiEvaluada && enLectura && puedeEditar" type="button" @click="editandoInterno = true"
                class="inline-flex h-9 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
          <PencilSquareIcon class="h-4 w-4 shrink-0" aria-hidden="true" /> Editar evaluación
        </button>
      </div>
    </div>

    <!-- Solo lectura: lo guardado, sin campos -->
    <template v-if="enLectura">
      <p v-if="!evaluada" class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-3 text-sm text-texto-secundario">
        Este grupo todavía no tiene evaluación curricular.
      </p>
      <p v-if="!form.ras.length" class="text-sm italic text-texto-secundario">El reto ajustado no tiene RA/CE oficiales asignados todavía.</p>
      <ul v-else class="space-y-2">
        <li v-for="(r, idx) in form.ras" :key="idx" class="rounded-xl border border-borde bg-white p-3">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <p class="min-w-0 break-words text-sm font-medium text-azul-noche">{{ r.ra }}</p>
            <span class="shrink-0 self-start rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="r.nivel ? NIVEL_CLASES[r.nivel] : 'bg-gray-100 text-texto-secundario'">{{ etiquetaNivel(r.nivel) }}</span>
          </div>
          <p v-if="r.observaciones" class="mt-1.5 break-words text-xs text-texto-secundario">{{ r.observaciones }}</p>
        </li>
      </ul>
      <div v-if="form.observaciones_docente" class="rounded-xl border border-amber-100 bg-amber-50 p-3">
        <p class="text-xs font-semibold text-amber-800">Observaciones generales</p>
        <p class="mt-1 whitespace-pre-line break-words text-sm text-amber-900">{{ form.observaciones_docente }}</p>
      </div>
      <!-- La nota grande ya va al final de la tarjeta del grupo; aquí, en una línea -->
      <p class="text-sm text-azul-noche">
        <span class="font-semibold">Nota final:</span>
        <template v-if="tieneNota"> {{ Number(form.nota_docente).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}/10</template>
        <span v-else class="text-texto-secundario"> sin nota</span>
      </p>
    </template>

    <!-- Edición. fieldset disabled: un colaborador de solo lectura ve la evaluación pero no la cambia -->
    <fieldset v-else :disabled="!puedeEditar" class="min-w-0 space-y-3">
      <p v-if="!form.ras.length" class="text-xs text-gray-400 italic">
        El reto ajustado no tiene RA/CE oficiales asignados todavía.
      </p>

      <div v-for="(r, idx) in form.ras" :key="idx"
           class="bg-white border border-centros/10 rounded-xl p-3 space-y-2">
        <p class="text-xs font-semibold text-[#1F2937] break-words">{{ r.ra }}</p>
        <div class="flex flex-wrap gap-1.5" role="radiogroup" :aria-label="`Nivel alcanzado: ${r.ra}`">
          <button v-for="op in NIVEL_OPCIONES" :key="op.value" type="button"
                  role="radio" :aria-checked="r.nivel === op.value"
                  @click="r.nivel = op.value"
                  :class="['inline-flex min-h-9 items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider transition-all',
                           r.nivel === op.value ? 'bg-emerald-500 text-white' : 'bg-white border border-gray-200 text-gray-500 hover:border-emerald-300']">
            {{ op.label }}
          </button>
        </div>
        <input v-model="r.observaciones" type="text" placeholder="Observaciones (opcional)" :aria-label="`Observaciones: ${r.ra}`"
               class="w-full text-xs border border-gray-200 rounded-xl px-2.5 py-1.5 bg-white
                      focus:outline-none focus:border-emerald-400"/>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <label :for="`nota-${equipo.id}`" class="text-xs font-black text-gray-500 uppercase tracking-wider shrink-0">Nota</label>
        <input :id="`nota-${equipo.id}`" v-model.number="form.nota_docente" type="number" min="0" max="10" step="0.1"
               class="w-20 text-sm border border-gray-200 rounded-xl px-2 py-1.5
                      focus:outline-none focus:border-emerald-400"/>
        <span class="text-xs text-gray-400">/ 10 (opcional)</span>
      </div>
      <textarea v-model="form.observaciones_docente" rows="2" aria-label="Observaciones generales"
                placeholder="Observaciones generales del reto ajustado (opcional)"
                class="w-full text-xs border border-gray-200 rounded-xl px-2.5 py-1.5 bg-white resize-none
                       focus:outline-none focus:border-emerald-400"/>

      <p v-if="error" role="alert" class="text-xs text-red-500 font-semibold">{{ error }}</p>

      <button v-if="puedeEditar" type="button" @click="enviar"
              :disabled="!puedeEnviar || guardando"
              :class="['w-full py-2.5 rounded-full text-xs font-black uppercase tracking-wider transition-all',
                       puedeEnviar ? 'bg-emerald-500 text-white hover:bg-emerald-600' : 'bg-gray-100 text-gray-300 cursor-not-allowed']">
        {{ guardando ? 'Guardando…' : (evaluada ? 'Actualizar evaluación' : 'Guardar evaluación') }}
      </button>
      <button v-if="lecturaSiEvaluada && evaluada && puedeEditar" type="button" @click="cancelarEdicion" :disabled="guardando"
              class="accion-secundaria">Cancelar</button>
    </fieldset>
  </div>
</template>
