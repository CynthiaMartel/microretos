import api from '../api.js';

// Generación IA y guardado de microretos (GeneradorMicroretos.vue).
export const generarMicroretos = (payload) => api.post('/generar-microreto', payload);
export const simularInfoEmpresa = (payload) => api.post('/simular-info-empresa', payload);
export const guardarMicroreto = (reto) => api.post('/guardar-microreto-bd', reto);
// solicitudRetoId: retos de una solicitud de docente (solo superadmin): se enlazan y van a la recámara
export const guardarMicroretosLote = (microretos, solicitudRetoId = null) =>
  api.post('/guardar-microretos-lote', { microretos, ...(solicitudRetoId && { solicitud_reto_id: solicitudRetoId }) });
