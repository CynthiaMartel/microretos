<!-- Miniatura de un reto/proyecto (diseños crear_encuentro.png y crear_proyecto*.png).
     Con `src` pinta la imagen; sin ella (los retos aún no tienen imagen), una ilustración
     propia con los colores de marca: portátil con gráfica sobre fondo degradado suave.
     El tamaño lo pone quien la usa (clases h-*/w-* en el componente). Decorativa: alt="". -->
<script setup>
defineProps({
  src: { type: String, default: '' },
})
</script>

<template>
  <img v-if="src" :src="src" alt="" class="shrink-0 rounded-xl object-cover" />
  <span v-else aria-hidden="true"
        class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-[#E6F0F8] via-[#F4F8FB] to-[#EAF5F0]">
    <svg viewBox="0 0 120 90" class="h-[82%] w-[82%]" fill="none">
      <!-- planta -->
      <path d="M14 62c-6-8-4-18 2-22 2 8 1 15-2 22Z" fill="#509928" opacity=".85"/>
      <path d="M16 62c4-9 11-12 16-10-4 6-9 9-16 10Z" fill="#6EC13F" opacity=".85"/>
      <path d="M15 62c-5-5-11-6-14-4 3 4 8 5 14 4Z" fill="#3D7A1E" opacity=".7"/>
      <rect x="9" y="61" width="13" height="11" rx="2.5" fill="#FFFFFF" stroke="#C9D5E1"/>
      <!-- pantalla -->
      <rect x="30" y="16" width="70" height="46" rx="4" fill="#17283E"/>
      <rect x="33.5" y="19.5" width="63" height="39" rx="2" fill="#FFFFFF"/>
      <!-- gráfica -->
      <rect x="39" y="44" width="5" height="10" rx="1" fill="#93BDE0"/>
      <rect x="47" y="38" width="5" height="16" rx="1" fill="#3072AA"/>
      <rect x="55" y="41" width="5" height="13" rx="1" fill="#19A7A8"/>
      <rect x="63" y="32" width="5" height="22" rx="1" fill="#3072AA"/>
      <rect x="71" y="36" width="5" height="18" rx="1" fill="#FF8920"/>
      <rect x="79" y="27" width="5" height="27" rx="1" fill="#509928"/>
      <path d="M39 33l9-5 8 3 9-7 9 2 11-6" stroke="#FF8920" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      <rect x="39" y="23.5" width="18" height="2.5" rx="1.25" fill="#D9E1EA"/>
      <!-- base del portátil -->
      <path d="M24 62h82l-5 7H29l-5-7Z" fill="#C9D5E1"/>
      <rect x="57" y="62" width="16" height="2" rx="1" fill="#A9B8C8"/>
    </svg>
  </span>
</template>
