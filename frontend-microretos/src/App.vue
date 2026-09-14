<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { RouterView } from 'vue-router'
import TopBar from './components/TopBar.vue'
import SidePanel from './components/SidePanel.vue'
import AppCredit from './components/AppCredit.vue'
import ComoFuncionaModal from './components/ComoFuncionaModal.vue'
import LoginModal from './components/LoginModal.vue'
import { useAuthStore, ROLE_DOCENTE, ROLE_ADMIN, ROLE_EMPRESA } from './stores/auth'
import { useIdleTimer } from './composables/useIdleTimer'
import { useLoginModal } from './composables/useLoginModal.js'
import { useUIState } from './composables/useUIState.js'
import api from './api.js'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()

const isPublicRetoRoute = computed(() =>
  route.path.startsWith('/reto/') ||
  route.path.startsWith('/startup/landing/') ||
  route.path.startsWith('/proyecto/equipo/')
)

// El panel lateral es navegación de docente/admin/empresa: sin sesión no aporta nada
// y solo resta ancho útil a las vistas públicas (Home, Unirse, etc.)
const showSidePanel = computed(() => !isPublicRetoRoute.value && authStore.isAuthenticated)

// ── Login modal: vivía dentro de SidePanel, pero este ya no se monta sin sesión.
// Se sube a App.vue para que las vistas públicas (Home, etc.) puedan seguir abriéndolo.
const { showLogin, destinoTrasLogin } = useLoginModal()
const { triggerWelcome } = useUIState()

watch(
  () => route.query.redirect,
  (redirect) => {
    if (redirect && !authStore.isAuthenticated) {
      destinoTrasLogin.value = String(redirect)
      showLogin.value = true
    }
  },
  { immediate: true }
)

const onLoginSuccess = (data) => {
  const role    = data?.role ?? authStore.userRole
  const destino = destinoTrasLogin.value || rolHome(role)
  destinoTrasLogin.value = null
  router.push(destino).finally(() => triggerWelcome(role, authStore.userName))
}

function rolHome(role) {
  if (role === ROLE_DOCENTE || role === ROLE_ADMIN) return '/panel-docente'
  if (role === ROLE_EMPRESA) return '/proyectos'
  return '/retos/crear'
}

// ── Sesión caducada (401 del servidor) ────────────────────────────────────────
const handleTokenExpired = async () => {
  await authStore.logout()
  const currentPath = router.currentRoute.value.fullPath
  const isPublic = currentPath === '/' || currentPath.startsWith('/reto/') || currentPath.startsWith('/startup/landing/')
  if (!isPublic) {
    router.push({ path: '/', query: { redirect: currentPath } })
  }
}

onMounted(async () => {
  window.addEventListener('auth:token-expired', handleTokenExpired)
  // Hidrata la sesión desde la cookie también en rutas públicas — el guard del router
  // solo llama a auth.init() para rutas con requiresAuth, si no la TopBar no reflejaría
  // una sesión válida hasta la primera navegación a una ruta protegida.
  if (!authStore.isInitialized) await authStore.init()
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

const handleIdle = async () => {
  if (!authStore.isAuthenticated) return
  showIdleWarning.value = false
  await authStore.logout()
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
  // Ping de cortesía: cualquier request autenticada desliza la sesión de Laravel
  // (last_activity) — no hace falta rotar ni renovar nada a mano.
  if (authStore.isAuthenticated) authStore.ping()
}

const cerrarSesionIdle = async () => {
  showIdleWarning.value = false
  await authStore.logout()
  router.push('/')
}

// ── Toast de expiración de sesión (bocadillo esquina inferior derecha) ────────
// Distinto del modal de inactividad: este avisa cuando la sesión de Laravel está a
// punto de caducar en el SERVIDOR (sliding, basada en last_activity), algo que puede
// ocurrir incluso con el usuario "activo" en pantalla si no se ha disparado ningún
// request autenticado en un rato largo.
const TOAST_THRESHOLD_MINUTES = 20
const tokenToastDismissedAt = ref(null) // minutos restantes en el momento del cierre manual
const tokenToastForceHidden = ref(false) // oculta el toast tras "Extender" sin esperar a que recalculen los minutos
const tokenToastRefreshing  = ref(false)
const tokenToastFeedback    = ref(null) // 'ok' | 'error' | null

const showTokenToast = computed(() => {
  if (!authStore.isAuthenticated || isPublicRetoRoute.value || tokenToastForceHidden.value) return false
  const m = authStore.minutosRestantes
  if (m < 0 || m > TOAST_THRESHOLD_MINUTES) return false
  if (tokenToastDismissedAt.value !== null && m >= tokenToastDismissedAt.value - 5) return false
  return true
})

const tokenToastLabel = computed(() => {
  const m = authStore.minutosRestantes
  if (m <= 0) return 'Tu sesión expira ahora mismo'
  if (m === 1) return 'Tu sesión expira en 1 minuto'
  return `Tu sesión expira en ${m} minutos`
})

watch(() => authStore.minutosRestantes, (nuevo, anterior) => {
  // La sesión se ha renovado: olvidar el cierre manual previo y permitir que el toast vuelva a aparecer
  if (anterior !== undefined && nuevo > anterior + 10) {
    tokenToastDismissedAt.value = null
    tokenToastForceHidden.value = false
  }
})

const cerrarTokenToast = () => {
  tokenToastDismissedAt.value = authStore.minutosRestantes
}

const extenderDesdeToast = async () => {
  tokenToastRefreshing.value = true
  const ok = await authStore.ping()
  tokenToastFeedback.value = ok ? 'ok' : 'error'
  tokenToastRefreshing.value = false
  // Deja el mensaje de confirmación/error visible 2s antes de desvanecer el aviso
  setTimeout(() => {
    tokenToastFeedback.value = null
    if (ok) tokenToastForceHidden.value = true
  }, 2000)
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
  <SidePanel v-if="showSidePanel" />
  <div :class="showSidePanel ? 'lg:pl-72' : ''" class="relative" style="z-index: 1;">
    <RouterView />
  </div>
  <AppCredit />
  <ComoFuncionaModal v-if="authStore.isAuthenticated" />
  <LoginModal v-model="showLogin" @login-success="onLoginSuccess" />

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

  <!-- Bocadillo: sesión a punto de expirar en el servidor -->
  <Transition name="toast-slide">
    <div
      v-if="showTokenToast"
      class="fixed bottom-5 right-5 z-[9990] w-[300px] max-w-[calc(100vw-2.5rem)]
             rounded-2xl shadow-2xl border p-4 backdrop-blur-sm"
      :class="authStore.minutosRestantes <= 5
        ? 'bg-red-950/95 border-red-500/40'
        : 'bg-[#1a2332]/95 border-amber-500/30'"
      role="alert"
    >
      <button
        @click="cerrarTokenToast"
        title="Cerrar aviso"
        class="absolute top-2.5 right-2.5 w-5 h-5 rounded-full flex items-center justify-center
               text-white/30 hover:text-white/70 hover:bg-white/10 transition-all"
      >
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      <div class="flex items-start gap-3 pr-4">
        <div
          class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 border"
          :class="authStore.minutosRestantes <= 5
            ? 'bg-red-500/15 border-red-500/30'
            : 'bg-amber-500/15 border-amber-500/30'"
        >
          <svg class="w-4.5 h-4.5" :class="authStore.minutosRestantes <= 5 ? 'text-red-400' : 'text-amber-400'"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-black text-white leading-tight">{{ tokenToastLabel }}</p>
          <p class="text-xs text-white/50 mt-1 leading-relaxed">
            Extiende tu sesión ahora para no perder cambios sin guardar.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 mt-3">
        <button
          @click="extenderDesdeToast"
          :disabled="tokenToastRefreshing"
          class="flex-1 py-2 rounded-xl text-xs font-black uppercase tracking-widest
                 bg-[#00A859] hover:bg-[#009950] text-white transition-all shadow-md
                 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ tokenToastRefreshing ? 'Extendiendo…' : 'Extender sesión' }}
        </button>
        <Transition name="fade">
          <span
            v-if="tokenToastFeedback"
            class="text-[10px] font-bold leading-none shrink-0"
            :class="tokenToastFeedback === 'ok' ? 'text-green-400' : 'text-red-400'"
          >
            {{ tokenToastFeedback === 'ok' ? '✓ Extendida' : 'Error' }}
          </span>
        </Transition>
      </div>
    </div>
  </Transition>
</template>

<style>
.idle-fade-enter-active, .idle-fade-leave-active { transition: opacity 0.25s ease; }
.idle-fade-enter-from,  .idle-fade-leave-to      { opacity: 0; }

.toast-slide-enter-active { transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
.toast-slide-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateY(16px) scale(0.95); }

.fade-enter-active, .fade-leave-active { transition: opacity 0.4s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>
