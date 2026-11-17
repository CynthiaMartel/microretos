<!-- DuaLab Central → Agenda de prácticas: desde aquí DuaLab sigue el avance del alumnado en las
     empresas, de todos los centros (misma vista que la del docente, con centro y docente). -->
<script setup>
import AyudaVista from '../../src/components/AyudaVista.vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import AgendaPracticas from '../../src/components/AgendaPracticas.vue'
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Agenda de" destacado="prácticas" color="text-administraciones"
                       subtitulo="El avance del alumnado en las empresas de todos los centros: hitos, fase de cada grupo y quién va con retraso.">
        <AyudaVista titulo="Agenda de prácticas: el avance en la empresa"
                    :textos="['El seguimiento del alumnado de todos los centros en las prácticas: fase de cada grupo, retrasos según los hitos y horas anotadas.', 'Desde aquí DuaLab controla el avance y anota horas con el tutor de empresa. El día a día de cada docente está en su Calendario, en la app.']" />
      </CabeceraSeccion>
      <AgendaPracticas central />
    </div>
  </div>
</template>
