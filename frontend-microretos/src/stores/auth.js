import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api.js'

// Sin sesión (o rol desconocido): cero permisos — nunca usar SUPERADMIN como fallback.
export const ROLE_NONE       = 0
export const ROLE_SUPERADMIN = 1
export const ROLE_DOCENTE    = 2
export const ROLE_EMPRESA    = 3
export const ROLE_ADMIN      = 4

// Rutas permitidas por rol (nombre de ruta de Vue Router).
// 'mis-equipos'/'mis-equipos-detalle': antes 'mis-grupos'/'mis-grupos-detalle' — renombradas
// porque "grupo" ya significa otra cosa en el dominio (Encuentro.grupo = clase/curso, ej. "2ºB").
export const ROLE_ROUTES = {
  [ROLE_SUPERADMIN]: ['microretos', 'biblioteca', 'detalle-microreto', 'dashboard-docente',
                      'encuentros-registrados', 'mis-equipos-detalle', 'mis-equipos',
                      'pantalla-acceso', 'pantalla-acceso-lista', 'startup-day', 'startup-day-crear',
                      'startup-day-editar', 'startup-day-detalle', 'base-datos', 'papelera',
                      'empresas', 'gestion-usuarios', 'inicio-docente', 'mi-usuario'],
  [ROLE_ADMIN]:      ['microretos', 'biblioteca', 'detalle-microreto', 'dashboard-docente',
                      'encuentros-registrados', 'mis-equipos-detalle', 'mis-equipos',
                      'pantalla-acceso', 'pantalla-acceso-lista', 'startup-day', 'startup-day-crear',
                      'startup-day-editar', 'startup-day-detalle', 'gestion-usuarios',
                      'inicio-docente', 'mi-usuario', 'empresas'],
  [ROLE_DOCENTE]:    ['microretos', 'biblioteca', 'detalle-microreto', 'dashboard-docente',
                      'encuentros-registrados', 'mis-equipos-detalle', 'mis-equipos',
                      'pantalla-acceso', 'pantalla-acceso-lista', 'startup-day', 'startup-day-crear',
                      'startup-day-editar', 'startup-day-detalle', 'empresas', 'inicio-docente',
                      'mi-usuario'],
  [ROLE_EMPRESA]:    ['biblioteca', 'detalle-microreto',
                      'startup-day', 'startup-day-detalle', 'mi-usuario'],
}

export const useAuthStore = defineStore('auth', () => {
  // La sesión vive en una cookie HttpOnly (Sanctum stateful) — JS no puede leerla.
  // isInitialized distingue "todavía no hemos preguntado al backend" de "preguntamos
  // y no hay sesión", para que el guard del router sepa cuándo puede decidir.
  const isInitialized   = ref(false)
  const isAuthenticated = ref(false)
  const userRole         = ref(ROLE_NONE)
  const userName         = ref('Administrador')
  const userCentroId     = ref(null)
  const userCentroNombre = ref('')
  const userCentroImg    = ref('')

  // La sesión de Laravel expira 'minutos' después del último request que la tocó
  // (sliding, no un instante fijo), así que el backend manda en cada /perfil los
  // minutos restantes EN ESE momento. Para no repreguntar cada minuto solo para
  // hacer bajar un contador, se ancla aquí (minutosRestantesServidor + medidoEn) y
  // se descuenta localmente con un reloj que tickea cada minuto.
  const minutosRestantesServidor = ref(-1)
  const medidoEn                 = ref(0)
  const now                      = ref(Date.now())
  setInterval(() => { now.value = Date.now() }, 60_000)

  const minutosRestantes = computed(() => {
    if (!isAuthenticated.value || minutosRestantesServidor.value < 0) return -1
    const transcurridos = (now.value - medidoEn.value) / 1000 / 60
    return Math.max(0, Math.floor(minutosRestantesServidor.value - transcurridos))
  })

  const clearLocalSession = () => {
    isAuthenticated.value  = false
    userRole.value         = ROLE_NONE
    userName.value         = 'Administrador'
    userCentroId.value     = null
    userCentroNombre.value = ''
    userCentroImg.value    = ''
    minutosRestantesServidor.value = -1
  }

  // data = payload de /perfil (data.data) o del login — misma forma en ambos.
  const hidratar = (data) => {
    isAuthenticated.value  = true
    userRole.value         = Number(data.role ?? ROLE_NONE)
    userName.value         = data.name || 'Administrador'
    userCentroId.value     = data.centro_educativo_id ?? null
    userCentroNombre.value = data.centro_nombre || ''
    userCentroImg.value    = data.centro_img || ''
    minutosRestantesServidor.value = Number(data.minutos_restantes ?? -1)
    medidoEn.value = Date.now()
  }

  // Compartido entre logout() y la sesión caducada detectada por el interceptor de
  // axios (api.js dispara 'auth:token-expired' en cualquier 401 no marcado como skip).
  const onSessionExpired = () => { clearLocalSession() }
  window.addEventListener('auth:token-expired', onSessionExpired)

  const isSuperAdmin = computed(() => userRole.value === ROLE_SUPERADMIN)
  const isAdmin      = computed(() => userRole.value === ROLE_ADMIN)
  const isDocente    = computed(() => userRole.value === ROLE_DOCENTE)
  const isEmpresa    = computed(() => userRole.value === ROLE_EMPRESA)

  const roleLabel = computed(() => {
    if (userRole.value === ROLE_DOCENTE)    return 'Docente'
    if (userRole.value === ROLE_EMPRESA)    return 'Empresa'
    if (userRole.value === ROLE_ADMIN)      return 'Administrador'
    if (userRole.value === ROLE_SUPERADMIN) return 'Superadministrador'
    return ''
  })

  // Sin sesión, nunca hay acceso — evita que el menú muestre secciones de
  // administración a un visitante anónimo solo porque el rol por defecto sea permisivo.
  const canAccess = (routeName) => {
    if (!isAuthenticated.value) return false
    const allowed = ROLE_ROUTES[userRole.value] ?? []
    return allowed.includes(routeName)
  }

  // Llamada al arrancar la app (App.vue en onMounted, y también el guard del router
  // en la primera navegación a una ruta protegida — lo que dispare primero): pregunta
  // al backend si la cookie de sesión sigue siendo válida. Un 401 aquí es esperado
  // (visitante sin sesión) y no es un error. initPromise evita disparar /perfil dos
  // veces si ambos sitios llaman a init() casi a la vez.
  let initPromise = null
  const init = () => {
    if (isInitialized.value) return Promise.resolve()
    if (!initPromise) {
      initPromise = (async () => {
        try {
          // skipAuthRedirect: un 401 aquí es "todavía no hay sesión", no una sesión
          // que acaba de expirar — no debe disparar 'auth:token-expired' (evita
          // además un bucle con logout(), que también llama al backend).
          const { data } = await api.get('/perfil', { skipAuthRedirect: true })
          hidratar(data.data)
        } catch {
          clearLocalSession()
        } finally {
          isInitialized.value = true
        }
      })()
    }
    return initPromise
  }

  const login = (data) => {
    hidratar(data)
    // Inicializar el timer de seguridad de BD: el login cuenta como verificación
    sessionStorage.setItem('db_security_verified_at', String(Date.now()))
    isInitialized.value = true
  }

  // Best-effort: si la llamada falla (p. ej. la sesión ya había caducado en el
  // servidor), se limpia igualmente el estado local — nunca dejar al usuario
  // atrapado en una sesión que la UI ya no puede cerrar. skipAuthRedirect: un 401
  // aquí no debe volver a disparar 'auth:token-expired' (evitaría un bucle, ya que
  // ese evento es precisamente lo que puede haber llamado a logout() en primer lugar).
  const logout = async () => {
    try {
      await api.post('/admin/logout', {}, { skipAuthRedirect: true })
    } catch { /* sesión ya inválida en el servidor, no pasa nada */ }
    clearLocalSession()
  }

  const updateName = (name) => { userName.value = name }

  const updateCentroImg = (img) => { userCentroImg.value = img || '' }

  // "Sigo conectado" tras el aviso de inactividad: una request autenticada cualquiera
  // desliza la sesión de Laravel (last_activity) — no hace falta rotar nada a mano.
  const ping = async () => {
    try {
      const { data } = await api.get('/perfil')
      hidratar(data.data)
      return true
    } catch {
      return false
    }
  }

  return {
    isInitialized, isAuthenticated, userRole, userName, userCentroId, userCentroNombre, userCentroImg,
    minutosRestantes,
    isSuperAdmin, isAdmin, isDocente, isEmpresa, roleLabel,
    init, login, logout, ping, updateName, updateCentroImg, canAccess,
  }
})
