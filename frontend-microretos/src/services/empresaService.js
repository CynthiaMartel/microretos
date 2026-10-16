import api from '../api.js';

export const getEmpresas = () => api.get('/empresas');
export const getFamiliasDeEmpresa = (empresaId) => api.get(`/empresas/${empresaId}/familias`);
export const crearEmpresa = (payload) => api.post('/empresas', payload);
export const actualizarEmpresa = (empresaId, payload) => api.put(`/empresas/${empresaId}`, payload);
export const actualizarEstadoEmpresa = (empresaId, estadoContacto) =>
  api.patch(`/empresas/${empresaId}/estado`, { estadoContacto });
// Diagnóstico (P1–P4) desde el Generador: solo empresas ficticias (las reales → 403).
export const actualizarDiagnosticoEmpresa = (empresaId, payload) =>
  api.patch(`/empresas/${empresaId}/diagnostico`, payload);
// Empresa ficticia completa (datos + diagnóstico P1–P5) con IA, en dos pasos: la IA genera
// una propuesta (queda en el servidor, no se guarda) y el usuario la guarda o la descarta.
export const proponerEmpresaFicticiaIA = (payload) => api.post('/empresas/ficticia-ia/propuesta', payload);
// Familias elegibles para crear una empresa ficticia con IA en un destino (centro o catálogo):
// solo las que el centro tiene asociadas y con módulos, RA y CE. `centro`/`catalogo`: superadmin.
export const getFamiliasFicticiaIA = ({ centro = null, catalogo = false } = {}) =>
  api.get('/empresas/ficticia-ia/familias', { params: {
    ...(centro ? { centro } : {}),
    ...(catalogo ? { catalogo: 1 } : {}),
  } });
// diagnostico (opcional): P1–P5 retocados antes de guardar ({ diaANormal, friccionArea, ... }).
export const guardarEmpresaFicticiaIA = (token, diagnostico = {}) => api.post('/empresas/ficticia-ia', { token, ...diagnostico });
export const verPropuestaFicticiaIA = (token) => api.get(`/empresas/ficticia-ia/propuesta/${token}`);
export const descartarEmpresaFicticiaIA = (token) => api.delete(`/empresas/ficticia-ia/propuesta/${token}`);
// Catálogo DuaLab de empresas ficticias (T2): plantillas de solo lectura y copia al centro.
// `centro` solo lo tiene en cuenta el backend para superadmin; docente/admin usan siempre el suyo.
export const getCatalogoEmpresas = (familiaId = null, centro = null) =>
  api.get('/empresas/catalogo', { params: {
    ...(familiaId ? { familia_id: familiaId } : {}),
    ...(centro ? { centro } : {}),
  } });
export const usarEmpresaCatalogo = (empresaId, payload = {}) =>
  api.post(`/empresas/catalogo/${empresaId}/usar`, payload);
// Interés de la empresa en una familia vinculada (paso 3 del Generador)
export const actualizarInteresEmpresa = (empresaId, familia, interes) => api.patch(`/empresas/${empresaId}/interes`, { familia, interes });
