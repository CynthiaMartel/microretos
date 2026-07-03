<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api.js'

const route  = useRoute()
const router = useRouter()

// ── Estado global ──────────────────────────────────────────────────────────
const cargando  = ref(true)
const error     = ref(false)
const isLoaded  = ref(false)
const workspace = ref(null)
const faseVista = ref(0)
const guardando = ref(false)
const msgOk     = ref('')

const token = route.params.token

// ── Helpers de acceso a datos ─────────────────────────────────────────────
const equipo      = computed(() => workspace.value?.equipo)
const proyecto    = computed(() => workspace.value?.proyecto)
const diagnostico = computed(() => workspace.value?.diagnostico)
const preguntasF0 = computed(() => workspace.value?.preguntas_f0 ?? [])
const tareas      = computed(() => workspace.value?.tareas ?? [])
const reflexiones = computed(() => workspace.value?.reflexiones ?? [])

function getFase(n) {
  return workspace.value?.fases?.[n] ?? { numero_fase: n, datos: null, completada: false, validado_docente: false }
}

// ── Definición visual de fases ─────────────────────────────────────────────
const fasesConfig = [
  { num: 0, label: 'Inicio del equipo',  shortLabel: 'Inicio',     icono: '👥', color: 'slate',  desc: 'Constitución del equipo',    descLarga: 'Conóceos, estableced roles y acordad cómo vais a trabajar juntos durante el reto. Esta fase no se evalúa, pero es clave para que todo lo demás funcione.' },
  { num: 1, label: 'Análisis del reto',  shortLabel: 'Análisis',   icono: '🔍', color: 'blue',   desc: 'Comprensión del reto',        descLarga: 'Analizad en profundidad el reto planteado por la empresa y definid vuestra propuesta de solución con datos concretos.' },
  { num: 2, label: 'Diseño de solución', shortLabel: 'Diseño',     icono: '💡', color: 'amber',  desc: 'Prototipo y propuesta',       descLarga: 'Dividid el trabajo en tareas y avanzad en la construcción de vuestra solución. Registrad el progreso de cada tarea.' },
  { num: 3, label: 'Desarrollo',         shortLabel: 'Desarrollo', icono: '🔨', color: 'orange', desc: 'Construcción del producto',   descLarga: 'Entregad el trabajo final al docente y a la empresa validadora. Incluye el enlace a vuestro entregable.' },
  { num: 4, label: 'Presentación',       shortLabel: 'Presenta.',  icono: '🎓', color: 'green',  desc: 'Entrega y reflexión',         descLarga: 'Reflexionad individualmente y en grupo sobre lo aprendido. Es vuestro cierre del proyecto.' },
]

const colorMap = {
  slate:  { activo: 'bg-slate-600  border-slate-400  text-white',  done: 'bg-slate-500/20 border-slate-400/40 text-slate-300', lock: 'bg-white/5 border-white/10 text-white/20' },
  blue:   { activo: 'bg-blue-600   border-blue-400   text-white',  done: 'bg-blue-500/20  border-blue-400/40  text-blue-300',  lock: 'bg-white/5 border-white/10 text-white/20' },
  amber:  { activo: 'bg-amber-500  border-amber-400  text-white',  done: 'bg-amber-500/20 border-amber-400/40 text-amber-300', lock: 'bg-white/5 border-white/10 text-white/20' },
  orange: { activo: 'bg-orange-500 border-orange-400 text-white',  done: 'bg-orange-500/20 border-orange-400/40 text-orange-300', lock: 'bg-white/5 border-white/10 text-white/20' },
  green:  { activo: 'bg-[#00A859] border-[#00A859]   text-white',  done: 'bg-[#00A859]/20 border-[#00A859]/40  text-[#00A859]', lock: 'bg-white/5 border-white/10 text-white/20' },
}

function estadoFaseBtn(n) {
  if (!workspace.value) return 'lock'
  if (faseVista.value === n) return 'activo'
  if (getFase(n).completada)  return 'done'
  if (n <= equipo.value.fase_actual) return 'activo'
  return 'lock'
}

function puedeVerFase(n) {
  if (!equipo.value) return false
  return n <= equipo.value.fase_actual || getFase(n).completada
}

// ── Sidebar helpers ────────────────────────────────────────────────────────
const AVATAR_COLORS = ['#4F7FFA', '#10B981', '#F59E0B', '#8B5CF6', '#06B6D4', '#EC4899', '#EF4444']

function avatarColor(idx) {
  return AVATAR_COLORS[idx % AVATAR_COLORS.length]
}

function initials(name) {
  return (name || '').split(' ').filter(Boolean).map(n => n[0]).join('').slice(0, 2).toUpperCase()
}

function pctFase(n) {
  if (!equipo.value) return null
  const fase = getFase(n)
  if (fase.completada || n < equipo.value.fase_actual) return 100
  if (n === equipo.value.fase_actual) {
    return n === 2 ? progreso.value : null
  }
  return null
}

// ── Carga inicial ──────────────────────────────────────────────────────────
onMounted(async () => {
  setTimeout(() => { isLoaded.value = true }, 60)
  try {
    const res = await api.get(`/equipo/${token}`)
    workspace.value = res.data
    faseVista.value = equipo.value.fase_actual
    localStorage.setItem('dualab_equipo_token', token)
    localStorage.setItem('dualab_equipo_nombre', equipo.value.nombre)
    localStorage.setItem('dualab_proyecto_titulo', proyecto.value.titulo)
  } catch {
    error.value = true
  } finally {
    cargando.value = false
  }
})

// ── Guardar fase ──────────────────────────────────────────────────────────
async function guardarFase(n, datos) {
  guardando.value = true
  try {
    await api.put(`/equipo/${token}/fase/${n}`, { datos })
    workspace.value.fases[n] = { ...getFase(n), datos }
    if (n === 0 && datos.miembros) workspace.value.equipo.miembros = datos.miembros
    mostrarOk('Guardado correctamente')
  } finally {
    guardando.value = false
  }
}

async function completarFase(n) {
  guardando.value = true
  try {
    const res = await api.post(`/equipo/${token}/fase/${n}/completar`)
    workspace.value.fases[n] = { ...getFase(n), completada: true }
    workspace.value.equipo.fase_actual = res.data.fase_actual
    faseVista.value = res.data.fase_actual
    mostrarOk('¡Fase completada! Ahora podéis continuar con la siguiente.')
  } finally {
    guardando.value = false
  }
}

function mostrarOk(msg) {
  msgOk.value = msg
  setTimeout(() => { msgOk.value = '' }, 3000)
}

// ── F0: Formularios de equipo y síntesis ───────────────────────────────────
const f0 = ref({ contrato_firmado: false, miembros: [], sintesis: [] })
const nuevoMiembro = ref({ nombre: '', rol: '' })
const rolesOpciones = ['portavoz', 'tiempos', 'documentacion', 'foco']

function inicializarF0() {
  const datos = getFase(0).datos ?? {}
  f0.value = {
    contrato_firmado: datos.contrato_firmado ?? false,
    miembros: datos.miembros ?? [],
    sintesis: datos.sintesis?.length
      ? datos.sintesis
      : preguntasF0.value.map(p => ({ pregunta: p, respuesta: '' })),
  }
}

function addMiembro() {
  if (!nuevoMiembro.value.nombre.trim()) return
  f0.value.miembros.push({ ...nuevoMiembro.value })
  nuevoMiembro.value = { nombre: '', rol: '' }
}
function removeMiembro(i) { f0.value.miembros.splice(i, 1) }

const f0Valido = computed(() =>
  f0.value.miembros.length > 0 &&
  f0.value.contrato_firmado &&
  f0.value.sintesis.every(s => s.respuesta.trim().length > 0)
)

async function guardarF0() { await guardarFase(0, { ...f0.value }) }
async function completarF0() {
  await guardarFase(0, { ...f0.value })
  await completarFase(0)
}

// ── F1: Diseño y prototipado ───────────────────────────────────────────────
const f1 = ref({
  reto_frase: '', hallazgos: ['', ''], propuesta: '',
  tipo_prototipo: '', prototipo_url: '', iteracion: 1,
})
const tiposPrototipo = ['Croquis / boceto papel', 'Storyboard / mapa visual', 'Maqueta física', 'Prototipo digital (Figma/Canva/Genially)', 'Diagrama de procesos']
const nuevoHallazgo = ref('')

function inicializarF1() {
  const datos = getFase(1).datos ?? {}
  f1.value = {
    reto_frase:     datos.reto_frase    ?? '',
    hallazgos:      datos.hallazgos     ?? ['', ''],
    propuesta:      datos.propuesta     ?? '',
    tipo_prototipo: datos.tipo_prototipo ?? '',
    prototipo_url:  datos.prototipo_url  ?? '',
    iteracion:      datos.iteracion     ?? 1,
  }
}

function addHallazgo() { if (nuevoHallazgo.value.trim()) { f1.value.hallazgos.push(nuevoHallazgo.value.trim()); nuevoHallazgo.value = '' } }
function removeHallazgo(i) { f1.value.hallazgos.splice(i, 1) }

const f1Valido = computed(() =>
  f1.value.reto_frase.trim() &&
  f1.value.hallazgos.some(h => h.trim()) &&
  f1.value.propuesta.trim() &&
  f1.value.tipo_prototipo
)

async function guardarF1() { await guardarFase(1, { ...f1.value }) }
async function completarF1() {
  await guardarFase(1, { ...f1.value })
  await completarFase(1)
}

// ── F2: Tareas de desarrollo ───────────────────────────────────────────────
const nuevaTarea = ref({ descripcion: '', responsable: '', estado: 'pendiente' })
const cargandoTarea = ref(false)

async function addTarea() {
  if (!nuevaTarea.value.descripcion.trim()) return
  cargandoTarea.value = true
  try {
    const res = await api.post(`/equipo/${token}/tareas`, nuevaTarea.value)
    workspace.value.tareas.push(res.data)
    nuevaTarea.value = { descripcion: '', responsable: '', estado: 'pendiente' }
  } finally { cargandoTarea.value = false }
}

async function cambiarEstadoTarea(tarea, estado) {
  await api.put(`/equipo/${token}/tareas/${tarea.id}`, { estado })
  tarea.estado = estado
}

async function eliminarTarea(tarea, idx) {
  await api.delete(`/equipo/${token}/tareas/${tarea.id}`)
  workspace.value.tareas.splice(idx, 1)
}

const progreso = computed(() => {
  if (!tareas.value.length) return 0
  return Math.round(tareas.value.filter(t => t.estado === 'realizado').length / tareas.value.length * 100)
})

const f2Valido = computed(() => tareas.value.length > 0 && progreso.value === 100)

async function completarF2() { await completarFase(2) }

// ── F3: Entrega ────────────────────────────────────────────────────────────
const f3 = ref({ descripcion_entregable: '', url_entregable: '' })

function inicializarF3() {
  const datos = getFase(3).datos ?? {}
  f3.value = {
    descripcion_entregable: datos.descripcion_entregable ?? '',
    url_entregable:         datos.url_entregable         ?? '',
  }
}

const f3Valido = computed(() => f3.value.descripcion_entregable.trim().length > 0)
async function guardarF3() { await guardarFase(3, { ...f3.value }) }
async function completarF3() {
  await guardarFase(3, { ...f3.value })
  await completarFase(3)
}

// ── F4: Reflexiones ────────────────────────────────────────────────────────
const PREGUNTAS_INDIVIDUAL = [
  '¿Qué has aprendido que no sabías antes?',
  '¿Cuál fue la parte más difícil del proyecto?',
  '¿Qué habilidad has practicado más durante el proyecto?',
  '¿Qué cambiarías de tu propio desempeño si repitieras el proyecto?',
  '¿Dónde crees que podrías aplicar lo aprendido en el futuro?',
]
const PREGUNTAS_GRUPAL = [
  '¿Qué ha funcionado bien como equipo?',
  '¿Qué mejoraríais en vuestra forma de trabajar?',
  '¿Qué aplicaríais en el próximo proyecto?',
]

const modoReflexion     = ref('')
const nombreAlumno      = ref('')
const respuestasIndiv   = ref(PREGUNTAS_INDIVIDUAL.map(p => ({ pregunta: p, respuesta: '' })))
const respuestasGrupal  = ref(PREGUNTAS_GRUPAL.map(p => ({ pregunta: p, respuesta: '' })))
const guardandoReflexion = ref(false)
const reflexionEnviada  = ref(false)

const reflexionGrupal = computed(() => reflexiones.value.find(r => r.tipo === 'grupal'))
const misReflexiones  = computed(() => reflexiones.value.filter(r => r.tipo === 'individual'))

async function enviarReflexion(tipo) {
  guardandoReflexion.value = true
  try {
    const res = await api.post(`/equipo/${token}/reflexiones`, {
      tipo,
      autor_nombre: tipo === 'individual' ? nombreAlumno.value : null,
      respuestas:   tipo === 'individual' ? respuestasIndiv.value : respuestasGrupal.value,
    })
    workspace.value.reflexiones.push(res.data)
    reflexionEnviada.value = true
    modoReflexion.value = ''
    mostrarOk('Reflexión guardada correctamente.')
  } finally {
    guardandoReflexion.value = false
  }
}

function abrirReflexion(tipo) {
  modoReflexion.value = tipo
  reflexionEnviada.value = false
  if (tipo === 'individual') {
    nombreAlumno.value = ''
    respuestasIndiv.value = PREGUNTAS_INDIVIDUAL.map(p => ({ pregunta: p, respuesta: '' }))
  } else {
    respuestasGrupal.value = PREGUNTAS_GRUPAL.map(p => ({ pregunta: p, respuesta: '' }))
  }
}

const f4ValidoParaCompletar = computed(() =>
  reflexiones.value.length > 0 && reflexionGrupal.value
)

async function completarF4() { await completarFase(4) }

function alCambiarFase(n) {
  faseVista.value = n
  if (n === 0) inicializarF0()
  if (n === 1) inicializarF1()
  if (n === 3) inicializarF3()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

watch(workspace, (val) => {
  if (val) {
    inicializarF0()
    inicializarF1()
    inicializarF3()
  }
}, { once: true })
</script>

<template>
  <div class="min-h-screen bg-[#F0F4F8] font-sans text-[#1F2937]"
       :class="isLoaded ? 'opacity-100' : 'opacity-0'"
       style="transition: opacity 0.4s ease">

    <!-- Cargando -->
    <div v-if="cargando" class="flex flex-col items-center justify-center min-h-screen">
      <svg class="animate-spin w-10 h-10 text-[#00A859] mb-3" viewBox="0 0 24 24" fill="none">
        <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
      </svg>
      <p class="text-[#00A859] font-black text-xs uppercase tracking-widest animate-pulse">Cargando proyecto...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex flex-col items-center justify-center min-h-screen px-6 text-center">
      <div class="w-16 h-16 rounded-3xl bg-red-50 border border-red-200 flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </div>
      <h2 class="text-lg font-black text-[#1F2937] mb-2">Enlace no válido</h2>
      <p class="text-sm text-gray-500 mb-6">Este enlace no existe o ha caducado. Comprueba el código con tu docente.</p>
      <button @click="router.push({ name: 'unirse-equipo' })"
              class="px-6 py-3 rounded-2xl bg-[#00A859] text-white text-sm font-black uppercase tracking-widest">
        Volver
      </button>
    </div>

    <template v-else-if="workspace">

      <!-- ══ CABECERA OSCURA ══ -->
      <div class="bg-[#0C1220] text-white">
        <div class="max-w-[1380px] mx-auto px-4 sm:px-8 py-4 sm:py-5
                    flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-6">
          <!-- Izquierda: info del microreto -->
          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#00A859] mb-1">
              Microreto activo
            </p>
            <h1 class="text-lg sm:text-xl font-black text-white leading-tight mb-2 sm:mb-3">
              {{ proyecto.titulo }}
            </h1>
            <div class="flex gap-1.5 sm:gap-2 flex-wrap">
              <span v-if="proyecto.empresa_nombre"
                    class="text-[10px] sm:text-[11px] font-bold bg-white/10 text-white px-2.5 py-1 rounded-full">
                {{ proyecto.empresa_nombre }}
              </span>
              <span v-if="proyecto.curso"
                    class="text-[10px] sm:text-[11px] font-bold bg-white/10 text-white px-2.5 py-1 rounded-full">
                {{ proyecto.curso }}
              </span>
            </div>
          </div>
          <!-- Derecha: nombre de equipo + miembros -->
          <div class="sm:text-right min-w-0 sm:max-w-[40%]">
            <p class="text-sm sm:text-base font-black text-[#00A859] truncate">{{ equipo.nombre }}</p>
            <p v-if="equipo.miembros?.length"
               class="text-[11px] text-gray-400 mt-0.5 leading-relaxed sm:leading-normal truncate sm:whitespace-normal">
              {{ equipo.miembros.slice(0, 4).map(m => m.nombre.split(' ')[0]).join(' · ') }}
            </p>
            <p v-if="proyecto.centro_nombre" class="text-[11px] text-gray-500 mt-0.5 truncate">
              {{ proyecto.centro_nombre }}<span v-if="proyecto.curso"> · {{ proyecto.curso }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- ══ BARRA DE FASES ══ -->
      <div class="bg-white border-b border-gray-200 sticky top-0 z-20 shadow-sm">
        <div class="max-w-[1380px] mx-auto">
          <div class="flex overflow-x-auto scrollbar-none">
            <button
              v-for="f in fasesConfig" :key="f.num"
              @click="puedeVerFase(f.num) && alCambiarFase(f.num)"
              class="flex-1 min-w-[72px] sm:min-w-[110px] flex flex-col items-center gap-0.5
                     py-3 sm:py-3.5 px-1.5 sm:px-3 border-b-2 transition-all duration-200"
              :class="[
                faseVista === f.num
                  ? 'border-[#00A859]'
                  : puedeVerFase(f.num)
                    ? 'border-transparent hover:border-gray-200 cursor-pointer'
                    : 'border-transparent cursor-default',
              ]">
              <span class="text-[9px] font-black uppercase tracking-wider"
                    :class="faseVista === f.num ? 'text-[#00A859]' : 'text-gray-400'">
                <template v-if="getFase(f.num).completada">✓ </template>
                F{{ f.num }}
              </span>
              <!-- Label completo en sm+, abreviado en móvil -->
              <span class="hidden sm:block text-[11px] font-bold leading-tight text-center"
                    :class="faseVista === f.num
                      ? 'text-[#00A859]'
                      : puedeVerFase(f.num) ? 'text-gray-600' : 'text-gray-300'">
                {{ f.label }}
              </span>
              <span class="sm:hidden text-[10px] font-bold leading-tight text-center"
                    :class="faseVista === f.num
                      ? 'text-[#00A859]'
                      : puedeVerFase(f.num) ? 'text-gray-600' : 'text-gray-300'">
                {{ f.shortLabel }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Toast éxito -->
      <Transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 -translate-y-2"
                  leave-active-class="transition-all duration-200" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="msgOk"
             class="fixed top-4 left-1/2 -translate-x-1/2 z-50 bg-[#00A859] text-white
                    px-5 py-2.5 rounded-full shadow-lg text-sm font-bold whitespace-nowrap">
          ✓ {{ msgOk }}
        </div>
      </Transition>

      <!-- ══ LAYOUT DOS COLUMNAS ══ -->
      <div class="max-w-[1380px] mx-auto px-3 sm:px-6 py-4 sm:py-6">
        <div class="flex flex-col lg:flex-row gap-4 sm:gap-6 items-start">

          <!-- ─────────────────────────────────────────────── -->
          <!--  CONTENIDO PRINCIPAL (fases)                    -->
          <!-- ─────────────────────────────────────────────── -->
          <div class="flex-1 min-w-0 space-y-4 sm:space-y-5">

            <!-- Tarjeta intro de la fase activa -->
            <div class="bg-[#F0FDF4] border border-[#00A859]/20 rounded-2xl px-5 py-4 flex gap-3 items-start">
              <span class="text-2xl mt-0.5 shrink-0">{{ fasesConfig[faseVista].icono }}</span>
              <div>
                <p class="text-sm font-black text-[#065F46] mb-0.5">{{ fasesConfig[faseVista].label }}</p>
                <p class="text-sm text-[#047857] leading-relaxed">{{ fasesConfig[faseVista].descLarga }}</p>
              </div>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 0 — Inicio del equipo                   -->
            <!-- ════════════════════════════════════════════ -->
            <div v-if="faseVista === 0" class="space-y-5">

              <!-- Validado -->
              <div v-if="getFase(0).validado_docente"
                   class="bg-green-50 border border-green-200 rounded-2xl px-4 py-3 flex items-center gap-3">
                <span class="text-green-600 text-lg">✓</span>
                <div>
                  <p class="text-sm font-bold text-green-700">Fase validada por el docente</p>
                  <p v-if="getFase(0).nota_docente" class="text-xs text-green-600">
                    Nota: {{ getFase(0).nota_docente }}/10 · {{ getFase(0).observaciones_docente }}
                  </p>
                </div>
              </div>

              <!-- BLOQUE A: Contrato de equipo -->
              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-4">A · Contrato de equipo</p>

                <div class="space-y-2 mb-4">
                  <div v-for="(m, i) in f0.miembros" :key="i"
                       class="flex items-center gap-2 bg-slate-50 rounded-xl px-3 py-2">
                    <span class="flex-1 text-sm font-bold text-[#1F2937]">{{ m.nombre }}</span>
                    <span class="text-[10px] text-slate-500 capitalize bg-white border border-slate-200 px-2 py-0.5 rounded-full">
                      {{ m.rol || 'sin rol' }}
                    </span>
                    <button @click="removeMiembro(i)" class="text-gray-300 hover:text-red-400 transition-colors text-xs font-black">✕</button>
                  </div>
                  <p v-if="!f0.miembros.length" class="text-sm text-gray-400 text-center py-3">
                    Añadid los integrantes del equipo
                  </p>
                </div>

                <div class="flex gap-2 flex-wrap">
                  <input v-model="nuevoMiembro.nombre" type="text" placeholder="Nombre del alumno/a"
                         class="flex-1 min-w-32 text-sm border border-gray-200 rounded-xl px-3 py-2
                                focus:outline-none focus:border-slate-400 bg-gray-50"
                         @keydown.enter="addMiembro" />
                  <select v-model="nuevoMiembro.rol"
                          class="text-sm border border-gray-200 rounded-xl px-3 py-2 bg-gray-50
                                 focus:outline-none focus:border-slate-400 capitalize">
                    <option value="">— Rol —</option>
                    <option v-for="r in rolesOpciones" :key="r" :value="r" class="capitalize">{{ r }}</option>
                  </select>
                  <button @click="addMiembro"
                          class="px-4 py-2 rounded-xl bg-slate-600 text-white text-xs font-black uppercase tracking-wider">
                    + Añadir
                  </button>
                </div>

                <label class="mt-4 flex items-center gap-3 cursor-pointer group">
                  <input v-model="f0.contrato_firmado" type="checkbox"
                         class="w-5 h-5 rounded-lg accent-slate-600 cursor-pointer" />
                  <span class="text-sm font-semibold text-[#1F2937] group-hover:text-slate-600 transition-colors">
                    El equipo acepta colaborar con respeto y responsabilidad (contrato de equipo)
                  </span>
                </label>
              </div>

              <!-- BLOQUE B: Diagnóstico de empresa -->
              <div v-if="diagnostico" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-blue-500 mb-4">B · Diagnóstico de la empresa</p>
                <p class="text-[11px] text-gray-400 mb-4">Información del reto que os ha propuesto la empresa. Leedla con atención antes de responder las preguntas.</p>

                <div class="space-y-3">
                  <div v-if="diagnostico.quien_es" class="bg-blue-50 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">¿Quiénes son?</p>
                    <p class="text-sm text-[#1F2937] leading-relaxed">{{ diagnostico.quien_es }}</p>
                  </div>
                  <div v-if="diagnostico.dia_a_dia" class="bg-blue-50 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">Su día a día</p>
                    <p class="text-sm text-[#1F2937] leading-relaxed">{{ diagnostico.dia_a_dia }}</p>
                  </div>
                  <div v-if="diagnostico.pregunta_reto" class="bg-slate-800 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-[#99CC33] mb-1">El reto que os proponen</p>
                    <p class="text-sm font-bold text-white leading-relaxed">{{ diagnostico.pregunta_reto }}</p>
                  </div>
                  <div v-if="diagnostico.que_necesitan?.length" class="bg-blue-50 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-400 mb-1">Qué necesitan</p>
                    <ul class="space-y-1">
                      <li v-for="(n, i) in diagnostico.que_necesitan" :key="i" class="text-sm text-[#1F2937] flex gap-2">
                        <span class="text-blue-400 shrink-0">·</span>{{ n }}
                      </li>
                    </ul>
                  </div>
                  <div v-if="diagnostico.dificultades?.length" class="bg-orange-50 rounded-xl px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-orange-400 mb-1">Dificultades</p>
                    <ul class="space-y-1">
                      <li v-for="(d, i) in diagnostico.dificultades" :key="i" class="text-sm text-[#1F2937] flex gap-2">
                        <span class="text-orange-400 shrink-0">·</span>{{ d }}
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- BLOQUE C: Síntesis del equipo -->
              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">C · Síntesis del equipo</p>
                <p class="text-[11px] text-gray-400 mb-4">Responded juntos a estas preguntas sobre el reto de la empresa.</p>

                <div class="space-y-4">
                  <div v-for="(item, i) in f0.sintesis" :key="i">
                    <label class="block text-xs font-bold text-[#1F2937] mb-1.5">
                      {{ i + 1 }}. {{ item.pregunta }}
                    </label>
                    <textarea v-model="item.respuesta" rows="2"
                              class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                     focus:outline-none focus:border-slate-400 bg-gray-50 resize-none"
                              placeholder="Respuesta del equipo..."/>
                  </div>
                </div>
              </div>

              <!-- Acciones F0 -->
              <div class="flex gap-3 flex-wrap">
                <button @click="guardarF0" :disabled="guardando"
                        class="flex-1 py-3 rounded-2xl bg-slate-100 border border-slate-200
                               text-slate-700 text-sm font-black uppercase tracking-wider
                               hover:bg-slate-200 transition-all disabled:opacity-50">
                  Guardar borrador
                </button>
                <button @click="completarF0" :disabled="!f0Valido || guardando"
                        :class="['flex-1 py-3 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                                 f0Valido ? 'bg-slate-600 text-white hover:bg-slate-700' : 'bg-gray-100 text-gray-300 cursor-not-allowed']">
                  Completar fase ✓
                </button>
              </div>
              <p v-if="!f0Valido" class="text-xs text-gray-400 text-center -mt-2">
                Añadid al menos un miembro, aceptad el contrato y responded todas las preguntas.
              </p>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 1 — Análisis del reto                   -->
            <!-- ════════════════════════════════════════════ -->
            <div v-else-if="faseVista === 1" class="space-y-5">

              <div v-if="getFase(1).validado_docente"
                   class="bg-green-50 border border-green-200 rounded-2xl px-4 py-3 flex items-center gap-3">
                <span class="text-green-600 text-lg">✓</span>
                <div>
                  <p class="text-sm font-bold text-green-700">Fase validada por el docente</p>
                  <p v-if="getFase(1).observaciones_docente" class="text-xs text-green-600">{{ getFase(1).observaciones_docente }}</p>
                </div>
              </div>
              <div v-else-if="getFase(1).datos && !getFase(1).validado_docente && getFase(1).completada"
                   class="bg-blue-50 border border-blue-200 rounded-2xl px-4 py-3">
                <p class="text-sm font-bold text-blue-700">Enviado al docente para validación. Espera su respuesta.</p>
              </div>

              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 space-y-5">

                <div>
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    El reto en una frase <span class="text-red-400">*</span>
                  </label>
                  <p class="text-[11px] text-gray-400 mb-2">Responde a la pregunta "¿Cómo podríamos...?" del reto de la empresa.</p>
                  <input v-model="f1.reto_frase" type="text"
                         placeholder="¿Cómo podríamos...?"
                         class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5
                                focus:outline-none focus:border-blue-400 bg-gray-50 font-semibold"/>
                </div>

                <div>
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    Hallazgos clave <span class="text-red-400">*</span>
                  </label>
                  <p class="text-[11px] text-gray-400 mb-2">2-4 datos o conclusiones del análisis que justifican vuestra propuesta.</p>
                  <div class="space-y-2 mb-2">
                    <div v-for="(h, i) in f1.hallazgos" :key="i" class="flex gap-2 items-center">
                      <span class="text-blue-400 font-black text-sm shrink-0">{{ i + 1 }}.</span>
                      <input v-model="f1.hallazgos[i]" type="text" :placeholder="`Hallazgo ${i + 1}`"
                             class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                    focus:outline-none focus:border-blue-400 bg-gray-50"/>
                      <button @click="removeHallazgo(i)" class="text-gray-300 hover:text-red-400 font-black text-xs">✕</button>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <input v-model="nuevoHallazgo" type="text" placeholder="Añadir hallazgo..."
                           class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                  focus:outline-none focus:border-blue-400 bg-gray-50"
                           @keydown.enter="addHallazgo"/>
                    <button @click="addHallazgo"
                            class="px-4 py-2 rounded-xl bg-blue-500 text-white text-xs font-black">+ Añadir</button>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    Propuesta de solución <span class="text-red-400">*</span>
                  </label>
                  <textarea v-model="f1.propuesta" rows="3"
                            placeholder="Describid vuestra solución y en qué se diferencia de lo que existe..."
                            class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                   focus:outline-none focus:border-blue-400 bg-gray-50 resize-none"/>
                </div>

                <div>
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-2">
                    Tipo de prototipo <span class="text-red-400">*</span>
                  </label>
                  <div class="grid grid-cols-1 gap-2">
                    <label v-for="tipo in tiposPrototipo" :key="tipo"
                           class="flex items-center gap-3 cursor-pointer p-3 rounded-xl border transition-all"
                           :class="f1.tipo_prototipo === tipo ? 'border-blue-400 bg-blue-50' : 'border-gray-100 bg-gray-50 hover:border-blue-200'">
                      <input type="radio" v-model="f1.tipo_prototipo" :value="tipo" class="accent-blue-500"/>
                      <span class="text-sm font-semibold text-[#1F2937]">{{ tipo }}</span>
                    </label>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    Enlace al prototipo
                  </label>
                  <input v-model="f1.prototipo_url" type="url"
                         placeholder="https://figma.com/... o https://drive.google.com/..."
                         class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                focus:outline-none focus:border-blue-400 bg-gray-50"/>
                  <p class="text-[10px] text-gray-400 mt-1">Pegad el enlace a vuestro prototipo digital, o dejad vacío si es físico.</p>
                </div>

                <div class="flex items-center gap-4">
                  <label class="text-xs font-black text-[#1F2937] uppercase tracking-wider">Iteración</label>
                  <div class="flex items-center gap-2">
                    <button @click="f1.iteracion = Math.max(1, f1.iteracion - 1)"
                            class="w-8 h-8 rounded-lg bg-gray-100 border border-gray-200 font-black text-gray-500 hover:bg-gray-200 transition-all">−</button>
                    <span class="text-lg font-black text-[#1F2937] w-8 text-center">{{ f1.iteracion }}</span>
                    <button @click="f1.iteracion++"
                            class="w-8 h-8 rounded-lg bg-gray-100 border border-gray-200 font-black text-gray-500 hover:bg-gray-200 transition-all">+</button>
                  </div>
                  <p class="text-xs text-gray-400">¿Es la primera versión o habéis mejorado el prototipo?</p>
                </div>
              </div>

              <div class="flex gap-3">
                <button @click="guardarF1" :disabled="guardando"
                        class="flex-1 py-3 rounded-2xl bg-blue-50 border border-blue-200 text-blue-700
                               text-sm font-black uppercase tracking-wider hover:bg-blue-100 transition-all disabled:opacity-50">
                  Guardar
                </button>
                <button @click="completarF1" :disabled="!f1Valido || guardando"
                        :class="['flex-1 py-3 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                                 f1Valido ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-gray-100 text-gray-300 cursor-not-allowed']">
                  Enviar para validación →
                </button>
              </div>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 2 — Diseño de solución (tareas)         -->
            <!-- ════════════════════════════════════════════ -->
            <div v-else-if="faseVista === 2" class="space-y-5">

              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 sm:px-5 py-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs font-black text-[#1F2937] uppercase tracking-wider">Progreso</span>
                  <span class="text-sm font-black text-amber-600">{{ progreso }}%</span>
                </div>
                <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full transition-all duration-500"
                       :style="{ width: progreso + '%' }"/>
                </div>
                <p class="text-[10px] text-gray-400 mt-2">
                  {{ tareas.filter(t => t.estado === 'realizado').length }} de {{ tareas.length }} tareas realizadas
                </p>
              </div>

              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-amber-500 mb-4">Tareas del equipo</p>

                <div class="space-y-2 mb-4">
                  <div v-for="(t, i) in tareas" :key="t.id"
                       class="flex items-center gap-3 p-3 rounded-xl border transition-all"
                       :class="{
                         'border-green-200 bg-green-50': t.estado === 'realizado',
                         'border-amber-200 bg-amber-50': t.estado === 'en_progreso',
                         'border-gray-100 bg-gray-50':   t.estado === 'pendiente',
                       }">
                    <select :value="t.estado" @change="cambiarEstadoTarea(t, $event.target.value)"
                            class="text-[10px] font-black uppercase tracking-wider border-0 bg-transparent
                                   focus:outline-none cursor-pointer"
                            :class="{
                              'text-green-600': t.estado === 'realizado',
                              'text-amber-600': t.estado === 'en_progreso',
                              'text-gray-400':  t.estado === 'pendiente',
                            }">
                      <option value="pendiente">⬜ Pendiente</option>
                      <option value="en_progreso">🔄 En progreso</option>
                      <option value="realizado">✅ Realizado</option>
                    </select>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-semibold text-[#1F2937] leading-snug"
                         :class="{ 'line-through text-gray-400': t.estado === 'realizado' }">
                        {{ t.descripcion }}
                      </p>
                      <p v-if="t.responsable" class="text-[10px] text-gray-400 mt-0.5">→ {{ t.responsable }}</p>
                    </div>
                    <button @click="eliminarTarea(t, i)" class="text-gray-200 hover:text-red-400 transition-colors font-black text-xs shrink-0">✕</button>
                  </div>
                  <p v-if="!tareas.length" class="text-sm text-gray-400 text-center py-6">
                    Añadid las tareas que necesitáis completar
                  </p>
                </div>

                <div class="border-t border-gray-100 pt-4 space-y-2">
                  <div class="flex gap-2">
                    <input v-model="nuevaTarea.descripcion" type="text" placeholder="Descripción de la tarea"
                           class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                  focus:outline-none focus:border-amber-400 bg-gray-50"
                           @keydown.enter="addTarea"/>
                  </div>
                  <div class="flex gap-2">
                    <input v-model="nuevaTarea.responsable" type="text" placeholder="Responsable (nombre)"
                           class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2
                                  focus:outline-none focus:border-amber-400 bg-gray-50"/>
                    <button @click="addTarea" :disabled="!nuevaTarea.descripcion.trim() || cargandoTarea"
                            class="px-4 py-2 rounded-xl bg-amber-500 text-white text-xs font-black
                                   uppercase tracking-wider disabled:opacity-50 hover:bg-amber-600 transition-all">
                      + Tarea
                    </button>
                  </div>
                </div>
              </div>

              <button @click="completarF2" :disabled="!f2Valido || guardando"
                      :class="['w-full py-3.5 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                               f2Valido ? 'bg-amber-500 text-white hover:bg-amber-600' : 'bg-gray-100 text-gray-300 cursor-not-allowed']">
                {{ f2Valido ? 'Todas las tareas completadas — Ir a Desarrollo →' : 'Completad todas las tareas para continuar' }}
              </button>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 3 — Desarrollo (entrega)               -->
            <!-- ════════════════════════════════════════════ -->
            <div v-else-if="faseVista === 3" class="space-y-5">

              <div v-if="getFase(3).validado_docente"
                   class="bg-green-50 border border-green-200 rounded-2xl p-4">
                <p class="text-sm font-bold text-green-700">✓ Entrega validada por el docente</p>
                <p v-if="getFase(3).nota_docente" class="text-xs text-green-600 mt-1">
                  Nota: <strong>{{ getFase(3).nota_docente }}/10</strong>
                  <span v-if="getFase(3).observaciones_docente"> · {{ getFase(3).observaciones_docente }}</span>
                </p>
              </div>
              <div v-else-if="getFase(3).completada"
                   class="bg-orange-50 border border-orange-200 rounded-2xl px-4 py-3">
                <p class="text-sm font-bold text-orange-700">Entrega enviada — esperando validación del docente</p>
              </div>

              <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 space-y-4">
                <div>
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    Descripción del entregable <span class="text-red-400">*</span>
                  </label>
                  <textarea v-model="f3.descripcion_entregable" rows="4"
                            placeholder="Describid brevemente qué entregáis, cómo funciona y qué problemas resuelve..."
                            class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5
                                   focus:outline-none focus:border-orange-400 bg-gray-50 resize-none"/>
                </div>
                <div>
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">
                    Enlace al entregable final
                  </label>
                  <input v-model="f3.url_entregable" type="url"
                         placeholder="https://drive.google.com/... o https://github.com/..."
                         class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                focus:outline-none focus:border-orange-400 bg-gray-50"/>
                  <p class="text-[10px] text-gray-400 mt-1">
                    Enlace a Drive, GitHub, Figma, Canva… donde esté el trabajo final.
                  </p>
                </div>
              </div>

              <div class="flex gap-3">
                <button @click="guardarF3" :disabled="guardando"
                        class="flex-1 py-3 rounded-2xl bg-orange-50 border border-orange-200
                               text-orange-700 text-sm font-black uppercase tracking-wider
                               hover:bg-orange-100 transition-all disabled:opacity-50">
                  Guardar
                </button>
                <button @click="completarF3" :disabled="!f3Valido || guardando"
                        :class="['flex-1 py-3 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                                 f3Valido ? 'bg-orange-500 text-white hover:bg-orange-600' : 'bg-gray-100 text-gray-300 cursor-not-allowed']">
                  Enviar entrega →
                </button>
              </div>
            </div>

            <!-- ════════════════════════════════════════════ -->
            <!-- FASE 4 — Presentación y reflexión            -->
            <!-- ════════════════════════════════════════════ -->
            <div v-else-if="faseVista === 4" class="space-y-5">

              <!-- Formulario de reflexión activo -->
              <div v-if="modoReflexion" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 space-y-4">
                <div class="flex items-center justify-between">
                  <p class="text-sm font-black text-[#121212]">
                    {{ modoReflexion === 'individual' ? '👤 Reflexión individual' : '👥 Reflexión grupal' }}
                  </p>
                  <button @click="modoReflexion = ''"
                          class="text-gray-300 hover:text-gray-500 font-black text-sm">✕</button>
                </div>

                <div v-if="modoReflexion === 'individual'">
                  <label class="block text-xs font-black text-[#1F2937] uppercase tracking-wider mb-1.5">Tu nombre <span class="text-red-400">*</span></label>
                  <input v-model="nombreAlumno" type="text" placeholder="Escribe tu nombre"
                         class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                focus:outline-none focus:border-green-400 bg-gray-50"/>
                </div>

                <div class="space-y-4">
                  <div v-for="(item, i) in (modoReflexion === 'individual' ? respuestasIndiv : respuestasGrupal)" :key="i">
                    <label class="block text-xs font-bold text-[#1F2937] mb-1.5">
                      {{ i + 1 }}. {{ item.pregunta }}
                    </label>
                    <textarea v-model="item.respuesta" rows="2"
                              class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2
                                     focus:outline-none focus:border-green-400 bg-gray-50 resize-none"
                              placeholder="Tu reflexión..."/>
                  </div>
                </div>

                <button @click="enviarReflexion(modoReflexion)"
                        :disabled="guardandoReflexion || (modoReflexion === 'individual' && !nombreAlumno.trim())"
                        class="w-full py-3.5 rounded-2xl bg-[#00A859] text-white text-sm font-black
                               uppercase tracking-widest hover:bg-[#00A859]/90 transition-all disabled:opacity-50">
                  Enviar reflexión →
                </button>
              </div>

              <template v-else>
                <!-- Reflexión grupal -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                  <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-3">Reflexión grupal</p>
                  <div v-if="reflexionGrupal">
                    <div v-for="r in reflexionGrupal.respuestas" :key="r.pregunta" class="mb-3 last:mb-0">
                      <p class="text-[11px] font-bold text-gray-500 mb-0.5">{{ r.pregunta }}</p>
                      <p class="text-sm text-[#1F2937]">{{ r.respuesta }}</p>
                    </div>
                  </div>
                  <button v-else @click="abrirReflexion('grupal')"
                          class="w-full py-3 rounded-2xl border-2 border-dashed border-green-200
                                 text-sm font-black text-[#00A859] hover:bg-green-50 transition-all">
                    + Añadir reflexión grupal (portavoz)
                  </button>
                </div>

                <!-- Reflexiones individuales -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                  <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-3">
                    Reflexiones individuales ({{ misReflexiones.length }})
                  </p>
                  <div v-if="misReflexiones.length" class="space-y-3 mb-3">
                    <div v-for="r in misReflexiones" :key="r.id"
                         class="bg-green-50 rounded-xl px-4 py-3">
                      <p class="text-xs font-black text-[#00A859] mb-1.5">{{ r.autor_nombre }}</p>
                      <div v-for="resp in r.respuestas.slice(0,2)" :key="resp.pregunta" class="mb-1.5 last:mb-0">
                        <p class="text-[10px] text-gray-400">{{ resp.pregunta }}</p>
                        <p class="text-xs text-[#1F2937]">{{ resp.respuesta }}</p>
                      </div>
                    </div>
                  </div>
                  <button @click="abrirReflexion('individual')"
                          class="w-full py-3 rounded-2xl border-2 border-dashed border-green-200
                                 text-sm font-black text-[#00A859] hover:bg-green-50 transition-all">
                    + Añadir mi reflexión individual
                  </button>
                </div>

                <!-- Info evaluación docente -->
                <div v-if="getFase(4).validado_docente" class="bg-green-50 border border-green-200 rounded-2xl p-4">
                  <p class="text-sm font-bold text-green-700 mb-1">✓ Evaluación del docente recibida</p>
                  <p v-if="getFase(4).nota_docente" class="text-xs text-green-600">
                    Nota del proyecto: <strong>{{ getFase(4).nota_docente }}/10</strong>
                  </p>
                  <p v-if="getFase(4).observaciones_docente" class="text-xs text-green-600 mt-1">
                    {{ getFase(4).observaciones_docente }}
                  </p>
                </div>
                <div v-else class="bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3">
                  <p class="text-xs text-gray-400 text-center">
                    La evaluación curricular (RA + niveles + nota) la realizará el docente desde su panel.
                  </p>
                </div>

                <button @click="completarF4" :disabled="!f4ValidoParaCompletar || guardando"
                        :class="['w-full py-3.5 rounded-2xl text-sm font-black uppercase tracking-wider transition-all',
                                 f4ValidoParaCompletar ? 'bg-[#00A859] text-white hover:bg-[#00A859]/90' : 'bg-gray-100 text-gray-300 cursor-not-allowed']">
                  {{ f4ValidoParaCompletar ? '✓ Marcar proyecto como completado' : 'Añadid la reflexión grupal para cerrar' }}
                </button>
              </template>
            </div>

          </div><!-- /main content -->

          <!-- ─────────────────────────────────────────────── -->
          <!--  SIDEBAR DERECHO                                -->
          <!-- ─────────────────────────────────────────────── -->
          <!-- Separador visual del sidebar solo en móvil -->
          <aside class="w-full lg:w-72 xl:w-80 shrink-0 space-y-4 lg:sticky lg:top-[62px]
                         border-t border-gray-200 pt-4 lg:border-t-0 lg:pt-0">

            <!-- Tarjeta equipo + miembros -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
              <h3 class="text-sm font-black text-[#121212] mb-4">{{ equipo.nombre }}</h3>

              <div v-if="equipo.miembros?.length" class="space-y-3">
                <div v-for="(m, i) in equipo.miembros" :key="i" class="flex items-center gap-3">
                  <div :style="{ backgroundColor: avatarColor(i) }"
                       class="w-9 h-9 rounded-full flex items-center justify-center
                              text-white font-black text-sm shrink-0 select-none">
                    {{ initials(m.nombre) }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-[#1F2937] truncate">{{ m.nombre }}</p>
                    <p class="text-[11px] text-gray-400 capitalize">{{ m.rol || 'Miembro' }}</p>
                  </div>
                </div>
              </div>
              <p v-else class="text-xs text-gray-400 text-center py-2 leading-relaxed">
                Los miembros aparecerán aquí al completar la fase de inicio.
              </p>

              <!-- Centro y docente -->
              <div v-if="proyecto.centro_nombre || proyecto.docente_nombre"
                   class="mt-4 pt-4 border-t border-gray-100 space-y-1">
                <p v-if="proyecto.centro_nombre" class="text-[11px] text-gray-600 font-semibold">
                  {{ proyecto.centro_nombre }}
                  <span v-if="proyecto.curso" class="font-normal text-gray-400"> · {{ proyecto.curso }}</span>
                </p>
                <p v-if="proyecto.docente_nombre" class="text-[11px] text-gray-400">
                  Docente: {{ proyecto.docente_nombre }}
                </p>
              </div>
            </div>

            <!-- Tarjeta progreso general -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
              <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4">
                Progreso general
              </h3>
              <div class="space-y-3">
                <div v-for="f in fasesConfig" :key="f.num" class="flex items-center gap-3">
                  <span class="text-[10px] font-bold text-gray-500 w-[70px] shrink-0 leading-snug">
                    F{{ f.num }} {{ f.shortLabel }}
                  </span>
                  <template v-if="pctFase(f.num) !== null">
                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                      <div class="h-full rounded-full transition-all duration-700"
                           :class="pctFase(f.num) === 100 ? 'bg-[#00A859]' : 'bg-amber-400'"
                           :style="{ width: pctFase(f.num) + '%' }"/>
                    </div>
                    <span class="text-[11px] font-black w-9 text-right shrink-0"
                          :class="pctFase(f.num) === 100 ? 'text-[#00A859]' : 'text-amber-500'">
                      {{ pctFase(f.num) }}%
                    </span>
                  </template>
                  <span v-else class="text-gray-300 text-sm flex-1">—</span>
                </div>
              </div>
            </div>

            <!-- Tarjeta empresa / startup day -->
            <div v-if="proyecto.empresa_nombre" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
              <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">
                Startup Day
              </h3>
              <p class="text-sm text-gray-500 leading-relaxed">
                Empresa validadora:
                <strong class="text-[#1F2937]">{{ proyecto.empresa_nombre }}</strong>
              </p>
            </div>

          </aside><!-- /sidebar -->

        </div>
      </div>

    </template>
  </div>
</template>
