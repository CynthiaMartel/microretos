// Familias en las que DuaLab tiene más retos y más experiencia: salen primero en los buscadores
// de familias de la app comercial (empresas y centros), marcadas como «Nuestro fuerte».
// Los nombres son los de la tabla `familias`.
export const FAMILIAS_PRIORITARIAS = [
  'Informática y Comunicaciones',
  'Comercio y Marketing',
  'Administración y Gestión',
]

export const esPrioritaria = (nombre) => FAMILIAS_PRIORITARIAS.includes(nombre)

// Lo que el comercial cuenta antes de preguntar qué familias le interesan (SelectorFamilias)
export const AVISO_OTRAS_FAMILIAS =
  'Si le interesan otras familias, estamos trabajando en ello para poder ofrecerle retos también en ellas.'
