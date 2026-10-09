<!-- Halos (cintas de colores de la marca) de los banners del panel docente (InicioDocente.vue),
     copia de "Aplicación moodboard a dashboard.png". Decorativo: ocupa todo su contenedor
     (preserveAspectRatio="none"), así que el padre decide tamaño, posición y máscara.
     Ids de degradado únicos por instancia para poder usarlo varias veces en la misma página. -->
<script setup>
import { useId } from 'vue'

const id = useId()
const g = (nombre) => `url(#${id}-${nombre})`
</script>

<template>
  <svg aria-hidden="true" class="pointer-events-none" viewBox="0 0 1000 300" preserveAspectRatio="none">
    <defs>
      <linearGradient :id="`${id}-azul`" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#2F7FD8" /><stop offset="100%" stop-color="#1E5FB8" /></linearGradient>
      <linearGradient :id="`${id}-azul-claro`" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#9BD3F7" /><stop offset="100%" stop-color="#4FA3E8" /></linearGradient>
      <linearGradient :id="`${id}-naranja`" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#FFB45C" /><stop offset="45%" stop-color="#FF8920" /><stop offset="100%" stop-color="#F57A0E" /></linearGradient>
      <linearGradient :id="`${id}-melocoton`" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#FFC48A" stop-opacity="0.85" /><stop offset="100%" stop-color="#FFE6CC" stop-opacity="0.15" /></linearGradient>
      <linearGradient :id="`${id}-verde`" x1="1" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3D8A22" /><stop offset="100%" stop-color="#6EC13F" /></linearGradient>
      <linearGradient :id="`${id}-turquesa`" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#4CC7D6" /><stop offset="100%" stop-color="#19A7A8" /></linearGradient>
      <linearGradient :id="`${id}-cian`" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#9BE7EF" stop-opacity="0.9" /><stop offset="100%" stop-color="#6FD6E3" stop-opacity="0.75" /></linearGradient>
    </defs>
    <!-- Izquierda: azul (debajo) en dos capas, se afila hacia la punta -->
    <path d="M0,0 L40,0 C52,46 70,82 96,118 C60,112 26,108 6,103 C0,70 0,30 0,0 Z" :fill="g('azul-claro')" opacity="0.9" />
    <path d="M40,0 L76,0 C98,46 140,104 222,202 C160,168 120,140 96,118 C70,82 52,46 40,0 Z" :fill="g('azul')" />
    <!-- Izquierda: naranja encima de la azul, con velo melocotón y brillo -->
    <path d="M240,192 C330,208 435,228 540,256 L560,300 L300,300 C285,262 265,222 240,192 Z" :fill="g('melocoton')" />
    <path d="M0,72 C60,100 150,150 240,192 C275,212 305,238 332,262 L300,300 L0,300 Z" :fill="g('naranja')" />
    <path d="M110,185 C165,220 230,258 300,300 L262,300 C200,265 150,232 110,185 Z" fill="#FFFFFF" opacity="0.35" />
    <!-- Derecha: verde desde arriba, franja clara + cuerpo oscuro -->
    <path d="M836,0 L870,0 C852,80 822,150 780,206 C800,140 822,70 836,0 Z" fill="#8BD45F" opacity="0.9" />
    <path d="M870,0 L1000,0 L1000,40 C958,100 880,170 780,206 C822,150 852,80 870,0 Z" :fill="g('verde')" />
    <!-- Derecha: ola turquesa que sube desde abajo, con capa cian encima y ola oscura debajo -->
    <path d="M500,300 C620,252 760,200 900,112 C940,86 970,64 1000,46 L1000,68 C900,128 780,222 546,300 Z" :fill="g('cian')" />
    <path d="M546,300 C700,242 800,190 900,128 C940,102 975,82 1000,68 L1000,300 Z" :fill="g('turquesa')" />
    <path d="M600,300 C700,268 790,228 870,178" fill="none" stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="2" vector-effect="non-scaling-stroke" />
    <path d="M720,300 C830,270 925,222 1000,165 L1000,300 Z" fill="#138C9C" opacity="0.55" />
  </svg>
</template>
