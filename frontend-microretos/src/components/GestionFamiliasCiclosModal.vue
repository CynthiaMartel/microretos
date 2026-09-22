<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../api.js'
import { useRoleTheme } from '../composables/useRoleTheme.js'

const router    = useRouter()
const authStore = useAuthStore()
const { theme } = useRoleTheme()

const paletteExtra = {
  centros:          { hoverText: 'hover:text-centros', hoverBgSoft: 'hover:bg-centros/20', hoverIconBtn: 'hover:text-centros hover:bg-centros/10 hover:border-centros/30' },
  empresas:         { hoverText: 'hover:text-empresas', hoverBgSoft: 'hover:bg-empresas/20', hoverIconBtn: 'hover:text-empresas hover:bg-empresas/10 hover:border-empresas/30' },
  administraciones: { hoverText: 'hover:text-administraciones', hoverBgSoft: 'hover:bg-administraciones/20', hoverIconBtn: 'hover:text-administraciones hover:bg-administraciones/10 hover:border-administraciones/30' },
  primary:          { hoverText: 'hover:text-primary-700', hoverBgSoft: 'hover:bg-primary-600/20', hoverIconBtn: 'hover:text-primary-700 hover:bg-primary-600/10 hover:border-primary-600/30' },
}

function handle401(e) {
  if (e.response?.status === 401) {
    authStore.logout()
    router.push('/')
    return true
  }
  return false
}

const props = defineProps({
  visible: { type: Boolean, default: false },
})

const emit = defineEmits(['cerrar', 'cambios'])

// ─── Datos ──────────────────────────────────────────────────
const familias  = ref([])          // [{ id, nombre, imagen_url, ciclos: [], ciclosLoaded: false }]
const cargando  = ref(false)
const errorCarga = ref('')

// ─── Acordeón ────────────────────────────────────────────────
const familiaExpandida = ref(null)

// ─── Edición inline de familia ───────────────────────────────
const editFamiliaId   = ref(null)
const editFamiliaForm = reactive({ nombre: '', imagen_url: '' })
const editFamiliaErr  = ref('')
const editFamiliaLoad = ref(false)

// ─── Nueva familia ────────────────────────────────────────────
const mostrarNuevaFamilia = ref(false)
const nuevaFamiliaForm    = reactive({ nombre: '', imagen_url: '' })
const nuevaFamiliaErr     = ref('')
const nuevaFamiliaLoad    = ref(false)

// ─── Edición inline de ciclo ──────────────────────────────────
const editCicloId      = ref(null)
const editCicloFamId   = ref(null)   // para recargar la familia correcta tras guardar
const editCicloForm    = reactive({ nombre: '', grado: '', siglasGrado: '' })
const editCicloErr     = ref('')
const editCicloLoad    = ref(false)

// ─── Nuevo ciclo ──────────────────────────────────────────────
const nuevoCicloPorFamiliaId = ref(null)
const nuevoCicloForm         = reactive({ nombre: '', grado: '', siglasGrado: 'FPS' })
const nuevoCicloErr          = ref('')
const nuevoCicloLoad         = ref(false)

// ─── Confirmación de eliminación ─────────────────────────────
const confirmElimFamilia   = ref(null)   // { id, nombre, numCiclos }
const confirmElimCiclo     = ref(null)   // { id, nombre, familiaId }
const confirmNombreFamilia = ref('')
const confirmNombreCiclo   = ref('')
const elimLoading          = ref(false)
const elimError            = ref('')

const totalFamilias = computed(() => familias.value.length)
const totalCiclos   = computed(() =>
  familias.value.reduce((sum, f) => sum + (f.ciclos_count ?? 0), 0)
)

const GRADOS = [
  { value: 'Ciclo Formativo de Grado Básico',    siglas: 'FPB' },
  { value: 'Ciclo Formativo de Grado Medio',     siglas: 'FPM' },
  { value: 'Ciclo Formativo de Grado Superior',  siglas: 'FPS' },
]

// ════════════════════════════════════════════════════════════
//  CARGA
// ════════════════════════════════════════════════════════════
async function cargarFamilias() {
  cargando.value  = true
  errorCarga.value = ''
  try {
    const { data } = await api.get('/familias')
    familias.value = data.map(f => ({ ...f, ciclos: [], ciclosLoaded: false }))
  } catch {
    errorCarga.value = 'No se pudieron cargar las familias.'
  } finally {
    cargando.value = false
  }
}

async function cargarCiclosDeFamilia(familiaId) {
  const f = familias.value.find(x => x.id === familiaId)
  if (!f) return
  try {
    const { data } = await api.get(`/familias/${encodeURIComponent(f.nombre)}/ciclos`)
    f.ciclos       = data
    f.ciclosLoaded = true
  } catch {
    f.ciclosLoaded = true
    f.ciclos       = []
  }
}

async function toggleFamilia(id) {
  if (familiaExpandida.value === id) {
    familiaExpandida.value = null
    return
  }
  familiaExpandida.value = id
  const f = familias.value.find(x => x.id === id)
  if (f && !f.ciclosLoaded) await cargarCiclosDeFamilia(id)
}

watch(() => props.visible, async (v) => {
  if (v) {
    await cargarFamilias()
  } else {
    resetAll()
  }
})

function resetAll() {
  familiaExpandida.value       = null
  editFamiliaId.value          = null
  mostrarNuevaFamilia.value    = false
  editCicloId.value            = null
  nuevoCicloPorFamiliaId.value = null
  confirmElimFamilia.value     = null
  confirmElimCiclo.value       = null
  confirmNombreFamilia.value   = ''
  confirmNombreCiclo.value     = ''
  elimError.value              = ''
}

// ════════════════════════════════════════════════════════════
//  FAMILIA — CRUD
// ════════════════════════════════════════════════════════════
function abrirEditarFamilia(f) {
  editFamiliaId.value        = f.id
  editFamiliaForm.nombre     = f.nombre
  editFamiliaForm.imagen_url = f.imagen_url || ''
  editFamiliaErr.value       = ''
  // cerrar form de nuevo ciclo si estaba abierto para esta familia
  if (nuevoCicloPorFamiliaId.value === f.id) nuevoCicloPorFamiliaId.value = null
  editCicloId.value = null
}

function cancelarEditarFamilia() {
  editFamiliaId.value  = null
  editFamiliaErr.value = ''
}

async function guardarFamilia() {
  editFamiliaErr.value = ''
  if (!editFamiliaForm.nombre.trim()) { editFamiliaErr.value = 'El nombre es obligatorio.'; return }
  editFamiliaLoad.value = true
  try {
    const { data } = await api.put(`/familias/${editFamiliaId.value}`, {
      nombre:     editFamiliaForm.nombre.trim(),
      imagen_url: editFamiliaForm.imagen_url || null,
    })
    const idx = familias.value.findIndex(f => f.id === editFamiliaId.value)
    if (idx !== -1) {
      familias.value[idx].nombre     = data.familia.nombre
      familias.value[idx].imagen_url = data.familia.imagen_url
    }
    editFamiliaId.value = null
    emit('cambios')
  } catch (e) {
    editFamiliaErr.value = e.response?.data?.message || e.response?.data?.errors?.nombre?.[0] || 'Error al guardar.'
  } finally {
    editFamiliaLoad.value = false
  }
}

function pedirEliminarFamilia(f) {
  elimError.value            = ''
  confirmNombreFamilia.value = ''
  confirmElimFamilia.value   = {
    id:        f.id,
    nombre:    f.nombre,
    numCiclos: f.ciclos?.length ?? 0,
  }
}

async function confirmarEliminarFamilia() {
  elimLoading.value = true
  elimError.value   = ''
  try {
    await api.delete(`/familias/${confirmElimFamilia.value.id}`)
    familias.value = familias.value.filter(f => f.id !== confirmElimFamilia.value.id)
    confirmElimFamilia.value = null
    emit('cambios')
  } catch (e) {
    elimError.value = e.response?.data?.error || 'No se pudo eliminar.'
  } finally {
    elimLoading.value = false
  }
}

async function guardarNuevaFamilia() {
  nuevaFamiliaErr.value = ''
  if (!nuevaFamiliaForm.nombre.trim()) { nuevaFamiliaErr.value = 'El nombre es obligatorio.'; return }
  nuevaFamiliaLoad.value = true
  try {
    const { data } = await api.post('/familias', {
      nombre:     nuevaFamiliaForm.nombre.trim(),
      imagen_url: nuevaFamiliaForm.imagen_url || null,
    })
    familias.value = [...familias.value, { ...data.familia, ciclos: [], ciclosLoaded: true }]
      .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
    nuevaFamiliaForm.nombre     = ''
    nuevaFamiliaForm.imagen_url = ''
    mostrarNuevaFamilia.value   = false
    emit('cambios')
  } catch (e) {
    nuevaFamiliaErr.value = e.response?.data?.message || e.response?.data?.errors?.nombre?.[0] || 'Error al crear.'
  } finally {
    nuevaFamiliaLoad.value = false
  }
}

// ════════════════════════════════════════════════════════════
//  CICLO — CRUD
// ════════════════════════════════════════════════════════════
function abrirEditarCiclo(ciclo, familiaId) {
  editCicloId.value    = ciclo.id
  editCicloFamId.value = familiaId
  editCicloForm.nombre      = ciclo.nombre
  editCicloForm.grado       = ciclo.grado || ''
  editCicloForm.siglasGrado = ciclo.siglasGrado || ''
  editCicloErr.value         = ''
  nuevoCicloPorFamiliaId.value = null
}

function cancelarEditarCiclo() {
  editCicloId.value    = null
  editCicloFamId.value = null
  editCicloErr.value   = ''
}

async function guardarCiclo() {
  editCicloErr.value = ''
  if (!editCicloForm.nombre.trim()) { editCicloErr.value = 'El nombre es obligatorio.'; return }
  editCicloLoad.value = true
  try {
    await api.put(`/ciclos/${editCicloId.value}`, {
      nombre:      editCicloForm.nombre.trim(),
      familia_id:  editCicloFamId.value,
      grado:       editCicloForm.grado       || null,
      siglasGrado: editCicloForm.siglasGrado || null,
    })
    // Recargar ciclos de la familia
    const f = familias.value.find(x => x.id === editCicloFamId.value)
    if (f) { f.ciclosLoaded = false; await cargarCiclosDeFamilia(f.id) }
    editCicloId.value = null
    emit('cambios')
  } catch (e) {
    editCicloErr.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    editCicloLoad.value = false
  }
}

function pedirEliminarCiclo(ciclo, familiaId) {
  elimError.value           = ''
  confirmNombreCiclo.value  = ''
  confirmElimCiclo.value    = { id: ciclo.id, nombre: ciclo.nombre, familiaId }
}

async function confirmarEliminarCiclo() {
  elimLoading.value = true
  elimError.value   = ''
  try {
    await api.delete(`/ciclos/${confirmElimCiclo.value.id}`)
    const f = familias.value.find(x => x.id === confirmElimCiclo.value.familiaId)
    if (f) f.ciclos = f.ciclos.filter(c => c.id !== confirmElimCiclo.value.id)
    confirmElimCiclo.value = null
    emit('cambios')
  } catch (e) {
    elimError.value = e.response?.data?.error || 'No se pudo eliminar.'
  } finally {
    elimLoading.value = false
  }
}

function abrirNuevoCiclo(familiaId) {
  nuevoCicloPorFamiliaId.value  = familiaId
  nuevoCicloForm.nombre         = ''
  nuevoCicloForm.grado          = 'Ciclo Formativo de Grado Superior'
  nuevoCicloForm.siglasGrado    = 'FPS'
  nuevoCicloErr.value           = ''
  editCicloId.value             = null
}

function onGradoChange() {
  const g = GRADOS.find(x => x.value === nuevoCicloForm.grado)
  if (g) nuevoCicloForm.siglasGrado = g.siglas
}
function onGradoEditChange() {
  const g = GRADOS.find(x => x.value === editCicloForm.grado)
  if (g) editCicloForm.siglasGrado = g.siglas
}

async function guardarNuevoCiclo(familiaId) {
  nuevoCicloErr.value = ''
  if (!nuevoCicloForm.nombre.trim()) { nuevoCicloErr.value = 'El nombre es obligatorio.'; return }
  nuevoCicloLoad.value = true
  try {
    await api.post('/ciclos', {
      nombre:      nuevoCicloForm.nombre.trim(),
      familia_id:  familiaId,
      grado:       nuevoCicloForm.grado       || null,
      siglasGrado: nuevoCicloForm.siglasGrado || 'FP',
    })
    const f = familias.value.find(x => x.id === familiaId)
    if (f) { f.ciclosLoaded = false; await cargarCiclosDeFamilia(f.id) }
    nuevoCicloPorFamiliaId.value = null
    emit('cambios')
  } catch (e) {
    nuevoCicloErr.value = e.response?.data?.message || 'Error al crear.'
  } finally {
    nuevoCicloLoad.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="gfcm-overlay">
      <div
        v-if="visible"
        class="gfcm-overlay"
        @click.self="$emit('cerrar')"
      >
        <Transition name="gfcm-card">
          <div v-if="visible" class="gfcm-card">

            <!-- ══ Cabecera ══ -->
            <div class="flex items-start gap-4 mb-6">
              <div class="w-12 h-12 rounded-2xl bg-[#1F2937]/8 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-[#1F2937]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="text-xl font-black text-[#1F2937] tracking-tight">Catálogo de Familias y Ciclos</h2>
                <p class="text-xs text-gray-400 font-medium mt-0.5">
                  Gestiona las familias profesionales y sus ciclos formativos. Los cambios afectan a todo el sistema.
                </p>
              </div>
              <button @click="$emit('cerrar')" class="gfcm-x">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- ══ Contadores ══ -->
            <div v-if="!cargando && totalFamilias > 0" class="flex items-center gap-2 mb-5 -mt-2">
              <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border" :class="[theme.bg5, theme.border20]">
                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="theme.bg" />
                <span class="text-[11px] font-black" :class="theme.text">{{ totalFamilias }}</span>
                <span class="text-[11px]" :class="theme.text50">familias</span>
              </div>
              <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gray-50 border border-gray-200">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 shrink-0" />
                <span class="text-[11px] font-black text-gray-600">{{ totalCiclos }}</span>
                <span class="text-[11px] text-gray-400">ciclos</span>
              </div>
            </div>

            <!-- ══ Cargando ══ -->
            <div v-if="cargando" class="flex items-center justify-center py-16 gap-3 text-gray-400">
              <div class="w-6 h-6 rounded-full border-2 border-gray-200 border-t-current animate-spin" :class="theme.text"/>
              <span class="text-sm font-medium">Cargando catálogo...</span>
            </div>

            <!-- ══ Error ══ -->
            <div v-else-if="errorCarga" class="bg-red-50 border border-red-200 rounded-2xl p-4 text-center text-sm text-red-600 font-bold">
              {{ errorCarga }}
              <button @click="cargarFamilias" class="block mt-2 mx-auto text-xs text-red-500 hover:text-red-700 underline">Reintentar</button>
            </div>

            <!-- ══ Contenido ══ -->
            <div v-else class="space-y-2">

              <!-- Botón nueva familia -->
              <div class="flex justify-end mb-3">
                <button
                  v-if="!mostrarNuevaFamilia"
                  @click="mostrarNuevaFamilia = true; editFamiliaId = null"
                  class="flex items-center gap-1.5 px-4 py-2 rounded-xl
                         border
                         font-black text-xs uppercase tracking-widest
                         transition-all duration-150"
                  :class="[theme.bg5, theme.border20, theme.text, paletteExtra[theme.key].hoverBgSoft]"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                  </svg>
                  Nueva familia
                </button>
              </div>

              <!-- Form nueva familia -->
              <Transition name="gfcm-fade">
                <div v-if="mostrarNuevaFamilia"
                  class="border rounded-2xl p-4 mb-2 space-y-3" :class="[theme.bg5, theme.border20]">
                  <p class="text-[10px] font-black uppercase tracking-widest mb-1" :class="theme.text">Nueva familia profesional</p>
                  <div class="flex gap-3">
                    <div class="flex-1">
                      <input
                        v-model="nuevaFamiliaForm.nombre"
                        placeholder="Nombre de la familia (ej: Informática y Comunicaciones)"
                        class="gfcm-input w-full"
                        @keyup.enter="guardarNuevaFamilia"
                        @keyup.escape="mostrarNuevaFamilia = false"
                      />
                      <p v-if="nuevaFamiliaErr" class="text-xs text-red-500 font-bold mt-1 ml-1">{{ nuevaFamiliaErr }}</p>
                    </div>
                    <button @click="guardarNuevaFamilia" :disabled="nuevaFamiliaLoad"
                      class="gfcm-btn-green shrink-0">
                      <svg v-if="nuevaFamiliaLoad" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                      </svg>
                      <span v-else>Crear</span>
                    </button>
                    <button @click="mostrarNuevaFamilia = false; nuevaFamiliaErr = ''" class="gfcm-btn-ghost shrink-0">
                      Cancelar
                    </button>
                  </div>
                </div>
              </Transition>

              <!-- Sin familias -->
              <div v-if="familias.length === 0"
                class="text-center py-12 text-gray-400 text-sm font-medium">
                No hay familias en el catálogo.
              </div>

              <!-- Lista de familias -->
              <div
                v-for="familia in familias"
                :key="familia.id"
                class="bg-gray-50/60 border border-gray-100 rounded-2xl overflow-hidden"
              >
                <!-- Cabecera familia -->
                <div class="flex items-center gap-2 pl-4 pr-2 py-3">
                  <button
                    @click="toggleFamilia(familia.id)"
                    class="flex-1 flex items-center gap-3 text-left min-w-0"
                  >
                    <div class="w-2 h-2 rounded-full shrink-0" :class="theme.bg"/>
                    <span class="font-bold text-sm text-[#1F2937] truncate">{{ familia.nombre }}</span>
                    <span v-if="familia.ciclosLoaded"
                      class="text-[10px] font-black uppercase tracking-widest
                             bg-white border border-gray-200 text-gray-400
                             px-2 py-0.5 rounded-full shrink-0">
                      {{ familia.ciclos.length }} ciclos
                    </span>
                    <svg
                      class="w-4 h-4 text-gray-400 transition-transform duration-200 ml-auto shrink-0"
                      :class="familiaExpandida === familia.id ? 'rotate-180' : ''"
                      fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </button>

                  <!-- Botones editar / eliminar familia -->
                  <div class="flex items-center gap-1 shrink-0">
                    <button
                      @click.stop="abrirEditarFamilia(familia)"
                      :class="editFamiliaId === familia.id ? [theme.text, theme.bg5, theme.border20] : ['text-gray-300', paletteExtra[theme.key].hoverIconBtn]"
                      class="w-8 h-8 rounded-xl flex items-center justify-center border border-transparent transition-all duration-150"
                      title="Editar familia"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </button>
                    <button
                      @click.stop="pedirEliminarFamilia(familia)"
                      class="w-8 h-8 rounded-xl flex items-center justify-center border border-transparent
                             text-gray-300 hover:text-red-500 hover:bg-red-50 hover:border-red-200
                             transition-all duration-150"
                      title="Eliminar familia"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Form edición inline de familia -->
                <Transition name="gfcm-fade">
                  <div v-if="editFamiliaId === familia.id"
                    class="px-4 pb-3 pt-0 border-t border-gray-100 bg-white">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 mt-3">Editar familia</p>
                    <div class="flex gap-3">
                      <div class="flex-1">
                        <input
                          v-model="editFamiliaForm.nombre"
                          placeholder="Nombre"
                          class="gfcm-input w-full"
                          @keyup.enter="guardarFamilia"
                          @keyup.escape="cancelarEditarFamilia"
                        />
                        <p v-if="editFamiliaErr" class="text-xs text-red-500 font-bold mt-1 ml-1">{{ editFamiliaErr }}</p>
                      </div>
                      <button @click="guardarFamilia" :disabled="editFamiliaLoad" class="gfcm-btn-dark shrink-0">
                        <svg v-if="editFamiliaLoad" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24">
                          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                        </svg>
                        <span v-else>Guardar</span>
                      </button>
                      <button @click="cancelarEditarFamilia" class="gfcm-btn-ghost shrink-0">Cancelar</button>
                    </div>
                  </div>
                </Transition>

                <!-- Ciclos expandidos -->
                <Transition name="gfcm-expand">
                  <div v-if="familiaExpandida === familia.id" class="border-t border-gray-100">

                    <!-- Cargando ciclos -->
                    <div v-if="!familia.ciclosLoaded" class="flex items-center gap-2 px-5 py-4 text-gray-400 text-xs">
                      <div class="w-4 h-4 rounded-full border-2 border-gray-200 border-t-current animate-spin" :class="theme.text"/>
                      Cargando ciclos...
                    </div>

                    <template v-else>
                      <!-- Sin ciclos -->
                      <div v-if="familia.ciclos.length === 0 && nuevoCicloPorFamiliaId !== familia.id"
                        class="px-5 py-3 text-xs text-gray-400 italic">
                        Sin ciclos registrados.
                      </div>

                      <!-- Lista de ciclos -->
                      <div
                        v-for="ciclo in familia.ciclos"
                        :key="ciclo.id"
                        class="border-b border-gray-50 last:border-b-0"
                      >
                        <!-- Fila ciclo -->
                        <div v-if="editCicloId !== ciclo.id"
                          class="flex items-center gap-2 pl-8 pr-2 py-2.5 hover:bg-white/70 transition-colors">
                          <span class="text-[11px] font-semibold text-gray-600 flex-1 truncate">
                            {{ ciclo.nombre }}
                          </span>
                          <span v-if="ciclo.siglasGrado"
                            class="text-[10px] font-black bg-[#1F2937]/8 text-gray-500 px-2 py-0.5 rounded-full shrink-0 uppercase">
                            {{ ciclo.siglasGrado }}
                          </span>
                          <button
                            @click="abrirEditarCiclo(ciclo, familia.id)"
                            class="w-7 h-7 rounded-lg flex items-center justify-center border border-transparent
                                   text-gray-300
                                   transition-all duration-150 shrink-0"
                            :class="paletteExtra[theme.key].hoverIconBtn"
                            title="Editar ciclo"
                          >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                          </button>
                          <button
                            @click="pedirEliminarCiclo(ciclo, familia.id)"
                            class="w-7 h-7 rounded-lg flex items-center justify-center border border-transparent
                                   text-gray-300 hover:text-red-500 hover:bg-red-50 hover:border-red-200
                                   transition-all duration-150 shrink-0"
                            title="Eliminar ciclo"
                          >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                          </button>
                        </div>

                        <!-- Form edición inline ciclo -->
                        <div v-else class="pl-8 pr-3 py-3 bg-white border-b border-gray-50">
                          <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Editar ciclo</p>
                          <div class="space-y-2">
                            <input
                              v-model="editCicloForm.nombre"
                              placeholder="Nombre del ciclo"
                              class="gfcm-input w-full"
                              @keyup.escape="cancelarEditarCiclo"
                            />
                            <div class="flex gap-2">
                              <select v-model="editCicloForm.grado" @change="onGradoEditChange" class="gfcm-input flex-1 text-xs">
                                <option value="">Sin grado</option>
                                <option v-for="g in GRADOS" :key="g.value" :value="g.value">{{ g.value }}</option>
                              </select>
                              <input v-model="editCicloForm.siglasGrado" maxlength="3"
                                placeholder="Siglas" class="gfcm-input w-20 text-center text-xs uppercase"/>
                            </div>
                            <p v-if="editCicloErr" class="text-xs text-red-500 font-bold ml-1">{{ editCicloErr }}</p>
                            <div class="flex gap-2 pt-1">
                              <button @click="guardarCiclo" :disabled="editCicloLoad" class="gfcm-btn-dark">
                                <svg v-if="editCicloLoad" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24">
                                  <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                                </svg>
                                <span v-else>Guardar</span>
                              </button>
                              <button @click="cancelarEditarCiclo" class="gfcm-btn-ghost">Cancelar</button>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Form nuevo ciclo -->
                      <Transition name="gfcm-fade">
                        <div v-if="nuevoCicloPorFamiliaId === familia.id"
                          class="pl-8 pr-3 py-3 border-t" :class="[theme.bg5, theme.border15]">
                          <p class="text-[10px] font-black uppercase tracking-widest mb-2" :class="theme.text">Nuevo ciclo</p>
                          <div class="space-y-2">
                            <input
                              v-model="nuevoCicloForm.nombre"
                              placeholder="Nombre del ciclo formativo"
                              class="gfcm-input w-full"
                              @keyup.escape="nuevoCicloPorFamiliaId = null"
                            />
                            <div class="flex gap-2">
                              <select v-model="nuevoCicloForm.grado" @change="onGradoChange" class="gfcm-input flex-1 text-xs">
                                <option value="">Sin grado</option>
                                <option v-for="g in GRADOS" :key="g.value" :value="g.value">{{ g.value }}</option>
                              </select>
                              <input v-model="nuevoCicloForm.siglasGrado" maxlength="3"
                                placeholder="Siglas" class="gfcm-input w-20 text-center text-xs uppercase"/>
                            </div>
                            <p v-if="nuevoCicloErr" class="text-xs text-red-500 font-bold ml-1">{{ nuevoCicloErr }}</p>
                            <div class="flex gap-2 pt-1">
                              <button @click="guardarNuevoCiclo(familia.id)" :disabled="nuevoCicloLoad" class="gfcm-btn-green">
                                <svg v-if="nuevoCicloLoad" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24">
                                  <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                                </svg>
                                <span v-else>Crear ciclo</span>
                              </button>
                              <button @click="nuevoCicloPorFamiliaId = null; nuevoCicloErr = ''" class="gfcm-btn-ghost">Cancelar</button>
                            </div>
                          </div>
                        </div>
                      </Transition>

                      <!-- Botón añadir ciclo -->
                      <div v-if="nuevoCicloPorFamiliaId !== familia.id"
                        class="px-5 py-2.5 border-t border-gray-50">
                        <button
                          @click="abrirNuevoCiclo(familia.id)"
                          class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest
                                 text-gray-400 transition-colors duration-150"
                          :class="paletteExtra[theme.key].hoverText"
                        >
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                          </svg>
                          Añadir ciclo
                        </button>
                      </div>
                    </template>

                  </div>
                </Transition>
              </div>
            </div>

            <!-- ══ Modal confirmación eliminar FAMILIA ══ -->
            <Transition name="gfcm-fade">
              <div v-if="confirmElimFamilia"
                class="fixed inset-0 z-10010 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white rounded-[2rem] shadow-2xl max-w-sm w-full p-6 border border-gray-100">

                  <!-- Cabecera -->
                  <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-2xl bg-red-100 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </div>
                    <div>
                      <h3 class="font-black text-base text-[#1F2937]">Eliminar familia</h3>
                      <p class="text-xs text-gray-400">Esta acción no se puede deshacer</p>
                    </div>
                  </div>

                  <!-- Nombre + estado -->
                  <div class="bg-red-50 border border-red-200 rounded-2xl p-3 mb-4">
                    <p class="text-sm font-black text-red-800">"{{ confirmElimFamilia.nombre }}"</p>
                    <p class="text-xs text-red-600 mt-1">
                      {{ confirmElimFamilia.numCiclos > 0
                        ? `Tiene ${confirmElimFamilia.numCiclos} ciclo(s) asociado(s). Elimina primero todos sus ciclos.`
                        : 'Afecta a todos los retos y empresas vinculadas a esta familia.' }}
                    </p>
                  </div>

                  <!-- Confirmación por nombre (solo si se puede borrar) -->
                  <template v-if="confirmElimFamilia.numCiclos === 0">
                    <p class="text-xs text-gray-500 mb-1.5">
                      Escribe <span class="font-black text-gray-700">{{ confirmElimFamilia.nombre }}</span> para confirmar:
                    </p>
                    <input
                      v-model="confirmNombreFamilia"
                      :placeholder="confirmElimFamilia.nombre"
                      class="w-full px-3 py-2 rounded-xl border text-sm mb-3 focus:outline-none focus:ring-2 transition-all"
                      :class="confirmNombreFamilia === confirmElimFamilia.nombre
                        ? 'border-green-300 focus:ring-green-200 bg-green-50'
                        : 'border-gray-200 focus:ring-red-200'"
                      @keyup.enter="confirmNombreFamilia === confirmElimFamilia.nombre && confirmarEliminarFamilia()"
                    />
                  </template>

                  <p v-if="elimError" class="text-xs text-red-600 font-bold mb-3">{{ elimError }}</p>

                  <div class="flex gap-2">
                    <button
                      @click="confirmElimFamilia = null; confirmNombreFamilia = ''; elimError = ''"
                      class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-gray-500 hover:bg-gray-50 transition-all">
                      Cancelar
                    </button>
                    <button
                      @click="confirmarEliminarFamilia"
                      :disabled="elimLoading || confirmElimFamilia.numCiclos > 0 || confirmNombreFamilia !== confirmElimFamilia.nombre"
                      class="flex-1 py-2.5 rounded-xl bg-red-500 text-white text-sm font-black hover:bg-red-600 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
                    >
                      {{ elimLoading ? 'Eliminando...' : 'Eliminar definitivamente' }}
                    </button>
                  </div>
                </div>
              </div>
            </Transition>

            <!-- ══ Modal confirmación eliminar CICLO ══ -->
            <Transition name="gfcm-fade">
              <div v-if="confirmElimCiclo"
                class="fixed inset-0 z-10010 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white rounded-[2rem] shadow-2xl max-w-sm w-full p-6 border border-gray-100">

                  <!-- Cabecera -->
                  <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-2xl bg-red-100 flex items-center justify-center shrink-0">
                      <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </div>
                    <div>
                      <h3 class="font-black text-base text-[#1F2937]">Eliminar ciclo formativo</h3>
                      <p class="text-xs text-gray-400">Esta acción no se puede deshacer</p>
                    </div>
                  </div>

                  <!-- Nombre -->
                  <div class="bg-red-50 border border-red-200 rounded-2xl p-3 mb-4">
                    <p class="text-sm font-black text-red-800">"{{ confirmElimCiclo.nombre }}"</p>
                    <p class="text-xs text-red-600 mt-1">Se eliminará permanentemente del catálogo y afectará a todas las relaciones del sistema.</p>
                  </div>

                  <!-- Confirmación por nombre -->
                  <p class="text-xs text-gray-500 mb-1.5">
                    Escribe <span class="font-black text-gray-700">{{ confirmElimCiclo.nombre }}</span> para confirmar:
                  </p>
                  <input
                    v-model="confirmNombreCiclo"
                    :placeholder="confirmElimCiclo.nombre"
                    class="w-full px-3 py-2 rounded-xl border text-sm mb-3 focus:outline-none focus:ring-2 transition-all"
                    :class="confirmNombreCiclo === confirmElimCiclo.nombre
                      ? 'border-green-300 focus:ring-green-200 bg-green-50'
                      : 'border-gray-200 focus:ring-red-200'"
                    @keyup.enter="confirmNombreCiclo === confirmElimCiclo.nombre && confirmarEliminarCiclo()"
                  />

                  <p v-if="elimError" class="text-xs text-red-600 font-bold mb-3">{{ elimError }}</p>

                  <div class="flex gap-2">
                    <button
                      @click="confirmElimCiclo = null; confirmNombreCiclo = ''; elimError = ''"
                      class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-gray-500 hover:bg-gray-50 transition-all">
                      Cancelar
                    </button>
                    <button
                      @click="confirmarEliminarCiclo"
                      :disabled="elimLoading || confirmNombreCiclo !== confirmElimCiclo.nombre"
                      class="flex-1 py-2.5 rounded-xl bg-red-500 text-white text-sm font-black hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                    >
                      {{ elimLoading ? 'Eliminando...' : 'Eliminar definitivamente' }}
                    </button>
                  </div>
                </div>
              </div>
            </Transition>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.gfcm-overlay {
  position: fixed; inset: 0;
  background: rgba(10, 18, 25, 0.70);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999; padding: 1rem;
}
.gfcm-card {
  position: relative; background: #fff;
  border: 1px solid #e5e7eb; border-radius: 2rem; padding: 2rem;
  width: 100%; max-width: 680px; max-height: 88vh; overflow-y: auto;
  box-shadow: 0 24px 48px rgba(0,0,0,.14);
  scrollbar-width: thin; scrollbar-color: #e5e7eb transparent;
}
.gfcm-card::-webkit-scrollbar       { width: 4px; }
.gfcm-card::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 2px; }

.gfcm-x {
  flex-shrink: 0; width: 32px; height: 32px;
  background: #f3f4f6; border: none; border-radius: .5rem; color: #9ca3af;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background .15s, color .15s;
}
.gfcm-x:hover { background: #e5e7eb; color: #1F2937; }

.gfcm-input {
  border: 1.5px solid #d1d5db; border-radius: .75rem; padding: .6rem .85rem;
  font-size: .8rem; font-weight: 600; color: #1F2937; background: #fff;
  outline: none; transition: all .15s; appearance: none;
}
.gfcm-input::placeholder { color: #9CA3AF; }
.gfcm-input:focus { border-color: v-bind('theme.hex'); box-shadow: 0 0 0 3px v-bind('theme.hexSoft'); }

.gfcm-btn-green {
  display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
  padding: .55rem 1.1rem; background: v-bind('theme.hex'); color: #fff;
  border: none; border-radius: .75rem; font-weight: 900; font-size: .7rem;
  letter-spacing: .1em; text-transform: uppercase; cursor: pointer; transition: all .15s;
}
.gfcm-btn-green:hover:not(:disabled) { filter: brightness(0.92); }
.gfcm-btn-green:disabled { opacity: .5; cursor: not-allowed; }

.gfcm-btn-dark {
  display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
  padding: .55rem 1.1rem; background: #1F2937; color: #fff;
  border: none; border-radius: .75rem; font-weight: 900; font-size: .7rem;
  letter-spacing: .1em; text-transform: uppercase; cursor: pointer; transition: all .15s;
}
.gfcm-btn-dark:hover:not(:disabled) { background: #374151; }
.gfcm-btn-dark:disabled { opacity: .5; cursor: not-allowed; }

.gfcm-btn-ghost {
  display: inline-flex; align-items: center; justify-content: center;
  padding: .55rem .9rem; background: #fff; color: #6b7280;
  border: 1.5px solid #e5e7eb; border-radius: .75rem; font-weight: 900; font-size: .7rem;
  letter-spacing: .1em; text-transform: uppercase; cursor: pointer; transition: all .15s;
}
.gfcm-btn-ghost:hover { border-color: #d1d5db; color: #374151; }

/* Transitions */
.gfcm-overlay-enter-active, .gfcm-overlay-leave-active { transition: opacity .25s ease; }
.gfcm-overlay-enter-from,   .gfcm-overlay-leave-to    { opacity: 0; }
.gfcm-card-enter-active { transition: all .38s cubic-bezier(.34,1.56,.64,1); }
.gfcm-card-leave-active { transition: all .18s ease; }
.gfcm-card-enter-from   { opacity: 0; transform: scale(.92) translateY(24px); }
.gfcm-card-leave-to     { opacity: 0; transform: scale(.96) translateY(8px); }
.gfcm-fade-enter-active, .gfcm-fade-leave-active { transition: all .18s ease; }
.gfcm-fade-enter-from,   .gfcm-fade-leave-to    { opacity: 0; transform: translateY(-4px); }
.gfcm-expand-enter-active, .gfcm-expand-leave-active { transition: all .22s ease; overflow: hidden; }
.gfcm-expand-enter-from,   .gfcm-expand-leave-to    { opacity: 0; }
</style>
