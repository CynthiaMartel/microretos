<!-- «¿No da tiempo a hacer la entrevista ahora?» (primer paso de la entrevista): el comercial
     escribe el correo de la empresa en un modal y le llega un enlace para completar ella misma
     la entrevista entera: sus datos, las preguntas y la firma (FormularioEmpresa.vue).
     Sin entrevista todavía (empresa = null), el envío la crea solo con ese correo.
     El enlace en claro solo se ve justo al enviarlo (de DuaLab no se puede recuperar). -->
<script setup>
import { ref, computed, watch } from 'vue'
import Modal from '../../src/components/Modal.vue'
import { enviarFormularioCaptada, anularFormularioCaptada, crearFormularioCaptada } from '../../src/services/comercialService.js'

const props = defineProps({
  empresa: { type: Object, default: null },
})
const emit = defineEmits(['actualizada', 'creada'])

const f = computed(() => props.empresa?.formulario)
const estado = computed(() => !f.value ? 'sin_enviar'
  : f.value.respondido_at ? 'respondido'
  : f.value.abierto ? 'abierto'
  : 'cerrado') // caducado o anulado

// Mismo asunto que FormularioEmpresaMail::envelope()
const asunto = computed(() => props.empresa?.nombre_comercial
  ? `Completa la entrevista de ${props.empresa.nombre_comercial} para colaborar con DuaLab`
  : 'Completa la entrevista para colaborar con DuaLab')

const modal    = ref(false)
const email    = ref('')
const enviando = ref(false)
const anulando = ref(false)
const error    = ref('')
const enlace   = ref('')   // tras enviar: el modal pasa a mostrar el enlace
const copiado  = ref(false)
// Entrevista recién creada: se avisa al padre al cerrar el modal (pasa a la ruta de la
// entrevista y eso desmontaría el modal antes de que se vea el enlace)
let creada = null
watch(modal, (abiertoAhora) => {
  if (!abiertoAhora && creada) {
    emit('creada', creada)
    creada = null
  }
})

const fecha = (iso) => iso ? new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'long' }) : ''

function abrir() {
  email.value = props.empresa?.email_general ?? ''
  error.value = ''
  enlace.value = ''
  modal.value = true
}

async function enviar() {
  error.value = ''
  enviando.value = true
  try {
    const correo = email.value.trim()
    const { data } = props.empresa?.id
      ? await enviarFormularioCaptada(props.empresa.id, correo)
      : await crearFormularioCaptada(correo)
    enlace.value = data.enlace
    if (props.empresa?.id) emit('actualizada', data.data)
    else creada = data.data
  } catch (err) {
    error.value = err.response?.data?.errors?.email?.[0]
      || err.response?.data?.error
      || (err.response?.status === 429 ? 'Has enviado varios correos seguidos: espera un minuto.' : 'No se ha podido enviar. Comprueba la conexión.')
  } finally {
    enviando.value = false
  }
}

async function anular() {
  error.value = ''
  anulando.value = true
  try {
    emit('actualizada', (await anularFormularioCaptada(props.empresa.id)).data.data)
  } catch (err) {
    error.value = err.response?.data?.error || 'No se ha podido anular el enlace.'
  } finally {
    anulando.value = false
  }
}

async function copiar() {
  try {
    await navigator.clipboard.writeText(enlace.value)
    copiado.value = true
    setTimeout(() => { copiado.value = false }, 2000)
  } catch {
    error.value = 'No se ha podido copiar: selecciona el enlace y cópialo a mano.'
  }
}
</script>

<template>
  <section class="rounded-2xl border p-4 sm:p-5"
           :class="estado === 'respondido' ? 'border-empresas/30 bg-empresas/5' : 'border-gray-200 bg-white'">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rol/15 text-rol-fuerte" aria-hidden="true">
        <svg v-if="estado === 'respondido'" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
      </span>

      <div class="min-w-0 flex-1 text-sm">
        <template v-if="estado === 'respondido'">
          <p class="font-semibold text-azul-noche">La empresa completó y firmó la entrevista el {{ fecha(f.respondido_at) }}</p>
          <p class="mt-0.5 text-texto-secundario">Lo hizo desde el enlace que le enviaste por correo. DuaLab la validará.</p>
        </template>
        <template v-else-if="estado === 'abierto'">
          <p class="break-words font-semibold text-azul-noche">Entrevista enviada por correo el {{ fecha(f.enviado_at) }}<template v-if="empresa.email_general"> a {{ empresa.email_general }}</template></p>
          <p class="mt-0.5 text-texto-secundario">Esperando a que la empresa la complete. El enlace vale hasta el {{ fecha(f.expira_at) }}; te avisaremos por correo cuando la firme.</p>
        </template>
        <template v-else-if="estado === 'cerrado'">
          <p class="font-semibold text-azul-noche">El enlace enviado ya no vale</p>
          <p class="mt-0.5 text-texto-secundario">Caducó o lo anulaste. Si lo necesita, envíale uno nuevo.</p>
        </template>
        <template v-else>
          <p class="font-semibold text-azul-noche">¿No da tiempo a hacer la entrevista ahora?</p>
          <p class="mt-0.5 text-texto-secundario">Envíasela por correo: la empresa rellenará ella misma sus datos, las preguntas y la firma cuando pueda.</p>
        </template>
        <p v-if="error && !modal" role="alert" class="mt-2 break-words font-medium text-red-700">{{ error }}</p>
      </div>

      <div v-if="estado !== 'respondido'" class="flex shrink-0 flex-wrap gap-2">
        <button v-if="estado === 'abierto'" type="button" class="accion-secundaria" :disabled="anulando" @click="anular">{{ anulando ? 'Anulando…' : 'Anular enlace' }}</button>
        <button type="button" class="accion-secundaria" @click="abrir">{{ estado === 'sin_enviar' ? 'Enviar por correo' : 'Enviar de nuevo' }}</button>
      </div>
    </div>

    <Modal :visible="modal" max-width="max-w-lg" card-class="overflow-y-auto p-6 sm:p-8" @cerrar="modal = false">
      <!-- Enviado: el enlace, por si se quiere mandar también por otra vía -->
      <div v-if="enlace" class="flex flex-col gap-4">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-empresas/15 text-empresas-dark" aria-hidden="true">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        </span>
        <div class="pr-10">
          <h2 class="font-heading text-xl font-bold text-azul-noche">Entrevista enviada</h2>
          <p class="mt-1 break-words text-sm text-texto-secundario">Le ha llegado a {{ email.trim() }}. Si la necesita por otra vía (WhatsApp…), copia el enlace: es personal y vale 14 días.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
          <input :value="enlace" readonly aria-label="Enlace de la entrevista" class="input-style min-w-0 flex-1 text-xs" @focus="$event.target.select()" />
          <button type="button" class="accion-secundaria w-fit shrink-0" @click="copiar">{{ copiado ? 'Copiado' : 'Copiar enlace' }}</button>
        </div>
        <p v-if="error" role="alert" class="break-words text-sm font-medium text-red-700">{{ error }}</p>
        <div class="fila-pasos">
          <span />
          <button type="button" class="btn-paso" @click="modal = false">Hecho</button>
        </div>
      </div>

      <form v-else class="flex flex-col gap-4" @submit.prevent="enviar">
        <div class="pr-10">
          <h2 class="font-heading text-xl font-bold text-azul-noche">Enviar la entrevista por correo</h2>
          <p class="mt-1 text-sm text-texto-secundario">La empresa recibirá un enlace personal para completar ella misma la entrevista entera.</p>
        </div>

        <div>
          <label for="ef-email" class="label-style">Correo de la empresa</label>
          <input id="ef-email" v-model="email" type="email" required maxlength="255" autocomplete="email" inputmode="email"
                 placeholder="contacto@empresa.com" class="input-style" />
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
          <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario">Asunto</p>
          <p class="mt-1 break-words font-semibold text-azul-noche">{{ asunto }}</p>
          <p class="mt-3 text-xs font-bold uppercase tracking-wider text-texto-secundario">Qué tendrá que hacer</p>
          <ol class="mt-1 space-y-1 text-gray-600">
            <li>1. Los datos de su empresa y con qué alumnado le gustaría trabajar.</li>
            <li>2. Las siete preguntas de la entrevista.</li>
            <li>3. La firma de las condiciones y la protección de datos.</li>
          </ol>
          <p class="mt-3 text-xs text-texto-secundario">Lo envía info@dualab.es en tu nombre; si responde al correo, te llega a ti. El enlace vale 14 días.</p>
        </div>

        <p v-if="error" role="alert" class="aviso-error"><span class="min-w-0 break-words">{{ error }}</span></p>

        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="modal = false">Cancelar</button>
          <button type="submit" class="btn-paso" :disabled="enviando || !email.trim()">{{ enviando ? 'Enviando…' : 'Enviar' }}</button>
        </div>
      </form>
    </Modal>
  </section>
</template>
