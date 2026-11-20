<!-- Soporte: segunda fase (TAREAS_PENDIENTES T14). Dos apartados, aún por hacer: «Tickets» (las
     incidencias de soporte@dualab.es y de «¿No puedes entrar?» del login de la app) y «Correo (soporte@)»,
     el buzón de soporte@ como el Correo de info@ (hará falta que la web lea también ese buzón). -->
<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import BandejaCorreo from './BandejaCorreo.vue'
import { BUZONES } from '../primerContacto.js'

const route = useRoute()
const esCorreo = computed(() => route.name === 'soporte-correo')
</script>

<template>
  <!-- El correo de soporte@ será la misma bandeja que info@ en cuanto la web lea el buzón -->
  <BandejaCorreo v-if="esCorreo && BUZONES.soporte.disponible" buzon="soporte" />
  <div v-else class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Soporte" :titulo="esCorreo ? 'Correo de soporte@' : 'Tickets'" color="text-rol"
                       subtitulo="Incidencias de las personas que usan la app DuaLab." />
      <div class="rounded-2xl border border-borde bg-white p-6 shadow-sm sm:p-8">
        <span class="inline-flex rounded-full bg-rol/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-rol-fuerte">Próximamente</span>
        <template v-if="esCorreo">
          <h2 class="mt-3 font-heading text-lg font-bold">Correo de soporte@dualab.es</h2>
          <p class="mt-2 max-w-2xl text-sm text-texto-secundario">
            Aquí estará el buzón de soporte@dualab.es, igual que el Correo de info@: leer, responder y archivar sin salir de Central.
            Mientras tanto, el correo de soporte@ se sigue atendiendo desde el webmail.
          </p>
        </template>
        <template v-else>
          <h2 class="mt-3 font-heading text-lg font-bold">Tickets de soporte</h2>
          <p class="mt-2 max-w-2xl text-sm text-texto-secundario">
            Aquí llegarán las incidencias que escriban a soporte@dualab.es y las del enlace «¿No puedes entrar?» del acceso a la app.
            Podrás verlas, responderlas y, si es un problema de cuenta, desbloquearla o reenviar el enlace de contraseña desde aquí.
            Mientras tanto, las incidencias siguen llegando por correo a soporte@dualab.es.
          </p>
        </template>
      </div>
    </div>
  </div>
</template>
