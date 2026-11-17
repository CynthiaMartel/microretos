<!-- Planificación de sesiones de un encuentro: qué fase(s) del workspace (F0–F4) se trabajan en
     cada sesión. v-model = [{ fases: [0, 1] }, …] (encuentros.planificacion_sesiones). Una sesión
     puede cubrir varias fases; la duración de cada fase y la fecha fin estimada se derivan de aquí.
     Antes vivía en el paso 5 del Taller de Ideas (la propuesta); ahora es de cada encuentro. -->
<script setup>
import { computed } from 'vue'
import { FASES_PROYECTO, COLOR_MAP_FASES, duracionPorFase } from '../config/fasesProyecto.js'

const sesiones = defineModel({ type: Array, default: () => [] })
// Cómo se llama cada unidad: sesiones en un encuentro creativo, hitos en el cronograma de un plan
const props = defineProps({
  unidad:  { type: String, default: 'sesión' },
  unidades: { type: String, default: 'sesiones' },
  // Cronograma de un plan: cada hito lleva su fecha real (obligatoria y en orden)
  conFecha: { type: Boolean, default: false },
  fechaMinima: { type: String, default: '' },
})
const Unidad = computed(() => props.unidad.charAt(0).toUpperCase() + props.unidad.slice(1))

const total = computed(() => sesiones.value.length)
const sinFases = computed(() => sesiones.value.some(s => !(s.fases || []).length))

function anadir() { sesiones.value = [...sesiones.value, props.conFecha ? { fases: [], fecha: '' } : { fases: [] }] }
function ponerFecha(i, fecha) { sesiones.value = sesiones.value.map((s, j) => j === i ? { ...s, fecha } : s) }
// Fecha que no puede ser anterior a la del hito previo (ni al inicio de las prácticas)
const minimaDe = (i) => (i > 0 && sesiones.value[i - 1]?.fecha) || props.fechaMinima || undefined
const fechaMal = (i) => props.conFecha && (!sesiones.value[i]?.fecha || (minimaDe(i) && sesiones.value[i].fecha < minimaDe(i)))
function quitar(i) { sesiones.value = sesiones.value.filter((_, j) => j !== i) }
function alternar(i, num) {
  sesiones.value = sesiones.value.map((s, j) => {
    if (j !== i) return s
    const fases = (s.fases || []).includes(num) ? s.fases.filter(f => f !== num) : [...(s.fases || []), num].sort()
    return { ...s, fases }
  })
}
</script>

<template>
  <div class="space-y-4">
    <!-- Resumen: sesiones por fase -->
    <!-- Etiquetas que bajan de línea según el ancho del contenedor (no del viewport): el editor
         vive en columnas estrechas (crear encuentro, detalle del encuentro) -->
    <ul class="flex flex-wrap gap-2" aria-label="Sesiones por fase">
      <li v-for="f in FASES_PROYECTO" :key="f.num"
          class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-3 py-1" :class="COLOR_MAP_FASES[f.color]">
        <span class="shrink-0 text-sm leading-none" aria-hidden="true">{{ f.icono }}</span>
        <span class="min-w-0 truncate text-xs font-bold text-azul-noche">F{{ f.num }} · {{ f.label }}</span>
        <span class="shrink-0 text-xs font-bold">· {{ duracionPorFase(sesiones, f.num) }}</span>
      </li>
    </ul>

    <p class="text-sm text-texto-secundario">
      Marca qué fase(s) se trabajan en cada {{ unidad }}: {{ unidad === 'hito' ? 'un mismo hito' : 'una misma sesión' }} puede cubrir varias fases.
    </p>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
      <div v-for="(s, i) in sesiones" :key="i" class="min-w-0 rounded-2xl border border-borde bg-white p-3 sm:p-4">
        <div class="mb-2 flex items-center justify-between gap-2">
          <p class="text-sm font-bold text-azul-noche">{{ Unidad }} {{ i + 1 }}</p>
          <button type="button" @click="quitar(i)" :aria-label="`Quitar ${unidad} ${i + 1}`" :title="`Quitar ${unidad}`"
                  class="-mr-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-texto-secundario hover:bg-red-50 hover:text-red-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div v-if="conFecha" class="mb-2">
          <label :for="`fecha-hito-${i}`" class="text-xs font-semibold text-azul-noche">Fecha</label>
          <input :id="`fecha-hito-${i}`" type="date" :value="s.fecha || ''" :min="minimaDe(i)" @input="ponerFecha(i, $event.target.value)"
                 class="mt-1 w-full rounded-xl px-3 py-1.5 text-sm" :aria-invalid="fechaMal(i)" />
          <p v-if="fechaMal(i)" class="mt-1 text-xs font-medium text-amber-800">
            {{ !s.fecha ? 'Pon la fecha del hito.' : 'Tiene que ser posterior al hito anterior y al inicio.' }}
          </p>
        </div>
        <div class="space-y-1.5" role="group" :aria-label="`Fases de ${unidad} ${i + 1}`">
          <button v-for="f in FASES_PROYECTO" :key="f.num" type="button" @click="alternar(i, f.num)"
                  :aria-pressed="(s.fases || []).includes(f.num)"
                  class="flex min-h-9 w-full items-center gap-2.5 rounded-xl border-2 px-3 py-1.5 text-left transition-colors"
                  :class="(s.fases || []).includes(f.num)
                    ? COLOR_MAP_FASES[f.color] + ' border-current'
                    : 'border-transparent bg-gray-50 text-texto-secundario hover:bg-gray-100'">
            <span class="shrink-0 text-base leading-none" aria-hidden="true">{{ f.icono }}</span>
            <span class="min-w-0 flex-1 text-xs font-bold" :class="(s.fases || []).includes(f.num) ? 'text-azul-noche' : ''">F{{ f.num }} - {{ f.label }}</span>
            <span v-if="(s.fases || []).includes(f.num)" class="shrink-0 text-sm font-bold text-rol-fuerte" aria-hidden="true">✓</span>
          </button>
        </div>
        <p v-if="!(s.fases || []).length" class="mt-2 text-xs font-medium text-amber-800">Marca al menos una fase.</p>
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <button type="button" @click="anadir"
              class="inline-flex min-h-9 items-center rounded-full border border-dashed border-rol/40 px-4 py-2 text-xs font-bold uppercase tracking-widest text-rol-fuerte transition-colors hover:bg-rol/5">
        + Añadir {{ unidad }}
      </button>
      <p class="text-sm font-bold text-azul-noche" aria-live="polite">
        Total: {{ total }} {{ total === 1 ? unidad : unidades }}
        <span v-if="sinFases" class="ml-1 font-medium text-amber-800">· hay {{ unidades }} sin fase</span>
      </p>
    </div>
  </div>
</template>
