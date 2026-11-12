// Entrevistas sin conexión. Cada paso de la entrevista (empresa, diagnóstico y firma) se guarda
// en el servidor si hay red; si no la hay, o la petición se queda sin respuesta, se guarda
// cifrado en la tablet (almacen.js) y se envía después, en el mismo orden, con sincronizar().
//
// Registro local (uno por entrevista, id 'entrevista:<userId>:<clave>'):
//   { clave, userId, empresaId (null hasta que el servidor la crea), base (la empresa tal como
//     la dio el servidor, si ya existía), empresa / diagnostico / firma (payloads pendientes de
//     enviar, o null), creadaAt, actualizadaAt, error (último rechazo del servidor) }
// vista() lo convierte en un objeto con la forma de EmpresaCaptacionResource, para que los
// pasos de la entrevista no distingan si la empresa es del servidor o de la tablet.
//
// Los retos no funcionan sin conexión: los genera la IA en el servidor.
import { ref } from 'vue'
import { useAuthStore } from '../../src/stores/auth.js'
import { COLUMNA_DE } from '../../src/config/preguntasDiagnostico.js'
import {
  crearEmpresaCaptada, actualizarEmpresaCaptada, guardarDiagnosticoCaptada,
  firmarEmpresaCaptada, getEmpresaCaptada,
} from '../../src/services/comercialService.js'
import { guardar, leer, borrar, listar, pedirPersistencia } from './almacen.js'
import { sinVerificar } from './sesionLocal.js'

export const pendientes           = ref([])  // vistas de las entrevistas guardadas en la tablet
export const sincronizando        = ref(false)
export const ultimaSincronizacion = ref(0)   // MisEntrevistas recarga la lista del servidor al cambiar
// Clave de la entrevista local abierta en pantalla: sincronizar() no la toca mientras se edita
export const entrevistaAbierta    = ref(null)

const prefijo = () => `entrevista:${useAuthStore().userId}:`
const idDe = (clave) => `${prefijo()}${clave}`

// Error de red: la petición no obtuvo respuesta del servidor
const sinRespuesta = (err) => !err?.response

const aSnake = (k) => k.replace(/[A-Z]/g, (m) => `_${m.toLowerCase()}`)

export function vista(r) {
  const e = { ...(r.base ?? {}) }
  if (r.empresa) {
    for (const [k, v] of Object.entries(r.empresa)) {
      if (!['familias', 'interesesFamilia', 'clienteUuid'].includes(k)) e[aSnake(k)] = v
    }
    if (r.empresa.familias) {
      e.familias = r.empresa.familias.map((nombre) => ({ nombre, interes: r.empresa.interesesFamilia?.[nombre] ?? '' }))
    }
  }
  if (r.diagnostico) {
    for (const [campo, valor] of Object.entries(r.diagnostico)) e[COLUMNA_DE[campo]] = valor
  }
  e.id = r.empresaId ?? null
  e.created_at = e.created_at ?? r.creadaAt
  e._local = r.clave
  e._error = r.error || ''
  if (r.firma) {
    // Firmada en la tablet: solo consulta, como una firmada en el servidor
    e.estado = 'pendiente_envio'
    e.firmada_at = r.firma.capturadaAt
    e.firma = { firmante_nombre: r.firma.firmanteNombre }
  } else {
    e.estado = 'borrador'
  }
  return e
}

export async function recargarPendientes() {
  try {
    const registros = await listar(prefijo())
    pendientes.value = registros
      .sort((a, b) => b.actualizadaAt - a.actualizadaAt)
      .map(vista)
  } catch {
    pendientes.value = []
  }
}

export async function obtenerLocal(clave) {
  const r = await leer(idDe(clave))
  return r ? vista(r) : null
}

export const pendienteDeEmpresa = (empresaId) => pendientes.value.find((e) => e.id === empresaId) ?? null

// Registro de una empresa: el que ya hay en la tablet, o uno nuevo a partir de la del servidor
async function registroDe(empresa, clave) {
  if (empresa?._local) {
    const r = await leer(idDe(empresa._local))
    if (!r) throw new Error('Esta entrevista ya se ha enviado. Vuelve a abrirla desde «Empresas».')
    return r
  }
  const base = empresa ? { ...empresa } : null
  return {
    clave: clave ?? crypto.randomUUID(), userId: useAuthStore().userId,
    empresaId: empresa?.id ?? null, base,
    empresa: null, diagnostico: null, firma: null,
    creadaAt: new Date().toISOString(), actualizadaAt: Date.now(), error: '',
  }
}

async function guardarRegistro(r) {
  r.actualizadaAt = Date.now()
  r.error = ''
  try {
    await guardar(idDe(r.clave), r)
  } catch {
    throw new Error('No se ha podido guardar en esta tablet. Comprueba que el navegador no está en modo privado.')
  }
  pedirPersistencia()
  await recargarPendientes()
  return vista(r)
}

// ── Guardado de cada paso: servidor si se puede, tablet si no ──────────────────────────────

export async function guardarEmpresa(empresa, payload) {
  // Mismo identificador en el alta en línea y en la que se guarde en la tablet: si la respuesta
  // se pierde y luego se reenvía, el servidor no la duplica
  const clave = crypto.randomUUID()
  if (!empresa?._local && navigator.onLine) {
    try {
      const { data } = empresa
        ? await actualizarEmpresaCaptada(empresa.id, payload)
        : await crearEmpresaCaptada({ ...payload, clienteUuid: clave })
      return data.data
    } catch (err) {
      if (!sinRespuesta(err)) throw err
    }
  }
  const r = await registroDe(empresa, clave)
  r.empresa = r.empresaId ? payload : { ...payload, clienteUuid: r.clave }
  return guardarRegistro(r)
}

export async function guardarDiagnostico(empresa, payload) {
  if (!empresa._local && navigator.onLine) {
    try {
      return (await guardarDiagnosticoCaptada(empresa.id, payload)).data.data
    } catch (err) {
      if (!sinRespuesta(err)) throw err
    }
  }
  const r = await registroDe(empresa)
  r.diagnostico = payload
  return guardarRegistro(r)
}

export async function firmar(empresa, payload) {
  if (!empresa._local && navigator.onLine) {
    try {
      return (await firmarEmpresaCaptada(empresa.id, payload)).data.data
    } catch (err) {
      if (!sinRespuesta(err)) throw err
    }
  }
  const r = await registroDe(empresa)
  // Hora de la tablet: el servidor la guarda aparte y sella la firma al recibirla
  r.firma = { ...payload, capturadaAt: new Date().toISOString() }
  return guardarRegistro(r)
}

// DuaLab rechazó una entrevista firmada en la tablet (datos inválidos, textos legales que han
// cambiado…): se descarta esa firma para corregir lo que haga falta y volver a firmar
export async function descartarFirma(clave) {
  const r = await leer(idDe(clave))
  if (!r) return null
  r.firma = null
  return guardarRegistro(r)
}

// ── Envío al servidor ──────────────────────────────────────────────────────────────────────

const mensajeDe = (err) => {
  const errores = err.response?.data?.errors
  return (errores && Object.values(errores)[0]?.[0]) || err.response?.data?.error
    || err.response?.data?.message || 'El servidor no ha aceptado la entrevista.'
}

// Envía lo pendiente de un registro, paso a paso. Tras cada paso aceptado se guarda el avance
// (y lo que devolvió el servidor pasa a ser la base): si la conexión se corta a mitad, la
// siguiente vez sigue desde ahí sin perder lo que se ve en pantalla.
async function enviar(r) {
  let empresa = null
  const avanzar = () => guardar(idDe(r.clave), r)

  if (r.empresa) {
    if (!r.empresaId) {
      const datos = Object.fromEntries(Object.entries(r.empresa).filter(([, v]) => v !== null))
      empresa = (await crearEmpresaCaptada(datos)).data.data
      r.empresaId = empresa.id
    } else {
      empresa = (await actualizarEmpresaCaptada(r.empresaId, r.empresa)).data.data
    }
    r.base = empresa
    r.empresa = null
    await avanzar()
  }
  if (r.diagnostico) {
    empresa = (await guardarDiagnosticoCaptada(r.empresaId, r.diagnostico)).data.data
    r.base = empresa
    r.diagnostico = null
    await avanzar()
  }
  if (r.firma) {
    try {
      empresa = (await firmarEmpresaCaptada(r.empresaId, r.firma)).data.data
    } catch (err) {
      // Ya firmada: la firma llegó en un intento anterior cuya respuesta se perdió
      if (err.response?.status !== 422) throw err
      empresa = (await getEmpresaCaptada(r.empresaId)).data.data
      if (empresa.estado === 'borrador') throw err
    }
  }
  await borrar(idDe(r.clave))
  return empresa ?? (await getEmpresaCaptada(r.empresaId)).data.data
}

// Envía todas las entrevistas de la tablet (menos la que está abierta en pantalla).
// Se llama al arrancar con conexión, al volver la conexión y desde «Enviar ahora».
export async function sincronizar() {
  if (sincronizando.value || !navigator.onLine || sinVerificar.value) return
  sincronizando.value = true
  try {
    const registros = await listar(prefijo())
    for (const r of registros) {
      if (r.clave === entrevistaAbierta.value) continue
      try {
        await enviar(r)
      } catch (err) {
        // Sin conexión, sesión caducada (api.js ya lleva al acceso) o demasiadas peticiones:
        // se para y se reintenta más tarde. Un rechazo del servidor se apunta en la entrevista.
        if (sinRespuesta(err) || [401, 419, 429].includes(err.response.status)) break
        r.error = mensajeDe(err)
        await guardar(idDe(r.clave), r)
      }
    }
  } catch { /* almacén no disponible */ } finally {
    sincronizando.value = false
    await recargarPendientes()
    ultimaSincronizacion.value = Date.now()
  }
}

// Envía ya la entrevista abierta en pantalla (p. ej. para generar retos). Devuelve la empresa
// del servidor; si falla, el registro sigue en la tablet y se lanza el error con su mensaje.
export async function enviarAhora(clave) {
  const r = await leer(idDe(clave))
  if (!r) throw new Error('Esta entrevista ya se ha enviado.')
  try {
    return await enviar(r)
  } catch (err) {
    if (sinRespuesta(err)) throw new Error('Sigue sin haber conexión con DuaLab. Inténtalo en un momento.')
    r.error = mensajeDe(err)
    await guardar(idDe(r.clave), r)
    throw new Error(r.error)
  } finally {
    await recargarPendientes()
  }
}

// ── Datos de consulta para trabajar sin conexión (no personales) ──────────────────────────

// Pide al servidor y guarda la respuesta; sin red, devuelve la última guardada
export async function conCache(nombre, peticion) {
  try {
    const { data } = await peticion()
    guardar(`cache:${nombre}`, data).catch(() => {})
    return data
  } catch (err) {
    if (!sinRespuesta(err)) throw err
    const guardado = await leer(`cache:${nombre}`).catch(() => null)
    if (guardado) return guardado
    throw err
  }
}
