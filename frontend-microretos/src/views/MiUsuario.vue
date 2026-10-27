<script setup>
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import api from '../api.js'
import { useRoleTheme } from '../composables/useRoleTheme.js'
import PanelModulosDocente from '../components/PanelModulosDocente.vue'

const router    = useRouter()
const authStore = useAuthStore()
const { theme } = useRoleTheme()

const paletteExtra = {
  centros:          { focusInput: 'focus:border-centros/50 focus:ring-2 focus:ring-centros/10' },
  empresas:         { focusInput: 'focus:border-empresas/50 focus:ring-2 focus:ring-empresas/10' },
  administraciones: { focusInput: 'focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10' },
  primary:          { focusInput: 'focus:border-primary-600/50 focus:ring-2 focus:ring-primary-600/10' },
}

const email    = ref('')
const cargando = ref(true)

const form = ref({
  name:                  authStore.userName,
  password:              '',
  password_confirmation: '',
})

const errors    = ref({})
const msg       = ref('')
const msgOk     = ref(false)
const guardando = ref(false)

const passwordRelleno = computed(() => form.value.password.length > 0)

// El correo no se edita, pero sí se puede copiar
const copiado = ref(false)
async function copiarEmail() {
  try {
    await navigator.clipboard.writeText(email.value)
    copiado.value = true
    setTimeout(() => { copiado.value = false }, 2000)
  } catch { /* sin permiso de portapapeles: el texto sigue seleccionable a mano */ }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/perfil')
    email.value     = data.data.email
    form.value.name = data.data.name
  } catch { /* silencioso */ } finally {
    cargando.value = false
  }
})

// «Módulos que imparto» (docentes y admins de centro): toda la lógica en PanelModulosDocente
const imparte = computed(() => (authStore.isDocente || authStore.isAdmin) && !!authStore.userCentroId)

async function guardar() {
  errors.value    = {}
  msg.value       = ''
  msgOk.value     = false

  // Validación cliente: contraseñas deben coincidir
  if (passwordRelleno.value && form.value.password !== form.value.password_confirmation) {
    errors.value.password_confirmation = 'Las contraseñas no coinciden.'
    return
  }

  guardando.value = true

  try {
    const payload = { name: form.value.name }
    if (passwordRelleno.value) {
      payload.password              = form.value.password
      payload.password_confirmation = form.value.password_confirmation
    }

    const { data } = await api.patch('/perfil', payload)

    if (data.password_changed) {
      authStore.logout()
      router.push('/')
    } else {
      authStore.updateName(data.data.name)
      form.value.password              = ''
      form.value.password_confirmation = ''
      msg.value   = data.message
      msgOk.value = true
    }
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(e.response.data.errors ?? {}).map(([k, v]) => [k, v[0]])
      )
    } else {
      msg.value = e.response?.data?.message ?? 'Error al guardar.'
    }
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div class="min-h-screen pt-16">
    <!-- Mismo contenedor que el resto de vistas (la cabecera empieza en el mismo sitio); debajo,
         el formulario centrado y, si imparte, la tarjeta de módulos (más ancha) bajo él -->
    <div class="mx-auto max-w-[90rem] px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Mi" destacado="usuario" antetitulo="Mi cuenta" volver="/"
                       subtitulo="Edita tu nombre o cambia la contraseña de acceso." class="mb-6" />

      <div class="space-y-6">

      <!-- Tarjeta -->
      <div class="mx-auto w-full max-w-lg bg-white border border-gray-200 rounded-[1.75rem] p-5 sm:p-8 shadow-sm space-y-5">

        <!-- Email (solo lectura) + recordatorio -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">
            Correo electrónico
          </label>
          <div v-if="cargando" class="h-10 bg-gray-100 rounded-xl animate-pulse"></div>
          <div v-else class="flex items-center gap-2 w-full bg-gray-100 border border-gray-200 rounded-xl py-1 pl-4 pr-1">
            <span class="min-w-0 flex-1 py-1.5 text-sm text-gray-500 break-all">{{ email }}</span>
            <button type="button" @click="copiarEmail" :disabled="!email"
                    :aria-label="copiado ? 'Correo copiado' : 'Copiar correo electrónico'"
                    :title="copiado ? 'Copiado' : 'Copiar'"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white border border-gray-200
                           text-gray-500 transition-colors hover:text-gray-700 disabled:opacity-40"
                    :class="copiado && theme.text">
              <svg v-if="!copiado" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="9" y="9" width="11" height="11" rx="2" stroke-width="2" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15V6a2 2 0 012-2h9" />
              </svg>
              <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
            </button>
          </div>
          <span class="sr-only" aria-live="polite">{{ copiado ? 'Correo copiado al portapapeles' : '' }}</span>
          <!-- Aviso permanente -->
          <div class="mt-2 rounded-xl bg-blue-50 border border-blue-200 px-3 py-2.5 flex items-start gap-2">
            <svg class="w-3.5 h-3.5 text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-[11px] text-blue-700 leading-relaxed">
              Tu correo electrónico es tu identificador de acceso y <strong>no cambia</strong> aunque modifiques el nombre o la contraseña. Necesitarás este correo para iniciar sesión.
            </p>
          </div>
        </div>

        <!-- Nombre -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">
            Nombre
          </label>
          <input v-model="form.name" type="text" maxlength="255" placeholder="Tu nombre completo"
            class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                   placeholder-gray-300 outline-none transition-all"
            :class="[errors.name ? 'border-red-400' : 'border-gray-200', paletteExtra[theme.key].focusInput]" />
          <p v-if="errors.name" class="text-[10px] text-red-500 mt-1">{{ errors.name }}</p>
        </div>

        <!-- Separador cambio de contraseña -->
        <div class="flex items-center gap-3">
          <div class="flex-1 h-px bg-gray-100"></div>
          <span class="text-[10px] font-black uppercase tracking-widest text-gray-300">
            Cambiar contraseña
          </span>
          <div class="flex-1 h-px bg-gray-100"></div>
        </div>

        <!-- Nueva contraseña -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">
            Nueva contraseña
            <span class="normal-case text-gray-400 font-normal">(opcional)</span>
          </label>
          <input v-model="form.password" type="password" maxlength="128"
            placeholder="Dejar en blanco para no cambiar"
            class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                   placeholder-gray-300 outline-none transition-all"
            :class="[errors.password ? 'border-red-400' : 'border-gray-200', paletteExtra[theme.key].focusInput]" />
          <p v-if="errors.password" class="text-[10px] text-red-500 mt-1">{{ errors.password }}</p>
          <p v-else-if="passwordRelleno" class="text-[10px] text-gray-400 mt-1">
            Mín. 8 caracteres · mayúscula + minúscula + número
          </p>
        </div>

        <!-- Repetir contraseña (solo si se está rellenando) -->
        <div v-if="passwordRelleno">
          <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">
            Repetir contraseña
          </label>
          <input v-model="form.password_confirmation" type="password" maxlength="128"
            placeholder="Escribe de nuevo la contraseña"
            class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                   placeholder-gray-300 outline-none transition-all"
            :class="[errors.password_confirmation ? 'border-red-400' : 'border-gray-200', paletteExtra[theme.key].focusInput]" />
          <p v-if="errors.password_confirmation" class="text-[10px] text-red-500 mt-1">
            {{ errors.password_confirmation }}
          </p>
        </div>

        <!-- Aviso al cambiar contraseña -->
        <div v-if="passwordRelleno"
          class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3">
          <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor"
               viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          <p class="text-xs text-amber-700">
            Al cambiar la contraseña tu sesión se cerrará en todos los dispositivos y tendrás que volver a iniciar sesión.
          </p>
        </div>

        <!-- Feedback -->
        <p v-if="msg" class="text-xs px-3 py-2 rounded-lg border"
           :class="msgOk
             ? [theme.text, theme.bg5, theme.border20]
             : 'text-red-500 bg-red-50 border-red-200'">
          {{ msg }}
        </p>

        <!-- Guardar -->
        <button @click="guardar" :disabled="guardando"
          class="w-full py-3 rounded-full text-white font-black
                 text-xs uppercase tracking-widest transition-all
                 disabled:opacity-50 disabled:cursor-not-allowed"
          :class="[theme.bg, theme.bgHover]">
          {{ guardando ? 'Guardando...' : 'Guardar cambios' }}
        </button>

      </div>

      <!-- Módulos que imparto (docentes y admins de centro) -->
      <div v-if="imparte" class="mx-auto w-full max-w-3xl bg-white border border-gray-200 rounded-[1.75rem] p-5 sm:p-8 shadow-sm">
        <PanelModulosDocente />
      </div>
      </div>
    </div>
  </div>
</template>
