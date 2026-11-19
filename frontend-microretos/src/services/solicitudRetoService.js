import api from '../api.js';

// Solicitudes de retos de docentes (SolicitudRetoService en el backend).

// ── Formulario público del docente (/solicitud-reto/:token, sin cuenta) ──
export const getSolicitudPublica = (token) => api.get(`/public/solicitud-reto/${token}`);
export const guardarSolicitud = (token, payload) => api.put(`/public/solicitud-reto/${token}`, payload);
export const enviarSolicitud = (token, payload) => api.post(`/public/solicitud-reto/${token}/enviar`, payload);
export const getRetosSolicitud = (token) => api.get(`/public/solicitud-reto/${token}/retos`);

// ── DuaLab Central → Red DuaLab → Primer contacto (red_dualab, central_dualab y superadmin) ──
export const listarSolicitudes = (params) => api.get('/central/solicitudes-reto', { params });
export const crearSolicitud = (payload) => api.post('/central/solicitudes-reto', payload);
export const getSolicitud = (id) => api.get(`/central/solicitudes-reto/${id}`);
export const actualizarSolicitud = (id, payload) => api.patch(`/central/solicitudes-reto/${id}`, payload);
export const reenviarSolicitud = (id) => api.post(`/central/solicitudes-reto/${id}/reenviar`);
export const avisarDocente = (id, payload) => api.post(`/central/solicitudes-reto/${id}/avisar`, payload);
// A la papelera (solo superadmin); se restaura en Red DuaLab → Papelera
export const eliminarSolicitud = (id) => api.delete(`/central/solicitudes-reto/${id}`);
// Respuestas de los docentes en info@ (por la referencia «Ref. SR-…» del asunto)
export const getRespuestasSolicitudes = (params) => api.get('/central/solicitudes-reto/correos', { params });
export const getCorreosSolicitud = (id) => api.get(`/central/solicitudes-reto/${id}/correos`);
// Superadmin y Admin Central DuaLab: «generación rápida» de una petición y publicar retos en la biblioteca
export const getGeneracion = (id, bloque) => api.get(`/central/solicitudes-reto/${id}/generacion`, { params: { bloque } });
export const publicarRetosSolicitud = (id, payload) => api.post(`/central/solicitudes-reto/${id}/publicar`, payload);
// Superadmin y Admin Central DuaLab: una petición (bloque) con los nombres del estado del generador
export const getDatosGenerador = (id, bloque = 0) => api.get(`/central/solicitudes-reto/${id}/generador`, { params: { bloque } });
// Superadmin y Admin Central DuaLab: empresas con la familia de una petición (para elegir la de cada una)
export const getEmpresasPeticion = (id, bloque, todas = false) => api.get(`/central/solicitudes-reto/${id}/empresas`, { params: { bloque, ...(todas && { todas: 1 }) } });
// Superadmin y Admin Central DuaLab: solicitudes respondidas con sus peticiones (desplegable del generador)
export const getSolicitudesParaGenerador = () => api.get('/central/solicitudes-reto/para-generador');

// Opciones: mismas claves que EnviarSolicitudRetoRequest
export const NIVELES = [
  { valor: 'Bajo',  texto: 'Básico', ayuda: 'Nivel inicial: reto sencillo y guiado' },
  { valor: 'Medio', texto: 'Medio',  ayuda: 'Manejan los conceptos con soltura' },
  { valor: 'Alto',  texto: 'Alto',   ayuda: 'Dominan la materia: reto exigente y abierto' },
];
export const PUNTOS_TEMARIO = [
  { valor: 'primera_vez', texto: 'Lo ven por primera vez' },
  { valor: 'en_curso',    texto: 'Lo están trabajando ahora' },
  { valor: 'repaso',      texto: 'Ya lo han visto (repaso o ampliación)' },
];
