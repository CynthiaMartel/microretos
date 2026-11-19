<!-- Papelera del comercial: sus entrevistas en curso borradas desde «Mis empresas»
     (BorrarEntrevistaModal). Aquí solo se restauran; borrarlas del todo es de la Papelera de
     DuaLab Central (superadmin). Misma plantilla que MisEntrevistas: cuatro estados de datos. -->
<script setup>
import { ref, onMounted } from 'vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import { getPapeleraCaptadas, restaurarEmpresaCaptada } from '../../src/services/comercialService.js'

const entrevistas = ref([])
const cargando    = ref(true)
const error       = ref('')
const restaurando = ref(null) // id
const aviso       = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    entrevistas.value = (await getPapeleraCaptadas()).data.data
  } catch {
    error.value = 'No se ha podido cargar la papelera. Comprueba la conexión.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

const nombreDe = (e) => e.nombre_comercial || e.email_general || 'Empresa sin nombre'

async function restaurar(e) {
  restaurando.value = e.id
  aviso.value = ''
  try {
    await restaurarEmpresaCaptada(e.id)
    entrevistas.value = entrevistas.value.filter(x => x.id !== e.id)
    aviso.value = `«${nombreDe(e)}» ha vuelto a «Mis empresas».`
  } catch {
    aviso.value = 'No se ha podido restaurar. Inténtalo de nuevo.'
  } finally {
    restaurando.value = null
  }
}

const fecha = (iso) => new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
</script>

<template>
  <div class="relative mx-auto max-w-6xl space-y-6 px-4 pb-10 pt-[5.25rem] sm:px-6 lg:px-8">
    <CabeceraSeccion antetitulo="Equipo comercial" titulo="Papelera" color="text-empresas-dark"
                     :volver="{ name: 'mis-entrevistas' }" volver-label="Mis empresas"
                     subtitulo="Las entrevistas en curso que has borrado. Restaúralas y vuelven tal cual estaban; borrarlas del todo solo lo puede hacer DuaLab." />

    <p v-if="aviso" role="status" class="break-words rounded-2xl border border-empresas/20 bg-empresas/5 px-4 py-3 text-sm text-azul-noche">{{ aviso }}</p>

    <div v-if="cargando" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-busy="true">
      <div v-for="n in 3" :key="n" class="tarjeta space-y-3">
        <div class="h-6 w-3/4 animate-pulse rounded-xl bg-gray-200" />
        <div class="h-4 w-1/2 animate-pulse rounded-xl bg-gray-200" />
      </div>
    </div>

    <div v-else-if="error" class="tarjeta flex flex-col items-center gap-3 py-12 text-center">
      <p class="text-sm text-texto-secundario">{{ error }}</p>
      <button type="button" class="accion-secundaria" @click="cargar">Reintentar</button>
    </div>

    <div v-else-if="!entrevistas.length" class="tarjeta flex flex-col items-center gap-3 py-14 text-center">
      <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-500" aria-hidden="true">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
      </span>
      <p class="font-heading text-lg font-bold">La papelera está vacía</p>
      <p class="max-w-md text-sm text-texto-secundario">Las entrevistas en curso que borres desde «Mis empresas» aparecerán aquí.</p>
    </div>

    <ul v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
      <li v-for="e in entrevistas" :key="e.id" class="tarjeta flex h-full flex-col gap-3">
        <div class="flex items-start gap-3">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-100 font-heading text-lg font-bold text-gray-500" aria-hidden="true">
            {{ nombreDe(e).charAt(0).toUpperCase() }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="break-words font-heading text-base font-bold">{{ nombreDe(e) }}</p>
            <p class="truncate text-sm text-texto-secundario">{{ [e.sector, e.municipio].filter(Boolean).join(' · ') || 'Sin sector' }}</p>
          </div>
        </div>
        <div v-if="e.familias?.length" class="flex flex-wrap gap-1.5">
          <span v-for="f in e.familias" :key="f.nombre" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ f.nombre }}</span>
        </div>
        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
          <span class="text-xs text-texto-secundario">Borrada el {{ fecha(e.eliminada_at) }}</span>
          <button type="button" class="accion-secundaria" :disabled="restaurando === e.id" @click="restaurar(e)">
            {{ restaurando === e.id ? 'Restaurando…' : 'Restaurar' }}
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>
