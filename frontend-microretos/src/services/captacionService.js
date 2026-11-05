import api from '../api.js'

// Revisión de lo captado por los comerciales (solo superadmin): apartado «Captación» de Notificaciones.

export const getResumenCaptacion = () => api.get('/captacion/resumen')

// estado: 'pendiente_centro' (por defecto) | 'asignada' | 'borrador'
export const getEmpresasCaptadas = (estado = 'pendiente_centro') => api.get('/captacion/empresas', { params: { estado } })
export const asignarCentrosEmpresa = (id, centroIds) => api.put(`/captacion/empresas/${id}/centros`, { centro_ids: centroIds })

export const getBorradoresPendientes = () => api.get('/captacion/borradores')
export const getBorradorCaptacion = (uuid) => api.get(`/captacion/borradores/${uuid}`)
export const publicarBorrador = (uuid, centroIds) => api.post(`/captacion/borradores/${uuid}/publicar`, { centro_ids: centroIds })
export const descartarBorrador = (uuid) => api.delete(`/captacion/borradores/${uuid}`)

// PNG de la firma (disco privado): se pide como blob para mostrarlo con un object URL
export const getImagenFirma = (uuid) => api.get(`/captacion/firmas/${uuid}/imagen`, { responseType: 'blob' })

// Centros para colocar la empresa: [{ id, nombre, img, ciclos }]
export const getCentrosCaptacion = () => api.get('/centros')
