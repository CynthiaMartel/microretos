<!-- Banner de bienvenida de la app comercial: mismo diseño que el del dashboard del docente
     (InicioDocente.vue, «idea_dashboard.png»): degradado suave, foto real a la derecha con las
     cintas de los colores del logo y saludo con las acciones a la izquierda.
     Las cintas son una copia de las de InicioDocente (ids con prefijo bc- para no chocar). -->
<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../src/stores/auth.js'
import foto from '../../src/assets/bienvenida_caso_real.webp'

defineProps({
  enCurso:  { type: Number, default: 0 },
  firmadas: { type: Number, default: 0 },
})
const emit = defineEmits(['nueva'])

const authStore = useAuthStore()
const primerNombre = computed(() => (authStore.userName || '').split(' ')[0])
</script>

<template>
  <section class="@container relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#E8F3E2] via-[#EFF6EA] to-transparent @3xl:min-h-[230px]">
    <div class="relative h-36 sm:h-44 @lg:absolute @lg:inset-y-0 @lg:right-0 @lg:h-auto @lg:w-[52%] @lg:[mask-image:linear-gradient(to_right,black_82%,transparent)] @lg:[-webkit-mask-image:linear-gradient(to_right,black_82%,transparent)]">
      <img :src="foto" alt="Sesión de trabajo de un reto con alumnado de FP y profesionales"
           class="absolute inset-0 h-full w-full object-cover object-[60%_50%] @lg:[mask-image:linear-gradient(to_left,black_35%,rgba(0,0,0,0.3))] @lg:[-webkit-mask-image:linear-gradient(to_left,black_35%,rgba(0,0,0,0.3))]" />
        <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 1000 300" preserveAspectRatio="none">
          <!-- Copia de los halos del banner de "Aplicación moodboard a dashboard.png":
               izquierda = cinta azul que cae y se afila + cinta naranja encima que cubre la esquina inferior;
               derecha = cinta verde que baja desde arriba + gran ola turquesa que sube hasta la esquina -->
          <defs>
            <linearGradient id="bc-azul" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#2F7FD8" /><stop offset="100%" stop-color="#1E5FB8" /></linearGradient>
            <linearGradient id="bc-azul-claro" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#9BD3F7" /><stop offset="100%" stop-color="#4FA3E8" /></linearGradient>
            <linearGradient id="bc-naranja" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#FFB45C" /><stop offset="45%" stop-color="#FF8920" /><stop offset="100%" stop-color="#F57A0E" /></linearGradient>
            <linearGradient id="bc-melocoton" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#FFC48A" stop-opacity="0.85" /><stop offset="100%" stop-color="#FFE6CC" stop-opacity="0.15" /></linearGradient>
            <linearGradient id="bc-verde" x1="1" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3D8A22" /><stop offset="100%" stop-color="#6EC13F" /></linearGradient>
            <linearGradient id="bc-turquesa" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#4CC7D6" /><stop offset="100%" stop-color="#19A7A8" /></linearGradient>
            <linearGradient id="bc-cian" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#9BE7EF" stop-opacity="0.9" /><stop offset="100%" stop-color="#6FD6E3" stop-opacity="0.75" /></linearGradient>
          </defs>
          <!-- Izquierda: azul (debajo) en dos capas, se afila hacia la punta -->
          <path d="M0,0 L40,0 C52,46 70,82 96,118 C60,112 26,108 6,103 C0,70 0,30 0,0 Z" fill="url(#bc-azul-claro)" opacity="0.9" />
          <path d="M40,0 L76,0 C98,46 140,104 222,202 C160,168 120,140 96,118 C70,82 52,46 40,0 Z" fill="url(#bc-azul)" />
          <!-- Izquierda: naranja ENCIMA de la azul, en la línea de las demás cintas; por la izquierda
               llega hasta la esquina inferior para que no asome la foto, con velo melocotón y brillo -->
          <path d="M240,192 C330,208 435,228 540,256 L560,300 L300,300 C285,262 265,222 240,192 Z" fill="url(#bc-melocoton)" />
          <path d="M0,72 C60,100 150,150 240,192 C275,212 305,238 332,262 L300,300 L0,300 Z" fill="url(#bc-naranja)" />
          <path d="M110,185 C165,220 230,258 300,300 L262,300 C200,265 150,232 110,185 Z" fill="#FFFFFF" opacity="0.35" />
          <!-- Derecha: verde desde arriba, franja clara + cuerpo oscuro, se afila hacia abajo a la izquierda -->
          <path d="M836,0 L870,0 C852,80 822,150 780,206 C800,140 822,70 836,0 Z" fill="#8BD45F" opacity="0.9" />
          <path d="M870,0 L1000,0 L1000,40 C958,100 880,170 780,206 C822,150 852,80 870,0 Z" fill="url(#bc-verde)" />
          <!-- Derecha: ola turquesa que sube desde abajo, con capa cian encima y ola oscura debajo -->
          <path d="M500,300 C620,252 760,200 900,112 C940,86 970,64 1000,46 L1000,68 C900,128 780,222 546,300 Z" fill="url(#bc-cian)" />
          <path d="M546,300 C700,242 800,190 900,128 C940,102 975,82 1000,68 L1000,300 Z" fill="url(#bc-turquesa)" />
          <path d="M600,300 C700,268 790,228 870,178" fill="none" stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="2" vector-effect="non-scaling-stroke" />
          <path d="M720,300 C830,270 925,222 1000,165 L1000,300 Z" fill="#138C9C" opacity="0.55" />
        </svg>
      <div class="absolute inset-x-0 bottom-0 flex h-1.5 @lg:hidden"><span class="flex-1 bg-centros" /><span class="flex-1 bg-empresas" /><span class="flex-1 bg-administraciones" /><span class="flex-1 bg-alumnos" /></div>
    </div>
    <div class="relative z-10 p-5 sm:p-6 @lg:max-w-[46%]">
      <p class="text-xs font-bold uppercase tracking-[0.15em] text-texto-secundario">Equipo comercial</p>
      <h1 class="mt-1 font-heading text-2xl font-bold tracking-tight text-azul-noche sm:text-3xl">¡Hola, {{ primerNombre }}!</h1>
      <p class="mt-1 font-heading text-lg font-bold leading-snug text-azul-noche sm:text-xl">
        Cada entrevista es una empresa <br class="hidden sm:block" />que <span class="text-empresas-dark">abre sus puertas a la FP</span>
      </p>
      <p class="mt-2 text-sm text-gray-600">
        Regístrala, hazle la entrevista diagnóstica y enséñale en directo un reto para el alumnado.
        <template v-if="enCurso || firmadas">
          Llevas <strong class="text-azul-noche">{{ firmadas }}</strong> {{ firmadas === 1 ? 'firmada' : 'firmadas' }}
          y <strong class="text-azul-noche">{{ enCurso }}</strong> en curso.
        </template>
      </p>
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="accion-principal" @click="emit('nueva')">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
          Nueva entrevista
        </button>
      </div>
    </div>
  </section>
</template>
