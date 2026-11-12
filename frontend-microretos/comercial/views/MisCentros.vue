<!-- Centros educativos que ha entrevistado el comercial: los guardados en la tablet sin enviar
     (offline/centrosLocales.js) y los ya enviados con su estado en DuaLab. Misma plantilla que
     «Empresas» (MisEntrevistas): cuatro estados de datos y tarjetas en rejilla. -->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import { getMisCentros } from '../../src/services/comercialService.js'
import { centrosPendientes, enviandoCentros, sincronizarCentros, ultimoEnvioCentros } from '../offline/centrosLocales.js'

const router   = useRouter()
const centros  = ref([])
const cargando = ref(true)
const error    = ref('')

const ESTADO = {
  pendiente_validacion: { texto: 'Enviada · pendiente de validar', clase: 'bg-centros/10 text-centros' },
  validada:             { texto: 'Validado · dado de alta en DuaLab', clase: 'bg-empresas/10 text-empresas-dark' },
  descartada:           { texto: 'Descartado por DuaLab',          clase: 'bg-gray-100 text-gray-600' },
}
const ACCION = { registro: 'Quiere darse de alta', info: 'Pide más información', demo: 'Pide una demo' }

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    centros.value = (await getMisCentros()).data.data
  } catch {
    error.value = 'No se han podido cargar tus centros. Comprueba la conexión.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)
watch(ultimoEnvioCentros, () => { if (!cargando.value) cargar() })

const hayAlgo = computed(() => centros.value.length > 0 || centrosPendientes.value.length > 0)
const enLinea = ref(navigator.onLine)
const alCambiarRed = () => { enLinea.value = navigator.onLine }
onMounted(() => { window.addEventListener('online', alCambiarRed); window.addEventListener('offline', alCambiarRed) })
onUnmounted(() => { window.removeEventListener('online', alCambiarRed); window.removeEventListener('offline', alCambiarRed) })

const fecha = (iso) => new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
const nueva = () => router.push({ name: 'centro-nuevo' })
const corregir = (clave) => router.push({ name: 'centro-nuevo', query: { local: clave } })
</script>

<template>
  <div class="relative mx-auto max-w-6xl space-y-6 px-4 pb-10 pt-[5.25rem] sm:px-6 lg:px-8">
    <CabeceraSeccion antetitulo="Equipo comercial" titulo="Mis" destacado="centros" color="text-centros"
                     subtitulo="Los centros que envías los valida el superadmin de DuaLab antes de darlos de alta, junto con los que llegan por el formulario de la web.">
      <button type="button" class="accion-principal" @click="nueva">Nueva entrevista a centro</button>
    </CabeceraSeccion>

    <div v-if="cargando && !centrosPendientes.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-busy="true">
      <div v-for="n in 3" :key="n" class="tarjeta space-y-3">
        <div class="h-5 w-32 animate-pulse rounded-full bg-gray-200" />
        <div class="h-6 w-3/4 animate-pulse rounded-xl bg-gray-200" />
      </div>
    </div>

    <div v-else-if="error && !centrosPendientes.length" class="tarjeta flex flex-col items-center gap-3 py-12 text-center">
      <p class="text-sm text-texto-secundario">{{ error }}</p>
      <button type="button" class="accion-secundaria" @click="cargar">Reintentar</button>
    </div>

    <div v-else-if="!hayAlgo" class="tarjeta flex flex-col items-center gap-3 py-14 text-center">
      <span class="flex h-14 w-14 items-center justify-center rounded-full bg-centros/10 text-centros" aria-hidden="true">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 10L12 5 2 10l10 5 10-5zM6 12v5c3 2 9 2 12 0v-5" /></svg>
      </span>
      <p class="font-heading text-lg font-bold">Aún no has entrevistado a ningún centro</p>
      <p class="max-w-md text-sm text-texto-secundario">Son los mismos datos que el formulario de centros de la web. DuaLab los valida antes de dar de alta el centro.</p>
      <button type="button" class="accion-principal mt-2" @click="nueva">Nueva entrevista a centro</button>
    </div>

    <template v-else>
      <p v-if="error" role="status" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Sin conexión con DuaLab: ahora solo ves las entrevistas guardadas en esta tablet.
      </p>

      <!-- En la tablet, sin enviar -->
      <section v-if="centrosPendientes.length" class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="font-heading text-lg font-bold">Guardadas en esta tablet <span class="text-sm font-semibold text-texto-secundario">· {{ centrosPendientes.length }}</span></h2>
          <button v-if="enLinea" type="button" class="accion-secundaria w-fit" :disabled="enviandoCentros" @click="sincronizarCentros">
            {{ enviandoCentros ? 'Enviando…' : 'Enviar ahora' }}
          </button>
        </div>
        <p class="text-sm text-texto-secundario">Están cifradas en esta tablet y se envían solas a DuaLab cuando hay conexión.</p>
        <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          <li v-for="r in centrosPendientes" :key="r.clave" class="tarjeta flex flex-col gap-3">
            <p class="break-words font-heading text-base font-bold">{{ r.payload.centroNombre }}</p>
            <p class="truncate text-sm text-texto-secundario">{{ [r.payload.municipio, r.payload.provincia].filter(Boolean).join(' · ') }}</p>
            <span class="w-fit rounded-full px-2.5 py-1 text-xs font-semibold" :class="r.error ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'">
              {{ r.error ? 'DuaLab no la ha aceptado · revisar' : 'En la tablet · sin enviar' }}
            </span>
            <p v-if="r.error" class="break-words text-xs text-red-700">{{ r.error }}</p>
            <div class="mt-auto flex items-center justify-between gap-3 border-t border-gray-100 pt-3 text-xs text-texto-secundario">
              <span>Hecha el {{ fecha(r.creadaAt) }}</span>
              <button v-if="r.error" type="button" class="shrink-0 font-semibold text-rol-fuerte" @click="corregir(r.clave)">Corregir →</button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Enviados -->
      <section v-if="centros.length" class="space-y-3">
        <h2 class="font-heading text-lg font-bold">Enviados <span class="text-sm font-semibold text-texto-secundario">· {{ centros.length }}</span></h2>
        <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
          <li v-for="c in centros" :key="c.id" class="tarjeta flex flex-col gap-3">
            <div class="flex items-start gap-3">
              <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-centros/10 font-heading text-lg font-bold text-centros" aria-hidden="true">
                {{ (c.centro_nombre || '?').charAt(0).toUpperCase() }}
              </span>
              <div class="min-w-0 flex-1">
                <p class="break-words font-heading text-base font-bold">{{ c.centro_nombre }}</p>
                <p class="truncate text-sm text-texto-secundario">{{ [c.municipio, c.provincia].filter(Boolean).join(' · ') }}</p>
              </div>
            </div>
            <span class="w-fit rounded-full px-2.5 py-1 text-xs font-semibold" :class="ESTADO[c.estado]?.clase">{{ ESTADO[c.estado]?.texto ?? c.estado }}</span>
            <p class="text-sm text-texto-secundario">
              {{ ACCION[c.accion] }} · {{ c.contacto_nombre }} {{ c.contacto_apellidos }}<template v-if="c.contacto_cargo"> ({{ c.contacto_cargo }})</template>
            </p>
            <div v-if="c.familias?.length" class="flex flex-wrap gap-1.5">
              <span v-for="f in c.familias" :key="f" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ f }}</span>
            </div>
            <p class="mt-auto border-t border-gray-100 pt-3 text-xs text-texto-secundario">Enviada el {{ fecha(c.created_at) }}</p>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
