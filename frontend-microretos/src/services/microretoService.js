import api from '../api.js';

// Generación IA y guardado de microretos (GeneradorMicroretos.vue).
export const generarMicroretos = (payload) => api.post('/generar-microreto', payload);
export const simularInfoEmpresa = (payload) => api.post('/simular-info-empresa', payload);
export const guardarMicroreto = (reto) => api.post('/guardar-microreto-bd', reto);
export const guardarMicroretosLote = (microretos) => api.post('/guardar-microretos-lote', { microretos });
