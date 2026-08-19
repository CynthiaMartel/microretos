<!-- Ruta: /usuarios (name: gestion-usuarios). Antes vivía en /admin/usuarios — ver router/index.js. Nota: el endpoint del backend sigue siendo /admin/usuarios, no confundir. -->
<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import api from '../api.js'
import { useAuthStore } from '../stores/auth.js'

const authStore = useAuthStore()

// ── Estado principal ────────────────────────────────────────────────
const usuarios    = ref([])
const papelera    = ref([])
const cargando    = ref(false)
const vista       = ref('activos')
const filtroRol   = ref(0)

// ── Modal: crear cuenta ─────────────────────────────────────────────
const modalCrear  = ref(false)
const creando     = ref(false)
const form        = ref({ name: '', email: '', password: '', password_confirmation: '', role: '2', centro_educativo_id: null, empresa_id: null })
const formErrors  = ref({})
const msgCrear    = ref('')

// ── Modal: éxito tras crear ─────────────────────────────────────────
const modalExito       = ref(false)
const cuentaRecienCreada = ref(null)   // el objeto usuario recién creado

// ── Puntero animado al botón Activar ───────────────────────────────
const highlightId      = ref(null)     // id del usuario a destacar
const activarBtns      = ref({})       // map id → el DOM del botón
const tooltipStyle     = ref({})
let   highlightTimer   = null

// ── Modal: editar cuenta ────────────────────────────────────────────
const modalEditar        = ref(false)
const editando           = ref(false)
const usuarioEditando    = ref(null)
const formEditar         = ref({ name: '', email: '', password: '', password_confirmation: '', role: '2', centro_educativo_id: null, empresa_id: null })
const confirmarCambioPassword = ref(false)   // switch que el admin debe marcar para autorizar el reseteo de contraseña

// ── Requisitos de contraseña (mismos que valida el backend: min 8, mayúscula, minúscula, número, símbolo) ──
function evaluarPassword(pwd) {
  return [
    { label: 'Mínimo 8 caracteres',  ok: pwd.length >= 8 },
    { label: 'Una letra mayúscula',  ok: /[A-Z]/.test(pwd) },
    { label: 'Una letra minúscula',  ok: /[a-z]/.test(pwd) },
    { label: 'Un número',            ok: /[0-9]/.test(pwd) },
    { label: 'Un carácter especial (!@#$…)', ok: /[^A-Za-z0-9]/.test(pwd) },
  ]
}
const passwordRequisitos       = computed(() => evaluarPassword(form.value.password || ''))
const passwordRequisitosEditar = computed(() => evaluarPassword(formEditar.value.password || ''))
const formEmailLocal       = ref('')   // parte antes del @ en el form crear
const formEditarEmailLocal = ref('')   // parte antes del @ en el form editar
const formEditarErrors   = ref({})
const msgEditar          = ref('')

async function abrirModalEditar(usuario) {
  usuarioEditando.value      = usuario
  formEditarEmailLocal.value = usuario.email?.includes('@') ? usuario.email.split('@')[0] : (usuario.email || '')
  formEditar.value           = {
    name:                 usuario.name,
    email:                usuario.email,
    password:             '',
    password_confirmation: '',
    role:                 String(usuario.role),
    centro_educativo_id:  usuario.centro_educativo_id ?? null,
    empresa_id:           usuario.empresa_id ?? null,
  }
  confirmarCambioPassword.value = false
  formEditarErrors.value = {}
  msgEditar.value        = ''
  modalEditar.value      = true

  if (authStore.isSuperAdmin) {
    await Promise.all([
      centros.value.length === 0 ? api.get('/centros').then(r => { centros.value = r.data }) : Promise.resolve(),
      empresasList.value.length === 0 ? api.get('/empresas').then(r => { empresasList.value = r.data }) : Promise.resolve(),
    ])
  }
}

async function guardarEdicion() {
  formEditarErrors.value = {}
  msgEditar.value        = ''

  if (authStore.isSuperAdmin && ['2', '4'].includes(formEditar.value.role) && !formEditar.value.centro_educativo_id) {
    formEditarErrors.value.centro_educativo_id = 'Debes asignar un centro educativo.'
    return
  }
  if (authStore.isSuperAdmin && formEditar.value.role === '3' && !formEditar.value.empresa_id) {
    formEditarErrors.value.empresa_id = 'Debes asignar una empresa.'
    return
  }

  if (formEditar.value.password) {
    if (formEditar.value.password !== formEditar.value.password_confirmation) {
      formEditarErrors.value.password_confirmation = 'Las contraseñas no coinciden.'
      return
    }
    if (!confirmarCambioPassword.value) {
      formEditarErrors.value.password = 'Debes confirmar que quieres cambiar la contraseña de esta cuenta.'
      return
    }
  }

  editando.value         = true
  try {
    const payload = {
      name:  formEditar.value.name,
      email: formEditar.value.email,
      role:  formEditar.value.role,
    }
    if (formEditar.value.password) {
      payload.password              = formEditar.value.password
      payload.password_confirmation = formEditar.value.password_confirmation
      payload.confirm_password_change = true
    }
    if (authStore.isSuperAdmin && ['2', '4'].includes(formEditar.value.role)) {
      payload.centro_educativo_id = formEditar.value.centro_educativo_id
    }
    if (authStore.isSuperAdmin && formEditar.value.role === '3') {
      payload.empresa_id = formEditar.value.empresa_id
    }
    const { data } = await api.patch(`/admin/usuarios/${usuarioEditando.value.id}`, payload)
    reemplazar(usuarios.value, data.data)
    modalEditar.value = false
    mostrarToast(
      payload.password
        ? 'Cuenta actualizada. Contraseña cambiada correctamente.'
        : 'Cuenta actualizada correctamente.'
    )
  } catch (e) {
    const status = e.response?.status

    if (status === 422) {
      const errs = e.response.data.errors ?? {}
      formEditarErrors.value = Object.fromEntries(
        Object.entries(errs).map(([k, v]) => [k, v[0]])
      )
      // Si el fallo de validación viene del bloque de contraseña, dejarlo explícito
      // además del error en el campo concreto (el switch queda lejos del mensaje genérico)
      if (payload.password && (
        formEditarErrors.value.password ||
        formEditarErrors.value.password_confirmation ||
        formEditarErrors.value.confirm_password_change
      )) {
        msgEditar.value = 'No se ha podido cambiar la contraseña. Revisa los campos marcados en rojo.'
      }
    } else if (status === 403) {
      msgEditar.value = e.response?.data?.message || 'No tienes permiso para realizar este cambio.'
    } else if (status === 429) {
      msgEditar.value = e.response?.data?.message || 'Demasiados intentos. Inténtalo más tarde.'
    } else if (!e.response) {
      msgEditar.value = 'Error de conexión. Inténtalo más tarde.'
    } else {
      msgEditar.value = e.response?.data?.message ?? 'Error al actualizar la cuenta.'
    }
  } finally {
    editando.value = false
  }
}

// ── Modal: asociar centro ───────────────────────────────────────────
const modalCentro       = ref(false)
const usuarioCentro     = ref(null)    // usuario al que se asocia centro
const centros           = ref([])
const cargandoCentros   = ref(false)
const busquedaCentro    = ref('')
const asociandoCentro   = ref(false)

// ── Listas auxiliares en modales ────────────────────────────────────
const empresasList      = ref([])
const cargandoEmpresas  = ref(false)

// ── Confirmación + Toast ────────────────────────────────────────────
const confirm = ref({ show: false, title: '', body: '', action: null, danger: true })
const toast   = ref({ show: false, msg: '', ok: true })

// ── Computed ────────────────────────────────────────────────────────
const usuariosFiltrados = computed(() => {
  const lista = vista.value === 'papelera' ? papelera.value : usuarios.value
  if (filtroRol.value === 0) return lista
  return lista.filter(u => u.role === filtroRol.value)
})

const totalActivos  = computed(() => usuarios.value.length)
const totalDocentes = computed(() => usuarios.value.filter(u => u.role === 2 || u.role === 4).length)
const totalEmpresas = computed(() => usuarios.value.filter(u => u.role === 3).length)
const totalPapelera = computed(() => papelera.value.length)

function fuzzyNorm(s) {
  return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
}

const centrosFiltrados = computed(() => {
  if (!busquedaCentro.value.trim()) return centros.value
  const words = fuzzyNorm(busquedaCentro.value).split(/\s+/).filter(Boolean)
  return centros.value.filter(c =>
    words.every(w => fuzzyNorm(c.nombre).includes(w))
  )
})

// ── Email automático por centro ─────────────────────────────────────
function centroSlug(nombre) {
  return nombre
    .toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]/g, '')
}

// Dominio por centro educativo (docente/admin)
const emailDomain = computed(() => {
  // Admin de centro: el dominio es siempre el de su propio centro (no necesita la lista)
  if (authStore.isAdmin && authStore.userCentroNombre) {
    return centroSlug(authStore.userCentroNombre) + '.dualab'
  }
  const id = form.value.centro_educativo_id
  if (!id) return null
  const c = centros.value.find(c => c.id === id)
  return c ? centroSlug(c.nombre) + '.dualab' : null
})
const emailEditarDomain = computed(() => {
  if (authStore.isAdmin && authStore.userCentroNombre) {
    return centroSlug(authStore.userCentroNombre) + '.dualab'
  }
  const id = formEditar.value.centro_educativo_id
  if (!id) return null
  const c = centros.value.find(c => c.id === id)
  return c ? centroSlug(c.nombre) + '.dualab' : null
})

// Dominio por empresa (role 3)
const emailEmpresaDomain = computed(() => {
  const id = form.value.empresa_id
  if (!id) return null
  const e = empresasList.value.find(e => e.id === id)
  return e ? centroSlug(e.nombre_comercial) + '.dualab' : null
})
const emailEditarEmpresaDomain = computed(() => {
  const id = formEditar.value.empresa_id
  if (!id) return null
  const e = empresasList.value.find(e => e.id === id)
  return e ? centroSlug(e.nombre_comercial) + '.dualab' : null
})

// Dominio activo (centro tiene prioridad; si no hay, empresa)
const activeEmailDomain       = computed(() => emailDomain.value || emailEmpresaDomain.value)
const activeEmailEditarDomain = computed(() => emailEditarDomain.value || emailEditarEmpresaDomain.value)

// ── Watchers de composición de email ────────────────────────────────

// CREAR: cuando el dominio activo aparece/cambia → siembra local part desde el nombre
watch(activeEmailDomain, (domain) => {
  if (domain) {
    formEmailLocal.value = centroSlug(form.value.name) || formEmailLocal.value
    form.value.email = formEmailLocal.value + '@' + domain
  }
})
// CREAR: el usuario edita el local part → recompone el email
watch(formEmailLocal, (local) => {
  if (activeEmailDomain.value) form.value.email = local + '@' + activeEmailDomain.value
})
// CREAR: el nombre cambia con dominio activo → actualiza el local part
watch(() => form.value.name, (nombre) => {
  if (activeEmailDomain.value) formEmailLocal.value = centroSlug(nombre)
})

// EDITAR: cuando el dominio activo aparece/cambia → preserva local part o siembra desde email
watch(activeEmailEditarDomain, (domain) => {
  if (domain) {
    if (!formEditarEmailLocal.value && formEditar.value.email?.includes('@')) {
      formEditarEmailLocal.value = formEditar.value.email.split('@')[0]
    }
    formEditar.value.email = formEditarEmailLocal.value + '@' + domain
  }
})
// EDITAR: el usuario edita el local part → recompone el email
watch(formEditarEmailLocal, (local) => {
  if (activeEmailEditarDomain.value) formEditar.value.email = local + '@' + activeEmailEditarDomain.value
})

// ── API: usuarios ───────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  try {
    if (vista.value === 'papelera') {
      const { data } = await api.get('/admin/usuarios/papelera')
      papelera.value = data.data
    } else {
      const { data } = await api.get('/admin/usuarios')
      usuarios.value = data.data
    }
  } catch (e) {
    mostrarToast(e.response?.data?.message ?? 'Error al cargar las cuentas.', false)
  } finally {
    cargando.value = false
  }
}

async function crearUsuario() {
  formErrors.value = {}
  msgCrear.value   = ''

  if (authStore.isSuperAdmin && ['2', '4'].includes(form.value.role) && !form.value.centro_educativo_id) {
    formErrors.value.centro_educativo_id = 'Debes asignar un centro educativo antes de crear la cuenta.'
    return
  }
  if (authStore.isSuperAdmin && form.value.role === '3' && !form.value.empresa_id) {
    formErrors.value.empresa_id = 'Debes asignar una empresa antes de crear la cuenta.'
    return
  }
  if (form.value.password !== form.value.password_confirmation) {
    formErrors.value.password_confirmation = 'Las contraseñas no coinciden.'
    return
  }

  creando.value    = true
  try {
    const { data } = await api.post('/admin/usuarios', form.value)
    usuarios.value.push(data.data)
    modalCrear.value      = false
    cuentaRecienCreada.value = data.data
    form.value       = { name: '', email: '', password: '', password_confirmation: '', role: '2', centro_educativo_id: null, empresa_id: null }
    formEmailLocal.value = ''
    // Pequeño delay para que el DOM renderice la nueva fila antes del modal
    await nextTick()
    modalExito.value = true
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors ?? {}
      formErrors.value = Object.fromEntries(
        Object.entries(errs).map(([k, v]) => [k, v[0]])
      )
    } else {
      msgCrear.value = e.response?.data?.message ?? 'Error al crear la cuenta.'
    }
  } finally {
    creando.value = false
  }
}

async function abrirModalCrear() {
  form.value = { name: '', email: '', password: '', password_confirmation: '', role: '2', centro_educativo_id: null, empresa_id: null }
  formEmailLocal.value = ''
  formErrors.value = {}
  msgCrear.value = ''
  modalCrear.value = true
  if (authStore.isSuperAdmin) {
    const [, ] = await Promise.all([
      centros.value.length === 0 ? api.get('/centros').then(r => { centros.value = r.data }) : Promise.resolve(),
      empresasList.value.length === 0 ? api.get('/empresas').then(r => { empresasList.value = r.data }) : Promise.resolve(),
    ])
  }
}

function cerrarModalExito() {
  modalExito.value = false
  // Lanzar el puntero al botón Activar de la nueva cuenta
  if (cuentaRecienCreada.value && !cuentaRecienCreada.value.is_active) {
    activarHighlight(cuentaRecienCreada.value.id)
  }
}

// ── Puntero animado ─────────────────────────────────────────────────
function activarHighlight(id) {
  if (highlightTimer) clearTimeout(highlightTimer)
  highlightId.value = id

  // Calcular posición del tooltip sobre el botón
  nextTick(() => {
    const btn = activarBtns.value[id]
    if (btn) {
      const rect = btn.getBoundingClientRect()
      tooltipStyle.value = {
        top:  `${rect.top + window.scrollY - 52}px`,
        left: `${rect.left + rect.width / 2}px`,
      }
      btn.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  })

  highlightTimer = setTimeout(() => {
    highlightId.value = null
  }, 7000)
}

function onActivarClick(usuario) {
  if (highlightId.value === usuario.id) {
    highlightId.value = null
    if (highlightTimer) clearTimeout(highlightTimer)
  }
  activar(usuario)
}

// ── API: acciones sobre usuarios ────────────────────────────────────
async function activar(usuario) {
  try {
    const { data } = await api.patch(`/admin/usuarios/${usuario.id}/activar`)
    reemplazar(usuarios.value, data.data)
    mostrarToast('Cuenta activada correctamente.')
  } catch (e) {
    mostrarToast(e.response?.data?.message ?? 'Error al activar.', false)
  }
}

async function activarEnModal() {
  if (!usuarioEditando.value) return
  try {
    const { data } = await api.patch(`/admin/usuarios/${usuarioEditando.value.id}/activar`)
    reemplazar(usuarios.value, data.data)
    usuarioEditando.value = data.data
    mostrarToast('Cuenta activada correctamente.')
  } catch (e) {
    mostrarToast(e.response?.data?.message ?? 'Error al activar.', false)
  }
}

async function toggleBloquear(usuario) {
  const accion = usuario.is_blocked ? 'desbloquear' : 'bloquear'
  pedirConfirmacion(
    `${accion.charAt(0).toUpperCase() + accion.slice(1)} cuenta`,
    `¿Seguro que quieres ${accion} la cuenta de ${usuario.name}?`,
    async () => {
      const { data } = await api.patch(`/admin/usuarios/${usuario.id}/bloquear`)
      reemplazar(usuarios.value, data.data)
      mostrarToast(`Cuenta ${data.data.is_blocked ? 'bloqueada' : 'desbloqueada'}.`)
    },
    !usuario.is_blocked
  )
}

async function enviarPapelera(usuario) {
  pedirConfirmacion(
    'Eliminar cuenta',
    `¿Enviar a la papelera la cuenta de ${usuario.name}? Podrás restaurarla después.`,
    async () => {
      await api.delete(`/admin/usuarios/${usuario.id}`)
      usuarios.value = usuarios.value.filter(u => u.id !== usuario.id)
      mostrarToast('Cuenta enviada a la papelera.')
    },
    true
  )
}

async function restaurar(usuario) {
  try {
    const { data } = await api.post(`/admin/usuarios/${usuario.id}/restaurar`)
    papelera.value  = papelera.value.filter(u => u.id !== usuario.id)
    usuarios.value.push(data.data)
    mostrarToast('Cuenta restaurada.')
  } catch (e) {
    mostrarToast(e.response?.data?.message ?? 'Error al restaurar.', false)
  }
}

async function destruir(usuario) {
  pedirConfirmacion(
    'Eliminación definitiva',
    `Esta acción es irreversible. ¿Eliminar permanentemente la cuenta de ${usuario.name}?`,
    async () => {
      await api.delete(`/admin/usuarios/${usuario.id}/destruir`)
      papelera.value = papelera.value.filter(u => u.id !== usuario.id)
      mostrarToast('Cuenta eliminada definitivamente.')
    },
    true
  )
}

// ── Centros: búsqueda y asociación ──────────────────────────────────
async function abrirModalCentro(usuario) {
  usuarioCentro.value  = usuario
  busquedaCentro.value = ''
  modalCentro.value    = true

  if (centros.value.length === 0) {
    cargandoCentros.value = true
    try {
      const { data } = await api.get('/centros')
      centros.value = data
    } finally {
      cargandoCentros.value = false
    }
  }
}

async function seleccionarCentro(centro) {
  if (!usuarioCentro.value) return
  asociandoCentro.value = true
  try {
    const { data } = await api.patch(
      `/admin/usuarios/${usuarioCentro.value.id}/centro`,
      { centro_educativo_id: centro ? centro.id : null }
    )
    reemplazar(usuarios.value, data.data)
    usuarioCentro.value = data.data
    mostrarToast(`Centro ${centro ? `"${centro.nombre}" asociado` : 'desvinculado'} correctamente.`)
    if (!centro) {
      modalCentro.value = false
    }
  } catch (e) {
    mostrarToast(e.response?.data?.message ?? 'Error al asociar el centro.', false)
  } finally {
    asociandoCentro.value = false
  }
}

async function desvincularCentro() {
  pedirConfirmacion(
    'Desvincular centro',
    `¿Quitar el centro educativo de ${usuarioCentro.value?.name}?`,
    () => seleccionarCentro(null),
    false
  )
}

// ── Helpers ─────────────────────────────────────────────────────────
function reemplazar(lista, item) {
  const idx = lista.findIndex(u => u.id === item.id)
  if (idx !== -1) lista[idx] = item
}

function pedirConfirmacion(title, body, action, danger = true) {
  confirm.value = { show: true, title, body, action, danger }
}

async function ejecutarConfirm() {
  try {
    await confirm.value.action()
  } catch (e) {
    mostrarToast(e.response?.data?.message ?? 'Error.', false)
  } finally {
    confirm.value.show = false
  }
}

function mostrarToast(msg, ok = true) {
  toast.value = { show: true, msg, ok }
  setTimeout(() => { toast.value.show = false }, 3500)
}

function cambiarVista(v) {
  vista.value     = v
  filtroRol.value = 0
  cargar()
}

// ── Formato ─────────────────────────────────────────────────────────
const ROLE_COLORS = {
  2: { bg: 'bg-blue-50 text-blue-600 border-blue-200',       label: 'Docente' },
  3: { bg: 'bg-amber-50 text-amber-600 border-amber-200',    label: 'Empresa' },
  4: { bg: 'bg-purple-50 text-purple-600 border-purple-200', label: 'Admin' },
}
function roleChip(role) {
  return ROLE_COLORS[role] ?? { bg: 'bg-gray-100 text-gray-500 border-gray-200', label: 'Superadmin' }
}
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(async () => {
  cargando.value = true
  try {
    const [resActivos, resPapelera] = await Promise.all([
      api.get('/admin/usuarios'),
      api.get('/admin/usuarios/papelera'),
    ])
    usuarios.value = resActivos.data.data
    papelera.value = resPapelera.data.data
  } catch (e) {
    mostrarToast(e.response?.data?.message ?? 'Error al cargar las cuentas.', false)
  } finally {
    cargando.value = false
  }
})
</script>

<template>
  <div class="min-h-screen text-[#121212] px-4 py-8 lg:px-8 pt-12 md:pt-12">

    <!-- ── Cabecera ────────────────────────────────────────── -->
    <div class="max-w-5xl mx-auto mb-8 flex flex-col sm:flex-row sm:items-end gap-4">
      <div class="flex-1">
        <p class="text-[10px] font-black uppercase tracking-[0.25em] text-[#00A859] mb-1">Administración</p>
        <h1 class="text-2xl font-black tracking-tight">Gestión de cuentas</h1>
        <p class="text-sm text-gray-500 mt-1">Crea, activa, bloquea y elimina cuentas de docentes y empresas.</p>
      </div>
      <button
        @click="abrirModalCrear()"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#00A859] hover:bg-[#009950]
               text-white font-black text-xs uppercase tracking-widest transition-all shrink-0"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/>
        </svg>
        Nueva cuenta
      </button>
    </div>

    <!-- ── Contadores ───────────────────────────────────────── -->
    <div v-if="!cargando" class="max-w-5xl mx-auto mb-5 flex flex-wrap gap-2">
      <!-- Total activos -->
      <div class="flex items-center gap-2 px-3 py-1.5 bg-white rounded-2xl border border-gray-200">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 100 8 4 4 0 000-8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
        <span class="font-black text-xl text-[#121212]">{{ totalActivos }}</span>
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">cuentas</span>
      </div>
      <!-- Docentes -->
      <div class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 rounded-2xl border border-blue-200">
        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 3h6v4H9V3zM9 12l2 2 4-4"/>
        </svg>
        <span class="font-black text-xl text-blue-600">{{ totalDocentes }}</span>
        <span class="text-xs font-semibold text-blue-400 uppercase tracking-wider">docentes</span>
      </div>
      <!-- Empresas -->
      <div class="flex items-center gap-2 px-3 py-1.5 bg-amber-50 rounded-2xl border border-amber-200">
        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
        </svg>
        <span class="font-black text-xl text-amber-600">{{ totalEmpresas }}</span>
        <span class="text-xs font-semibold text-amber-400 uppercase tracking-wider">empresas</span>
      </div>
      <!-- Papelera -->
      <div v-if="totalPapelera > 0" class="flex items-center gap-2 px-3 py-1.5 bg-red-50 rounded-2xl border border-red-200">
        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <polyline points="3 6 5 6 21 6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6M10 11v6M14 11v6M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
        </svg>
        <span class="font-black text-xl text-red-500">{{ totalPapelera }}</span>
        <span class="text-xs font-semibold text-red-400 uppercase tracking-wider">En Papelera</span>
      </div>
    </div>

    <!-- ── Tabs + filtros ─────────────────────────────────── -->
    <div class="max-w-5xl mx-auto mb-5 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
      <div class="flex gap-1 p-1 bg-gray-100 border border-gray-200 rounded-xl">
        <button @click="cambiarVista('activos')"
          :class="vista === 'activos' ? 'bg-[#00A859]/20 text-[#00A859] border border-[#00A859]/40' : 'text-gray-400 hover:text-gray-600'"
          class="px-4 py-1.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all">
          Activos
        </button>
        <button @click="cambiarVista('papelera')"
          :class="vista === 'papelera' ? 'bg-red-100 text-red-600 border border-red-300' : 'text-gray-400 hover:text-gray-600'"
          class="px-4 py-1.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
          </svg>
          Papelera
        </button>
      </div>
      <div class="flex gap-1 p-1 bg-gray-100 border border-gray-200 rounded-xl">
        <button v-for="f in [
            { val: 0, label: 'Todos' },
            { val: 2, label: 'Docentes' },
            ...(authStore.isSuperAdmin ? [{ val: 4, label: 'Admins Docentes' }, { val: 3, label: 'Empresas' }] : [])
          ]"
          :key="f.val" @click="filtroRol = f.val"
          :class="filtroRol === f.val ? 'bg-white text-[#1F2937] shadow-sm' : 'text-gray-400 hover:text-gray-600'"
          class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
          {{ f.label }}
        </button>
      </div>
    </div>

    <!-- ── Lista de usuarios ──────────────────────────────── -->
    <div class="max-w-5xl mx-auto">
      <div v-if="cargando" class="flex items-center justify-center py-20 text-gray-400">
        <svg class="w-6 h-6 animate-spin mr-3" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        Cargando...
      </div>

      <div v-else-if="usuariosFiltrados.length === 0" class="text-center py-20 text-gray-400 text-sm">
        {{ vista === 'papelera' ? 'La papelera está vacía.' : 'No hay cuentas en este filtro.' }}
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="u in usuariosFiltrados" :key="u.id"
          class="flex flex-col gap-3 bg-white border rounded-2xl px-4 py-3.5 transition-all"
          :class="u.is_blocked ? 'border-red-200 bg-red-50' : vista === 'papelera' ? 'border-gray-200 opacity-70' : 'border-gray-200 hover:border-gray-300'"
        >
          <!-- Fila principal -->
          <div class="flex flex-col sm:flex-row sm:items-center gap-3">

            <!-- Avatar + info -->
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 font-black text-sm"
                   :class="[2,4].includes(u.role) ? 'bg-blue-100 text-blue-600' : u.role === 3 ? 'bg-amber-100 text-amber-600' : 'bg-gray-200 text-gray-500'">
                {{ u.name.charAt(0).toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-bold text-sm text-[#1F2937] truncate">{{ u.name }}</span>
                  <!-- Chip Docente siempre visible para roles 2 y 4 -->
                  <span v-if="[2,4].includes(u.role)"
                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border
                               bg-blue-50 text-blue-600 border-blue-200">
                    Docente
                  </span>
                  <!-- Chip Admin adicional solo para role 4 -->
                  <span v-if="u.role === 4"
                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border
                               bg-purple-50 text-purple-600 border-purple-200">
                    Admin
                  </span>
                  <!-- Chip para empresa y otros roles -->
                  <span v-if="![2,4].includes(u.role)"
                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
                        :class="roleChip(u.role).bg">
                    {{ roleChip(u.role).label }}
                  </span>
                  <span v-if="u.is_active"
                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider
                               bg-green-50 text-green-600 border border-green-200 flex items-center gap-1">
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Activada
                  </span>
                  <span v-else
                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider
                               bg-orange-50 text-orange-500 border border-orange-200">
                    Sin activar
                  </span>
                  <span v-if="u.is_blocked"
                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider
                               bg-red-50 text-red-600 border border-red-200">
                    Bloqueada
                  </span>
                </div>
                <p class="text-xs text-gray-400 break-all mt-0.5">{{ u.email }}</p>
                <!-- Centro asociado (docentes y admins de centro) -->
                <p v-if="[2,4].includes(u.role) && u.centro_nombre"
                   class="text-[10px] text-blue-500 mt-0.5 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                  </svg>
                  {{ u.centro_nombre }}
                </p>
              </div>
            </div>

            <!-- Fecha -->
            <div class="hidden md:block text-right shrink-0">
              <p class="text-[10px] text-gray-400 uppercase tracking-wider">Creada</p>
              <p class="text-xs text-gray-500">{{ fmtDate(u.created_at) }}</p>
            </div>
            <div v-if="vista === 'papelera'" class="hidden md:block text-right shrink-0">
              <p class="text-[10px] text-gray-400 uppercase tracking-wider">Eliminada</p>
              <p class="text-xs text-red-400">{{ fmtDate(u.deleted_at) }}</p>
            </div>

            <!-- Acciones activos -->
            <div v-if="vista === 'activos'" class="flex items-center gap-1.5 shrink-0 flex-wrap">

              <!-- Activar con highlight y ref dinámico -->
              <div v-if="!u.is_active" class="relative">
                <!-- Tooltip puntero -->
                <Transition name="pointer">
                  <div v-if="highlightId === u.id"
                       class="absolute -top-11 left-1/2 -translate-x-1/2 z-10 pointer-events-none
                              whitespace-nowrap px-3 py-1.5 rounded-lg
                              bg-[#00A859] text-white text-[10px] font-black shadow-lg
                              flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Activa esta cuenta aquí
                    <!-- Flecha apuntando abajo -->
                    <span class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3
                                 bg-[#00A859] rotate-45 rounded-sm"/>
                  </div>
                </Transition>

                <button
                  :ref="el => { if (el) activarBtns[u.id] = el }"
                  @click="onActivarClick(u)"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black
                         uppercase tracking-wider transition-all
                         bg-orange-50 text-orange-500 border border-orange-200
                         hover:bg-orange-100"
                  :class="highlightId === u.id ? 'ring-2 ring-orange-400/60 ring-offset-1 ring-offset-white animate-pulse-soft' : ''"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  </svg>
                  Activar
                </button>
              </div>

              <!-- Editar -->
              <button @click="abrirModalEditar(u)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black
                       uppercase tracking-wider bg-gray-50 text-gray-500 border border-gray-200
                       hover:bg-gray-100 hover:text-gray-700 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar
              </button>

              <!-- Bloquear / Desbloquear -->
              <button @click="toggleBloquear(u)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black
                       uppercase tracking-wider border transition-all"
                :class="u.is_blocked
                  ? 'bg-yellow-50 text-yellow-600 border-yellow-200 hover:bg-yellow-100'
                  : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100 hover:text-gray-700'">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
                {{ u.is_blocked ? 'Desbloquear' : 'Bloquear' }}
              </button>

              <!-- Eliminar -->
              <button @click="enviarPapelera(u)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black
                       uppercase tracking-wider bg-red-500/10 text-red-400 border border-red-500/20
                       hover:bg-red-500/20 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                  <polyline points="3 6 5 6 21 6"/>
                  <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                </svg>
                Eliminar
              </button>
            </div>

            <!-- Acciones papelera -->
            <div v-else class="flex items-center gap-1.5 shrink-0">
              <button @click="restaurar(u)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black
                       uppercase tracking-wider bg-[#00A859]/15 text-[#00A859]
                       border border-[#00A859]/30 hover:bg-[#00A859]/25 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Restaurar
              </button>
              <button @click="destruir(u)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black
                       uppercase tracking-wider bg-red-500/15 text-red-300
                       border border-red-500/30 hover:bg-red-500/25 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Definitivo
              </button>
            </div>
          </div>

          <!-- Fila secundaria: asociar centro (solo docentes activos; el backend restringe esta acción a superadmin) -->
          <div v-if="authStore.isSuperAdmin && vista === 'activos' && [2,4].includes(u.role)" class="border-t border-gray-100 pt-2.5">
            <button @click="abrirModalCentro(u)"
              class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider
                     text-gray-400 hover:text-blue-600 transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              {{ u.centro_nombre ? `Centro: ${u.centro_nombre} — cambiar` : 'Asociar centro educativo' }}
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- ══ MODAL: Crear cuenta ═════════════════════════════════════ -->
    <Transition name="overlay">
      <div v-if="modalCrear"
           class="fixed inset-0 z-[9000] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
           @click.self="modalCrear = false">
        <Transition name="modal-scale">
          <div v-if="modalCrear"
               class="relative bg-white border border-gray-200 rounded-[1.75rem] shadow-2xl w-full max-w-md p-8">
            <button @click="modalCrear = false"
              class="absolute top-4 right-4 w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200
                     flex items-center justify-center text-gray-400 hover:text-gray-600 transition-all">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>

            <h2 class="text-lg font-black mb-1 text-[#121212]">Nueva cuenta</h2>
            <p class="text-xs text-gray-500 mb-6">
              La cuenta quedará pendiente de activación hasta que la valides.
            </p>

            <form @submit.prevent="crearUsuario" class="space-y-4">
              <!-- Nombre -->
              <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Nombre</label>
                <input v-model="form.name" type="text" placeholder="Nombre completo"
                  maxlength="255"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formErrors.name ? 'border-red-400' : 'border-gray-200'" />
                <div class="flex justify-between items-center mt-1">
                  <p v-if="formErrors.name" class="text-[10px] text-red-500">{{ formErrors.name }}</p>
                  <span v-else></span>
                  <span class="text-[10px]" :class="(form.name || '').length >= 245 ? 'text-amber-500' : 'text-gray-300'">{{ (form.name || '').length }}/255</span>
                </div>
              </div>

              <!-- Correo electrónico -->
              <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Correo electrónico</label>
                <!-- Docente/Admin sin centro aún -->
                <div v-if="authStore.isSuperAdmin && ['2','4'].includes(form.role) && !activeEmailDomain"
                  class="w-full bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5 text-xs text-amber-600">
                  Selecciona un centro educativo para generar el correo
                </div>
                <!-- Empresa sin empresa asignada aún -->
                <div v-else-if="authStore.isSuperAdmin && form.role === '3' && !activeEmailDomain"
                  class="w-full bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5 text-xs text-amber-600">
                  Selecciona una empresa para generar el correo
                </div>
                <!-- Con dominio (centro o empresa): input dividido -->
                <div v-else-if="activeEmailDomain"
                  class="flex items-center bg-gray-50 border rounded-xl overflow-hidden transition-all
                         focus-within:border-[#00A859]/50 focus-within:ring-2 focus-within:ring-[#00A859]/10"
                  :class="formErrors.email ? 'border-red-400' : 'border-gray-200'">
                  <input v-model="formEmailLocal" type="text" placeholder="nombreusuario"
                    class="flex-1 min-w-0 bg-transparent px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300 outline-none" />
                  <span class="pr-4 text-sm text-gray-400 whitespace-nowrap select-none shrink-0">@{{ activeEmailDomain }}</span>
                </div>
                <!-- Fallback: input libre (no superadmin) -->
                <input v-else v-model="form.email" type="email" placeholder="correo@ejemplo.com"
                  maxlength="254"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formErrors.email ? 'border-red-400' : 'border-gray-200'" />
                <div class="flex justify-between items-center mt-1">
                  <p v-if="formErrors.email" class="text-[10px] text-red-500">{{ formErrors.email }}</p>
                  <span v-else></span>
                  <span v-if="activeEmailDomain" class="text-[10px]" :class="(form.email || '').length >= 244 ? 'text-amber-500' : 'text-gray-300'">{{ (form.email || '').length }}/254</span>
                </div>
              </div>

              <!-- Contraseña -->
              <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Contraseña temporal</label>
                <input v-model="form.password" type="password" placeholder="Mín. 8 caracteres, mayúsculas, minúsculas, número y símbolo"
                  maxlength="128"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formErrors.password ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="formErrors.password" class="text-[10px] text-red-500 font-bold mt-1.5">{{ formErrors.password }}</p>
                <ul class="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-0.5">
                  <li v-for="req in passwordRequisitos" :key="req.label"
                    class="flex items-center gap-1 text-[10px] transition-colors"
                    :class="req.ok ? 'text-[#00A859] font-bold' : 'text-gray-400'">
                    <svg v-if="req.ok" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg v-else class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="8" stroke-width="2"/>
                    </svg>
                    {{ req.label }}
                  </li>
                </ul>
              </div>

              <!-- Repite contraseña -->
              <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Repite la contraseña</label>
                <input v-model="form.password_confirmation" type="password" placeholder="Vuelve a escribir la contraseña"
                  maxlength="128"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formErrors.password_confirmation ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="formErrors.password_confirmation" class="text-[10px] text-red-500 mt-1">{{ formErrors.password_confirmation }}</p>
              </div>

              <!-- Tipo de cuenta (solo visible para superadmin; admin de centro siempre crea docentes) -->
              <div v-if="authStore.isSuperAdmin">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Tipo de cuenta</label>
                <div class="flex gap-3">
                  <!-- Docente: activo si role es 2 (docente) o 4 (admin de centro) -->
                  <button type="button"
                    @click="form.role = ['2','4'].includes(form.role) ? form.role : '2'"
                    :class="['2','4'].includes(form.role) ? 'bg-blue-50 border-blue-300 text-blue-600' : 'bg-gray-50 border-gray-200 text-gray-400 hover:text-gray-600'"
                    class="flex-1 flex flex-col items-center gap-1.5 py-3 rounded-xl border text-xs font-bold transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 3h6v4H9V3z"/>
                    </svg>
                    Docente
                  </button>
                  <button type="button" @click="form.role = '3'; form.centro_educativo_id = null"
                    :class="form.role === '3' ? 'bg-amber-50 border-amber-300 text-amber-600' : 'bg-gray-50 border-gray-200 text-gray-400 hover:text-gray-600'"
                    class="flex-1 flex flex-col items-center gap-1.5 py-3 rounded-xl border text-xs font-bold transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Empresa
                  </button>
                </div>

                <!-- Sub-opción anidada: ¿es admin del centro? Solo si Docente está seleccionado -->
                <div v-if="['2','4'].includes(form.role)"
                  class="mt-2 px-3.5 py-3 bg-gray-50 border border-gray-200 rounded-xl">
                  <label class="flex items-start gap-3 cursor-pointer select-none">
                    <input type="checkbox"
                      :checked="form.role === '4'"
                      @change="form.role = $event.target.checked ? '4' : '2'; if (!$event.target.checked) form.centro_educativo_id = null"
                      class="mt-0.5 w-4 h-4 rounded accent-purple-600 shrink-0" />
                    <div>
                      <span class="text-xs font-bold text-gray-700">Administrador del centro</span>
                      <p class="text-[10px] text-gray-400 mt-0.5">Gestiona los docentes de su centro educativo</p>
                    </div>
                  </label>
                </div>

                <p v-if="formErrors.role" class="text-[10px] text-red-500 mt-1">{{ formErrors.role }}</p>
              </div>

              <!-- Centro educativo (requerido para docente/admin) -->
              <div v-if="authStore.isSuperAdmin && ['2','4'].includes(form.role)">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                  Centro educativo
                  <span class="text-red-400 ml-0.5">*</span>
                </label>
                <div v-if="cargandoCentros" class="text-xs text-gray-400 py-2">Cargando centros…</div>
                <select v-else v-model="form.centro_educativo_id"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formErrors.centro_educativo_id ? 'border-red-400' : 'border-gray-200'">
                  <option :value="null">— Selecciona un centro —</option>
                  <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
                <p v-if="formErrors.centro_educativo_id" class="text-[10px] text-red-500 mt-1">{{ formErrors.centro_educativo_id }}</p>
              </div>

              <!-- Empresa (requerida para role empresa) -->
              <div v-if="authStore.isSuperAdmin && form.role === '3'">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                  Empresa
                  <span class="text-red-400 ml-0.5">*</span>
                </label>
                <div v-if="cargandoEmpresas" class="text-xs text-gray-400 py-2">Cargando empresas…</div>
                <select v-else v-model="form.empresa_id"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formErrors.empresa_id ? 'border-red-400' : 'border-gray-200'">
                  <option :value="null">— Selecciona una empresa —</option>
                  <option v-for="e in empresasList" :key="e.id" :value="e.id">{{ e.nombre_comercial }}</option>
                </select>
                <p v-if="formErrors.empresa_id" class="text-[10px] text-red-500 mt-1">{{ formErrors.empresa_id }}</p>
              </div>

              <p v-if="msgCrear" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                {{ msgCrear }}
              </p>

              <button type="submit" :disabled="creando"
                class="w-full py-3 rounded-xl bg-[#00A859] hover:bg-[#009950] text-white font-black
                       text-xs uppercase tracking-widest transition-all disabled:opacity-50 disabled:cursor-not-allowed mt-2">
                {{ creando ? 'Creando...' : 'Crear cuenta' }}
              </button>
            </form>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- ══ MODAL: Éxito al crear cuenta ═══════════════════════════ -->
    <Transition name="overlay">
      <div v-if="modalExito"
           class="fixed inset-0 z-[9100] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <Transition name="modal-scale">
          <div v-if="modalExito"
               class="bg-white border border-gray-200 rounded-[1.75rem] shadow-2xl w-full max-w-sm p-8 text-center">

            <!-- Icono -->
            <div class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-[#00A859]/10 border border-[#00A859]/20
                        flex items-center justify-center">
              <svg class="w-8 h-8 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            </div>

            <h2 class="text-xl font-black mb-2 text-[#121212]">¡Cuenta creada!</h2>
            <p class="text-sm text-gray-500 leading-relaxed mb-2">
              La cuenta de
              <span class="text-[#1F2937] font-bold">{{ cuentaRecienCreada?.name }}</span>
              se ha creado correctamente.
            </p>
            <p class="text-xs text-[#00A859] font-bold flex items-center justify-center gap-1.5">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
              Contraseña creada correctamente
            </p>

            <!-- Aviso destacado -->
            <div class="mt-4 mb-6 rounded-xl bg-yellow-50 border border-yellow-200 px-4 py-3.5
                        flex items-start gap-3 text-left">
              <svg class="w-4 h-4 text-yellow-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              </svg>
              <div>
                <p class="text-xs font-black text-yellow-600 uppercase tracking-wider mb-0.5">Pendiente de activación</p>
                <p class="text-xs text-gray-500">
                  La cuenta aún no puede iniciar sesión.
                  Pulsa el botón <span class="text-[#00A859] font-bold">Activar</span> en la fila
                  de esta cuenta para habilitarla.
                </p>
              </div>
            </div>

            <button @click="cerrarModalExito"
              class="w-full py-3 rounded-xl bg-[#00A859] hover:bg-[#009950] text-white
                     font-black text-xs uppercase tracking-widest transition-all">
              Entendido, ir a activar
            </button>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- ══ MODAL: Asociar centro ═══════════════════════════════════ -->
    <Transition name="overlay">
      <div v-if="modalCentro"
           class="fixed inset-0 z-[9000] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
           @click.self="modalCentro = false">
        <Transition name="modal-scale">
          <div v-if="modalCentro"
               class="relative bg-white border border-gray-200 rounded-[1.75rem] shadow-2xl
                      w-full max-w-lg flex flex-col" style="max-height: 85vh">

            <!-- Cabecera -->
            <div class="px-6 pt-6 pb-4 border-b border-gray-100 shrink-0">
              <button @click="modalCentro = false"
                class="absolute top-4 right-4 w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200
                       flex items-center justify-center text-gray-400 hover:text-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
              <h2 class="text-base font-black mb-0.5 text-[#121212]">Asociar centro educativo</h2>
              <p class="text-xs text-gray-500">
                Docente: <span class="text-[#1F2937] font-bold">{{ usuarioCentro?.name }}</span>
              </p>

              <!-- Centro actual -->
              <div v-if="usuarioCentro?.centro_nombre"
                   class="mt-3 flex items-center justify-between
                          bg-blue-50 border border-blue-200 rounded-xl px-3 py-2">
                <div class="flex items-center gap-2 text-xs text-blue-600">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                  </svg>
                  <span class="font-bold">{{ usuarioCentro.centro_nombre }}</span>
                </div>
                <button @click="desvincularCentro"
                  class="text-[10px] font-black uppercase tracking-wider text-red-400 hover:text-red-600 transition-colors">
                  Quitar
                </button>
              </div>

              <!-- Buscador -->
              <div class="relative mt-3">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <circle cx="11" cy="11" r="8" stroke-width="2"/>
                  <path stroke-linecap="round" stroke-width="2" d="M21 21l-4.35-4.35"/>
                </svg>
                <input
                  v-model="busquedaCentro"
                  type="text"
                  placeholder="Buscar por nombre del centro…"
                  autofocus
                  class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-9 pr-4 py-2.5
                         text-sm text-[#1F2937] placeholder-gray-300 outline-none
                         focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10 transition-all"
                />
              </div>
            </div>

            <!-- Lista de centros -->
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-1">
              <div v-if="cargandoCentros" class="flex items-center justify-center py-10 text-gray-400">
                <svg class="w-5 h-5 animate-spin mr-2" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                </svg>
                Cargando centros…
              </div>

              <div v-else-if="centrosFiltrados.length === 0"
                   class="text-center py-10 text-gray-400 text-sm">
                No se encontraron centros con ese nombre.
              </div>

              <button
                v-else
                v-for="c in centrosFiltrados" :key="c.id"
                @click="seleccionarCentro(c)"
                :disabled="asociandoCentro"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left
                       border transition-all disabled:opacity-50"
                :class="usuarioCentro?.centro_educativo_id === c.id
                  ? 'bg-blue-50 border-blue-300 text-blue-700'
                  : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100 hover:border-gray-300 hover:text-[#1F2937]'"
              >
                <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                     :class="usuarioCentro?.centro_educativo_id === c.id ? 'bg-blue-100' : 'bg-gray-100'">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                       :class="usuarioCentro?.centro_educativo_id === c.id ? 'text-blue-500' : 'text-gray-400'">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-bold truncate">{{ c.nombre }}</p>
                </div>
                <svg v-if="usuarioCentro?.centro_educativo_id === c.id"
                     class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
              </button>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 shrink-0">
              <p class="text-[10px] text-gray-400 text-center">
                {{ centrosFiltrados.length }} centro{{ centrosFiltrados.length !== 1 ? 's' : '' }} encontrado{{ centrosFiltrados.length !== 1 ? 's' : '' }}
              </p>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- ══ MODAL: Editar cuenta ══════════════════════════════════ -->
    <Transition name="overlay">
      <div v-if="modalEditar"
           class="fixed inset-0 z-[9000] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
           @click.self="modalEditar = false">
        <Transition name="modal-scale">
          <div v-if="modalEditar"
               class="relative bg-white border border-gray-200 rounded-[1.75rem] shadow-2xl w-full max-w-md flex flex-col max-h-[90vh]">

            <!-- Botón cerrar -->
            <button @click="modalEditar = false"
              class="absolute top-4 right-4 w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200
                     flex items-center justify-center text-gray-400 hover:text-gray-600 transition-all z-10">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>

            <!-- Cabecera fija -->
            <div class="px-8 pt-8 pb-4 shrink-0">
              <!-- Aviso de seguridad -->
              <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3">
                <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <div>
                  <p class="text-xs font-black text-amber-600 uppercase tracking-wider mb-0.5">Edición de cuenta sensible</p>
                  <p class="text-xs text-gray-500">Estás modificando los datos de <span class="text-[#1F2937] font-bold">{{ usuarioEditando?.name }}</span>. Cualquier cambio tendrá efecto inmediato.</p>
                </div>
              </div>
              <h2 class="text-lg font-black mb-1 text-[#121212]">Editar cuenta</h2>
              <p class="text-xs text-gray-500">Modifica los datos de la cuenta. Deja la contraseña en blanco para no cambiarla.</p>
            </div>

            <!-- Cuerpo scrollable -->
            <div class="overflow-y-auto px-8 pb-8 pt-1">
            <form @submit.prevent="guardarEdicion" class="space-y-4">
              <!-- Nombre -->
              <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Nombre</label>
                <input v-model="formEditar.name" type="text" placeholder="Nombre completo"
                  maxlength="255"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formEditarErrors.name ? 'border-red-400' : 'border-gray-200'" />
                <div class="flex justify-between items-center mt-1">
                  <p v-if="formEditarErrors.name" class="text-[10px] text-red-500">{{ formEditarErrors.name }}</p>
                  <span v-else></span>
                  <span class="text-[10px]" :class="(formEditar.name || '').length >= 245 ? 'text-amber-500' : 'text-gray-300'">{{ (formEditar.name || '').length }}/255</span>
                </div>
              </div>

              <!-- Correo electrónico -->
              <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Correo electrónico</label>
                <!-- Con dominio (centro o empresa): input dividido -->
                <div v-if="activeEmailEditarDomain"
                  class="flex items-center bg-gray-50 border rounded-xl overflow-hidden transition-all
                         focus-within:border-[#00A859]/50 focus-within:ring-2 focus-within:ring-[#00A859]/10"
                  :class="formEditarErrors.email ? 'border-red-400' : 'border-gray-200'">
                  <input v-model="formEditarEmailLocal" type="text" placeholder="nombreusuario"
                    class="flex-1 min-w-0 bg-transparent px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300 outline-none" />
                  <span class="pr-4 text-sm text-gray-400 whitespace-nowrap select-none shrink-0">@{{ activeEmailEditarDomain }}</span>
                </div>
                <!-- Sin dominio: input libre -->
                <input v-else v-model="formEditar.email" type="email" placeholder="correo@ejemplo.com"
                  maxlength="254"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formEditarErrors.email ? 'border-red-400' : 'border-gray-200'" />
                <div class="flex justify-between items-center mt-1">
                  <p v-if="formEditarErrors.email" class="text-[10px] text-red-500">{{ formEditarErrors.email }}</p>
                  <span v-else></span>
                  <span class="text-[10px]" :class="(formEditar.email || '').length >= 244 ? 'text-amber-500' : 'text-gray-300'">{{ (formEditar.email || '').length }}/254</span>
                </div>
              </div>

              <!-- Nueva contraseña (opcional) -->
              <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Nueva contraseña <span class="normal-case text-gray-400 font-normal">(opcional)</span></label>
                <input v-model="formEditar.password" type="password" placeholder="Dejar en blanco para no cambiar"
                  maxlength="128"
                  class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formEditarErrors.password ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="formEditarErrors.password" class="text-[10px] text-red-500 font-bold mt-1.5">{{ formEditarErrors.password }}</p>
                <ul v-if="formEditar.password" class="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-0.5">
                  <li v-for="req in passwordRequisitosEditar" :key="req.label"
                    class="flex items-center gap-1 text-[10px] transition-colors"
                    :class="req.ok ? 'text-[#00A859] font-bold' : 'text-gray-400'">
                    <svg v-if="req.ok" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg v-else class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="8" stroke-width="2"/>
                    </svg>
                    {{ req.label }}
                  </li>
                </ul>
              </div>

              <!-- Repite nueva contraseña (solo si se ha escrito una) -->
              <div v-if="formEditar.password">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Repite la nueva contraseña</label>
                <input v-model="formEditar.password_confirmation" type="password" placeholder="Vuelve a escribir la contraseña"
                  maxlength="128"
                  class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formEditarErrors.password_confirmation ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="formEditarErrors.password_confirmation" class="text-[10px] text-red-500 mt-1">{{ formEditarErrors.password_confirmation }}</p>
              </div>

              <!-- Switch de confirmación: obliga a un clic deliberado antes de resetear la contraseña de otra cuenta -->
              <div v-if="formEditar.password" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3.5">
                <label class="flex items-start gap-3 cursor-pointer select-none">
                  <button type="button" role="switch" :aria-checked="confirmarCambioPassword"
                    @click="confirmarCambioPassword = !confirmarCambioPassword"
                    :class="confirmarCambioPassword ? 'bg-red-500' : 'bg-gray-300'"
                    class="relative w-12 h-6 rounded-full transition-all duration-200 shrink-0">
                    <span :class="confirmarCambioPassword ? 'translate-x-6' : 'translate-x-1'"
                      class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 block"></span>
                  </button>
                  <div>
                    <span class="text-xs font-black text-red-600">Confirmo que quiero cambiar la contraseña de esta cuenta</span>
                    <p class="text-[10px] text-gray-500 mt-0.5">Se cerrará la sesión en todos los dispositivos donde estuviera conectada.</p>
                  </div>
                </label>
                <p v-if="formEditarErrors.confirm_password_change" class="text-[10px] text-red-600 font-bold mt-2">{{ formEditarErrors.confirm_password_change }}</p>
              </div>

              <!-- Tipo de cuenta (solo visible para superadmin) -->
              <div v-if="authStore.isSuperAdmin">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Tipo de cuenta</label>
                <div class="flex gap-3">
                  <!-- Docente: activo si role es 2 (docente) o 4 (admin de centro) -->
                  <button type="button"
                    @click="formEditar.role = ['2','4'].includes(formEditar.role) ? formEditar.role : '2'"
                    :class="['2','4'].includes(formEditar.role) ? 'bg-blue-50 border-blue-300 text-blue-600' : 'bg-gray-50 border-gray-200 text-gray-400 hover:text-gray-600'"
                    class="flex-1 flex flex-col items-center gap-1.5 py-3 rounded-xl border text-xs font-bold transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 3h6v4H9V3z"/>
                    </svg>
                    Docente
                  </button>
                  <button type="button" @click="formEditar.role = '3'"
                    :class="formEditar.role === '3' ? 'bg-amber-50 border-amber-300 text-amber-600' : 'bg-gray-50 border-gray-200 text-gray-400 hover:text-gray-600'"
                    class="flex-1 flex flex-col items-center gap-1.5 py-3 rounded-xl border text-xs font-bold transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Empresa
                  </button>
                </div>

                <!-- Sub-opción anidada: ¿es admin del centro? Solo si Docente está seleccionado -->
                <div v-if="['2','4'].includes(formEditar.role)"
                  class="mt-2 px-3.5 py-3 bg-gray-50 border border-gray-200 rounded-xl">
                  <label class="flex items-start gap-3 cursor-pointer select-none">
                    <input type="checkbox"
                      :checked="formEditar.role === '4'"
                      @change="formEditar.role = $event.target.checked ? '4' : '2'"
                      class="mt-0.5 w-4 h-4 rounded accent-purple-600 shrink-0" />
                    <div>
                      <span class="text-xs font-bold text-gray-700">Administrador del centro</span>
                      <p class="text-[10px] text-gray-400 mt-0.5">Gestiona los docentes de su centro educativo</p>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Centro educativo (visible para cualquier docente) -->
              <div v-if="authStore.isSuperAdmin && ['2','4'].includes(formEditar.role)">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                  Centro educativo
                  <span class="text-red-400 ml-0.5">*</span>
                </label>
                <div v-if="cargandoCentros" class="text-xs text-gray-400 py-2">Cargando centros…</div>
                <select v-else v-model="formEditar.centro_educativo_id"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formEditarErrors.centro_educativo_id ? 'border-red-400' : 'border-gray-200'">
                  <option :value="null">— Selecciona un centro —</option>
                  <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
                <p v-if="formEditarErrors.centro_educativo_id" class="text-[10px] text-red-500 mt-1">{{ formEditarErrors.centro_educativo_id }}</p>
              </div>

              <!-- Empresa (requerida para role empresa) -->
              <div v-if="authStore.isSuperAdmin && formEditar.role === '3'">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                  Empresa
                  <span class="text-red-400 ml-0.5">*</span>
                </label>
                <div v-if="cargandoEmpresas" class="text-xs text-gray-400 py-2">Cargando empresas…</div>
                <select v-else v-model="formEditar.empresa_id"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                         outline-none transition-all focus:border-[#00A859]/50 focus:ring-2 focus:ring-[#00A859]/10"
                  :class="formEditarErrors.empresa_id ? 'border-red-400' : 'border-gray-200'">
                  <option :value="null">— Selecciona una empresa —</option>
                  <option v-for="e in empresasList" :key="e.id" :value="e.id">{{ e.nombre_comercial }}</option>
                </select>
                <p v-if="formEditarErrors.empresa_id" class="text-[10px] text-red-500 mt-1">{{ formEditarErrors.empresa_id }}</p>
              </div>

              <!-- Switch de activación -->
              <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Activar aquí</label>
                <div class="flex items-center gap-3">
                  <button type="button"
                    @click="!usuarioEditando?.is_active && activarEnModal()"
                    :class="usuarioEditando?.is_active
                      ? 'bg-[#00A859] cursor-default'
                      : 'bg-orange-400 hover:bg-orange-500 cursor-pointer'"
                    class="relative w-12 h-6 rounded-full transition-all duration-200 shrink-0">
                    <span
                      :class="usuarioEditando?.is_active ? 'translate-x-6' : 'translate-x-1'"
                      class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 block">
                    </span>
                  </button>
                  <span :class="usuarioEditando?.is_active ? 'text-[#00A859]' : 'text-orange-500'"
                        class="text-xs font-bold">
                    {{ usuarioEditando?.is_active ? 'Activada' : 'Sin activar' }}
                  </span>
                </div>
              </div>

              <p v-if="msgEditar" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                {{ msgEditar }}
              </p>

              <button type="submit" :disabled="editando"
                class="w-full py-3 rounded-xl bg-[#00A859] hover:bg-[#009950] text-white font-black
                       text-xs uppercase tracking-widest transition-all disabled:opacity-50 disabled:cursor-not-allowed mt-2">
                {{ editando ? 'Guardando...' : 'Guardar cambios' }}
              </button>
            </form>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- ══ MODAL: Confirmación ════════════════════════════════════ -->
    <Transition name="overlay">
      <div v-if="confirm.show"
           class="fixed inset-0 z-[9200] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
           @click.self="confirm.show = false">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="font-black text-base mb-2 text-[#121212]">{{ confirm.title }}</h3>
          <p class="text-sm text-gray-500 mb-6">{{ confirm.body }}</p>
          <div class="flex gap-3">
            <button @click="confirm.show = false"
              class="flex-1 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-xs font-black
                     uppercase tracking-widest text-gray-500 hover:bg-gray-100 transition-all">
              Cancelar
            </button>
            <button @click="ejecutarConfirm"
              :class="confirm.danger
                ? 'bg-red-50 border-red-200 text-red-600 hover:bg-red-100'
                : 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859] hover:bg-[#00A859]/20'"
              class="flex-1 py-2.5 rounded-xl border text-xs font-black uppercase tracking-widest transition-all">
              Confirmar
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ══ TOAST (centrado en pantalla) ══════════════════════════════ -->
    <div class="fixed inset-0 z-[9300] flex items-center justify-center pointer-events-none px-4">
      <Transition name="toast">
        <div v-if="toast.show"
             class="pointer-events-auto flex items-center gap-3 px-5 py-3.5
                    rounded-xl border shadow-2xl text-xs font-bold text-white"
             :class="toast.ok
               ? 'bg-[#00A859] border-[#00A859]'
               : 'bg-red-600 border-red-600'">
          <svg v-if="toast.ok" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
          <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          {{ toast.msg }}
        </div>
      </Transition>
    </div>

  </div>
</template>

<style scoped>
/* Transiciones generales */
.overlay-enter-active, .overlay-leave-active { transition: opacity 0.25s ease; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }

.modal-scale-enter-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-scale-leave-active { transition: all 0.2s ease; }
.modal-scale-enter-from   { opacity: 0; transform: scale(0.92) translateY(10px); }
.modal-scale-leave-to     { opacity: 0; transform: scale(0.96); }

.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to       { opacity: 0; transform: translateY(10px); }

/* Tooltip puntero al botón Activar */
.pointer-enter-active { transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
.pointer-leave-active { transition: all 0.2s ease; }
.pointer-enter-from   { opacity: 0; transform: translateX(-50%) translateY(6px); }
.pointer-leave-to     { opacity: 0; transform: translateX(-50%) translateY(-4px); }

/* Pulso suave para el botón destacado */
@keyframes pulse-soft {
  0%, 100% { box-shadow: 0 0 0 3px rgba(0, 168, 89, 0.5), 0 0 0 6px rgba(0, 168, 89, 0.15); }
  50%       { box-shadow: 0 0 0 5px rgba(0, 168, 89, 0.35), 0 0 0 10px rgba(0, 168, 89, 0.05); }
}
.animate-pulse-soft { animation: pulse-soft 1.4s ease-in-out infinite; }
</style>
