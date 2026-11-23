import { ref, computed } from 'vue'
import { useTareasPersonales } from './useTareasPersonales.js'

// Tareas pendientes del docente = avisos automáticos (derivados de sus proyectos) +
// tareas personales. Copia de la lógica de InicioDocente.vue para la vista Calendario.
// Las personales viven en BD (useTareasPersonales, compartido con el panel); el estado
// "hecha" de las automáticas sigue en localStorage ('docente_tareas_hechas'), igual que
// en el panel: es una preferencia de UI sobre avisos que se recalculan solos.
const leerJSON = (clave, defecto) => { try { return JSON.parse(localStorage.getItem(clave) || 'null') ?? defecto } catch { return defecto } }
const guardarJSON = (clave, valor) => { try { localStorage.setItem(clave, JSON.stringify(valor)) } catch { /* sin almacenamiento */ } }

export function useTareasDocente(proyectos, cargando) {
  const tareasAuto = computed(() => {
    if (cargando.value) return []
    const now = Date.now()
    const dias = (d) => Math.floor((now - new Date(d).getTime()) / 86_400_000)
    const s = (n) => (n > 1 ? 's' : '')
    const t = []
    // Sin validar: el docente la valida cuando la quiere trabajar (la empresa ya no valida al inicio)
    const validar = proyectos.value.filter(p => p.estado === 'propuesta')
    if (validar.length) t.push({ id: `validar:${validar.length}`, texto: `Validar ${validar.length} reto${s(validar.length)} ajustado${s(validar.length)}`, ruta: '/proyectos', nivel: 'media' })
    const estancados = proyectos.value.filter(p => p.estado === 'en_edicion' && p.updated_at && dias(p.updated_at) >= 14)
    if (estancados.length) t.push({ id: `retomar:${estancados.length}`, texto: `Retomar ${estancados.length} reto${s(estancados.length)} ajustado${s(estancados.length)} parado${s(estancados.length)} hace +14 días`, ruta: '/proyectos', nivel: 'media' })
    return t
  })

  const autoHechas = ref(leerJSON('docente_tareas_hechas', []))
  const autoHecha  = (t) => autoHechas.value.includes(t.id)
  function toggleAuto(t) {
    autoHechas.value = autoHecha(t) ? autoHechas.value.filter(id => id !== t.id) : [...autoHechas.value, t.id]
    guardarJSON('docente_tareas_hechas', autoHechas.value)
  }

  // Tareas personales: { id, text, hecha } desde BD
  const { tareas: propias, error: errorTareas, addTarea, toggleTarea, borrarTarea } = useTareasPersonales()

  // Primero las automáticas pendientes, luego las personales pendientes y al final las hechas
  const listaTareas = computed(() => {
    const autos = tareasAuto.value.map(t => ({ key: t.id, tipo: 'auto', t, hecha: autoHecha(t) }))
    const mias  = propias.value.map(n => ({ key: 'nota-' + n.id, tipo: 'nota', n, hecha: !!n.hecha }))
    return [...autos.filter(x => !x.hecha), ...mias.filter(x => !x.hecha), ...autos.filter(x => x.hecha), ...mias.filter(x => x.hecha)]
  })
  const pendientes = computed(() => listaTareas.value.filter(x => !x.hecha).length)

  return { listaTareas, pendientes, errorTareas, toggleAuto, addTarea, toggleTarea, borrarTarea }
}
