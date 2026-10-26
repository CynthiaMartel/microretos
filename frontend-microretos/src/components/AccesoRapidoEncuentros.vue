<!-- «En curso hoy»: encuentros con grupos y código cuya fecha incluye hoy (fecha ≤ hoy ≤ fecha_fin),
     con lo justo para dar acceso al alumnado en un clic: proyecto, clase, código grande (copiar) y
     «Proyectar» (abre la pantalla con el QR). Lo usan la Pantalla de acceso (/pantalla-acceso) y la
     sección Acceso del alumnado (/seccion/alumnado). Recibe los encuentros o, si no, los pide. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'
import { formatCurso } from '../utils/formatCurso.js'

const props = defineProps({
  // Lista de /encuentros ya cargada por la vista; null = la carga el componente
  encuentros: { type: Array, default: null },
  cargandoExterno: { type: Boolean, default: false },
  // Enlace «Ver todos» (en la sección); en la propia pantalla de acceso no hace falta
  verTodos: { type: Boolean, default: false },
  // Título del bloque: «En curso hoy» en la pantalla de acceso; «Dar acceso al encuentro» en la
  // sección (ahí «En curso hoy» pasa a subtítulo con el número)
  titulo: { type: String, default: 'En curso hoy' },
  // Máximo de tarjetas (en la sección se enseñan 3 para no empujar el resto); 0 = todas
  limite: { type: Number, default: 0 },
})

const router = useRouter()
const propios = ref([])
const cargandoPropio = ref(false)
onMounted(async () => {
  if (props.encuentros) return
  cargandoPropio.value = true
  try { propios.value = (await api.get('/encuentros')).data } catch { propios.value = [] } finally { cargandoPropio.value = false }
})
const lista = computed(() => props.encuentros ?? propios.value)
const cargando = computed(() => props.cargandoExterno || cargandoPropio.value)

const hoy = new Date().toISOString().slice(0, 10)
const dia = (f) => String(f || '').slice(0, 10)
const enCurso = computed(() => lista.value
  .filter(e => e.codigo_clase && e.microproyecto_uuid && dia(e.fecha) && dia(e.fecha) <= hoy && dia(e.fecha_fin || e.fecha) >= hoy)
  .sort((a, b) => dia(b.fecha).localeCompare(dia(a.fecha))))

const mostrados = computed(() => props.limite ? enCurso.value.slice(0, props.limite) : enCurso.value)
const corta = (f) => { const d = new Date(dia(f) + 'T12:00:00'); return isNaN(d) ? '' : d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' }) }
const tituloDe = (e) => e.proyecto_titulo || (e.grupo && `Clase ${e.grupo}`) || 'Encuentro'

const aviso = ref('')
async function copiar(codigo) {
  try { await navigator.clipboard.writeText(codigo); aviso.value = `Código ${codigo} copiado` } catch { aviso.value = `Código: ${codigo}` }
  setTimeout(() => { aviso.value = '' }, 2500)
}
const proyectar = (e) => router.push({ name: 'pantalla-acceso', params: { uuid: e.microproyecto_uuid } })
</script>

<template>
  <section class="rounded-2xl border border-alumnos/30 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="titulo-en-curso">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <div class="flex min-w-0 items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-alumnos/10 text-alumnos-dark" aria-hidden="true">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </span>
        <div class="min-w-0">
          <h2 id="titulo-en-curso" class="font-heading text-lg font-bold text-azul-noche">
            {{ titulo }}<span v-if="!cargando && titulo === 'En curso hoy'" class="font-sans text-base font-semibold text-texto-secundario"> · {{ enCurso.length }}</span>
          </h2>
          <p class="text-sm text-texto-secundario">
            <template v-if="titulo !== 'En curso hoy'"><span class="font-semibold text-azul-noche">En curso hoy<template v-if="!cargando"> · {{ enCurso.length }}</template></span> — </template>Proyecta el QR y el código para que el alumnado entre en su espacio de trabajo.
          </p>
        </div>
      </div>
      <RouterLink v-if="verTodos" to="/pantalla-acceso" class="accion-secundaria">Dar acceso a otro encuentro</RouterLink>
    </div>

    <div v-if="cargando" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3" aria-busy="true">
      <div v-for="n in 3" :key="n" class="h-40 animate-pulse rounded-xl bg-gray-100" />
    </div>

    <p v-else-if="!enCurso.length" class="rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-texto-secundario">
      Hoy no hay ningún encuentro en curso.
      <template v-if="verTodos"> Puedes proyectar cualquier otro con «Dar acceso a otro encuentro».</template>
    </p>

    <ul v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
      <li v-for="e in mostrados" :key="e.id" class="flex min-w-0 flex-col rounded-xl border border-borde bg-white p-3">
        <p class="line-clamp-2 min-h-11 font-heading text-base font-bold leading-snug text-azul-noche" :title="tituloDe(e)">{{ tituloDe(e) }}</p>
        <p class="truncate text-sm text-texto-secundario">
          <template v-if="e.grupo">Clase {{ e.grupo }} · </template><template v-if="e.curso">{{ formatCurso(e.curso) }} curso · </template>{{ corta(e.fecha) }}<template v-if="e.fecha_fin && dia(e.fecha_fin) !== dia(e.fecha)"> – {{ corta(e.fecha_fin) }}</template>
        </p>
        <div class="mt-3 flex items-center gap-2 rounded-xl bg-alumnos/5 px-3 py-2">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-alumnos-dark">Código de acceso</p>
            <p class="font-heading text-xl font-bold tracking-[0.15em] text-azul-noche">{{ e.codigo_clase }}</p>
          </div>
          <button type="button" @click="copiar(e.codigo_clase)" :aria-label="`Copiar el código ${e.codigo_clase}`" title="Copiar el código"
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-borde bg-white text-texto-secundario hover:text-azul-noche">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          </button>
        </div>
        <button type="button" @click="proyectar(e)" class="accion-principal mt-3 w-full">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          Proyectar
        </button>
      </li>
    </ul>

    <p v-if="!cargando && limite && enCurso.length > limite" class="mt-3 text-center">
      <RouterLink to="/pantalla-acceso" class="text-sm font-semibold text-rol-fuerte hover:underline">Ver los {{ enCurso.length }} encuentros en curso</RouterLink>
    </p>

    <Transition enter-from-class="opacity-0 translate-y-2" leave-to-class="opacity-0" enter-active-class="transition" leave-active-class="transition">
      <p v-if="aviso" role="status"
         class="fixed bottom-14 right-4 z-[60] max-w-[calc(100vw-2rem)] rounded-2xl bg-azul-noche px-4 py-3 text-sm font-semibold text-white shadow-xl">{{ aviso }}</p>
    </Transition>
  </section>
</template>
