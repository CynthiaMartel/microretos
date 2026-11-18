<!-- DuaLab Central: barra superior y panel lateral como la app comercial (misma TopBar y
     SidePanel de DuaLab), en el turquesa del superadmin. Sin sesión, solo la pantalla de acceso. -->
<script setup>
import BotonInstalarApp from '../src/components/BotonInstalarApp.vue'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute, RouterView } from 'vue-router'
import { useAuthStore } from '../src/stores/auth.js'
import AppCredit from '../src/components/AppCredit.vue'
import AvisoNuevaVersion from '../src/components/AvisoNuevaVersion.vue'
import PanelLateralCentral from './components/PanelLateralCentral.vue'
import { ESPACIOS, espacioDe } from './espacios.js'
import { forzarTemaRol } from '../src/composables/useRoleTheme.js'
// Icono de la herramienta, igual que su favicon (pwa/iconos-herramientas.mjs --barra)
import logo from './icono-barra.png'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()

const conChrome = computed(() => authStore.isAuthenticated && route.name !== 'acceso')
const panelAbierto = ref(false)
// El selector de espacios y Mi usuario van sin panel lateral; el resto, con el de su espacio
const conPanel = computed(() => conChrome.value && !route.meta.sinPanel)
const espacio = computed(() => espacioDe(route.meta.espacio))

// Cada espacio en el color de su botón del selector (botones, foco, panel, cabecera): pone
// html[data-rol] y style.css cambia las variables --color-rol*. El selector y lo común (Mi usuario),
// en el turquesa de Central. En /acceso, el del espacio al que se va a entrar.
watch(() => espacioDe(route.meta.espacio ?? route.query.espacio)?.color ?? 'administraciones',
  (color) => { document.documentElement.dataset.rol = color; forzarTemaRol(color) }, { immediate: true })

// 401 (sesión caducada): a la pantalla de acceso
const onSesionCaducada = async () => {
  await authStore.logout()
  if (route.name !== 'acceso') router.push({ name: 'acceso', query: { motivo: 'caducada' } })
}

// ── Menú de usuario (Mi usuario / Cerrar sesión), como en la TopBar de DuaLab ──
const menuAbierto = ref(false)
const menuRef     = ref(null)
const saliendo    = ref(false)
const cerrarMenuFuera = (e) => {
  if (menuRef.value && !menuRef.value.contains(e.target)) menuAbierto.value = false
  if (espaciosRef.value && !espaciosRef.value.contains(e.target)) espaciosAbierto.value = false
}
const cerrarMenuEsc   = (e) => { if (e.key === 'Escape') { menuAbierto.value = false; espaciosAbierto.value = false } }

// ── Desplegable «cambiar de espacio» (píldora del espacio en la barra superior) ──
const espaciosAbierto = ref(false)
const espaciosRef = ref(null)
// Clases literales (Tailwind no genera clases compuestas al vuelo)
const PUNTO_ESPACIO = { 'espacio-contacto': 'bg-espacio-contacto', 'espacio-red': 'bg-espacio-red', 'espacio-practicas': 'bg-espacio-practicas' }
function irAEspacio(e) {
  espaciosAbierto.value = false
  router.push(e ? e.ruta : { name: 'inicio' })
}

onMounted(() => {
  window.addEventListener('auth:token-expired', onSesionCaducada)
  document.addEventListener('pointerdown', cerrarMenuFuera)
  document.addEventListener('keydown', cerrarMenuEsc)
})
onUnmounted(() => {
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
  await authStore.logout()
  saliendo.value = false
  menuAbierto.value = false
  router.push({ name: 'acceso' })
}
</script>

<template>
  <div class="min-h-dvh font-sans text-azul-noche">
    <header v-if="conChrome" class="fixed inset-x-0 top-0 z-50 flex h-16 items-center gap-2 bg-azul-noche px-3 shadow-md sm:gap-3 sm:px-5">
      <button v-if="conPanel" type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-white/10 hover:text-white lg:hidden"
              :aria-expanded="panelAbierto" aria-controls="panel-central" :aria-label="panelAbierto ? 'Cerrar menú' : 'Abrir menú'"
              @click="panelAbierto = !panelAbierto">
        <svg v-if="!panelAbierto" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>

      <RouterLink :to="{ name: 'inicio' }" class="flex shrink-0 items-center gap-2 transition-opacity hover:opacity-80" aria-label="DuaLab Central · Inicio">
        <img :src="logo" alt="" class="h-10 w-10 shrink-0 rounded-[22%] ring-1 ring-white/25" />
        <span class="select-none text-lg font-black uppercase tracking-tighter text-white">Dua<span class="text-rol-claro">Lab</span></span>
      </RouterLink>
      <span class="hidden select-none text-white/15 sm:inline" aria-hidden="true">/</span>
      <span class="hidden min-w-0 truncate text-xs font-bold uppercase tracking-[0.15em] text-white/60 sm:inline">Central</span>
      <!-- Espacio actual: desplegable para cambiar a otro espacio sin pasar por el selector -->
      <template v-if="espacio">
        <span class="hidden select-none text-white/15 md:inline" aria-hidden="true">/</span>
        <div ref="espaciosRef" class="relative hidden min-w-0 md:block">
          <button type="button" :aria-expanded="espaciosAbierto" aria-haspopup="menu" @click="espaciosAbierto = !espaciosAbierto"
                  class="inline-flex min-w-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold text-white/90 ring-1 ring-white/20 transition hover:bg-white/10">
            <span class="h-2 w-2 shrink-0 rounded-full" :class="PUNTO_ESPACIO[espacio.color]" aria-hidden="true" />
            <span class="truncate">{{ espacio.nombre }}</span>
            <svg class="h-3.5 w-3.5 shrink-0 text-white/50 transition-transform" :class="espaciosAbierto && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            <span class="sr-only">· cambiar de espacio</span>
          </button>
          <Transition name="menu-usuario">
            <div v-if="espaciosAbierto" role="menu" aria-label="Cambiar de espacio"
                 class="absolute left-0 top-full mt-2 w-72 overflow-hidden rounded-2xl bg-white py-1.5 text-sm text-azul-noche shadow-xl ring-1 ring-black/5">
              <p class="px-4 pb-1 pt-2 text-[11px] font-bold uppercase tracking-[0.15em] text-texto-secundario">Cambiar de espacio</p>
              <button v-for="e in ESPACIOS" :key="e.clave" type="button" role="menuitem"
                      :disabled="e.clave === espacio.clave" :aria-current="e.clave === espacio.clave ? 'true' : undefined"
                      class="flex min-h-12 w-full items-center gap-3 px-4 py-2 text-left hover:bg-gray-50 disabled:cursor-default disabled:bg-gray-50"
                      @click="irAEspacio(e)">
                <span class="h-3 w-3 shrink-0 rounded-full" :class="PUNTO_ESPACIO[e.color]" aria-hidden="true" />
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-semibold">{{ e.nombre }}</span>
                  <span class="block truncate text-xs text-texto-secundario">{{ e.clave === espacio.clave ? 'Estás aquí' : e.quien }}</span>
                </span>
                <svg v-if="e.clave === espacio.clave" class="h-4 w-4 shrink-0 text-texto-secundario" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              </button>
              <button type="button" role="menuitem" class="mt-1 flex min-h-11 w-full items-center gap-3 border-t border-gray-100 px-4 py-2.5 text-left font-medium text-texto-secundario hover:bg-gray-50 hover:text-azul-noche"
                      @click="irAEspacio(null)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm9 0a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2h-3a2 2 0 01-2-2V6zm-9 9a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2H6a2 2 0 01-2-2v-3zm9 0a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2h-3a2 2 0 01-2-2v-3z" /></svg>
                Ver todos los espacios
              </button>
            </div>
          </Transition>
        </div>
      </template>

      <div class="flex-1" />

      <!-- Instalar como app (en la barra superior, sin scroll en el panel lateral) -->
      <BotonInstalarApp nombre="DuaLab Central" ruta-inicio="/" />

      <div ref="menuRef" class="relative">
        <button type="button" :aria-expanded="menuAbierto" aria-haspopup="menu" aria-label="Menú de usuario"
                class="flex items-center gap-2.5 rounded-full py-1.5 pl-1.5 pr-2 transition-colors hover:bg-white/10 sm:pr-3"
                @click="menuAbierto = !menuAbierto">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rol-fuerte text-white ring-2 ring-white/20">
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

    <PanelLateralCentral v-if="conPanel" v-model:abierto="panelAbierto" />

    <!-- lg:pl-72 = panel lateral; la barra superior la compensa cada vista con su pt, como en la app -->
    <main :class="conPanel ? 'lg:pl-72' : ''" class="relative pb-12">
      <RouterView :key="route.path" />
    </main>
    <AppCredit />
    <AvisoNuevaVersion :router="router" />
  </div>
</template>

<style scoped>
.menu-usuario-enter-active, .menu-usuario-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.menu-usuario-enter-from, .menu-usuario-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
