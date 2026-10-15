import api, { primeCsrfCookie } from '../api.js'

// "Contacta con soporte" — ruta pública; Sanctum SPA necesita la cookie XSRF-TOKEN antes del POST
export const enviarSoporte = async (payload) => {
  await primeCsrfCookie()
  return api.post('/soporte', payload)
}
