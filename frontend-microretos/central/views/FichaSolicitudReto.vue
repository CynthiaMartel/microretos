<!-- Ficha de una solicitud de retos (Primer contacto). Muestra lo que contó el docente; el superadmin
     elige la empresa y abre el generador con esas respuestas (se genera allí, con revisión humana).
     Los retos guardados desde el generador aparecen aquí, y «Avisar al docente» es manual: se eligen
     los retos que se le envían, por si se ha generado más de una vez. -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import RedactarCorreo from '../components/RedactarCorreo.vue'
import { cambiarEstadoCorreo } from '../../src/services/primerContactoService.js'
import VistaPreviaSolicitud from '../components/VistaPreviaSolicitud.vue'
import GenerarRetosPeticion from '../components/GenerarRetosPeticion.vue'
import AvisoAccionCritica from '../../src/components/AvisoAccionCritica.vue'
import { getCentros } from '../../src/services/datosFPService.js'
import { useAuthStore, ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB } from '../../src/stores/auth.js'
import {
  getSolicitud, actualizarSolicitud, reenviarSolicitud, avisarDocente, getCorreosSolicitud, getEmpresasPeticion, publicarRetosSolicitud,
  NIVELES, PUNTOS_TEMARIO,
} from '../../src/services/solicitudRetoService.js'
import { FASES_SOLICITUD, fechaHora, textoSesion, faltaEncuentro, urlApp } from '../primerContacto.js'

const route = useRoute()
const auth = useAuthStore()
const esSuperadmin = computed(() => auth.userRole === ROLE_SUPERADMIN)
// Gestionan las peticiones (empresa, generar aquí, publicar): superadmin y Admin Central DuaLab. El generador
// completo de la app es solo del superadmin (Admin Central DuaLab no entra en la app de docentes).
const gestiona = computed(() => [ROLE_SUPERADMIN, ROLE_ADMIN_CENTRAL_DUALAB].includes(auth.userRole))

const s = ref(null)
const error = ref('')
const aviso = ref('')
const guardando = ref(false)

async function cargar() {
  try {
    s.value = (await getSolicitud(route.params.id)).data.data
    notas.value = s.value.notas || ''
    empresaElegida.value = Object.fromEntries((s.value.bloques || []).map(b => [b.indice, b.empresa?.id ?? '']))
    retosElegidos.value = []
  } catch (e) {
    error.value = e.response?.data?.error || 'No se ha podido abrir la solicitud.'
  }
}
onMounted(async () => {
  await cargar()
  cargarCorreos()
  if (gestiona.value && s.value?.bloques?.length) cargarEmpresasPeticiones()
  if (gestiona.value) cargarCentros()
})

async function guardar(cambios, texto) {
  guardando.value = true
  aviso.value = ''
  try {
    s.value = (await actualizarSolicitud(s.value.id, cambios)).data.data
    empresaElegida.value = Object.fromEntries((s.value.bloques || []).map(b => [b.indice, b.empresa?.id ?? '']))
    aviso.value = texto
  } catch (e) {
    aviso.value = e.response?.data?.message || 'No se ha podido guardar el cambio.'
  } finally {
    guardando.value = false
  }
}

// ── Enlace ──────────────────────────────────────────────────────────────────
const enlace = ref('')
const copiado = ref(false)
async function reenviar() {
  if (!confirm(`Se enviará un enlace nuevo a ${s.value.email_docente} y el anterior dejará de funcionar. ¿Continuar?`)) return
  guardando.value = true
  try {
    const { data } = await reenviarSolicitud(s.value.id)
    s.value = data.data
    enlace.value = data.enlace
    aviso.value = 'Enlace nuevo enviado por correo.'
  } catch (e) {
    aviso.value = e.response?.data?.message || 'No se ha podido reenviar.'
  } finally {
    guardando.value = false
  }
}
async function copiar() {
  try { await navigator.clipboard.writeText(enlace.value); copiado.value = true } catch { copiado.value = false }
}

// ── Empresa de cada petición y notas ────────────────────────────────────────
// Solo se ofrecen las empresas con la familia de la petición (primero las del centro con el que se
// abrirá el generador): así, al abrirlo, la familia del paso 3 ya encaja con la empresa.
const empresasPorPeticion = ref({})   // { índice: [{ id, nombre, sector, es_simulada, centro }] }
const empresaElegida = ref({})        // { índice: empresa_id | '' }
async function cargarEmpresasPeticiones() {
  const listas = await Promise.all(s.value.bloques.map(b =>
    getEmpresasPeticion(s.value.id, b.indice).then(r => r.data.data).catch(() => [])))
  empresasPorPeticion.value = Object.fromEntries(s.value.bloques.map((b, i) => [b.indice, listas[i]]))
}
const guardarEmpresa = (b) => guardar(
  { bloque: b.indice, empresa_id: empresaElegida.value[b.indice] || null },
  empresaElegida.value[b.indice] ? `Empresa guardada para la petición ${b.indice + 1}.` : 'Empresa quitada.')
const notas = ref('')

// ── Retos y aviso ───────────────────────────────────────────────────────────
const retosElegidos = ref([])
const mensaje = ref('')
async function avisar() {
  const n = retosElegidos.value.length
  if (!n || !confirm(`Se enviará a ${s.value.email_docente} el correo «Tus retos están listos» con ${n} ${n === 1 ? 'reto' : 'retos'}. ¿Enviar?`)) return
  guardando.value = true
  aviso.value = ''
  try {
    s.value = (await avisarDocente(s.value.id, { retos: retosElegidos.value, mensaje: mensaje.value.trim() || null })).data.data
    retosElegidos.value = []
    mensaje.value = ''
    aviso.value = 'Aviso enviado al docente.'
  } catch (e) {
    aviso.value = e.response?.data?.message || 'No se ha podido enviar el aviso.'
  } finally {
    guardando.value = false
  }
}

// ── Correos con el docente (info@, por la referencia del asunto o su email) ──
const correos = ref([])
const cargandoCorreos = ref(true)
const errorCorreos = ref('')
const escribiendo = ref(false)
async function cargarCorreos() {
  cargandoCorreos.value = true
  errorCorreos.value = ''
  try { correos.value = (await getCorreosSolicitud(route.params.id)).data.data }
  catch (e) { errorCorreos.value = e.response?.data?.error || 'No se han podido cargar los correos.' }
  finally { cargandoCorreos.value = false }
}
// Quitar un correo de la lista, en dos pasos (la fila pide confirmación). Se archiva: sigue en el
// buzón de info@ y se recupera en «Respuestas de solicitudes» con el filtro «Archivados».
const quitandoCorreo = ref(null)     // id del correo que pide confirmación
const quitando = ref(false)
async function quitarCorreo(c) {
  quitando.value = true
  try {
    await cambiarEstadoCorreo(c.id, 'archivado')
    correos.value = correos.value.filter(x => x.id !== c.id)
    aviso.value = 'Correo quitado de la lista. Sigue en info@: lo recuperas en «Respuestas de solicitudes» → Archivados.'
  } catch (e) {
    aviso.value = e.response?.data?.error || 'No se ha podido quitar el correo. Inténtalo de nuevo.'
  } finally {
    quitando.value = false
    quitandoCorreo.value = null
  }
}

// Correo nuevo desde info@ con la referencia en el asunto: su respuesta vuelve a esta solicitud
const contactoDocente = computed(() => s.value && {
  email: s.value.email_docente, nombre: s.value.nombre_docente.split(' ')[0],
  asunto: `DuaLab · Tus retos · Ref. SR-${s.value.id}`,
})
function correoEnviado() {
  escribiendo.value = false
  aviso.value = 'Correo enviándose desde info@. Aparecerá aquí en un par de minutos, cuando se sincronice el buzón.'
  setTimeout(cargarCorreos, 120000)
}

const viendoComoDocente = ref(false)

// Cada petición se abre en el generador por separado (?bloque=N)
// ── Publicar retos de la recámara en la biblioteca de un centro (acción crítica) ──
const centros = ref([])
const centroPublicar = ref('')
const confirmandoPublicar = ref(false)
const publicando = ref(false)
async function cargarCentros() {
  try {
    centros.value = (await getCentros()).data
    // Por defecto, el centro del docente si está dado de alta; si no, el de la beta
    centroPublicar.value = s.value?.centro?.id ?? centros.value.find(c => c.nombre === 'DuaLab')?.id ?? ''
  } catch { centros.value = [] }
}
const retosParaPublicar = computed(() => (s.value?.retos ?? []).filter(r => r.en_recamara && retosElegidos.value.includes(r.uuid)))
async function publicar() {
  publicando.value = true
  aviso.value = ''
  try {
    const n = retosParaPublicar.value.length
    s.value = (await publicarRetosSolicitud(s.value.id, { retos: retosParaPublicar.value.map(r => r.uuid), centro_id: centroPublicar.value })).data.data
    retosElegidos.value = []
    confirmandoPublicar.value = false
    aviso.value = `${n} ${n === 1 ? 'reto publicado' : 'retos publicados'} en la biblioteca de ${centros.value.find(c => c.id === centroPublicar.value)?.nombre ?? 'el centro'}.`
  } catch (e) {
    aviso.value = e.response?.data?.message || 'No se han podido publicar los retos.'
  } finally {
    publicando.value = false
  }
}

const enlaceGenerador = (indice) => `${urlApp()}/retos/crear?solicitud=${s.value.id}&bloque=${indice}`

// ── Lectura de las respuestas ───────────────────────────────────────────────
const texto = (lista, valor) => lista.find(o => o.valor === valor)?.texto ?? ''
const curso = (c) => ({ 1: '1.º', 2: '2.º', ambos_cursos: '1.º y 2.º' })[c] ?? ''
const r = computed(() => s.value?.respuestas ?? {})
const filasBloque = (b) => [
  { e: 'Familia', v: b.familia },
  { e: 'Ciclo', v: b.ciclo },
  { e: 'Curso', v: curso(b.curso) },
  { e: 'Módulos', v: b.modulos.map(m => m.nombre).join(' · ') || 'Los elige DuaLab' },
  { e: 'Tipo de propuesta', v: b.tipoPropuesta },
].filter(x => x.v)
const grupo = computed(() => s.value ? [
  { e: 'Punto del temario', v: [texto(PUNTOS_TEMARIO, r.value.puntoTemario), r.value.unidadTemario].filter(Boolean).join(' · ') },
  { e: 'Nivel', v: texto(NIVELES, r.value.nivelGrupo) || 'Que lo decida DuaLab' },
  { e: 'Sesión', v: [textoSesion(s.value.sesion), s.value.sesion?.duracion].filter(Boolean).join(' · ') },
].filter(x => x.v) : [])
const pretende = computed(() => [
  { e: 'Tareas en la empresa', v: r.value.tareasEmpresa },
  { e: 'Entregable', v: r.value.entregable },
  { e: 'Observaciones', v: r.value.observaciones },
].filter(x => x.v))

const volver = computed(() => ({ name: 'solicitudes-reto', query: route.query }))
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-5xl space-y-4 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Solicitud de retos" :titulo="s ? s.nombre_docente : 'Solicitud'" color="text-rol"
                       :volver="volver" volver-label="Solicitudes"
                       :subtitulo="s ? `${s.centro_nombre} · ${s.email_docente}` : ''" />

      <p v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ error }}</p>
      <p v-else-if="!s" class="py-10 text-center text-sm text-texto-secundario" role="status">Cargando…</p>

      <template v-else>
        <!-- Estado y acciones -->
        <section class="flex flex-col gap-3 rounded-2xl border border-borde bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:p-5" aria-label="Estado">
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-bold" :class="FASES_SOLICITUD[s.fase]?.clases">{{ FASES_SOLICITUD[s.fase]?.texto ?? s.fase }}</span>
            <span v-if="faltaEncuentro(s)" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Falta encuentro · sesión {{ textoSesion(s.sesion) }}</span>
          </div>
          <div class="flex flex-wrap gap-2 sm:ml-auto">
            <button type="button" class="accion-secundaria" @click="viendoComoDocente = true">Ver cómo lo ve el docente</button>
            <button v-if="s.estado === 'pendiente'" type="button" class="accion-secundaria" :disabled="guardando" @click="reenviar">Reenviar enlace</button>
            <button v-if="s.estado !== 'descartada'" type="button" class="accion-secundaria" :disabled="guardando" @click="guardar({ descartada: true }, 'Solicitud descartada.')">Descartar</button>
            <button v-else type="button" class="accion-secundaria" :disabled="guardando" @click="guardar({ descartada: false }, 'Solicitud recuperada.')">Recuperar</button>
          </div>
          <p v-if="aviso" class="w-full text-sm font-semibold text-texto-secundario" role="status">{{ aviso }}</p>
          <div v-if="enlace" class="flex w-full flex-col gap-2 sm:flex-row sm:items-center">
            <p class="min-w-0 flex-1 break-all rounded-xl bg-[#F8FAFC] px-3 py-2 font-mono text-xs">{{ enlace }}</p>
            <button type="button" class="accion-secundaria self-end sm:self-auto" @click="copiar">{{ copiado ? 'Copiado' : 'Copiar enlace' }}</button>
          </div>
          <p v-if="s.estado === 'pendiente'" class="w-full text-xs text-texto-secundario">
            Enviada el {{ fechaHora(s.enviado_at) }}<template v-if="s.expira_at"> · el enlace {{ s.enlace_vigente ? 'vale hasta el' : 'caducó el' }} {{ fechaHora(s.expira_at) }}</template>.
            El enlace solo se ve al crearlo o reenviarlo: si lo necesitas para pasarlo por otro canal, reenvíalo.
          </p>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <div class="space-y-4 lg:col-span-2">
            <p v-if="s.estado === 'pendiente'" class="rounded-2xl border border-borde bg-white p-5 text-sm text-texto-secundario shadow-sm">
              El docente aún no ha enviado la solicitud. Sus respuestas aparecerán aquí.
            </p>

            <template v-else>
              <!-- Una tarjeta por petición: cada una se lleva al generador por separado -->
              <section v-for="b in s.bloques" :key="'b' + b.indice" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" :aria-labelledby="'sol-bloque-' + b.indice">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                  <h2 :id="'sol-bloque-' + b.indice" class="font-heading text-lg font-bold">
                    {{ s.bloques.length > 1 ? `Petición ${b.indice + 1} de ${s.bloques.length}` : 'Qué quiere trabajar' }}
                  </h2>
                  <a v-if="esSuperadmin && s.estado === 'respondida'" :href="enlaceGenerador(b.indice)" target="_blank" rel="noopener" class="accion-principal">Abrir en el generador</a>
                </div>
                <dl class="space-y-3">
                  <div v-for="f in filasBloque(b)" :key="f.e" class="min-w-0">
                    <dt class="text-xs font-bold uppercase tracking-wider text-texto-secundario">{{ f.e }}</dt>
                    <dd class="mt-0.5 whitespace-pre-line break-words text-sm">{{ f.v }}</dd>
                  </div>
                </dl>

                <!-- Empresa de esta petición: solo las de su familia -->
                <div class="mt-4 rounded-xl bg-[#F8FAFC] p-3 sm:p-4">
                  <template v-if="gestiona">
                    <label :for="'sol-empresa-' + b.indice" class="field-label">Empresa para esta petición</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                      <select :id="'sol-empresa-' + b.indice" v-model="empresaElegida[b.indice]" class="field-input min-w-0 flex-1">
                        <option value="">Sin elegir: la elijo en el generador</option>
                        <option v-if="b.empresa && !(empresasPorPeticion[b.indice] || []).some(e => e.id === b.empresa.id)" :value="b.empresa.id">{{ b.empresa.nombre }}</option>
                        <option v-for="e in empresasPorPeticion[b.indice] || []" :key="e.id" :value="e.id">
                          {{ e.nombre }} · {{ e.centro }}{{ e.es_simulada ? ' · ficticia' : '' }}
                        </option>
                      </select>
                      <button type="button" class="accion-secundaria" :disabled="guardando || (empresaElegida[b.indice] || null) === (b.empresa?.id ?? null)"
                              @click="guardarEmpresa(b)">Guardar</button>
                    </div>
                    <p class="mt-1.5 text-xs text-texto-secundario">
                      <template v-if="empresasPorPeticion[b.indice] && !empresasPorPeticion[b.indice].length">No hay empresas de {{ b.familia }}: créala en el generador (ficticia con IA o del catálogo).</template>
                      <template v-else>Solo empresas de {{ b.familia }}. Al abrir el generador quedará elegida, con su centro y la familia ya puestos.</template>
                    </p>
                  </template>
                  <p v-else class="text-sm"><span class="font-semibold">Empresa:</span> {{ b.empresa?.nombre || 'la elige Admin Central DuaLab o el superadmin al generar los retos' }}</p>
                </div>

                <!-- Generación rápida (sin abrir el generador de la app); se recarga al cambiar la empresa -->
                <GenerarRetosPeticion v-if="gestiona && s.estado === 'respondida'" :key="`gen-${b.indice}-${b.empresa?.id ?? 0}`"
                                      :solicitud-id="s.id" :bloque="b.indice" @guardado="cargar" />
                <div v-if="b.ra_ce.length" class="mt-4 space-y-3 border-t border-borde pt-4">
                  <p class="text-xs font-bold uppercase tracking-wider text-texto-secundario">RA y CE elegidos</p>
                  <div v-for="ra in b.ra_ce" :key="ra.ra_id" class="border-l-2 border-centros/30 pl-3">
                    <p class="break-words text-sm font-semibold">{{ ra.ra }}</p>
                    <ul class="mt-1 list-disc pl-5 text-sm text-texto-secundario">
                      <li v-for="(ce, i) in ra.ce" :key="i" class="break-words">{{ ce }}</li>
                    </ul>
                  </div>
                </div>
              </section>

              <section v-for="bloque in [
                { id: 'grupo', t: 'Su grupo y la sesión', filas: grupo },
                { id: 'pretende', t: 'Para afinar los retos', filas: pretende },
              ]" :key="bloque.id" class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" :aria-labelledby="'sol-' + bloque.id">
                <h2 :id="'sol-' + bloque.id" class="mb-3 font-heading text-lg font-bold">{{ bloque.t }}</h2>
                <p v-if="!bloque.filas.length" class="text-sm text-texto-secundario">Sin respuesta.</p>
                <dl v-else class="space-y-3">
                  <div v-for="f in bloque.filas" :key="f.e" class="min-w-0">
                    <dt class="text-xs font-bold uppercase tracking-wider text-texto-secundario">{{ f.e }}</dt>
                    <dd class="mt-0.5 whitespace-pre-line break-words text-sm">{{ f.v }}</dd>
                  </div>
                </dl>
              </section>
            </template>
          </div>

          <div class="space-y-4">
            <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" aria-labelledby="sol-retos">
              <h2 id="sol-retos" class="mb-2 font-heading text-lg font-bold">Retos</h2>
              <p v-if="!s.retos.length" class="text-sm text-texto-secundario">
                Aún no hay retos. {{ gestiona ? 'Genéralos en la tarjeta de cada petición («Generar retos») y guarda los que te convenzan: aparecerán aquí.' : 'Los genera Admin Central DuaLab o el superadmin.' }}
              </p>
              <template v-else>
                <p class="mb-3 text-xs text-texto-secundario">Los de la recámara solo los ve DuaLab. Marca los que quieras enviar al docente o publicar en una biblioteca.</p>
                <ul class="space-y-2">
                  <li v-for="reto in s.retos" :key="reto.uuid">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-[#F8FAFC] px-3 py-2">
                      <input v-model="retosElegidos" type="checkbox" :value="reto.uuid" class="mt-1 h-4 w-4 shrink-0" :disabled="s.estado !== 'respondida'" />
                      <span class="min-w-0">
                        <span class="block break-words text-sm font-semibold">{{ reto.titulo || 'Reto sin título' }}</span>
                        <span class="block text-xs text-texto-secundario">{{ reto.empresa }} · {{ fechaHora(reto.created_at) }}<template v-if="reto.avisado"> · enviado</template></span>
                        <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-bold"
                              :class="reto.en_recamara ? 'bg-gray-200 text-texto-secundario' : 'bg-empresas/15 text-empresas-dark'">
                          {{ reto.en_recamara ? 'En la recámara' : `En la biblioteca de ${reto.centro}` }}
                        </span>
                      </span>
                    </label>
                  </li>
                </ul>
                <template v-if="s.estado === 'respondida'">
                  <label for="sol-mensaje" class="field-label mt-4">Unas líneas para el docente (opcional)</label>
                  <textarea id="sol-mensaje" v-model="mensaje" maxlength="1000" rows="3" class="field-input"></textarea>
                  <button type="button" class="accion-principal mt-3" :disabled="guardando || !retosElegidos.length" @click="avisar">Avisar al docente</button>
                  <p v-if="s.avisado_at" class="mt-2 text-xs text-texto-secundario">Último aviso: {{ fechaHora(s.avisado_at) }}. Avisar de nuevo le envía un enlace nuevo con los retos que marques.</p>
                </template>

                <!-- Publicar en la biblioteca de un centro: acción crítica, con confirmación -->
                <div v-if="gestiona && s.retos.some(r => r.en_recamara)" class="mt-4 border-t border-borde pt-4">
                  <label for="sol-centro-publicar" class="field-label">Publicar en la biblioteca de</label>
                  <select id="sol-centro-publicar" v-model="centroPublicar" class="field-input">
                    <option value="" disabled>Elige el centro</option>
                    <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                  </select>
                  <p class="mt-1 text-xs text-texto-secundario">Los docentes de ese centro los verán en su biblioteca. {{ s.centro ? '' : 'El centro del docente no está dado de alta: por defecto, el de DuaLab.' }}</p>
                  <button v-if="!confirmandoPublicar" type="button" class="accion-secundaria mt-3"
                          :disabled="!retosParaPublicar.length || !centroPublicar" @click="confirmandoPublicar = true">
                    Publicar en la biblioteca ({{ retosParaPublicar.length }})
                  </button>
                  <div v-else class="mt-3 space-y-3">
                    <AvisoAccionCritica :texto="`Vas a publicar ${retosParaPublicar.length} reto(s) en la biblioteca de «${centros.find(c => c.id === centroPublicar)?.nombre}»: dejan la recámara y los verán sus docentes. Queda en el Historial de Central con tu nombre y la fecha.`" />
                    <div class="flex flex-wrap justify-end gap-2">
                      <button type="button" class="accion-secundaria" :disabled="publicando" @click="confirmandoPublicar = false">Cancelar</button>
                      <button type="button" class="accion-principal" :disabled="publicando" @click="publicar">{{ publicando ? 'Publicando…' : 'Sí, publicar' }}</button>
                    </div>
                  </div>
                </div>
              </template>
            </section>

            <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" aria-labelledby="sol-correos">
              <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 id="sol-correos" class="font-heading text-lg font-bold">Correos con el docente</h2>
                <button type="button" class="accion-secundaria" @click="escribiendo = true">Escribir</button>
              </div>
              <p v-if="cargandoCorreos" class="text-sm text-texto-secundario" role="status">Cargando…</p>
              <p v-else-if="errorCorreos" class="text-sm text-red-700" role="alert">{{ errorCorreos }}</p>
              <p v-else-if="!correos.length" class="text-sm text-texto-secundario">Aún no hay correos con este docente en info@.</p>
              <ul v-else class="space-y-2">
                <li v-for="c in correos" :key="c.id" class="rounded-xl bg-[#F8FAFC]">
                  <div class="flex items-center gap-1">
                    <RouterLink :to="{ name: 'correo-ficha', params: { id: c.id }, query: { desde: 'solicitud', solicitud: s.id } }"
                                class="block min-w-0 flex-1 rounded-xl px-3 py-2 transition hover:bg-gray-100">
                      <span class="flex min-w-0 items-center gap-2">
                        <span v-if="c.estado === 'nuevo'" class="h-2 w-2 shrink-0 rounded-full bg-alumnos" aria-label="No leído" />
                        <span class="min-w-0 truncate text-sm" :class="c.estado === 'nuevo' && c.carpeta !== 'enviados' ? 'font-black' : 'font-semibold'">{{ c.asunto || '(Sin asunto)' }}</span>
                      </span>
                      <span class="block text-xs text-texto-secundario">{{ c.carpeta === 'enviados' ? 'Enviado' : 'Recibido' }} · {{ fechaHora(c.fecha) }}</span>
                    </RouterLink>
                    <button v-if="quitandoCorreo !== c.id" type="button" class="mr-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-texto-secundario transition-colors hover:bg-red-50 hover:text-red-600"
                            :aria-label="`Quitar de la lista el correo «${c.asunto || 'sin asunto'}»`" title="Quitar de la lista" @click="quitandoCorreo = c.id">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                  <!-- Segundo paso: confirmar en la propia fila -->
                  <div v-if="quitandoCorreo === c.id" class="flex flex-wrap items-center gap-2 border-t border-borde px-3 py-2" role="group" aria-label="Confirmar">
                    <p class="min-w-0 flex-1 text-xs text-texto-secundario">¿Quitar este correo de la lista? Seguirá en info@ (archivado).</p>
                    <button type="button" class="h-9 rounded-full border-2 border-gray-200 bg-white px-4 text-xs font-bold text-gray-600 hover:border-gray-300" :disabled="quitando" @click="quitandoCorreo = null">Cancelar</button>
                    <button type="button" class="h-9 rounded-full bg-red-600 px-4 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-50" :disabled="quitando" @click="quitarCorreo(c)">
                      {{ quitando ? 'Quitando…' : 'Quitar' }}
                    </button>
                  </div>
                </li>
              </ul>
              <p class="mt-3 text-xs text-texto-secundario">Lo que escribas sale desde info@ con la referencia de esta solicitud, para que su respuesta vuelva aquí.</p>
            </section>

            <section class="rounded-2xl border border-borde bg-white p-4 shadow-sm sm:p-5" aria-labelledby="sol-notas">
              <h2 id="sol-notas" class="mb-2 font-heading text-lg font-bold">Notas internas</h2>
              <label for="sol-notas-texto" class="sr-only">Notas internas</label>
              <textarea id="sol-notas-texto" v-model="notas" maxlength="2000" rows="4" class="field-input" placeholder="Solo las ve DuaLab"></textarea>
              <button type="button" class="accion-secundaria mt-3" :disabled="guardando || notas === (s.notas || '')"
                      @click="guardar({ notas: notas.trim() || null }, 'Notas guardadas.')">Guardar notas</button>
              <p class="mt-3 text-xs text-texto-secundario">Creada por {{ s.creada_por || '—' }} el {{ fechaHora(s.created_at) }}.</p>
            </section>
          </div>
        </div>
      </template>
    </div>

    <VistaPreviaSolicitud v-if="s" :abierto="viendoComoDocente" :datos="{ nombreDocente: s.nombre_docente, centroNombre: s.centro_nombre }"
                          @cerrar="viendoComoDocente = false" />
    <RedactarCorreo v-if="s" :abierto="escribiendo" modo="nuevo" :contacto="contactoDocente"
                    @cerrar="escribiendo = false" @enviado="correoEnviado" />
  </div>
</template>
