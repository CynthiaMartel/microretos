<!-- Valoración final de la empresa de un encuentro (P3), en DuaLab Central: DuaLab envía a la empresa,
     por info@, el enlace para ver las soluciones de los grupos y valorar el reto (o lo copia para
     pasarlo por otro canal y lo marca como enviado), y lee la respuesta. Lo usa Empresas → «Enviar
     soluciones a las empresas» (EnvioSolucionesCentral.vue). `valoracion`: ValoracionEmpresaCentralResource.
     Emite `actualizada` con la fila nueva. -->
<script setup>
import { computed, ref } from 'vue'
import { generarEnlaceValoracion, enviarValoracionEmpresa, marcarValoracionEnviada } from '../../src/services/valoracionCentralService.js'
import { PREGUNTAS_VALORACION } from '../../src/services/valoracionEmpresaService.js'
import { urlApp } from '../primerContacto.js'

const props = defineProps({
  valoracion: { type: Object, required: true },
})
const emit = defineEmits(['actualizada'])

const v = computed(() => props.valoracion)
const trabajando = ref(false)
const error = ref('')
const aviso = ref('')
const copiado = ref(false)

// Enviar por correo: por defecto al contacto de la empresa
const email = ref(props.valoracion.empresa_email || '')
const mensaje = ref('')
const errorEmail = ref('')

const enlace = computed(() => v.value.token ? `${urlApp()}/valoracion/${v.value.token}` : '')

async function accion(llamada, texto) {
  trabajando.value = true
  error.value = ''
  aviso.value = ''
  errorEmail.value = ''
  try {
    emit('actualizada', (await llamada()).data.data)
    aviso.value = texto
    return true
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors?.email) errorEmail.value = e.response.data.errors.email[0]
    else error.value = e.response?.data?.message || 'No se pudo completar la acción.'
    return false
  } finally {
    trabajando.value = false
  }
}
const generar = () => accion(() => generarEnlaceValoracion(v.value.encuentro_id), 'Enlace generado.')
const marcarEnviada = () => accion(() => marcarValoracionEnviada(v.value.encuentro_id), 'Marcado como enviado.')
async function enviar() {
  if (!confirm(`Se enviará a ${email.value.trim()} el correo con el enlace para valorar las soluciones. ¿Enviar?`)) return
  if (await accion(() => enviarValoracionEmpresa(v.value.encuentro_id, { email: email.value.trim(), mensaje: mensaje.value.trim() || null }),
    'Correo en camino desde info@: puede tardar unos minutos en llegar.')) mensaje.value = ''
}

async function copiar() {
  try {
    await navigator.clipboard.writeText(enlace.value)
    copiado.value = true
    setTimeout(() => { copiado.value = false }, 2500)
  } catch {
    error.value = 'No se pudo copiar el enlace. Selecciónalo y cópialo a mano.'
  }
}

const fecha = (iso) => iso ? new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
</script>

<template>
  <div aria-live="polite">
    <!-- Respondida -->
    <div v-if="v.respondido_en" class="space-y-2">
      <p class="text-sm font-bold text-empresas-dark">Valorada el {{ fecha(v.respondido_en) }} · {{ v.valoracion_global }}/5</p>
      <dl class="space-y-1.5 text-sm">
        <div v-for="p in PREGUNTAS_VALORACION" :key="p.key" class="flex flex-col gap-0.5 sm:flex-row sm:gap-2">
          <dt class="min-w-0 text-texto-secundario sm:flex-1">{{ p.label }}</dt>
          <dd class="shrink-0 font-semibold text-azul-noche">{{ v.respuestas?.[p.key] || '—' }}</dd>
        </div>
      </dl>
      <p v-if="v.comentarios" class="whitespace-pre-line break-words rounded-xl bg-white px-3 py-2 text-sm italic text-azul-noche">«{{ v.comentarios }}»</p>
    </div>

    <template v-else>
      <p v-if="!v.grupos_con_entrega" class="mb-3 text-sm text-texto-secundario">
        Aún no ha entregado ningún grupo (F3): si lo envías ahora, la empresa no verá soluciones.
      </p>

      <!-- Enviar por correo desde info@ (si no hay enlace vigente, se genera al enviar) -->
      <form class="space-y-2" novalidate @submit.prevent="enviar">
        <label :for="`val-email-${v.encuentro_id}`" class="field-label">Correo de la empresa</label>
        <input :id="`val-email-${v.encuentro_id}`" v-model="email" type="email" maxlength="255" class="field-input" :aria-invalid="!!errorEmail"
               placeholder="contacto@empresa.es" />
        <p v-if="!v.empresa_email" class="text-xs text-texto-secundario">La empresa no tiene correo de contacto guardado: escríbelo.</p>
        <p v-if="errorEmail" class="text-xs text-red-600">{{ errorEmail }}</p>
        <label :for="`val-mensaje-${v.encuentro_id}`" class="field-label">Unas líneas para la empresa (opcional)</label>
        <textarea :id="`val-mensaje-${v.encuentro_id}`" v-model="mensaje" maxlength="1000" rows="2" class="field-input"></textarea>
        <div class="flex flex-wrap items-center justify-end gap-2">
          <button type="submit" class="accion-principal" :disabled="trabajando || !email.trim()">
            {{ trabajando ? 'Enviando…' : v.enviado_en ? 'Enviar de nuevo' : 'Enviar a la empresa' }}
          </button>
        </div>
      </form>

      <!-- Enlace: copiarlo para otro canal y marcarlo como enviado -->
      <div class="mt-4 border-t border-borde pt-4">
        <template v-if="enlace">
          <label :for="`val-enlace-${v.encuentro_id}`" class="block text-xs font-semibold text-azul-noche">O copia el enlace para pasárselo por otro canal</label>
          <div class="mt-1 flex flex-col gap-2 sm:flex-row">
            <input :id="`val-enlace-${v.encuentro_id}`" type="text" readonly :value="enlace" @focus="$event.target.select()"
                   class="field-input min-w-0 flex-1 text-texto-secundario" />
            <button type="button" class="accion-secundaria self-end sm:self-auto" @click="copiar">{{ copiado ? 'Copiado' : 'Copiar enlace' }}</button>
          </div>
          <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-texto-secundario">
            <span>Caduca el {{ fecha(v.token_expira_en) }}<template v-if="v.enviado_en"> · Enviado el {{ fecha(v.enviado_en) }}</template></span>
            <button v-if="!v.enviado_en" type="button" class="accion-secundaria" :disabled="trabajando" @click="marcarEnviada">Marcar como enviado</button>
          </div>
        </template>
        <div v-else class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <p class="min-w-0 text-xs text-texto-secundario">{{ v.caducado ? 'El enlace caducó sin respuesta.' : 'Aún no hay enlace.' }} Si prefieres pasárselo por otro canal, genéralo.</p>
          <button type="button" class="accion-secundaria self-end sm:self-auto" :disabled="trabajando" @click="generar">
            {{ v.caducado ? 'Generar enlace nuevo' : 'Generar enlace' }}
          </button>
        </div>
      </div>
    </template>
    <p v-if="aviso" class="mt-2 text-sm font-semibold text-texto-secundario" role="status">{{ aviso }}</p>
    <p v-if="error" class="mt-2 text-xs text-red-600" role="alert">{{ error }}</p>
  </div>
</template>
