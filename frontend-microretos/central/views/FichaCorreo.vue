<!-- Un correo de info@ en DuaLab Central («Primer contacto»). Abrirlo lo marca como leído (en la copia de
     la web). Responder, responder a todos y reenviar salen desde info@ por la web. Los adjuntos se
     descargan siempre como archivo. -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import CuerpoCorreo from '../components/CuerpoCorreo.vue'
import RedactarCorreo from '../components/RedactarCorreo.vue'
import { getCorreo, cambiarEstadoCorreo, descargarAdjuntoCorreo } from '../../src/services/primerContactoService.js'
import { fechaHora } from '../primerContacto.js'

const route = useRoute()
const router = useRouter()
const c = ref(null)
const error = ref('')
const aviso = ref('')
const guardando = ref(false)
const modo = ref(null) // responder | responder_todos | reenviar

onMounted(async () => {
  try { c.value = (await getCorreo(route.params.id)).data.data }
  catch (e) { error.value = e.response?.data?.error || 'No se ha podido abrir el correo.' }
})

async function cambiar(estado, texto) {
  guardando.value = true
  aviso.value = ''
  try {
    c.value = (await cambiarEstadoCorreo(c.value.id, estado)).data.data
    aviso.value = texto
  } catch (e) {
    aviso.value = e.response?.data?.error || 'No se ha podido guardar el cambio.'
  } finally {
    guardando.value = false
  }
}

// A la papelera y de vuelta a la lista (solo el superadmin la ve y la restaura)
async function aPapelera() {
  await cambiar('papelera', 'Movido a la papelera.')
  if (c.value?.estado === 'papelera') router.push(volver.value)
}

async function descargar(a) {
  aviso.value = ''
  try {
    const { data } = await descargarAdjuntoCorreo(c.value.id, a.id)
    const url = URL.createObjectURL(data)
    const enlace = Object.assign(document.createElement('a'), { href: url, download: a.nombre })
    enlace.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch {
    aviso.value = `No se ha podido descargar «${a.nombre}».`
  }
}

function enviado() {
  modo.value = null
  aviso.value = 'Enviando… Aparecerá en Enviados en un par de minutos.'
}

const quien = d => (d.nombre ? `${d.nombre} <${d.email}>` : d.email)
const esEnviado = computed(() => c.value?.carpeta === 'enviados')
const variosDestinatarios = computed(() => c.value && ((c.value.para?.length ?? 0) + (c.value.cc?.length ?? 0)) > 1)
const adjuntos = computed(() => (c.value?.adjuntos ?? []).filter(a => !a.en_linea))
const tamano = b => (b >= 1024 * 1024 ? `${(b / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`)
// Se vuelve a donde se abrió: el Correo de info@, «Respuestas de solicitudes» o la ficha de una solicitud
const volver = computed(() => {
  const { desde, solicitud, ...query } = route.query
  if (desde === 'respuestas') return { name: 'respuestas-solicitudes', query }
  if (desde === 'solicitud' && /^\d+$/.test(String(solicitud))) return { name: 'solicitud-reto-ficha', params: { id: solicitud } }
  return { name: 'correo', query: route.query }
})
const volverLabel = computed(() => ({ respuestas: 'Respuestas', solicitud: 'Solicitud' })[route.query.desde] ?? 'Correo')
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-5xl space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Correo" :titulo="c ? (c.asunto || '(Sin asunto)') : 'Correo'" :halos="false"
                       :volver="volver" :volver-label="volverLabel" />

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-else-if="!c" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>

      <template v-else>
        <!-- Acciones -->
        <section class="flex flex-wrap items-center gap-2 rounded-2xl border border-borde bg-white p-3 shadow-sm sm:p-4" aria-label="Acciones">
          <button type="button" class="accion-principal" @click="modo = 'responder'">Responder</button>
          <button v-if="variosDestinatarios && !esEnviado" type="button" class="accion-secundaria" @click="modo = 'responder_todos'">Responder a todos</button>
          <button type="button" class="accion-secundaria" @click="modo = 'reenviar'">Reenviar</button>
          <span class="hidden flex-1 sm:block" />
          <template v-if="!esEnviado">
            <button v-if="c.estado !== 'nuevo'" type="button" class="accion-secundaria" :disabled="guardando" @click="cambiar('nuevo', 'Marcado como no leído.')">No leído</button>
            <button v-if="c.estado !== 'archivado'" type="button" class="accion-secundaria" :disabled="guardando" @click="cambiar('archivado', 'Archivado.')">Archivar</button>
            <button v-else type="button" class="accion-secundaria" :disabled="guardando" @click="cambiar('leido', 'Sacado del archivo.')">Sacar del archivo</button>
            <button v-if="c.estado !== 'papelera'" type="button" class="accion-secundaria !text-red-700" :disabled="guardando" @click="aPapelera">Mover a la papelera</button>
          </template>
          <p v-if="aviso" class="w-full text-sm font-semibold text-texto-secundario" role="status">{{ aviso }}</p>
        </section>

        <article class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-6">
          <header class="mb-4 space-y-1 border-b border-borde pb-4 text-sm">
            <p class="break-words"><span class="font-bold">De:</span> {{ quien({ nombre: c.de_nombre, email: c.de_email }) }}</p>
            <p class="break-words"><span class="font-bold">Para:</span> {{ (c.para ?? []).map(quien).join(', ') || '—' }}</p>
            <p v-if="c.cc?.length" class="break-words"><span class="font-bold">CC:</span> {{ c.cc.map(quien).join(', ') }}</p>
            <p class="text-texto-secundario">{{ fechaHora(c.fecha) }}</p>
            <p v-if="c.contacto" class="pt-1">
              <RouterLink :to="{ name: 'formulario-web', params: { id: c.contacto.id } }"
                          class="inline-flex items-center gap-1.5 rounded-full bg-rol/10 px-3 py-1 text-xs font-bold text-rol-fuerte hover:bg-rol/20">
                <span class="sm:hidden">Formulario de {{ c.contacto.tipo_nombre.toLowerCase() }}</span>
                <span class="hidden sm:inline">Nos escribió por el formulario de {{ c.contacto.tipo_nombre.toLowerCase() }} · {{ fechaHora(c.contacto.fecha) }}</span>
              </RouterLink>
            </p>
          </header>

          <CuerpoCorreo :html="c.html" :texto="c.texto" />

          <section v-if="adjuntos.length" class="mt-5 border-t border-borde pt-4" aria-labelledby="titulo-adjuntos">
            <h2 id="titulo-adjuntos" class="mb-2 text-xs font-bold uppercase tracking-wider text-texto-secundario">Adjuntos</h2>
            <ul class="flex flex-wrap gap-2">
              <li v-for="a in adjuntos" :key="a.id" class="min-w-0 max-w-full">
                <button v-if="a.disponible" type="button" class="accion-secundaria max-w-full" @click="descargar(a)">
                  <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                  <span class="min-w-0 truncate">{{ a.nombre }}</span>
                  <span class="shrink-0 text-xs font-normal text-texto-secundario">{{ tamano(a.tamano) }}</span>
                </button>
                <span v-else class="inline-flex max-w-full items-center rounded-full bg-gray-100 px-3 py-1.5 text-xs text-texto-secundario">
                  <span class="min-w-0 truncate">{{ a.nombre }}</span>&nbsp;· solo en el webmail
                </span>
              </li>
            </ul>
          </section>
        </article>
      </template>
    </div>

    <RedactarCorreo v-if="c" :abierto="!!modo" :modo="modo || 'responder'" :original="c"
                    @cerrar="modo = null" @enviado="enviado" />
  </div>
</template>
