import api from '../api.js';

// Seguimiento docente: encuentros con sus equipos y progreso (Mis equipos, diagnósticos)
export const getMisGrupos = () => api.get('/encuentros/mis-grupos');
