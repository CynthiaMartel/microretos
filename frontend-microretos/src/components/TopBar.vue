<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useSidePanel } from '../composables/useSidePanel.js'

const authStore = useAuthStore()
const route     = useRoute()
const router    = useRouter()
const { mobileOpen, toggleMobilePanel } = useSidePanel()

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
  'startup-day-crear':    'Nueva propuesta',
  'startup-day-detalle':  'Proyecto',
  'startup-day-editar':   'Editar proyecto',
  'gestion-usuarios':     'Usuarios',
}

const sectionLabel = computed(() => sectionLabels[route.name] ?? null)

const cerrarSesion = async () => {
  cargandoOut.value = true
  await authStore.logout()
  cargandoOut.value = false
  router.push('/')
}
</script>

<template>
  <header
    class="fixed top-0 left-0 right-0 h-12 z-50 flex items-center gap-2 px-3
           bg-[#1F2937] border-b border-[#333333] select-none"
  >
    <!-- Menú (cajón lateral) — solo visible en móvil/tablet con sesión iniciada -->
    <button
      v-if="authStore.isAuthenticated"
      @click="toggleMobilePanel"
      title="Menú"
      class="lg:hidden w-8 h-8 rounded-lg flex items-center justify-center shrink-0
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

    <!-- Logo DuaLab -->
    <button
      @click="irHome"
      class="flex items-center gap-2 shrink-0 hover:opacity-80 transition-opacity duration-150 cursor-pointer"
    >
      <img src="../assets/logo.png" alt="DuaLab" class="h-6 w-auto object-contain" />
      <span class="font-black text-sm tracking-tighter text-white uppercase select-none">
        Dua<span class="text-[#00A859]">Lab</span>
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

      <!-- Nombre y rol -->
      <div class="hidden sm:flex flex-col items-end leading-none gap-0.5">
        <span class="text-[9px] font-black uppercase tracking-widest text-white/30">
          {{ authStore.roleLabel }}
        </span>
        <span class="text-[11px] font-bold text-white/70 truncate max-w-[130px]">
          {{ authStore.userName }}
        </span>
      </div>

      <!-- Mi usuario -->
      <button
        @click="router.push('/mi-usuario')"
        title="Mi usuario"
        class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0
               text-white/40 hover:text-white/80 hover:bg-white/10
               transition-all duration-150"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="8" r="4"/>
          <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg>
      </button>

      <!-- Logout -->
      <button
        @click="cerrarSesion"
        :disabled="cargandoOut"
        title="Cerrar sesión"
        class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0
               text-red-400/60 hover:text-red-300 hover:bg-red-500/10
               transition-all duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <svg v-if="!cargandoOut" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        <svg v-else class="w-4 h-4 animate-spin" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
      </button>
    </template>
  </header>
</template>
