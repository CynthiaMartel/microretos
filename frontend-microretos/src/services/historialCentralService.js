import api from '../api.js';

// DuaLab Central → Historial: qué se hizo, quién y cuándo (superadmin y Admin Central DuaLab)
export const getHistorial = (params) => api.get('/central/historial', { params });
// Qué se envió en un correo de info@ (se lee de la web en el momento)
export const getEnvioHistorial = (id) => api.get(`/central/historial/envios/${id}`);

// Secciones del historial (ListarActividadCentralRequest::SECCIONES)
export const SECCIONES_HISTORIAL = [
  { valor: 'captaciones', texto: 'Captaciones' },
  { valor: 'solicitudes', texto: 'Solicitudes' },
  { valor: 'retos',       texto: 'Retos' },
  { valor: 'usuarios',    texto: 'Usuarios' },
  { valor: 'papelera',    texto: 'Papelera' },
  { valor: 'correo',      texto: 'Correo' },
  { valor: 'empresas',    texto: 'Empresas' },
];
