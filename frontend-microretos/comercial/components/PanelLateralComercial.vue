<!-- Panel lateral de la app comercial: misma estética que el SidePanel de DuaLab (azul noche,
     .nav-item con indicador lateral en el color del rol, banner de marca al fondo). Fijo en lg+,
     cajón deslizante en móvil y tablet (lo abre el botón de menú de la barra superior). -->
<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import logo from '../../src/assets/logo_colores.png'

const abierto = defineModel('abierto', { type: Boolean, default: false })

const route  = useRoute()
const router = useRouter()

// Cada grupo con el color de su agente, como en el logo: empresas en verde, centros en azul
const COLOR_GRUPO = { Empresas: 'empresas', 'Centros educativos': 'centros' }
const enCentros = computed(() => route.meta.seccion === 'centros')

// Activa = color + fondo + indicador lateral (GUIA_DISENO §8.1), no solo color
const GRUPOS = [
  {
    titulo: 'Empresas',
    items: [
      { key: 'empresas', label: 'Mis empresas', ruta: { name: 'mis-entrevistas' },
        activa: () => ['mis-entrevistas', 'entrevista', 'entrevista-local'].includes(route.name),
        icono: 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1' },
      { key: 'nueva', label: 'Entrevista a empresa', ruta: { name: 'entrevista-nueva' },
        activa: () => route.name === 'entrevista-nueva',
        icono: 'M8 10h8M8 14h5M21 12c0 4.4-4 8-9 8a9.8 9.8 0 01-4-.8L3 20l1.3-3.9A7.6 7.6 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z' },
    ],
  },
  {
    titulo: 'Centros educativos',
    items: [
      { key: 'centros', label: 'Mis centros', ruta: { name: 'mis-centros' },
        activa: () => route.name === 'mis-centros',
        icono: 'M22 10L12 5 2 10l10 5 10-5zM6 12v5c3 2 9 2 12 0v-5' },
      { key: 'centro-nuevo', label: 'Entrevista a centro', ruta: { name: 'centro-nuevo' },
        activa: () => route.name === 'centro-nuevo',
        icono: 'M8 10h8M8 14h5M21 12c0 4.4-4 8-9 8a9.8 9.8 0 01-4-.8L3 20l1.3-3.9A7.6 7.6 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z' },
    ],
  },
  {
    titulo: 'Mi cuenta',
    items: [
      { key: 'usuario', label: 'Mi usuario', ruta: { name: 'mi-usuario' },
        activa: () => route.name === 'mi-usuario',
        icono: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 20c0-4 3.6-7 8-7s8 3 8 7' },
    ],
  },
]

function ir(item) {
  abierto.value = false
  router.push(item.ruta)
}


// Al navegar (también con atrás/adelante) se cierra el cajón en móvil
watch(() => route.fullPath, () => { abierto.value = false })
</script>

<template>
  <!-- Fondo oscuro del cajón en móvil -->
  <Transition name="velo">
    <div v-if="abierto" class="fixed inset-0 top-16 z-30 bg-black/40 backdrop-blur-[1px] lg:hidden" aria-hidden="true" @click="abierto = false" />
  </Transition>

  <aside id="panel-comercial" aria-label="Navegación de la app comercial"
         class="panel-lateral fixed left-0 top-16 z-40 flex h-[calc(100dvh-6.5rem)] w-72 max-w-[85vw] flex-col border-r border-azul-noche-border bg-azul-noche shadow-[6px_0_32px_rgba(0,0,0,0.25)] transition-transform duration-300 ease-in-out lg:translate-x-0"
         :class="abierto ? 'translate-x-0' : '-translate-x-full'">
    <nav class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-3 py-3">
      <template v-for="grupo in GRUPOS" :key="grupo.titulo">
        <p class="nav-grupo">{{ grupo.titulo }}</p>
        <div class="space-y-0.5">
          <button v-for="item in grupo.items" :key="item.key" type="button" class="nav-item w-full text-left"
                  :class="[item.activa() ? 'nav-item--activo' : 'nav-item--idle', `nav-item--${COLOR_GRUPO[grupo.titulo] ?? 'empresas'}`]"
                  :aria-current="item.activa() ? 'page' : undefined" @click="ir(item)">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path :d="item.icono" />
            </svg>
            <span>{{ item.label }}</span>
          </button>
        </div>
      </template>

      <!-- «Instalar app» está en la cabecera (BotonInstalarApp); este hueco mantiene el banner abajo -->
      <div class="mt-auto shrink-0" aria-hidden="true"></div>

      <!-- Banner de marca al fondo del panel, como en el SidePanel de DuaLab: verde en empresas, azul en centros -->
      <div class="shrink-0 pt-4 [@media(max-height:600px)]:hidden" aria-hidden="true">
        <div :class="enCentros ? 'banner-centros' : 'banner-empresas'" class="panel-banner relative h-24 overflow-hidden rounded-2xl border border-white/15 px-4 py-3 shadow-[0_8px_24px_rgba(0,0,0,0.25)] sm:h-28">
          <svg class="absolute inset-x-0 bottom-0 h-12 w-full" viewBox="0 0 240 48" preserveAspectRatio="none">
            <path d="M0 30 C60 14 120 46 240 22 V48 H0Z" fill="#FF8920" opacity="0.85" />
            <path d="M0 38 C70 26 140 50 240 34 V48 H0Z" fill="#3FC7C8" opacity="0.9" />
            <path d="M0 44 C80 38 160 52 240 44 V48 H0Z" fill="#ffffff" opacity="0.35" />
          </svg>
          <img :src="logo" alt="" class="panel-banner-logo absolute right-3 top-1/2 h-16 w-auto -translate-y-1/2 object-contain drop-shadow-[0_4px_10px_rgba(0,0,0,0.35)] sm:right-4 sm:h-20" />
          <p class="relative text-base font-black tracking-wide text-white">DUALAB</p>
          <p class="panel-banner-sub relative mt-1 max-w-[8.5rem] text-xs font-semibold leading-snug text-white/85">{{ enCentros ? 'Centros que conectan su FP con empresas' : 'Empresas que abren sus puertas a la FP' }}</p>
        </div>
      </div>
    </nav>
  </aside>

</template>

<style scoped>
/* Mismas medidas que .nav-item del SidePanel de DuaLab */
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 10px 12px;
  border-radius: 1rem;
  font-size: 0.875rem;
  font-weight: 700;
  transition: background-color 150ms ease, color 150ms ease;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nav-item--idle { color: rgba(255, 255, 255, 0.68); }
.nav-item--idle:hover { background-color: rgba(255, 255, 255, 0.07); color: rgba(255, 255, 255, 0.9); }
.nav-item--activo {
  background: color-mix(in srgb, var(--activo) 18%, transparent);
  color: var(--activo);
  box-shadow: inset 3px 0 0 var(--activo);
}
/* Versión clara de cada color de marca: legible sobre el azul noche del panel */
.nav-item--empresas { --activo: #6EC13F; } /* --color-empresas-light */
.nav-item--centros  { --activo: #6BA4D5; } /* --color-centros-light */
.banner-empresas { background-image: linear-gradient(to bottom right, #3D7A1E, #2F5E1A, #1F3F12); }
.banner-centros  { background-image: linear-gradient(to bottom right, #3072AA, #255C8C, #17406A); }
.nav-grupo {
  padding: 14px 12px 6px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.55);
  user-select: none;
}
.nav-icon { width: 17px; height: 17px; flex-shrink: 0; }

.velo-enter-active, .velo-leave-active { transition: opacity 0.2s ease; }
.velo-enter-from, .velo-leave-to { opacity: 0; }

nav::-webkit-scrollbar { width: 3px; }
nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.12); border-radius: 99px; }
</style>
