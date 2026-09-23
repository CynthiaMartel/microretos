<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useRoleTheme } from '../composables/useRoleTheme.js';
import api from '../api.js';

const props = defineProps({
  proyecto: { type: Object, required: true },
  resaltarEditar: { type: Boolean, default: false }
});
const emit = defineEmits(['eliminar']);

const router = useRouter();
const authStore = useAuthStore();
const { theme } = useRoleTheme();

// Escaparate público (dualab.es) — opt-in manual, solo sobre proyectos completados.
const cambiandoVisibilidad = ref(false);
async function toggleVisiblePublico() {
  if (cambiandoVisibilidad.value) return;
  cambiandoVisibilidad.value = true;
  try {
    const { data } = await api.patch(`/startup/proyectos/${props.proyecto.uuid}/visible-publico`);
    props.proyecto.visible_publico = data.visible_publico;
  } catch {
    // Sin toast propio en esta card — el estado del botón no cambia si falla.
  } finally {
    cambiandoVisibilidad.value = false;
  }
}

const paletteExtra = {
  centros:          { groupHoverText: 'group-hover:text-centros', hoverBorderTextBg5: 'hover:border-centros hover:text-centros hover:bg-centros/5' },
  empresas:         { groupHoverText: 'group-hover:text-empresas', hoverBorderTextBg5: 'hover:border-empresas hover:text-empresas hover:bg-empresas/5' },
  administraciones: { groupHoverText: 'group-hover:text-administraciones', hoverBorderTextBg5: 'hover:border-administraciones hover:text-administraciones hover:bg-administraciones/5' },
  primary:          { groupHoverText: 'group-hover:text-primary-700', hoverBorderTextBg5: 'hover:border-primary-600 hover:text-primary-700 hover:bg-primary-600/5' },
}

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
    return `${theme.value.bg5} ${theme.value.border20} ${theme.value.text}`;
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
      <h3 class="font-black text-[#1F2937] text-sm leading-snug line-clamp-2 transition-colors"
          :class="paletteExtra[theme.key].groupHoverText">
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
             class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl border"
             :class="[theme.bg5, theme.border20, theme.text]">
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
      <button v-if="proyecto.estado === 'completado'"
        @click="toggleVisiblePublico"
        :disabled="cambiandoVisibilidad"
        class="py-2 px-3 rounded-xl border transition-all disabled:opacity-50"
        :class="proyecto.visible_publico
          ? 'bg-emerald-50 border-emerald-300 text-emerald-600 hover:bg-emerald-100'
          : 'bg-gray-50 border-gray-200 text-gray-400 hover:bg-gray-100'"
        :title="proyecto.visible_publico ? 'Visible en el escaparate público — clic para ocultar' : 'Oculto del escaparate público — clic para publicar'"
      >
        <svg v-if="proyecto.visible_publico" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
        </svg>
      </button>
      <button
        @click="router.push({ name: 'startup-day-editar', params: { uuid: proyecto.uuid } })"
        class="flex-1 py-2 rounded-xl border text-xs font-black
               uppercase tracking-widest transition-all"
        :class="resaltarEditar
          ? 'bg-amber-50 border-amber-300 text-amber-700 shadow-[0_0_0_3px_rgba(251,191,36,0.3)] animate-pulse'
          : ['bg-gray-50 border-gray-200 text-gray-500', paletteExtra[theme.key].hoverBorderTextBg5]"
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
