// Estilo Kahoot: "generar otro" elige animal + color al azar en el momento, sin ir al
// backend — el backend (App\Support\AliasGenerator) solo genera el alias inicial
// (determinista) al dar de alta. Si se cambia una lista aquí, cambiar también la de
// AliasGenerator para que un alias regenerado en frontend nunca use una combinación
// que el backend no reconocería.
export const ANIMALES_ALIAS = [
  'Panda', 'Tigre', 'León', 'Delfín', 'Águila', 'Lobo', 'Ratón', 'Koala',
  'Halcón', 'Pingüino', 'Jaguar', 'Puma', 'Búho', 'Colibrí', 'Nutria',
  'Lince', 'Gacela', 'Cóndor', 'Orca', 'Mapache',
]

export const COLORES_ALIAS = [
  'Magenta', 'Azul', 'Coral', 'Verde', 'Amarillo', 'Violeta', 'Turquesa',
  'Naranja', 'Rosa', 'Dorado', 'Plateado', 'Índigo', 'Esmeralda', 'Rubí',
  'Aqua', 'Lima', 'Carmesí', 'Ámbar',
]

export function generarAliasAleatorio(nombreCompleto) {
  const primerNombre = (nombreCompleto || 'Alumno').trim().split(' ')[0] || 'Alumno'
  const animal = ANIMALES_ALIAS[Math.floor(Math.random() * ANIMALES_ALIAS.length)]
  const color = COLORES_ALIAS[Math.floor(Math.random() * COLORES_ALIAS.length)]
  return `${primerNombre} ${animal} ${color}`
}
