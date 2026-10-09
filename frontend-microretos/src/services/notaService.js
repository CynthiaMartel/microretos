import api from '../api.js';

// Notas personales del docente (vista Calendario); `fecha` opcional (YYYY-MM-DD)
export const getNotas = () => api.get('/notas');
export const crearNota = (payload) => api.post('/notas', payload);
export const actualizarNota = (id, payload) => api.patch(`/notas/${id}`, payload);
export const borrarNota = (id) => api.delete(`/notas/${id}`);
