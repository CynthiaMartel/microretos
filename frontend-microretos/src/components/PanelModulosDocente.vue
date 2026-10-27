<!-- «Módulos que imparte» un docente: una sola lógica para todas las pantallas.
     - Sin `usuario`: los del docente con sesión (GET/PUT /perfil/modulos) — «Mi usuario» y el
       aviso del panel docente (AvisoModulosDocente).
     - Con `usuario` ({ id, name, role_label, centro_nombre }): los de ese docente
       (GET/PUT /admin/usuarios/{id}/modulos, autorizado por la Policy) — Gestión de usuarios.

     Un único concepto: sirven para colaborar (compañeros del centro que compartan familia y
     módulo con un proyecto pueden invitarse a sus encuentros) y NO limitan los módulos que se
     pueden usar en los proyectos. Los módulos de sus proyectos solo son sugerencia: se premarcan
     si no tiene ninguno guardado y llevan un punto en el editor.

     Props: `cabecera` (avatar, nombre, rol y centro), `ficha` (cifras y desplegables por familia),
     `explicacion`, `editable` (editor + guardar; sin él, solo lectura).
     Emite `guardado` con los ids guardados. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import { useRoleTheme } from '../composables/useRoleTheme.js'
import SelectorModulosDocente from './SelectorModulosDocente.vue'
import {
  getMisModulos, guardarMisModulos, getModulosDocente, guardarModulosDocente,
} from '../services/modulosDocenteService.js'

const props = defineProps({
  usuario:     { type: Object,  default: null },
  cabecera:    { type: Boolean, default: true },
  ficha:       { type: Boolean, default: true },
  explicacion: { type: Boolean, default: true },
  editable:    { type: Boolean, default: true },
})
const emit = defineEmits(['guardado'])

const authStore = useAuthStore()
const { theme } = useRoleTheme()

// Propio (Mi usuario, aviso) o de otro docente (admin): cambian endpoint y textos
const propio = computed(() => !props.usuario)
const cargar = () => propio.value ? getMisModulos() : getModulosDocente(props.usuario.id)
const enviar = (ids) => propio.value ? guardarMisModulos(ids) : guardarModulosDocente(props.usuario.id, ids)
const t = computed(() => propio.value
  ? { titulo: 'Módulos que imparto', sus: 'Tus', tiene: 'tienes', le: 'te', puede: 'puedes', su: 'tu' }
  : { titulo: 'Módulos que imparte', sus: 'Sus', tiene: 'tiene', le: 'le', puede: 'puede', su: 'su' })

const nombre    = computed(() => propio.value ? authStore.userName : props.usuario.name)
const rolCentro = computed(() => {
  const rol    = propio.value ? authStore.roleLabel : props.usuario.role_label
  const centro = propio.value ? authStore.userCentroNombre : props.usuario.centro_nombre
  return [rol, centro].filter(Boolean).join(' · ')
})
const inicial = computed(() => (nombre.value || '?').trim().charAt(0).toUpperCase())

const oferta       = ref([])
const seleccion    = ref([])
const guardados    = ref([])
const enProyectos  = ref([])    // ids de la oferta que usa en sus propios proyectos
const numProyectos = ref(0)
const premarcados  = ref(false) // sin nada guardado: se premarcan los de sus proyectos
const cargando     = ref(true)
const guardando    = ref(false)
const msg          = ref('')
const msgOk        = ref(false)

const cambiados = computed(() =>
  [...seleccion.value].sort().join() !== [...guardados.value].sort().join())

// La ficha refleja la selección actual (lo que se guardará); si difiere de lo guardado se
// avisa de que hay cambios pendientes, para que cifras y marcas no se contradigan
const misCiclos = computed(() => oferta.value
  .map(c => ({ ...c, modulos: c.modulos.filter(m => seleccion.value.includes(m.id)) }))
  .filter(c => c.modulos.length))
const numFamilias = computed(() => new Set(misCiclos.value.map(c => c.familia).filter(Boolean)).size)

// Familia → ciclos → cursos, para los desplegables de la ficha
const agruparPorCurso = (modulos) => {
  const grupos = new Map()
  for (const m of modulos) {
    const curso = m.curso ? `${m.curso}º curso` : 'Sin curso'
    if (!grupos.has(curso)) grupos.set(curso, [])
    grupos.get(curso).push(m)
  }
  return [...grupos.entries()]
}
const misFamilias = computed(() => {
  const familias = new Map()
  for (const c of misCiclos.value) {
    const nombreFamilia = c.familia || 'Sin familia'
    if (!familias.has(nombreFamilia)) familias.set(nombreFamilia, { nombre: nombreFamilia, total: 0, ciclos: [] })
    const f = familias.get(nombreFamilia)
    f.total += c.modulos.length
    f.ciclos.push({ id: c.id, nombre: c.nombre, cursos: agruparPorCurso(c.modulos) })
  }
  return [...familias.values()]
})

// Sugerencia: módulos de sus proyectos que aún no tiene marcados
const sinMarcar = computed(() => enProyectos.value.filter(id => !seleccion.value.includes(id)))
function marcarLosDeProyectos() {
  seleccion.value = [...new Set([...seleccion.value, ...enProyectos.value])]
}

onMounted(async () => {
  try {
    const data = await cargar()
    oferta.value       = data.oferta
    guardados.value    = data.asignados
    enProyectos.value  = data.en_proyectos ?? []
    numProyectos.value = data.num_proyectos ?? 0
    // Solo al editar; no se guarda nada hasta pulsar «Guardar módulos»
    premarcados.value = props.editable && !data.asignados.length && enProyectos.value.length > 0
    seleccion.value   = premarcados.value ? [...enProyectos.value] : data.asignados
  } catch {
    msg.value = 'No se pudieron cargar los módulos.'
  } finally {
    cargando.value = false
  }
})

async function guardar() {
  guardando.value = true
  msg.value = ''
  try {
    const data = await enviar(seleccion.value)
    seleccion.value = data.asignados
    guardados.value = data.asignados
    premarcados.value = false
    if (propio.value) authStore.tieneModulos = data.asignados.length > 0
    msg.value   = 'Módulos guardados.'
    msgOk.value = true
    emit('guardado', data.asignados)
  } catch (e) {
    msg.value   = e.response?.data?.message || 'No se pudieron guardar los módulos.'
    msgOk.value = false
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div class="space-y-5">

    <!-- Cabecera -->
    <div v-if="cabecera" class="flex min-w-0 items-center gap-3">
      <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rol-fuerte text-lg font-black text-white"
            aria-hidden="true">{{ inicial }}</span>
      <div class="min-w-0">
        <p class="truncate text-base font-black text-[#1F2937]">{{ nombre }}</p>
        <p v-if="rolCentro" class="truncate text-xs text-gray-500">{{ rolCentro }}</p>
      </div>
    </div>

    <!-- Cifras -->
    <div v-if="ficha" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
      <div v-for="dato in [
             { n: seleccion.length,  t: propio ? 'Imparto' : 'Imparte' },
             { n: misCiclos.length,  t: misCiclos.length === 1 ? 'Ciclo' : 'Ciclos' },
             { n: numFamilias,       t: numFamilias === 1 ? 'Familia' : 'Familias' },
             { n: numProyectos,      t: numProyectos === 1 ? 'Proyecto' : 'Proyectos' },
           ]" :key="dato.t"
           class="min-w-0 rounded-xl border px-2 py-3 text-center" :class="[theme.bg5, theme.border15]">
        <p class="text-xl font-black sm:text-2xl" :class="theme.text">{{ cargando ? '–' : dato.n }}</p>
        <p class="truncate text-[10px] font-black uppercase tracking-widest text-gray-500">{{ dato.t }}</p>
      </div>
    </div>

    <!-- Qué son -->
    <div v-if="explicacion">
      <h2 class="text-sm font-black uppercase tracking-widest text-[#1F2937]">{{ t.titulo }}</h2>
      <p class="mt-1 text-xs text-gray-500 leading-relaxed">
        Sirven para colaborar: solo los compañeros del centro que compartan familia y algún módulo
        con un proyecto pueden ser invitados a ver sus encuentros, y al revés.
        <strong class="font-semibold text-gray-600">No limitan los módulos que {{ t.puede }} usar en {{ t.su }}s proyectos</strong>:
        ahí está siempre toda la oferta del centro.
      </p>
    </div>

    <div v-if="cargando" class="h-24 bg-gray-100 rounded-xl animate-pulse"></div>
    <template v-else>
      <!-- Cambios pendientes / sin módulos -->
      <p v-if="editable && cambiados" class="rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
        <template v-if="premarcados">
          Aún no {{ t.tiene }} módulos guardados: {{ t.le }} hemos premarcado los <strong>{{ enProyectos.length }}</strong>
          que {{ propio ? 'usas' : 'usa' }} en {{ t.su }}s proyectos.
        </template>
        <template v-else>Hay cambios sin guardar.</template>
        Revísalos y pulsa «Guardar módulos»; hasta entonces no cuentan para colaborar.
      </p>
      <p v-else-if="!seleccion.length && !msg" class="rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
        {{ propio ? 'No tienes' : 'Aún no tiene' }} módulos marcados, así que no {{ propio ? 'pueden invitarte' : 'pueden invitarle' }} a colaborar.
        <template v-if="editable">Elígelos abajo.</template>
        <template v-else-if="enProyectos.length">
          {{ t.sus }} proyectos usan {{ enProyectos.length }} módulo{{ enProyectos.length === 1 ? '' : 's' }} de la oferta del centro.
        </template>
      </p>

      <!-- Ficha: desplegable por familia; dentro, sus ciclos y cursos. Abierta si solo hay una -->
      <div v-if="ficha && misFamilias.length" class="space-y-2">
        <details v-for="f in misFamilias" :key="f.nombre" :open="misFamilias.length === 1"
                 class="group rounded-xl border border-gray-200">
          <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 py-2
                          [&::-webkit-details-marker]:hidden">
            <span class="min-w-0 text-xs font-black text-[#1F2937] break-words">{{ f.nombre }}</span>
            <span class="flex shrink-0 items-center gap-2">
              <span class="text-[11px] font-semibold" :class="theme.text">
                {{ f.total }} módulo{{ f.total === 1 ? '' : 's' }}
              </span>
              <svg class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor"
                   viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </span>
          </summary>
          <div class="grid grid-cols-1 gap-2 px-3 pb-3 sm:grid-cols-2">
            <div v-for="c in f.ciclos" :key="c.id" class="min-w-0 rounded-xl bg-gray-50 px-3 py-2.5">
              <p class="text-xs font-black text-[#1F2937] break-words">{{ c.nombre }}</p>
              <div v-for="[curso, modulos] in c.cursos" :key="curso" class="mt-1.5">
                <p class="text-[10px] font-black uppercase tracking-widest" :class="theme.text">{{ curso }}</p>
                <ul class="mt-0.5 space-y-0.5">
                  <li v-for="m in modulos" :key="m.id" class="text-[11px] text-gray-600 break-words">{{ m.nombre }}</li>
                </ul>
              </div>
            </div>
          </div>
        </details>
      </div>

      <template v-if="editable">
        <!-- Sugerencia desde sus proyectos -->
        <div v-if="sinMarcar.length"
             class="flex flex-col gap-2 rounded-xl border px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
             :class="[theme.bg5, theme.border20]">
          <p class="text-xs text-gray-600">
            {{ t.sus }} proyectos usan <strong>{{ sinMarcar.length }}</strong> módulo{{ sinMarcar.length === 1 ? '' : 's' }}
            que no {{ t.tiene }} marcado{{ sinMarcar.length === 1 ? '' : 's' }}.
          </p>
          <button type="button" @click="marcarLosDeProyectos" :disabled="guardando"
                  class="inline-flex min-h-9 shrink-0 items-center self-start rounded-full bg-rol-fuerte px-4 py-1.5 text-[11px] font-black text-white
                         transition-all hover:bg-rol-fuerte/90 disabled:opacity-40 sm:self-auto">
            Marcar{{ sinMarcar.length === 1 ? 'lo' : 'los' }}
          </button>
        </div>

        <!-- Editor -->
        <div v-if="ficha" class="flex items-center gap-3">
          <div class="flex-1 h-px bg-gray-100"></div>
          <span class="text-[10px] font-black uppercase tracking-widest text-gray-300">Editar módulos</span>
          <div class="flex-1 h-px bg-gray-100"></div>
        </div>
        <SelectorModulosDocente v-model="seleccion" :oferta="oferta" :disabled="guardando"
                                :destacados="enProyectos" plegable :resumen="false" />
      </template>
    </template>

    <p v-if="msg" class="text-xs px-3 py-2 rounded-xl border"
       :class="msgOk ? [theme.text, theme.bg5, theme.border20] : 'text-red-500 bg-red-50 border-red-200'">
      {{ msg }}
    </p>
    <div v-if="editable" class="flex justify-end">
      <button type="button" @click="guardar" :disabled="cargando || guardando || !cambiados"
              class="inline-flex min-h-10 items-center rounded-full bg-rol-fuerte px-5 py-2.5 text-xs font-black uppercase tracking-widest text-white
                     transition-all hover:bg-rol-fuerte/90 disabled:opacity-40 disabled:cursor-not-allowed">
        {{ guardando ? 'Guardando…' : 'Guardar módulos' }}
      </button>
    </div>
  </div>
</template>
