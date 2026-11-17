<!-- «¿Para qué sirve?» en la cabecera de una vista (junto a sus acciones, como «Ayuda»): explica la
     vista en una burbuja al pasar el cursor o con el foco y, en táctil, al pulsar (no depende solo
     del hover). Se cierra con Escape o al pulsar fuera. Uso: Calendario y Agenda de prácticas, para
     distinguir una de otra. -->
<script setup>
import { ref, onMounted, onBeforeUnmount, useId } from 'vue'

defineProps({
  titulo: { type: String, required: true },
  // Párrafos de la explicación
  textos: { type: Array, required: true },
})

const abierto = ref(false)
const fijado = ref(false)          // abierto por clic/toque: no se cierra al quitar el cursor
const raiz = ref(null)
const id = useId()

const alPulsarFuera = (e) => { if (raiz.value && !raiz.value.contains(e.target)) { abierto.value = false; fijado.value = false } }
const alTeclear = (e) => { if (e.key === 'Escape') { abierto.value = false; fijado.value = false } }
onMounted(() => { document.addEventListener('click', alPulsarFuera); document.addEventListener('keydown', alTeclear) })
onBeforeUnmount(() => { document.removeEventListener('click', alPulsarFuera); document.removeEventListener('keydown', alTeclear) })

function alternar() { fijado.value = !fijado.value; abierto.value = fijado.value }
</script>

<template>
  <div ref="raiz" class="relative" @mouseenter="abierto = true" @mouseleave="abierto = fijado">
    <button type="button" class="accion-secundaria" :aria-expanded="abierto" :aria-controls="id"
            @click="alternar" @focus="abierto = true" @blur="abierto = fijado">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      ¿Para qué sirve?
    </button>
    <div v-show="abierto" :id="id" role="tooltip"
         class="absolute right-0 top-full z-40 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-2xl bg-azul-noche p-4 text-left text-sm leading-relaxed text-white shadow-2xl">
      <p class="font-bold">{{ titulo }}</p>
      <p v-for="(t, i) in textos" :key="i" class="mt-1.5 text-white/85">{{ t }}</p>
    </div>
  </div>
</template>
