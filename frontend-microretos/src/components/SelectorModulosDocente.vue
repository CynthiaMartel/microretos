<!-- Selector de los módulos que imparte un docente: ciclo de la oferta de su centro → módulos
     por curso (píldoras). Lo usan «Mi usuario» y la gestión de usuarios del admin.
     `oferta`: [{ id, nombre, familia, modulos: [{ id, nombre, curso, codigo }] }] (GET …/modulos).
     v-model: array de ids de módulo.
     `plegable`: cada curso se abre y cierra (cerrados al empezar) para que la tarjeta no crezca.
     `resumen`: muestra la lista de módulos seleccionados al pie (desactivar si la vista ya la pinta).
     `destacados`: ids que el docente usa en sus proyectos; llevan un punto y una leyenda al pie. -->
<script setup>
import { ref, computed, watch } from 'vue'

const seleccion = defineModel({ type: Array, default: () => [] })

const props = defineProps({
  oferta:   { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  plegable: { type: Boolean, default: false },
  resumen:  { type: Boolean, default: true },
  destacados: { type: Array, default: () => [] },
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

// Cursos abiertos (solo con `plegable`); al cambiar de ciclo se vuelven a cerrar
const abiertos = ref(new Set())
watch(cicloId, () => { abiertos.value = new Set() })
const estaAbierto = (curso) => !props.plegable || abiertos.value.has(curso)
function alternarCurso(curso) {
  const s = new Set(abiertos.value)
  s.has(curso) ? s.delete(curso) : s.add(curso)
  abiertos.value = s
}
const elegidosEnCurso = (modulos) => modulos.filter(m => seleccion.value.includes(m.id)).length

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

      <div v-for="[curso, modulos] in modulosPorCurso" :key="curso"
           :class="plegable && 'rounded-xl border border-gray-200'">
        <button v-if="plegable" type="button" @click="alternarCurso(curso)" :aria-expanded="estaAbierto(curso)"
                class="flex min-h-10 w-full items-center justify-between gap-2 px-3 py-2 text-left">
          <span class="text-[10px] font-black uppercase tracking-[0.15em] text-gray-500">{{ curso }}</span>
          <span class="flex shrink-0 items-center gap-2">
            <span class="text-[11px] font-semibold"
                  :class="elegidosEnCurso(modulos) ? 'text-centros' : 'text-gray-400'">
              {{ elegidosEnCurso(modulos) }}/{{ modulos.length }}
            </span>
            <svg class="h-4 w-4 text-gray-400 transition-transform" :class="estaAbierto(curso) && 'rotate-180'"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </span>
        </button>
        <p v-else class="mb-1.5 text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">{{ curso }}</p>
        <div v-show="estaAbierto(curso)" class="flex flex-wrap gap-1.5" :class="plegable && 'px-3 pb-3'">
          <button v-for="m in modulos" :key="m.id" type="button" :disabled="disabled"
                  @click="alternar(m.id)" :aria-pressed="seleccion.includes(m.id)"
                  :class="['inline-flex min-h-9 max-w-full items-center rounded-full border px-3 py-1.5 text-left text-xs font-semibold transition-colors disabled:cursor-not-allowed',
                           seleccion.includes(m.id)
                             ? 'border-centros bg-centros text-white'
                             : 'border-gray-200 bg-white text-gray-600 hover:border-centros/40 hover:text-centros']">
            <span class="break-words">{{ m.nombre }}</span>
            <span v-if="destacados.includes(m.id)" class="ml-1.5 h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="seleccion.includes(m.id) ? 'bg-white' : 'bg-centros'"
                  title="Lo usas en tus proyectos" aria-hidden="true"></span>
            <span v-if="destacados.includes(m.id)" class="sr-only">(lo usas en tus proyectos)</span>
          </button>
        </div>
      </div>

      <p v-if="destacados.length" class="flex items-center gap-1.5 text-[11px] text-gray-500">
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-centros" aria-hidden="true"></span>
        Lo usas en alguno de tus proyectos
      </p>

      <div v-if="resumen && elegidos.length" class="rounded-xl bg-gray-50 px-3 py-2">
        <p class="mb-1.5 text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">
          {{ elegidos.length }} módulo{{ elegidos.length === 1 ? '' : 's' }} seleccionado{{ elegidos.length === 1 ? '' : 's' }}
        </p>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="m in elegidos" :key="m.id"
                class="inline-flex max-w-full items-center gap-1 rounded-full bg-centros/10 py-0.5 pl-2.5 pr-0.5 text-[11px] font-semibold text-centros">
            <span class="truncate" :title="`${m.nombre} · ${m.ciclo}`">{{ m.nombre }}</span>
            <button type="button" :disabled="disabled" @click="alternar(m.id)" :aria-label="`Quitar ${m.nombre}`"
                    class="relative -my-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full hover:bg-centros/15 before:absolute before:-inset-0.5 before:content-[''] disabled:cursor-not-allowed">×</button>
          </span>
        </div>
      </div>
    </template>
  </div>
</template>
