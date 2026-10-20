<script setup>
import { ref, reactive, computed } from 'vue'
import { useRoute } from 'vue-router'
import { restablecerContrasena } from '../services/authService.js'
import AccesoLayout from '../components/AccesoLayout.vue'
import CampoContrasena from '../components/CampoContrasena.vue'

// Destino del enlace del email: /restablecer-contrasena?token=…&email=…
const route = useRoute()
const token = typeof route.query.token === 'string' ? route.query.token : ''
const email = typeof route.query.email === 'string' ? route.query.email : ''
const enlaceIncompleto = computed(() => !token || !email)

const form   = reactive({ password: '', password_confirmation: '' })
const errors = reactive({ password: '', password_confirmation: '' })
const errorGeneral = ref('')
const enviando     = ref(false)
const hecho        = ref(false)

// Misma política que /mi-usuario y el backend (UpdatePerfilRequest / ResetPasswordRequest)
const POLITICA = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/

async function enviar() {
  errors.password = ''
  errors.password_confirmation = ''
  errorGeneral.value = ''

  if (!POLITICA.test(form.password)) {
    errors.password = 'Mín. 8 caracteres con mayúscula, minúscula, número y símbolo.'
    return
  }
  if (form.password !== form.password_confirmation) {
    errors.password_confirmation = 'Las contraseñas no coinciden.'
    return
  }

  enviando.value = true
  try {
    await restablecerContrasena({ token, email, ...form })
    hecho.value = true
  } catch (error) {
    const status = error.response?.status
    const errs = error.response?.data?.errors
    if (status === 422 && errs?.password) {
      errors.password = errs.password[0]
    } else if (status === 422) {
      errorGeneral.value = error.response?.data?.message || 'El enlace no es válido o ha caducado. Solicita uno nuevo.'
    } else if (status === 429) {
      errorGeneral.value = 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'
    } else {
      errorGeneral.value = 'Error de conexión. Inténtalo más tarde.'
    }
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <AccesoLayout>
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary-500 mb-3">Acceso a la plataforma</p>
    <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-azul-noche leading-tight break-words">
      Crea una nueva contraseña
    </h1>

    <!-- Enlace sin token/email (copiado a medias) -->
    <template v-if="enlaceIncompleto">
      <p class="mt-4 text-base text-gray-600">
        Este enlace no es válido. Pide uno nuevo desde «¿Has olvidado tu contraseña?» en la página de acceso.
      </p>
      <RouterLink to="/login" class="mt-8 w-full h-13 inline-flex items-center justify-center rounded-full bg-primary-600 text-white text-base font-bold hover:bg-primary-700">
        Ir a iniciar sesión
      </RouterLink>
    </template>

    <template v-else-if="hecho">
      <div role="status" class="mt-8 flex items-start gap-3 rounded-xl border border-empresas/30 bg-empresas/10 px-4 py-4 text-sm text-azul-noche">
        <svg class="w-5 h-5 shrink-0 text-empresas-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="min-w-0">Contraseña actualizada. Se ha cerrado la sesión en todos tus dispositivos.</span>
      </div>
      <RouterLink to="/login" class="mt-6 w-full h-13 inline-flex items-center justify-center gap-3 rounded-full bg-primary-600 text-white text-base font-bold hover:bg-primary-700">
        Iniciar sesión
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m0 0l-6-6m6 6l-6 6"/>
        </svg>
      </RouterLink>
    </template>

    <template v-else>
      <p class="mt-2 text-base sm:text-lg text-gray-600 break-words">
        Para la cuenta <strong class="font-semibold text-azul-noche">{{ email }}</strong>.
      </p>

      <form class="mt-8 flex flex-col gap-5" novalidate @submit.prevent="enviar">
        <CampoContrasena id="nueva-password" v-model="form.password" label="Nueva contraseña"
                         autocomplete="new-password" :error="errors.password" @focus="errors.password = ''">
          <template #ayuda>
            <p v-if="!errors.password" class="mt-1.5 text-xs text-gray-500">
              Mín. 8 caracteres · mayúscula + minúscula + número + símbolo
            </p>
          </template>
        </CampoContrasena>

        <CampoContrasena id="repetir-password" v-model="form.password_confirmation" label="Repetir contraseña"
                         placeholder="Escribe de nuevo la contraseña" autocomplete="new-password"
                         :error="errors.password_confirmation" @focus="errors.password_confirmation = ''" />

        <div v-if="errorGeneral" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 break-words">
          {{ errorGeneral }}
          <RouterLink to="/login" class="ml-1 underline">Volver al acceso</RouterLink>
        </div>

        <button
          type="submit"
          :disabled="enviando"
          class="mt-1 w-full h-13 inline-flex items-center justify-center rounded-full bg-primary-600 text-white text-base font-bold shadow-[0_8px_24px_rgba(48,114,170,0.25)] transition hover:bg-primary-700 disabled:opacity-60 disabled:cursor-not-allowed"
        >
          {{ enviando ? 'Guardando…' : 'Guardar contraseña' }}
        </button>
      </form>
    </template>
  </AccesoLayout>
</template>
