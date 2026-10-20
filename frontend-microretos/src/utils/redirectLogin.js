// "Volver donde estabas" tras iniciar sesión, con caducidad.
//
// Cuando la app manda al acceso (sesión caducada, inactividad, ruta protegida sin
// sesión) guarda la ruta en ?redirect junto a la hora en ?rt. Si se inicia sesión
// dentro de REDIRECT_VIGENCIA_MIN se vuelve a esa ruta; pasado ese tiempo (p. ej. una
// pestaña que se quedó en el acceso desde ayer) se entra al panel del rol.

export const REDIRECT_VIGENCIA_MIN = 30

// Query para mandar al acceso recordando `path`
export function queryRedirect(path) {
  return { redirect: path, rt: String(Date.now()) }
}

// Ruta a la que volver tras el login, o null si no hay, no es interna o ha caducado.
// Sin ?rt (enlaces externos, p. ej. del frontoffice) se respeta: es una intención actual.
export function redirectVigente(query = {}) {
  const redirect = typeof query.redirect === 'string' ? query.redirect : ''
  // Solo rutas internas: un ?redirect=//otro-dominio.com sería un open redirect
  if (!redirect.startsWith('/') || redirect.startsWith('//')) return null
  if (query.rt === undefined) return redirect
  const edad = Date.now() - Number(query.rt)
  if (!Number.isFinite(edad) || edad < 0 || edad > REDIRECT_VIGENCIA_MIN * 60 * 1000) return null
  return redirect
}
