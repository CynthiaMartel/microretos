import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore, ROLE_COMERCIAL, ROLE_SUPERADMIN } from '../src/stores/auth.js'
import { iniciarSesion } from './offline/sesionLocal.js'

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
    // Entrevista guardada en la tablet sin conexión (offline/entrevistasLocales.js)
    { path: '/entrevista/local/:clave', name: 'entrevista-local', component: () => import('./views/Entrevista.vue'), props: true, meta: { requiresAuth: true } },
    { path: '/entrevista/:id(\\d+)', name: 'entrevista', component: () => import('./views/Entrevista.vue'), props: true, meta: { requiresAuth: true } },
    // Entrevista a centros educativos (mismos datos que el formulario de centros de la web)
    // seccion: 'centros' → la app toma el azul de centros (App.vue); el resto, el verde de empresas
    { path: '/centros', name: 'mis-centros', component: () => import('./views/MisCentros.vue'), meta: { requiresAuth: true, seccion: 'centros' } },
    { path: '/centros/nuevo', name: 'centro-nuevo', component: () => import('./views/EntrevistaCentro.vue'), meta: { requiresAuth: true, seccion: 'centros' } },
    // Misma vista «Mi usuario» que la app principal (nombre y contraseña); el panel de módulos no sale al comercial
    { path: '/mi-usuario', name: 'mi-usuario', component: () => import('../src/views/MiUsuario.vue'), meta: { requiresAuth: true } },
    // Formulario que la empresa responde sin cuenta desde el enlace que le envía el comercial
    // por correo (EnviarFormulario). Pública: ni sesión ni chrome de la app.
    { path: '/formulario/:token([A-Za-z0-9]{64})', name: 'formulario-empresa', component: () => import('./views/FormularioEmpresa.vue'), props: true, meta: { publica: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  // La empresa que abre su enlace no tiene cuenta: no se comprueba ni se toca ninguna sesión
  if (to.meta.publica) return true

  const auth = useAuthStore()
  // Como auth.init(), pero sin conexión entra con la sesión recordada en la tablet
  await iniciarSesion(auth)

  const puedeUsarla = auth.isAuthenticated && ROLES_APP.includes(auth.userRole)
  // Sesión de otro rol (p. ej. un docente que entra aquí con su cuenta): esta app no es para él
  if (auth.isAuthenticated && !puedeUsarla) {
    await auth.logout()
    return to.name === 'acceso' ? true : { name: 'acceso', query: { motivo: 'rol' } }
  }
  if (to.meta.requiresAuth && !puedeUsarla) return { name: 'acceso' }
  if (to.meta.soloInvitado && puedeUsarla) return { name: 'mis-entrevistas' }
  return true
})

export default router
