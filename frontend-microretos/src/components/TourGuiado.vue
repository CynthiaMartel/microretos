<!-- Tour guiado: oscurece la pantalla salvo el elemento que se explica (foco recortado) y muestra
     al lado un bocadillo con flecha que lo señala. En móvil (< sm) el bocadillo se ancla abajo, o
     arriba si el elemento está en la mitad de abajo, como un toast. Estado y pasos: useTour.js;
     contenido de cada pantalla: src/tours/. Se monta una vez en App.vue. -->
<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount, useId } from 'vue'
import { useRoute } from 'vue-router'
import { useTour, elementoPaso } from '../composables/useTour.js'
import { useSidePanel } from '../composables/useSidePanel.js'

const { activo, titulo, pasos, indice, paso, cerrar } = useTour()
const { mobileOpen } = useSidePanel()
const route = useRoute()
const idTitulo = useId()

const MARGEN = 16          // separación mínima del bocadillo con los bordes de la pantalla
const HUECO = 14           // entre el elemento y el bocadillo (lo ocupa la flecha)
const RELLENO = 6          // aire del foco alrededor del elemento

const ancho = ref(typeof window !== 'undefined' ? window.innerWidth : 1280)
const alto = ref(typeof window !== 'undefined' ? window.innerHeight : 800)
const esMovil = computed(() => ancho.value < 640)

const rect = ref(null)              // del elemento señalado (null: paso centrado)
const bocadillo = ref(null)
const altoBocadillo = ref(200)
const btnSiguiente = ref(null)

const esUltimo = computed(() => indice.value === pasos.value.length - 1)

// ── Medir y colocar ──────────────────────────────────────────────────────────
let elActual = null
let raf = 0
function medir() {
  cancelAnimationFrame(raf)
  raf = requestAnimationFrame(() => {
    ancho.value = window.innerWidth
    alto.value = window.innerHeight
    if (bocadillo.value) altoBocadillo.value = bocadillo.value.offsetHeight
    if (!elActual || !elActual.isConnected) { rect.value = null; return }
    const r = elActual.getBoundingClientRect()
    rect.value = { top: r.top, left: r.left, width: r.width, height: r.height }
  })
}

const espera = (ms) => new Promise(res => setTimeout(res, ms))

async function mostrarPaso() {
  const p = paso.value
  if (!p) return
  // Menú lateral: en móvil/tablet es un cajón; se abre para sus pasos y se cierra para el resto
  const cajon = ancho.value < 1024
  if (cajon) {
    const abrir = !!p.panel
    if (mobileOpen.value !== abrir) { mobileOpen.value = abrir; await espera(320) }
  }
  elActual = elementoPaso(p)
  if (elActual) {
    const r = elActual.getBoundingClientRect()
    const fuera = r.top < 72 || r.bottom > window.innerHeight - 24
    if (fuera) {
      elActual.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' })
      await espera(380)
    }
  }
  await nextTick()
  medir()
  // Segunda medida con el alto real del bocadillo ya pintado
  setTimeout(medir, 60)
  btnSiguiente.value?.focus({ preventScroll: true })
}

watch([activo, indice], ([a]) => { if (a) mostrarPaso() })

function siguiente() {
  if (esUltimo.value) return terminar()
  indice.value++
}
function anterior() { if (indice.value > 0) indice.value-- }

function terminar() {
  cerrar()
  elActual = null
  rect.value = null
  if (ancho.value < 1024) mobileOpen.value = false
}

// Navegar a otra pantalla corta el recorrido (sus elementos ya no están)
watch(() => route.fullPath, () => { if (activo.value) terminar() })

function alTeclear(e) {
  if (!activo.value) return
  if (e.key === 'Escape') { e.preventDefault(); terminar() }
  else if (e.key === 'ArrowRight') { e.preventDefault(); siguiente() }
  else if (e.key === 'ArrowLeft') { e.preventDefault(); anterior() }
}

onMounted(() => {
  window.addEventListener('resize', medir)
  window.addEventListener('scroll', medir, true)
  document.addEventListener('keydown', alTeclear)
})
onBeforeUnmount(() => {
  window.removeEventListener('resize', medir)
  window.removeEventListener('scroll', medir, true)
  document.removeEventListener('keydown', alTeclear)
  cancelAnimationFrame(raf)
})

// ── Geometría ────────────────────────────────────────────────────────────────
const foco = computed(() => rect.value && {
  top: rect.value.top - RELLENO,
  left: rect.value.left - RELLENO,
  width: rect.value.width + RELLENO * 2,
  height: rect.value.height + RELLENO * 2,
})

const anchoBocadillo = computed(() => Math.min(352, ancho.value - MARGEN * 2))

// Dónde va el bocadillo: { estilo, lado, flecha } — lado null = sin flecha (centrado o anclado)
const colocacion = computed(() => {
  const f = foco.value
  const w = anchoBocadillo.value
  const h = altoBocadillo.value
  const vw = ancho.value, vh = alto.value
  const minTop = 72 // bajo la TopBar

  if (!f) {
    return { lado: null, estilo: { top: '50%', left: '50%', width: `${w}px`, transform: 'translate(-50%, -50%)' } }
  }
  if (esMovil.value) {
    const arriba = f.top + f.height / 2 > vh / 2
    return { lado: null, estilo: arriba
      ? { top: `${minTop}px`, left: `${MARGEN}px`, right: `${MARGEN}px` }
      : { bottom: `${MARGEN}px`, left: `${MARGEN}px`, right: `${MARGEN}px` } }
  }

  const cabe = {
    abajo: f.top + f.height + HUECO + h <= vh - MARGEN,
    arriba: f.top - HUECO - h >= minTop,
    derecha: f.left + f.width + HUECO + w <= vw - MARGEN,
    izquierda: f.left - HUECO - w >= MARGEN,
  }
  const orden = [paso.value?.lado, 'abajo', 'arriba', 'derecha', 'izquierda'].filter(Boolean)
  const lado = orden.find(l => cabe[l])
  const limitar = (v, min, max) => Math.max(min, Math.min(v, max))

  if (lado === 'abajo' || lado === 'arriba') {
    const left = limitar(f.left + f.width / 2 - w / 2, MARGEN, vw - w - MARGEN)
    const top = lado === 'abajo' ? f.top + f.height + HUECO : f.top - HUECO - h
    const flecha = limitar(f.left + f.width / 2 - left, 24, w - 24)
    return { lado, flecha, estilo: { top: `${top}px`, left: `${left}px`, width: `${w}px` } }
  }
  if (lado === 'derecha' || lado === 'izquierda') {
    const top = limitar(f.top + f.height / 2 - h / 2, minTop, vh - h - MARGEN)
    const left = lado === 'derecha' ? f.left + f.width + HUECO : f.left - HUECO - w
    const flecha = limitar(f.top + f.height / 2 - top, 24, h - 24)
    return { lado, flecha, estilo: { top: `${top}px`, left: `${left}px`, width: `${w}px` } }
  }
  // No cabe junto al elemento (muy grande): anclado abajo, sobre el foco
  return { lado: null, estilo: { bottom: `${MARGEN}px`, right: `${MARGEN}px`, width: `${w}px` } }
})

const estiloFlecha = computed(() => {
  const { lado, flecha } = colocacion.value
  if (!lado) return null
  const vertical = lado === 'abajo' || lado === 'arriba'
  const pos = { abajo: { top: '-7px' }, arriba: { bottom: '-7px' }, derecha: { left: '-7px' }, izquierda: { right: '-7px' } }[lado]
  return { ...pos, [vertical ? 'left' : 'top']: `${flecha}px`,
           transform: `${vertical ? 'translateX(-50%)' : 'translateY(-50%)'} rotate(45deg)` }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="tour">
      <div v-if="activo && paso" class="fixed inset-0 z-[10000]">
        <!-- Capa que bloquea los clics: durante el recorrido no se navega por error -->
        <div class="absolute inset-0" :class="!foco && 'bg-azul-noche/55'" />

        <!-- Foco: el oscurecido es su sombra, así el elemento se ve tal cual dentro del hueco -->
        <div v-if="foco" aria-hidden="true"
             class="tour-foco pointer-events-none absolute rounded-2xl ring-2 ring-white/90"
             :style="{ top: `${foco.top}px`, left: `${foco.left}px`, width: `${foco.width}px`, height: `${foco.height}px` }" />

        <!-- Bocadillo -->
        <div ref="bocadillo" role="dialog" aria-modal="true" :aria-labelledby="idTitulo"
             class="absolute rounded-2xl bg-white p-4 text-azul-noche shadow-2xl ring-1 ring-black/5 sm:p-5"
             :class="foco ? esMovil && 'max-h-[45vh] overflow-y-auto' : 'max-h-[80dvh] overflow-y-auto'"
             :style="colocacion.estilo">
          <span v-if="estiloFlecha" aria-hidden="true" class="absolute h-3.5 w-3.5 bg-white" :style="estiloFlecha" />

          <div class="relative flex items-start gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-[11px] font-bold uppercase tracking-[0.15em] text-rol-fuerte">
                <span v-if="titulo">{{ titulo }} · </span>{{ indice + 1 }} de {{ pasos.length }}
              </p>
              <h2 :id="idTitulo" class="mt-1 break-words font-heading text-base font-bold leading-snug sm:text-lg">{{ paso.titulo }}</h2>
            </div>
            <button type="button" @click="terminar" aria-label="Cerrar la guía" title="Cerrar la guía"
                    class="-mr-1 -mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
          </div>

          <p class="relative mt-2 whitespace-pre-line text-sm leading-relaxed text-texto-secundario" aria-live="polite">{{ paso.texto }}</p>

          <!-- Progreso -->
          <div class="relative mt-4 flex h-1 gap-1" aria-hidden="true">
            <span v-for="(_, i) in pasos" :key="i" class="flex-1 rounded-full transition-colors"
                  :class="i <= indice ? 'bg-rol' : 'bg-gray-200'" />
          </div>

          <div class="fila-pasos relative mt-4">
            <button v-if="indice > 0" type="button" class="btn-paso-atras h-10! px-4!" @click="anterior">Anterior</button>
            <button ref="btnSiguiente" type="button" class="btn-paso h-10! px-4!" @click="siguiente">
              {{ esUltimo ? 'Terminar' : 'Siguiente' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.tour-foco {
  box-shadow: 0 0 0 9999px rgba(23, 40, 62, 0.55);
  transition: top 250ms ease, left 250ms ease, width 250ms ease, height 250ms ease;
}
.tour-enter-active, .tour-leave-active { transition: opacity 180ms ease; }
.tour-enter-from, .tour-leave-to { opacity: 0; }
@media (prefers-reduced-motion: reduce) {
  .tour-foco { transition: none; }
}
</style>
