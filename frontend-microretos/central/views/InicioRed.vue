<!-- Inicio del espacio «Red DuaLab» de Central: lo pendiente de validar y el acceso a cada sección
     (captaciones, base de datos con empresas y centros adheridos, usuarios, papelera). -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import { getResumenCaptacion } from '../../src/services/captacionService.js'

const resumen = ref(null)
const error = ref(false)

onMounted(async () => {
  try { resumen.value = (await getResumenCaptacion()).data }
  catch { error.value = true }
})

const pendientes = computed(() => [
  { clave: 'empresas', texto: 'Empresas por validar', valor: resumen.value?.empresas_pendientes },
  { clave: 'centros', texto: 'Centros por validar', valor: resumen.value?.centros_pendientes },
  { clave: 'retos', texto: 'Retos en borrador por publicar', valor: resumen.value?.retos_pendientes },
])

// Tarjetas de sección (tarjeta de varias líneas: rectángulo curvo, no píldora)
const SECCIONES = [
  { ruta: { name: 'captaciones' }, titulo: 'Captaciones', texto: 'Empresas y centros que llegan de la app comercial y del formulario de la web: validarlos o descartarlos y publicar sus retos.' },
  { ruta: { name: 'base-datos' }, titulo: 'Base de datos', texto: 'Centros, familias, ciclos, currículo y empresas de todos los centros.' },
  { ruta: { name: 'usuarios' }, titulo: 'Usuarios', texto: 'Cuentas de docentes, empresas, admin de centro y comerciales: alta, activación y bloqueo.' },
  { ruta: { name: 'papelera' }, titulo: 'Papelera', texto: 'Lo eliminado de la base de datos: restaurarlo o borrarlo del todo.' },
]
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Red" destacado="DuaLab" color="text-administraciones" :volver="{ name: 'inicio' }" volver-label="Espacios"
                       subtitulo="Las empresas y centros que forman parte de DuaLab y los que están entrando: captaciones por validar, base de datos, usuarios y papelera." />

      <section aria-labelledby="pendientes-titulo">
        <h2 id="pendientes-titulo" class="mb-3 font-heading text-lg font-bold">Pendiente de tu revisión</h2>
        <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">No se ha podido cargar lo pendiente. Recarga la página.</p>
        <ul v-else class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <li v-for="p in pendientes" :key="p.clave">
            <RouterLink :to="{ name: 'captaciones' }"
                        class="flex h-full items-center gap-4 rounded-2xl border border-borde bg-white p-4 shadow-sm transition hover:border-administraciones/40 sm:p-5">
              <span class="min-w-[3rem] font-heading text-3xl font-black" :class="p.valor ? 'text-alumnos-dark' : 'text-administraciones-dark'">
                <span v-if="resumen === null" class="inline-block h-8 w-10 animate-pulse rounded-lg bg-gray-200 align-middle" aria-label="Cargando" />
                <template v-else>{{ p.valor ?? 0 }}</template>
              </span>
              <span class="text-sm font-semibold">{{ p.texto }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>

      <section aria-labelledby="secciones-titulo">
        <h2 id="secciones-titulo" class="mb-3 font-heading text-lg font-bold">Secciones</h2>
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
