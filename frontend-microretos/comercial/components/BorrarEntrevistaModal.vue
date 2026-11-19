<!-- Mover una entrevista en curso a la papelera, con el mismo doble aviso que Base de datos
     (EliminarEmpresaModal + DbSecurityModal):
       Fase 1 — qué va a pasar, antes de comprometerse.
       Fase 2 — escribir el nombre exacto y la contraseña. La contraseña la comprueba el backend
                en la misma petición (BorrarEntrevistaComercialRequest), no es solo de pantalla. -->
<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import Modal from '../../src/components/Modal.vue'
import { borrarEmpresaCaptada } from '../../src/services/comercialService.js'

const props = defineProps({
  visible: { type: Boolean, default: false },
  empresa: { type: Object, default: null },
})
const emit = defineEmits(['borrada', 'cerrar'])

const fase       = ref(1)
const confirmar  = ref('')
const password   = ref('')
const verPwd     = ref(false)
const borrando   = ref(false)
const error      = ref('')
const inputNombre = ref(null)

// Enviada por correo y sin rellenar: solo se sabe su email
const nombre = computed(() => props.empresa?.nombre_comercial || props.empresa?.email_general || 'BORRAR')
const nombreValido = computed(() => confirmar.value.trim() === nombre.value)
const enlaceAbierto = computed(() => props.empresa?.formulario?.abierto)

watch(() => props.visible, (v) => {
  if (!v) return
  fase.value = 1
  confirmar.value = ''
  password.value = ''
  verPwd.value = false
  borrando.value = false
  error.value = ''
})

function aFase2() {
  fase.value = 2
  nextTick(() => inputNombre.value?.focus())
}

async function borrar() {
  if (!nombreValido.value || !password.value || borrando.value) return
  borrando.value = true
  error.value = ''
  try {
    await borrarEmpresaCaptada(props.empresa.id, password.value)
    emit('borrada', { id: props.empresa.id, nombre: nombre.value })
  } catch (e) {
    const s = e.response?.status
    error.value = s === 422 ? (e.response.data?.errors?.password?.[0] ?? 'La contraseña no es correcta.')
      : s === 429 ? 'Demasiados intentos. Espera un minuto y vuelve a probar.'
      : s === 403 ? (e.response.data?.error ?? 'Esta entrevista ya no se puede borrar.')
      : s === 404 ? 'La entrevista no se encuentra: puede que ya esté en la papelera.'
      : 'No se ha podido borrar. Comprueba la conexión e inténtalo de nuevo.'
    password.value = ''
  } finally {
    borrando.value = false
  }
}
</script>

<template>
  <Modal :visible="visible" max-width="max-w-lg" card-class="overflow-y-auto border-red-200 p-6 sm:p-8" @cerrar="emit('cerrar')">
    <div class="flex flex-col gap-4">
      <div class="flex items-start gap-3 pr-10">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-500" aria-hidden="true">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
        </span>
        <div class="min-w-0">
          <h2 class="font-heading text-xl font-bold text-azul-noche">Borrar entrevista</h2>
          <p class="mt-1 text-sm text-texto-secundario">Irá a la <strong class="text-amber-700">papelera</strong> y podrás restaurarla desde allí.</p>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
        <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario">Entrevista a borrar</p>
        <p class="break-words font-heading text-base font-bold text-azul-noche">{{ nombre }}</p>
      </div>

      <!-- Fase 1: qué va a pasar -->
      <template v-if="fase === 1">
        <p class="text-sm font-semibold text-gray-600">Al borrarla ocurrirá lo siguiente:</p>
        <ul class="space-y-2 text-sm">
          <li class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="font-bold text-amber-900">Desaparece de «Mis empresas» y pasa a la papelera</p>
            <p class="mt-0.5 text-xs text-amber-800">Sus datos, las respuestas y los retos generados se conservan: al restaurarla vuelve tal cual estaba.</p>
          </li>
          <li v-if="enlaceAbierto" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="font-bold text-amber-900">El enlace que le enviaste por correo deja de funcionar</p>
            <p class="mt-0.5 text-xs text-amber-800">Si la empresa lo abre, verá que no existe. Si la restauras antes de que caduque, vuelve a funcionar.</p>
          </li>
          <li class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
            <p class="font-bold text-blue-800">Borrarla del todo solo lo puede hacer DuaLab</p>
            <p class="mt-0.5 text-xs text-blue-700">Mientras esté en la papelera nadie la valida ni la ve en los centros.</p>
          </li>
        </ul>
        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="emit('cerrar')">Cancelar</button>
          <button type="button" class="btn-paso border-2 border-red-300 bg-white text-red-600 shadow-none hover:border-red-400 hover:bg-red-50" @click="aFase2">
            <span class="sm:hidden">Continuar</span><span class="hidden sm:inline">Entiendo, quiero borrarla</span>
          </button>
        </div>
      </template>

      <!-- Fase 2: nombre exacto + contraseña -->
      <form v-else class="flex flex-col gap-4" @submit.prevent="borrar">
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
          <p class="text-sm font-bold text-red-700">Confirmación final</p>
          <p class="mt-0.5 text-xs text-red-600">Escribe el nombre exacto y tu contraseña para confirmar que no es un error.</p>
        </div>

        <div>
          <label for="be-nombre" class="label-style">Escribe <span class="normal-case tracking-normal text-azul-noche">{{ nombre }}</span> para confirmar</label>
          <input id="be-nombre" ref="inputNombre" v-model="confirmar" autocomplete="off" spellcheck="false"
                 placeholder="Escribe el nombre exacto…" class="input-style"
                 :class="confirmar && !nombreValido ? '!border-red-300' : confirmar && nombreValido ? '!border-green-400' : ''" />
          <p v-if="confirmar && !nombreValido" class="mt-1 text-xs font-semibold text-red-600">No coincide. Revisa mayúsculas, tildes y espacios.</p>
        </div>

        <div>
          <label for="be-pwd" class="label-style">Tu contraseña</label>
          <div class="relative">
            <input id="be-pwd" v-model="password" :type="verPwd ? 'text' : 'password'" autocomplete="current-password" maxlength="128"
                   class="input-style pr-12" />
            <button type="button" class="absolute right-1.5 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    :aria-label="verPwd ? 'Ocultar contraseña' : 'Mostrar contraseña'" @click="verPwd = !verPwd">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path v-if="verPwd" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.1A9.8 9.8 0 0112 5c5 0 9 4.5 10 7a12 12 0 01-3 4.2M6.6 6.6A12 12 0 002 12c1 2.5 5 7 10 7a9.6 9.6 0 004.4-1" />
                <path v-else d="M2 12c1-2.5 5-7 10-7s9 4.5 10 7c-1 2.5-5 7-10 7S3 14.5 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z" />
              </svg>
            </button>
          </div>
        </div>

        <p v-if="error" role="alert" class="aviso-error"><span class="min-w-0 break-words">{{ error }}</span></p>

        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="fase = 1">Volver</button>
          <button type="submit" class="btn-paso bg-red-600 shadow-red-600/30 hover:bg-red-700" :disabled="!nombreValido || !password || borrando">
            <span class="sm:hidden">{{ borrando ? 'Moviendo…' : 'Mover' }}</span>
            <span class="hidden sm:inline">{{ borrando ? 'Moviendo a la papelera…' : 'Mover a la papelera' }}</span>
          </button>
        </div>
      </form>
    </div>
  </Modal>
</template>
