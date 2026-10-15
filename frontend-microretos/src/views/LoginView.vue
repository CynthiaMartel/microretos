<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore, ROLE_EMPRESA } from '../stores/auth'
import { useLoginForm } from '../composables/useLoginForm.js'
import { useUIState } from '../composables/useUIState.js'
import { solicitarEnlaceContrasena } from '../services/authService.js'
import { SOLICITAR_REGISTRO } from '../config/frontoffice.js'
import AccesoLayout from '../components/AccesoLayout.vue'
import CampoContrasena from '../components/CampoContrasena.vue'
import SoporteModal from '../components/SoporteModal.vue'

const route     = useRoute()
const router    = useRouter()
const authStore = useAuthStore()
const { triggerWelcome } = useUIState()

// Solo rutas internas: un ?redirect=//otro-dominio.com sería un open redirect
function destinoTrasLogin(role) {
  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
  if (redirect.startsWith('/') && !redirect.startsWith('//')) return redirect
  return role === ROLE_EMPRESA ? '/proyectos' : '/panel-docente'
}

const { form, errors, loginError, isLoading, handleLogin } = useLoginForm((data) => {
  const role = data?.role ?? authStore.userRole
  router.push(destinoTrasLogin(role)).finally(() => triggerWelcome(role, authStore.userName))
})

const soporteAbierto = ref(false)

// ── "¿Has olvidado tu contraseña?" ───────────────────────────────────────────
const modo = ref('login') // 'login' | 'recuperar'
const recuperarEmail    = ref('')
const recuperarError    = ref('')
const recuperarAviso    = ref('')
const recuperarEnviando = ref(false)

function abrirRecuperar() {
  recuperarEmail.value = form.email
  recuperarError.value = ''
  recuperarAviso.value = ''
  modo.value = 'recuperar'
}

async function enviarRecuperar() {
  recuperarError.value = ''
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recuperarEmail.value)) {
    recuperarError.value = 'Introduce un correo electrónico válido'
    return
  }
  recuperarEnviando.value = true
  try {
    const { data } = await solicitarEnlaceContrasena(recuperarEmail.value)
    recuperarAviso.value = data.message
  } catch (error) {
    recuperarError.value = error.response?.status === 429
      ? 'Has hecho demasiadas solicitudes. Espera un minuto e inténtalo de nuevo.'
      : 'No se ha podido enviar la solicitud. Inténtalo más tarde.'
  } finally {
    recuperarEnviando.value = false
  }
}
</script>

<template>
  <AccesoLayout>
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-acceso-azul mb-2 sm:mb-3 [@media(min-width:1024px)_and_(max-height:820px)]:mb-2">Acceso a la plataforma</p>

    <!-- ── Iniciar sesión ─────────────────────────────────────────────── -->
    <template v-if="modo === 'login'">
      <h1 class="text-[1.75rem] sm:text-4xl xl:text-[2.6rem] 2xl:text-5xl [@media(min-width:1024px)_and_(max-height:820px)]:text-[2.1rem] font-black tracking-tight text-acceso-tinta leading-tight break-words">
        Bienvenido a DUALAB
      </h1>
      <p class="mt-2 text-base sm:text-lg lg:text-base xl:text-lg text-acceso-texto">Accede a tu espacio y conecta con tu Formación Dual.</p>

      <form class="mt-6 sm:mt-8 [@media(min-width:1024px)_and_(max-height:820px)]:mt-5 flex flex-col gap-4 sm:gap-5 [@media(min-width:1024px)_and_(max-height:820px)]:gap-3" novalidate @submit.prevent="handleLogin">
        <div>
          <label for="login-email" class="block text-sm font-semibold text-acceso-tinta mb-2">Correo electrónico</label>
          <div class="relative">
            <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <input
              id="login-email"
              v-model="form.email"
              type="email"
              placeholder="tuemail@ejemplo.com"
              autocomplete="email"
              autocapitalize="off"
              autocorrect="off"
              spellcheck="false"
              :aria-invalid="!!errors.email"
              class="w-full h-12 sm:h-13 [@media(min-width:1024px)_and_(max-height:820px)]:h-12 pl-12 pr-4 rounded-xl border bg-white text-base outline-none transition placeholder:text-gray-400 focus:border-acceso-azul focus:ring-4 focus:ring-acceso-azul/15"
              :class="errors.email ? 'border-red-300 bg-red-50' : 'border-gray-300'"
              @focus="errors.email = ''"
            />
          </div>
          <p v-if="errors.email" class="mt-1.5 text-xs font-semibold text-red-600">{{ errors.email }}</p>
        </div>

        <CampoContrasena id="login-password" v-model="form.password" label="Contraseña"
                         :error="errors.password" @focus="errors.password = ''" />

        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
          <!-- 7 días; el backend lo ignora para superadmin (User::puedeRecordarSesion) -->
          <label class="inline-flex items-start gap-3 min-h-9 cursor-pointer text-[15px] lg:text-sm text-gray-700">
            <input v-model="form.remember" type="checkbox" aria-describedby="recordarme-ayuda"
                   class="mt-0.5 w-5 h-5 shrink-0 rounded border-gray-400 text-acceso-azul focus:ring-acceso-azul/30" />
            <span>
              Recordarme
              <span id="recordarme-ayuda" class="block text-xs text-gray-500">No lo marques en ordenadores compartidos</span>
            </span>
          </label>
          <button type="button" class="min-h-9 text-[15px] lg:text-sm font-medium text-acceso-azul hover:text-acceso-azul-hover hover:underline"
                  @click="abrirRecuperar">
            ¿Has olvidado tu contraseña?
          </button>
        </div>

        <div v-if="loginError" role="alert" class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span class="min-w-0 break-words">{{ loginError }}</span>
        </div>

        <button
          type="submit"
          :disabled="isLoading"
          class="mt-1 w-full h-12 sm:h-13 [@media(min-width:1024px)_and_(max-height:820px)]:h-12 inline-flex items-center justify-center gap-3 rounded-xl bg-acceso-azul text-white text-base font-bold shadow-[0_8px_24px_rgba(17,113,219,0.28)] transition hover:bg-acceso-azul-hover disabled:opacity-60 disabled:cursor-not-allowed"
        >
          <template v-if="!isLoading">
            Iniciar sesión
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m0 0l-6-6m6 6l-6 6"/>
            </svg>
          </template>
          <span v-else class="flex gap-1.5" aria-label="Iniciando sesión">
            <span class="w-1.5 h-1.5 rounded-full bg-white animate-bounce"></span>
            <span class="w-1.5 h-1.5 rounded-full bg-white animate-bounce [animation-delay:150ms]"></span>
            <span class="w-1.5 h-1.5 rounded-full bg-white animate-bounce [animation-delay:300ms]"></span>
          </span>
        </button>
      </form>

      <div class="mt-6 sm:mt-8 [@media(min-width:1024px)_and_(max-height:820px)]:mt-5 flex items-center gap-4 text-sm text-gray-500">
        <span class="h-px flex-1 bg-gray-200"></span>
        <span class="shrink-0">¿Todavía no tienes cuenta?</span>
        <span class="h-px flex-1 bg-gray-200"></span>
      </div>

      <!-- No hay autorregistro en la app: el alta se pide con el formulario del frontoffice -->
      <a
        :href="SOLICITAR_REGISTRO"
        class="mt-4 sm:mt-5 [@media(min-width:1024px)_and_(max-height:820px)]:mt-3.5 w-full h-12 sm:h-13 [@media(min-width:1024px)_and_(max-height:820px)]:h-12 inline-flex items-center justify-center rounded-xl border-2 border-acceso-azul text-acceso-azul text-base font-bold transition hover:bg-primary-50"
      >
        Solicitar registro
      </a>
    </template>

    <!-- ── Recuperar contraseña ───────────────────────────────────────── -->
    <template v-else>
      <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-acceso-tinta leading-tight break-words">
        ¿Has olvidado tu contraseña?
      </h1>
      <p class="mt-2 text-base sm:text-lg text-acceso-texto">
        Escribe el correo con el que accedes y te enviaremos un enlace para crear una nueva.
      </p>

      <div v-if="recuperarAviso" role="status" class="mt-8 flex items-start gap-3 rounded-xl border border-empresas/30 bg-empresas/10 px-4 py-4 text-sm text-acceso-tinta">
        <svg class="w-5 h-5 shrink-0 text-empresas-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="min-w-0 break-words">{{ recuperarAviso }} Revisa también la carpeta de spam. El enlace caduca en 60 minutos.</span>
      </div>

      <form v-else class="mt-6 sm:mt-8 flex flex-col gap-4 sm:gap-5" novalidate @submit.prevent="enviarRecuperar">
        <div>
          <label for="recuperar-email" class="block text-sm font-semibold text-acceso-tinta mb-2">Correo electrónico</label>
          <div class="relative">
            <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <input
              id="recuperar-email"
              v-model="recuperarEmail"
              type="email"
              placeholder="tuemail@ejemplo.com"
              autocomplete="email"
              autocapitalize="off"
              autocorrect="off"
              spellcheck="false"
              maxlength="255"
              :aria-invalid="!!recuperarError"
              class="w-full h-12 sm:h-13 [@media(min-width:1024px)_and_(max-height:820px)]:h-12 pl-12 pr-4 rounded-xl border bg-white text-base outline-none transition placeholder:text-gray-400 focus:border-acceso-azul focus:ring-4 focus:ring-acceso-azul/15"
              :class="recuperarError ? 'border-red-300 bg-red-50' : 'border-gray-300'"
              @focus="recuperarError = ''"
            />
          </div>
          <p v-if="recuperarError" class="mt-1.5 text-xs font-semibold text-red-600" role="alert">{{ recuperarError }}</p>
        </div>

        <button
          type="submit"
          :disabled="recuperarEnviando"
          class="w-full h-12 sm:h-13 [@media(min-width:1024px)_and_(max-height:820px)]:h-12 inline-flex items-center justify-center gap-3 rounded-xl bg-acceso-azul text-white text-base font-bold shadow-[0_8px_24px_rgba(17,113,219,0.28)] transition hover:bg-acceso-azul-hover disabled:opacity-60 disabled:cursor-not-allowed"
        >
          {{ recuperarEnviando ? 'Enviando…' : 'Enviar enlace' }}
        </button>
      </form>

      <button type="button" class="mt-6 inline-flex items-center gap-2 min-h-9 text-[15px] font-medium text-acceso-azul hover:text-acceso-azul-hover"
              @click="modo = 'login'">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m0 0l6 6m-6-6l6-6"/>
        </svg>
        Volver a iniciar sesión
      </button>
    </template>

    <p class="mt-4 sm:mt-6 [@media(min-width:1024px)_and_(max-height:820px)]:mt-3 text-center text-sm text-acceso-texto">
      ¿Necesitas ayuda?
      <button type="button" class="inline-flex items-center min-h-9 font-medium text-acceso-azul underline underline-offset-2 hover:text-acceso-azul-hover"
              @click="soporteAbierto = true">
        Contacta con soporte
      </button>
    </p>
    <SoporteModal v-model="soporteAbierto" :email-inicial="modo === 'recuperar' ? recuperarEmail : form.email" />
  </AccesoLayout>
</template>
