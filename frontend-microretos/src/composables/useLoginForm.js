import { ref, reactive } from 'vue'
import { useAuthStore } from '../stores/auth'
import { login } from '../services/authService.js'

// Lógica de login compartida entre LoginModal (Home) y la página /login (LoginView).
// onSuccess recibe la respuesta del backend una vez guardada la sesión en el store.
export function useLoginForm(onSuccess) {
  const authStore = useAuthStore()

  const form = reactive({ email: '', password: '', remember: false })
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
      const response = await login({
        email: form.email,
        password: form.password,
        remember: form.remember
      })

      if (response.data.success) {
        authStore.login(response.data)
        form.email = ''
        form.password = ''
        onSuccess?.(response.data)
      }
    } catch (error) {
      const status = error.response?.status
      if (status === 401) {
        loginError.value = 'Credenciales incorrectas. Inténtalo de nuevo.'
      } else if (status === 403 || status === 429) {
        loginError.value = error.response?.data?.message || 'No se ha podido iniciar sesión.'
      } else if (status === 422) {
        const errs = error.response?.data?.errors ?? {}
        loginError.value = Object.values(errs)[0]?.[0] || 'Revisa el correo y la contraseña introducidos.'
      } else {
        loginError.value = 'Error de conexión. Inténtalo más tarde.'
      }
    } finally {
      isLoading.value = false
    }
  }

  return { form, errors, loginError, isLoading, showPassword, handleLogin }
}
