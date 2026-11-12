<!-- Acceso a DuaLab Central: misma plantilla que el login de DuaLab y la app comercial
     (AccesoLayout). Solo superadmin. Mismo login y misma sesión que la app principal. -->
<script setup>
import { computed, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AccesoLayout from '../../src/components/AccesoLayout.vue'
import CampoContrasena from '../../src/components/CampoContrasena.vue'
import { useLoginForm } from '../../src/composables/useLoginForm.js'
import { useAuthStore, ROLE_SUPERADMIN } from '../../src/stores/auth.js'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()
const errorRol  = ref('')
const SOLO_SUPERADMIN = 'DuaLab Central es solo para el superadmin de DuaLab.'

const { form, errors, loginError, isLoading, handleLogin } = useLoginForm(async (data) => {
  if (Number(data?.role) !== ROLE_SUPERADMIN) {
    await authStore.logout()
    errorRol.value = SOLO_SUPERADMIN
    return
  }
  router.push({ name: 'inicio' })
})

const error = computed(() => errorRol.value || loginError.value || (route.query.motivo === 'rol' ? SOLO_SUPERADMIN : ''))
const sesionCaducada = computed(() => route.query.motivo === 'caducada' && !error.value)

const entrar = () => {
  errorRol.value = ''
  handleLogin()
}
</script>

<template>
  <AccesoLayout>
    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-administraciones-dark sm:mb-3">Superadmin</p>
    <h1 class="break-words text-[1.75rem] font-black leading-tight tracking-tight text-acceso-tinta sm:text-4xl xl:text-[2.6rem]">
      DuaLab Central
    </h1>
    <p class="mt-2 text-base text-acceso-texto sm:text-lg lg:text-base xl:text-lg">Usuarios, base de datos y validación de lo captado.</p>

    <div v-if="sesionCaducada" role="status" class="mt-6 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
      <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      <span>Tu sesión ha caducado. Vuelve a entrar para continuar.</span>
    </div>

    <form class="mt-6 flex flex-col gap-4 sm:mt-8 sm:gap-5" novalidate @submit.prevent="entrar">
      <div>
        <label for="central-email" class="mb-2 block text-sm font-semibold text-acceso-tinta">Correo electrónico</label>
        <div class="relative">
          <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <input id="central-email" v-model="form.email" type="email" placeholder="tuemail@dualab.es"
                 autocomplete="email" autocapitalize="off" autocorrect="off" spellcheck="false" :aria-invalid="!!errors.email"
                 class="h-12 w-full rounded-xl border bg-white pl-12 pr-4 text-base outline-none transition placeholder:text-gray-500 focus:border-administraciones-dark focus:ring-4 focus:ring-administraciones/15"
                 :class="errors.email ? 'border-red-300 bg-red-50' : 'border-borde-campo'"
                 @focus="errors.email = ''" />
        </div>
        <p v-if="errors.email" class="mt-1.5 text-xs font-semibold text-red-600">{{ errors.email }}</p>
      </div>

      <CampoContrasena id="central-password" v-model="form.password" label="Contraseña"
                       :error="errors.password" @focus="errors.password = ''" />

      <div v-if="error" role="alert" class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span class="min-w-0 break-words">{{ error }}</span>
      </div>

      <button type="submit" :disabled="isLoading"
              class="mt-1 inline-flex h-12 w-full items-center justify-center gap-3 rounded-full bg-administraciones-dark text-base font-bold text-white shadow-[0_8px_24px_rgba(15,124,125,0.28)] transition hover:bg-administraciones-dark/90 disabled:cursor-not-allowed disabled:opacity-60">
        <template v-if="!isLoading">
          Entrar
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m0 0l-6-6m6 6l-6 6" /></svg>
        </template>
        <span v-else class="flex gap-1.5" aria-label="Entrando">
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-white" />
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-white [animation-delay:150ms]" />
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-white [animation-delay:300ms]" />
        </span>
      </button>
    </form>

    <p class="mt-6 text-sm text-gray-500">Es la misma cuenta de superadmin que usas en la app DuaLab.</p>
  </AccesoLayout>
</template>
