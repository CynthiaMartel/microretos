<!-- Ruta: /calendario (name: calendario). Calendario mensual de encuentros + notas
     personales con fecha, tareas pendientes y notas. El calendario y las tareas son
     una copia ampliada de los del panel docente (InicioDocente.vue), que se mantienen
     allí; las tareas personales se guardan en BD y son las mismas que en el panel (useTareasPersonales). -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import PaginacionFlechas from '../components/PaginacionFlechas.vue'
import { useTareasDocente } from '../composables/useTareasDocente.js'
import { usePaginacion } from '../composables/usePaginacion.js'
import { getNotas, crearNota, actualizarNota, borrarNota } from '../services/notaService.js'

const router = useRouter()

// ── Datos ─────────────────────────────────────────────────────────────────────
const encuentros = ref([])
const proyectos  = ref([])
const notas      = ref([])
const cargando   = ref(true)
const errorNotas = ref('')

onMounted(async () => {
  const [enc, pro, not] = await Promise.allSettled([api.get('/encuentros'), api.get('/startup/proyectos'), getNotas()])
  if (enc.status === 'fulfilled') encuentros.value = enc.value.data
  if (pro.status === 'fulfilled') proyectos.value  = pro.value.data
  if (not.status === 'fulfilled') notas.value      = not.value.data.data
  else errorNotas.value = 'No se han podido cargar tus notas.'
  cargando.value = false
})

const _pf    = (s) => s ? (s.includes('T') ? new Date(s) : new Date(s + 'T12:00:00')) : null
const pad    = (n) => String(n).padStart(2, '0')
const iso    = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`
const hoy    = new Date()
const hoyISO = iso(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())

const tituloEnc  = (e) => e.proyecto_titulo || e.microreto_titulo || 'Encuentro'
const fechaLarga = (isoStr) => _pf(isoStr).toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' })

// ── Calendario mensual ────────────────────────────────────────────────────────
const calMes = ref(new Date(hoy.getFullYear(), hoy.getMonth(), 1))
const selDia = ref(hoyISO)

const calLabel = computed(() => calMes.value.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' }))
const moverMes = (delta) => { calMes.value = new Date(calMes.value.getFullYear(), calMes.value.getMonth() + delta, 1) }
function irAHoy() {
  calMes.value = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
  selDia.value = hoyISO
}

// Un encuentro dura semanas: se marca el día en que EMPIEZA (como en el panel docente)
// y, al seleccionar un día, se listan también los que están en curso ese día.
const cubreDia = (e, diaISO) => e.fecha && diaISO >= e.fecha && diaISO <= (e.fecha_fin || e.fecha)

const calDias = computed(() => {
  const y = calMes.value.getFullYear(), m = calMes.value.getMonth()
  const offset = (new Date(y, m, 1).getDay() + 6) % 7 // semana empieza en lunes
  const total  = new Date(y, m + 1, 0).getDate()
  const finMesAnterior = new Date(y, m, 0).getDate()
  const celdas = Array.from({ length: offset }, (_, i) => ({ d: finMesAnterior - offset + 1 + i, otroMes: true }))
  for (let d = 1; d <= total; d++) {
    const diaISO = iso(y, m, d)
    celdas.push({
      d, iso: diaISO,
      encs:  encuentros.value.filter(e => e.fecha === diaISO),
      notas: notas.value.filter(n => n.fecha === diaISO),
      hoy:   diaISO === hoyISO,
    })
  }
  for (let d = 1; celdas.length < 42; d++) celdas.push({ d, otroMes: true })
  return celdas
})

const encuentrosDia = computed(() => encuentros.value.filter(e => cubreDia(e, selDia.value)))
const notasDia      = computed(() => notas.value.filter(n => n.fecha === selDia.value))

// Encuentros del día agrupados por módulo(s) del proyecto + familia. Un proyecto
// puede trabajar varios módulos a la vez: ese conjunto forma un único grupo para
// no repetir el mismo encuentro en varias cabeceras.
const gruposDia = computed(() => {
  const mapa = new Map()
  for (const e of encuentrosDia.value) {
    const modulos = e.modulos?.length ? e.modulos.join(' · ') : 'Sin módulo asignado'
    const familia = e.familia_nombre || 'Sin familia asignada'
    const clave   = `${familia}|${modulos}`
    if (!mapa.has(clave)) mapa.set(clave, { clave, modulos, familia, encs: [] })
    mapa.get(clave).encs.push(e)
  }
  return [...mapa.values()].sort((a, b) => a.familia.localeCompare(b.familia, 'es') || a.modulos.localeCompare(b.modulos, 'es'))
})

// Paginación de los grupos del día: pocos por página para no saturar la columna lateral
const { pagina: paginaGrupos, totalPaginas: totalPagGrupos, itemsPagina: gruposPagina } = usePaginacion(gruposDia, 3)
watch(selDia, () => { paginaGrupos.value = 0 })

// ── Encuentro activo: ilumina en el calendario todos los días que dura ──────────
// Como en el panel docente (InicioDocente.vue): pasar el ratón (o el foco) por un
// encuentro lo ilumina; hacer clic lo deja fijado. Cambiar de día suelta el fijado
// si el encuentro no está en curso ese día.
const hoverEncId = ref(null)
const selEncId   = ref(null)
const encActivo  = computed(() => {
  const id = hoverEncId.value ?? selEncId.value
  return id ? encuentros.value.find(e => e.id === id) : null
})
const fijarEncuentro = (e) => { selEncId.value = selEncId.value === e.id ? null : e.id }
watch(selDia, (d) => {
  const fijado = encuentros.value.find(e => e.id === selEncId.value)
  if (fijado && !cubreDia(fijado, d)) selEncId.value = null
})

// Días naturales que dura un encuentro (inicio y fin incluidos)
const diasEnc = (e) => Math.round((_pf(e.fecha_fin || e.fecha) - _pf(e.fecha)) / 86_400_000) + 1
const fechaCortaEnc = (isoStr) => _pf(isoStr).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' }).replace('.', '')
const rangoEnc = (e) => e.fecha_fin && e.fecha_fin !== e.fecha
  ? `${fechaCortaEnc(e.fecha)} – ${fechaCortaEnc(e.fecha_fin)}`
  : fechaCortaEnc(e.fecha)
const diasTexto = (e) => { const n = diasEnc(e); return `${n} día${n === 1 ? '' : 's'}` }

// Posición del día dentro del encuentro activo: 'inicio' | 'fin' | 'unico' | 'medio' | null
function tramoDia(c) {
  const e = encActivo.value
  if (!e || !cubreDia(e, c.iso)) return null
  const fin = e.fecha_fin || e.fecha
  if (c.iso === e.fecha && c.iso === fin) return 'unico'
  if (c.iso === e.fecha) return 'inicio'
  if (c.iso === fin) return 'fin'
  return 'medio'
}

// ── Notas (BD) ────────────────────────────────────────────────────────────────
const notasSinFecha = computed(() => notas.value.filter(n => !n.fecha))
// Misma paginación que las tareas pendientes, para que ambas tarjetas queden a la par
const { pagina: paginaNotas, totalPaginas: totalPagNotas, itemsPagina: notasPagina } = usePaginacion(notasSinFecha, 5)
const proximasNotas = computed(() =>
  notas.value.filter(n => n.fecha && n.fecha >= hoyISO).sort((a, b) => a.fecha.localeCompare(b.fecha)).slice(0, 5))

const textoDia   = ref('')
const textoLibre = ref('')
const guardando  = ref(false)

async function guardarNota(texto, fecha) {
  const limpio = texto.trim()
  if (!limpio || guardando.value) return false
  guardando.value = true
  errorNotas.value = ''
  try {
    const { data } = await crearNota({ texto: limpio, fecha })
    notas.value = [data.data, ...notas.value]
    return true
  } catch {
    errorNotas.value = 'No se ha podido guardar la nota. Inténtalo de nuevo.'
    return false
  } finally {
    guardando.value = false
  }
}
async function addNotaDia()   { if (await guardarNota(textoDia.value, selDia.value)) textoDia.value = '' }
async function addNotaLibre() { if (await guardarNota(textoLibre.value, null)) textoLibre.value = '' }

async function quitarFecha(n) {
  try {
    const { data } = await actualizarNota(n.id, { fecha: null })
    notas.value = notas.value.map(x => x.id === n.id ? data.data : x)
  } catch { errorNotas.value = 'No se ha podido actualizar la nota.' }
}
async function eliminarNota(n) {
  try {
    await borrarNota(n.id)
    notas.value = notas.value.filter(x => x.id !== n.id)
  } catch { errorNotas.value = 'No se ha podido borrar la nota.' }
}
function verNotaEnCalendario(n) {
  const d = _pf(n.fecha)
  calMes.value = new Date(d.getFullYear(), d.getMonth(), 1)
  selDia.value = n.fecha
}

// ── Tareas pendientes (copia del panel docente) ───────────────────────────────
const { listaTareas, pendientes, errorTareas, toggleAuto, addTarea, toggleTarea, borrarTarea } = useTareasDocente(proyectos, cargando)
const nuevaTarea = ref('')

// Paginación de tareas: altura acotada para que la tarjeta quede a la par que "Mis notas"
const { pagina: paginaTareas, totalPaginas: totalPagTareas, itemsPagina: tareasPagina } = usePaginacion(listaTareas, 5)
function enviarTarea() {
  addTarea(nuevaTarea.value)
  nuevaTarea.value = ''
}
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="@container/page relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">

      <CabeceraSeccion titulo="Mi" destacado="calendario"
                       subtitulo="Tus encuentros del mes, las notas que apuntes en cada día y las tareas que tienes pendientes.">
        <button type="button" @click="router.push('/encuentros/crear')" class="accion-principal">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
          Nuevo encuentro
        </button>
      </CabeceraSeccion>

      <!-- Fila superior: calendario + día seleccionado (mismo alto en escritorio) -->
      <div class="grid grid-cols-1 gap-4 @4xl/page:grid-cols-[minmax(0,1fr)_360px]">

        <!-- ══ Calendario mensual ══ -->
        <article class="card min-w-0 p-4">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-1">
              <button class="cal-nav" aria-label="Mes anterior" @click="moverMes(-1)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <h2 class="card-title min-w-[11rem] text-center capitalize">{{ calLabel }}</h2>
              <button class="cal-nav" aria-label="Mes siguiente" @click="moverMes(1)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
              </button>
            </div>
            <!-- Encuentro activo: cuántos días dura y de cuándo a cuándo; si no, la leyenda -->
            <p v-if="encActivo" class="flex min-w-0 items-center gap-2 text-xs" aria-live="polite">
              <span class="shrink-0 rounded-full bg-centros px-2 py-0.5 font-bold text-white">{{ diasTexto(encActivo) }}</span>
              <span class="min-w-0 truncate text-gray-600"><strong class="font-semibold text-azul-noche">{{ tituloEnc(encActivo) }}</strong> · {{ rangoEnc(encActivo) }}</span>
              <button v-if="selEncId" type="button" class="-my-2 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg leading-none text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Dejar de destacar el encuentro" @click="selEncId = null">×</button>
            </p>
            <div v-else class="flex items-center gap-4 text-xs text-gray-500">
              <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-centros" />Encuentro</span>
              <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-alumnos" />Nota</span>
              <button type="button" class="-my-1.5 inline-flex h-9 items-center rounded-full px-3 text-sm font-semibold text-centros hover:bg-centros/10" @click="irAHoy">Hoy</button>
            </div>
          </div>

          <div class="grid grid-cols-7 border-b border-gray-100 pb-1 text-center text-[11px] font-semibold uppercase text-gray-400">
            <span v-for="d in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']" :key="d">{{ d }}</span>
          </div>
          <div v-if="cargando" class="mt-2 h-[480px] animate-pulse rounded-xl bg-gray-100" />
          <div v-else class="mt-1 grid grid-cols-7 gap-1">
            <template v-for="(c, i) in calDias" :key="i">
              <div v-if="c.otroMes" class="min-h-[64px] rounded-lg p-1.5 text-xs text-gray-300 sm:min-h-[84px]">{{ c.d }}</div>
              <button v-else type="button" @click="selDia = c.iso"
                      class="flex min-h-[64px] min-w-0 flex-col gap-0.5 rounded-lg p-1.5 text-left ring-1 transition sm:min-h-[84px]"
                      :class="[
                        tramoDia(c) ? 'bg-centros/15' : (selDia === c.iso ? 'bg-centros/8' : 'hover:bg-gray-50'),
                        selDia === c.iso ? 'ring-centros/50' : (tramoDia(c) ? 'ring-centros/25' : 'ring-transparent'),
                      ]"
                      :aria-label="`${fechaLarga(c.iso)}: ${c.encs.length} encuentros, ${c.notas.length} notas`">
                <span class="flex items-center gap-1">
                  <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                        :class="['inicio', 'fin', 'unico'].includes(tramoDia(c)) ? 'bg-centros text-white shadow-md shadow-centros/30'
                              : c.hoy ? 'bg-alumnos text-white'
                              : tramoDia(c) ? 'font-bold text-centros' : 'text-gray-700'">{{ c.d }}</span>
                  <!-- Primer día del encuentro activo: cuántos días dura -->
                  <span v-if="['inicio', 'unico'].includes(tramoDia(c))"
                        class="hidden truncate text-[10px] font-bold text-centros sm:inline">{{ diasTexto(encActivo) }}</span>
                </span>
                <!-- En móvil, solo puntos; desde sm, el título -->
                <span v-for="e in c.encs.slice(0, 2)" :key="'e' + e.id"
                      class="hidden truncate rounded px-1 text-[11px] font-medium leading-5 sm:block"
                      :class="encActivo?.id === e.id ? 'bg-centros text-white' : 'bg-centros/10 text-centros'"
                      @mouseenter="hoverEncId = e.id" @mouseleave="hoverEncId = null">{{ tituloEnc(e) }}</span>
                <span v-for="n in c.notas.slice(0, 1)" :key="'n' + n.id"
                      class="hidden truncate rounded bg-alumnos/10 px-1 text-[11px] font-medium leading-5 text-alumnos-dark sm:block">{{ n.texto }}</span>
                <span v-if="c.encs.length + c.notas.length > 3" class="hidden text-[10px] text-gray-400 sm:block">+{{ c.encs.length + c.notas.length - 3 }} más</span>
                <span class="flex gap-1 sm:hidden">
                  <span v-if="c.encs.length" class="h-1.5 w-1.5 rounded-full bg-centros" />
                  <span v-if="c.notas.length" class="h-1.5 w-1.5 rounded-full bg-alumnos" />
                </span>
              </button>
            </template>
          </div>
        </article>

        <!-- ══ Día seleccionado: encuentros en curso + notas del día ══
             Tamaño fijo: en escritorio no aporta altura a la fila (h-0) y ocupa la del
             calendario (min-h-full); en pantallas estrechas, alto fijo. El contenido
             hace scroll dentro y el formulario queda siempre abajo. -->
        <article class="card flex h-[26rem] min-w-0 flex-col p-4 @4xl/page:h-0 @4xl/page:min-h-full">
          <h2 class="card-title shrink-0 text-base capitalize">{{ fechaLarga(selDia) }}</h2>
          <div class="-mx-1 mt-3 min-h-0 flex-1 overflow-y-auto px-1">
            <!-- Encuentros del día agrupados por módulo, indicando la familia -->
            <div v-if="gruposDia.length" class="space-y-2.5">
              <section v-for="g in gruposPagina" :key="g.clave" class="rounded-xl bg-gray-50/80 p-2 ring-1 ring-gray-100">
                <header class="mb-1.5 px-1">
                  <p class="text-[13px] font-bold leading-snug text-azul-noche">{{ g.modulos }}</p>
                  <span class="mt-0.5 inline-block max-w-full truncate rounded-full bg-centros/10 px-2 py-0.5 text-[10px] font-semibold text-centros" :title="g.familia">{{ g.familia }}</span>
                </header>
                <ul class="space-y-1">
                  <!-- Pasar el ratón ilumina sus días en el calendario; clic lo deja fijado; la flecha lo abre -->
                  <li v-for="e in g.encs" :key="e.id"
                      class="flex items-stretch rounded-lg ring-1 transition"
                      :class="encActivo?.id === e.id ? 'bg-centros/[0.14] ring-centros/50 shadow-sm' : 'bg-centros/[0.07] ring-centros/20 hover:bg-centros/[0.12] hover:ring-centros/45'"
                      @mouseenter="hoverEncId = e.id" @mouseleave="hoverEncId = null">
                    <button class="flex min-w-0 flex-1 items-center gap-2.5 p-1.5 text-left" :aria-pressed="selEncId === e.id"
                            @click="fijarEncuentro(e)" @focus="hoverEncId = e.id" @blur="hoverEncId = null">
                      <span class="h-8 w-1 shrink-0 rounded-full bg-centros" />
                      <span class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-semibold">{{ tituloEnc(e) }}</span>
                        <span class="block truncate text-[11px] text-gray-500">{{ [e.ciclo_formativo, e.curso, e.grupo && `Clase ${e.grupo}`].filter(Boolean).join(' · ') }}</span>
                        <span class="block truncate text-[11px] font-semibold text-centros">{{ diasTexto(e) }} · {{ rangoEnc(e) }}</span>
                      </span>
                    </button>
                    <button class="flex w-9 shrink-0 items-center justify-center rounded-r-lg text-gray-400 hover:text-centros"
                            :aria-label="`Abrir ${tituloEnc(e)}`" @click="router.push('/mis-grupos/' + e.id)">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                  </li>
                </ul>
              </section>
              <PaginacionFlechas v-model="paginaGrupos" :total="totalPagGrupos" etiqueta="Páginas de encuentros del día" class="pt-0.5" />
            </div>
            <ul v-if="notasDia.length" class="mt-2 space-y-1">
              <li v-for="n in notasDia" :key="n.id" class="group flex items-start gap-2 rounded-lg bg-alumnos/5 p-2 text-sm">
                <span class="min-w-0 flex-1 break-words text-gray-700">{{ n.texto }}</span>
                <button type="button" class="-my-1.5 inline-flex h-9 shrink-0 items-center rounded-full px-2 text-xs text-gray-500 transition hover:bg-centros/10 hover:text-centros sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
                        title="Quitar del calendario (se queda en tus notas)" @click="quitarFecha(n)">Sin fecha</button>
                <button type="button" class="-my-1.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg leading-none text-gray-400 transition hover:bg-red-50 hover:text-red-500 sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
                        :aria-label="`Borrar nota: ${n.texto}`" @click="eliminarNota(n)">×</button>
              </li>
            </ul>
            <p v-if="!encuentrosDia.length && !notasDia.length" class="text-sm text-gray-500">Nada programado este día.</p>
          </div>
            <form class="mt-3 flex shrink-0 gap-2" @submit.prevent="addNotaDia">
              <input v-model="textoDia" maxlength="1000" placeholder="Apuntar una nota en este día…"
                     class="h-9 min-w-0 flex-1 rounded-xl border border-gray-200 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-centros/30" />
              <button class="h-9 shrink-0 rounded-full bg-centros/10 px-4 text-sm font-semibold text-centros hover:bg-centros/15 disabled:opacity-50" :disabled="!textoDia.trim() || guardando">Añadir</button>
            </form>
          </article>
      </div>

      <!-- Fila inferior: tareas y notas, lado a lado en pantallas anchas -->
      <div class="grid grid-cols-1 gap-4 @3xl/page:grid-cols-2">

          <!-- Tareas pendientes: mismas que en el panel docente -->
          <article class="card flex min-w-0 flex-col p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
              <h2 class="card-title text-base">Tareas pendientes</h2>
              <span v-if="pendientes" class="rounded-full bg-alumnos/15 px-2 py-0.5 text-xs font-bold text-alumnos-dark">{{ pendientes }}</span>
            </div>
            <p v-if="errorTareas" class="mb-2 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700">{{ errorTareas }}</p>
            <ul v-if="listaTareas.length" class="space-y-0.5">
              <li v-for="item in tareasPagina" :key="item.key" class="group flex min-h-11 items-center gap-2.5 rounded-lg px-1.5 text-sm hover:bg-gray-50">
                <template v-if="item.tipo === 'auto'">
                  <button role="switch" :aria-checked="item.hecha" :aria-label="`Marcar como hecha: ${item.t.texto}`"
                          class="interruptor" :class="item.hecha ? 'bg-empresas' : (item.t.nivel === 'alta' ? 'bg-alumnos/30' : 'bg-gray-200')"
                          @click="toggleAuto(item.t)">
                    <span class="interruptor-bola" :class="item.hecha && 'translate-x-3.5'" />
                  </button>
                  <button type="button" class="min-w-0 flex-1 self-stretch py-2 text-left leading-snug" :class="item.hecha ? 'text-gray-400 line-through' : 'text-gray-700 hover:text-centros'" @click="router.push(item.t.ruta)">
                    {{ item.t.texto }}
                  </button>
                </template>
                <template v-else>
                  <button role="switch" :aria-checked="item.hecha" :aria-label="`Marcar como hecha: ${item.n.text}`"
                          class="interruptor" :class="item.hecha ? 'bg-empresas' : 'bg-gray-200'" @click="toggleTarea(item.n.id)">
                    <span class="interruptor-bola" :class="item.hecha && 'translate-x-3.5'" />
                  </button>
                  <span class="min-w-0 flex-1 break-words leading-snug" :class="item.hecha ? 'text-gray-400 line-through' : 'text-gray-700'">{{ item.n.text }}</span>
                  <button type="button" class="-my-1.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg leading-none text-gray-400 transition hover:bg-red-50 hover:text-red-500 sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
                          :aria-label="`Borrar tarea: ${item.n.text}`" @click="borrarTarea(item.n.id)">×</button>
                </template>
              </li>
            </ul>
            <p v-else class="py-4 text-center text-sm text-gray-500">{{ cargando ? 'Cargando…' : 'Todo al día 🎉' }}</p>
            <PaginacionFlechas v-model="paginaTareas" :total="totalPagTareas" etiqueta="Páginas de tareas pendientes" class="mt-2" />
            <!-- mt-auto: el formulario queda al pie aunque la tarjeta se estire a la altura de "Mis notas" -->
            <form class="mt-auto flex gap-2 pt-2" @submit.prevent="enviarTarea">
              <input v-model="nuevaTarea" maxlength="200" placeholder="Añadir tarea personal…"
                     class="h-9 min-w-0 flex-1 rounded-xl border border-gray-200 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-centros/30" />
              <button class="h-9 shrink-0 rounded-full bg-centros/10 px-4 text-sm font-semibold text-centros hover:bg-centros/15 disabled:opacity-50" :disabled="!nuevaTarea.trim()">Añadir</button>
            </form>
          </article>

          <!-- Notas: libres (sin fecha) y próximas con fecha -->
          <article class="card flex min-w-0 flex-col p-4">
            <h2 class="card-title text-base">Mis notas</h2>
            <p v-if="errorNotas" class="mt-2 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700">{{ errorNotas }}</p>

            <template v-if="proximasNotas.length">
              <p class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Próximas</p>
              <ul class="mt-1 space-y-0.5">
                <li v-for="n in proximasNotas" :key="n.id">
                  <button class="flex w-full items-start gap-2 rounded-lg p-1.5 text-left text-sm hover:bg-gray-50" @click="verNotaEnCalendario(n)">
                    <span class="shrink-0 pt-px text-xs font-semibold capitalize text-alumnos-dark">{{ _pf(n.fecha).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' }) }}</span>
                    <span class="min-w-0 flex-1 truncate text-gray-700">{{ n.texto }}</span>
                  </button>
                </li>
              </ul>
            </template>

            <p class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Sin fecha</p>
            <ul v-if="notasSinFecha.length" class="mt-1 space-y-1">
              <li v-for="n in notasPagina" :key="n.id" class="group flex items-start gap-2 rounded-lg bg-gray-50 p-2 text-sm">
                <span class="min-w-0 flex-1 whitespace-pre-line break-words text-gray-700">{{ n.texto }}</span>
                <button type="button" class="-my-1.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg leading-none text-gray-400 transition hover:bg-red-50 hover:text-red-500 sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
                        :aria-label="`Borrar nota: ${n.texto}`" @click="eliminarNota(n)">×</button>
              </li>
            </ul>
            <p v-else class="mt-1 text-sm text-gray-500">{{ cargando ? 'Cargando…' : 'Aún no tienes notas sueltas.' }}</p>
            <PaginacionFlechas v-model="paginaNotas" :total="totalPagNotas" etiqueta="Páginas de notas sin fecha" class="mt-2" />
            <form class="mt-auto space-y-2 pt-3" @submit.prevent="addNotaLibre">
              <textarea v-model="textoLibre" maxlength="1000" rows="2" placeholder="Escribe una nota…"
                        class="block w-full resize-none rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-centros/30" />
              <button class="h-9 w-full rounded-full bg-centros/10 text-sm font-semibold text-centros hover:bg-centros/15 disabled:opacity-50" :disabled="!textoLibre.trim() || guardando">Guardar nota</button>
            </form>
          </article>

      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.card        { @apply rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70; }
.card-title  { @apply font-heading text-lg font-bold text-azul-noche; }
.cal-nav     { @apply flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-centros; }
.interruptor { @apply relative inline-flex h-4 w-7.5 shrink-0 items-center rounded-full p-0.5 transition-colors; }
.interruptor-bola { @apply h-3 w-3 rounded-full bg-white shadow transition-transform; }
/* Zona táctil de ~36px alrededor del interruptor (16px de alto a la vista) */
.interruptor::before { content: ''; position: absolute; inset: -10px -4px; }
</style>
