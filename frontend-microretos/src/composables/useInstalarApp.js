import { ref, computed } from 'vue'

// Instalar DuaLab como app (PWA). Cada navegador lo hace a su manera:
//  - Chrome / Edge (escritorio y Android): lanzan `beforeinstallprompt`; se guarda y el botón
//    "Instalar app" abre su diálogo nativo.
//  - Safari iOS/iPadOS: sin diálogo; Compartir → "Añadir a pantalla de inicio".
//  - Safari macOS 17+: Archivo → "Añadir al Dock".
//  - Firefox Android: menú ⋮ → "Instalar". Firefox de escritorio no instala apps web.
// El listener se registra al importar el módulo (desde main.js): el evento puede llegar antes
// de que se monte el panel lateral.

const eventoInstalacion = ref(null)
const modoApp = () =>
  window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true
const instalada = ref(modoApp())

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault()
  eventoInstalacion.value = e
})
window.addEventListener('appinstalled', () => {
  eventoInstalacion.value = null
  instalada.value = true
})

function detectarPlataforma() {
  const ua = navigator.userAgent
  // iPadOS se presenta como Mac: se distingue por la pantalla táctil
  const ios = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)
  if (ios) return 'ios'
  const android = /Android/.test(ua)
  if (/Firefox\//.test(ua)) return android ? 'firefox-android' : 'firefox'
  if (/Safari\//.test(ua) && !/Chrome\/|Chromium\/|Edg\//.test(ua)) return 'safari-mac'
  return 'otro'
}
const plataforma = detectarPlataforma()

export function useInstalarApp() {
  // Chrome/Edge sin evento: ya instalada o el navegador no la considera instalable todavía;
  // el modal explica cómo hacerlo desde su menú.
  const puedeInstalar = computed(() => !instalada.value)

  // true si se abrió el diálogo nativo; false → mostrar las instrucciones del navegador
  async function instalar() {
    const evento = eventoInstalacion.value
    if (!evento) return false
    eventoInstalacion.value = null // el evento solo se puede usar una vez
    await evento.prompt()
    const { outcome } = await evento.userChoice
    if (outcome === 'accepted') instalada.value = true
    return true
  }

  return { puedeInstalar, instalar, plataforma }
}
