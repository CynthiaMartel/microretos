<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api.js'

const route  = useRoute()
const router = useRouter()

const cargando  = ref(true)
const error     = ref('')
const sesion    = ref(null)
const proyecto  = ref(null)
const equipos   = ref([])

const equipoAbierto = ref(null)
const faseAbierta   = ref(null)

const FASES = [
  { num: 0, label: 'Organización', icono: '👥', color: 'slate',  desc: 'Equipo + análisis del reto' },
  { num: 1, label: 'Diseño',       icono: '💡', color: 'blue',   desc: 'Prototipo + propuesta' },
  { num: 2, label: 'Desarrollo',   icono: '🔨', color: 'amber',  desc: 'Tareas y progreso' },
  { num: 3, label: 'Entrega',      icono: '📦', color: 'orange', desc: 'Entregable final' },
  { num: 4, label: 'Cierre',       icono: '🎓', color: 'green',  desc: 'Reflexión y evaluación' },
]

const ROLES = {
  portavoz:       { label: 'Portavoz',       color: 'bg-blue-100 text-blue-700' },
  tiempos:        { label: 'Tiempos',        color: 'bg-amber-100 text-amber-700' },
  documentacion:  { label: 'Documentación',  color: 'bg-violet-100 text-violet-700' },
  foco:           { label: 'Foco',           color: 'bg-emerald-100 text-emerald-700' },
}

const FASE_COLORS = {
  slate:  { bg: 'bg-slate-100',  text: 'text-slate-600',  ring: 'ring-slate-300' },
  blue:   { bg: 'bg-blue-100',   text: 'text-blue-600',   ring: 'ring-blue-300' },
  amber:  { bg: 'bg-amber-100',  text: 'text-amber-600',  ring: 'ring-amber-300' },
  orange: { bg: 'bg-orange-100', text: 'text-orange-600', ring: 'ring-orange-300' },
  green:  { bg: 'bg-green-100',  text: 'text-green-600',  ring: 'ring-green-300' },
}

const totalEquipos    = computed(() => equipos.value.length)
const equiposActivos  = computed(() => equipos.value.filter(e => e.fase_actual > 0).length)
const progresoMedio   = computed(() => {
  if (!totalEquipos.value) return 0
  const sum = equipos.value.reduce((acc, e) => acc + (e.fases_completas / 5), 0)
  return Math.round((sum / totalEquipos.value) * 100)
})

async function cargar() {
  cargando.value = true; error.value = ''
  try {
    const res = await api.get(`/startup/sesiones/${route.params.id}/workspace`)
    sesion.value   = res.data.sesion
    proyecto.value = res.data.proyecto
    equipos.value  = res.data.equipos
  } catch (e) {
    error.value = e.response?.status === 404
      ? 'Sesión no encontrada o sin acceso.'
      : 'Error al cargar el workspace.'
  } finally {
    cargando.value = false
  }
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

function formatDatos(datos) {
  if (!datos) return []
  const entries = []
  for (const [k, v] of Object.entries(datos)) {
    if (v && typeof v === 'string' && v.trim()) {
      entries.push({ clave: k.replace(/_/g, ' '), valor: v })
    } else if (Array.isArray(v) && v.length) {
      entries.push({ clave: k.replace(/_/g, ' '), valor: v.join(' · ') })
    }
  }
  return entries
}

function progresoPct(equipo) {
  return Math.round((equipo.fases_completas / 5) * 100)
}

function estadoBadge(equipo) {
  const fa = equipo.fase_actual
  if (fa === 0 && equipo.fases_completas === 0) return { label: 'Sin iniciar', cls: 'bg-gray-100 text-gray-500' }
  if (equipo.fases_completas === 5)              return { label: 'Completado', cls: 'bg-emerald-100 text-emerald-700' }
  return { label: `Fase ${fa} · ${FASES[fa]?.label}`, cls: 'bg-blue-100 text-blue-700' }
}

onMounted(cargar)
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC]">
    <!-- Topbar -->
    <div class="sticky top-0 z-20 bg-white/90 backdrop-blur-sm border-b border-gray-100 px-4 py-3 flex items-center gap-3">
      <button @click="router.back()"
              class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <p class="text-xs font-black uppercase tracking-widest text-[#00A859]">Workspace docente</p>
        <p class="text-sm font-bold text-[#121212] truncate">
          {{ sesion?.grupo || sesion?.ciclo_formativo || 'Cargando…' }}
        </p>
      </div>
      <button v-if="proyecto?.uuid"
              @click="router.push({ name: 'startup-day-detalle', params: { uuid: proyecto.uuid } })"
              class="shrink-0 px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors
                     text-xs font-black text-gray-600 uppercase tracking-wider">
        Ver proyecto
      </button>
    </div>

    <!-- Cuerpo -->
    <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">

      <!-- Estado de carga / error -->
      <div v-if="cargando" class="flex items-center justify-center py-24">
        <div class="w-8 h-8 border-2 border-[#00A859] border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error"
           class="rounded-3xl bg-red-50 border border-red-200 p-8 text-center text-red-600 text-sm font-semibold">
        {{ error }}
      </div>

      <template v-else>

        <!-- Resumen sesión + proyecto -->
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-2">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Sesión</p>
            <p class="text-lg font-black text-[#121212]">{{ sesion.grupo || '—' }}</p>
            <p class="text-sm text-gray-500">{{ sesion.ciclo_formativo }}</p>
            <div class="flex flex-wrap gap-2 pt-1">
              <span v-if="sesion.centro_educativo"
                    class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                {{ sesion.centro_educativo }}
              </span>
              <span v-if="sesion.fecha"
                    class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                {{ sesion.fecha }}
              </span>
              <span v-if="sesion.num_alumnos"
                    class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                {{ sesion.num_alumnos }} alumnos
              </span>
            </div>
          </div>

          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-3">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Proyecto</p>
            <p v-if="proyecto" class="text-base font-black text-[#121212] leading-snug">{{ proyecto.titulo }}</p>
            <p v-else class="text-sm text-gray-400 italic">Sin proyecto asociado</p>
            <div class="flex items-center gap-4 pt-1">
              <div class="text-center">
                <p class="text-2xl font-black text-[#00A859]">{{ totalEquipos }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Equipos</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-blue-600">{{ equiposActivos }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Activos</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-violet-600">{{ progresoMedio }}%</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Progreso medio</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Sin equipos -->
        <div v-if="!equipos.length"
             class="bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
          <p class="text-gray-400 text-sm">No hay equipos creados en este workspace todavía.</p>
        </div>

        <!-- Lista de equipos -->
        <div v-else class="space-y-3">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Equipos</p>

          <div v-for="equipo in equipos" :key="equipo.id"
               class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">

            <!-- Cabecera del equipo — siempre visible -->
            <button @click="toggleEquipo(equipo.id)"
                    class="w-full px-5 py-4 flex items-center gap-4 hover:bg-gray-50 transition-colors text-left">

              <!-- Progreso circular -->
              <div class="shrink-0 w-12 h-12 relative">
                <svg class="w-12 h-12 -rotate-90" viewBox="0 0 48 48">
                  <circle cx="24" cy="24" r="20" fill="none" stroke="#F3F4F6" stroke-width="4"/>
                  <circle cx="24" cy="24" r="20" fill="none" stroke="#00A859" stroke-width="4"
                          :stroke-dasharray="`${progresoPct(equipo) * 1.257} 125.7`"
                          stroke-linecap="round"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-[10px] font-black text-[#00A859]">
                  {{ progresoPct(equipo) }}%
                </span>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-black text-[#121212]">{{ equipo.nombre }}</p>
                  <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black', estadoBadge(equipo).cls]">
                    {{ estadoBadge(equipo).label }}
                  </span>
                </div>
                <!-- Miembros -->
                <div class="flex flex-wrap gap-1 mt-1.5">
                  <span v-for="m in equipo.miembros" :key="m.id"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[10px] font-semibold">
                    {{ m.nombre }}
                    <span v-if="m.rol" :class="['px-1.5 py-px rounded-full text-[9px] font-black', ROLES[m.rol]?.color]">
                      {{ ROLES[m.rol]?.label }}
                    </span>
                  </span>
                </div>
              </div>

              <!-- Fases visuales -->
              <div class="shrink-0 hidden sm:flex items-center gap-1">
                <div v-for="f in FASES" :key="f.num"
                     :title="f.label"
                     :class="[
                       'w-7 h-7 rounded-lg flex items-center justify-center text-xs',
                       equipo.fases[f.num]?.validado_docente
                         ? 'bg-emerald-500 text-white'
                         : equipo.fases[f.num]?.completada
                           ? 'bg-[#00A859]/20 text-[#00A859]'
                           : equipo.fase_actual === f.num
                             ? 'bg-blue-100 text-blue-600 ring-1 ring-blue-300'
                             : 'bg-gray-100 text-gray-400'
                     ]">
                  {{ f.icono }}
                </div>
              </div>

              <svg :class="['w-4 h-4 text-gray-400 shrink-0 transition-transform', equipoAbierto === equipo.id ? 'rotate-180' : '']"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <!-- Detalle expandido -->
            <div v-if="equipoAbierto === equipo.id" class="border-t border-gray-100 px-5 py-4 space-y-3">

              <!-- Fases -->
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Progreso por fases</p>
              <div class="space-y-2">
                <div v-for="f in FASES" :key="f.num"
                     class="rounded-2xl border border-gray-100 overflow-hidden">

                  <button @click="toggleFase(equipo.id, f.num)"
                          class="w-full px-4 py-3 flex items-center gap-3 hover:bg-gray-50 transition-colors text-left">
                    <span :class="[
                            'w-8 h-8 rounded-xl flex items-center justify-center text-sm shrink-0',
                            FASE_COLORS[f.color].bg, FASE_COLORS[f.color].text
                          ]">{{ f.icono }}</span>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-bold text-[#1F2937]">{{ f.label }}</p>
                      <p class="text-xs text-gray-400">{{ f.desc }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                      <span v-if="equipo.fases[f.num]?.validado_docente"
                            class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black">
                        Validado
                      </span>
                      <span v-else-if="equipo.fases[f.num]?.completada"
                            class="px-2 py-0.5 rounded-full bg-[#00A859]/10 text-[#00A859] text-[10px] font-black">
                        Completa
                      </span>
                      <span v-else-if="equipo.fase_actual === f.num"
                            class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 text-[10px] font-black">
                        En progreso
                      </span>
                      <span v-else class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-400 text-[10px] font-semibold">
                        Pendiente
                      </span>
                      <svg :class="['w-3.5 h-3.5 text-gray-400 transition-transform', faseEstaAbierta(equipo.id, f.num) ? 'rotate-180' : '']"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                      </svg>
                    </div>
                  </button>

                  <!-- Contenido de la fase -->
                  <div v-if="faseEstaAbierta(equipo.id, f.num)" class="px-4 pb-4 border-t border-gray-50 pt-3">
                    <template v-if="equipo.fases[f.num]?.datos">
                      <div v-for="entry in formatDatos(equipo.fases[f.num].datos)" :key="entry.clave"
                           class="mb-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">{{ entry.clave }}</p>
                        <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line">{{ entry.valor }}</p>
                      </div>
                    </template>
                    <p v-else class="text-xs text-gray-400 italic">Sin contenido registrado en esta fase.</p>
                    <div v-if="equipo.fases[f.num]?.nota_docente !== null && equipo.fases[f.num]?.nota_docente !== undefined"
                         class="mt-3 flex items-center gap-2">
                      <span class="text-xs font-black text-gray-500">Nota:</span>
                      <span class="text-sm font-black text-emerald-700">{{ equipo.fases[f.num].nota_docente }}</span>
                    </div>
                    <div v-if="equipo.fases[f.num]?.observaciones_docente"
                         class="mt-2 p-3 bg-amber-50 border border-amber-100 rounded-xl">
                      <p class="text-[10px] font-black uppercase tracking-wider text-amber-600 mb-1">Observaciones docente</p>
                      <p class="text-xs text-amber-800">{{ equipo.fases[f.num].observaciones_docente }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Reflexiones -->
              <div v-if="equipo.reflexiones.length" class="mt-2">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                  Reflexiones ({{ equipo.reflexiones.length }})
                </p>
                <div class="space-y-2">
                  <div v-for="r in equipo.reflexiones" :key="r.id"
                       class="p-3 bg-violet-50 border border-violet-100 rounded-2xl">
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-[10px] font-black uppercase tracking-wider text-violet-600">{{ r.tipo }}</span>
                      <span v-if="r.autor_nombre" class="text-[10px] text-gray-400">{{ r.autor_nombre }}</span>
                    </div>
                    <div v-if="r.respuestas" class="space-y-1">
                      <div v-for="(resp, idx) in r.respuestas" :key="idx">
                        <p v-if="resp.respuesta" class="text-xs text-[#1F2937] leading-relaxed">
                          <span class="text-gray-400">{{ resp.pregunta }}:</span> {{ resp.respuesta }}
                        </p>
                      </div>
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
