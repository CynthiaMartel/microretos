<script setup>
import { computed } from 'vue'

const props = defineProps({
  code: { type: String, required: true },
  variant: { type: String, default: 'clase' }, // 'clase' | 'ia'
  label: { type: String, default: '' },
})
defineEmits(['copiar'])

const esIa = computed(() => props.variant === 'ia')
const pillClasses = computed(() => esIa.value
  ? 'bg-orange-50 border border-orange-200'
  : 'bg-[#00A859]/10 border border-[#00A859]/20')
const codeTextClass = computed(() => esIa.value ? 'text-orange-600' : 'text-[#00A859]')
const copyBtnClass = computed(() => esIa.value
  ? 'hover:bg-orange-100 text-orange-300 hover:text-orange-600'
  : 'hover:bg-[#00A859]/10 text-[#00A859]/50 hover:text-[#00A859]')
</script>

<template>
  <div :class="label ? 'space-y-1' : ''">
    <p v-if="label" class="text-[9px] font-black uppercase tracking-wide text-gray-400">{{ label }}</p>
    <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-full w-fit" :class="pillClasses">
      <span v-if="esIa" class="text-[10px] shrink-0">✨</span>
      <span v-else class="w-1.5 h-1.5 rounded-full bg-[#00A859] animate-pulse shrink-0"></span>
      <span class="text-[11px] font-black tracking-widest" :class="codeTextClass">{{ code }}</span>
      <button @click.stop="$emit('copiar', code)"
              class="p-0.5 rounded transition-all" :class="copyBtnClass"
              title="Copiar código">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
        </svg>
      </button>
    </span>
  </div>
</template>
