<!-- «Empresa para esta petición» en la ficha de una solicitud de retos (Central). Mismos filtros que el
     buscador de empresas del generador: tipo (ficticias / reales), familia y búsqueda por nombre, sector
     o centro. Empieza con la familia de la petición (la que encaja con sus RA/CE); «Todas las familias»
     carga el resto. Cada resultado enseña sector, familias y centro para saber de quién es.
     v-model = id de la empresa elegida ('' = sin elegir). Backend: SolicitudRetoService::empresasParaPeticion. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { getEmpresasPeticion } from '../../src/services/solicitudRetoService.js'

const props = defineProps({
  solicitudId: { type: Number, required: true },
  bloque:      { type: Object, required: true },   // { indice, familia, empresa }
})
const elegida = defineModel({ type: [Number, String], default: '' })

const deLaFamilia = ref(null)   // empresas de la familia de la petición
const todas = ref(null)         // empresas de cualquier familia (se cargan al pedirlas)
const cargando = ref(false)
const error = ref('')

const tipo = ref('')
const familia = ref(props.bloque.familia || '')
const buscar = ref('')

async function cargar(conTodas) {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await getEmpresasPeticion(props.solicitudId, props.bloque.indice, conTodas)
    if (conTodas) todas.value = data.data
    else deLaFamilia.value = data.data
  } catch {
    error.value = 'No se han podido cargar las empresas.'
  } finally {
    cargando.value = false
  }
}
onMounted(() => cargar(false))
watch(familia, (f) => { if (f !== props.bloque.familia && !todas.value) cargar(true) })

const base = computed(() => (familia.value === props.bloque.familia ? deLaFamilia.value : todas.value) ?? [])
const familias = computed(() => {
  const nombres = new Set([props.bloque.familia, ...(todas.value ?? []).flatMap(e => e.familias ?? [])].filter(Boolean))
  return [...nombres].sort((a, b) => a.localeCompare(b, 'es'))
})
const norm = (t) => (t ?? '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
const resultados = computed(() => {
  const q = norm(buscar.value.trim())
  return base.value.filter(e =>
    (tipo.value === '' || (tipo.value === 'simulada') === e.es_simulada)
    && (!familia.value || familia.value === props.bloque.familia || (e.familias ?? []).includes(familia.value))
    && (!q || [e.nombre, e.sector, e.centro, ...(e.familias ?? [])].some(c => norm(c).includes(q))))
})
const MAX = 50
const actual = computed(() => [...(deLaFamilia.value ?? []), ...(todas.value ?? [])].find(e => e.id === elegida.value)
  ?? (props.bloque.empresa?.id === elegida.value ? props.bloque.empresa : null))
const fueraDeFamilia = computed(() => actual.value?.familias && props.bloque.familia && !actual.value.familias.includes(props.bloque.familia))

const TIPOS = [{ v: '', t: 'Todas' }, { v: 'simulada', t: 'Ficticias' }, { v: 'real', t: 'Reales' }]
</script>

<template>
  <div class="space-y-2">
    <!-- Elegida -->
    <div class="flex flex-wrap items-center gap-2 text-sm">
      <span class="font-semibold">Elegida:</span>
      <template v-if="elegida && actual">
        <span class="min-w-0 break-words font-bold text-azul-noche">{{ actual.nombre }}</span>
        <button type="button" class="inline-flex min-h-9 items-center rounded-full border border-borde bg-white px-3 text-xs font-semibold text-texto-secundario hover:bg-gray-50" @click="elegida = ''">Quitar</button>
      </template>
      <span v-else class="text-texto-secundario">ninguna</span>
    </div>
    <p v-if="fueraDeFamilia" class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900" role="note">
      Esta empresa no es de {{ bloque.familia }}. Se puede generar igual, pero el reto encajará peor con los RA/CE de la petición.
    </p>

    <!-- Filtros, como en el generador -->
    <!-- Tipo y familia en una fila (saltan de línea si no caben) y el buscador debajo, a todo el ancho:
         en la columna de la ficha no hay sitio para los tres en fila -->
    <div class="flex flex-col gap-2">
      <div class="flex flex-wrap items-center gap-2">
      <div class="flex shrink-0 flex-wrap gap-1.5" role="group" aria-label="Tipo de empresa">
        <button v-for="o in TIPOS" :key="o.v" type="button" :aria-pressed="tipo === o.v" @click="tipo = o.v"
                class="min-h-9 rounded-full border px-4 text-xs font-bold transition-colors"
                :class="tipo === o.v ? 'border-azul-noche bg-azul-noche text-white' : 'border-borde bg-white text-texto-secundario hover:border-gray-400'">{{ o.t }}</button>
      </div>
      <label class="sr-only" :for="`emp-familia-${bloque.indice}`">Familia</label>
      <!-- .field-input fija width:100%: el ancho lo da este contenedor -->
      <div class="w-full min-w-0 sm:w-64">
        <select :id="`emp-familia-${bloque.indice}`" v-model="familia" class="field-input">
          <option v-if="bloque.familia" :value="bloque.familia">{{ bloque.familia }} (la de la petición)</option>
          <option value="">Todas las familias</option>
          <option v-for="f in familias.filter(f => f !== bloque.familia)" :key="f" :value="f">{{ f }}</option>
        </select>
      </div>
      </div>
      <label class="sr-only" :for="`emp-buscar-${bloque.indice}`">Buscar empresa</label>
      <input :id="`emp-buscar-${bloque.indice}`" v-model="buscar" type="search" autocomplete="off"
             placeholder="Buscar por nombre, sector, familia o centro…" class="field-input" />
    </div>

    <!-- Resultados -->
    <p v-if="error" class="text-xs font-semibold text-red-700" role="alert">{{ error }}</p>
    <p v-else-if="cargando && !base.length" class="text-xs text-texto-secundario" role="status">Cargando empresas…</p>
    <p v-else-if="!resultados.length" class="text-xs text-texto-secundario">
      {{ base.length ? 'Ninguna empresa con estos filtros.' : `No hay empresas de ${bloque.familia}: elige «Todas las familias» o créala en el generador.` }}
    </p>
    <ul v-else class="max-h-72 space-y-1 overflow-y-auto rounded-xl border border-borde bg-white p-1" role="listbox" :aria-label="`Empresas para la petición ${bloque.indice + 1}`">
      <li v-for="e in resultados.slice(0, MAX)" :key="e.id">
        <button type="button" role="option" :aria-selected="elegida === e.id" @click="elegida = e.id"
                class="flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left transition-colors"
                :class="elegida === e.id ? 'bg-rol/10' : 'hover:bg-gray-50'">
          <span class="mt-1 h-3.5 w-3.5 shrink-0 rounded-full border-2" :class="elegida === e.id ? 'border-rol bg-rol' : 'border-gray-300'" aria-hidden="true" />
          <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-1.5">
              <span class="min-w-0 break-words text-sm font-bold text-azul-noche">{{ e.nombre }}</span>
              <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                    :class="e.es_simulada ? 'bg-gray-100 text-texto-secundario' : 'bg-centros/10 text-centros'">{{ e.es_simulada ? 'Ficticia' : 'Real' }}</span>
            </span>
            <span class="block break-words text-xs text-texto-secundario">
              {{ [e.sector, (e.familias ?? []).join(', '), e.centro].filter(Boolean).join(' · ') }}
            </span>
          </span>
        </button>
      </li>
      <li v-if="resultados.length > MAX" class="px-3 py-2 text-xs text-texto-secundario">
        Y {{ resultados.length - MAX }} más: afina la búsqueda.
      </li>
    </ul>
  </div>
</template>
