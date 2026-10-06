import api from '../api.js';

// Datos académicos (catálogo BOE): familias, ciclos, módulos y RA/CE.
// Devuelven la respuesta de axios tal cual (res.data), igual que las llamadas directas.

export const getFamilias = () => api.get('/familias');

// centro: nombre del centro educativo para filtrar los ciclos que imparte (opcional)
export const getCiclosDeFamilia = (familia, centro = null) =>
  api.get(`/familias/${encodeURIComponent(familia)}/ciclos`, { params: centro ? { centro } : {} });

export const getModulosDeCiclo = (cicloId) => api.get(`/ciclos/${cicloId}/modulos`);

// RA de un módulo con sus CE: { ra: [{ id, orden, descripcion, criterios: [{ id, orden, descripcion }] }] }
export const getRaCeModulo = (moduloId) => api.get(`/modulos/${moduloId}/ra-ce`);
