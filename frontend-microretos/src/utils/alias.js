// Protección de datos: el alumnado solo existe por su alias "NombreDePila Animal Color"
// ("Adrián Águila Amarillo") — nunca se guardan apellidos. El nombre de pila es una sola
// palabra (compuestos con guion: "María-José") y el backend (App\Support\AliasGenerator)
// solo acepta animal + color de estas listas. Estilo Kahoot: el dado cambia animal y color
// al azar en el momento, sin ir al backend. Si se cambia una lista o el formato aquí,
// cambiar también AliasGenerator para que el backend reconozca el alias.
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

const norm = (t) => String(t ?? '').trim().toLowerCase()
const azar = (lista) => lista[Math.floor(Math.random() * lista.length)]

// Una palabra de letras, con guion o apóstrofo internos
const PATRON_NOMBRE = /^\p{L}+(?:['-]\p{L}+)*$/u

// Valida lo que escribe el docente/alumnado: una sola palabra, sin apellidos.
// Devuelve el mensaje de error o '' si es válido.
export function errorNombrePila(texto) {
  const t = String(texto ?? '').trim()
  if (!t) return 'Escribe el nombre de pila.'
  if (/\s/.test(t)) return 'Solo el nombre de pila, sin apellidos (los compuestos con guion: María-José).'
  if (!PATRON_NOMBRE.test(t) || t.length > 30) return 'El nombre de pila solo puede llevar letras (y guion en los compuestos).'
  return ''
}

// "adrián" → "Adrián", "maría-josé" → "María-José"
export function formatearNombrePila(texto) {
  return String(texto ?? '').trim().toLowerCase()
    .replace(/(^|[-'])(\p{L})/gu, (_, sep, letra) => sep + letra.toUpperCase())
}

// Nombre de pila de un alias "Nombre Animal Color" ('' si es un alias antiguo sin nombre)
export function nombreDeAlias(alias) {
  const partes = String(alias ?? '').trim().split(/\s+/)
  return partes.length >= 3 ? partes[0] : ''
}

// Alias con ese nombre de pila y un animal + color al azar que no esté en `usados`
// (los de la clase), para que no se repitan.
export function generarAliasAleatorio(nombrePila, usados = []) {
  const nombre = formatearNombrePila(nombrePila) || 'Alumno'
  const ocupados = new Set([...usados].map(norm))
  const libres = ANIMALES_ALIAS.flatMap(a => COLORES_ALIAS.map(c => `${nombre} ${a} ${c}`)).filter(x => !ocupados.has(norm(x)))
  return libres.length ? azar(libres) : `${nombre} ${azar(ANIMALES_ALIAS)} ${azar(COLORES_ALIAS)}`
}
