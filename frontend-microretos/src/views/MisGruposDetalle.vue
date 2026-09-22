<!-- Ruta: /mis-equipos/:id (name: mis-equipos-detalle). Antes /mis-grupos/:id, y antes /workspace/:id
     — "workspace" ya es el sitio de trabajo del alumnado (EquipoWorkspace.vue). El path pasó de
     "grupos" a "equipos" porque "grupo" ya significa la clase/curso del encuentro (Encuentro.grupo,
     ej. "2ºB"), y esta pantalla es el detalle de progreso de los EQUIPOS de ese encuentro/grupo.
     El componente sigue llamándose MisGruposDetalle.vue (no renombrado, para no ampliar el diff).
     Ver router/index.js. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api.js'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'
import DiagnosticoModal from '../components/DiagnosticoModal.vue'
import EquipoResolucionCard from '../components/EquipoResolucionCard.vue'

const route  = useRoute()
const router = useRouter()

const cargando  = ref(true)
const error     = ref('')
const encuentro = ref(null)
const proyecto  = ref(null)
const equipos   = ref([])

const equipoAbierto  = ref(null)
// Set de claves "equipoId-numFase" — al abrir un equipo se precargan aquí las 5 fases
// para que arranquen desplegadas con su información; el docente puede replegar
// individualmente las que no le interesen sin afectar a las demás.
const fasesAbiertas  = ref(new Set())

// Mismas 5 fases que ve el alumnado en su workspace (fuente única: fasesProyecto.js)
const FASES = FASES_PROYECTO

const FASE_COLORS = {
  slate:  { bg: 'bg-slate-100',  text: 'text-slate-600',  ring: 'ring-slate-300', dot: 'bg-slate-400' },
  blue:   { bg: 'bg-blue-100',   text: 'text-blue-600',   ring: 'ring-blue-300', dot: 'bg-blue-400' },
  amber:  { bg: 'bg-amber-100',  text: 'text-amber-600',  ring: 'ring-amber-300', dot: 'bg-amber-400' },
  orange: { bg: 'bg-orange-100', text: 'text-orange-600', ring: 'ring-orange-300', dot: 'bg-orange-400' },
  green:  { bg: 'bg-green-100',  text: 'text-green-600',  ring: 'ring-green-300', dot: 'bg-green-400' },
}

// Mismos tres estados que en MisGrupos.vue (mutuamente excluyentes, suman totalEquipos):
// finalizado = las 5 fases completas; en curso = ha empezado pero no ha terminado;
// el resto es "sin iniciar" (no se cuenta aparte, pero se deduce: total - en curso - finalizado).
const totalEquipos        = computed(() => equipos.value.length)
const equiposFinalizados  = computed(() => equipos.value.filter(e => e.fases_completas === 5).length)
const equiposSinIniciar   = computed(() => equipos.value.filter(e => e.fase_actual === 0 && e.fases_completas === 0).length)
const equiposEnCurso      = computed(() => totalEquipos.value - equiposFinalizados.value - equiposSinIniciar.value)
const progresoMedio       = computed(() => {
  if (!totalEquipos.value) return 0
  const sum = equipos.value.reduce((acc, e) => acc + progresoPct(e), 0)
  return Math.round(sum / totalEquipos.value)
})

async function cargar() {
  cargando.value = true; error.value = ''
  try {
    const res = await api.get(`/encuentros/${route.params.id}/workspace`)
    encuentro.value = res.data.encuentro
    proyecto.value = res.data.proyecto
    equipos.value  = res.data.equipos
  } catch (e) {
    error.value = e.response?.status === 404
      ? 'Encuentro no encontrado o sin acceso.'
      : 'Error al cargar el workspace.'
  } finally {
    cargando.value = false
  }
}

// Precarga las 5 fases como abiertas para este equipo (con su evaluación RA/CE ya
// inicializada si toca) — se llama al abrir el equipo, no al abrir cada fase suelta.
function abrirTodasLasFases(equipo) {
  fasesAbiertas.value = new Set(FASES.map(f => `${equipo.id}-${f.num}`))
  initEvaluacionForm(equipo)
}

function toggleEquipo(id) {
  if (equipoAbierto.value === id) {
    equipoAbierto.value = null
    fasesAbiertas.value = new Set()
    return
  }
  equipoAbierto.value = id
  const equipo = equipos.value.find(e => e.id === id)
  if (equipo) abrirTodasLasFases(equipo)
}

function toggleFase(equipoId, faseNum) {
  const key = `${equipoId}-${faseNum}`
  const next = new Set(fasesAbiertas.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  fasesAbiertas.value = next
}

function abrirFase(equipo, faseNum) {
  toggleFase(equipo.id, faseNum)
  if (faseNum === 4) initEvaluacionForm(equipo)
}

// ── Evaluación curricular RA/CE (F4) ────────────────────────────────────────
const NIVEL_OPCIONES = [
  { value: 'no_alcanzado', label: 'No alcanzado' },
  { value: 'en_proceso',   label: 'En proceso' },
  { value: 'alcanzado',    label: 'Alcanzado' },
  { value: 'superado',     label: 'Superado' },
]
const evaluacionForms   = ref({})
const guardandoEval     = ref(false)
const errorEval         = ref('')

function initEvaluacionForm(equipo) {
  if (evaluacionForms.value[equipo.id]) return

  const existentes = equipo.fases[4]?.datos?.evaluacion_docente?.ras ?? []
  const catalogo    = proyecto.value?.evaluacion_oficial ?? []

  evaluacionForms.value[equipo.id] = {
    ras: catalogo.map(item => {
      const previa = existentes.find(e => e.ra === item.ra)
      return { ra: item.ra, nivel: previa?.nivel ?? '', observaciones: previa?.observaciones ?? '' }
    }),
    nota_docente:          equipo.fases[4]?.nota_docente ?? null,
    observaciones_docente: equipo.fases[4]?.observaciones_docente ?? '',
  }
}

function puedeEnviarEvaluacion(equipoId) {
  const form = evaluacionForms.value[equipoId]
  return form && form.ras.some(r => r.nivel)
}

async function enviarEvaluacion(equipo) {
  const form = evaluacionForms.value[equipo.id]
  guardandoEval.value = true
  errorEval.value = ''
  try {
    await api.patch(`/startup/equipos/${equipo.id}/evaluar`, {
      evaluacion: {
        ras:           form.ras.filter(r => r.nivel),
        nota_opcional: form.nota_docente,
      },
      nota_docente:          form.nota_docente,
      observaciones_docente: form.observaciones_docente,
    })
    await cargar()
  } catch (e) {
    errorEval.value = e.response?.data?.error ?? 'No se pudo guardar la evaluación.'
  } finally {
    guardandoEval.value = false
  }
}

// ── Diagnóstico final IA (equipos con las 5 fases completas) ───────────────
const generandoDiagnostico = ref({})
const errorDiagnostico     = ref({})

// Abre (no alterna) el detalle del equipo — el botón de cabecera despliega la misma
// información que un click normal, dejando a la vista el botón de dentro del panel.
function abrirDiagnostico(equipo) {
  equipoAbierto.value = equipo.id
  abrirTodasLasFases(equipo)
}

// Modal "Ver diagnóstico" — una vez generado, se consulta en el modal en vez del
// panel inline (que sigue existiendo para generar/regenerar).
const diagnosticoModalEquipo = ref(null)
function verDiagnostico(equipo) {
  diagnosticoModalEquipo.value = equipo
}

async function generarDiagnostico(equipo) {
  if (generandoDiagnostico.value[equipo.id]) return
  // Ya existe uno: pulsar el mismo botón lo sobrescribiría sin más aviso — confirmar antes.
  if (equipo.diagnostico_final && !confirm('Ya existe un diagnóstico final para este equipo. ¿Quieres generarlo de nuevo? Se sustituirá el actual.')) {
    return
  }
  generandoDiagnostico.value = { ...generandoDiagnostico.value, [equipo.id]: true }
  errorDiagnostico.value = { ...errorDiagnostico.value, [equipo.id]: '' }
  try {
    const res = await api.post(`/startup/equipos/${equipo.id}/diagnostico-final`)
    equipo.diagnostico_final = res.data.diagnostico
    equipo.diagnostico_generado_en = res.data.generado_en
  } catch (e) {
    errorDiagnostico.value = { ...errorDiagnostico.value, [equipo.id]: e.response?.data?.error ?? 'No se pudo generar el diagnóstico.' }
  } finally {
    generandoDiagnostico.value = { ...generandoDiagnostico.value, [equipo.id]: false }
  }
}

function progresoPct(equipo) {
  return progresoPonderado(equipo.fases)
}

onMounted(cargar)
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC] pt-16">
    <!-- Topbar. top-16 (no top-0): la TopBar global es fixed h-16 con z-50 — con top-0
         esta cabecera propia quedaba pegada al viewport y desaparecía detrás de aquella. -->
    <div class="sticky top-16 z-20 bg-white/90 backdrop-blur-sm border-b border-gray-100 px-4 py-3 flex items-center gap-3">
      <button @click="router.back()"
              class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <p class="text-xs font-black uppercase tracking-widest text-centros">Detalle de equipos</p>
        <p class="text-sm font-bold text-[#121212] truncate">
          {{ proyecto?.titulo || encuentro?.grupo || encuentro?.ciclo_formativo || 'Cargando…' }}
        </p>
      </div>
      <!-- name 'mis-equipos' (antes 'mis-grupos') — ver router/index.js -->
      <button @click="router.push({ name: 'mis-equipos' })"
              class="shrink-0 px-3 py-1.5 rounded-xl bg-violet-50 border border-violet-200 text-violet-700
                     hover:bg-violet-100 transition-colors text-xs font-black uppercase tracking-wider">
        Mis grupos
      </button>
      <button v-if="proyecto?.uuid"
              @click="router.push({ name: 'startup-day-detalle', params: { uuid: proyecto.uuid } })"
              class="shrink-0 px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors
                     text-xs font-black text-gray-600 uppercase tracking-wider">
        Ver proyecto
      </button>
    </div>

    <!-- Cuerpo -->
    <div class="max-w-5xl mx-auto px-4 py-6 space-y-8">

      <!-- Estado de carga / error -->
      <div v-if="cargando" class="flex items-center justify-center py-24">
        <div class="w-8 h-8 border-2 border-centros border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error"
           class="rounded-3xl bg-red-50 border border-red-200 p-8 text-center text-red-600 text-sm font-semibold">
        {{ error }}
      </div>

      <template v-else>

        <!-- Sección: Resumen -->
        <section class="space-y-3">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Resumen</p>
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div class="text-center">
                <p class="text-2xl font-black text-[#121212]">{{ totalEquipos }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Equipos</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-blue-600">{{ equiposEnCurso }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">En curso</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-emerald-600">{{ equiposFinalizados }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Finalizados</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-black text-violet-600">{{ progresoMedio }}%</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Progreso medio</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Sección: Información -->
        <section class="space-y-3 pt-6 border-t border-gray-100">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Información</p>
          <div class="grid sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-2">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Encuentro</p>
              <p class="text-lg font-black text-[#121212]">{{ encuentro.grupo || '—' }}</p>
              <p class="text-sm text-gray-500">{{ encuentro.ciclo_formativo }}</p>
              <div class="flex flex-wrap gap-2 pt-1">
                <span v-if="encuentro.centro_educativo"
                      class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                  {{ encuentro.centro_educativo }}
                </span>
                <span v-if="encuentro.fecha"
                      class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                  {{ encuentro.fecha }}
                </span>
                <span v-if="encuentro.num_alumnos"
                      class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                  {{ encuentro.num_alumnos }} alumnos
                </span>
              </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-2">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Proyecto</p>
              <p v-if="proyecto" class="text-base font-black text-[#121212] leading-snug">{{ proyecto.titulo }}</p>
              <p v-else class="text-sm text-gray-400 italic">Sin proyecto asociado</p>
              <span v-if="proyecto?.estado"
                    class="inline-block px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                {{ proyecto.estado }}
              </span>
            </div>
          </div>
        </section>

        <!-- Sección: Fases del proyecto — referencia general de qué cubre cada fase, sin
             porcentajes: el progreso real y concreto de cada equipo ya se muestra arriba
             (contadores) y en su tarjeta (ring/badges). -->
        <section class="space-y-3 pt-6 border-t border-gray-100">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Fases del proyecto</p>
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div v-for="f in FASES" :key="f.num"
                 class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-1.5">
              <span :class="[FASE_COLORS[f.color].bg, FASE_COLORS[f.color].text,
                             'w-9 h-9 rounded-xl flex items-center justify-center text-base']">
                {{ f.icono }}
              </span>
              <p class="text-xs font-bold text-[#1F2937]">F{{ f.num }} · {{ f.label }}</p>
              <p class="text-[10px] text-gray-400">{{ f.desc }}</p>
              <p v-if="f.num === 4" class="text-[10px] font-bold text-centros">Aquí se asigna la nota final</p>
            </div>
          </div>
        </section>

        <!-- Sección: Equipos -->
        <section class="space-y-3 pt-6 border-t border-gray-100">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Equipos</p>

          <!-- Sin equipos -->
          <div v-if="!equipos.length"
               class="bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
            <p class="text-gray-400 text-sm">No hay equipos creados en este encuentro todavía.</p>
          </div>

          <EquipoResolucionCard
            v-for="equipo in equipos" :key="equipo.id"
            :equipo="equipo"
            :abierto="equipoAbierto === equipo.id"
            :fases-abiertas="fasesAbiertas"
            @toggle-equipo="toggleEquipo(equipo.id)"
            @toggle-fase="(faseNum) => abrirFase(equipo, faseNum)">

            <!-- Solo cuando el equipo ha completado sus 5 fases. Sin diagnóstico aún: abre
                 el detalle (igual que pulsar en cualquier otra parte de la cabecera) para
                 dejar a la vista el botón de generar dentro del panel. Con diagnóstico ya
                 generado: abre directamente el modal, sin pasar por el panel inline. -->
            <template #acciones-cabecera="{ equipo: eq }">
              <button v-if="eq.fases_completas === 5 && !eq.diagnostico_final"
                      @click.stop="abrirDiagnostico(eq)"
                      class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700
                             hover:bg-emerald-100 transition-colors text-[10px] font-black uppercase tracking-wider">
                Generar diagnóstico final
              </button>
              <button v-else-if="eq.fases_completas === 5"
                      @click.stop="verDiagnostico(eq)"
                      class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-500 text-white
                             hover:bg-emerald-600 transition-colors text-[10px] font-black uppercase tracking-wider">
                Ver diagnóstico
              </button>
            </template>

            <!-- Evaluación curricular RA/CE — solo en Cierre (F4) -->
            <template #evaluacion-fase-4="{ equipo: eq }">
              <div v-if="evaluacionForms[eq.id]" class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Evaluación curricular (RA/CE)</p>

                <p v-if="!evaluacionForms[eq.id].ras.length" class="text-xs text-gray-400 italic">
                  El proyecto no tiene RA/CE oficiales asignados todavía.
                </p>

                <div v-for="(r, idx) in evaluacionForms[eq.id].ras" :key="idx"
                     class="bg-gray-50 rounded-xl p-3 space-y-2">
                  <p class="text-xs font-semibold text-[#1F2937]">{{ r.ra }}</p>
                  <div class="flex flex-wrap gap-1.5">
                    <button v-for="op in NIVEL_OPCIONES" :key="op.value"
                            @click="r.nivel = op.value"
                            :class="['px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider transition-all',
                                     r.nivel === op.value ? 'bg-emerald-500 text-white' : 'bg-white border border-gray-200 text-gray-500 hover:border-emerald-300']">
                      {{ op.label }}
                    </button>
                  </div>
                  <input v-model="r.observaciones" type="text" placeholder="Observaciones (opcional)"
                         class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white
                                focus:outline-none focus:border-emerald-400"/>
                </div>

                <div class="flex items-center gap-3">
                  <label class="text-xs font-black text-gray-500 uppercase tracking-wider shrink-0">Nota</label>
                  <input v-model.number="evaluacionForms[eq.id].nota_docente" type="number" min="0" max="10" step="0.1"
                         class="w-20 text-sm border border-gray-200 rounded-lg px-2 py-1.5
                                focus:outline-none focus:border-emerald-400"/>
                  <span class="text-xs text-gray-400">/ 10 (opcional)</span>
                </div>
                <textarea v-model="evaluacionForms[eq.id].observaciones_docente" rows="2"
                          placeholder="Observaciones generales del proyecto (opcional)"
                          class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white resize-none
                                 focus:outline-none focus:border-emerald-400"/>

                <p v-if="errorEval" class="text-xs text-red-500 font-semibold">{{ errorEval }}</p>

                <button @click="enviarEvaluacion(eq)"
                        :disabled="!puedeEnviarEvaluacion(eq.id) || guardandoEval"
                        :class="['w-full py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all',
                                 puedeEnviarEvaluacion(eq.id) ? 'bg-emerald-500 text-white hover:bg-emerald-600' : 'bg-gray-100 text-gray-300 cursor-not-allowed']">
                  {{ eq.fases[4]?.validado_docente ? 'Actualizar evaluación' : 'Guardar evaluación' }}
                </button>
              </div>
            </template>

            <template #diagnostico-acciones="{ equipo: eq }">
              <p v-if="errorDiagnostico[eq.id]" class="text-xs text-red-500 font-semibold">{{ errorDiagnostico[eq.id] }}</p>
              <div class="flex flex-wrap items-center gap-2">
                <button v-if="eq.diagnostico_final"
                        @click="verDiagnostico(eq)"
                        class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-500 text-white
                               hover:bg-emerald-600 transition-colors text-[10px] font-black uppercase tracking-wider">
                  Ver diagnóstico completo
                </button>
                <button @click="generarDiagnostico(eq)"
                        :disabled="generandoDiagnostico[eq.id]"
                        class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700
                               hover:bg-emerald-100 transition-colors text-[10px] font-black uppercase tracking-wider
                               disabled:opacity-50 disabled:cursor-not-allowed">
                  {{ generandoDiagnostico[eq.id] ? 'Generando…' : (eq.diagnostico_final ? 'Regenerar diagnóstico' : 'Generar diagnóstico final') }}
                </button>
              </div>
            </template>
          </EquipoResolucionCard>
        </section>

      </template>
    </div>

    <DiagnosticoModal :equipo="diagnosticoModalEquipo" :encuentro="encuentro" @close="diagnosticoModalEquipo = null" />
  </div>
</template>
