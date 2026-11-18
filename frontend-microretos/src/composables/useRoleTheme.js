import { computed, ref } from 'vue'
import { ROLE_SUPERADMIN, ROLE_ADMIN, ROLE_DOCENTE, ROLE_EMPRESA, ROLE_COMERCIAL, ROLE_ATENCION_CLIENTE, useAuthStore } from '../stores/auth.js'

// Color de marca por rol de quien ha iniciado sesión — mismo criterio que el
// frontoffice (ver logo_colores.png): docente/centro=azul, empresa=verde,
// admin/superadmin=turquesa (administraciones). El alumnado no inicia sesión
// aquí (accede por token a EquipoWorkspace/PublicMicroreto, coloreados en
// naranja de forma estática en esas vistas, sin pasar por este composable).
//
// Los campos "*Dark" son para texto/iconos sobre fondos oscuros (el gris
// #1F2937 del TopBar/SidePanel y de muchas cabeceras de modal): el azul plano
// de "centros" solo da ~2.9:1 de contraste ahí (falla incluso el umbral WCAG
// de texto grande/negrita), así que docente y el tema por defecto usan un
// azul aclarado (--color-centros-light en style.css) en vez del azul de marca
// tal cual. Empresa y administración ya leen bien sobre oscuro sin aclarar.
// Los campos normales (sin "Dark") están pensados para fondo claro/blanco.
//
// Los valores de clase son literales a propósito (nunca `` `bg-${key}` ``):
// Tailwind v4 escanea el texto fuente para saber qué utilidades generar, así
// que una clase construida en tiempo de ejecución no se generaría. Cualquier
// variante de opacidad nueva que haga falta en un componente se añade aquí
// como campo literal, no se compone al vuelo.
const THEME_BY_ROLE = {
  [ROLE_DOCENTE]: {
    key: 'centros',
    text: 'text-centros',
    text50: 'text-centros/50',
    textDark: 'text-centros-light',
    text50Dark: 'text-centros-light/50',
    bg: 'bg-centros',
    bgHover: 'hover:bg-centros/90',
    bgSoft: 'bg-centros/15',
    bg5: 'bg-centros/5',
    border: 'border-centros',
    border15: 'border-centros/15',
    border20: 'border-centros/20',
    ring: 'ring-centros',
    hex: '#3072AA', // para v-bind() en bloques <style> que no pueden usar clases Tailwind
    hexDark: '#6BA4D5',
    hexSoft: 'rgba(48,114,170,0.15)',
  },
  [ROLE_EMPRESA]: {
    key: 'empresas',
    text: 'text-empresas',
    text50: 'text-empresas/50',
    textDark: 'text-empresas',
    text50Dark: 'text-empresas/50',
    bg: 'bg-empresas',
    bgHover: 'hover:bg-empresas/90',
    bgSoft: 'bg-empresas/15',
    bg5: 'bg-empresas/5',
    border: 'border-empresas',
    border15: 'border-empresas/15',
    border20: 'border-empresas/20',
    ring: 'ring-empresas',
    hex: '#509928',
    hexDark: '#509928',
    hexSoft: 'rgba(80,153,40,0.15)',
  },
  [ROLE_ADMIN]: {
    key: 'administraciones',
    text: 'text-administraciones',
    text50: 'text-administraciones/50',
    textDark: 'text-administraciones',
    text50Dark: 'text-administraciones/50',
    bg: 'bg-administraciones',
    bgHover: 'hover:bg-administraciones/90',
    bgSoft: 'bg-administraciones/15',
    bg5: 'bg-administraciones/5',
    border: 'border-administraciones',
    border15: 'border-administraciones/15',
    border20: 'border-administraciones/20',
    ring: 'ring-administraciones',
    hex: '#19A7A8',
    hexDark: '#19A7A8',
    hexSoft: 'rgba(25,167,168,0.15)',
  },
  [ROLE_SUPERADMIN]: {
    key: 'administraciones',
    text: 'text-administraciones',
    text50: 'text-administraciones/50',
    textDark: 'text-administraciones',
    text50Dark: 'text-administraciones/50',
    bg: 'bg-administraciones',
    bgHover: 'hover:bg-administraciones/90',
    bgSoft: 'bg-administraciones/15',
    bg5: 'bg-administraciones/5',
    border: 'border-administraciones',
    border15: 'border-administraciones/15',
    border20: 'border-administraciones/20',
    ring: 'ring-administraciones',
    hex: '#19A7A8',
    hexDark: '#19A7A8',
    hexSoft: 'rgba(25,167,168,0.15)',
  },
}

// Sin sesión todavía (Home antes de login) o rol desconocido: azul de marca genérico.
// Comercial: verde de empresas, que es con quien trabaja (app comercial.dualab.es)
THEME_BY_ROLE[ROLE_COMERCIAL] = THEME_BY_ROLE[ROLE_EMPRESA]
// Atención al cliente: trabaja en DuaLab Central, con el turquesa del superadmin
THEME_BY_ROLE[ROLE_ATENCION_CLIENTE] = THEME_BY_ROLE[ROLE_SUPERADMIN]

const DEFAULT_THEME = {
  key: 'primary',
  text: 'text-primary-700',
  text50: 'text-primary-700/50',
  textDark: 'text-centros-light',
  text50Dark: 'text-centros-light/50',
  bg: 'bg-primary-600',
  bgHover: 'hover:bg-primary-700',
  bgSoft: 'bg-primary-100',
  bg5: 'bg-primary-600/5',
  border: 'border-primary-600',
  border15: 'border-primary-600/15',
  border20: 'border-primary-600/20',
  ring: 'ring-primary-600',
  hex: '#3072AA',
  hexDark: '#6BA4D5',
  hexSoft: 'rgba(48,114,170,0.15)',
}

// DuaLab Central pinta cada espacio con el color de su botón (central/App.vue): mientras se está en
// un espacio, las vistas compartidas con la app (Base de datos, Usuarios…) usan ese tema en vez del
// del rol. null = el del rol.
// Espacios de DuaLab Central (forzarTemaRol): mismas claves que su html[data-rol]
const TEMA_ESPACIO_CONTACTO = {
  key: 'espacio-contacto',
  text: 'text-espacio-contacto-fuerte',
  text50: 'text-espacio-contacto-fuerte/50',
  textDark: 'text-espacio-contacto-claro',
  text50Dark: 'text-espacio-contacto-claro/50',
  bg: 'bg-espacio-contacto-fuerte',
  bgHover: 'hover:bg-espacio-contacto-fuerte/90',
  bgSoft: 'bg-espacio-contacto/15',
  bg5: 'bg-espacio-contacto/5',
  border: 'border-espacio-contacto-fuerte',
  border15: 'border-espacio-contacto/15',
  border20: 'border-espacio-contacto/20',
  ring: 'ring-espacio-contacto-fuerte',
  hex: '#C2413A',
  hexDark: '#FF9A93',
  hexSoft: 'rgba(242,102,94,0.15)',
}
const TEMA_ESPACIO_RED = {
  key: 'espacio-red',
  text: 'text-espacio-red-fuerte',
  text50: 'text-espacio-red-fuerte/50',
  textDark: 'text-espacio-red-claro',
  text50Dark: 'text-espacio-red-claro/50',
  bg: 'bg-espacio-red-fuerte',
  bgHover: 'hover:bg-espacio-red-fuerte/90',
  bgSoft: 'bg-espacio-red/15',
  bg5: 'bg-espacio-red/5',
  border: 'border-espacio-red-fuerte',
  border15: 'border-espacio-red/15',
  border20: 'border-espacio-red/20',
  ring: 'ring-espacio-red-fuerte',
  hex: '#8A6400',
  hexDark: '#E8B84A',
  hexSoft: 'rgba(201,151,28,0.15)',
}
const TEMA_ESPACIO_PRACTICAS = {
  key: 'espacio-practicas',
  text: 'text-espacio-practicas-fuerte',
  text50: 'text-espacio-practicas-fuerte/50',
  textDark: 'text-espacio-practicas-claro',
  text50Dark: 'text-espacio-practicas-claro/50',
  bg: 'bg-espacio-practicas-fuerte',
  bgHover: 'hover:bg-espacio-practicas-fuerte/90',
  bgSoft: 'bg-espacio-practicas/15',
  bg5: 'bg-espacio-practicas/5',
  border: 'border-espacio-practicas-fuerte',
  border15: 'border-espacio-practicas/15',
  border20: 'border-espacio-practicas/20',
  ring: 'ring-espacio-practicas-fuerte',
  hex: '#6B4BB8',
  hexDark: '#BBA6F5',
  hexSoft: 'rgba(142,111,216,0.15)',
}
const THEME_BY_KEY = Object.fromEntries([...Object.values(THEME_BY_ROLE), TEMA_ESPACIO_CONTACTO, TEMA_ESPACIO_RED, TEMA_ESPACIO_PRACTICAS].map(t => [t.key, t]))
const temaForzado = ref(null)
export function forzarTemaRol(clave) {
  temaForzado.value = THEME_BY_KEY[clave] ? clave : null
}

export function useRoleTheme() {
  const authStore = useAuthStore()
  const theme = computed(() => (temaForzado.value && THEME_BY_KEY[temaForzado.value])
    || THEME_BY_ROLE[authStore.userRole] || DEFAULT_THEME)
  return { theme }
}
