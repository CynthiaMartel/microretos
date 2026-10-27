import api from '../api.js'

// Módulos que imparte un docente. Limitan a quién se puede invitar como colaborador de un
// encuentro (compañeros del centro que compartan familia y módulo con el proyecto).
// Respuesta: { asignados: [ids], oferta: [{ id, nombre, familia, modulos: [...] }] }
// + en_proyectos: [ids] (los que usa en sus proyectos, solo sugerencia) y num_proyectos.

export const getMisModulos = () => api.get('/perfil/modulos').then(r => r.data)

export const guardarMisModulos = (moduloIds) =>
  api.put('/perfil/modulos', { modulo_ids: moduloIds }).then(r => r.data)

export const getModulosDocente = (userId) => api.get(`/admin/usuarios/${userId}/modulos`).then(r => r.data)

export const guardarModulosDocente = (userId, moduloIds) =>
  api.put(`/admin/usuarios/${userId}/modulos`, { modulo_ids: moduloIds }).then(r => r.data)
