<!-- RA/CE de la propuesta en el paso 3 del Taller de Ideas, agrupados por módulo.
     Empiezan siendo los originales del reto; cada criterio dice de dónde viene
     («Del reto», «Añadido», «Sugerencia IA»: icono + texto, no solo color) y se puede
     quitar con el menú «⋯». Presentacional: los cambios se emiten al wizard. -->
<script setup>
import { ref, computed } from 'vue'
import { separarCodigo, normalizarTexto } from '../utils/curriculo.js'

const props = defineProps({
  evaluacion: { type: Array, required: true },   // form.evaluacion_oficial
  ceIdsReto:  { type: Object, required: true },  // Set con los ce_id del reto
  textosReto: { type: Object, required: true },  // Set con «ra|ce» normalizado del reto (entradas sin id)
  ceIdsIa:    { type: Object, required: true },  // Set
  textosIa:   { type: Object, required: true },  // Set de RA (texto normalizado) añadidos por la IA sin id
})
const emit = defineEmits(['quitar-entrada', 'quitar-ce'])

const menu = ref(null)

const porModulo = computed(() => {
  const grupos = []
  const idx = {}
  props.evaluacion.forEach((e, i) => {
    const mod = e.modulo || 'Sin módulo'
    if (!(mod in idx)) { idx[mod] = grupos.length; grupos.push({ modulo: mod, entradas: [] }) }
    grupos[idx[mod]].entradas.push({ ...e, _i: i })
  })
  return grupos
})

function origenCe(e, j) {
  const id = e.ce_ids?.[j]
  if (id && props.ceIdsIa.has(id)) return 'ia'
  if (!e.ra_id && props.textosIa.has(normalizarTexto(e.ra))) return 'ia'
  if (id && props.ceIdsReto.has(id)) return 'reto'
  if (!id && props.textosReto.has(`${normalizarTexto(e.ra)}|${normalizarTexto(e.ce?.[j])}`)) return 'reto'
  return 'añadido'
}
function numeroRa(e) {
  return separarCodigo(e.ra).codigo.replace(/[.)]/g, '') || ''
}

function abrir(clave) { menu.value = menu.value === clave ? null : clave }
function quitarEntrada(i) { menu.value = null; emit('quitar-entrada', i) }
function quitarCe(i, j) { menu.value = null; emit('quitar-ce', i, j) }
</script>

<template>
  <div class="space-y-3" @keydown.esc="menu = null">
    <div v-for="g in porModulo" :key="g.modulo" class="rounded-xl border border-borde">
      <div class="flex items-center gap-2 rounded-t-xl bg-gray-50/80 px-3 py-2.5 sm:gap-3 sm:px-4">
        <span class="shrink-0 rounded-full bg-rol/10 px-2 py-0.5 text-xs font-bold text-rol-fuerte">MF</span>
        <p class="min-w-0 flex-1 break-words text-sm font-semibold text-azul-noche">{{ g.modulo }}</p>
        <span class="shrink-0 text-xs font-medium text-texto-secundario">
          {{ g.entradas.length }} RA · {{ g.entradas.reduce((n, e) => n + (e.ce?.length || 0), 0) }} CE
        </span>
      </div>

      <div class="space-y-2 p-2 sm:p-3">
        <div v-for="e in g.entradas" :key="e._i" class="rounded-xl border border-borde bg-white">
          <div class="flex items-start gap-2 px-3 py-2 sm:gap-3">
            <span class="mt-0.5 w-fit shrink-0 rounded-full px-2 py-0.5 text-xs font-bold"
                  :class="e.ra_id ? 'bg-rol/10 text-rol-fuerte' : 'bg-gray-100 text-texto-secundario'">
              {{ e.ra_id ? `RA ${numeroRa(e)}`.trim() : 'RA propio' }}
            </span>
            <p class="min-w-0 flex-1 break-words py-0.5 text-sm text-azul-noche">{{ separarCodigo(e.ra).texto }}</p>
            <div class="relative shrink-0">
              <button type="button" @click="abrir(`ra-${e._i}`)"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100"
                      :aria-expanded="menu === `ra-${e._i}`" aria-haspopup="menu"
                      :aria-label="`Opciones del resultado de aprendizaje «${separarCodigo(e.ra).texto}»`">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
              </button>
              <div v-if="menu === `ra-${e._i}`" role="menu"
                   class="absolute right-0 top-full z-30 mt-1 w-52 rounded-xl border border-borde bg-white py-1 shadow-lg">
                <button type="button" role="menuitem" @click="quitarEntrada(e._i)"
                        class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">
                  Quitar este RA y sus CE
                </button>
              </div>
            </div>
          </div>

          <ul v-if="e.ce?.length" class="border-t border-borde px-3 pb-2 pt-1.5 sm:pl-10">
            <li class="mb-1 text-xs font-bold uppercase tracking-wide text-alumnos-dark" aria-hidden="true">Criterios de evaluación</li>
            <li v-for="(ce, j) in e.ce" :key="j" class="flex items-start gap-2.5 py-1">
              <svg class="mt-1 h-3.5 w-3.5 shrink-0 text-empresas-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
              <span class="w-6 shrink-0 text-sm font-semibold text-texto-secundario">{{ separarCodigo(ce).codigo }}</span>
              <span class="min-w-0 flex-1 break-words text-sm text-azul-noche">
                {{ separarCodigo(ce).texto }}
                <span v-if="origenCe(e, j) === 'reto'"
                      class="ml-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-amber-50 px-2 py-0.5 align-middle text-xs font-semibold text-amber-800">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                  Del reto
                </span>
                <span v-else-if="origenCe(e, j) === 'ia'"
                      class="ml-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-alumnos/10 px-2 py-0.5 align-middle text-xs font-semibold text-azul-noche">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                  Sugerencia IA
                </span>
                <span v-else
                      class="ml-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-rol/10 px-2 py-0.5 align-middle text-xs font-semibold text-rol-fuerte">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                  Añadido
                </span>
              </span>
              <div class="relative shrink-0">
                <button type="button" @click="abrir(`ce-${e._i}-${j}`)"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100"
                        :aria-expanded="menu === `ce-${e._i}-${j}`" aria-haspopup="menu"
                        :aria-label="`Opciones del criterio «${separarCodigo(ce).texto}»`">
                  <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                </button>
                <div v-if="menu === `ce-${e._i}-${j}`" role="menu"
                     class="absolute right-0 top-full z-30 mt-1 w-44 rounded-xl border border-borde bg-white py-1 shadow-lg">
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

    <!-- Cierra el menú «⋯» al pulsar fuera -->
    <div v-if="menu" class="fixed inset-0 z-20" @click="menu = null" />
  </div>
</template>
