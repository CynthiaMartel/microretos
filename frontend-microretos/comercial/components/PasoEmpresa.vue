<!-- Paso «Empresa»: el comercial da de alta la empresa (o corrige lo ya guardado) con sus
     familias profesionales y para qué querría al alumnado de cada una. Se guarda al avanzar. -->
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { getFamilias } from '../../src/services/datosFPService.js'
import { crearEmpresaCaptada, actualizarEmpresaCaptada } from '../../src/services/comercialService.js'
import { SECTORES_EMPRESA } from '../../src/config/sectoresEmpresa.js'
import { TAMANOS_EMPRESA } from '../../src/config/tamanosEmpresa.js'
import SelectorFamilias from './SelectorFamilias.vue'

const props = defineProps({
  empresa: { type: Object, default: null },
})
const emit = defineEmits(['guardada', 'atras'])

const NIVELES = ['Bajo', 'Medio', 'Alto']

const e = props.empresa
const form = reactive({
  nombreComercial: e?.nombre_comercial ?? '', razonSocial: e?.razon_social ?? '', cif: e?.cif ?? '',
  sector: e?.sector ?? '', actividad: e?.actividad ?? '', tamano: e?.tamano ?? '',
  nivelDigitalizacion: e?.nivel_digitalizacion ?? '', web: e?.web ?? '',
  personaContacto: e?.persona_contacto ?? '', telefono: e?.telefono ?? '', emailGeneral: e?.email_general ?? '',
  direccion: e?.direccion ?? '', municipio: e?.municipio ?? '', provincia: e?.provincia ?? '', codigoPostal: e?.codigo_postal ?? '',
})
const familiasSel = ref((e?.familias ?? []).map(f => f.nombre))
const intereses   = ref(Object.fromEntries((e?.familias ?? []).map(f => [f.nombre, f.interes ?? ''])))

const familias  = ref([])
const errores   = ref({})
const error     = ref('')
const guardando = ref(false)

onMounted(async () => {
  try {
    familias.value = (await getFamilias()).data
  } catch {
    error.value = 'No se han podido cargar las familias profesionales. Comprueba la conexión.'
  }
})


const valido = computed(() => form.nombreComercial.trim() && form.sector && familiasSel.value.length > 0)

async function guardar() {
  errores.value = {}
  error.value = ''
  if (!valido.value) {
    error.value = 'Faltan el nombre, el sector o al menos una familia profesional.'
    return
  }
  guardando.value = true
  // Vacío = null: en el alta no se envía y en la edición borra lo que hubiera
  const limpios = Object.entries(form).map(([k, v]) => [k, v.trim() || null])
  const datos = Object.fromEntries(props.empresa ? limpios : limpios.filter(([, v]) => v !== null))
  const payload = {
    ...datos,
    familias: familiasSel.value,
    interesesFamilia: Object.fromEntries(familiasSel.value.map(n => [n, (intereses.value[n] ?? '').trim()])),
  }
  try {
    const { data } = props.empresa
      ? await actualizarEmpresaCaptada(props.empresa.id, payload)
      : await crearEmpresaCaptada(payload)
    emit('guardada', data.data)
  } catch (err) {
    errores.value = err.response?.data?.errors ?? {}
    error.value = err.response?.data?.error || err.response?.data?.message || 'No se ha podido guardar la empresa.'
  } finally {
    guardando.value = false
  }
}

const err = (campo) => errores.value[campo]?.[0]
</script>

<template>
  <div class="space-y-6">
    <section class="tarjeta space-y-4">
      <div>
        <h2 class="titulo-bloque">La empresa</h2>
        <p class="mt-1 text-sm text-texto-secundario">Solo son obligatorios los campos con *. Lo que falte se avisará a DuaLab para completarlo después.</p>
      </div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <label for="ce-nombre" class="label-style">Nombre comercial *</label>
          <input id="ce-nombre" v-model="form.nombreComercial" class="input-style" maxlength="255" autocomplete="organization" />
          <p v-if="err('nombreComercial')" class="error-campo">{{ err('nombreComercial') }}</p>
        </div>
        <div>
          <label for="ce-sector" class="label-style">Sector *</label>
          <select id="ce-sector" v-model="form.sector" class="input-style">
            <option value="" disabled>Elige el sector</option>
            <option v-for="s in SECTORES_EMPRESA" :key="s" :value="s">{{ s }}</option>
          </select>
          <p v-if="err('sector')" class="error-campo">{{ err('sector') }}</p>
        </div>
        <div>
          <label for="ce-tamano" class="label-style">Tamaño <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <select id="ce-tamano" v-model="form.tamano" class="input-style">
            <option value="">Sin indicar</option>
            <option v-for="t in TAMANOS_EMPRESA" :key="t.valor" :value="t.valor">{{ t.texto }}</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label for="ce-actividad" class="label-style">¿A qué se dedica exactamente? <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-actividad" v-model="form.actividad" class="input-style" maxlength="500" placeholder="Ej.: reparación de maquinaria agrícola" />
        </div>
        <div>
          <p class="label-style">Nivel de digitalización <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></p>
          <div class="flex flex-wrap gap-2" role="group" aria-label="Nivel de digitalización">
            <button v-for="n in NIVELES" :key="n" type="button" :aria-pressed="form.nivelDigitalizacion === n"
                    class="chip" :class="form.nivelDigitalizacion === n && 'chip-activo'"
                    @click="form.nivelDigitalizacion = form.nivelDigitalizacion === n ? '' : n"><svg v-if="form.nivelDigitalizacion === n" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>{{ n }}</button>
          </div>
        </div>
        <div>
          <label for="ce-web" class="label-style">Web <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-web" v-model="form.web" class="input-style" maxlength="255" inputmode="url" placeholder="www.ejemplo.es" />
        </div>
        <div>
          <label for="ce-razon" class="label-style">Razón social <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-razon" v-model="form.razonSocial" class="input-style" maxlength="255" />
        </div>
        <div>
          <label for="ce-cif" class="label-style">CIF <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-cif" v-model="form.cif" class="input-style" maxlength="20" autocapitalize="characters" />
        </div>
      </div>
    </section>

    <section class="tarjeta space-y-4">
      <h2 class="titulo-bloque">Contacto y ubicación</h2>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label for="ce-contacto" class="label-style">Persona de contacto <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-contacto" v-model="form.personaContacto" class="input-style" maxlength="255" autocomplete="name" />
        </div>
        <div>
          <label for="ce-telefono" class="label-style">Teléfono <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-telefono" v-model="form.telefono" class="input-style" maxlength="20" inputmode="tel" autocomplete="tel" />
        </div>
        <div class="md:col-span-2">
          <label for="ce-email" class="label-style">Email <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-email" v-model="form.emailGeneral" type="email" class="input-style" maxlength="255" inputmode="email" autocomplete="email" />
          <p v-if="err('emailGeneral')" class="error-campo">{{ err('emailGeneral') }}</p>
        </div>
        <div class="md:col-span-2">
          <label for="ce-direccion" class="label-style">Dirección <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-direccion" v-model="form.direccion" class="input-style" maxlength="255" autocomplete="street-address" />
        </div>
        <div>
          <label for="ce-municipio" class="label-style">Municipio <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
          <input id="ce-municipio" v-model="form.municipio" class="input-style" maxlength="255" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="min-w-0">
            <label for="ce-provincia" class="label-style">Provincia <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
            <input id="ce-provincia" v-model="form.provincia" class="input-style" maxlength="255" />
          </div>
          <div class="min-w-0">
            <label for="ce-cp" class="label-style">C. P. <span class="normal-case tracking-normal font-semibold text-texto-secundario">(opcional)</span></label>
            <input id="ce-cp" v-model="form.codigoPostal" class="input-style" maxlength="10" inputmode="numeric" />
          </div>
        </div>
      </div>
    </section>

    <section class="tarjeta space-y-4">
      <div>
        <h2 class="titulo-bloque">¿Con qué alumnado le gustaría trabajar? *</h2>
        <p class="mt-1 text-sm text-texto-secundario">Busca una o varias familias profesionales y apunta para qué querría a ese alumnado.</p>
      </div>
      <SelectorFamilias v-model="familiasSel" v-model:intereses="intereses" :opciones="familias" :error="err('familias')" />
    </section>

    <div v-if="error" role="alert" class="aviso-error"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="min-w-0 break-words">{{ error }}</span></div>

    <div class="fila-pasos">
      <button type="button" class="btn-paso-atras" @click="emit('atras')">Volver</button>
      <button type="button" class="btn-paso" :disabled="guardando" @click="guardar">
        {{ guardando ? 'Guardando…' : 'Siguiente' }}
      </button>
    </div>
  </div>
</template>

