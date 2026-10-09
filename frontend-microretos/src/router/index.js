import { createRouter, createWebHistory } from 'vue-router'
import GeneradorMicroretos from '../views/GeneradorMicroretos.vue'
import BibliotecaMicroretos from '../views/BibliotecaMicroretos.vue'
import Home from '../views/Home.vue'
import DetalleMicroreto from '../views/DetalleMicroreto.vue'
import MasFamilias from '../views/MasFamilias.vue'
import BaseDatosDashboard from '../views/BaseDatosDashboard.vue'
import PublicMicroreto from '../views/PublicMicroreto.vue'
import DashboardDocente from '../views/DashboardDocente.vue'
import EncuentrosRegistrados from '../views/EncuentrosRegistrados.vue'
import StartupDayProyectos from '../views/StartupDayProyectos.vue'
import ProyectosTerminados from '../views/ProyectosTerminados.vue'
import StartupDayWizard from '../views/StartupDayWizard.vue'
import StartupDayDetalle from '../views/StartupDayDetalle.vue'
import StartupDayLanding from '../views/StartupDayLanding.vue'
import UnirseEquipo from '../views/UnirseEquipo.vue'
import EquipoWorkspace from '../views/EquipoWorkspace.vue'
import EmpresasView from '../views/EmpresasView.vue'
import PapeleraBaseDatos from '../views/PapeleraBaseDatos.vue'
import GestionUsuarios from '../views/GestionUsuarios.vue'
import InicioDocente from '../views/InicioDocente.vue'
import NoticiasListado from '../views/NoticiasListado.vue'
import MiUsuario from '../views/MiUsuario.vue'
import MisGruposDetalle from '../views/MisGruposDetalle.vue'
import PantallaAcceso from '../views/PantallaAcceso.vue'
import PantallaAccesoLista from '../views/PantallaAccesoLista.vue'
import MisGrupos from '../views/MisGrupos.vue'
import EntrarWorkspace from '../views/EntrarWorkspace.vue'
import SeccionHub from '../views/SeccionHub.vue'
import CalendarioDocente from '../views/CalendarioDocente.vue'
import NotificacionesDocente from '../views/NotificacionesDocente.vue'
import PropuestasEmpresas from '../views/PropuestasEmpresas.vue'
import BibliotecaDiagnosticos from '../views/BibliotecaDiagnosticos.vue'
import AlumnadoListado from '../views/AlumnadoListado.vue'
import { SECCIONES } from '../config/navegacion.js'
import { ROLE_SUPERADMIN, ROLE_ADMIN, ROLE_DOCENTE, ROLE_EMPRESA, useAuthStore } from '../stores/auth.js'

const SA = ROLE_SUPERADMIN  // 1
const AD = ROLE_ADMIN       // 4
const DO = ROLE_DOCENTE     // 2
const EM = ROLE_EMPRESA     // 3

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: Home
    },
    {
      // Entrada directa desde fuera del dominio (p. ej. el botón "Iniciar sesión"
      // de la home del frontoffice en dualab.es/info.dualab.es) — la raíz "/" ya
      // no está disponible como primer punto de entrada porque un carve-out de
      // Apache la reserva para esa home; esta ruta reutiliza el mismo Home.vue
      // (con el modal de login, ver App.vue) para que dualab.es/login funcione
      // como un enlace externo normal. Admite ?redirect=/ruta para abrir el modal
      // ya apuntando a esa ruta tras iniciar sesión (ver el watch en App.vue).
      path: '/login',
      name: 'login',
      component: Home
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
      // Seguimiento del envío de propuestas: no pide la contraseña del directorio
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
      // Antes /mis-equipos (y antes de eso /mis-grupos y /dashboard/mis-grupos). Vuelve a
      // "grupos" porque en las vistas del docente los equipos de alumnado se llaman "grupos"
      // y la letra del encuentro (Encuentro.grupo, ej. "B") se muestra como "Clase".
      path: '/mis-grupos',
      name: 'mis-grupos',
      component: MisGrupos,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
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
      // Ruta específica antes de /proyectos/:uuid — si no, "terminados" se capturaría como uuid.
      path: '/proyectos/terminados',
      name: 'proyectos-terminados',
      component: ProyectosTerminados,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
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
    {
      // Secciones del panel lateral (Retos y proyectos, Encuentros, Alumnado…): cards de
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
// Si no hay sesión → redirige a / con ?redirect=<ruta>
// Si hay sesión pero el rol no tiene acceso → redirige a /
router.beforeEach(async (to, _from, next) => {
  // Con sesión iniciada, la home (/) y /login no tienen sentido: se va directo al panel
  // (docente/admin/superadmin) o, en cuentas de empresa, a sus proyectos. Si llega un
  // ?redirect interno (p. ej. desde un enlace /login?redirect=/retos/crear), se respeta.
  if (to.name === 'home' || to.name === 'login') {
    const auth = useAuthStore()
    if (!auth.isInitialized) await auth.init()
    if (auth.isAuthenticated) {
      const redirect = typeof to.query.redirect === 'string' ? to.query.redirect : ''
      if (redirect.startsWith('/') && !redirect.startsWith('//')) {
        next(redirect)
        return
      }
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
    next({ path: '/', query: { redirect: to.fullPath } })
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
