// Encuentro.curso es texto libre (StoreEncuentroRequest: 'curso' => 'string|max:10'),
// no un valor de un select controlado — según cómo lo escribiera quien creó el
// encuentro puede venir como "2" o ya como "2º". Concatenar "º" a ciegas duplica el
// símbolo cuando ya viene incluido (p.ej. "2ºº curso"). Úsalo en cualquier sitio que
// muestre Encuentro.curso con la palabra "curso" al lado; no aplica a Microreto/
// Microproyecto.curso, que es un valor controlado (bare digit o 'ambos_cursos') con
// su propio formato ya consistente en toda la app.
export function formatCurso(curso) {
  if (!curso) return ''
  const texto = String(curso).trim()
  return /º$/.test(texto) ? texto : `${texto}º`
}
