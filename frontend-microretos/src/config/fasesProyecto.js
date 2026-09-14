// Fases estándar del microproyecto — reflejan las mismas 5 fases fijas que
// el alumnado recorre en EquipoWorkspace.vue (fasesConfig); si se renombra una fase
// aquí, hacer lo mismo en fasesConfig para que alumnado y docentes vean el mismo nombre.
// La duración no vive aquí por fase, sino que se deriva del calendario de clases (ver
// CLASES_PROYECTO_DEFECTO/duracionPorFase más abajo), porque una misma clase puede
// cubrir varias fases a la vez.
// `peso`: contribución de cada fase al progreso total de un equipo (deben sumar 100).
// Hoy están repartidas a partes iguales, pero viven aquí — no repartidas en cada
// componente — para poder ajustarlas sin tocar el cálculo que las consume.
export const FASES_PROYECTO = [
  { num: 0, label: 'Inicio del equipo',  icono: '👥', color: 'slate',  desc: 'Constitución del equipo', descLarga: 'Conóceos, estableced roles y acordad cómo vais a trabajar juntos durante el reto.', peso: 20 },
  { num: 1, label: 'Análisis del reto',  icono: '🔍', color: 'blue',   desc: 'Comprensión del reto',     descLarga: 'Analizad en profundidad el reto planteado por la empresa y definid vuestra propuesta de solución con datos concretos.', peso: 20 },
  { num: 2, label: 'Diseño de solución y desarrollo', icono: '💡', color: 'amber',  desc: 'Prototipo, tareas y desarrollo', descLarga: 'Diseñad y construid vuestra solución: definid el prototipo, dividid el trabajo en tareas y avanzad en la construcción.', peso: 20 },
  { num: 3, label: 'Entrega de la solución',          icono: '🔨', color: 'orange', desc: 'Entrega de la solución',         descLarga: 'Entregad la solución final que proponéis para cubrir la necesidad de la empresa, al docente y a la empresa validadora.', peso: 20 },
  { num: 4, label: 'Presentación',       icono: '🎓', color: 'green',  desc: 'Entrega y reflexión',      descLarga: 'Reflexionad individualmente y en grupo sobre lo aprendido. Es el cierre del proyecto.', peso: 20 },
]

// Progreso real de un equipo (0-100) según los pesos de fase de arriba, no un reparto
// igual asumido en cada sitio que lo calculaba. `fases` es el objeto indexado por
// número de fase que devuelve la API (equipo.fases), con `completada` por fase.
export function progresoPonderado(fases) {
  const pesoTotal = FASES_PROYECTO.reduce((acc, f) => acc + f.peso, 0)
  if (!pesoTotal) return 0
  const pesoCompletado = FASES_PROYECTO.reduce(
    (acc, f) => acc + (fases?.[f.num]?.completada ? f.peso : 0),
    0
  )
  return Math.round((pesoCompletado / pesoTotal) * 100)
}

// Calendario de clases por defecto — cada entrada es una clase, con las fases
// (por su `num`) que se trabajan en ella. Una clase puede cubrir varias fases.
export const CLASES_PROYECTO_DEFECTO = [
  { fases: [0, 1] }, // Inicio del equipo + Análisis del reto
  { fases: [2, 3] }, // Diseño de solución y desarrollo + Entrega de la solución
  { fases: [2, 3] },
  { fases: [4] },    // Presentación
]

// Colores de fondo/borde/texto por fase — únicos para todos los sitios que
// pintan tarjetas de fase (wizard, detalle de proyecto publicado).
export const COLOR_MAP_FASES = {
  slate:  'bg-slate-50  border-slate-200  text-slate-600',
  blue:   'bg-blue-50   border-blue-200   text-blue-600',
  amber:  'bg-amber-50  border-amber-200  text-amber-600',
  orange: 'bg-orange-50 border-orange-200 text-orange-600',
  green:  'bg-[#00A859]/10 border-[#00A859]/20 text-[#00A859]',
}

// Nº de clases en las que aparece una fase concreta del calendario.
export function duracionPorFase(clases, numFase) {
  return (clases || []).filter(c => (c.fases || []).includes(numFase)).length
}

// Heurística única para convertir "clases" en calendario — debe coincidir con
// Microproyecto::SEMANAS_POR_CLASE en el backend. Toda vista previa de fecha_fin
// en el frontend debe pasar por fechaFinEstimada(), no repetir el cálculo.
export const SEMANAS_POR_CLASE = 1

export function fechaFinEstimada(fechaInicioISO, totalClases) {
  if (!fechaInicioISO || !totalClases) return null
  const d = new Date(fechaInicioISO + 'T00:00:00')
  d.setDate(d.getDate() + totalClases * SEMANAS_POR_CLASE * 7)
  return d.toISOString().slice(0, 10)
}
