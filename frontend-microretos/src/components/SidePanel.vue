<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore, ROLE_DOCENTE, ROLE_EMPRESA } from '../stores/auth'
import { useUiHighlightStore } from '../stores/uiHighlight'
import { useUIState } from '../composables/useUIState.js'
import { useCredits } from '../composables/useCredits.js'
import { useComoFunciona } from '../composables/useComoFunciona.js'
import { useSidePanel } from '../composables/useSidePanel.js'

const authStore = useAuthStore()
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
      class="fixed top-12 left-0 h-[calc(100vh-5rem)] w-72 max-w-[85vw] z-40 flex flex-col
             bg-[#1F2937] border-r border-[#333333]
             shadow-[6px_0_32px_rgba(0,0,0,0.25)]
             transition-transform duration-300 ease-in-out
             lg:translate-x-0"
      :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- ── Navegación ── -->
      <nav class="flex-1 min-h-0 px-3 py-3 space-y-1 overflow-y-auto overscroll-contain" @scroll.passive="hideTooltip">

        <!-- ═══ DOCENTE ═══ -->
        <template v-if="authStore.isDocente || authStore.canAccess('microretos') || authStore.canAccess('dashboard-docente') || authStore.canAccess('startup-day')">

          <div v-if="!authStore.isEmpresa" class="px-3 mb-1.5 flex items-center gap-1.5
                      text-[9px] font-black uppercase tracking-[0.2em] text-white/40 select-none">
            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full
                         bg-white/10 text-white/50 text-[8px] font-black shrink-0">D</span>
            Docente
          </div>

          <div class="rounded-2xl border border-[#00A859]/20 bg-[#00A859]/5 px-2 pt-2 pb-2 space-y-1">

            <!-- Panel docente -->
            <div v-if="authStore.isDocente || authStore.isAdmin || authStore.isSuperAdmin" class="group/tip relative">
              <button
                @click="irA('/panel-docente')"
                data-tip="Panel de inicio para docentes"
                class="nav-item w-full text-left"
                @mouseenter="showTooltip"
                @mouseleave="hideTooltip"
                :class="isActive('/panel-docente') ? 'nav-item--active' : 'nav-item--idle'"
              >
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="3" width="7" height="7" rx="1"/>
                  <rect x="14" y="3" width="7" height="7" rx="1"/>
                  <rect x="14" y="14" width="7" height="7" rx="1"/>
                  <rect x="3" y="14" width="7" height="7" rx="1"/>
                </svg>
                <span>Panel docente</span>
              </button>
            </div>

            <!-- Separador Panel / Retos -->
            <div
              v-if="(authStore.isDocente || authStore.isAdmin || authStore.isSuperAdmin) && (authStore.canAccess('microretos') || authStore.canAccess('biblioteca'))"
              class="border-t border-[#00A859]/15 mx-1 my-1"
            />

            <!-- RETOS -->
            <div v-if="authStore.canAccess('microretos') || authStore.canAccess('biblioteca')">
              <div class="w-full flex items-center gap-2 px-3 mb-1
                          text-[9px] font-black uppercase tracking-[0.2em]
                          text-lime-400/80 select-none">
                <span class="flex-1 text-left flex items-center gap-1.5">
                  <span class="inline-flex items-center justify-center w-4 h-4 rounded-full
                               bg-lime-400/20 text-lime-400 text-[8px] font-black shrink-0">1</span>
                  Retos
                </span>
              </div>

              <div class="space-y-0.5">

                <!-- Generador -->
                <div v-if="authStore.canAccess('microretos')" class="group/tip relative">
                  <button
                    @click="irA('/retos/crear')"
                    data-tip="Genera retos con IA a partir de una empresa y los criterios del ciclo"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="isActive('/retos/crear') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Generador Retos</span>
                  </button>
                </div>

                <!-- Biblioteca Retos -->
                <div v-if="authStore.canAccess('biblioteca')" class="group/tip relative">
                  <button
                    @click="irA('/retos')"
                    data-tip="Consulta todos los retos guardados y comparte el QR con el alumnado"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="isActive('/retos') && !isActive('/retos/crear') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/>
                      <path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 22v-15A2.5 2.5 0 016.5 2z"/>
                      <line x1="9" y1="7" x2="15" y2="7"/>
                      <line x1="9" y1="11" x2="15" y2="11"/>
                    </svg>
                    <span>Biblioteca Retos</span>
                  </button>
                </div>

              </div>
            </div>

            <!-- Separador Retos / Taller de Ideas -->
            <div
              v-if="(authStore.canAccess('microretos') || authStore.canAccess('biblioteca')) && authStore.canAccess('startup-day')"
              class="border-t border-[#00A859]/15 mx-1 my-1"
            />

            <!-- TALLER DE IDEAS -->
            <div v-if="authStore.canAccess('startup-day')">
              <div class="w-full flex items-center gap-2 px-3 mb-1
                          text-[9px] font-black uppercase tracking-[0.2em]
                          text-amber-400/80 select-none">
                <span class="flex-1 text-left flex items-center gap-1.5">
                  <span class="inline-flex items-center justify-center w-4 h-4 rounded-full
                               bg-amber-400/20 text-amber-400 text-[8px] font-black shrink-0">2</span>
                  Taller de Ideas
                </span>
              </div>

              <div class="space-y-0.5">

                <!-- Generar Propuesta-Proyecto -->
                <div v-if="authStore.canAccess('startup-day-crear')" class="group/tip relative">
                  <button
                    @click="irA('/proyectos/crear')"
                    data-tip="Crea una nueva propuesta para el Taller de Ideas"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="[isActive('/proyectos/crear') ? 'nav-item--active' : 'nav-item--idle',
                             { 'nav-item--highlighted': uiHighlight.highlightedNavItem === 'generar-proyecto' }]"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/>
                      <line x1="12" y1="8" x2="12" y2="16"/>
                      <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    <span>Generar Proyecto</span>
                  </button>
                </div>

                <!-- Biblioteca Propuestas-Proyecto -->
                <div v-if="authStore.canAccess('startup-day')" class="group/tip relative">
                  <button
                    @click="irA('/proyectos')"
                    data-tip="Crea y gestiona propuestas y proyectos del Taller de Ideas"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="$route.path.startsWith('/proyectos') && !isActive('/proyectos/crear') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                      <path d="M2 17l10 5 10-5"/>
                      <path d="M2 12l10 5 10-5"/>
                    </svg>
                    <span>Biblioteca Proyectos</span>
                  </button>
                </div>

              </div>
            </div>

            <!-- Separador Taller de Ideas / Encuentro con alumnado -->
            <div v-if="!authStore.isEmpresa" class="border-t border-[#00A859]/15 mx-1 my-1" />

            <!-- ENCUENTRO CON ALUMNADO — no aplica a empresa (solo lectura de retos/proyectos) -->
            <div v-if="!authStore.isEmpresa">
              <div class="w-full flex items-center gap-2 px-3 mb-1
                          text-[9px] font-black uppercase tracking-[0.2em]
                          text-blue-400/80 select-none">
                <span class="flex-1 text-left flex items-center gap-1.5">
                  <span class="inline-flex items-center justify-center w-4 h-4 rounded-full
                               bg-blue-400/20 text-blue-400 text-[8px] font-black shrink-0">3</span>
                  Encuentro con alumnado
                </span>
              </div>

              <div class="space-y-0.5">

                <!-- Generar Encuentros -->
                <div v-if="authStore.canAccess('dashboard-docente')" class="group/tip relative">
                  <button
                    @click="irA('/encuentros/crear')"
                    data-tip="Crea encuentros de trabajo con retos"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="isActive('/encuentros/crear') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                      <rect x="9" y="3" width="6" height="4" rx="1"/>
                      <path d="M9 12l2 2 4-4"/>
                    </svg>
                    <span>Generar encuentros</span>
                  </button>
                </div>

                <!-- Biblioteca de Encuentros -->
                <div v-if="authStore.canAccess('dashboard-docente')" class="group/tip relative">
                  <button
                    @click="irA('/encuentros')"
                    data-tip="Consulta todos los encuentros registrados"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="isActive('/encuentros') && !isActive('/encuentros/crear') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/>
                      <path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 22v-15A2.5 2.5 0 016.5 2z"/>
                      <line x1="9" y1="7" x2="15" y2="7"/>
                      <line x1="9" y1="11" x2="15" y2="11"/>
                    </svg>
                    <span>Biblioteca de encuentros</span>
                  </button>
                </div>

                <!-- Dar acceso alumnado (docentes, admin y superadmin) — elige el encuentro y abre su QR/código -->
                <div v-if="authStore.isDocente || authStore.isAdmin || authStore.isSuperAdmin" class="group/tip relative">
                  <button
                    @click="irA('/pantalla-acceso')"
                    data-tip="Elige un encuentro y proyecta su QR y código para el alumnado"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="isActive('/pantalla-acceso') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="3" y="11" width="18" height="11" rx="2"/>
                      <path d="M7 11V7a5 5 0 0110 0v4"/>
                      <circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"/>
                    </svg>
                    <span>Dar acceso al encuentro</span>
                  </button>
                </div>

                <!-- Separador docente / alumnado -->
                <div v-if="authStore.isDocente || authStore.isAdmin || authStore.isSuperAdmin" class="border-t border-[#00A859]/15 mx-1 my-1" />

                <!-- Título distintivo: separa los dos accesos "Alumnado: ..." (unirse / retomar),
                     ambos puntos de entrada al workspace del equipo, del resto de la sección.
                     Más pequeño y sin badge (a diferencia de "3 · Encuentro con alumnado") para
                     que se lea como subnivel dentro de esa sección, no como un título hermano. -->
                <div class="pl-7 pr-3 mb-1 text-[8px] font-bold uppercase tracking-[0.15em] text-[#00A859]/50 select-none">
                  Workspace alumnado
                </div>

                <!-- Unirse a equipo -->
                <div class="group/tip relative">
                  <button
                    @click="router.push('/unirse')"
                    data-tip="Primera vez: elige tu clase y tu equipo"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="isActive('/unirse') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/>
                      <polyline points="10 17 15 12 10 7"/>
                      <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    <span>Alumnado: unirse a equipo</span>
                  </button>
                </div>

                <!-- Workspace proyecto: reentrada directa con el código del equipo -->
                <div class="group/tip relative">
                  <button
                    @click="router.push('/workspace-proyecto')"
                    data-tip="Mete tu código para ver tu flujo de trabajo"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="isActive('/workspace-proyecto') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Alumnado: retomar workspace</span>
                  </button>
                </div>

                <!-- Separador alumnado / docente -->
                <div v-if="authStore.isDocente || authStore.isAdmin || authStore.isSuperAdmin" class="border-t border-[#00A859]/15 mx-1 my-1" />

                <!-- Mis grupos — seguimiento del avance del alumnado (docentes, admin y superadmin).
                     Ruta /mis-equipos (antes /mis-grupos): "grupo" ya significa la clase/curso del
                     encuentro (Encuentro.grupo, ej. "2ºB"); esta pantalla sigue el progreso de los
                     EQUIPOS de alumnado dentro de cada grupo/encuentro — de ahí el nuevo nombre de
                     ruta, aunque el texto sigue hablando de "grupos" porque así es como el docente
                     navega (por clase), y dentro de cada uno ve sus equipos. -->
                <div v-if="authStore.isDocente || authStore.isAdmin || authStore.isSuperAdmin" class="group/tip relative">
                  <button
                    @click="irA('/mis-equipos')"
                    data-tip="Seguimiento del avance de todos tus grupos y sus equipos"
                    class="nav-item w-full text-left"
                    @mouseenter="showTooltip"
                    @mouseleave="hideTooltip"
                    :class="isActive('/mis-equipos') ? 'nav-item--active' : 'nav-item--idle'"
                  >
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 3v18h18"/>
                      <path d="M7 15l4-6 4 4 5-8"/>
                    </svg>
                    <span>Seguimiento de mis equipos</span>
                  </button>
                </div>

              </div>
            </div>

          </div>

        </template>

        <div class="border-t border-white/10 mx-1 my-2" />

        <!-- ═══════════════ EMPRESAS ════════════════════ -->
        <template v-if="authStore.canAccess('empresas')">
          <div class="my-2 border-t border-white/10" />

          <div class="group/tip relative">
            <div class="w-full flex items-center gap-2 px-3 mb-1
                     text-[9px] font-black uppercase tracking-[0.2em]
                     text-white/40 select-none">
              <span class="flex-1 text-left flex items-center gap-1.5">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full
                             bg-blue-400/20 text-blue-400 text-[8px] font-black shrink-0">E</span>
                Empresas
                <!-- Candado: indica que requiere contraseña especial -->
                <svg class="w-3 h-3 text-white/30 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
              </span>
            </div>
            <div class="sp-tooltip">Módulo Empresas — Contacto directo y envío de enlaces de validación (requiere contraseña especial)<div class="sp-tooltip-arrow"/></div>
          </div>

          <div class="space-y-0.5">
              <div class="group/tip relative">
                <button
                  @click="irA('/empresas')"
                  data-tip="Consulta y contacta con las empresas de la base de datos (requiere contraseña especial)"
                  class="nav-item w-full text-left"
                  @mouseenter="showTooltip"
                  @mouseleave="hideTooltip"
                  :class="isActive('/empresas') ? 'nav-item--active' : 'nav-item--idle'"
                >
                  <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                  </svg>
                  <span>Directorio empresas</span>
                </button>
                <div class="sp-tooltip">Consulta y contacta con las empresas de la base de datos<div class="sp-tooltip-arrow"/></div>
              </div>
          </div>
        </template>

        <!-- ═══════════════ ADMINISTRACIÓN ═════════════ -->
        <template v-if="authStore.canAccess('base-datos') || authStore.canAccess('papelera') || authStore.canAccess('gestion-usuarios')">
          <div class="my-2 border-t border-white/10" />

          <div class="group/tip relative">
            <div class="w-full flex items-center gap-2 px-3 mb-1
                     text-[9px] font-black uppercase tracking-[0.2em]
                     text-white/40 select-none">
              <span class="flex-1 text-left">Administración</span>
            </div>
            <div class="sp-tooltip">Gestión de datos de la plataforma<div class="sp-tooltip-arrow"/></div>
          </div>

          <div class="space-y-0.5">

              <!-- Gestión de usuarios -->
              <div v-if="authStore.canAccess('gestion-usuarios')" class="group/tip relative">
                <button
                  @click="irA('/usuarios')"
                  data-tip="Gestiona las cuentas de docentes y empresas"
                  class="nav-item w-full text-left"
                  @mouseenter="showTooltip"
                  @mouseleave="hideTooltip"
                  :class="isActive('/usuarios') ? 'nav-item--active' : 'nav-item--idle'"
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
                  :class="isActive('/base-datos') ? 'nav-item--active' : 'nav-item--idle'"
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
                  :class="isActive('/papelera') ? 'nav-item--active' : 'nav-item--idle'"
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
        <div class="flex items-center gap-2 px-3 py-2 rounded-2xl
                    bg-[#99CC33]/10 border border-[#99CC33]/20">
          <span class="w-1.5 h-1.5 rounded-full bg-[#99CC33] animate-pulse flex-shrink-0" />
          <span class="text-[10px] font-black uppercase tracking-widest text-[#99CC33]">
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
          <div class="mx-auto mb-6 w-16 h-16 rounded-2xl
                      bg-[#F0FBF4] border border-[#BBE8D0]
                      flex items-center justify-center">
            <svg class="w-8 h-8 text-[#00A859]" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </div>

          <!-- Etiqueta -->
          <p class="text-[#00A859] text-[10px] font-black uppercase tracking-[0.2em] mb-4">
            Sesión iniciada
          </p>

          <!-- Mensaje principal -->
          <h2 class="text-[#121212] text-xl font-bold leading-snug">
            ¡Te damos la bienvenida<br>a DuaLab para
          </h2>
          <p class="text-[#00A859] text-4xl font-black tracking-tight mt-2 mb-1">
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
            class="mt-6 w-full py-3 rounded-xl bg-[#00A859] text-white
                   font-black text-xs uppercase tracking-widest
                   hover:bg-[#009950] transition-colors duration-200"
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
  font-size: 0.8125rem;
  font-weight: 700;
  text-decoration: none;
  transition: background-color 150ms ease, color 150ms ease;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nav-item--idle   { color: rgba(255,255,255,0.5); }
.nav-item--idle:hover {
  background-color: rgba(255,255,255,0.07);
  color: rgba(255,255,255,0.9);
}
.nav-item--active {
  background: linear-gradient(135deg, rgba(0,168,89,0.18) 0%, rgba(153,204,51,0.12) 100%);
  color: #00A859;
  box-shadow: inset 3px 0 0 #00A859;
}
.nav-item--highlighted {
  color: #00A859 !important;
  animation: navHighlightPulse 1.1s ease-in-out infinite;
}
@keyframes navHighlightPulse {
  0%, 100% { box-shadow: inset 3px 0 0 #00A859, 0 0 0 0 rgba(0,168,89,0.35); background-color: rgba(0,168,89,0.12); }
  50%      { box-shadow: inset 3px 0 0 #00A859, 0 0 0 6px rgba(0,168,89,0); background-color: rgba(0,168,89,0.24); }
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
