<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api.js';

const route    = useRoute();
const reto     = ref(null);
const expira   = ref(null);
const cargando = ref(true);
const error    = ref(null);
const isLoaded = ref(false);

onMounted(async () => {
  setTimeout(() => { isLoaded.value = true; }, 100);
  try {
    const res = await api.get(`/public/microreto/${route.params.token}`);
    reto.value   = res.data.microreto;
    expira.value = res.data.expires_at;
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'acceso-expirado';
    } else {
      error.value = 'error-red';
    }
  } finally {
    cargando.value = false;
  }
});

const expiraFormateado = computed(() => {
  if (!expira.value) return '';
  return new Date(expira.value).toLocaleString('es-ES', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
});

const imagenFondo = computed(() => {
  if (!reto.value?.familia) return null;
  const slug = reto.value.familia
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '');
  const base = import.meta.env.VITE_API_URL.replace(/\/api$/, '');
  return `${base}/familias/${slug}.webp`;
});
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC] font-sans text-[#1F2937]">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                bg-[#99CC33] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <!-- ── CARGANDO ── -->
    <div v-if="cargando" class="flex flex-col items-center justify-center min-h-screen">
      <svg class="animate-spin w-12 h-12 text-[#00A859] mb-4" viewBox="0 0 24 24">
        <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
      </svg>
      <p class="text-[#00A859] font-black tracking-widest uppercase text-sm animate-pulse">
        Cargando reto...
      </p>
    </div>

    <!-- ── ACCESO EXPIRADO / TOKEN INVÁLIDO ── -->
    <div v-else-if="error === 'acceso-expirado'"
         class="flex flex-col items-center justify-center min-h-screen px-6 text-center">
      <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mb-6">
        <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-black text-[#1F2937] mb-3">Acceso no disponible</h1>
      <p class="text-gray-500 text-sm max-w-sm leading-relaxed">
        Este enlace ha caducado o ha sido desactivado por tu profesora o profesor.
        Solicita el QR actualizado para continuar.
      </p>
    </div>

    <!-- ── ERROR DE RED ── -->
    <div v-else-if="error === 'error-red'"
         class="flex flex-col items-center justify-center min-h-screen px-6 text-center">
      <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mb-6">
        <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667
                   1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0
                   L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-black text-[#1F2937] mb-3">Error de conexión</h1>
      <p class="text-gray-500 text-sm max-w-sm">Comprueba tu conexión a internet e intenta de nuevo.</p>
    </div>

    <!-- ── CONTENIDO DEL MICRORETO ── -->
    <div v-else-if="reto" class="relative z-10 max-w-3xl mx-auto px-4 py-8 md:px-6 md:py-12">

      <!-- Logo + badge de acceso temporal -->
      <div class="flex items-center justify-between mb-8
                  transition-all duration-700 ease-out"
           :class="isLoaded ? 'translate-y-0 opacity-100' : '-translate-y-4 opacity-0'">
        <span class="text-[#00A859] font-black text-lg tracking-tight">DuaLab</span>
        <span v-if="expira"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 border border-amber-200
                     text-amber-700 rounded-full text-[10px] font-bold uppercase tracking-wider">
          <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Acceso hasta {{ expiraFormateado }}
        </span>
      </div>

      <!-- Bloque principal -->
      <div class="bg-white rounded-[2rem] shadow-[0_20px_50px_rgb(0,0,0,0.06)]
                  overflow-hidden border border-gray-100
                  transition-all duration-700 delay-100 ease-out"
           :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'">

        <!-- Cabecera con imagen de fondo -->
        <div class="relative bg-gray-50 border-b border-gray-100 overflow-hidden">
          <div class="absolute inset-0 z-0 pointer-events-none">
            <div class="absolute inset-0 bg-gradient-to-r from-gray-50 via-gray-50/95 to-transparent z-10"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-gray-50 via-transparent to-transparent z-10"></div>
            <img v-if="imagenFondo" :src="imagenFondo" alt=""
                 class="w-full h-full object-cover object-right opacity-30 mix-blend-multiply" />
          </div>

          <div class="relative z-10 px-6 py-8 md:px-10 md:pt-10 md:pb-8">
            <p class="text-[#00A859] font-bold text-[10px] tracking-[0.2em] uppercase mb-4 flex items-center gap-2">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                         a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              DuaLab · Ficha de Reto
            </p>

            <h1 class="text-2xl sm:text-3xl md:text-4xl font-black text-[#1F2937]
                       tracking-tight leading-tight mb-3">
              {{ reto.titulo }}
            </h1>

            <div class="flex flex-wrap gap-2 mt-6">
              <span class="flex items-center gap-1.5 px-3 py-1.5 bg-[#1F2937] text-white
                           rounded-lg text-[10px] font-bold uppercase tracking-wider shadow-sm">
                {{ reto.empresa_nombre || reto.empresa?.nombre_comercial }}
              </span>
              <span v-if="reto.familia"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-white/80 border border-gray-200
                           text-[#1F2937] rounded-lg text-[10px] font-bold uppercase tracking-wider">
                {{ reto.familia }}
              </span>
              <span v-if="reto.ciclo"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-[#00A859]/10 border border-[#00A859]/20
                           text-[#00A859] rounded-lg text-[10px] font-bold uppercase tracking-wider">
                {{ reto.ciclo }}
              </span>
              <span v-if="reto.curso"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 border border-indigo-200
                           text-indigo-700 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                {{ reto.curso === 'transversal' ? 'Transversal: 1º y/o 2º' : reto.curso + 'º Curso' }}
              </span>
              <span v-if="reto.duracion"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-white/80 border border-gray-200
                           text-gray-600 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                {{ reto.duracion }}
              </span>
            </div>
          </div>
        </div>

        <!-- Cuerpo -->
        <div class="px-6 py-8 md:px-10 md:py-10 space-y-10">

          <!-- Quién es / Día a día -->
          <div v-if="reto.quien_es || reto.dia_a_dia"
               class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-if="reto.quien_es">
              <h3 class="flex items-center gap-2 text-[#00A859] font-black text-[11px]
                         uppercase tracking-[0.15em] mb-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8
                           a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
                </svg>
                ¿Quién es {{ reto.empresa_nombre || reto.empresa?.nombre_comercial }}?
              </h3>
              <p class="text-gray-600 text-sm leading-relaxed">{{ reto.quien_es }}</p>
            </div>
            <div v-if="reto.dia_a_dia">
              <h3 class="flex items-center gap-2 text-[#00A859] font-black text-[11px]
                         uppercase tracking-[0.15em] mb-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Su día a día
              </h3>
              <p class="text-gray-600 text-sm leading-relaxed">{{ reto.dia_a_dia }}</p>
            </div>
          </div>

          <!-- Dificultades -->
          <div v-if="reto.dificultades?.length">
            <h3 class="flex items-center gap-2 text-yellow-600 font-black text-[11px]
                       uppercase tracking-[0.15em] mb-3">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667
                         1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0
                         L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
              Dificultades
            </h3>
            <ul class="space-y-2 pl-1">
              <li v-for="(item, i) in reto.dificultades" :key="i"
                  class="flex items-start gap-3 text-sm text-gray-700">
                <span class="text-yellow-500 font-black mt-0.5 shrink-0">•</span>
                <span>{{ item }}</span>
              </li>
            </ul>
          </div>

          <!-- Pregunta del reto -->
          <div class="bg-gradient-to-r from-gray-50 to-white border-l-4 border-[#00A859]
                      p-6 rounded-r-2xl shadow-sm border-y border-r border-gray-100">
            <p class="text-[#00A859] font-black uppercase text-[10px] tracking-[0.2em] mb-3">
              Pregunta del Reto
            </p>
            <p class="text-xl md:text-2xl font-bold text-[#1F2937] leading-snug">
              {{ reto.pregunta_reto }}
            </p>
          </div>

          <!-- Qué necesitan / Limitaciones -->
          <div v-if="reto.que_necesitan?.length || reto.limitaciones?.length"
               class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-if="reto.que_necesitan?.length">
              <h3 class="flex items-center gap-2 text-[#00A859] font-black text-[11px]
                         uppercase tracking-[0.15em] mb-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7
                           a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2
                           M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Qué necesitan
              </h3>
              <ul class="space-y-2 pl-1">
                <li v-for="(item, i) in reto.que_necesitan" :key="i"
                    class="flex items-start gap-3 text-sm text-gray-700">
                  <span class="text-[#00A859] font-black mt-0.5 shrink-0">•</span>
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
            <div v-if="reto.limitaciones?.length">
              <h3 class="flex items-center gap-2 text-red-500 font-black text-[11px]
                         uppercase tracking-[0.15em] mb-3">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0
                           015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                Limitaciones
              </h3>
              <ul class="space-y-2 pl-1">
                <li v-for="(item, i) in reto.limitaciones" :key="i"
                    class="flex items-start gap-3 text-sm text-gray-700">
                  <span class="text-red-500 font-black mt-0.5 shrink-0">•</span>
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Prototipos -->
          <div v-if="reto.prototipos?.length">
            <h3 class="flex items-center gap-2 text-[#00A859] font-black text-[11px]
                       uppercase tracking-[0.15em] mb-3">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3
                         m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547
                         A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531
                         c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
              </svg>
              Prototipos esperados
            </h3>
            <ul class="space-y-2 pl-1">
              <li v-for="(item, i) in reto.prototipos" :key="i"
                  class="flex items-start gap-3 text-sm text-gray-700">
                <span class="text-[#00A859] font-black mt-0.5 shrink-0">→</span>
                <span>{{ item }}</span>
              </li>
            </ul>
          </div>

          <!-- ODS -->
          <div v-if="reto.ods_sugeridos?.length">
            <h3 class="flex items-center gap-2 text-indigo-600 font-black text-[11px]
                       uppercase tracking-[0.15em] mb-3">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945
                         M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0
                         2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0
                         11-18 0 9 9 0 0118 0z"/>
              </svg>
              ODS relacionados
            </h3>
            <div class="flex flex-wrap gap-2">
              <span v-for="(ods, i) in reto.ods_sugeridos" :key="i"
                    class="px-3 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700
                           rounded-full text-xs font-semibold">
                {{ ods }}
              </span>
            </div>
          </div>

          <!-- Soft skills -->
          <div v-if="reto.soft_skills?.length">
            <h3 class="flex items-center gap-2 text-purple-600 font-black text-[11px]
                       uppercase tracking-[0.15em] mb-3">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283
                         -.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283
                         .356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              Soft skills
            </h3>
            <div class="flex flex-wrap gap-2">
              <span v-for="(skill, i) in reto.soft_skills" :key="i"
                    class="px-3 py-1 bg-purple-50 border border-purple-200 text-purple-700
                           rounded-full text-xs font-semibold">
                {{ skill }}
              </span>
            </div>
          </div>

        </div>
      </div>

      <!-- Pie de página -->
      <p class="text-center text-[10px] text-gray-400 mt-8 tracking-wider uppercase">
        DuaLab Studio · Acceso temporal para alumnado
      </p>
    </div>

  </div>
</template>
