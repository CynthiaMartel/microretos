<!-- Redactar en Central («Primer contacto»): correo nuevo, responder, responder a todos, reenviar o
     responder a un formulario de la web. Sale desde info@ por la web (bandeja de salida y cola); el
     backend de la app y la web validan lo mismo (destinatarios, adjuntos en lista blanca, tamaños). -->
<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { enviarCorreo } from '../../src/services/primerContactoService.js'
import { fechaHora } from '../primerContacto.js'

const props = defineProps({
  abierto: { type: Boolean, default: false },
  // nuevo | responder | responder_todos | reenviar
  modo: { type: String, default: 'nuevo' },
  original: { type: Object, default: null },
  // Respuesta a un formulario de la web: { id, email, nombre, asunto }. Sin id: correo nuevo con
  // destinatario y asunto ya puestos (p. ej. escribir al docente de una solicitud de retos)
  contacto: { type: Object, default: null },
  remitente: { type: String, default: 'info@dualab.es' },
})
const emit = defineEmits(['cerrar', 'enviado'])

const TITULOS = { formulario: 'Responder al formulario', nuevo: 'Nuevo correo', responder: 'Responder', responder_todos: 'Responder a todos', reenviar: 'Reenviar' }
const EXTENSIONES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip']
const ACEPTA = EXTENSIONES.map(e => `.${e}`).join(',')
const MAX_MB = 10
const MAX_TOTAL_MB = 20

const id = `redactar-${Math.random().toString(36).slice(2, 9)}`
const campoTexto = ref(null)
const form = reactive({ para: '', cc: '', cco: '', asunto: '', texto: '', adjuntos: [], reenviar_adjuntos: [] })
const inicial = ref('')
const verCopias = ref(false)
const enviando = ref(false)
const errores = ref({})
const errorGeneral = ref('')
const errorAdjuntos = ref('')

const titulo = computed(() => TITULOS[props.contacto?.id ? 'formulario' : props.modo])
const adjuntosOriginal = computed(() => (props.modo === 'reenviar'
  ? (props.original?.adjuntos ?? []).filter(a => a.disponible && !a.en_linea) : []))
// Errores de para.0, cc.3… se muestran en su campo
const errorDe = campo => {
  const e = errores.value[campo] ?? Object.entries(errores.value).find(([k]) => k.startsWith(`${campo}.`))?.[1]
  return Array.isArray(e) ? e[0] : e
}

const lista = texto => texto.split(/[,;\s]+/).map(s => s.trim()).filter(Boolean)
const quien = d => (d.nombre ? `${d.nombre} <${d.email}>` : d.email)
const conPrefijo = (prefijo, asunto) => (new RegExp(`^${prefijo}:`, 'i').test(asunto ?? '') ? asunto : `${prefijo}: ${asunto ?? ''}`.trim())
// Cita recortada: el mensaje entero no puede pasar de 50 000 caracteres
const citable = o => (o.texto_citable ?? o.texto ?? '').slice(0, 20000)
const tamano = b => (b >= 1024 * 1024 ? `${(b / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`)

function rellenar() {
  const o = props.original
  const propio = props.remitente.toLowerCase()
  Object.assign(form, { para: '', cc: '', cco: '', asunto: '', texto: '', adjuntos: [], reenviar_adjuntos: [] })
  errores.value = {}
  errorGeneral.value = ''
  errorAdjuntos.value = ''
  verCopias.value = false

  if (props.contacto) {
    form.para = props.contacto.email ?? ''
    form.asunto = props.contacto.asunto ?? ''
    form.texto = props.contacto.nombre ? `Hola, ${props.contacto.nombre}:\n\n` : ''
  } else if (o && (props.modo === 'responder' || props.modo === 'responder_todos')) {
    // Si el remitente pidió respuesta a otra dirección (Reply-To), se responde ahí
    const destino = o.carpeta === 'enviados' ? o.para?.[0]?.email : (o.responder_a?.[0]?.email ?? o.de_email)
    form.para = destino ?? ''
    if (props.modo === 'responder_todos') {
      const resto = [...(o.para ?? []), ...(o.cc ?? [])].map(d => d.email)
        .filter(e => e && e.toLowerCase() !== propio && e.toLowerCase() !== destino?.toLowerCase())
      form.cc = [...new Set(resto)].join(', ')
      verCopias.value = !!form.cc
    }
    form.asunto = conPrefijo('Re', o.asunto)
    const cita = citable(o).split('\n').map(l => `> ${l}`).join('\n')
    form.texto = `\n\nEl ${fechaHora(o.fecha)}, ${quien({ nombre: o.de_nombre, email: o.de_email })} escribió:\n${cita}`
  } else if (o && props.modo === 'reenviar') {
    form.asunto = conPrefijo('Fwd', o.asunto)
    form.texto = `\n\n---------- Mensaje reenviado ----------\nDe: ${quien({ nombre: o.de_nombre, email: o.de_email })}\n`
      + `Fecha: ${fechaHora(o.fecha)}\nAsunto: ${o.asunto ?? ''}\nPara: ${(o.para ?? []).map(quien).join(', ')}\n\n${citable(o)}`
    form.reenviar_adjuntos = adjuntosOriginal.value.map(a => a.id)
  }
  inicial.value = JSON.stringify(form)
}

watch(() => props.abierto, async (abierto) => {
  if (!abierto) return
  rellenar()
  await nextTick()
  if (!campoTexto.value) return
  // Al responder a un formulario, el cursor tras el saludo; al responder un correo, encima de la cita
  if (props.contacto) {
    campoTexto.value.focus()
    campoTexto.value.setSelectionRange(form.texto.length, form.texto.length)
  } else if (props.modo !== 'nuevo') {
    campoTexto.value.focus()
    campoTexto.value.setSelectionRange(0, 0)
    campoTexto.value.scrollTop = 0
  }
}, { immediate: true })

function anadirArchivos(e) {
  errorAdjuntos.value = ''
  for (const f of e.target.files) {
    const ext = f.name.split('.').pop()?.toLowerCase()
    if (!EXTENSIONES.includes(ext)) { errorAdjuntos.value = `«${f.name}» no se puede adjuntar (tipo de archivo no permitido).`; continue }
    if (f.size > MAX_MB * 1024 * 1024) { errorAdjuntos.value = `«${f.name}» ocupa más de ${MAX_MB} MB.`; continue }
    if (form.adjuntos.length >= 10) { errorAdjuntos.value = 'Como mucho 10 archivos por correo.'; break }
    form.adjuntos.push(f)
  }
  const total = form.adjuntos.reduce((s, f) => s + f.size, 0)
  if (total > MAX_TOTAL_MB * 1024 * 1024) errorAdjuntos.value = `Los adjuntos suman más de ${MAX_TOTAL_MB} MB: quita alguno.`
  e.target.value = ''
}

function intentarCerrar() {
  if (enviando.value) return
  if (JSON.stringify(form) !== inicial.value && !window.confirm('¿Descartar este correo? Se perderá lo que has escrito.')) return
  emit('cerrar')
}

async function enviar() {
  errores.value = {}
  errorGeneral.value = ''
  if (errorAdjuntos.value) return
  const datos = new FormData()
  datos.append('tipo', props.contacto ? 'nuevo' : (props.modo === 'responder_todos' ? 'responder' : props.modo))
  if (props.original && props.modo !== 'nuevo' && !props.contacto) datos.append('correo_id', props.original.id)
  if (props.contacto?.id) datos.append('contacto_id', props.contacto.id)
  for (const campo of ['para', 'cc', 'cco']) lista(form[campo]).forEach(e => datos.append(`${campo}[]`, e))
  datos.append('asunto', form.asunto)
  datos.append('texto', form.texto)
  form.adjuntos.forEach(f => datos.append('adjuntos[]', f))
  form.reenviar_adjuntos.forEach(a => datos.append('reenviar_adjuntos[]', a))

  enviando.value = true
  try {
    await enviarCorreo(datos)
    emit('enviado')
  } catch (e) {
    if (e.response?.status === 422) {
      errores.value = e.response.data?.errors ?? {}
      const deAdjuntos = Object.entries(errores.value).find(([k]) => k.startsWith('adjuntos') || k.startsWith('reenviar_adjuntos'))
      if (deAdjuntos) errorAdjuntos.value = [deAdjuntos[1]].flat()[0]
      if (!Object.keys(errores.value).length) errorGeneral.value = e.response.data?.message || 'Revisa los datos del correo.'
    } else {
      errorGeneral.value = e.response?.data?.error || 'No se ha podido enviar. Inténtalo de nuevo.'
    }
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="abierto" class="fixed inset-0 z-[60] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4"
         @keydown.esc="intentarCerrar" @click.self="intentarCerrar">
      <section role="dialog" aria-modal="true" :aria-labelledby="`${id}-titulo`"
               class="flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:rounded-[2rem]">
        <header class="flex items-center gap-3 border-b border-borde px-4 py-3 sm:px-6 sm:py-4">
          <h2 :id="`${id}-titulo`" class="min-w-0 flex-1 truncate font-heading text-lg font-bold">{{ titulo }}</h2>
          <button type="button" class="accion-icono" aria-label="Cerrar" @click="intentarCerrar">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </header>

        <form :id="`${id}-form`" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4 sm:px-6" novalidate @submit.prevent="enviar">
          <p class="text-xs text-texto-secundario">Sale desde <strong class="font-semibold text-azul-noche">{{ remitente }}</strong>. Separa varias direcciones con comas.</p>

          <div>
            <div class="flex items-end justify-between gap-2">
              <label :for="`${id}-para`" class="field-label">Para</label>
              <button v-if="!verCopias" type="button" class="mb-1 text-xs font-bold text-rol-fuerte hover:underline" @click="verCopias = true">CC / CCO</button>
            </div>
            <input :id="`${id}-para`" v-model="form.para" type="text" inputmode="email" autocomplete="off" class="field-input" :aria-invalid="!!errorDe('para')" />
            <p v-if="errorDe('para')" class="mt-1 text-xs font-semibold text-red-600">{{ errorDe('para') }}</p>
          </div>
          <div v-if="verCopias" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label :for="`${id}-cc`" class="field-label">CC</label>
              <input :id="`${id}-cc`" v-model="form.cc" type="text" inputmode="email" autocomplete="off" class="field-input" />
              <p v-if="errorDe('cc')" class="mt-1 text-xs font-semibold text-red-600">{{ errorDe('cc') }}</p>
            </div>
            <div>
              <label :for="`${id}-cco`" class="field-label">CCO</label>
              <input :id="`${id}-cco`" v-model="form.cco" type="text" inputmode="email" autocomplete="off" class="field-input" />
              <p v-if="errorDe('cco')" class="mt-1 text-xs font-semibold text-red-600">{{ errorDe('cco') }}</p>
            </div>
          </div>
          <div>
            <label :for="`${id}-asunto`" class="field-label">Asunto</label>
            <input :id="`${id}-asunto`" v-model="form.asunto" type="text" maxlength="255" class="field-input" :aria-invalid="!!errorDe('asunto')" />
            <p v-if="errorDe('asunto')" class="mt-1 text-xs font-semibold text-red-600">{{ errorDe('asunto') }}</p>
          </div>
          <div>
            <label :for="`${id}-texto`" class="field-label">Mensaje</label>
            <textarea :id="`${id}-texto`" ref="campoTexto" v-model="form.texto" rows="10" maxlength="50000" class="field-input min-h-[12rem] resize-y" :aria-invalid="!!errorDe('texto')" />
            <p v-if="errorDe('texto')" class="mt-1 text-xs font-semibold text-red-600">{{ errorDe('texto') }}</p>
          </div>

          <!-- Adjuntos del original (reenviar) y nuevos -->
          <div v-if="adjuntosOriginal.length" class="space-y-1">
            <p class="field-label">Adjuntos del correo original</p>
            <label v-for="a in adjuntosOriginal" :key="a.id" class="flex min-h-9 items-center gap-2 text-sm">
              <input v-model="form.reenviar_adjuntos" type="checkbox" :value="a.id" class="h-4 w-4 rounded" />
              <span class="min-w-0 truncate">{{ a.nombre }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ tamano(a.tamano) }}</span>
            </label>
          </div>
          <div>
            <ul v-if="form.adjuntos.length" class="mb-2 space-y-1">
              <li v-for="(f, i) in form.adjuntos" :key="i" class="flex items-center gap-2 rounded-xl bg-[#F8FAFC] px-3 py-1.5 text-sm">
                <span class="min-w-0 flex-1 truncate">{{ f.name }}</span>
                <span class="shrink-0 text-xs text-texto-secundario">{{ tamano(f.size) }}</span>
                <button type="button" class="accion-icono !h-8 !w-8" :aria-label="`Quitar ${f.name}`" @click="form.adjuntos.splice(i, 1); errorAdjuntos = ''">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </li>
            </ul>
            <label class="accion-secundaria cursor-pointer">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.17 7l-6.59 6.59a2 2 0 102.83 2.83l6.41-6.59a4 4 0 00-5.66-5.66l-6.4 6.58a6 6 0 108.48 8.49L20.5 13" /></svg>
              Adjuntar archivos
              <input type="file" multiple :accept="ACEPTA" class="sr-only" @change="anadirArchivos" />
            </label>
            <p class="mt-1 text-xs text-texto-secundario">Hasta 10 archivos, {{ MAX_MB }} MB cada uno y {{ MAX_TOTAL_MB }} MB en total.</p>
            <p v-if="errorAdjuntos" class="mt-1 text-xs font-semibold text-red-600" role="alert">{{ errorAdjuntos }}</p>
          </div>

          <p v-if="errorGeneral" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700" role="alert">{{ errorGeneral }}</p>
        </form>

        <footer class="fila-pasos border-t border-borde px-4 py-3 sm:px-6">
          <button type="button" class="btn-paso-atras" :disabled="enviando" @click="intentarCerrar">Cancelar</button>
          <button type="submit" :form="`${id}-form`" class="btn-paso" :disabled="enviando">
            {{ enviando ? 'Enviando…' : 'Enviar' }}
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
