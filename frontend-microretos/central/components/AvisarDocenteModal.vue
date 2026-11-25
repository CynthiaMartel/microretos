<script setup>
/**
 * Ficha de una solicitud de retos → «Avisar al docente»: pide confirmación antes de enviar el correo
 * «Tus retos están listos» (por si se pulsa sin querer) y después dice si se ha enviado o ha fallado.
 *
 * Props: estado (null = cerrada · 'confirmar' · 'enviando' · 'ok' · 'error'), docente, email, retos
 * (títulos), error · Emits: @confirmar, @cerrar
 */
import { ref, watch, onBeforeUnmount, nextTick } from 'vue'

const props = defineProps({
  estado:  { type: String, default: null },
  docente: { type: String, default: '' },
  email:   { type: String, default: '' },
  retos:   { type: Array, default: () => [] },
  error:   { type: String, default: '' },
})
const emit = defineEmits(['confirmar', 'cerrar'])

const botonPrincipal = ref(null)
watch(() => props.estado, async (e) => {
  if (!e || e === 'enviando') return
  await nextTick()
  botonPrincipal.value?.focus()
})

const cerrar = () => { if (props.estado !== 'enviando') emit('cerrar') }
const alTeclear = (e) => { if (e.key === 'Escape' && props.estado) cerrar() }
window.addEventListener('keydown', alTeclear)
onBeforeUnmount(() => window.removeEventListener('keydown', alTeclear))
</script>

<template>
  <Teleport to="body">
    <div v-if="estado" class="fixed inset-0 z-[70] flex items-end justify-center bg-azul-noche/50 p-0 sm:items-center sm:p-4" @mousedown.self="cerrar">
      <div role="dialog" aria-modal="true" aria-labelledby="avisar-titulo"
           class="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:w-[min(32rem,calc(100vw-2rem))] sm:rounded-[2rem]">
        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-6 sm:px-7">
          <!-- Confirmar -->
          <template v-if="estado === 'confirmar' || estado === 'enviando'">
            <h2 id="avisar-titulo" class="font-heading text-lg font-bold text-azul-noche">¿Seguro que quieres avisar al docente?</h2>
            <p class="mt-2 break-words text-sm text-texto-secundario">
              Se enviará a <strong class="text-azul-noche">{{ docente }}</strong> ({{ email }}) el correo «Tus retos están listos»
              con {{ retos.length === 1 ? 'este reto' : `estos ${retos.length} retos` }}, para leerlos sin cuenta:
            </p>
            <ul class="mt-3 space-y-1 rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm">
              <li v-for="(t, i) in retos" :key="i" class="break-words">· {{ t || 'Reto sin título' }}</li>
            </ul>
            <p class="mt-3 text-xs text-texto-secundario">El correo puede tardar unos minutos en llegarle. El enlace que tuviera antes deja de funcionar.</p>
            <div class="mt-6 flex flex-wrap justify-end gap-2">
              <button type="button" class="accion-secundaria" :disabled="estado === 'enviando'" @click="cerrar">Cancelar</button>
              <button ref="botonPrincipal" type="button" class="accion-principal" :disabled="estado === 'enviando'" @click="emit('confirmar')">
                {{ estado === 'enviando' ? 'Enviando…' : 'Sí, avisar' }}
              </button>
            </div>
          </template>

          <!-- Resultado -->
          <template v-else>
            <div class="flex flex-col items-center text-center" :role="estado === 'error' ? 'alert' : 'status'">
              <span class="flex h-12 w-12 items-center justify-center rounded-full"
                    :class="estado === 'ok' ? 'bg-empresas/15 text-empresas-dark' : 'bg-red-50 text-red-700'" aria-hidden="true">
                <svg v-if="estado === 'ok'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2.5" d="M12 8v5m0 3.5v.01" /></svg>
              </span>
              <h2 id="avisar-titulo" class="mt-3 font-heading text-lg font-bold text-azul-noche">
                {{ estado === 'ok' ? 'Docente avisado' : 'No se ha podido avisar al docente' }}
              </h2>
              <p class="mt-2 break-words text-sm text-texto-secundario">
                <template v-if="estado === 'ok'">El correo a {{ email }} ya está saliendo desde info@. <strong class="text-azul-noche">Puede tardar unos minutos</strong> en llegarle y en aparecer en «Correos con el docente».</template>
                <template v-else>{{ error || 'Inténtalo de nuevo en un momento.' }} No se ha enviado nada.</template>
              </p>
              <button ref="botonPrincipal" type="button" class="accion-principal mt-6" @click="cerrar">Entendido</button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>
