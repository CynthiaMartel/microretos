// Entrevistas a centros sin conexión. Es un solo envío (no hay pasos): con red va al servidor;
// sin red, o si la petición se queda sin respuesta, se guarda cifrada en la tablet y se envía
// después con sincronizarCentros(). El clienteUuid es el mismo en el intento en línea y en el
// guardado local: si el primero llegó pero se perdió la respuesta, el servidor no lo duplica.
//
// Registro local (id 'centro:<userId>:<clave>'):
//   { clave, userId, payload (con clienteUuid = clave), creadaAt, actualizadaAt, error }
import { ref } from 'vue'
import { useAuthStore } from '../../src/stores/auth.js'
import { crearCentroCaptado } from '../../src/services/comercialService.js'
import { guardar, leer, borrar, listar, pedirPersistencia } from './almacen.js'
import { sinVerificar } from './sesionLocal.js'

export const centrosPendientes = ref([]) // registros locales, los más recientes primero
export const enviandoCentros   = ref(false)
export const ultimoEnvioCentros = ref(0) // MisCentros recarga la lista del servidor al cambiar

const prefijo = () => `centro:${useAuthStore().userId}:`
const idDe = (clave) => `${prefijo()}${clave}`
const sinRespuesta = (err) => !err?.response

const mensajeDe = (err) => {
  const errores = err.response?.data?.errors
  return (errores && Object.values(errores)[0]?.[0]) || err.response?.data?.error
    || err.response?.data?.message || 'El servidor no ha aceptado la entrevista.'
}

export async function recargarCentrosPendientes() {
  try {
    centrosPendientes.value = (await listar(prefijo())).sort((a, b) => b.actualizadaAt - a.actualizadaAt)
  } catch {
    centrosPendientes.value = []
  }
}

export async function obtenerCentroLocal(clave) {
  return leer(idDe(clave))
}

/**
 * Guarda la entrevista a un centro: en el servidor si se puede, en la tablet si no.
 * claveLocal: al corregir una que DuaLab rechazó, se reutiliza su registro (y su clienteUuid).
 * Devuelve { enServidor: true, centro } o { enServidor: false, clave }.
 */
export async function guardarCentro(payload, claveLocal = null) {
  const clave = claveLocal ?? crypto.randomUUID()
  const datos = { ...payload, clienteUuid: clave }
  if (navigator.onLine && !sinVerificar.value) {
    try {
      const { data } = await crearCentroCaptado(datos)
      if (claveLocal) await borrar(idDe(claveLocal)).catch(() => {})
      await recargarCentrosPendientes()
      return { enServidor: true, centro: data.data }
    } catch (err) {
      if (!sinRespuesta(err)) throw err
    }
  }
  const anterior = claveLocal ? await leer(idDe(claveLocal)) : null
  try {
    await guardar(idDe(clave), {
      clave, userId: useAuthStore().userId, payload: datos,
      creadaAt: anterior?.creadaAt ?? new Date().toISOString(), actualizadaAt: Date.now(), error: '',
    })
  } catch {
    throw new Error('No se ha podido guardar en esta tablet. Comprueba que el navegador no está en modo privado.')
  }
  pedirPersistencia()
  await recargarCentrosPendientes()
  return { enServidor: false, clave }
}

// Envía las entrevistas a centros guardadas en la tablet. Se llama al arrancar con conexión,
// al volver la conexión y desde «Enviar ahora».
export async function sincronizarCentros() {
  if (enviandoCentros.value || !navigator.onLine || sinVerificar.value) return
  enviandoCentros.value = true
  try {
    for (const r of await listar(prefijo())) {
      try {
        await crearCentroCaptado(r.payload)
        await borrar(idDe(r.clave))
      } catch (err) {
        // Sin conexión, sesión caducada o demasiadas peticiones: se reintenta más tarde.
        // Un rechazo del servidor se apunta en la entrevista para corregirla.
        if (sinRespuesta(err) || [401, 419, 429].includes(err.response.status)) break
        r.error = mensajeDe(err)
        await guardar(idDe(r.clave), r)
      }
    }
  } catch { /* almacén no disponible */ } finally {
    enviandoCentros.value = false
    await recargarCentrosPendientes()
    ultimoEnvioCentros.value = Date.now()
  }
}
