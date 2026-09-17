<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const props = defineProps({
  proyecto: { type: Object, required: true }
});
const emit = defineEmits(['eliminar']);

const router = useRouter();
const authStore = useAuthStore();

function getEtiqueta(p) {
  if (p.estado === 'en_edicion') return 'En edición';
  if (p.estado === 'archivado')  return 'Archivado';
  if (p.estado === 'completado') return 'Completado';
  if (p.estado === 'validado') {
    if (p.empresa_validado && p.docente_validado) return 'Validado · Completo';
    if (p.empresa_validado)  return 'Validado · Empresa';
    if (p.docente_validado)  return 'Validado · Docente';
    return 'Validado';
  }
  if (p.empresa_no_valida_aun)    return 'No validar aún';
  if (p.enviado_a_empresa_mail)   return 'Esperando respuesta';
  return 'Pendiente enviar';
}
function getColor(p) {
  if (p.estado === 'en_edicion') return 'bg-amber-50 border-amber-200 text-amber-700';
  if (p.estado === 'archivado')  return 'bg-gray-100 border-gray-200 text-gray-400';
  if (p.estado === 'completado') return 'bg-sky-50 border-sky-300 text-sky-700';
  if (p.estado === 'validado') {
    if (p.docente_validado && !p.empresa_validado) return 'bg-emerald-50 border-emerald-300 text-emerald-700';
    return 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]';
  }
  if (p.empresa_no_valida_aun)   return 'bg-red-50 border-red-300 text-red-700';
  if (p.enviado_a_empresa_mail)  return 'bg-blue-50 border-blue-200 text-blue-700';
  return 'bg-violet-50 border-violet-300 text-violet-700';
}
</script>

<template>
  <div
    class="group bg-white border border-gray-100 rounded-[1.5rem] shadow-sm
           hover:shadow-lg hover:-translate-y-0.5 hover:border-gray-200
           transition-all duration-300 cursor-pointer flex flex-col"
    @click="router.push({ name: 'startup-day-detalle', params: { uuid: proyecto.uuid } })"
  >
    <img v-if="proyecto.imagen_portada_url" :src="proyecto.imagen_portada_url" :alt="proyecto.titulo"
         class="w-full h-32 object-cover rounded-t-[1.5rem]" />
    <div class="p-5 flex-1 flex flex-col gap-3">
      <!-- Estado + paso -->
      <div class="flex items-center justify-between">
        <span :class="['text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full border', getColor(proyecto)]">
          {{ getEtiqueta(proyecto) }}
        </span>
        <span class="text-[10px] text-gray-400 font-bold">Paso {{ proyecto.paso_actual }}/8</span>
      </div>

      <!-- Título -->
      <h3 class="font-black text-[#1F2937] text-sm leading-snug line-clamp-2
                 group-hover:text-[#00A859] transition-colors">
        {{ proyecto.titulo }}
      </h3>

      <!-- Meta -->
      <div class="space-y-1.5 text-xs text-gray-500 mt-auto">
        <div v-if="proyecto.empresa_nombre" class="flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
          </svg>
          {{ proyecto.empresa_nombre }}
        </div>
        <div v-if="proyecto.centro_nombre" class="flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
          </svg>
          {{ proyecto.centro_nombre }}
        </div>
        <div v-if="proyecto.empresa_validado"
             class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl
                    bg-[#00A859]/10 border border-[#00A859]/30 text-[#00A859]">
          <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
          <span class="text-[9px] font-black uppercase tracking-wider leading-tight">
            Validado por empresa
          </span>
        </div>
      </div>

      <!-- ── Etiquetas de sub-estado en miniatura ───────────────── -->
      <div v-if="proyecto.estado === 'propuesta' && !proyecto.empresa_validado"
           class="flex flex-col gap-1.5 mt-1">

        <!-- Propuesta NO enviada por mail aún -->
        <div v-if="!proyecto.enviado_a_empresa_mail && !proyecto.empresa_no_valida_aun"
             class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl
                    bg-violet-50 border border-violet-300 text-violet-700">
          <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span class="text-[9px] font-black uppercase tracking-wider leading-tight">
            Pendiente de enviar a empresa
          </span>
        </div>

        <!-- Enviada por mail, esperando respuesta -->
        <div v-if="proyecto.enviado_a_empresa_mail && !proyecto.empresa_no_valida_aun"
             class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl
                    bg-blue-50 border border-blue-200 text-blue-600">
          <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
          <span class="text-[9px] font-black uppercase tracking-wider leading-tight">
            Enviado a empresa · Sin respuesta
          </span>
        </div>

        <!-- Empresa contestó "No validar aún" — requiere atención -->
        <div v-if="proyecto.empresa_no_valida_aun"
             class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl
                    bg-red-50 border border-red-300 text-red-700">
          <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span class="text-[9px] font-black uppercase tracking-wider leading-tight">
            Empresa: "No validar aún" · Revisar
          </span>
        </div>

      </div>
      <!-- ── Fin etiquetas de sub-estado ────────────────────────── -->
    </div>

    <!-- Acciones -->
    <div v-if="!authStore.isEmpresa" class="px-5 pb-4 flex gap-2 border-t border-gray-50 pt-3" @click.stop>
      <button
        @click="router.push({ name: 'startup-day-editar', params: { uuid: proyecto.uuid } })"
        class="flex-1 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs font-black
               uppercase tracking-widest text-gray-500
               hover:border-[#00A859] hover:text-[#00A859] hover:bg-[#00A859]/5
               transition-all"
      >
        Editar
      </button>
      <button
        @click="emit('eliminar', proyecto)"
        class="py-2 px-3 rounded-xl bg-gray-50 border border-gray-200 text-red-400
               hover:bg-red-50 hover:border-red-200 transition-all"
        title="Mover a papelera"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
      </button>
    </div>
  </div>
</template>
