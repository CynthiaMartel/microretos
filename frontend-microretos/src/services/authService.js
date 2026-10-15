import api, { primeCsrfCookie } from '../api.js'

// Sanctum SPA stateful: la cookie XSRF-TOKEN tiene que existir antes de cada POST público
export const login = async (payload) => {
  await primeCsrfCookie()
  return api.post('/admin/login', payload)
}

export const solicitarEnlaceContrasena = async (email) => {
  await primeCsrfCookie()
  return api.post('/password/forgot', { email })
}

export const restablecerContrasena = async (payload) => {
  await primeCsrfCookie()
  return api.post('/password/reset', payload)
}
