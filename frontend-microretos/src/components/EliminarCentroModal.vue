<script setup>
/**
 * EliminarCentroModal.vue
 * Eliminación de centro educativo con doble confirmación:
 *   Fase 1 — muestra el impacto real y pide una primera confirmación explícita.
 *   Fase 2 — obliga a escribir el nombre exacto del centro para desbloquear el borrado.
 *
 * Props:
 *   visible        Boolean
 *   centro         { id, nombre }
 *   numEmpresas    Number   — empresas que perderán su centro asignado
 *   numCiclos      Number   — ciclos que serán desvinculados
 *
 * Emits:
 *   @centro-eliminado  { id, nombre }
 *   @cerrar
 */
import { ref, computed, watch } from 'vue'
import api from '../api.js'

const props = defineProps({
  visible:     { type: Boolean, default: false },
  centro:      { type: Object,  default: null  },   // { id, nombre }
  numEmpresas: { type: Number,  default: 0 },
  numCiclos:   { type: Number,  default: 0 },
})
const emit = defineEmits(['centro-eliminado', 'cerrar'])

// ─── Estado ────────────────────────────────────────────────
const fase       = ref(1)     // 1 = impacto, 2 = confirmación con nombre
const confirmNombre = ref('')
const eliminando = ref(false)
const error      = ref('')

// Nombre coincide exactamente (sin espacios extra, case-sensitive)
const nombreValido = computed(() =>
  confirmNombre.value === props.centro?.nombre
)

watch(() => props.visible, (v) => {
  if (v) {
    fase.value          = 1
    confirmNombre.value = ''
    eliminando.value    = false
    error.value         = ''
  }
})

// ─── Acciones ───────────────────────────────────────────────
function avanzarFase2() {
  fase.value = 2
  // Pequeño delay para que el input aparezca y el usuario lo vea
  setTimeout(() => document.getElementById('confirm-nombre-input')?.focus(), 150)
}

async function confirmarEliminacion() {
  if (!nombreValido.value || eliminando.value) return
  eliminando.value = true
  error.value = ''
  try {
    await api.delete(`/centros/${props.centro.id}`)
    emit('centro-eliminado', { id: props.centro.id, nombre: props.centro.nombre })
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'El centro no se encontró. Es posible que ya haya sido eliminado.'
    } else {
      error.value = 'Error al eliminar el centro. Inténtalo de nuevo.'
    }
  } finally {
    eliminando.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="ecc-overlay">
      <div v-if="visible" class="ecc-overlay" @click.self="$emit('cerrar')">
        <Transition name="ecc-card">
          <div v-if="visible" class="ecc-card">

            <!-- ══ CABECERA ══ -->
            <div class="ecc-header">
              <div class="ecc-icon-box">
                <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="ecc-title">Eliminar centro educativo</h2>
                <p class="ecc-sub">El centro se moverá a la <span class="font-black text-amber-600">papelera</span> y podrá restaurarse desde allí</p>
              </div>
            </div>

            <!-- Nombre del centro -->
            <div class="mt-5 px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-0.5">Centro a eliminar</p>
              <p class="font-black text-[#1F2937] text-base truncate">{{ centro?.nombre }}</p>
            </div>

            <!-- ══ FASE 1: impacto ══ -->
            <Transition name="ecc-fase" mode="out-in">
              <div v-if="fase === 1" key="fase1" class="mt-5 space-y-3">

                <!-- Tarjetas de impacto -->
                <p class="text-sm font-semibold text-gray-600 mb-1">
                  Al eliminar este centro ocurrirá lo siguiente:
                </p>

                <div class="ecc-impact-row" :class="numEmpresas > 0 ? 'ecc-impact-warn' : 'ecc-impact-ok'">
                  <div class="ecc-impact-icon" :class="numEmpresas > 0 ? 'bg-amber-100' : 'bg-gray-100'">
                    <svg class="w-4 h-4" :class="numEmpresas > 0 ? 'text-amber-600' : 'text-gray-400'"
                      fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-black text-sm" :class="numEmpresas > 0 ? 'text-amber-800' : 'text-gray-500'">
                      {{ numEmpresas > 0
                        ? `${numEmpresas} ${numEmpresas === 1 ? 'empresa quedará' : 'empresas quedarán'} sin centro asignado`
                        : 'Ninguna empresa se verá afectada' }}
                    </p>
                    <p class="text-xs mt-0.5" :class="numEmpresas > 0 ? 'text-amber-600' : 'text-gray-400'">
                      {{ numEmpresas > 0
                        ? 'Las empresas no se eliminarán, pero perderán la referencia al centro.'
                        : 'Este centro no tiene empresas asociadas.' }}
                    </p>
                  </div>
                </div>

                <div class="ecc-impact-row" :class="numCiclos > 0 ? 'ecc-impact-warn' : 'ecc-impact-ok'">
                  <div class="ecc-impact-icon" :class="numCiclos > 0 ? 'bg-amber-100' : 'bg-gray-100'">
                    <svg class="w-4 h-4" :class="numCiclos > 0 ? 'text-amber-600' : 'text-gray-400'"
                      fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-black text-sm" :class="numCiclos > 0 ? 'text-amber-800' : 'text-gray-500'">
                      {{ numCiclos > 0
                        ? `${numCiclos} ${numCiclos === 1 ? 'ciclo formativo será desvinculado' : 'ciclos formativos serán desvinculados'}`
                        : 'No hay ciclos vinculados a este centro' }}
                    </p>
                    <p class="text-xs mt-0.5" :class="numCiclos > 0 ? 'text-amber-600' : 'text-gray-400'">
                      {{ numCiclos > 0
                        ? 'Los ciclos formativos no se eliminan, solo la asociación con este centro.'
                        : 'No hay relaciones de ciclos que eliminar.' }}
                    </p>
                  </div>
                </div>

                <div class="ecc-impact-row ecc-impact-warn mt-1">
                  <div class="ecc-impact-icon bg-amber-100">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-black text-sm text-amber-800">El centro se moverá a la papelera</p>
                    <p class="text-xs text-amber-600 mt-0.5">Podrás recuperarlo desde la sección Papelera si lo necesitas.</p>
                  </div>
                </div>

                <div class="fila-pasos mt-2">
                  <button type="button" @click="$emit('cerrar')" class="btn-paso-atras">
                    Cancelar
                  </button>
                  <button type="button" @click="avanzarFase2"
                    class="btn-paso border-2 border-red-300 bg-white text-red-600 shadow-none hover:border-red-400 hover:bg-red-50">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span class="sm:hidden">Continuar</span><span class="hidden sm:inline">Entiendo, quiero eliminarlo</span>
                  </button>
                </div>
              </div>

              <!-- ══ FASE 2: confirmación con nombre ══ -->
              <div v-else key="fase2" class="mt-5 space-y-4">

                <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                  <p class="text-sm font-black text-red-700 mb-1 flex items-center gap-1.5">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Confirmación final requerida
                  </p>
                  <p class="text-xs text-red-600">
                    Escribe el nombre exacto del centro para confirmar que no es un error accidental.
                  </p>
                </div>

                <div>
                  <label class="ecc-label">
                    Escribe <span class="font-black text-[#1F2937] normal-case tracking-normal">{{ centro?.nombre }}</span> para confirmar
                  </label>
                  <input
                    id="confirm-nombre-input"
                    v-model="confirmNombre"
                    class="ecc-input"
                    :class="confirmNombre && !nombreValido ? 'ecc-input-err' : confirmNombre && nombreValido ? 'ecc-input-ok' : ''"
                    placeholder="Escribe el nombre exacto..."
                    autocomplete="off"
                    @keydown.enter.prevent="nombreValido && !eliminando && confirmarEliminacion()"
                  />
                  <p v-if="confirmNombre && !nombreValido" class="ecc-hint-err">
                    El nombre no coincide exactamente. Comprueba mayúsculas y espacios.
                  </p>
                  <p v-if="confirmNombre && nombreValido" class="ecc-hint-ok">
                    Nombre confirmado. Ya puedes mover el centro a la papelera.
                  </p>
                </div>

                <Transition name="ecc-fade">
                  <div v-if="error" class="ecc-alert-error">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ error }}</span>
                  </div>
                </Transition>

                <div class="fila-pasos">
                  <button type="button" @click="fase = 1" class="btn-paso-atras">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver
                  </button>
                  <button
                    type="button"
                    @click="confirmarEliminacion"
                    :disabled="!nombreValido || eliminando"
                    class="btn-paso bg-red-600 shadow-red-600/30 hover:bg-red-700"
                  >
                    <svg v-if="eliminando" class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24">
                      <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                    </svg>
                    <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span class="sm:hidden">{{ eliminando ? 'Moviendo...' : 'Mover' }}</span>
                    <span class="hidden sm:inline">{{ eliminando ? 'Moviendo a papelera...' : 'Mover a papelera' }}</span>
                  </button>
                </div>
              </div>
            </Transition>

            <!-- X -->
            <button type="button" class="ecc-x" @click="$emit('cerrar')">
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
.ecc-overlay {
  position: fixed; inset: 0;
  background: rgba(10, 10, 10, 0.85);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  display: flex; align-items: center; justify-content: center;
  z-index: 10001; padding: 1rem;
}
.ecc-card {
  position: relative; background: #fff;
  border: 1px solid #fecaca; border-radius: 2rem; padding: 2.25rem;
  width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto;
  box-shadow: 0 0 0 1px rgba(220,38,38,.08), 0 24px 48px rgba(0,0,0,.2), 0 0 80px rgba(220,38,38,.06);
  scrollbar-width: thin; scrollbar-color: #fecaca transparent;
}
.ecc-card::-webkit-scrollbar       { width: 5px; }
.ecc-card::-webkit-scrollbar-thumb { background: #fecaca; border-radius: 3px; }

.ecc-header   { display: flex; align-items: flex-start; gap: 1rem; }
.ecc-icon-box { flex-shrink: 0; width: 52px; height: 52px; background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 1rem; display: flex; align-items: center; justify-content: center; }
.ecc-title    { font-size: 1.2rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.ecc-sub      { font-size: .78rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }

/* Impacto */
.ecc-impact-row  { display: flex; align-items: flex-start; gap: .75rem; padding: .875rem 1rem; border-radius: 1rem; }
.ecc-impact-warn { background: #fffbeb; border: 1px solid #fde68a; }
.ecc-impact-ok   { background: #f9fafb; border: 1px solid #e5e7eb; }
.ecc-impact-danger { background: #fef2f2; border: 1px solid #fecaca; }
.ecc-impact-icon { width: 32px; height: 32px; border-radius: .625rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

/* Inputs */
.ecc-label { display: block; font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .45rem; margin-left: .25rem; line-height: 1.6; }
.ecc-input {
  width: 100%; border: 2px solid #e5e7eb; border-radius: 1rem; padding: .85rem 1rem;
  font-size: .875rem; font-weight: 600; color: #1F2937; background: #fff; outline: none; transition: all .2s;
}
.ecc-input:focus       { border-color: #f87171; box-shadow: 0 0 0 4px rgba(239,68,68,.1); }
.ecc-input-err         { border-color: #fca5a5 !important; background: #fff5f5 !important; }
.ecc-input-err:focus   { border-color: #ef4444 !important; box-shadow: 0 0 0 4px rgba(239,68,68,.1) !important; }
.ecc-input-ok          { border-color: #86efac !important; background: #f0fdf4 !important; }
.ecc-input-ok:focus    { border-color: #22c55e !important; box-shadow: 0 0 0 4px rgba(34,197,94,.1) !important; }

.ecc-hint-err { font-size: .7rem; color: #ef4444; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.ecc-hint-ok  { font-size: .7rem; color: #16a34a; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }

.ecc-alert-error { display: flex; align-items: center; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 1rem; padding: .75rem 1rem; color: #dc2626; font-size: .78rem; font-weight: 700; }

/* Botones */
.ecc-actions { display: flex; gap: .75rem; margin-top: .5rem; }

.ecc-btn-danger {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: 1rem 1.5rem; background: #dc2626; color: #fff; border: none;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer;
  box-shadow: 0 6px 20px rgba(220,38,38,.3); transition: all .2s;
}
.ecc-btn-danger:hover:not(:disabled)  { background: #b91c1c; transform: translateY(-1px); box-shadow: 0 10px 28px rgba(220,38,38,.4); }
.ecc-btn-danger:active:not(:disabled) { transform: scale(.97); }
.ecc-btn-danger:disabled              { opacity: .4; cursor: not-allowed; }

.ecc-btn-danger-outline {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: 1rem 1.5rem; background: #fff; color: #dc2626;
  border: 2px solid #fca5a5; border-radius: 1rem; font-weight: 900; font-size: .7rem;
  letter-spacing: .15em; text-transform: uppercase; cursor: pointer; transition: all .2s;
}
.ecc-btn-danger-outline:hover  { background: #fef2f2; border-color: #f87171; }
.ecc-btn-danger-outline:active { transform: scale(.97); }

.ecc-btn-ghost {
  display: flex; align-items: center; justify-content: center; gap: .4rem;
  padding: 1rem 1.5rem; background: #fff; color: #6b7280; border: 2px solid #e5e7eb;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; transition: all .2s;
}
.ecc-btn-ghost:hover  { border-color: #d1d5db; color: #374151; }
.ecc-btn-ghost:active { transform: scale(.97); }

.ecc-x {
  position: absolute; top: 1.25rem; right: 1.25rem; width: 32px; height: 32px;
  background: #fef2f2; border: none; border-radius: 9999px; color: #f87171;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .2s, color .2s;
}
.ecc-x:hover { background: #fee2e2; color: #dc2626; }

/* Transiciones */
.ecc-overlay-enter-active, .ecc-overlay-leave-active { transition: opacity .3s ease; }
.ecc-overlay-enter-from,   .ecc-overlay-leave-to    { opacity: 0; }
.ecc-card-enter-active  { transition: all .42s cubic-bezier(.34,1.56,.64,1); }
.ecc-card-leave-active  { transition: all .2s ease; }
.ecc-card-enter-from    { opacity: 0; transform: scale(.91) translateY(28px); }
.ecc-card-leave-to      { opacity: 0; transform: scale(.96) translateY(10px); }

.ecc-fase-enter-active  { transition: all .28s cubic-bezier(.34,1.56,.64,1); }
.ecc-fase-leave-active  { transition: all .16s ease; }
.ecc-fase-enter-from    { opacity: 0; transform: translateX(20px); }
.ecc-fase-leave-to      { opacity: 0; transform: translateX(-20px); }

.ecc-fade-enter-active, .ecc-fade-leave-active { transition: all .22s ease; }
.ecc-fade-enter-from,   .ecc-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>
