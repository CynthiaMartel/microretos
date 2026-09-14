<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useComoFunciona } from '../composables/useComoFunciona.js'
import {
  LightBulbIcon,
  XMarkIcon,
  SparklesIcon,
  BookOpenIcon,
  RocketLaunchIcon,
  RectangleStackIcon,
  CalendarIcon,
  QrCodeIcon,
  UserGroupIcon,
  ArrowUturnRightIcon,
  ChartBarIcon,
  AcademicCapIcon,
  BuildingOfficeIcon,
  ShieldCheckIcon,
  ArrowLongDownIcon,
} from '@heroicons/vue/24/outline'

const { comoFuncionaAbierto: abierto, abrirComoFunciona: abrir, cerrarComoFunciona: cerrar } = useComoFunciona()
const onOverlay = (e) => { if (e.target === e.currentTarget) cerrar() }
const onKeydown = (e) => { if (e.key === 'Escape') cerrar() }

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))

// Colores por rol — solo Docente y Alumnado/Equipo actúan dentro del flujo.
// Empresa y Admin se explican aparte, en la sección de roles.
const ROL = {
  docente:  { bg: 'bg-[#00A859]/10', border: 'border-[#00A859]/25', text: 'text-[#00A859]' },
  alumnado: { bg: 'bg-blue-50',      border: 'border-blue-200',     text: 'text-blue-600' },
}

const roles = [
  { label: 'Docente',            icon: AcademicCapIcon,    ...ROL.docente,
    desc: 'Crea retos y proyectos, los comparte con el alumnado y hace seguimiento del progreso.' },
  { label: 'Alumnado / Equipo',  icon: UserGroupIcon,       ...ROL.alumnado,
    desc: 'Accede sin necesidad de cuenta y resuelve retos, avanza en el proyecto por fases.' },
  { label: 'Empresa',            icon: BuildingOfficeIcon,  bg: 'bg-amber-50',  border: 'border-amber-200',  text: 'text-amber-600',
    desc: 'Valida la entrega del equipo desde un enlace público, sin necesidad de cuenta. El alumnado resuelve sus necesidades a partir de un reto.' },
  { label: 'Admin',               icon: ShieldCheckIcon,     bg: 'bg-violet-50', border: 'border-violet-200', text: 'text-violet-600',
    desc: 'Gestiona centros, ciclos formativos y usuarios de forma transversal.' },
]

// Nombres y descripciones tal y como aparecen literalmente en la navegación del SidePanel.
const pasosRetos = [
  { icon: SparklesIcon,  rol: 'docente',  titulo: 'Generador Retos',
    desc: 'Genera retos con IA a partir de una empresa y los criterios del ciclo.' },
  { icon: BookOpenIcon,  rol: 'docente',  titulo: 'Biblioteca Retos',
    desc: 'Consulta todos los retos guardados y comparte el QR con el alumnado.' },
]

const pasosProyecto = [
  { icon: RocketLaunchIcon,     rol: 'docente', titulo: 'Generar Proyecto',
    desc: 'Crea una nueva propuesta para el Taller de Ideas.' },
  { icon: RectangleStackIcon,   rol: 'docente', titulo: 'Biblioteca Proyectos',
    desc: 'Crea y gestiona propuestas y proyectos del Taller de Ideas.' },
]

const pasosEncuentro = [
  { icon: CalendarIcon,         rol: 'docente',  titulo: 'Generar encuentros',
    desc: 'Crea encuentros de trabajo con retos.' },
  { icon: BookOpenIcon,         rol: 'docente',  titulo: 'Biblioteca de encuentros',
    desc: 'Consulta todos los encuentros registrados.' },
  { icon: QrCodeIcon,           rol: 'docente',  titulo: 'Dar acceso al encuentro',
    desc: 'Elige un encuentro y proyecta su QR y código para el alumnado.' },
  { icon: UserGroupIcon,        rol: 'alumnado', titulo: 'Alumnado: unirse a equipo',
    desc: 'Primera vez: elige tu clase y tu equipo.' },
  { icon: ArrowUturnRightIcon,  rol: 'alumnado', titulo: 'Alumnado: retomar workspace',
    desc: 'Mete tu código para ver tu flujo de trabajo.' },
  { icon: ChartBarIcon,         rol: 'docente',  titulo: 'Seguimiento de mis equipos',
    desc: 'Seguimiento del avance de todos tus grupos y sus equipos.' },
]
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
      class="w-14 h-14 rounded-full bg-[#00A859] hover:bg-[#009950] text-white shadow-lg
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
                <div class="w-12 h-12 rounded-2xl bg-[#00A859]/10 border border-[#00A859]/25
                            flex items-center justify-center shrink-0">
                  <LightBulbIcon class="w-6 h-6 text-[#00A859]" />
                </div>
                <div>
                  <h2 class="text-xl sm:text-2xl font-black tracking-tight text-[#121212]">
                    ¿Cómo funciona Dua<span class="text-[#00A859]">Lab</span>?
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
            <div class="flex-1 overflow-y-auto px-6 sm:px-10 py-8">

              <!-- Intro -->
              <p class="text-gray-500 text-sm leading-relaxed mb-8 max-w-3xl">
                DuaLab es la plataforma que conecta centros educativos de FP con empresas para
                generar retos de aprendizaje real, alineados con los módulos y resultados de
                aprendizaje del ciclo.
              </p>

              <!-- Sección de roles -->
              <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-3">
                Quién participa
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-10">
                <div
                  v-for="r in roles" :key="r.label"
                  class="flex items-start gap-3 p-3 rounded-2xl border"
                  :class="[r.bg, r.border]"
                >
                  <div class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                    :class="[r.bg, r.border, 'border']">
                    <component :is="r.icon" class="w-4 h-4" :class="r.text" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-black" :class="r.text">{{ r.label }}</p>
                    <p class="text-[11px] text-gray-500 mt-0.5 leading-snug">{{ r.desc }}</p>
                  </div>
                </div>
              </div>

              <!-- Tres bloques de flujo -->
              <!-- lg:contents en cada bloque "desenvuelve" sus 3 partes (título/definición/pasos) para que
                   se coloquen como filas explícitas de esta misma grid — así las 3 columnas quedan a la
                   misma altura fila a fila, aunque el texto de la definición tenga longitudes distintas. -->
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                <!-- Bloque: Retos -->
                <div class="lg:contents">
                  <div class="lg:col-start-1 lg:row-start-1">
                    <p class="text-[11px] font-black uppercase tracking-widest text-lime-700 mb-1">
                      Retos
                    </p>
                    <h3 class="text-lg font-black text-[#121212] mb-2">Generador y biblioteca</h3>
                  </div>

                  <div class="lg:col-start-1 lg:row-start-2 flex items-start gap-3 rounded-2xl bg-lime-50 border-2 border-lime-300 p-4 mb-5 lg:mb-0 shadow-sm">
                    <div class="shrink-0 w-7 h-7 rounded-lg bg-lime-200 flex items-center justify-center mt-0.5">
                      <span class="text-xs font-black text-lime-800">?</span>
                    </div>
                    <p class="text-sm text-gray-900 leading-relaxed">
                      Un <span class="font-black text-lime-700">RETO</span> es la necesidad real de una
                      empresa, transformada (con ayuda de la IA) en una
                      <span class="font-black text-lime-700">pregunta</span> que el alumnado deberá responder.
                    </p>
                  </div>

                  <div class="lg:col-start-1 lg:row-start-3">
                    <template v-for="(paso, i) in pasosRetos" :key="'r-'+i">
                      <div class="flex items-start gap-4 p-4 rounded-2xl bg-white border border-gray-100">
                        <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center border"
                          :class="[ROL[paso.rol].bg, ROL[paso.rol].border]">
                          <component :is="paso.icon" class="w-5 h-5" :class="ROL[paso.rol].text" />
                        </div>
                        <div class="min-w-0">
                          <p class="font-bold text-sm text-[#1F2937]">{{ paso.titulo }}</p>
                          <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ paso.desc }}</p>
                        </div>
                      </div>
                      <div v-if="i < pasosRetos.length - 1" class="flex justify-center py-1">
                        <ArrowLongDownIcon class="w-5 h-5 text-gray-300" />
                      </div>
                    </template>
                  </div>
                </div>

                <!-- Bloque: Taller de Ideas -->
                <div class="lg:contents">
                  <div class="lg:col-start-2 lg:row-start-1">
                    <p class="text-[11px] font-black uppercase tracking-widest text-amber-600 mb-1">
                      Taller de Ideas
                    </p>
                    <h3 class="text-lg font-black text-[#121212] mb-2">Propuestas y proyectos</h3>
                  </div>

                  <div class="lg:col-start-2 lg:row-start-2 flex items-start gap-3 rounded-2xl bg-amber-50 border-2 border-amber-300 p-4 mb-5 lg:mb-0 shadow-sm">
                    <div class="shrink-0 w-7 h-7 rounded-lg bg-amber-200 flex items-center justify-center mt-0.5">
                      <span class="text-xs font-black text-amber-800">?</span>
                    </div>
                    <p class="text-sm text-gray-900 leading-relaxed">
                      Una <span class="font-black text-amber-700">PROPUESTA</span> es la concreción
                      curricular del reto que hace el docente. Al validarla la empresa y el propio docente,
                      pasa a ser <span class="font-black text-amber-700">PROYECTO</span>: la respuesta que
                      elaborará el alumnado, con sus fases, entregables y evaluación.
                    </p>
                  </div>

                  <div class="lg:col-start-2 lg:row-start-3">
                    <template v-for="(paso, i) in pasosProyecto" :key="'tp-'+i">
                      <div class="flex items-start gap-4 p-4 rounded-2xl bg-white border border-gray-100">
                        <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center border"
                          :class="[ROL[paso.rol].bg, ROL[paso.rol].border]">
                          <component :is="paso.icon" class="w-5 h-5" :class="ROL[paso.rol].text" />
                        </div>
                        <div class="min-w-0">
                          <p class="font-bold text-sm text-[#1F2937]">{{ paso.titulo }}</p>
                          <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ paso.desc }}</p>
                        </div>
                      </div>
                      <div v-if="i < pasosProyecto.length - 1" class="flex justify-center py-1">
                        <ArrowLongDownIcon class="w-5 h-5 text-gray-300" />
                      </div>
                    </template>
                  </div>
                </div>

                <!-- Bloque: Encuentro con alumnado -->
                <div class="lg:contents">
                  <div class="lg:col-start-3 lg:row-start-1">
                    <p class="text-[11px] font-black uppercase tracking-widest text-blue-600 mb-1">
                      Encuentro con alumnado
                    </p>
                    <h3 class="text-lg font-black text-[#121212] mb-2">Acceso y seguimiento</h3>
                  </div>

                  <div class="lg:col-start-3 lg:row-start-2 flex items-start gap-3 rounded-2xl bg-blue-50 border-2 border-blue-300 p-4 mb-5 lg:mb-0 shadow-sm">
                    <div class="shrink-0 w-7 h-7 rounded-lg bg-blue-200 flex items-center justify-center mt-0.5">
                      <span class="text-xs font-black text-blue-800">?</span>
                    </div>
                    <p class="text-sm text-gray-900 leading-relaxed">
                      Un <span class="font-black text-blue-700">ENCUENTRO</span> es el
                      <span class="font-black text-blue-700">cuándo</span>: la fecha y los equipos con los
                      que ese proyecto se trabaja en el aula. A partir de ahí, cada equipo avanza el
                      proyecto por fases en su <span class="font-black text-blue-700">workspace</span>.
                    </p>
                  </div>

                  <div class="lg:col-start-3 lg:row-start-3">
                    <template v-for="(paso, i) in pasosEncuentro" :key="'e-'+i">
                      <div class="flex items-start gap-4 p-4 rounded-2xl bg-white border border-gray-100">
                        <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center border"
                          :class="[ROL[paso.rol].bg, ROL[paso.rol].border]">
                          <component :is="paso.icon" class="w-5 h-5" :class="ROL[paso.rol].text" />
                        </div>
                        <div class="min-w-0">
                          <p class="font-bold text-sm text-[#1F2937]">{{ paso.titulo }}</p>
                          <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ paso.desc }}</p>
                        </div>
                      </div>
                      <div v-if="i < pasosEncuentro.length - 1" class="flex justify-center py-1">
                        <ArrowLongDownIcon class="w-5 h-5 text-gray-300" />
                      </div>
                    </template>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pie -->
            <div class="shrink-0 px-6 sm:px-10 py-4 bg-white border-t border-gray-100">
              <button
                @click="cerrar"
                class="w-full sm:w-auto sm:ml-auto sm:block py-3 px-8 rounded-xl bg-[#00A859] text-white
                       font-black text-xs uppercase tracking-widest hover:bg-[#009950] transition-all"
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
