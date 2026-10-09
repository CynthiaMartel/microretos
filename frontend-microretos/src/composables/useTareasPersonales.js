import { ref } from 'vue'
import { getTareas, crearTarea, actualizarTarea, borrarTarea } from '../services/tareaService.js'

// Tareas personales del docente, guardadas en BD (antes en localStorage 'docente_notas').
// Estado compartido a nivel de módulo: el panel docente y el Calendario ven la misma lista.
// Cada tarea se expone como { id, text, hecha } — el formato que ya usaban las plantillas.
const CLAVE_ANTIGUA = 'docente_notas'

const tareas = ref([])
const error  = ref('')
let carga = null

const aVista = (t) => ({ id: t.id, text: t.texto, hecha: t.hecha })

// Sube una sola vez las tareas que quedaran en localStorage de este navegador y limpia la clave
async function importarAntiguas() {
  let antiguas = []
  try { antiguas = JSON.parse(localStorage.getItem(CLAVE_ANTIGUA) || '[]') } catch { /* clave corrupta: se ignora */ }
  if (!Array.isArray(antiguas) || !antiguas.length) return
  for (const n of antiguas) {
    const texto = String(n?.text ?? '').trim().slice(0, 200)
    if (!texto) continue
    const { data } = await crearTarea({ texto, hecha: !!n.hecha })
    tareas.value = [...tareas.value, aVista(data.data)]
  }
  try { localStorage.removeItem(CLAVE_ANTIGUA) } catch { /* sin almacenamiento */ }
}

function cargar() {
  carga ??= (async () => {
    try {
      const { data } = await getTareas()
      tareas.value = data.data.map(aVista)
      await importarAntiguas()
    } catch {
      error.value = 'No se han podido cargar tus tareas.'
      carga = null // permite reintentar al volver a la vista
    }
  })()
  return carga
}

async function addTarea(texto) {
  const limpio = texto.trim()
  if (!limpio) return
  error.value = ''
  try {
    const { data } = await crearTarea({ texto: limpio })
    tareas.value = [...tareas.value, aVista(data.data)]
  } catch { error.value = 'No se ha podido guardar la tarea.' }
}

async function toggleTarea(id) {
  const t = tareas.value.find(x => x.id === id)
  if (!t) return
  // Cambio optimista: se revierte si falla
  t.hecha = !t.hecha
  try { await actualizarTarea(id, { hecha: t.hecha }) }
  catch { t.hecha = !t.hecha; error.value = 'No se ha podido actualizar la tarea.' }
}

async function quitarTarea(id) {
  const previas = tareas.value
  tareas.value = previas.filter(x => x.id !== id)
  try { await borrarTarea(id) }
  catch { tareas.value = previas; error.value = 'No se ha podido borrar la tarea.' }
}

export function useTareasPersonales() {
  cargar()
  return { tareas, error, addTarea, toggleTarea, borrarTarea: quitarTarea }
}
