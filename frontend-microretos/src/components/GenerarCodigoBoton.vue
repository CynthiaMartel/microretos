<script setup>
import { computed } from 'vue'

const props = defineProps({
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  variant: { type: String, default: 'clase' }, // 'clase' | 'ia'
  label: { type: String, required: true },
  loadingLabel: { type: String, default: 'Generando...' },
})
defineEmits(['generar'])

const hoverClass = computed(() => props.variant === 'ia' ? 'hover:text-orange-600' : 'hover:text-[#00A859]')
</script>

<template>
  <div>
    <button @click.stop="$emit('generar')" :disabled="loading"
            class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest
                   text-gray-400 transition-colors disabled:opacity-50" :class="hoverClass">
      <svg class="w-3 h-3" :class="loading ? 'animate-spin' : ''"
           fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path v-if="!loading" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
        <path v-else fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
      </svg>
      {{ loading ? loadingLabel : label }}
    </button>
    <p v-if="error" class="text-[10px] text-red-400 mt-1">{{ error }}</p>
  </div>
</template>
