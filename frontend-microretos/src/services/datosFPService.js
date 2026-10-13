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

// Currículo de un ciclo (módulos, RA y CE) desde CSV — superadmin. Con simular=true solo valida.
export const importarCurriculo = (cicloId, archivo, simular) => {
  const datos = new FormData();
  datos.append('archivo', archivo);
  datos.append('simular', simular ? '1' : '0');
  return api.post(`/ciclos/${cicloId}/curriculo/importar`, datos, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
};
