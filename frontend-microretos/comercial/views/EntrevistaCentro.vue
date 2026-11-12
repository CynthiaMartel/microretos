<!-- Entrevista a un centro educativo: los mismos datos que el formulario de centros de la web
     (dualab.es/contacto), en tres pasos cómodos en tablet: Centro → Contacto → Protección de datos.
     Sin firma: el centro acepta la información de protección de datos y decide las cesiones.
     Sin conexión se guarda en la tablet y se envía después (offline/centrosLocales.js).
     ?local=<clave>: corregir una entrevista que DuaLab no aceptó (se reutiliza su registro). -->
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getFamilias } from '../../src/services/datosFPService.js'
import { getTextosLegalesCentros } from '../../src/services/comercialService.js'
import { PROVINCIAS, MUNICIPIOS } from '../../src/config/municipiosCanarias.js'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import TallerPasos from '../../src/components/TallerPasos.vue'
import SelectorFamilias from '../components/SelectorFamilias.vue'
import { conCache } from '../offline/entrevistasLocales.js'
import { guardarCentro, obtenerCentroLocal } from '../offline/centrosLocales.js'

const route  = useRoute()
const router = useRouter()

const PASOS = [
  { num: 1, label: 'Centro' },
  { num: 2, label: 'Contacto' },
  { num: 3, label: 'Protección de datos' },
]
const ACCIONES = [
  { valor: 'registro', texto: 'Darse de alta en DuaLab' },
  { valor: 'info',     texto: 'Más información' },
  { valor: 'demo',     texto: 'Una demo' },
]
const MAX_FAMILIAS = 4 // como el formulario web

const paso    = ref(1)
const maximo  = ref(1)
const form = reactive({
  centroNombre: '', direccion: '', provincia: 'Santa Cruz de Tenerife', municipio: '', familias: [],
  contactoNombre: '', contactoApellidos: '', contactoCargo: '', contactoEmail: '', contactoTelefono: '',
  accion: 'info', comentarios: '', aceptaInfoRgpd: false,
})
const cesiones = reactive({})

const familias  = ref([])
const textos    = ref(null)
const leyendo   = ref(null)
const cargando  = ref(true)
const error     = ref('')
const errores   = ref({})
const enviando  = ref(false)
const resultado = ref(null) // { enServidor } tras enviar
const claveLocal = ref(route.query.local ? String(route.query.local) : null)

const municipios = computed(() => MUNICIPIOS[form.provincia] ?? [])
// Al cambiar de provincia, el municipio elegido ya no vale
watch(() => form.provincia, () => { if (!municipios.value.includes(form.municipio)) form.municipio = '' })

onMounted(async () => {
  try {
    const [f, t] = await Promise.all([conCache('familias', getFamilias), conCache('textos-legales-centros', getTextosLegalesCentros)])
    familias.value = f
    textos.value = t
    for (const c of t.cesiones) cesiones[c.clave] = false
  } catch {
    error.value = 'No se han podido cargar las familias o los textos legales. Conéctate una vez para guardarlos en la tablet.'
  }
  // Corregir una entrevista guardada en la tablet que DuaLab rechazó: se rellena con lo que
  // había, salvo la aceptación y las cesiones, que el centro vuelve a decidir
  if (claveLocal.value) {
    const r = await obtenerCentroLocal(claveLocal.value).catch(() => null)
    if (r) {
      const { aceptaInfoRgpd, cesiones: _c, versiones: _v, clienteUuid: _u, ...datos } = r.payload
      Object.assign(form, datos)
      if (r.error) error.value = `DuaLab no la aceptó: ${r.error}`
    } else {
      claveLocal.value = null
    }
  }
  cargando.value = false
})

const telefonoValido = computed(() => /^\+?[0-9 ]{9,16}$/.test(form.contactoTelefono.trim()))
const emailValido    = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.contactoEmail.trim()))
const pasoValido = computed(() => ({
  1: form.centroNombre.trim() && form.direccion.trim() && form.provincia && form.municipio && form.familias.length > 0,
  2: form.contactoNombre.trim() && form.contactoApellidos.trim() && form.contactoCargo.trim() && emailValido.value && telefonoValido.value && form.accion,
  3: !!textos.value && form.aceptaInfoRgpd,
}))
const FALTA = {
  1: 'Faltan el nombre, la dirección, el municipio o al menos una familia profesional.',
  2: 'Faltan datos de contacto, o el email o el teléfono no son válidos.',
}

function irA(n) {
  paso.value = n
  maximo.value = Math.max(maximo.value, n)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
function siguiente() {
  error.value = ''
  if (!pasoValido.value[paso.value]) { error.value = FALTA[paso.value]; return }
  irA(paso.value + 1)
}
const puedeIr = (num) => num <= maximo.value

async function enviar() {
  error.value = ''
  errores.value = {}
  enviando.value = true
  try {
    const versiones = Object.fromEntries([...textos.value.textos, ...textos.value.cesiones].map(t => [t.clave, t.version]))
    const limpio = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, typeof v === 'string' ? v.trim() : v]))
    resultado.value = await guardarCentro({
      ...limpio,
      comentarios: limpio.comentarios || null,
      cesiones: { ...cesiones },
      versiones,
    }, claveLocal.value)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (err) {
    errores.value = err.response?.data?.errors ?? {}
    const primero = Object.values(errores.value)[0]?.[0]
    error.value = primero || err.response?.data?.error || err.message || 'No se ha podido enviar la entrevista.'
    // El error está en un campo de un paso anterior: se vuelve a ese paso
    const campos = Object.keys(errores.value)
    if (campos.some(c => ['centroNombre', 'direccion', 'provincia', 'municipio'].includes(c) || c.startsWith('familias'))) paso.value = 1
    else if (campos.some(c => c.startsWith('contacto') || c === 'accion' || c === 'comentarios')) paso.value = 2
  } finally {
    enviando.value = false
  }
}

const err = (campo) => errores.value[campo]?.[0]
// Otra entrevista sin salir de la vista: se vacía todo (la misma ruta no vuelve a montarla)
const VACIO = { ...form }
function nueva() {
  Object.assign(form, VACIO, { familias: [] })
  for (const k of Object.keys(cesiones)) cesiones[k] = false
  errores.value = {}
  error.value = ''
  resultado.value = null
  claveLocal.value = null
  paso.value = 1
  maximo.value = 1
  router.replace({ name: 'centro-nuevo' })
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div class="relative mx-auto max-w-4xl space-y-6 px-4 pb-10 pt-[5.25rem] sm:px-6 lg:px-8">
    <CabeceraSeccion :antetitulo="resultado ? 'Equipo comercial · Entrevista a centro' : `Equipo comercial · Paso ${paso} de ${PASOS.length}`"
                     titulo="Entrevista a" destacado="centro" color="text-centros"
                     :volver="{ name: 'mis-centros' }" volver-label="Mis centros" />

    <!-- Enviada -->
    <section v-if="resultado" class="tarjeta text-center sm:p-8">
      <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full" aria-hidden="true"
            :class="resultado.enServidor ? 'bg-centros/15 text-centros' : 'bg-amber-100 text-amber-800'">
        <svg v-if="resultado.enServidor" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        <svg v-else class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2" /><path d="M12 18h.01" /></svg>
      </span>
      <p class="mt-3 font-heading text-xl font-bold">{{ resultado.enServidor ? 'Entrevista enviada' : 'Guardada en esta tablet' }}</p>
      <p class="mx-auto mt-2 max-w-lg text-sm text-texto-secundario">
        {{ resultado.enServidor
          ? `${form.contactoNombre} recibirá un email de confirmación. DuaLab validará «${form.centroNombre}» y se pondrá en contacto con el centro.`
          : 'No hay conexión con DuaLab: se enviará sola cuando vuelva. Está cifrada y no se borra hasta enviarse.' }}
      </p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <button type="button" class="accion-secundaria" @click="router.push({ name: 'mis-centros' })">Ver mis centros</button>
        <button type="button" class="accion-principal" @click="nueva">Entrevistar a otro centro</button>
      </div>
    </section>

    <template v-else>
      <TallerPasos :pasos="PASOS" :actual="paso" :maximo="maximo" :puede-ir="puedeIr"
                   etiqueta="Pasos de la entrevista a centro" variante="tarjeta" @ir="irA" />

      <div v-if="cargando" class="h-64 animate-pulse rounded-2xl bg-white" />

      <!-- 1 · Centro -->
      <section v-else-if="paso === 1" class="tarjeta space-y-4">
        <div>
          <h2 class="titulo-bloque">El centro educativo</h2>
          <p class="mt-1 text-sm text-texto-secundario">Los mismos datos que pide el formulario de centros de la web. Todos son obligatorios.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="md:col-span-2">
            <label for="ec-nombre" class="label-style">Nombre del centro *</label>
            <input id="ec-nombre" v-model="form.centroNombre" class="input-style" maxlength="160" autocomplete="organization" placeholder="Ej.: CIFP Villa de Agüimes" />
            <p v-if="err('centroNombre')" class="error-campo">{{ err('centroNombre') }}</p>
          </div>
          <div class="md:col-span-2">
            <label for="ec-direccion" class="label-style">Dirección *</label>
            <input id="ec-direccion" v-model="form.direccion" class="input-style" maxlength="200" autocomplete="street-address" />
            <p v-if="err('direccion')" class="error-campo">{{ err('direccion') }}</p>
          </div>
          <div>
            <label for="ec-provincia" class="label-style">Provincia *</label>
            <select id="ec-provincia" v-model="form.provincia" class="input-style">
              <option v-for="p in PROVINCIAS" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div>
            <label for="ec-municipio" class="label-style">Municipio *</label>
            <select id="ec-municipio" v-model="form.municipio" class="input-style">
              <option value="" disabled>Elige un municipio</option>
              <option v-for="m in municipios" :key="m" :value="m">{{ m }}</option>
            </select>
            <p v-if="err('municipio')" class="error-campo">{{ err('municipio') }}</p>
          </div>
        </div>
        <div>
          <p class="label-style">Familias profesionales de interés * <span class="normal-case tracking-normal">(hasta {{ MAX_FAMILIAS }})</span></p>
          <SelectorFamilias v-model="form.familias" :opciones="familias" :con-interes="false" :maximo="MAX_FAMILIAS" :error="err('familias') || ''" />
        </div>
      </section>

      <!-- 2 · Contacto -->
      <section v-else-if="paso === 2" class="tarjeta space-y-4">
        <div>
          <h2 class="titulo-bloque">Persona de contacto</h2>
          <p class="mt-1 text-sm text-texto-secundario">Recibirá un email de confirmación al enviar la entrevista.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label for="ec-cnombre" class="label-style">Nombre *</label>
            <input id="ec-cnombre" v-model="form.contactoNombre" class="input-style" maxlength="120" autocomplete="given-name" />
            <p v-if="err('contactoNombre')" class="error-campo">{{ err('contactoNombre') }}</p>
          </div>
          <div>
            <label for="ec-capellidos" class="label-style">Apellidos *</label>
            <input id="ec-capellidos" v-model="form.contactoApellidos" class="input-style" maxlength="120" autocomplete="family-name" />
            <p v-if="err('contactoApellidos')" class="error-campo">{{ err('contactoApellidos') }}</p>
          </div>
          <div class="md:col-span-2">
            <label for="ec-cargo" class="label-style">Cargo en el centro *</label>
            <input id="ec-cargo" v-model="form.contactoCargo" class="input-style" maxlength="120" placeholder="Ej.: Dirección, Jefatura de Estudios, Coordinación de FP…" />
            <p v-if="err('contactoCargo')" class="error-campo">{{ err('contactoCargo') }}</p>
          </div>
          <div>
            <label for="ec-email" class="label-style">Email *</label>
            <input id="ec-email" v-model="form.contactoEmail" type="email" inputmode="email" class="input-style" maxlength="255" autocomplete="email" />
            <p v-if="err('contactoEmail')" class="error-campo">{{ err('contactoEmail') }}</p>
          </div>
          <div>
            <label for="ec-telefono" class="label-style">Teléfono *</label>
            <input id="ec-telefono" v-model="form.contactoTelefono" type="tel" inputmode="tel" class="input-style" maxlength="20" placeholder="+34 600 000 000" autocomplete="tel" />
            <p v-if="err('contactoTelefono')" class="error-campo">{{ err('contactoTelefono') }}</p>
          </div>
        </div>
        <div>
          <p class="label-style">¿Qué necesita el centro? *</p>
          <div class="flex flex-wrap gap-2" role="group" aria-label="Qué necesita el centro">
            <button v-for="a in ACCIONES" :key="a.valor" type="button" :aria-pressed="form.accion === a.valor"
                    class="chip" :class="form.accion === a.valor && 'chip-activo'" @click="form.accion = a.valor"><svg v-if="form.accion === a.valor" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>{{ a.texto }}</button>
          </div>
        </div>
        <div>
          <label for="ec-comentarios" class="label-style">Comentarios <span class="normal-case tracking-normal">(opcional)</span></label>
          <textarea id="ec-comentarios" v-model="form.comentarios" rows="3" maxlength="2000" class="input-style" placeholder="Lo que cuente el centro: ciclos que imparte, cuándo quiere empezar…" />
        </div>
      </section>

      <!-- 3 · Protección de datos -->
      <template v-else-if="paso === 3 && textos">
        <p v-if="!textos.revisados" role="note" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
          Textos legales de ejemplo, pendientes de revisión legal. No los uses todavía con centros reales.
        </p>
        <section class="tarjeta space-y-3">
          <h2 class="titulo-bloque">Protección de datos</h2>
          <div v-for="t in textos.textos" :key="t.clave" class="rounded-xl border border-borde">
            <button type="button" class="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold"
                    :aria-expanded="leyendo === t.clave" @click="leyendo = leyendo === t.clave ? null : t.clave">
              <span class="min-w-0 break-words">{{ t.titulo }}</span>
              <span class="shrink-0 text-xs text-rol-fuerte">{{ leyendo === t.clave ? 'Ocultar' : 'Leer' }}</span>
            </button>
            <p v-if="leyendo === t.clave" class="whitespace-pre-line border-t border-borde px-4 py-3 text-sm text-gray-600">{{ t.texto }}</p>
            <label class="flex min-h-12 cursor-pointer items-start gap-3 border-t border-borde px-4 py-3">
              <input v-model="form.aceptaInfoRgpd" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 accent-rol-fuerte" />
              <span class="text-sm">He leído y acepto: <strong>{{ t.titulo }}</strong> *</span>
            </label>
          </div>
        </section>
        <section v-if="textos.cesiones.length" class="tarjeta space-y-3">
          <div>
            <h2 class="titulo-bloque">Cesión de datos</h2>
            <p class="mt-1 text-sm text-texto-secundario">Es voluntaria: márcala solo si el centro la autoriza.</p>
          </div>
          <label v-for="c in textos.cesiones" :key="c.clave" class="flex cursor-pointer items-start gap-3 rounded-xl border border-borde px-4 py-3">
            <input v-model="cesiones[c.clave]" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 accent-rol-fuerte" />
            <span class="min-w-0 text-sm">
              <strong class="block break-words">{{ c.cesionaria }}</strong>
              <span class="mt-1 block whitespace-pre-line text-gray-600">{{ c.texto }}</span>
            </span>
          </label>
        </section>
      </template>

      <div v-if="error" role="alert" class="aviso-error"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="min-w-0 break-words">{{ error }}</span></div>

      <div v-if="!cargando" class="fila-pasos">
        <button type="button" class="btn-paso-atras" :disabled="enviando"
                @click="paso === 1 ? router.push({ name: 'mis-centros' }) : irA(paso - 1)">Volver</button>
        <button v-if="paso < 3" type="button" class="btn-paso" @click="siguiente">
          <span class="sm:hidden">Siguiente</span><span class="hidden sm:inline">Siguiente: {{ PASOS[paso].label }}</span>
        </button>
        <button v-else type="button" class="btn-paso" :disabled="!pasoValido[3] || enviando" @click="enviar">
          {{ enviando ? 'Enviando…' : 'Enviar' }}
        </button>
      </div>
    </template>
  </div>
</template>
