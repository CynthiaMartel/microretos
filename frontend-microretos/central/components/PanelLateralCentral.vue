<!-- Panel lateral de DuaLab Central: misma estética que el SidePanel de DuaLab y el de la app
     comercial (azul noche, .nav-item con indicador lateral, banner de marca al fondo), en turquesa.
     Fijo en lg+, cajón deslizante en móvil y tablet. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getResumenCaptacion } from '../../src/services/captacionService.js'
import logo from '../../src/assets/logo_colores.png'

const abierto = defineModel('abierto', { type: Boolean, default: false })

const route  = useRoute()
const router = useRouter()

const GRUPOS = [
  {
    titulo: 'Central',
    items: [
      { key: 'inicio', label: 'Inicio', ruta: { name: 'inicio' }, nombres: ['inicio'],
        icono: 'M3 11l9-7 9 7v9a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9z' },
      { key: 'captaciones', label: 'Captaciones', ruta: { name: 'captaciones' }, nombres: ['captaciones'], contador: true,
        icono: 'M9 12l2 2 4-4M7.8 4.4A3.4 3.4 0 009.7 3.6a3.4 3.4 0 014.6 0 3.4 3.4 0 001.9.8 3.4 3.4 0 013.2 3.2c.1.7.3 1.4.8 1.9a3.4 3.4 0 010 4.6 3.4 3.4 0 00-.8 1.9 3.4 3.4 0 01-3.2 3.2 3.4 3.4 0 00-1.9.8 3.4 3.4 0 01-4.6 0 3.4 3.4 0 00-1.9-.8 3.4 3.4 0 01-3.2-3.2 3.4 3.4 0 00-.8-1.9 3.4 3.4 0 010-4.6 3.4 3.4 0 00.8-1.9 3.4 3.4 0 013.2-3.2z' },
    ],
  },
  {
    titulo: 'Datos',
    items: [
      { key: 'base-datos', label: 'Base de datos', ruta: { name: 'base-datos' }, nombres: ['base-datos'],
        icono: 'M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zm0 0v10c0 1.7 3.6 3 8 3s8-1.3 8-3V7M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3' },
      { key: 'usuarios', label: 'Usuarios', ruta: { name: 'usuarios' }, nombres: ['usuarios'],
        icono: 'M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
      { key: 'papelera', label: 'Papelera', ruta: { name: 'papelera' }, nombres: ['papelera'],
        icono: 'M19 7l-.9 12.1A2 2 0 0116.1 21H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16' },
    ],
  },
  {
    titulo: 'Ayuda',
    items: [
      { key: 'soporte', label: 'Soporte', ruta: { name: 'soporte' }, nombres: ['soporte'], proximamente: true,
        icono: 'M18.4 5.6A9 9 0 105.6 18.4 9 9 0 0018.4 5.6zM15 9a3 3 0 00-6 0c0 2 3 2 3 4m0 3h.01' },
      { key: 'usuario', label: 'Mi usuario', ruta: { name: 'mi-usuario' }, nombres: ['mi-usuario'],
        icono: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 20c0-4 3.6-7 8-7s8 3 8 7' },
    ],
  },
]
const activo = item => item.nombres.includes(route.name)

function ir(item) {
  abierto.value = false
  router.push(item.ruta)
}

// Pendientes de validar (empresas + centros + retos): se refresca al navegar, como mucho cada 30 s
const pendientes = ref(0)
let ultima = 0
async function refrescar() {
  if (Date.now() - ultima < 30000) return
  ultima = Date.now()
  try {
    const { data } = await getResumenCaptacion()
    pendientes.value = (data.empresas_pendientes ?? 0) + (data.centros_pendientes ?? 0) + (data.retos_pendientes ?? 0)
  } catch { /* el contador no es crítico */ }
}
onMounted(refrescar)
watch(() => route.fullPath, () => { abierto.value = false; refrescar() })
const textoPendientes = computed(() => (pendientes.value > 99 ? '99+' : String(pendientes.value)))
</script>

<template>
  <Transition name="velo">
    <div v-if="abierto" class="fixed inset-0 top-16 z-30 bg-black/40 backdrop-blur-[1px] lg:hidden" aria-hidden="true" @click="abierto = false" />
  </Transition>

  <aside id="panel-central" aria-label="Navegación de DuaLab Central"
         class="fixed left-0 top-16 z-40 flex h-[calc(100dvh-6.5rem)] w-72 max-w-[85vw] flex-col border-r border-azul-noche-border bg-azul-noche shadow-[6px_0_32px_rgba(0,0,0,0.25)] transition-transform duration-300 ease-in-out lg:translate-x-0"
         :class="abierto ? 'translate-x-0' : '-translate-x-full'">
    <nav class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-3 py-3">
      <template v-for="grupo in GRUPOS" :key="grupo.titulo">
        <p class="nav-grupo">{{ grupo.titulo }}</p>
        <div class="space-y-0.5">
          <button v-for="item in grupo.items" :key="item.key" type="button" class="nav-item w-full text-left"
                  :class="activo(item) ? 'nav-item--activo' : 'nav-item--idle'"
                  :aria-current="activo(item) ? 'page' : undefined" @click="ir(item)">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path :d="item.icono" />
            </svg>
            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
            <span v-if="item.contador && pendientes" class="nav-badge" :aria-label="`${pendientes} pendientes de validar`">{{ textoPendientes }}</span>
            <span v-if="item.proximamente" class="text-[10px] font-bold uppercase tracking-wider text-white/40">Pronto</span>
          </button>
        </div>
      </template>

      <div class="mt-auto shrink-0 pt-4 [@media(max-height:600px)]:hidden" aria-hidden="true">
        <div class="banner-central relative h-24 overflow-hidden rounded-2xl border border-white/15 px-4 py-3 shadow-[0_8px_24px_rgba(0,0,0,0.25)] sm:h-28">
          <svg class="absolute inset-x-0 bottom-0 h-12 w-full" viewBox="0 0 240 48" preserveAspectRatio="none">
            <path d="M0 30 C60 14 120 46 240 22 V48 H0Z" fill="#FF8920" opacity="0.85" />
            <path d="M0 38 C70 26 140 50 240 34 V48 H0Z" fill="#3FC7C8" opacity="0.9" />
            <path d="M0 44 C80 38 160 52 240 44 V48 H0Z" fill="#ffffff" opacity="0.35" />
          </svg>
          <img :src="logo" alt="" class="absolute right-3 top-1/2 h-16 w-auto -translate-y-1/2 object-contain drop-shadow-[0_4px_10px_rgba(0,0,0,0.35)] sm:right-4 sm:h-20" />
          <p class="relative text-base font-black tracking-wide text-white">DUALAB</p>
          <p class="relative mt-1 max-w-[8.5rem] text-xs font-semibold leading-snug text-white/85">Central del superadmin</p>
        </div>
      </div>
    </nav>
  </aside>
</template>

<style scoped>
/* Mismas medidas que .nav-item del SidePanel de DuaLab y de la app comercial */
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
}
.nav-item--idle { color: rgba(255, 255, 255, 0.68); }
.nav-item--idle:hover { background-color: rgba(255, 255, 255, 0.07); color: rgba(255, 255, 255, 0.9); }
/* Turquesa claro del superadmin: legible sobre el azul noche */
.nav-item--activo {
  background: color-mix(in srgb, #3FC7C8 18%, transparent);
  color: #3FC7C8;
  box-shadow: inset 3px 0 0 #3FC7C8;
}
.nav-badge {
  min-width: 1.4rem;
  border-radius: 9999px;
  background: #FF8920;
  padding: 0.05rem 0.4rem;
  text-align: center;
  font-size: 0.7rem;
  font-weight: 800;
  color: #17283E;
}
.banner-central { background-image: linear-gradient(to bottom right, #0F7C7D, #0B5F60, #083F40); }
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
