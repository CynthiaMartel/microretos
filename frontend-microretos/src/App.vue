<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { RouterView } from 'vue-router'
import TopBar from './components/TopBar.vue'
import SidePanel from './components/SidePanel.vue'
import AppCredit from './components/AppCredit.vue'
import { useAuthStore } from './stores/auth'
import { useIdleTimer } from './composables/useIdleTimer'
import api from './api.js'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()

const isPublicRetoRoute = computed(() =>
  route.path.startsWith('/reto/') ||
  route.path.startsWith('/startup/landing/') ||
  route.path.startsWith('/proyecto/equipo/')
)

// ── Expiración de token (401 del servidor) ────────────────────────────────────
const handleTokenExpired = () => {
  authStore.logout()
  const currentPath = router.currentRoute.value.fullPath
  const isPublic = currentPath === '/' || currentPath.startsWith('/reto/') || currentPath.startsWith('/startup/landing/')
  if (!isPublic) {
    router.push({ path: '/', query: { redirect: currentPath } })
  }
}

onMounted(() => {
  window.addEventListener('auth:token-expired', handleTokenExpired)
  if (!authStore.userCentroImg && authStore.userCentroId) cargarCentroImg()
})
onUnmounted(() => window.removeEventListener('auth:token-expired', handleTokenExpired))

async function cargarCentroImg() {
  try {
    const { data } = await api.get('/centros')
    const centro = data.find(c => c.id === authStore.userCentroId)
    if (centro?.img) authStore.updateCentroImg(centro.img)
  } catch { /* silencioso */ }
}

// ── Idle timeout ──────────────────────────────────────────────────────────────
const showIdleWarning = ref(false)

const handleIdle = () => {
  if (!authStore.isAuthenticated) return
  showIdleWarning.value = false
  authStore.logout()
  const currentPath = router.currentRoute.value.fullPath
  const isPublic = currentPath === '/' || currentPath.startsWith('/reto/') || currentPath.startsWith('/startup/landing/')
  if (!isPublic) {
    router.push({ path: '/', query: { redirect: currentPath } })
  }
}

const handleWarning = () => {
  if (!authStore.isAuthenticated) return
  showIdleWarning.value = true
}

const handleActive = () => {
  showIdleWarning.value = false
}

const { secondsUntilLogout, reset: resetIdle } = useIdleTimer({
  timeoutMinutes: 60,
  warningMinutes: 2,
  onIdle:    handleIdle,
  onWarning: handleWarning,
  onActive:  handleActive,
})

const minutosModal  = computed(() => Math.floor(secondsUntilLogout.value / 60))
const segundosModal = computed(() => secondsUntilLogout.value % 60)

const seguirConectado = () => {
  showIdleWarning.value = false
  resetIdle()
  // Si el token tiene menos de 2 horas, aprovechamos que el usuario ha confirmado
  // que quiere seguir para renovarlo silenciosamente (sin introducir credenciales)
  if (authStore.isAuthenticated && authStore.minutosRestantes >= 0 && authStore.minutosRestantes <= 120) {
    authStore.refresh()
  }
}

const cerrarSesionIdle = () => {
  showIdleWarning.value = false
  authStore.logout()
  router.push('/')
}
</script>

<template>
  <!-- Fondo del centro educativo (visible en todas las vistas autenticadas) -->
  <template v-if="authStore.userCentroImg && !isPublicRetoRoute">
    <div aria-hidden="true" class="fixed inset-0 bg-cover"
         :style="`background-image: url('${authStore.userCentroImg}'); background-position: center 30%; z-index: 0;`"></div>
    <div aria-hidden="true" class="fixed inset-0 pointer-events-none"
         style="background: linear-gradient(to bottom, rgba(248,250,252,0) 0%, rgba(248,250,252,0.4) 20%, rgba(248,250,252,0.8) 45%, rgba(248,250,252,0.97) 65%, rgba(248,250,252,1) 80%); z-index: 0;"></div>
  </template>

  <TopBar v-if="!isPublicRetoRoute" />
  <SidePanel v-if="!isPublicRetoRoute" />
  <div :class="!isPublicRetoRoute ? 'lg:pl-72' : ''" class="relative" style="z-index: 1;">
    <RouterView />
  </div>
  <AppCredit />

  <!-- Modal idle: ¿Sigues ahí? -->
  <Transition name="idle-fade">
    <div v-if="showIdleWarning"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-[#1a2332] border border-white/10 rounded-[2rem] shadow-2xl
                  max-w-sm w-full p-8 text-white text-center">

        <!-- Icono -->
        <div class="w-16 h-16 rounded-full bg-amber-500/15 border border-amber-500/30
                    flex items-center justify-center mx-auto mb-5">
          <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>

        <h2 class="text-2xl font-black tracking-tight mb-2">¿Sigues ahí?</h2>
        <p class="text-white/50 text-sm mb-5 leading-relaxed">
          Por inactividad, la sesión se cerrará en
        </p>

        <!-- Contador -->
        <div class="text-5xl font-black text-amber-400 tabular-nums mb-6">
          {{ String(minutosModal).padStart(2, '0') }}:{{ String(segundosModal).padStart(2, '0') }}
        </div>

        <div class="flex gap-3">
          <button @click="cerrarSesionIdle"
            class="flex-1 py-3 rounded-xl font-bold text-xs uppercase tracking-widest
                   border border-white/10 text-white/40
                   hover:bg-white/5 hover:text-white/60 transition-all">
            Cerrar sesión
          </button>
          <button @click="seguirConectado"
            class="flex-1 py-3 rounded-xl font-black text-xs uppercase tracking-widest
                   bg-[#00A859] hover:bg-[#009950] text-white transition-all shadow-md">
            Seguir conectado
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style>
.idle-fade-enter-active, .idle-fade-leave-active { transition: opacity 0.25s ease; }
.idle-fade-enter-from,  .idle-fade-leave-to      { opacity: 0; }
</style>
