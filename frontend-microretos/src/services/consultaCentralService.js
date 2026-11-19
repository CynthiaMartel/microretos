import api from '../api.js';

// DuaLab Central → Red DuaLab → Empresas y Centros: consulta de solo lectura (ConsultaCentralController)
export const getEmpresasCentral = (params) => api.get('/central/empresas', { params });
export const getCentrosCentral = (params) => api.get('/central/centros', { params });
