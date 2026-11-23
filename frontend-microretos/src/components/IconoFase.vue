<!-- Icono de una fase del proyecto (Heroicons outline, mismo estilo que el resto de la web).
     Sustituye a los emojis de FASES_PROYECTO.icono / fasesConfig.icono. El tamaño y el color
     los pone quien lo usa (class="h-4 w-4 text-…"). Decorativo: aria-hidden. -->
<script setup>
import { computed } from 'vue'
import { UserGroupIcon, MagnifyingGlassIcon, LightBulbIcon, WrenchScrewdriverIcon, AcademicCapIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  num: { type: Number, required: true },
})

const ICONOS = [UserGroupIcon, MagnifyingGlassIcon, LightBulbIcon, WrenchScrewdriverIcon, AcademicCapIcon]
const icono = computed(() => ICONOS[props.num] ?? LightBulbIcon)
</script>

<template>
  <component :is="icono" aria-hidden="true" />
</template>
