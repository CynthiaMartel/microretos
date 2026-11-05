import api from '../api.js'

// App comercial (comercial.dualab.es): empresas que capta el comercial.
// Devuelven la respuesta de axios tal cual; los recursos llegan en res.data.data.

export const getTextosLegales = () => api.get('/comercial/textos-legales')

export const getMisEmpresas = () => api.get('/comercial/empresas')
export const getEmpresaCaptada = (id) => api.get(`/comercial/empresas/${id}`)
export const crearEmpresaCaptada = (payload) => api.post('/comercial/empresas', payload)
export const actualizarEmpresaCaptada = (id, payload) => api.put(`/comercial/empresas/${id}`, payload)
export const guardarDiagnosticoCaptada = (id, payload) => api.patch(`/comercial/empresas/${id}/diagnostico`, payload)

// { familia, ciclo_id, cursoSeleccionado, nivelGrupo, cantidad, enfoqueReto? } → borradores
export const generarRetosCaptada = (id, payload) => api.post(`/comercial/empresas/${id}/generar-retos`, payload)
export const getBorradoresCaptada = (id) => api.get(`/comercial/empresas/${id}/borradores`)

export const firmarEmpresaCaptada = (id, payload) => api.post(`/comercial/empresas/${id}/firma`, payload)
