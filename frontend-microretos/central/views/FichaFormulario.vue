<!-- Ficha de un formulario de la web en DuaLab Central («Primer contacto»). Abrirla lo marca como
     leído (lo hace la web). Muestra los datos tal cual llegaron (texto escapado, nunca HTML), si ya
     es una captación y las respuestas enviadas. Se responde desde aquí: sale desde info@ por la web. -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import { getFormularioWeb, cambiarEstadoFormularioWeb } from '../../src/services/primerContactoService.js'
import RedactarCorreo from '../components/RedactarCorreo.vue'
import { ESTADOS_FORMULARIO, fechaHora, textoCaptacion } from '../primerContacto.js'

const route = useRoute()
const f = ref(null)
const error = ref('')
const guardando = ref(false)
const aviso = ref('')
const respondiendo = ref(false)

async function cargar() {
  try { f.value = (await getFormularioWeb(route.params.id)).data.data }
  catch (e) { error.value = e.response?.data?.error || 'No se ha podido abrir el formulario.' }
}
onMounted(cargar)

// Datos para responder: el nombre de pila en el saludo y un asunto que se entienda
const contactoRespuesta = computed(() => f.value && {
  id: f.value.id, email: f.value.email, nombre: f.value.nombre,
  asunto: `DuaLab · ${f.value.accion_nombre || f.value.formulario_nombre || 'Tu mensaje'}`,
})
function enviado() {
  respondiendo.value = false
  aviso.value = 'Respuesta enviándose desde info@. Aparecerá en «Respuestas» en un par de minutos.'
  cargar()
}

async function cambiar(estado, texto) {
  guardando.value = true
  aviso.value = ''
  try {
    f.value = (await cambiarEstadoFormularioWeb(f.value.id, estado)).data.data
    aviso.value = texto
  } catch (e) {
    aviso.value = e.response?.data?.error || 'No se ha podido guardar el cambio.'
  } finally {
    guardando.value = false
  }
}

const nombre = computed(() => f.value ? ([f.value.nombre, f.value.apellidos].filter(Boolean).join(' ') || 'Sin nombre') : '')
const contacto = computed(() => f.value ? [
  { etiqueta: 'Email', valor: f.value.email, enlace: f.value.email && `mailto:${f.value.email}` },
  { etiqueta: 'Teléfono', valor: f.value.telefono, enlace: f.value.telefono && `tel:${f.value.telefono.replace(/\s+/g, '')}` },
  { etiqueta: 'Cargo', valor: f.value.cargo },
  { etiqueta: 'Página de la web', valor: f.value.pagina_origen },
  { etiqueta: 'Recibido', valor: fechaHora(f.value.created_at) },
].filter(c => c.valor) : [])
// «nombre_empresa» → «Nombre empresa»
const etiqueta = (clave) => { const t = String(clave).replace(/[_-]+/g, ' ').trim(); return t.charAt(0).toUpperCase() + t.slice(1) }
const volver = computed(() => ({ name: 'primer-contacto', query: route.query }))
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-5xl space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Contactos" :titulo="f ? nombre : 'Formulario'" color="text-rol"
                       :volver="volver" volver-label="Formularios"
                       :subtitulo="f ? [f.formulario_nombre, f.accion_nombre].filter(Boolean).join(' · ') : ''" />

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-else-if="!f" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>

      <template v-else>
        <!-- Estado y acciones -->
        <section class="flex flex-col gap-3 rounded-2xl border border-borde bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:p-5" aria-label="Estado">
          <span class="self-start rounded-full px-3 py-1 text-xs font-bold sm:self-auto" :class="ESTADOS_FORMULARIO[f.estado]?.clases">{{ ESTADOS_FORMULARIO[f.estado]?.texto ?? f.estado }}</span>
          <div class="flex flex-wrap gap-2 sm:ml-auto">
            <button v-if="f.estado !== 'nuevo'" type="button" class="accion-secundaria" :disabled="guardando" @click="cambiar('nuevo', 'Marcado como no leído.')">Marcar como no leído</button>
            <button v-if="f.estado !== 'archivado'" type="button" class="accion-secundaria" :disabled="guardando" @click="cambiar('archivado', 'Archivado.')">Archivar</button>
            <button v-else type="button" class="accion-secundaria" :disabled="guardando" @click="cambiar('leido', 'Sacado del archivo.')">Sacar del archivo</button>
            <button v-if="f.email" type="button" class="accion-principal" @click="respondiendo = true">Responder</button>
          </div>
          <p v-if="aviso" class="w-full text-sm font-semibold text-texto-secundario" role="status">{{ aviso }}</p>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <div class="space-y-4 lg:col-span-2">
            <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" aria-labelledby="ficha-contacto">
              <h2 id="ficha-contacto" class="mb-3 font-heading text-lg font-bold">Contacto</h2>
              <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div v-for="c in contacto" :key="c.etiqueta" class="min-w-0">
                  <dt class="text-xs font-bold uppercase tracking-wider text-texto-secundario">{{ c.etiqueta }}</dt>
                  <dd class="mt-0.5 break-words text-sm">
                    <a v-if="c.enlace" :href="c.enlace" class="font-semibold text-rol-fuerte underline-offset-2 hover:underline">{{ c.valor }}</a>
                    <template v-else>{{ c.valor }}</template>
                  </dd>
                </div>
              </dl>
            </section>

            <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" aria-labelledby="ficha-datos">
              <h2 id="ficha-datos" class="mb-3 font-heading text-lg font-bold">Lo que nos cuenta</h2>
              <p v-if="!Object.keys(f.datos || {}).length" class="text-sm text-texto-secundario">El formulario no trae más datos.</p>
              <dl v-else class="space-y-3">
                <div v-for="(valor, clave) in f.datos" :key="clave" class="min-w-0">
                  <dt class="text-xs font-bold uppercase tracking-wider text-texto-secundario">{{ etiqueta(clave) }}</dt>
                  <dd class="mt-0.5 whitespace-pre-line break-words text-sm">{{ valor }}</dd>
                </div>
              </dl>
            </section>
          </div>

          <div class="space-y-4">
            <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" aria-labelledby="ficha-captacion">
              <h2 id="ficha-captacion" class="mb-2 font-heading text-lg font-bold">Captación</h2>
              <template v-if="f.captacion">
                <p class="text-sm">{{ textoCaptacion(f.captacion) }}.</p>
                <RouterLink :to="{ name: 'captaciones' }" class="accion-secundaria mt-3">Ver en captaciones</RouterLink>
              </template>
              <p v-else class="text-sm text-texto-secundario">
                Este formulario no está en captaciones. Los de empresas y centros pasan solos si la integración de la web está activa.
              </p>
            </section>

            <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" aria-labelledby="ficha-respuestas">
              <h2 id="ficha-respuestas" class="mb-2 font-heading text-lg font-bold">Respuestas</h2>
              <p v-if="!f.respuestas.length" class="text-sm text-texto-secundario">Aún no se le ha respondido.</p>
              <ul v-else class="space-y-2">
                <li v-for="r in f.respuestas" :key="r.id" class="rounded-xl bg-[#F8FAFC] px-3 py-2">
                  <p class="break-words text-sm font-semibold">{{ r.asunto || 'Sin asunto' }}</p>
                  <p class="text-xs text-texto-secundario">{{ fechaHora(r.fecha) }}<template v-if="r.por"> · {{ r.por }}</template> · {{ r.estado }}</p>
                </li>
              </ul>
            </section>
          </div>
        </div>
      </template>
    </div>

    <RedactarCorreo v-if="f" :abierto="respondiendo" modo="nuevo" :contacto="contactoRespuesta"
                    @cerrar="respondiendo = false" @enviado="enviado" />
  </div>
</template>
