<!-- Ruta: /empresas/propuestas (name: propuestas-empresas). Seguimiento del envío de
     propuestas de proyecto a las empresas: pendientes de enviar, esperando respuesta y
     respondidas. El envío en sí se hace desde la ficha del proyecto (StartupDayDetalle.vue),
     que ya tiene su confirmación; aquí solo se agrupa y se lleva hasta ella.
     Es un tablero de flujo (no una biblioteca): mantiene las tres columnas y solo toma de las
     demás vistas lo que aquí hace falta — buscador, empresa, familia y curso académico — y un
     límite por columna con «Ver más» (una columna puede tener más de cien propuestas). -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import FiltrosPanel from '../components/FiltrosPanel.vue'
import FiltroSelect from '../components/FiltroSelect.vue'
import SelectorCurso from '../components/SelectorCurso.vue'
import ProyectoFichaModal from '../components/ProyectoFichaModal.vue'
import { useCursoAcademicoStore, cursoDeProyecto } from '../stores/cursoAcademico.js'

const router = useRouter()

const proyectos = ref([])
const cargando  = ref(true)
const error     = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await api.get('/startup/proyectos')
    proyectos.value = data
  } catch {
    error.value = 'No se han podido cargar tus propuestas.'
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

const dias = (d) => d ? Math.floor((Date.now() - new Date(d).getTime()) / 86_400_000) : 0
const DIAS_RECORDATORIO = 10 // mismo umbral que la tarea "Recordar a empresas" del panel docente

// Propuestas que entran en el tablero (las tres columnas)
const delTablero = (p) => p.estado === 'propuesta' || (p.estado === 'validado' && p.empresa_validado && !p.docente_validado)

// ── Filtros (solo los útiles para un tablero de envíos) ──────────────────────
const cursoStore     = useCursoAcademicoStore()
const cursosConDatos = computed(() => [...new Set(proyectos.value.filter(delTablero).map(cursoDeProyecto))])
const busqueda      = ref('')
const filtroEmpresa = ref('')
const filtroFamilia = ref('')
const base = computed(() => proyectos.value.filter(p => delTablero(p) && cursoStore.coincide(cursoDeProyecto(p))))
const empresasDisponibles = computed(() => [...new Set(base.value.map(p => p.empresa_nombre).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const familiasDisponibles = computed(() => [...new Set(base.value.map(p => p.familia_nombre).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')))
const filtrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return base.value.filter(p =>
    (!filtroEmpresa.value || p.empresa_nombre === filtroEmpresa.value) &&
    (!filtroFamilia.value || p.familia_nombre === filtroFamilia.value) &&
    (!q || [p.titulo, p.empresa_nombre, p.familia_nombre].filter(Boolean).join(' ').toLowerCase().includes(q)))
})
const hayFiltros = computed(() => !!(busqueda.value.trim() || filtroEmpresa.value || filtroFamilia.value))
function limpiar() { busqueda.value = ''; filtroEmpresa.value = ''; filtroFamilia.value = '' }

// Mismos criterios de estado que ProyectoCard / InicioDocente (etiquetaProyecto)
const columnas = computed(() => {
  const prop = filtrados.value.filter(p => p.estado === 'propuesta')
  return [
    { key: 'enviar', titulo: 'Pendientes de enviar', color: 'bg-violet-500', accion: 'Enviar a la empresa',
      vacio: 'No tienes propuestas sin enviar.',
      items: prop.filter(p => !p.enviado_a_empresa_mail && !p.empresa_no_valida_aun) },
    { key: 'esperando', titulo: 'Esperando respuesta', color: 'bg-centros', accion: 'Ver o reenviar',
      vacio: 'Ninguna propuesta espera respuesta.',
      items: prop.filter(p => p.enviado_a_empresa_mail && !p.empresa_validado && !p.empresa_no_valida_aun)
        .sort((a, b) => new Date(a.updated_at) - new Date(b.updated_at)) },
    { key: 'respondidas', titulo: 'Respuesta de la empresa', color: 'bg-empresas', accion: 'Ver respuesta',
      vacio: 'Aún no hay respuestas.',
      items: [
        ...prop.filter(p => p.empresa_no_valida_aun),
        ...filtrados.value.filter(p => p.estado === 'validado' && p.empresa_validado && !p.docente_validado),
      ] },
  ]
})

// Límite por columna: 6 y «Ver 6 más» (al filtrar se vuelve a 6)
const PASO = 6
const limites = ref({})
const limiteDe = (key) => limites.value[key] ?? PASO
function verMas(key) { limites.value = { ...limites.value, [key]: limiteDe(key) + PASO } }
function verMenos(key) { limites.value = { ...limites.value, [key]: PASO } }
watch([busqueda, filtroEmpresa, filtroFamilia, () => cursoStore.curso], () => { limites.value = {} })

function etiqueta(p) {
  if (p.empresa_no_valida_aun) return { texto: 'No validar aún', cls: 'bg-red-50 text-red-700' }
  if (p.empresa_validado)      return { texto: 'Validada · falta la tuya', cls: 'bg-empresas/10 text-empresas-dark' }
  if (p.enviado_a_empresa_mail) {
    const d = dias(p.updated_at)
    return d >= DIAS_RECORDATORIO
      ? { texto: `Sin respuesta · ${d} días`, cls: 'bg-alumnos/15 text-alumnos-dark' }
      : { texto: d ? `Enviada hace ${d} día${d > 1 ? 's' : ''}` : 'Enviada hoy', cls: 'bg-centros/10 text-centros' }
  }
  return { texto: 'Sin enviar', cls: 'bg-violet-50 text-violet-800' }
}

const abrir = (p) => router.push(`/proyectos/${p.uuid}`)
// Pulsar la tarjeta abre la ficha completa en un modal (en la tarjeta solo cabe un resumen)
const fichaUuid = ref(null)
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Propuestas a" destacado="empresas" color="text-empresas" volver="/seccion/empresas"
                       subtitulo="Envía tus propuestas de proyecto a las empresas y sigue sus respuestas. El envío se confirma desde la ficha de cada proyecto.">
        <button type="button" @click="router.push('/proyectos/crear')" class="accion-principal">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
          Nuevo proyecto
        </button>
      </CabeceraSeccion>

      <!-- Filtros: buscador, curso académico, empresa y familia -->
      <FiltrosPanel separadores v-model:busqueda="busqueda" placeholder="Buscar por proyecto o empresa…"
                    :hay-activos="hayFiltros" @limpiar="limpiar">
        <template #curso>
          <SelectorCurso permitir-todos :cursos-con-datos="cursosConDatos" />
        </template>
        <FiltroSelect v-if="empresasDisponibles.length > 1" label="Empresa" v-model="filtroEmpresa" :opciones="empresasDisponibles" todos="Todas las empresas" />
        <FiltroSelect v-if="familiasDisponibles.length > 1" label="Familia profesional" v-model="filtroFamilia" :opciones="familiasDisponibles" todos="Todas las familias" />
      </FiltrosPanel>

      <div v-if="error && !cargando" class="flex flex-col items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center" role="alert">
        <p class="min-w-0 flex-1 text-sm font-medium text-red-700">{{ error }}</p>
        <button type="button" @click="cargar" class="inline-flex h-10 items-center rounded-full bg-white px-4 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Reintentar</button>
      </div>

      <!-- Tablero: una columna por paso del envío. Sin items-start: las tres columnas miden lo mismo
           (la más larga marca la altura), aunque alguna esté vacía -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section v-for="col in columnas" :key="col.key" class="flex min-w-0 flex-col rounded-2xl border border-borde bg-white p-4 shadow-sm"
                 :aria-labelledby="`col-${col.key}`">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 :id="`col-${col.key}`" class="flex items-center gap-2 font-heading text-base font-bold text-azul-noche">
              <span class="h-2.5 w-2.5 rounded-full" :class="col.color" aria-hidden="true" />{{ col.titulo }}
            </h2>
            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-bold text-azul-noche">{{ cargando ? '—' : col.items.length }}</span>
          </div>

          <div v-if="cargando" class="space-y-2" aria-busy="true"><div v-for="n in 3" :key="n" class="h-24 animate-pulse rounded-xl bg-gray-100" /></div>
          <!-- Vacía: el mensaje arriba (a la vista) y el fondo gris rellena la columna hasta igualar a las demás -->
          <p v-else-if="!col.items.length" class="flex-1 rounded-xl bg-gray-50 px-4 pt-8 pb-8 text-center text-sm text-texto-secundario">
            {{ hayFiltros ? 'Ninguna coincide con los filtros.' : col.vacio }}
          </p>
          <template v-else>
            <ul class="space-y-2">
              <!-- Toda la tarjeta abre la ficha (teclado: Enter/Espacio); el botón de acción va a la página del proyecto -->
              <li v-for="p in col.items.slice(0, limiteDe(col.key))" :key="p.uuid"
                  role="button" tabindex="0" :aria-label="`Ver la ficha de «${p.titulo || 'Proyecto sin título'}»`"
                  @click="fichaUuid = p.uuid" @keydown.enter.self="fichaUuid = p.uuid" @keydown.space.self.prevent="fichaUuid = p.uuid"
                  class="cursor-pointer rounded-xl border border-borde p-3 transition-shadow hover:border-rol-fuerte/30 hover:shadow-md">
                <p class="line-clamp-2 text-sm font-semibold leading-snug text-azul-noche" :title="p.titulo">{{ p.titulo || 'Proyecto sin título' }}</p>
                <p class="mt-0.5 truncate text-xs text-texto-secundario">{{ [p.empresa_nombre, p.familia_nombre].filter(Boolean).join(' · ') || 'Sin empresa asignada' }}</p>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                  <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="etiqueta(p).cls">{{ etiqueta(p).texto }}</span>
                  <button type="button" @click.stop="abrir(p)"
                          class="inline-flex h-9 items-center rounded-full border border-rol-fuerte/30 bg-white px-3 text-xs font-semibold text-rol-fuerte hover:bg-rol-fuerte/5">
                    {{ col.accion }}
                  </button>
                </div>
              </li>
            </ul>
            <!-- Límite por columna -->
            <div v-if="col.items.length > PASO" class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-borde pt-3">
              <span class="text-xs text-texto-secundario">Mostrando {{ Math.min(limiteDe(col.key), col.items.length) }} de {{ col.items.length }}</span>
              <div class="flex gap-2">
                <button v-if="limiteDe(col.key) > PASO" type="button" @click="verMenos(col.key)"
                        class="inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-texto-secundario hover:bg-gray-100">Ver menos</button>
                <button v-if="limiteDe(col.key) < col.items.length" type="button" @click="verMas(col.key)"
                        class="inline-flex h-9 items-center rounded-full border border-rol-fuerte/30 bg-white px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol-fuerte/5">
                  Ver {{ Math.min(PASO, col.items.length - limiteDe(col.key)) }} más
                </button>
              </div>
            </div>
          </template>
        </section>
      </div>
    </div>

    <ProyectoFichaModal :proyecto-uuid="fichaUuid" @close="fichaUuid = null" />
  </div>
</template>
