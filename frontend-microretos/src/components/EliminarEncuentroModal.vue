<script setup>
import { ref, computed, watch } from 'vue'
import api from '../api.js'

const props = defineProps({
  visible:   { type: Boolean, default: false },
  encuentro: { type: Object,  default: null  },
})
const emit = defineEmits(['encuentro-eliminado', 'cerrar'])

const fase          = ref(1)
const confirmTexto  = ref('')
const eliminando    = ref(false)
const error         = ref('')

const nombreConfirm = computed(() => props.encuentro?.microreto_titulo || `Encuentro #${props.encuentro?.id}`)
const textoValido   = computed(() => confirmTexto.value === nombreConfirm.value)

watch(() => props.visible, (v) => {
  if (v) {
    fase.value         = 1
    confirmTexto.value = ''
    eliminando.value   = false
    error.value        = ''
  }
})

function avanzarFase2() {
  fase.value = 2
  setTimeout(() => document.getElementById('esm-confirm-input')?.focus(), 150)
}

async function confirmarEliminacion() {
  if (!textoValido.value || eliminando.value) return
  eliminando.value = true
  error.value = ''
  try {
    await api.delete(`/encuentros/${props.encuentro.id}`)
    emit('encuentro-eliminado', { id: props.encuentro.id, titulo: props.encuentro.microreto_titulo })
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'El encuentro no se encontró. Es posible que ya haya sido eliminado.'
    } else if (e.response?.status === 401) {
      error.value = 'Tu sesión ha expirado. Vuelve a iniciar sesión e inténtalo de nuevo.'
    } else {
      error.value = 'Error al eliminar el encuentro. Inténtalo de nuevo.'
    }
  } finally {
    eliminando.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="esm-overlay">
      <div v-if="visible" class="esm-overlay" @click.self="$emit('cerrar')">
        <Transition name="esm-card">
          <div v-if="visible" class="esm-card">

            <!-- Cabecera -->
            <div class="esm-header">
              <div class="esm-icon-box">
                <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="esm-title">Eliminar encuentro</h2>
                <p class="esm-sub">El encuentro se moverá a la <span class="font-black text-amber-600">papelera</span> y podrá restaurarse desde allí</p>
              </div>
            </div>

            <!-- Identificación del encuentro -->
            <div class="mt-5 px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-0.5">Encuentro a eliminar</p>
              <p class="font-black text-[#1F2937] text-sm leading-snug">{{ encuentro?.microreto_titulo || '(sin título)' }}</p>
              <div class="flex flex-wrap gap-1.5 mt-2">
                <span v-if="encuentro?.fecha"
                  class="text-[10px] font-bold bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">
                  {{ encuentro.fecha }}
                </span>
                <span v-if="encuentro?.centro_educativo"
                  class="text-[10px] font-bold bg-[#00A859]/10 text-[#00A859] px-2 py-0.5 rounded-full">
                  {{ encuentro.centro_educativo }}
                </span>
                <span v-if="encuentro?.ciclo_formativo"
                  class="text-[10px] font-bold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">
                  {{ encuentro.ciclo_formativo }}
                </span>
              </div>
            </div>

            <!-- ═══ FASE 1 ═══ -->
            <Transition name="esm-fase" mode="out-in">
              <div v-if="fase === 1" key="f1" class="mt-5 space-y-3">

                <p class="text-sm font-semibold text-gray-600">Al eliminar este encuentro ocurrirá lo siguiente:</p>

                <!-- Movido a papelera -->
                <div class="esm-impact-row esm-impact-warn">
                  <div class="esm-impact-icon bg-amber-100">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-amber-800">El encuentro se moverá a la papelera</p>
                    <p class="text-xs text-amber-600 mt-0.5">Podrás recuperarla desde la sección Papelera si lo necesitas.</p>
                  </div>
                </div>

                <!-- Proyectos vinculados se desvinculan -->
                <div class="esm-impact-row esm-impact-danger">
                  <div class="esm-impact-icon bg-red-100">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-red-700">Dejará de aparecer en el historial de encuentros</p>
                    <p class="text-xs text-red-500 mt-0.5">El encuentro no será visible mientras esté en la papelera.</p>
                  </div>
                </div>

                <!-- Proyectos no afectados -->
                <div class="esm-impact-row esm-impact-ok">
                  <div class="esm-impact-icon bg-blue-50">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-blue-700">Los proyectos StartUp Day vinculados no se eliminarán</p>
                    <p class="text-xs text-blue-500 mt-0.5">Los proyectos creados a partir de este encuentro permanecen en la biblioteca.</p>
                  </div>
                </div>

                <div class="esm-actions mt-2">
                  <button type="button" @click="$emit('cerrar')" class="esm-btn-ghost flex-1">Cancelar</button>
                  <button type="button" @click="avanzarFase2" class="esm-btn-danger-outline flex-[2]">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Entiendo, quiero eliminarlo
                  </button>
                </div>
              </div>

              <!-- ═══ FASE 2 ═══ -->
              <div v-else key="f2" class="mt-5 space-y-4">

                <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                  <p class="text-sm font-black text-red-700 mb-1 flex items-center gap-1.5">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Confirmación final requerida
                  </p>
                  <p class="text-xs text-red-600">
                    Escribe el título exacto del reto para confirmar que no es un error accidental.
                  </p>
                </div>

                <div>
                  <label class="esm-label">
                    Escribe <span class="font-black text-[#1F2937] normal-case tracking-normal">{{ nombreConfirm }}</span> para confirmar
                  </label>
                  <input
                    id="esm-confirm-input"
                    v-model="confirmTexto"
                    class="esm-input"
                    :class="confirmTexto && !textoValido ? 'esm-input-err' : confirmTexto && textoValido ? 'esm-input-ok' : ''"
                    placeholder="Escribe el título exacto..."
                    autocomplete="off"
                    @keydown.enter.prevent="textoValido && !eliminando && confirmarEliminacion()"
                  />
                  <p v-if="confirmTexto && !textoValido" class="esm-hint-err">
                    El título no coincide. Comprueba mayúsculas, espacios y caracteres especiales.
                  </p>
                  <p v-if="confirmTexto && textoValido" class="esm-hint-ok">
                    Título confirmado. Ya puedes mover el encuentro a la papelera.
                  </p>
                </div>

                <Transition name="esm-fade">
                  <div v-if="error" class="esm-alert-error">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ error }}</span>
                  </div>
                </Transition>

                <div class="esm-actions">
                  <button type="button" @click="fase = 1" class="esm-btn-ghost flex-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver
                  </button>
                  <button
                    type="button"
                    @click="confirmarEliminacion"
                    :disabled="!textoValido || eliminando"
                    class="esm-btn-danger flex-[2]"
                  >
                    <svg v-if="eliminando" class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24">
                      <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                    </svg>
                    <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    {{ eliminando ? 'Moviendo a papelera...' : 'Mover a papelera' }}
                  </button>
                </div>
              </div>
            </Transition>

            <button type="button" class="esm-x" @click="$emit('cerrar')">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.esm-overlay {
  position: fixed; inset: 0; background: rgba(10,10,10,.85);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  display: flex; align-items: center; justify-content: center;
  z-index: 10001; padding: 1rem;
}
.esm-card {
  position: relative; background: #fff; border: 1px solid #fecaca;
  border-radius: 2rem; padding: 2.25rem; width: 100%; max-width: 520px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 0 0 1px rgba(220,38,38,.08), 0 24px 48px rgba(0,0,0,.2), 0 0 80px rgba(220,38,38,.06);
  scrollbar-width: thin; scrollbar-color: #fecaca transparent;
}
.esm-card::-webkit-scrollbar { width: 5px; }
.esm-card::-webkit-scrollbar-thumb { background: #fecaca; border-radius: 3px; }

.esm-header   { display: flex; align-items: flex-start; gap: 1rem; }
.esm-icon-box { flex-shrink: 0; width: 52px; height: 52px; background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 1rem; display: flex; align-items: center; justify-content: center; }
.esm-title    { font-size: 1.2rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.esm-sub      { font-size: .78rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }

.esm-impact-row    { display: flex; align-items: flex-start; gap: .75rem; padding: .875rem 1rem; border-radius: 1rem; }
.esm-impact-danger { background: #fef2f2; border: 1px solid #fecaca; }
.esm-impact-warn   { background: #fffbeb; border: 1px solid #fde68a; }
.esm-impact-ok     { background: #eff6ff; border: 1px solid #bfdbfe; }
.esm-impact-icon   { width: 32px; height: 32px; border-radius: .625rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.esm-label { display: block; font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .45rem; margin-left: .25rem; line-height: 1.6; }
.esm-input {
  width: 100%; border: 2px solid #e5e7eb; border-radius: 1rem; padding: .85rem 1rem;
  font-size: .875rem; font-weight: 600; color: #1F2937; background: #fff; outline: none; transition: all .2s;
}
.esm-input:focus     { border-color: #f87171; box-shadow: 0 0 0 4px rgba(239,68,68,.1); }
.esm-input-err       { border-color: #fca5a5 !important; background: #fff5f5 !important; }
.esm-input-ok        { border-color: #86efac !important; background: #f0fdf4 !important; }
.esm-hint-err { font-size: .7rem; color: #ef4444; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.esm-hint-ok  { font-size: .7rem; color: #16a34a; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.esm-alert-error { display: flex; align-items: center; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 1rem; padding: .75rem 1rem; color: #dc2626; font-size: .78rem; font-weight: 700; }

.esm-actions { display: flex; gap: .75rem; margin-top: .5rem; }
.esm-btn-danger {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: 1rem 1.5rem; background: #dc2626; color: #fff; border: none;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; box-shadow: 0 6px 20px rgba(220,38,38,.3); transition: all .2s;
}
.esm-btn-danger:hover:not(:disabled)  { background: #b91c1c; transform: translateY(-1px); }
.esm-btn-danger:active:not(:disabled) { transform: scale(.97); }
.esm-btn-danger:disabled              { opacity: .4; cursor: not-allowed; }
.esm-btn-danger-outline {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: 1rem 1.5rem; background: #fff; color: #dc2626; border: 2px solid #fca5a5;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; transition: all .2s;
}
.esm-btn-danger-outline:hover  { background: #fef2f2; border-color: #f87171; }
.esm-btn-danger-outline:active { transform: scale(.97); }
.esm-btn-ghost {
  display: flex; align-items: center; justify-content: center; gap: .4rem;
  padding: 1rem 1.5rem; background: #fff; color: #6b7280; border: 2px solid #e5e7eb;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; transition: all .2s;
}
.esm-btn-ghost:hover  { border-color: #d1d5db; color: #374151; }
.esm-btn-ghost:active { transform: scale(.97); }
.esm-x {
  position: absolute; top: 1.25rem; right: 1.25rem; width: 32px; height: 32px;
  background: #fef2f2; border: none; border-radius: .5rem; color: #f87171;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s;
}
.esm-x:hover { background: #fee2e2; color: #dc2626; }

.esm-overlay-enter-active, .esm-overlay-leave-active { transition: opacity .3s ease; }
.esm-overlay-enter-from,   .esm-overlay-leave-to    { opacity: 0; }
.esm-card-enter-active  { transition: all .42s cubic-bezier(.34,1.56,.64,1); }
.esm-card-leave-active  { transition: all .2s ease; }
.esm-card-enter-from    { opacity: 0; transform: scale(.91) translateY(28px); }
.esm-card-leave-to      { opacity: 0; transform: scale(.96) translateY(10px); }
.esm-fase-enter-active  { transition: all .28s cubic-bezier(.34,1.56,.64,1); }
.esm-fase-leave-active  { transition: all .16s ease; }
.esm-fase-enter-from    { opacity: 0; transform: translateX(20px); }
.esm-fase-leave-to      { opacity: 0; transform: translateX(-20px); }
.esm-fade-enter-active, .esm-fade-leave-active { transition: all .22s ease; }
.esm-fade-enter-from,   .esm-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>
