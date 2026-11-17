<!-- Cuerpo de un correo en Central («Primer contacto»). Mismo criterio que Gestión web: el HTML ya llega
     limpio de la web (HTMLPurifier) y aquí se vuelve a limpiar (DOMPurify) y se pinta en un iframe aislado,
     sin scripts y con una CSP que solo carga imágenes externas si se piden (para que el remitente no
     sepa que se ha abierto). Sin HTML, el texto plano (escapado por Vue). -->
<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import DOMPurify from 'dompurify'

const props = defineProps({
  html: { type: String, default: null },
  texto: { type: String, default: null },
})

const marco = ref(null)
const alto = ref(200)
const imagenes = ref(false)

const limpio = computed(() => (props.html ? DOMPurify.sanitize(props.html, { FORBID_TAGS: ['style', 'form', 'input'] }) : ''))
const tieneImagenesExternas = computed(() => /<img[^>]+src=["']?https?:/i.test(limpio.value))

const documento = computed(() => `<!doctype html><html><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src data:${imagenes.value ? ' https: http:' : ''}">
<base target="_blank">
<style>
  html,body{margin:0;padding:0}
  body{font-family:system-ui,-apple-system,'Segoe UI',sans-serif;font-size:14px;line-height:1.5;color:#17283E;overflow-wrap:anywhere}
  img{max-width:100%;height:auto}
  table{max-width:100%!important}
  a{color:#0F7C7D}
</style></head><body>${limpio.value}</body></html>`)

// Correos maquetados a ancho fijo (boletines de 600px): se reducen hasta caber; por debajo de la mitad,
// scroll horizontal dentro del marco
function ajustar() {
  const doc = marco.value?.contentDocument
  if (!doc?.body) return
  doc.body.style.zoom = ''
  const ancho = doc.documentElement.clientWidth
  const contenido = doc.documentElement.scrollWidth
  if (contenido > ancho) doc.body.style.zoom = String(Math.max(0.5, ancho / contenido))
  alto.value = Math.max(120, doc.documentElement.scrollHeight)
}
function medir() {
  ajustar()
  marco.value?.contentDocument?.querySelectorAll('img').forEach(img => img.addEventListener('load', ajustar, { once: true }))
}

let observador
onMounted(() => {
  observador = new ResizeObserver(() => ajustar())
  if (marco.value) observador.observe(marco.value)
})
watch(marco, (nuevo, viejo) => {
  if (viejo) observador?.unobserve(viejo)
  if (nuevo) observador?.observe(nuevo)
})
onBeforeUnmount(() => observador?.disconnect())
watch(() => props.html, () => { imagenes.value = false })
</script>

<template>
  <div>
    <div v-if="tieneImagenesExternas && !imagenes" class="mb-3 flex flex-col gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center">
      <p class="min-w-0 flex-1">Las imágenes externas están bloqueadas para que el remitente no sepa que has abierto el correo.</p>
      <button type="button" class="accion-secundaria w-fit" @click="imagenes = true">Mostrar imágenes</button>
    </div>
    <!-- allow-same-origin solo para medir la altura (sin allow-scripts no se ejecuta nada) -->
    <iframe v-if="html" ref="marco" :srcdoc="documento" title="Contenido del correo"
            sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            class="block w-full border-0" :style="{ height: `${alto}px` }" @load="medir" />
    <pre v-else class="whitespace-pre-wrap break-words font-sans text-sm text-azul-noche">{{ texto || '(Sin contenido)' }}</pre>
  </div>
</template>
