<!-- Una petición del paso 2 de la solicitud de retos (SolicitudRetoDocente.vue): familia, ciclo, curso,
     módulos y RA/CE. El docente puede añadir varias («¿Quieres otro más?»); cada una se lleva luego
     al generador por separado. Carga sus propios ciclos y módulos; al reabrir el enlace los recarga
     sin borrar lo guardado. Edita el bloque que recibe (v-model:bloque). -->
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import SelectorRaCeModulos from './SelectorRaCeModulos.vue'
import { getCiclosDeFamilia, getModulosDeCiclo } from '../services/datosFPService.js'

const bloque = defineModel('bloque', { type: Object, required: true })
const props = defineProps({
  indice:     { type: Number, required: true },
  familias:   { type: Array, required: true },
  puedeQuitar: { type: Boolean, default: false },
  // (campo) => mensaje de error del backend para este bloque ('' si no hay)
  errorDe:    { type: Function, default: () => '' },
})
const emit = defineEmits(['quitar'])

const ciclos = ref([])
const modulos = ref([])
const idBase = computed(() => `sr-b${props.indice}`)

const familiaNombre = computed(() => props.familias.find(f => f.id === Number(bloque.value.familiaId))?.nombre || '')
const modulosDelCurso = computed(() => {
  const curso = bloque.value.curso
  if (!curso) return []
  return curso === 'ambos_cursos' ? modulos.value : modulos.value.filter(m => String(m.curso) === curso)
})
const modulosElegidos = computed(() => modulos.value
  .filter(m => bloque.value.moduloIds.includes(m.id))
  .map(m => ({ id: m.id, nombre: m.nombre, curso: m.curso })))

// Al montar (bloque nuevo o restaurado) se cargan los desplegables sin que los watch borren nada
let restaurando = true
onMounted(async () => {
  try {
    if (familiaNombre.value) ciclos.value = (await getCiclosDeFamilia(familiaNombre.value)).data.filter(c => c.con_datos_retos)
    if (bloque.value.cicloId) modulos.value = (await getModulosDeCiclo(bloque.value.cicloId)).data
  } finally {
    setTimeout(() => { restaurando = false })
  }
})
// Las familias llegan después del montaje la primera vez: entonces se cargan los ciclos del bloque restaurado
watch(() => props.familias.length, async () => {
  if (familiaNombre.value && !ciclos.value.length) ciclos.value = (await getCiclosDeFamilia(familiaNombre.value)).data.filter(c => c.con_datos_retos)
})

watch(() => bloque.value.familiaId, async () => {
  if (restaurando) return
  Object.assign(bloque.value, { cicloId: '', moduloIds: [], seleccionRaCe: {} })
  ciclos.value = []; modulos.value = []
  if (familiaNombre.value) ciclos.value = (await getCiclosDeFamilia(familiaNombre.value)).data.filter(c => c.con_datos_retos)
})
watch(() => bloque.value.cicloId, async (id) => {
  if (restaurando) return
  Object.assign(bloque.value, { moduloIds: [], seleccionRaCe: {} })
  modulos.value = []
  if (id) modulos.value = (await getModulosDeCiclo(id)).data
})
watch(() => bloque.value.curso, () => {
  if (restaurando) return
  Object.assign(bloque.value, { moduloIds: [], seleccionRaCe: {} })
})
// Quitar un módulo quita sus RA/CE
watch(() => [...bloque.value.moduloIds], (ids) => {
  bloque.value.seleccionRaCe = Object.fromEntries(Object.entries(bloque.value.seleccionRaCe).filter(([, s]) => ids.includes(s.moduloId)))
})

function alternarModulo(id) {
  const ids = bloque.value.moduloIds
  bloque.value.moduloIds = ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id]
}
const error = (campo) => props.errorDe(`bloques.${props.indice}.${campo}`)
</script>

<template>
  <div class="space-y-5">
    <div v-if="indice > 0 || puedeQuitar" class="flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm font-black uppercase tracking-wide text-centros">Petición {{ indice + 1 }}</p>
      <button v-if="puedeQuitar" type="button" class="inline-flex min-h-9 items-center gap-1.5 rounded-full border-2 border-gray-200 bg-white px-4 text-xs font-bold text-gray-600 hover:border-red-200 hover:text-red-600"
              @click="emit('quitar')">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        Quitar esta petición
      </button>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
      <div class="min-w-0">
        <label :for="`${idBase}-familia`" class="label-style">Familia profesional *</label>
        <select :id="`${idBase}-familia`" v-model="bloque.familiaId" class="input-style">
          <option value="">Elige la familia</option>
          <option v-for="f in familias" :key="f.id" :value="f.id">{{ f.nombre }}</option>
        </select>
      </div>
      <div class="min-w-0">
        <label :for="`${idBase}-ciclo`" class="label-style">Ciclo *</label>
        <select :id="`${idBase}-ciclo`" v-model="bloque.cicloId" class="input-style" :disabled="!bloque.familiaId">
          <option value="">{{ bloque.familiaId ? 'Elige el ciclo' : 'Primero elige la familia' }}</option>
          <option v-for="c in ciclos" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
        <p v-if="error('cicloId')" class="mt-1 ml-4 text-xs text-red-600">{{ error('cicloId') }}</p>
      </div>
    </div>

    <div>
      <p class="label-style">Curso *</p>
      <div class="inline-flex flex-wrap gap-1 rounded-full bg-gray-100 p-1" role="radiogroup" aria-label="Curso">
        <button v-for="op in [{ v: '1', t: '1.º' }, { v: '2', t: '2.º' }, { v: 'ambos_cursos', t: 'Los dos' }]" :key="op.v"
                type="button" role="radio" :aria-checked="bloque.curso === op.v" :disabled="!bloque.cicloId"
                class="h-9 rounded-full px-4 text-sm font-bold transition-colors disabled:opacity-50"
                :class="bloque.curso === op.v ? 'bg-gradient-to-r from-centros to-primary-400 text-white shadow' : 'text-gray-600 hover:text-gray-900'"
                @click="bloque.curso = op.v">{{ op.t }}</button>
      </div>
    </div>

    <div v-if="bloque.curso">
      <p class="label-style">Módulos (opcional)</p>
      <p class="mb-3 ml-4 text-xs text-gray-500">
        Elige los que quieras trabajar. Si no eliges ninguno, los proponemos nosotros.
        <template v-if="bloque.curso === 'ambos_cursos'"> Con los dos cursos, elige al menos uno de 1.º y uno de 2.º.</template>
      </p>
      <div class="flex flex-wrap gap-2">
        <button v-for="m in modulosDelCurso" :key="m.id" type="button"
                :aria-pressed="bloque.moduloIds.includes(m.id)"
                class="min-h-9 rounded-full border-2 px-4 py-1.5 text-left text-xs font-bold transition-colors break-words"
                :class="bloque.moduloIds.includes(m.id) ? 'border-transparent bg-gradient-to-r from-centros to-primary-400 text-white shadow-md' : 'border-gray-200 bg-white text-gray-700 hover:border-centros/50'"
                @click="alternarModulo(m.id)">
          <span v-if="bloque.curso === 'ambos_cursos'" class="opacity-70">{{ m.curso }}.º · </span>{{ m.nombre }}
        </button>
      </div>
      <p v-if="!modulosDelCurso.length" class="mt-2 ml-4 text-xs italic text-gray-500">No hay módulos cargados para este curso.</p>
      <p v-if="error('moduloIds')" class="mt-2 ml-4 text-xs text-red-600">{{ error('moduloIds') }}</p>
    </div>

    <SelectorRaCeModulos v-if="modulosElegidos.length" v-model="bloque.seleccionRaCe" :modulos="modulosElegidos"
      titulo="Resultados de aprendizaje y criterios (opcional)"
      ayuda="Marca los RA y CE que quieres que trabaje el reto. Donde no marques nada, los elegimos nosotros." />
    <p v-if="error('seleccionRaCe')" class="ml-4 text-xs text-red-600">{{ error('seleccionRaCe') }}</p>
  </div>
</template>
