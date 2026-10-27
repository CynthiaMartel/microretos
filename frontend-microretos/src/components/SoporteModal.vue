<!-- "Contacta con soporte": formulario que llega a soporte@dualab.es (POST /soporte).
     Público: lo usa sobre todo quien no consigue iniciar sesión. -->
<script setup>
import { ref, reactive, watch, nextTick, onBeforeUnmount } from 'vue'
import { enviarSoporte } from '../services/soporteService.js'

const props = defineProps({
  // Prerrelleno con lo que ya se haya escrito en el login
  emailInicial: { type: String, default: '' },
})
const abierto = defineModel({ type: Boolean, default: false })

// Mismas claves que SoporteAvisoMail::TIPOS en el backend
const TIPOS = [
  { value: 'acceso',   label: 'No puedo iniciar sesión' },
  { value: 'error',    label: 'Algo no funciona' },
  { value: 'consulta', label: 'Duda sobre la plataforma' },
  { value: 'otro',     label: 'Otro' },
]

const form = reactive({ nombre: '', email: '', tipo: 'acceso', mensaje: '', website: '' })
const errores  = ref({})
const errorGeneral = ref('')
const enviando = ref(false)
const enviado  = ref('')
const primerCampo = ref(null)

watch(abierto, async (v) => {
  if (!v) return
  if (!form.email) form.email = props.emailInicial
  errores.value = {}
  errorGeneral.value = ''
  await nextTick()
  primerCampo.value?.focus()
})

function cerrar() {
  abierto.value = false
  // Tras un envío correcto, la próxima apertura empieza con el formulario limpio
  if (enviado.value) {
    Object.assign(form, { nombre: '', tipo: 'acceso', mensaje: '', website: '' })
    enviado.value = ''
  }
}

function onKeydown(e) {
  if (e.key === 'Escape' && abierto.value) cerrar()
}
window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

function validar() {
  const e = {}
  if (!form.nombre.trim()) e.nombre = 'Indica tu nombre.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = 'Introduce un correo electrónico válido.'
  if (form.mensaje.trim().length < 10) e.mensaje = 'Cuéntanos un poco más (mínimo 10 caracteres).'
  errores.value = e
  // En móvil el primer error puede quedar fuera de la vista: llevar el foco allí
  const primero = Object.keys(e)[0]
  if (primero) document.getElementById(`soporte-${primero}`)?.focus()
  return !primero
}

async function enviar() {
  errorGeneral.value = ''
  if (!validar()) return
  enviando.value = true
  try {
    const { data } = await enviarSoporte({ ...form })
    enviado.value = data.message
  } catch (error) {
    const status = error.response?.status
    if (status === 422) {
      const errs = error.response.data?.errors ?? {}
      errores.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]))
    } else if (status === 429) {
      errorGeneral.value = 'Has enviado demasiadas consultas. Espera un poco e inténtalo de nuevo.'
    } else {
      errorGeneral.value = 'No hemos podido enviar tu consulta. Inténtalo de nuevo más tarde.'
    }
  } finally {
    enviando.value = false
  }
}

const inputClase = (campo) => [
  'w-full rounded-xl border bg-white px-4 text-base outline-none transition placeholder:text-gray-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/15',
  errores.value[campo] ? 'border-red-300 bg-red-50' : 'border-gray-300',
]
</script>

<template>
  <Teleport to="body">
    <div v-if="abierto" class="fixed inset-0 z-[9999] flex items-center justify-center bg-azul-noche/60 backdrop-blur-sm p-4"
         @click.self="cerrar">
      <div role="dialog" aria-modal="true" aria-labelledby="soporte-titulo"
           class="relative w-[min(36rem,calc(100vw-2rem))] max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-[2rem] bg-white p-5 sm:p-8 shadow-2xl">

        <button type="button" aria-label="Cerrar" @click="cerrar"
                class="absolute top-3 right-3 w-10 h-10 flex items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-azul-noche">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>

        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary-500 mb-2 pr-10">Soporte DUALAB</p>
        <h2 id="soporte-titulo" class="text-2xl sm:text-3xl font-black tracking-tight text-azul-noche pr-10">¿En qué podemos ayudarte?</h2>

        <!-- Confirmación -->
        <div v-if="enviado" role="status" class="mt-6">
          <div class="flex items-start gap-3 rounded-xl border border-empresas/30 bg-empresas/10 px-4 py-4 text-sm text-azul-noche">
            <svg class="w-5 h-5 shrink-0 text-empresas-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="min-w-0 break-words">{{ enviado }} Te hemos enviado una copia de confirmación a <strong>{{ form.email }}</strong>.</span>
          </div>
          <button type="button" class="mt-6 w-full h-12 rounded-full bg-primary-600 text-white font-bold hover:bg-primary-700" @click="cerrar">
            Cerrar
          </button>
        </div>

        <form v-else class="mt-6 flex flex-col gap-4" novalidate @submit.prevent="enviar">
          <p class="text-sm text-gray-600">Te responderemos por email, normalmente en uno o dos días laborables.</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="min-w-0">
              <label for="soporte-nombre" class="block text-sm font-semibold text-azul-noche mb-1.5">Nombre</label>
              <input id="soporte-nombre" ref="primerCampo" v-model="form.nombre" type="text" maxlength="120"
                     autocomplete="name" :class="inputClase('nombre')" class="h-12" @input="delete errores.nombre" />
              <p v-if="errores.nombre" class="mt-1 text-xs font-semibold text-red-600">{{ errores.nombre }}</p>
            </div>
            <div class="min-w-0">
              <label for="soporte-email" class="block text-sm font-semibold text-azul-noche mb-1.5">Correo electrónico</label>
              <input id="soporte-email" v-model="form.email" type="email" maxlength="255" placeholder="tuemail@ejemplo.com"
                     autocomplete="email" autocapitalize="off" spellcheck="false"
                     :class="inputClase('email')" class="h-12" @input="delete errores.email" />
              <p v-if="errores.email" class="mt-1 text-xs font-semibold text-red-600">{{ errores.email }}</p>
            </div>
          </div>

          <fieldset>
            <legend class="block text-sm font-semibold text-azul-noche mb-1.5">Tipo de consulta</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label v-for="t in TIPOS" :key="t.value"
                     class="flex items-center gap-3 min-h-11 rounded-full border px-3 cursor-pointer text-sm transition"
                     :class="form.tipo === t.value ? 'border-primary-500 bg-primary-50 text-azul-noche font-semibold' : 'border-gray-200 text-gray-700 hover:border-gray-300'">
                <input v-model="form.tipo" type="radio" name="soporte-tipo" :value="t.value" class="w-4 h-4 text-primary-600 focus:ring-primary-500/30" />
                {{ t.label }}
              </label>
            </div>
            <p v-if="errores.tipo" class="mt-1 text-xs font-semibold text-red-600">{{ errores.tipo }}</p>
          </fieldset>

          <div>
            <label for="soporte-mensaje" class="block text-sm font-semibold text-azul-noche mb-1.5">Mensaje</label>
            <textarea id="soporte-mensaje" v-model="form.mensaje" rows="5" maxlength="3000"
                      placeholder="Cuéntanos qué ocurre: qué intentabas hacer y qué mensaje te aparece."
                      aria-describedby="soporte-mensaje-ayuda"
                      :class="inputClase('mensaje')" class="py-3 resize-y" @input="delete errores.mensaje" />
            <div class="mt-1 flex justify-between gap-3 text-xs">
              <span v-if="errores.mensaje" id="soporte-mensaje-ayuda" role="alert" class="min-w-0 font-semibold text-red-600">{{ errores.mensaje }}</span>
              <span v-else id="soporte-mensaje-ayuda" class="min-w-0 text-gray-500">Mínimo 10 caracteres</span>
              <span class="shrink-0" :class="form.mensaje.trim().length < 10 ? 'text-gray-400' : 'text-primary-600'">{{ form.mensaje.length }}/3000</span>
            </div>
          </div>

          <!-- Honeypot: invisible para personas, los bots lo rellenan -->
          <div class="absolute -left-[9999px] w-px h-px overflow-hidden" aria-hidden="true">
            <label for="soporte-website">No rellenes este campo</label>
            <input id="soporte-website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
          </div>

          <p class="text-xs text-gray-500">
            Usaremos tus datos únicamente para responder a esta consulta. No incluyas tu contraseña en el mensaje.
          </p>

          <p v-if="errorGeneral" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ errorGeneral }}</p>

          <div class="flex flex-col-reverse sm:flex-row gap-3">
            <button type="button" class="w-full sm:w-auto sm:flex-1 h-12 rounded-full border-2 border-gray-200 text-gray-700 font-bold hover:border-gray-300" @click="cerrar">
              Cancelar
            </button>
            <button type="submit" :disabled="enviando"
                    class="w-full sm:w-auto sm:flex-[2] h-12 rounded-full bg-primary-600 text-white font-bold shadow-[0_8px_24px_rgba(48,114,170,0.25)] hover:bg-primary-700 disabled:opacity-60 disabled:cursor-not-allowed">
              {{ enviando ? 'Enviando…' : 'Enviar consulta' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
