<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useSidePanel } from '../composables/useSidePanel.js'
import { useRoleTheme } from '../composables/useRoleTheme.js'

const authStore = useAuthStore()
const route     = useRoute()
const router    = useRouter()
const { mobileOpen, toggleMobilePanel } = useSidePanel()
const { theme } = useRoleTheme()

const irHome = () => {
  const destino = authStore.isAuthenticated && (authStore.isDocente || authStore.isAdmin)
    ? '/panel-docente'
    : '/'
  if (route.path !== destino) {
    router.push(destino)
  }
}

const cargandoOut = ref(false)

const sectionLabels = {
  'microretos':           'Generador',
  'biblioteca':           'Biblioteca',
  'detalle-microreto':    'Reto',
  'base-datos':           'Base de datos',
  'papelera':             'Papelera',
  'empresas':             'Empresas',
  'dashboard-docente':    'Crear encuentro',
  'encuentros-registrados': 'Encuentros',
  'startup-day':          'Propuestas-Proyecto',
  'proyectos-terminados': 'Proyectos Completados',
  'startup-day-crear':    'Nueva propuesta',
  'startup-day-detalle':  'Proyecto',
  'startup-day-editar':   'Editar proyecto',
  'gestion-usuarios':     'Usuarios',
}

const sectionLabel = computed(() => sectionLabels[route.name] ?? null)

// ── Menú de usuario (Mi usuario / Cerrar sesión) ──────────────────────────────
const menuAbierto = ref(false)
const menuRef     = ref(null)

const cerrarMenu = () => { menuAbierto.value = false }
const alPulsarFuera = (e) => { if (menuRef.value && !menuRef.value.contains(e.target)) cerrarMenu() }
const alPulsarTecla = (e) => { if (e.key === 'Escape') cerrarMenu() }

onMounted(() => {
  document.addEventListener('click', alPulsarFuera)
  document.addEventListener('keydown', alPulsarTecla)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', alPulsarFuera)
  document.removeEventListener('keydown', alPulsarTecla)
})
watch(() => route.fullPath, cerrarMenu)

const irAMiUsuario = () => {
  cerrarMenu()
  router.push('/mi-usuario')
}

const cerrarSesion = async () => {
  cargandoOut.value = true
  await authStore.logout()
  cargandoOut.value = false
  cerrarMenu()
  router.push('/')
}
</script>

<template>
  <header
    class="fixed top-0 left-0 right-0 h-16 z-50 flex items-center gap-2 px-3
           bg-azul-noche border-b border-azul-noche-border select-none"
  >
    <!-- Menú (cajón lateral) — solo visible en móvil/tablet con sesión iniciada -->
    <button
      v-if="authStore.isAuthenticated"
      @click="toggleMobilePanel"
      title="Menú"
      class="lg:hidden w-10 h-10 rounded-lg flex items-center justify-center shrink-0
             text-white/60 hover:text-white hover:bg-white/10
             transition-all duration-150"
    >
      <svg v-if="!mobileOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
      <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>

    <!-- Logo DuaLab — el color de "Lab" refleja el rol de quien ha iniciado sesión -->
    <button
      @click="irHome"
      class="flex items-center gap-2 mr-1 shrink-0 hover:opacity-80 transition-opacity duration-150 cursor-pointer"
    >
      <img src="../assets/logo_colores.png" alt="DuaLab" class="h-11 w-auto object-contain" />
      <span class="font-black text-lg tracking-tighter text-white uppercase select-none">
        Dua<span :class="theme.textDark">Lab</span>
      </span>
    </button>

    <!-- Separador + sección activa -->
    <template v-if="sectionLabel">
      <span class="text-white/15 select-none">/</span>
      <span class="text-[11px] font-black uppercase tracking-[0.15em] text-white/50 truncate">
        {{ sectionLabel }}
      </span>
    </template>

    <div class="flex-1" />

    <!-- ── Sesión activa ── -->
    <template v-if="authStore.isAuthenticated">

      <!-- Usuario (como en la maqueta idea_dashboard): avatar + nombre + "Rol · Centro" y
           flecha que despliega Mi usuario / Cerrar sesión. En vez de foto, el icono de usuario. -->
      <div ref="menuRef" class="relative shrink-0">
        <button
          type="button"
          @click="menuAbierto = !menuAbierto"
          :aria-expanded="menuAbierto"
          aria-haspopup="menu"
          title="Mi cuenta"
          class="flex items-center gap-2.5 rounded-xl py-1.5 pl-1.5 pr-2 sm:pr-3
                 hover:bg-white/10 transition-colors duration-150"
        >
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white ring-2 ring-white/20"
                :class="theme.bg">
            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="8" r="4"/>
              <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>
          </span>
          <span class="hidden min-w-0 flex-col items-start text-left leading-tight sm:flex">
            <span class="max-w-[180px] truncate text-[13px] font-bold text-white">{{ authStore.userName }}</span>
            <span class="max-w-[180px] truncate text-[11px] text-white/50">
              {{ authStore.roleLabel }}<template v-if="authStore.userCentroNombre"> · {{ authStore.userCentroNombre }}</template>
            </span>
          </span>
          <svg class="hidden h-4 w-4 shrink-0 text-white/50 transition-transform sm:block"
               :class="menuAbierto && 'rotate-180'"
               fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>

        <Transition name="menu-usuario">
          <div v-if="menuAbierto" role="menu"
               class="absolute right-0 top-full mt-2 w-64 overflow-hidden rounded-2xl bg-white py-1.5
                      text-sm text-azul-noche shadow-xl ring-1 ring-black/5">
            <!-- En móvil el nombre no cabe en la barra: se muestra aquí -->
            <div class="border-b border-gray-100 px-4 py-2.5 sm:hidden">
              <p class="truncate font-bold">{{ authStore.userName }}</p>
              <p class="truncate text-xs text-gray-500">
                {{ authStore.roleLabel }}<template v-if="authStore.userCentroNombre"> · {{ authStore.userCentroNombre }}</template>
              </p>
            </div>
            <button type="button" role="menuitem" @click="irAMiUsuario"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left font-medium hover:bg-gray-50">
              <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
              </svg>
              Mi usuario
            </button>
            <button type="button" role="menuitem" @click="cerrarSesion" :disabled="cargandoOut"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left font-medium text-red-600
                           hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50">
              <svg v-if="!cargandoOut" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
              </svg>
              <svg v-else class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
              Cerrar sesión
            </button>
          </div>
        </Transition>
      </div>
    </template>
  </header>
</template>

<style scoped>
.menu-usuario-enter-active, .menu-usuario-leave-active { transition: opacity .15s ease, transform .15s ease; }
.menu-usuario-enter-from, .menu-usuario-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
