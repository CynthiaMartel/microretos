// Fases estándar del microproyecto — reflejan las mismas 5 fases fijas que
// el alumnado recorre en EquipoWorkspace.vue (fasesConfig). Nombre/descripción
// no se editan; la duración ya no vive aquí por fase, sino que se deriva del
// calendario de clases (ver CLASES_PROYECTO_DEFECTO/duracionPorFase más abajo),
// porque una misma clase puede cubrir varias fases a la vez.
export const FASES_PROYECTO = [
  { num: 0, label: 'Inicio del equipo',  icono: '👥', color: 'slate',  desc: 'Constitución del equipo', descLarga: 'Conóceos, estableced roles y acordad cómo vais a trabajar juntos durante el reto.' },
  { num: 1, label: 'Análisis del reto',  icono: '🔍', color: 'blue',   desc: 'Comprensión del reto',     descLarga: 'Analizad en profundidad el reto planteado por la empresa y definid vuestra propuesta de solución con datos concretos.' },
  { num: 2, label: 'Diseño de solución', icono: '💡', color: 'amber',  desc: 'Prototipo y propuesta',    descLarga: 'Dividid el trabajo en tareas y avanzad en la construcción de vuestra solución.' },
  { num: 3, label: 'Desarrollo',         icono: '🔨', color: 'orange', desc: 'Construcción del producto', descLarga: 'Construid y entregad el trabajo final al docente y a la empresa validadora.' },
  { num: 4, label: 'Presentación',       icono: '🎓', color: 'green',  desc: 'Entrega y reflexión',      descLarga: 'Reflexionad individualmente y en grupo sobre lo aprendido. Es el cierre del proyecto.' },
]

// Calendario de clases por defecto — cada entrada es una clase, con las fases
// (por su `num`) que se trabajan en ella. Una clase puede cubrir varias fases.
export const CLASES_PROYECTO_DEFECTO = [
  { fases: [0, 1] }, // Inicio del equipo + Análisis del reto
  { fases: [2, 3] }, // Diseño de solución + Desarrollo
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
