import api from '../api.js';

// Workspace del grupo en solo lectura (vista del docente: por id y con su sesión, sin token)
export const getWorkspaceDocente = (equipoId) => api.get(`/startup/equipos/${equipoId}/workspace`);

// Evaluación y diagnóstico final de un grupo (equipo) por el docente — Detalle de grupos
export const evaluarEquipo = (equipoId, payload) => api.patch(`/startup/equipos/${equipoId}/evaluar`, payload);
export const generarDiagnosticoFinal = (equipoId) => api.post(`/startup/equipos/${equipoId}/diagnostico-final`);
export const guardarDiagnosticoFinal = (equipoId, payload) => api.patch(`/startup/equipos/${equipoId}/diagnostico-final`, payload);
