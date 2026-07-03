<template>
  <Teleport to="body">
    <Transition name="overlay">
      <div v-if="modelValue" class="modal-overlay" @click.self="$emit('update:modelValue', false)">
        <Transition name="modal">
          <div class="modal-card" v-if="modelValue">

            <div class="modal-header">
              <div class="logo-mark">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
              </div>
              <h2 class="modal-title">Acceso DuaLab</h2>
              <p class="modal-subtitle">Introduce tus credenciales</p>
            </div>

            <form class="modal-form" @submit.prevent="handleLogin">
              <div class="field-group" :class="{ 'has-error': errors.email }">
                <label class="field-label">Correo electrónico</label>
                <div class="field-wrapper">
                  <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                  <input
                    v-model="form.email"
                    type="email"
                    class="field-input"
                    placeholder="admin@ejemplo.com"
                    autocomplete="email"
                    @focus="errors.email = ''"
                  />
                </div>
                <span class="field-error" v-if="errors.email">{{ errors.email }}</span>
              </div>

              <div class="field-group" :class="{ 'has-error': errors.password }">
                <label class="field-label">Contraseña</label>
                <div class="field-wrapper">
                  <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                  <input
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    class="field-input"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    @focus="errors.password = ''"
                  />
                  <button type="button" class="toggle-password" @click="showPassword = !showPassword">
                    <svg v-if="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                  </button>
                </div>
                <span class="field-error" v-if="errors.password">{{ errors.password }}</span>
              </div>

              <Transition name="alert">
                <div class="alert-error" v-if="loginError">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  {{ loginError }}
                </div>
              </Transition>

              <button type="submit" class="btn-submit" :class="{ loading: isLoading }" :disabled="isLoading">
                <span class="btn-text" v-if="!isLoading">Entrar</span>
                <span class="btn-loader" v-else>
                  <span></span><span></span><span></span>
                </span>
              </button>
            </form>

            <button class="modal-close" @click="$emit('update:modelValue', false)" aria-label="Cerrar">
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

<script setup>
import { ref, reactive } from 'vue'
import { useAuthStore } from '../stores/auth'
import api from '../api.js'

const authStore = useAuthStore()

const props = defineProps({
  modelValue: Boolean
})

const emit = defineEmits(['update:modelValue', 'login-success'])

const form = reactive({ email: '', password: '' })
const errors = reactive({ email: '', password: '' })
const loginError = ref('')
const isLoading = ref(false)
const showPassword = ref(false)

function validate() {
  let valid = true
  errors.email = ''
  errors.password = ''

  if (!form.email) {
    errors.email = 'El correo es obligatorio'
    valid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.email = 'Formato de correo inválido'
    valid = false
  }

  if (!form.password) {
    errors.password = 'La contraseña es obligatoria'
    valid = false
  }

  return valid
}

async function handleLogin() {
  if (!validate()) return

  isLoading.value = true
  loginError.value = ''

  try {
    const response = await api.post('/admin/login', {
      email: form.email,
      password: form.password
    })

    if (response.data.success) {
      authStore.login(
        response.data.token,
        response.data.role,
        response.data.name,
        response.data.centro_educativo_id ?? null,
        response.data.centro_nombre ?? '',
        response.data.centro_img ?? ''
      )
      emit('login-success', response.data)
      emit('update:modelValue', false)
      form.email = ''
      form.password = ''
    }
  } catch (error) {
    if (error.response?.status === 401) {
      loginError.value = 'Credenciales incorrectas. Inténtalo de nuevo.'
    } else {
      loginError.value = 'Error de conexión. Inténtalo más tarde.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
* { box-sizing: border-box; margin: 0; padding: 0; }

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1rem;
}

.modal-card {
  position: relative;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 2rem;
  padding: 3rem 2.5rem 2.5rem;
  width: 100%;
  max-width: 420px;
  box-shadow:
    0 0 0 1px rgba(0, 168, 89, 0.04),
    0 40px 80px rgba(0, 0, 0, 0.12),
    0 0 60px rgba(0, 168, 89, 0.06);
}

.modal-header {
  text-align: center;
  margin-bottom: 2.5rem;
}

.logo-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  background: linear-gradient(135deg, #00A859, #99CC33);
  border-radius: 14px;
  margin-bottom: 1.25rem;
  box-shadow: 0 8px 24px rgba(0, 168, 89, 0.3);
}

.modal-title {
  font-size: 1.6rem;
  font-weight: 900;
  color: #1F2937;
  letter-spacing: -0.02em;
  margin-bottom: 0.4rem;
}

.modal-subtitle {
  font-size: 0.72rem;
  color: #9CA3AF;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  font-weight: 600;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.field-label {
  font-size: 0.7rem;
  color: #6B7280;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  font-weight: 700;
}

.field-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.field-icon {
  position: absolute;
  left: 1rem;
  width: 1rem;
  height: 1rem;
  color: #9CA3AF;
  pointer-events: none;
}

.field-input {
  width: 100%;
  background: #F0FBF4;
  border: 2px solid #BBE8D0;
  border-radius: 12px;
  padding: 0.85rem 2.8rem 0.85rem 2.8rem;
  color: #1F2937;
  font-size: 0.88rem;
  font-weight: 500;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
  outline: none;
}

.field-input::placeholder { color: #9CA3AF; }

.field-input:focus {
  border-color: #00A859;
  background: #E6F7EE;
  box-shadow: 0 0 0 4px rgba(0, 168, 89, 0.12);
}

.has-error .field-input {
  border-color: #fca5a5;
  background: #fff5f5;
}

.toggle-password {
  position: absolute;
  right: 0.9rem;
  background: none;
  border: none;
  cursor: pointer;
  color: #9CA3AF;
  padding: 0.2rem;
  transition: color 0.2s;
  display: flex;
  align-items: center;
}

.toggle-password:hover { color: #6B7280; }

.field-error {
  font-size: 0.68rem;
  color: #ef4444;
  font-weight: 600;
  padding-left: 0.2rem;
}

.alert-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  font-size: 0.78rem;
  color: #dc2626;
  font-weight: 500;
}

.btn-submit {
  margin-top: 0.5rem;
  width: 100%;
  padding: 0.95rem;
  background: linear-gradient(135deg, #00A859, #3db87a);
  border: none;
  border-radius: 12px;
  color: white;
  font-size: 0.8rem;
  font-weight: 900;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
  box-shadow: 0 4px 20px rgba(0, 168, 89, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
}

.btn-submit:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 28px rgba(0, 168, 89, 0.4);
}

.btn-submit:active:not(:disabled) { transform: translateY(0); }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-loader {
  display: flex;
  gap: 5px;
  align-items: center;
}

.btn-loader span {
  width: 6px;
  height: 6px;
  background: white;
  border-radius: 50%;
  animation: bounce 0.6s infinite alternate;
}

.btn-loader span:nth-child(2) { animation-delay: 0.15s; }
.btn-loader span:nth-child(3) { animation-delay: 0.3s; }

@keyframes bounce {
  from { transform: translateY(0); opacity: 0.4; }
  to   { transform: translateY(-5px); opacity: 1; }
}

.modal-close {
  position: absolute;
  top: 1.25rem;
  right: 1.25rem;
  background: #f3f4f6;
  border: none;
  border-radius: 8px;
  color: #9CA3AF;
  width: 32px;
  height: 32px;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-close:hover {
  background: #e5e7eb;
  color: #1F2937;
}

/* Transitions */
.overlay-enter-active, .overlay-leave-active { transition: opacity 0.3s ease; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }

.modal-enter-active { transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from { opacity: 0; transform: scale(0.9) translateY(20px); }
.modal-leave-to   { opacity: 0; transform: scale(0.95) translateY(10px); }

.alert-enter-active, .alert-leave-active { transition: all 0.25s ease; }
.alert-enter-from, .alert-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
