<script setup>
import { ref, computed, watch } from 'vue'
import { getRaCeModulo } from '../services/datosFPService.js'

// Selección manual de RA/CE sobre los módulos forzados del generador de retos.
// modelValue: { [raId]: { moduloId, ceIds: [] } } — un RA sin CE no se guarda.
// Un módulo sin nada marcado queda a elección de la IA (modo mixto en el backend).
const props = defineProps({
  modulos:    { type: Array, required: true },   // [{ id, nombre, curso }]
  modelValue: { type: Object, default: () => ({}) },
  maxRa:      { type: Number, default: 8 },      // = GenerarMicroretoRequest::MAX_RA_SELECCIONADOS
  maxCe:      { type: Number, default: 12 },     // = GenerarMicroretoRequest::MAX_CE_SELECCIONADOS
  disabled:   { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const rasPorModulo     = ref({})  // { moduloId: ras[] }
const cargando         = ref({})  // { moduloId: true }
const errorCarga       = ref({})  // { moduloId: true }
const moduloExpandido  = ref({})  // { moduloId: true }
const raExpandido      = ref({})  // { raId: true }
const avisoLimite      = ref('')

const cargarModulo = async (moduloId) => {
  if (rasPorModulo.value[moduloId] || cargando.value[moduloId]) return
  cargando.value = { ...cargando.value, [moduloId]: true }
  errorCarga.value = { ...errorCarga.value, [moduloId]: false }
  try {
    const { data } = await getRaCeModulo(moduloId)
    // RA sin CE = módulo aún no importado del BOE; el backend tampoco los ofrece a la IA.
    rasPorModulo.value = { ...rasPorModulo.value, [moduloId]: (data.ra || []).filter(ra => ra.criterios?.length) }
  } catch {
    errorCarga.value = { ...errorCarga.value, [moduloId]: true }
  } finally {
    cargando.value = { ...cargando.value, [moduloId]: false }
  }
}

watch(() => props.modulos.map(m => m.id), (ids) => ids.forEach(cargarModulo), { immediate: true })

const totalRa = computed(() => Object.keys(props.modelValue).length)
const totalCe = computed(() => Object.values(props.modelValue).reduce((n, s) => n + s.ceIds.length, 0))

const resumenModulo = (moduloId) => {
  const sel = Object.values(props.modelValue).filter(s => s.moduloId === moduloId)
  return { ra: sel.length, ce: sel.reduce((n, s) => n + s.ceIds.length, 0) }
}

const raEstado = (ra) => {
  const n = props.modelValue[ra.id]?.ceIds.length ?? 0
  if (n === 0) return 'none'
  return n === ra.criterios.length ? 'all' : 'some'
}
const ceMarcado = (ra, ceId) => props.modelValue[ra.id]?.ceIds.includes(ceId) ?? false

const avisar = (texto) => {
  avisoLimite.value = texto
  setTimeout(() => { if (avisoLimite.value === texto) avisoLimite.value = '' }, 4000)
}

const aplicar = (raId, moduloId, ceIds) => {
  const nuevo = { ...props.modelValue }
  if (ceIds.length === 0) delete nuevo[raId]
  else nuevo[raId] = { moduloId, ceIds }
  emit('update:modelValue', nuevo)
}

const toggleCe = (moduloId, ra, ceId) => {
  if (props.disabled) return
  const actuales = props.modelValue[ra.id]?.ceIds ?? []
  if (actuales.includes(ceId)) return aplicar(ra.id, moduloId, actuales.filter(id => id !== ceId))
  if (actuales.length === 0 && totalRa.value >= props.maxRa) return avisar(`Máximo ${props.maxRa} RA por reto.`)
  if (totalCe.value >= props.maxCe) return avisar(`Máximo ${props.maxCe} criterios de evaluación por reto.`)
  aplicar(ra.id, moduloId, [...actuales, ceId])
}

// Marca todos los CE del RA (o los desmarca si ya estaban todos).
const toggleRa = (moduloId, ra) => {
  if (props.disabled) return
  if (raEstado(ra) === 'all') return aplicar(ra.id, moduloId, [])
  const actuales = props.modelValue[ra.id]?.ceIds ?? []
  if (actuales.length === 0 && totalRa.value >= props.maxRa) return avisar(`Máximo ${props.maxRa} RA por reto.`)
  const faltan = ra.criterios.length - actuales.length
  if (totalCe.value + faltan > props.maxCe) {
    raExpandido.value = { ...raExpandido.value, [ra.id]: true }
    return avisar(`No caben todos los CE de este RA (máximo ${props.maxCe}). Marca solo los que quieras trabajar.`)
  }
  aplicar(ra.id, moduloId, ra.criterios.map(ce => ce.id))
  raExpandido.value = { ...raExpandido.value, [ra.id]: true }
}

const vaciarModulo = (moduloId) => {
  if (props.disabled) return
  const nuevo = Object.fromEntries(Object.entries(props.modelValue).filter(([, s]) => s.moduloId !== moduloId))
  emit('update:modelValue', nuevo)
}

const toggleModulo = (moduloId) => {
  moduloExpandido.value = { ...moduloExpandido.value, [moduloId]: !moduloExpandido.value[moduloId] }
}
const toggleRaExpandido = (raId) => {
  raExpandido.value = { ...raExpandido.value, [raId]: !raExpandido.value[raId] }
}
</script>

<template>
  <div class="mt-5 pt-5 border-t border-gray-200">
    <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
      <div>
        <p class="label-style !mb-1 !ml-0">Fijar RA y CE (Opcional)</p>
        <p class="text-xs text-gray-500 leading-relaxed max-w-xl">
          Marca los RA/CE que el reto debe trabajar <strong>sí o sí</strong>: serán los mismos en todas las variantes.
          En los módulos donde no marques nada, la IA elegirá los RA/CE (distintos en cada variante).
        </p>
      </div>
      <div class="shrink-0 flex items-center gap-2 text-[11px] font-black">
        <span class="px-2.5 py-1 rounded-full bg-centros/10 text-centros">{{ totalRa }}/{{ maxRa }} RA</span>
        <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">{{ totalCe }}/{{ maxCe }} CE</span>
      </div>
    </div>

    <p v-if="avisoLimite" class="text-xs font-bold text-red-500 mb-3">{{ avisoLimite }}</p>

    <div class="space-y-2">
      <div v-for="mod in modulos" :key="mod.id" class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
        <!-- Cabecera del módulo -->
        <div class="flex flex-wrap items-center gap-2 px-3 sm:px-4 py-3 cursor-pointer select-none hover:bg-gray-50"
             @click="toggleModulo(mod.id)">
          <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 transition-transform duration-200"
               :class="moduloExpandido[mod.id] ? 'rotate-90' : ''"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
          <span class="flex-1 min-w-[9rem] text-xs font-bold text-gray-700 break-words">{{ mod.nombre }}</span>
          <span v-if="mod.curso" class="shrink-0 text-[9px] font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full">{{ mod.curso }}º</span>
          <span v-if="resumenModulo(mod.id).ra > 0"
                class="shrink-0 text-[10px] font-black text-white bg-centros px-2.5 py-0.5 rounded-full">
            {{ resumenModulo(mod.id).ra }} RA · {{ resumenModulo(mod.id).ce }} CE fijados
          </span>
          <span v-else class="shrink-0 text-[10px] font-bold text-gray-400 bg-gray-100 px-2.5 py-0.5 rounded-full">La IA elegirá</span>
          <button v-if="resumenModulo(mod.id).ra > 0 && !disabled" type="button"
                  class="shrink-0 text-[10px] font-bold text-red-500 hover:underline ml-1"
                  @click.stop="vaciarModulo(mod.id)">Quitar</button>
        </div>

        <!-- RA del módulo -->
        <div v-if="moduloExpandido[mod.id]" class="border-t border-gray-100">
          <p v-if="cargando[mod.id]" class="px-4 py-3 text-xs text-gray-400 italic">Cargando RA/CE…</p>
          <p v-else-if="errorCarga[mod.id]" class="px-4 py-3 text-xs text-red-500">
            No se pudieron cargar los RA/CE.
            <button type="button" class="font-bold underline" @click="cargarModulo(mod.id)">Reintentar</button>
          </p>
          <p v-else-if="!rasPorModulo[mod.id]?.length" class="px-4 py-3 text-xs text-gray-400 italic">
            Este módulo no tiene RA/CE cargados todavía; la IA no podrá asignarle criterios.
          </p>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="ra in rasPorModulo[mod.id]" :key="ra.id" :class="raEstado(ra) !== 'none' ? 'bg-centros/5' : ''">
              <div class="flex items-start gap-2 sm:gap-2.5 px-3 sm:px-4 py-3">
                <button type="button" :disabled="disabled" @click="toggleRa(mod.id, ra)"
                        class="mt-0.5 shrink-0 w-4 h-4 rounded border-2 flex items-center justify-center transition-all"
                        :class="raEstado(ra) === 'all' ? 'bg-centros border-centros'
                          : raEstado(ra) === 'some' ? 'bg-centros/30 border-centros'
                          : 'bg-white border-gray-300 hover:border-centros/50'">
                  <svg v-if="raEstado(ra) !== 'none'" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="raEstado(ra) === 'all'" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 12h14"/>
                  </svg>
                </button>
                <span class="shrink-0 mt-0.5 text-[9px] font-black uppercase tracking-widest text-centros bg-centros/10 px-2 py-0.5 rounded-full">RA{{ ra.orden }}</span>
                <p class="flex-1 text-[11px] font-semibold text-gray-700 leading-snug">{{ ra.descripcion }}</p>
                <button type="button" @click="toggleRaExpandido(ra.id)"
                        class="shrink-0 mt-0.5 ml-1 flex items-center gap-1 text-[10px] text-gray-400 hover:text-gray-600">
                  {{ ra.criterios.length }} CE
                  <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="raExpandido[ra.id] ? 'rotate-180' : ''"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
              </div>

              <div v-if="raExpandido[ra.id]" class="px-3 sm:px-4 pb-3 pl-8 sm:pl-11 space-y-1.5">
                <label v-for="ce in ra.criterios" :key="ce.id" class="flex items-start gap-2.5 group"
                       :class="disabled ? 'cursor-default' : 'cursor-pointer'">
                  <input type="checkbox" class="sr-only" :checked="ceMarcado(ra, ce.id)" :disabled="disabled"
                         @change="toggleCe(mod.id, ra, ce.id)" />
                  <span class="mt-0.5 shrink-0 w-3.5 h-3.5 rounded border-2 flex items-center justify-center transition-all"
                        :class="ceMarcado(ra, ce.id) ? 'bg-amber-400 border-amber-400' : 'bg-white border-gray-300 group-hover:border-amber-300'">
                    <svg v-if="ceMarcado(ra, ce.id)" class="w-2 h-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                  </span>
                  <span class="text-[11px] text-gray-600 leading-snug">
                    <span class="font-bold text-amber-500 mr-1">{{ ce.orden }}.</span>{{ ce.descripcion }}
                  </span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
