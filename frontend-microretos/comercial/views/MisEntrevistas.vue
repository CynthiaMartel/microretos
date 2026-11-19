<!-- Inicio del comercial: sus entrevistas (en curso y firmadas) y el botón para empezar otra.
     Plantilla de vista común (GUIA_DISENO §7): CabeceraSeccion, contenedor estándar y los
     cuatro estados de datos (§6.1). -->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import BannerComercial from '../components/BannerComercial.vue'
import BorrarEntrevistaModal from '../components/BorrarEntrevistaModal.vue'
import { getMisEmpresas, restaurarEmpresaCaptada } from '../../src/services/comercialService.js'
import {
  pendientes, sincronizando, sincronizar, ultimaSincronizacion, pendienteDeEmpresa,
} from '../offline/entrevistasLocales.js'

const router   = useRouter()
const empresas = ref([])
const cargando = ref(true)
const error    = ref('')

// Estado = color + icono + texto (§6.2)
const ESTADO = {
  borrador:         { texto: 'En curso',                      clase: 'bg-amber-50 text-amber-800',             icono: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  pendiente_validacion: { texto: 'Firmada · pendiente de validar', clase: 'bg-centros/10 text-centros',      icono: 'M9 12l2 2 4-4M12 3l7 4v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V7l7-4z' },
  validada:         { texto: 'Validada · disponible en centros', clase: 'bg-empresas/10 text-empresas-dark',   icono: 'M5 13l4 4L19 7' },
  descartada:       { texto: 'Descartada por DuaLab',            clase: 'bg-gray-100 text-gray-600',            icono: 'M6 18L18 6M6 6l12 12' },
  // Guardadas en la tablet sin conexión (offline/entrevistasLocales.js)
  local_borrador:   { texto: 'En la tablet · sin enviar',        clase: 'bg-amber-50 text-amber-800',             icono: 'M7 2h10a2 2 0 012 2v16a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2zM12 18h.01' },
  local_firmada:    { texto: 'Firmada en la tablet · sin enviar', clase: 'bg-amber-50 text-amber-800',            icono: 'M7 2h10a2 2 0 012 2v16a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2zM12 18h.01' },
  local_error:      { texto: 'DuaLab no la ha aceptado · revisar', clase: 'bg-red-50 text-red-700',               icono: 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
}
const estadoDe = (e) => !e._local ? e.estado
  : e._error ? 'local_error'
  : e.estado === 'pendiente_envio' ? 'local_firmada' : 'local_borrador'
const rutaDe = (e) => e._local
  ? { name: 'entrevista-local', params: { clave: e._local } }
  : { name: 'entrevista', params: { id: e.id } }

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
// Tras enviar las de la tablet, la lista del servidor ya las incluye
watch(ultimaSincronizacion, () => { if (!cargando.value) cargar() })

// Las del servidor con cambios guardados en la tablet salen solo en «Guardadas en esta tablet»
const delServidor = computed(() => empresas.value.filter(e => !pendienteDeEmpresa(e.id)))
const enCurso  = computed(() => delServidor.value.filter(e => e.estado === 'borrador'))
const firmadas = computed(() => delServidor.value.filter(e => e.estado !== 'borrador'))
const grupos   = computed(() => [
  { key: 'tablet',   titulo: 'Guardadas en esta tablet', lista: pendientes.value },
  { key: 'curso',    titulo: 'En curso', lista: enCurso.value },
  { key: 'firmadas', titulo: 'Firmadas', lista: firmadas.value },
].filter(g => g.lista.length))

const hayAlgo = computed(() => empresas.value.length > 0 || pendientes.value.length > 0)
const enLinea = ref(navigator.onLine)
const alCambiarRed = () => { enLinea.value = navigator.onLine }
onMounted(() => { window.addEventListener('online', alCambiarRed); window.addEventListener('offline', alCambiarRed) })
onUnmounted(() => { window.removeEventListener('online', alCambiarRed); window.removeEventListener('offline', alCambiarRed) })

// Papelera: solo las entrevistas en curso del servidor (las de la tablet aún no están en DuaLab)
const aBorrar = ref(null)
const aviso   = ref(null) // { id, nombre } de la última borrada, para «Deshacer»
let avisoTimer = null
function onBorrada({ id, nombre }) {
  aBorrar.value = null
  empresas.value = empresas.value.filter(e => e.id !== id)
  aviso.value = { id, nombre }
  clearTimeout(avisoTimer)
  avisoTimer = setTimeout(() => { aviso.value = null }, 8000)
}
async function deshacer() {
  const { id } = aviso.value
  aviso.value = null
  try {
    await restaurarEmpresaCaptada(id)
  } finally {
    cargar()
  }
}
onUnmounted(() => clearTimeout(avisoTimer))

const fecha = (iso) => new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
const nueva = () => router.push({ name: 'entrevista-nueva' })
</script>

<template>
  <!-- Centrado: es una app de una sola tarea en tablet, sin menú lateral que la desplace -->
  <div class="relative mx-auto max-w-6xl space-y-6 px-4 pb-10 pt-[5.25rem] sm:px-6 lg:px-8">
    <BannerComercial :en-curso="enCurso.length + pendientes.length" :firmadas="firmadas.length" @nueva="nueva" />

    <!-- Quién valida lo captado (lo mismo se explica en Formularios de la gestión web) -->
    <p class="rounded-2xl border border-empresas/20 bg-empresas/5 px-4 py-3 text-sm text-azul-noche">
      Las empresas que envías las valida el <strong>superadmin de DuaLab</strong> antes de darlas de alta, junto con las que llegan por el formulario de la web.
      Hasta entonces verás «pendiente de validar».
    </p>

    <!-- Carga -->
    <div v-if="cargando" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-busy="true">
      <div v-for="n in 6" :key="n" class="tarjeta space-y-3">
        <div class="h-5 w-32 animate-pulse rounded-full bg-gray-200" />
        <div class="h-6 w-3/4 animate-pulse rounded-xl bg-gray-200" />
        <div class="h-4 w-1/2 animate-pulse rounded-xl bg-gray-200" />
      </div>
    </div>

    <!-- Error sin nada en la tablet -->
    <div v-else-if="error && !pendientes.length" class="tarjeta flex flex-col items-center gap-3 py-12 text-center">
      <p class="text-sm text-texto-secundario">{{ error }}</p>
      <button type="button" class="accion-secundaria" @click="cargar">Reintentar</button>
    </div>

    <!-- Vacío -->
    <div v-else-if="!hayAlgo" class="tarjeta flex flex-col items-center gap-3 py-14 text-center">
      <span class="flex h-14 w-14 items-center justify-center rounded-full bg-empresas/10 text-empresas-dark" aria-hidden="true">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1" /></svg>
      </span>
      <p class="font-heading text-lg font-bold">Aún no has hecho ninguna entrevista</p>
      <p class="max-w-md text-sm text-texto-secundario">Cuando estés con la empresa, empieza una entrevista: se guarda paso a paso y puedes retomarla desde aquí.</p>
      <button type="button" class="accion-principal mt-2" @click="nueva">Nueva entrevista</button>
    </div>

    <!-- Con datos -->
    <template v-else>
    <!-- Sin conexión con DuaLab, pero con entrevistas en la tablet -->
    <p v-if="error" role="status" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      Sin conexión con DuaLab: ahora solo ves las entrevistas guardadas en esta tablet. Puedes empezar una nueva igualmente.
    </p>

    <section v-for="g in grupos" :key="g.key" class="space-y-3">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="font-heading text-lg font-bold">{{ g.titulo }} <span class="text-sm font-semibold text-texto-secundario">· {{ g.lista.length }}</span></h2>
        <button v-if="g.key === 'tablet' && enLinea" type="button" class="accion-secundaria w-fit" :disabled="sincronizando" @click="sincronizar">
          {{ sincronizando ? 'Enviando…' : 'Enviar ahora' }}
        </button>
        <RouterLink v-if="g.key === 'curso'" :to="{ name: 'papelera' }" class="accion-secundaria w-fit">Papelera</RouterLink>
      </div>
      <p v-if="g.key === 'tablet'" class="text-sm text-texto-secundario">
        Están cifradas en esta tablet y se envían solas a DuaLab cuando hay conexión. No borres los datos del navegador ni desinstales la app hasta que se envíen.
      </p>
      <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <li v-for="e in g.lista" :key="e._local ?? e.id" class="relative">
          <RouterLink :to="rutaDe(e)"
                      class="tarjeta flex h-full flex-col gap-3 transition hover:border-rol-fuerte/40 hover:shadow-md">
            <div class="flex items-start gap-3">
              <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-empresas/10 font-heading text-lg font-bold text-empresas-dark" aria-hidden="true">
                {{ (e.nombre_comercial || e.email_general || '?').charAt(0).toUpperCase() }}
              </span>
              <div class="min-w-0 flex-1" :class="{ 'pr-10': g.key === 'curso' }">
                <!-- Enviada por correo y aún sin rellenar: solo se sabe su email -->
                <p class="break-words font-heading text-base font-bold">{{ e.nombre_comercial || e.email_general || 'Empresa sin nombre' }}</p>
                <p class="truncate text-sm text-texto-secundario">{{ [e.sector, e.municipio].filter(Boolean).join(' · ') || (e.nombre_comercial ? 'Sin sector' : 'Pendiente de que la empresa rellene sus datos') }}</p>
              </div>
            </div>
            <span class="inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="ESTADO[estadoDe(e)]?.clase">
              <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="ESTADO[estadoDe(e)]?.icono" /></svg>
              {{ ESTADO[estadoDe(e)]?.texto ?? e.estado }}
            </span>
            <p v-if="e._error" class="break-words text-xs text-red-700">{{ e._error }}</p>
            <!-- Preguntas enviadas por correo (EnviarFormulario) -->
            <p v-if="e.formulario?.respondido_at || (e.estado === 'borrador' && e.formulario?.abierto)"
               class="flex items-center gap-1.5 text-xs font-semibold" :class="e.formulario.respondido_at ? 'text-empresas-dark' : 'text-texto-secundario'">
              <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
              {{ e.formulario.respondido_at ? 'Completada y firmada por la empresa por correo' : 'Enviada por correo · sin completar' }}
            </p>
            <div v-if="e.familias?.length" class="flex flex-wrap gap-1.5">
              <span v-for="f in e.familias" :key="f.nombre" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ f.nombre }}</span>
            </div>
            <div class="mt-auto flex items-center justify-between gap-3 border-t border-gray-100 pt-3 text-xs text-texto-secundario">
              <span>{{ e.firmada_at ? `Firmada el ${fecha(e.firmada_at)}` : `Empezada el ${fecha(e.created_at)}` }}</span>
              <span class="shrink-0 font-semibold text-rol-fuerte">{{ e.estado === 'borrador' || e._error ? 'Continuar' : 'Ver' }} →</span>
            </div>
          </RouterLink>
          <!-- Fuera del enlace (un botón dentro de <a> no es válido); siempre visible, también en táctil -->
          <button v-if="g.key === 'curso' && enLinea" type="button" class="accion-icono absolute right-3 top-3"
                  :aria-label="`Borrar la entrevista de ${e.nombre_comercial || e.email_general || 'empresa sin nombre'}`"
                  title="Mover a la papelera" @click="aBorrar = e">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          </button>
        </li>
      </ul>
    </section>
    </template>

    <BorrarEntrevistaModal :visible="!!aBorrar" :empresa="aBorrar" @borrada="onBorrada" @cerrar="aBorrar = null" />

    <!-- Aviso tras borrar, con «Deshacer» (§6: feedback de cada acción) -->
    <div v-if="aviso" role="status"
         class="fixed inset-x-4 bottom-4 z-50 mx-auto flex max-w-lg flex-wrap items-center justify-between gap-3 rounded-2xl bg-azul-noche px-4 py-3 text-sm text-white shadow-xl">
      <span class="min-w-0 break-words">«{{ aviso.nombre }}» se ha movido a la papelera.</span>
      <div class="flex shrink-0 gap-2">
        <button type="button" class="h-9 rounded-full bg-white/15 px-4 font-semibold hover:bg-white/25" @click="deshacer">Deshacer</button>
        <RouterLink :to="{ name: 'papelera' }" class="inline-flex h-9 items-center rounded-full px-3 font-semibold text-white/80 hover:text-white">Ver papelera</RouterLink>
      </div>
    </div>
  </div>
</template>
