import api from '../api.js';

// Participaciones del alumnado (una por miembro de grupo) en los encuentros visibles
// para el docente — vista Listado de alumnado
export const getParticipacionesAlumnado = () => api.get('/alumnado');
