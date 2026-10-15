<!-- Contraseña del módulo Empresas antes de enviar un email o un enlace de validación a una
     empresa. La protección real está en el backend (sesión desbloqueada en verificar-acceso);
     este modal solo la pide. `motivo` adapta textos y color al envío que se va a hacer. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api.js'

const props = defineProps({
  motivo: { type: String, default: 'email' },   // 'email' | 'validacion'
})

const emit = defineEmits(['desbloqueado', 'cerrar'])

const TEXTOS = {
  email: {
    titulo:      'Enviar email a la empresa',
    descripcion: 'Para escribir directamente a una empresa necesitas la contraseña del módulo Empresas.',
    abriendo:    'Abriendo el formulario de email…',
  },
  validacion: {
    titulo:      'Enviar validación a la empresa',
    descripcion: 'Para enviar a la empresa el enlace de validación de un proyecto necesitas la contraseña del módulo Empresas.',
    abriendo:    'Abriendo el formulario de validación…',
  },
}
const texto = computed(() => TEXTOS[props.motivo] || TEXTOS.email)
// Mismo acento que el botón y el panel que abre: verde empresas (email), ámbar (validación)
const esValidacion = computed(() => props.motivo === 'validacion')

const passwordInput = ref('')
const verificando   = ref(false)
const errorAcceso   = ref('')
const exitoso       = ref(false)
const visible       = ref(false)
const input         = ref(null)

onMounted(() => setTimeout(() => {
  visible.value = true
  input.value?.focus()
}, 60))

async function verificarAcceso() {
  if (!passwordInput.value || verificando.value) return
  verificando.value = true
  errorAcceso.value = ''
  try {
    await api.post('/empresas/verificar-acceso', { password: passwordInput.value }, { skipAuthRedirect: true })
    exitoso.value = true
    setTimeout(() => emit('desbloqueado'), 700)
  } catch (e) {
    errorAcceso.value = e.response?.status === 429
      ? 'Demasiados intentos. Espera un minuto y vuelve a probar.'
      : 'Contraseña incorrecta. Inténtalo de nuevo.'
    passwordInput.value = ''
  } finally {
    verificando.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
    :class="visible ? 'opacity-100' : 'opacity-0'"
    style="transition: opacity 0.3s ease"
    @click.self="!exitoso && emit('cerrar')"
  >
    <div class="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-[2rem] border border-gray-200
                bg-white p-6 sm:p-8 font-sans text-[#1F2937] shadow-2xl"
         role="dialog" aria-modal="true" aria-labelledby="acceso-empresas-titulo">

      <!-- Cabecera: icono (candado → check) + título -->
      <div class="mb-5 flex items-center gap-3">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border transition-colors duration-300"
             :class="exitoso || !esValidacion
               ? 'bg-empresas/10 border-empresas/20'
               : 'bg-amber-50 border-amber-200'">
          <Transition name="icon-swap" mode="out-in">
            <svg v-if="exitoso" key="check" class="h-6 w-6 text-empresas-dark"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            <svg v-else key="lock" class="h-6 w-6"
                 :class="esValidacion ? 'text-amber-700' : 'text-empresas-dark'"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
          </Transition>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] font-black uppercase tracking-widest text-gray-400">Módulo Empresas</p>
          <h2 id="acceso-empresas-titulo" class="text-base sm:text-lg font-black leading-tight text-[#121212] break-words">
            {{ texto.titulo }}
          </h2>
        </div>
      </div>

      <!-- Estado éxito -->
      <div v-if="exitoso" class="flex items-center gap-2 rounded-xl border border-empresas/20 bg-empresas/10 p-3">
        <svg class="h-4 w-4 shrink-0 text-empresas-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        <p class="text-sm font-bold text-empresas-dark">{{ texto.abriendo }}</p>
      </div>

      <!-- Formulario -->
      <form v-else @submit.prevent="verificarAcceso" class="space-y-4">
        <p class="text-sm text-gray-500">
          {{ texto.descripcion }} Solo se pide una vez por sesión.
        </p>

        <div>
          <label for="acceso-empresas-password"
                 class="mb-1.5 block text-[11px] font-black uppercase tracking-widest text-gray-400">
            Contraseña del módulo
          </label>
          <input
            id="acceso-empresas-password"
            ref="input"
            v-model="passwordInput"
            type="password"
            autocomplete="off"
            placeholder="Introduce la contraseña"
            :disabled="verificando"
            :class="[
              'h-11 w-full rounded-xl border bg-gray-50 px-4 text-sm text-[#1F2937] placeholder-gray-400',
              'transition-colors focus:bg-white focus:outline-none',
              errorAcceso ? 'border-red-300 focus:border-red-400' : 'border-gray-200 focus:border-empresas'
            ]"
          />
        </div>

        <div v-if="errorAcceso" class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3" role="alert">
          <svg class="h-4 w-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p class="text-sm font-semibold text-red-600">{{ errorAcceso }}</p>
        </div>

        <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
          <button type="button" @click="emit('cerrar')"
                  class="h-10 rounded-xl border border-gray-200 bg-gray-50 px-4 text-sm font-bold text-gray-600
                         transition-colors hover:border-gray-300 hover:text-[#1F2937]">
            Cancelar
          </button>
          <button type="submit" :disabled="verificando || !passwordInput"
                  class="flex h-10 items-center justify-center gap-2 rounded-xl bg-empresas px-5 text-sm font-bold text-white
                         transition-colors hover:bg-empresas-dark disabled:cursor-not-allowed disabled:opacity-40">
            <svg v-if="verificando" class="h-4 w-4 shrink-0 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
            </svg>
            {{ verificando ? 'Comprobando…' : 'Continuar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.icon-swap-enter-active, .icon-swap-leave-active { transition: all 0.25s ease; }
.icon-swap-enter-from  { opacity: 0; transform: scale(0.5); }
.icon-swap-leave-to    { opacity: 0; transform: scale(1.3); }
</style>
