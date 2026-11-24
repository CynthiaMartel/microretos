import api from '../api.js';

// DuaLab Central → Empresas → «Enviar soluciones a las empresas» (ValoracionEmpresaCentralController):
// DuaLab envía a la empresa el enlace para valorar las soluciones de los grupos de un encuentro.
export const getValoracionesCentral = () => api.get('/central/valoraciones-empresa');
export const generarEnlaceValoracion = (encuentroId) => api.post(`/central/valoraciones-empresa/${encuentroId}`);
export const enviarValoracionEmpresa = (encuentroId, payload) => api.post(`/central/valoraciones-empresa/${encuentroId}/enviar`, payload);
export const marcarValoracionEnviada = (encuentroId) => api.patch(`/central/valoraciones-empresa/${encuentroId}/enviada`);
