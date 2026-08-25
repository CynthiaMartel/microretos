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

const route  = useRoute()
const router = useRouter()

const cargando  = ref(true)
const error     = ref('')
const encuentro = ref(null)
const proyecto  = ref(null)
const equipos   = ref([])

const equipoAbierto = ref(null)
const faseAbierta   = ref(null)

// Mismas 5 fases que ve el alumnado en su workspace (fuente única: fasesProyecto.js)
const FASES = FASES_PROYECTO

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

function toggleEquipo(id) {
  equipoAbierto.value = equipoAbierto.value === id ? null : id
  faseAbierta.value = null
}

function toggleFase(equipoId, faseNum) {
  const key = `${equipoId}-${faseNum}`
  faseAbierta.value = faseAbierta.value === key ? null : key
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

function faseEstaAbierta(equipoId, faseNum) {
  return faseAbierta.value === `${equipoId}-${faseNum}`
}

// ── Diagnóstico final IA (equipos con las 5 fases completas) ───────────────
const generandoDiagnostico = ref({})
const errorDiagnostico     = ref({})

// Abre (no alterna) el detalle del equipo — el botón de cabecera despliega la misma
// información que un click normal, dejando a la vista el botón de dentro del panel.
function abrirDiagnostico(equipo) {
  equipoAbierto.value = equipo.id
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

// Cada entrada se clasifica para pintarla distinto en la plantilla: 'lista' (una fila
// por elemento — hallazgos, síntesis, miembros...) vs 'texto' (párrafo suelto). Antes
// todo se aplanaba a un único string con join('\n'), lo que dejaba listas y párrafos
// visualmente idénticos.
function formatDatos(datos) {
  if (!datos) return []
  const entries = []
  for (const [k, v] of Object.entries(datos)) {
    // La evaluación curricular se muestra aparte, en su propio bloque más abajo.
    if (k === 'evaluacion_docente') continue
    const entry = formatEntradaFase(v)
    if (entry) entries.push({ clave: k.replace(/_/g, ' '), ...entry })
  }
  return entries
}

// Los campos de fase no son siempre texto plano: sintesis (F1) y miembros (F0) son
// arrays de objetos, y organizacion (F4) es un objeto — un join()/interpolación directa
// de eso produce literalmente "[object Object]". Aquí se resuelven a texto legible.
function formatEntradaFase(v) {
  if (v === null || v === undefined) return null
  if (typeof v === 'string') {
    const texto = v.trim()
    return texto ? { tipo: 'texto', valor: texto } : null
  }
  if (typeof v === 'boolean') return { tipo: 'texto', valor: v ? 'Sí' : 'No' }
  if (Array.isArray(v)) {
    // Síntesis (F1): pares pregunta/respuesta — se conservan sin aplanar para poder
    // enmarcar la pregunta (predefinida) distinto de la respuesta (la escribe el equipo).
    if (v.length && v.every(item => item && typeof item === 'object' && 'pregunta' in item)) {
      const items = v
        .map(item => ({ pregunta: item.pregunta, respuesta: item.respuesta?.trim() || '' }))
        .filter(item => item.pregunta)
      return items.length ? { tipo: 'preguntas', items } : null
    }
    const items = v.map(formatItemFase).filter(Boolean)
    return items.length ? { tipo: 'lista', items } : null
  }
  if (typeof v === 'object') {
    const items = Object.entries(v)
      .map(([k, val]) => {
        const texto = formatItemFase(val)
        return texto ? `${k.replace(/_/g, ' ')}: ${texto}` : null
      })
      .filter(Boolean)
    return items.length ? { tipo: 'lista', items } : null
  }
  return null
}

function formatItemFase(item) {
  if (item === null || item === undefined || item === '') return ''
  if (typeof item === 'string' || typeof item === 'number') return String(item)
  if (typeof item === 'boolean') return item ? 'Sí' : 'No'
  if (Array.isArray(item)) return item.map(formatItemFase).filter(Boolean).join(', ')
  if (typeof item === 'object') {
    // Formas conocidas: pregunta/respuesta (síntesis F1), miembro con nombre/rol (F0)
    if ('pregunta' in item) return `${item.pregunta}: ${item.respuesta || '—'}`
    if ('nombre' in item)   return item.rol ? `${item.nombre} (${item.rol})` : item.nombre
    return Object.values(item).filter(x => typeof x === 'string' && x).join(' · ')
  }
  return ''
}

function progresoPct(equipo) {
  return progresoPonderado(equipo.fases)
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
  <div class="min-h-screen bg-[#F8FAFC] pt-12">
    <!-- Topbar. top-12 (no top-0): la TopBar global es fixed h-12 con z-50 — con top-0
         esta cabecera propia quedaba pegada al viewport y desaparecía detrás de aquella. -->
    <div class="sticky top-12 z-20 bg-white/90 backdrop-blur-sm border-b border-gray-100 px-4 py-3 flex items-center gap-3">
      <button @click="router.back()"
              class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <p class="text-xs font-black uppercase tracking-widest text-[#00A859]">Detalle de equipos</p>
        <p class="text-sm font-bold text-[#121212] truncate">
          {{ encuentro?.grupo || encuentro?.ciclo_formativo || 'Cargando…' }}
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
        <div class="w-8 h-8 border-2 border-[#00A859] border-t-transparent rounded-full animate-spin"></div>
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
              <p v-if="f.num === 4" class="text-[10px] font-bold text-[#00A859]">Aquí se asigna la nota final</p>
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

          <div v-for="equipo in equipos" :key="equipo.id"
               class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">

            <!-- Cabecera del equipo — siempre visible. div (no button): contiene el botón
                 de diagnóstico final anidado, que no puede ir dentro de otro <button>. -->
            <div @click="toggleEquipo(equipo.id)" @keydown.enter="toggleEquipo(equipo.id)"
                 role="button" tabindex="0"
                 class="w-full px-5 py-4 flex items-center gap-4 hover:bg-gray-50 transition-colors text-left cursor-pointer">

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
              <div class="shrink-0 hidden sm:flex items-center gap-1.5">
                <div v-for="f in FASES" :key="f.num" :title="f.label" class="flex flex-col items-center gap-0.5">
                  <div :class="[
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
                  <span class="text-[8px] font-black text-gray-400 uppercase tracking-wider">F{{ f.num }}</span>
                </div>
              </div>

              <!-- Solo cuando el equipo ha completado sus 5 fases. Sin diagnóstico aún: abre
                   el detalle (igual que pulsar en cualquier otra parte de la cabecera) para
                   dejar a la vista el botón de generar dentro del panel. Con diagnóstico ya
                   generado: abre directamente el modal, sin pasar por el panel inline. -->
              <button v-if="equipo.fases_completas === 5 && !equipo.diagnostico_final"
                      @click.stop="abrirDiagnostico(equipo)"
                      class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700
                             hover:bg-emerald-100 transition-colors text-[10px] font-black uppercase tracking-wider">
                Generar diagnóstico final
              </button>
              <button v-else-if="equipo.fases_completas === 5"
                      @click.stop="verDiagnostico(equipo)"
                      class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-500 text-white
                             hover:bg-emerald-600 transition-colors text-[10px] font-black uppercase tracking-wider">
                Ver diagnóstico
              </button>

              <svg :class="['w-4 h-4 text-gray-400 shrink-0 transition-transform', equipoAbierto === equipo.id ? 'rotate-180' : '']"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </div>

            <!-- Detalle expandido -->
            <div v-if="equipoAbierto === equipo.id" class="border-t border-gray-100 px-5 py-4 space-y-3">

              <!-- Fases -->
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Progreso por fases</p>
              <div class="space-y-2">
                <div v-for="f in FASES" :key="f.num"
                     class="rounded-2xl border border-gray-100 overflow-hidden">

                  <button @click="abrirFase(equipo, f.num)"
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
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="entry in formatDatos(equipo.fases[f.num].datos)" :key="entry.clave"
                             :class="['rounded-xl border border-gray-100 bg-gray-50 p-3',
                                      entry.tipo !== 'texto' || entry.valor?.length > 70 ? 'sm:col-span-2' : '']">
                          <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">{{ entry.clave }}</p>

                          <p v-if="entry.tipo === 'texto'" class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line">{{ entry.valor }}</p>

                          <!-- Pregunta predefinida vs. respuesta del equipo — enmarcadas distinto,
                               no solo diferenciadas por texto: la pregunta parece una plantilla
                               (fondo rayado, icono "?"), la respuesta parece escrita a mano
                               (fondo blanco, borde de acento, icono de persona). -->
                          <div v-else-if="entry.tipo === 'preguntas'" class="space-y-2.5">
                            <div v-for="(item, i) in entry.items" :key="i" class="space-y-1">
                              <p class="flex items-start gap-1.5 text-xs italic text-gray-500 bg-[repeating-linear-gradient(135deg,rgba(0,0,0,0.03)_0px,rgba(0,0,0,0.03)_1px,transparent_1px,transparent_8px)] border border-dashed border-gray-300 rounded-lg px-2.5 py-1.5">
                                <span class="shrink-0 not-italic text-gray-400">❓</span>
                                {{ item.pregunta }}
                              </p>
                              <p class="text-sm text-[#1F2937] leading-relaxed bg-white
                                        border border-gray-100 border-l-4 border-l-[#00A859] rounded-lg px-2.5 py-1.5 ml-3">
                                <span v-if="item.respuesta">{{ item.respuesta }}</span>
                                <span v-else class="text-gray-400 italic">Sin responder</span>
                              </p>
                            </div>
                          </div>

                          <ul v-else class="space-y-1.5">
                            <li v-for="(item, i) in entry.items" :key="i"
                                class="text-sm text-[#1F2937] leading-relaxed border-l-2 border-[#00A859]/30 pl-2.5">
                              {{ item }}
                            </li>
                          </ul>
                        </div>
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

                    <!-- Evaluación curricular RA/CE — solo en Cierre (F4) -->
                    <div v-if="f.num === 4 && evaluacionForms[equipo.id]" class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                      <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Evaluación curricular (RA/CE)</p>

                      <p v-if="!evaluacionForms[equipo.id].ras.length" class="text-xs text-gray-400 italic">
                        El proyecto no tiene RA/CE oficiales asignados todavía.
                      </p>

                      <div v-for="(r, idx) in evaluacionForms[equipo.id].ras" :key="idx"
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
                        <input v-model.number="evaluacionForms[equipo.id].nota_docente" type="number" min="0" max="10" step="0.1"
                               class="w-20 text-sm border border-gray-200 rounded-lg px-2 py-1.5
                                      focus:outline-none focus:border-emerald-400"/>
                        <span class="text-xs text-gray-400">/ 10 (opcional)</span>
                      </div>
                      <textarea v-model="evaluacionForms[equipo.id].observaciones_docente" rows="2"
                                placeholder="Observaciones generales del proyecto (opcional)"
                                class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white resize-none
                                       focus:outline-none focus:border-emerald-400"/>

                      <p v-if="errorEval" class="text-xs text-red-500 font-semibold">{{ errorEval }}</p>

                      <button @click="enviarEvaluacion(equipo)"
                              :disabled="!puedeEnviarEvaluacion(equipo.id) || guardandoEval"
                              :class="['w-full py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all',
                                       puedeEnviarEvaluacion(equipo.id) ? 'bg-emerald-500 text-white hover:bg-emerald-600' : 'bg-gray-100 text-gray-300 cursor-not-allowed']">
                        {{ equipo.fases[4]?.validado_docente ? 'Actualizar evaluación' : 'Guardar evaluación' }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Diagnóstico final IA — solo con las 5 fases completas -->
              <div v-if="equipo.fases_completas === 5" class="mt-2 pt-4 border-t border-gray-100 space-y-3">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Diagnóstico final</p>

                <div v-if="equipo.diagnostico_final" class="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-4 space-y-3">
                  <p class="text-sm text-[#1F2937] leading-relaxed">{{ equipo.diagnostico_final.resumen }}</p>

                  <div v-if="equipo.diagnostico_final.fortalezas?.length" class="space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Fortalezas</p>
                    <ul class="space-y-1">
                      <li v-for="(f, i) in equipo.diagnostico_final.fortalezas" :key="i"
                          class="text-xs text-[#1F2937] leading-relaxed border-l-2 border-emerald-300 pl-2.5">{{ f }}</li>
                    </ul>
                  </div>

                  <div v-if="equipo.diagnostico_final.areas_mejora?.length" class="space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-600">Áreas de mejora</p>
                    <ul class="space-y-1">
                      <li v-for="(a, i) in equipo.diagnostico_final.areas_mejora" :key="i"
                          class="text-xs text-[#1F2937] leading-relaxed border-l-2 border-amber-300 pl-2.5">{{ a }}</li>
                    </ul>
                  </div>

                  <div v-if="equipo.diagnostico_final.valoracion_ra_ce" class="space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Valoración RA/CE</p>
                    <p class="text-xs text-[#1F2937] leading-relaxed">{{ equipo.diagnostico_final.valoracion_ra_ce }}</p>
                  </div>

                  <p v-if="equipo.diagnostico_final.conclusion" class="text-xs font-semibold text-[#1F2937] italic">
                    {{ equipo.diagnostico_final.conclusion }}
                  </p>

                  <p v-if="equipo.diagnostico_generado_en" class="text-[10px] text-gray-400">
                    Generado el {{ new Date(equipo.diagnostico_generado_en).toLocaleString('es-ES') }}
                  </p>
                </div>
                <p v-else class="text-xs text-gray-400 italic">Todavía no se ha generado el diagnóstico final de este equipo.</p>

                <p v-if="errorDiagnostico[equipo.id]" class="text-xs text-red-500 font-semibold">{{ errorDiagnostico[equipo.id] }}</p>

                <div class="flex flex-wrap items-center gap-2">
                  <button v-if="equipo.diagnostico_final"
                          @click="verDiagnostico(equipo)"
                          class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-500 text-white
                                 hover:bg-emerald-600 transition-colors text-[10px] font-black uppercase tracking-wider">
                    Ver diagnóstico completo
                  </button>
                  <button @click="generarDiagnostico(equipo)"
                          :disabled="generandoDiagnostico[equipo.id]"
                          class="shrink-0 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700
                                 hover:bg-emerald-100 transition-colors text-[10px] font-black uppercase tracking-wider
                                 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ generandoDiagnostico[equipo.id] ? 'Generando…' : (equipo.diagnostico_final ? 'Regenerar diagnóstico' : 'Generar diagnóstico final') }}
                  </button>
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
        </section>

      </template>
    </div>

    <DiagnosticoModal :equipo="diagnosticoModalEquipo" :encuentro="encuentro" @close="diagnosticoModalEquipo = null" />
  </div>
</template>
