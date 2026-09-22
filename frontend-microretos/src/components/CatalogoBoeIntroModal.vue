<script setup>
import { useRoleTheme } from '../composables/useRoleTheme.js'

defineProps({ show: Boolean })
const emit = defineEmits(['update:show', 'siguiente'])

const { theme } = useRoleTheme()

function cerrar() { emit('update:show', false) }
function siguiente() { emit('update:show', false); emit('siguiente') }
</script>

<template>
  <Transition name="modal-fade">
    <div v-if="show"
         class="fixed inset-0 z-[9000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         @click.self="cerrar">
      <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-md
                  flex flex-col border border-gray-100 overflow-hidden">

        <!-- Cabecera -->
        <div class="px-8 pt-8 pb-6 flex flex-col items-center text-center">
          <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-5"
               :class="[theme.bg5, theme.border20, 'border']">
            <svg class="w-7 h-7" :class="theme.text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
          </div>

          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full mb-4"
               :class="[theme.bg5, theme.border20, 'border']">
            <span class="w-1.5 h-1.5 rounded-full" :class="theme.bg" />
            <span class="text-[10px] font-black uppercase tracking-widest" :class="theme.text">Solo lectura</span>
          </div>

          <h2 class="font-black text-2xl text-[#1F2937] mb-3 leading-tight">
            Catálogo BOE
          </h2>

          <p class="text-sm text-gray-500 leading-relaxed max-w-xs">
            Aquí podrás ver las <span class="font-bold text-indigo-600">familias</span>,
            <span class="font-bold" :class="theme.text">ciclos</span>,
            <span class="font-bold text-amber-600">RA</span> y
            <span class="font-bold text-amber-500">CE</span>
            que existen en nuestra base de datos.
          </p>
        </div>

        <!-- Leyenda visual -->
        <div class="mx-8 mb-6 rounded-2xl bg-gray-50 border border-gray-100 p-4">
          <div class="grid grid-cols-2 gap-3">
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0">
                <span class="text-[10px] font-black text-indigo-500">F</span>
              </div>
              <div>
                <p class="text-[11px] font-bold text-[#1F2937]">Familias profesionales</p>
                <p class="text-[10px] text-gray-400">Agrupaciones de ciclos</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0"
                   :class="[theme.bg5, theme.border20, 'border']">
                <span class="text-[10px] font-black" :class="theme.text">C</span>
              </div>
              <div>
                <p class="text-[11px] font-bold text-[#1F2937]">Ciclos formativos</p>
                <p class="text-[10px] text-gray-400">FPB · FPM · FPS</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0"
                   :class="[theme.bg5, theme.border15, 'border']">
                <span class="text-[10px] font-black" :class="theme.text">RA</span>
              </div>
              <div>
                <p class="text-[11px] font-bold text-[#1F2937]">Resultados de aprendizaje</p>
                <p class="text-[10px] text-gray-400">Por módulo formativo</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center shrink-0">
                <span class="text-[10px] font-black text-amber-500">CE</span>
              </div>
              <div>
                <p class="text-[11px] font-bold text-[#1F2937]">Criterios de evaluación</p>
                <p class="text-[10px] text-gray-400">Por resultado de aprendizaje</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Botones -->
        <div class="px-8 pb-8 flex items-center gap-3">
          <button @click="cerrar"
                  class="flex-1 px-5 py-3 rounded-xl bg-gray-100 text-gray-500 text-sm font-black
                         uppercase tracking-widest hover:bg-gray-200 transition-all">
            Cancelar
          </button>
          <button @click="siguiente"
                  class="flex-1 px-5 py-3 rounded-xl text-white text-sm font-black
                         uppercase tracking-widest transition-all
                         flex items-center justify-center gap-2"
                  :class="[theme.bg, theme.bgHover]">
            Siguiente
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
