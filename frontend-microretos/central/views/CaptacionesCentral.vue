<!-- Captaciones en DuaLab Central: los mismos paneles que «Notificaciones → Captación comercial»
     de la app (PanelCaptacionComercial y PanelCaptacionCentros), sin cambios. -->
<script setup>
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import PanelCaptacionComercial from '../../src/components/PanelCaptacionComercial.vue'
import PanelCaptacionCentros from '../../src/components/PanelCaptacionCentros.vue'
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Validar" destacado="captaciones" color="text-rol"
                       subtitulo="Empresas y centros captados por el equipo comercial o por el formulario de dualab.es, y los retos en borrador de esas empresas." />
      <PanelCaptacionComercial />
      <PanelCaptacionCentros />
    </div>
  </div>
</template>
