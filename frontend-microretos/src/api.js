import axios from 'axios';

// El código detecta automáticamente dónde está abierto el navegador
const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

// Si está en tu Mac, busca en local. Si está en internet, dispara al subdominio.
const apiURL = isLocal ? '/api' : 'https://api.dualab.es/api';

// Misma base que apiURL pero sin el prefijo /api — /sanctum/csrf-cookie vive en el
// grupo de rutas 'web', no en 'api'. En local, vite.config.js proxya /sanctum igual que /api.
const rootURL = isLocal ? '' : 'https://api.dualab.es';

const api = axios.create({
  baseURL: apiURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
});

// Si el servidor responde 401, la sesión (cookie) ha caducado o no existe.
// Excepción: peticiones marcadas con { skipAuthRedirect: true } (ej. verificar un código/
// contraseña de módulo) — ahí el 401 es "código incorrecto", no una sesión caducada, y no
// debe cerrar la sesión del usuario ni disparar una redirección a login.
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401 && !error.config?.skipAuthRedirect) {
      // Notificar a los componentes que escuchen este evento
      window.dispatchEvent(new CustomEvent('auth:token-expired'));
    }
    return Promise.reject(error);
  }
);

// Sanctum SPA stateful: antes de login hay que tener la cookie XSRF-TOKEN plantada
// (axios la adjunta sola como header X-XSRF-TOKEN en cada request gracias a withXSRFToken).
export const primeCsrfCookie = () => axios.get(`${rootURL}/sanctum/csrf-cookie`, { withCredentials: true });

export default api;