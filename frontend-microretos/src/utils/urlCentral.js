// DuaLab Central (build aparte, ver vite.central.config.js). En local corre en su propio puerto;
// VITE_CENTRAL_URL permite cambiarla sin tocar código (es pública, no un secreto).
const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'

export const URL_CENTRAL = import.meta.env.VITE_CENTRAL_URL
  || (isLocal ? 'http://localhost:5179' : 'https://central.dualab.es')
