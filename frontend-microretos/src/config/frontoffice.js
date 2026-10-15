// Enlaces a la web pública (FRONTOFFICE/dualab_proyecto). VITE_* es público: solo URLs.
// En producción la web pública vive en la raíz de dualab.es: el .htaccess del servidor
// manda sus rutas (/, /contacto…) al frontoffice y el resto a esta app.
const FRONTOFFICE_URL = (import.meta.env.VITE_FRONTOFFICE_URL || 'https://dualab.es').replace(/\/$/, '')

export const WEB_PUBLICA     = `${FRONTOFFICE_URL}/`
// Formulario de alta del frontoffice: centro, empresa, alumnado o administración, con
// la acción "Registrarme como…" (POST /contacto en su backend)
export const SOLICITAR_REGISTRO = `${FRONTOFFICE_URL}/contacto`
