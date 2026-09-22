<script setup>
/**
 * InsertModifyEmpresa.vue
 * ─────────────────────────────────────────────────────────────
 * Gestiona los modales de NUEVA EMPRESA y EDITAR EMPRESA.
 * El Login se delega al LoginModal.vue ya existente en el proyecto.
 *
 * Flujo de auth:
 *  1. El padre llama a abrirNuevaEmpresa() o abrirEditarEmpresa()
 *  2. Si no está autenticado, emite @necesita-login con la acción ('nueva' | 'editar')
 *  3. El padre abre LoginModal y, tras login-success, llama a
 *     this.$refs.modalesRef.abrirTrasLogin(accion)
 *  4. Este componente abre el modal correspondiente
 */
import { ref, reactive, watch, onMounted, computed, nextTick } from 'vue'
import api from '../api.js'
import CentroEducativoModal from './CentroEducativoModal.vue'

const props = defineProps({
  mostrarNuevaEmpresa:   { type: Boolean, default: false },
  mostrarEditarEmpresa:  { type: Boolean, default: false },
  nombreBuscado:         { type: String,  default: '' },
  familiasProfesionales: { type: Array,   default: () => [] },
  centrosDisponibles:    { type: Array,   default: () => [] },
  empresaAEditar:        { type: Object,  default: null },
  // true cuando este modal se abre desde otro modal ya visible (ej. CentroEducativoModal)
  // que no debe desaparecer detrás — eleva el overlay por encima de esos 10000.
  elevarZIndex:          { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:mostrarNuevaEmpresa',
  'update:mostrarEditarEmpresa',
  'empresa-creada',
  'empresa-actualizada',
  'necesita-login',   // payload: 'nueva' | 'editar'
])

const tabs = [
  { id: 'basico',    label: 'Datos básicos', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1z' },
  { id: 'contacto',  label: 'Contacto',      icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { id: 'ubicacion', label: 'Ubicación',     icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z' },
]

// ════════════════════════════════════════════════════════════
//  CENTROS (cargados internamente desde la API)
// ════════════════════════════════════════════════════════════
const centrosInternos   = ref([])          // [{ id, nombre }]
const mostrarModalCentro = ref(false)

async function cargarCentros() {
  try {
    const { data } = await api.get('/centros')
    centrosInternos.value = data
  } catch (e) {
    console.error('Error cargando centros:', e)
  }
}

onMounted(cargarCentros)

function onCentroCreado(centro) {
  // Añadir a la lista interna si no existe ya
  if (!centrosInternos.value.find(c => c.id === centro.id)) {
    centrosInternos.value = [...centrosInternos.value, centro]
      .sort((a, b) => a.nombre.localeCompare(b.nombre))
  }
  mostrarModalCentro.value = false
  // Auto-seleccionar el nuevo centro en el formulario activo
  if (props.mostrarNuevaEmpresa)  nuevaForm.centro_educativo  = centro.nombre
  if (props.mostrarEditarEmpresa) editarForm.centro_educativo = centro.nombre
}

// ════════════════════════════════════════════════════════════
//  MODAL NUEVA EMPRESA
// ════════════════════════════════════════════════════════════
const tabNueva                = ref('basico')
const nuevaLoading            = ref(false)
const nuevaErrors             = reactive({})
const nuevaForm               = reactive({
  nombre_comercial: '', razon_social: '', cif: '', sector: '', tamano: '',
  web: '', centro_educativo: '', familia: '', persona_contacto: '',
  telefono: '', email_general: '', direccion: '', municipio: '',
  provincia: '', codigo_postal: '', actividad: '',
  es_simulada: false, estado_contacto: '',
})

watch(() => props.mostrarNuevaEmpresa, (v) => {
  if (v) {
    cargarCentros()
    Object.keys(nuevaErrors).forEach(k => delete nuevaErrors[k])
    Object.keys(nuevaForm).forEach(k => (nuevaForm[k] = ''))
    nuevaForm.nombre_comercial = props.nombreBuscado || ''
    nuevaForm.es_simulada = false
    nuevaConfirmando.value = false
    tabNueva.value = 'basico'
    iaAvisoCentro.value = false
    iaError.value = ''
    campoDestacado.value = null
  }
  estadoDropdownNuevaAbierto.value = false
})

// Interceptar "nuevo centro" → abrir modal en lugar de flujo inline
watch(() => nuevaForm.centro_educativo, (val) => {
  if (val === '__nuevo__') {
    nuevaForm.centro_educativo = ''
    mostrarModalCentro.value = true
    return
  }
  if (val) {
    iaAvisoCentro.value = false
    limpiarDestacado('centro')
  }
  // Resetear familia si ya no pertenece al centro seleccionado
  if (nuevaForm.familia && familiasFiltradas.value.length &&
      !familiasFiltradas.value.some(f => (f.nombre ?? f) === nuevaForm.familia)) {
    nuevaForm.familia = ''
  }
  // Guiar al siguiente paso natural: tras elegir centro, toca elegir la familia profesional.
  if (val && !nuevaForm.familia) {
    destacarCampo(familiaSelectRef, 'familia', 'Sigue por aquí')
  }
})

watch(() => nuevaForm.familia, (val) => {
  if (val) {
    delete nuevaErrors.familia
    limpiarDestacado('familia')
    // Guiar al siguiente paso: con centro y familia ya elegidos, toca pulsar "Crear con IA".
    if (nuevaForm.es_simulada && nuevaForm.centro_educativo) {
      destacarCampo(crearIaBtnRef, 'crear_ia', 'Ahora clica aquí')
    }
  }
})

watch(() => nuevaForm.nombre_comercial, (val) => {
  if (val) limpiarDestacado('nombre_comercial')
})

const familiasFiltradas = computed(() => {
  const centroNombre = nuevaForm.centro_educativo
  if (!centroNombre) return props.familiasProfesionales
  const centro = centrosInternos.value.find(c => c.nombre === centroNombre)
  if (!centro?.ciclos?.length) return props.familiasProfesionales
  // Se filtra por nombre (no por id): algunos padres (GeneradorMicroretos.vue)
  // pasan familiasProfesionales ya reducido a strings, sin id.
  const nombres = new Set(centro.ciclos.map(c => c.familia_nombre).filter(Boolean))
  return props.familiasProfesionales.filter(f => nombres.has(f.nombre ?? f))
})

const nuevaConfirmando = ref(false)
const nuevaCardRef     = ref(null)

// ── Generación de datos con IA (empresa ficticia) ──
const iaGenerando    = ref(false)
const iaAvisoCentro  = ref(false)
const iaError        = ref('')
const centroSelectRef        = ref(null)
const familiaSelectRef       = ref(null)
const nombreComercialInputRef = ref(null)
const crearIaBtnRef            = ref(null)

// Resalta visualmente (scroll + focus + flecha animada) el campo que el usuario
// debe rellenar a continuación, tanto en el camino manual como en el de IA.
const campoDestacado   = ref(null)   // 'centro' | 'familia' | 'crear_ia' | 'nombre_comercial' | null
const mensajeDestacado = ref('')
let destacadoTimeoutId = null

function destacarCampo(elRef, key, mensaje) {
  clearTimeout(destacadoTimeoutId)
  campoDestacado.value   = key
  mensajeDestacado.value = mensaje
  nextTick(() => {
    const el = elRef.value
    if (!el) return
    el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    el.focus({ preventScroll: true })
  })
  destacadoTimeoutId = setTimeout(() => {
    if (campoDestacado.value === key) campoDestacado.value = null
  }, 6000)
}

function limpiarDestacado(key) {
  if (campoDestacado.value === key) {
    clearTimeout(destacadoTimeoutId)
    campoDestacado.value = null
  }
}

function iniciarRellenoManual() {
  tabNueva.value = 'basico'
  destacarCampo(nombreComercialInputRef, 'nombre_comercial', 'Empieza por aquí')
}

// Campos "de contenido" de la empresa: los que rellena la IA o se escriben a mano.
// Deliberadamente fuera: centro_educativo, familia, es_simulada, estado_contacto
// (son selección/estado del formulario, no datos de la empresa en sí).
const CAMPOS_VACIABLES = [
  'nombre_comercial', 'razon_social', 'cif', 'sector', 'tamano', 'web', 'actividad',
  'persona_contacto', 'telefono', 'email_general', 'direccion', 'municipio',
  'provincia', 'codigo_postal',
]

const hayDatosParaVaciar = computed(() => CAMPOS_VACIABLES.some(k => !!nuevaForm[k]))

function vaciarCampos() {
  CAMPOS_VACIABLES.forEach(k => { nuevaForm[k] = '' })
  CAMPOS_VACIABLES.forEach(k => delete nuevaErrors[k])
  iaError.value = ''
  campoDestacado.value = null
}

function iniciarCreacionIA() {
  iaError.value = ''
  if (!nuevaForm.centro_educativo) {
    iaAvisoCentro.value = true
    tabNueva.value = 'basico'
    destacarCampo(centroSelectRef, 'centro', 'Selecciona aquí')
    return
  }
  iaAvisoCentro.value = false
  if (!nuevaForm.familia) {
    nuevaErrors.familia = 'Selecciona la familia profesional para que la IA genere datos coherentes'
    tabNueva.value = 'basico'
    destacarCampo(familiaSelectRef, 'familia', 'Ahora la familia')
    return
  }
  generarEmpresaConIA()
}

async function generarEmpresaConIA() {
  const centro    = centrosInternos.value.find(c => c.nombre === nuevaForm.centro_educativo)
  const familiaObj = familiasFiltradas.value.find(f => (f.nombre ?? f) === nuevaForm.familia)
  if (!centro || !familiaObj?.id) {
    iaError.value = 'No se pudo identificar el centro o la familia seleccionada.'
    return
  }
  iaGenerando.value = true
  try {
    const { data } = await api.post('/generar-empresa-ficticia', {
      centroId:  centro.id,
      familiaId: familiaObj.id,
    })
    nuevaForm.nombre_comercial = data.nombre_comercial || nuevaForm.nombre_comercial
    nuevaForm.razon_social     = data.razon_social     || nuevaForm.razon_social
    nuevaForm.cif              = data.cif              || nuevaForm.cif
    nuevaForm.sector           = data.sector           || nuevaForm.sector
    nuevaForm.tamano           = data.tamano           || nuevaForm.tamano
    nuevaForm.web              = data.web              || nuevaForm.web
    nuevaForm.actividad        = data.actividad        || nuevaForm.actividad
    nuevaForm.persona_contacto = data.persona_contacto || nuevaForm.persona_contacto
    nuevaForm.telefono         = data.telefono         || nuevaForm.telefono
    nuevaForm.email_general    = data.email_general    || nuevaForm.email_general
    nuevaForm.direccion        = data.direccion        || nuevaForm.direccion
    nuevaForm.municipio        = data.municipio        || nuevaForm.municipio
    nuevaForm.provincia        = data.provincia        || nuevaForm.provincia
    nuevaForm.codigo_postal    = data.codigo_postal    || nuevaForm.codigo_postal
    tabNueva.value = 'basico'
    destacarCampo(nombreComercialInputRef, 'nombre_comercial', 'Revisa lo generado')
  } catch (e) {
    if (e.response?.status === 401) {
      emit('update:mostrarNuevaEmpresa', false)
      emit('necesita-login', 'nueva')
      return
    }
    iaError.value = e.response?.data?.error || 'No se ha podido generar la empresa con IA. Inténtalo de nuevo.'
  } finally {
    iaGenerando.value = false
  }
}

function nuevaAvisosDatosIncompletos() {
  const avisos = []
  if (!nuevaForm.persona_contacto && !nuevaForm.telefono && !nuevaForm.email_general)
    avisos.push('Contacto: no hay persona, teléfono ni email.')
  else if (!nuevaForm.persona_contacto)
    avisos.push('Contacto: falta el nombre de la persona de contacto.')
  if (!nuevaForm.direccion && !nuevaForm.municipio)
    avisos.push('Ubicación: no hay dirección ni municipio.')
  return avisos
}

function validarNueva() {
  Object.keys(nuevaErrors).forEach(k => delete nuevaErrors[k])
  let ok = true
  if (!nuevaForm.nombre_comercial) { nuevaErrors.nombre_comercial = 'Obligatorio'; ok = false }
  if (!nuevaForm.sector)           { nuevaErrors.sector           = 'Obligatorio'; ok = false }
  if (!nuevaForm.tamano)           { nuevaErrors.tamano           = 'Obligatorio'; ok = false }
  if (!nuevaForm.familia)          { nuevaErrors.familia          = 'Obligatorio'; ok = false }
  if (!nuevaForm.centro_educativo) { nuevaErrors.centro_educativo = 'Obligatorio'; ok = false }
  if (!ok) tabNueva.value = 'basico'
  return ok
}

function pedirConfirmacionNueva() {
  if (!validarNueva()) return
  nuevaConfirmando.value = true
  if (nuevaAvisosDatosIncompletos().length) {
    nuevaCardRef.value?.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

async function guardarNueva() {
  nuevaConfirmando.value = false
  nuevaLoading.value = true
  try {
    const { data } = await api.post('/empresas', {
      nombreComercial: nuevaForm.nombre_comercial,
      razonSocial:     nuevaForm.razon_social     || nuevaForm.nombre_comercial,
      cif:             nuevaForm.cif              || null,
      sector:          nuevaForm.sector,
      tamano:          nuevaForm.tamano,
      web:             nuevaForm.web              || null,
      centroEducativo: nuevaForm.centro_educativo || null,
      ciclosIds: [],
      familia:         nuevaForm.familia,
      personaContacto: nuevaForm.persona_contacto || null,
      telefono:        nuevaForm.telefono         || null,
      emailGeneral:    nuevaForm.email_general    || null,
      direccion:       nuevaForm.direccion        || null,
      municipio:       nuevaForm.municipio        || null,
      provincia:       nuevaForm.provincia        || null,
      codigoPostal:    nuevaForm.codigo_postal    || null,
      actividad:       nuevaForm.actividad        || null,
      esSimulada:      nuevaForm.es_simulada,
      estadoContacto:  nuevaForm.estado_contacto || null,
    })
    emit('empresa-creada', data.empresa)
    emit('update:mostrarNuevaEmpresa', false)
  } catch (e) {
    if (e.response?.status === 401) {
      emit('update:mostrarNuevaEmpresa', false)
      emit('necesita-login', 'nueva')
      return
    }
    nuevaErrors._global =
      e.response?.data?.message || 'Error al guardar. Comprueba los datos e inténtalo de nuevo.'
  } finally {
    nuevaLoading.value = false
  }
}

// ════════════════════════════════════════════════════════════
//  MODAL EDITAR EMPRESA
// ════════════════════════════════════════════════════════════
const tabEditar     = ref('basico')
const editarLoading = ref(false)
const editarErrors  = reactive({})
const ESTADOS_CONTACTO = [
  { value: 'Pendiente de llamar',            label: 'Pendiente de llamar',       bg: 'bg-amber-100',    text: 'text-amber-700',  border: 'border-amber-300',    dot: 'bg-amber-400',   pulse: true  },
  { value: 'Llamado - Información obtenida', label: 'Llamado — Info obtenida ✓', bg: 'bg-centros/10', text: 'text-centros',  border: 'border-centros/30', dot: 'bg-centros',   pulse: false },
  { value: 'Llamado - Negativa',             label: 'Llamado — Negativa ✗',      bg: 'bg-red-50',       text: 'text-red-600',    border: 'border-red-200',      dot: 'bg-red-400',     pulse: false },
  { value: 'Llamado - Llamar más tarde',     label: 'Llamado — Llamar más tarde', bg: 'bg-blue-50',     text: 'text-blue-600',   border: 'border-blue-200',     dot: 'bg-blue-400',    pulse: false },
  { value: 'En colaboración activa',         label: 'En colaboración activa ★',  bg: 'bg-gray-100',     text: 'text-gray-700',   border: 'border-gray-300',     dot: 'bg-gray-600',    pulse: false },
  { value: 'Descartada',                     label: 'Descartada',                bg: 'bg-gray-50',      text: 'text-gray-400',   border: 'border-gray-200',     dot: 'bg-gray-300',    pulse: false },
]

function estadoActual(valor) {
  return ESTADOS_CONTACTO.find(e => e.value === valor) ?? null
}

const estadoDropdownNuevaAbierto  = ref(false)
const estadoDropdownEditarAbierto = ref(false)

const editarForm               = reactive({
  nombre_comercial: '', razon_social: '', cif: '', sector: '', tamano: '',
  web: '', centro_educativo: '', persona_contacto: '', telefono: '',
  email_general: '', direccion: '', municipio: '', provincia: '',
  codigo_postal: '', actividad: '', estado_contacto: '', es_simulada: false,
})

watch(() => props.mostrarEditarEmpresa, (v) => {
  estadoDropdownEditarAbierto.value = false
  if (v && props.empresaAEditar) {
    cargarCentros()
    Object.keys(editarErrors).forEach(k => delete editarErrors[k])
    const e = props.empresaAEditar
    editarForm.nombre_comercial = e.nombre_comercial || ''
    editarForm.razon_social     = e.razon_social     || ''
    editarForm.cif              = e.cif              || ''
    editarForm.sector           = e.sector           || ''
    editarForm.tamano           = e.tamano           || ''
    editarForm.web              = e.web              || ''
    editarForm.centro_educativo = e.centro_educativo || ''
    editarForm.persona_contacto = e.persona_contacto || ''
    editarForm.telefono         = e.telefono         || ''
    editarForm.email_general    = e.email_general    || ''
    editarForm.direccion        = e.direccion        || ''
    editarForm.municipio        = e.municipio        || ''
    editarForm.provincia        = e.provincia        || ''
    editarForm.codigo_postal    = e.codigo_postal    || ''
    editarForm.actividad        = e.actividad        || ''
    editarForm.estado_contacto  = e.estado_contacto  || ''
    editarForm.es_simulada      = !!e.es_simulada
    tabEditar.value = 'basico'
  }
})

// Interceptar "nuevo centro" → abrir modal
watch(() => editarForm.centro_educativo, (val) => {
  if (val === '__nuevo__') {
    editarForm.centro_educativo = ''
    mostrarModalCentro.value = true
  }
})

async function guardarEdicion() {
  Object.keys(editarErrors).forEach(k => delete editarErrors[k])
  if (!editarForm.nombre_comercial) {
    editarErrors.nombre_comercial = 'El nombre comercial es obligatorio'
    tabEditar.value = 'basico'
    return
  }
  editarLoading.value = true
  try {
    const { data } = await api.put(`/empresas/${props.empresaAEditar.id}`, {
      nombreComercial: editarForm.nombre_comercial,
      razonSocial:     editarForm.razon_social     || editarForm.nombre_comercial,
      cif:             editarForm.cif              || null,
      sector:          editarForm.sector           || null,
      tamano:          editarForm.tamano           || null,
      web:             editarForm.web              || null,
      centroEducativo: editarForm.centro_educativo || null,
      ciclosIds:       [],
      personaContacto: editarForm.persona_contacto || null,
      telefono:        editarForm.telefono         || null,
      emailGeneral:    editarForm.email_general    || null,
      direccion:       editarForm.direccion        || null,
      municipio:       editarForm.municipio        || null,
      provincia:       editarForm.provincia        || null,
      codigoPostal:    editarForm.codigo_postal    || null,
      actividad:       editarForm.actividad        || null,
      esSimulada:      editarForm.es_simulada,
      estadoContacto:  editarForm.estado_contacto  || null,
    })
    // El backend devuelve la empresa completa con el JOIN de familia
    emit('empresa-actualizada', data.empresa)
    emit('update:mostrarEditarEmpresa', false)
  } catch (e) {
    if (e.response?.status === 401) {
      emit('update:mostrarEditarEmpresa', false)
      emit('necesita-login', 'editar')
      return
    }
    editarErrors._global =
      e.response?.data?.message || 'Error al actualizar. Inténtalo de nuevo.'
  } finally {
    editarLoading.value = false
  }
}

/**
 * Llamado por el padre (GeneradorMicroretos) tras un login exitoso.
 * Abre el modal correspondiente a la acción que estaba pendiente.
 */
function abrirTrasLogin(accion) {
  if (accion === 'nueva')  emit('update:mostrarNuevaEmpresa', true)
  if (accion === 'editar') emit('update:mostrarEditarEmpresa', true)
}

defineExpose({ abrirTrasLogin })
</script>

<template>

  <!-- ═══════════════════════════════════════════════════════
       MODAL — NUEVA EMPRESA
  ════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="ime-overlay">
      <div
        v-if="mostrarNuevaEmpresa"
        class="ime-overlay"
        :class="{ 'ime-overlay--elevado': elevarZIndex }"
        @click.self="$emit('update:mostrarNuevaEmpresa', false)"
      >
        <Transition name="ime-card">
          <div v-if="mostrarNuevaEmpresa" ref="nuevaCardRef" class="ime-card">

            <div class="ime-header">
              <div class="ime-icon-box" style="background:rgba(48,114,170,0.1)">
                <svg class="w-7 h-7 text-centros" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1z"/>
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <h2 class="ime-title">Registrar nueva empresa</h2>
                <p class="ime-sub">
                  "<span class="text-centros font-black">{{ nombreBuscado }}</span>"
                  no está en DuaLab — completa los datos para añadirla
                </p>
              </div>
            </div>

            <!-- Selector: ¿Empresa real o ficticia? -->
            <div class="mt-5 p-4 rounded-2xl border-2 transition-colors"
              :class="nuevaForm.es_simulada ? 'border-[#1F2937]/20 bg-[#1F2937]/5' : 'border-centros/20 bg-centros/5'">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-3">¿Qué tipo de empresa es?</p>
              <div class="flex gap-3">
                <button
                  type="button"
                  @click="nuevaForm.es_simulada = false"
                  :class="!nuevaForm.es_simulada ? 'bg-centros text-white border-centros shadow-md' : 'bg-white text-gray-500 border-gray-200 hover:border-centros/50'"
                  class="flex-1 py-3 rounded-xl border-2 font-black text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1z"/>
                  </svg>
                  Empresa Real
                </button>
                <button
                  type="button"
                  @click="nuevaForm.es_simulada = true"
                  :class="nuevaForm.es_simulada ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-md' : 'bg-white text-gray-500 border-gray-200 hover:border-gray-400'"
                  class="flex-1 py-3 rounded-xl border-2 font-black text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                  <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                  Empresa Ficticia
                </button>
              </div>
              <p class="text-[10px] text-gray-400 mt-2 ml-1 leading-relaxed">
                <template v-if="!nuevaForm.es_simulada">Contacto real: la empresa ha sido o será contactada para recabar información.</template>
                <template v-else>Empresa con datos inventados o generados por IA. No ha habido contacto real.</template>
              </p>

              <!-- Explicación + opciones al elegir "Ficticia" -->
              <div v-if="nuevaForm.es_simulada" class="mt-3 p-3 rounded-xl bg-white border border-gray-200">
                <p class="text-[11px] font-bold text-gray-600 mb-1">¿Cómo quieres rellenar los datos de esta empresa ficticia?</p>
                <p class="text-[10px] text-gray-400 mb-2 leading-relaxed flex items-start gap-1.5">
                  <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span>
                    <strong class="text-gray-500">Crear con IA:</strong> elige primero el centro educativo y la familia
                    profesional (abajo, en "Datos básicos") — la IA usa esos dos datos para inventar una empresa coherente
                    y rellena el resto del formulario por ti. Puedes editar cualquier campo después.
                  </span>
                </p>
                <div class="flex flex-col sm:flex-row gap-2">
                  <button type="button" @click="iniciarRellenoManual"
                    class="flex-1 py-2 rounded-lg border-2 border-gray-200 text-gray-600 hover:border-gray-400 font-black text-[11px] uppercase tracking-widest transition-all flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Introducir manualmente
                  </button>
                  <div class="relative flex-1">
                    <Transition name="ime-fade">
                      <div v-if="campoDestacado === 'crear_ia'"
                        class="absolute -top-8 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center pointer-events-none">
                        <span class="bg-amber-500 text-white text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg shadow-lg whitespace-nowrap">{{ mensajeDestacado }}</span>
                        <svg class="w-4 h-4 text-amber-500 -mt-0.5 animate-bounce" fill="currentColor" viewBox="0 0 24 24"><path d="M12 16l-6-6h12l-6 6z"/></svg>
                      </div>
                    </Transition>
                    <button ref="crearIaBtnRef" type="button" @click="iniciarCreacionIA" :disabled="iaGenerando"
                      :class="campoDestacado === 'crear_ia' ? 'ring-4 ring-amber-400/60 ring-offset-2' : ''"
                      class="w-full py-2 rounded-lg border-2 border-[#1F2937] bg-[#1F2937] text-white hover:bg-[#374151] font-black text-[11px] uppercase tracking-widest transition-all flex items-center justify-center gap-1.5 disabled:opacity-60">
                      <svg v-if="iaGenerando" class="animate-spin w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                      </svg>
                      <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                      </svg>
                      {{ iaGenerando ? 'Generando...' : 'Crear con IA' }}
                    </button>
                  </div>
                </div>
                <button v-if="hayDatosParaVaciar" type="button" @click="vaciarCampos"
                  class="mt-3 w-full py-2.5 rounded-lg border-2 border-red-500 bg-red-500 text-white
                         font-black text-[11px] uppercase tracking-widest shadow-md shadow-red-500/25
                         hover:bg-red-600 hover:border-red-600 active:scale-[.98] transition-all
                         flex items-center justify-center gap-1.5">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/>
                  </svg>
                  Vaciar campos rellenados
                </button>
                <Transition name="ime-fade">
                  <p v-if="iaAvisoCentro" class="mt-2 text-[11px] font-bold text-amber-600 leading-relaxed">
                    Primero has de seleccionar el centro educativo. Dicho centro educativo tiene asociado ciertas familias profesionales concretas.
                  </p>
                </Transition>
                <Transition name="ime-fade">
                  <p v-if="iaError" class="mt-2 text-[11px] font-bold text-red-600 leading-relaxed">{{ iaError }}</p>
                </Transition>
              </div>
            </div>

            <div class="ime-tabs mt-7">
              <button v-for="tab in tabs" :key="tab.id"
                @click="tabNueva = tab.id" class="ime-tab"
                :class="tabNueva === tab.id ? 'ime-tab-on' : 'ime-tab-off'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"/>
                </svg>
                <span>{{ tab.label }}</span>
                <!-- Punto rojo: errores de validación en básico -->
                <span v-if="tab.id === 'basico' && (nuevaErrors.nombre_comercial || nuevaErrors.sector || nuevaErrors.tamano || nuevaErrors.familia || nuevaErrors.centro_educativo)"
                  class="w-2 h-2 rounded-full bg-red-500 shrink-0"/>
                <!-- Punto ámbar: datos incompletos opcionales pero avisados -->
                <span v-if="nuevaConfirmando && nuevaAvisosDatosIncompletos().length && (tab.id === 'contacto' || tab.id === 'ubicacion')"
                  class="w-2 h-2 rounded-full bg-amber-400 shrink-0 animate-pulse"/>
              </button>
            </div>

            <!-- Toast sticky de aviso cuando hay datos incompletos -->
            <Transition name="ime-fade">
              <div v-if="nuevaConfirmando && nuevaAvisosDatosIncompletos().length"
                class="sticky top-0 z-10 mt-4 -mx-1 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 flex items-start gap-3 shadow-md">
                <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="flex-1 min-w-0">
                  <p class="font-black text-amber-700 text-xs uppercase tracking-widest mb-1">Revisa los tabs marcados en naranja</p>
                  <ul class="space-y-0.5">
                    <li v-for="aviso in nuevaAvisosDatosIncompletos()" :key="aviso"
                      class="text-xs text-amber-700 flex items-center gap-1.5">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"/>
                      {{ aviso }}
                    </li>
                  </ul>
                </div>
                <button @click="nuevaConfirmando = false" class="text-amber-400 hover:text-amber-600 shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </Transition>

            <!-- Tab Básico -->
            <div v-show="tabNueva === 'basico'" class="space-y-5 mt-6">
              <!-- Estado de contacto -->
              <div>
                <label class="ime-label">Estado de Contacto</label>
                <div class="relative inline-block mt-1" @click.stop>
                  <button
                    type="button"
                    @click="estadoDropdownNuevaAbierto = !estadoDropdownNuevaAbierto"
                    :class="estadoActual(nuevaForm.estado_contacto)
                      ? [estadoActual(nuevaForm.estado_contacto).bg, estadoActual(nuevaForm.estado_contacto).text, estadoActual(nuevaForm.estado_contacto).border]
                      : 'bg-gray-100 text-gray-400 border-gray-200'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border cursor-pointer hover:opacity-80 transition-opacity"
                  >
                    <span v-if="estadoActual(nuevaForm.estado_contacto)"
                      :class="[estadoActual(nuevaForm.estado_contacto).dot, estadoActual(nuevaForm.estado_contacto).pulse ? 'animate-pulse' : '']"
                      class="w-1.5 h-1.5 rounded-full shrink-0"></span>
                    {{ nuevaForm.estado_contacto || 'Sin estado' }}
                    <svg class="w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </button>
                  <Transition name="ime-fade">
                    <div v-if="estadoDropdownNuevaAbierto"
                      class="absolute left-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden min-w-[250px]">
                      <button
                        v-for="est in ESTADOS_CONTACTO" :key="est.value"
                        type="button"
                        @click="nuevaForm.estado_contacto = est.value; estadoDropdownNuevaAbierto = false"
                        :class="[est.bg, est.text, nuevaForm.estado_contacto === est.value ? 'font-black ring-2 ring-inset ring-current/30' : 'hover:opacity-80']"
                        class="w-full text-left px-4 py-2.5 flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-widest border-b border-white/40 last:border-0 transition-opacity"
                      >
                        <span :class="[est.dot, est.pulse ? 'animate-pulse' : '']" class="w-2 h-2 rounded-full shrink-0"></span>
                        {{ est.label }}
                        <svg v-if="nuevaForm.estado_contacto === est.value" class="w-3.5 h-3.5 ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                      </button>
                      <button
                        type="button"
                        @click="nuevaForm.estado_contacto = ''; estadoDropdownNuevaAbierto = false"
                        class="w-full text-left px-4 py-2.5 flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-widest text-gray-400 hover:bg-gray-50 border-t border-gray-100 transition-colors"
                      >
                        <span class="w-2 h-2 rounded-full shrink-0 bg-gray-300"></span>
                        Sin estado
                      </button>
                    </div>
                  </Transition>
                </div>
              </div>
              <div class="ime-g2">
                <div class="relative">
                  <Transition name="ime-fade">
                    <div v-if="campoDestacado === 'nombre_comercial'"
                      class="absolute -top-8 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center pointer-events-none">
                      <span class="bg-amber-500 text-white text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg shadow-lg whitespace-nowrap">{{ mensajeDestacado }}</span>
                      <svg class="w-4 h-4 text-amber-500 -mt-0.5 animate-bounce" fill="currentColor" viewBox="0 0 24 24"><path d="M12 16l-6-6h12l-6 6z"/></svg>
                    </div>
                  </Transition>
                  <label class="ime-label">Nombre Comercial *</label>
                  <input ref="nombreComercialInputRef" v-model="nuevaForm.nombre_comercial" class="ime-input"
                    :class="[{'ime-input-err': nuevaErrors.nombre_comercial}, campoDestacado === 'nombre_comercial' ? 'ring-4 ring-amber-400/60 ring-offset-2' : '']"
                    placeholder="Ej: Acme Solutions SL"/>
                  <p v-if="nuevaErrors.nombre_comercial" class="ime-err">{{ nuevaErrors.nombre_comercial }}</p>
                </div>
                <div>
                  <label class="ime-label">Razón Social</label>
                  <input v-model="nuevaForm.razon_social" class="ime-input" placeholder="Ej: Acme Solutions S.L.U."/>
                </div>
              </div>
              <div class="ime-g2">
                <div>
                  <label class="ime-label">CIF</label>
                  <input v-model="nuevaForm.cif" class="ime-input" placeholder="Ej: B12345678"/>
                </div>
                <div>
                  <label class="ime-label">Sector de Actividad *</label>
                  <input v-model="nuevaForm.sector" class="ime-input"
                    :class="{'ime-input-err': nuevaErrors.sector}"
                    placeholder="Ej: Informática y Comunicaciones"/>
                  <p v-if="nuevaErrors.sector" class="ime-err">{{ nuevaErrors.sector }}</p>
                </div>
              </div>
              <div class="ime-g2">
                <div>
                  <label class="ime-label">Tamaño de la Empresa *</label>
                  <select v-model="nuevaForm.tamano" class="ime-input" :class="{'ime-input-err': nuevaErrors.tamano}">
                    <option value="">Selecciona...</option>
                    <option value="Micropyme (1-10)">Micropyme (1 a 10 empleados)</option>
                    <option value="Pequeña (10-50)">Pequeña (10 a 50 empleados)</option>
                    <option value="Mediana (50-250)">Mediana (50 a 250 empleados)</option>
                    <option value="Grande (+250)">Grande (Más de 250 empleados)</option>
                  </select>
                  <p v-if="nuevaErrors.tamano" class="ime-err">{{ nuevaErrors.tamano }}</p>
                </div>
                <div>
                  <label class="ime-label">Web</label>
                  <input v-model="nuevaForm.web" class="ime-input" placeholder="https://..."/>
                </div>
              </div>
              <div class="ime-g2">
                <div class="relative">
                  <Transition name="ime-fade">
                    <div v-if="campoDestacado === 'centro'"
                      class="absolute -top-8 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center pointer-events-none">
                      <span class="bg-amber-500 text-white text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg shadow-lg whitespace-nowrap">{{ mensajeDestacado }}</span>
                      <svg class="w-4 h-4 text-amber-500 -mt-0.5 animate-bounce" fill="currentColor" viewBox="0 0 24 24"><path d="M12 16l-6-6h12l-6 6z"/></svg>
                    </div>
                  </Transition>
                  <label class="ime-label">Centro Educativo *</label>
                  <select ref="centroSelectRef" v-model="nuevaForm.centro_educativo" class="ime-input"
                    :class="[{'ime-input-err': nuevaErrors.centro_educativo}, campoDestacado === 'centro' ? 'ring-4 ring-amber-400/60 ring-offset-2' : '']">
                    <option value="">Selecciona un centro...</option>
                    <option v-for="c in centrosInternos" :key="c.id" :value="c.nombre">{{ c.nombre }}</option>
                    <option value="__nuevo__">+ Crear nuevo centro educativo...</option>
                  </select>
                  <p v-if="nuevaErrors.centro_educativo" class="ime-err">{{ nuevaErrors.centro_educativo }}</p>
                </div>
                <div class="relative">
                  <Transition name="ime-fade">
                    <div v-if="campoDestacado === 'familia'"
                      class="absolute -top-8 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center pointer-events-none">
                      <span class="bg-amber-500 text-white text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg shadow-lg whitespace-nowrap">{{ mensajeDestacado }}</span>
                      <svg class="w-4 h-4 text-amber-500 -mt-0.5 animate-bounce" fill="currentColor" viewBox="0 0 24 24"><path d="M12 16l-6-6h12l-6 6z"/></svg>
                    </div>
                  </Transition>
                  <label class="ime-label">Familia Profesional *</label>
                  <select ref="familiaSelectRef" v-model="nuevaForm.familia" class="ime-input"
                          :class="[{'ime-input-err': nuevaErrors.familia}, campoDestacado === 'familia' ? 'ring-4 ring-amber-400/60 ring-offset-2' : '']"
                          :disabled="!nuevaForm.centro_educativo">
                    <option value="">{{ nuevaForm.centro_educativo ? 'Selecciona familia...' : 'Primero elige un centro' }}</option>
                    <option v-for="f in familiasFiltradas" :key="f.id ?? f" :value="f.nombre ?? f">{{ f.nombre ?? f }}</option>
                  </select>
                  <p v-if="nuevaErrors.familia" class="ime-err">{{ nuevaErrors.familia }}</p>
                </div>
              </div>
              <div>
                <label class="ime-label">Actividad (descripción)</label>
                <textarea v-model="nuevaForm.actividad" class="ime-input h-20 resize-none"
                  placeholder="Breve descripción de la actividad de la empresa..."/>
              </div>
            </div>

            <!-- Tab Contacto -->
            <div v-show="tabNueva === 'contacto'" class="space-y-5 mt-6">
              <div>
                <label class="ime-label">Persona de Contacto</label>
                <input v-model="nuevaForm.persona_contacto" class="ime-input" placeholder="Nombre y apellidos"/>
              </div>
              <div class="ime-g2">
                <div>
                  <label class="ime-label">Teléfono</label>
                  <input v-model="nuevaForm.telefono" type="tel" class="ime-input" placeholder="Ej: 928 000 000"/>
                </div>
                <div>
                  <label class="ime-label">Email General</label>
                  <input v-model="nuevaForm.email_general" type="email" class="ime-input" placeholder="info@empresa.com"/>
                </div>
              </div>
            </div>

            <!-- Tab Ubicación -->
            <div v-show="tabNueva === 'ubicacion'" class="space-y-5 mt-6">
              <div>
                <label class="ime-label">Dirección</label>
                <input v-model="nuevaForm.direccion" class="ime-input" placeholder="Calle y número"/>
              </div>
              <div class="ime-g3">
                <div>
                  <label class="ime-label">Código Postal</label>
                  <input v-model="nuevaForm.codigo_postal" class="ime-input" placeholder="35001"/>
                </div>
                <div>
                  <label class="ime-label">Municipio</label>
                  <input v-model="nuevaForm.municipio" class="ime-input" placeholder="Las Palmas de G.C."/>
                </div>
                <div>
                  <label class="ime-label">Provincia</label>
                  <input v-model="nuevaForm.provincia" class="ime-input" placeholder="Las Palmas"/>
                </div>
              </div>
            </div>

            <Transition name="ime-fade">
              <div v-if="nuevaErrors._global" class="ime-alert-error mt-4">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ nuevaErrors._global }}</span>
              </div>
            </Transition>

            <!-- Panel de confirmación antes de guardar -->
            <Transition name="ime-fade">
              <div v-if="nuevaConfirmando" class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 space-y-3">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                  </svg>
                  <div class="flex-1">
                    <p class="font-black text-amber-700 text-sm uppercase tracking-widest mb-1">Revisa antes de guardar</p>
                    <p class="text-amber-700 text-xs font-medium mb-2">
                      Comprueba que todos los datos son correctos. Una vez registrada, la empresa quedará visible para todos los centros vinculados.
                    </p>
                    <ul v-if="nuevaAvisosDatosIncompletos().length" class="space-y-1 mb-2">
                      <li v-for="aviso in nuevaAvisosDatosIncompletos()" :key="aviso"
                        class="flex items-center gap-2 text-xs text-amber-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"/>
                        {{ aviso }}
                        <span class="text-amber-500 font-bold">(puedes completarlo más tarde)</span>
                      </li>
                    </ul>
                    <p class="text-xs text-amber-600 font-semibold">¿Confirmas que los datos son correctos?</p>
                  </div>
                </div>
                <div class="flex gap-2">
                  <button @click="nuevaConfirmando = false" class="ime-btn-ghost flex-1 !text-amber-700 !border-amber-300 hover:!bg-amber-100">
                    Revisar datos
                  </button>
                  <button @click="guardarNueva" :disabled="nuevaLoading" class="ime-btn-green flex-[2]">
                    <svg v-if="nuevaLoading" class="animate-spin w-4 h-4" viewBox="0 0 24 24">
                      <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                    </svg>
                    Sí, registrar empresa
                  </button>
                </div>
              </div>
            </Transition>

            <div class="ime-actions">
              <button @click="$emit('update:mostrarNuevaEmpresa', false)" class="ime-btn-ghost flex-1">Cancelar</button>
              <button @click="pedirConfirmacionNueva" :disabled="nuevaLoading || nuevaConfirmando" class="ime-btn-green flex-[2]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Registrar en DuaLab
              </button>
            </div>

            <button class="ime-x" @click="$emit('update:mostrarNuevaEmpresa', false)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>


  <!-- ═══════════════════════════════════════════════════════
       MODAL — EDITAR EMPRESA
  ════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="ime-overlay">
      <div
        v-if="mostrarEditarEmpresa"
        class="ime-overlay"
        :class="{ 'ime-overlay--elevado': elevarZIndex }"
        @click.self="$emit('update:mostrarEditarEmpresa', false)"
      >
        <Transition name="ime-card">
          <div v-if="mostrarEditarEmpresa" class="ime-card">

            <div class="ime-header">
              <div class="ime-icon-box" style="background:rgba(31,41,55,0.07)">
                <svg class="w-7 h-7 text-[#1F2937]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <h2 class="ime-title">Editar empresa</h2>
                <p class="ime-sub">
                  Modificando:
                  <span class="font-black text-[#1F2937]">{{ empresaAEditar?.nombre_comercial }}</span>
                </p>
              </div>
            </div>

            <!-- Banner pendiente de llamar -->
            <Transition name="ime-fade">
              <div v-if="editarForm.estado_contacto === 'Pendiente de llamar'"
                class="mt-5 flex items-start gap-3 bg-amber-50 border border-amber-300 rounded-2xl px-4 py-3">
                <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-xs font-bold text-amber-700">
                  Esta empresa está <span class="uppercase tracking-wider">pendiente de llamar</span>. Los datos de diagnóstico pueden estar incompletos hasta establecer contacto.
                </p>
              </div>
            </Transition>

            <!-- Tipo de empresa (es_simulada) -->
            <div class="mt-5 p-4 rounded-2xl border-2 transition-colors"
              :class="editarForm.es_simulada ? 'border-[#1F2937]/20 bg-[#1F2937]/5' : 'border-centros/20 bg-centros/5'">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-3">Tipo de empresa</p>
              <div class="flex gap-3">
                <button
                  type="button"
                  @click="editarForm.es_simulada = false"
                  :class="!editarForm.es_simulada ? 'bg-centros text-white border-centros shadow-md' : 'bg-white text-gray-500 border-gray-200 hover:border-centros/50'"
                  class="flex-1 py-2.5 rounded-xl border-2 font-black text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7z"/>
                  </svg>
                  Empresa Real
                </button>
                <button
                  type="button"
                  @click="editarForm.es_simulada = true"
                  :class="editarForm.es_simulada ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-md' : 'bg-white text-gray-500 border-gray-200 hover:border-gray-400'"
                  class="flex-1 py-2.5 rounded-xl border-2 font-black text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                  Empresa Ficticia
                </button>
              </div>
            </div>

            <div class="ime-tabs mt-7">
              <button v-for="tab in tabs" :key="tab.id"
                @click="tabEditar = tab.id" class="ime-tab"
                :class="tabEditar === tab.id ? 'ime-tab-on' : 'ime-tab-off'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"/>
                </svg>
                <span>{{ tab.label }}</span>
                <span v-if="tab.id === 'basico' && editarErrors.nombre_comercial"
                  class="w-2 h-2 rounded-full bg-red-500 shrink-0"/>
              </button>
            </div>

            <!-- Tab Básico -->
            <div v-show="tabEditar === 'basico'" class="space-y-5 mt-6">
              <!-- Estado de contacto -->
              <div>
                <label class="ime-label">Estado de Contacto</label>
                <div class="relative inline-block mt-1" @click.stop>
                  <button
                    type="button"
                    @click="estadoDropdownEditarAbierto = !estadoDropdownEditarAbierto"
                    :class="estadoActual(editarForm.estado_contacto)
                      ? [estadoActual(editarForm.estado_contacto).bg, estadoActual(editarForm.estado_contacto).text, estadoActual(editarForm.estado_contacto).border]
                      : 'bg-gray-100 text-gray-400 border-gray-200'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border cursor-pointer hover:opacity-80 transition-opacity"
                  >
                    <span v-if="estadoActual(editarForm.estado_contacto)"
                      :class="[estadoActual(editarForm.estado_contacto).dot, estadoActual(editarForm.estado_contacto).pulse ? 'animate-pulse' : '']"
                      class="w-1.5 h-1.5 rounded-full shrink-0"></span>
                    {{ editarForm.estado_contacto || 'Sin estado' }}
                    <svg class="w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </button>
                  <Transition name="ime-fade">
                    <div v-if="estadoDropdownEditarAbierto"
                      class="absolute left-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden min-w-[250px]">
                      <button
                        v-for="est in ESTADOS_CONTACTO" :key="est.value"
                        type="button"
                        @click="editarForm.estado_contacto = est.value; estadoDropdownEditarAbierto = false"
                        :class="[est.bg, est.text, editarForm.estado_contacto === est.value ? 'font-black ring-2 ring-inset ring-current/30' : 'hover:opacity-80']"
                        class="w-full text-left px-4 py-2.5 flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-widest border-b border-white/40 last:border-0 transition-opacity"
                      >
                        <span :class="[est.dot, est.pulse ? 'animate-pulse' : '']" class="w-2 h-2 rounded-full shrink-0"></span>
                        {{ est.label }}
                        <svg v-if="editarForm.estado_contacto === est.value" class="w-3.5 h-3.5 ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                      </button>
                      <button
                        type="button"
                        @click="editarForm.estado_contacto = ''; estadoDropdownEditarAbierto = false"
                        class="w-full text-left px-4 py-2.5 flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-widest text-gray-400 hover:bg-gray-50 border-t border-gray-100 transition-colors"
                      >
                        <span class="w-2 h-2 rounded-full shrink-0 bg-gray-300"></span>
                        Sin estado
                      </button>
                    </div>
                  </Transition>
                </div>
              </div>
              <div class="ime-g2">
                <div>
                  <label class="ime-label">Nombre Comercial *</label>
                  <input v-model="editarForm.nombre_comercial" class="ime-input"
                    :class="{'ime-input-err': editarErrors.nombre_comercial}"
                    placeholder="Nombre comercial"/>
                  <p v-if="editarErrors.nombre_comercial" class="ime-err">{{ editarErrors.nombre_comercial }}</p>
                </div>
                <div>
                  <label class="ime-label">Razón Social</label>
                  <input v-model="editarForm.razon_social" class="ime-input" placeholder="Razón social"/>
                </div>
              </div>
              <div class="ime-g2">
                <div>
                  <label class="ime-label">CIF{{ editarForm.cif ? ' — asignado, no editable' : '' }}</label>
                  <input v-model="editarForm.cif" :disabled="!!props.empresaAEditar?.cif" class="ime-input"
                    :class="props.empresaAEditar?.cif ? 'opacity-60 cursor-not-allowed bg-gray-100' : ''"
                    placeholder="Ej: B12345678"/>
                </div>
                <div>
                  <label class="ime-label">Sector de Actividad</label>
                  <input v-model="editarForm.sector" class="ime-input" placeholder="Sector"/>
                </div>
              </div>
              <div class="ime-g2">
                <div>
                  <label class="ime-label">Tamaño de la Empresa</label>
                  <select v-model="editarForm.tamano" class="ime-input">
                    <option value="">Selecciona...</option>
                    <option value="Micropyme (1-10)">Micropyme (1 a 10 empleados)</option>
                    <option value="Pequeña (10-50)">Pequeña (10 a 50 empleados)</option>
                    <option value="Mediana (50-250)">Mediana (50 a 250 empleados)</option>
                    <option value="Grande (+250)">Grande (Más de 250 empleados)</option>
                  </select>
                </div>
                <div>
                  <label class="ime-label">Web</label>
                  <input v-model="editarForm.web" class="ime-input" placeholder="https://..."/>
                </div>
              </div>
              <div class="ime-g2">
                <div>
                  <label class="ime-label">Centro Educativo</label>
                  <select v-model="editarForm.centro_educativo" class="ime-input">
                    <option value="">Sin asignar...</option>
                    <option v-for="c in centrosInternos" :key="c.id" :value="c.nombre">{{ c.nombre }}</option>
                    <option value="__nuevo__">+ Crear nuevo centro educativo...</option>
                  </select>
                  <p v-if="editarErrors.centro_educativo" class="ime-err">{{ editarErrors.centro_educativo }}</p>
                </div>
                <div>
                  <label class="ime-label">Actividad</label>
                  <input v-model="editarForm.actividad" class="ime-input" placeholder="Descripción de actividad"/>
                </div>
              </div>
            </div>

            <!-- Tab Contacto -->
            <div v-show="tabEditar === 'contacto'" class="space-y-5 mt-6">
              <div>
                <label class="ime-label">Persona de Contacto</label>
                <input v-model="editarForm.persona_contacto" class="ime-input" placeholder="Nombre y apellidos"/>
              </div>
              <div class="ime-g2">
                <div>
                  <label class="ime-label">Teléfono</label>
                  <input v-model="editarForm.telefono" type="tel" class="ime-input" placeholder="Ej: 928 000 000"/>
                </div>
                <div>
                  <label class="ime-label">Email General</label>
                  <input v-model="editarForm.email_general" type="email" class="ime-input" placeholder="info@empresa.com"/>
                </div>
              </div>
            </div>

            <!-- Tab Ubicación -->
            <div v-show="tabEditar === 'ubicacion'" class="space-y-5 mt-6">
              <div>
                <label class="ime-label">Dirección</label>
                <input v-model="editarForm.direccion" class="ime-input" placeholder="Calle y número"/>
              </div>
              <div class="ime-g3">
                <div>
                  <label class="ime-label">Código Postal</label>
                  <input v-model="editarForm.codigo_postal" class="ime-input" placeholder="35001"/>
                </div>
                <div>
                  <label class="ime-label">Municipio</label>
                  <input v-model="editarForm.municipio" class="ime-input"/>
                </div>
                <div>
                  <label class="ime-label">Provincia</label>
                  <input v-model="editarForm.provincia" class="ime-input"/>
                </div>
              </div>
            </div>

            <Transition name="ime-fade">
              <div v-if="editarErrors._global" class="ime-alert-error mt-4">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ editarErrors._global }}</span>
              </div>
            </Transition>

            <div class="ime-actions">
              <button @click="$emit('update:mostrarEditarEmpresa', false)" class="ime-btn-ghost flex-1">Cancelar</button>
              <button @click="guardarEdicion" :disabled="editarLoading" class="ime-btn-dark flex-[2]">
                <svg v-if="editarLoading" class="animate-spin w-4 h-4" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                {{ editarLoading ? 'Guardando cambios...' : 'Guardar Cambios' }}
              </button>
            </div>

            <button class="ime-x" @click="$emit('update:mostrarEditarEmpresa', false)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>

  <!-- Modal para crear nuevo centro educativo (compartido por nueva y editar empresa) -->
  <CentroEducativoModal
    :visible="mostrarModalCentro"
    :permitir-crear-empresa="false"
    @centro-creado="onCentroCreado"
    @cerrar="mostrarModalCentro = false"
  />

</template>

<style scoped>
.ime-overlay--elevado { z-index: 10050 !important; }

.ime-overlay {
  position: fixed; inset: 0;
  background: rgba(10, 18, 25, 0.75);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999; padding: 1rem;
}
.ime-card {
  position: relative; background: #fff;
  border: 1px solid #e5e7eb; border-radius: 2rem; padding: 2.5rem;
  width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto;
  box-shadow: 0 0 0 1px rgba(0,0,0,.03), 0 24px 48px rgba(0,0,0,.16), 0 0 80px rgba(48,114,170,.05);
  scrollbar-width: thin; scrollbar-color: #BAD5EC transparent;
}
.ime-card::-webkit-scrollbar       { width: 5px; }
.ime-card::-webkit-scrollbar-thumb { background: #BAD5EC; border-radius: 3px; }

.ime-header   { display: flex; align-items: flex-start; gap: 1rem; }
.ime-icon-box { flex-shrink: 0; width: 52px; height: 52px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; }
.ime-title    { font-size: 1.3rem; font-weight: 900; color: #1F2937; letter-spacing: -.025em; line-height: 1.2; }
.ime-sub      { font-size: .78rem; color: #6b7280; margin-top: .3rem; font-weight: 500; }

.ime-x {
  position: absolute; top: 1.25rem; right: 1.25rem; width: 32px; height: 32px;
  background: #f3f4f6; border: none; border-radius: .5rem; color: #9ca3af;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .2s, color .2s;
}
.ime-x:hover { background: #e5e7eb; color: #1F2937; }

.ime-tabs   { display: flex; gap: .25rem; background: #f3f4f6; padding: .3rem; border-radius: 1rem; }
.ime-tab    { flex: 1; display: flex; align-items: center; justify-content: center; gap: .4rem; padding: .6rem .75rem; border-radius: .75rem; border: none; font-size: .625rem; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; cursor: pointer; transition: all .2s; }
.ime-tab-on  { background: #fff; color: #1F2937; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
.ime-tab-off { background: transparent; color: #6b7280; }
.ime-tab-off:hover { color: #374151; }

.ime-input {
  width: 100%; border: 2px solid #BAD5EC; border-radius: 1rem; padding: .85rem 1rem;
  font-size: .875rem; font-weight: 600; color: #1F2937; background: #F6FAFC; outline: none; transition: all .2s; appearance: none;
}
.ime-input::placeholder { color: #9CA3AF; }
.ime-input:focus        { background: #DAE8F5; border-color: #3072AA; box-shadow: 0 0 0 4px rgba(48,114,170,.12); }
.ime-input-err          { border-color: #fca5a5 !important; background: #fff5f5 !important; }
.ime-input-err:focus    { border-color: #ef4444 !important; box-shadow: 0 0 0 4px rgba(239,68,68,.1) !important; }

.ime-label { display: block; font-size: .625rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: #6b7280; margin-bottom: .45rem; margin-left: .25rem; }
.ime-err   { font-size: .7rem; color: #ef4444; font-weight: 700; margin-top: .35rem; margin-left: .25rem; }

.ime-alert-error { display: flex; align-items: center; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 1rem; padding: .75rem 1rem; color: #dc2626; font-size: .78rem; font-weight: 700; }

.ime-g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.ime-g3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; }
@media (max-width: 640px) { .ime-g2, .ime-g3 { grid-template-columns: 1fr; } }

.ime-actions { display: flex; gap: .75rem; margin-top: 2rem; }

.ime-btn-green { display: flex; align-items: center; justify-content: center; gap: .5rem; padding: 1rem 1.5rem; background: linear-gradient(135deg, #3072AA, #6BA4D5); color: #fff; border: none; border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; cursor: pointer; box-shadow: 0 6px 20px rgba(48,114,170,.28); transition: all .2s; }
.ime-btn-green:hover:not(:disabled)  { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(48,114,170,.38); }
.ime-btn-green:active:not(:disabled) { transform: scale(.97); }
.ime-btn-green:disabled              { opacity: .5; cursor: not-allowed; }

.ime-btn-dark { display: flex; align-items: center; justify-content: center; gap: .5rem; padding: 1rem 1.5rem; background: #1F2937; color: #fff; border: none; border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; cursor: pointer; box-shadow: 0 6px 20px rgba(31,41,55,.25); transition: all .2s; }
.ime-btn-dark:hover:not(:disabled)  { background: #374151; transform: translateY(-1px); box-shadow: 0 10px 28px rgba(31,41,55,.35); }
.ime-btn-dark:active:not(:disabled) { transform: scale(.97); }
.ime-btn-dark:disabled              { opacity: .5; cursor: not-allowed; }

.ime-btn-ghost { display: flex; align-items: center; justify-content: center; padding: 1rem 1.5rem; background: #fff; color: #6b7280; border: 2px solid #e5e7eb; border-radius: 1rem; font-weight: 900; font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; cursor: pointer; transition: all .2s; }
.ime-btn-ghost:hover  { border-color: #d1d5db; color: #374151; }
.ime-btn-ghost:active { transform: scale(.97); }

.ime-overlay-enter-active, .ime-overlay-leave-active { transition: opacity .3s ease; }
.ime-overlay-enter-from,   .ime-overlay-leave-to    { opacity: 0; }
.ime-card-enter-active  { transition: all .42s cubic-bezier(.34,1.56,.64,1); }
.ime-card-leave-active  { transition: all .2s ease; }
.ime-card-enter-from    { opacity: 0; transform: scale(.91) translateY(28px); }
.ime-card-leave-to      { opacity: 0; transform: scale(.96) translateY(10px); }
.ime-fade-enter-active, .ime-fade-leave-active { transition: all .22s ease; }
.ime-fade-enter-from,   .ime-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>