<!-- Botón «Guía» de la TopBar (en móvil solo el icono): despliega el recorrido del menú lateral y el de la pantalla actual (useGuia).
     Lleva un punto hasta que se usa por primera vez, para que se descubra. -->
<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useGuia } from '../composables/useGuia.js'

const route = useRoute()
const { tourPantalla, recorrerMenu, recorrerPantalla } = useGuia()

const CLAVE_USADA = 'dualab_guia_usada'
const leerUsada = () => { try { return localStorage.getItem(CLAVE_USADA) === '1' } catch { return true } }
const usada = ref(leerUsada())

const abierto = ref(false)
const raiz = ref(null)

function marcarUsada() {
  usada.value = true
  try { localStorage.setItem(CLAVE_USADA, '1') } catch { /* sin almacenamiento: el punto vuelve a salir */ }
}

function elegir(accion) {
  abierto.value = false
  marcarUsada()
  accion()
}

const alPulsarFuera = (e) => { if (raiz.value && !raiz.value.contains(e.target)) abierto.value = false }
const alTeclear = (e) => { if (e.key === 'Escape') abierto.value = false }
onMounted(() => { document.addEventListener('click', alPulsarFuera); document.addEventListener('keydown', alTeclear) })
onBeforeUnmount(() => { document.removeEventListener('click', alPulsarFuera); document.removeEventListener('keydown', alTeclear) })
watch(() => route.fullPath, () => { abierto.value = false })
</script>

<template>
  <div ref="raiz" class="relative shrink-0">
    <button type="button" @click="abierto = !abierto" data-tour="tb-guia"
            aria-haspopup="menu" :aria-expanded="abierto" aria-label="Guía" title="Guía"
            class="relative flex h-10 min-w-10 items-center justify-center gap-2 rounded-full px-2.5
                   text-white/70 transition-colors duration-150 hover:bg-white/10 hover:text-white"
            :class="abierto && 'bg-white/10 text-white'">
      <svg class="h-5 w-5 shrink-0 text-centros-light" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
      </svg>
      <span class="hidden whitespace-nowrap text-[13px] font-bold md:inline">Guía</span>
      <span v-if="!usada" class="absolute right-1.5 top-1.5 h-2.5 w-2.5 animate-pulse rounded-full bg-alumnos ring-2 ring-azul-noche" aria-hidden="true" />
    </button>

    <Transition name="menu-guia">
      <div v-if="abierto" role="menu"
           class="fixed inset-x-4 top-[4.5rem] overflow-hidden sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-72 rounded-2xl bg-white py-1.5 text-sm text-azul-noche shadow-xl ring-1 ring-black/5">
        <p class="border-b border-gray-100 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-texto-secundario">Guía paso a paso</p>
        <button type="button" role="menuitem" @click="elegir(recorrerPantalla)" :disabled="!tourPantalla"
                class="flex w-full items-start gap-3 px-4 py-2.5 text-left hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent">
          <svg class="mt-0.5 h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span class="min-w-0">
            <span class="block font-semibold">Esta pantalla</span>
            <span class="block text-xs text-texto-secundario">{{ tourPantalla ? 'Qué puedes hacer aquí' : 'Esta pantalla aún no tiene guía' }}</span>
          </span>
        </button>
        <button type="button" role="menuitem" @click="elegir(recorrerMenu)"
                class="flex w-full items-start gap-3 px-4 py-2.5 text-left hover:bg-gray-50">
          <svg class="mt-0.5 h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/>
          </svg>
          <span class="min-w-0">
            <span class="block font-semibold">Recorrido del menú</span>
            <span class="block text-xs text-texto-secundario">Para qué sirve cada sección</span>
          </span>
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.menu-guia-enter-active, .menu-guia-leave-active { transition: opacity .15s ease, transform .15s ease; }
.menu-guia-enter-from, .menu-guia-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
