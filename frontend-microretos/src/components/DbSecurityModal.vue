<script setup>
import { ref, watch, nextTick } from 'vue'
import api from '../api.js'

const props = defineProps({
  visible: { type: Boolean, default: false },
})
const emit = defineEmits(['verified', 'cancelled'])

const password = ref('')
const error    = ref('')
const loading  = ref(false)
const showPwd  = ref(false)
const inputRef = ref(null)

watch(() => props.visible, (v) => {
  if (v) {
    password.value = ''
    error.value    = ''
    loading.value  = false
    showPwd.value  = false
    nextTick(() => inputRef.value?.focus())
  }
})

async function verificar() {
  if (!password.value.trim()) {
    error.value = 'Introduce tu contraseña de administrador.'
    return
  }
  loading.value = true
  error.value   = ''
  try {
    await api.post('/admin/verify-password', { password: password.value }, { skipAuthRedirect: true })
    emit('verified')
  } catch (e) {
    if (e.response?.status === 401) {
      // Sanctum rechaza el token (sesión expirada) vs contraseña incorrecta del admin
      const esTokenExpirado = (e.response?.data?.message || '').toLowerCase().includes('unauthenticated')
      if (esTokenExpirado) {
        error.value = 'Tu sesión ha expirado. Recarga la página e inicia sesión de nuevo.'
        // Disparar manualmente el evento para que App.vue limpie y redirija
        window.dispatchEvent(new CustomEvent('auth:token-expired'))
      } else {
        error.value = 'Contraseña incorrecta. Inténtalo de nuevo.'
        password.value = ''
        nextTick(() => inputRef.value?.focus())
      }
    } else if (e.response?.status === 429) {
      error.value = e.response?.data?.message || 'Demasiados intentos. Inténtalo más tarde.'
    } else {
      error.value = 'Error al verificar. Inténtalo de nuevo.'
      password.value = ''
      nextTick(() => inputRef.value?.focus())
    }
  } finally {
    loading.value = false
  }
}

function cancelar() {
  emit('cancelled')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="dsm-overlay">
      <div
        v-if="visible"
        class="dsm-overlay"
        @click.self="cancelar"
      >
        <Transition name="dsm-card">
          <div v-if="visible" class="dsm-card">

            <!-- Cabecera -->
            <div class="dsm-header">
              <div class="dsm-icon-box">
                <svg class="w-7 h-7 text-[#99CC33]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="dsm-title">Verificación de seguridad</h2>
                <p class="dsm-sub">Caduca cada 30 minutos para proteger la base de datos</p>
              </div>
            </div>

            <!-- Info -->
            <div class="dsm-info-box">
              <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <p class="text-xs text-amber-700 leading-relaxed">
                Para crear, editar o eliminar registros necesitas re-verificar tu identidad. Introduce tu contraseña de administrador para continuar.
              </p>
            </div>

            <!-- Campo contraseña -->
            <div class="mt-6">
              <label class="dsm-label">Contraseña de administrador</label>
              <div class="relative">
                <input
                  ref="inputRef"
                  :type="showPwd ? 'text' : 'password'"
                  v-model="password"
                  class="dsm-input"
                  :class="{ 'dsm-input-err': error }"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  @keydown.enter.prevent="verificar"
                />
                <button
                  type="button"
                  @click="showPwd = !showPwd"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                >
                  <svg v-if="showPwd" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
              </div>
              <Transition name="dsm-fade">
                <p v-if="error" class="dsm-err">{{ error }}</p>
              </Transition>
            </div>

            <!-- Acciones -->
            <div class="dsm-actions">
              <button type="button" @click="cancelar" class="dsm-btn-ghost flex-1">
                Cancelar
              </button>
              <button
                type="button"
                @click="verificar"
                :disabled="loading || !password.trim()"
                class="dsm-btn-dark flex-[2]"
              >
                <svg v-if="loading" class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                </svg>
                <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                {{ loading ? 'Verificando...' : 'Verificar identidad' }}
              </button>
            </div>

            <!-- X -->
            <button type="button" class="dsm-x" @click="cancelar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.dsm-overlay {
  position: fixed; inset: 0;
  background: rgba(10, 18, 25, 0.82);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  display: flex; align-items: center; justify-content: center;
  z-index: 10100; padding: 1rem;
}
.dsm-card {
  position: relative; background: #fff;
  border: 1px solid #e5e7eb; border-radius: 2rem; padding: 2.5rem;
  width: 100%; max-width: 480px;
  box-shadow: 0 0 0 1px rgba(0,0,0,.03), 0 24px 48px rgba(0,0,0,.22), 0 0 80px rgba(153,204,51,.06);
}

.dsm-header   { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
.dsm-icon-box {
  flex-shrink: 0; width: 52px; height: 52px;
  background: rgba(31,41,55,0.9); border-radius: 1rem;
  display: flex; align-items: center; justify-content: center;
}
.dsm-title    { font-size: 1.2rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.dsm-sub      { font-size: .75rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }

.dsm-info-box {
  display: flex; gap: .75rem; align-items: flex-start;
  background: #fffbeb; border: 1px solid #fde68a; border-radius: 1rem;
  padding: .85rem 1rem;
}

.dsm-label { display: block; font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .45rem; margin-left: .25rem; }
.dsm-err   { font-size: .7rem; color: #ef4444; font-weight: 700; margin-top: .35rem; margin-left: .25rem; }

.dsm-input {
  width: 100%; border: 2px solid #e5e7eb; border-radius: 1rem; padding: .85rem 2.75rem .85rem 1rem;
  font-size: .875rem; font-weight: 600; color: #1F2937; background: #f9fafb; outline: none; transition: all .2s;
}
.dsm-input:focus     { background: #fff; border-color: #1F2937; box-shadow: 0 0 0 4px rgba(31,41,55,.1); }
.dsm-input-err       { border-color: #fca5a5 !important; background: #fff5f5 !important; }

.dsm-actions { display: flex; gap: .75rem; margin-top: 2rem; }

.dsm-btn-dark {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: 1rem 1.5rem;
  background: linear-gradient(135deg, #00A859, #3db87a); color: #fff;
  border: none; border-radius: 1rem;
  font-weight: 900; font-size: .7rem; letter-spacing: .15em; text-transform: uppercase;
  cursor: pointer; box-shadow: 0 6px 20px rgba(0,168,89,.25); transition: all .2s;
}
.dsm-btn-dark:hover:not(:disabled)  { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(0,168,89,.35); }
.dsm-btn-dark:active:not(:disabled) { transform: scale(.97); }
.dsm-btn-dark:disabled              { opacity: .45; cursor: not-allowed; }

.dsm-btn-ghost {
  display: flex; align-items: center; justify-content: center;
  padding: 1rem 1.5rem;
  background: #fff; color: #6b7280; border: 2px solid #e5e7eb; border-radius: 1rem;
  font-weight: 900; font-size: .7rem; letter-spacing: .15em; text-transform: uppercase;
  cursor: pointer; transition: all .2s;
}
.dsm-btn-ghost:hover  { border-color: #d1d5db; color: #374151; }
.dsm-btn-ghost:active { transform: scale(.97); }

.dsm-x {
  position: absolute; top: 1.25rem; right: 1.25rem; width: 32px; height: 32px;
  background: #f3f4f6; border: none; border-radius: .5rem; color: #9ca3af;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .2s, color .2s;
}
.dsm-x:hover { background: #e5e7eb; color: #1F2937; }

.dsm-overlay-enter-active, .dsm-overlay-leave-active { transition: opacity .3s ease; }
.dsm-overlay-enter-from,   .dsm-overlay-leave-to    { opacity: 0; }
.dsm-card-enter-active  { transition: all .4s cubic-bezier(.34,1.56,.64,1); }
.dsm-card-leave-active  { transition: all .2s ease; }
.dsm-card-enter-from    { opacity: 0; transform: scale(.91) translateY(24px); }
.dsm-card-leave-to      { opacity: 0; transform: scale(.96) translateY(8px); }
.dsm-fade-enter-active, .dsm-fade-leave-active { transition: all .22s ease; }
.dsm-fade-enter-from,   .dsm-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>
