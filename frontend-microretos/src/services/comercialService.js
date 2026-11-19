import api from '../api.js'

// App comercial (comercial.dualab.es): empresas que capta el comercial.
// Devuelven la respuesta de axios tal cual; los recursos llegan en res.data.data.

export const getTextosLegales = () => api.get('/comercial/textos-legales')

export const getMisEmpresas = () => api.get('/comercial/empresas')
export const getEmpresaCaptada = (id) => api.get(`/comercial/empresas/${id}`)
export const crearEmpresaCaptada = (payload) => api.post('/comercial/empresas', payload)
export const actualizarEmpresaCaptada = (id, payload) => api.put(`/comercial/empresas/${id}`, payload)
export const guardarDiagnosticoCaptada = (id, payload) => api.patch(`/comercial/empresas/${id}/diagnostico`, payload)

// Ideas de la IA para las preguntas de la entrevista: lo ya contado (aún sin guardar) →
// { diaANormal: { preguntas, ejemplos }, consecuencias: { preguntas, ejemplos, opciones }, … }
export const sugerirRespuestasCaptada = (id, respuestas) => api.post(`/comercial/empresas/${id}/sugerencias`, respuestas)
// Dictado de pago: FormData con 'audio' → { texto }
export const transcribirAudio = (formData) => api.post('/comercial/transcribir', formData, {
  headers: { 'Content-Type': 'multipart/form-data' },
})

// Formulario por correo: { email } → { data: empresa, enlace } (el enlace solo llega aquí)
export const enviarFormularioCaptada = (id, email) => api.post(`/comercial/empresas/${id}/formulario`, { email })
// Entrevista nueva por correo, solo con el email de la empresa → { data: empresa, enlace }
export const crearFormularioCaptada = (email) => api.post('/comercial/formularios', { email })
export const anularFormularioCaptada = (id) => api.delete(`/comercial/empresas/${id}/formulario`)

// { familia, ciclo_id, cursoSeleccionado, nivelGrupo, cantidad, enfoqueReto? } → borradores
export const generarRetosCaptada = (id, payload) => api.post(`/comercial/empresas/${id}/generar-retos`, payload)
export const getBorradoresCaptada = (id) => api.get(`/comercial/empresas/${id}/borradores`)

export const firmarEmpresaCaptada = (id, payload) => api.post(`/comercial/empresas/${id}/firma`, payload)

// Papelera: solo entrevistas en curso; moverlas pide la contraseña (la comprueba el backend).
// Borrarlas del todo es de la Papelera de Central (superadmin).
export const borrarEmpresaCaptada = (id, password) => api.delete(`/comercial/empresas/${id}`, { data: { password } })
export const getPapeleraCaptadas = () => api.get('/comercial/papelera')
export const restaurarEmpresaCaptada = (id) => api.patch(`/comercial/papelera/${id}/restaurar`)

// Entrevista a centros educativos (sin firma: aceptación de protección de datos y cesiones)
export const getTextosLegalesCentros = () => api.get('/comercial/centros/textos-legales')
export const getMisCentros = () => api.get('/comercial/centros')
export const crearCentroCaptado = (payload) => api.post('/comercial/centros', payload)
