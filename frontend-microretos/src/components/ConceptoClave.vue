<!-- Definición de un concepto de DuaLab (reto, proyecto, encuentro, seguimiento…) en la vista
     donde se usa. Mismo estilo y textos que los bloques "?" de ComoFuncionaModal.vue.
     El texto llega en segmentos ({ t, b }) para resaltar palabras sin usar v-html. -->
<script setup>
const props = defineProps({
  // Color de marca: 'centros' (retos) | 'empresas' (proyectos) | 'alumnos' (encuentros) | 'administraciones'
  color:     { type: String, default: 'centros' },
  segmentos: { type: Array, required: true },
})

// Clases completas (no interpoladas) para que Tailwind las detecte
const COLORES = {
  centros:          { caja: 'bg-centros/10 border-centros/30',                   icono: 'bg-centros/20 text-centros',                   fuerte: 'text-centros' },
  empresas:         { caja: 'bg-empresas/10 border-empresas/30',                 icono: 'bg-empresas/20 text-empresas-dark',            fuerte: 'text-empresas-dark' },
  alumnos:          { caja: 'bg-alumnos/10 border-alumnos/30',                   icono: 'bg-alumnos/20 text-alumnos-dark',              fuerte: 'text-alumnos-dark' },
  administraciones: { caja: 'bg-administraciones/10 border-administraciones/30', icono: 'bg-administraciones/20 text-administraciones', fuerte: 'text-[#0F7273]' },
}
const c = COLORES[props.color] ?? COLORES.centros
</script>

<template>
  <div class="flex items-start gap-3 rounded-2xl border-2 p-4 shadow-sm" :class="c.caja">
    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-black" :class="c.icono" aria-hidden="true">?</span>
    <p class="text-sm leading-relaxed text-gray-900">
      <template v-for="(s, i) in segmentos" :key="i"><span v-if="s.b" class="font-black" :class="c.fuerte">{{ s.t }}</span><template v-else>{{ s.t }}</template></template>
    </p>
  </div>
</template>
