import api from '../api.js';

// DuaLab Central → Alumnado destacado para prácticas (solo superadmin)
export const getEncuentrosDestacados = (params = {}) => api.get('/central/destacados', { params });
export const marcarDestacado = (miembroId, destacado) => api.put(`/central/destacados/miembros/${miembroId}`, { destacado });
