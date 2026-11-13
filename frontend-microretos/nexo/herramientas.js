// Herramientas que enlaza el Nexo. Cada una tiene su propio acceso: aquí solo están sus
// direcciones (públicas). En local apuntan a los servidores de desarrollo; se pueden cambiar
// con VITE_NEXO_URL_* en el .env de la raíz si algún puerto o dominio cambia.
const local = import.meta.env.DEV
const url = (variable, enLocal, enProduccion) => import.meta.env[variable] || (local ? enLocal : enProduccion)

export const HERRAMIENTAS = [
  {
    clave: 'app',
    nombre: 'App DuaLab',
    para: 'Docentes, alumnado, empresas y admin de centro',
    texto: 'Retos, encuentros, grupos y el día a día de los centros.',
    color: 'centros',
    url: url('VITE_NEXO_URL_APP', 'http://localhost:5173/login', 'https://dualab.es/login'),
  },
  {
    clave: 'web',
    nombre: 'Gestión web',
    para: 'Administración, edición y atención de contactos',
    texto: 'Páginas, noticias, medios, apariencia, SEO y formularios de dualab.es.',
    color: 'administraciones',
    url: url('VITE_NEXO_URL_WEB', 'http://localhost:5174/gestion-web', 'https://dualab.es/gestion-web'),
  },
  {
    clave: 'comercial',
    nombre: 'App comercial',
    para: 'Equipo comercial',
    texto: 'Entrevistas a empresas y centros, retos en directo y firma.',
    color: 'empresas',
    url: url('VITE_NEXO_URL_COMERCIAL', 'http://localhost:5177', 'https://comercial.dualab.es'),
  },
  {
    clave: 'central',
    nombre: 'DuaLab Central',
    para: 'Superadmin',
    texto: 'Usuarios, base de datos, validación de captaciones y soporte.',
    color: 'azul-noche',
    url: url('VITE_NEXO_URL_CENTRAL', 'http://localhost:5179', 'https://central.dualab.es'),
  },
]
