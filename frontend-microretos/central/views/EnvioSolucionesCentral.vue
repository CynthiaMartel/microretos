<!-- Contactos → Empresas y centros → «Enviar soluciones a las empresas»: DuaLab, como intermediaria, envía
     a la empresa el enlace para ver las soluciones de los grupos de un encuentro y valorar el reto (P3).
     Un encuentro por fila con su estado (listo para enviar, enlace sin enviar, esperando a la empresa,
     valorado, sin entregas); al desplegarla, ValoracionEmpresaPanel. ?encuentro=ID abre esa fila.
     Antes lo hacía el docente desde la ficha del encuentro (la app ahora solo enseña el estado).
     Backend: ValoracionEmpresaCentralController. -->
<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import FiltrosPanel from '../../src/components/FiltrosPanel.vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import FiltroSelect from '../../src/components/FiltroSelect.vue'
import ValoracionEmpresaPanel from '../components/ValoracionEmpresaPanel.vue'
import { getValoracionesCentral } from '../../src/services/valoracionCentralService.js'
import { useFiltroEmpresaSector } from '../../src/composables/useFiltroEmpresaSector.js'

const route = useRoute()

const filas = ref([])
const cargando = ref(true)
const error = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    filas.value = (await getValoracionesCentral()).data.data
  } catch (e) {
    error.value = e.response?.data?.message || 'No se han podido cargar los encuentros.'
  } finally {
    cargando.value = false
  }
}

// ── Estado de cada encuentro ────────────────────────────────────────────────
const ESTADOS = {
  listo:        { texto: 'Listo para enviar',      clases: 'bg-empresas/15 text-empresas-dark' },
  enlace:       { texto: 'Enlace sin enviar',      clases: 'bg-amber-100 text-amber-800' },
  enviado:      { texto: 'Esperando a la empresa', clases: 'bg-centros/10 text-centros' },
  valorado:     { texto: 'Valorado',               clases: 'bg-empresas text-white' },
  sin_entregas: { texto: 'Sin entregas',           clases: 'bg-gray-100 text-texto-secundario' },
}
function estadoDe(f) {
  if (f.respondido_en) return 'valorado'
  if (f.solicitada && !f.caducado) return f.enviado_en ? 'enviado' : 'enlace'
  return f.grupos_con_entrega ? 'listo' : 'sin_entregas'
}

// ── Filtros ─────────────────────────────────────────────────────────────────
const busqueda = ref(route.query.buscar ?? '')
const estado = ref(route.query.estado ?? '')
const centro = ref('')
const centros = computed(() => [...new Set(filas.value.map(f => f.centro).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const baseCentro = computed(() => filas.value.filter(f => !centro.value || f.centro === centro.value))
const { filtroEmpresa, filtroSector, empresasDisponibles, sectoresDisponibles, cumpleEmpresaSector } = useFiltroEmpresaSector(baseCentro)

const cuenta = computed(() => baseCentro.value.reduce((n, f) => ({ ...n, [estadoDe(f)]: (n[estadoDe(f)] ?? 0) + 1 }), {}))
const opcionesEstado = computed(() => [
  { value: '', label: 'Todos' },
  ...['listo', 'enlace', 'enviado', 'valorado', 'sin_entregas'].map(k => ({ value: k, label: ESTADOS[k].texto, count: cuenta.value[k] ?? 0 })),
])

const visibles = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return baseCentro.value.filter(f => (!estado.value || estadoDe(f) === estado.value) && cumpleEmpresaSector(f)
    && (!q || [f.proyecto_titulo, f.empresa_nombre, f.docente, f.centro].some(t => t?.toLowerCase().includes(q))))
})
const hayFiltros = computed(() => !!(busqueda.value.trim() || estado.value || centro.value || filtroEmpresa.value || filtroSector.value))
function limpiar() { busqueda.value = ''; estado.value = ''; centro.value = ''; filtroEmpresa.value = ''; filtroSector.value = '' }

// ── Filas desplegadas ───────────────────────────────────────────────────────
const abiertas = ref(new Set())
function alternar(id) {
  const s = new Set(abiertas.value)
  s.has(id) ? s.delete(id) : s.add(id)
  abiertas.value = s
}
function actualizada(fila) {
  filas.value = filas.value.map(f => f.encuentro_id === fila.encuentro_id ? fila : f)
}

onMounted(async () => {
  await cargar()
  const id = Number(route.query.encuentro)
  if (id && filas.value.some(f => f.encuentro_id === id)) {
    abiertas.value = new Set([id])
    await nextTick()
    document.getElementById(`envio-${id}`)?.scrollIntoView({ block: 'start', behavior: 'smooth' })
  }
})

const fechaCorta = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Empresas y centros" titulo="Enviar soluciones a las" destacado="empresas" color="text-rol"
                       :volver="{ name: 'empresas-centros' }" volver-label="Empresas y centros"
                       subtitulo="Cuando los grupos de un encuentro entregan su solución, envía a la empresa el enlace para que las vea y valore el reto. No necesita cuenta." />

      <FiltrosPanel v-model:busqueda="busqueda" placeholder="Buscar por reto, empresa, docente o centro…"
                    :resultados="cargando ? null : visibles.length" :unidad="['encuentro', 'encuentros']"
                    :hay-activos="hayFiltros" @limpiar="limpiar">
        <FiltroSegmentado v-model="estado" label="Estado" :opciones="opcionesEstado" />
        <FiltroSelect v-model="centro" label="Centro educativo" :opciones="centros" todos="Todos los centros" />
        <FiltroSelect v-model="filtroEmpresa" label="Empresa" :opciones="empresasDisponibles" todos="Todas las empresas" />
        <FiltroSelect v-model="filtroSector" label="Sector de la empresa" :opciones="sectoresDisponibles" todos="Todos los sectores" />
      </FiltrosPanel>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
        {{ error }} <button type="button" class="ml-2 font-bold underline" @click="cargar">Reintentar</button>
      </p>
      <p v-else-if="cargando" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-else-if="!visibles.length" class="rounded-2xl border border-borde bg-white px-4 py-8 text-center text-sm text-texto-secundario">
        {{ hayFiltros ? 'No hay encuentros con estos filtros.' : 'Aún no hay encuentros con un reto ajustado.' }}
      </p>

      <ul v-else class="space-y-2">
        <li v-for="f in visibles" :id="`envio-${f.encuentro_id}`" :key="f.encuentro_id"
            class="scroll-mt-24 rounded-2xl border bg-white shadow-sm"
            :class="estadoDe(f) === 'listo' ? 'border-empresas/40' : 'border-borde'">
          <button type="button" class="flex w-full flex-col gap-2 rounded-2xl p-4 text-left transition hover:bg-gray-50 sm:flex-row sm:items-center sm:gap-4 sm:p-5"
                  :aria-expanded="abiertas.has(f.encuentro_id)" :aria-controls="`envio-panel-${f.encuentro_id}`" @click="alternar(f.encuentro_id)">
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="min-w-0 break-words font-heading text-base font-bold">{{ f.proyecto_titulo || 'Reto ajustado sin título' }}</span>
              <span class="mt-0.5 min-w-0 truncate text-sm text-texto-secundario">
                {{ [f.empresa_nombre || 'Sin empresa', f.empresa_sector].filter(Boolean).join(' · ') }}
              </span>
              <span class="mt-0.5 min-w-0 truncate text-xs text-texto-secundario">
                {{ [f.centro, f.docente, fechaCorta(f.fecha), [f.curso, f.grupo && `Clase ${f.grupo}`].filter(Boolean).join(' ')].filter(Boolean).join(' · ') }}
              </span>
              <span class="mt-1 text-xs text-texto-secundario">{{ f.grupos_con_entrega }} de {{ f.grupos_total }} grupos han entregado su solución</span>
            </span>
            <span class="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
              <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="ESTADOS[estadoDe(f)].clases">{{ ESTADOS[estadoDe(f)].texto }}</span>
              <svg class="h-5 w-5 shrink-0 text-texto-secundario transition-transform" :class="abiertas.has(f.encuentro_id) && 'rotate-180'"
                   fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </span>
          </button>
          <div v-if="abiertas.has(f.encuentro_id)" :id="`envio-panel-${f.encuentro_id}`" class="border-t border-borde bg-empresas/5 p-4 sm:p-5">
            <ValoracionEmpresaPanel :valoracion="f" @actualizada="actualizada" />
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
