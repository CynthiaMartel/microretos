import api from '../api.js'

// Revisión de las captaciones (solo superadmin): apartado «Captación» de Notificaciones.
// Una captación (entrevista del comercial o formulario web) no es una empresa de Base de datos
// hasta que se valida.

export const getResumenCaptacion = () => api.get('/captacion/resumen')

// estado: 'pendiente_validacion' (por defecto) | 'validada' | 'descartada' | 'borrador'
export const getEmpresasCaptadas = (estado = 'pendiente_validacion') => api.get('/captacion/empresas', { params: { estado } })
// Crea la empresa en Base de datos y la coloca en esos centros (si ya estaba validada, solo cambia los centros).
// empresaExistenteId: es la misma que una empresa real de Base de datos (se fusiona, no se duplica)
export const validarEmpresaCaptada = (id, centroIds, empresaExistenteId = null) =>
  api.put(`/captacion/empresas/${id}/validar`, { centro_ids: centroIds, empresa_existente_id: empresaExistenteId })
export const descartarEmpresaCaptada = (id, motivo) => api.post(`/captacion/empresas/${id}/descartar`, { motivo: motivo || null })
// Posibles duplicados en Base de datos (mismo CIF o nombre parecido), o búsqueda libre con q
export const getCoincidenciasEmpresa = (id, q = '') => api.get(`/captacion/empresas/${id}/coincidencias`, { params: q ? { q } : {} })

// Centros educativos captados (entrevista del comercial o formulario web)
// estado: 'pendiente_validacion' (por defecto) | 'validada' | 'descartada'
export const getCentrosCaptados = (estado = 'pendiente_validacion') => api.get('/captacion/centros', { params: { estado } })
// centroExistenteId: el centro ya está en DuaLab (se enlaza); null → se da de alta
export const validarCentroCaptado = (id, centroExistenteId = null) => api.put(`/captacion/centros/${id}/validar`, { centro_existente_id: centroExistenteId })
export const descartarCentroCaptado = (id, motivo) => api.post(`/captacion/centros/${id}/descartar`, { motivo: motivo || null })
export const getCoincidenciasCentro = (id, q = '') => api.get(`/captacion/centros/${id}/coincidencias`, { params: q ? { q } : {} })

// enCurso: retos de empresas que la comercial aún está entrevistando
export const getBorradoresPendientes = (enCurso = false) => api.get('/captacion/borradores', { params: enCurso ? { en_curso: 1 } : {} })
export const getBorradorCaptacion = (uuid) => api.get(`/captacion/borradores/${uuid}`)
export const publicarBorrador = (uuid, centroIds) => api.post(`/captacion/borradores/${uuid}/publicar`, { centro_ids: centroIds })
export const descartarBorrador = (uuid) => api.delete(`/captacion/borradores/${uuid}`)

// PNG de la firma (disco privado): se pide como blob para mostrarlo con un object URL
export const getImagenFirma = (uuid) => api.get(`/captacion/firmas/${uuid}/imagen`, { responseType: 'blob' })

// Centros para colocar la empresa: [{ id, nombre, img, ciclos }]
export const getCentrosCaptacion = () => api.get('/centros')

// A la papelera (superadmin y Admin Central DuaLab; restaurar o borrar del todo, en la Papelera)
export const eliminarEmpresaCaptada = (id) => api.delete(`/captacion/empresas/${id}`)
export const eliminarCentroCaptado = (id) => api.delete(`/captacion/centros/${id}`)
export const eliminarBorrador = (uuid) => api.delete(`/captacion/borradores/${uuid}/papelera`)
