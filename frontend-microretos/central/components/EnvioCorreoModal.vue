<script setup>
/**
 * Historial → «Ver correo»: qué se envió desde info@ (para, CC, asunto, texto y adjuntos).
 * Se pide a la web en el momento (el historial no guarda direcciones ni textos); los envíos se
 * conservan 4 meses, después la web responde 404.
 *
 * Props: envioId (null = cerrada) · Emits: @cerrar
 */
import { ref, watch, onBeforeUnmount } from 'vue'
import { getEnvioHistorial } from '../../src/services/historialCentralService.js'
import { fechaHora } from '../primerContacto.js'

const props = defineProps({ envioId: { type: Number, default: null } })
const emit = defineEmits(['cerrar'])

const envio = ref(null)
const cargando = ref(false)
const error = ref('')
const cerrarBtn = ref(null)

const ESTADOS = { pendiente: 'Pendiente de salir', enviado: 'Enviado', error: 'No se pudo enviar' }
const TIPOS = { nuevo: 'Correo nuevo', responder: 'Respuesta', reenviar: 'Reenvío' }
const kb = (bytes) => `${Math.max(1, Math.round((bytes || 0) / 1024))} KB`

watch(() => props.envioId, async (id) => {
  envio.value = null
  error.value = ''
  if (!id) return
  cargando.value = true
  setTimeout(() => cerrarBtn.value?.focus(), 50)
  try {
    envio.value = (await getEnvioHistorial(id)).data
  } catch (e) {
    error.value = e.response?.data?.error || 'No se ha podido cargar el correo.'
  } finally {
    cargando.value = false
  }
})

const alTeclear = (e) => { if (e.key === 'Escape' && props.envioId) emit('cerrar') }
window.addEventListener('keydown', alTeclear)
onBeforeUnmount(() => window.removeEventListener('keydown', alTeclear))
</script>

<template>
  <Teleport to="body">
    <div v-if="envioId" class="fixed inset-0 z-[70] flex items-end justify-center bg-azul-noche/50 p-0 sm:items-center sm:p-4" @mousedown.self="emit('cerrar')">
      <div role="dialog" aria-modal="true" aria-labelledby="envio-titulo"
           class="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:w-[min(44rem,calc(100vw-2rem))] sm:rounded-[2rem]">
        <div class="flex items-start gap-3 border-b border-borde px-5 py-4 sm:px-6">
          <div class="min-w-0 flex-1">
            <h2 id="envio-titulo" class="break-words font-heading text-lg font-bold text-azul-noche">{{ envio?.asunto || 'Correo enviado' }}</h2>
            <p v-if="envio" class="mt-0.5 text-sm text-texto-secundario">
              {{ TIPOS[envio.tipo] ?? 'Correo' }} desde info@ · {{ fechaHora(envio.enviado_en || envio.creado_en) }}
            </p>
          </div>
          <button ref="cerrarBtn" type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100" aria-label="Cerrar" @click="emit('cerrar')">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6">
          <p v-if="cargando" class="py-6 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
          <p v-else-if="error" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">{{ error }}</p>
          <template v-else-if="envio">
            <dl class="space-y-2 text-sm">
              <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                <dt class="w-16 shrink-0 font-semibold text-texto-secundario">Para</dt>
                <dd class="min-w-0 break-words">{{ envio.para.join(', ') }}</dd>
              </div>
              <div v-if="envio.cc?.length" class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                <dt class="w-16 shrink-0 font-semibold text-texto-secundario">CC</dt>
                <dd class="min-w-0 break-words">{{ envio.cc.join(', ') }}</dd>
              </div>
              <div v-if="envio.cco?.length" class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                <dt class="w-16 shrink-0 font-semibold text-texto-secundario">CCO</dt>
                <dd class="min-w-0 break-words">{{ envio.cco.join(', ') }}</dd>
              </div>
              <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                <dt class="w-16 shrink-0 font-semibold text-texto-secundario">Estado</dt>
                <dd><span class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                          :class="envio.estado === 'error' ? 'bg-red-50 text-red-700' : envio.estado === 'enviado' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-texto-secundario'">{{ ESTADOS[envio.estado] ?? envio.estado }}</span></dd>
              </div>
            </dl>
            <!-- Texto plano: Vue lo escapa (sin v-html) -->
            <p class="whitespace-pre-line break-words rounded-2xl bg-gray-50 p-4 text-sm text-azul-noche">{{ envio.texto }}</p>
            <div v-if="envio.adjuntos?.length">
              <p class="mb-1 text-xs font-bold uppercase tracking-wide text-texto-secundario">Adjuntos</p>
              <ul class="flex flex-wrap gap-2">
                <li v-for="(a, i) in envio.adjuntos" :key="i" class="max-w-full truncate rounded-full border border-borde px-3 py-1 text-xs">{{ a.nombre }} · {{ kb(a.tamano) }}</li>
              </ul>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>
