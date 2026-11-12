<!-- Soporte: segunda fase (TAREAS_PENDIENTES T14). Aquí irán los tickets de soporte@dualab.es
     y de «¿No puedes entrar?» del login de la app. -->
<script setup>
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="DuaLab Central" titulo="Soporte" color="text-administraciones"
                       subtitulo="Incidencias de las personas que usan la app DuaLab." />
      <div class="rounded-2xl border border-borde bg-white p-6 shadow-sm sm:p-8">
        <span class="inline-flex rounded-full bg-administraciones/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-administraciones-dark">Próximamente</span>
        <h2 class="mt-3 font-heading text-lg font-bold">Tickets de soporte</h2>
        <p class="mt-2 max-w-2xl text-sm text-texto-secundario">
          Aquí llegarán las incidencias que escriban a soporte@dualab.es y las del enlace «¿No puedes entrar?» del acceso a la app.
          Podrás verlas, responderlas y, si es un problema de cuenta, desbloquearla o reenviar el enlace de contraseña desde aquí.
          Mientras tanto, las incidencias siguen llegando por correo a soporte@dualab.es.
        </p>
      </div>
    </div>
  </div>
</template>
