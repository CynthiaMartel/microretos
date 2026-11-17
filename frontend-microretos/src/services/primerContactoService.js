import api from '../api.js';

// DuaLab Central → Primer contacto: formularios de la web (los trae el backend de la app por la API
// firmada de la web; solo superadmin)
export const getFormulariosWeb = (params = {}) => api.get('/central/primer-contacto/formularios', { params });
export const getFormularioWeb = (id) => api.get(`/central/primer-contacto/formularios/${id}`);
export const cambiarEstadoFormularioWeb = (id, estado) => api.patch(`/central/primer-contacto/formularios/${id}`, { estado });

// Correo de info@ (los correos y el envío siguen en la web; ver PrimerContactoCorreoController)
export const getCorreos = (params = {}) => api.get('/central/primer-contacto/correo', { params });
export const getCorreo = (id) => api.get(`/central/primer-contacto/correo/${id}`);
export const cambiarEstadoCorreo = (id, estado) => api.patch(`/central/primer-contacto/correo/${id}`, { estado });
export const descargarAdjuntoCorreo = (correoId, adjuntoId) =>
  api.get(`/central/primer-contacto/correo/${correoId}/adjuntos/${adjuntoId}`, { responseType: 'blob' });
// FormData: puede llevar adjuntos
export const enviarCorreo = (datos) => api.post('/central/primer-contacto/correo/enviar', datos);
export const reintentarEnvio = (id) => api.post(`/central/primer-contacto/correo/envios/${id}/reintentar`);
export const descartarEnvio = (id) => api.delete(`/central/primer-contacto/correo/envios/${id}`);
