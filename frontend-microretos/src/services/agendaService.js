import api from '../api.js';

// Agenda de prácticas: cronogramas de planes con el avance de cada grupo (docente: los suyos; Central: todos)
export const getAgendaPracticas = () => api.get('/agenda-practicas');

// Horas de prácticas (A3): las anotan el docente y DuaLab; el alumnado solo las ve en su workspace
export const getHoras = (cronogramaId) => api.get(`/agenda-practicas/${cronogramaId}/horas`);
export const anotarHoras = (cronogramaId, payload) => api.post(`/agenda-practicas/${cronogramaId}/horas`, payload);
export const borrarHoras = (registroId) => api.delete(`/agenda-practicas/horas/${registroId}`);
