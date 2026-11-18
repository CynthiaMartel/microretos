<!-- DuaLab Central → Alumnado destacado (P3b): DuaLab, como empresa intermediaria, elige al
     alumnado más destacado de los encuentros creativos para las prácticas. Se marca por alias (en
     la plataforma no hay nombres reales): después DuaLab pregunta al docente quién es cada alias y
     envía los datos a la empresa fuera de la plataforma. El docente del encuentro ve la marca. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import { getEncuentrosDestacados, marcarDestacado } from '../../src/services/destacadoService.js'
import { crearPlan } from '../../src/services/planService.js'

const encuentros = ref([])
const cargando = ref(true)
const error = ref('')
const buscar = ref('')
const soloValorados = ref(false)
const soloDestacados = ref(false)
const guardando = ref(new Set())

const totalDestacados = computed(() =>
  encuentros.value.reduce((n, e) => n + e.equipos.reduce((m, g) => m + g.miembros.filter(x => x.destacado).length, 0), 0))

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const params = {}
    if (buscar.value.trim()) params.buscar = buscar.value.trim()
    if (soloValorados.value) params.solo_valorados = 1
    if (soloDestacados.value) params.solo_destacados = 1
    encuentros.value = (await getEncuentrosDestacados(params)).data
  } catch {
    error.value = 'No se han podido cargar los encuentros. Recarga la página.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

let espera = null
watch(buscar, () => { clearTimeout(espera); espera = setTimeout(cargar, 400) })
watch([soloValorados, soloDestacados], cargar)

async function alternar(m) {
  if (guardando.value.has(m.id)) return
  guardando.value = new Set([...guardando.value, m.id])
  const anterior = m.destacado
  m.destacado = !anterior // optimista; se revierte si falla
  try {
    m.destacado = (await marcarDestacado(m.id, m.destacado)).data.destacado
  } catch {
    m.destacado = anterior
    error.value = 'No se pudo guardar la marca. Inténtalo de nuevo.'
  } finally {
    const s = new Set(guardando.value); s.delete(m.id); guardando.value = s
  }
}

// Plan de proyecto (P4c): DuaLab lo crea a partir de la propuesta del encuentro; queda a nombre
// del docente de esa propuesta, que lo completa y le crea el cronograma en DuaLab
const creandoPlan = ref(null)
const planesCreados = ref({})   // encuentroId → título del plan creado
async function crearPlanDe(e) {
  if (!e.propuesta.uuid || creandoPlan.value) return
  creandoPlan.value = e.id
  error.value = ''
  try {
    const { data } = await crearPlan({ origen_uuid: e.propuesta.uuid })
    planesCreados.value = { ...planesCreados.value, [e.id]: data.titulo }
  } catch (err) {
    const errores = err.response?.data?.errors
    error.value = (errores && Object.values(errores)[0]?.[0]) || err.response?.data?.message || 'No se pudo crear el plan.'
  } finally {
    creandoPlan.value = null
  }
}

const fecha = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Alumnado" destacado="destacado" color="text-rol"
                       subtitulo="Elige, por alias, al alumnado más destacado de los encuentros creativos para las prácticas. Después pide al docente quién es cada alias." />

      <!-- Filtros -->
      <div class="flex flex-col gap-3 rounded-2xl border border-borde bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-center">
        <label for="dest-buscar" class="sr-only">Buscar por propuesta</label>
        <input id="dest-buscar" v-model="buscar" type="search" placeholder="Buscar por propuesta…"
               class="min-w-0 rounded-xl px-3 py-2 text-sm sm:w-72" />
        <div class="flex flex-wrap gap-2">
          <button type="button" @click="soloValorados = !soloValorados" :aria-pressed="soloValorados"
                  class="inline-flex min-h-9 items-center rounded-full border px-4 text-sm font-semibold transition-colors"
                  :class="soloValorados ? 'border-rol-fuerte bg-rol-fuerte text-white' : 'border-borde bg-white hover:bg-gray-50'">
            Valorados por la empresa
          </button>
          <button type="button" @click="soloDestacados = !soloDestacados" :aria-pressed="soloDestacados"
                  class="inline-flex min-h-9 items-center rounded-full border px-4 text-sm font-semibold transition-colors"
                  :class="soloDestacados ? 'border-rol-fuerte bg-rol-fuerte text-white' : 'border-borde bg-white hover:bg-gray-50'">
            Con destacados
          </button>
        </div>
        <p class="text-sm text-texto-secundario sm:ml-auto" aria-live="polite">
          {{ encuentros.length }} encuentros · {{ totalDestacados }} destacados
        </p>
      </div>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-if="cargando" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!encuentros.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        No hay encuentros con estos filtros.
      </p>

      <ul v-else class="space-y-3">
        <li v-for="e in encuentros" :key="e.id" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <p class="break-words font-heading text-base font-bold">{{ e.propuesta.titulo || 'Sin propuesta' }}</p>
              <p class="mt-0.5 text-sm text-texto-secundario">
                <template v-if="e.propuesta.empresa">{{ e.propuesta.empresa }} · </template>{{ e.centro_educativo || 'Sin centro' }}
                · {{ e.curso }} Clase {{ e.grupo }} · {{ fecha(e.fecha) }}<template v-if="e.docente"> · {{ e.docente }}</template>
              </p>
            </div>
            <span v-if="e.valoracion" class="inline-flex shrink-0 items-center gap-1 self-start rounded-full bg-empresas/10 px-3 py-1 text-xs font-bold text-empresas-dark">
              Empresa: {{ e.valoracion.global }}/5<template v-if="e.valoracion.interes_plan_practicas"> · Plan en prácticas: {{ e.valoracion.interes_plan_practicas }}</template>
            </span>
            <span v-else class="inline-flex shrink-0 self-start rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-texto-secundario">Sin valorar por la empresa</span>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <button v-if="e.propuesta.uuid && !planesCreados[e.id]" type="button" @click="crearPlanDe(e)" :disabled="creandoPlan === e.id"
                    class="inline-flex min-h-9 items-center rounded-full border border-empresas/40 bg-white px-4 text-xs font-bold uppercase tracking-wide text-empresas-dark hover:bg-empresas/10 disabled:opacity-60">
              {{ creandoPlan === e.id ? 'Creando…' : 'Crear plan de proyecto' }}
            </button>
            <p v-if="planesCreados[e.id]" class="text-xs font-semibold text-empresas-dark" role="status">
              Plan creado: «{{ planesCreados[e.id] }}». Lo completa {{ e.docente || 'el docente' }} en DuaLab (Planes de proyecto).
            </p>
          </div>
          <p v-if="e.valoracion?.comentarios" class="mt-2 whitespace-pre-line break-words rounded-xl bg-[#F8FAFC] px-3 py-2 text-sm italic">«{{ e.valoracion.comentarios }}»</p>

          <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="g in e.equipos" :key="g.id" class="min-w-0 rounded-xl border border-borde p-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold">{{ g.nombre }}</p>
                <span class="text-xs text-texto-secundario">{{ g.fases_completadas }}/5 fases<template v-if="g.entrega"> · entregó</template></span>
              </div>
              <p v-if="g.entrega?.descripcion" class="mt-1 line-clamp-3 break-words text-xs text-texto-secundario">{{ g.entrega.descripcion }}</p>
              <a v-if="g.entrega?.url" :href="g.entrega.url" target="_blank" rel="noopener noreferrer nofollow"
                 class="mt-1 inline-flex min-h-9 items-center text-xs font-semibold text-rol-fuerte underline">Ver entregable</a>
              <ul class="mt-2 flex flex-wrap gap-1.5" :aria-label="`Alumnado de ${g.nombre}`">
                <li v-for="m in g.miembros" :key="m.id" class="min-w-0 max-w-full">
                  <button type="button" @click="alternar(m)" :aria-pressed="m.destacado" :disabled="guardando.has(m.id)"
                          :title="m.destacado ? 'Quitar de destacados' : 'Marcar como destacado para prácticas'"
                          class="inline-flex min-h-9 max-w-full items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors disabled:opacity-60"
                          :class="m.destacado ? 'border-amber-400 bg-amber-50 text-amber-900' : 'border-borde bg-white hover:bg-gray-50'">
                    <span aria-hidden="true">{{ m.destacado ? '★' : '☆' }}</span>
                    <span class="truncate">{{ m.alias }}</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
