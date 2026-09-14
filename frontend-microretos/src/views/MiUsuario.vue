<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import api from '../api.js'

const router    = useRouter()
const authStore = useAuthStore()

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

onMounted(async () => {
  try {
    const { data } = await api.get('/perfil')
    email.value     = data.data.email
    form.value.name = data.data.name
  } catch { /* silencioso */ } finally {
    cargando.value = false
  }
})

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
  <div class="min-h-screen pt-12">
    <div class="max-w-lg mx-auto px-4 py-10">

      <!-- Cabecera -->
      <div class="mb-6">
        <button @click="router.back()"
          class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600
                 transition-colors mb-4">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Volver
        </button>
        <h1 class="text-2xl font-black text-[#1F2937]">Mi usuario</h1>
        <p class="text-sm text-gray-400 mt-1">Edita tu nombre o cambia la contraseña de acceso.</p>
      </div>

      <!-- Tarjeta -->
      <div class="bg-white border border-gray-200 rounded-[1.75rem] p-8 shadow-sm space-y-5">

        <!-- Email (solo lectura) + recordatorio -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">
            Correo electrónico
          </label>
          <div v-if="cargando" class="h-10 bg-gray-100 rounded-xl animate-pulse"></div>
          <div v-else
            class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-2.5
                   text-sm text-gray-400 select-none">
            {{ email }}
          </div>
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
                   placeholder-gray-300 outline-none transition-all
                   focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
            :class="errors.name ? 'border-red-400' : 'border-gray-200'" />
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
                   placeholder-gray-300 outline-none transition-all
                   focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
            :class="errors.password ? 'border-red-400' : 'border-gray-200'" />
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
                   placeholder-gray-300 outline-none transition-all
                   focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
            :class="errors.password_confirmation ? 'border-red-400' : 'border-gray-200'" />
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
             ? 'text-[#00A859] bg-[#00A859]/5 border-[#00A859]/20'
             : 'text-red-500 bg-red-50 border-red-200'">
          {{ msg }}
        </p>

        <!-- Guardar -->
        <button @click="guardar" :disabled="guardando"
          class="w-full py-3 rounded-xl bg-[#00A859] hover:bg-[#009950] text-white font-black
                 text-xs uppercase tracking-widest transition-all
                 disabled:opacity-50 disabled:cursor-not-allowed">
          {{ guardando ? 'Guardando...' : 'Guardar cambios' }}
        </button>

      </div>
    </div>
  </div>
</template>
