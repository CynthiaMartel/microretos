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
    const revisar = proyectos.value.filter(p => p.estado === 'propuesta' && p.empresa_no_valida_aun)
    if (revisar.length) t.push({ id: `revisar:${revisar.length}`, texto: `Revisar los cambios que propone${revisar.length > 1 ? 'n' : ''} ${revisar.length} empresa${s(revisar.length)}`, ruta: '/proyectos', nivel: 'alta' })
    // La validan el docente o la empresa: se avisa de las que esperan cualquiera de las dos
    const validar = proyectos.value.filter(p => p.estado === 'propuesta' && !p.empresa_no_valida_aun)
    if (validar.length) t.push({ id: `validar:${validar.length}`, texto: `Validar ${validar.length} propuesta${s(validar.length)} pendiente${s(validar.length)}`, ruta: '/proyectos', nivel: 'media' })
    const estancados = proyectos.value.filter(p => p.estado === 'en_edicion' && p.updated_at && dias(p.updated_at) >= 14)
    if (estancados.length) t.push({ id: `retomar:${estancados.length}`, texto: `Retomar ${estancados.length} propuesta${s(estancados.length)} parada${s(estancados.length)} hace +14 días`, ruta: '/proyectos', nivel: 'media' })
    const sinRespuesta = proyectos.value.filter(p => p.estado === 'propuesta' && p.enviado_a_empresa_mail && !p.empresa_validado && p.updated_at && dias(p.updated_at) >= 10)
    if (sinRespuesta.length) t.push({ id: `recordar:${sinRespuesta.length}`, texto: `Recordar a ${sinRespuesta.length} empresa${s(sinRespuesta.length)} sin responder (+10 días)`, ruta: '/proyectos', nivel: 'media' })
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
