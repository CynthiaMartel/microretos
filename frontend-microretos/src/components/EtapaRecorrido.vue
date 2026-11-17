<!-- Etapa del recorrido del alumnado (microproyectos.etapa), para que se distinga a simple vista desde
     que mete el código: Taller de ideas (encuentro creativo en el aula, naranja del alumnado) o Plan de
     proyecto (prácticas en la empresa, azul como los planes del docente). Pensado para fondos oscuros
     (pantallas de código y cabecera del workspace). -->
<script setup>
import { computed } from 'vue'

const props = defineProps({
  etapa: { type: String, default: 'taller' },
  // Texto extra tras el nombre (p. ej. la empresa del plan)
  detalle: { type: String, default: '' },
})

const ETAPAS = {
  taller: {
    nombre: 'Taller de ideas',
    lugar: 'Encuentro creativo en el aula',
    clases: 'bg-alumnos/15 text-alumnos ring-alumnos/40',
    icono: 'M9.66 17h4.68M12 3v1m6.36 1.64l-.7.7M21 12h-1M4 12H3m3.34-5.66l-.7-.7M8.46 15.54a5 5 0 117.08 0l-.55.55A3.37 3.37 0 0014 18.47V19a2 2 0 11-4 0v-.53c0-.89-.36-1.75-.99-2.38l-.55-.55z',
  },
  plan: {
    nombre: 'Plan de proyecto',
    lugar: 'Prácticas en la empresa',
    clases: 'bg-centros/25 text-centros-light ring-centros-light/50',
    icono: 'M21 13.26A23.9 23.9 0 0112 15c-3.18 0-6.22-.62-9-1.74M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  },
}
const info = computed(() => ETAPAS[props.etapa] ?? ETAPAS.taller)
</script>

<template>
  <span class="inline-flex max-w-full items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em] ring-1"
        :class="info.clases" :title="info.lugar">
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="info.icono" /></svg>
    <span class="min-w-0 truncate">{{ info.nombre }}<template v-if="detalle"> · {{ detalle }}</template></span>
    <span class="sr-only">({{ info.lugar }})</span>
  </span>
</template>
