import api from '../api.js';

// Retos ajustados (tabla microproyectos, /startup/proyectos). El docente lo marca como completado
// desde el encuentro cuando los grupos han entregado (el PUT comprueba la Policy).
export const marcarRetoAjustadoCompletado = (uuid) => api.put(`/startup/proyectos/${uuid}`, { estado: 'completado' });
