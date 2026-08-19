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
import MisGruposDetalle from '../views/MisGruposDetalle.vue'
import PantallaAcceso from '../views/PantallaAcceso.vue'
import PantallaAccesoLista from '../views/PantallaAccesoLista.vue'
import MisGrupos from '../views/MisGrupos.vue'
import EntrarWorkspace from '../views/EntrarWorkspace.vue'
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
      // "equipos", no "grupos": "grupo" ya se usa en el dominio para la clase/curso del
      // encuentro (campo Encuentro.grupo, ej. "2ºB"); esta pantalla sigue el progreso de
      // los EQUIPOS de alumnado (modelo Equipo), de ahí el path y el name en plural "equipos".
      // El componente sigue llamándose MisGrupos.vue (no renombrado a propósito, ver el
      // comentario en ese archivo) — solo cambia la URL/nombre de ruta expuestos.
      path: '/mis-equipos',
      name: 'mis-equipos',
      component: MisGrupos,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    {
      // Antes /workspace/:id (name: workspace-docente), y antes de eso /mis-grupos/:id —
      // mismo motivo de renombrado que la ruta de arriba (equipos, no grupos).
      path: '/mis-equipos/:id',
      name: 'mis-equipos-detalle',
      component: MisGruposDetalle,
      meta: { requiresAuth: true, roles: [SA, AD, DO] }
    },
    // Compatibilidad: enlaces antiguos con el prefijo /workspace y /mis-grupos
    { path: '/workspace/:id', redirect: to => ({ path: `/mis-equipos/${to.params.id}`, query: to.query }) },
    { path: '/mis-grupos', redirect: to => ({ path: '/mis-equipos', query: to.query }) },
    { path: '/mis-grupos/:id', redirect: to => ({ path: `/mis-equipos/${to.params.id}`, query: to.query }) },
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
