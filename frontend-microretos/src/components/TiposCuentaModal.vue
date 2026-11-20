<!-- Explicación de todos los tipos de cuenta, agrupados por app. Se abre desde la cabecera de Usuarios y
     desde «¿Qué es cada tipo?» del formulario (por eso va por encima del modal de crear/editar). -->
<script setup>
import Modal from './Modal.vue'
import { SECCIONES_TIPO_CUENTA } from '../utils/tiposCuenta.js'

defineProps({ visible: { type: Boolean, default: false } })
defineEmits(['cerrar'])
</script>

<template>
  <Modal :visible="visible" max-width="max-w-2xl" :z-index="9100" @cerrar="$emit('cerrar')">
    <div class="flex max-h-[90dvh] min-h-0 flex-col">
      <div class="shrink-0 px-5 pb-3 pt-6 pr-14 sm:px-8 sm:pt-8">
        <h2 class="font-heading text-lg font-black text-azul-noche sm:text-xl">Tipos de cuenta</h2>
        <p class="mt-1 text-xs text-gray-500 sm:text-sm">Qué puede hacer cada cuenta y a qué app entra.</p>
      </div>
      <div class="min-h-0 overflow-y-auto px-5 pb-6 sm:px-8 sm:pb-8">
        <section v-for="s in SECCIONES_TIPO_CUENTA" :key="s.titulo" class="border-t border-gray-200 py-4 first:border-t-0 first:pt-1">
          <h3 class="mb-2 text-[11px] font-black uppercase tracking-widest text-gray-500">{{ s.titulo }}</h3>
          <ul class="space-y-3">
            <li v-for="t in s.tipos" :key="t.val" class="flex items-start gap-3">
              <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border" :class="t.activo" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="t.icono" /></svg>
              </span>
              <div class="min-w-0">
                <p class="text-sm font-bold text-azul-noche">{{ t.label }}</p>
                <p class="mt-0.5 text-xs leading-relaxed text-texto-secundario sm:text-sm">{{ t.texto }}</p>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </Modal>
</template>
