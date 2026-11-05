<!-- Familias profesionales con las que quiere trabajar la empresa: buscador con desplegable
     (las 30 familias no caben como chips en una tablet) y filtro «Con retos disponibles», como
     en los filtros del resto de vistas. Las elegidas quedan como píldoras quitables, cada una con
     su «para qué querría a este alumnado».
     v-model: nombres de familia · v-model:intereses: { [familia]: texto } -->
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import FiltroSegmentado from '../../src/components/FiltroSegmentado.vue'
import { iconoFiltro } from '../../src/config/iconosFiltro.js'
import { ejemploInteres } from '../../src/config/ejemplosInteresFamilia.js'

const seleccion = defineModel({ type: Array, default: () => [] })
const intereses = defineModel('intereses', { type: Object, default: () => ({}) })

const props = defineProps({
  opciones: { type: Array, default: () => [] }, // [{ id, nombre, con_datos_retos }]
  error:    { type: String, default: '' },
})

const busqueda = ref('')
const soloConRetos = ref('')
const abierto = ref(false)
const raiz = ref(null)

const normalizar = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const filtradas = computed(() => {
  const q = normalizar(busqueda.value.trim())
  return props.opciones.filter(f =>
    (!soloConRetos.value || f.con_datos_retos) && (!q || normalizar(f.nombre).includes(q)))
})
const conRetos = computed(() => props.opciones.filter(f => f.con_datos_retos).length)

function alternar(nombre) {
  if (seleccion.value.includes(nombre)) {
    quitar(nombre)
  } else {
    seleccion.value = [...seleccion.value, nombre]
    intereses.value = { ...intereses.value, [nombre]: intereses.value[nombre] ?? '' }
  }
}
function quitar(nombre) {
  seleccion.value = seleccion.value.filter(n => n !== nombre)
}
function cambiarInteres(nombre, texto) {
  intereses.value = { ...intereses.value, [nombre]: texto }
}

// Cierre del desplegable al tocar fuera o con Escape
const fuera = (e) => { if (raiz.value && !raiz.value.contains(e.target)) abierto.value = false }
const tecla = (e) => { if (e.key === 'Escape') abierto.value = false }
onMounted(() => { document.addEventListener('pointerdown', fuera); document.addEventListener('keydown', tecla) })
onUnmounted(() => { document.removeEventListener('pointerdown', fuera); document.removeEventListener('keydown', tecla) })
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-end">
      <!-- Buscador con desplegable -->
      <div ref="raiz" class="relative min-w-0 flex-1">
        <label for="sf-buscar" class="mb-1 flex items-center gap-2 text-xs font-semibold text-azul-noche">
          <span aria-hidden="true" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="iconoFiltro('Familia')" /></svg>
          </span>
          Buscar familia profesional
        </label>
        <div class="relative">
          <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M21 21l-4.3-4.3M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" /></svg>
          <input id="sf-buscar" v-model="busqueda" type="search" autocomplete="off" role="combobox"
                 :aria-expanded="abierto" aria-controls="sf-lista" placeholder="Ej.: comercio, sanidad, informática…"
                 class="h-12 w-full rounded-xl border border-borde-campo bg-white pl-12 pr-4 text-base text-azul-noche outline-none placeholder:text-gray-500 focus:border-rol-fuerte focus:ring-4 focus:ring-rol-fuerte/15"
                 @focus="abierto = true" @input="abierto = true" />
        </div>
        <ul v-if="abierto" id="sf-lista" role="listbox" aria-multiselectable="true"
            class="absolute inset-x-0 top-full z-30 mt-1 max-h-[min(20rem,50dvh)] overflow-y-auto rounded-xl border border-borde bg-white p-1 shadow-xl">
          <li v-for="f in filtradas" :key="f.id" role="option" :aria-selected="seleccion.includes(f.nombre)">
            <button type="button" class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-gray-50"
                    @click="alternar(f.nombre)">
              <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded border-2"
                    :class="seleccion.includes(f.nombre) ? 'border-rol-fuerte bg-rol-fuerte text-white' : 'border-borde-campo'" aria-hidden="true">
                <svg v-if="seleccion.includes(f.nombre)" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              </span>
              <span class="min-w-0 flex-1 break-words font-semibold text-azul-noche">{{ f.nombre }}</span>
              <span v-if="f.con_datos_retos" class="shrink-0 rounded-full bg-empresas/10 px-2 py-0.5 text-xs font-semibold text-empresas-dark">Con retos</span>
            </button>
          </li>
          <li v-if="!filtradas.length" class="px-3 py-4 text-sm text-gray-500">Ninguna familia coincide con «{{ busqueda }}».</li>
        </ul>
      </div>

      <FiltroSegmentado v-model="soloConRetos" label="Mostrar"
                        :opciones="[{ value: '', label: 'Todas', count: opciones.length }, { value: 'retos', label: 'Con retos disponibles', count: conRetos }]" />
    </div>

    <p v-if="error" class="error-campo ml-0">{{ error }}</p>

    <!-- Elegidas -->
    <p v-if="!seleccion.length" class="rounded-xl border border-dashed border-borde-campo px-4 py-3 text-sm text-texto-secundario">
      Aún no has elegido ninguna familia. Busca arriba y marca una o varias.
    </p>
    <ul v-else class="space-y-3">
      <li v-for="nombre in seleccion" :key="nombre" class="rounded-xl border border-borde bg-gray-50/60 p-3 sm:p-4">
        <div class="flex items-center justify-between gap-3">
          <span class="inline-flex min-w-0 items-center gap-1.5 rounded-full bg-rol-fuerte px-3 py-1 text-sm font-semibold text-white">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            <span class="truncate">{{ nombre }}</span>
          </span>
          <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-red-50 hover:text-red-600"
                  :aria-label="`Quitar ${nombre}`" :title="`Quitar ${nombre}`" @click="quitar(nombre)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <label :for="`sf-interes-${nombre}`" class="label-style mt-3">Para qué querría a este alumnado <span class="normal-case tracking-normal">(opcional)</span></label>
        <textarea :id="`sf-interes-${nombre}`" :value="intereses[nombre] ?? ''" rows="2" maxlength="500" class="input-style"
                  :placeholder="ejemploInteres(nombre)" @input="cambiarInteres(nombre, $event.target.value)" />
      </li>
    </ul>
  </div>
</template>
