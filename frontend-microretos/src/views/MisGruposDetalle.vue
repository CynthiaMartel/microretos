<!-- Ruta: /mis-grupos/:id (name: mis-grupos-detalle). Antes /mis-grupos/:id y /workspace/:id
     — "workspace" ya es el sitio de trabajo del alumnado (EquipoWorkspace.vue). Vuelve a "grupos"
     porque en las vistas del docente los equipos de alumnado se llaman "grupos" y la letra del
     encuentro (Encuentro.grupo) se muestra como "Clase". Ver router/index.js. -->
<script setup>
import IconoFase from '../components/IconoFase.vue'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getWorkspaceEncuentro } from '../services/encuentroService.js'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'
import EquipoResolucionCard from '../components/EquipoResolucionCard.vue'
import EvaluacionGrupoPanel from '../components/EvaluacionGrupoPanel.vue'

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
    const res = await getWorkspaceEncuentro(route.params.id)
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

// Precarga las 5 fases como abiertas para este equipo — se llama al abrir el equipo, no al
// abrir cada fase suelta.
function abrirTodasLasFases(equipo) {
  fasesAbiertas.value = new Set(FASES.map(f => `${equipo.id}-${f.num}`))
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
}

// La evaluación del grupo (RA/CE, diagnóstico final y nota final) vive en EvaluacionGrupoPanel.vue,
// el mismo panel del modal «Evaluación del grupo»: aquí va dentro de la tarjeta de cada grupo.

// Abre (no alterna) el detalle del equipo
function abrirDiagnostico(equipo) {
  equipoAbierto.value = equipo.id
  abrirTodasLasFases(equipo)
}

// Botones de la cabecera ("Ir a generar diagnóstico" / "Ir al diagnóstico"): abren el
// equipo y llevan el scroll + foco a su sección de diagnóstico, resaltándola un momento.
// Todas las acciones (ver, editar, regenerar, PDF) viven en esa sección del panel.
const diagnosticoResaltado = ref(null)
let timerResaltado = null
const reducirMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches

async function irADiagnostico(equipo) {
  if (equipoAbierto.value !== equipo.id) abrirDiagnostico(equipo)
  await nextTick()
  const el = document.getElementById(`diagnostico-equipo-${equipo.id}`)
  if (el) {
    el.scrollIntoView({ behavior: reducirMovimiento() ? 'auto' : 'smooth', block: 'start' })
    el.focus({ preventScroll: true })
    diagnosticoResaltado.value = equipo.id
    clearTimeout(timerResaltado)
    timerResaltado = setTimeout(() => { diagnosticoResaltado.value = null }, 2500)
  }
}

function progresoPct(equipo) {
  return progresoPonderado(equipo.fases)
}

// «Ver evaluación y diagnóstico» (Listado de alumnado, Seguimiento de grupos): abre el grupo y lleva
// el scroll a su evaluación
async function irAEvaluacion(equipo) {
  if (equipoAbierto.value !== equipo.id) toggleEquipo(equipo.id)
  await nextTick()
  const el = document.getElementById(`evaluacion-equipo-${equipo.id}`)
  if (!el) return irAEquipo(equipo)
  el.scrollIntoView({ behavior: reducirMovimiento() ? 'auto' : 'smooth', block: 'start' })
}

// «Ver detalle del grupo» de un grupo concreto (Encuentros, Listado de alumnado, Mis grupos):
// abre ese grupo y lleva el scroll hasta su tarjeta, resaltándola un momento.
const equipoResaltado = ref(null)
async function irAEquipo(equipo) {
  if (equipoAbierto.value !== equipo.id) toggleEquipo(equipo.id)
  await nextTick()
  const el = document.getElementById(`equipo-${equipo.id}`)
  if (!el) return
  el.scrollIntoView({ behavior: reducirMovimiento() ? 'auto' : 'smooth', block: 'start' })
  equipoResaltado.value = equipo.id
  clearTimeout(timerResaltado)
  timerResaltado = setTimeout(() => { equipoResaltado.value = null }, 2500)
}

// Llegada con ?equipo= (y ?ver=diagnostico desde el aviso "Aquí puedes ver el diagnóstico
// del equipo"): tras cargar, abrir ese equipo y llevar el scroll a él o a su diagnóstico.
onMounted(async () => {
  await cargar()
  const equipo = equipos.value.find(e => String(e.id) === String(route.query.equipo))
  if (!equipo) return
  if (route.query.ver === 'diagnostico') irADiagnostico(equipo)
  else if (route.query.ver === 'evaluacion') irAEvaluacion(equipo)
  else irAEquipo(equipo)
})

// Fecha del encuentro legible (la API la manda en ISO: «2026-10-06T00:00:00.000000Z»)
const fechaEncuentro = computed(() => {
  const f = encuentro.value?.fecha
  if (!f) return ''
  const d = new Date(String(f).slice(0, 10) + 'T12:00:00')
  return isNaN(d) ? String(f) : d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
})
// Título de la cabecera: el proyecto del encuentro (o, si no tiene, su clase o ciclo)
const tituloCabecera = computed(() =>
  proyecto.value?.titulo || (encuentro.value?.grupo && `Clase ${encuentro.value.grupo}`) || encuentro.value?.ciclo_formativo || 'Detalle de grupos')
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC] pt-16">
    <!-- Cabecera común (sustituye a la barra fija propia, que repetía título y botones): el
         proyecto como título, «Detalle de grupos» (y «solo lectura» si es un encuentro compartido)
         como antetítulo, volver al seguimiento de grupos y «Ver proyecto» como acción. -->
    <div class="mx-auto max-w-[90rem] px-4 pt-5 pb-4 sm:px-6 lg:px-8">
      <CabeceraSeccion :titulo="tituloCabecera" :volver="{ name: 'encuentros-registrados', query: { ver: 'seguimiento', encuentro: route.params.id } }" volver-label="Volver al seguimiento de grupos"
                       :antetitulo="encuentro && encuentro.puede_editar === false ? 'Detalle de grupos · solo lectura' : 'Detalle de grupos'"
                       subtitulo="Avance de cada grupo del encuentro, fase a fase.">
        <button v-if="proyecto?.uuid" type="button" class="accion-secundaria"
                @click="router.push({ name: 'startup-day-detalle', params: { uuid: proyecto.uuid } })">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          Ver reto ajustado
        </button>
      </CabeceraSeccion>
    </div>

    <!-- Cuerpo -->
    <div class="mx-auto max-w-[90rem] px-4 py-6 space-y-8 sm:px-6 lg:px-8">

      <!-- Estado de carga / error -->
      <div v-if="cargando" class="flex items-center justify-center py-24">
        <div class="w-8 h-8 border-2 border-centros border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error"
           class="rounded-3xl bg-red-50 border border-red-200 p-5 sm:p-8 text-center text-red-600 text-sm font-semibold">
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
                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Grupos</p>
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
              <p class="text-lg font-black text-[#121212]">{{ encuentro.grupo ? `Clase ${encuentro.grupo}` : '—' }}</p>
              <p class="text-sm text-gray-500">{{ encuentro.ciclo_formativo }}</p>
              <div class="flex flex-wrap gap-2 pt-1">
                <span v-if="encuentro.centro_educativo"
                      class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                  {{ encuentro.centro_educativo }}
                </span>
                <span v-if="encuentro.fecha"
                      class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                  {{ fechaEncuentro }}
                </span>
                <span v-if="encuentro.num_alumnos"
                      class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                  {{ encuentro.num_alumnos }} alumnos
                </span>
              </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-2">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Reto ajustado</p>
              <p v-if="proyecto" class="text-base font-black text-[#121212] leading-snug">{{ proyecto.titulo }}</p>
              <p v-else class="text-sm text-gray-400 italic">Sin reto ajustado asociado</p>
              <span v-if="proyecto?.estado"
                    class="inline-block px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                {{ ({ en_edicion: 'En edición', propuesta: 'Sin validar', validado: 'Validado', completado: 'Completado', archivado: 'Archivado' })[proyecto.estado] || proyecto.estado }}
              </span>
            </div>
          </div>
        </section>

        <!-- Sección: Fases del proyecto — referencia general de qué cubre cada fase, sin
             porcentajes: el progreso real y concreto de cada equipo ya se muestra arriba
             (contadores) y en su tarjeta (ring/badges). -->
        <section class="space-y-3 pt-6 border-t border-gray-100">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Fases del trabajo en el aula</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div v-for="f in FASES" :key="f.num"
                 class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-1.5">
              <span :class="[FASE_COLORS[f.color].bg, FASE_COLORS[f.color].text,
                             'w-9 h-9 rounded-xl flex items-center justify-center text-base']">
                <IconoFase :num="f.num" class="h-5 w-5" />
              </span>
              <p class="text-xs font-bold text-[#1F2937]">F{{ f.num }} · {{ f.label }}</p>
              <p class="text-[10px] text-gray-400">{{ f.desc }}</p>
              <p v-if="f.num === 4" class="text-[10px] font-bold text-centros">Aquí se asigna la nota final</p>
            </div>
          </div>
        </section>

        <!-- Sección: Equipos -->
        <section class="space-y-3 pt-6 border-t border-gray-100">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Grupos</p>
            <!-- Leyenda del stepper de fases de cada tarjeta (EquipoResolucionCard) -->
            <div v-if="equipos.length" class="flex items-center gap-3 flex-wrap text-[10px] font-semibold text-gray-500">
              <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 ring-2 ring-blue-200" />En curso</span>
              <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-centros" />Completada</span>
              <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500" />Validada</span>
              <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full border border-dashed border-gray-300 bg-gray-50" />Pendiente</span>
            </div>
          </div>

          <!-- Sin equipos -->
          <div v-if="!equipos.length"
               class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-10 text-center">
            <p class="text-gray-400 text-sm">No hay grupos creados en este encuentro todavía.</p>
          </div>

          <EquipoResolucionCard
            v-for="equipo in equipos" :key="equipo.id"
            :id="`equipo-${equipo.id}`"
            :class="['scroll-mt-24 transition-shadow duration-500', equipoResaltado === equipo.id ? 'ring-2 ring-rol-fuerte/50 ring-offset-4' : '']"
            :equipo="equipo"
            :abierto="equipoAbierto === equipo.id"
            :fases-abiertas="fasesAbiertas"
            :diagnostico-resaltado="diagnosticoResaltado === equipo.id"
            @toggle-equipo="toggleEquipo(equipo.id)"
            @toggle-fase="(faseNum) => abrirFase(equipo, faseNum)">

            <!-- Solo cuando el equipo ha completado sus 5 fases. Ambos botones hacen lo mismo
                 (irADiagnostico: desplegar + scroll/foco a la sección); solo cambia el aviso
                 visual: claro = diagnóstico pendiente de generar, sólido = ya generado. -->
            <template #acciones-cabecera="{ equipo: eq }">
              <button v-if="eq.fases_completas === 5"
                      @click.stop="irADiagnostico(eq)"
                      :class="['shrink-0 inline-flex items-center gap-1.5 min-h-9 px-3 py-1.5 rounded-full transition-colors text-[10px] font-black uppercase tracking-wider',
                               eq.diagnostico_final
                                 ? 'bg-emerald-500 text-white hover:bg-emerald-600'
                                 : 'bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100']">
                {{ eq.diagnostico_final ? 'Ir al diagnóstico' : 'Ir a generar diagnóstico' }}
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
              </button>
            </template>

            <!-- Evaluación del grupo editable (mismo panel y aspecto que el modal «Evaluación del grupo») -->
            <template #evaluacion="{ equipo: eq }">
              <EvaluacionGrupoPanel :equipo="eq" :encuentro="encuentro" :puede-editar="!!encuentro?.puede_editar"
                                    :resaltar-diagnostico="diagnosticoResaltado === eq.id"
                                    @actualizado="d => Object.assign(eq, d)" @evaluada="cargar" />
            </template>
          </EquipoResolucionCard>
        </section>

      </template>
    </div>

  </div>
</template>

