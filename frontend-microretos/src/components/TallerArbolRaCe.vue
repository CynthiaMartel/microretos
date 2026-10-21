<!-- Árbol Módulo → RA → CE del Taller de Ideas, paso 3 (diseño crear_proyecto.png).
     Presentacional: el estado vive en StartupDayWizard (form.evaluacion_oficial, catálogo,
     ceChecked) y aquí solo se emiten los cambios.
     · Casilla del módulo = módulo trabajado en la propuesta (carga su catálogo oficial).
     · Casillas de RA y CE = RA/CE de esta propuesta; se aplican al marcar.
     · «Del reto» y «Sugerencia IA»: etiqueta con icono y texto, no solo color.
     · RA/CE añadidos a mano (o que no están en el catálogo cargado): debajo de su módulo,
       con menú «⋯» para quitarlos. Los códigos y textos oficiales no se editan aquí. -->
<script setup>
import { ref, computed } from 'vue'
import { separarCodigo } from '../utils/curriculo.js'

const props = defineProps({
  modulos:       { type: Array, required: true },   // módulos del ciclo [{ id, nombre, codigoBOE }]
  seleccionados: { type: Array, required: true },   // ids de módulos de la propuesta
  catalogo:      { type: Array, required: true },   // catálogo (filtrado por la búsqueda) [{ moduloId, modulo, ras }]
  raIdsCatalogo: { type: Object, required: true },  // Set con los ra_id de todo el catálogo cargado
  cargando:      { type: Boolean, default: false },
  ceChecked:     { type: Object, required: true },  // { ceId: bool }
  evaluacion:    { type: Array, required: true },   // form.evaluacion_oficial
  ceIdsReto:     { type: Object, required: true },  // Set
  ceIdsIa:       { type: Object, required: true },  // Set
  textosIa:      { type: Object, required: true },  // Set de RA (texto normalizado) añadidos por la IA sin id
  busqueda:      { type: String, default: '' },
  // RA/CE manuales o fuera del catálogo bajo su módulo. En el panel «Añadir otros» del
  // paso 3 van a false: ya se ven en la selección de la propuesta.
  mostrarExtras: { type: Boolean, default: true },
  // false cuando el buscador va fuera (barra fija del modal «Seleccionar otros»).
  conBuscador:   { type: Boolean, default: true },
})
const emit = defineEmits(['update:busqueda', 'toggle-modulo', 'toggle-ra', 'toggle-ce', 'quitar-entrada', 'quitar-ce-entrada'])

const abierto = ref({})   // { moduloId: bool } — sin valor = abierto si está seleccionado
const raCerrado = ref({}) // { raId: true }
const menu = ref(null)    // clave del menú «⋯» abierto

const seleccionado = id => props.seleccionados.includes(id)
// Módulos marcados primero (como en el diseño), luego el resto en su orden.
const modulosOrdenados = computed(() => [
  ...props.modulos.filter(m => seleccionado(m.id)),
  ...props.modulos.filter(m => !seleccionado(m.id)),
])
const estaAbierto = m => abierto.value[m.id] ?? seleccionado(m.id)
function alternar(m) { abierto.value[m.id] = !estaAbierto(m) }

// «Plegar todo» / «Desplegar todo» (barra del modal): módulos y criterios a la vez.
function plegarTodo() {
  props.modulos.forEach(m => { abierto.value[m.id] = false })
}
function desplegarTodo() {
  props.modulos.forEach(m => { abierto.value[m.id] = seleccionado(m.id) })
  raCerrado.value = {}
}
defineExpose({ plegarTodo, desplegarTodo })

const catalogoPorModulo = computed(() => {
  const mapa = {}
  props.catalogo.forEach(c => { mapa[c.moduloId] = c })
  return mapa
})

// Entradas de la propuesta que no salen del catálogo cargado (manuales, o de un módulo
// sin catálogo), agrupadas por nombre de módulo, con su índice real en evaluacion_oficial.
const extrasPorModulo = computed(() => {
  const mapa = {}
  props.evaluacion.forEach((e, i) => {
    if (e.ra_id && props.raIdsCatalogo.has(e.ra_id)) return
    const clave = e.modulo || 'Sin módulo'
    ;(mapa[clave] ||= []).push({ ...e, _i: i })
  })
  return mapa
})
const nombresModulos = computed(() => new Set(props.modulos.map(m => m.nombre)))
const extrasHuerfanos = computed(() =>
  Object.entries(extrasPorModulo.value).filter(([mod]) => !nombresModulos.value.has(mod))
)

function conteo(nombre) {
  const entradas = props.evaluacion.filter(e => (e.modulo || 'Sin módulo') === nombre)
  return { ra: entradas.length, ce: entradas.reduce((n, e) => n + (e.ce?.length || 0), 0) }
}

function estadoRa(ra) {
  const n = ra.criterios.filter(ce => props.ceChecked[ce.id]).length
  if (!n) return 'none'
  return n === ra.criterios.length ? 'all' : 'some'
}
function cesMarcados(ra) { return ra.criterios.filter(ce => props.ceChecked[ce.id]).length }

function normalizar(t) { return (t || '').trim().toLowerCase().replace(/\s+/g, ' ') }

function abrirMenu(clave) { menu.value = menu.value === clave ? null : clave }
function quitarEntrada(i) { menu.value = null; emit('quitar-entrada', i) }
function quitarCe(i, j) { menu.value = null; emit('quitar-ce-entrada', i, j) }
</script>

<template>
  <div class="space-y-3" @keydown.esc="menu = null">

    <!-- Buscador -->
    <div v-if="conBuscador && seleccionados.length">
      <label for="buscar-ra-ce" class="sr-only">Buscar en los RA y CE de los módulos marcados</label>
      <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
        </svg>
        <input id="buscar-ra-ce" type="search" :value="busqueda"
               @input="emit('update:busqueda', $event.target.value)"
               placeholder="Buscar resultado de aprendizaje o criterio…"
               class="w-full rounded-xl py-2 pl-9 pr-3 text-sm" />
      </div>
    </div>

    <p v-if="!modulos.length" class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
      Selecciona un ciclo en el paso 1 para ver sus módulos.
    </p>

    <!-- Módulos -->
    <div v-for="m in modulosOrdenados" :key="m.id"
         class="rounded-xl border"
         :class="seleccionado(m.id) ? 'border-rol/25' : 'border-gray-100'">

      <!-- Sin overflow-hidden en la tarjeta: recortaría el menú «⋯» -->
      <div class="flex items-center gap-2 rounded-t-xl bg-gray-50/80 px-3 py-2 sm:gap-3 sm:px-4"
           :class="estaAbierto(m) ? '' : 'rounded-b-xl'">
        <input type="checkbox" :id="`mod-${m.id}`" :checked="seleccionado(m.id)"
               @change="emit('toggle-modulo', m)"
               class="h-4 w-4 shrink-0 cursor-pointer accent-rol-fuerte" />
        <span class="hidden shrink-0 rounded-full bg-rol/10 px-2 py-0.5 text-xs font-bold text-rol-fuerte sm:inline">MF</span>
        <label :for="`mod-${m.id}`" class="min-w-0 flex-1 cursor-pointer py-1.5">
          <span class="block break-words text-sm font-semibold text-azul-noche">{{ m.nombre }}</span>
          <span v-if="m.codigoBOE" class="block text-xs text-gray-500">{{ m.codigoBOE }}</span>
        </label>
        <span v-if="conteo(m.nombre).ra" class="hidden shrink-0 text-xs font-medium text-gray-600 sm:inline">
          {{ conteo(m.nombre).ra }} RA · {{ conteo(m.nombre).ce }} CE
        </span>
        <button type="button" @click="alternar(m)"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
                :aria-expanded="estaAbierto(m)" :aria-controls="`mod-cont-${m.id}`"
                :aria-label="`${estaAbierto(m) ? 'Plegar' : 'Desplegar'} ${m.nombre}`">
          <svg class="h-4 w-4 transition-transform" :class="estaAbierto(m) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
      </div>
      <p v-if="conteo(m.nombre).ra" class="border-t border-gray-100 px-4 py-1 text-xs text-gray-600 sm:hidden">
        {{ conteo(m.nombre).ra }} RA · {{ conteo(m.nombre).ce }} CE seleccionados
      </p>

      <div v-if="estaAbierto(m)" :id="`mod-cont-${m.id}`" class="space-y-2 border-t border-gray-100 p-2 sm:p-3">

        <p v-if="!seleccionado(m.id) && !(mostrarExtras && extrasPorModulo[m.nombre])" class="px-2 py-2 text-sm text-gray-500">
          Marca el módulo para ver sus resultados de aprendizaje.
        </p>

        <!-- Catálogo oficial -->
        <template v-if="seleccionado(m.id)">
          <div v-if="cargando && !catalogoPorModulo[m.id]" class="space-y-2 p-2" aria-busy="true">
            <div class="h-4 w-3/4 animate-pulse rounded-full bg-gray-200" />
            <div class="h-4 w-1/2 animate-pulse rounded-full bg-gray-200" />
            <span class="sr-only">Cargando resultados de aprendizaje…</span>
          </div>
          <p v-else-if="!catalogoPorModulo[m.id]?.ras.length && !busqueda" class="px-2 py-2 text-sm text-gray-500">
            Este módulo no tiene RA/CE oficiales cargados. Puedes añadirlos a mano más abajo.
          </p>
          <p v-else-if="!catalogoPorModulo[m.id]?.ras.length" class="px-2 py-2 text-sm text-gray-500">
            Sin resultados para «{{ busqueda }}» en este módulo.
          </p>

          <div v-for="ra in catalogoPorModulo[m.id]?.ras || []" :key="ra.id"
               class="rounded-xl border border-gray-100 bg-white">
            <div class="flex items-start gap-2 px-3 py-2 sm:gap-3">
              <input type="checkbox" :id="`ra-${ra.id}`"
                     :checked="estadoRa(ra) === 'all'" :indeterminate="estadoRa(ra) === 'some'"
                     @change="emit('toggle-ra', ra, catalogoPorModulo[m.id])"
                     class="mt-2 h-4 w-4 shrink-0 cursor-pointer accent-rol-fuerte" />
              <label :for="`ra-${ra.id}`" class="flex min-w-0 flex-1 cursor-pointer flex-col gap-1 py-1 sm:flex-row sm:items-start sm:gap-3">
                <span class="w-fit shrink-0 rounded-full bg-rol/10 px-2 py-0.5 text-xs font-bold text-rol-fuerte">RA {{ ra.orden }}</span>
                <span class="min-w-0 break-words text-sm text-gray-800">{{ separarCodigo(ra.descripcion).texto }}</span>
              </label>
              <span class="mt-1.5 hidden shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 sm:inline">
                {{ cesMarcados(ra) }}/{{ ra.criterios.length }} CE
              </span>
              <button type="button" @click="raCerrado[ra.id] = !raCerrado[ra.id]"
                      class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
                      :aria-expanded="!raCerrado[ra.id]"
                      :aria-label="`${raCerrado[ra.id] ? 'Mostrar' : 'Ocultar'} criterios del RA ${ra.orden}`">
                <svg class="h-4 w-4 transition-transform" :class="raCerrado[ra.id] ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
            </div>

            <fieldset v-if="!raCerrado[ra.id]" class="border-t border-gray-100 px-3 pb-2 pt-2 sm:pl-10">
              <legend class="sr-only">Criterios de evaluación del RA {{ ra.orden }}</legend>
              <p class="mb-1 text-xs font-bold uppercase tracking-wide text-alumnos-dark" aria-hidden="true">Criterios de evaluación</p>
              <label v-for="ce in ra.criterios" :key="ce.id"
                     class="flex cursor-pointer items-start gap-2.5 rounded-lg px-1 py-1.5 hover:bg-gray-50">
                <input type="checkbox" :checked="!!ceChecked[ce.id]"
                       @change="emit('toggle-ce', ce, ra, catalogoPorModulo[m.id])"
                       class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-rol-fuerte" />
                <span class="w-8 shrink-0 text-sm font-semibold text-gray-600">{{ separarCodigo(ce.descripcion, `${ra.orden}.${ce.orden}`).codigo }}</span>
                <span class="min-w-0 flex-1 break-words text-sm text-gray-700">
                  {{ separarCodigo(ce.descripcion).texto }}
                  <span v-if="ceIdsReto.has(ce.id)"
                        class="ml-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-amber-50 px-2 py-0.5 align-middle text-xs font-semibold text-amber-800">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    Del reto
                  </span>
                  <span v-if="ceIdsIa.has(ce.id)"
                        class="ml-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-violet-50 px-2 py-0.5 align-middle text-xs font-semibold text-violet-800">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    Sugerencia IA
                  </span>
                </span>
              </label>
            </fieldset>
          </div>
        </template>

        <!-- Añadidos a mano / fuera del catálogo cargado -->
        <div v-for="e in (mostrarExtras ? extrasPorModulo[m.nombre] || [] : [])" :key="`x-${e._i}`"
             class="rounded-xl border border-dashed border-gray-200 bg-white">
          <div class="flex items-start gap-2 px-3 py-2 sm:gap-3">
            <span class="mt-1 w-fit shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-600">
              {{ e.ra_id ? 'RA' : 'RA propio' }}
            </span>
            <p class="min-w-0 flex-1 break-words py-0.5 text-sm text-gray-800">
              {{ e.ra }}
              <span v-if="!e.ra_id && textosIa.has(normalizar(e.ra))"
                    class="ml-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-violet-50 px-2 py-0.5 align-middle text-xs font-semibold text-violet-800">
                Sugerencia IA
              </span>
            </p>
            <div class="relative shrink-0">
              <button type="button" @click="abrirMenu(`ra-${e._i}`)"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
                      :aria-expanded="menu === `ra-${e._i}`" aria-haspopup="menu"
                      :aria-label="`Opciones del RA «${e.ra}»`">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
              </button>
              <div v-if="menu === `ra-${e._i}`" role="menu"
                   class="absolute right-0 top-full z-30 mt-1 w-48 rounded-xl border border-gray-100 bg-white py-1 shadow-lg">
                <button type="button" role="menuitem" @click="quitarEntrada(e._i)"
                        class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">
                  Quitar este RA y sus CE
                </button>
              </div>
            </div>
          </div>
          <ul v-if="e.ce?.length" class="border-t border-gray-100 px-3 pb-2 pt-1 sm:pl-10">
            <li v-for="(ce, j) in e.ce" :key="j" class="flex items-start gap-2.5 py-1">
              <svg class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
              <span class="min-w-0 flex-1 break-words text-sm text-gray-700">{{ ce }}</span>
              <div class="relative shrink-0">
                <button type="button" @click="abrirMenu(`ce-${e._i}-${j}`)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
                        :aria-expanded="menu === `ce-${e._i}-${j}`" aria-haspopup="menu"
                        :aria-label="`Opciones del criterio «${ce}»`">
                  <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                </button>
                <div v-if="menu === `ce-${e._i}-${j}`" role="menu"
                     class="absolute right-0 top-full z-30 mt-1 w-44 rounded-xl border border-gray-100 bg-white py-1 shadow-lg">
                  <button type="button" role="menuitem" @click="quitarCe(e._i, j)"
                          class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">
                    Quitar criterio
                  </button>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- RA/CE de la propuesta cuyo módulo no está en el ciclo actual -->
    <div v-for="[mod, entradas] in (mostrarExtras ? extrasHuerfanos : [])" :key="`h-${mod}`"
         class="rounded-xl border border-gray-100 p-3">
      <p class="mb-2 text-sm font-semibold text-azul-noche break-words">{{ mod }}</p>
      <div v-for="e in entradas" :key="`hx-${e._i}`" class="flex items-start gap-2 py-1">
        <p class="min-w-0 flex-1 break-words text-sm text-gray-700">
          <span class="font-semibold">{{ e.ra }}</span>
          <span v-if="e.ce?.length" class="text-gray-500"> · {{ e.ce.length }} CE</span>
        </p>
        <button type="button" @click="quitarEntrada(e._i)"
                class="inline-flex h-8 shrink-0 items-center rounded-full px-3 text-xs font-semibold text-red-700 hover:bg-red-50">
          Quitar
        </button>
      </div>
    </div>

    <!-- Cierra el menú «⋯» al pulsar fuera -->
    <div v-if="menu" class="fixed inset-0 z-20" @click="menu = null" />
  </div>
</template>
