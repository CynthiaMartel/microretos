import api from '../api.js';

export const getDemos = () => api.get('/demos');
export const getDemoDeFamilia = (familia) => api.get(`/demos/${encodeURIComponent(familia)}`);
export const getMicroretosDemoDeFamilia = (familia) => api.get(`/demos/${encodeURIComponent(familia)}/microretos`);
