import { createRouter, createWebHistory } from 'vue-router'
import GeneradorMicroretos from '../views/GeneradorMicroretos.vue'
import BibliotecaMicroretos from '../views/BibliotecaMicroretos.vue'
import Home from '../views/Home.vue'
import DetalleMicroreto from '../views/DetalleMicroreto.vue'
import BaseDatosDashboard from '../views/BaseDatosDashboard.vue'
import PublicMicroreto from '../views/PublicMicroreto.vue'
import DashboardDocente from '../views/DashboardDocente.vue'
import EncuentrosRegistrados from '../views/EncuentrosRegistrados.vue'
import StartupDayProyectos from '../views/StartupDayProyectos.vue'
import StartupDayWizard from '../views/StartupDayWizard.vue'
import StartupDayDetalle from '../views/StartupDayDetalle.vue'
import StartupDayLanding from '../views/StartupDayLanding.vue'
import UnirseEquipo from '../views/UnirseEquipo.vue'
import EquipoWorkspace from '../views/EquipoWorkspace.vue'
import EmpresasView from '../views/EmpresasView.vue'
import PapeleraBaseDatos from '../views/PapeleraBaseDatos.vue'
import GestionUsuarios from '../views/GestionUsuarios.vue'
import InicioDocente from '../views/InicioDocente.vue'
import MiUsuario from '../views/MiUsuario.vue'
import WorkspaceDocente from '../views/WorkspaceDocente.vue'
import PantallaAcceso from '../views/PantallaAcceso.vue'
import PantallaAccesoLista from '../views/PantallaAccesoLista.vue'
import MisGrupos from '../views/MisGrupos.vue'
import EntrarWorkspace from '../views/EntrarWorkspace.vue'
import { ROLE_SUPERADMIN, ROLE_ADMIN, ROLE_DOCENTE, ROLE_EMPRESA } from '../stores/auth.js'

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
      path: '/microretos',
      name: 'microretos',
      component: GeneradorMicroretos,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/biblioteca',
      name: 'biblioteca',
      component: BibliotecaMicroretos,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
    },
    {
      path: '/biblioteca/:id',
      name: 'detalle-microreto',
      component: DetalleMicroreto,
      meta: { requiresAuth: true, roles: [SA, AD, DO, EM] }
    },
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
      meta: { requiresAuth: true, roles: [SA, AD] }
    },
    {
      path: '/empresas',
      name: 'empresas',
      component: EmpresasView,
      meta: { requiresAuth: true, roles: [SA, DO] }
    },
    {
      path: '/dashboard',
      name: 'dashboard-docente',
      component: DashboardDocente,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/dashboard/encuentros',
      name: 'encuentros-registrados',
      component: EncuentrosRegistrados,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    // Compatibilidad: enlaces antiguos con el nombre "sesiones"
    { path: '/dashboard/sesiones', redirect: to => ({ path: '/dashboard/encuentros', query: to.query }) },
    {
      path: '/dashboard/workspace/:id',
      name: 'workspace-docente',
      component: WorkspaceDocente,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      path: '/dashboard/mis-grupos',
      name: 'mis-grupos',
      component: MisGrupos,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
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
      path: '/dashboard/pantalla-acceso',
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
      path: '/admin/usuarios',
      name: 'gestion-usuarios',
      component: GestionUsuarios,
      meta: { requiresAuth: true, roles: [SA, AD] }
    },
    {
      path: '/inicio-docente',
      name: 'inicio-docente',
      component: InicioDocente,
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

// Duración del token en ms — debe coincidir con TOKEN_DURATION_MINUTES en auth.js
const TOKEN_DURATION_MS = 1440 * 60 * 1000

// Guard global: verifica autenticación y permisos de rol.
// Si no hay sesión → redirige a / con ?redirect=<ruta>
// Si hay sesión pero el rol no tiene acceso → redirige a /
router.beforeEach((to, _from, next) => {
  if (!to.meta.requiresAuth) {
    next()
    return
  }

  const token     = localStorage.getItem('admin_token')
  const createdAt = Number(localStorage.getItem('admin_token_created_at') || 0)
  const isValid   = token && createdAt && (Date.now() - createdAt) < TOKEN_DURATION_MS

  if (!isValid) {
    localStorage.removeItem('admin_token')
    localStorage.removeItem('admin_token_created_at')
    localStorage.removeItem('user_role')
    localStorage.removeItem('user_name')
    next({ path: '/', query: { redirect: to.fullPath } })
    return
  }

  // Verificar permiso de rol para esta ruta
  const role         = Number(localStorage.getItem('user_role') || ROLE_SUPERADMIN)
  const allowedRoles = to.meta.roles ?? []
  if (allowedRoles.length > 0 && !allowedRoles.includes(role)) {
    next({ path: '/' })
    return
  }

  next()
})

export default router
