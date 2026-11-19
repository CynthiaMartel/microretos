import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore, ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB } from '../src/stores/auth.js'
import { avisoSuperadmin } from './avisoSuperadmin.js'
import { ROLES_CENTRAL, espacioDe, puedeEntrar } from './espacios.js'

// DuaLab Central: superadmin (todo), red_dualab, gestion_practicas y central_dualab (sus espacios). El guard es
// experiencia de usuario: lo que protege los datos es el middleware de cada endpoint en el backend.
// Tres espacios (central/espacios.js), cada uno con su inicio y su panel lateral (meta.espacio).
// Las vistas reutilizadas de la app conservan sus nombres de ruta ('papelera', 'base-datos'…) y sus
// rutas antiguas redirigen a las nuevas: sus enlaces internos y sus «Volver» funcionan igual aquí.
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/acceso', name: 'acceso', component: () => import('./views/AccesoCentral.vue'), meta: { soloInvitado: true } },
    // Selector de espacios, también sin sesión: el login se pide al entrar en uno (central/espacios.js)
    { path: '/', name: 'inicio', component: () => import('./views/EspaciosCentral.vue'), meta: { sinPanel: true } },

    // ── Red DuaLab · Primer contacto: formularios y correo de la web (por su API firmada) ──
    { path: '/primer-contacto', name: 'primer-contacto', component: () => import('./views/BandejaFormularios.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/primer-contacto/formularios/:id(\\d+)', name: 'formulario-web', component: () => import('./views/FichaFormulario.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/primer-contacto/correo', name: 'correo', component: () => import('./views/BandejaCorreo.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/primer-contacto/correo/:id(\\d+)', name: 'correo-ficha', component: () => import('./views/FichaCorreo.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/primer-contacto/solicitudes-reto', name: 'solicitudes-reto', component: () => import('./views/BandejaSolicitudesReto.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/primer-contacto/respuestas-solicitudes', name: 'respuestas-solicitudes', component: () => import('./views/RespuestasSolicitudes.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/primer-contacto/solicitudes-reto/:id(\\d+)', name: 'solicitud-reto-ficha', component: () => import('./views/FichaSolicitudReto.vue'), meta: { requiresAuth: true, espacio: 'red' } },

    // ── Espacio «Red DuaLab» ──
    { path: '/red', name: 'red-inicio', component: () => import('./views/InicioRed.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/red/captaciones', name: 'captaciones', component: () => import('./views/CaptacionesCentral.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/red/empresas-centros', name: 'empresas-centros', component: () => import('./views/EmpresasCentrosCentral.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/red/empresas', name: 'empresas-central', component: () => import('./views/EmpresasCentral.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/red/centros', name: 'centros-central', component: () => import('./views/CentrosCentral.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/red/soporte', name: 'soporte', component: () => import('./views/SoporteCentral.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    { path: '/red/soporte/correo', name: 'soporte-correo', component: () => import('./views/SoporteCentral.vue'), meta: { requiresAuth: true, espacio: 'red' } },
    // avisoSuperadmin: Admin Central DuaLab que entra por la URL ve el aviso para iniciar sesión como superadmin
    { path: '/red/base-datos', name: 'base-datos', component: () => import('../src/views/BaseDatosDashboard.vue'), meta: { requiresAuth: true, espacio: 'red', roles: [ROLE_SUPERADMIN], avisoSuperadmin: 'Base de datos' } },
    { path: '/red/historial', name: 'historial', component: () => import('./views/HistorialCentral.vue'), meta: { requiresAuth: true, espacio: 'red', roles: [ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB] } },
    { path: '/red/usuarios', name: 'usuarios', component: () => import('../src/views/GestionUsuarios.vue'), meta: { requiresAuth: true, espacio: 'red', roles: [ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB] } },
    { path: '/red/papelera', name: 'papelera', component: () => import('../src/views/PapeleraBaseDatos.vue'), meta: { requiresAuth: true, espacio: 'red', roles: [ROLE_SUPERADMIN], avisoSuperadmin: 'Papelera' } },

    // ── Espacio «Seguimiento de prácticas» ──
    { path: '/practicas', name: 'practicas-inicio', component: () => import('./views/InicioPracticas.vue'), meta: { requiresAuth: true, espacio: 'practicas' } },
    { path: '/practicas/agenda', name: 'agenda', component: () => import('./views/AgendaCentral.vue'), meta: { requiresAuth: true, espacio: 'practicas' } },
    { path: '/practicas/planes', name: 'planes', component: () => import('./views/PlanesCentral.vue'), meta: { requiresAuth: true, espacio: 'practicas' } },
    // Misma pantalla que en la app; al guardar o cancelar vuelve a 'planes' (esta lista)
    { path: '/practicas/planes/:uuid/cronograma', name: 'plan-cronograma', component: () => import('../src/views/CronogramaPlan.vue'), meta: { requiresAuth: true, espacio: 'practicas' } },
    { path: '/practicas/destacados', name: 'destacados', component: () => import('./views/DestacadosCentral.vue'), meta: { requiresAuth: true, espacio: 'practicas' } },

    { path: '/mi-usuario', name: 'mi-usuario', component: () => import('../src/views/MiUsuario.vue'), meta: { requiresAuth: true, sinPanel: true } },

    // Rutas anteriores a los espacios (enlaces guardados y enlaces internos de las vistas compartidas)
    { path: '/captaciones', redirect: '/red/captaciones' },
    { path: '/soporte', redirect: '/red/soporte' },
    { path: '/base-datos', redirect: '/red/base-datos' },
    { path: '/usuarios', redirect: '/red/usuarios' },
    { path: '/papelera', redirect: '/red/papelera' },
    { path: '/agenda', redirect: '/practicas/agenda' },
    { path: '/destacados', redirect: '/practicas/destacados' },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

// Destino seguro tras el login: solo rutas internas (nada de //otro-dominio)
const rutaInterna = (r) => (typeof r === 'string' && r.startsWith('/') && !r.startsWith('//') ? r : null)

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()
  document.title = 'Central DuaLab'

  const rol = auth.isAuthenticated ? auth.userRole : null
  // Sesión de otro rol (un docente, un comercial… que entra aquí con su cuenta): Central no es para él
  if (auth.isAuthenticated && !ROLES_CENTRAL.includes(rol)) {
    await auth.logout()
    return to.name === 'acceso' ? true : { name: 'acceso', query: { motivo: 'rol', espacio: to.meta.espacio } }
  }

  // Dentro de un espacio, una sección solo para otros roles (Base de datos, Usuarios, Papelera): a su inicio
  if (to.meta.requiresAuth && to.meta.roles && puedeEntrar(rol, to.meta.espacio) && !to.meta.roles.includes(Number(rol))) {
    if (to.meta.avisoSuperadmin && Number(rol) === ROLE_ADMIN_CENTRAL_DUALAB) {
      avisoSuperadmin.value = { nombre: to.meta.avisoSuperadmin, destino: to.fullPath }
    }
    return espacioDe(to.meta.espacio)?.ruta ?? { name: 'inicio' }
  }
  if (to.meta.requiresAuth && !puedeEntrar(rol, to.meta.espacio)) {
    return { name: 'acceso', query: {
      espacio: to.meta.espacio, redirect: to.fullPath,
      // Con sesión de una cuenta que no abre este espacio: se pide la otra cuenta
      ...(auth.isAuthenticated && { motivo: 'espacio' }),
    } }
  }

  // En la pantalla de acceso con una sesión que ya vale para ese espacio: se entra directamente
  if (to.name === 'acceso' && auth.isAuthenticated && to.query.motivo !== 'espacio' && puedeEntrar(rol, to.query.espacio)) {
    return rutaInterna(to.query.redirect) ?? espacioDe(to.query.espacio)?.ruta ?? { name: 'inicio' }
  }
  return true
})

export default router
