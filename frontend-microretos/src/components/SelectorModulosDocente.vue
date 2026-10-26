<!-- Selector de los módulos que imparte un docente: ciclo de la oferta de su centro → módulos
     por curso (píldoras). Lo usan «Mi usuario» y la gestión de usuarios del admin.
     `oferta`: [{ id, nombre, familia, modulos: [{ id, nombre, curso, codigo }] }] (GET …/modulos).
     v-model: array de ids de módulo. -->
<script setup>
import { ref, computed, watch } from 'vue'

const seleccion = defineModel({ type: Array, default: () => [] })

const props = defineProps({
  oferta:   { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

// Ciclo abierto: el primero que ya tenga módulos elegidos, o el primero de la oferta
const cicloId = ref(null)
watch(() => props.oferta, (oferta) => {
  if (cicloId.value && oferta.some(c => c.id === cicloId.value)) return
  const conSeleccion = oferta.find(c => c.modulos.some(m => seleccion.value.includes(m.id)))
  cicloId.value = (conSeleccion || oferta[0])?.id ?? null
}, { immediate: true })

const ciclo = computed(() => props.oferta.find(c => c.id === cicloId.value))

const modulosPorCurso = computed(() => {
  const grupos = new Map()
  for (const m of ciclo.value?.modulos || []) {
    const curso = m.curso ? `${m.curso}º curso` : 'Sin curso'
    if (!grupos.has(curso)) grupos.set(curso, [])
    grupos.get(curso).push(m)
  }
  return [...grupos.entries()]
})

const todos = computed(() => props.oferta.flatMap(c => c.modulos.map(m => ({ ...m, ciclo: c.nombre }))))
const elegidos = computed(() => todos.value.filter(m => seleccion.value.includes(m.id)))
const elegidosEnCiclo = (c) => c.modulos.filter(m => seleccion.value.includes(m.id)).length

function alternar(id) {
  if (props.disabled) return
  seleccion.value = seleccion.value.includes(id)
    ? seleccion.value.filter(x => x !== id)
    : [...seleccion.value, id]
}
</script>

<template>
  <div class="space-y-3">
    <p v-if="!oferta.length" class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">
      El centro aún no tiene ciclos configurados. Pide al administrador que añada su oferta formativa.
    </p>

    <template v-else>
      <label class="block">
        <span class="mb-1 block text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">Ciclo</span>
        <select v-model="cicloId" :disabled="disabled"
                class="w-full cursor-pointer rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700
                       focus:outline-none focus:ring-2 focus:ring-centros/30 disabled:opacity-60">
          <option v-for="c in oferta" :key="c.id" :value="c.id">
            {{ c.nombre }}<template v-if="c.familia"> · {{ c.familia }}</template><template v-if="elegidosEnCiclo(c)"> ({{ elegidosEnCiclo(c) }})</template>
          </option>
        </select>
      </label>

      <div v-for="[curso, modulos] in modulosPorCurso" :key="curso">
        <p class="mb-1.5 text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">{{ curso }}</p>
        <div class="flex flex-wrap gap-1.5">
          <button v-for="m in modulos" :key="m.id" type="button" :disabled="disabled"
                  @click="alternar(m.id)" :aria-pressed="seleccion.includes(m.id)"
                  :class="['inline-flex min-h-9 max-w-full items-center rounded-full border px-3 py-1.5 text-left text-xs font-semibold transition-colors disabled:cursor-not-allowed',
                           seleccion.includes(m.id)
                             ? 'border-centros bg-centros text-white'
                             : 'border-gray-200 bg-white text-gray-600 hover:border-centros/40 hover:text-centros']">
            <span class="break-words">{{ m.nombre }}</span>
          </button>
        </div>
      </div>

      <div v-if="elegidos.length" class="rounded-xl bg-gray-50 px-3 py-2">
        <p class="mb-1.5 text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">
          {{ elegidos.length }} módulo{{ elegidos.length === 1 ? '' : 's' }} seleccionado{{ elegidos.length === 1 ? '' : 's' }}
        </p>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="m in elegidos" :key="m.id"
                class="inline-flex max-w-full items-center gap-1 rounded-full bg-centros/10 py-0.5 pl-2.5 pr-0.5 text-[11px] font-semibold text-centros">
            <span class="truncate" :title="`${m.nombre} · ${m.ciclo}`">{{ m.nombre }}</span>
            <button type="button" :disabled="disabled" @click="alternar(m.id)" :aria-label="`Quitar ${m.nombre}`"
                    class="-my-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full hover:bg-centros/15 disabled:cursor-not-allowed">×</button>
          </span>
        </div>
      </div>
    </template>
  </div>
</template>
