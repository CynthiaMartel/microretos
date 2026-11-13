import axios from 'axios';

// El código detecta automáticamente dónde está abierto el navegador
const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

// Si está en tu Mac, busca en local. Si está en internet, dispara al subdominio.
const apiURL = isLocal ? '/api' : 'https://api.dualab.es/api';

// Misma base que apiURL pero sin el prefijo /api — /sanctum/csrf-cookie vive en el
// grupo de rutas 'web', no en 'api'. En local, vite.config.js proxya /sanctum igual que /api.
const rootURL = isLocal ? '' : 'https://api.dualab.es';

// La app comercial y Central tienen su propia sesión en el backend (SesionPorHerramienta.php)
// y su propia cookie del token CSRF; la app DuaLab sigue con XSRF-TOKEN. __HERRAMIENTA__ lo
// define vite.comercial.config.js / vite.central.config.js.
const herramienta = typeof __HERRAMIENTA__ === 'undefined' ? null : __HERRAMIENTA__;

const api = axios.create({
  baseURL: apiURL,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: herramienta ? `XSRF-TOKEN-${herramienta}` : 'XSRF-TOKEN',
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
  async error => {
    // 419 = la cookie XSRF-TOKEN ya no corresponde a la sesión. Pasa cuando otra app de
    // *.dualab.es con Sanctum (la gestión web de dualab.es) planta su propia XSRF-TOKEN en el
    // mismo dominio. Se pide una nueva y se repite la petición una sola vez.
    if (error.response?.status === 419 && error.config && !error.config._reintentoCsrf) {
      error.config._reintentoCsrf = true;
      await primeCsrfCookie();
      return api(error.config);
    }
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