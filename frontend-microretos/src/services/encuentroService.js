import api from '../api.js';

// Seguimiento docente: encuentros con sus equipos y progreso (Mis equipos, diagnósticos)
export const getMisGrupos = () => api.get('/encuentros/mis-grupos');

// Encuentros registrados (biblioteca de encuentros)
export const getEncuentros = () => api.get('/encuentros');
export const actualizarEncuentro = (id, payload) => api.put(`/encuentros/${id}`, payload);
export const crearCodigoAcceso = (id) => api.post(`/encuentros/${id}/crear-codigo`);
export const crearCodigoIa = (id) => api.post(`/encuentros/${id}/codigo-ia`);

// Detalle de grupos de un encuentro (seguimiento docente del trabajo de cada grupo)
export const getWorkspaceEncuentro = (id) => api.get(`/encuentros/${id}/workspace`);
