import api from '../api.js';

// Plan de proyecto (etapa 2). Listado y ficha reutilizan los de la propuesta (?etapa=plan).
export const getPlanes = () => api.get('/startup/proyectos', { params: { etapa: 'plan' } });
export const crearPlan = (payload) => api.post('/planes', payload);
// decision: 'validar' | 'desvalidar' (docente del plan, admin de su centro o superadmin)
export const validarPlan = (uuid, decision) => api.post(`/startup/proyectos/${uuid}/validar-docente`, { decision });
export const crearCronogramaPlan = (uuid, payload) => api.post(`/planes/${uuid}/cronograma`, payload);
// Cronogramas (encuentros de tipo plan) y encuentros creativos de donde sale el alumnado
export const getCronogramas = () => api.get('/encuentros', { params: { tipo: 'plan_practicas_etapa2' } });
export const getEncuentrosCreativos = () => api.get('/encuentros');
