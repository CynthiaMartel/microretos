// App comercial (build aparte, ver vite.comercial.config.js). En local corre en
// su propio puerto; VITE_COMERCIAL_URL permite cambiarla sin tocar código (es pública, no un secreto).
const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'

export const URL_APP_COMERCIAL = import.meta.env.VITE_COMERCIAL_URL
  || (isLocal ? 'http://localhost:5177' : 'https://comercial.dualab.es')
