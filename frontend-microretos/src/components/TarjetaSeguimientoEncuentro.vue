<!-- Tarjeta de un encuentro en la pestaña «Seguimiento de grupos» de la biblioteca de encuentros
     (EncuentrosRegistrados.vue, ?ver=seguimiento). Antes era la tarjeta de Mis grupos (/mis-grupos):
     resumen del estado de sus grupos y, desplegados, el avance de cada uno fase a fase. El trabajo
     completo de cada grupo vive en «Detalle de grupos» (MisGruposDetalle.vue).

     El padre guarda qué está desplegado (para poder abrir un encuentro o un grupo al llegar desde
     un enlace) y muestra el aviso al copiar un código. -->
<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import FamiliaModulos from './FamiliaModulos.vue'
import ChipDocente from './ChipDocente.vue'
import RolGrupo from './RolGrupo.vue'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'
import { modulosDeEncuentro } from '../utils/modulos.js'
import { nombreGrupo } from '../utils/nombreGrupo.js'

const props = defineProps({
  encuentro:       { type: Object, required: true },   // EncuentroResource (/encuentros)
  equipos:         { type: Array, required: true },    // grupos a mostrar (ya filtrados por estado)
  abierto:         { type: Boolean, default: false },
  equiposAbiertos: { type: Set, default: () => new Set() },
  resaltado:       { type: Boolean, default: false },
  moduloActivo:    { type: String, default: '' },
})
const emit = defineEmits(['toggle', 'toggle-equipo', 'desplegar-todo', 'mas-informacion', 'copiar'])

const router = useRouter()
const e = computed(() => props.encuentro)

const progresoPct = (equipo) => progresoPonderado(equipo.fases)

function formatoFecha(fecha) {
  if (!fecha) return ''
  return new Date(String(fecha).slice(0, 10) + 'T12:00:00').toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Estado del chip de fase (color + texto): resumen; el detalle real vive en «Detalle de grupos».
function estadoFase(equipo, faseNum) {
  if (equipo.fases[faseNum]?.validado_docente) return { label: 'Validado', cls: 'bg-emerald-500 text-white' }
  if (equipo.fases[faseNum]?.completada)       return { label: 'Completa', cls: 'bg-centros/20 text-centros' }
  if (equipo.fase_actual === faseNum)          return { label: 'En curso', cls: 'bg-blue-100 text-blue-600 ring-1 ring-blue-300' }
  return { label: 'Pendiente', cls: 'bg-gray-100 text-gray-400' }
}

// Mismo criterio que el filtro «Estado de los grupos» (estadoEquipo en EncuentrosRegistrados.vue)
function estadoEquipo(equipo) {
  if (equipo.fases_completas === 5) return 'completado'
  if (equipo.fase_actual === 0 && equipo.fases_completas === 0) return 'sin_iniciar'
  return 'en_curso'
}

// `borde`: franja lateral de la fila del grupo, para distinguir el estado antes de desplegarla.
function estadoBadge(equipo) {
  const estado = estadoEquipo(equipo)
  if (estado === 'completado')  return { label: '✓ Completado', cls: 'bg-emerald-100 text-emerald-700', borde: 'border-l-emerald-500' }
  if (estado === 'sin_iniciar') return { label: 'Sin iniciar', cls: 'bg-gray-100 text-gray-500', borde: 'border-l-gray-300' }
  const fa = equipo.fase_actual
  return { label: `En curso · F${fa} ${FASES_PROYECTO[fa]?.label ?? ''}`, cls: 'bg-blue-100 text-blue-700', borde: 'border-l-blue-500' }
}

const resumen = computed(() => ({
  completados:  props.equipos.filter(x => estadoEquipo(x) === 'completado').length,
  enCurso:      props.equipos.filter(x => estadoEquipo(x) === 'en_curso').length,
  sinIniciar:   props.equipos.filter(x => estadoEquipo(x) === 'sin_iniciar').length,
  diagnosticos: props.equipos.filter(x => x.diagnostico_final).length,
}))

const titulo = computed(() => e.value.proyecto_titulo || props.equipos[0]?.proyecto?.titulo
  || (e.value.grupo && `Clase ${e.value.grupo}`) || 'Sin nombre')

const irADetalle     = (equipo = null) => router.push({ name: 'mis-grupos-detalle', params: { id: e.value.id }, query: equipo ? { equipo: equipo.id } : {} })
const irADiagnostico = (equipo) => router.push({ name: 'mis-grupos-detalle', params: { id: e.value.id }, query: { equipo: equipo.id, ver: 'diagnostico' } })
const primerDiagnostico = computed(() => props.equipos.find(x => x.diagnostico_final) || null)

// ── Menú «⋯» ─────────────────────────────────────────────────────────────────
// Cierre al pulsar fuera o con Escape (un overlay fixed quedaría por debajo del menú)
const menu = ref(false)
const raizMenu = ref(null)
function cerrarMenuFuera(ev) { if (!raizMenu.value?.contains(ev.target)) menu.value = false }
function cerrarMenuEsc(ev) { if (ev.key === 'Escape') menu.value = false }
watch(menu, (abierto) => {
  const m = abierto ? 'addEventListener' : 'removeEventListener'
  document[m]('click', cerrarMenuFuera, true)
  document[m]('keydown', cerrarMenuEsc)
})
onUnmounted(() => {
  document.removeEventListener('click', cerrarMenuFuera, true)
  document.removeEventListener('keydown', cerrarMenuEsc)
})
function accionMenu(fn) { menu.value = false; fn() }
</script>

<template>
  <!-- Sin overflow-hidden: el menú «⋯» no debe recortarse. Con el menú abierto, por encima
       de la tarjeta siguiente. -->
  <div :id="`seguimiento-${e.id}`"
       class="relative scroll-mt-24 rounded-2xl border bg-white shadow-sm transition-shadow duration-500"
       :class="[{ 'z-10': menu }, resaltado ? 'border-rol-fuerte/50 ring-4 ring-rol/30' : 'border-borde']">

    <div class="px-4 pb-3 pt-4 sm:px-5">
      <!-- La información también despliega los grupos (atajo del ratón; el botón accesible está en el pie) -->
      <div class="min-w-0 cursor-pointer" @click="emit('toggle')">
        <p class="font-heading text-base font-bold leading-snug text-azul-noche">
          {{ titulo }}
          <span v-if="e.fecha" class="font-sans text-sm font-medium text-texto-secundario">· {{ formatoFecha(e.fecha) }}</span>
        </p>
        <p class="mt-0.5 text-sm text-texto-secundario">{{ e.ciclo_formativo }} · {{ equipos.length }} {{ equipos.length === 1 ? 'grupo' : 'grupos' }}<template v-if="e.grupo"> · Clase {{ e.grupo }}</template></p>
        <FamiliaModulos class="mt-2" :familia="e.familia_nombre || 'Sin familia asignada'" :modulos="modulosDeEncuentro(e)" :modulo-activo="moduloActivo" />
        <!-- Estado de sus grupos sin tener que desplegar -->
        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
          <span v-if="resumen.completados"
                class="inline-flex items-center gap-1 rounded-full bg-empresas/10 px-2 py-0.5 text-xs font-semibold text-empresas-dark">
            ✓ {{ resumen.completados }} completado{{ resumen.completados === 1 ? '' : 's' }}
          </span>
          <span v-if="resumen.enCurso"
                class="inline-flex items-center gap-1 rounded-full bg-centros/10 px-2 py-0.5 text-xs font-semibold text-centros">
            <span class="h-1.5 w-1.5 rounded-full bg-centros"></span>
            {{ resumen.enCurso }} en curso
          </span>
          <span v-if="resumen.sinIniciar"
                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-texto-secundario">
            {{ resumen.sinIniciar }} sin iniciar
          </span>
          <span v-if="resumen.diagnosticos"
                class="inline-flex items-center gap-1 rounded-full border border-empresas/40 bg-white px-2 py-0.5 text-xs font-semibold text-empresas-dark">
            📊 {{ resumen.diagnosticos }} diagnóstico{{ resumen.diagnosticos === 1 ? '' : 's' }} disponible{{ resumen.diagnosticos === 1 ? '' : 's' }}
          </span>
        </div>
        <!-- Encuentro de un compañero que me ha dado permiso como colaborador -->
        <ChipDocente v-if="e.es_propietario === false && e.propietario_nombre" class="mt-1.5"
                     :nombre="e.propietario_nombre" :solo-lectura="!e.puede_editar" />
      </div>
    </div>

    <!-- Pie: desplegar los grupos a la izquierda; «Detalle de grupos» y «⋯» a la derecha -->
    <div class="flex flex-wrap items-center gap-2 border-t border-borde px-4 py-2.5 sm:px-5">
      <button type="button" @click="emit('toggle')" :aria-expanded="abierto"
              class="inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-azul-noche hover:bg-gray-100">
        <svg :class="['h-4 w-4 text-texto-secundario transition-transform', abierto ? 'rotate-180' : '']"
             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
        {{ abierto ? 'Ocultar los grupos' : `Ver los grupos (${equipos.length})` }}
      </button>
      <div class="ml-auto flex items-center gap-2">
        <button type="button" @click="irADetalle()"
                class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-rol-fuerte px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rol-fuerte/90">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          Detalle de grupos
        </button>
        <!-- Menú «⋯»: el resto de acciones del encuentro -->
        <div ref="raizMenu" class="relative shrink-0">
          <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-haspopup="menu"
                  :aria-label="`Más acciones de «${titulo}»`"
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde text-texto-secundario transition-colors hover:bg-gray-50"
                  :class="{ 'bg-gray-50': menu }">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
          </button>
          <div v-if="menu" role="menu"
               class="absolute right-0 top-full z-30 mt-1 w-[min(17rem,calc(100vw-3rem))] rounded-xl border border-borde bg-white py-1 shadow-lg">
            <button type="button" role="menuitem" @click="accionMenu(() => emit('mas-informacion'))"
                    class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Más información del encuentro</button>
            <button v-if="equipos.length > 1" type="button" role="menuitem" @click="accionMenu(() => emit('desplegar-todo'))"
                    class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Desplegar el resumen de todos</button>
            <button v-if="primerDiagnostico" type="button" role="menuitem" @click="accionMenu(() => irADiagnostico(primerDiagnostico))"
                    class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Ver diagnósticos ({{ resumen.diagnosticos }})</button>
            <template v-if="e.codigo_clase || e.codigo_ia">
              <div class="my-1 border-t border-borde" role="separator" />
              <button v-if="e.codigo_clase" type="button" role="menuitem" @click="accionMenu(() => emit('copiar', e.codigo_clase))"
                      class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">
                <span class="min-w-0 flex-1">Copiar código de acceso al alumnado</span>
                <span class="shrink-0 text-xs font-bold tracking-wider text-texto-secundario">{{ e.codigo_clase }}</span>
              </button>
              <button v-if="e.codigo_ia" type="button" role="menuitem" @click="accionMenu(() => emit('copiar', e.codigo_ia))"
                      class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">
                <span class="min-w-0 flex-1">Copiar código sugerencia IA</span>
                <span class="shrink-0 text-xs font-bold tracking-wider text-alumnos-dark">{{ e.codigo_ia }}</span>
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div v-if="abierto" class="space-y-3 border-t border-borde px-4 py-4 sm:px-5">
      <div v-for="equipo in equipos" :key="equipo.id" :id="`seguimiento-equipo-${equipo.id}`"
           :class="['scroll-mt-24 overflow-hidden rounded-xl border border-l-4 border-borde', estadoBadge(equipo).borde]">

        <button type="button" @click="emit('toggle-equipo', equipo.id)" :aria-expanded="equiposAbiertos.has(equipo.id)"
                class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50">
          <div class="relative h-10 w-10 shrink-0">
            <svg class="h-10 w-10 -rotate-90" viewBox="0 0 48 48" aria-hidden="true">
              <circle cx="24" cy="24" r="20" fill="none" stroke="#F3F4F6" stroke-width="4"/>
              <circle cx="24" cy="24" r="20" fill="none" stroke="#3072AA" stroke-width="4"
                      :stroke-dasharray="`${progresoPct(equipo) * 1.257} 125.7`" stroke-linecap="round"/>
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-[11px] font-semibold text-centros">
              {{ progresoPct(equipo) }}%
            </span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-sm font-bold text-azul-noche">{{ nombreGrupo(equipo) }}</p>
              <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', estadoBadge(equipo).cls]">
                {{ estadoBadge(equipo).label }}
              </span>
              <span v-if="equipo.fases_completas === 5"
                    :class="['rounded-full px-2 py-0.5 text-xs font-semibold',
                             equipo.diagnostico_final ? 'border border-empresas/40 bg-white text-empresas-dark' : 'bg-amber-100 text-amber-700']">
                {{ equipo.diagnostico_final ? '📊 Diagnóstico listo' : 'Diagnóstico pendiente' }}
              </span>
            </div>
            <p v-if="equipo.proyecto" class="truncate text-[11px] font-semibold text-texto-secundario">
              {{ equipo.proyecto.titulo }}
              <span v-if="equipo.proyecto.familia" class="text-texto-secundario">· {{ equipo.proyecto.familia }}</span>
            </p>
            <div class="mt-1 flex flex-wrap gap-1">
              <!-- Dentro del botón de la fila no cabe otro botón: el rol, sin «i» (explicación en el title) -->
              <span v-for="m in equipo.miembros" :key="m.id"
                    class="inline-flex max-w-full items-center gap-1 rounded-full bg-gray-100 py-0.5 pl-2 pr-0.5 text-xs font-semibold text-azul-noche">
                {{ m.alias }}
                <RolGrupo v-if="m.rol" :rol="m.rol" :ayuda="false" />
              </span>
            </div>
          </div>
          <svg :class="['h-3.5 w-3.5 shrink-0 text-texto-secundario transition-transform', equiposAbiertos.has(equipo.id) ? 'rotate-180' : '']"
               fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>

        <div v-if="equiposAbiertos.has(equipo.id)" class="space-y-3 border-t border-borde px-4 pb-4 pt-3">
          <!-- Aviso destacado de diagnóstico — lo primero del desplegable cuando el grupo ha
               terminado, con acceso directo a su sección en el detalle del encuentro. -->
          <div v-if="equipo.fases_completas === 5"
               class="flex flex-col gap-3 rounded-2xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4 sm:flex-row sm:items-center">
            <div class="flex min-w-0 flex-1 items-start gap-3">
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-base text-white">📊</span>
              <div class="min-w-0">
                <p class="text-sm font-black text-emerald-800">Aquí puedes ver el diagnóstico del grupo</p>
                <p class="text-[11px] leading-snug text-emerald-700/80">
                  {{ equipo.diagnostico_final
                      ? 'La IA ya lo ha redactado a partir de lo trabajado. Puedes revisarlo, editarlo o descargarlo en PDF.'
                      : 'El grupo ha completado las 5 fases. Genera su diagnóstico final con IA a partir de lo trabajado.' }}
                </p>
              </div>
            </div>
            <button type="button" @click="irADiagnostico(equipo)"
                    :class="['inline-flex min-h-9 shrink-0 items-center justify-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-wider transition-colors',
                             equipo.diagnostico_final
                               ? 'bg-emerald-500 text-white shadow-md shadow-emerald-200 hover:bg-emerald-600'
                               : 'border border-empresas/40 bg-white text-empresas-dark hover:bg-emerald-50']">
              {{ equipo.diagnostico_final ? 'Ver diagnóstico' : 'Ir a generar diagnóstico' }} →
            </button>
          </div>

          <!-- Deja claro que esto es solo un resumen de estado, no el contenido de las fases -->
          <div class="flex items-start gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3 py-2">
            <span class="shrink-0 text-sm leading-none">ℹ️</span>
            <p class="text-[11px] leading-snug text-blue-700">
              <span class="font-black">Resumen.</span> Haz clic en "Ver detalle del grupo" para ver su trabajo fase a fase.
            </p>
          </div>

          <!-- Resumen de fases — solo estado, sin contenido -->
          <div class="flex flex-wrap gap-1.5">
            <div v-for="f in FASES_PROYECTO" :key="f.num" :title="f.label"
                 :class="['flex items-center gap-1.5 rounded-full px-2 py-1 text-[11px] font-semibold', estadoFase(equipo, f.num).cls]">
              <span class="text-xs leading-none">{{ f.icono }}</span>
              <span class="uppercase tracking-wider">F{{ f.num }}</span>
              <span class="font-bold normal-case">· {{ estadoFase(equipo, f.num).label }}</span>
            </div>
          </div>

          <div v-if="equipo.codigo_acceso" class="flex flex-wrap items-center gap-1.5">
            <span class="text-[11px] font-semibold uppercase tracking-widest text-texto-secundario">Código de acceso del grupo</span>
            <!-- Botón con zona táctil de 36px; la píldora visible conserva su tamaño -->
            <button type="button" @click.stop="emit('copiar', equipo.codigo_acceso)"
                    title="Copiar código de acceso del grupo"
                    :aria-label="`Copiar código de acceso del grupo ${equipo.codigo_acceso}`"
                    class="inline-flex min-h-9 cursor-pointer items-center rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-centros/40">
              <span class="flex items-center gap-1 rounded-full border border-centros/20 bg-centros/10 px-2 py-0.5">
                <span class="h-1 w-1 shrink-0 rounded-full bg-centros"></span>
                <span class="text-xs font-semibold tracking-wider text-centros">{{ equipo.codigo_acceso }}</span>
              </span>
            </button>
          </div>

          <p v-if="equipo.reflexiones.length" class="text-[11px] font-semibold uppercase tracking-widest text-texto-secundario">
            {{ equipo.reflexiones.length }} reflexión(es) registrada(s)
          </p>

          <!-- Workspace del grupo en solo lectura (vista del docente, en otra pestaña) -->
          <div class="flex justify-end">
            <router-link :to="{ name: 'equipo-workspace-docente', params: { equipoId: equipo.id } }"
                         target="_blank" rel="noopener"
                         class="inline-flex h-9 items-center gap-1.5 rounded-full border border-violet-200 bg-violet-50 px-4 text-xs font-semibold text-violet-700 transition-colors hover:bg-violet-100">
              <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
              Ver en workspace <span class="font-normal opacity-80">(solo lectura)</span>
            </router-link>
          </div>

          <button type="button" @click="irADetalle(equipo)"
                  class="inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-centros py-3 text-xs font-black uppercase tracking-wider text-white shadow-md shadow-centros/20
                         transition-all hover:bg-centros/90 hover:shadow-lg">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            Ver detalle del grupo →
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
