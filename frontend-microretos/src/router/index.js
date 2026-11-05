import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import { SECCIONES } from '../config/navegacion.js'
import { queryRedirect, redirectVigente } from '../utils/redirectLogin.js'
import { ROLE_SUPERADMIN, ROLE_ADMIN, ROLE_DOCENTE, ROLE_EMPRESA, useAuthStore } from '../stores/auth.js'

// Cada vista se descarga al entrar en ella (un chunk por vista), no todas en el bundle
// inicial. LoginView va dentro porque es la primera pantalla que se ve.
const GeneradorMicroretos = () => import('../views/GeneradorMicroretos.vue')
const BibliotecaMicroretos = () => import('../views/BibliotecaMicroretos.vue')
const RestablecerContrasena = () => import('../views/RestablecerContrasena.vue')
const DetalleMicroreto = () => import('../views/DetalleMicroreto.vue')
const MasFamilias = () => import('../views/MasFamilias.vue')
const BaseDatosDashboard = () => import('../views/BaseDatosDashboard.vue')
const PublicMicroreto = () => import('../views/PublicMicroreto.vue')
const DashboardDocente = () => import('../views/DashboardDocente.vue')
const EncuentrosRegistrados = () => import('../views/EncuentrosRegistrados.vue')
const StartupDayProyectos = () => import('../views/StartupDayProyectos.vue')
const StartupDayWizard = () => import('../views/StartupDayWizard.vue')
const StartupDayDetalle = () => import('../views/StartupDayDetalle.vue')
const StartupDayLanding = () => import('../views/StartupDayLanding.vue')
const UnirseEquipo = () => import('../views/UnirseEquipo.vue')
const EquipoWorkspace = () => import('../views/EquipoWorkspace.vue')
const EmpresasView = () => import('../views/EmpresasView.vue')
const PapeleraBaseDatos = () => import('../views/PapeleraBaseDatos.vue')
const GestionUsuarios = () => import('../views/GestionUsuarios.vue')
const InicioDocente = () => import('../views/InicioDocente.vue')
const NoticiasListado = () => import('../views/NoticiasListado.vue')
const MiUsuario = () => import('../views/MiUsuario.vue')
const MisGruposDetalle = () => import('../views/MisGruposDetalle.vue')
const PantallaAcceso = () => import('../views/PantallaAcceso.vue')
const PantallaAccesoLista = () => import('../views/PantallaAccesoLista.vue')
const EntrarWorkspace = () => import('../views/EntrarWorkspace.vue')
const SeccionHub = () => import('../views/SeccionHub.vue')
const CalendarioDocente = () => import('../views/CalendarioDocente.vue')
const NotificacionesDocente = () => import('../views/NotificacionesDocente.vue')
const PropuestasEmpresas = () => import('../views/PropuestasEmpresas.vue')
const BibliotecaDiagnosticos = () => import('../views/BibliotecaDiagnosticos.vue')
const AlumnadoListado = () => import('../views/AlumnadoListado.vue')

const SA = ROLE_SUPERADMIN  // 1
const AD = ROLE_ADMIN       // 4
const DO = ROLE_DOCENTE     // 2
const EM = ROLE_EMPRESA     // 3

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  // Las vistas hacen scroll sobre la ventana (App.vue no tiene contenedor con overflow):
  // sin esto, al cambiar de vista se conservaba el scroll de la anterior. Cada navegación
  // empieza arriba; solo atrás/adelante del navegador recupera la posición que tenía.
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash }
    // Misma vista con otra query (filtros, pestañas que se guardan en la URL): no mover la
    // página; si no, cada filtro marcado la devolvía arriba del todo.
    if (to.path === from.path) return false
    return { top: 0 }
  },
  routes: [
    {
      // Sin sesión, la raíz muestra la misma página de acceso que /login (en producción
      // la raíz de dualab.es la sirve el frontoffice, ver comentario de /login)
      path: '/',
      name: 'home',
      component: LoginView
    },
    {
      // Entrada directa desde fuera del dominio (p. ej. el botón "Iniciar sesión"
      // de la home del frontoffice en dualab.es/info.dualab.es) — la raíz "/" ya
      // no está disponible como primer punto de entrada porque un carve-out de
      // Apache la reserva para esa home. Página de acceso a pantalla completa
      // (LoginView); admite ?redirect=/ruta para volver a esa ruta tras iniciar sesión
      // (con caducidad, ver utils/redirectLogin.js).
      path: '/login',
      name: 'login',
      component: LoginView
    },
    {
      // Enlace del email de "¿Has olvidado tu contraseña?" (?token=…&email=…)
      path: '/restablecer-contrasena',
      name: 'restablecer-contrasena',
      component: RestablecerContrasena
    },
    {
      path: '/retos/crear',
      name: 'microretos',
      component: GeneradorMicroretos,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/retos',
      name: 'biblioteca',
      component: BibliotecaMicroretos,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
    },
    {
      path: '/retos/familias',
      name: 'mas-familias',
      component: MasFamilias,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
    },
    {
      path: '/retos/:id',
      name: 'detalle-microreto',
      component: DetalleMicroreto,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
    },
    // Compatibilidad: enlaces antiguos con el prefijo /microretos y /biblioteca
    { path: '/microretos', redirect: to => ({ path: '/retos/crear', query: to.query }) },
    { path: '/biblioteca', redirect: to => ({ path: '/retos', query: to.query }) },
    { path: '/biblioteca/:id', redirect: to => ({ path: `/retos/${to.params.id}`, query: to.query }) },
    {
      path: '/base-datos',
      name: 'base-datos',
      component: BaseDatosDashboard,
      meta: { requiresAuth: true, roles: [SA] }
    },
    {
      path: '/papelera',
      name: 'papelera',
      component: PapeleraBaseDatos,
      meta: { requiresAuth: true, roles: [SA] }
    },
    {
      // Seguimiento del envío de propuestas
      path: '/empresas/propuestas',
      name: 'propuestas-empresas',
      component: PropuestasEmpresas,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/empresas',
      name: 'empresas',
      component: EmpresasView,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/encuentros/crear',
      name: 'dashboard-docente',
      component: DashboardDocente,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/encuentros',
      name: 'encuentros-registrados',
      component: EncuentrosRegistrados,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    // Compatibilidad: enlaces antiguos con el nombre "sesiones"
    { path: '/sesiones', redirect: to => ({ path: '/encuentros', query: to.query }) },
    {
      // Mis grupos ya no es una vista propia: es la pestaña «Seguimiento de grupos» de la
      // biblioteca de encuentros. Se conserva la ruta (con su nombre) para los enlaces y las
      // notificaciones guardadas; ?encuentro=&equipo= siguen abriendo ese encuentro y grupo.
      path: '/mis-grupos',
      name: 'mis-grupos',
      redirect: to => ({ path: '/encuentros', query: { ...to.query, ver: 'seguimiento' } })
    },
    {
      // Antes /mis-equipos/:id y /workspace/:id — mismo motivo que la ruta de arriba.
      path: '/mis-grupos/:id',
      name: 'mis-grupos-detalle',
      component: MisGruposDetalle,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    // Compatibilidad: enlaces antiguos (favoritos y notificaciones ya guardadas en BD con /mis-equipos)
    { path: '/mis-equipos', redirect: to => ({ path: '/mis-grupos', query: to.query }) },
    { path: '/mis-equipos/:id', redirect: to => ({ path: `/mis-grupos/${to.params.id}`, query: to.query }) },
    { path: '/workspace/:id', redirect: to => ({ path: `/mis-grupos/${to.params.id}`, query: to.query }) },
    {
      // Vista pública para alumnado — acceso mediante token temporal (QR)
      path: '/reto/:token',
      name: 'public-microreto',
      component: PublicMicroreto
    },
    {
      path: '/proyectos',
      name: 'startup-day',
      component: StartupDayProyectos,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
    },
    {
      // Los completados son una pestaña de /proyectos: los enlaces antiguos llegan a ella
      // (con su familia). Ruta específica antes de /proyectos/:uuid — si no, "terminados"
      // se capturaría como uuid.
      path: '/proyectos/terminados',
      name: 'proyectos-terminados',
      redirect: to => ({ name: 'startup-day', query: { ...to.query, ver: 'completados' } })
    },
    {
      path: '/proyectos/crear',
      name: 'startup-day-crear',
      component: StartupDayWizard,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/proyectos/:uuid/editar',
      name: 'startup-day-editar',
      component: StartupDayWizard,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/proyectos/:uuid',
      name: 'startup-day-detalle',
      component: StartupDayDetalle,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
    },
    {
      // Pantalla para proyectar en clase: QR + código corto por equipo
      path: '/proyectos/:uuid/pantalla-acceso',
      name: 'pantalla-acceso',
      component: PantallaAcceso,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      // Elegir qué encuentro proyectar antes de abrir su pantalla de acceso
      path: '/pantalla-acceso',
      name: 'pantalla-acceso-lista',
      component: PantallaAccesoLista,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      // Vista pública para validación por parte de la empresa
      path: '/startup/landing/:token',
      name: 'startup-day-landing',
      component: StartupDayLanding
    },
    // ── Compatibilidad: enlaces antiguos con el prefijo /startup-day ──────────
    { path: '/startup-day', redirect: '/proyectos' },
    { path: '/startup-day/crear', redirect: '/proyectos/crear' },
    { path: '/startup-day/:uuid/editar', redirect: to => `/proyectos/${to.params.uuid}/editar` },
    { path: '/startup-day/:uuid', redirect: to => `/proyectos/${to.params.uuid}` },
    {
      // Página de entrada tipo Kahoot para el alumnado (acceso por código corto)
      path: '/unirse',
      name: 'unirse-equipo',
      component: UnirseEquipo
    },
    {
      // Reentrada directa al workspace propio con el código del equipo
      path: '/workspace-proyecto',
      name: 'entrar-workspace',
      component: EntrarWorkspace
    },
    {
      // Workspace completo del equipo (F0-F4) — acceso por token de 40 chars
      path: '/proyecto/equipo/:token',
      name: 'equipo-workspace',
      component: EquipoWorkspace
    },
    {
      // El mismo workspace visto por el docente, en solo lectura: por id del grupo y con su
      // sesión, sin token (sin él no se puede escribir nada en el trabajo del alumnado)
      path: '/grupos/:equipoId/workspace',
      name: 'equipo-workspace-docente',
      component: EquipoWorkspace,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/usuarios',
      name: 'gestion-usuarios',
      component: GestionUsuarios,
      meta: { requiresAuth: true, roles: [SA, AD] }
    },
    // Compatibilidad: enlaces antiguos con el prefijo /admin
    { path: '/admin/usuarios', redirect: to => ({ path: '/usuarios', query: to.query }) },
    {
      path: '/panel-docente',
      name: 'inicio-docente',
      component: InicioDocente,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    // Compatibilidad: enlaces antiguos con el nombre "inicio-docente"
    { path: '/inicio-docente', redirect: to => ({ path: '/panel-docente', query: to.query }) },
    // Compatibilidad: la sección «Encuentros» pasó a «Encuentros y grupos» (2026-11-05). Antes que
    // /seccion/:seccion: si no, «encuentros» se capturaría como sección inexistente → panel de inicio
    { path: '/seccion/encuentros', redirect: '/seccion/encuentros-grupos' },
    {
      // Secciones del panel lateral (Retos y proyectos, Encuentros y grupos, Alumnado…): cards de
      // entrada a las herramientas de cada área — ver config/navegacion.js
      path: '/seccion/:seccion',
      name: 'seccion',
      component: SeccionHub,
      meta: { requiresAuth: true, roles: [SA, AD, DO] },
      beforeEnter: to => (SECCIONES[to.params.seccion] ? true : { path: '/panel-docente' })
    },
    {
      path: '/calendario',
      name: 'calendario',
      component: CalendarioDocente,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/notificaciones',
      name: 'notificaciones',
      component: NotificacionesDocente,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/evaluacion/diagnosticos',
      name: 'biblioteca-diagnosticos',
      component: BibliotecaDiagnosticos,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/alumnado/listado',
      name: 'alumnado-listado',
      component: AlumnadoListado,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/noticias/:tipo',
      name: 'noticias-listado',
      component: NoticiasListado,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/mi-usuario',
      name: 'mi-usuario',
      component: MiUsuario,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
    }
  ]
})

// Guard global: verifica autenticación y permisos de rol. La sesión vive en una cookie
// HttpOnly (Sanctum stateful) — no hay nada que leer en localStorage; en la primera
// navegación se pregunta al backend (GET /perfil) si la cookie es válida.
// Si no hay sesión → redirige a / con ?redirect=<ruta>&rt=<hora>
// Si hay sesión pero el rol no tiene acceso → redirige a /
router.beforeEach(async (to, _from, next) => {
  // Con sesión iniciada, la home (/) y /login no tienen sentido: se va directo al panel
  // (docente/admin/superadmin) o, en cuentas de empresa, a sus proyectos. Un ?redirect
  // interno y aún vigente (ver utils/redirectLogin.js) se respeta.
  if (to.name === 'home' || to.name === 'login') {
    const auth = useAuthStore()
    if (!auth.isInitialized) await auth.init()
    if (auth.isAuthenticated) {
      const redirect = redirectVigente(to.query)
      if (redirect) { next(redirect); return }
      if ([SA, AD, DO].includes(auth.userRole)) { next({ name: 'inicio-docente' }); return }
      if (auth.userRole === EM) { next({ path: '/proyectos' }); return }
    }
    next()
    return
  }

  if (!to.meta.requiresAuth) {
    next()
    return
  }

  const auth = useAuthStore()
  if (!auth.isInitialized) await auth.init()

  if (!auth.isAuthenticated) {
    next({ path: '/', query: queryRedirect(to.fullPath) })
    return
  }

  // Verificar permiso de rol para esta ruta — nunca asumir superadmin por defecto
  const allowedRoles = to.meta.roles ?? []
  if (allowedRoles.length > 0 && !allowedRoles.includes(auth.userRole)) {
    next({ path: '/' })
    return
  }

  next()
})

export default router
