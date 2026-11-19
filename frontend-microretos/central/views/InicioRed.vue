<!-- Inicio del espacio «Red DuaLab» de Central: lo pendiente de validar y el acceso a cada sección
     (captaciones, base de datos con empresas y centros adheridos, usuarios, papelera). -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import { getResumenCaptacion } from '../../src/services/captacionService.js'
import { getFormulariosWeb, getCorreos } from '../../src/services/primerContactoService.js'
import { useAuthStore, ROLE_SUPERADMIN } from '../../src/stores/auth.js'

const auth = useAuthStore()
const resumen = ref(null)
const entrada = ref(null)   // { formularios, correos } nuevos
const error = ref(false)

onMounted(async () => {
  const [c, f, m] = await Promise.allSettled([getResumenCaptacion(), getFormulariosWeb({ per_page: 1 }), getCorreos({ per_page: 1 })])
  if (c.status === 'fulfilled') resumen.value = c.value.data
  else error.value = true
  entrada.value = {
    formularios: f.status === 'fulfilled' ? (f.value.data.resumen?.por_estado?.nuevo ?? 0) : null,
    correos: m.status === 'fulfilled' ? (m.value.data.resumen?.por_estado?.nuevo ?? 0) : null,
  }
})

// La cadena de un contacto: lo que entra → lo que hay que validar
const BLOQUES = computed(() => [
  { titulo: 'Lo que entra', items: [
    { clave: 'formularios', texto: 'Formularios nuevos', valor: entrada.value?.formularios, cargando: entrada.value === null, ruta: { name: 'primer-contacto', query: { estado: 'nuevo' } } },
    { clave: 'correos', texto: 'Correos sin leer', valor: entrada.value?.correos, cargando: entrada.value === null, ruta: { name: 'correo', query: { estado: 'nuevo' } } },
  ] },
  { titulo: 'Por validar', items: [
    { clave: 'empresas', texto: 'Empresas', valor: resumen.value?.empresas_pendientes, cargando: resumen.value === null && !error.value, ruta: { name: 'captaciones' } },
    { clave: 'centros', texto: 'Centros', valor: resumen.value?.centros_pendientes, cargando: resumen.value === null && !error.value, ruta: { name: 'captaciones' } },
    { clave: 'retos', texto: 'Retos en borrador', valor: resumen.value?.retos_pendientes, cargando: resumen.value === null && !error.value, ruta: { name: 'captaciones' } },
  ] },
])

// Tarjetas de sección (tarjeta de varias líneas: rectángulo curvo, no píldora). `soloSuperadmin`: Datos
const SECCIONES = [
  { ruta: { name: 'primer-contacto' }, titulo: 'Formularios de la web', texto: 'Lo que llega desde dualab.es: leerlo, responder desde info@ y ver si ya está en captaciones.' },
  { ruta: { name: 'correo' }, titulo: 'Correo (info@)', texto: 'El buzón de info@dualab.es: leer, responder y reenviar sin abrir el webmail.' },
  { ruta: { name: 'captaciones' }, titulo: 'Captaciones', texto: 'Empresas y centros que llegan de la app comercial y del formulario de la web: validarlos o descartarlos y publicar sus retos.' },
  { ruta: { name: 'base-datos' }, titulo: 'Base de datos', texto: 'Centros, familias, ciclos, currículo y empresas de todos los centros.', soloSuperadmin: true },
  { ruta: { name: 'usuarios' }, titulo: 'Usuarios', texto: 'Cuentas de docentes, empresas, admin de centro, comerciales y personal de DuaLab: alta, activación y bloqueo.', soloSuperadmin: true },
  { ruta: { name: 'papelera' }, titulo: 'Papelera', texto: 'Lo eliminado de la base de datos: restaurarlo o borrarlo del todo.', soloSuperadmin: true },
]
const secciones = computed(() => SECCIONES.filter(s => !s.soloSuperadmin || auth.userRole === ROLE_SUPERADMIN))
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Red" destacado="DuaLab" color="text-rol" :volver="{ name: 'inicio' }" volver-label="Espacios"
                       subtitulo="Todo lo que entra (formularios de la web y correo de info@) y la red de empresas y centros: captaciones por validar y alta." />

      <!-- Lo que entra y lo que hay que validar, de un vistazo -->
      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">No se ha podido cargar lo pendiente de validar. Recarga la página.</p>
      <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
        <section v-for="b in BLOQUES" :key="b.titulo" :class="b.items.length === 2 ? 'lg:col-span-2' : 'lg:col-span-3'" :aria-label="b.titulo">
          <h2 class="mb-3 font-heading text-lg font-bold">{{ b.titulo }}</h2>
          <ul class="grid grid-cols-1 gap-3" :class="b.items.length === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3'">
            <li v-for="p in b.items" :key="p.clave">
              <RouterLink :to="p.ruta"
                          class="flex h-full items-center gap-4 rounded-2xl border border-borde bg-white p-4 shadow-sm transition hover:border-rol/40 sm:flex-col sm:items-start sm:gap-1 sm:p-5">
                <span class="min-w-[3rem] font-heading text-3xl font-black" :class="p.valor ? 'text-rol-fuerte' : 'text-texto-secundario'">
                  <span v-if="p.cargando" class="inline-block h-8 w-10 animate-pulse rounded-lg bg-gray-200 align-middle" aria-label="Cargando" />
                  <template v-else>{{ p.valor ?? '—' }}</template>
                </span>
                <span class="text-sm font-semibold">{{ p.texto }}</span>
              </RouterLink>
            </li>
          </ul>
        </section>
      </div>

      <section aria-labelledby="secciones-titulo">
        <h2 id="secciones-titulo" class="mb-3 font-heading text-lg font-bold">Secciones</h2>
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
          <li v-for="s in secciones" :key="s.titulo">
            <RouterLink :to="s.ruta" class="flex h-full flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm transition hover:border-rol/40 sm:p-5">
              <span class="font-heading text-base font-bold">{{ s.titulo }}</span>
              <span class="mt-1 text-sm text-texto-secundario">{{ s.texto }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
