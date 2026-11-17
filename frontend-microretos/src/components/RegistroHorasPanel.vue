<!-- Registro de horas de prácticas de un cronograma (A3), dentro de la agenda. Lo usan el docente
     y DuaLab (Central) en la reunión con el tutor de empresa: alumno/a (alias), fecha, horas y qué
     hizo. El alumnado ve sus horas en su workspace. Emite 'cambio' (miembroId, delta de horas)
     para que la agenda actualice los totales sin recargar. -->
<script setup>
import { ref, computed } from 'vue'
import { getHoras, anotarHoras, borrarHoras } from '../services/agendaService.js'

const props = defineProps({
  cronograma: { type: Object, required: true },
})
const emit = defineEmits(['cambio'])

const abierto = ref(false)
const registros = ref([])
const cargando = ref(false)
const guardando = ref(false)
const error = ref('')
const hoy = new Date().toISOString().slice(0, 10)
const form = ref({ miembro_id: '', fecha: hoy, horas: '', descripcion: '' })

const miembros = computed(() => props.cronograma.grupos.flatMap(g => g.miembros.map(m => ({ ...m, grupo: g.nombre }))))
const valido = computed(() => form.value.miembro_id && form.value.fecha && form.value.fecha <= hoy
  && Number(form.value.horas) >= 0.25 && Number(form.value.horas) <= 24)

async function alternar() {
  abierto.value = !abierto.value
  if (abierto.value && !registros.value.length) {
    cargando.value = true
    try { registros.value = (await getHoras(props.cronograma.id)).data }
    catch { error.value = 'No se han podido cargar las horas.' }
    finally { cargando.value = false }
  }
}

async function guardar() {
  if (!valido.value || guardando.value) return
  guardando.value = true
  error.value = ''
  try {
    const { data } = await anotarHoras(props.cronograma.id, { ...form.value, horas: Number(form.value.horas), descripcion: form.value.descripcion || null })
    registros.value = [data, ...registros.value]
    emit('cambio', data.miembro_id, data.horas)
    form.value = { ...form.value, horas: '', descripcion: '' }
  } catch (e) {
    const errores = e.response?.data?.errors
    error.value = (errores && Object.values(errores)[0]?.[0]) || e.response?.data?.message || 'No se pudieron anotar las horas.'
  } finally {
    guardando.value = false
  }
}

async function borrar(r) {
  if (!confirm(`¿Borrar ${r.horas} h de ${r.alias} del ${r.fecha}?`)) return
  try {
    await borrarHoras(r.id)
    registros.value = registros.value.filter(x => x.id !== r.id)
    emit('cambio', r.miembro_id, -r.horas)
  } catch {
    error.value = 'No se pudo borrar el registro.'
  }
}

const fecha = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
</script>

<template>
  <div class="mt-3 rounded-xl border border-borde">
    <button type="button" @click="alternar" :aria-expanded="abierto"
            class="flex w-full min-h-11 items-center justify-between gap-2 rounded-xl px-3 text-left text-sm font-semibold hover:bg-gray-50">
      <span>Horas de prácticas · {{ cronograma.horas_total }} h anotadas<template v-if="cronograma.plan.horas_estimadas"> (previstas {{ cronograma.plan.horas_estimadas }} h por alumno/a)</template></span>
      <svg class="h-4 w-4 shrink-0 transition-transform" :class="abierto && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <div v-if="abierto" class="space-y-3 border-t border-borde p-3">
      <!-- Anotar (docente / DuaLab, con el tutor de empresa) -->
      <form v-if="miembros.length && cronograma.puede_editar" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="guardar"
            :aria-label="`Anotar horas en ${cronograma.plan.titulo}`">
        <div class="min-w-0 lg:col-span-2">
          <label :for="`h-al-${cronograma.id}`" class="text-xs font-semibold">Alumno/a</label>
          <select :id="`h-al-${cronograma.id}`" v-model="form.miembro_id" class="field-input mt-1" required>
            <option value="" disabled>Elige…</option>
            <option v-for="m in miembros" :key="m.id" :value="m.id">{{ m.alias }} · {{ m.grupo }}</option>
          </select>
        </div>
        <div class="min-w-0">
          <label :for="`h-fe-${cronograma.id}`" class="text-xs font-semibold">Fecha</label>
          <input :id="`h-fe-${cronograma.id}`" v-model="form.fecha" type="date" :max="hoy" class="field-input mt-1" required />
        </div>
        <div class="min-w-0">
          <label :for="`h-ho-${cronograma.id}`" class="text-xs font-semibold">Horas</label>
          <input :id="`h-ho-${cronograma.id}`" v-model="form.horas" type="number" min="0.25" max="24" step="0.25" inputmode="decimal"
                 class="field-input mt-1" placeholder="5" required />
        </div>
        <div class="min-w-0 sm:col-span-2 lg:col-span-4">
          <label :for="`h-de-${cronograma.id}`" class="text-xs font-semibold">Qué hizo (opcional)</label>
          <input :id="`h-de-${cronograma.id}`" v-model="form.descripcion" type="text" maxlength="500" class="field-input mt-1"
                 placeholder="Por ejemplo: montaje del tablero de tareas" />
        </div>
        <div class="flex items-end">
          <button type="submit" :disabled="!valido || guardando"
                  class="inline-flex h-10 w-full items-center justify-center rounded-full bg-rol-fuerte px-4 text-sm font-bold text-white disabled:opacity-50 sm:w-auto">
            {{ guardando ? 'Guardando…' : 'Anotar horas' }}
          </button>
        </div>
      </form>
      <p v-else-if="!miembros.length" class="text-sm text-texto-secundario">Genera los grupos del cronograma para poder anotar horas.</p>

      <p v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ error }}</p>
      <p v-if="cargando" class="text-sm text-texto-secundario" role="status">Cargando…</p>

      <!-- Registros -->
      <ul v-else-if="registros.length" class="max-h-72 divide-y divide-borde overflow-y-auto rounded-xl border border-borde">
        <li v-for="r in registros" :key="r.id" class="flex items-start gap-3 px-3 py-2 text-sm">
          <div class="min-w-0 flex-1">
            <p class="font-semibold"><span class="break-words">{{ r.alias }}</span> · {{ r.horas }} h · {{ fecha(r.fecha) }}</p>
            <p v-if="r.descripcion" class="break-words text-xs text-texto-secundario">{{ r.descripcion }}</p>
            <p v-if="r.registrado_por" class="text-[11px] text-texto-secundario">Anotado por {{ r.registrado_por }}</p>
          </div>
          <button v-if="cronograma.puede_editar" type="button" @click="borrar(r)" :aria-label="`Borrar el registro de ${r.alias} del ${r.fecha}`"
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-texto-secundario hover:bg-red-50 hover:text-red-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </li>
      </ul>
      <p v-else-if="!cargando" class="text-sm text-texto-secundario">Aún no hay horas anotadas.</p>
    </div>
  </div>
</template>
