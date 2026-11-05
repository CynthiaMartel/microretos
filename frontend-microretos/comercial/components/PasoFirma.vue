<!-- Paso «Firma»: el empresario lee y acepta el aviso legal y la información de protección de
     datos (obligatorios), decide una a una las cesiones a otras empresas (opcionales, sin marcar)
     y firma. Tras firmar, la entrevista ya no se puede cambiar y DuaLab la coloca en centros. -->
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { getTextosLegales, firmarEmpresaCaptada } from '../../src/services/comercialService.js'
import LienzoFirma from './LienzoFirma.vue'

const props = defineProps({
  empresa: { type: Object, required: true },
})
const emit = defineEmits(['atras', 'firmada'])

const textos    = ref(null)
const cargando  = ref(true)
const error     = ref('')
const errores   = ref({})
const firmando  = ref(false)
const hayFirma  = ref(false)
const lienzo    = ref(null)
const leyendo   = ref(null)

const form = reactive({
  firmanteNombre: props.empresa.persona_contacto ?? '',
  firmanteDni: '',
  firmanteCargo: '',
  firmanteEmail: props.empresa.email_general ?? '',
  aceptaAvisoLegal: false,
  aceptaInfoRgpd: false,
})
const cesiones = reactive({})

const CASILLA_OBLIGATORIA = { aviso_legal: 'aceptaAvisoLegal', info_rgpd: 'aceptaInfoRgpd' }

onMounted(async () => {
  try {
    textos.value = (await getTextosLegales()).data
    for (const c of textos.value.cesiones) cesiones[c.clave] = false
  } catch {
    error.value = 'No se han podido cargar los textos legales. Comprueba la conexión.'
  } finally {
    cargando.value = false
  }
})

const puedeFirmar = computed(() =>
  textos.value && form.aceptaAvisoLegal && form.aceptaInfoRgpd
  && form.firmanteNombre.trim() && form.firmanteDni.trim() && hayFirma.value)

async function firmar() {
  error.value = ''
  errores.value = {}
  firmando.value = true
  try {
    const versiones = Object.fromEntries([...textos.value.textos, ...textos.value.cesiones].map(t => [t.clave, t.version]))
    const { data } = await firmarEmpresaCaptada(props.empresa.id, {
      firmanteNombre: form.firmanteNombre.trim(),
      firmanteDni: form.firmanteDni.trim(),
      firmanteCargo: form.firmanteCargo.trim() || null,
      firmanteEmail: form.firmanteEmail.trim() || null,
      aceptaAvisoLegal: form.aceptaAvisoLegal,
      aceptaInfoRgpd: form.aceptaInfoRgpd,
      cesiones: { ...cesiones },
      versiones,
      firma: lienzo.value.png(),
    })
    emit('firmada', data.data)
  } catch (err) {
    errores.value = err.response?.data?.errors ?? {}
    const primero = Object.values(errores.value)[0]?.[0]
    error.value = primero || err.response?.data?.error || 'No se ha podido registrar la firma.'
  } finally {
    firmando.value = false
  }
}

const err = (campo) => errores.value[campo]?.[0]
</script>

<template>
  <div class="space-y-6">
    <p v-if="textos && !textos.revisados" role="note"
       class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
      Textos legales de ejemplo, pendientes de revisión legal. No los uses todavía con empresas reales.
    </p>

    <div v-if="cargando" class="h-64 animate-pulse rounded-2xl bg-white" />

    <template v-else-if="textos">
      <!-- Textos obligatorios -->
      <section class="tarjeta space-y-3">
        <h2 class="titulo-bloque">Condiciones y protección de datos</h2>
        <div v-for="t in textos.textos" :key="t.clave" class="rounded-xl border border-borde">
          <button type="button" class="flex min-h-12 w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold"
                  :aria-expanded="leyendo === t.clave" @click="leyendo = leyendo === t.clave ? null : t.clave">
            <span class="min-w-0 break-words">{{ t.titulo }}</span>
            <span class="shrink-0 text-xs text-rol-fuerte">{{ leyendo === t.clave ? 'Ocultar' : 'Leer' }}</span>
          </button>
          <p v-if="leyendo === t.clave" class="whitespace-pre-line border-t border-borde px-4 py-3 text-sm text-gray-600">{{ t.texto }}</p>
          <label class="flex min-h-12 cursor-pointer items-start gap-3 border-t border-borde px-4 py-3">
            <input v-model="form[CASILLA_OBLIGATORIA[t.clave]]" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 accent-rol-fuerte" />
            <span class="text-sm">He leído y acepto: <strong>{{ t.titulo }}</strong> *</span>
          </label>
        </div>
      </section>

      <!-- Cesiones: una casilla por empresa, sin marcar -->
      <section v-if="textos.cesiones.length" class="tarjeta space-y-3">
        <div>
          <h2 class="titulo-bloque">Cesión de datos a otras empresas</h2>
          <p class="mt-1 text-sm text-texto-secundario">Son voluntarias: marca solo las que la empresa autoriza.</p>
        </div>
        <label v-for="c in textos.cesiones" :key="c.clave" class="flex cursor-pointer items-start gap-3 rounded-xl border border-borde px-4 py-3">
          <input v-model="cesiones[c.clave]" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 accent-rol-fuerte" />
          <span class="min-w-0 text-sm">
            <strong class="block break-words">{{ c.cesionaria }}</strong>
            <span class="mt-1 block whitespace-pre-line text-gray-600">{{ c.texto }}</span>
          </span>
        </label>
      </section>

      <!-- Firmante -->
      <section class="tarjeta space-y-4">
        <h2 class="titulo-bloque">Quién firma</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label for="pf-nombre" class="label-style">Nombre y apellidos *</label>
            <input id="pf-nombre" v-model="form.firmanteNombre" class="input-style" maxlength="255" autocomplete="name" />
            <p v-if="err('firmanteNombre')" class="error-campo">{{ err('firmanteNombre') }}</p>
          </div>
          <div>
            <label for="pf-dni" class="label-style">DNI / NIE *</label>
            <input id="pf-dni" v-model="form.firmanteDni" class="input-style" maxlength="12" autocapitalize="characters" autocomplete="off" />
            <p v-if="err('firmanteDni')" class="error-campo">{{ err('firmanteDni') }}</p>
          </div>
          <div>
            <label for="pf-cargo" class="label-style">Cargo en la empresa</label>
            <input id="pf-cargo" v-model="form.firmanteCargo" class="input-style" maxlength="255" placeholder="Ej.: Gerente" />
          </div>
          <div>
            <label for="pf-email" class="label-style">Email del firmante</label>
            <input id="pf-email" v-model="form.firmanteEmail" type="email" class="input-style" maxlength="255" inputmode="email" />
            <p v-if="err('firmanteEmail')" class="error-campo">{{ err('firmanteEmail') }}</p>
          </div>
        </div>
        <div>
          <p class="label-style">Firma *</p>
          <LienzoFirma ref="lienzo" @cambio="hayFirma = $event" />
          <p v-if="err('firma')" class="error-campo">{{ err('firma') }}</p>
        </div>
      </section>
    </template>

    <div v-if="error" role="alert" class="aviso-error"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="min-w-0 break-words">{{ error }}</span></div>

    <div class="fila-pasos">
      <button type="button" class="btn-paso-atras" :disabled="firmando" @click="emit('atras')">Volver</button>
      <button type="button" class="btn-paso" :disabled="!puedeFirmar || firmando" @click="firmar">
        {{ firmando ? 'Firmando…' : 'Firmar' }}
      </button>
    </div>
  </div>
</template>

