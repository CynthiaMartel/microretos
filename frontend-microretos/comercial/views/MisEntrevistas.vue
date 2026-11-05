<!-- Inicio del comercial: sus entrevistas (en curso y firmadas) y el botón para empezar otra.
     Plantilla de vista común (GUIA_DISENO §7): CabeceraSeccion, contenedor estándar y los
     cuatro estados de datos (§6.1). -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BannerComercial from '../components/BannerComercial.vue'
import { getMisEmpresas } from '../../src/services/comercialService.js'

const router   = useRouter()
const empresas = ref([])
const cargando = ref(true)
const error    = ref('')

// Estado = color + icono + texto (§6.2)
const ESTADO = {
  borrador:         { texto: 'En curso',                      clase: 'bg-amber-50 text-amber-800',             icono: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  pendiente_centro: { texto: 'Firmada · pendiente de centro', clase: 'bg-centros/10 text-centros',             icono: 'M9 12l2 2 4-4M12 3l7 4v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V7l7-4z' },
  asignada:         { texto: 'Firmada · disponible en centros', clase: 'bg-empresas/10 text-empresas-dark',    icono: 'M5 13l4 4L19 7' },
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    empresas.value = (await getMisEmpresas()).data.data
  } catch {
    error.value = 'No se han podido cargar tus entrevistas. Comprueba la conexión.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

const enCurso  = computed(() => empresas.value.filter(e => e.estado_captacion === 'borrador'))
const firmadas = computed(() => empresas.value.filter(e => e.estado_captacion !== 'borrador'))
const grupos   = computed(() => [
  { key: 'curso',    titulo: 'En curso', lista: enCurso.value },
  { key: 'firmadas', titulo: 'Firmadas', lista: firmadas.value },
].filter(g => g.lista.length))

const fecha = (iso) => new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
const nueva = () => router.push({ name: 'entrevista-nueva' })
</script>

<template>
  <!-- Centrado: es una app de una sola tarea en tablet, sin menú lateral que la desplace -->
  <div class="relative mx-auto max-w-6xl space-y-6 px-4 pb-10 pt-[5.25rem] sm:px-6 lg:px-8">
    <BannerComercial :en-curso="enCurso.length" :firmadas="firmadas.length" @nueva="nueva" />

    <!-- Carga -->
    <div v-if="cargando" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-busy="true">
      <div v-for="n in 6" :key="n" class="tarjeta space-y-3">
        <div class="h-5 w-32 animate-pulse rounded-full bg-gray-200" />
        <div class="h-6 w-3/4 animate-pulse rounded-xl bg-gray-200" />
        <div class="h-4 w-1/2 animate-pulse rounded-xl bg-gray-200" />
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="tarjeta flex flex-col items-center gap-3 py-12 text-center">
      <p class="text-sm text-texto-secundario">{{ error }}</p>
      <button type="button" class="accion-secundaria" @click="cargar">Reintentar</button>
    </div>

    <!-- Vacío -->
    <div v-else-if="!empresas.length" class="tarjeta flex flex-col items-center gap-3 py-14 text-center">
      <span class="flex h-14 w-14 items-center justify-center rounded-full bg-empresas/10 text-empresas-dark" aria-hidden="true">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1" /></svg>
      </span>
      <p class="font-heading text-lg font-bold">Aún no has hecho ninguna entrevista</p>
      <p class="max-w-md text-sm text-texto-secundario">Cuando estés con la empresa, empieza una entrevista: se guarda paso a paso y puedes retomarla desde aquí.</p>
      <button type="button" class="accion-principal mt-2" @click="nueva">Nueva entrevista</button>
    </div>

    <!-- Con datos -->
    <template v-else>
    <section v-for="g in grupos" :key="g.key" class="space-y-3">
      <h2 class="font-heading text-lg font-bold">{{ g.titulo }} <span class="text-sm font-semibold text-texto-secundario">· {{ g.lista.length }}</span></h2>
      <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <li v-for="e in g.lista" :key="e.id">
          <RouterLink :to="{ name: 'entrevista', params: { id: e.id } }"
                      class="tarjeta flex h-full flex-col gap-3 transition hover:border-rol-fuerte/40 hover:shadow-md">
            <div class="flex items-start gap-3">
              <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-empresas/10 font-heading text-lg font-bold text-empresas-dark" aria-hidden="true">
                {{ (e.nombre_comercial || '?').charAt(0).toUpperCase() }}
              </span>
              <div class="min-w-0 flex-1">
                <p class="break-words font-heading text-base font-bold">{{ e.nombre_comercial }}</p>
                <p class="truncate text-sm text-texto-secundario">{{ [e.sector, e.municipio].filter(Boolean).join(' · ') || 'Sin sector' }}</p>
              </div>
            </div>
            <span class="inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="ESTADO[e.estado_captacion]?.clase">
              <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="ESTADO[e.estado_captacion]?.icono" /></svg>
              {{ ESTADO[e.estado_captacion]?.texto ?? e.estado_captacion }}
            </span>
            <div v-if="e.familias?.length" class="flex flex-wrap gap-1.5">
              <span v-for="f in e.familias" :key="f.nombre" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ f.nombre }}</span>
            </div>
            <div class="mt-auto flex items-center justify-between gap-3 border-t border-gray-100 pt-3 text-xs text-texto-secundario">
              <span>{{ e.firmada_at ? `Firmada el ${fecha(e.firmada_at)}` : `Empezada el ${fecha(e.created_at)}` }}</span>
              <span class="shrink-0 font-semibold text-rol-fuerte">{{ e.estado_captacion === 'borrador' ? 'Continuar' : 'Ver' }} →</span>
            </div>
          </RouterLink>
        </li>
      </ul>
    </section>
    </template>
  </div>
</template>
