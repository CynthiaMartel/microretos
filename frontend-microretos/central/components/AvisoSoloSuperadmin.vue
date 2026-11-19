<!-- «Este acceso es solo para el superadmin» (Admin Central DuaLab al pulsar Base de datos o Papelera, o al
     entrar por su URL). Fase 1: el aviso. Fase 2: el mismo inicio de sesión que la pantalla de acceso,
     aquí dentro; si la cuenta es de superadmin, entra en la sección. Central tiene una sola sesión: entrar
     como superadmin sustituye la de Admin Central DuaLab. Lo que protege los datos es el backend
     (middleware 'superadmin'): esto es experiencia de usuario. Estado compartido: avisoSuperadmin.js. -->
<script setup>
import { nextTick, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CampoContrasena from '../../src/components/CampoContrasena.vue'
import { useLoginForm } from '../../src/composables/useLoginForm.js'
import { ROLE_SUPERADMIN } from '../../src/stores/auth.js'
import { avisoSuperadmin } from '../avisoSuperadmin.js'

const router = useRouter()
const fase = ref(1)
const errorRol = ref('')

const { form, errors, loginError, isLoading, handleLogin } = useLoginForm((data) => {
  if (Number(data?.role) !== ROLE_SUPERADMIN) {
    errorRol.value = 'Esa cuenta no es de superadmin. Has entrado con ella; para esta sección hace falta una de superadmin.'
    return
  }
  const destino = avisoSuperadmin.value?.destino
  cerrar()
  router.push(destino ?? { name: 'inicio' })
})

watch(avisoSuperadmin, (v) => {
  if (!v) return
  fase.value = 1
  errorRol.value = ''
  form.email = ''
  form.password = ''
})

function cerrar() { avisoSuperadmin.value = null }
async function iniciarSesion() {
  fase.value = 2
  await nextTick()
  document.getElementById('aviso-super-email')?.focus()
}
function entrar() {
  errorRol.value = ''
  handleLogin()
}
</script>

<template>
  <Teleport to="body">
    <div v-if="avisoSuperadmin" class="fixed inset-0 z-[70] flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
         @keydown.esc="cerrar" @click.self="cerrar">
      <section role="dialog" aria-modal="true" aria-labelledby="aviso-super-titulo"
               class="relative w-full max-w-md overflow-y-auto rounded-t-[2rem] bg-white p-6 shadow-2xl max-h-[92dvh] sm:rounded-[2rem] sm:p-8">
        <button type="button" class="accion-icono absolute right-4 top-4" aria-label="Cerrar" @click="cerrar">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600" aria-hidden="true">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </span>
        <h2 id="aviso-super-titulo" class="mt-4 pr-10 font-heading text-xl font-bold text-azul-noche">Este acceso es solo para el superadmin</h2>

        <template v-if="fase === 1">
          <p class="mt-2 text-sm text-texto-secundario">
            {{ avisoSuperadmin.nombre }} guarda información sensible. Vuelve a iniciar sesión como superadmin para entrar.
          </p>
          <p class="mt-2 text-xs text-texto-secundario">Se cerrará tu sesión de Admin Central DuaLab.</p>
          <div class="fila-pasos mt-6">
            <button type="button" class="btn-paso-atras" @click="cerrar">Cancelar</button>
            <button type="button" class="btn-paso" @click="iniciarSesion">Iniciar sesión como superadmin</button>
          </div>
        </template>

        <form v-else class="mt-4 space-y-4" novalidate @submit.prevent="entrar">
          <div>
            <label for="aviso-super-email" class="field-label">Correo de superadmin</label>
            <input id="aviso-super-email" v-model="form.email" type="email" autocomplete="username" class="field-input" :aria-invalid="!!errors.email" />
            <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
          </div>
          <CampoContrasena id="aviso-super-password" v-model="form.password" label="Contraseña" :error="errors.password" />
          <p v-if="errorRol || loginError" class="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ errorRol || loginError }}</p>
          <div class="fila-pasos">
            <button type="button" class="btn-paso-atras" :disabled="isLoading" @click="fase = 1">Volver</button>
            <button type="submit" class="btn-paso" :disabled="isLoading">{{ isLoading ? 'Entrando…' : 'Entrar' }}</button>
          </div>
        </form>
      </section>
    </div>
  </Teleport>
</template>
