<!-- Crear un plan de proyecto (etapa 2, P4): ampliando una propuesta del taller ya validada o
     completada (hereda lo curricular) o partiendo de otro reto. Después se completa en el mismo
     asistente que la propuesta (paso 6: horas y «qué espera la empresa»). ?origen=uuid preselecciona
     la propuesta (botón «Ampliar en un plan» de su ficha). -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { crearPlan } from '../services/planService.js'
import ProyectoFichaModal from '../components/ProyectoFichaModal.vue'
import MicroretoModal from '../components/MicroretoModal.vue'

const route = useRoute()
const router = useRouter()

const modo = ref('propuesta')          // 'propuesta' | 'reto'
const propuestas = ref([])
const retos = ref([])
const cargando = ref(true)
const buscar = ref('')
const origen = ref(route.query.origen || '')
const retoId = ref('')
const titulo = ref('')
const horas = ref(null)
const expectativas = ref('')
const guardando = ref(false)
const error = ref('')

onMounted(async () => {
  try {
    const [p, r] = await Promise.all([api.get('/startup/proyectos'), api.get('/microretos', { params: { limit: 500 } })])
    // Solo se amplían propuestas ya validadas o completadas
    propuestas.value = p.data.filter(x => ['validado', 'completado'].includes(x.estado))
    retos.value = r.data
  } catch {
    error.value = 'No se han podido cargar las propuestas y los retos.'
  } finally {
    cargando.value = false
  }
})

const norm = (t) => (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const lista = computed(() => {
  const q = norm(buscar.value)
  const base = modo.value === 'propuesta' ? propuestas.value : retos.value
  return base.filter(x => !q || norm(x.titulo).includes(q) || norm(x.empresa_nombre).includes(q)).slice(0, 50)
})
const elegido = computed(() => modo.value === 'propuesta'
  ? propuestas.value.find(p => p.uuid === origen.value)
  : retos.value.find(r => String(r.id) === String(retoId.value)))
const valido = computed(() => !!elegido.value && (horas.value === null || horas.value === '' || (horas.value >= 1 && horas.value <= 2000)))

function elegir(x) {
  if (modo.value === 'propuesta') origen.value = x.uuid
  else retoId.value = x.id
}
// Ficha de cada resultado (como en «Crear encuentro»): la de la propuesta o la del reto, sin elegirlo
const fichaPropuestaUuid = ref(null)
const fichaRetoId = ref(null)
const verFicha = (x) => { if (modo.value === 'propuesta') fichaPropuestaUuid.value = x.uuid; else fichaRetoId.value = x.id }

const esElegido = (x) => modo.value === 'propuesta' ? x.uuid === origen.value : String(x.id) === String(retoId.value)

async function crear() {
  if (!valido.value || guardando.value) return
  guardando.value = true
  error.value = ''
  try {
    const { data } = await crearPlan({
      ...(modo.value === 'propuesta' ? { origen_uuid: origen.value } : { microreto_id: Number(retoId.value) }),
      titulo: titulo.value || null,
      horas_estimadas: horas.value || null,
      expectativas_empresa: expectativas.value || null,
    })
    // Se completa en el asistente (el mismo de la propuesta)
    router.push({ name: 'startup-day-editar', params: { uuid: data.uuid } })
  } catch (e) {
    const errores = e.response?.data?.errors
    error.value = (errores && Object.values(errores)[0]?.[0]) || e.response?.data?.message || 'No se pudo crear el plan.'
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-4xl space-y-4 px-4 pb-10 pt-5 sm:px-6">
      <CabeceraSeccion antetitulo="Plan de proyecto" titulo="Crear" destacado="plan" color="text-centros" :volver="{ name: 'planes' }"
                       subtitulo="El trabajo ampliado que el alumnado seleccionado desarrollará en la empresa durante las prácticas." />

      <!-- 1. De dónde parte -->
      <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-origen">
        <h2 id="titulo-origen" class="text-lg font-bold">¿De dónde parte el plan?</h2>
        <div class="mt-3 inline-flex flex-wrap gap-1 rounded-full border border-borde bg-white p-1" role="group" aria-label="Origen del plan">
          <button v-for="o in [{ v: 'propuesta', l: 'Ampliar una propuesta' }, { v: 'reto', l: 'Otro reto' }]" :key="o.v" type="button"
                  @click="modo = o.v; buscar = ''" :aria-pressed="modo === o.v"
                  class="inline-flex min-h-9 items-center rounded-full px-4 text-sm font-semibold transition-colors"
                  :class="modo === o.v ? 'bg-rol-fuerte text-white' : 'text-azul-noche hover:bg-gray-50'">{{ o.l }}</button>
        </div>
        <p class="mt-2 text-sm text-texto-secundario">
          {{ modo === 'propuesta'
            ? 'El plan hereda la empresa, los módulos, los RA y CE y los objetivos de la propuesta del taller, para ampliarlos.'
            : 'El plan parte de otro reto de la biblioteca; completarás lo curricular en el asistente.' }}
        </p>

        <label for="plan-buscar" class="sr-only">Buscar</label>
        <input id="plan-buscar" v-model="buscar" type="search" class="field-input mt-3"
               :placeholder="modo === 'propuesta' ? 'Buscar por título o empresa' : 'Buscar reto por título o empresa'" />

        <p v-if="cargando" class="py-6 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
        <p v-else-if="!lista.length" class="py-6 text-center text-sm text-texto-secundario">
          {{ modo === 'propuesta' ? 'No hay propuestas validadas o completadas con ese texto.' : 'No hay retos con ese texto.' }}
        </p>
        <ul v-else class="mt-3 max-h-80 space-y-1.5 overflow-y-auto" :aria-label="modo === 'propuesta' ? 'Propuestas' : 'Retos'">
          <li v-for="x in lista" :key="x.uuid || x.id"
              class="flex min-w-0 items-stretch rounded-xl border transition-colors"
              :class="esElegido(x) ? 'border-rol-fuerte bg-rol/10' : 'border-borde hover:bg-gray-50'">
            <button type="button" @click="elegir(x)" :aria-pressed="esElegido(x)"
                    class="flex min-w-0 flex-1 items-center gap-3 rounded-l-xl px-3 py-2 text-left">
              <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold">{{ x.titulo }}</span>
                <span class="block truncate text-xs text-texto-secundario">
                  {{ x.empresa_nombre || 'Sin empresa' }}<template v-if="x.estado"> · {{ x.estado === 'completado' ? 'Completada' : 'Validada' }}</template>
                </span>
              </span>
              <span v-if="esElegido(x)" class="shrink-0 text-sm font-bold text-rol-fuerte" aria-hidden="true">✓</span>
            </button>
            <!-- Ver la ficha sin elegirla -->
            <button type="button" @click="verFicha(x)"
                    :title="modo === 'propuesta' ? 'Ver la ficha de la propuesta' : 'Ver la ficha del reto'"
                    :aria-label="`${modo === 'propuesta' ? 'Ver la ficha de la propuesta' : 'Ver la ficha del reto'}: ${x.titulo}`"
                    class="flex w-11 shrink-0 items-center justify-center rounded-r-xl border-l border-borde text-texto-secundario transition-colors hover:bg-rol/10 hover:text-rol-fuerte">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </li>
        </ul>
      </section>

      <!-- 2. Datos del plan -->
      <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-datos">
        <h2 id="titulo-datos" class="text-lg font-bold">Datos del plan</h2>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="min-w-0 sm:col-span-2">
            <label for="plan-titulo" class="field-label">Título del plan</label>
            <input id="plan-titulo" v-model="titulo" type="text" maxlength="255" class="field-input" aria-describedby="plan-titulo-ayuda"
                   :placeholder="elegido ? `Plan de proyecto: ${elegido.titulo}` : 'Elige antes de dónde parte'" />
            <p id="plan-titulo-ayuda" class="mt-1 text-xs text-texto-secundario">Opcional: si lo dejas vacío, se usa el del ejemplo.</p>
          </div>
          <div class="min-w-0">
            <label for="plan-horas-c" class="field-label">Horas estimadas</label>
            <input id="plan-horas-c" v-model.number="horas" type="number" min="1" max="2000" class="field-input" placeholder="120" />
          </div>
          <div class="min-w-0 sm:col-span-3">
            <label for="plan-expectativas-c" class="field-label">¿Qué espera la empresa del alumnado?</label>
            <textarea id="plan-expectativas-c" v-model="expectativas" rows="3" maxlength="3000" class="field-input resize-none"
                      placeholder="Por ejemplo: que implante la solución en su equipo y forme a dos personas" />
            <p class="mt-1 text-xs text-texto-secundario">Lo verá el alumnado en su workspace. Puedes completarlo después.</p>
          </div>
        </div>
      </section>

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>

      <div class="fila-pasos">
        <button type="button" class="btn-paso-atras" @click="router.push({ name: 'planes' })">Cancelar</button>
        <button type="button" class="btn-paso" :disabled="!valido || guardando" @click="crear">
          {{ guardando ? 'Creando…' : 'Crear plan' }}
        </button>
      </div>
    </div>
    <ProyectoFichaModal :proyecto-uuid="fichaPropuestaUuid" @close="fichaPropuestaUuid = null" />
    <MicroretoModal :microreto-id="fichaRetoId" @close="fichaRetoId = null" />
  </div>
</template>
