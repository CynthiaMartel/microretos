import api from '../api.js'

// Entrevista por correo: la empresa la completa sin cuenta desde el enlace que le envía el
// comercial (app comercial, /formulario/:token): sus datos, las preguntas y la firma.
// Errores: 404 enlace no válido, 410 ya no admite respuestas ({ error, motivo }).
// Cada guardado lleva { aceptaPrivacidad, privacidadVersion } (aceptada al empezar).

const sinRedireccion = { skipAuthRedirect: true }

// { empresa, expira_at, privacidad, textos_legales }
export const getFormularioEmpresa = (token) =>
  api.get(`/public/formulario-empresa/${token}`, sinRedireccion)

// Paso «Empresa» (mismos campos camelCase que el alta del comercial) → mismos datos actualizados
export const guardarEmpresaFormulario = (token, payload) =>
  api.put(`/public/formulario-empresa/${token}/empresa`, payload, sinRedireccion)

// Respuestas a las preguntas, parciales (camelCase, como el diagnóstico)
export const guardarRespuestasFormulario = (token, payload) =>
  api.put(`/public/formulario-empresa/${token}`, payload, sinRedireccion)

// Paso «Firma» (mismo payload que la firma en la tablet)
export const firmarFormularioEmpresa = (token, payload) =>
  api.post(`/public/formulario-empresa/${token}/firma`, payload, sinRedireccion)
