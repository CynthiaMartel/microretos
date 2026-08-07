<script setup>
import { ref, watch } from 'vue'
import api from '../api.js'

const props = defineProps({
  visible:   { type: Boolean, default: false },
  encuentro: { type: Object,  default: null  },
})
const emit = defineEmits(['cerrar'])

const cargando      = ref(false)
const colaboradores = ref([])
const candidatos     = ref([])
const candidatoId    = ref('')
const puedeEditarNuevo = ref(false)
const enviando       = ref(false)
const error          = ref('')

watch(() => props.visible, (v) => {
  if (v && props.encuentro?.id) cargar()
})

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const [resColab, resCand] = await Promise.all([
      api.get(`/encuentros/${props.encuentro.id}/colaboradores`),
      api.get(`/encuentros/${props.encuentro.id}/colaboradores/candidatos`),
    ])
    colaboradores.value = resColab.data
    candidatos.value    = resCand.data
    candidatoId.value   = ''
    puedeEditarNuevo.value = false
  } catch (e) {
    error.value = 'No se pudieron cargar los colaboradores.'
  } finally {
    cargando.value = false
  }
}

async function anadirColaborador() {
  if (!candidatoId.value || enviando.value) return
  enviando.value = true
  error.value = ''
  try {
    const res = await api.post(`/encuentros/${props.encuentro.id}/colaboradores`, {
      user_id: candidatoId.value,
      puede_editar: puedeEditarNuevo.value,
    })
    colaboradores.value = res.data
    candidatos.value = candidatos.value.filter(c => String(c.id) !== String(candidatoId.value))
    candidatoId.value = ''
    puedeEditarNuevo.value = false
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.errors?.user_id?.[0] || 'No se pudo añadir al colaborador.'
  } finally {
    enviando.value = false
  }
}

async function cambiarPermiso(colaborador) {
  const nuevoValor = !colaborador.puede_editar
  try {
    await api.patch(`/encuentros/${props.encuentro.id}/colaboradores/${colaborador.id}`, {
      puede_editar: nuevoValor,
    })
    colaborador.puede_editar = nuevoValor
  } catch (e) {
    error.value = 'No se pudo actualizar el permiso.'
  }
}

async function quitarColaborador(colaborador) {
  try {
    await api.delete(`/encuentros/${props.encuentro.id}/colaboradores/${colaborador.id}`)
    colaboradores.value = colaboradores.value.filter(c => c.id !== colaborador.id)
    candidatos.value.push({ id: colaborador.id, name: colaborador.name })
  } catch (e) {
    error.value = 'No se pudo quitar al colaborador.'
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="cem-overlay">
      <div v-if="visible" class="cem-overlay" @click.self="$emit('cerrar')">
        <Transition name="cem-card">
          <div v-if="visible" class="cem-card">

            <div class="cem-header">
              <div class="cem-icon-box">
                <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="cem-title">Compartir encuentro</h2>
                <p class="cem-sub">Solo docentes de tu centro con acceso explícito pueden ver este encuentro</p>
              </div>
            </div>

            <div class="mt-5 px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100">
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-0.5">Encuentro</p>
              <p class="font-black text-[#1F2937] text-sm leading-snug">{{ encuentro?.microreto_titulo || `Encuentro #${encuentro?.id}` }}</p>
            </div>

            <div v-if="cargando" class="mt-6 text-center text-xs text-gray-400 py-6">Cargando...</div>

            <template v-else>
              <div class="mt-5">
                <p class="cem-label">Colaboradores actuales</p>
                <div v-if="!colaboradores.length" class="text-xs text-gray-400 py-3">
                  Todavía no has compartido este encuentro con nadie.
                </div>
                <div v-for="c in colaboradores" :key="c.id" class="cem-colab-row">
                  <span class="font-bold text-sm text-[#1F2937] flex-1 truncate">{{ c.name }}</span>
                  <button type="button" @click="cambiarPermiso(c)"
                          class="cem-toggle" :class="c.puede_editar ? 'cem-toggle-on' : ''">
                    {{ c.puede_editar ? 'Puede editar' : 'Solo ver' }}
                  </button>
                  <button type="button" @click="quitarColaborador(c)" class="cem-remove" title="Quitar acceso">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </div>

              <div class="mt-5 pt-4 border-t border-gray-100">
                <p class="cem-label">Añadir colaborador</p>
                <div v-if="!candidatos.length" class="text-xs text-gray-400 py-2">
                  No hay más docentes de tu centro disponibles para añadir.
                </div>
                <div v-else class="space-y-2.5">
                  <select v-model="candidatoId" class="cem-select">
                    <option value="" disabled>Selecciona un docente...</option>
                    <option v-for="c in candidatos" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                  <label class="cem-checkbox-row">
                    <input type="checkbox" v-model="puedeEditarNuevo" class="cem-checkbox" />
                    <span>Puede editar (reestructurar equipos, generar códigos, eliminar)</span>
                  </label>
                  <button type="button" @click="anadirColaborador" :disabled="!candidatoId || enviando"
                          class="cem-btn-primary w-full">
                    {{ enviando ? 'Añadiendo...' : 'Compartir con este docente' }}
                  </button>
                </div>
              </div>

              <Transition name="cem-fade">
                <div v-if="error" class="cem-alert-error mt-3">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span>{{ error }}</span>
                </div>
              </Transition>
            </template>

            <button type="button" class="cem-x" @click="$emit('cerrar')">
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
.cem-overlay {
  position: fixed; inset: 0; background: rgba(10,10,10,.85);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  display: flex; align-items: center; justify-content: center;
  z-index: 10001; padding: 1rem;
}
.cem-card {
  position: relative; background: #fff; border: 1px solid #fde68a;
  border-radius: 2rem; padding: 2.25rem; width: 100%; max-width: 480px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 0 0 1px rgba(245,158,11,.08), 0 24px 48px rgba(0,0,0,.2), 0 0 80px rgba(245,158,11,.06);
  scrollbar-width: thin; scrollbar-color: #fde68a transparent;
}
.cem-card::-webkit-scrollbar { width: 5px; }
.cem-card::-webkit-scrollbar-thumb { background: #fde68a; border-radius: 3px; }

.cem-header   { display: flex; align-items: flex-start; gap: 1rem; }
.cem-icon-box { flex-shrink: 0; width: 52px; height: 52px; background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 1rem; display: flex; align-items: center; justify-content: center; }
.cem-title    { font-size: 1.2rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.cem-sub      { font-size: .78rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }

.cem-label { font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .5rem; }

.cem-colab-row {
  display: flex; align-items: center; gap: .6rem; padding: .6rem .75rem;
  border-radius: .9rem; background: #f9fafb; border: 1px solid #f3f4f6; margin-bottom: .5rem;
}
.cem-toggle {
  font-size: .65rem; font-weight: 900; text-transform: uppercase; letter-spacing: .08em;
  padding: .35rem .6rem; border-radius: .6rem; background: #f3f4f6; color: #6b7280;
  border: none; cursor: pointer; white-space: nowrap; transition: all .15s;
}
.cem-toggle-on { background: #d1fae5; color: #047857; }
.cem-remove {
  width: 26px; height: 26px; border-radius: .5rem; background: #fef2f2; color: #f87171;
  border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .15s;
}
.cem-remove:hover { background: #fee2e2; color: #dc2626; }

.cem-select {
  width: 100%; border: 2px solid #e5e7eb; border-radius: 1rem; padding: .75rem 1rem;
  font-size: .85rem; font-weight: 600; color: #1F2937; background: #fff; outline: none;
}
.cem-select:focus { border-color: #fbbf24; box-shadow: 0 0 0 4px rgba(251,191,36,.12); }
.cem-checkbox-row { display: flex; align-items: flex-start; gap: .55rem; font-size: .78rem; color: #4b5563; font-weight: 600; cursor: pointer; }
.cem-checkbox { margin-top: .15rem; width: 16px; height: 16px; accent-color: #f59e0b; cursor: pointer; }

.cem-btn-primary {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: .9rem 1.5rem; background: #f59e0b; color: #fff; border: none;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .12em;
  text-transform: uppercase; cursor: pointer; box-shadow: 0 6px 20px rgba(245,158,11,.25); transition: all .2s;
}
.cem-btn-primary:hover:not(:disabled)  { background: #d97706; transform: translateY(-1px); }
.cem-btn-primary:disabled              { opacity: .4; cursor: not-allowed; }

.cem-alert-error { display: flex; align-items: center; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 1rem; padding: .75rem 1rem; color: #dc2626; font-size: .78rem; font-weight: 700; }

.cem-x {
  position: absolute; top: 1.25rem; right: 1.25rem; width: 32px; height: 32px;
  background: #fffbeb; border: none; border-radius: .5rem; color: #f59e0b;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s;
}
.cem-x:hover { background: #fef3c7; color: #d97706; }

.cem-overlay-enter-active, .cem-overlay-leave-active { transition: opacity .3s ease; }
.cem-overlay-enter-from,   .cem-overlay-leave-to    { opacity: 0; }
.cem-card-enter-active  { transition: all .42s cubic-bezier(.34,1.56,.64,1); }
.cem-card-leave-active  { transition: all .2s ease; }
.cem-card-enter-from    { opacity: 0; transform: scale(.91) translateY(28px); }
.cem-card-leave-to      { opacity: 0; transform: scale(.96) translateY(10px); }
.cem-fade-enter-active, .cem-fade-leave-active { transition: all .22s ease; }
.cem-fade-enter-from,   .cem-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>
