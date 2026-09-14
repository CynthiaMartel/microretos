<script setup>
import { ref, computed, watch } from 'vue'
import api from '../api.js'

const props = defineProps({
  visible:   { type: Boolean, default: false },
  encuentro: { type: Object,  default: null  },
})
const emit = defineEmits(['cerrar', 'actualizado'])

const numEquipos      = ref(3)
const alumnados       = ref([])
const nuevoNombre     = ref('')
const nuevoEquipo     = ref(1)
const guardando       = ref(false)
const error           = ref('')

watch(() => props.visible, (v) => {
  if (v && props.encuentro) {
    numEquipos.value  = props.encuentro.num_equipos || 3
    // Copia propia — no se toca el objeto del padre hasta guardar con éxito.
    // Se parte de encuentro.equipos (tiene el id real de equipo_miembros, necesario
    // para poder renombrar sin que el backend lo trate como borrar+crear) y, si un
    // encuentro antiguo no lo trae, se cae al snapshot plano `alumnados` (sin id).
    const equipos = props.encuentro.equipos
    if (equipos?.length) {
      alumnados.value = equipos.flatMap(e =>
        e.miembros.map(m => ({
          id: m.id, nombre: m.nombre, alias: m.alias, equipo_num: e.numero_equipo, rol: m.rol,
          // El equipo ya pulsó "Confirmar nombres" en su F0: el nombre real ya no se puede tocar aquí.
          bloqueado: !!e.nombres_confirmados,
        }))
      )
    } else {
      alumnados.value = (props.encuentro.alumnados || []).map(a => ({ ...a, bloqueado: false }))
    }
    nuevoNombre.value = ''
    nuevoEquipo.value = 1
    error.value       = ''
    guardando.value   = false
  }
})

function addAlumno() {
  const nombre = nuevoNombre.value.trim()
  if (!nombre) return
  alumnados.value.push({ nombre, equipo_num: nuevoEquipo.value })
  nuevoNombre.value = ''
}
function removeAlumno(i) { alumnados.value.splice(i, 1) }

function alumnadosDeEquipo(n) {
  return alumnados.value
    .map((a, i) => ({ ...a, _i: i }))
    .filter(a => a.equipo_num === n)
}
const alumnadosSinEquipo = computed(() =>
  alumnados.value
    .map((a, i) => ({ ...a, _i: i }))
    .filter(a => !a.equipo_num || a.equipo_num < 1)
)

async function guardar() {
  if (guardando.value) return
  guardando.value = true
  error.value = ''
  try {
    const res = await api.patch(`/encuentros/${props.encuentro.id}/reestructurar-equipos`, {
      num_equipos: numEquipos.value,
      alumnados:   alumnados.value,
    })
    emit('actualizado', {
      id: props.encuentro.id,
      num_equipos: numEquipos.value,
      alumnados: alumnados.value,
      equipos: res.data.equipos,
    })
  } catch (e) {
    error.value = e.response?.data?.error || 'Error al reestructurar los equipos.'
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="req-overlay">
      <div v-if="visible" class="req-overlay" @click.self="$emit('cerrar')">
        <Transition name="req-card">
          <div v-if="visible" class="req-card">

            <div class="req-header">
              <div class="req-icon-box">
                <svg class="w-7 h-7 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="req-title">Editar equipo</h2>
                <p class="req-sub">
                  Cambia el reparto de alumnado sin perder el progreso ya hecho — se actualiza,
                  no se borra y recrea.
                </p>
              </div>
            </div>

            <div class="mt-4 px-4 py-2.5 bg-slate-50 rounded-2xl border border-slate-100 text-xs text-slate-500">
              Si algún equipo tiene progreso real (fases completadas, tareas, reflexiones o
              prototipos) y reduces el número de equipos hasta eliminarlo, se bloqueará el
              guardado explicando cuál.
            </div>

            <div class="mt-2 px-4 py-2.5 bg-emerald-50 rounded-2xl border border-emerald-100 text-xs text-emerald-700 flex items-start gap-2">
              <span class="shrink-0">🔒</span>
              <span>Creamos un alias automático para cada alumno/a (protección de datos) — el
              nombre real que escribas aquí no se muestra fuera del equipo ni del panel docente.</span>
            </div>

            <!-- Número de equipos -->
            <div class="mt-5">
              <label class="req-label">Número de equipos</label>
              <div class="flex items-center gap-2 mt-1">
                <button type="button" @click="numEquipos = Math.max(1, numEquipos - 1)"
                        class="w-8 h-8 rounded-xl bg-gray-100 border border-gray-200 text-gray-600
                               font-black text-sm hover:bg-gray-200 transition-all">−</button>
                <span class="w-8 text-center text-base font-black text-[#1F2937]">{{ numEquipos }}</span>
                <button type="button" @click="numEquipos = Math.min(30, numEquipos + 1)"
                        class="w-8 h-8 rounded-xl bg-gray-100 border border-gray-200 text-gray-600
                               font-black text-sm hover:bg-gray-200 transition-all">+</button>
              </div>
            </div>

            <!-- Alumnado por equipos -->
            <div class="mt-5 space-y-3">
              <label class="req-label">Alumnado por equipos</label>

              <div class="flex flex-wrap gap-2">
                <input v-model="nuevoNombre" type="text" placeholder="Nombre del alumno/a"
                       class="req-input flex-1 min-w-32 !text-sm"
                       @keyup.enter="addAlumno" />
                <select v-model="nuevoEquipo" class="req-input !w-auto pr-8 cursor-pointer !text-sm">
                  <option v-for="n in numEquipos" :key="n" :value="n">Equipo {{ n }}</option>
                </select>
                <button type="button" @click="addAlumno"
                        class="shrink-0 px-3 py-2 bg-violet-500 text-white rounded-xl
                               text-xs font-black hover:bg-violet-600 transition-all">
                  + Añadir
                </button>
              </div>

              <div class="grid grid-cols-2 gap-2">
                <div v-for="n in numEquipos" :key="n"
                     class="rounded-xl border border-gray-100 bg-gray-50/50 p-3">
                  <div class="flex items-center gap-1.5 mb-2">
                    <span class="w-5 h-5 rounded-full bg-violet-100 flex items-center justify-center
                                 text-[9px] font-black text-violet-600 flex-shrink-0">{{ n }}</span>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 flex-1 truncate">
                      Equipo {{ n }}
                    </p>
                    <span class="text-[9px] text-gray-400 flex-shrink-0">{{ alumnadosDeEquipo(n).length }}</span>
                  </div>
                  <div v-if="alumnadosDeEquipo(n).length" class="space-y-1">
                    <div v-for="a in alumnadosDeEquipo(n)" :key="a._i" class="flex items-center gap-1 text-xs">
                      <span v-if="a.bloqueado" title="Ya confirmó su nombre en el workspace — no se puede editar"
                            class="shrink-0 text-gray-300">🔒</span>
                      <input v-model="alumnados[a._i].nombre" type="text" maxlength="100" :disabled="a.bloqueado"
                             :title="a.bloqueado ? 'Ya confirmó su nombre en el workspace — no se puede editar' : ''"
                             class="flex-1 min-w-0 truncate font-medium text-[#1F2937] bg-transparent
                                    border border-transparent hover:border-gray-200 focus:border-violet-300
                                    focus:bg-white rounded px-1 py-0.5 outline-none transition-colors
                                    disabled:text-gray-400 disabled:hover:border-transparent disabled:cursor-not-allowed" />
                      <span v-if="a.alias" class="shrink-0 text-[10px] text-gray-400 truncate max-w-[80px]">{{ a.alias }}</span>
                      <button type="button" @click="removeAlumno(a._i)"
                              class="text-gray-300 hover:text-red-400 font-black transition-colors
                                     text-sm leading-none flex-shrink-0">×</button>
                    </div>
                  </div>
                  <p v-else class="text-[10px] text-gray-300 italic">Sin alumnos</p>
                </div>
              </div>

              <div v-if="alumnadosSinEquipo.length"
                   class="rounded-xl border border-amber-100 bg-amber-50/50 p-3">
                <p class="text-[10px] font-black uppercase tracking-widest text-amber-500 mb-2">
                  Sin equipo asignado
                </p>
                <div class="space-y-1">
                  <div v-for="a in alumnadosSinEquipo" :key="a._i" class="flex items-center gap-1 text-xs">
                    <span v-if="a.bloqueado" title="Ya confirmó su nombre en el workspace — no se puede editar"
                          class="shrink-0 text-amber-300">🔒</span>
                    <input v-model="alumnados[a._i].nombre" type="text" maxlength="100" :disabled="a.bloqueado"
                           :title="a.bloqueado ? 'Ya confirmó su nombre en el workspace — no se puede editar' : ''"
                           class="flex-1 min-w-0 truncate font-medium text-amber-700 bg-transparent
                                  border border-transparent hover:border-amber-200 focus:border-amber-400
                                  focus:bg-white rounded px-1 py-0.5 outline-none transition-colors
                                  disabled:text-amber-400/60 disabled:hover:border-transparent disabled:cursor-not-allowed" />
                    <span v-if="a.alias" class="shrink-0 text-[10px] text-amber-400/80 truncate max-w-[80px]">{{ a.alias }}</span>
                    <button type="button" @click="removeAlumno(a._i)"
                            class="text-amber-300 hover:text-red-400 font-black transition-colors
                                   text-sm leading-none flex-shrink-0">×</button>
                  </div>
                </div>
              </div>
            </div>

            <Transition name="req-fade">
              <div v-if="error" class="req-alert-error mt-4">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ error }}</span>
              </div>
            </Transition>

            <div class="req-actions">
              <button type="button" @click="$emit('cerrar')" class="req-btn-ghost flex-1">Cancelar</button>
              <button type="button" @click="guardar" :disabled="guardando || !alumnados.length"
                      class="req-btn-primary flex-[2]">
                <svg v-if="guardando" class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                </svg>
                {{ guardando ? 'Guardando...' : 'Guardar reparto' }}
              </button>
            </div>

            <button type="button" class="req-x" @click="$emit('cerrar')">
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
.req-overlay {
  position: fixed; inset: 0; background: rgba(10,10,10,.85);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  display: flex; align-items: center; justify-content: center;
  z-index: 10001; padding: 1rem;
}
.req-card {
  position: relative; background: #fff; border: 1px solid #ddd6fe;
  border-radius: 2rem; padding: 2.25rem; width: 100%; max-width: 560px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 0 0 1px rgba(139,92,246,.08), 0 24px 48px rgba(0,0,0,.2), 0 0 80px rgba(139,92,246,.06);
  scrollbar-width: thin; scrollbar-color: #ddd6fe transparent;
}
.req-card::-webkit-scrollbar { width: 5px; }
.req-card::-webkit-scrollbar-thumb { background: #ddd6fe; border-radius: 3px; }

.req-header   { display: flex; align-items: flex-start; gap: 1rem; }
.req-icon-box { flex-shrink: 0; width: 52px; height: 52px; background: #f5f3ff; border: 1.5px solid #ddd6fe; border-radius: 1rem; display: flex; align-items: center; justify-content: center; }
.req-title    { font-size: 1.2rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.req-sub      { font-size: .78rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }

.req-label { display: block; font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .45rem; margin-left: .25rem; line-height: 1.6; }
.req-input {
  border: 2px solid #e5e7eb; border-radius: .8rem; padding: .6rem .9rem;
  font-size: .875rem; font-weight: 600; color: #1F2937; background: #fff; outline: none; transition: all .2s;
}
.req-input:focus { border-color: #a78bfa; box-shadow: 0 0 0 4px rgba(139,92,246,.1); }

.req-alert-error { display: flex; align-items: center; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 1rem; padding: .75rem 1rem; color: #dc2626; font-size: .78rem; font-weight: 700; }

.req-actions { display: flex; gap: .75rem; margin-top: 1.5rem; }
.req-btn-primary {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: 1rem 1.5rem; background: #7c3aed; color: #fff; border: none;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; box-shadow: 0 6px 20px rgba(124,58,237,.3); transition: all .2s;
}
.req-btn-primary:hover:not(:disabled)  { background: #6d28d9; transform: translateY(-1px); }
.req-btn-primary:active:not(:disabled) { transform: scale(.97); }
.req-btn-primary:disabled              { opacity: .4; cursor: not-allowed; }
.req-btn-ghost {
  display: flex; align-items: center; justify-content: center; gap: .4rem;
  padding: 1rem 1.5rem; background: #fff; color: #6b7280; border: 2px solid #e5e7eb;
  border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em;
  text-transform: uppercase; cursor: pointer; transition: all .2s;
}
.req-btn-ghost:hover  { border-color: #d1d5db; color: #374151; }
.req-btn-ghost:active { transform: scale(.97); }
.req-x {
  position: absolute; top: 1.25rem; right: 1.25rem; width: 32px; height: 32px;
  background: #f5f3ff; border: none; border-radius: .5rem; color: #a78bfa;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s;
}
.req-x:hover { background: #ede9fe; color: #7c3aed; }

.req-overlay-enter-active, .req-overlay-leave-active { transition: opacity .3s ease; }
.req-overlay-enter-from,   .req-overlay-leave-to    { opacity: 0; }
.req-card-enter-active  { transition: all .42s cubic-bezier(.34,1.56,.64,1); }
.req-card-leave-active  { transition: all .2s ease; }
.req-card-enter-from    { opacity: 0; transform: scale(.91) translateY(28px); }
.req-card-leave-to      { opacity: 0; transform: scale(.96) translateY(10px); }
.req-fade-enter-active, .req-fade-leave-active { transition: all .22s ease; }
.req-fade-enter-from,   .req-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>
