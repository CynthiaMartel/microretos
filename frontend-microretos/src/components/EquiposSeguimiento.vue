<!-- Seguimiento de equipos de UN proyecto, en modo solo lectura — pensado para incrustarse
     como desplegable en la ficha del proyecto (ProyectoFichaModal.vue / StartupDayDetalle.vue)
     y responder "¿cómo ha resuelto el alumnado este proyecto?" sin salir de la ficha.

     La tarjeta de cada equipo es <EquipoResolucionCard>, compartida con MisGruposDetalle.vue
     (gestión docente) — aquí se usa en modo `expandido-siempre` y sin rellenar ninguno de sus
     slots de mutación (generar diagnóstico, evaluar RA/CE), así que solo se ve la parte de
     solo-lectura. Ver los comentarios de ese componente para el porqué de repartirlo así.

     El fetch ocurre en el watch de `proyectoUuid`, igual que MicroretoModal.vue/
     ProyectoFichaModal.vue; el padre solo monta este componente cuando el proyecto está
     completado. -->
<script setup>
import { ref, computed, watch } from 'vue'
import api from '../api.js'
import { progresoPonderado } from '../config/fasesProyecto.js'
import EquipoResolucionCard from './EquipoResolucionCard.vue'

const props = defineProps({
  proyectoUuid: { type: String, required: true },
})

const cargando = ref(true)
const error    = ref('')
const equipos  = ref([])

const totalEquipos       = computed(() => equipos.value.length)
const progresoMedio      = computed(() => {
  if (!totalEquipos.value) return 0
  const suma = equipos.value.reduce((acc, e) => acc + progresoPonderado(e.fases), 0)
  return Math.round(suma / totalEquipos.value)
})

watch(() => props.proyectoUuid, async (uuid) => {
  if (!uuid) return
  cargando.value = true
  error.value    = ''
  try {
    const res = await api.get(`/startup/proyectos/${uuid}/equipos`)
    equipos.value = res.data.equipos || []
  } catch (e) {
    console.error('Error cargando seguimiento de equipos:', e)
    error.value = 'No se pudo cargar el seguimiento de equipos.'
  } finally {
    cargando.value = false
  }
}, { immediate: true })
</script>

<template>
  <div>
    <!-- Carga / error -->
    <div v-if="cargando" class="flex items-center justify-center py-10">
      <div class="w-6 h-6 border-2 border-centros border-t-transparent rounded-full animate-spin"></div>
    </div>
    <div v-else-if="error" class="rounded-2xl bg-red-50 border border-red-200 p-5 text-center text-red-600 text-xs font-semibold">
      {{ error }}
    </div>

    <template v-else>
      <!-- Sin equipos -->
      <div v-if="!equipos.length" class="rounded-2xl bg-gray-50 border border-gray-100 p-6 text-center">
        <p class="text-gray-400 text-sm">Todavía no hay equipos que hayan trabajado este proyecto.</p>
      </div>

      <template v-else>
        <!-- Resumen -->
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div class="text-center bg-gray-50 rounded-xl py-2.5">
            <p class="text-lg font-black text-[#121212]">{{ totalEquipos }}</p>
            <p class="text-[9px] text-gray-400 uppercase tracking-wider">Equipos</p>
          </div>
          <div class="text-center bg-gray-50 rounded-xl py-2.5">
            <p class="text-lg font-black text-violet-600">{{ progresoMedio }}%</p>
            <p class="text-[9px] text-gray-400 uppercase tracking-wider">Progreso medio</p>
          </div>
        </div>

        <!-- Equipos -->
        <div class="space-y-2">
          <EquipoResolucionCard v-for="equipo in equipos" :key="equipo.id" :equipo="equipo" expandido-siempre />
        </div>
      </template>
    </template>
  </div>
</template>
