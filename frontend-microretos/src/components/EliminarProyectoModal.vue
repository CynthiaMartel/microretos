<script setup>
import { ref, computed, watch } from 'vue'
import api from '../api.js'

const props = defineProps({
  visible:   { type: Boolean, default: false },
  proyecto:  { type: Object,  default: null  },
})
const emit = defineEmits(['proyecto-eliminado', 'cerrar'])

const fase          = ref(1)
const confirmTitulo = ref('')
const eliminando    = ref(false)
const error         = ref('')

const tituloValido = computed(() =>
  confirmTitulo.value === props.proyecto?.titulo
)

watch(() => props.visible, (v) => {
  if (v) {
    fase.value          = 1
    confirmTitulo.value = ''
    eliminando.value    = false
    error.value         = ''
  }
})

function avanzarFase2() {
  fase.value = 2
  setTimeout(() => document.getElementById('epm-confirm-input')?.focus(), 150)
}

async function confirmarEliminacion() {
  if (!tituloValido.value || eliminando.value) return
  eliminando.value = true
  error.value = ''
  try {
    await api.delete(`/startup/proyectos/${props.proyecto.uuid}`)
    emit('proyecto-eliminado', { uuid: props.proyecto.uuid, titulo: props.proyecto.titulo })
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'El proyecto no se encontró. Es posible que ya haya sido eliminado.'
    } else if (e.response?.status === 401) {
      error.value = 'Tu sesión ha expirado. Vuelve a iniciar sesión e inténtalo de nuevo.'
    } else {
      error.value = 'Error al eliminar el proyecto. Inténtalo de nuevo.'
    }
  } finally {
    eliminando.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="epm-overlay">
      <div v-if="visible" class="epm-overlay" @click.self="$emit('cerrar')">
        <Transition name="epm-card">
          <div v-if="visible" class="epm-card">

            <!-- Cabecera -->
            <div class="epm-header">
              <div class="epm-icon-box">
                <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="epm-title">Eliminar propuesta/proyecto</h2>
                <p class="epm-sub">Se moverá a la <span class="font-black text-amber-600">papelera</span> y podrá restaurarse desde allí</p>
              </div>
            </div>

            <!-- Identificación de la propuesta/proyecto -->
            <div class="mt-5 px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-0.5">Propuesta/Proyecto a eliminar</p>
              <p class="font-black text-[#1F2937] text-sm leading-snug">{{ proyecto?.titulo }}</p>
              <div class="flex flex-wrap gap-1.5 mt-2">
                <span v-if="proyecto?.empresa_nombre"
                  class="text-[10px] font-bold bg-[#00A859]/10 text-[#00A859] px-2 py-0.5 rounded-full">
                  {{ proyecto.empresa_nombre }}
                </span>
                <span v-if="proyecto?.centro_nombre"
                  class="text-[10px] font-bold bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">
                  {{ proyecto.centro_nombre }}
                </span>
                <span v-if="proyecto?.estado"
                  class="text-[10px] font-bold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full capitalize">
                  {{ proyecto.estado }}
                </span>
              </div>
            </div>

            <!-- ═══ FASE 1 ═══ -->
            <Transition name="epm-fase" mode="out-in">
              <div v-if="fase === 1" key="f1" class="mt-5 space-y-3">

                <p class="text-sm font-semibold text-gray-600">Al eliminarla ocurrirá lo siguiente:</p>

                <!-- Movido a papelera -->
                <div class="epm-impact-row epm-impact-warn">
                  <div class="epm-impact-icon bg-amber-100">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-amber-800">Se moverá a la papelera</p>
                    <p class="text-xs text-amber-600 mt-0.5">Podrás recuperarlo desde la sección Papelera si lo necesitas.</p>
                  </div>
                </div>

                <!-- Deja de ser visible -->
                <div class="epm-impact-row epm-impact-danger">
                  <div class="epm-impact-icon bg-red-100">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-red-700">Dejará de ser visible en el listado de propuestas y proyectos</p>
                    <p class="text-xs text-red-500 mt-0.5">El equipo no podrá acceder a ella mientras esté en la papelera.</p>
                  </div>
                </div>

                <!-- Enlace empresa no afectado -->
                <div class="epm-impact-row epm-impact-ok">
                  <div class="epm-impact-icon bg-blue-50">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-blue-700">El enlace de validación de empresa se desactivará</p>
                    <p class="text-xs text-blue-500 mt-0.5">Si todavía está en fase de propuesta, la empresa no podrá validarla mientras esté en la papelera.</p>
                  </div>
                </div>

                <div class="epm-actions mt-2">
                  <button type="button" @click="$emit('cerrar')" class="epm-btn-ghost flex-1">Cancelar</button>
                  <button type="button" @click="avanzarFase2" class="epm-btn-danger-outline flex-[2]">
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
                    Escribe el título exacto para confirmar que no es un error accidental.
                  </p>
                </div>

                <div>
                  <label class="epm-label">
                    Escribe <span class="font-black text-[#1F2937] normal-case tracking-normal">{{ proyecto?.titulo }}</span> para confirmar
                  </label>
                  <input
                    id="epm-confirm-input"
                    v-model="confirmTitulo"
                    class="epm-input"
                    :class="confirmTitulo && !tituloValido ? 'epm-input-err' : confirmTitulo && tituloValido ? 'epm-input-ok' : ''"
                    placeholder="Escribe el título exacto..."
                    autocomplete="off"
                    @keydown.enter.prevent="tituloValido && !eliminando && confirmarEliminacion()"
                  />
                  <p v-if="confirmTitulo && !tituloValido" class="epm-hint-err">
                    El título no coincide. Comprueba mayúsculas, espacios y caracteres especiales.
                  </p>
                  <p v-if="confirmTitulo && tituloValido" class="epm-hint-ok">
                    Título confirmado. Ya puedes moverla a la papelera.
                  </p>
                </div>

                <Transition name="epm-fade">
                  <div v-if="error" class="epm-alert-error">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ error }}</span>
                  </div>
                </Transition>

                <div class="epm-actions">
                  <button type="button" @click="fase = 1" class="epm-btn-ghost flex-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver
                  </button>
                  <button
                    type="button"
                    @click="confirmarEliminacion"
                    :disabled="!tituloValido || eliminando"
                    class="epm-btn-danger flex-[2]"
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

            <button type="button" class="epm-x" @click="$emit('cerrar')">
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
.epm-overlay {
  position: fixed; inset: 0; background: rgba(10,10,10,.85);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  display: flex; align-items: center; justify-content: center;
  z-index: 10001; padding: 1rem;
}
.epm-card {
  position: relative; background: #fff; border: 1px solid #fecaca;
  border-radius: 2rem; padding: 2.25rem; width: 100%; max-width: 520px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 0 0 1px rgba(220,38,38,.08), 0 24px 48px rgba(0,0,0,.2), 0 0 80px rgba(220,38,38,.06);
  scrollbar-width: thin; scrollbar-color: #fecaca transparent;
}
.epm-card::-webkit-scrollbar { width: 5px; }
.epm-card::-webkit-scrollbar-thumb { background: #fecaca; border-radius: 3px; }

.epm-header   { display: flex; align-items: flex-start; gap: 1rem; }
.epm-icon-box { flex-shrink: 0; width: 52px; height: 52px; background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 1rem; display: flex; align-items: center; justify-content: center; }
.epm-title    { font-size: 1.2rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.epm-sub      { font-size: .78rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }

.epm-impact-row    { display: flex; align-items: flex-start; gap: .75rem; padding: .875rem 1rem; border-radius: 1rem; }
.epm-impact-danger { background: #fef2f2; border: 1px solid #fecaca; }
.epm-impact-warn   { background: #fffbeb; border: 1px solid #fde68a; }
.epm-impact-ok     { background: #eff6ff; border: 1px solid #bfdbfe; }
.epm-impact-icon   { width: 32px; height: 32px; border-radius: .625rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.epm-label { display: block; font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .45rem; margin-left: .25rem; line-height: 1.6; }
.epm-input {
  width: 100%; border: 2px solid #e5e7eb; border-radius: 1rem; padding: .85rem 1rem;
  font-size: .875rem; font-weight: 600; color: #1F2937; background: #fff; outline: none; transition: all .2s;
}
.epm-input:focus     { border-color: #f87171; box-shadow: 0 0 0 4px rgba(239,68,68,.1); }
.epm-input-err       { border-color: #fca5a5 !important; background: #fff5f5 !important; }
.epm-input-ok        { border-color: #86efac !important; background: #f0fdf4 !important; }
.epm-hint-err { font-size: .7rem; color: #ef4444; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.epm-hint-ok  { font-size: .7rem; color: #16a34a; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.epm-alert-error { display: flex; align-items: center; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 1rem; padding: .75rem 1rem; color: #dc2626; font-size: .78rem; font-weight: 700; }

.epm-actions { display: flex; gap: .75rem; margin-top: .5rem; }
.epm-btn-danger {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: 1rem 1.5rem; background: #dc2626; color: #fff; border: none;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; box-shadow: 0 6px 20px rgba(220,38,38,.3); transition: all .2s;
}
.epm-btn-danger:hover:not(:disabled)  { background: #b91c1c; transform: translateY(-1px); }
.epm-btn-danger:active:not(:disabled) { transform: scale(.97); }
.epm-btn-danger:disabled              { opacity: .4; cursor: not-allowed; }
.epm-btn-danger-outline {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: 1rem 1.5rem; background: #fff; color: #dc2626; border: 2px solid #fca5a5;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; transition: all .2s;
}
.epm-btn-danger-outline:hover  { background: #fef2f2; border-color: #f87171; }
.epm-btn-danger-outline:active { transform: scale(.97); }
.epm-btn-ghost {
  display: flex; align-items: center; justify-content: center; gap: .4rem;
  padding: 1rem 1.5rem; background: #fff; color: #6b7280; border: 2px solid #e5e7eb;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; transition: all .2s;
}
.epm-btn-ghost:hover  { border-color: #d1d5db; color: #374151; }
.epm-btn-ghost:active { transform: scale(.97); }
.epm-x {
  position: absolute; top: 1.25rem; right: 1.25rem; width: 32px; height: 32px;
  background: #fef2f2; border: none; border-radius: .5rem; color: #f87171;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s;
}
.epm-x:hover { background: #fee2e2; color: #dc2626; }

.epm-overlay-enter-active, .epm-overlay-leave-active { transition: opacity .3s ease; }
.epm-overlay-enter-from,   .epm-overlay-leave-to    { opacity: 0; }
.epm-card-enter-active  { transition: all .42s cubic-bezier(.34,1.56,.64,1); }
.epm-card-leave-active  { transition: all .2s ease; }
.epm-card-enter-from    { opacity: 0; transform: scale(.91) translateY(28px); }
.epm-card-leave-to      { opacity: 0; transform: scale(.96) translateY(10px); }
.epm-fase-enter-active  { transition: all .28s cubic-bezier(.34,1.56,.64,1); }
.epm-fase-leave-active  { transition: all .16s ease; }
.epm-fase-enter-from    { opacity: 0; transform: translateX(20px); }
.epm-fase-leave-to      { opacity: 0; transform: translateX(-20px); }
.epm-fade-enter-active, .epm-fade-leave-active { transition: all .22s ease; }
.epm-fade-enter-from,   .epm-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>
