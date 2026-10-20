<script setup>
/**
 * ImportarCurriculoModal.vue
 * Carga los módulos, RA y CE de un ciclo desde un CSV (superadmin, «Base de datos» →
 * gestión de familias y ciclos). Primero se comprueba el archivo (sin escribir nada) y,
 * si está bien, se importa. Sin módulos con RA y CE una familia no sirve para generar retos.
 */
import { ref, computed, watch } from 'vue'
import { importarCurriculo } from '../services/datosFPService.js'

const props = defineProps({
  ciclo: { type: Object, default: null },   // { id, nombre }
})
const emit = defineEmits(['cerrar', 'importado'])

const archivo    = ref(null)
const inputRef   = ref(null)
const resumen    = ref(null)   // respuesta de la comprobación
const errores    = ref([])
const error      = ref('')
const cargando   = ref(false)
const importado  = ref(null)

watch(() => props.ciclo, () => reiniciar())

function reiniciar() {
  archivo.value = null; resumen.value = null; errores.value = []; error.value = ''; importado.value = null
  if (inputRef.value) inputRef.value.value = ''
}

const columnas = ['modulo_codigo', 'modulo_nombre', 'curso', 'horas', 'ra', 'ce']

// Plantilla con «;» y BOM: Excel en español la abre bien en columnas y con tildes
function descargarPlantilla() {
  const filas = [
    columnas,
    ['0373', 'Lenguajes de marcas y sistemas de gestión de información', '1', '133', '1. Reconoce las características de lenguajes de marcas analizando e interpretando fragmentos de código.', 'a) Se han identificado las características generales de los lenguajes de marcas.'],
    ['0373', 'Lenguajes de marcas y sistemas de gestión de información', '1', '133', '1. Reconoce las características de lenguajes de marcas analizando e interpretando fragmentos de código.', 'b) Se han reconocido las ventajas que proporcionan en el tratamiento de la información.'],
    ['0373', 'Lenguajes de marcas y sistemas de gestión de información', '1', '133', '2. Utiliza lenguajes de marcas para la transmisión de información a través de la web.', 'a) Se han identificado y clasificado los lenguajes de marcas relacionados con la web.'],
  ]
  const csv = filas.map(f => f.map(c => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\r\n')
  const url = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }))
  const a = document.createElement('a')
  a.href = url
  a.download = 'plantilla_curriculo.csv'
  a.click()
  URL.revokeObjectURL(url)
}

function onArchivo(e) {
  archivo.value = e.target.files?.[0] ?? null
  resumen.value = null; errores.value = []; error.value = ''; importado.value = null
}

async function enviar(simular) {
  if (!archivo.value || !props.ciclo || cargando.value) return
  cargando.value = true
  error.value = ''
  errores.value = []
  try {
    const { data } = await importarCurriculo(props.ciclo.id, archivo.value, simular)
    if (simular) resumen.value = data
    else { importado.value = data; resumen.value = null; emit('importado', data) }
  } catch (e) {
    errores.value = e.response?.data?.errores ?? []
    error.value = e.response?.status === 429
      ? 'Demasiados intentos seguidos. Espera un minuto.'
      : (e.response?.data?.error || e.response?.data?.message || 'No se pudo procesar el archivo.')
    resumen.value = null
  } finally {
    cargando.value = false
  }
}

const hayNuevos = computed(() => (resumen.value?.modulos_nuevos?.length ?? 0) > 0)
</script>

<template>
  <Teleport to="body">
    <div v-if="ciclo" class="fixed inset-0 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         style="z-index: 10020" @click.self="!cargando && emit('cerrar')">
      <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl p-6 sm:p-8" role="dialog" aria-modal="true"
           aria-labelledby="icm-titulo">
        <div class="flex items-start gap-3 mb-4">
          <div class="flex-1 min-w-0">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Importar módulos, RA y CE</p>
            <h2 id="icm-titulo" class="font-black text-lg text-azul-noche break-words">{{ ciclo.nombre }}</h2>
          </div>
          <button type="button" @click="emit('cerrar')" :disabled="cargando" aria-label="Cerrar"
            class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Instrucciones -->
        <div class="rounded-2xl bg-gray-50 border border-gray-100 p-4 text-xs text-gray-600 leading-relaxed space-y-2">
          <p>
            Un archivo CSV con <strong>una fila por criterio de evaluación</strong> y estas columnas:
            <code class="text-[11px] font-bold text-gray-800">{{ columnas.join(' ; ') }}</code>.
          </p>
          <ul class="list-disc pl-4 space-y-0.5">
            <li>Las filas del mismo módulo (mismo código, o mismo nombre si no tiene código) forman un módulo.</li>
            <li>Dentro de un módulo, las filas con el mismo texto de RA forman un RA; el orden del archivo se respeta.</li>
            <li><strong>curso</strong>: 1 o 2. <strong>horas</strong> y <strong>modulo_codigo</strong> son opcionales.</li>
            <li>Solo se añaden módulos nuevos: los que el ciclo ya tiene (mismo código o nombre) se omiten.</li>
          </ul>
          <button type="button" @click="descargarPlantilla"
            class="mt-1 inline-flex items-center gap-1.5 font-bold text-administraciones hover:underline">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Descargar plantilla (se abre en Excel)
          </button>
        </div>

        <!-- Archivo -->
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
          <input ref="inputRef" type="file" accept=".csv,text/csv" @change="onArchivo" :disabled="cargando"
            class="text-xs text-gray-600 file:mr-3 file:px-4 file:py-2 file:rounded-full file:border-0 file:bg-[#1F2937] file:text-white file:text-[11px] file:font-bold min-w-0" />
          <button type="button" @click="enviar(true)" :disabled="!archivo || cargando"
            class="sm:ml-auto px-4 py-2 rounded-full text-[11px] font-bold uppercase tracking-widest border transition-all shrink-0"
            :class="!archivo || cargando ? 'opacity-50 cursor-not-allowed bg-white text-gray-400 border-gray-200' : 'bg-white text-azul-noche border-azul-noche/30 hover:bg-gray-50'">
            {{ cargando && !resumen ? 'Comprobando…' : 'Comprobar archivo' }}
          </button>
        </div>

        <!-- Errores -->
        <div v-if="error" role="alert" class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
          <p class="font-bold">{{ error }}</p>
          <ul v-if="errores.length" class="mt-2 list-disc pl-4 space-y-0.5 max-h-48 overflow-y-auto">
            <li v-for="(e, i) in errores" :key="i">{{ e }}</li>
          </ul>
        </div>

        <!-- Resumen de la comprobación -->
        <div v-if="resumen" class="mt-4 rounded-2xl border border-gray-200 p-4 text-xs text-gray-700">
          <p class="font-black text-sm text-azul-noche">
            {{ hayNuevos
              ? `Se añadirán ${resumen.modulos_nuevos.length} módulos, ${resumen.total_ras} RA y ${resumen.total_ces} CE`
              : 'No hay nada nuevo que importar' }}
          </p>
          <ul v-if="hayNuevos" class="mt-2 space-y-1 max-h-48 overflow-y-auto">
            <li v-for="m in resumen.modulos_nuevos" :key="m.nombre" class="flex gap-2">
              <span class="flex-1 min-w-0 break-words">{{ m.nombre }}</span>
              <span class="shrink-0 text-gray-400">{{ m.ras }} RA · {{ m.ces }} CE</span>
            </li>
          </ul>
          <p v-if="resumen.modulos_omitidos.length" class="mt-2 text-amber-700">
            Se omiten por existir ya en el ciclo: {{ resumen.modulos_omitidos.join(', ') }}.
          </p>
          <button v-if="hayNuevos" type="button" @click="enviar(false)" :disabled="cargando"
            class="mt-4 w-full sm:w-auto px-5 py-2.5 rounded-full text-[11px] font-bold uppercase tracking-widest text-white bg-administraciones hover:opacity-90 transition-all"
            :class="cargando ? 'opacity-50 cursor-not-allowed' : ''">
            {{ cargando ? 'Importando…' : 'Importar' }}
          </button>
        </div>

        <!-- Hecho -->
        <div v-if="importado" class="mt-4 rounded-2xl border border-centros/30 bg-centros/5 p-4 text-xs text-azul-noche">
          <p class="font-black text-sm">
            Importados {{ importado.modulos_nuevos.length }} módulos, {{ importado.total_ras }} RA y {{ importado.total_ces }} CE.
          </p>
          <p class="mt-1">La familia de este ciclo ya se puede usar para generar retos.</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>
