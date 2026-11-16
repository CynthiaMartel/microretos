<!-- Aviso "Hay una versión nueva" para las herramientas sin pila de avisos propia (Central, Nexo).
     La app y la comercial lo llevan dentro de su pila de avisos en App.vue. Mismo bocadillo y misma
     lógica (composables/useNuevaVersion.js): necesita __APP_VERSION__ y version.json en el build. -->
<script setup>
import { ref, onMounted } from 'vue'
import { useNuevaVersion } from '../composables/useNuevaVersion.js'

const props = defineProps({
  // Router de la herramienta: comprueba también al navegar. Sin él (Nexo), al volver a la
  // pestaña y cada pocos minutos
  router: { type: Object, default: null },
  texto: { type: String, default: 'Recarga para ver las novedades. Guarda antes lo que tengas a medias.' },
})

const { hayNuevaVersion, iniciarComprobacion, recargarLimpio, posponer } = useNuevaVersion()
const recargando = ref(false)

const recargar = () => {
  recargando.value = true
  recargarLimpio()
}

onMounted(() => iniciarComprobacion(props.router))
</script>

<template>
  <div class="pointer-events-none fixed bottom-4 right-4 z-[60] w-[min(22rem,calc(100vw-2rem))]">
    <Transition name="toast-slide">
      <div v-if="hayNuevaVersion" role="status"
           class="pointer-events-auto relative rounded-2xl border border-white/15 bg-[#1a2332]/95 p-4 shadow-2xl backdrop-blur-sm">
        <button type="button" title="Más tarde" aria-label="Recordármelo más tarde"
                class="absolute right-1 top-1 flex h-9 w-9 items-center justify-center rounded-full text-white/40 transition-colors hover:bg-white/10 hover:text-white/70"
                @click="posponer">
          <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
        <div class="flex items-start gap-3 pr-6">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sky-500/30 bg-sky-500/15" aria-hidden="true">
            <svg class="h-4 w-4 text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          </span>
          <div class="min-w-0">
            <p class="text-sm font-black leading-tight text-white">Hay una versión nueva</p>
            <p class="mt-1 text-xs leading-relaxed text-white/60">{{ texto }}</p>
          </div>
        </div>
        <button type="button" :disabled="recargando"
                class="mt-3 min-h-9 w-full rounded-full bg-rol-fuerte py-2 text-xs font-black uppercase tracking-widest text-white shadow-md transition-colors hover:bg-rol disabled:cursor-not-allowed disabled:opacity-50"
                @click="recargar">
          {{ recargando ? 'Recargando…' : 'Recargar' }}
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.toast-slide-enter-active, .toast-slide-leave-active { transition: opacity .25s ease, transform .25s ease; }
.toast-slide-enter-from, .toast-slide-leave-to { opacity: 0; transform: translateY(8px); }
</style>
