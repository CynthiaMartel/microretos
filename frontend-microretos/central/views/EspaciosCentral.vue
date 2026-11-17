<!-- Entrada de DuaLab Central, con o sin sesión: elegir espacio, al estilo del Nexo. Tres espacios en una
     misma app (central/espacios.js): Primer contacto, Red DuaLab y Seguimiento de prácticas. Al pulsar uno
     se pide la cuenta que necesita (el router lleva a /acceso?espacio=…). Con sesión, cada tarjeta
     muestra lo pendiente de los espacios que se pueden abrir. -->
<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../src/stores/auth.js'
import { getResumenCaptacion } from '../../src/services/captacionService.js'
import { getAgendaPracticas } from '../../src/services/agendaService.js'
import { getFormulariosWeb } from '../../src/services/primerContactoService.js'
import { ESPACIOS, puedeEntrar } from '../espacios.js'

const router = useRouter()
const auth = useAuthStore()

// Pendientes de cada espacio, solo de los que puede abrir la sesión actual (null mientras carga o
// sin sesión; un fallo no bloquea el selector)
const pendientes = ref({ 'primer-contacto': null, red: null, practicas: null })
const puede = (clave) => auth.isAuthenticated && puedeEntrar(auth.userRole, clave)
onMounted(() => {
  if (puede('primer-contacto')) {
    getFormulariosWeb({ per_page: 1 })
      .then(({ data }) => { pendientes.value['primer-contacto'] = data.resumen?.por_estado?.nuevo ?? 0 })
      .catch(() => { pendientes.value['primer-contacto'] = 0 })
  }
  if (puede('red')) {
    getResumenCaptacion()
      .then(({ data }) => { pendientes.value.red = (data.empresas_pendientes ?? 0) + (data.centros_pendientes ?? 0) + (data.retos_pendientes ?? 0) })
      .catch(() => { pendientes.value.red = 0 })
  }
  if (puede('practicas')) {
    getAgendaPracticas()
      .then(({ data }) => { pendientes.value.practicas = data.reduce((n, c) => n + c.grupos.filter(g => g.estado === 'retrasado').length, 0) })
      .catch(() => { pendientes.value.practicas = 0 })
  }
})
const TEXTO_PENDIENTE = { 'primer-contacto': 'formularios nuevos', red: 'por validar', practicas: 'grupos retrasados' }

const COLORES = {
  alumnos:  { icono: 'bg-alumnos/10 text-alumnos-dark', boton: 'bg-alumnos-dark hover:bg-alumnos-dark/90', franja: 'bg-alumnos' },
  centros:  { icono: 'bg-centros/10 text-centros', boton: 'bg-centros hover:bg-centros/90', franja: 'bg-centros' },
  empresas: { icono: 'bg-empresas/10 text-empresas-dark', boton: 'bg-empresas-dark hover:bg-empresas-dark/90', franja: 'bg-empresas' },
}

// Sin sesión (o sin la cuenta que pide el espacio), el router lleva al acceso de ese espacio
function abrir(e) {
  router.push(e.ruta)
}
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-5xl px-4 pb-10 pt-8 sm:px-6">
      <header class="text-center">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-administraciones-dark">DuaLab Central</p>
        <h1 class="mt-2 font-heading text-3xl font-bold sm:text-4xl">
          <template v-if="auth.isAuthenticated">Hola, <span class="text-administraciones-dark">{{ auth.userName?.split(' ')[0] }}</span></template>
          <template v-else>Elige tu <span class="text-administraciones-dark">espacio</span></template>
        </h1>
        <p class="mx-auto mt-2 max-w-xl text-sm text-texto-secundario sm:text-base">
          <template v-if="auth.isAuthenticated">Elige el espacio en el que vas a trabajar. Puedes cambiar de espacio cuando quieras desde la barra superior.</template>
          <template v-else>Cada espacio pide su cuenta al entrar: atención al cliente para Primer contacto y superadmin para todo.</template>
        </p>
      </header>

      <ul class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3 sm:gap-5">
        <li v-for="e in ESPACIOS" :key="e.clave" class="relative flex flex-col overflow-hidden rounded-2xl border border-borde bg-white p-5 shadow-sm sm:p-6">
          <span class="absolute inset-x-0 top-0 h-1.5" :class="COLORES[e.color].franja" aria-hidden="true" />
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full" :class="COLORES[e.color].icono" aria-hidden="true">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="e.icono" /></svg>
          </span>
          <h2 class="mt-3 font-heading text-lg font-bold">{{ e.nombre }}</h2>
          <p class="mt-1 flex-1 text-sm text-texto-secundario">{{ e.texto }}</p>
          <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-texto-secundario">
            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            {{ e.quien }}
          </p>
          <p v-if="puede(e.clave)" class="mt-3 text-sm font-semibold" aria-live="polite">
            <span v-if="pendientes[e.clave] === null" class="inline-block h-4 w-24 animate-pulse rounded bg-gray-200 align-middle" aria-label="Cargando" />
            <template v-else>
              <span :class="pendientes[e.clave] ? 'text-alumnos-dark' : 'text-texto-secundario'">{{ pendientes[e.clave] }} {{ TEXTO_PENDIENTE[e.clave] }}</span>
            </template>
          </p>
          <button type="button" @click="abrir(e)"
                  class="mt-4 inline-flex h-11 items-center justify-center gap-2 self-start rounded-full px-5 text-sm font-bold text-white shadow-sm transition-colors"
                  :class="COLORES[e.color].boton">
            Entrar
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
