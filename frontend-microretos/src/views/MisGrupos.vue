<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'
import { FASES_PROYECTO } from '../config/fasesProyecto.js'

const router = useRouter()

const cargando = ref(true)
const error    = ref('')
const grupos   = ref([])

const encuentroAbierto = ref(null)
const equipoAbierto    = ref(null)
const faseAbierta      = ref(null)

const ROLES = {
  portavoz:      { label: 'Portavoz',       color: 'bg-blue-100 text-blue-700' },
  tiempos:       { label: 'Tiempos',        color: 'bg-amber-100 text-amber-700' },
  documentacion: { label: 'Documentación',  color: 'bg-violet-100 text-violet-700' },
  foco:          { label: 'Foco',           color: 'bg-emerald-100 text-emerald-700' },
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const res = await api.get('/encuentros/mis-grupos')
    grupos.value = res.data
  } catch (e) {
    error.value = 'Error al cargar tus grupos.'
  } finally {
    cargando.value = false
  }
}

function toggleEncuentro(id) {
  encuentroAbierto.value = encuentroAbierto.value === id ? null : id
  equipoAbierto.value = null
  faseAbierta.value = null
}

function toggleEquipo(id) {
  equipoAbierto.value = equipoAbierto.value === id ? null : id
  faseAbierta.value = null
}

function toggleFase(equipoId, faseNum) {
  const key = `${equipoId}-${faseNum}`
  faseAbierta.value = faseAbierta.value === key ? null : key
}

function faseEstaAbierta(equipoId, faseNum) {
  return faseAbierta.value === `${equipoId}-${faseNum}`
}

function progresoPct(equipo) {
  return Math.round((equipo.fases_completas / 5) * 100)
}

function formatDatos(datos) {
  if (!datos) return []
  const entries = []
  for (const [k, v] of Object.entries(datos)) {
    if (k === 'evaluacion_docente') continue
    if (v && typeof v === 'string' && v.trim()) {
      entries.push({ clave: k.replace(/_/g, ' '), valor: v })
    } else if (Array.isArray(v) && v.length) {
      entries.push({ clave: k.replace(/_/g, ' '), valor: v.join(' · ') })
    }
  }
  return entries
}

function estadoBadge(equipo) {
  if (equipo.fases_completas === 5) return { label: 'Completado', cls: 'bg-emerald-100 text-emerald-700' }
  if (equipo.fase_actual === 0 && equipo.fases_completas === 0) return { label: 'Sin iniciar', cls: 'bg-gray-100 text-gray-500' }
  return { label: `${FASES_PROYECTO[equipo.fase_actual]?.label ?? ''}`, cls: 'bg-blue-100 text-blue-700' }
}

// Grupos con al menos un equipo que no ha avanzado (para destacarlos como alerta)
const gruposConAlerta = computed(() =>
  grupos.value.filter(g => g.equipos.some(e => e.fase_actual === 0 && e.fases_completas === 0))
)

onMounted(cargar)
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC]">
    <div class="sticky top-0 z-20 bg-white/90 backdrop-blur-sm border-b border-gray-100 px-4 py-3 flex items-center gap-3">
      <button @click="router.back()"
              class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <p class="text-xs font-black uppercase tracking-widest text-[#00A859]">Mis grupos</p>
        <p class="text-sm font-bold text-[#121212]">Seguimiento de todos tus equipos activos</p>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-6 space-y-4">

      <div v-if="cargando" class="flex items-center justify-center py-24">
        <div class="w-8 h-8 border-2 border-[#00A859] border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error" class="rounded-3xl bg-red-50 border border-red-200 p-8 text-center text-red-600 text-sm font-semibold">
        {{ error }}
      </div>

      <template v-else>
        <div v-if="!grupos.length" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
          <p class="text-gray-400 text-sm">Todavía no tienes encuentros con equipos creados.</p>
        </div>

        <p v-if="gruposConAlerta.length" class="text-xs font-semibold text-amber-600 bg-amber-50 border border-amber-100 rounded-xl px-4 py-2">
          {{ gruposConAlerta.length }} grupo(s) con equipos que todavía no han empezado (Fase 0 sin completar).
        </p>

        <div v-for="g in grupos" :key="g.encuentro.id"
             class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">

          <button @click="toggleEncuentro(g.encuentro.id)"
                  class="w-full px-5 py-4 flex items-center gap-4 hover:bg-gray-50 transition-colors text-left">
            <div class="flex-1 min-w-0">
              <p class="font-black text-[#121212]">{{ g.encuentro.grupo || g.proyecto?.titulo || 'Sin nombre' }}</p>
              <p class="text-xs text-gray-400">{{ g.encuentro.ciclo_formativo }} · {{ g.equipos.length }} equipo(s)</p>
            </div>
            <button @click.stop="router.push({ name: 'workspace-docente', params: { id: g.encuentro.id } })"
                    class="shrink-0 px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors
                           text-[10px] font-black text-gray-600 uppercase tracking-wider">
              Workspace completo →
            </button>
            <svg :class="['w-4 h-4 text-gray-400 shrink-0 transition-transform', encuentroAbierto === g.encuentro.id ? 'rotate-180' : '']"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <div v-if="encuentroAbierto === g.encuentro.id" class="border-t border-gray-100 px-5 py-4 space-y-3">
            <div v-for="equipo in g.equipos" :key="equipo.id"
                 class="rounded-2xl border border-gray-100 overflow-hidden">

              <button @click="toggleEquipo(equipo.id)"
                      class="w-full px-4 py-3 flex items-center gap-3 hover:bg-gray-50 transition-colors text-left">
                <div class="shrink-0 w-10 h-10 relative">
                  <svg class="w-10 h-10 -rotate-90" viewBox="0 0 48 48">
                    <circle cx="24" cy="24" r="20" fill="none" stroke="#F3F4F6" stroke-width="4"/>
                    <circle cx="24" cy="24" r="20" fill="none" stroke="#00A859" stroke-width="4"
                            :stroke-dasharray="`${progresoPct(equipo) * 1.257} 125.7`" stroke-linecap="round"/>
                  </svg>
                  <span class="absolute inset-0 flex items-center justify-center text-[9px] font-black text-[#00A859]">
                    {{ progresoPct(equipo) }}%
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <p class="font-bold text-sm text-[#121212]">{{ equipo.nombre }}</p>
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black', estadoBadge(equipo).cls]">
                      {{ estadoBadge(equipo).label }}
                    </span>
                  </div>
                  <div class="flex flex-wrap gap-1 mt-1">
                    <span v-for="m in equipo.miembros" :key="m.id"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px] font-semibold">
                      {{ m.nombre }}
                      <span v-if="m.rol" :class="['px-1.5 py-px rounded-full text-[9px] font-black', ROLES[m.rol]?.color]">
                        {{ ROLES[m.rol]?.label }}
                      </span>
                    </span>
                  </div>
                </div>
                <svg :class="['w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform', equipoAbierto === equipo.id ? 'rotate-180' : '']"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>

              <div v-if="equipoAbierto === equipo.id" class="px-4 pb-4 border-t border-gray-50 pt-3 space-y-2">
                <div v-for="f in FASES_PROYECTO" :key="f.num" class="rounded-xl border border-gray-100 overflow-hidden">
                  <button @click="toggleFase(equipo.id, f.num)"
                          class="w-full px-3 py-2 flex items-center gap-2 hover:bg-gray-50 transition-colors text-left">
                    <span class="text-sm">{{ f.icono }}</span>
                    <span class="flex-1 text-xs font-bold text-[#1F2937]">{{ f.label }}</span>
                    <span v-if="equipo.fases[f.num]?.validado_docente" class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[9px] font-black">Validado</span>
                    <span v-else-if="equipo.fases[f.num]?.completada" class="px-2 py-0.5 rounded-full bg-[#00A859]/10 text-[#00A859] text-[9px] font-black">Completa</span>
                    <span v-else-if="equipo.fase_actual === f.num" class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 text-[9px] font-black">En progreso</span>
                    <span v-else class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-400 text-[9px] font-semibold">Pendiente</span>
                  </button>
                  <div v-if="faseEstaAbierta(equipo.id, f.num)" class="px-3 pb-3 pt-1 space-y-2">
                    <template v-if="equipo.fases[f.num]?.datos">
                      <div v-for="entry in formatDatos(equipo.fases[f.num].datos)" :key="entry.clave">
                        <p class="text-[9px] font-black uppercase tracking-wider text-gray-400">{{ entry.clave }}</p>
                        <p class="text-xs text-[#1F2937] leading-relaxed whitespace-pre-line">{{ entry.valor }}</p>
                      </div>
                    </template>
                    <p v-else class="text-xs text-gray-400 italic">Sin contenido registrado todavía.</p>
                  </div>
                </div>

                <div v-if="equipo.reflexiones.length" class="pt-1">
                  <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1.5">
                    Reflexiones ({{ equipo.reflexiones.length }})
                  </p>
                  <div v-for="r in equipo.reflexiones" :key="r.id" class="p-2.5 bg-violet-50 border border-violet-100 rounded-xl mb-1.5 last:mb-0">
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-[9px] font-black uppercase tracking-wider text-violet-600">{{ r.tipo }}</span>
                      <span v-if="r.autor_nombre" class="text-[9px] text-gray-400">{{ r.autor_nombre }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
