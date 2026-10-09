<!-- Ruta: /empresas/propuestas (name: propuestas-empresas). Seguimiento del envío de
     propuestas de proyecto a las empresas: pendientes de enviar, esperando respuesta y
     respondidas. El envío en sí se hace desde la ficha del proyecto (StartupDayDetalle.vue),
     que ya tiene su confirmación; aquí solo se agrupa y se lleva hasta ella. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'

const router = useRouter()

const proyectos = ref([])
const cargando  = ref(true)
const error     = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/startup/proyectos')
    proyectos.value = data
  } catch {
    error.value = 'No se han podido cargar tus propuestas.'
  } finally {
    cargando.value = false
  }
})

const dias = (d) => d ? Math.floor((Date.now() - new Date(d).getTime()) / 86_400_000) : 0
const DIAS_RECORDATORIO = 10 // mismo umbral que la tarea "Recordar a empresas" del panel docente

// Mismos criterios de estado que ProyectoCard / InicioDocente (etiquetaProyecto)
const columnas = computed(() => {
  const prop = proyectos.value.filter(p => p.estado === 'propuesta')
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
        ...proyectos.value.filter(p => p.estado === 'validado' && p.empresa_validado && !p.docente_validado),
      ] },
  ]
})

function etiqueta(p) {
  if (p.empresa_no_valida_aun) return { texto: 'No validar aún', cls: 'bg-red-50 text-red-700' }
  if (p.empresa_validado)      return { texto: 'Validada · falta la tuya', cls: 'bg-emerald-50 text-emerald-700' }
  if (p.enviado_a_empresa_mail) {
    const d = dias(p.updated_at)
    return d >= DIAS_RECORDATORIO
      ? { texto: `Sin respuesta · ${d} días`, cls: 'bg-alumnos/15 text-alumnos-dark' }
      : { texto: d ? `Enviada hace ${d} día${d > 1 ? 's' : ''}` : 'Enviada hoy', cls: 'bg-blue-50 text-blue-700' }
  }
  return { texto: 'Sin enviar', cls: 'bg-violet-50 text-violet-700' }
}

const abrir = (p) => router.push(`/proyectos/${p.uuid}`)
</script>

<template>
  <div class="min-h-screen bg-[#F3F6FA] font-sans text-azul-noche pt-16">
    <div class="mx-auto max-w-[1440px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Propuestas a" destacado="empresas" color="text-empresas"
                       subtitulo="Envía tus propuestas de proyecto a las empresas y sigue sus respuestas. El envío se confirma desde la ficha de cada proyecto.">
        <button @click="router.push('/proyectos/crear')"
                class="flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-centros px-4 text-sm font-semibold text-white shadow-md shadow-centros/25 hover:bg-centros/90">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
          Nuevo proyecto
        </button>
      </CabeceraSeccion>

      <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <article v-for="col in columnas" :key="col.key" class="card flex min-w-0 flex-col p-4">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="card-title flex items-center gap-2 text-base">
              <span class="h-2.5 w-2.5 rounded-full" :class="col.color" />{{ col.titulo }}
            </h2>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-600">{{ cargando ? '—' : col.items.length }}</span>
          </div>

          <div v-if="cargando" class="space-y-2"><div v-for="n in 3" :key="n" class="h-20 animate-pulse rounded-xl bg-gray-100" /></div>
          <p v-else-if="!col.items.length" class="py-8 text-center text-sm text-gray-500">{{ col.vacio }}</p>
          <ul v-else class="space-y-2">
            <li v-for="p in col.items" :key="p.uuid" class="rounded-xl p-3 ring-1 ring-gray-200/70 transition hover:shadow-md">
              <p class="line-clamp-2 text-sm font-semibold leading-snug">{{ p.titulo || 'Proyecto sin título' }}</p>
              <p class="mt-0.5 truncate text-xs text-gray-500">{{ [p.empresa_nombre, p.familia_nombre].filter(Boolean).join(' · ') || 'Sin empresa asignada' }}</p>
              <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="etiqueta(p).cls">{{ etiqueta(p).texto }}</span>
                <button class="link text-xs" @click="abrir(p)">{{ col.accion }} →</button>
              </div>
            </li>
          </ul>
        </article>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.card       { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
.card-title { @apply font-heading text-lg font-bold text-azul-noche; }
.link       { @apply font-semibold text-centros hover:underline; }
</style>
