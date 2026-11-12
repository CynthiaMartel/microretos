import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore, ROLE_SUPERADMIN } from '../src/stores/auth.js'

// Solo el superadmin usa DuaLab Central. El guard es experiencia de usuario: lo que protege los
// datos es el middleware 'superadmin' de cada endpoint en el backend.
// Las rutas reutilizan las vistas de la app con las MISMAS rutas y nombres ('papelera',
// '/base-datos'…): sus enlaces internos y sus «Volver» funcionan igual aquí.
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/acceso', name: 'acceso', component: () => import('./views/AccesoCentral.vue'), meta: { soloInvitado: true } },
    { path: '/', name: 'inicio', component: () => import('./views/InicioCentral.vue'), meta: { requiresAuth: true } },
    { path: '/captaciones', name: 'captaciones', component: () => import('./views/CaptacionesCentral.vue'), meta: { requiresAuth: true } },
    { path: '/base-datos', name: 'base-datos', component: () => import('../src/views/BaseDatosDashboard.vue'), meta: { requiresAuth: true } },
    { path: '/papelera', name: 'papelera', component: () => import('../src/views/PapeleraBaseDatos.vue'), meta: { requiresAuth: true } },
    { path: '/usuarios', name: 'usuarios', component: () => import('../src/views/GestionUsuarios.vue'), meta: { requiresAuth: true } },
    { path: '/soporte', name: 'soporte', component: () => import('./views/SoporteCentral.vue'), meta: { requiresAuth: true } },
    { path: '/mi-usuario', name: 'mi-usuario', component: () => import('../src/views/MiUsuario.vue'), meta: { requiresAuth: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()

  const esSuperadmin = auth.isAuthenticated && auth.userRole === ROLE_SUPERADMIN
  // Sesión de otro rol (misma cookie que la app: un docente, un comercial…): Central no es para él
  if (auth.isAuthenticated && !esSuperadmin) {
    await auth.logout()
    return to.name === 'acceso' ? true : { name: 'acceso', query: { motivo: 'rol' } }
  }
  if (to.meta.requiresAuth && !esSuperadmin) return { name: 'acceso' }
  if (to.meta.soloInvitado && esSuperadmin) return { name: 'inicio' }
  document.title = 'Central DuaLab'
  return true
})

export default router
