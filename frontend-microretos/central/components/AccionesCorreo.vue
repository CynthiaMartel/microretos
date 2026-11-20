<!-- Acciones de un correo en la lista (Correo de info@ y, más adelante, el de soporte@): interruptor
     «Leído» y menú «⋯» con Abrir, Archivar / Sacar del archivo y Mover a la papelera. Va fuera del
     enlace de la fila (un botón dentro de un <a> no es válido). El menú se abre hacia arriba si no
     cabe debajo (la barra inferior de AppCredit tapaba la última fila). Emite el estado nuevo y la
     bandeja lo guarda. Avisa con `menu` si está abierto: la fila sube de capa para que la tarjeta
     siguiente (también posicionada) no tape el desplegable. -->
<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  correo:  { type: Object, required: true },
  ficha:   { type: Object, required: true },   // ruta de la ficha
  ocupado: { type: Boolean, default: false },
})
const emit = defineEmits(['estado', 'menu'])

const leido = computed(() => props.correo.estado !== 'nuevo')
const quien = computed(() => props.correo.de_nombre || props.correo.de_email || 'sin remitente')

const abierto = ref(false)
const arriba = ref(false)
const raiz = ref(null)
const boton = ref(null)

function alternar() {
  if (abierto.value) return cerrar()
  arriba.value = window.innerHeight - boton.value.getBoundingClientRect().bottom < 180
  abierto.value = true
}
function cerrar(devolverFoco = false) {
  abierto.value = false
  if (devolverFoco) boton.value?.focus()
}
function elegir(estado) {
  cerrar()
  emit('estado', estado)
}
const alPulsarFuera = (e) => { if (!raiz.value?.contains(e.target)) cerrar() }
const alPulsarTecla = (e) => { if (e.key === 'Escape') cerrar(true) }
watch(abierto, (si) => {
  emit('menu', si)
  const metodo = si ? 'addEventListener' : 'removeEventListener'
  document[metodo]('click', alPulsarFuera, true)
  document[metodo]('keydown', alPulsarTecla)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', alPulsarFuera, true)
  document.removeEventListener('keydown', alPulsarTecla)
})
</script>

<template>
  <div ref="raiz" class="flex items-center gap-1.5">
    <!-- Interruptor: objetivo táctil de 36px aunque el carril sea más fino -->
    <button type="button" role="switch" :aria-checked="leido" :disabled="ocupado"
            :aria-label="`Leído: correo de ${quien}`" :title="leido ? 'Marcar como no leído' : 'Marcar como leído'"
            class="group inline-flex h-9 items-center gap-2 rounded-full px-1.5 text-xs font-semibold text-texto-secundario disabled:opacity-50"
            @click="emit('estado', leido ? 'nuevo' : 'leido')">
      <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors"
            :class="leido ? 'bg-rol' : 'bg-gray-300'" aria-hidden="true">
        <span class="absolute left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform" :class="leido && 'translate-x-4'" />
      </span>
      <span class="hidden sm:inline">Leído</span>
    </button>

    <div class="relative">
      <button ref="boton" type="button" :disabled="ocupado" @click="alternar"
              :aria-expanded="abierto" aria-haspopup="menu" :aria-label="`Más acciones del correo de ${quien}`"
              class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde bg-white text-texto-secundario transition-colors hover:bg-gray-50 disabled:opacity-50"
              :class="{ 'bg-gray-50': abierto }">
        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
      </button>
      <div v-if="abierto" role="menu" :class="arriba ? 'bottom-full mb-1' : 'top-full mt-1'"
           class="absolute right-0 z-30 w-[min(15rem,calc(100vw-3rem))] rounded-xl border border-borde bg-white py-1 shadow-lg">
        <RouterLink role="menuitem" :to="ficha" class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Abrir</RouterLink>
        <button v-if="correo.estado !== 'archivado'" type="button" role="menuitem" @click="elegir('archivado')"
                class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Archivar</button>
        <button v-else type="button" role="menuitem" @click="elegir('leido')"
                class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Sacar del archivo</button>
        <button type="button" role="menuitem" @click="elegir('papelera')"
                class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Mover a la papelera</button>
      </div>
    </div>
  </div>
</template>
