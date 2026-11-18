<!-- Nexo DuaLab: punto de partida del equipo. Una tarjeta por herramienta con su botón de
     entrada; cada herramienta pide su propio inicio de sesión (aquí no hay sesión ni datos). -->
<script setup>
import BotonInstalarApp from '../src/components/BotonInstalarApp.vue'
import {
  AcademicCapIcon, ArrowRightIcon, BriefcaseIcon, GlobeAltIcon, ShieldCheckIcon,
} from '@heroicons/vue/24/outline'
// Mismo icono que el favicon (pwa/iconos-herramientas.mjs --barra nexo)
import logo from './icono-barra.png'
import AvisoNuevaVersion from '../src/components/AvisoNuevaVersion.vue'
import { HERRAMIENTAS } from './herramientas.js'

const ICONOS = { app: AcademicCapIcon, web: GlobeAltIcon, comercial: BriefcaseIcon, central: ShieldCheckIcon }

// Clases literales (Tailwind no ve clases montadas con variables). Mismo color que el icono
// de cada herramienta (pwa/iconos-herramientas.mjs); el botón va en el tono oscuro del color
// para que el texto blanco tenga contraste AA
const COLORES = {
  centros: { icono: 'bg-centros/10 text-centros', boton: 'bg-centros hover:bg-centros/90 shadow-centros/25', franja: 'bg-centros' },
  administraciones: { icono: 'bg-administraciones/10 text-administraciones-dark', boton: 'bg-administraciones-dark hover:bg-administraciones-dark/90 shadow-administraciones-dark/25', franja: 'bg-administraciones' },
  empresas: { icono: 'bg-empresas/10 text-empresas-dark', boton: 'bg-empresas-dark hover:bg-empresas-dark/90 shadow-empresas-dark/25', franja: 'bg-empresas' },
  'azul-noche': { icono: 'bg-azul-noche/10 text-azul-noche', boton: 'bg-azul-noche hover:bg-azul-noche/90 shadow-azul-noche/25', franja: 'bg-azul-noche' },
}

const enLocal = import.meta.env.DEV
</script>

<template>
  <div class="min-h-dvh bg-azul-noche px-4 py-8 sm:px-6 sm:py-12">
    <div class="mx-auto max-w-4xl">
      <!-- Instalar el Nexo como app (en la cabecera, como en el resto de herramientas) -->
      <div class="mb-2 flex justify-end">
        <BotonInstalarApp nombre="Nexo DuaLab" ruta-inicio="/" />
      </div>
      <header class="flex flex-col items-center text-center text-white">
        <img :src="logo" alt="" class="h-14 w-14 rounded-[22%] shadow-lg ring-1 ring-white/25 sm:h-16 sm:w-16" />
        <h1 class="mt-4 font-heading text-3xl font-bold sm:text-4xl">
          Nexo <span class="text-administraciones">DuaLab</span>
        </h1>
        <p class="mt-2 max-w-xl text-sm text-white/75 sm:text-base">Elige la herramienta a la que quieres entrar. Cada una tiene su propio acceso.</p>
        <span aria-hidden="true" class="mt-4 flex h-1 w-40 gap-1.5">
          <span class="flex-1 rounded-full bg-centros" /><span class="flex-1 rounded-full bg-empresas" />
          <span class="flex-1 rounded-full bg-alumnos" /><span class="flex-1 rounded-full bg-administraciones" />
        </span>
        <span v-if="enLocal" class="mt-4 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">Entorno local</span>
      </header>

      <ul class="mt-8 grid grid-cols-1 gap-4 sm:mt-10 sm:grid-cols-2 sm:gap-5">
        <li v-for="h in HERRAMIENTAS" :key="h.clave"
            class="relative flex flex-col overflow-hidden rounded-2xl bg-white p-5 shadow-lg sm:p-6">
          <span class="absolute inset-x-0 top-0 h-1.5" :class="COLORES[h.color].franja" aria-hidden="true" />
          <div class="flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full" :class="COLORES[h.color].icono" aria-hidden="true">
              <component :is="ICONOS[h.clave]" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
              <h2 class="flex flex-wrap items-center gap-2 font-heading text-lg font-bold text-azul-noche">
                {{ h.nombre }}
                <span v-if="!h.url" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-texto-secundario">Próximamente</span>
              </h2>
              <p class="text-xs font-semibold uppercase tracking-wide text-texto-secundario">{{ h.para }}</p>
            </div>
          </div>
          <p class="mt-3 flex-1 text-sm text-azul-noche/80">{{ h.texto }}</p>
          <div class="mt-5">
            <a v-if="h.url" :href="h.url"
               class="inline-flex h-11 items-center gap-2 rounded-full px-5 text-sm font-bold text-white shadow-md transition-colors"
               :class="COLORES[h.color].boton">
              Entrar<span class="sr-only"> en {{ h.nombre }}</span> <ArrowRightIcon class="h-4 w-4" aria-hidden="true" />
            </a>
            <span v-else class="inline-flex h-11 items-center rounded-full border-2 border-gray-200 px-5 text-sm font-bold text-gray-400" aria-disabled="true">
              Aún no disponible
            </span>
          </div>
        </li>
      </ul>

      <footer class="mt-10 text-center text-sm text-white/70">
        <p>¿No tienes cuenta en alguna? Pídela a quien administra esa herramienta.</p>
        <a href="https://dualab.es" class="mt-3 inline-block font-semibold text-white/80 underline-offset-4 hover:text-white hover:underline">Ir a dualab.es</a>
      </footer>
    </div>
    <!-- Una pestaña del Nexo abierta tras un deploy seguiría con los enlaces de antes -->
    <AvisoNuevaVersion texto="Recarga para ver los enlaces y las herramientas al día." />
  </div>
</template>
