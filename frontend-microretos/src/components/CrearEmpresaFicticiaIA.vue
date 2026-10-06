<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useAuthStore } from '../stores/auth'
import {
  proponerEmpresaFicticiaIA, guardarEmpresaFicticiaIA, descartarEmpresaFicticiaIA,
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

const familiaId    = ref(props.familiaInicialId || '')
const centroElegido = ref(props.centroFijo || '')
const paraCatalogo = ref(false)
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
  if (paraCatalogo.value) return 'el catálogo DuaLab'
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
    if (actual.paraCatalogo) aviso.value = `«${data.empresa.nombre_comercial}» añadida al catálogo DuaLab.`
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
          <span><strong>Añadir al catálogo DuaLab</strong>: compartida con todos los centros y de solo lectura (la usarán a través de una copia).</span>
        </label>
        <!-- Sin centro fijo (p. ej. «Base de datos»): el superadmin elige el centro de destino -->
        <select v-if="!paraCatalogo && !centroFijo" v-model="centroElegido" :disabled="generando"
          class="input-style !py-2 text-sm mb-2 w-full" aria-label="Centro educativo de la empresa ficticia">
          <option value="">Elige el centro educativo…</option>
          <option v-for="c in centros" :key="c" :value="c">{{ c }}</option>
        </select>
      </template>

      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
        <select v-model="familiaId" :disabled="generando" class="input-style !py-2 text-sm sm:flex-1" aria-label="Familia profesional de la empresa ficticia">
          <option value="">Elige la familia profesional…</option>
          <option v-for="f in familias" :key="f.id ?? f" :value="f.id">{{ f.nombre ?? f }}</option>
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
