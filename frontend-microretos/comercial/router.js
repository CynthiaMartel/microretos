import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore, ROLE_COMERCIAL, ROLE_SUPERADMIN } from '../src/stores/auth.js'

// Solo el comercial (y el superadmin, que la supervisa) usa esta app. El guard es experiencia
// de usuario: lo que protege los datos es el middleware 'comercial' y EmpresaPolicy en el backend.
const ROLES_APP = [ROLE_COMERCIAL, ROLE_SUPERADMIN]

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/acceso', name: 'acceso', component: () => import('./views/AccesoComercial.vue'), meta: { soloInvitado: true } },
    { path: '/', name: 'mis-entrevistas', component: () => import('./views/MisEntrevistas.vue'), meta: { requiresAuth: true } },
    // Ruta específica antes de la paramétrica
    { path: '/entrevista/nueva', name: 'entrevista-nueva', component: () => import('./views/Entrevista.vue'), meta: { requiresAuth: true } },
    { path: '/entrevista/:id(\\d+)', name: 'entrevista', component: () => import('./views/Entrevista.vue'), props: true, meta: { requiresAuth: true } },
    // Misma vista «Mi usuario» que la app principal (nombre y contraseña); el panel de módulos no sale al comercial
    { path: '/mi-usuario', name: 'mi-usuario', component: () => import('../src/views/MiUsuario.vue'), meta: { requiresAuth: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.isInitialized) await auth.init()

  const puedeUsarla = auth.isAuthenticated && ROLES_APP.includes(auth.userRole)
  // Sesión de otro rol (p. ej. docente con la misma cookie): esta app no es para él
  if (auth.isAuthenticated && !puedeUsarla) {
    await auth.logout()
    return to.name === 'acceso' ? true : { name: 'acceso', query: { motivo: 'rol' } }
  }
  if (to.meta.requiresAuth && !puedeUsarla) return { name: 'acceso' }
  if (to.meta.soloInvitado && puedeUsarla) return { name: 'mis-entrevistas' }
  return true
})

export default router
