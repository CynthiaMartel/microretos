// Sesión para abrir la app sin conexión. La sesión real es la cookie de Sanctum, pero sin red
// no se puede preguntar al servidor (/perfil) y el comercial se quedaría fuera en mitad de una
// entrevista. Por eso, tras cada comprobación correcta con el servidor se recuerda quién es
// (id, nombre y rol, cifrado en el almacén) y, sin red, se le deja entrar como ese usuario
// durante VALIDEZ_MS. Solo da acceso a lo que hay en esta tablet: los datos del servidor
// siguen exigiendo la cookie. Al volver la conexión se comprueba de nuevo (comprobarAlVolver).
import { ref } from 'vue'
import { guardar, leer, borrar } from './almacen.js'

const CLAVE = 'sesion'
const VALIDEZ_MS = 24 * 60 * 60 * 1000 // una jornada de visitas

// true mientras la sesión viene de la tablet y no del servidor
export const sinVerificar = ref(false)

export async function recordarSesion(auth) {
  if (!auth.isAuthenticated || sinVerificar.value) return
  try {
    await guardar(CLAVE, { id: auth.userId, name: auth.userName, role: auth.userRole, verificadaAt: Date.now() })
  } catch { /* sin almacén (navegación privada): simplemente no habrá acceso sin conexión */ }
}

export async function olvidarSesion() {
  sinVerificar.value = false
  try { await borrar(CLAVE) } catch { /* nada que borrar */ }
}

// Sin red y sin sesión del servidor: entra con la sesión recordada si no ha caducado
async function restaurar(auth) {
  try {
    const sesion = await leer(CLAVE)
    if (!sesion || Date.now() - sesion.verificadaAt > VALIDEZ_MS) return false
    auth.login({ id: sesion.id, name: sesion.name, role: sesion.role })
    sinVerificar.value = true
    return true
  } catch {
    return false
  }
}

// ¿Responde el servidor? navigator.onLine dice si hay red, no si hay internet (wifi sin salida)
async function hayServidor() {
  if (!navigator.onLine) return false
  try {
    await fetch(`/version.json?t=${Date.now()}`, { cache: 'no-store' })
    return true
  } catch {
    return false
  }
}

// Sustituye a auth.init() en el guard de la app comercial
export async function iniciarSesion(auth) {
  if (auth.isInitialized) return
  if (navigator.onLine) {
    await auth.init()
    if (auth.isAuthenticated) { recordarSesion(auth); return }
    // Sin sesión: puede ser un 401 de verdad o que no haya internet
    if (await hayServidor()) return
  }
  if (!(await restaurar(auth))) {
    // Que el guard no lo tome por inicializado con sesión: queda como invitado (pantalla de acceso)
    if (!auth.isInitialized) await auth.init()
  }
}

// Al volver la conexión con una sesión recordada: /perfil confirma la cookie. Un 401 lo
// gestiona api.js ('auth:token-expired' → App.vue cierra sesión y lleva al acceso).
export async function comprobarAlVolver(auth) {
  if (!sinVerificar.value) return
  if (await auth.ping()) {
    sinVerificar.value = false
    recordarSesion(auth)
  }
}
