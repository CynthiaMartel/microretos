<!-- Campo de contraseña de las páginas de acceso: icono de candado y botón mostrar/ocultar -->
<script setup>
import { ref } from 'vue'

defineProps({
  id:           { type: String, required: true },
  label:        { type: String, required: true },
  error:        { type: String, default: '' },
  placeholder:  { type: String, default: '••••••••' },
  autocomplete: { type: String, default: 'current-password' },
})
defineEmits(['focus'])

const modelValue = defineModel({ type: String, default: '' })
const visible = ref(false)
</script>

<template>
  <div>
    <label :for="id" class="block text-sm font-semibold text-acceso-tinta mb-2">{{ label }}</label>
    <div class="relative">
      <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
      </svg>
      <input
        :id="id"
        v-model="modelValue"
        :type="visible ? 'text' : 'password'"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        maxlength="128"
        autocapitalize="off"
        autocorrect="off"
        spellcheck="false"
        :aria-invalid="!!error"
        class="w-full h-12 sm:h-13 [@media(min-width:1024px)_and_(max-height:820px)]:h-12 pl-12 pr-14 rounded-xl border bg-white text-base outline-none transition placeholder:text-gray-400 focus:border-acceso-azul focus:ring-4 focus:ring-acceso-azul/15"
        :class="error ? 'border-red-300 bg-red-50' : 'border-gray-300'"
        @focus="$emit('focus')"
      />
      <button
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center rounded-full text-gray-500 hover:text-acceso-tinta"
        :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        @click="visible = !visible"
      >
        <svg v-if="visible" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        </svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
      </button>
    </div>
    <p v-if="error" class="mt-1.5 text-xs font-semibold text-red-600">{{ error }}</p>
    <slot name="ayuda" />
  </div>
</template>
