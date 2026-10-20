<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useAuthStore } from '../stores/auth'
import {
  proponerEmpresaFicticiaIA, guardarEmpresaFicticiaIA, descartarEmpresaFicticiaIA, getFamiliasFicticiaIA,
} from '../services/empresaService.js'

// «Crear empresa ficticia con IA»: único flujo de creación con IA (Generador de Retos y
// «Base de datos»). La IA genera una propuesta completa (datos + diagnóstico P1–P5) que se
// queda en el servidor; el usuario la revisa y la guarda o la descarta. Docente/admin
// crean siempre en su centro (lo fija el backend); superadmin elige centro o catálogo.
const props = defineProps({
  familias:           { type: Array, default: () => [] },  // [{ id, nombre }]
  familiaInicialId:   { type: [Number, String], default: '' },
  centroFijo:         { type: String, default: '' },        // superadmin en el generador: el centro elegido allí
  centros:            { type: Array, default: () => [] },   // superadmin sin centro fijo («Base de datos»): nombres
  empresaSeleccionada:{ type: String, default: '' },        // nombre de la empresa que se sustituiría
  seleccionaAlGuardar:{ type: Boolean, default: true },     // el generador la selecciona; «Base de datos», no
  // 'guardar': se revisa y guarda aquí («Base de datos»). 'borrador': la propuesta se entrega
  // al padre (evento 'borrador') para revisarla en los pasos 1–2 del generador y guardarla al
  // final del paso 2. Las del catálogo DuaLab se guardan siempre aquí.
  modo:               { type: String, default: 'guardar' },
})
const emit = defineEmits(['guardada', 'cerrar', 'borrador'])

const authStore = useAuthStore()
const esSuperAdmin = computed(() => authStore.isSuperAdmin)

const familiaId    = ref('')
const centroElegido = ref(props.centroFijo || '')
const paraCatalogo = ref(false)

// ── Familias elegibles ─────────────────────────────────────────
// Solo las que el centro de destino tiene asociadas y con módulos, RA y CE: con otra familia
// la empresa no serviría en el Generador de Retos (la IA necesita esos datos). La lista la da
// el backend según el destino; mientras no llega, las que tienen datos en general. El backend
// vuelve a comprobarlo al generar.
const destinoFamilias = ref(null)   // { familias, centro, limitado_por_centro, sin_datos_en_centro }
const cargandoFamilias = ref(false)
let peticionFamilias = 0

const necesitaCentro = computed(() => esSuperAdmin.value && !paraCatalogo.value && !centroElegido.value)

async function cargarFamiliasDestino() {
  const id = ++peticionFamilias
  if (necesitaCentro.value) { destinoFamilias.value = null; return }
  cargandoFamilias.value = true
  try {
    const { data } = await getFamiliasFicticiaIA(esSuperAdmin.value
      ? { centro: paraCatalogo.value ? null : centroElegido.value, catalogo: paraCatalogo.value }
      : {})
    if (id === peticionFamilias) destinoFamilias.value = data
  } catch {
    if (id === peticionFamilias) destinoFamilias.value = null
  } finally {
    if (id === peticionFamilias) cargandoFamilias.value = false
  }
}

const familiasUsables = computed(() => destinoFamilias.value?.familias
  ?? (props.familias.some(f => 'con_datos_retos' in f) ? props.familias.filter(f => f.con_datos_retos) : props.familias))

// La familia elegida (o la que llega preseleccionada) solo vale si está entre las elegibles
watch(familiasUsables, (lista) => {
  const deseada = familiaId.value || props.familiaInicialId
  familiaId.value = lista.some(f => String(f.id) === String(deseada)) ? deseada : ''
}, { immediate: true })

// ── Aviso flotante sobre el selector de familia ─────────────────
const avisoFamiliasVisible = ref(true)
watch([paraCatalogo, centroElegido], () => { avisoFamiliasVisible.value = true; cargarFamiliasDestino() }, { immediate: true })

const nombreCentroAviso = computed(() => destinoFamilias.value?.centro || centroElegido.value || '')
const sinDatosEnCentro = computed(() => destinoFamilias.value?.sin_datos_en_centro ?? [])
const avisoFamilias = computed(() => {
  const centro = nombreCentroAviso.value
  if (paraCatalogo.value) {
    return {
      titulo: 'Solo familias con currículo cargado',
      texto: 'Aparecen únicamente las familias profesionales que ya tienen módulos, resultados de aprendizaje (RA) y criterios de evaluación (CE) en la base de datos. Son los datos que el Generador de Retos envía a la IA para construir cada reto: una plantilla de otra familia no se podría usar.',
      accion: 'Para habilitar otra familia, carga el currículo de alguno de sus ciclos en «Base de datos» → gestión de familias y ciclos (botón de importar de cada ciclo).',
    }
  }
  if (necesitaCentro.value) {
    return {
      titulo: 'Primero, el centro',
      texto: 'Las familias disponibles dependen del centro educativo: elige el centro para ver solo las que tiene asociadas.',
      accion: '',
    }
  }
  const limitado = destinoFamilias.value?.limitado_por_centro
  const deQuien = esSuperAdmin.value ? `«${centro}»` : (centro ? `tu centro, «${centro}»,` : 'tu centro')
  return {
    titulo: limitado
      ? (esSuperAdmin.value ? 'Solo las familias de este centro' : 'Solo las familias de tu centro')
      : (esSuperAdmin.value ? 'Este centro aún no tiene ciclos asociados' : 'Tu centro aún no tiene ciclos asociados'),
    texto: limitado
      ? `Aparecen únicamente las familias profesionales que ${deQuien} tiene asociadas en la base de datos y que ya cuentan con módulos, resultados de aprendizaje (RA) y criterios de evaluación (CE). El Generador de Retos necesita esos datos para crear un reto, así que con cualquier otra familia la empresa no se podría usar.`
      : `${esSuperAdmin.value ? `«${centro}»` : 'Tu centro'} todavía no tiene ciclos formativos asociados en la base de datos, así que se muestran todas las familias que ya tienen módulos, resultados de aprendizaje (RA) y criterios de evaluación (CE). Es lo mínimo que necesita el Generador de Retos.`,
    accion: esSuperAdmin.value
      ? 'Para ampliar las familias de este centro, asóciale sus ciclos y carga el currículo que falte en «Base de datos» → gestión de familias y ciclos.'
      : '¿Necesitas otra familia? Pídeselo al superadmin de DUALAB: tiene que asociarla a tu centro en la base de datos y cargar sus módulos, RA y CE. En cuanto lo haga, aparecerá aquí.',
  }
})
const propuesta    = ref(null)   // { token, destino, familia, empresa, paraCatalogo }
const generando    = ref(false)
const guardando    = ref(false)
const error        = ref('')
const aviso        = ref('')

watch(() => props.centroFijo, (v) => { centroElegido.value = v || '' })

// Superadmin: hace falta un destino (centro o catálogo). Docente/admin: su centro, siempre.
const destinoValido = computed(() => !esSuperAdmin.value || paraCatalogo.value || !!centroElegido.value)
const destinoTexto = computed(() => {
  if (!esSuperAdmin.value) return 'tu centro'
  if (paraCatalogo.value) return 'el catálogo DUALAB'
  return centroElegido.value || '—'
})

const generar = async () => {
  if (!familiaId.value || !destinoValido.value || generando.value) return
  generando.value = true
  error.value = ''
  aviso.value = ''
  try {
    if (propuesta.value) descartar() // «Generar otra»
    const payload = { familiaId: familiaId.value }
    if (esSuperAdmin.value) {
      if (paraCatalogo.value) payload.catalogo = true
      else payload.centro = centroElegido.value
    }
    const { data } = await proponerEmpresaFicticiaIA(payload)
    const esCatalogo = esSuperAdmin.value && paraCatalogo.value
    if (props.modo === 'borrador' && !esCatalogo) {
      // El padre se queda la propuesta: aquí no se descarta al desmontar.
      emit('borrador', data)
      return
    }
    propuesta.value = { ...data, paraCatalogo: esCatalogo }
  } catch (e) {
    error.value = e.response?.status === 429
      ? 'Has hecho muchas peticiones a la IA seguidas. Espera un minuto y vuelve a intentarlo.'
      : (e.response?.data?.error || e.response?.data?.message || 'No se pudo generar la empresa. Inténtalo de nuevo.')
  } finally {
    generando.value = false
  }
}

// El servidor guarda lo que generó la IA (por el token), nunca datos enviados desde aquí.
const guardar = async () => {
  const actual = propuesta.value
  if (!actual || guardando.value) return
  guardando.value = true
  error.value = ''
  try {
    const { data } = await guardarEmpresaFicticiaIA(actual.token)
    propuesta.value = null
    if (actual.paraCatalogo) aviso.value = `«${data.empresa.nombre_comercial}» añadida al catálogo DUALAB.`
    emit('guardada', data.empresa, { paraCatalogo: actual.paraCatalogo })
  } catch (e) {
    if (e.response?.status === 410) propuesta.value = null
    error.value = e.response?.data?.error || e.response?.data?.message || 'No se pudo guardar la empresa.'
  } finally {
    guardando.value = false
  }
}

// Descarta en el servidor sin esperar (si falla, la propuesta caduca sola).
const descartar = () => {
  const token = propuesta.value?.token
  propuesta.value = null
  if (token) descartarEmpresaFicticiaIA(token).catch(() => {})
}

const cerrar = () => { descartar(); emit('cerrar') }

// Si el padre lo desmonta (Vaciar, cambio de paso...), no dejar propuestas colgando.
onBeforeUnmount(descartar)

const CAMPOS_DIAGNOSTICO = [
  ['¿Qué ofrece y qué hace en su día a día?', 'dia_a_normal'],
  ['¿Qué tarea da más trabajo del que debería?', 'friccion_area'],
  ['¿Por qué? Qué ocurre hoy', 'friccion_problema'],
  ['¿Qué NO quieren bajo ningún concepto?', 'lo_que_no_quieren'],
  ['¿Qué esperan que realice el alumno?', 'expectativas_alumno'],
]
const resumen = computed(() => {
  const e = propuesta.value?.empresa
  if (!e) return ''
  const ubicacion = [e.municipio, e.provincia].filter(Boolean).join(', ')
  return [e.sector, e.tamano, ubicacion, propuesta.value.familia].filter(Boolean).join(' · ')
})
</script>

<template>
  <div class="p-4 sm:p-5 rounded-2xl border-2 border-[#1F2937]/15 bg-[#1F2937]/5">
    <div class="flex items-start justify-between gap-3 mb-1">
      <p class="text-xs font-black uppercase tracking-widest text-[#1F2937]">Empresa ficticia con IA</p>
      <button type="button" @click="cerrar" :disabled="generando || guardando"
        class="text-gray-400 hover:text-gray-600 shrink-0" aria-label="Cerrar">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <!-- 1) Elegir familia (y destino si es superadmin) y generar la propuesta -->
    <template v-if="!propuesta">
      <p class="text-xs text-gray-600 leading-relaxed mb-3">
        La IA inventará una empresa y su diagnóstico completo para la familia que elijas. Tarda unos 20 segundos.
        Antes de guardarla podrás revisarla y decidir si la guardas o la descartas.
      </p>
      <p v-if="empresaSeleccionada && !paraCatalogo" class="text-xs text-gray-600 leading-relaxed mb-3 bg-white/70 border border-gray-200 rounded-xl px-3 py-2">
        Ahora tienes seleccionada <strong>«{{ empresaSeleccionada }}»</strong>. Si guardas la nueva, la sustituirá; si cancelas, la recuperas.
      </p>

      <template v-if="esSuperAdmin">
        <label class="flex items-start gap-2 mb-3 text-xs text-gray-700 cursor-pointer">
          <input type="checkbox" v-model="paraCatalogo" :disabled="generando" class="mt-0.5" />
          <span><strong>Añadir al catálogo DUALAB</strong>: compartida con todos los centros y de solo lectura (la usarán a través de una copia).</span>
        </label>
        <!-- Sin centro fijo (p. ej. «Base de datos»): el superadmin elige el centro de destino -->
        <select v-if="!paraCatalogo && !centroFijo" v-model="centroElegido" :disabled="generando"
          class="input-style !py-2 text-sm mb-2 w-full" aria-label="Centro educativo de la empresa ficticia">
          <option value="">Elige el centro educativo…</option>
          <option v-for="c in centros" :key="c" :value="c">{{ c }}</option>
        </select>
      </template>

      <!-- Aviso flotante que señala el selector: por qué solo aparecen estas familias -->
      <Transition name="cefi-aviso">
        <div v-if="avisoFamiliasVisible" role="note" class="relative mb-3 rounded-2xl border-2 border-amber-300 bg-amber-50 px-4 py-3 pr-9 shadow-lg shadow-amber-200/50 text-xs text-amber-900 leading-relaxed">
          <button type="button" @click="avisoFamiliasVisible = false" aria-label="Cerrar aviso"
            class="absolute top-2 right-2 w-6 h-6 rounded-full flex items-center justify-center text-amber-500 hover:bg-amber-100">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
          <p class="font-black uppercase tracking-wide text-[11px] flex items-center gap-1.5">
            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ avisoFamilias.titulo }}
          </p>
          <p class="mt-1">{{ avisoFamilias.texto }}</p>
          <p v-if="sinDatosEnCentro.length && !paraCatalogo" class="mt-1">
            {{ esSuperAdmin ? 'Este centro' : 'Tu centro' }} también tiene asociada{{ sinDatosEnCentro.length > 1 ? 's' : '' }}
            <strong>{{ sinDatosEnCentro.join(', ') }}</strong>, pero todavía sin módulos ni RA/CE cargados.
          </p>
          <p v-if="avisoFamilias.accion" class="mt-1 font-bold">{{ avisoFamilias.accion }}</p>
          <!-- Flecha hacia el selector de familia -->
          <span aria-hidden="true" class="absolute -bottom-2 left-8 w-3.5 h-3.5 rotate-45 bg-amber-50 border-r-2 border-b-2 border-amber-300"></span>
        </div>
      </Transition>
      <button v-if="!avisoFamiliasVisible" type="button" @click="avisoFamiliasVisible = true"
        class="mb-2 text-[11px] font-bold text-amber-700 hover:underline">¿Por qué solo aparecen estas familias?</button>

      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
        <select v-model="familiaId" :disabled="generando" class="input-style !py-2 text-sm sm:flex-1" aria-label="Familia profesional de la empresa ficticia">
          <option value="">{{ cargandoFamilias ? 'Cargando familias…' : (familiasUsables.length ? 'Elige la familia profesional…' : 'No hay familias disponibles') }}</option>
          <option v-for="f in familiasUsables" :key="f.id ?? f" :value="f.id">{{ f.nombre ?? f }}</option>
        </select>
        <div class="flex gap-2 shrink-0">
          <button type="button" @click="generar" :disabled="!familiaId || !destinoValido || generando"
            class="flex-1 sm:flex-none px-4 py-2 rounded-full font-bold text-[11px] tracking-widest uppercase transition-all border shadow-sm flex items-center justify-center gap-2"
            :class="!familiaId || !destinoValido || generando ? 'opacity-50 cursor-not-allowed bg-white text-gray-400 border-gray-200' : 'bg-[#1F2937] text-white border-[#1F2937] hover:bg-[#374151]'">
            <svg v-if="generando" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            {{ generando ? 'Generando…' : 'Generar propuesta' }}
          </button>
          <button type="button" @click="cerrar" :disabled="generando"
            class="px-4 py-2 rounded-full font-bold text-[11px] tracking-widest uppercase transition-all border bg-white text-gray-500 border-gray-200 hover:bg-gray-50">
            Cancelar
          </button>
        </div>
      </div>
    </template>

    <!-- 2) Revisar la propuesta y confirmar: guardar, generar otra o descartar -->
    <template v-else>
      <p class="text-xs text-gray-600 leading-relaxed mb-3">
        Revisa la propuesta. <strong>Todavía no se ha guardado.</strong>
        Si pulsas «Guardar en la base de datos», se guardará como empresa ficticia de
        <strong>{{ propuesta.destino }}</strong><template v-if="!propuesta.paraCatalogo && seleccionaAlGuardar"> y quedará seleccionada</template>.
        Después podrás retocar su diagnóstico con «Editar empresa» y sus datos con «Modificar datos empresa».
      </p>
      <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3 max-h-80 overflow-y-auto">
        <div>
          <p class="text-sm font-black text-[#1F2937] break-words">{{ propuesta.empresa.nombre_comercial }}</p>
          <p class="text-[11px] text-gray-500 break-words">{{ resumen }}</p>
          <p v-if="propuesta.empresa.actividad" class="text-xs text-gray-600 mt-1 break-words">{{ propuesta.empresa.actividad }}</p>
        </div>
        <template v-for="campo in CAMPOS_DIAGNOSTICO" :key="campo[1]">
          <div v-if="propuesta.empresa[campo[1]]">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ campo[0] }}</p>
            <p class="text-xs text-gray-700 leading-relaxed break-words">{{ propuesta.empresa[campo[1]] }}</p>
          </div>
        </template>
      </div>
      <div class="flex flex-wrap gap-2 mt-3">
        <button type="button" @click="guardar" :disabled="guardando || generando"
          class="px-4 py-2 rounded-full font-bold text-[11px] tracking-widest uppercase transition-all border shadow-sm"
          :class="guardando || generando ? 'opacity-50 cursor-not-allowed bg-white text-gray-400 border-gray-200' : 'bg-[#1F2937] text-white border-[#1F2937] hover:bg-[#374151]'">
          {{ guardando ? 'Guardando…' : 'Guardar en la base de datos' }}
        </button>
        <button type="button" @click="generar" :disabled="guardando || generando"
          class="px-4 py-2 rounded-full font-bold text-[11px] tracking-widest uppercase transition-all border bg-white text-[#1F2937] border-gray-200 hover:bg-gray-50">
          {{ generando ? 'Generando…' : 'Generar otra' }}
        </button>
        <button type="button" @click="descartar" :disabled="guardando || generando"
          class="px-4 py-2 rounded-full font-bold text-[11px] tracking-widest uppercase transition-all border bg-white text-red-500 border-gray-200 hover:bg-red-50">
          Descartar
        </button>
      </div>
    </template>

    <p v-if="aviso" class="mt-2 text-xs font-bold text-centros">{{ aviso }}</p>
    <p v-if="error" role="alert" class="mt-2 text-xs font-bold text-red-500">{{ error }}</p>
  </div>
</template>

<style scoped>
.cefi-aviso-enter-active, .cefi-aviso-leave-active { transition: opacity .2s ease, transform .2s ease; }
.cefi-aviso-enter-from, .cefi-aviso-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
