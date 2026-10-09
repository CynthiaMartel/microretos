import api from '../api.js';

// Tareas personales del docente (Tareas pendientes del panel docente y del Calendario)
export const getTareas = () => api.get('/tareas');
export const crearTarea = (payload) => api.post('/tareas', payload);
export const actualizarTarea = (id, payload) => api.patch(`/tareas/${id}`, payload);
export const borrarTarea = (id) => api.delete(`/tareas/${id}`);
