<!-- Inicio del espacio «Seguimiento de prácticas» de Central: resumen del avance del alumnado en las
     empresas (planes en curso, grupos retrasados) y acceso a la agenda y al alumnado destacado. -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import { getAgendaPracticas } from '../../src/services/agendaService.js'

const cronogramas = ref(null)
const error = ref(false)
onMounted(async () => {
  try { cronogramas.value = (await getAgendaPracticas()).data }
  catch { error.value = true; cronogramas.value = [] }
})

const resumen = computed(() => {
  const c = cronogramas.value ?? []
  return [
    { clave: 'curso', texto: 'Planes en curso', valor: c.filter(x => x.estado === 'en_curso').length },
    { clave: 'empezar', texto: 'Por empezar', valor: c.filter(x => x.estado === 'por_empezar').length },
    { clave: 'retrasados', texto: 'Grupos retrasados', valor: c.reduce((n, x) => n + x.grupos.filter(g => g.estado === 'retrasado').length, 0), alerta: true },
  ]
})

const SECCIONES = [
  { ruta: { name: 'agenda' }, titulo: 'Agenda de prácticas', texto: 'El avance del alumnado en las empresas de todos los centros: hitos, fase de cada grupo, retrasos y horas.' },
  { ruta: { name: 'destacados' }, titulo: 'Alumnado destacado', texto: 'Elige por alias al alumnado más destacado de los encuentros creativos, con la valoración de la empresa, y crea su plan.' },
]
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Seguimiento de" destacado="prácticas" color="text-administraciones"
                       :volver="{ name: 'inicio' }" volver-label="Espacios"
                       subtitulo="Cómo avanza el alumnado seleccionado en las empresas y quién pasa a las prácticas." />

      <section aria-labelledby="resumen-practicas">
        <h2 id="resumen-practicas" class="mb-3 font-heading text-lg font-bold">Resumen</h2>
        <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">No se ha podido cargar el resumen. Recarga la página.</p>
        <ul v-else class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <li v-for="r in resumen" :key="r.clave">
            <RouterLink :to="{ name: 'agenda' }"
                        class="flex h-full items-center gap-4 rounded-2xl border bg-white p-4 shadow-sm transition hover:border-administraciones/40 sm:p-5"
                        :class="r.alerta && r.valor ? 'border-red-200' : 'border-borde'">
              <span class="min-w-[3rem] font-heading text-3xl font-black" :class="r.alerta && r.valor ? 'text-red-600' : 'text-administraciones-dark'">
                <span v-if="cronogramas === null" class="inline-block h-8 w-10 animate-pulse rounded-lg bg-gray-200 align-middle" aria-label="Cargando" />
                <template v-else>{{ r.valor }}</template>
              </span>
              <span class="text-sm font-semibold">{{ r.texto }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>

      <section aria-labelledby="secciones-practicas">
        <h2 id="secciones-practicas" class="mb-3 font-heading text-lg font-bold">Secciones</h2>
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <li v-for="s in SECCIONES" :key="s.titulo">
            <RouterLink :to="s.ruta" class="flex h-full flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm transition hover:border-administraciones/40 sm:p-5">
              <span class="font-heading text-base font-bold">{{ s.titulo }}</span>
              <span class="mt-1 text-sm text-texto-secundario">{{ s.texto }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
