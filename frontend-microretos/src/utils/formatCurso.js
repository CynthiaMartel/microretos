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
  // Solo se añade «º» a un número suelto ("2" → "2º"); «1º y 2º» o «2º» quedan como están
  return /^\d+$/.test(texto) ? `${texto}º` : texto
}

// Curso de una propuesta o de un reto (Microproyecto/Microreto.curso: '1', '2', 'ambos_cursos'; en
// datos antiguos, '1º'/'2º') → nivel común: '1' | '2' | 'ambos' | ''.
export function nivelCurso(curso) {
  const t = String(curso ?? '').trim().toLowerCase()
  if (!t) return ''
  if (t === 'ambos_cursos' || t === 'ambos' || t.includes('y')) return 'ambos'
  if (t.startsWith('1')) return '1'
  if (t.startsWith('2')) return '2'
  return ''
}

// Valor del curso de un encuentro (texto libre que se muestra con «curso» detrás) para cada nivel
export const CURSO_ENCUENTRO = { 1: '1º', 2: '2º', ambos: '1º y 2º' }
