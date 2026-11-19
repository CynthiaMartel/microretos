<script setup>
/**
 * EliminarSolicitudModal.vue — mover una solicitud de retos a la papelera, con el mismo borrado
 * seguro que las empresas (EliminarEmpresaModal.vue):
 *   Fase 1 — qué pasa al eliminarla, antes de comprometerse.
 *   Fase 2 — escribir el nombre exacto del docente para desbloquear el borrado.
 * La mueven el superadmin y Admin Central DuaLab; se restaura o se borra del todo en Red DuaLab →
 * Papelera (solo superadmin).
 *
 * Props:  visible, solicitud ({ id, nombre_docente, centro_nombre, email_docente, num_retos, fase })
 * Emits:  @eliminada { id }, @cerrar
 */
import { ref, computed, watch } from 'vue'
import { eliminarSolicitud } from '../../src/services/solicitudRetoService.js'
import AvisoAccionCritica from '../../src/components/AvisoAccionCritica.vue'

const props = defineProps({
  visible:   { type: Boolean, default: false },
  solicitud: { type: Object,  default: null  },
})
const emit = defineEmits(['eliminada', 'cerrar'])

const fase          = ref(1)
const confirmNombre = ref('')
const eliminando    = ref(false)
const error         = ref('')

const textoRetos = computed(() => {
  const n = props.solicitud?.num_retos
  if (!n) return 'No tiene retos generados'
  return n === 1 ? 'Su reto se queda en la recámara' : `Sus ${n} retos se quedan en la recámara`
})
const nombreValido = computed(() => confirmNombre.value === props.solicitud?.nombre_docente)

watch(() => props.visible, (v) => {
  if (v) { fase.value = 1; confirmNombre.value = ''; eliminando.value = false; error.value = '' }
})

function avanzarFase2() {
  fase.value = 2
  setTimeout(() => document.getElementById('esr-confirm-input')?.focus(), 150)
}

async function confirmarEliminacion() {
  if (!nombreValido.value || eliminando.value) return
  eliminando.value = true
  error.value = ''
  try {
    await eliminarSolicitud(props.solicitud.id)
    emit('eliminada', { id: props.solicitud.id })
  } catch (e) {
    error.value = e.response?.status === 404 ? 'La solicitud no se encontró. Es posible que ya esté en la papelera.'
      : e.response?.status === 403 ? 'Solo Admin Central DuaLab o el superadmin pueden mover solicitudes a la papelera.'
      : e.response?.status === 401 ? 'Tu sesión ha expirado. Vuelve a iniciar sesión e inténtalo de nuevo.'
      : 'Error al eliminar la solicitud. Inténtalo de nuevo.'
  } finally {
    eliminando.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="esr-overlay">
      <div v-if="visible" class="esr-overlay" @click.self="$emit('cerrar')" @keydown.esc="$emit('cerrar')">
        <Transition name="esr-card">
          <div v-if="visible" class="esr-card" role="dialog" aria-modal="true" aria-labelledby="esr-titulo">

            <div class="esr-header">
              <div class="esr-icon-box">
                <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.66 17h4.68M12 3v1m6.36 1.64l-.7.7M21 12h-1M4 12H3m3.34-5.66l-.7-.7m2.83 9.9a5 5 0 117.07 0l-.55.54A3.37 3.37 0 0014 18.47V19a2 2 0 11-4 0v-.53c0-.9-.36-1.75-.99-2.38l-.54-.55z"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 id="esr-titulo" class="esr-title">Eliminar solicitud de retos</h2>
                <p class="esr-sub">La solicitud se moverá a la <span class="font-black text-amber-600">papelera</span> y podrá restaurarse desde allí</p>
              </div>
            </div>

            <div class="mt-5 px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-0.5">Solicitud a eliminar</p>
              <p class="font-black text-[#1F2937] text-base truncate">{{ solicitud?.nombre_docente }}</p>
              <p class="text-xs text-gray-500 truncate">{{ solicitud?.centro_nombre }} · {{ solicitud?.email_docente }}</p>
            </div>

            <Transition name="esr-fase" mode="out-in">
              <!-- ═══ FASE 1 ═══ -->
              <div v-if="fase === 1" key="f1" class="mt-5 space-y-3">
                <p class="text-sm font-semibold text-gray-600">Al eliminar esta solicitud ocurrirá lo siguiente:</p>

                <div class="esr-impact-row esr-impact-warn">
                  <div class="esr-impact-icon bg-amber-100">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-amber-800">La solicitud se moverá a la papelera</p>
                    <p class="text-xs text-amber-600 mt-0.5">Podrás recuperarla desde Red DuaLab → Papelera, con sus respuestas y notas.</p>
                  </div>
                </div>

                <div class="esr-impact-row esr-impact-warn">
                  <div class="esr-impact-icon bg-amber-100">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-amber-800">El enlace del docente dejará de funcionar</p>
                    <p class="text-xs text-amber-600 mt-0.5">Ni el formulario ni la página de sus retos se podrán abrir. Sus correos a info@ volverán a aparecer en el Correo de info@.</p>
                  </div>
                </div>

                <div class="esr-impact-row esr-impact-ok">
                  <div class="esr-impact-icon bg-blue-50">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-blue-700">{{ textoRetos }}</p>
                    <p class="text-xs text-blue-500 mt-0.5">No se eliminan y siguen sin verse en los centros. Los encuentros creados tampoco cambian.</p>
                  </div>
                </div>

                <div class="fila-pasos mt-2">
                  <button type="button" @click="$emit('cerrar')" class="btn-paso-atras">Cancelar</button>
                  <button type="button" @click="avanzarFase2" class="btn-paso border-2 border-red-300 bg-white text-red-600 shadow-none hover:border-red-400 hover:bg-red-50">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span class="sm:hidden">Continuar</span><span class="hidden sm:inline">Entiendo, quiero eliminarla</span>
                  </button>
                </div>
              </div>

              <!-- ═══ FASE 2 ═══ -->
              <div v-else key="f2" class="mt-5 space-y-4">
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                  <p class="text-sm font-black text-red-700 mb-1 flex items-center gap-1.5">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Confirmación final requerida
                  </p>
                  <p class="text-xs text-red-600">Escribe el nombre exacto del docente para confirmar que no es un error accidental.</p>
                </div>

                <div>
                  <label for="esr-confirm-input" class="esr-label">
                    Escribe <span class="font-black text-[#1F2937] normal-case tracking-normal">{{ solicitud?.nombre_docente }}</span> para confirmar
                  </label>
                  <input id="esr-confirm-input" v-model="confirmNombre" class="esr-input"
                         :class="confirmNombre && !nombreValido ? 'esr-input-err' : confirmNombre && nombreValido ? 'esr-input-ok' : ''"
                         placeholder="Escribe el nombre exacto..." autocomplete="off"
                         @keydown.enter.prevent="nombreValido && !eliminando && confirmarEliminacion()" />
                  <p v-if="confirmNombre && !nombreValido" class="esr-hint-err">El nombre no coincide. Comprueba mayúsculas, espacios y tildes.</p>
                  <p v-if="confirmNombre && nombreValido" class="esr-hint-ok">Nombre confirmado. Ya puedes mover la solicitud a la papelera.</p>
                </div>

                <AvisoAccionCritica texto="Restaurarla o borrarla del todo solo puede hacerlo el superadmin, desde la Papelera. Queda en el Historial de Central con tu nombre y la fecha." />

                <Transition name="esr-fade">
                  <div v-if="error" class="esr-alert-error" role="alert">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ error }}</span>
                  </div>
                </Transition>

                <div class="fila-pasos">
                  <button type="button" @click="fase = 1" class="btn-paso-atras">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Volver
                  </button>
                  <button type="button" @click="confirmarEliminacion" :disabled="!nombreValido || eliminando"
                          class="btn-paso bg-red-600 shadow-red-600/30 hover:bg-red-700">
                    <svg v-if="eliminando" class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
                    <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span class="sm:hidden">{{ eliminando ? 'Moviendo...' : 'Mover' }}</span>
                    <span class="hidden sm:inline">{{ eliminando ? 'Moviendo a papelera...' : 'Mover a papelera' }}</span>
                  </button>
                </div>
              </div>
            </Transition>

            <button type="button" class="esr-x" @click="$emit('cerrar')" aria-label="Cerrar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Mismo aspecto que EliminarEmpresaModal.vue (borrado seguro de Base de datos) */
.esr-overlay {
  position: fixed; inset: 0; background: rgba(10,10,10,.85);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  display: flex; align-items: center; justify-content: center;
  z-index: 10001; padding: 1rem;
}
.esr-card {
  position: relative; background: #fff; border: 1px solid #fecaca;
  border-radius: 2rem; padding: 2.25rem; width: 100%; max-width: 520px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 0 0 1px rgba(220,38,38,.08), 0 24px 48px rgba(0,0,0,.2), 0 0 80px rgba(220,38,38,.06);
  scrollbar-width: thin; scrollbar-color: #fecaca transparent;
}
.esr-header   { display: flex; align-items: flex-start; gap: 1rem; padding-right: 2.5rem; }
.esr-icon-box { flex-shrink: 0; width: 52px; height: 52px; background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 1rem; display: flex; align-items: center; justify-content: center; }
.esr-title    { font-size: 1.2rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.esr-sub      { font-size: .78rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }
.esr-impact-row  { display: flex; align-items: flex-start; gap: .75rem; padding: .875rem 1rem; border-radius: 1rem; }
.esr-impact-warn { background: #fffbeb; border: 1px solid #fde68a; }
.esr-impact-ok   { background: #eff6ff; border: 1px solid #bfdbfe; }
.esr-impact-icon { width: 32px; height: 32px; border-radius: .625rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.esr-label { display: block; font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .45rem; margin-left: .25rem; line-height: 1.6; }
.esr-input { width: 100%; border: 2px solid #e5e7eb; border-radius: 1rem; padding: .85rem 1rem; font-size: .875rem; font-weight: 600; color: #1F2937; background: #fff; outline: none; transition: all .2s; }
.esr-input:focus { border-color: #f87171; box-shadow: 0 0 0 4px rgba(239,68,68,.1); }
.esr-input-err { border-color: #fca5a5 !important; background: #fff5f5 !important; }
.esr-input-ok  { border-color: #86efac !important; background: #f0fdf4 !important; }
.esr-hint-err { font-size: .7rem; color: #ef4444; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.esr-hint-ok  { font-size: .7rem; color: #16a34a; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.esr-alert-error { display: flex; align-items: center; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 1rem; padding: .75rem 1rem; color: #dc2626; font-size: .78rem; font-weight: 700; }
.esr-x { position: absolute; top: 1.25rem; right: 1.25rem; width: 36px; height: 36px; background: #fef2f2; border: none; border-radius: 9999px; color: #f87171; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s; }
.esr-x:hover { background: #fee2e2; color: #dc2626; }
@media (max-width: 640px) {
  .esr-card { padding: 1.5rem 1.25rem; }
  .esr-x    { top: 1rem; right: 1rem; }
}
.esr-overlay-enter-active, .esr-overlay-leave-active { transition: opacity .3s ease; }
.esr-overlay-enter-from,   .esr-overlay-leave-to    { opacity: 0; }
.esr-card-enter-active { transition: all .42s cubic-bezier(.34,1.56,.64,1); }
.esr-card-leave-active { transition: all .2s ease; }
.esr-card-enter-from   { opacity: 0; transform: scale(.91) translateY(28px); }
.esr-card-leave-to     { opacity: 0; transform: scale(.96) translateY(10px); }
.esr-fase-enter-active { transition: all .28s cubic-bezier(.34,1.56,.64,1); }
.esr-fase-leave-active { transition: all .16s ease; }
.esr-fase-enter-from   { opacity: 0; transform: translateX(20px); }
.esr-fase-leave-to     { opacity: 0; transform: translateX(-20px); }
.esr-fade-enter-active, .esr-fade-leave-active { transition: all .22s ease; }
.esr-fade-enter-from,   .esr-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
@media (prefers-reduced-motion: reduce) {
  .esr-card-enter-active, .esr-card-leave-active, .esr-fase-enter-active, .esr-fase-leave-active { transition: none; }
}
</style>
