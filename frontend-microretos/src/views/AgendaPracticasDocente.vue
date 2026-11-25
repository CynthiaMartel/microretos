<!-- Agenda de prácticas del docente (etapa 2): sus planes de proyecto en la empresa, con hitos
     fechados y el avance de cada grupo. La misma vista, con todos los centros, está en DuaLab Central. -->
<script setup>
import AyudaVista from '../components/AyudaVista.vue'
import { useRouter } from 'vue-router'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import AgendaPracticas from '../components/AgendaPracticas.vue'

const router = useRouter()
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion data-tour="agp-cabecera" antetitulo="Plan de proyecto" titulo="Agenda de" destacado="prácticas" color="text-centros"
:volver="'/seccion/planes-proyecto'"
                       subtitulo="Cómo avanza en la empresa el alumnado de tus planes: hitos, fase de cada grupo y quién va con retraso.">
        <AyudaVista data-tour="agp-ayuda" titulo="Agenda de prácticas: el avance en la empresa" :textos="['El seguimiento del alumnado en las prácticas: en qué fase va cada grupo, quién va con retraso según los hitos y las horas anotadas.', 'Para saber cómo avanza el alumnado en la empresa. Lo que tienes cada día (encuentros, hitos, notas) está en el Calendario.']" />
      </CabeceraSeccion>
      <AgendaPracticas data-tour="agp-agenda" @ver-grupos="id => router.push({ name: 'mis-grupos-detalle', params: { id } })" />
    </div>
  </div>
</template>
