<!-- Lista de textos editables en el sitio, con reordenación y borrado confirmado
     (Taller de Ideas, paso 6: objetivos y KPIs — diseño crear_proyecto_2.png).
     · Reordenar: arrastrando el asa (escritorio), con ↑/↓ en el teclado sobre el asa,
       o con los botones ↑↓ (móvil, donde el arrastre HTML5 no funciona).
     · Borrar: un ítem con texto pide confirmación en la propia fila.
     · Sugerencias de la IA: van aparte (prop `sugerencias`) y no entran en la lista
       hasta que el docente las acepta (GUIA_DISENO §8.4). -->
<script setup>
import { ref, nextTick } from 'vue'

const props = defineProps({
  modelValue:  { type: Array, required: true },
  sugerencias: { type: Array, default: () => [] },
  // Nombre del ítem en singular, para etiquetas y textos accesibles ("objetivo", "KPI").
  nombre:      { type: String, required: true },
  placeholder: { type: String, default: '' },
  vacio:       { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'aceptar', 'descartar', 'aceptar-todas'])

const inputs = ref([])
const confirmando = ref(null) // índice del ítem con la confirmación de borrado abierta
const arrastrando = ref(null)
const destino = ref(null)

function actualizar(lista) { emit('update:modelValue', lista) }

function editar(i, texto) {
  const lista = [...props.modelValue]
  lista[i] = texto
  actualizar(lista)
}

async function anadir() {
  actualizar([...props.modelValue, ''])
  await nextTick()
  inputs.value[props.modelValue.length - 1]?.focus()
}

function pedirBorrado(i) {
  if (!props.modelValue[i]?.trim()) return borrar(i)
  confirmando.value = i
}

function borrar(i) {
  confirmando.value = null
  actualizar(props.modelValue.filter((_, j) => j !== i))
}

function mover(desde, hasta) {
  if (hasta < 0 || hasta >= props.modelValue.length || desde === hasta) return
  const lista = [...props.modelValue]
  const [item] = lista.splice(desde, 1)
  lista.splice(hasta, 0, item)
  confirmando.value = null
  actualizar(lista)
}

async function moverConTeclado(i, delta, ev) {
  mover(i, i + delta)
  await nextTick()
  // El asa se vuelve a pintar en su nueva posición: el foco la sigue.
  ev.target.closest('ul')?.querySelectorAll('[data-asa]')[i + delta]?.focus()
}

function alSoltar(i) {
  if (arrastrando.value !== null) mover(arrastrando.value, i)
  arrastrando.value = null
  destino.value = null
}

function inicioArrastre(i, ev) {
  arrastrando.value = i
  ev.dataTransfer.effectAllowed = 'move'
  const fila = ev.target.closest('li')
  if (fila) ev.dataTransfer.setDragImage(fila, 16, 16)
}
</script>

<template>
  <div class="space-y-2">
    <ul v-if="modelValue.length" class="space-y-2">
      <li v-for="(texto, i) in modelValue" :key="i"
          class="flex items-center gap-1.5 rounded-xl transition-colors sm:gap-2"
          :class="destino === i && arrastrando !== i ? 'bg-rol/5 ring-2 ring-rol/30' : ''"
          @dragover.prevent="destino = i"
          @dragleave="destino = null"
          @drop.prevent="alSoltar(i)">

        <!-- Asa: arrastrar (escritorio) o ↑/↓ con el teclado -->
        <button type="button" data-asa draggable="true"
                class="hidden h-9 w-7 shrink-0 cursor-grab items-center justify-center rounded-full text-texto-secundario hover:text-texto-secundario active:cursor-grabbing sm:inline-flex"
                :aria-label="`Mover ${nombre} ${i + 1} (flechas arriba y abajo)`"
                :title="`Arrastra para reordenar`"
                @dragstart="inicioArrastre(i, $event)"
                @dragend="arrastrando = null; destino = null"
                @keydown.up.prevent="moverConTeclado(i, -1, $event)"
                @keydown.down.prevent="moverConTeclado(i, 1, $event)">
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <circle cx="7" cy="5" r="1.4"/><circle cx="13" cy="5" r="1.4"/>
            <circle cx="7" cy="10" r="1.4"/><circle cx="13" cy="10" r="1.4"/>
            <circle cx="7" cy="15" r="1.4"/><circle cx="13" cy="15" r="1.4"/>
          </svg>
        </button>

        <!-- ↑↓ en móvil -->
        <div class="flex shrink-0 flex-col sm:hidden">
          <button type="button" :disabled="i === 0" @click="mover(i, i - 1)"
                  class="inline-flex h-5 w-7 items-center justify-center rounded-full text-texto-secundario disabled:opacity-30"
                  :aria-label="`Subir ${nombre} ${i + 1}`">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
          </button>
          <button type="button" :disabled="i === modelValue.length - 1" @click="mover(i, i + 1)"
                  class="inline-flex h-5 w-7 items-center justify-center rounded-full text-texto-secundario disabled:opacity-30"
                  :aria-label="`Bajar ${nombre} ${i + 1}`">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
          </button>
        </div>

        <input :ref="el => (inputs[i] = el)" type="text" :value="texto"
               :placeholder="placeholder"
               :aria-label="`${nombre} ${i + 1}`"
               class="min-w-0 flex-1 rounded-xl px-3 py-2 text-sm"
               @input="editar(i, $event.target.value)" />

        <!-- Borrar, con confirmación en la fila si tiene texto -->
        <div v-if="confirmando === i" class="flex shrink-0 items-center gap-1">
          <button type="button" @click="borrar(i)"
                  class="inline-flex h-9 items-center rounded-full bg-red-600 px-3 text-xs font-bold text-white hover:bg-red-700">
            Eliminar
          </button>
          <button type="button" @click="confirmando = null"
                  class="inline-flex h-9 items-center rounded-full border border-borde bg-white px-3 text-xs font-bold text-texto-secundario hover:bg-gray-50">
            No
          </button>
        </div>
        <button v-else type="button" @click="pedirBorrado(i)"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-texto-secundario transition-colors hover:bg-red-50 hover:text-red-600"
                :aria-label="`Eliminar ${nombre} ${i + 1}`" :title="`Eliminar ${nombre}`">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </button>
      </li>
    </ul>
    <p v-else-if="vacio" class="px-1 text-sm text-texto-secundario">{{ vacio }}</p>

    <button type="button" @click="anadir"
            class="inline-flex h-9 items-center gap-1.5 rounded-full bg-rol/10 px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol/15">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
      </svg>
      Añadir {{ nombre }}
    </button>

    <!-- Sugerencias de la IA pendientes de revisar -->
    <div v-if="sugerencias.length"
         class="mt-3 rounded-xl border border-alumnos/30 bg-alumnos/5 p-3 sm:p-4">
      <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-azul-noche">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
          </svg>
          Sugerencias IA · revisa antes de añadir
        </p>
        <button type="button" @click="emit('aceptar-todas')"
                class="inline-flex h-8 items-center rounded-full bg-rol-fuerte px-3 text-xs font-bold text-white hover:bg-rol-fuerte/90">
          Añadir todas
        </button>
      </div>
      <ul class="space-y-1.5">
        <li v-for="(s, i) in sugerencias" :key="s"
            class="flex items-start gap-2 rounded-xl bg-white px-3 py-2 text-sm text-azul-noche">
          <span class="min-w-0 flex-1 break-words">{{ s }}</span>
          <button type="button" @click="emit('aceptar', i)"
                  class="inline-flex h-8 shrink-0 items-center gap-1 rounded-full border border-alumnos/30 px-3 text-xs font-bold text-azul-noche hover:bg-alumnos/15"
                  :aria-label="`Añadir sugerencia: ${s}`">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            Añadir
          </button>
          <button type="button" @click="emit('descartar', i)"
                  class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-texto-secundario hover:bg-gray-100 hover:text-texto-secundario"
                  :aria-label="`Descartar sugerencia: ${s}`" title="Descartar">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
