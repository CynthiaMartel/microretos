<!-- Tipo de cuenta en crear/editar de Usuarios: píldoras agrupadas por app (DuaLab App · App comercial ·
     DuaLab Central · Superadmin) con raya entre grupos, la explicación del tipo elegido debajo y un enlace
     a la explicación de todos. El padre limpia centro/empresa al cambiar de tipo (evento `elegir`). -->
<script setup>
import { computed } from 'vue'
import { SECCIONES_TIPO_CUENTA, tipoCuenta } from '../utils/tiposCuenta.js'

const props = defineProps({
  modelValue:     { type: String, default: '' },
  conSuperadmin:  { type: Boolean, default: false },   // la sección Superadmin (solo al crear, solo superadmin)
  error:          { type: String, default: '' },
})
const emit = defineEmits(['elegir', 'ayuda'])

const secciones = computed(() => SECCIONES_TIPO_CUENTA.filter(s => !s.soloSuperadmin || props.conSuperadmin))
const elegido = computed(() => tipoCuenta(props.modelValue))
</script>

<template>
  <div>
    <div class="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
      <span class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500">Tipo de cuenta</span>
      <button type="button" class="inline-flex min-h-9 items-center gap-1 rounded-full px-2 text-xs font-semibold text-texto-secundario underline-offset-2 hover:text-azul-noche hover:underline"
              @click="emit('ayuda')">
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        ¿Qué es cada tipo?
      </button>
    </div>

    <div role="radiogroup" aria-label="Tipo de cuenta" class="divide-y divide-gray-200 rounded-xl border border-gray-200">
      <div v-for="s in secciones" :key="s.titulo" class="px-3 py-2.5">
        <p class="mb-1.5 text-[11px] font-bold text-gray-400">{{ s.titulo }}</p>
        <!-- Cada píldora a su ancho y bajan de línea si no caben (en móvil el texto pisaba los iconos) -->
        <div class="flex flex-wrap gap-2">
          <button v-for="t in s.tipos" :key="t.val" type="button" role="radio" :aria-checked="modelValue === t.val"
                  :class="modelValue === t.val ? t.activo : 'bg-gray-50 border-gray-200 text-texto-secundario hover:text-azul-noche'"
                  class="inline-flex h-10 grow items-center justify-center gap-2 whitespace-nowrap rounded-full border px-4 text-sm font-semibold transition-all"
                  @click="emit('elegir', t.val)">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="t.icono" />
            </svg>
            {{ t.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Qué implica el tipo elegido (superadmin, en ámbar: es el que más puede) -->
    <p v-if="elegido" aria-live="polite"
       class="mt-2 flex items-start gap-2 rounded-xl border px-3.5 py-2.5 text-xs leading-relaxed"
       :class="elegido.val === '1' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-gray-200 bg-white text-texto-secundario'">
      <svg class="mt-0.5 h-4 w-4 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span>{{ elegido.texto }}</span>
    </p>
    <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
  </div>
</template>
