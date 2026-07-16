import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api.js'

// Duración del token en minutos (debe coincidir con config/sanctum.php → expiration)
const TOKEN_DURATION_MINUTES = 1440

export const ROLE_SUPERADMIN = 1
export const ROLE_DOCENTE    = 2
export const ROLE_EMPRESA    = 3
export const ROLE_ADMIN      = 4

// Rutas permitidas por rol (nombre de ruta de Vue Router)
export const ROLE_ROUTES = {
  [ROLE_SUPERADMIN]: ['microretos', 'biblioteca', 'detalle-microreto', 'dashboard-docente',
                      'encuentros-registrados', 'startup-day', 'startup-day-crear',
                      'startup-day-editar', 'startup-day-detalle', 'base-datos', 'papelera',
                      'empresas', 'gestion-usuarios', 'inicio-docente', 'mi-usuario'],
  [ROLE_ADMIN]:      ['microretos', 'biblioteca', 'detalle-microreto', 'dashboard-docente',
                      'encuentros-registrados', 'startup-day', 'startup-day-crear',
                      'startup-day-editar', 'startup-day-detalle', 'gestion-usuarios',
                      'papelera', 'inicio-docente', 'mi-usuario'],
  [ROLE_DOCENTE]:    ['microretos', 'biblioteca', 'detalle-microreto', 'dashboard-docente',
                      'encuentros-registrados', 'startup-day', 'startup-day-crear',
                      'startup-day-editar', 'startup-day-detalle', 'empresas', 'inicio-docente',
                      'mi-usuario'],
  [ROLE_EMPRESA]:    ['biblioteca', 'detalle-microreto',
                      'startup-day', 'startup-day-detalle', 'mi-usuario'],
}

export const useAuthStore = defineStore('auth', () => {
  // Limpiar token caducado o sin timestamp al inicializar la store
  const _initToken     = localStorage.getItem('admin_token')
  const _initCreatedAt = Number(localStorage.getItem('admin_token_created_at') || 0)
  const _isExpiredOnLoad = _initToken && (!_initCreatedAt ||
    (Date.now() - _initCreatedAt) / 1000 / 60 >= TOKEN_DURATION_MINUTES)
  if (_isExpiredOnLoad) {
    localStorage.removeItem('admin_token')
    localStorage.removeItem('admin_token_created_at')
    localStorage.removeItem('user_role')
    localStorage.removeItem('user_name')
    localStorage.removeItem('user_centro_id')
    localStorage.removeItem('user_centro_nombre')
    localStorage.removeItem('user_centro_img')
  }

  const isAuthenticated  = ref(!!localStorage.getItem('admin_token'))
  const userRole         = ref(Number(localStorage.getItem('user_role') || ROLE_SUPERADMIN))
  const userName         = ref(localStorage.getItem('user_name') || 'Administrador')
  const userCentroId     = ref(Number(localStorage.getItem('user_centro_id') || 0) || null)
  const userCentroNombre = ref(localStorage.getItem('user_centro_nombre') || '')
  const userCentroImg    = ref(localStorage.getItem('user_centro_img') || '')
  const refreshing       = ref(false)

  // Reloj reactivo: se actualiza cada minuto
  const now = ref(Date.now())
  setInterval(() => { now.value = Date.now() }, 60_000)

  const onTokenExpired = () => { isAuthenticated.value = false }
  window.addEventListener('auth:token-expired', onTokenExpired)

  const minutosRestantes = computed(() => {
    const createdAt = localStorage.getItem('admin_token_created_at')
    if (!createdAt || !isAuthenticated.value) return -1
    const elapsed = (now.value - Number(createdAt)) / 1000 / 60
    return Math.max(0, Math.floor(TOKEN_DURATION_MINUTES - elapsed))
  })

  const isSuperAdmin = computed(() => userRole.value === ROLE_SUPERADMIN)
  const isAdmin      = computed(() => userRole.value === ROLE_ADMIN)
  const isDocente    = computed(() => userRole.value === ROLE_DOCENTE)
  const isEmpresa    = computed(() => userRole.value === ROLE_EMPRESA)

  const roleLabel = computed(() => {
    if (userRole.value === ROLE_DOCENTE)    return 'Docente'
    if (userRole.value === ROLE_EMPRESA)    return 'Empresa'
    if (userRole.value === ROLE_ADMIN)      return 'Administrador'
    return 'Superadministrador'
  })

  const canAccess = (routeName) => {
    const allowed = ROLE_ROUTES[userRole.value] ?? []
    return allowed.includes(routeName)
  }

  const login = (token, role = ROLE_SUPERADMIN, name = 'Administrador', centroId = null, centroNombre = '', centroImg = '') => {
    localStorage.setItem('admin_token', token)
    localStorage.setItem('admin_token_created_at', String(Date.now()))
    localStorage.setItem('user_role', String(role))
    localStorage.setItem('user_name', name)
    if (centroId) localStorage.setItem('user_centro_id', String(centroId))
    else          localStorage.removeItem('user_centro_id')
    if (centroNombre) localStorage.setItem('user_centro_nombre', centroNombre)
    else              localStorage.removeItem('user_centro_nombre')
    if (centroImg) localStorage.setItem('user_centro_img', centroImg)
    else           localStorage.removeItem('user_centro_img')
    // Inicializar el timer de seguridad de BD: el login cuenta como verificación
    sessionStorage.setItem('db_security_verified_at', String(Date.now()))
    isAuthenticated.value  = true
    userRole.value         = role
    userName.value         = name
    userCentroId.value     = centroId
    userCentroNombre.value = centroNombre || ''
    userCentroImg.value    = centroImg || ''
  }

  const logout = () => {
    localStorage.removeItem('admin_token')
    localStorage.removeItem('admin_token_created_at')
    localStorage.removeItem('user_role')
    localStorage.removeItem('user_name')
    localStorage.removeItem('user_centro_id')
    localStorage.removeItem('user_centro_nombre')
    localStorage.removeItem('user_centro_img')
    isAuthenticated.value  = false
    userRole.value         = ROLE_SUPERADMIN
    userName.value         = 'Administrador'
    userCentroId.value     = null
    userCentroNombre.value = ''
    userCentroImg.value    = ''
  }

  const updateName = (name) => {
    userName.value = name
    localStorage.setItem('user_name', name)
  }

  const updateCentroImg = (img) => {
    userCentroImg.value = img || ''
    if (img) localStorage.setItem('user_centro_img', img)
    else     localStorage.removeItem('user_centro_img')
  }

  const refresh = async () => {
    if (refreshing.value) return false
    refreshing.value = true
    try {
      const { data } = await api.post('/admin/refresh')
      if (data.role !== undefined && data.role !== userRole.value) {
        logout()
        return false
      }
      localStorage.setItem('admin_token', data.token)
      localStorage.setItem('admin_token_created_at', String(Date.now()))
      isAuthenticated.value = true
      return true
    } catch {
      return false
    } finally {
      refreshing.value = false
    }
  }

  return {
    isAuthenticated, userRole, userName, userCentroId, userCentroNombre, userCentroImg, refreshing,
    isSuperAdmin, isAdmin, isDocente, isEmpresa, roleLabel,
    minutosRestantes, login, logout, refresh, updateName, updateCentroImg, canAccess,
  }
})
