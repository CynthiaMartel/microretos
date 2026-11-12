<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute, RouterView } from 'vue-router'
import { useAuthStore } from '../src/stores/auth.js'
import AppCredit from '../src/components/AppCredit.vue'
import AvisoSinConexion from '../src/components/AvisoSinConexion.vue'
import { useNuevaVersion } from '../src/composables/useNuevaVersion.js'
import PanelLateralComercial from './components/PanelLateralComercial.vue'
import { recordarSesion, olvidarSesion, comprobarAlVolver } from './offline/sesionLocal.js'
import { recargarPendientes, sincronizar, conCache } from './offline/entrevistasLocales.js'
import { recargarCentrosPendientes, sincronizarCentros } from './offline/centrosLocales.js'
import { getFamilias } from '../src/services/datosFPService.js'
import { getTextosLegales, getTextosLegalesCentros } from '../src/services/comercialService.js'
import logo from '../src/assets/logo_colores.png'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()

// Color de la sección, como los colores del logo: empresas en verde y centros en azul.
// style.css redefine --color-rol según html[data-rol] (.btn-paso, .accion-principal, foco…);
// 'centros' no tiene regla propia y deja el azul por defecto (#3072AA).
const seccion = computed(() => route.meta.seccion === 'centros' ? 'centros' : 'empresas')
watch(seccion, (s) => { document.documentElement.dataset.rol = s }, { immediate: true })

// App instalada (PWA): aviso de versión nueva tras un deploy, como en la app principal.
// Nunca recarga sola: el comercial puede estar a mitad de una entrevista.
const { hayNuevaVersion, iniciarComprobacion, recargarLimpio, posponer } = useNuevaVersion()
iniciarComprobacion(router)
const recargando = ref(false)
const recargarNuevaVersion = () => {
  recargando.value = true
  recargarLimpio()
}

const conChrome = computed(() => authStore.isAuthenticated && route.name !== 'acceso')
const panelAbierto = ref(false)

// 401 en mitad de la entrevista (sesión caducada): a la pantalla de acceso. Lo ya guardado
// sigue en el servidor; al volver a entrar se continúa desde «Empresas».
const onSesionCaducada = async () => {
  await olvidarSesion()
  await authStore.logout()
  if (route.name !== 'acceso') router.push({ name: 'acceso', query: { motivo: 'caducada' } })
}

// ── Menú de usuario (Mi usuario / Cerrar sesión), como en la TopBar de DuaLab ──
const menuAbierto = ref(false)
const menuRef     = ref(null)
const saliendo    = ref(false)
const cerrarMenuFuera = (e) => { if (menuRef.value && !menuRef.value.contains(e.target)) menuAbierto.value = false }
const cerrarMenuEsc   = (e) => { if (e.key === 'Escape') menuAbierto.value = false }

// ── Trabajo sin conexión (offline/) ──
// Con sesión: se recuerda para poder abrir la app sin red, se cargan las entrevistas guardadas
// en la tablet, se envían si hay conexión y se guardan familias y textos legales para la próxima
// entrevista sin red.
async function alEntrar() {
  await Promise.all([recargarPendientes(), recargarCentrosPendientes()])
  if (!navigator.onLine) return
  recordarSesion(authStore)
  sincronizar()
  sincronizarCentros()
  conCache('familias', getFamilias).catch(() => {})
  conCache('textos-legales', getTextosLegales).catch(() => {})
  conCache('textos-legales-centros', getTextosLegalesCentros).catch(() => {})
}
watch(() => authStore.isAuthenticated && authStore.userId, (dentro) => { if (dentro) alEntrar() }, { immediate: true })

// Vuelve la conexión: primero se confirma la sesión con el servidor y luego se envía lo pendiente
const alVolverConexion = async () => {
  if (!authStore.isAuthenticated) return
  await comprobarAlVolver(authStore)
  if (authStore.isAuthenticated) alEntrar()
}

onMounted(() => {
  window.addEventListener('online', alVolverConexion)
  window.addEventListener('auth:token-expired', onSesionCaducada)
  document.addEventListener('pointerdown', cerrarMenuFuera)
  document.addEventListener('keydown', cerrarMenuEsc)
})
onUnmounted(() => {
  window.removeEventListener('online', alVolverConexion)
  window.removeEventListener('auth:token-expired', onSesionCaducada)
  document.removeEventListener('pointerdown', cerrarMenuFuera)
  document.removeEventListener('keydown', cerrarMenuEsc)
})

const irAMiUsuario = () => {
  menuAbierto.value = false
  router.push({ name: 'mi-usuario' })
}
const salir = async () => {
  saliendo.value = true
  // Las entrevistas sin enviar se quedan cifradas en la tablet hasta que vuelva a entrar
  await olvidarSesion()
  await authStore.logout()
  saliendo.value = false
  menuAbierto.value = false
  router.push({ name: 'acceso' })
}
</script>

<template>
  <div class="min-h-dvh font-sans text-azul-noche">
    <!-- Barra superior: misma que la TopBar de DuaLab (azul noche, logo, sección y menú de usuario) -->
    <header v-if="conChrome" class="fixed inset-x-0 top-0 z-50 flex h-16 items-center gap-2 bg-azul-noche px-3 shadow-md sm:gap-3 sm:px-5">
      <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-white/10 hover:text-white lg:hidden"
              :aria-expanded="panelAbierto" aria-controls="panel-comercial" :aria-label="panelAbierto ? 'Cerrar menú' : 'Abrir menú'"
              @click="panelAbierto = !panelAbierto">
        <svg v-if="!panelAbierto" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>

      <RouterLink :to="{ name: 'mis-entrevistas' }" class="flex shrink-0 items-center gap-2 transition-opacity hover:opacity-80" aria-label="DuaLab · Empresas">
        <img :src="logo" alt="" class="h-11 w-auto object-contain" />
        <span class="select-none text-lg font-black uppercase tracking-tighter text-white">
          Dua<span :class="seccion === 'centros' ? 'text-centros-light' : 'text-empresas-light'">Lab</span>
        </span>
      </RouterLink>
      <span class="hidden select-none text-white/15 sm:inline" aria-hidden="true">/</span>
      <span class="hidden min-w-0 truncate text-xs font-bold uppercase tracking-[0.15em] text-white/60 sm:inline">Comercial</span>

      <div class="flex-1" />

      <div ref="menuRef" class="relative">
        <button type="button" :aria-expanded="menuAbierto" aria-haspopup="menu" aria-label="Menú de usuario"
                class="flex items-center gap-2.5 rounded-full py-1.5 pl-1.5 pr-2 transition-colors hover:bg-white/10 sm:pr-3"
                @click="menuAbierto = !menuAbierto">
          <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rol-fuerte text-white ring-2 ring-white/20" title="Sistema activo">
            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-400 ring-2 ring-azul-noche" aria-hidden="true" />
            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" /></svg>
          </span>
          <span class="hidden min-w-0 flex-col items-start text-left leading-tight sm:flex">
            <span class="max-w-[180px] truncate text-[13px] font-bold text-white">{{ authStore.userName }}</span>
            <span class="max-w-[180px] truncate text-xs text-white/60">{{ authStore.roleLabel }}</span>
          </span>
          <svg class="hidden h-4 w-4 shrink-0 text-white/50 transition-transform sm:block" :class="menuAbierto && 'rotate-180'"
               fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
        </button>

        <Transition name="menu-usuario">
          <div v-if="menuAbierto" role="menu" class="absolute right-0 top-full mt-2 w-64 overflow-hidden rounded-2xl bg-white py-1.5 text-sm text-azul-noche shadow-xl ring-1 ring-black/5">
            <div class="border-b border-gray-100 px-4 py-2.5 sm:hidden">
              <p class="truncate font-bold">{{ authStore.userName }}</p>
              <p class="truncate text-xs text-texto-secundario">{{ authStore.roleLabel }}</p>
            </div>
            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
              <span class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-emerald-400" aria-hidden="true" />
              <span class="text-xs font-bold uppercase tracking-widest text-emerald-700">Sistema activo</span>
            </div>
            <button type="button" role="menuitem" class="flex min-h-11 w-full items-center gap-3 px-4 py-2.5 text-left font-medium hover:bg-gray-50" @click="irAMiUsuario">
              <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" /></svg>
              Mi usuario
            </button>
            <button type="button" role="menuitem" :disabled="saliendo"
                    class="flex min-h-11 w-full items-center gap-3 px-4 py-2.5 text-left font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50" @click="salir">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
              Cerrar sesión
            </button>
          </div>
        </Transition>
      </div>
    </header>

    <PanelLateralComercial v-if="conChrome" v-model:abierto="panelAbierto" />

    <!-- pb-12: hueco para el pie fijo de AppCredit; lg:pl-72 = panel lateral. La barra superior la compensa cada vista con su pt (pt-[5.25rem]), como en la app principal -->
    <main :class="conChrome ? 'lg:pl-72' : ''" class="relative pb-12">
      <!-- key por ruta: abrir otra entrevista (o pasar de «nueva» a la ya guardada) vuelve a
           montar la vista y carga la empresa del servidor, en vez de reutilizar la anterior -->
      <RouterView :key="route.path" />
    </main>
    <AppCredit />

    <!-- Bocadillos de la esquina inferior derecha (como en la app principal), sobre el pie de AppCredit -->
    <div class="pointer-events-none fixed bottom-14 right-4 z-[9990] flex w-[300px] max-w-[calc(100vw-2rem)] flex-col gap-3 sm:right-5">
      <AvisoSinConexion />

      <Transition name="toast-slide">
        <div v-if="hayNuevaVersion" role="status"
             class="pointer-events-auto relative rounded-2xl border border-white/15 bg-[#1a2332]/95 p-4 shadow-2xl backdrop-blur-sm">
          <button type="button" title="Más tarde" aria-label="Recordármelo más tarde"
                  class="absolute right-1 top-1 flex h-9 w-9 items-center justify-center rounded-full text-white/40 transition-colors hover:bg-white/10 hover:text-white/70"
                  @click="posponer">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
          <div class="flex items-start gap-3 pr-6">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sky-500/30 bg-sky-500/15" aria-hidden="true">
              <svg class="h-4 w-4 text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            </span>
            <div class="min-w-0">
              <p class="text-sm font-black leading-tight text-white">Hay una versión nueva</p>
              <p class="mt-1 text-xs leading-relaxed text-white/60">Recarga cuando acabes la entrevista: lo ya guardado no se pierde, lo que estés escribiendo sí.</p>
            </div>
          </div>
          <button type="button" :disabled="recargando"
                  class="mt-3 min-h-9 w-full rounded-full bg-rol-fuerte py-2 text-xs font-black uppercase tracking-widest text-white shadow-md transition-colors hover:bg-rol disabled:cursor-not-allowed disabled:opacity-50"
                  @click="recargarNuevaVersion">
            {{ recargando ? 'Recargando…' : 'Recargar' }}
          </button>
        </div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.menu-usuario-enter-active, .menu-usuario-leave-active { transition: opacity .15s ease, transform .15s ease; }
.menu-usuario-enter-from, .menu-usuario-leave-to { opacity: 0; transform: translateY(-4px); }
.toast-slide-enter-active, .toast-slide-leave-active { transition: opacity .25s ease, transform .25s ease; }
.toast-slide-enter-from, .toast-slide-leave-to { opacity: 0; transform: translateY(8px); }
</style>
