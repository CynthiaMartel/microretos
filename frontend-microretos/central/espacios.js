// Espacios de DuaLab Central (decisión de Cynthia 2026-11-17, TAREAS_PENDIENTES T21): una sola app,
// con un login y una sesión, pero tres espacios de trabajo, cada uno con su inicio y su panel lateral.
// Al abrir Central se ven los tres espacios sin sesión (EspaciosCentral.vue, al estilo del Nexo) y el
// login se pide al entrar en uno, según quién puede usarlo (`roles`). Lo que protege los datos es el
// middleware de cada endpoint ('primer.contacto' o 'superadmin'); esto es solo experiencia de usuario.
//   · Primer contacto: formularios de la web. Los datos siguen en la web; el backend de la app los
//     trae por la API firmada de la web (PrimerContactoController, PrimerContactoCorreoController).
//     El correo sale desde info@ por la web; Gestión web ya no atiende formularios ni correo.
//   · Red DuaLab: captaciones, empresas y centros adheridos (base de datos), usuarios, papelera y soporte.
//   · Seguimiento de prácticas: agenda de prácticas y alumnado destacado.
import { ROLE_SUPERADMIN, ROLE_ATENCION_CLIENTE } from '../src/stores/auth.js'

// Roles que pueden entrar en Central (en algún espacio)
export const ROLES_CENTRAL = [ROLE_SUPERADMIN, ROLE_ATENCION_CLIENTE]

export const ICONOS = {
  "inicio": "M3 11l9-7 9 7v9a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9z",
  "agenda": "M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zM16 2v4M8 2v4M3 10h18M8 14h3v3H8z",
  "captaciones": "M9 12l2 2 4-4M7.8 4.4A3.4 3.4 0 009.7 3.6a3.4 3.4 0 014.6 0 3.4 3.4 0 001.9.8 3.4 3.4 0 013.2 3.2c.1.7.3 1.4.8 1.9a3.4 3.4 0 010 4.6 3.4 3.4 0 00-.8 1.9 3.4 3.4 0 01-3.2 3.2 3.4 3.4 0 00-1.9.8 3.4 3.4 0 01-4.6 0 3.4 3.4 0 00-1.9-.8 3.4 3.4 0 01-3.2-3.2 3.4 3.4 0 00-.8-1.9 3.4 3.4 0 010-4.6 3.4 3.4 0 00.8-1.9 3.4 3.4 0 013.2-3.2z",
  "destacados": "M11.48 3.5a.56.56 0 011.04 0l2.13 5.11 5.52.44c.5.04.7.66.32.98l-4.2 3.6 1.28 5.38a.56.56 0 01-.84.61L12 16.73l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.38-4.2-3.6a.56.56 0 01.32-.98l5.52-.44 2.13-5.11z",
  "base-datos": "M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zm0 0v10c0 1.7 3.6 3 8 3s8-1.3 8-3V7M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3",
  "usuarios": "M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z",
  "papelera": "M19 7l-.9 12.1A2 2 0 0116.1 21H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16",
  "soporte": "M18.4 5.6A9 9 0 105.6 18.4 9 9 0 0018.4 5.6zM15 9a3 3 0 00-6 0c0 2 3 2 3 4m0 3h.01",
  "usuario": "M12 12a4 4 0 100-8 4 4 0 000 8zM4 20c0-4 3.6-7 8-7s8 3 8 7",
  "correo": "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
  "formularios": "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
}

export const ESPACIOS = [
  {
    clave: 'primer-contacto',
    nombre: 'Primer contacto',
    texto: 'Los formularios de dualab.es y el correo de info@: leerlos, responderlos y ver si ya están en captaciones.',
    color: 'alumnos',
    roles: [ROLE_ATENCION_CLIENTE, ROLE_SUPERADMIN],
    quien: 'Atención al cliente o superadmin',
    ruta: { name: 'primer-contacto' },
    icono: ICONOS.correo,
    grupos: [
      { titulo: 'Primer contacto', items: [
        { key: 'formularios', label: 'Formularios de la web', ruta: { name: 'primer-contacto' }, nombres: ['primer-contacto', 'formulario-web'], contador: 'formularios', icono: ICONOS.formularios },
        { key: 'correo', label: 'Correo (info@)', ruta: { name: 'correo' }, nombres: ['correo', 'correo-ficha'], contador: 'correo', icono: ICONOS.correo },
      ] },
    ],
  },
  {
    clave: 'red',
    nombre: 'Red DuaLab',
    texto: 'Captaciones por validar, empresas y centros adheridos, usuarios, papelera y soporte de cuentas.',
    color: 'centros',
    roles: [ROLE_SUPERADMIN],
    quien: 'Superadmin',
    ruta: { name: 'red-inicio' },
    icono: 'M17 20h5v-2a3 3 0 00-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 015.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    grupos: [
      { titulo: 'Red DuaLab', items: [
        { key: 'red-inicio', label: 'Inicio', ruta: { name: 'red-inicio' }, nombres: ['red-inicio'], icono: ICONOS.inicio },
        { key: 'captaciones', label: 'Captaciones', ruta: { name: 'captaciones' }, nombres: ['captaciones'], contador: 'captaciones', icono: ICONOS.captaciones },
        { key: 'soporte', label: 'Soporte', ruta: { name: 'soporte' }, nombres: ['soporte'], proximamente: true, icono: ICONOS.soporte },
      ] },
      { titulo: 'Datos', items: [
        { key: 'base-datos', label: 'Base de datos', ruta: { name: 'base-datos' }, nombres: ['base-datos'], icono: ICONOS['base-datos'] },
        { key: 'usuarios', label: 'Usuarios', ruta: { name: 'usuarios' }, nombres: ['usuarios'], icono: ICONOS.usuarios },
        { key: 'papelera', label: 'Papelera', ruta: { name: 'papelera' }, nombres: ['papelera'], icono: ICONOS.papelera },
      ] },
    ],
  },
  {
    clave: 'practicas',
    nombre: 'Seguimiento de prácticas',
    texto: 'El avance del alumnado en las empresas y la selección del alumnado destacado para las prácticas.',
    color: 'empresas',
    roles: [ROLE_SUPERADMIN],
    quien: 'Superadmin',
    ruta: { name: 'practicas-inicio' },
    icono: ICONOS.agenda,
    grupos: [
      { titulo: 'Seguimiento de prácticas', items: [
        { key: 'practicas-inicio', label: 'Inicio', ruta: { name: 'practicas-inicio' }, nombres: ['practicas-inicio'], icono: ICONOS.inicio },
        { key: 'agenda', label: 'Agenda de prácticas', ruta: { name: 'agenda' }, nombres: ['agenda'], icono: ICONOS.agenda },
        { key: 'destacados', label: 'Alumnado destacado', ruta: { name: 'destacados' }, nombres: ['destacados'], icono: ICONOS.destacados },
      ] },
    ],
  },
]

export const espacioDe = (clave) => ESPACIOS.find(e => e.clave === clave) ?? null

/** ¿Puede este rol entrar en el espacio? Sin espacio (selector, Mi usuario): cualquier rol de Central. */
export const puedeEntrar = (rol, clave) => {
  const roles = clave ? espacioDe(clave)?.roles : ROLES_CENTRAL
  return !!roles?.includes(Number(rol))
}
