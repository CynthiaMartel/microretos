<!-- Plantilla de las páginas de acceso (diseño "Página de acceso a DuaLab.png"): columna
     de formulario a la izquierda (slot) y foto real con el lema a la derecha (solo lg+).
     La usan LoginView (/ y /login) y RestablecerContrasena. -->
<script setup>
import { WEB_PUBLICA } from '../config/frontoffice.js'
import EnlaceProximamente from './EnlaceProximamente.vue'
import fotoAcceso from '../assets/dua_10.jpeg'

const miradas = [
  { label: 'Centros',          dot: 'bg-centros' },
  { label: 'Empresas',         dot: 'bg-empresas' },
  { label: 'Alumnado',         dot: 'bg-alumnos' },
  { label: 'Administraciones', dot: 'bg-administraciones' },
]

// Páginas legales aún sin publicar
const legales = ['Privacidad', 'Aviso legal', 'Accesibilidad']
</script>

<template>
  <!-- En escritorio ocupa exactamente la ventana: se ve entera al entrar. El pie fijo (AppCredit) mide
       ~2rem en escritorio pero App.vue reserva pb-12; el lg:-mb-4 absorbe la diferencia para que no quede una franja vacía. -->
  <div class="min-h-[calc(100dvh-3rem)] lg:h-[calc(100dvh-2rem)] lg:-mb-4 bg-white font-sans text-[#1F2937] grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">

    <!-- ── Columna formulario ──────────────────────────────────────────── -->
    <section class="flex flex-col min-h-0 lg:overflow-y-auto px-4 sm:px-10 lg:px-12 xl:px-16 py-4 sm:py-8 lg:py-6 [@media(min-width:1024px)_and_(max-height:820px)]:py-4">

      <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 min-w-0">
          <img src="../assets/logo_colores.png" alt="" class="h-9 sm:h-11 [@media(min-width:1024px)_and_(max-height:820px)]:h-9 w-auto object-contain shrink-0" />
          <span class="font-black text-2xl sm:text-3xl [@media(min-width:1024px)_and_(max-height:820px)]:text-2xl tracking-tight uppercase text-acceso-tinta">
            Dua<span class="text-transparent bg-clip-text bg-gradient-to-r from-acceso-azul to-[#00B6C7]">Lab</span>
          </span>
        </div>
        <a :href="WEB_PUBLICA" class="inline-flex items-center gap-2 min-h-9 text-sm font-medium text-acceso-azul hover:text-acceso-azul-hover">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m0 0l6 6m-6-6l6-6"/>
          </svg>
          Volver a la web
        </a>
      </header>

      <div class="flex-1 flex items-center">
        <div class="w-full max-w-md xl:max-w-xl mx-auto lg:mx-0 py-6 sm:py-10 lg:py-6 [@media(min-width:1024px)_and_(max-height:820px)]:py-3">
          <slot />
        </div>
      </div>

      <nav aria-label="Información legal" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
        <template v-for="(nombre, i) in legales" :key="nombre">
          <span v-if="i > 0" aria-hidden="true">·</span>
          <EnlaceProximamente>
            <span class="inline-flex items-center min-h-9 hover:text-acceso-tinta">{{ nombre }}</span>
          </EnlaceProximamente>
        </template>
      </nav>
    </section>

    <!-- ── Columna imagen (solo escritorio) ────────────────────────────── -->
    <aside class="hidden lg:block relative overflow-hidden bg-azul-noche" aria-hidden="true">
      <img :src="fotoAcceso" alt="" class="absolute inset-0 w-full h-full object-cover object-[center_45%]" />

      <!-- Halos de color: coordenadas en px de la foto de referencia (906×941), escaladas al panel -->
      <!-- Esquina superior izquierda: banda azul y arco verde-lima translúcidos -->
      <svg class="absolute top-0 left-0 w-[30%] h-[25%]" viewBox="0 0 272 230" preserveAspectRatio="none">
        <defs>
          <linearGradient id="accesoHaloAzul" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0%" stop-color="#1E88E5" />
            <stop offset="100%" stop-color="#4FB3F6" />
          </linearGradient>
          <linearGradient id="accesoHaloVerde" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0%" stop-color="#19A7A8" />
            <stop offset="50%" stop-color="#3DB54A" />
            <stop offset="100%" stop-color="#9BCB3C" />
          </linearGradient>
        </defs>
        <path d="M0 34 C 45 30, 95 20, 135 0 C 75 30, 30 75, 0 132 Z" fill="url(#accesoHaloAzul)" opacity="0.85" />
        <path d="M0 132 C 30 75, 75 30, 128 0 L 262 0 C 150 30, 40 110, 0 226 Z" fill="url(#accesoHaloVerde)" opacity="0.9" />
      </svg>

      <!-- Ola inferior: cintas por el borde izquierdo, línea cian central y abanico a la derecha -->
      <svg class="absolute bottom-0 left-0 w-full h-[max(62%,28rem)]" viewBox="0 421 906 520" preserveAspectRatio="none">
        <defs>
          <linearGradient id="accesoCintaIzq" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#1E88E5" />
            <stop offset="40%" stop-color="#3DB54A" />
            <stop offset="75%" stop-color="#9BCB3C" />
            <stop offset="100%" stop-color="#FF9F1C" />
          </linearGradient>
          <linearGradient id="accesoCintaAzul" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0%" stop-color="#1976D2" />
            <stop offset="100%" stop-color="#29B6F6" />
          </linearGradient>
          <linearGradient id="accesoCintaVerde" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0%" stop-color="#2FA84F" />
            <stop offset="100%" stop-color="#9BCB3C" />
          </linearGradient>
          <linearGradient id="accesoCintaNaranja" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0%" stop-color="#FF8920" />
            <stop offset="100%" stop-color="#FFB833" />
          </linearGradient>
        </defs>
        <path d="M0 682 C 80 660, 170 642, 270 640 C 150 668, 60 740, 30 941 L 0 941 Z" fill="url(#accesoCintaIzq)" opacity="0.92" />
        <path d="M200 656 C 350 626, 520 608, 720 596 L 720 606 C 520 620, 350 640, 200 668 Z" fill="#4FB3F6" opacity="0.55" />
        <path d="M600 650 C 700 610, 810 530, 906 438 L 906 640 Z" fill="url(#accesoCintaAzul)" opacity="0.9" />
        <path d="M545 662 C 640 640, 720 600, 792 552 L 740 672 Z" fill="url(#accesoCintaNaranja)" opacity="0.95" />
        <path d="M700 668 C 740 620, 770 578, 800 548 C 840 525, 880 512, 906 505 L 906 650 Z" fill="url(#accesoCintaVerde)" opacity="0.92" />
        <path d="M48 941 C 50 830, 110 715, 240 670 C 360 632, 470 636, 560 664 C 650 684, 770 652, 906 604 L 906 941 Z" fill="#17283E" />
      </svg>

      <div class="absolute inset-x-0 bottom-0 px-10 xl:px-14 pb-8 xl:pb-12 [@media(min-width:1024px)_and_(max-height:820px)]:pb-6 text-center text-white">
        <p class="text-3xl xl:text-4xl 2xl:text-5xl font-black tracking-tight leading-[1.05]">
          Cuatro miradas.<br />Un mismo futuro.
        </p>
        <p class="mt-3 2xl:mt-4 text-sm 2xl:text-lg text-white/90 tracking-wide">
          Centros, empresas, alumnado y administraciones, conectados.
        </p>
        <ul class="mt-5 2xl:mt-6 [@media(min-width:1024px)_and_(max-height:820px)]:mt-4 flex flex-wrap justify-center gap-2 2xl:gap-3">
          <li v-for="m in miradas" :key="m.label"
              class="inline-flex items-center gap-2 2xl:gap-2.5 rounded-full border border-white/40 px-3.5 2xl:px-5 py-1.5 2xl:py-2 text-xs 2xl:text-sm font-medium">
            <span class="w-3 h-3 rounded-full" :class="m.dot"></span>
            {{ m.label }}
          </li>
        </ul>
      </div>
    </aside>
  </div>
</template>
