import api from '../api.js';

// DuaLab Central → Historial: qué se hizo, quién y cuándo (superadmin y Admin Central DuaLab)
export const getHistorial = (params) => api.get('/central/historial', { params });

// Secciones del historial (ListarActividadCentralRequest::SECCIONES)
export const SECCIONES_HISTORIAL = [
  { valor: 'captaciones', texto: 'Captaciones' },
  { valor: 'solicitudes', texto: 'Solicitudes' },
  { valor: 'retos',       texto: 'Retos' },
  { valor: 'usuarios',    texto: 'Usuarios' },
  { valor: 'papelera',    texto: 'Papelera' },
];
