<!-- Instrucciones para instalar DuaLab como app cuando el navegador no ofrece su diálogo
     (Safari, Firefox, o Chrome antes de considerarla instalable). Ver useInstalarApp. -->
<script setup>
import { computed } from 'vue'
import ModalTaller from './ModalTaller.vue'

const props = defineProps({
  abierto:    { type: Boolean, required: true },
  plataforma: { type: String, required: true },
})
defineEmits(['cerrar'])

const GUIAS = {
  ios: {
    navegador: 'iPhone o iPad',
    pasos: [
      'Abre DuaLab en Safari.',
      'Pulsa el botón Compartir (el cuadrado con la flecha hacia arriba).',
      'Elige «Añadir a pantalla de inicio» y confirma con «Añadir».',
    ],
    nota: 'La app guarda su propia sesión: la primera vez que la abras tendrás que iniciar sesión de nuevo.',
  },
  'safari-mac': {
    navegador: 'Safari en Mac',
    pasos: [
      'En la barra de menús, abre «Archivo».',
      'Elige «Añadir al Dock» y confirma con «Añadir».',
    ],
    nota: 'Necesitas macOS Sonoma (14) o posterior.',
  },
  'firefox-android': {
    navegador: 'Firefox en Android',
    pasos: [
      'Pulsa el menú ⋮ de Firefox.',
      'Elige «Instalar» o «Añadir a pantalla de inicio».',
    ],
  },
  firefox: {
    navegador: 'Firefox en ordenador',
    pasos: [
      'Firefox de escritorio no instala apps web: puedes seguir usando DuaLab en esta pestaña, funciona igual.',
      'Si quieres tenerla como app, ábrela en Chrome, Edge o Safari y usa «Instalar app».',
    ],
  },
  otro: {
    navegador: 'tu navegador',
    pasos: [
      'Busca el icono de instalar en la barra de direcciones (una pantalla con una flecha).',
      'O abre el menú del navegador (⋮) y elige «Instalar DuaLab» o «Añadir a pantalla de inicio».',
    ],
    nota: 'Si no aparece, navega un poco por la app y vuelve a intentarlo: el navegador tarda unos segundos en ofrecerlo.',
  },
}

const guia = computed(() => GUIAS[props.plataforma] ?? GUIAS.otro)
</script>

<template>
  <ModalTaller :abierto="abierto" titulo="Instalar DuaLab como app"
               :subtitulo="`Cómo hacerlo en ${guia.navegador}`"
               ancho="w-[min(30rem,calc(100vw-2rem))]" marca @cerrar="$emit('cerrar')">
    <div class="flex items-center gap-3 sm:gap-4">
      <img src="/pwa-192x192.png" alt="" class="h-12 w-12 shrink-0 sm:h-14 sm:w-14" />
      <p class="min-w-0 text-sm text-texto-secundario">
        Con DuaLab instalada la abres desde su icono, a pantalla completa y sin las barras del navegador.
      </p>
    </div>

    <ol class="mt-4 space-y-3">
      <li v-for="(paso, i) in guia.pasos" :key="i" class="flex gap-3 text-sm text-azul-noche">
        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rol/15 text-xs font-bold text-rol-fuerte">
          {{ i + 1 }}
        </span>
        <span class="min-w-0 break-words pt-0.5">{{ paso }}</span>
      </li>
    </ol>

    <p v-if="guia.nota" class="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-xs text-texto-secundario">
      {{ guia.nota }}
    </p>

    <template #pie>
      <div class="flex justify-center">
        <button type="button" @click="$emit('cerrar')"
                class="inline-flex h-11 items-center justify-center rounded-full bg-rol-fuerte px-8 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rol-fuerte/90">
          Entendido
        </button>
      </div>
    </template>
  </ModalTaller>
</template>
