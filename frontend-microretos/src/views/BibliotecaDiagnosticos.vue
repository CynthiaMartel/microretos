<!-- Ruta: /evaluacion/diagnosticos (name: biblioteca-diagnosticos). Todos los diagnósticos
     finales de los equipos del docente, en un solo sitio. Usa el endpoint que ya alimenta
     Mis equipos (/encuentros/mis-grupos) y el mismo modal de MisGruposDetalle.vue. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMisGrupos } from '../services/encuentroService.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import DiagnosticoModal from '../components/DiagnosticoModal.vue'
import { formatCurso } from '../utils/formatCurso.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

const router = useRouter()

const grupos   = ref([])
const cargando = ref(true)
const error    = ref('')

onMounted(async () => {
  try {
    const { data } = await getMisGrupos()
    grupos.value = data
  } catch {
    error.value = 'No se han podido cargar los diagnósticos.'
  } finally {
    cargando.value = false
  }
})

// Un elemento por equipo con diagnóstico final generado, del más reciente al más antiguo
const diagnosticos = computed(() =>
  grupos.value
    .flatMap(g => g.equipos.filter(e => e.diagnostico_final).map(equipo => ({ equipo, encuentro: g.encuentro })))
    .sort((a, b) => new Date(b.equipo.diagnostico_generado_en || 0) - new Date(a.equipo.diagnostico_generado_en || 0)))

// Filtros: texto libre (equipo, proyecto, ciclo) y grupo
const busqueda = ref('')
const grupoSel = ref('')
const gruposDisponibles = computed(() => [...new Set(diagnosticos.value.map(d => d.encuentro.grupo).filter(Boolean))].sort())
const filtrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return diagnosticos.value.filter(d =>
    (!grupoSel.value || d.encuentro.grupo === grupoSel.value) &&
    (!q || [nombreGrupo(d.equipo), d.equipo.proyecto?.titulo, d.encuentro.ciclo_formativo].filter(Boolean).join(' ').toLowerCase().includes(q)))
})

const notaFinal = (equipo) => equipo.fases?.[4]?.nota_docente ?? null
const fecha = (isoStr) => isoStr ? new Date(isoStr).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''

// Modal de diagnóstico (mismo que en Mis equipos)
const abierto = ref(null)
</script>

<template>
  <div class="min-h-screen bg-[#F3F6FA] font-sans text-azul-noche pt-16">
    <div class="mx-auto max-w-[1440px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Biblioteca de" destacado="diagnósticos" color="text-administraciones"
                       subtitulo="Los diagnósticos finales de tus grupos: fortalezas, áreas de mejora y valoración de RA y CE de cada proyecto terminado." />

      <article class="card p-4">
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <label class="sr-only" for="diag-buscar">Buscar</label>
          <input id="diag-buscar" v-model="busqueda" type="search" placeholder="Buscar por grupo, proyecto o ciclo…"
                 class="h-9 min-w-0 flex-1 rounded-lg border border-gray-200 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-centros/40" />
          <label class="sr-only" for="diag-grupo">Clase</label>
          <select id="diag-grupo" v-model="grupoSel"
                  class="h-9 rounded-lg border border-gray-200 bg-white px-3 pr-8 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-centros/40">
            <option value="">Todas las clases</option>
            <option v-for="g in gruposDisponibles" :key="g" :value="g">Clase {{ g }}</option>
          </select>
        </div>

        <p v-if="error" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <div v-if="cargando" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"><div v-for="n in 6" :key="n" class="h-40 animate-pulse rounded-xl bg-gray-100" /></div>
        <div v-else-if="!diagnosticos.length" class="flex flex-col items-center justify-center py-12 text-center text-sm text-gray-500">
          Todavía no hay diagnósticos finales.
          <span class="mt-1">Se generan desde <button class="link" @click="router.push('/mis-grupos')">Mis grupos</button> cuando un grupo termina su proyecto.</span>
        </div>
        <p v-else-if="!filtrados.length" class="py-12 text-center text-sm text-gray-500">Ningún diagnóstico coincide con la búsqueda.</p>

        <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <li v-for="d in filtrados" :key="d.equipo.id">
            <button class="flex h-full w-full flex-col rounded-xl p-4 text-left ring-1 ring-gray-200/70 transition hover:-translate-y-0.5 hover:shadow-md"
                    @click="abierto = d">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="truncate font-semibold">{{ nombreGrupo(d.equipo) }}</p>
                  <p class="truncate text-xs text-gray-500">{{ d.equipo.proyecto?.titulo || 'Sin proyecto asociado' }}</p>
                </div>
                <span v-if="notaFinal(d.equipo) !== null"
                      class="shrink-0 rounded-lg bg-administraciones/10 px-2 py-1 font-heading text-sm font-bold text-[#0F7273]">{{ notaFinal(d.equipo) }}</span>
              </div>
              <p class="mt-3 line-clamp-3 flex-1 text-sm leading-snug text-gray-600">{{ d.equipo.diagnostico_final.resumen }}</p>
              <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px]">
                <span v-if="d.encuentro.grupo" class="rounded-full bg-gray-900 px-2 py-0.5 font-semibold text-white">Clase {{ d.encuentro.grupo }}</span>
                <span v-if="d.encuentro.curso" class="rounded-full bg-administraciones/10 px-2 py-0.5 font-semibold text-administraciones">{{ formatCurso(d.encuentro.curso) }} curso</span>
                <span class="ml-auto text-gray-400">{{ fecha(d.equipo.diagnostico_generado_en) }}</span>
              </div>
            </button>
          </li>
        </ul>
      </article>
    </div>

    <DiagnosticoModal :equipo="abierto?.equipo ?? null" :encuentro="abierto?.encuentro ?? null" @close="abierto = null" />
  </div>
</template>

<style scoped>
@reference "../style.css";

.card { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
.link { @apply font-semibold text-centros hover:underline; }
</style>
