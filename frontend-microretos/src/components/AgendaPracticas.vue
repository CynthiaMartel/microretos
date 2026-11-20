<!-- Agenda de prácticas (A1/A2): cronogramas de los planes de proyecto con sus hitos fechados y el
     avance de cada grupo frente a la fase que toca por fecha («al día» / «retrasado»). Común a la
     app DuaLab (docente: sus planes) y a DuaLab Central (`central`: todos, con centro y docente).
     Solo lectura: el registro de horas llegará en A3. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { FASES_PROYECTO } from '../config/fasesProyecto.js'
import { getAgendaPracticas } from '../services/agendaService.js'
import MarcaDestacado from './MarcaDestacado.vue'
import RegistroHorasPanel from './RegistroHorasPanel.vue'
import FiltrosPanel from './FiltrosPanel.vue'
import FiltroSegmentado from './FiltroSegmentado.vue'

const props = defineProps({
  central: { type: Boolean, default: false },
})
const emit = defineEmits(['ver-grupos'])

const cronogramas = ref([])
const cargando = ref(true)
const error = ref('')
const filtro = ref('todos')   // 'todos' | 'en_curso' | 'por_empezar' | 'terminado'
const filtroRetrasos = ref('')
const soloRetrasos = computed(() => filtroRetrasos.value === 'con')
const buscar = ref('')

onMounted(async () => {
  try {
    cronogramas.value = (await getAgendaPracticas()).data
  } catch {
    error.value = 'No se ha podido cargar la agenda. Recarga la página.'
  } finally {
    cargando.value = false
  }
})

const retrasados = (c) => c.grupos.filter(g => g.estado === 'retrasado').length
const resumen = computed(() => ({
  en_curso: cronogramas.value.filter(c => c.estado === 'en_curso').length,
  por_empezar: cronogramas.value.filter(c => c.estado === 'por_empezar').length,
  grupos_retrasados: cronogramas.value.reduce((n, c) => n + retrasados(c), 0),
}))
const norm = (t) => (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const visibles = computed(() => cronogramas.value.filter(c =>
  (filtro.value === 'todos' || c.estado === filtro.value)
  && (!soloRetrasos.value || retrasados(c) > 0)
  && (!buscar.value || [c.plan.titulo, c.plan.empresa, c.centro, c.docente].some(t => norm(t).includes(norm(buscar.value))))))

// ── Calendario de planes: una barra por plan de inicio a fin, con sus hitos y la línea de hoy ──
const DIA = 86_400_000
const t = (iso) => new Date(iso + 'T00:00:00').getTime()
const conFechas = computed(() => visibles.value.filter(c => c.fecha && c.fecha_fin))
const rango = computed(() => {
  if (!conFechas.value.length) return null
  const ini = new Date(Math.min(...conFechas.value.map(c => t(c.fecha))))
  const fin = new Date(Math.max(...conFechas.value.map(c => t(c.fecha_fin))))
  const desde = new Date(ini.getFullYear(), ini.getMonth(), 1).getTime()
  const hasta = new Date(fin.getFullYear(), fin.getMonth() + 1, 1).getTime()
  return { desde, hasta, total: hasta - desde }
})
const pct = (ms) => ((ms - rango.value.desde) / rango.value.total) * 100
const meses = computed(() => {
  if (!rango.value) return []
  const lista = []
  const d = new Date(rango.value.desde)
  while (d.getTime() < rango.value.hasta) {
    const inicio = d.getTime()
    d.setMonth(d.getMonth() + 1)
    lista.push({ clave: inicio, nombre: new Date(inicio).toLocaleDateString('es-ES', { month: 'short', year: '2-digit' }),
                 izq: pct(inicio), ancho: pct(d.getTime()) - pct(inicio) })
  }
  return lista
})
const hoyPct = computed(() => {
  if (!rango.value) return null
  const h = new Date(); h.setHours(0, 0, 0, 0)
  const p = pct(h.getTime())
  return p >= 0 && p <= 100 ? p : null
})
const barra = (c) => ({ left: pct(t(c.fecha)) + '%', width: Math.max(1, pct(t(c.fecha_fin) + DIA) - pct(t(c.fecha))) + '%' })

const FILTROS = [
  { value: 'todos', label: 'Todos' }, { value: 'en_curso', label: 'En curso' },
  { value: 'por_empezar', label: 'Por empezar' }, { value: 'terminado', label: 'Terminados' },
]

// Cada plan con un color (de la familia de la marca) que se repite en su barra del calendario y en
// su tarjeta: así se ve qué hitos son de qué plan. Por posición en la lista completa, para que no
// cambie al filtrar.
const COLORES_PLAN = ['#3072AA', '#8E6FD8', '#2BA672', '#F2665E', '#19A7A8', '#B85E0A', '#509928', '#6B4BB8']
const colorPlan = (c) => COLORES_PLAN[Math.max(0, cronogramas.value.indexOf(c)) % COLORES_PLAN.length]
const hitoPct = (h) => pct(t(h.fecha) + DIA / 2)
function irAPlan(c) {
  document.getElementById(`plan-${c.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
const fecha = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Sin fecha'
// Tras anotar o borrar horas: se actualizan los totales en la propia agenda
function aplicarHoras(c, miembroId, delta) {
  c.horas_total = Math.round((c.horas_total + delta) * 100) / 100
  for (const g of c.grupos) for (const m of g.miembros) if (m.id === miembroId) m.horas = Math.round((m.horas + delta) * 100) / 100
}

const fechaCorta = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short' }) : '—'
const nombreFase = (n) => FASES_PROYECTO.find(f => f.num === n)?.label ?? `F${n}`
const ESTADO_GRUPO = {
  al_dia:    { l: 'Al día',    c: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
  retrasado: { l: 'Retrasado', c: 'bg-red-50 text-red-700 border-red-200' },
  terminado: { l: 'Terminado', c: 'bg-sky-50 text-sky-800 border-sky-200' },
}
const ESTADO_CRONO = { en_curso: 'En curso', por_empezar: 'Por empezar', terminado: 'Terminado' }
</script>

<template>
  <div class="space-y-4">
    <!-- Resumen -->
    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Resumen de la agenda">
      <li class="rounded-2xl border border-borde bg-white p-4 shadow-sm">
        <p class="font-heading text-3xl font-black text-rol-fuerte">{{ resumen.en_curso }}</p>
        <p class="text-sm font-semibold">Planes en curso</p>
      </li>
      <li class="rounded-2xl border border-borde bg-white p-4 shadow-sm">
        <p class="font-heading text-3xl font-black text-azul-noche">{{ resumen.por_empezar }}</p>
        <p class="text-sm font-semibold">Por empezar</p>
      </li>
      <li class="rounded-2xl border bg-white p-4 shadow-sm" :class="resumen.grupos_retrasados ? 'border-red-200' : 'border-borde'">
        <p class="font-heading text-3xl font-black" :class="resumen.grupos_retrasados ? 'text-red-600' : 'text-texto-secundario'">{{ resumen.grupos_retrasados }}</p>
        <p class="text-sm font-semibold">Grupos retrasados</p>
      </li>
    </ul>

    <!-- Filtros: mismo panel que el resto de bibliotecas (FiltrosPanel + FiltroSegmentado) -->
    <FiltrosPanel separadores v-model:busqueda="buscar"
                  :placeholder="central ? 'Buscar por plan, empresa, centro o docente' : 'Buscar por plan o empresa'"
                  :resultados="visibles.length">
      <FiltroSegmentado label="Estado" v-model="filtro" :opciones="FILTROS" />
      <FiltroSegmentado label="Retrasos" v-model="filtroRetrasos" :opciones="[{ value: '', label: 'Todos' }, { value: 'con', label: 'Con retrasos' }]" />
    </FiltrosPanel>

    <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
    <p v-if="cargando" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
    <p v-else-if="!visibles.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
      No hay planes con estos filtros.
    </p>

    <!-- Calendario de planes: una fila por plan, de inicio a fin, con sus hitos y la línea de hoy.
         Mismo color que su tarjeta; pulsar una fila lleva a la tarjeta. -->
    <section v-if="!cargando && rango" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" aria-labelledby="titulo-calendario-planes">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 id="titulo-calendario-planes" class="font-heading text-lg font-bold text-azul-noche">Calendario de planes</h2>
        <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-texto-secundario">
          <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rotate-45 rounded-[2px] bg-azul-noche" aria-hidden="true" />Hito</span>
          <span v-if="hoyPct !== null" class="inline-flex items-center gap-1.5"><span class="h-3 w-0.5 bg-red-500" aria-hidden="true" />Hoy</span>
        </p>
      </div>
      <div class="overflow-x-auto">
        <div class="min-w-[40rem]">
          <!-- Meses -->
          <div class="flex">
            <span class="w-44 shrink-0 sm:w-56" />
            <div class="relative h-6 flex-1 border-b border-borde">
              <span v-for="m in meses" :key="m.clave" class="absolute top-0 border-l border-borde pl-1.5 text-[11px] font-semibold uppercase tracking-wide text-texto-secundario"
                    :style="{ left: m.izq + '%', width: m.ancho + '%' }">{{ m.nombre }}</span>
            </div>
          </div>
          <!-- Una fila por plan -->
          <ul class="relative [--col-plan:11rem] sm:[--col-plan:14rem]">
            <li v-for="c in conFechas" :key="c.id" class="flex items-center border-b border-gray-100 last:border-b-0">
              <button type="button" @click="irAPlan(c)" class="flex w-44 shrink-0 items-center gap-2 py-2.5 pr-3 text-left sm:w-56"
                      :title="`Ir a «${c.plan.titulo}»`">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: colorPlan(c) }" aria-hidden="true" />
                <span class="min-w-0">
                  <span class="block truncate text-sm font-semibold text-azul-noche hover:underline">{{ c.plan.titulo || 'Plan sin título' }}</span>
                  <span class="block truncate text-xs text-texto-secundario">{{ c.plan.empresa || ESTADO_CRONO[c.estado] }}</span>
                </span>
              </button>
              <div class="relative h-10 flex-1">
                <!-- Rejilla de meses -->
                <span v-for="m in meses" :key="m.clave" class="absolute inset-y-0 border-l border-gray-100" :style="{ left: m.izq + '%' }" aria-hidden="true" />
                <!-- Barra del plan -->
                <!-- Zona táctil de 36px; la barra visible sigue siendo de 12px -->
                <button type="button" @click="irAPlan(c)" class="group absolute top-1/2 flex h-9 -translate-y-1/2 items-center"
                        :style="barra(c)" :aria-label="`${c.plan.titulo}: del ${fecha(c.fecha)} al ${fecha(c.fecha_fin)}`">
                  <span class="h-3 w-full rounded-full opacity-90 transition-opacity group-hover:opacity-100" aria-hidden="true"
                        :style="{ background: colorPlan(c) + '33', boxShadow: `inset 0 0 0 1.5px ${colorPlan(c)}` }" />
                </button>
                <!-- Hitos -->
                <span v-for="h in c.hitos" :key="h.numero"
                      class="absolute top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rotate-45 rounded-[3px] ring-2 ring-white"
                      :style="{ left: hitoPct(h) + '%', background: colorPlan(c) }"
                      :title="`Hito ${h.numero} · ${fecha(h.fecha)} · ${h.fases.map(f => 'F' + f).join(' · ')}`" />
              </div>
            </li>
            <!-- Hoy -->
            <span v-if="hoyPct !== null" class="pointer-events-none absolute inset-y-0 w-0.5 bg-red-500/70"
                  :style="{ left: `calc(${hoyPct / 100} * (100% - var(--col-plan)) + var(--col-plan))` }" aria-hidden="true" />
          </ul>
        </div>
      </div>
    </section>

    <ul v-if="!cargando && visibles.length" class="space-y-4">
      <li v-for="c in visibles" :key="c.id" :id="`plan-${c.id}`"
          class="scroll-mt-24 rounded-2xl border border-borde bg-white p-4 shadow-sm transition-shadow hover:shadow-md sm:p-5"
          :style="{ borderTop: `4px solid ${colorPlan(c)}` }">
        <!-- Cabecera del plan -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0">
            <p class="flex items-start gap-2 break-words font-heading text-base font-bold">
              <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: colorPlan(c) }" aria-hidden="true" />
              {{ c.plan.titulo || 'Plan sin título' }}
            </p>
            <p class="mt-0.5 text-sm text-texto-secundario">
              <template v-if="c.plan.empresa">{{ c.plan.empresa }} · </template>
              <template v-if="central">{{ c.centro || 'Sin centro' }} · {{ c.docente || 'Sin docente' }} · </template>
              {{ fecha(c.fecha) }} → {{ fecha(c.fecha_fin) }}<template v-if="c.plan.horas_estimadas"> · {{ c.plan.horas_estimadas }} h</template>
            </p>
          </div>
          <div class="flex shrink-0 flex-wrap gap-2 self-start">
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold">{{ ESTADO_CRONO[c.estado] }}</span>
            <button v-if="!central && c.grupos.length" type="button" class="accion-secundaria" @click="emit('ver-grupos', c.id)">Detalle de grupos</button>
          </div>
        </div>

        <!-- Hitos: línea con fecha; el actual resaltado -->
        <ol class="mt-3 flex gap-2 overflow-x-auto pb-1" :aria-label="`Hitos de ${c.plan.titulo}`">
          <li v-for="h in c.hitos" :key="h.numero"
              class="min-w-[7.5rem] shrink-0 rounded-xl border px-3 py-2"
              :class="h.numero === c.hito_actual ? 'bg-white' : (c.hito_actual && h.numero < c.hito_actual ? 'border-borde bg-gray-50 opacity-70' : 'border-borde')"
              :style="h.numero === c.hito_actual ? { borderColor: colorPlan(c), boxShadow: `inset 0 0 0 1px ${colorPlan(c)}` } : {}">
            <p class="flex items-center gap-1.5 text-xs font-bold"><span class="h-2 w-2 rotate-45 rounded-[2px]" :style="{ background: colorPlan(c) }" aria-hidden="true" />Hito {{ h.numero }}<span v-if="h.numero === c.hito_actual" class="sr-only"> (actual)</span></p>
            <p class="text-xs text-texto-secundario">{{ fechaCorta(h.fecha) }}</p>
            <p class="text-[11px] font-semibold text-azul-noche">{{ h.fases.map(f => 'F' + f).join(' · ') }}</p>
          </li>
        </ol>
        <p v-if="c.siguiente_hito" class="mt-1 text-xs text-texto-secundario">
          Próximo: hito {{ c.siguiente_hito.numero }} el {{ fecha(c.siguiente_hito.fecha) }}
          ({{ c.siguiente_hito.fases.map(nombreFase).join(', ') }})
        </p>

        <!-- Grupos -->
        <p v-if="!c.grupos.length" class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">
          Grupos sin generar todavía<template v-if="c.alumnado_pendiente.length"> · {{ c.alumnado_pendiente.join(', ') }}</template>.
        </p>
        <ul v-else class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
          <li v-for="g in c.grupos" :key="g.id" class="min-w-0 rounded-xl border border-borde p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-sm font-bold">{{ g.nombre }}</p>
              <span class="rounded-full border px-2.5 py-0.5 text-xs font-bold" :class="ESTADO_GRUPO[g.estado].c">{{ ESTADO_GRUPO[g.estado].l }}</span>
            </div>
            <p class="mt-1 text-xs text-texto-secundario">
              F{{ g.fase_actual }} · {{ nombreFase(g.fase_actual) }}
              <template v-if="c.fase_esperada !== null && g.estado === 'retrasado'"> (toca F{{ c.fase_esperada }})</template>
            </p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="g.progreso" aria-valuemin="0" aria-valuemax="100" :aria-label="`Avance de ${g.nombre}`">
              <div class="h-full rounded-full" :style="{ width: g.progreso + '%', background: colorPlan(c) }" />
            </div>
            <ul class="mt-2 flex flex-wrap gap-1.5">
              <li v-for="m in g.miembros" :key="m.alias"
                  class="inline-flex max-w-full items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold">
                <span class="truncate">{{ m.alias }}</span><MarcaDestacado v-if="m.destacado" />
                <span class="shrink-0 text-texto-secundario" :title="`${m.horas} h anotadas`">· {{ m.horas }} h</span>
              </li>
            </ul>
          </li>
        </ul>

        <!-- Horas de prácticas (A3): docente y DuaLab -->
        <RegistroHorasPanel :cronograma="c" @cambio="(id, delta) => aplicarHoras(c, id, delta)" />
      </li>
    </ul>
  </div>
</template>
