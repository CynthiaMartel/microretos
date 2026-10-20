<!-- Filtro de pocas opciones fijas (estado, nivel 1º/2º, clase A–D…) dentro de FiltrosPanel.
     Regla: hasta ~5 opciones fijas → segmentado; listas largas o que crecen → FiltroSelect.
     `opciones`: [{ value, label, count? }] — la opción "todos" se pasa como una más (value ''). -->
<script setup>
const modelo = defineModel({ type: [String, Number], default: '' })

defineProps({
  label:    { type: String, required: true },
  opciones: { type: Array, required: true },
})
</script>

<template>
  <div class="min-w-0 max-w-full" role="group" :aria-label="label">
    <p class="mb-1 text-[10px] font-black uppercase tracking-[0.15em] text-gray-400">{{ label }}</p>
    <div class="flex max-w-full overflow-x-auto rounded-full border border-gray-200">
      <button v-for="(op, i) in opciones" :key="String(op.value)" type="button"
              @click="modelo = op.value" :aria-pressed="modelo === op.value"
              :class="['inline-flex h-9 shrink-0 rounded-full items-center gap-1.5 whitespace-nowrap px-3 text-xs font-black uppercase tracking-wider transition-colors',
                       i > 0 && 'border-l border-gray-200',
                       modelo === op.value ? 'bg-azul-noche text-white' : 'bg-gray-50 text-gray-500 hover:text-azul-noche']">
        {{ op.label }}
        <span v-if="op.count != null"
              :class="['rounded-full px-1.5 text-[10px]', modelo === op.value ? 'bg-white/20' : 'bg-gray-200/70']">{{ op.count }}</span>
      </button>
    </div>
  </div>
</template>
