<!-- Firma a mano (dedo, lápiz o ratón) con Pointer Events, sin dependencias. Expone
     png() (data URL de la firma sobre fondo blanco) y limpiar(); emite 'cambio' con
     true/false según haya trazo. Al cambiar el tamaño (girar la tablet) se borra: un
     trazo reescalado ya no sería la firma que se hizo. -->
<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const emit = defineEmits(['cambio'])

const lienzo = ref(null)
let ctx = null
let dibujando = false
let hayTrazo = false
let anchoPrevio = 0

function preparar() {
  const c = lienzo.value
  if (!c) return
  const ratio = Math.min(window.devicePixelRatio || 1, 2)
  const { width, height } = c.getBoundingClientRect()
  c.width = Math.round(width * ratio)
  c.height = Math.round(height * ratio)
  ctx = c.getContext('2d')
  ctx.scale(ratio, ratio)
  ctx.lineWidth = 2.5
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = '#17283E'
  anchoPrevio = width
  marcar(false)
}

function marcar(valor) {
  if (hayTrazo !== valor) emit('cambio', valor)
  hayTrazo = valor
}

const punto = (e) => {
  const r = lienzo.value.getBoundingClientRect()
  return { x: e.clientX - r.left, y: e.clientY - r.top }
}

function empezar(e) {
  e.preventDefault()
  lienzo.value.setPointerCapture(e.pointerId)
  dibujando = true
  const { x, y } = punto(e)
  ctx.beginPath()
  ctx.moveTo(x, y)
  ctx.lineTo(x + 0.1, y + 0.1) // un toque suelto también deja marca
  ctx.stroke()
}
function mover(e) {
  if (!dibujando) return
  e.preventDefault()
  const { x, y } = punto(e)
  ctx.lineTo(x, y)
  ctx.stroke()
  marcar(true)
}
function terminar() {
  dibujando = false
}

function limpiar() {
  const c = lienzo.value
  ctx.save()
  ctx.setTransform(1, 0, 0, 1, 0, 0)
  ctx.clearRect(0, 0, c.width, c.height)
  ctx.restore()
  marcar(false)
}

// PNG con fondo blanco (el lienzo es transparente): se ve igual en cualquier visor
function png() {
  const c = lienzo.value
  const copia = document.createElement('canvas')
  copia.width = c.width
  copia.height = c.height
  const cc = copia.getContext('2d')
  cc.fillStyle = '#ffffff'
  cc.fillRect(0, 0, copia.width, copia.height)
  cc.drawImage(c, 0, 0)
  return copia.toDataURL('image/png')
}

const alRedimensionar = () => {
  const ancho = lienzo.value?.getBoundingClientRect().width
  if (ancho && Math.abs(ancho - anchoPrevio) > 1) preparar()
}

onMounted(() => {
  preparar()
  window.addEventListener('resize', alRedimensionar)
})
onUnmounted(() => window.removeEventListener('resize', alRedimensionar))

defineExpose({ png, limpiar })
</script>

<template>
  <div>
    <canvas ref="lienzo"
            class="block h-48 w-full touch-none rounded-xl border-2 border-dashed border-borde-campo bg-white sm:h-56"
            aria-label="Zona de firma: firma con el dedo o con un lápiz"
            @pointerdown="empezar" @pointermove="mover" @pointerup="terminar" @pointercancel="terminar" @pointerleave="terminar" />
    <div class="mt-2 flex items-center justify-between gap-3">
      <p class="text-xs text-gray-500">Firma dentro del recuadro.</p>
      <button type="button" class="accion-secundaria h-9 px-4" @click="limpiar">Borrar firma</button>
    </div>
  </div>
</template>
