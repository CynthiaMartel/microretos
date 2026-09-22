<script setup>
import { ref, watch, computed } from 'vue'
import api from '../api.js'
import { useRoleTheme } from '../composables/useRoleTheme.js'

const { theme } = useRoleTheme()

const props = defineProps({
  show: Boolean,
})
const emit = defineEmits(['update:show'])

function cerrar() {
  emit('update:show', false)
}

// ─── Estado interno ─────────────────────────────────────
const boeFamilias   = ref([])
const cargandoBoe   = ref(false)
const boeFamiliaExp = ref(null)
const boeCicloExp   = ref({})
const boeModulosMap = ref({})   // { cicloId: modulos[] }
const boeModuloExp  = ref({})   // { 'cicloId-moduloId': true }
const boeRaCeMap    = ref({})   // { moduloId: { ra: [{orden,descripcion,criterios[]}] } }

const totalFamilias = computed(() => boeFamilias.value.length)
const totalCiclos   = computed(() =>
  boeFamilias.value.reduce((sum, f) => sum + (f.ciclos_count ?? 0), 0)
)

// Carga familias la primera vez que se abre el modal
watch(() => props.show, async (val) => {
  if (!val || boeFamilias.value.length > 0) return
  cargandoBoe.value = true
  try {
    const { data } = await api.get('/familias')
    boeFamilias.value = data.map(f => ({ ...f, ciclos: [], ciclosLoaded: false }))
  } catch { /* silencioso */ }
  finally { cargandoBoe.value = false }
})

async function toggleBoeFamilia(familiaId) {
  if (boeFamiliaExp.value === familiaId) { boeFamiliaExp.value = null; return }
  boeFamiliaExp.value = familiaId
  const f = boeFamilias.value.find(x => x.id === familiaId)
  if (!f || f.ciclosLoaded) return
  try {
    const { data } = await api.get(`/familias/${encodeURIComponent(f.nombre)}/ciclos`)
    f.ciclos = data
  } catch { f.ciclos = [] }
  finally { f.ciclosLoaded = true }
}

async function toggleBoeCiclo(familiaId, cicloId) {
  const key = `${familiaId}-${cicloId}`
  if (boeCicloExp.value[key]) {
    boeCicloExp.value = { ...boeCicloExp.value, [key]: false }
    return
  }
  boeCicloExp.value = { ...boeCicloExp.value, [key]: true }
  if (boeModulosMap.value[cicloId] !== undefined) return
  try {
    const { data } = await api.get(`/ciclos/${cicloId}/modulos`)
    boeModulosMap.value = { ...boeModulosMap.value, [cicloId]: data }
  } catch {
    boeModulosMap.value = { ...boeModulosMap.value, [cicloId]: [] }
  }
}

// Carga RA y CE del módulo — la API devuelve { ra: [{orden,descripcion,criterios:[]}] }
// Relación BD: resultados_aprendizaje.idmodulo → modulos.id
//              criterios_evaluacion.idmoduloRA  → resultados_aprendizaje.id
async function toggleBoeModulo(cicloId, moduloId) {
  const key = `${cicloId}-${moduloId}`
  if (boeModuloExp.value[key]) {
    boeModuloExp.value = { ...boeModuloExp.value, [key]: false }
    return
  }
  boeModuloExp.value = { ...boeModuloExp.value, [key]: true }
  if (boeRaCeMap.value[moduloId] !== undefined) return
  try {
    const { data } = await api.get(`/modulos/${moduloId}/ra-ce`)
    boeRaCeMap.value = { ...boeRaCeMap.value, [moduloId]: data }
  } catch {
    boeRaCeMap.value = { ...boeRaCeMap.value, [moduloId]: null }
  }
}
</script>

<template>
  <Transition name="modal-fade">
    <div v-if="show"
         class="fixed inset-0 z-[9000] flex items-start justify-center p-4 pt-10 bg-black/60 backdrop-blur-sm"
         @click.self="cerrar">
      <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl max-h-[85vh]
                  flex flex-col border border-gray-100 overflow-hidden">

        <!-- Cabecera fija -->
        <div class="flex items-center gap-3 px-7 py-5 border-b border-gray-100 shrink-0">
          <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-200
                      flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <h2 class="font-black text-lg text-[#1F2937]">Catálogo BOE — Solo lectura</h2>
            <p class="text-xs text-gray-400">Familias · Ciclos · Módulos · Resultados de Aprendizaje · Criterios de Evaluación</p>
          </div>
          <button @click="cerrar"
                  class="w-8 h-8 rounded-xl bg-gray-100 hover:bg-gray-200 flex items-center justify-center
                         text-gray-400 hover:text-gray-600 transition-all shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Contadores -->
        <div v-if="!cargandoBoe && totalFamilias > 0"
             class="px-7 py-2 border-b border-gray-100 shrink-0 flex items-center gap-3">
          <div class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 shrink-0" />
            <span class="text-[11px] font-black text-indigo-600">{{ totalFamilias }}</span>
            <span class="text-[11px] text-indigo-400">familias</span>
          </div>
          <div class="flex items-center gap-1.5 px-3 py-1 rounded-full border" :class="[theme.bg5, theme.border20]">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="theme.bg" />
            <span class="text-[11px] font-black" :class="theme.text">{{ totalCiclos }}</span>
            <span class="text-[11px]" :class="theme.text50">ciclos</span>
          </div>
        </div>

        <!-- Leyenda de relaciones BD -->
        <div class="px-7 py-2.5 bg-indigo-50/60 border-b border-indigo-100 shrink-0 flex flex-wrap gap-x-4 gap-y-1">
          <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Relaciones BD:</span>
          <span class="text-[10px] text-gray-500">
            <span class="font-bold text-indigo-400">modulos</span>
            <span class="mx-1 text-gray-300">→</span>
            <span class="font-bold" :class="theme.text">resultados_aprendizaje</span>
            <span class="text-gray-400 ml-1">( idmodulo )</span>
          </span>
          <span class="text-[10px] text-gray-500">
            <span class="font-bold" :class="theme.text">resultados_aprendizaje</span>
            <span class="mx-1 text-gray-300">→</span>
            <span class="font-bold text-amber-500">criterios_evaluacion</span>
            <span class="text-gray-400 ml-1">( idmoduloRA )</span>
          </span>
        </div>

        <!-- Cuerpo con scroll -->
        <div class="overflow-y-auto flex-1 px-6 py-4">

          <!-- Cargando familias -->
          <div v-if="cargandoBoe" class="flex flex-col items-center justify-center py-16 gap-3">
            <div class="w-10 h-10 rounded-full border-4 border-indigo-200 border-t-indigo-500 animate-spin" />
            <p class="text-sm text-gray-400">Cargando familias...</p>
          </div>

          <!-- Lista de familias -->
          <div v-else class="space-y-2">
            <div v-for="familia in boeFamilias" :key="familia.id"
                 class="rounded-2xl border border-gray-100 overflow-hidden">

              <!-- Cabecera familia -->
              <button
                @click="toggleBoeFamilia(familia.id)"
                class="w-full flex items-center gap-3 px-5 py-4 text-left
                       hover:bg-gray-50 transition-colors duration-150"
              >
                <div class="w-8 h-8 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                  </svg>
                </div>
                <span class="flex-1 font-bold text-sm text-[#1F2937]">{{ familia.nombre }}</span>
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 shrink-0"
                     :class="boeFamiliaExp === familia.id ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>

              <!-- Ciclos de la familia -->
              <div v-if="boeFamiliaExp === familia.id" class="border-t border-gray-100">

                <!-- Cargando ciclos -->
                <div v-if="!familia.ciclosLoaded"
                     class="flex items-center gap-2 px-6 py-3 text-xs text-gray-400">
                  <div class="w-3 h-3 rounded-full border-2 border-indigo-300 border-t-indigo-500 animate-spin" />
                  Cargando ciclos...
                </div>

                <!-- Sin ciclos -->
                <p v-else-if="familia.ciclos.length === 0"
                   class="px-6 py-3 text-xs text-gray-400 italic">Sin ciclos registrados</p>

                <!-- Lista de ciclos -->
                <div v-else>
                  <div v-for="ciclo in familia.ciclos" :key="ciclo.id"
                       class="border-b border-gray-50 last:border-b-0">

                    <!-- Cabecera ciclo -->
                    <button
                      @click="toggleBoeCiclo(familia.id, ciclo.id)"
                      class="w-full flex items-center gap-3 pl-8 pr-5 py-3 text-left
                             hover:bg-gray-50/60 transition-colors duration-150"
                    >
                      <div class="w-2 h-2 rounded-full bg-indigo-400 shrink-0" />
                      <span class="flex-1 text-sm text-gray-700 font-semibold">{{ ciclo.nombre }}</span>
                      <span v-if="ciclo.siglasGrado"
                            class="text-[10px] font-black uppercase tracking-widest
                                   bg-indigo-50 text-indigo-500 px-2 py-0.5 rounded-full shrink-0">
                        {{ ciclo.siglasGrado }}
                      </span>
                      <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200 ml-1 shrink-0"
                           :class="boeCicloExp[`${familia.id}-${ciclo.id}`] ? 'rotate-180' : ''"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                      </svg>
                    </button>

                    <!-- Módulos del ciclo -->
                    <div v-if="boeCicloExp[`${familia.id}-${ciclo.id}`]"
                         class="pl-12 pr-5 pb-3 pt-1">

                      <!-- Cargando módulos -->
                      <div v-if="boeModulosMap[ciclo.id] === undefined"
                           class="flex items-center gap-2 py-2 text-xs text-gray-400">
                        <div class="w-3 h-3 rounded-full border-2 border-indigo-200 border-t-indigo-400 animate-spin" />
                        Cargando módulos...
                      </div>

                      <!-- Sin módulos -->
                      <p v-else-if="boeModulosMap[ciclo.id].length === 0"
                         class="text-xs text-gray-400 italic py-2">Sin módulos registrados</p>

                      <!-- Lista de módulos -->
                      <div v-else class="space-y-1">
                        <div v-for="modulo in boeModulosMap[ciclo.id]" :key="modulo.id"
                             class="rounded-xl border border-gray-100 overflow-hidden">

                          <!-- Cabecera módulo -->
                          <button
                            @click="toggleBoeModulo(ciclo.id, modulo.id)"
                            class="w-full flex items-center gap-2 px-3 py-2.5 text-left
                                   bg-gray-50/60 hover:bg-gray-100/60 transition-colors duration-150"
                          >
                            <span class="text-[10px] font-black uppercase tracking-widest
                                         bg-indigo-100/60 text-indigo-500 px-2 py-0.5 rounded-full shrink-0">
                              MF
                            </span>
                            <span class="flex-1 text-xs font-semibold text-gray-600">{{ modulo.nombre }}</span>
                            <span v-if="modulo.horastotales"
                                  class="text-[9px] text-gray-400 shrink-0">{{ modulo.horastotales }}h</span>
                            <svg class="w-3 h-3 text-gray-400 transition-transform duration-200 shrink-0"
                                 :class="boeModuloExp[`${ciclo.id}-${modulo.id}`] ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                          </button>

                          <!-- RA/CE del módulo -->
                          <div v-if="boeModuloExp[`${ciclo.id}-${modulo.id}`]"
                               class="px-3 pb-3 pt-2 border-t border-gray-100">

                            <!-- Cargando RA/CE -->
                            <div v-if="boeRaCeMap[modulo.id] === undefined"
                                 class="flex items-center gap-2 py-2 text-xs text-gray-400">
                              <div class="w-3 h-3 rounded-full border-2 border-gray-200 border-t-gray-400 animate-spin" />
                              Cargando RA y CE...
                            </div>

                            <!-- Error o vacío -->
                            <p v-else-if="boeRaCeMap[modulo.id] === null || !boeRaCeMap[modulo.id]?.ra?.length"
                               class="text-xs text-gray-400 italic py-1">Sin datos de RA/CE registrados</p>

                            <!-- Resultados de Aprendizaje con sus CE anidados -->
                            <!--
                              BD: resultados_aprendizaje.idmodulo = modulo.id
                                  criterios_evaluacion.idmoduloRA = resultadoAprendizaje.id
                            -->
                            <div v-else class="space-y-3 mt-1">

                              <!-- Etiqueta de relación -->
                              <div class="flex items-center gap-2 mb-1">
                                <span class="text-[9px] font-black uppercase tracking-widest text-indigo-400">
                                  Módulo ID {{ modulo.id }}
                                </span>
                                <span class="text-[9px] text-gray-300">·</span>
                                <span class="text-[9px] text-gray-400">
                                  {{ boeRaCeMap[modulo.id].ra.length }} resultado{{ boeRaCeMap[modulo.id].ra.length !== 1 ? 's' : '' }} de aprendizaje
                                </span>
                              </div>

                              <div v-for="ra in boeRaCeMap[modulo.id].ra" :key="ra.id"
                                   class="rounded-xl border overflow-hidden" :class="[theme.border20, theme.bg5]">

                                <!-- Cabecera RA -->
                                <div class="flex items-start gap-2 px-3 py-2.5">
                                  <div class="flex items-center gap-1.5 shrink-0 mt-0.5">
                                    <span class="text-[9px] font-black uppercase tracking-widest
                                                 px-2 py-0.5 rounded-full" :class="[theme.text, theme.bg5]">
                                      RA{{ ra.orden }}
                                    </span>
                                    <span class="text-[9px] text-gray-300">#{{ ra.id }}</span>
                                  </div>
                                  <p class="text-[11px] font-semibold text-gray-700 leading-snug flex-1">
                                    {{ ra.descripcion }}
                                  </p>
                                </div>

                                <!-- Criterios de Evaluación del RA -->
                                <!--
                                  BD: criterios_evaluacion.idmoduloRA = ra.id (resultados_aprendizaje.id)
                                -->
                                <div v-if="ra.criterios?.length"
                                     class="border-t px-3 pb-2.5 pt-2 bg-white/60" :class="theme.border15">
                                  <div class="flex items-center gap-1.5 mb-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
                                    <span class="text-[9px] font-black uppercase tracking-widest text-amber-600">
                                      Criterios de Evaluación
                                      <span class="font-normal text-amber-400 normal-case tracking-normal">
                                        · idmoduloRA = {{ ra.id }}
                                      </span>
                                    </span>
                                  </div>
                                  <div class="space-y-1 pl-3">
                                    <div v-for="ce in ra.criterios" :key="ce.id"
                                         class="flex gap-2 text-[10px] text-gray-600 leading-snug">
                                      <span class="text-amber-500 font-bold shrink-0 min-w-[1.2rem]">{{ ce.orden }}.</span>
                                      <span>{{ ce.descripcion }}</span>
                                    </div>
                                  </div>
                                </div>

                                <!-- RA sin CE -->
                                <div v-else
                                     class="border-t px-3 py-1.5 bg-white/40" :class="theme.border15">
                                  <span class="text-[9px] text-gray-400 italic">Sin criterios de evaluación registrados</span>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer fijo -->
        <div class="shrink-0 px-7 py-4 border-t border-gray-100 flex items-center gap-3">
          <div class="flex-1 flex flex-wrap items-center gap-x-3 gap-y-1">
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full" :class="theme.bg" />
              <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">RA — resultados_aprendizaje</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-amber-400" />
              <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">CE — criterios_evaluacion</span>
            </div>
          </div>
          <button @click="cerrar"
                  class="px-5 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-black
                         uppercase tracking-widest hover:bg-gray-200 transition-all">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
