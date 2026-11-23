import api from '../api.js';

// Valoración final de la empresa por enlace con token (vista pública, sin cuenta)
export const getValoracionPublica = (token) => api.get(`/valoracion-empresa/${token}`);
export const enviarValoracion = (token, payload) => api.post(`/valoracion-empresa/${token}`, payload);

// Preguntas: las claves y opciones deben coincidir con ValorarPropuestaEmpresaRequest::PREGUNTAS
export const PREGUNTAS_VALORACION = [
  { key: 'responde_necesidad',     label: '¿Las soluciones de los grupos responden a la necesidad de vuestra empresa?', opciones: ['Sí', 'Parcialmente', 'No'] },
  { key: 'aplicable',              label: '¿Alguna solución sería aplicable en vuestra empresa?',                       opciones: ['Sí', 'Parcialmente', 'No'] },
  { key: 'calidad',                label: '¿El trabajo del alumnado tiene la calidad que esperabais?',                  opciones: ['Sí', 'Parcialmente', 'No'] },
  { key: 'interes_plan_practicas', label: '¿Os interesaría desarrollar un proyecto a partir de este reto, con alumnado en prácticas en vuestra empresa?', opciones: ['Sí', 'Quizá', 'No'] },
];
