import api from '../api.js';

// Avisos in-app del docente (empresa responde, equipo completa fase, validación, invitaciones)
export const getNotificaciones = (params = {}) => api.get('/notificaciones', { params });
export const getContadorNoLeidas = () => api.get('/notificaciones/no-leidas');
export const marcarNotificacionLeida = (id) => api.patch(`/notificaciones/${id}/leida`);
export const marcarTodasLeidas = () => api.post('/notificaciones/leer-todas');
