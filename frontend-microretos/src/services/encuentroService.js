import api from '../api.js';

// Seguimiento docente: encuentros con sus equipos y progreso (Mis equipos, diagnósticos)
export const getMisGrupos = () => api.get('/encuentros/mis-grupos');

// Encuentros registrados (biblioteca de encuentros)
export const getEncuentros = () => api.get('/encuentros');
export const actualizarEncuentro = (id, payload) => api.put(`/encuentros/${id}`, payload);
export const crearCodigoAcceso = (id) => api.post(`/encuentros/${id}/crear-codigo`);
export const crearCodigoIa = (id) => api.post(`/encuentros/${id}/codigo-ia`);

// Valoración final de la empresa (P3): estado, pedirla (genera/renueva el enlace) y marcarla enviada
// Valoración de la empresa: el docente solo la consulta; la envía DuaLab desde Central
export const getValoracionEmpresa = (id) => api.get(`/encuentros/${id}/valoracion-empresa`);

// Detalle de grupos de un encuentro (seguimiento docente del trabajo de cada grupo)
export const getWorkspaceEncuentro = (id) => api.get(`/encuentros/${id}/workspace`);
