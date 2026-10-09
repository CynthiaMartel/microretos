<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useComoFunciona } from '../composables/useComoFunciona.js'
import { useCredits } from '../composables/useCredits.js'
import { SECCIONES, ICONOS_NAV } from '../config/navegacion.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import ConceptoClave from '../components/ConceptoClave.vue'
import FlujoDiagrama from '../components/FlujoDiagrama.vue'
import HalosMarca from '../components/HalosMarca.vue'
import ResumenEncuentrosPanel from '../components/ResumenEncuentrosPanel.vue'
import EliminarEncuentroModal from '../components/EliminarEncuentroModal.vue'
import api from '../api.js'

// Entrada de cada área del panel lateral: cards con las herramientas de la sección.
// El contenido vive en config/navegacion.js; esta vista solo lo pinta, con el mismo
// lenguaje visual que el panel docente (InicioDocente.vue).
const route  = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { abrirComoFunciona } = useComoFunciona()
const { abrirCreditos } = useCredits()

const ACCIONES = { comoFunciona: abrirComoFunciona, creditos: abrirCreditos }

const seccion = computed(() => SECCIONES[route.params.seccion] ?? null)

// Cards sin routeName (rutas públicas o modales) se muestran siempre
const visibles = (lista = []) => lista.filter(c => !c.routeName || authStore.canAccess(c.routeName))
const cards     = computed(() => visibles(seccion.value?.cards))
const pasoAtras = computed(() => {
  const b = seccion.value?.pasoAtras
  return b && { ...b, cards: visibles(b.cards) }
})

const flujo = computed(() => seccion.value?.flujo ?? null)

// ─── Historial de encuentros (solo secciones con `historialEncuentros`) ───────
// La vista se reutiliza al cambiar de sección (mismo componente, otro :seccion), así que
// se carga al entrar en una sección que lo pide, no en onMounted.
const encuentros         = ref([])
const cargandoEncuentros = ref(false)

async function cargarEncuentros() {
  cargandoEncuentros.value = true
  try {
    const res = await api.get('/encuentros')
    encuentros.value = res.data
  } catch (e) {
    console.error('Error cargando encuentros:', e)
    encuentros.value = []
  } finally {
    cargandoEncuentros.value = false
  }
}

watch(() => seccion.value?.historialEncuentros, (activo) => { if (activo) cargarEncuentros() }, { immediate: true })

// El detalle (código del alumnado, RA/CE, grupos…) vive en la biblioteca: ?id= abre su modal
function verEncuentro(s) {
  router.push({ name: 'encuentros-registrados', query: { id: s.id } })
}

const encuentroAEliminar = ref(null)
function onEncuentroEliminado({ id }) {
  encuentros.value = encuentros.value.filter(s => s.id !== id)
  encuentroAEliminar.value = null
}

const abrir = (card) => {
  if (card.proximamente) return
  if (card.accion) return ACCIONES[card.accion]?.()
  router.push(card.ruta)
}
</script>

<template>
  <div class="min-h-screen bg-[#F3F6FA] font-sans text-azul-noche pt-16">
    <!-- pb-20: el footer de créditos (AppCredit) es fixed y en móvil ocupa dos líneas;
         sin este margen tapa el final del flujo y el scroll rebota sin llegar a verlo -->
    <div v-if="seccion" class="mx-auto max-w-[1100px] space-y-4 px-4 pb-20 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion :titulo="seccion.titulo" :destacado="seccion.destacado" :color="seccion.color" :subtitulo="seccion.subtitulo" />

      <!-- Qué es cada concepto de la sección (reto, proyecto, encuentro…), con los textos de ComoFuncionaModal.vue -->
      <div v-if="seccion.conceptos?.length" class="grid gap-3" :class="seccion.conceptos.length > 1 && 'md:grid-cols-2'">
        <ConceptoClave v-for="(c, i) in seccion.conceptos" :key="i" :color="c.color" :segmentos="c.segmentos" />
      </div>

      <!-- Herramientas de la sección. Las marcadas `principal` llevan el botón relleno -->
      <!-- auto-rows-fr: todas las cards miden lo mismo aunque cambie el largo de su descripción -->
      <div class="grid auto-rows-fr gap-4 sm:grid-cols-2">
        <article v-for="card in cards" :key="card.titulo"
                 class="card flex min-w-0 flex-col p-5 transition"
                 :class="card.proximamente ? 'opacity-60' : 'hover:-translate-y-0.5 hover:shadow-md cursor-pointer'"
                 @click="abrir(card)">
          <div class="flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm" :class="card.tile">
              <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path :d="ICONOS_NAV[card.icon]" />
              </svg>
            </span>
            <div class="min-w-0 flex-1">
              <h2 class="card-title flex flex-wrap items-center gap-2">
                {{ card.titulo }}
                <span v-if="card.proximamente" class="rounded-full bg-gray-100 px-2 py-0.5 font-sans text-[10px] font-bold uppercase tracking-wider text-gray-500">Próximamente</span>
              </h2>
              <p class="mt-1 text-sm leading-snug text-gray-500">{{ card.desc }}</p>
            </div>
          </div>
          <div v-if="card.cta" class="mt-4 flex flex-1 items-end">
            <button type="button" @click.stop="abrir(card)"
                    class="flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 text-sm font-semibold"
                    :class="card.principal
                      ? 'bg-centros text-white shadow-md shadow-centros/25 hover:bg-centros/90'
                      : 'border border-centros/30 bg-white text-centros shadow-sm hover:bg-centros/5'">
              <svg v-if="card.principal" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
              {{ card.cta }}
            </button>
          </div>
        </article>
      </div>

      <!-- Historial de encuentros: el mismo panel que en /encuentros/crear, a ancho completo -->
      <section v-if="seccion.historialEncuentros" class="card overflow-hidden">
        <ResumenEncuentrosPanel :encuentros="encuentros" :cargando="cargandoEncuentros"
                                @ver="verEncuentro" @eliminar="encuentroAEliminar = $event" />
      </section>

      <!-- Bloque secundario: herramientas previas al flujo principal (Retos antes de Proyectos) -->
      <section v-if="pasoAtras?.cards.length" class="card p-4 sm:p-5">
        <div class="grid gap-4 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)] md:items-center">
          <div>
            <h2 class="card-title">{{ pasoAtras.titulo }}</h2>
            <p class="mt-1 text-sm leading-relaxed text-gray-600">{{ pasoAtras.texto }}</p>
          </div>
          <!-- Dos caminos, separados por "o": la primera opción es la vía directa -->
          <div class="grid items-stretch gap-2 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
            <template v-for="(card, i) in pasoAtras.cards" :key="card.titulo">
              <span v-if="i > 0" aria-hidden="true"
                    class="flex items-center justify-center text-xs font-semibold uppercase tracking-widest text-gray-400">o</span>
              <button type="button" @click="abrir(card)"
                      class="group flex min-w-0 flex-col rounded-xl p-4 text-left ring-1 transition hover:-translate-y-0.5 hover:shadow-md"
                      :class="i === 0 ? 'bg-centros/5 ring-centros/25' : 'bg-white ring-gray-200/70'">
                <span class="flex items-center gap-3">
                  <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white shadow-sm transition group-hover:scale-105" :class="card.tile">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                      <path :d="ICONOS_NAV[card.icon]" />
                    </svg>
                  </span>
                  <span class="min-w-0">
                    <span v-if="card.etiqueta" class="block text-[10px] font-bold uppercase tracking-widest"
                          :class="i === 0 ? 'text-centros' : 'text-gray-400'">{{ card.etiqueta }}</span>
                    <span class="block text-sm font-semibold">{{ card.titulo }}</span>
                  </span>
                </span>
                <span class="mt-2 block text-xs leading-relaxed text-gray-500">{{ card.desc }}</span>
              </button>
            </template>
          </div>
        </div>
      </section>

      <!-- Flujo explicativo de la sección (p. ej. Retos y proyectos): ver FlujoDiagrama.vue -->
      <section v-if="flujo" class="pt-4">
        <!-- Franja de título con los halos de los banners del panel docente a la derecha,
             fundidos hacia el texto (mismo lenguaje que la bienvenida de InicioDocente) -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#E4EEF9] via-[#EDF3FA] to-transparent">
          <HalosMarca class="absolute inset-y-0 right-0 hidden h-full w-[45%] sm:block [mask-image:linear-gradient(to_right,transparent,black_45%)] [-webkit-mask-image:linear-gradient(to_right,transparent,black_45%)]" />
          <div class="relative z-10 px-5 py-4 sm:max-w-[60%]">
            <h2 class="card-title">{{ flujo.titulo }}</h2>
            <p class="mt-1 text-sm text-gray-600">
              Más detalle en <button type="button" class="font-semibold text-centros hover:underline" @click="abrirComoFunciona">¿Cómo funciona DuaLab?</button>
            </p>
          </div>
        </div>

        <FlujoDiagrama class="mt-5" :fases="flujo.fases" />
      </section>

    </div>

    <EliminarEncuentroModal :visible="!!encuentroAEliminar" :encuentro="encuentroAEliminar"
                            @encuentro-eliminado="onEncuentroEliminado" @cerrar="encuentroAEliminar = null" />
  </div>
</template>

<style scoped>
@reference "../style.css";

.card       { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
.card-title { @apply font-heading text-lg font-bold text-azul-noche; }
</style>
