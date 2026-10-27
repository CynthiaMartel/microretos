<script setup>
/**
 * EliminarEmpresaModal.vue
 * Eliminación de empresa con doble confirmación:
 *   Fase 1 — impacto y advertencias antes de comprometerse.
 *   Fase 2 — escritura del nombre exacto para desbloquear el borrado.
 *
 * Props:
 *   visible   Boolean
 *   empresa   Object  — el objeto empresa completo (id, nombre_comercial, familias_nombres, …)
 *
 * Emits:
 *   @empresa-eliminada  { id, nombre }
 *   @cerrar
 */
import { ref, computed, watch } from 'vue'
import api from '../api.js'

const props = defineProps({
  visible: { type: Boolean, default: false },
  empresa: { type: Object,  default: null  },
})
const emit = defineEmits(['empresa-eliminada', 'cerrar'])

const fase           = ref(1)
const confirmNombre  = ref('')
const eliminando     = ref(false)
const error          = ref('')

// microretos_count llega de /empresas/dashboard; sin él, el texto genérico
const textoRetos = computed(() => {
  const n = props.empresa?.microretos_count
  if (n == null) return 'Los retos generados permanecerán en la biblioteca'
  if (n === 0) return 'Esta empresa no tiene retos'
  return n === 1 ? 'Su reto seguirá en la biblioteca' : `Sus ${n} retos seguirán en la biblioteca`
})

const nombreValido = computed(() =>
  confirmNombre.value === props.empresa?.nombre_comercial
)

watch(() => props.visible, (v) => {
  if (v) {
    fase.value          = 1
    confirmNombre.value = ''
    eliminando.value    = false
    error.value         = ''
  }
})

function avanzarFase2() {
  fase.value = 2
  setTimeout(() => document.getElementById('eem-confirm-input')?.focus(), 150)
}

async function confirmarEliminacion() {
  if (!nombreValido.value || eliminando.value) return
  eliminando.value = true
  error.value = ''
  try {
    await api.delete(`/empresas/${props.empresa.id}`)
    emit('empresa-eliminada', { id: props.empresa.id, nombre: props.empresa.nombre_comercial })
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'La empresa no se encontró. Es posible que ya haya sido eliminada.'
    } else if (e.response?.status === 401) {
      error.value = 'Tu sesión ha expirado. Vuelve a iniciar sesión e inténtalo de nuevo.'
    } else {
      error.value = 'Error al eliminar la empresa. Inténtalo de nuevo.'
    }
  } finally {
    eliminando.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="eem-overlay">
      <div v-if="visible" class="eem-overlay" @click.self="$emit('cerrar')">
        <Transition name="eem-card">
          <div v-if="visible" class="eem-card">

            <!-- Cabecera -->
            <div class="eem-header">
              <div class="eem-icon-box">
                <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1z"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="eem-title">Eliminar empresa</h2>
                <p class="eem-sub">La empresa se moverá a la <span class="font-black text-amber-600">papelera</span> y podrá restaurarse desde allí</p>
              </div>
            </div>

            <!-- Identificación de la empresa -->
            <div class="mt-5 px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-0.5">Empresa a eliminar</p>
              <p class="font-black text-[#1F2937] text-base truncate">{{ empresa?.nombre_comercial }}</p>
              <div v-if="empresa?.familias_nombres?.length" class="flex flex-wrap gap-1 mt-2">
                <span
                  v-for="f in empresa.familias_nombres" :key="f"
                  class="text-[10px] font-bold bg-centros/10 text-centros px-2 py-0.5 rounded-full"
                >{{ f }}</span>
              </div>
            </div>

            <!-- ═══ FASE 1 ═══ -->
            <Transition name="eem-fase" mode="out-in">
              <div v-if="fase === 1" key="f1" class="mt-5 space-y-3">

                <p class="text-sm font-semibold text-gray-600">Al eliminar esta empresa ocurrirá lo siguiente:</p>

                <!-- Movido a papelera -->
                <div class="eem-impact-row eem-impact-warn">
                  <div class="eem-impact-icon bg-amber-100">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-amber-800">La empresa se moverá a la papelera</p>
                    <p class="text-xs text-amber-600 mt-0.5">Podrás recuperarla desde la sección Papelera. Sus datos (CIF, contacto, sector, etc.) se preservarán.</p>
                  </div>
                </div>

                <!-- Relaciones de familia -->
                <div class="eem-impact-row eem-impact-warn">
                  <div class="eem-impact-icon bg-amber-100">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-amber-800">Sus asociaciones de familias profesionales serán eliminadas</p>
                    <p class="text-xs text-amber-600 mt-0.5">
                      {{ empresa?.familias_nombres?.length
                        ? `Familias afectadas: ${empresa.familias_nombres.join(', ')}.`
                        : 'No tiene familias asociadas.' }}
                    </p>
                  </div>
                </div>

                <!-- Microretos -->
                <div class="eem-impact-row eem-impact-ok">
                  <div class="eem-impact-icon bg-blue-50">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="font-black text-sm text-blue-700">{{ textoRetos }}</p>
                    <p class="text-xs text-blue-500 mt-0.5">
                      No se eliminan: el centro los seguirá viendo, marcados como «empresa en la papelera»
                      (y «empresa eliminada» si se borra definitivamente).
                    </p>
                  </div>
                </div>

                <div class="fila-pasos mt-2">
                  <button type="button" @click="$emit('cerrar')" class="btn-paso-atras">Cancelar</button>
                  <button type="button" @click="avanzarFase2" class="btn-paso border-2 border-red-300 bg-white text-red-600 shadow-none hover:border-red-400 hover:bg-red-50">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span class="sm:hidden">Continuar</span><span class="hidden sm:inline">Entiendo, quiero eliminarla</span>
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
                    Escribe el nombre exacto de la empresa para confirmar que no es un error accidental.
                  </p>
                </div>

                <div>
                  <label class="eem-label">
                    Escribe <span class="font-black text-[#1F2937] normal-case tracking-normal">{{ empresa?.nombre_comercial }}</span> para confirmar
                  </label>
                  <input
                    id="eem-confirm-input"
                    v-model="confirmNombre"
                    class="eem-input"
                    :class="confirmNombre && !nombreValido ? 'eem-input-err' : confirmNombre && nombreValido ? 'eem-input-ok' : ''"
                    placeholder="Escribe el nombre exacto..."
                    autocomplete="off"
                    @keydown.enter.prevent="nombreValido && !eliminando && confirmarEliminacion()"
                  />
                  <p v-if="confirmNombre && !nombreValido" class="eem-hint-err">
                    El nombre no coincide. Comprueba mayúsculas, espacios y caracteres especiales.
                  </p>
                  <p v-if="confirmNombre && nombreValido" class="eem-hint-ok">
                    Nombre confirmado. Ya puedes mover la empresa a la papelera.
                  </p>
                </div>

                <Transition name="eem-fade">
                  <div v-if="error" class="eem-alert-error">
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

            <button type="button" class="eem-x" @click="$emit('cerrar')" aria-label="Cerrar">
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
.eem-overlay {
  position: fixed; inset: 0; background: rgba(10,10,10,.85);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  display: flex; align-items: center; justify-content: center;
  z-index: 10001; padding: 1rem;
}
.eem-card {
  position: relative; background: #fff; border: 1px solid #fecaca;
  border-radius: 2rem; padding: 2.25rem; width: 100%; max-width: 520px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 0 0 1px rgba(220,38,38,.08), 0 24px 48px rgba(0,0,0,.2), 0 0 80px rgba(220,38,38,.06);
  scrollbar-width: thin; scrollbar-color: #fecaca transparent;
}
.eem-card::-webkit-scrollbar { width: 5px; }
.eem-card::-webkit-scrollbar-thumb { background: #fecaca; border-radius: 3px; }

.eem-header   { display: flex; align-items: flex-start; gap: 1rem; padding-right: 2.5rem; }
.eem-icon-box { flex-shrink: 0; width: 52px; height: 52px; background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 1rem; display: flex; align-items: center; justify-content: center; }
.eem-title    { font-size: 1.2rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.eem-sub      { font-size: .78rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }

.eem-impact-row    { display: flex; align-items: flex-start; gap: .75rem; padding: .875rem 1rem; border-radius: 1rem; }
.eem-impact-danger { background: #fef2f2; border: 1px solid #fecaca; }
.eem-impact-warn   { background: #fffbeb; border: 1px solid #fde68a; }
.eem-impact-ok     { background: #eff6ff; border: 1px solid #bfdbfe; }
.eem-impact-icon   { width: 32px; height: 32px; border-radius: .625rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.eem-label { display: block; font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .45rem; margin-left: .25rem; line-height: 1.6; }
.eem-input {
  width: 100%; border: 2px solid #e5e7eb; border-radius: 1rem; padding: .85rem 1rem;
  font-size: .875rem; font-weight: 600; color: #1F2937; background: #fff; outline: none; transition: all .2s;
}
.eem-input:focus     { border-color: #f87171; box-shadow: 0 0 0 4px rgba(239,68,68,.1); }
.eem-input-err       { border-color: #fca5a5 !important; background: #fff5f5 !important; }
.eem-input-ok        { border-color: #86efac !important; background: #f0fdf4 !important; }
.eem-hint-err { font-size: .7rem; color: #ef4444; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.eem-hint-ok  { font-size: .7rem; color: #16a34a; font-weight: 700; margin-top: .4rem; margin-left: .25rem; }
.eem-alert-error { display: flex; align-items: center; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 1rem; padding: .75rem 1rem; color: #dc2626; font-size: .78rem; font-weight: 700; }

.eem-x {
  position: absolute; top: 1.25rem; right: 1.25rem; width: 36px; height: 36px;
  background: #fef2f2; border: none; border-radius: 9999px; color: #f87171;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s;
}
.eem-x:hover { background: #fee2e2; color: #dc2626; }

@media (max-width: 640px) {
  .eem-card { padding: 1.5rem 1.25rem; }
  .eem-x    { top: 1rem; right: 1rem; }
}

.eem-overlay-enter-active, .eem-overlay-leave-active { transition: opacity .3s ease; }
.eem-overlay-enter-from,   .eem-overlay-leave-to    { opacity: 0; }
.eem-card-enter-active  { transition: all .42s cubic-bezier(.34,1.56,.64,1); }
.eem-card-leave-active  { transition: all .2s ease; }
.eem-card-enter-from    { opacity: 0; transform: scale(.91) translateY(28px); }
.eem-card-leave-to      { opacity: 0; transform: scale(.96) translateY(10px); }
.eem-fase-enter-active  { transition: all .28s cubic-bezier(.34,1.56,.64,1); }
.eem-fase-leave-active  { transition: all .16s ease; }
.eem-fase-enter-from    { opacity: 0; transform: translateX(20px); }
.eem-fase-leave-to      { opacity: 0; transform: translateX(-20px); }
.eem-fade-enter-active, .eem-fade-leave-active { transition: all .22s ease; }
.eem-fade-enter-from,   .eem-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>
