<!-- Cronograma de un plan de proyecto (etapa 2, P4b): fechas, hitos (qué fases se trabajan en cada
     uno, mismo editor que la planificación de sesiones) y el alumnado del plan, elegido por alias de
     uno o varios encuentros creativos — por defecto, el destacado por DuaLab (★). Se guarda como un
     encuentro de tipo plan; los grupos y códigos se generan después en la biblioteca de planes. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import PlanificacionSesiones from '../components/PlanificacionSesiones.vue'
import MarcaDestacado from '../components/MarcaDestacado.vue'
import { crearCronogramaPlan, getEncuentrosCreativos } from '../services/planService.js'
import { CLASES_PROYECTO_DEFECTO } from '../config/fasesProyecto.js'

const route = useRoute()
const router = useRouter()
const uuid = route.params.uuid

const plan = ref(null)
const encuentros = ref([])
const cargando = ref(true)
const error = ref('')
const guardando = ref(false)

const soloDestacados = ref(true)
const buscar = ref('')
const seleccion = ref(new Map())   // miembroId → { id, alias, destacado, equipo_num }
const numEquipos = ref(1)
const fecha = ref(new Date().toISOString().slice(0, 10))
const fechaFin = ref('')
// Hitos con fecha real (obligatoria y en orden); se parte de las fases por defecto sin fechas
const hitos = ref(CLASES_PROYECTO_DEFECTO.map(c => ({ fases: [...c.fases], fecha: '' })))

onMounted(async () => {
  try {
    const [p, e] = await Promise.all([api.get(`/startup/proyectos/${uuid}`), getEncuentrosCreativos()])
    plan.value = p.data
    encuentros.value = e.data.filter(x => (x.equipos || []).some(g => (g.miembros || []).length))
    // Sin nadie destacado todavía, se muestra todo el alumnado
    if (!encuentros.value.some(x => x.equipos.some(g => g.miembros.some(m => m.destacado)))) soloDestacados.value = false
  } catch {
    error.value = 'No se ha podido cargar el plan o los encuentros.'
  } finally {
    cargando.value = false
  }
})

const norm = (t) => (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const visibles = computed(() => {
  const q = norm(buscar.value)
  return encuentros.value
    .filter(e => !q || norm(e.proyecto_titulo).includes(q) || norm(`${e.curso} ${e.grupo}`).includes(q))
    .map(e => ({ ...e, miembros: e.equipos.flatMap(g => g.miembros).filter(m => !soloDestacados.value || m.destacado) }))
    .filter(e => e.miembros.length)
})

const elegidos = computed(() => [...seleccion.value.values()])
function alternar(m) {
  const s = new Map(seleccion.value)
  s.has(m.id) ? s.delete(m.id) : s.set(m.id, { id: m.id, alias: m.alias, destacado: m.destacado, equipo_num: 1 })
  seleccion.value = s
}
function asignar(id, n) {
  const s = new Map(seleccion.value)
  s.set(id, { ...s.get(id), equipo_num: Number(n) })
  seleccion.value = s
}

const planValidado = computed(() => ['validado', 'completado'].includes(plan.value?.estado))
const hitosValidos = computed(() => hitos.value.length > 0 && hitos.value.every((h, i) =>
  (h.fases || []).length && h.fecha && h.fecha >= fecha.value && (!fechaFin.value || h.fecha <= fechaFin.value)
  && (i === 0 || h.fecha >= hitos.value[i - 1].fecha)))
const valido = computed(() => planValidado.value && elegidos.value.length > 0 && !!fecha.value && hitosValidos.value
  && elegidos.value.every(m => m.equipo_num >= 1 && m.equipo_num <= numEquipos.value))

async function guardar() {
  if (!valido.value || guardando.value) return
  guardando.value = true
  error.value = ''
  try {
    await crearCronogramaPlan(uuid, {
      fecha: fecha.value,
      fecha_fin: fechaFin.value || null,
      num_equipos: numEquipos.value,
      miembros: elegidos.value.map(m => ({ id: m.id, equipo_num: m.equipo_num })),
      planificacion_sesiones: hitos.value,
    })
    router.push({ name: 'planes' })
  } catch (e) {
    const errores = e.response?.data?.errors
    error.value = (errores && Object.values(errores)[0]?.[0]) || e.response?.data?.message || 'No se pudo crear el cronograma.'
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-5xl space-y-4 px-4 pb-10 pt-5 sm:px-6">
      <CabeceraSeccion data-tour="cron-cabecera" antetitulo="Plan de proyecto" titulo="Cronograma del" destacado="plan" color="text-rol"
                       :volver="{ name: 'planes' }"
                       :subtitulo="plan ? plan.titulo : 'Fechas, hitos y alumnado del plan en la empresa.'" />

      <p v-if="cargando" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>
      <template v-else-if="plan">
        <p v-if="!planValidado" data-tour="cron-aviso" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900" role="alert">
          Valida el plan antes de crear su cronograma.
        </p>

        <!-- Fechas -->
        <section data-tour="cron-fechas" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-fechas">
          <h2 id="titulo-fechas" class="text-lg font-bold">Fechas de las prácticas</h2>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="min-w-0">
              <label for="cr-fecha" class="field-label">Inicio <span class="text-red-600" aria-hidden="true">*</span></label>
              <input id="cr-fecha" v-model="fecha" type="date" class="field-input" required />
            </div>
            <div class="min-w-0">
              <label for="cr-fin" class="field-label">Fin (opcional)</label>
              <input id="cr-fin" v-model="fechaFin" type="date" :min="fecha" class="field-input" />
              <p class="mt-1 text-xs text-texto-secundario">Si lo dejas vacío, será la fecha del último hito.</p>
            </div>
          </div>
        </section>

        <!-- Hitos -->
        <section data-tour="cron-hitos" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-hitos">
          <h2 id="titulo-hitos" class="text-lg font-bold">Hitos del plan</h2>
          <p class="mt-0.5 mb-4 text-sm text-texto-secundario">Cada hito es un encuentro del cronograma: marca qué fases del workspace se trabajan en él.</p>
          <PlanificacionSesiones v-model="hitos" unidad="hito" unidades="hitos" con-fecha :fecha-minima="fecha" />
        </section>

        <!-- Alumnado -->
        <section data-tour="cron-alumnado" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-alumnado">
          <h2 id="titulo-alumnado" class="text-lg font-bold">Alumnado del plan</h2>
          <p class="mt-0.5 text-sm text-texto-secundario">Elige por alias, de uno o varios encuentros. La ★ marca al alumnado destacado por DuaLab.</p>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <label for="cr-buscar" class="sr-only">Buscar encuentro</label>
            <input id="cr-buscar" v-model="buscar" type="search" placeholder="Buscar por reto ajustado o clase" class="field-input min-w-0 sm:w-72" />
            <button type="button" data-tour="cron-destacados" @click="soloDestacados = !soloDestacados" :aria-pressed="soloDestacados"
                    class="inline-flex min-h-9 items-center self-start rounded-full border px-4 text-sm font-semibold transition-colors"
                    :class="soloDestacados ? 'border-amber-400 bg-amber-50 text-amber-900' : 'border-borde bg-white hover:bg-gray-50'">
              ★ Solo destacados
            </button>
          </div>

          <p v-if="!visibles.length" class="mt-4 text-sm text-texto-secundario">
            {{ soloDestacados ? 'No hay alumnado destacado en tus encuentros. Quita el filtro para ver a todo el alumnado.' : 'No hay encuentros con alumnado.' }}
          </p>
          <ul v-else class="mt-3 max-h-96 space-y-3 overflow-y-auto">
            <li v-for="(e, i) in visibles" :key="e.id" :data-tour="i === 0 ? 'cron-encuentro' : undefined" class="rounded-xl border border-borde p-3">
              <p class="break-words text-sm font-bold">{{ e.proyecto_titulo || 'Sin reto ajustado' }}</p>
              <p class="text-xs text-texto-secundario">{{ e.curso }} · Clase {{ e.grupo }} · {{ e.fecha }}</p>
              <ul class="mt-2 flex flex-wrap gap-1.5">
                <li v-for="m in e.miembros" :key="m.id" class="min-w-0 max-w-full">
                  <button type="button" @click="alternar(m)" :aria-pressed="seleccion.has(m.id)"
                          class="inline-flex min-h-9 max-w-full items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors"
                          :class="seleccion.has(m.id) ? 'border-empresas-dark bg-rol-fuerte text-white' : 'border-borde bg-white hover:bg-gray-50'">
                    <span class="truncate">{{ m.alias }}</span>
                    <MarcaDestacado v-if="m.destacado" />
                  </button>
                </li>
              </ul>
            </li>
          </ul>

          <!-- Seleccionados y su grupo -->
          <div data-tour="cron-seleccion" class="mt-4 border-t border-borde pt-4">
            <div class="flex flex-wrap items-center gap-3">
              <p class="text-sm font-bold">{{ elegidos.length }} seleccionados</p>
              <label for="cr-grupos" class="text-sm text-texto-secundario">Grupos</label>
              <select id="cr-grupos" v-model.number="numEquipos" class="field-input !w-auto !py-1.5">
                <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
              </select>
              <span class="text-xs text-texto-secundario">Normalmente un grupo; excepcionalmente, una sola persona.</span>
            </div>
            <ul v-if="elegidos.length" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
              <li v-for="m in elegidos" :key="m.id" class="flex min-w-0 items-center gap-2 rounded-xl border border-borde px-3 py-2">
                <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ m.alias }}</span>
                <MarcaDestacado v-if="m.destacado" />
                <label :for="`grupo-${m.id}`" class="sr-only">Grupo de {{ m.alias }}</label>
                <select :id="`grupo-${m.id}`" :value="m.equipo_num" @change="asignar(m.id, $event.target.value)" class="shrink-0 rounded-xl px-2 py-1 text-sm">
                  <option v-for="n in numEquipos" :key="n" :value="n">Grupo {{ n }}</option>
                </select>
              </li>
            </ul>
          </div>
        </section>

        <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>

        <div class="fila-pasos">
          <button type="button" class="btn-paso-atras" @click="router.push({ name: 'planes' })">Cancelar</button>
          <button type="button" data-tour="cron-crear" class="btn-paso" :disabled="!valido || guardando" @click="guardar">
            {{ guardando ? 'Guardando…' : 'Crear cronograma' }}
          </button>
        </div>
      </template>
      <p v-else-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
    </div>
  </div>
</template>
