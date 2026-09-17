<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  code: { type: String, required: true },
  variant: { type: String, default: 'clase' }, // 'clase' | 'ia'
  label: { type: String, default: '' },
  // Texto del hover informativo. Si no se pasa, se usa un texto por defecto según variant.
  hint: { type: String, default: '' },
})
const emit = defineEmits(['copiar'])

const esIa = computed(() => props.variant === 'ia')
const pillClasses = computed(() => esIa.value
  ? 'bg-orange-50 border border-orange-200'
  : 'bg-[#00A859]/10 border border-[#00A859]/20')
const codeTextClass = computed(() => esIa.value ? 'text-orange-600' : 'text-[#00A859]')
const copyBtnClass = computed(() => esIa.value
  ? 'hover:bg-orange-100 text-orange-300 hover:text-orange-600'
  : 'hover:bg-[#00A859]/10 text-[#00A859]/50 hover:text-[#00A859]')

const HINT_DEFECTO = {
  clase: 'Código de acceso al workspace del alumnado — proyéctalo en pantalla para que el equipo lo escriba y entre',
  ia:    'Código para desbloquear la ayuda de la IA — compártelo con el alumnado cuando quieras habilitarla',
}
const hintTexto = computed(() => props.hint || HINT_DEFECTO[props.variant] || HINT_DEFECTO.clase)

// Feedback "¡Copiado!" en el propio hover, independiente de si el padre además
// muestra un snackbar u otra confirmación al escuchar @copiar.
const recienCopiado = ref(false)
let timeoutCopiado = null
function copiar() {
  emit('copiar', props.code)
  recienCopiado.value = true
  clearTimeout(timeoutCopiado)
  timeoutCopiado = setTimeout(() => { recienCopiado.value = false }, 1200)
}
</script>

<template>
  <div :class="label ? 'space-y-1' : ''">
    <p v-if="label" class="text-[9px] font-black uppercase tracking-wide text-gray-400">{{ label }}</p>
    <span class="relative group/codigo flex items-center gap-1.5 px-2.5 py-1 rounded-full w-fit" :class="pillClasses">
      <span v-if="esIa" class="text-[10px] shrink-0">✨</span>
      <span v-else class="w-1.5 h-1.5 rounded-full bg-[#00A859] animate-pulse shrink-0"></span>
      <span class="text-[11px] font-black tracking-widest" :class="codeTextClass">{{ code }}</span>
      <button @click.stop="copiar"
              class="p-0.5 rounded transition-all" :class="copyBtnClass"
              title="Copiar código">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
        </svg>
      </button>

      <!-- Hover informativo: qué es este código y para qué se usa, no solo "copiar". -->
      <span class="pointer-events-none absolute bottom-full left-0 mb-2 z-30
                   w-max max-w-[220px] px-3 py-2 rounded-xl
                   bg-[#1F2937] text-white text-[11px] font-semibold leading-snug
                   shadow-lg opacity-0 group-hover/codigo:opacity-100
                   translate-y-1 group-hover/codigo:translate-y-0
                   transition-all duration-150">
        {{ recienCopiado ? '¡Copiado!' : hintTexto }}
        <span class="absolute -bottom-1 left-3 w-2 h-2 bg-[#1F2937] rotate-45"></span>
      </span>
    </span>
  </div>
</template>
