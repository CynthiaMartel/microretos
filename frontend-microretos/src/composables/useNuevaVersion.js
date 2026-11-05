import { ref } from 'vue'

// Tras un deploy, una pestaña abierta sigue ejecutando el código anterior (la SPA no
// vuelve a pedir index.html al navegar). Se compara la versión incrustada en el bundle
// con /version.json del servidor y, si cambia, App.vue muestra el aviso "Recargar".
const VERSION_ACTUAL = __APP_VERSION__
const INTERVALO_MS = 10 * 60_000        // comprobación periódica con la pestaña abierta
const MIN_ENTRE_COMPROBACIONES_MS = 60_000 // navegar mucho no dispara una petición por ruta
const POSPONER_MS = 30 * 60_000          // "✕" en el aviso: vuelve a salir pasado este rato

const hayNuevaVersion = ref(false)
let ultimaComprobacion = 0
let pospuestoHasta = 0
let iniciado = false

async function comprobarVersion() {
  if (import.meta.env.DEV || hayNuevaVersion.value) return
  if (Date.now() < pospuestoHasta) return
  if (Date.now() - ultimaComprobacion < MIN_ENTRE_COMPROBACIONES_MS) return
  ultimaComprobacion = Date.now()
  try {
    const res = await fetch(`/version.json?t=${Date.now()}`, { cache: 'no-store' })
    if (!res.ok) return
    const { version } = await res.json()
    if (version && version !== VERSION_ACTUAL) hayNuevaVersion.value = true
  } catch { /* sin conexión: se reintenta en la siguiente comprobación */ }
}

function posponer() {
  hayNuevaVersion.value = false
  pospuestoHasta = Date.now() + POSPONER_MS
}

// Equivalente a Cmd+Shift+R (location.reload(true) ya no fuerza nada en los navegadores):
// vacía Cache Storage y Service Workers si los hubiera, pide index.html saltándose la caché
// HTTP para que la recarga reciba el nuevo, y recarga. Los assets llevan hash en el nombre,
// así que el index.html nuevo ya trae los JS/CSS nuevos.
async function recargarLimpio() {
  try {
    if ('caches' in window) {
      const claves = await caches.keys()
      await Promise.all(claves.map((k) => caches.delete(k)))
    }
    if ('serviceWorker' in navigator) {
      const registros = await navigator.serviceWorker.getRegistrations()
      await Promise.all(registros.map((r) => r.unregister()))
    }
    await fetch(window.location.pathname, { cache: 'reload' })
  } catch { /* la recarga sigue aunque falle la limpieza */ }
  window.location.reload()
}

// Se llama una vez desde App.vue: comprueba al cambiar de ruta (incluida la entrada al
// dashboard tras el login), al volver a la pestaña y cada INTERVALO_MS.
function iniciarComprobacion(router) {
  if (iniciado) return
  iniciado = true
  comprobarVersion()
  router.afterEach(() => comprobarVersion())
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') comprobarVersion()
  })
  setInterval(comprobarVersion, INTERVALO_MS)
}

export function useNuevaVersion() {
  return { hayNuevaVersion, iniciarComprobacion, recargarLimpio, posponer }
}
