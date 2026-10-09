<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore, ROLE_DOCENTE, ROLE_EMPRESA } from '../stores/auth'
import { useUiHighlightStore } from '../stores/uiHighlight'
import { useUIState } from '../composables/useUIState.js'
import { useCredits } from '../composables/useCredits.js'
import { useComoFunciona } from '../composables/useComoFunciona.js'
import { useSidePanel } from '../composables/useSidePanel.js'
import { useRoleTheme } from '../composables/useRoleTheme.js'
import { GRUPOS_NAV, ICONOS_NAV } from '../config/navegacion.js'
import { useNotificaciones } from '../composables/useNotificaciones.js'

const authStore = useAuthStore()
const { theme } = useRoleTheme()
const uiHighlight = useUiHighlightStore()
const { tourActivo, showWelcome, welcomeRole, welcomeName } = useUIState()
const { abrirCreditos } = useCredits()
const { abrirComoFunciona } = useComoFunciona()
const { mobileOpen, closeMobilePanel } = useSidePanel()

// Mostrar autoría una vez tras cerrar el modal de bienvenida
let _welShown = false
watch(showWelcome, (val) => {
  if (val) { _welShown = true }
  else if (_welShown) { _welShown = false; setTimeout(abrirCreditos, 450) }
})
const route     = useRoute()
const router    = useRouter()

const isActive = (path) =>
  path === '/' ? route.path === '/' : route.path.startsWith(path)

// Entradas del panel que el rol puede abrir; un grupo sin entradas no pinta su título
const gruposVisibles = computed(() =>
  GRUPOS_NAV
    .map(g => ({ ...g, items: g.items.filter(i => authStore.canAccess(i.routeName)) }))
    .filter(g => g.items.length))

// ─── Tooltip flotante (Teleport a body: el nav recorta con overflow-y:auto) ──
const tooltip = ref({ visible: false, text: '', top: 0, left: 0 })
let tooltipTimer = null

const showTooltip = (event) => {
  const text = event.currentTarget.dataset.tip
  if (!text) return
  const rect = event.currentTarget.getBoundingClientRect()
  clearTimeout(tooltipTimer)
  tooltipTimer = setTimeout(() => {
    tooltip.value = { visible: true, text, top: rect.top + rect.height / 2, left: rect.right + 10 }
  }, 200)
}

const hideTooltip = () => {
  clearTimeout(tooltipTimer)
  tooltip.value.visible = false
}

// El panel solo se monta con sesión iniciada (ver App.vue), así que aquí siempre hay usuario autenticado
const irA = (ruta) => {
  const yaEstoy = route.path === ruta || route.path.startsWith(ruta + '/')
  router.push(yaEstoy ? { path: ruta, query: { _t: Date.now() } } : ruta)
}

// En móvil/tablet el panel es un cajón (drawer): se cierra solo al navegar
watch(() => route.fullPath, closeMobilePanel)

// Badge de notificaciones sin leer: se actualiza al navegar (como mucho cada 30 s,
// ver useNotificaciones). Solo para roles con acceso: empresa recibiría un 403.
const { noLeidas, refrescarNoLeidas } = useNotificaciones()
watch(() => route.fullPath, () => {
  if (authStore.canAccess('notificaciones')) refrescarNoLeidas()
}, { immediate: true })
</script>

<template>
  <!-- Fondo oscuro tras el cajón en móvil/tablet (< lg) mientras el panel está abierto -->
  <Transition name="fade">
    <div
      v-if="!tourActivo && mobileOpen"
      class="fixed inset-0 bg-black/50 z-30 lg:hidden"
      @click="closeMobilePanel"
    />
  </Transition>

  <!-- Panel lateral (también oculto durante el tour) — cajón deslizante en móvil/tablet, fijo en lg+ -->
  <aside
      v-if="!tourActivo"
      class="fixed top-16 left-0 h-[calc(100dvh-6rem)] w-72 max-w-[85vw] z-40 flex flex-col
             bg-azul-noche border-r border-azul-noche-border
             shadow-[6px_0_32px_rgba(0,0,0,0.25)]
             transition-transform duration-300 ease-in-out
             lg:translate-x-0"
      :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- ── Navegación ── -->
      <!-- Cada entrada lleva a una sección (hub con cards) o a una pantalla directa;
           la estructura vive en config/navegacion.js -->
      <nav class="flex-1 min-h-0 px-3 py-3 overflow-y-auto overscroll-contain" @scroll.passive="hideTooltip">

        <!-- ═══ DOCENTE / ADMIN / SUPERADMIN ═══ -->
        <template v-if="!authStore.isEmpresa">
          <template v-for="grupo in gruposVisibles" :key="grupo.titulo ?? 'principal'">
            <p v-if="grupo.titulo" class="nav-grupo">{{ grupo.titulo }}</p>
            <div class="space-y-0.5">
              <button
                v-for="item in grupo.items" :key="item.key"
                @click="item.ruta && irA(item.ruta)"
                :disabled="item.proximamente"
                :data-tip="item.tip"
                class="nav-item w-full text-left"
                @mouseenter="showTooltip"
                @mouseleave="hideTooltip"
                :class="[item.proximamente ? 'nav-item--soon'
                           : item.activa?.(route.path) ? `nav-item--active-${item.color}` : 'nav-item--idle',
                         { 'nav-item--highlighted': item.key === 'retos-proyectos' && uiHighlight.highlightedNavItem === 'generar-proyecto' }]"
              >
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path :d="ICONOS_NAV[item.icon]" />
                </svg>
                <span class="flex-1 truncate">{{ item.label }}</span>
                <!-- Candado: el módulo Empresas pide contraseña especial -->
                <svg v-if="item.candado" class="w-3 h-3 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path :d="ICONOS_NAV.candado" />
                </svg>
                <span v-if="item.proximamente" class="nav-pronto">Pronto</span>
                <span v-if="item.badge === 'notificaciones' && noLeidas" class="nav-badge"
                      :aria-label="`${noLeidas} sin leer`">{{ noLeidas > 99 ? '99+' : noLeidas }}</span>
              </button>
            </div>
          </template>
        </template>

        <!-- ═══ EMPRESA ═══ — de momento conserva sus accesos de solo lectura (pendiente de rediseñar) -->
        <div v-else class="space-y-0.5">
          <button
            v-if="authStore.canAccess('biblioteca')"
            @click="irA('/retos')"
            data-tip="Consulta los retos guardados"
            class="nav-item w-full text-left"
            @mouseenter="showTooltip"
            @mouseleave="hideTooltip"
            :class="isActive('/retos') ? 'nav-item--active-docente' : 'nav-item--idle'"
          >
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path :d="ICONOS_NAV.libro" />
            </svg>
            <span>Biblioteca Retos</span>
          </button>
          <button
            v-if="authStore.canAccess('startup-day')"
            @click="irA('/proyectos')"
            data-tip="Consulta las propuestas y proyectos"
            class="nav-item w-full text-left"
            @mouseenter="showTooltip"
            @mouseleave="hideTooltip"
            :class="isActive('/proyectos') ? 'nav-item--active-docente' : 'nav-item--idle'"
          >
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path :d="ICONOS_NAV.capas" />
            </svg>
            <span>Biblioteca Proyectos</span>
          </button>
        </div>

        <!-- ═══════════════ ADMINISTRACIÓN ═════════════ -->
        <template v-if="authStore.canAccess('base-datos') || authStore.canAccess('papelera') || authStore.canAccess('gestion-usuarios')">
          <p class="nav-grupo">Administración</p>

          <div class="space-y-0.5">

              <!-- Gestión de usuarios -->
              <div v-if="authStore.canAccess('gestion-usuarios')" class="group/tip relative">
                <button
                  @click="irA('/usuarios')"
                  data-tip="Gestiona las cuentas de docentes y empresas"
                  class="nav-item w-full text-left"
                  @mouseenter="showTooltip"
                  @mouseleave="hideTooltip"
                  :class="isActive('/usuarios') ? 'nav-item--active-admin' : 'nav-item--idle'"
                >
                  <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                  </svg>
                  <span>Usuarios</span>
                </button>
                <div class="sp-tooltip">Gestiona las cuentas de docentes y empresas<div class="sp-tooltip-arrow"/></div>
              </div>

              <!-- Base de datos -->
              <div v-if="authStore.canAccess('base-datos')" class="group/tip relative">
                <button
                  @click="irA('/base-datos')"
                  data-tip="Empresas, centros educativos, familias y ciclos del ecosistema DuaLab"
                  class="nav-item w-full text-left"
                  @mouseenter="showTooltip"
                  @mouseleave="hideTooltip"
                  :class="isActive('/base-datos') ? 'nav-item--active-admin' : 'nav-item--idle'"
                >
                  <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/>
                    <path d="M21 12c0 1.657-4.03 3-9 3S3 13.657 3 12"/>
                    <path d="M3 5v14c0 1.657 4.03 3 9 3s9-1.343 9-3V5"/>
                  </svg>
                  <span>Base de datos</span>
                </button>
                <div class="sp-tooltip">Empresas, centros educativos, familias y ciclos del ecosistema DuaLab<div class="sp-tooltip-arrow"/></div>
              </div>

              <!-- Papelera -->
              <div v-if="authStore.canAccess('papelera')" class="group/tip relative">
                <button
                  @click="irA('/papelera')"
                  data-tip="Elementos eliminados — restáuralos o bórralos definitivamente"
                  class="nav-item w-full text-left"
                  @mouseenter="showTooltip"
                  @mouseleave="hideTooltip"
                  :class="isActive('/papelera') ? 'nav-item--active-admin' : 'nav-item--idle'"
                >
                  <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                    <path d="M10 11v6M14 11v6"/>
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                  </svg>
                  <span>Papelera</span>
                </button>
                <div class="sp-tooltip">Elementos eliminados — restáuralos o bórralos definitivamente<div class="sp-tooltip-arrow"/></div>
              </div>

          </div>
        </template>

      </nav>

      <!-- ── Footer: sesión + info + sistema ── -->
      <div class="px-4 py-3 border-t border-white/10 space-y-2 shrink-0">

        <!-- Acerca de — equipo de desarrollo -->
        <button
          @click="abrirCreditos"
          class="w-full flex items-center gap-2 px-3 py-2 rounded-xl
                 bg-white/5 border border-white/10 text-white/40
                 hover:text-white/70 hover:bg-white/8 hover:border-white/20
                 font-bold text-[10px] uppercase tracking-widest
                 transition-all duration-150"
        >
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
          </svg>
          Acerca de
        </button>

        <!-- Botón de información -->
        <button
          @click="abrirComoFunciona"
          class="w-full flex items-center gap-2 px-3 py-2 rounded-xl
                 bg-white/5 border border-white/10 text-white/40
                 hover:text-white/70 hover:bg-white/8 hover:border-white/20
                 font-bold text-[10px] uppercase tracking-widest
                 transition-all duration-150"
        >
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          ¿Qué es DuaLab?
        </button>

        <!-- Indicador sistema activo -->
        <div class="flex items-center gap-2 px-3 py-2 rounded-2xl border"
             :class="[theme.bgSoft, theme.border20]">
          <span class="w-1.5 h-1.5 rounded-full animate-pulse flex-shrink-0" :class="theme.bg" />
          <span class="text-[10px] font-black uppercase tracking-widest" :class="theme.textDark">
            Sistema activo
          </span>
        </div>
      </div>

  </aside>

  <!-- ── Tooltip flotante de los items del nav (fuera del overflow del aside) ── -->
  <Teleport to="body">
    <div
      v-if="tooltip.visible"
      class="sp-floating-tooltip"
      :style="{ top: tooltip.top + 'px', left: tooltip.left + 'px' }"
    >
      {{ tooltip.text }}
    </div>
  </Teleport>

  <!-- ── Modal de bienvenida por rol ── -->
  <Transition name="welcome-overlay">
    <div
      v-if="showWelcome"
      class="fixed inset-0 z-[10000] flex items-center justify-center p-6
             bg-black/25 backdrop-blur-sm"
      @click.self="showWelcome = false"
    >
      <Transition name="welcome-card" appear>
        <div
          v-if="showWelcome"
          class="relative rounded-3xl p-10 max-w-sm w-full text-center
                 bg-white border border-gray-100 shadow-xl"
        >
          <!-- Icono -->
          <div class="mx-auto mb-6 w-16 h-16 rounded-2xl border
                      flex items-center justify-center"
               :class="[theme.bgSoft, theme.border]">
            <svg class="w-8 h-8" :class="theme.text" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </div>

          <!-- Etiqueta -->
          <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" :class="theme.text">
            Sesión iniciada
          </p>

          <!-- Mensaje principal -->
          <h2 class="text-azul-noche text-xl font-bold leading-snug">
            ¡Te damos la bienvenida<br>a DuaLab para
          </h2>
          <p class="text-4xl font-black tracking-tight mt-2 mb-1" :class="theme.text">
            {{ welcomeRole === ROLE_DOCENTE ? 'docentes' : welcomeRole === ROLE_EMPRESA ? 'empresas' : 'admin' }}
          </p>
          <p class="text-[#121212] text-xl font-bold">!</p>

          <!-- Nombre de usuario -->
          <p v-if="welcomeName" class="mt-3 text-gray-400 text-sm">
            {{ welcomeName }}
          </p>

          <!-- Separador -->
          <div class="mt-8 border-t border-gray-100" />

          <!-- Botón cerrar -->
          <button
            @click="showWelcome = false"
            class="mt-6 w-full py-3 rounded-xl text-white
                   font-black text-xs uppercase tracking-widest
                   transition-colors duration-200"
            :class="[theme.bg, theme.bgHover]"
          >
            Continuar
          </button>

          <!-- X esquina -->
          <button
            @click="showWelcome = false"
            class="absolute top-4 right-4 w-8 h-8 rounded-lg bg-gray-100
                   hover:bg-gray-200 flex items-center justify-center
                   text-gray-400 hover:text-gray-600 transition-all"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 1rem;
  font-size: 0.875rem;
  font-weight: 700;
  text-decoration: none;
  transition: background-color 150ms ease, color 150ms ease;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nav-item--idle   { color: rgba(255,255,255,0.68); }
.nav-item--idle:hover {
  background-color: rgba(255,255,255,0.07);
  color: rgba(255,255,255,0.9);
}
/* Activo: color fijo por sección (no por el rol de quien mira), con tonos
   aclarados respecto a la marca para leerse con buen contraste sobre el
   fondo del panel (azul noche de marca, #17283E). */
.nav-item--active-docente {
  background: rgba(107,164,213,0.18);
  color: #6BA4D5;
  box-shadow: inset 3px 0 0 #6BA4D5;
}
.nav-item--active-alumnos {
  background: rgba(255,137,32,0.16);
  color: #FF8920;
  box-shadow: inset 3px 0 0 #FF8920;
}
.nav-item--active-empresas {
  background: rgba(110,193,63,0.18);
  color: #6EC13F;
  box-shadow: inset 3px 0 0 #6EC13F;
}
.nav-item--active-admin {
  background: rgba(63,199,200,0.18);
  color: #3FC7C8;
  box-shadow: inset 3px 0 0 #3FC7C8;
}
.nav-item--highlighted {
  color: #6BA4D5 !important;
  animation: navHighlightPulse 1.1s ease-in-out infinite;
}
@keyframes navHighlightPulse {
  0%, 100% { box-shadow: inset 3px 0 0 #6BA4D5, 0 0 0 0 rgba(107,164,213,0.35); background-color: rgba(107,164,213,0.18); }
  50%      { box-shadow: inset 3px 0 0 #6BA4D5, 0 0 0 6px transparent; background-color: rgba(107,164,213,0.3); }
}
/* Próximamente: visible para que se conozca la sección, pero sin acción */
.nav-item--soon { color: rgba(255,255,255,0.35); cursor: default; }
.nav-pronto {
  margin-left: auto;
  padding: 1px 6px;
  border-radius: 99px;
  background: rgba(255,255,255,0.08);
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
/* Contador de no leídas (Notificaciones) — naranja de marca, como en idea_dashboard.png */
.nav-badge {
  margin-left: auto;
  min-width: 20px;
  padding: 1px 6px;
  border-radius: 99px;
  background: #FF8920;
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  text-align: center;
}
/* Título de grupo del panel (Gestión académica, Organización…) */
.nav-grupo {
  padding: 14px 12px 6px;
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.4);
  user-select: none;
}
.nav-icon {
  width: 17px;
  height: 17px;
  flex-shrink: 0;
  color: inherit;
}

/* Tooltips deshabilitados: el nav usa overflow-y:auto (scroll)
   que crea un scroll container y recorta los children absolutos */
.sp-tooltip       { display: none; }
.sp-tooltip-arrow { display: none; }

/* Tooltip flotante de los items del nav — se renderiza vía Teleport a <body>
   para escapar del overflow-y:auto del nav (ver comentario arriba) */
.sp-floating-tooltip {
  position: fixed;
  transform: translateY(-50%);
  z-index: 9999;
  max-width: 220px;
  padding: 8px 12px;
  border-radius: 10px;
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: rgba(255, 255, 255, 0.9);
  font-size: 12px;
  font-weight: 600;
  line-height: 1.35;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
  pointer-events: none;
}

/* Scrollbar discreta para el nav */
nav::-webkit-scrollbar        { width: 3px; }
nav::-webkit-scrollbar-track  { background: transparent; }
nav::-webkit-scrollbar-thumb  { background: rgba(255,255,255,0.12); border-radius: 99px; }
nav::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }

/* Modal de bienvenida — overlay */
.welcome-overlay-enter-active,
.welcome-overlay-leave-active { transition: opacity 0.25s ease; }
.welcome-overlay-enter-from,
.welcome-overlay-leave-to     { opacity: 0; }

/* Modal de bienvenida — tarjeta */
.welcome-card-enter-active { transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
.welcome-card-leave-active { transition: all 0.2s ease; }
.welcome-card-enter-from   { opacity: 0; transform: scale(0.85) translateY(24px); }
.welcome-card-leave-to     { opacity: 0; transform: scale(0.95) translateY(8px); }
</style>
