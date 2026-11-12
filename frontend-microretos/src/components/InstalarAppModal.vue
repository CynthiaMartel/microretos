<!-- Instrucciones para instalar DuaLab como app cuando el navegador no ofrece su diálogo
     (Safari, Firefox, o Chrome antes de considerarla instalable). Ver useInstalarApp.
     En Safari (Mac e iPhone/iPad) los pasos van con una ilustración que marca qué pulsar;
     avanza sola y al tocar un paso se queda en él. -->
<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import ModalTaller from './ModalTaller.vue'
import InstalarAppIlustracion from './InstalarAppIlustracion.vue'

const props = defineProps({
  abierto:    { type: Boolean, required: true },
  plataforma: { type: String, required: true },
  // La app comercial (comercial.dualab.es) usa el mismo modal con su nombre y su pantalla de acceso
  nombre:     { type: String, default: 'DuaLab' },
  rutaInicio: { type: String, default: '/login' },
})
defineEmits(['cerrar'])

const NOTA_SESION = 'La app guarda su propia sesión: la primera vez que la abras tendrás que iniciar sesión.'

// nombre: el de la app que se instala («DuaLab», «DuaLab Comercial»)
const guias = (nombre) => ({
  ios: {
    navegador: 'iPhone o iPad',
    intro: `En iPhone y iPad las apps web se añaden desde Safari en tres toques. No se descarga ningún archivo: el icono de ${nombre} aparece en tu pantalla de inicio.`,
    ilustrada: true,
    pasos: [
      'Pulsa Compartir en la barra de Safari (si no lo ves, pulsa antes ···).',
      'Elige «Añadir a pantalla de inicio».',
      `Pulsa «Añadir». Ya tienes ${nombre} en tu pantalla de inicio.`,
    ],
    nota: NOTA_SESION,
  },
  'safari-mac': {
    navegador: 'Safari en Mac',
    intro: `Safari añade las apps web desde su propio menú, en dos clics. No se descarga ningún archivo: ${nombre} aparece en el Dock y se abre en su propia ventana.`,
    ilustrada: true,
    pasos: [
      'En la barra de menús de arriba, abre «Archivo».',
      'Elige «Añadir al Dock…».',
      `Pulsa «Añadir». Ya tienes ${nombre} en el Dock.`,
    ],
    nota: `${NOTA_SESION} Necesitas macOS Sonoma (14) o posterior.`,
  },
  'firefox-android': {
    navegador: 'Firefox en Android',
    intro: `Firefox instala ${nombre} desde su menú en tres toques. No se descarga ningún archivo: queda como una app más, con su icono.`,
    ilustrada: true,
    pasos: [
      'Pulsa el menú ⋮ de Firefox.',
      'Elige «Instalar» (en algunas versiones, «Añadir a pantalla de inicio»).',
      `Pulsa «Instalar». Ya tienes ${nombre} con tus apps.`,
    ],
  },
  // Chrome, Edge y demás navegadores Chromium cuando aún no ofrecen su diálogo
  // (recién entrado en la página, o se rechazó antes)
  chrome: {
    navegador: 'Chrome o Edge',
    intro: `Tu navegador instala ${nombre} en dos clics. No se descarga ningún archivo: queda en tus aplicaciones y se abre en su propia ventana.`,
    ilustrada: true,
    pasos: [
      `Pulsa el icono de instalar, a la derecha de la barra de direcciones (si no está, búscalo en el menú ⋮ como «Instalar ${nombre}»).`,
      'Confirma con «Instalar».',
      `Listo: ${nombre} se abre en su propia ventana y queda en tus aplicaciones.`,
    ],
    nota: `Si no ves la opción, navega un poco por ${nombre} y vuelve a intentarlo: el navegador tarda unos segundos en ofrecerla.`,
  },
  'chrome-android': {
    navegador: 'Android',
    intro: `Tu navegador instala ${nombre} desde su menú en tres toques. No se descarga ningún archivo: queda como una app más, con su icono.`,
    ilustrada: true,
    pasos: [
      'Pulsa el menú ⋮ del navegador, arriba a la derecha.',
      'Elige «Añadir a pantalla de inicio» (en algunas versiones, «Instalar app»).',
      `Pulsa «Instalar». Ya tienes ${nombre} con tus apps.`,
    ],
    nota: `Si no ves la opción, navega un poco por ${nombre} y vuelve a intentarlo: el navegador tarda unos segundos en ofrecerla.`,
  },
  // Sin pasos: Firefox de escritorio no instala apps web. Se dice claro y se dan alternativas.
  firefox: {
    navegador: 'Firefox en ordenador',
    subtitulo: 'Firefox para ordenador no permite instalar apps',
    noDisponible: true,
    intro: `Lo sentimos: Firefox en ordenador no deja instalar apps web, así que desde aquí no se puede instalar ${nombre}. Pero tienes alternativas:`,
    alternativas: [
      {
        icono: 'M15 3l6 6-3 1-4 4 1 4-2 2-4-4-5 5M9 15l-4-4 2-2 4 1 4-4 1-3',
        titulo: 'Sigue usándola en Firefox',
        texto: 'Funciona exactamente igual. Truco: clic derecho en la pestaña → «Fijar pestaña» y la tendrás siempre a mano.',
      },
      {
        icono: 'M3 5h18v11H3zM8 20h8M12 16v4',
        titulo: 'Instálala con otro navegador del ordenador',
        texto: `Abre ${nombre} en Chrome o Edge (o Safari en Mac) y pulsa «Instalar app» en el panel lateral.`,
        copiarEnlace: true,
      },
      {
        icono: 'M7 2h10a1 1 0 011 1v18a1 1 0 01-1 1H7a1 1 0 01-1-1V3a1 1 0 011-1zM11 18h2',
        titulo: 'Instálala en el móvil',
        texto: 'Firefox para Android sí la instala (menú ⋮ → «Instalar»). En iPhone, ábrela con Safari.',
      },
    ],
  },
})

const guia = computed(() => {
  const todas = guias(props.nombre)
  return todas[props.plataforma] ?? todas.chrome
})

// ── Paso resaltado en la ilustración ─────────────────────────────────────────
const paso = ref(0)
// Se consulta en cada apertura: si el usuario activa "reducir movimiento" en el sistema,
// la siguiente vez que abra el modal ya no avanza solo
const sinMovimiento = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
let temporizador = null
const parar = () => { clearInterval(temporizador); temporizador = null }

watch(() => props.abierto, (abierto) => {
  parar()
  paso.value = 0
  if (abierto && guia.value.ilustrada && !sinMovimiento()) {
    temporizador = setInterval(() => { paso.value = (paso.value + 1) % guia.value.pasos.length }, 2600)
  }
}, { immediate: true })
onUnmounted(parar)

const elegirPaso = (i) => { parar(); paso.value = i }

// ── Copiar el enlace para abrirla en otro navegador (alternativa de Firefox) ──
const URL_APP = `${window.location.origin}${props.rutaInicio}`
const enlaceCopiado = ref(false)
async function copiarEnlace() {
  try {
    await navigator.clipboard.writeText(URL_APP)
    enlaceCopiado.value = true
    setTimeout(() => { enlaceCopiado.value = false }, 2500)
  } catch { /* sin permiso de portapapeles: el enlace queda visible para copiarlo a mano */ }
}
</script>

<template>
  <ModalTaller :abierto="abierto" :titulo="`Instalar ${nombre} como app`"
               :subtitulo="guia.subtitulo ?? `Cómo hacerlo en ${guia.navegador}`"
               ancho="w-[min(30rem,calc(100vw-2rem))]" marca @cerrar="$emit('cerrar')">
    <div class="flex items-center gap-3 sm:gap-4">
      <!-- Sin instalación posible: el icono con una cara triste encima, para que se vea de un vistazo -->
      <span class="relative shrink-0">
        <img src="/pwa-192x192.png" alt="" class="h-12 w-12 sm:h-14 sm:w-14" :class="guia.noDisponible ? 'opacity-40 grayscale' : ''" />
        <span v-if="guia.noDisponible" aria-hidden="true"
              class="absolute -bottom-1 -right-1 inline-flex h-7 w-7 items-center justify-center rounded-full bg-alumnos text-white shadow ring-2 ring-white">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <circle cx="12" cy="12" r="9"/><path d="M8.5 16.5a5 5 0 017 0M9 9.5h.01M15 9.5h.01"/>
          </svg>
        </span>
      </span>
      <p class="min-w-0 text-sm" :class="guia.noDisponible ? 'font-semibold text-azul-noche' : 'text-texto-secundario'">
        {{ guia.intro ?? `Con ${nombre} instalada la abres desde su icono, a pantalla completa y sin las barras del navegador.` }}
      </p>
    </div>

    <InstalarAppIlustracion v-if="guia.ilustrada" :plataforma="plataforma" :paso="paso" class="mt-4" />

    <ul v-if="guia.alternativas" class="mt-4 space-y-2.5">
      <li v-for="alt in guia.alternativas" :key="alt.titulo"
          class="flex gap-3 rounded-xl border border-borde bg-white p-3">
        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte" aria-hidden="true">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="alt.icono"/></svg>
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-azul-noche">{{ alt.titulo }}</p>
          <p class="mt-0.5 break-words text-xs text-texto-secundario">{{ alt.texto }}</p>
          <div v-if="alt.copiarEnlace" class="mt-2 flex flex-wrap items-center gap-2">
            <code class="min-w-0 truncate rounded-lg bg-gray-50 px-2 py-1 text-xs text-azul-noche">{{ URL_APP }}</code>
            <button type="button" @click="copiarEnlace"
                    class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol/30 px-3 text-xs font-semibold text-rol-fuerte transition-colors hover:bg-rol/10">
              {{ enlaceCopiado ? '¡Copiado!' : 'Copiar enlace' }}
            </button>
          </div>
        </div>
      </li>
    </ul>

    <ol v-if="guia.pasos" class="mt-4 space-y-1.5">
      <li v-for="(texto, i) in guia.pasos" :key="i">
        <!-- Con ilustración cada paso es una fila que la lleva a ese momento -->
        <component :is="guia.ilustrada ? 'button' : 'div'" :type="guia.ilustrada ? 'button' : undefined"
                   class="flex w-full items-start gap-3 rounded-xl px-2 py-2 text-left text-sm transition-colors"
                   :class="guia.ilustrada && paso === i ? 'bg-rol/10 text-azul-noche' : 'text-azul-noche'"
                   @click="guia.ilustrada && elegirPaso(i)">
          <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors"
                :class="guia.ilustrada && paso === i ? 'bg-rol text-white' : 'bg-rol/15 text-rol-fuerte'">
            {{ i + 1 }}
          </span>
          <span class="min-w-0 break-words pt-0.5" :class="guia.ilustrada && paso === i ? 'font-semibold' : ''">{{ texto }}</span>
        </component>
      </li>
    </ol>

    <p v-if="guia.nota" class="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-xs text-texto-secundario">
      {{ guia.nota }}
    </p>

    <template #pie>
      <div class="flex justify-center">
        <button type="button" @click="$emit('cerrar')"
                class="inline-flex h-11 items-center justify-center rounded-full bg-rol-fuerte px-8 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rol-fuerte/90">
          Entendido
        </button>
      </div>
    </template>
  </ModalTaller>
</template>
