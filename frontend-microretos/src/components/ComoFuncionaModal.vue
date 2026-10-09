<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useComoFunciona } from '../composables/useComoFunciona.js'
import { useCredits } from '../composables/useCredits.js'
import { GRUPOS_NAV, SECCIONES, ICONOS_NAV } from '../config/navegacion.js'
import ConceptoClave from './ConceptoClave.vue'
import FlujoDiagrama from './FlujoDiagrama.vue'
import {
  LightBulbIcon,
  XMarkIcon,
  UserGroupIcon,
  AcademicCapIcon,
  BuildingOfficeIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const authStore = useAuthStore()
const { abrirCreditos } = useCredits()
const { comoFuncionaAbierto: abierto, abrirComoFunciona: abrir, cerrarComoFunciona: cerrar } = useComoFunciona()
const onOverlay = (e) => { if (e.target === e.currentTarget) cerrar() }
const onKeydown = (e) => { if (e.key === 'Escape') cerrar() }

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))

const roles = [
  { label: 'Docente',          icon: AcademicCapIcon,    bg: 'bg-centros/10',  border: 'border-centros/25',  text: 'text-centros',
    desc: 'Crea retos y proyectos, organiza encuentros con su alumnado y sigue y evalúa a sus grupos.' },
  { label: 'Alumnado / Grupo', icon: UserGroupIcon,      bg: 'bg-alumnos/10',  border: 'border-alumnos/25',  text: 'text-alumnos-dark',
    desc: 'Entra sin cuenta con el QR o el código del encuentro y avanza el proyecto por fases en el workspace de su grupo.' },
  { label: 'Empresa',          icon: BuildingOfficeIcon, bg: 'bg-empresas/10', border: 'border-empresas/25', text: 'text-empresas',
    desc: 'Aporta la necesidad real de la que nace el reto y valida la propuesta del docente desde su enlace.' },
  { label: 'Admin',            icon: ShieldCheckIcon,    bg: 'bg-violet-50',   border: 'border-violet-200',  text: 'text-violet-600',
    desc: 'Gestiona centros, ciclos formativos y usuarios de forma transversal.' },
]

// Conceptos clave: mismos textos que las secciones hub (config/navegacion.js), para que
// el modal y el panel no se desincronicen
const fasesRetosProyectos = SECCIONES['retos-proyectos'].flujo.fases
const conceptos = [
  ...fasesRetosProyectos.map(f => f.concepto).filter(Boolean),
  ...(SECCIONES.encuentros.conceptos ?? []),
]

// Recorrido completo: el flujo de la sección Recursos
const recorrido = SECCIONES.recursos.flujo

// Mapa del panel lateral: las mismas entradas que ve el rol en el SidePanel, con las
// herramientas que agrupa cada sección hub
const TILE = { docente: 'bg-centros', alumnos: 'bg-alumnos', empresas: 'bg-empresas' }
const visible = (c) => !c.routeName || authStore.canAccess(c.routeName)
const herramientasDe = (key) => {
  const s = SECCIONES[key]
  if (!s) return []
  return [...(s.pasoAtras?.cards ?? []), ...s.cards]
    .filter(c => visible(c) && c.accion !== 'comoFunciona')
}
const gruposPanel = computed(() =>
  GRUPOS_NAV
    .map(g => ({
      ...g,
      items: g.items
        .filter(i => !i.proximamente && authStore.canAccess(i.routeName))
        .map(i => ({ ...i, tile: TILE[i.color] ?? 'bg-centros', herramientas: herramientasDe(i.key) })),
    }))
    .filter(g => g.items.length))

const ir = (destino) => {
  if (destino.proximamente) return
  cerrar()
  if (destino.accion === 'creditos') return abrirCreditos()
  router.push(destino.ruta)
}
</script>

<template>
  <!-- ── Botón flotante ─────────────────────────────────────────────────── -->
  <div class="fixed bottom-16 right-5 z-[70] group">
    <span
      class="pointer-events-none absolute right-full top-1/2 -translate-y-1/2 mr-3 whitespace-nowrap
             rounded-lg bg-[#1a2332] px-3 py-1.5 text-xs font-bold text-white shadow-lg
             opacity-0 translate-x-1 transition-all duration-150
             group-hover:opacity-100 group-hover:translate-x-0"
    >
      ¿Cómo funciona DuaLab?
    </span>
    <button
      @click="abrir"
      aria-label="¿Cómo funciona DuaLab?"
      class="w-14 h-14 rounded-full bg-centros hover:bg-primary-700 text-white shadow-lg
             hover:shadow-xl hover:scale-105 flex items-center justify-center
             transition-all duration-200"
    >
      <LightBulbIcon class="w-6 h-6" />
    </button>
  </div>

  <!-- ── Modal ──────────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <Transition name="cfm-overlay">
      <div
        v-if="abierto"
        class="fixed inset-0 z-[9995] flex items-center justify-center p-0 sm:p-6 bg-black/70 backdrop-blur-sm"
        @click="onOverlay"
      >
        <Transition name="cfm-card">
          <div
            v-if="abierto"
            class="relative bg-[#F8FAFC] w-full h-full sm:w-[95vw] sm:h-[92vh] sm:max-w-6xl
                   rounded-none sm:rounded-[2rem] shadow-2xl flex flex-col overflow-hidden"
          >
            <!-- Cabecera -->
            <div class="shrink-0 flex items-start justify-between gap-4 px-6 sm:px-10 py-6
                        bg-white border-b border-gray-100">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-centros/10 border border-centros/25
                            flex items-center justify-center shrink-0">
                  <LightBulbIcon class="w-6 h-6 text-centros" />
                </div>
                <div>
                  <h2 class="text-xl sm:text-2xl font-black tracking-tight text-azul-noche">
                    ¿Cómo funciona Dua<span class="text-centros">Lab</span>?
                  </h2>
                  <p class="text-gray-400 text-xs sm:text-sm font-medium mt-0.5">
                    Plataforma de retos para FP Dual
                  </p>
                </div>
              </div>
              <button
                @click="cerrar"
                aria-label="Cerrar"
                class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center
                       text-gray-400 hover:text-gray-600 transition-all shrink-0"
              >
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <!-- Cuerpo (scrollable) -->
            <div class="flex-1 overflow-y-auto px-6 sm:px-10 py-8 space-y-10">

              <!-- Intro -->
              <p class="text-gray-500 text-sm leading-relaxed max-w-3xl">
                DuaLab es la plataforma que conecta centros educativos de FP con empresas para
                generar retos de aprendizaje real, alineados con los módulos y resultados de
                aprendizaje del ciclo.
              </p>

              <!-- Quién participa -->
              <section>
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-3">
                  Quién participa
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                  <div
                    v-for="r in roles" :key="r.label"
                    class="flex items-start gap-3 p-3 rounded-2xl border"
                    :class="[r.bg, r.border]"
                  >
                    <div class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center border"
                      :class="[r.bg, r.border]">
                      <component :is="r.icon" class="w-4 h-4" :class="r.text" />
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-black" :class="r.text">{{ r.label }}</p>
                      <p class="text-[11px] text-gray-500 mt-0.5 leading-snug">{{ r.desc }}</p>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Conceptos clave: reto, propuesta/proyecto, encuentro -->
              <section>
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-3">
                  Las piezas de DuaLab
                </p>
                <div class="grid gap-3 lg:grid-cols-3">
                  <ConceptoClave v-for="(c, i) in conceptos" :key="i" :color="c.color" :segmentos="c.segmentos" />
                </div>
              </section>

              <!-- Recorrido completo (mismo flujo que la sección Recursos) -->
              <section>
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-1">
                  {{ recorrido.titulo }}
                </p>
                <p class="text-sm text-gray-500 mb-5">Del reto de la empresa al diagnóstico final de cada grupo.</p>
                <FlujoDiagrama :fases="recorrido.fases" class="text-azul-noche" />
              </section>

              <!-- Dónde está cada cosa: mismas entradas que el panel lateral -->
              <section>
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-1">
                  Dónde está cada cosa
                </p>
                <p class="text-sm text-gray-500 mb-5">
                  Cada entrada del panel lateral abre una sección con sus herramientas. Pulsa cualquiera para ir directamente.
                </p>

                <div class="space-y-6">
                  <div v-for="(g, gi) in gruposPanel" :key="g.titulo ?? gi">
                    <p v-if="g.titulo" class="text-xs font-bold text-azul-noche mb-2">{{ g.titulo }}</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                      <article
                        v-for="item in g.items" :key="item.key"
                        class="flex flex-col rounded-2xl bg-white p-4 ring-1 ring-gray-200/70"
                      >
                        <button type="button" class="group flex items-start gap-3 text-left" @click="ir(item)">
                          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white shadow-sm transition group-hover:scale-105"
                                :class="item.tile">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                              <path :d="ICONOS_NAV[item.icon]" />
                            </svg>
                          </span>
                          <span class="min-w-0">
                            <span class="block text-sm font-bold text-azul-noche group-hover:text-centros">{{ item.label }}</span>
                            <span class="mt-0.5 block text-xs leading-snug text-gray-500">{{ item.tip }}</span>
                          </span>
                        </button>

                        <!-- Herramientas que agrupa la sección -->
                        <div v-if="item.herramientas.length" class="mt-3 flex flex-wrap gap-1.5 border-t border-gray-100 pt-3">
                          <button
                            v-for="h in item.herramientas" :key="h.titulo" type="button"
                            :disabled="h.proximamente"
                            :title="h.desc"
                            class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition"
                            :class="h.proximamente
                              ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                              : 'bg-centros/5 text-centros ring-1 ring-centros/20 hover:bg-centros/10'"
                            @click="ir(h)"
                          >
                            {{ h.titulo }}<span v-if="h.proximamente"> · próximamente</span>
                          </button>
                        </div>
                      </article>
                    </div>
                  </div>
                </div>
              </section>
            </div>

            <!-- Pie -->
            <div class="shrink-0 px-6 sm:px-10 py-4 bg-white border-t border-gray-100">
              <button
                @click="cerrar"
                class="w-full sm:w-auto sm:ml-auto sm:block py-3 px-8 rounded-xl bg-centros text-white
                       font-black text-xs uppercase tracking-widest hover:bg-primary-700 transition-all"
              >
                Entendido
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.cfm-overlay-enter-active, .cfm-overlay-leave-active { transition: opacity 0.2s ease; }
.cfm-overlay-enter-from,  .cfm-overlay-leave-to      { opacity: 0; }

.cfm-card-enter-active { transition: opacity 0.25s ease, transform 0.3s cubic-bezier(.34,1.56,.64,1); }
.cfm-card-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.cfm-card-enter-from,
.cfm-card-leave-to     { opacity: 0; transform: scale(0.96) translateY(12px); }
</style>
