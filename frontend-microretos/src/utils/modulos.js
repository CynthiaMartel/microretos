// Nombres de módulo de retos, proyectos y encuentros, para el filtro "Módulo" de las vistas
// por familia. Un elemento con varios módulos (multimódulo) aparece al filtrar por cada uno.

// El catálogo guarda algunos nombres con punto final ("Contabilidad y fiscalidad."):
// se normalizan para que el mismo módulo no salga dos veces en el filtro.
export const nombreModulo = (n) => String(n ?? '').trim().replace(/\.$/, '')

const limpios = (nombres) => [...new Set(nombres.map(nombreModulo).filter(Boolean))]

// Reto: los módulos de su evaluación oficial. No se trocea el texto de `modulo`
// ("A y B"), porque los nombres también llevan "y" ("Contabilidad y fiscalidad").
export function modulosDeReto(r) {
  const deEvaluacion = limpios((r?.evaluacion_oficial || []).map(e => e?.modulo))
  if (deEvaluacion.length) return deEvaluacion
  return r?.modulo && !r.multimodulo && !/transversal|a determinar/i.test(r.modulo) ? limpios([r.modulo]) : []
}

// Proyecto: los módulos elegidos y, si no los tiene, los de su evaluación oficial
// (mismo criterio que Microproyecto::nombresModulos en el backend).
export function modulosDeProyecto(p) {
  const elegidos = limpios((p?.modulos_seleccionados || []).map(m => m?.nombre))
  return elegidos.length ? elegidos : limpios((p?.evaluacion_oficial || []).map(e => e?.modulo))
}

// Encuentro: el backend ya los envía resueltos (EncuentroResource → `modulos`)
export const modulosDeEncuentro = (e) => limpios(e?.modulos || [])

// Opciones del filtro: módulos presentes en la lista, en orden alfabético
export const modulosDisponibles = (lista, modulosDe) =>
  [...new Set(lista.flatMap(modulosDe))].sort((a, b) => a.localeCompare(b, 'es'))
