<!-- Solicitud de retos del docente: DuaLab le envía este enlace (token que caduca, sin cuenta) para
     que cuente qué quiere trabajar; con esas respuestas DuaLab elige la empresa y genera los retos.
     Cuatro pasos que se guardan al avanzar (puede seguir más tarde con el mismo enlace). En el paso 2
     puede pedir varias cosas («¿Quieres otro más?»): cada petición lleva lo que necesita el generador
     (currículo y, en el paso 4, qué tipo de propuesta quiere) y DuaLab la genera por separado. Cuando
     DuaLab le avisa, el enlace del aviso muestra aquí los retos que le envió, en solo lectura.
     Campos y límites: EnviarSolicitudRetoRequest.
     Con `vistaPrevia` (DuaLab Central, «Ver cómo lo ve el docente»): mismo formulario sin enlace ni
     llamadas que guarden; se recorre y «Enviar» muestra la pantalla final. -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import BloqueSolicitudReto from '../components/BloqueSolicitudReto.vue'
import { getFamilias } from '../services/datosFPService.js'
import {
  getSolicitudPublica, guardarSolicitud, enviarSolicitud, getRetosSolicitud,
  NIVELES, PUNTOS_TEMARIO,
} from '../services/solicitudRetoService.js'

const props = defineProps({
  vistaPrevia: { type: Boolean, default: false },
  // Nombre y centro con los que se ve la vista previa (los de la solicitud, si se abre desde su ficha)
  datosPrevia: { type: Object, default: () => ({}) },
})

const route = useRoute()
const token = route.params.token
const raiz = ref(null)
// En la vista previa se sube al principio del modal, no de la página
const subir = () => props.vistaPrevia ? raiz.value?.scrollIntoView({ block: 'start', behavior: 'smooth' }) : window.scrollTo({ top: 0, behavior: 'smooth' })

const cargando = ref(true)
const error    = ref('')        // enlace inválido o caducado
const datos    = ref(null)      // datosPublicos del backend
const enviada  = ref(false)
const retos    = ref([])

const PASOS = ['Tú y tu centro', 'Qué quieres trabajar', 'Tu grupo y la sesión', 'Qué pretendes']
const paso       = ref(1)
const guardando  = ref(false)
const errores    = ref({})      // errores de validación del backend por campo
const errorGeneral = ref('')
const verPrivacidad = ref(false)

const form = ref({
  nombreDocente: '', centroNombre: '', aceptaPrivacidad: false,
  nivelGrupo: '', puntoTemario: '', unidadTemario: '',
  sesionFecha: '', sesionHoraInicio: '', sesionHoraFin: '',
  tareasEmpresa: '', entregable: '', observaciones: '',
})
// Peticiones del paso 2 (= EnviarSolicitudRetoRequest::MAX_BLOQUES como máximo). seleccionRaCe en el
// formato de SelectorRaCeModulos: { [raId]: { moduloId, ceIds } }
const MAX_BLOQUES = 5
let siguienteClave = 0
const bloqueVacio = () => ({ clave: siguienteClave++, familiaId: '', cicloId: '', curso: '', moduloIds: [], seleccionRaCe: {}, tipoPropuesta: '' })
const bloques = ref([bloqueVacio()])
const anadirBloque = () => { if (bloques.value.length < MAX_BLOQUES) bloques.value.push(bloqueVacio()) }
const quitarBloque = (i) => { bloques.value.splice(i, 1) }

const familias = ref([])

const LIMITES = { tipoPropuesta: 1000, tareasEmpresa: 1000, entregable: 800, observaciones: 1000, unidadTemario: 150 }
const hoy = new Date().toISOString().slice(0, 10)

// «Gestión Administrativa · 1.º · Comunicación…» para titular cada petición en el paso 4
const CURSOS = { 1: '1.º', 2: '2.º', ambos_cursos: '1.º y 2.º' }
const resumenBloque = (b) => {
  const familia = familias.value.find(f => f.id === Number(b.familiaId))?.nombre
  return [familia, CURSOS[b.curso]].filter(Boolean).join(' · ') || 'Petición sin completar'
}

const pasoValido = computed(() => {
  const f = form.value
  if (paso.value === 1) return !!(f.nombreDocente.trim() && f.centroNombre.trim() && f.aceptaPrivacidad)
  if (paso.value === 2) return bloques.value.every(b => b.familiaId && b.cicloId && b.curso)
  if (paso.value === 3) return !(f.sesionHoraFin && !f.sesionHoraInicio)
  return bloques.value.every(b => b.tipoPropuesta.trim())
})

// ── Carga inicial ───────────────────────────────────────────────────────────
onMounted(async () => {
  if (props.vistaPrevia) {
    datos.value = {
      estado: 'pendiente', nombreDocente: props.datosPrevia.nombreDocente || 'Nombre del docente',
      centroNombre: props.datosPrevia.centroNombre || 'Su centro educativo', respuestas: {}, sesion: {}, avisado: false,
      expira_at: new Date(Date.now() + 21 * 864e5).toISOString(),
      privacidad: { version: '', titulo: 'Información sobre protección de datos',
        texto: 'Aquí el docente lee el texto de protección de datos vigente (config/solicitudes_reto.php).' },
    }
    restaurar(datos.value)
    await cargarCurriculo()
    cargando.value = false
    return
  }
  try {
    const { data } = await getSolicitudPublica(token)
    datos.value = data.data
    restaurar(data.data)
    if (datos.value.estado !== 'pendiente') {
      enviada.value = true
      if (datos.value.avisado) await cargarRetos()
    } else {
      await cargarCurriculo()
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'No encontramos esta solicitud. Revisa que el enlace esté completo.'
  } finally {
    cargando.value = false
  }
})

function restaurar(d) {
  const r = d.respuestas || {}
  Object.assign(form.value, {
    nombreDocente: d.nombreDocente || '', centroNombre: d.centroNombre || '',
    nivelGrupo: r.nivelGrupo || '', puntoTemario: r.puntoTemario || '', unidadTemario: r.unidadTemario || '',
    tareasEmpresa: r.tareasEmpresa || '', entregable: r.entregable || '', observaciones: r.observaciones || '',
    sesionFecha: d.sesion?.fecha || '', sesionHoraInicio: d.sesion?.hora_inicio || '', sesionHoraFin: d.sesion?.hora_fin || '',
  })
  const guardados = (r.bloques || []).map(b => ({
    ...bloqueVacio(),
    familiaId: b.familiaId ?? '', cicloId: b.cicloId ?? '', curso: b.curso ? String(b.curso) : '',
    moduloIds: b.moduloIds || [], tipoPropuesta: b.tipoPropuesta || '',
    seleccionRaCe: Object.fromEntries((b.seleccionRaCe || []).map(s => [s.ra_id, { moduloId: s.modulo_id, ceIds: s.ce_ids || [] }])),
  }))
  bloques.value = guardados.length ? guardados : [bloqueVacio()]
  // Si ya guardó algo, aceptó la privacidad (cada guardado la exige)
  form.value.aceptaPrivacidad = Object.keys(r).length > 0
}

async function cargarCurriculo() {
  try {
    familias.value = (await getFamilias()).data.filter(f => f.con_datos_retos)
  } catch {
    errorGeneral.value = 'No se han podido cargar las familias y los ciclos. Recarga la página.'
  }
}

// ── Guardar y enviar ────────────────────────────────────────────────────────
function payload() {
  const f = form.value
  const vacioANull = (v) => (v === '' || v === undefined ? null : v)
  return {
    nombreDocente: f.nombreDocente.trim() || null, centroNombre: f.centroNombre.trim() || null,
    bloques: bloques.value.map(b => ({
      familiaId: vacioANull(b.familiaId), cicloId: vacioANull(b.cicloId), curso: vacioANull(b.curso),
      moduloIds: b.moduloIds,
      seleccionRaCe: Object.entries(b.seleccionRaCe)
        .filter(([, s]) => s.ceIds?.length)
        .map(([raId, s]) => ({ ra_id: Number(raId), modulo_id: s.moduloId, ce_ids: s.ceIds })),
      tipoPropuesta: b.tipoPropuesta.trim() || null,
    })),
    nivelGrupo: vacioANull(f.nivelGrupo), puntoTemario: vacioANull(f.puntoTemario),
    unidadTemario: f.unidadTemario.trim() || null,
    sesionFecha: vacioANull(f.sesionFecha), sesionHoraInicio: vacioANull(f.sesionHoraInicio), sesionHoraFin: vacioANull(f.sesionHoraFin),
    tareasEmpresa: f.tareasEmpresa.trim() || null,
    entregable: f.entregable.trim() || null, observaciones: f.observaciones.trim() || null,
    aceptaPrivacidad: f.aceptaPrivacidad, privacidadVersion: datos.value.privacidad.version,
  }
}

function tratarError(e) {
  if (e.response?.status === 422) {
    errores.value = e.response.data.errors || {}
    errorGeneral.value = 'Revisa los campos marcados.'
  } else if (e.response?.status === 410 || e.response?.status === 404) {
    error.value = e.response.data?.error || 'Este enlace ya no es válido.'
  } else {
    errorGeneral.value = 'No se ha podido guardar. Revisa tu conexión e inténtalo de nuevo.'
  }
}

async function siguiente() {
  if (!pasoValido.value || guardando.value) return
  guardando.value = true
  errores.value = {}; errorGeneral.value = ''
  try {
    if (!props.vistaPrevia) await guardarSolicitud(token, payload())
    paso.value++
    subir()
  } catch (e) {
    tratarError(e)
  } finally {
    guardando.value = false
  }
}

function anterior() {
  errores.value = {}; errorGeneral.value = ''
  paso.value--
  subir()
}

async function enviar() {
  if (!pasoValido.value || guardando.value) return
  guardando.value = true
  errores.value = {}; errorGeneral.value = ''
  try {
    if (!props.vistaPrevia) await enviarSolicitud(token, payload())
    enviada.value = true
    subir()
  } catch (e) {
    tratarError(e)
  } finally {
    guardando.value = false
  }
}

const errorDe = (...campos) => campos.map(c => errores.value[c]?.[0] || Object.entries(errores.value)
  .find(([k]) => k.startsWith(c + '.'))?.[1]?.[0]).find(Boolean) || ''

// ── Retos enviados por DuaLab (solo lectura) ────────────────────────────────
async function cargarRetos() {
  try {
    retos.value = (await getRetosSolicitud(token)).data.data || []
  } catch {
    retos.value = []
  }
}

const fecha = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' }) : ''
</script>

<template>
  <div ref="raiz" class="flex flex-col bg-[#F8FAFC] font-sans text-[#1F2937]" :class="vistaPrevia ? '' : 'min-h-screen'">
    <header class="border-b border-gray-100 bg-white px-4 py-3 sm:px-6">
      <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
          <img src="../assets/logo_colores.png" alt="" class="h-9 w-auto shrink-0 object-contain sm:h-10" />
          <span class="text-2xl font-black uppercase tracking-tight text-acceso-tinta">
            Dua<span class="bg-gradient-to-r from-acceso-azul to-[#00B6C7] bg-clip-text text-transparent">Lab</span>
          </span>
        </div>
        <span class="hidden truncate text-xs font-semibold text-texto-secundario sm:block">Solicitud de retos para tu alumnado</span>
      </div>
    </header>

    <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-6 sm:py-10">
      <p v-if="cargando" class="py-16 text-center text-sm text-gray-500" role="status">Cargando tu solicitud…</p>

      <div v-else-if="error" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center" role="alert">
        <p class="font-bold text-amber-900">{{ error }}</p>
      </div>

      <!-- Enviada: gracias o, si DuaLab ya avisó, los retos -->
      <template v-else-if="enviada">
        <div v-if="!retos.length" class="rounded-[2rem] border border-gray-100 bg-white p-6 text-center shadow-[0_20px_50px_rgb(0,0,0,0.05)] sm:rounded-[2.5rem] sm:p-10">
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-empresas/15 text-empresas-dark" aria-hidden="true">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
          </span>
          <h1 class="mt-4 font-heading text-2xl font-bold text-azul-noche sm:text-3xl">¡Gracias, {{ form.nombreDocente }}!</h1>
          <p class="mx-auto mt-2 max-w-md text-sm text-gray-600">
            Hemos recibido tu solicitud. Elegiremos la empresa y prepararemos los retos;
            cuando estén listos te avisaremos por correo.
          </p>
        </div>

        <template v-else>
          <CabeceraSeccion antetitulo="Solicitud de retos" titulo="Tus" destacado="retos" color="text-centros"
                           :subtitulo="`${form.nombreDocente}, los hemos preparado a partir de lo que nos contaste. Si quieres cambiar algo, responde al correo.`" />

          <article v-for="reto in retos" :key="reto.uuid" class="mt-5 rounded-[2rem] border border-gray-100 bg-white p-5 shadow-[0_20px_50px_rgb(0,0,0,0.05)] sm:p-7">
            <p v-if="reto.empresa_nombre" class="text-xs font-bold uppercase tracking-widest text-empresas-dark break-words">{{ reto.empresa_nombre }}</p>
            <h2 class="mt-1 text-lg font-black break-words sm:text-xl">{{ reto.titulo }}</h2>
            <p v-if="reto.subtitulo" class="mt-1 text-sm text-gray-600 break-words">{{ reto.subtitulo }}</p>

            <div v-if="reto.pregunta_reto" class="mt-4 rounded-xl bg-centros/5 p-4">
              <p class="text-xs font-bold uppercase tracking-widest text-centros">El reto</p>
              <p class="mt-1 text-sm break-words">{{ reto.pregunta_reto }}</p>
            </div>

            <details v-if="reto.quien_es || reto.dia_a_dia" class="mt-4">
              <summary class="cursor-pointer text-sm font-bold text-gray-700">La empresa</summary>
              <p v-if="reto.quien_es" class="mt-2 text-sm text-gray-600 break-words">{{ reto.quien_es }}</p>
              <p v-if="reto.dia_a_dia" class="mt-2 text-sm text-gray-600 break-words">{{ reto.dia_a_dia }}</p>
            </details>

            <details v-if="reto.evaluacion_oficial?.length" class="mt-3">
              <summary class="cursor-pointer text-sm font-bold text-gray-700">Resultados de aprendizaje y criterios</summary>
              <div v-for="(ev, i) in reto.evaluacion_oficial" :key="i" class="mt-3 border-l-2 border-centros/30 pl-3">
                <p class="text-xs font-bold text-gray-500 break-words">{{ ev.modulo }}</p>
                <p class="text-sm font-semibold break-words">{{ ev.ra }}</p>
                <ul class="mt-1 list-disc pl-5 text-sm text-gray-600">
                  <li v-for="(ce, j) in ev.ce" :key="j" class="break-words">{{ ce }}</li>
                </ul>
              </div>
            </details>
          </article>
        </template>
      </template>

      <!-- Formulario por pasos -->
      <template v-else>
        <CabeceraSeccion antetitulo="Solicitud de retos" titulo="Cuéntanos qué quieres" destacado="trabajar" color="text-centros"
                         subtitulo="Con esto te preparamos los retos; la empresa la elegimos nosotros. Se guarda al pasar de paso: puedes dejarlo a medias y seguir con el mismo enlace." />

        <div class="relative mt-6">
          <ol class="relative z-10 flex" aria-label="Pasos">
            <li v-for="(nombre, i) in PASOS" :key="nombre" class="flex w-1/4 flex-col items-center gap-1.5"
                :aria-current="i + 1 === paso ? 'step' : undefined">
              <span class="flex h-9 w-9 items-center justify-center rounded-full font-black transition-all duration-500 md:h-11 md:w-11"
                    :class="i + 1 <= paso ? 'bg-gradient-to-r from-centros to-primary-400 text-white shadow-lg' : 'border-2 border-gray-200 bg-white text-gray-400'">
                <svg v-if="i + 1 < paso" class="h-4 w-4 md:h-5 md:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                <span v-else>{{ i + 1 }}</span>
              </span>
              <span class="px-1 text-center text-[11px] font-black uppercase leading-tight tracking-wide sm:text-xs"
                    :class="i + 1 <= paso ? 'text-centros' : 'text-gray-400'">{{ nombre }}</span>
            </li>
          </ol>
          <div class="absolute left-[12.5%] right-[12.5%] top-[18px] h-1 rounded-full bg-gray-200 md:top-[22px]" aria-hidden="true"></div>
          <div class="absolute left-[12.5%] top-[18px] h-1 rounded-full bg-gradient-to-r from-centros to-primary-400 transition-all duration-700 md:top-[22px]"
               :style="{ width: ((paso - 1) / (PASOS.length - 1)) * 75 + '%' }" aria-hidden="true"></div>
        </div>

        <section class="mt-6 rounded-[2rem] border border-gray-100 bg-white p-5 shadow-[0_20px_50px_rgb(0,0,0,0.05)] sm:rounded-[2.5rem] sm:p-8">
          <!-- Paso 1 -->
          <div v-if="paso === 1" class="space-y-5">
            <div>
              <label for="sr-nombre" class="label-style">Tu nombre *</label>
              <input id="sr-nombre" v-model="form.nombreDocente" maxlength="150" class="input-style" autocomplete="name" />
              <p v-if="errorDe('nombreDocente')" class="mt-1 ml-4 text-xs text-red-600">{{ errorDe('nombreDocente') }}</p>
            </div>
            <div>
              <label for="sr-centro" class="label-style">Centro educativo *</label>
              <input id="sr-centro" v-model="form.centroNombre" maxlength="255" class="input-style" />
              <p v-if="errorDe('centroNombre')" class="mt-1 ml-4 text-xs text-red-600">{{ errorDe('centroNombre') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
              <label class="flex items-start gap-3 text-sm">
                <input v-model="form.aceptaPrivacidad" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 accent-[#3072AA]" />
                <span>He leído la <button type="button" class="font-bold text-centros underline" @click="verPrivacidad = !verPrivacidad">{{ datos.privacidad.titulo.toLowerCase() }}</button> y la acepto.</span>
              </label>
              <p v-if="verPrivacidad" class="mt-3 whitespace-pre-line text-xs leading-relaxed text-gray-600">{{ datos.privacidad.texto }}</p>
              <p v-if="errorDe('aceptaPrivacidad', 'privacidadVersion')" class="mt-2 text-xs text-red-600">{{ errorDe('aceptaPrivacidad', 'privacidadVersion') }}</p>
            </div>
          </div>

          <!-- Paso 2: una o varias peticiones -->
          <div v-else-if="paso === 2" class="space-y-6">
            <template v-for="(b, i) in bloques" :key="b.clave">
              <hr v-if="i > 0" class="border-gray-100" />
              <BloqueSolicitudReto v-model:bloque="bloques[i]" :indice="i" :familias="familias"
                                   :puede-quitar="bloques.length > 1" :error-de="errorDe" @quitar="quitarBloque(i)" />
            </template>
            <p v-if="errores.bloques" class="ml-4 text-xs text-red-600">{{ errores.bloques[0] }}</p>

            <div v-if="bloques.length < MAX_BLOQUES" class="rounded-2xl border-2 border-dashed border-centros/30 bg-centros/5 p-4 sm:p-5">
              <p class="text-sm font-bold text-azul-noche">¿Quieres otro más?</p>
              <p class="mt-0.5 text-xs text-gray-600">Si quieres retos para otro ciclo, curso o módulo, añade otra petición: te preparamos los retos de cada una.</p>
              <button type="button" class="mt-3 inline-flex min-h-9 items-center gap-1.5 rounded-full border-2 border-centros/40 bg-white px-4 text-sm font-bold text-centros hover:bg-centros/5"
                      @click="anadirBloque">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/></svg>
                Añadir otra petición
              </button>
            </div>
          </div>

          <!-- Paso 3 -->
          <div v-else-if="paso === 3" class="space-y-6">
            <div>
              <p class="label-style">¿En qué punto del temario están?</p>
              <div class="flex flex-wrap gap-2">
                <button v-for="op in PUNTOS_TEMARIO" :key="op.valor" type="button" :aria-pressed="form.puntoTemario === op.valor"
                        class="min-h-9 rounded-full border-2 px-4 py-1.5 text-xs font-bold transition-colors"
                        :class="form.puntoTemario === op.valor ? 'border-transparent bg-gradient-to-r from-centros to-primary-400 text-white shadow-md' : 'border-gray-200 bg-white text-gray-700 hover:border-centros/50'"
                        @click="form.puntoTemario = form.puntoTemario === op.valor ? '' : op.valor">{{ op.texto }}</button>
              </div>
              <label for="sr-unidad" class="label-style mt-4">Unidad o tema (opcional)</label>
              <input id="sr-unidad" v-model="form.unidadTemario" :maxlength="LIMITES.unidadTemario" class="input-style" placeholder="Por ejemplo: UT3, comunicación escrita" />
            </div>

            <div>
              <p class="label-style">Nivel del grupo</p>
              <div class="flex flex-wrap gap-2">
                <button v-for="op in NIVELES" :key="op.valor" type="button" :aria-pressed="form.nivelGrupo === op.valor" :title="op.ayuda"
                        class="min-h-9 rounded-full border-2 px-4 py-1.5 text-xs font-bold transition-colors"
                        :class="form.nivelGrupo === op.valor ? 'border-transparent bg-gradient-to-r from-centros to-primary-400 text-white shadow-md' : 'border-gray-200 bg-white text-gray-700 hover:border-centros/50'"
                        @click="form.nivelGrupo = op.valor">{{ op.texto }}</button>
                <button type="button" :aria-pressed="form.nivelGrupo === ''"
                        class="min-h-9 rounded-full border-2 px-4 py-1.5 text-xs font-bold transition-colors"
                        :class="form.nivelGrupo === '' ? 'border-transparent bg-gray-700 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-400'"
                        @click="form.nivelGrupo = ''">No lo sé, que lo decida DuaLab</button>
              </div>
              <p v-if="form.nivelGrupo" class="mt-2 ml-4 text-xs text-gray-500">{{ NIVELES.find(n => n.valor === form.nivelGrupo)?.ayuda }}</p>
            </div>

            <div>
              <p class="label-style">¿Cuándo haríais la sesión en el aula? (opcional)</p>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="min-w-0">
                  <label for="sr-fecha" class="sr-only">Fecha</label>
                  <input id="sr-fecha" v-model="form.sesionFecha" type="date" :min="hoy" class="input-style" />
                </div>
                <div class="min-w-0">
                  <label for="sr-inicio" class="mb-1 ml-4 block text-xs text-gray-500">Desde</label>
                  <input id="sr-inicio" v-model="form.sesionHoraInicio" type="time" class="input-style" />
                </div>
                <div class="min-w-0">
                  <label for="sr-fin" class="mb-1 ml-4 block text-xs text-gray-500">Hasta</label>
                  <input id="sr-fin" v-model="form.sesionHoraFin" type="time" class="input-style" />
                </div>
              </div>
              <p v-if="errorDe('sesionFecha', 'sesionHoraInicio', 'sesionHoraFin')" class="mt-2 ml-4 text-xs text-red-600">{{ errorDe('sesionFecha', 'sesionHoraInicio', 'sesionHoraFin') }}</p>
            </div>
          </div>

          <!-- Paso 4: lo que el generador pregunta (una vez por petición) y contexto para DuaLab -->
          <div v-else class="space-y-6">
            <div v-for="(b, i) in bloques" :key="b.clave">
              <div class="mb-2 ml-4 flex flex-wrap items-center gap-x-2 gap-y-1">
                <label :for="`sr-propuesta-${i}`" class="text-xs font-bold uppercase tracking-[0.15em] text-gray-500">¿Qué tipo de propuesta quieres trabajar? *</label>
                <span v-if="bloques.length > 1" class="rounded-full bg-centros/10 px-2.5 py-0.5 text-xs font-bold text-centros break-words">{{ i + 1 }}. {{ resumenBloque(b) }}</span>
              </div>
              <p class="mb-2 ml-4 text-xs text-gray-500">Orienta el reto hacia el tipo de trabajo que te interesa con tu alumnado.</p>
              <textarea :id="`sr-propuesta-${i}`" v-model="b.tipoPropuesta" :maxlength="LIMITES.tipoPropuesta" rows="3" class="input-style resize-y"
                        placeholder="Ej: que diseñen un sistema para mejorar la gestión del stock, o una campaña para captar clientes jóvenes…"></textarea>
              <div class="mt-1 flex justify-between gap-2 px-4 text-xs">
                <span class="text-red-600">{{ errorDe(`bloques.${i}.tipoPropuesta`) }}</span>
                <span class="shrink-0 text-gray-400">{{ b.tipoPropuesta.length }}/{{ LIMITES.tipoPropuesta }}</span>
              </div>
            </div>

            <div class="space-y-5 border-t border-gray-100 pt-6">
              <p class="ml-4 text-sm font-bold text-azul-noche">Para afinar los retos (opcional)</p>
              <div v-for="campo in [
                { k: 'tareasEmpresa', t: '¿Qué tareas reales harán en la empresa durante las prácticas?', p: 'Si lo sabes, nos ayuda a elegir la empresa.' },
                { k: 'entregable', t: '¿Qué te gustaría que entregara el alumnado?', p: 'Un registro, un informe corto, una presentación…' },
                { k: 'observaciones', t: 'Algo más que debamos saber', p: 'Por ejemplo, si ya tienes una empresa en mente.' },
              ]" :key="campo.k">
                <label :for="'sr-' + campo.k" class="label-style">{{ campo.t }}</label>
                <textarea :id="'sr-' + campo.k" v-model="form[campo.k]" :maxlength="LIMITES[campo.k]" rows="3" class="input-style resize-y" :placeholder="campo.p"></textarea>
                <div class="mt-1 flex justify-between gap-2 px-4 text-xs">
                  <span class="text-red-600">{{ errorDe(campo.k) }}</span>
                  <span class="shrink-0 text-gray-400">{{ form[campo.k].length }}/{{ LIMITES[campo.k] }}</span>
                </div>
              </div>
            </div>
          </div>

          <p v-if="errorGeneral" class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ errorGeneral }}</p>

          <div class="fila-pasos mt-8">
            <button v-if="paso > 1" type="button" class="btn-paso-atras" :disabled="guardando" @click="anterior">Anterior</button>
            <button v-if="paso < PASOS.length" type="button" class="btn-paso" :disabled="!pasoValido || guardando" @click="siguiente">
              {{ guardando ? 'Guardando…' : 'Siguiente' }}
            </button>
            <button v-else type="button" class="btn-paso" :disabled="!pasoValido || guardando" @click="enviar">
              {{ guardando ? 'Enviando…' : 'Enviar solicitud' }}
            </button>
          </div>
        </section>

        <p v-if="datos.expira_at" class="mt-4 text-center text-xs text-gray-500">
          El enlace vale hasta el {{ fecha(datos.expira_at.slice(0, 10)) }}.
        </p>
      </template>
    </main>
  </div>
</template>
