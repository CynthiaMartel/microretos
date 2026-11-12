// Almacén de la tablet para trabajar sin conexión (IndexedDB 'dualab-comercial').
//   'claves' → una clave AES-GCM generada en este dispositivo y NO exportable: el código de la
//              app puede usarla para cifrar y descifrar, pero nadie puede sacarla del navegador.
//   'datos'  → { id, iv, cifrado }: todo se guarda cifrado con esa clave (entrevistas, sesión y
//              las cachés de familias y textos legales).
// Nunca localStorage: las entrevistas llevan datos personales (contacto, DNI y firma).

const NOMBRE_DB = 'dualab-comercial'
const VERSION_DB = 1

let dbPromesa = null
function abrir() {
  if (!dbPromesa) {
    dbPromesa = new Promise((resolver, rechazar) => {
      const peticion = indexedDB.open(NOMBRE_DB, VERSION_DB)
      peticion.onupgradeneeded = () => {
        const db = peticion.result
        if (!db.objectStoreNames.contains('claves')) db.createObjectStore('claves')
        if (!db.objectStoreNames.contains('datos')) db.createObjectStore('datos', { keyPath: 'id' })
      }
      peticion.onsuccess = () => resolver(peticion.result)
      peticion.onerror = () => { dbPromesa = null; rechazar(peticion.error) }
    })
  }
  return dbPromesa
}

// Una operación sobre un almacén; devuelve el resultado de la petición
async function operar(almacen, modo, accion) {
  const db = await abrir()
  return new Promise((resolver, rechazar) => {
    const tx = db.transaction(almacen, modo)
    const peticion = accion(tx.objectStore(almacen))
    tx.oncomplete = () => resolver(peticion?.result)
    tx.onerror = () => rechazar(tx.error)
    tx.onabort = () => rechazar(tx.error)
  })
}

let clavePromesa = null
function clave() {
  if (!clavePromesa) {
    clavePromesa = (async () => {
      const guardada = await operar('claves', 'readonly', (s) => s.get('principal'))
      if (guardada) return guardada
      const nueva = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt'])
      await operar('claves', 'readwrite', (s) => s.put(nueva, 'principal'))
      return nueva
    })().catch((e) => { clavePromesa = null; throw e })
  }
  return clavePromesa
}

export async function guardar(id, valor) {
  const iv = crypto.getRandomValues(new Uint8Array(12))
  const texto = new TextEncoder().encode(JSON.stringify(valor))
  const cifrado = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await clave(), texto)
  await operar('datos', 'readwrite', (s) => s.put({ id, iv, cifrado }))
}

async function descifrar(registro) {
  const texto = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: registro.iv }, await clave(), registro.cifrado)
  return JSON.parse(new TextDecoder().decode(texto))
}

export async function leer(id) {
  const registro = await operar('datos', 'readonly', (s) => s.get(id))
  return registro ? descifrar(registro) : null
}

export async function borrar(id) {
  await operar('datos', 'readwrite', (s) => s.delete(id))
}

// Todos los valores cuyo id empieza por `prefijo` (p. ej. 'entrevista:')
export async function listar(prefijo) {
  const rango = IDBKeyRange.bound(prefijo, `${prefijo}￿`)
  const registros = await operar('datos', 'readonly', (s) => s.getAll(rango))
  return Promise.all((registros ?? []).map(descifrar))
}

// Pide al navegador que no borre estos datos si le falta espacio (las entrevistas sin enviar
// solo existen aquí). Si lo deniega no pasa nada: la app instalada ya tiene prioridad.
export function pedirPersistencia() {
  navigator.storage?.persist?.().catch(() => {})
}
