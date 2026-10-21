<!-- Indicador de pasos del Taller de Ideas (diseño crear_proyecto.png): tarjeta blanca con
     los 7 pasos, círculo (✓ en verde si está hecho, número en el color del rol si es el
     actual) + «N. Nombre», y una raya bajo cada paso (verde hecho / color del rol actual /
     gris pendiente). Ningún nombre se recorta:
     · ≥ 2xl (1536px): círculo y «N. Nombre» en línea, como en el diseño (cada paso ~114px;
       «5. Propuesta» a 12px + círculo ≈ 100px). A 1440 no cabía: se probó y se pisaba.
     · md–2xl: círculo encima del nombre (a 1024px cada paso tiene ~60px; «Propuesta»
       ocupa 52px a 12px, medido con Inter).
     · móvil: «Paso 3 de 7 · Currículo» + barra en 7 tramos tocables (saltan al paso).
     Completado = ✓ (icono + color + texto oculto «completado»), no solo color. -->
<script setup>
const props = defineProps({
  pasos:   { type: Array, required: true }, // [{ num, label }]
  actual:  { type: Number, required: true },
  maximo:  { type: Number, required: true }, // último paso alcanzado
  // Si se puede saltar a un paso (además de haberlo alcanzado): el wizard exige reto vinculado.
  puedeIr: { type: Function, default: () => true },
})
const emit = defineEmits(['ir'])

function estado(num) {
  if (num === props.actual) return 'actual'
  if (num < props.maximo || num < props.actual) return 'hecho'
  if (num <= props.maximo) return 'alcanzado'
  return 'pendiente'
}
function navegable(num) {
  return num !== props.actual && num <= props.maximo && props.puedeIr(num)
}
function ir(num) {
  if (navegable(num)) emit('ir', num)
}
</script>

<template>
  <nav aria-label="Pasos del Taller de Ideas"
       class="rounded-2xl border border-gray-100 bg-white px-3 pt-3 shadow-sm sm:px-4">

    <!-- Móvil -->
    <div class="pb-1 md:hidden">
      <p class="text-sm font-semibold text-azul-noche">
        Paso {{ actual }} de {{ pasos.length }} ·
        <span class="text-rol-fuerte">{{ pasos[actual - 1]?.label }}</span>
      </p>
      <!-- Cada tramo es tocable (36px de alto) para saltar a un paso ya alcanzado -->
      <ol class="mt-1 flex gap-1">
        <li v-for="p in pasos" :key="p.num" class="flex-1">
          <button type="button" :disabled="!navegable(p.num)" @click="ir(p.num)"
                  :aria-current="p.num === actual ? 'step' : undefined"
                  :aria-label="`Paso ${p.num}: ${p.label}${estado(p.num) === 'hecho' ? ' (completado)' : ''}`"
                  class="flex h-9 w-full items-center disabled:cursor-default">
            <span class="block h-1.5 w-full rounded-full"
                  :class="{
                    'bg-emerald-600': estado(p.num) === 'hecho',
                    'bg-rol-fuerte': estado(p.num) === 'actual',
                    'bg-gray-200': ['alcanzado', 'pendiente'].includes(estado(p.num)),
                  }" />
          </button>
        </li>
      </ol>
    </div>

    <!-- md en adelante -->
    <ol class="hidden gap-1 md:flex">
      <li v-for="p in pasos" :key="p.num" class="flex min-w-0 flex-1 flex-col">
        <button type="button"
                :disabled="!navegable(p.num)"
                :aria-current="p.num === actual ? 'step' : undefined"
                @click="ir(p.num)"
                class="group flex flex-1 flex-col items-center gap-1.5 rounded-t-xl px-0.5 pb-2.5 pt-1 text-center disabled:cursor-default 2xl:flex-row 2xl:justify-start 2xl:gap-2 2xl:px-1.5 2xl:text-left"
                :class="navegable(p.num) ? 'hover:bg-gray-50' : ''">
          <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                :class="{
                  'bg-emerald-600 text-white': estado(p.num) === 'hecho',
                  'bg-rol-fuerte text-white': estado(p.num) === 'actual',
                  'border-2 border-gray-300 text-gray-500': estado(p.num) === 'alcanzado',
                  'border-2 border-gray-200 text-gray-400': estado(p.num) === 'pendiente',
                }">
            <svg v-if="estado(p.num) === 'hecho'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
            </svg>
            <template v-else>{{ p.num }}</template>
          </span>
          <span class="whitespace-nowrap text-xs leading-tight"
                :class="{
                  'font-semibold text-rol-fuerte': estado(p.num) === 'actual',
                  'font-medium text-gray-700': estado(p.num) === 'hecho',
                  'text-gray-600': estado(p.num) === 'alcanzado',
                  'text-gray-500': estado(p.num) === 'pendiente',
                }">
            <span class="hidden 2xl:inline">{{ p.num }}. </span>{{ p.label }}
            <span v-if="estado(p.num) === 'hecho'" class="sr-only">(completado)</span>
          </span>
        </button>
        <span aria-hidden="true" class="block h-1 rounded-full"
              :class="{
                'bg-emerald-600': estado(p.num) === 'hecho',
                'bg-rol-fuerte': estado(p.num) === 'actual',
                'bg-gray-100': ['alcanzado', 'pendiente'].includes(estado(p.num)),
              }" />
      </li>
    </ol>
  </nav>
</template>
