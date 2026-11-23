<!-- «Generar retos» dentro de la tarjeta de una petición (Central → Solicitudes de retos): la generación
     rápida, sin abrir el generador de la app. Con la empresa elegida para la petición (y su diagnóstico),
     el currículo y lo que pidió el docente, llama al mismo motor del generador (POST /generar-microreto) y
     guarda los retos elegidos en la recámara de la solicitud; publicarlos en una biblioteca se hace en
     «Retos» de la ficha. Superadmin y Admin Central DuaLab. Datos: SolicitudRetoService::datosGeneracion. -->
<script setup>
import { ref } from 'vue'
import { getGeneracion } from '../../src/services/solicitudRetoService.js'
import { generarMicroretos, guardarMicroretosLote } from '../../src/services/microretoService.js'
import { NIVELES } from '../../src/services/solicitudRetoService.js'

const props = defineProps({
  solicitudId: { type: Number, required: true },
  bloque:      { type: Number, required: true },
})
const emit = defineEmits(['guardado'])

const abierto = ref(false)
const cargando = ref(false)
const datos = ref(null)          // datosGeneracion del backend
const editables = ref(null)      // nivelGrupo, enfoqueReto, tareasPracticas, entregableDocente, cantidad
const error = ref('')
const generando = ref(false)
const retos = ref([])            // generados, aún sin guardar
const elegidos = ref([])         // índices de retos a guardar
const guardando = ref(false)
const aviso = ref('')

async function abrir() {
  abierto.value = !abierto.value
  if (!abierto.value || datos.value) return
  await cargar()
}
async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    datos.value = (await getGeneracion(props.solicitudId, props.bloque)).data.data
    editables.value = { ...datos.value.editables }
  } catch (e) {
    error.value = e.response?.data?.error || 'No se han podido cargar los datos de la petición.'
  } finally {
    cargando.value = false
  }
}
// Al guardar empresa en la tarjeta, los datos cambian: se recargan la próxima vez
defineExpose({ recargar: () => { datos.value = null; if (abierto.value) cargar() } })

async function generar() {
  generando.value = true
  error.value = ''
  aviso.value = ''
  try {
    const { data } = await generarMicroretos({ ...datos.value.peticion, ...editables.value })
    retos.value = data.microretos || []
    elegidos.value = retos.value.map((_, i) => i)
    if (!retos.value.length) error.value = 'La IA no ha devuelto retos. Vuelve a intentarlo.'
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'No se ha podido contactar con la IA. Inténtalo de nuevo.'
  } finally {
    generando.value = false
  }
}

async function guardar() {
  if (!elegidos.value.length) return
  guardando.value = true
  error.value = ''
  try {
    // Mismo contexto que el generador añade a cada reto al guardarlo
    const lote = elegidos.value.map(i => ({ ...retos.value[i], ...datos.value.contexto, nivel_grupo: editables.value.nivelGrupo }))
    await guardarMicroretosLote(lote, props.solicitudId)
    aviso.value = `${lote.length} ${lote.length === 1 ? 'reto guardado' : 'retos guardados'} en la recámara. Publícalos desde «Retos» cuando te convenzan.`
    retos.value = []
    elegidos.value = []
    emit('guardado')
  } catch (e) {
    error.value = e.response?.data?.error || 'No se han podido guardar los retos.'
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div class="mt-4 rounded-xl border border-centros/20 bg-centros/5 p-3 sm:p-4">
    <button type="button" class="flex min-h-10 w-full flex-wrap items-center justify-between gap-2 text-left" :aria-expanded="abierto" @click="abrir">
      <span class="text-sm font-bold text-azul-noche">Generar retos aquí</span>
      <span class="text-xs font-semibold text-centros">{{ abierto ? 'Ocultar' : 'Abrir' }}</span>
    </button>

    <div v-if="abierto" class="mt-3 space-y-4">
      <p v-if="cargando" class="text-sm text-texto-secundario" role="status">Cargando…</p>
      <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm font-semibold text-red-700" role="alert">{{ error }}</p>
      <p v-if="aviso" class="rounded-xl bg-empresas/10 px-3 py-2 text-sm font-semibold text-empresas-dark" role="status">{{ aviso }}</p>

      <template v-if="datos">
        <p v-if="datos.faltan.includes('empresa')" class="text-sm text-texto-secundario">
          Elige primero la empresa de esta petición (arriba, «Empresa para esta petición») y guárdala.
        </p>
        <p v-else-if="datos.faltan.includes('diagnostico')" class="text-sm text-texto-secundario">
          «{{ datos.empresa.nombre }}» no tiene diagnóstico (el problema de la empresa). Complétalo en Base de datos o usa el generador completo.
        </p>

        <template v-else>
          <p class="text-xs text-texto-secundario">
            Empresa: <strong class="text-azul-noche">{{ datos.empresa.nombre }}</strong>
            · problema: {{ datos.empresa.friccion_problema }}
          </p>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="min-w-0">
              <label :for="`gen-nivel-${bloque}`" class="field-label">Nivel</label>
              <select :id="`gen-nivel-${bloque}`" v-model="editables.nivelGrupo" class="field-input">
                <option v-for="n in NIVELES" :key="n.valor" :value="n.valor">{{ n.texto }}</option>
              </select>
            </div>
            <div class="min-w-0">
              <label :for="`gen-cantidad-${bloque}`" class="field-label">Retos a generar</label>
              <select :id="`gen-cantidad-${bloque}`" v-model.number="editables.cantidad" class="field-input">
                <option v-for="n in [1, 2, 3, 4, 5]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
          </div>
          <div v-for="campo in [
            { k: 'enfoqueReto', t: '¿Qué tipo de reto quieres trabajar?', max: 1000 },
            { k: 'tareasPracticas', t: '¿Qué tareas reales harán en la empresa durante las prácticas?', max: 1000 },
            { k: 'entregableDocente', t: '¿Qué te gustaría que entregara el alumnado?', max: 800 },
          ]" :key="campo.k">
            <label :for="`gen-${campo.k}-${bloque}`" class="field-label">{{ campo.t }}</label>
            <textarea :id="`gen-${campo.k}-${bloque}`" v-model="editables[campo.k]" :maxlength="campo.max" rows="3" class="field-input"></textarea>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="accion-principal" :disabled="generando || guardando" @click="generar">
              {{ generando ? 'Generando…' : (retos.length ? 'Volver a generar' : 'Generar retos') }}
            </button>
            <span v-if="generando" class="text-xs text-texto-secundario" role="status">La IA tarda hasta un minuto.</span>
          </div>

          <!-- Retos generados: se eligen los que se guardan en la recámara -->
          <div v-if="retos.length" class="space-y-2">
            <p class="text-sm font-bold text-azul-noche">Revisa los retos y elige los que guardar</p>
            <label v-for="(r, i) in retos" :key="i" class="flex cursor-pointer items-start gap-3 rounded-xl border border-borde bg-white p-3">
              <input v-model="elegidos" type="checkbox" :value="i" class="mt-1 h-4 w-4 shrink-0" />
              <span class="min-w-0">
                <span class="block break-words text-sm font-bold text-azul-noche">{{ r.titulo }}</span>
                <span v-if="r.subtitulo" class="block break-words text-xs text-texto-secundario">{{ r.subtitulo }}</span>
                <span v-if="r.pregunta_reto" class="mt-1 block break-words text-sm">{{ r.pregunta_reto }}</span>
                <span v-if="r.evaluacion_oficial?.length" class="mt-1 block text-xs text-texto-secundario">
                  {{ r.evaluacion_oficial.map(e => e.ra?.slice(0, 60)).filter(Boolean).join(' · ') }}
                </span>
              </span>
            </label>
            <button type="button" class="accion-principal" :disabled="guardando || !elegidos.length" @click="guardar">
              {{ guardando ? 'Guardando…' : `Guardar en la recámara (${elegidos.length})` }}
            </button>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>
