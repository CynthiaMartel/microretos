<script setup>
import { computed, ref, onUnmounted } from 'vue';

// Card de familia para /proyectos — deliberadamente distinta de las cards de
// familia de BibliotecaMicroretos.vue (icono grande ilustrado + contador): aquí
// el foco es el desglose (mini barra apilada + leyenda) y dos acciones
// explícitas en el cuerpo ("En proceso" / "Completados"), porque cada una
// lleva a un sitio distinto (capa de detalle vs. /proyectos/terminados) y un
// solo clic ambiguo en la card confundiría a qué destino se va.
const props = defineProps({
  nombre:  { type: String, required: true },
  total:   { type: Number, required: true },
  segments: { type: Array, default: () => [] }, // [{ key, label, count, colorClass }]
  dashed:  { type: Boolean, default: false },
  countEnProceso:  { type: Number, default: 0 },
  countCompletados: { type: Number, default: 0 },
});
defineEmits(['click', 'ver-proceso', 'ver-completados']);

const segmentosConAncho = computed(() =>
  props.segments.map(s => ({ ...s, pct: props.total ? (s.count / props.total) * 100 : 0 }))
);
const segmentosConDatos = computed(() => props.segments.filter(s => s.count > 0));

// Al pulsar el cuerpo de la card (fuera de los dos botones) no navega a ningún
// sitio por sí solo — en vez de no hacer nada, resalta brevemente "En proceso"
// / "Completados" para dejar claro que la elección está ahí.
const destacarBotones = ref(false);
let destacarTimeout = null;
function resaltarBotones() {
  destacarBotones.value = true;
  clearTimeout(destacarTimeout);
  destacarTimeout = setTimeout(() => { destacarBotones.value = false; }, 900);
}
onUnmounted(() => clearTimeout(destacarTimeout));
</script>

<template>
  <div
    class="rounded-2xl transition-all duration-300 hover:-translate-y-0.5"
    :class="dashed
      ? 'border-2 border-dashed border-gray-200 bg-gray-50 hover:bg-white hover:border-gray-300'
      : 'border border-gray-100 bg-white shadow-sm hover:shadow-lg'"
  >
    <!-- Modo "dashed" (card "Todos"): un único destino, toda la card es el botón -->
    <button v-if="dashed" type="button" @click="$emit('click')"
            class="w-full text-left p-5 focus:outline-none focus:ring-2 focus:ring-gray-300 rounded-2xl">
      <div class="flex items-start justify-between gap-2 mb-4">
        <h3 class="font-black text-[#1F2937] text-sm leading-snug line-clamp-2">{{ nombre }}</h3>
        <span class="shrink-0 text-2xl font-black text-gray-300 leading-none">{{ total }}</span>
      </div>
      <div v-if="total > 0" class="flex h-2 rounded-full overflow-hidden bg-gray-100 mb-3">
        <div v-for="s in segmentosConAncho" :key="s.key" :class="s.colorClass" :style="{ width: s.pct + '%' }" />
      </div>
      <div v-else class="h-2 rounded-full bg-gray-100 mb-3" />
      <div v-if="segmentosConDatos.length > 0" class="flex flex-wrap gap-x-3 gap-y-1">
        <span v-for="s in segmentosConDatos" :key="s.key"
              class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-500">
          <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="s.colorClass"></span>
          {{ s.count }} {{ s.label }}
        </span>
      </div>
      <p v-else class="text-[10px] font-bold text-gray-300 uppercase tracking-wide">Sin datos todavía</p>
    </button>

    <!-- Modo familia: dos destinos explícitos. El clic en el cuerpo (fuera de
         los botones) no navega — solo resalta las dos opciones de abajo. -->
    <div v-else class="p-5 cursor-pointer" @click="resaltarBotones">
      <div class="flex items-start justify-between gap-2 mb-4">
        <h3 class="font-black text-[#1F2937] text-sm leading-snug line-clamp-2">{{ nombre }}</h3>
        <span class="shrink-0 text-2xl font-black text-gray-300 leading-none">{{ total }}</span>
      </div>
      <div v-if="total > 0" class="flex h-2 rounded-full overflow-hidden bg-gray-100 mb-3">
        <div v-for="s in segmentosConAncho" :key="s.key" :class="s.colorClass" :style="{ width: s.pct + '%' }" />
      </div>
      <div v-else class="h-2 rounded-full bg-gray-100 mb-3" />
      <div v-if="segmentosConDatos.length > 0" class="flex flex-wrap gap-x-3 gap-y-1 mb-1">
        <span v-for="s in segmentosConDatos" :key="s.key"
              class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-500">
          <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="s.colorClass"></span>
          {{ s.count }} {{ s.label }}
        </span>
      </div>
      <p v-else class="text-[10px] font-bold text-gray-300 uppercase tracking-wide mb-1">Sin datos todavía</p>

      <div class="flex gap-2 mt-3 pt-3 border-t border-gray-50">
        <button type="button" @click.stop="$emit('ver-proceso')"
                class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 rounded-xl border cursor-pointer
                       border-gray-200 bg-gray-50 text-gray-600 text-[10px] font-black uppercase tracking-wide
                       transition-all hover:border-gray-300 hover:bg-gray-100"
                :class="destacarBotones && 'ring-2 ring-offset-1 ring-gray-300 scale-[1.04] border-gray-300 bg-gray-100'">
          En proceso
          <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] rounded-full
                       bg-white text-gray-500 text-[9px] font-black border border-gray-200">{{ countEnProceso }}</span>
        </button>
        <button type="button" @click.stop="$emit('ver-completados')"
                class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 rounded-xl border cursor-pointer
                       border-sky-200 bg-sky-50 text-sky-700 text-[10px] font-black uppercase tracking-wide
                       transition-all hover:border-sky-300 hover:bg-sky-100"
                :class="destacarBotones && 'ring-2 ring-offset-1 ring-sky-300 scale-[1.04] border-sky-300 bg-sky-100'">
          Completados
          <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] rounded-full
                       bg-white text-sky-600 text-[9px] font-black border border-sky-200">{{ countCompletados }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
