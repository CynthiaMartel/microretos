<!-- Ruta: /usuarios (name: gestion-usuarios). Antes vivía en /admin/usuarios — ver router/index.js. Nota: el endpoint del backend sigue siendo /admin/usuarios, no confundir. -->
<script setup>
import PanelModulosDocente from '../components/PanelModulosDocente.vue'
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import api from '../api.js'
import { useAuthStore } from '../stores/auth.js'
import Modal from '../components/Modal.vue'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import FiltrosPanel from '../components/FiltrosPanel.vue'
import FiltroSelect from '../components/FiltroSelect.vue'
import TituloResultados from '../components/TituloResultados.vue'
import { usePaginacion } from '../composables/usePaginacion.js'

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

// ── Módulos que imparte el docente: toda la lógica en PanelModulosDocente (la misma que «Mi
//    usuario»), con los endpoints /admin/usuarios/{id}/modulos ──
const modulosAbiertos = ref(new Set())   // filas de docente con el desplegable de módulos abierto
const revModulos      = ref({})          // id → nº de guardados, para recargar el panel de la fila
function alternarModulos(id) {
  const abiertos = new Set(modulosAbiertos.value)
  abiertos.has(id) ? abiertos.delete(id) : abiertos.add(id)
  modulosAbiertos.value = abiertos
}
function onModulosGuardados(ids) {
  const id = usuarioEditando.value?.id
  const fila = usuarios.value.find(u => u.id === id)
  if (fila) fila.num_modulos = ids.length
  revModulos.value = { ...revModulos.value, [id]: (revModulos.value[id] || 0) + 1 }
  mostrarToast('Módulos del docente guardados.')
}
// Abre la edición del docente con su sección de módulos a la vista (fila o modal de cuenta creada)
async function editarModulos(usuario) {
  await abrirModalEditar(usuario)
  await nextTick()
  document.getElementById('modulos-docente-editar')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

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
// ── Explorador: buscador + centro arriba; tipo de cuenta y estado en «Filtros rápidos» ──
// (mismo esquema que las demás bibliotecas, GUIA_DISENO §8.2i)
const busqueda      = ref('')
const filtroCentro  = ref('')
const filtroEstados = ref([])          // 'activada' | 'sin_activar' | 'bloqueada' (vale cualquiera)
const filtroSinModulos = ref(false)    // docentes que aún no indicaron los módulos que imparten
const orden         = ref('recientes') // 'recientes' | 'nombre' | 'pendientes'
const panelFiltrosAbierto = ref(typeof window === 'undefined' || window.innerWidth >= 1024)

const listaVista = computed(() => vista.value === 'papelera' ? papelera.value : usuarios.value)
const normal = (t) => String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
const estadoDe = (u) => u.is_blocked ? 'bloqueada' : u.is_active ? 'activada' : 'sin_activar'
// num_modulos solo viene en el listado de activas; null = no calculado (papelera)
const sinModulos = (u) => u.role === 2 && u.num_modulos === 0

function cumpleFiltros(u, { omitirRol = false, omitirEstado = false } = {}) {
  const q = normal(busqueda.value.trim())
  return (!q || normal([u.name, u.email, u.centro_nombre, u.empresa_nombre].join(' ')).includes(q)) &&
    (!filtroCentro.value || u.centro_nombre === filtroCentro.value) &&
    (omitirRol || filtroRol.value === 0 || u.role === filtroRol.value) &&
    (omitirEstado || !filtroEstados.value.length || filtroEstados.value.includes(estadoDe(u))) &&
    (!filtroSinModulos.value || sinModulos(u))
}

const porNombre = (a, b) => (a.name || '').localeCompare(b.name || '', 'es')
const usuariosFiltrados = computed(() => {
  const lista = listaVista.value.filter(u => cumpleFiltros(u))
  if (orden.value === 'nombre') return lista.sort(porNombre)
  if (orden.value === 'pendientes') return lista.sort((a, b) => Number(a.is_active) - Number(b.is_active) || porNombre(a, b))
  const fecha = (u) => (vista.value === 'papelera' ? u.deleted_at : u.created_at) || ''
  return lista.sort((a, b) => fecha(b).localeCompare(fecha(a)) || porNombre(a, b))
})

const totalActivos  = computed(() => usuarios.value.length)
const totalPapelera = computed(() => papelera.value.length)

// Tipo de cuenta (radio con contador de lo que quedaría). Admin de centro solo ve docentes de su
// centro (el backend filtra), así que «Admins de centro», «Empresas» y «Superadmin» solo salen si hay.
const TIPOS = [
  { val: 2, label: 'Docentes',         punto: 'bg-centros' },
  { val: 4, label: 'Admins de centro', punto: 'bg-administraciones' },
  { val: 3, label: 'Empresas',         punto: 'bg-empresas' },
  { val: 1, label: 'Superadmin',       punto: 'bg-azul-noche' },
]
const conteoTipos = computed(() => {
  const base = listaVista.value.filter(u => cumpleFiltros(u, { omitirRol: true }))
  return { 0: base.length, ...Object.fromEntries(TIPOS.map(t => [t.val, base.filter(u => u.role === t.val).length])) }
})
const tiposPanel = computed(() => TIPOS.filter(t => listaVista.value.some(u => u.role === t.val) || filtroRol.value === t.val))

const ESTADOS = [
  { val: 'activada',    label: 'Activada',    punto: 'bg-empresas' },
  { val: 'sin_activar', label: 'Sin activar', punto: 'bg-orange-400' },
  { val: 'bloqueada',   label: 'Bloqueada',   punto: 'bg-red-500' },
]
const conteoEstados = computed(() => {
  const base = listaVista.value.filter(u => cumpleFiltros(u, { omitirEstado: true }))
  return Object.fromEntries(ESTADOS.map(e => [e.val, base.filter(u => estadoDe(u) === e.val).length]))
})
function alternarEstado(v) {
  filtroEstados.value = filtroEstados.value.includes(v) ? filtroEstados.value.filter(x => x !== v) : [...filtroEstados.value, v]
}
const etiquetaTipo   = (v) => TIPOS.find(t => t.val === v)?.label || ''
const etiquetaEstado = (v) => ESTADOS.find(e => e.val === v)?.label || ''

// Centro: solo para superadmin y si hay cuentas de más de un centro
const centrosDisponibles = computed(() =>
  [...new Set(listaVista.value.map(u => u.centro_nombre).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))

const filtrosLateral    = computed(() => (filtroRol.value ? 1 : 0) + filtroEstados.value.length + (filtroSinModulos.value ? 1 : 0))
const conteoSinModulos  = computed(() => listaVista.value.filter(u => sinModulos(u)).length)
const hayFiltrosActivos = computed(() => !!(busqueda.value.trim() || filtroCentro.value || filtrosLateral.value))
function limpiarFiltros() {
  busqueda.value = ''; filtroCentro.value = ''; filtroRol.value = 0; filtroEstados.value = []; filtroSinModulos.value = false
}
const nivelesTitulo = computed(() => filtroCentro.value
  ? [{ etiqueta: 'Centro', valor: filtroCentro.value, quitar: () => { filtroCentro.value = '' } }]
  : [])

// ── Paginación (12 filas) ─────────────────────────────────────────────────────
const POR_PAGINA = 12
const { pagina, totalPaginas, itemsPagina } = usePaginacion(usuariosFiltrados, POR_PAGINA)
watch([busqueda, filtroCentro, filtroRol, filtroEstados, filtroSinModulos, orden, vista], () => { pagina.value = 0 })
const paginasVisibles = computed(() => {
  const n = totalPaginas.value, a = pagina.value
  const ini = Math.max(0, Math.min(a - 2, n - 5)), fin = Math.min(n, ini + 5)
  return Array.from({ length: fin - ini }, (_, i) => ini + i)
})
function irAPagina(i) {
  pagina.value = i
  document.getElementById('resultados-usuarios')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Menú «⋯» de cada tarjeta (cierre con clic fuera o Escape, sin overlay) ───
const menuUsuario = ref(null)
function cerrarMenuFuera(ev) { if (!ev.target.closest?.('[data-menu-raiz]')) menuUsuario.value = null }
function cerrarMenuEsc(ev) { if (ev.key === 'Escape') menuUsuario.value = null }
watch(menuUsuario, (v) => {
  const m = v ? 'addEventListener' : 'removeEventListener'
  document[m]('click', cerrarMenuFuera, true)
  document[m]('keydown', cerrarMenuEsc)
})
onUnmounted(() => {
  document.removeEventListener('click', cerrarMenuFuera, true)
  document.removeEventListener('keydown', cerrarMenuEsc)
  if (highlightTimer) clearTimeout(highlightTimer)
})
function accionMenu(fn, u) { menuUsuario.value = null; fn(u) }

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
// El backend envía como mucho las 1000 cuentas más recientes de cada lista (truncado = hay más)
const truncado = ref({ activos: false, papelera: false })
async function cargar() {
  cargando.value = true
  try {
    if (vista.value === 'papelera') {
      const { data } = await api.get('/admin/usuarios/papelera')
      papelera.value = data.data
      truncado.value.papelera = !!data.truncado
    } else {
      const { data } = await api.get('/admin/usuarios')
      usuarios.value = data.data
      truncado.value.activos = !!data.truncado
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

// Desde el modal de cuenta creada
function asignarModulosRecienCreada() {
  const cuenta = cuentaRecienCreada.value
  cerrarModalExito()
  if (cuenta) editarModulos(cuenta)
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
  if (vista.value === v) return
  vista.value     = v
  filtroRol.value = 0
  filtroEstados.value = []
  menuUsuario.value = null
  cargar()
}

// ── Formato ─────────────────────────────────────────────────────────
// Chips y avatar por tipo de cuenta, con los colores de rol de la guía
const ROLE_COLORS = {
  2: { bg: 'bg-centros/10 text-centros',                         punto: 'bg-centros',         avatar: 'bg-centros/10 text-centros',                   label: 'Docente' },
  3: { bg: 'bg-empresas/10 text-empresas-dark',                  punto: 'bg-empresas',        avatar: 'bg-empresas/10 text-empresas-dark',            label: 'Empresa' },
  4: { bg: 'bg-administraciones/10 text-administraciones-dark',  punto: 'bg-administraciones', avatar: 'bg-administraciones/10 text-administraciones-dark', label: 'Admin de centro' },
}
function roleChip(role) {
  return ROLE_COLORS[role] ?? { bg: 'bg-gray-100 text-azul-noche', punto: 'bg-azul-noche', avatar: 'bg-gray-100 text-azul-noche', label: 'Superadmin' }
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
    truncado.value = { activos: !!resActivos.data.truncado, papelera: !!resPapelera.data.truncado }
  } catch (e) {
    mostrarToast(e.response?.data?.message ?? 'Error al cargar las cuentas.', false)
  } finally {
    cargando.value = false
  }
})
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Gestión de" destacado="cuentas" color="text-administraciones" antetitulo="Administración"
                       subtitulo="Crea, activa, bloquea y elimina cuentas de docentes y empresas." class="mb-5">
        <button type="button" @click="abrirModalCrear()" class="accion-principal">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/></svg>
          Nueva cuenta
        </button>
      </CabeceraSeccion>

      <!-- Pestañas: Activas | Papelera (primera decisión, fuera de la barra de filtros) -->
      <div class="mb-4 flex max-w-full rounded-full sm:w-fit">
        <div class="flex w-full gap-1 rounded-full border border-borde bg-white p-1 shadow-sm" role="group" aria-label="Qué cuentas ver">
          <button v-for="t in [
                    { v: 'activos', label: 'Activas', n: totalActivos, icono: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 100 8 4 4 0 000-8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75' },
                    { v: 'papelera', label: 'Papelera', n: totalPapelera, icono: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16' },
                  ]" :key="t.v" type="button" @click="cambiarVista(t.v)" :aria-pressed="vista === t.v"
                  class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-full px-2 text-sm font-semibold transition-colors sm:flex-none sm:gap-2 sm:px-5"
                  :class="vista === t.v ? 'bg-rol-fuerte text-white shadow-sm' : 'text-texto-secundario hover:bg-gray-50 hover:text-azul-noche'">
            <svg class="hidden h-4 w-4 shrink-0 min-[400px]:block" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="t.icono" /></svg>
            <span class="truncate">{{ t.label }}</span>
            <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="vista === t.v ? 'bg-white/20 text-white' : 'bg-gray-100 text-azul-noche'">{{ cargando ? '…' : t.n }}</span>
          </button>
        </div>
      </div>

      <!-- Filtros principales: buscador + «Más filtros» · centro (superadmin, si hay varios) -->
      <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar por nombre, email, centro o empresa…" class="mb-5">
        <template #curso>
          <button type="button" @click="panelFiltrosAbierto = !panelFiltrosAbierto" :aria-expanded="panelFiltrosAbierto"
                  aria-controls="filtros-rapidos" :title="panelFiltrosAbierto ? 'Ocultar los filtros rápidos' : 'Mostrar los filtros rápidos'"
                  class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-full border px-5 text-sm font-semibold transition-colors"
                  :class="panelFiltrosAbierto ? 'border-rol-fuerte/30 bg-rol/10 text-rol-fuerte' : 'border-rol-fuerte/30 bg-white text-rol-fuerte hover:bg-rol-fuerte/5'">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            <span>Más filtros</span>
            <span v-if="!panelFiltrosAbierto && filtrosLateral"
                  class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rol-fuerte px-1 text-[11px] font-bold text-white">{{ filtrosLateral }}</span>
          </button>
        </template>
        <!-- Slot condicional: sin filtro de centro (admin de centro) no queda una fila vacía -->
        <template v-if="authStore.isSuperAdmin && centrosDisponibles.length > 1" #default>
          <FiltroSelect v-model="filtroCentro" label="Centro educativo" todos="Todos los centros" :opciones="centrosDisponibles" />
        </template>
      </FiltrosPanel>

      <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        <!-- ═══ Filtros rápidos ═══ -->
        <aside id="filtros-rapidos" aria-label="Filtros rápidos"
               class="w-full shrink-0 rounded-2xl border border-borde bg-white p-4 shadow-sm lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:w-64 lg:overflow-y-auto xl:w-72"
               :class="panelFiltrosAbierto ? 'block' : 'hidden'">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-azul-noche">Filtros rápidos</h2>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros"
                    class="inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">Limpiar</button>
          </div>

          <div class="border-t border-borde pt-3" role="radiogroup" aria-labelledby="titulo-filtro-tipo">
            <p id="titulo-filtro-tipo" class="mb-1.5 text-sm font-semibold text-azul-noche">Tipo de cuenta</p>
            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="radio" name="usr-filtro-tipo" :value="0" v-model="filtroRol" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-gray-200" aria-hidden="true" />
              <span class="min-w-0 flex-1 text-sm text-azul-noche">Todas</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoTipos[0] }}</span>
            </label>
            <label v-for="t in tiposPanel" :key="t.val" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="radio" name="usr-filtro-tipo" :value="t.val" v-model="filtroRol" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="t.punto" aria-hidden="true" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ t.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoTipos[t.val] }}</span>
            </label>
          </div>

          <div class="mt-3 border-t border-borde pt-3" role="group" aria-labelledby="titulo-filtro-estado-cuenta">
            <p id="titulo-filtro-estado-cuenta" class="mb-1.5 text-sm font-semibold text-azul-noche">Estado</p>
            <label v-for="e in ESTADOS" :key="e.val" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" :checked="filtroEstados.includes(e.val)" @change="alternarEstado(e.val)" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="e.punto" aria-hidden="true" />
              <span class="min-w-0 flex-1 truncate text-sm text-azul-noche">{{ e.label }}</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoEstados[e.val] }}</span>
            </label>
          </div>

          <div v-if="vista === 'activos' && (conteoSinModulos || filtroSinModulos)" class="mt-3 border-t border-borde pt-3"
               role="group" aria-labelledby="titulo-filtro-modulos">
            <p id="titulo-filtro-modulos" class="mb-1.5 text-sm font-semibold text-azul-noche">Módulos que imparte</p>
            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-1 py-2 hover:bg-gray-50">
              <input type="checkbox" v-model="filtroSinModulos" class="h-4 w-4 shrink-0 accent-rol-fuerte" />
              <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-400" aria-hidden="true" />
              <span class="min-w-0 flex-1 text-sm leading-snug text-azul-noche">Docentes sin módulos</span>
              <span class="shrink-0 text-xs text-texto-secundario">{{ conteoSinModulos }}</span>
            </label>
          </div>
        </aside>

        <!-- ═══ Resultados ═══ -->
        <section id="resultados-usuarios" class="@container/lista min-w-0 flex-1 scroll-mt-24" aria-label="Cuentas">
          <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <TituloResultados :niveles="nivelesTitulo" :cargando="cargando">
              <template v-if="cargando">Cargando cuentas…</template>
              <template v-else>{{ usuariosFiltrados.length }} {{ usuariosFiltrados.length === 1 ? 'cuenta' : 'cuentas' }}<template v-if="vista === 'papelera'"> en la papelera</template></template>
            </TituloResultados>
            <div v-if="filtroRol || filtroEstados.length || filtroSinModulos" class="flex w-full flex-wrap gap-1.5 sm:order-last">
              <button v-if="filtroSinModulos" type="button" @click="filtroSinModulos = false" class="chip-filtro" aria-label="Quitar filtro Docentes sin módulos">
                Sin módulos<span aria-hidden="true">✕</span>
              </button>
              <button v-if="filtroRol" type="button" @click="filtroRol = 0" class="chip-filtro" :aria-label="`Quitar filtro ${etiquetaTipo(filtroRol)}`">
                {{ etiquetaTipo(filtroRol) }}<span aria-hidden="true">✕</span>
              </button>
              <button v-for="e in filtroEstados" :key="e" type="button" @click="alternarEstado(e)" class="chip-filtro" :aria-label="`Quitar filtro ${etiquetaEstado(e)}`">
                {{ etiquetaEstado(e) }}<span aria-hidden="true">✕</span>
              </button>
            </div>
            <label class="flex items-center gap-2 text-sm text-texto-secundario">
              <span class="hidden sm:inline">Ordenar por</span>
              <select v-model="orden" class="h-9 rounded-xl px-3 text-sm text-azul-noche" aria-label="Ordenar cuentas">
                <option value="recientes">{{ vista === 'papelera' ? 'Eliminadas hace menos' : 'Más recientes' }}</option>
                <option value="nombre">Nombre (A-Z)</option>
                <option value="pendientes">Sin activar primero</option>
              </select>
            </label>
          </div>

          <!-- Carga -->
          <div v-if="cargando" class="flex flex-col gap-3" aria-busy="true">
            <div v-for="n in 6" :key="n" class="h-40 animate-pulse rounded-2xl border border-borde bg-white @xl/lista:h-20" />
            <span class="sr-only">Cargando cuentas…</span>
          </div>

          <!-- Sin resultados -->
          <div v-else-if="!usuariosFiltrados.length" class="rounded-2xl border border-dashed border-borde bg-white px-6 py-14 text-center">
            <h3 class="text-lg font-bold text-azul-noche">
              {{ hayFiltrosActivos ? 'No hay cuentas con estos filtros' : vista === 'papelera' ? 'La papelera está vacía' : 'Todavía no hay cuentas' }}
            </h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-texto-secundario">
              {{ hayFiltrosActivos ? 'Prueba a quitar algún filtro.' : vista === 'papelera' ? 'Las cuentas eliminadas aparecerán aquí y se podrán restaurar.' : 'Crea la primera con «Nueva cuenta».' }}
            </p>
            <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros" class="accion-secundaria mt-4">Limpiar filtros</button>
          </div>

          <!-- Tarjetas horizontales, una por fila: identidad | centro y fecha | acciones.
               Pasan a fila cuando la lista mide ≥ 36rem (container query): así se adaptan solas
               con el menú lateral y los filtros abiertos o cerrados. Más estrecha, se apilan. -->
          <ul v-else class="flex flex-col gap-3">
            <li v-for="u in itemsPagina" :key="u.id"
                class="relative flex min-w-0 flex-col gap-3 rounded-2xl border bg-white p-4 shadow-sm transition-shadow hover:shadow-md @xl/lista:flex-row @xl/lista:flex-wrap @xl/lista:items-center @xl/lista:gap-5"
                :class="[u.is_blocked ? 'border-red-200' : 'border-borde', { 'z-10': menuUsuario === u.id || highlightId === u.id }]">
              <div class="flex min-w-0 items-start gap-3 @xl/lista:flex-1 @xl/lista:items-center">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full font-heading text-base font-bold select-none"
                      :class="roleChip(u.role).avatar" aria-hidden="true">{{ (u.name || '?').charAt(0).toUpperCase() }}</span>
                <div class="min-w-0 flex-1">
                  <h3 class="truncate font-heading text-base font-bold leading-snug text-azul-noche" :title="u.name">{{ u.name }}</h3>
                  <p class="truncate text-sm text-texto-secundario" :title="u.email">{{ u.email }}</p>

                  <!-- Tipo y estado: etiquetas informativas (sin borde ni hover, con punto o icono) para
                       que no se confundan con el único botón de esta línea, el de módulos -->
                  <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold" :class="roleChip(u.role).bg">
                      <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="roleChip(u.role).punto" aria-hidden="true"></span>
                      {{ roleChip(u.role).label }}
                    </span>
                    <span v-if="u.is_blocked" class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">
                      <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.36 5.64L5.64 18.36M12 21a9 9 0 100-18 9 9 0 000 18z"/></svg>
                      Bloqueada
                    </span>
                    <span v-else-if="u.is_active" class="inline-flex items-center gap-1 rounded-full bg-empresas/10 px-2 py-0.5 text-xs font-semibold text-empresas-dark">
                      <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                      Activada
                    </span>
                    <span v-else class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700">
                      <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 7v5l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      Sin activar
                    </span>
                    <!-- Docente: desplegable con sus módulos (misma ficha que «Mi usuario») -->
                    <button v-if="vista === 'activos' && u.role === 2 && u.num_modulos != null" type="button"
                            @click="alternarModulos(u.id)" :aria-expanded="modulosAbiertos.has(u.id)" :aria-controls="`modulos-fila-${u.id}`"
                            class="relative inline-flex cursor-pointer items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold shadow-sm transition-colors
                                   before:absolute before:-inset-x-1 before:-inset-y-2 before:content-['']"
                            :class="sinModulos(u) ? 'border-amber-300 bg-white text-amber-700 hover:bg-amber-50' : 'border-gray-300 bg-white text-azul-noche hover:bg-gray-50'"
                            :title="sinModulos(u) ? 'Aún no ha indicado los módulos que imparte: no aparecerá como candidato a colaborar' : 'Ver los módulos que imparte'">
                      {{ sinModulos(u) ? 'Sin módulos' : `${u.num_modulos} módulo${u.num_modulos === 1 ? '' : 's'}` }}
                      <svg class="h-3 w-3 shrink-0 transition-transform" :class="modulosAbiertos.has(u.id) && 'rotate-180'"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Centro o empresa y fecha (columna de ancho fijo en fila, para que se alineen) -->
              <dl class="grid gap-1 text-sm @xl/lista:w-44 @xl/lista:shrink-0 @4xl/lista:w-56">
                <div class="flex min-w-0 items-center gap-2">
                  <dt class="w-16 shrink-0 text-xs font-semibold text-texto-secundario">{{ u.role === 3 ? 'Empresa' : 'Centro' }}</dt>
                  <dd class="min-w-0 truncate font-semibold text-azul-noche" :title="u.role === 3 ? u.empresa_nombre : u.centro_nombre">
                    {{ (u.role === 3 ? u.empresa_nombre : u.centro_nombre) || '—' }}
                  </dd>
                </div>
                <div class="flex min-w-0 items-center gap-2">
                  <dt class="w-16 shrink-0 text-xs font-semibold text-texto-secundario">{{ vista === 'papelera' ? 'Eliminada' : 'Creada' }}</dt>
                  <dd class="min-w-0 truncate text-azul-noche">{{ fmtDate(vista === 'papelera' ? u.deleted_at : u.created_at) }}</dd>
                </div>
              </dl>

              <!-- Acción principal + «⋯» (en móvil, pie con separador; en fila, a la derecha) -->
              <div class="flex items-center justify-end gap-2 border-t border-borde pt-3 @xl/lista:shrink-0 @xl/lista:border-0 @xl/lista:pt-0">
                <template v-if="vista === 'activos'">
                  <!-- Sin activar: «Activar» es lo principal (con el puntero tras crear la cuenta) -->
                  <div v-if="!u.is_active" class="relative shrink-0">
                    <Transition name="pointer">
                      <div v-if="highlightId === u.id"
                           class="pointer-events-none absolute -top-11 right-0 z-10 flex items-center gap-1.5 whitespace-nowrap rounded-xl bg-administraciones px-3 py-1.5 text-xs font-bold text-white shadow-lg">
                        Activa esta cuenta aquí
                        <span class="absolute -bottom-1.5 right-8 h-3 w-3 rotate-45 rounded-sm bg-administraciones" />
                      </div>
                    </Transition>
                    <button type="button" :ref="el => { if (el) activarBtns[u.id] = el }" @click="onActivarClick(u)"
                            class="inline-flex h-9 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5"
                            :class="highlightId === u.id ? 'animate-pulse-soft' : ''">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                      Activar
                    </button>
                  </div>
                  <button v-else type="button" @click="abrirModalEditar(u)"
                          class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Editar
                  </button>
                </template>
                <button v-else type="button" @click="restaurar(u)"
                        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  Restaurar
                </button>

                <div class="relative shrink-0" data-menu-raiz>
                  <button type="button" @click="menuUsuario = menuUsuario === u.id ? null : u.id"
                          :aria-expanded="menuUsuario === u.id" aria-haspopup="menu" :aria-label="`Más acciones de la cuenta de ${u.name}`"
                          class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde text-texto-secundario hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                  </button>
                  <div v-if="menuUsuario === u.id" role="menu"
                       class="absolute bottom-full right-0 z-30 mb-1 w-[min(15rem,calc(100vw-3rem))] rounded-xl border border-borde bg-white py-1 shadow-lg">
                    <template v-if="vista === 'activos'">
                      <button v-if="!u.is_active" type="button" role="menuitem" @click="accionMenu(abrirModalEditar, u)"
                              class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Editar</button>
                      <button v-if="authStore.isSuperAdmin && [2, 4].includes(u.role)" type="button" role="menuitem" @click="accionMenu(abrirModalCentro, u)"
                              class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">{{ u.centro_nombre ? 'Cambiar centro educativo' : 'Asociar centro educativo' }}</button>
                      <button type="button" role="menuitem" @click="accionMenu(toggleBloquear, u)"
                              class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">{{ u.is_blocked ? 'Desbloquear' : 'Bloquear' }}</button>
                      <button type="button" role="menuitem" @click="accionMenu(enviarPapelera, u)"
                              class="block w-full border-t border-borde px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Enviar a la papelera</button>
                    </template>
                    <button v-else type="button" role="menuitem" @click="accionMenu(destruir, u)"
                            class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Eliminar definitivamente</button>
                  </div>
                </div>
              </div>

              <!-- Desplegable de módulos (ocupa todo el ancho también con la fila en horizontal) -->
              <div v-if="vista === 'activos' && modulosAbiertos.has(u.id)" :id="`modulos-fila-${u.id}`"
                   class="w-full space-y-3 border-t border-borde pt-4 @xl/lista:basis-full">
                <PanelModulosDocente :key="`${u.id}-${revModulos[u.id] || 0}`" :usuario="u"
                                     :cabecera="false" :explicacion="false" :editable="false" />
                <div class="flex justify-end">
                  <button type="button" @click="editarModulos(u)"
                          class="inline-flex min-h-9 items-center rounded-full border border-rol-fuerte/40 bg-white px-4 py-1.5
                                 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                    Editar módulos
                  </button>
                </div>
              </div>
            </li>
          </ul>

          <!-- Tope del listado alcanzado (el backend envía como mucho 1000 por lista) -->
          <p v-if="!cargando && truncado[vista]" role="status"
             class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Se muestran las 1000 cuentas más recientes; las más antiguas no aparecen en esta lista ni en la búsqueda.
          </p>

          <!-- Paginación -->
          <div v-if="!cargando && totalPaginas > 1" class="mt-5 flex flex-col flex-wrap items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-texto-secundario">Mostrando {{ itemsPagina.length }} de {{ usuariosFiltrados.length }} cuentas</p>
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación de cuentas">
              <button type="button" @click="irAPagina(0)" :disabled="pagina === 0" aria-label="Primera página"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">«</button>
              <button type="button" @click="irAPagina(pagina - 1)" :disabled="pagina === 0" aria-label="Página anterior"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">‹</button>
              <button v-for="i in paginasVisibles" :key="i" type="button" @click="irAPagina(i)"
                      :aria-current="i === pagina ? 'page' : undefined" :aria-label="`Página ${i + 1}`"
                      class="inline-flex h-9 min-w-9 items-center justify-center rounded-full px-2 text-sm font-semibold"
                      :class="i === pagina ? 'bg-rol-fuerte text-white' : 'text-azul-noche hover:bg-gray-100'">{{ i + 1 }}</button>
              <button type="button" @click="irAPagina(pagina + 1)" :disabled="pagina >= totalPaginas - 1" aria-label="Página siguiente"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">›</button>
              <button type="button" @click="irAPagina(totalPaginas - 1)" :disabled="pagina >= totalPaginas - 1" aria-label="Última página"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 disabled:opacity-40">»</button>
            </nav>
          </div>
        </section>
      </div>
    </div>

    <!-- ══ MODAL: Crear cuenta ═════════════════════════════════════ -->
    <Modal :visible="modalCrear" @cerrar="modalCrear = false">
            <div class="px-5 pt-6 pb-4 shrink-0 sm:px-8 sm:pt-8">
              <h2 class="text-lg font-black mb-1 text-azul-noche">Nueva cuenta</h2>
              <p class="text-xs text-gray-500">
                La cuenta quedará pendiente de activación hasta que la valides.
              </p>
            </div>

            <div class="overflow-y-auto px-5 pb-6 pt-1 sm:px-8 sm:pb-8">
            <form @submit.prevent="crearUsuario" class="space-y-4">
              <!-- Nombre -->
              <div>
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Nombre</label>
                <input v-model="form.name" type="text" placeholder="Nombre completo"
                  maxlength="255"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formErrors.name ? 'border-red-400' : 'border-gray-200'" />
                <div class="flex justify-between items-center mt-1">
                  <p v-if="formErrors.name" class="text-xs text-red-500">{{ formErrors.name }}</p>
                  <span v-else></span>
                  <span class="text-[11px] sm:text-[10px]" :class="(form.name || '').length >= 245 ? 'text-amber-500' : 'text-gray-300'">{{ (form.name || '').length }}/255</span>
                </div>
              </div>

              <!-- Correo electrónico -->
              <div>
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Correo electrónico</label>
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
                         focus-within:border-administraciones/50 focus-within:ring-2 focus-within:ring-administraciones/10"
                  :class="formErrors.email ? 'border-red-400' : 'border-gray-200'">
                  <input v-model="formEmailLocal" type="text" placeholder="nombreusuario"
                    class="flex-1 min-w-0 bg-transparent px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300 outline-none" />
                  <span class="min-w-0 max-w-[60%] truncate pr-4 text-sm text-gray-400 select-none" :title="`@${activeEmailDomain}`">@{{ activeEmailDomain }}</span>
                </div>
                <!-- Fallback: input libre (no superadmin) -->
                <input v-else v-model="form.email" type="email" placeholder="correo@ejemplo.com"
                  maxlength="254"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formErrors.email ? 'border-red-400' : 'border-gray-200'" />
                <div class="flex justify-between items-center mt-1">
                  <p v-if="formErrors.email" class="text-xs text-red-500">{{ formErrors.email }}</p>
                  <span v-else></span>
                  <span v-if="activeEmailDomain" class="text-[11px] sm:text-[10px]" :class="(form.email || '').length >= 244 ? 'text-amber-500' : 'text-gray-300'">{{ (form.email || '').length }}/254</span>
                </div>
              </div>

              <!-- Contraseña -->
              <div>
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Contraseña temporal</label>
                <input v-model="form.password" type="password" placeholder="Mín. 8 caracteres, mayúsculas, minúsculas, número y símbolo"
                  maxlength="128"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formErrors.password ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="formErrors.password" class="text-xs text-red-500 font-bold mt-1.5">{{ formErrors.password }}</p>
                <ul class="mt-1.5 grid grid-cols-1 gap-x-3 gap-y-0.5 sm:grid-cols-2">
                  <li v-for="req in passwordRequisitos" :key="req.label"
                    class="flex items-center gap-1 text-xs transition-colors"
                    :class="req.ok ? 'text-administraciones font-bold' : 'text-gray-400'">
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
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Repite la contraseña</label>
                <input v-model="form.password_confirmation" type="password" placeholder="Vuelve a escribir la contraseña"
                  maxlength="128"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formErrors.password_confirmation ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="formErrors.password_confirmation" class="text-xs text-red-500 mt-1">{{ formErrors.password_confirmation }}</p>
              </div>

              <!-- Tipo de cuenta (solo visible para superadmin; admin de centro siempre crea docentes) -->
              <div v-if="authStore.isSuperAdmin">
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Tipo de cuenta</label>
                <div class="flex gap-3">
                  <!-- Docente: activo si role es 2 (docente) o 4 (admin de centro) -->
                  <button type="button"
                    @click="form.role = ['2','4'].includes(form.role) ? form.role : '2'"
                    :class="['2','4'].includes(form.role) ? 'bg-centros/10 border-centros/40 text-centros' : 'bg-gray-50 border-gray-200 text-texto-secundario hover:text-azul-noche'"
                    class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-2 rounded-full border px-3 text-sm font-semibold transition-all">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 3h6v4H9V3z"/>
                    </svg>
                    Docente
                  </button>
                  <button type="button" @click="form.role = '3'; form.centro_educativo_id = null"
                    :class="form.role === '3' ? 'bg-empresas/10 border-empresas/40 text-empresas-dark' : 'bg-gray-50 border-gray-200 text-texto-secundario hover:text-azul-noche'"
                    class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-2 rounded-full border px-3 text-sm font-semibold transition-all">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                      <p class="text-[11px] sm:text-[10px] text-gray-400 mt-0.5">Gestiona los docentes de su centro educativo</p>
                    </div>
                  </label>
                </div>

                <p v-if="formErrors.role" class="text-xs text-red-500 mt-1">{{ formErrors.role }}</p>
              </div>

              <!-- Centro educativo (requerido para docente/admin) -->
              <div v-if="authStore.isSuperAdmin && ['2','4'].includes(form.role)">
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                  Centro educativo
                  <span class="text-red-400 ml-0.5">*</span>
                </label>
                <div v-if="cargandoCentros" class="text-xs text-gray-400 py-2">Cargando centros…</div>
                <select v-else v-model="form.centro_educativo_id"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formErrors.centro_educativo_id ? 'border-red-400' : 'border-gray-200'">
                  <option :value="null">— Selecciona un centro —</option>
                  <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
                <p v-if="formErrors.centro_educativo_id" class="text-xs text-red-500 mt-1">{{ formErrors.centro_educativo_id }}</p>
              </div>

              <!-- Empresa (requerida para role empresa) -->
              <div v-if="authStore.isSuperAdmin && form.role === '3'">
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                  Empresa
                  <span class="text-red-400 ml-0.5">*</span>
                </label>
                <div v-if="cargandoEmpresas" class="text-xs text-gray-400 py-2">Cargando empresas…</div>
                <select v-else v-model="form.empresa_id"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formErrors.empresa_id ? 'border-red-400' : 'border-gray-200'">
                  <option :value="null">— Selecciona una empresa —</option>
                  <option v-for="e in empresasList" :key="e.id" :value="e.id">{{ e.nombre_comercial }}</option>
                </select>
                <p v-if="formErrors.empresa_id" class="text-xs text-red-500 mt-1">{{ formErrors.empresa_id }}</p>
              </div>

              <p v-if="msgCrear" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-xl px-3 py-2">
                {{ msgCrear }}
              </p>

              <button type="submit" :disabled="creando"
                class="w-full py-3 rounded-full bg-administraciones hover:bg-administraciones/90 text-white font-black
                       text-xs uppercase tracking-widest transition-all disabled:opacity-50 disabled:cursor-not-allowed mt-2">
                {{ creando ? 'Creando...' : 'Crear cuenta' }}
              </button>
            </form>
            </div>
    </Modal>

    <!-- ══ MODAL: Éxito al crear cuenta ═══════════════════════════ -->
    <Modal :visible="modalExito" max-width="max-w-sm" :z-index="9100" :closable="false" card-class="p-6 sm:p-8 text-center overflow-y-auto">

            <!-- Icono -->
            <div class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-administraciones/10 border border-administraciones/20
                        flex items-center justify-center">
              <svg class="w-8 h-8 text-administraciones" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            </div>

            <h2 class="text-xl font-black mb-2 text-azul-noche">¡Cuenta creada!</h2>
            <p class="text-sm text-gray-500 leading-relaxed mb-2 break-words">
              La cuenta de
              <span class="text-[#1F2937] font-bold">{{ cuentaRecienCreada?.name }}</span>
              se ha creado correctamente.
            </p>
            <p class="text-xs text-administraciones font-bold flex items-center justify-center gap-1.5">
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
                  Pulsa el botón <span class="text-administraciones font-bold">Activar</span> en la fila
                  de esta cuenta para habilitarla.
                </p>
              </div>
            </div>

            <!-- Docente: recordar que indique sus módulos (o asignárselos ya) -->
            <div v-if="cuentaRecienCreada?.role === 2"
                 class="-mt-2 mb-6 rounded-xl bg-rol-fuerte/5 border border-rol-fuerte/20 px-4 py-3.5 text-left">
              <p class="text-xs font-black text-rol-fuerte uppercase tracking-wider mb-0.5">Módulos que imparte</p>
              <p class="text-xs text-gray-500 leading-relaxed break-words">
                Recuerda a <span class="font-bold text-[#1F2937]">{{ cuentaRecienCreada?.name }}</span> que indique en
                <span class="font-bold">Mi usuario</span> los módulos que imparte: sin ellos no podrá colaborar en los
                encuentros de sus compañeros. También puedes asignárselos tú ahora.
              </p>
              <button type="button" @click="asignarModulosRecienCreada"
                      class="mt-2.5 inline-flex min-h-9 items-center rounded-full border border-rol-fuerte/30 bg-white px-4 py-1.5
                             text-xs font-black text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
                Asignar módulos ahora
              </button>
            </div>

            <button @click="cerrarModalExito"
              class="w-full py-3 rounded-full bg-administraciones hover:bg-administraciones/90 text-white
                     font-black text-xs uppercase tracking-widest transition-all">
              Entendido, ir a activar
            </button>
    </Modal>

    <!-- ══ MODAL: Asociar centro ═══════════════════════════════════ -->
    <Transition name="overlay">
      <div v-if="modalCentro"
           class="fixed inset-0 z-[9000] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
           @click.self="modalCentro = false">
        <Transition name="modal-scale">
          <div v-if="modalCentro"
               class="relative bg-white border border-gray-200 rounded-[2rem] shadow-2xl
                      w-full max-w-lg flex flex-col max-h-[85dvh]">

            <!-- Cabecera -->
            <div class="px-6 pt-6 pb-4 border-b border-gray-100 shrink-0">
              <button type="button" @click="modalCentro = false" aria-label="Cerrar"
                class="absolute top-4 right-4 w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200
                       flex items-center justify-center text-gray-400 hover:text-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
              <h2 class="text-base font-black mb-0.5 text-azul-noche">Asociar centro educativo</h2>
              <p class="text-xs text-gray-500">
                Docente: <span class="text-[#1F2937] font-bold">{{ usuarioCentro?.name }}</span>
              </p>

              <!-- Centro actual -->
              <div v-if="usuarioCentro?.centro_nombre"
                   class="mt-3 flex items-center justify-between gap-2
                          bg-blue-50 border border-blue-200 rounded-xl px-3 py-1.5">
                <div class="flex min-w-0 items-center gap-2 text-xs text-blue-600">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                  </svg>
                  <span class="min-w-0 break-words font-bold">{{ usuarioCentro.centro_nombre }}</span>
                </div>
                <button type="button" @click="desvincularCentro"
                  class="inline-flex h-9 shrink-0 items-center rounded-full px-3 text-xs font-bold text-red-600 hover:bg-red-100 transition-colors">
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
                         focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10 transition-all"
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
              <p class="text-[11px] sm:text-[10px] text-gray-400 text-center">
                {{ centrosFiltrados.length }} centro{{ centrosFiltrados.length !== 1 ? 's' : '' }} encontrado{{ centrosFiltrados.length !== 1 ? 's' : '' }}
              </p>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- ══ MODAL: Editar cuenta ══════════════════════════════════ -->
    <Modal :visible="modalEditar" @cerrar="modalEditar = false">
            <!-- Cabecera fija -->
            <div class="px-5 pt-6 pb-4 shrink-0 sm:px-8 sm:pt-8">
              <!-- Aviso de seguridad -->
              <div class="mb-4 mr-10 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3">
                <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <div>
                  <p class="text-xs font-black text-amber-600 uppercase tracking-wider mb-0.5">Edición de cuenta sensible</p>
                  <p class="text-xs text-gray-500">Estás modificando los datos de <span class="text-[#1F2937] font-bold">{{ usuarioEditando?.name }}</span>. Cualquier cambio tendrá efecto inmediato.</p>
                </div>
              </div>
              <h2 class="text-lg font-black mb-1 text-azul-noche">Editar cuenta</h2>
              <p class="text-xs text-gray-500">Modifica los datos de la cuenta. Deja la contraseña en blanco para no cambiarla.</p>
            </div>

            <!-- Cuerpo scrollable -->
            <div class="overflow-y-auto px-5 pb-6 pt-1 sm:px-8 sm:pb-8">
            <form @submit.prevent="guardarEdicion" class="space-y-4">
              <!-- Nombre -->
              <div>
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Nombre</label>
                <input v-model="formEditar.name" type="text" placeholder="Nombre completo"
                  maxlength="255"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formEditarErrors.name ? 'border-red-400' : 'border-gray-200'" />
                <div class="flex justify-between items-center mt-1">
                  <p v-if="formEditarErrors.name" class="text-xs text-red-500">{{ formEditarErrors.name }}</p>
                  <span v-else></span>
                  <span class="text-[11px] sm:text-[10px]" :class="(formEditar.name || '').length >= 245 ? 'text-amber-500' : 'text-gray-300'">{{ (formEditar.name || '').length }}/255</span>
                </div>
              </div>

              <!-- Correo electrónico -->
              <div>
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Correo electrónico</label>
                <!-- Con dominio (centro o empresa): input dividido -->
                <div v-if="activeEmailEditarDomain"
                  class="flex items-center bg-gray-50 border rounded-xl overflow-hidden transition-all
                         focus-within:border-administraciones/50 focus-within:ring-2 focus-within:ring-administraciones/10"
                  :class="formEditarErrors.email ? 'border-red-400' : 'border-gray-200'">
                  <input v-model="formEditarEmailLocal" type="text" placeholder="nombreusuario"
                    class="flex-1 min-w-0 bg-transparent px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300 outline-none" />
                  <span class="min-w-0 max-w-[60%] truncate pr-4 text-sm text-gray-400 select-none" :title="`@${activeEmailEditarDomain}`">@{{ activeEmailEditarDomain }}</span>
                </div>
                <!-- Sin dominio: input libre -->
                <input v-else v-model="formEditar.email" type="email" placeholder="correo@ejemplo.com"
                  maxlength="254"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formEditarErrors.email ? 'border-red-400' : 'border-gray-200'" />
                <div class="flex justify-between items-center mt-1">
                  <p v-if="formEditarErrors.email" class="text-xs text-red-500">{{ formEditarErrors.email }}</p>
                  <span v-else></span>
                  <span class="text-[11px] sm:text-[10px]" :class="(formEditar.email || '').length >= 244 ? 'text-amber-500' : 'text-gray-300'">{{ (formEditar.email || '').length }}/254</span>
                </div>
              </div>

              <!-- Nueva contraseña (opcional) -->
              <div>
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Nueva contraseña <span class="normal-case text-gray-400 font-normal">(opcional)</span></label>
                <input v-model="formEditar.password" type="password" placeholder="Dejar en blanco para no cambiar"
                  maxlength="128"
                  class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formEditarErrors.password ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="formEditarErrors.password" class="text-xs text-red-500 font-bold mt-1.5">{{ formEditarErrors.password }}</p>
                <ul v-if="formEditar.password" class="mt-1.5 grid grid-cols-1 gap-x-3 gap-y-0.5 sm:grid-cols-2">
                  <li v-for="req in passwordRequisitosEditar" :key="req.label"
                    class="flex items-center gap-1 text-xs transition-colors"
                    :class="req.ok ? 'text-administraciones font-bold' : 'text-gray-400'">
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
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1.5">Repite la nueva contraseña</label>
                <input v-model="formEditar.password_confirmation" type="password" placeholder="Vuelve a escribir la contraseña"
                  maxlength="128"
                  class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-[#1F2937] placeholder-gray-300
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formEditarErrors.password_confirmation ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="formEditarErrors.password_confirmation" class="text-xs text-red-500 mt-1">{{ formEditarErrors.password_confirmation }}</p>
              </div>

              <!-- Switch de confirmación: obliga a un clic deliberado antes de resetear la contraseña de otra cuenta -->
              <div v-if="formEditar.password" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3.5">
                <label class="flex items-start gap-3 cursor-pointer select-none">
                  <!-- Zona táctil de 36px alrededor del interruptor -->
                  <button type="button" role="switch" :aria-checked="confirmarCambioPassword"
                    @click="confirmarCambioPassword = !confirmarCambioPassword"
                    class="-my-1.5 inline-flex h-9 w-14 shrink-0 items-center justify-center rounded-full">
                    <span :class="confirmarCambioPassword ? 'bg-red-500' : 'bg-gray-300'"
                      class="relative block h-6 w-12 rounded-full transition-all duration-200">
                      <span :class="confirmarCambioPassword ? 'translate-x-6' : 'translate-x-1'"
                        class="absolute top-0.5 left-0 block h-5 w-5 rounded-full bg-white shadow transition-transform duration-200"></span>
                    </span>
                  </button>
                  <div>
                    <span class="text-xs font-black text-red-600">Confirmo que quiero cambiar la contraseña de esta cuenta</span>
                    <p class="text-[11px] sm:text-[10px] text-gray-500 mt-0.5">Se cerrará la sesión en todos los dispositivos donde estuviera conectada.</p>
                  </div>
                </label>
                <p v-if="formEditarErrors.confirm_password_change" class="text-xs text-red-600 font-bold mt-2">{{ formEditarErrors.confirm_password_change }}</p>
              </div>

              <!-- Tipo de cuenta (solo visible para superadmin) -->
              <div v-if="authStore.isSuperAdmin">
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Tipo de cuenta</label>
                <div class="flex gap-3">
                  <!-- Docente: activo si role es 2 (docente) o 4 (admin de centro) -->
                  <button type="button"
                    @click="formEditar.role = ['2','4'].includes(formEditar.role) ? formEditar.role : '2'"
                    :class="['2','4'].includes(formEditar.role) ? 'bg-centros/10 border-centros/40 text-centros' : 'bg-gray-50 border-gray-200 text-texto-secundario hover:text-azul-noche'"
                    class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-2 rounded-full border px-3 text-sm font-semibold transition-all">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 3h6v4H9V3z"/>
                    </svg>
                    Docente
                  </button>
                  <button type="button" @click="formEditar.role = '3'"
                    :class="formEditar.role === '3' ? 'bg-empresas/10 border-empresas/40 text-empresas-dark' : 'bg-gray-50 border-gray-200 text-texto-secundario hover:text-azul-noche'"
                    class="inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-2 rounded-full border px-3 text-sm font-semibold transition-all">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                      <p class="text-[11px] sm:text-[10px] text-gray-400 mt-0.5">Gestiona los docentes de su centro educativo</p>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Centro educativo (visible para cualquier docente) -->
              <div v-if="authStore.isSuperAdmin && ['2','4'].includes(formEditar.role)">
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                  Centro educativo
                  <span class="text-red-400 ml-0.5">*</span>
                </label>
                <div v-if="cargandoCentros" class="text-xs text-gray-400 py-2">Cargando centros…</div>
                <select v-else v-model="formEditar.centro_educativo_id"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formEditarErrors.centro_educativo_id ? 'border-red-400' : 'border-gray-200'">
                  <option :value="null">— Selecciona un centro —</option>
                  <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
                <p v-if="formEditarErrors.centro_educativo_id" class="text-xs text-red-500 mt-1">{{ formEditarErrors.centro_educativo_id }}</p>
              </div>

              <!-- Empresa (requerida para role empresa) -->
              <div v-if="authStore.isSuperAdmin && formEditar.role === '3'">
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                  Empresa
                  <span class="text-red-400 ml-0.5">*</span>
                </label>
                <div v-if="cargandoEmpresas" class="text-xs text-gray-400 py-2">Cargando empresas…</div>
                <select v-else v-model="formEditar.empresa_id"
                  class="w-full bg-gray-50 border rounded-xl px-4 py-2.5 text-sm text-[#1F2937]
                         outline-none transition-all focus:border-administraciones/50 focus:ring-2 focus:ring-administraciones/10"
                  :class="formEditarErrors.empresa_id ? 'border-red-400' : 'border-gray-200'">
                  <option :value="null">— Selecciona una empresa —</option>
                  <option v-for="e in empresasList" :key="e.id" :value="e.id">{{ e.nombre_comercial }}</option>
                </select>
                <p v-if="formEditarErrors.empresa_id" class="text-xs text-red-500 mt-1">{{ formEditarErrors.empresa_id }}</p>
              </div>

              <!-- Switch de activación -->
              <div>
                <label class="block text-[11px] sm:text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Activar aquí</label>
                <div class="flex items-center gap-3">
                  <button type="button" role="switch" :aria-checked="!!usuarioEditando?.is_active"
                    :aria-label="usuarioEditando?.is_active ? 'Cuenta activada' : 'Activar la cuenta'"
                    @click="!usuarioEditando?.is_active && activarEnModal()"
                    :class="usuarioEditando?.is_active ? 'cursor-default' : 'cursor-pointer'"
                    class="group inline-flex h-9 w-14 shrink-0 items-center justify-center rounded-full">
                    <span :class="usuarioEditando?.is_active ? 'bg-administraciones' : 'bg-orange-400 group-hover:bg-orange-500'"
                      class="relative block h-6 w-12 rounded-full transition-all duration-200">
                      <span :class="usuarioEditando?.is_active ? 'translate-x-6' : 'translate-x-1'"
                        class="absolute top-0.5 left-0 block h-5 w-5 rounded-full bg-white shadow transition-transform duration-200"></span>
                    </span>
                  </button>
                  <span :class="usuarioEditando?.is_active ? 'text-administraciones' : 'text-orange-500'"
                        class="text-xs font-bold">
                    {{ usuarioEditando?.is_active ? 'Activada' : 'Sin activar' }}
                  </span>
                </div>
              </div>

              <p v-if="msgEditar" class="text-xs text-red-500 bg-red-50 border border-red-200 rounded-xl px-3 py-2">
                {{ msgEditar }}
              </p>

              <button type="submit" :disabled="editando"
                class="w-full py-3 rounded-full bg-administraciones hover:bg-administraciones/90 text-white font-black
                       text-xs uppercase tracking-widest transition-all disabled:opacity-50 disabled:cursor-not-allowed mt-2">
                {{ editando ? 'Guardando...' : 'Guardar cambios' }}
              </button>
            </form>

            <!-- Módulos que imparte (solo docentes): se guardan aparte del resto de la cuenta -->
            <div v-if="Number(usuarioEditando?.role) === 2" id="modulos-docente-editar" class="mt-6 border-t border-gray-100 pt-5 scroll-mt-4">
              <PanelModulosDocente :key="usuarioEditando.id" :usuario="usuarioEditando" :cabecera="false" :ficha="false"
                                   @guardado="onModulosGuardados" />
            </div>
            </div>
    </Modal>

    <!-- ══ MODAL: Confirmación ════════════════════════════════════ -->
    <Transition name="overlay">
      <div v-if="confirm.show"
           class="fixed inset-0 z-[9200] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
           @click.self="confirm.show = false">
        <div class="bg-white border border-gray-200 rounded-[2rem] shadow-2xl w-full max-w-sm p-6 overflow-y-auto max-h-[90dvh]" role="alertdialog" aria-modal="true" :aria-label="confirm.title">
          <h3 class="font-black text-base mb-2 text-azul-noche">{{ confirm.title }}</h3>
          <p class="text-sm text-gray-500 mb-6">{{ confirm.body }}</p>
          <div class="flex gap-3">
            <button type="button" @click="confirm.show = false"
              class="flex-1 py-2.5 rounded-full bg-gray-50 border border-gray-200 text-xs font-black
                     uppercase tracking-widest text-gray-500 hover:bg-gray-100 transition-all">
              Cancelar
            </button>
            <button type="button" @click="ejecutarConfirm"
              :class="confirm.danger
                ? 'bg-red-50 border-red-200 text-red-600 hover:bg-red-100'
                : 'bg-administraciones/10 border-administraciones/30 text-administraciones hover:bg-administraciones/20'"
              class="flex-1 py-2.5 rounded-full border text-xs font-black uppercase tracking-widest transition-all">
              Confirmar
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ══ TOAST (abajo a la derecha, como en las demás vistas) ═══════ -->
    <div class="pointer-events-none fixed bottom-14 right-4 z-[9300] max-w-[calc(100vw-2rem)]" role="status" aria-live="polite">
      <Transition name="toast">
        <div v-if="toast.show"
             class="flex items-center gap-3 px-4 py-3
                    rounded-2xl border shadow-xl text-sm font-semibold text-white"
             :class="toast.ok
               ? 'bg-administraciones border-administraciones'
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
.pointer-enter-from   { opacity: 0; transform: translateY(6px); }
.pointer-leave-to     { opacity: 0; transform: translateY(-4px); }

/* Pulso suave para el botón destacado */
@keyframes pulse-soft {
  0%, 100% { box-shadow: 0 0 0 3px rgba(25, 167, 168, 0.5), 0 0 0 6px rgba(25, 167, 168, 0.15); }
  50%       { box-shadow: 0 0 0 5px rgba(25, 167, 168, 0.35), 0 0 0 10px rgba(25, 167, 168, 0.05); }
}
.animate-pulse-soft { animation: pulse-soft 1.4s ease-in-out infinite; }

/* Chip de filtro activo (quitar con ✕), como en las demás bibliotecas */
.chip-filtro { display: inline-flex; height: 2.25rem; align-items: center; gap: 0.375rem; border-radius: 9999px; background: #f3f4f6; padding: 0 0.75rem; font-size: 0.75rem; font-weight: 600; color: var(--color-azul-noche); }
.chip-filtro:hover { background: #e5e7eb; }
</style>
