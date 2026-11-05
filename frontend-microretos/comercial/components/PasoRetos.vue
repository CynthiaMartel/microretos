<!-- Paso «Retos» (opcional): demostración delante de la empresa. Empieza preguntando si se
     quiere crear un reto para enseñarle cómo se usa o pasar directamente a la firma. La IA genera retos con lo que se acaba
     de contar en la entrevista; se guardan como borradores que DuaLab revisa antes de
     publicarlos. Es opcional: se puede pasar a la firma sin generar ninguno. -->
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { getCiclosDeFamilia } from '../../src/services/datosFPService.js'
import { generarRetosCaptada, getBorradoresCaptada } from '../../src/services/comercialService.js'

const props = defineProps({
  empresa: { type: Object, required: true },
})
const emit = defineEmits(['atras', 'siguiente'])

const CURSOS  = [{ valor: '1', texto: '1º curso' }, { valor: '2', texto: '2º curso' }, { valor: 'ambos_cursos', texto: '1º y 2º' }]
const NIVELES = [{ valor: 'Bajo', texto: 'Básico' }, { valor: 'Medio', texto: 'Medio' }, { valor: 'Alto', texto: 'Superior' }]

const familias = computed(() => (props.empresa.familias ?? []).map(f => f.nombre))
const form = reactive({
  familia: familias.value[0] ?? '',
  ciclo_id: '',
  cursoSeleccionado: '1',
  nivelGrupo: 'Medio',
  cantidad: 2,
})

const ciclos        = ref([])
const retos         = ref([])
const generando     = ref(false)
const error         = ref('')
const abierto       = ref(null)
// null = aún no se ha elegido (crear demostración o ir a la firma); 'demo' = formulario visible
const modo          = ref(null)

async function cargarCiclos() {
  ciclos.value = []
  form.ciclo_id = ''
  if (!form.familia) return
  try {
    // Solo los ciclos con currículo cargado: sin módulos ni RA/CE la IA no puede hacer el reto
    ciclos.value = (await getCiclosDeFamilia(form.familia)).data.filter(c => c.con_datos_retos)
    form.ciclo_id = ciclos.value[0]?.id ?? ''
  } catch {
    error.value = 'No se han podido cargar los ciclos.'
  }
}
watch(() => form.familia, cargarCiclos)

onMounted(async () => {
  cargarCiclos()
  try {
    retos.value = (await getBorradoresCaptada(props.empresa.id)).data.data
    if (retos.value.length) modo.value = 'demo' // ya hizo una demostración: se ve directamente
  } catch { /* se ven al generar */ }
})

async function generar() {
  error.value = ''
  generando.value = true
  try {
    const { data } = await generarRetosCaptada(props.empresa.id, { ...form, cantidad: Number(form.cantidad) })
    retos.value = [...data.data, ...retos.value]
    abierto.value = data.data[0]?.uuid ?? null
  } catch (err) {
    const errores = err.response?.data?.errors
    error.value = err.response?.status === 429
      ? 'Has generado muchos retos seguidos: espera un minuto y vuelve a intentarlo.'
      : (errores && Object.values(errores)[0]?.[0]) || err.response?.data?.error || 'No se han podido generar los retos.'
  } finally {
    generando.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Elección: demostración o directamente a la firma -->
    <section v-if="!modo" class="tarjeta flex flex-col items-center gap-4 py-8 text-center sm:py-10">
      <span class="flex h-14 w-14 items-center justify-center rounded-full bg-violet-100 text-violet-700" aria-hidden="true">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9.8 3.5l1.2 3.6a2 2 0 001.3 1.3l3.6 1.2-3.6 1.2a2 2 0 00-1.3 1.3l-1.2 3.6-1.2-3.6a2 2 0 00-1.3-1.3L3.7 9.6l3.6-1.2a2 2 0 001.3-1.3zM18 14l.6 1.8 1.8.6-1.8.6L18 19l-.6-1.8-1.8-.6 1.8-.6z" /></svg>
      </span>
      <div>
        <h2 class="font-heading text-xl font-bold">¿Le enseñas cómo funciona?</h2>
        <p class="mx-auto mt-2 max-w-lg text-sm text-texto-secundario">
          Crea un reto para demostrarle cómo se usa: la IA lo prepara en unos segundos con lo que te acaba de contar.
          Este paso es opcional; si no hace falta, pasa directamente a la firma.
        </p>
      </div>
      <div class="flex flex-wrap justify-center gap-3">
        <button type="button" class="inline-flex h-11 items-center gap-2 rounded-full bg-violet-600 px-5 text-sm font-bold text-white shadow-md shadow-violet-600/25 transition hover:bg-violet-700"
                @click="modo = 'demo'">Crear reto de demostración</button>
        <button type="button" class="accion-secundaria h-11" @click="emit('siguiente')">Pasar a la firma</button>
      </div>
    </section>

    <template v-else>
    <section class="tarjeta">
      <h2 class="font-heading text-lg font-bold">Genera retos con lo que te han contado</h2>
      <p class="mt-1 text-sm text-texto-secundario">Elige para qué alumnado: la IA propone retos reales a partir de la entrevista.</p>

      <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label for="pr-familia" class="label-style">Familia profesional</label>
          <select id="pr-familia" v-model="form.familia" class="input-style">
            <option v-for="f in familias" :key="f" :value="f">{{ f }}</option>
          </select>
        </div>
        <div>
          <label for="pr-ciclo" class="label-style">Ciclo formativo</label>
          <select id="pr-ciclo" v-model="form.ciclo_id" class="input-style" :disabled="!ciclos.length">
            <option v-if="!ciclos.length" value="">Sin ciclos con currículo en esta familia</option>
            <option v-for="c in ciclos" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </div>
        <div>
          <p class="label-style">Curso</p>
          <div class="flex flex-wrap gap-2" role="group" aria-label="Curso">
            <button v-for="c in CURSOS" :key="c.valor" type="button" :aria-pressed="form.cursoSeleccionado === c.valor"
                    class="chip" :class="form.cursoSeleccionado === c.valor && 'chip-activo'" @click="form.cursoSeleccionado = c.valor"><svg v-if="form.cursoSeleccionado === c.valor" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>{{ c.texto }}</button>
          </div>
        </div>
        <div>
          <p class="label-style">Nivel del grupo</p>
          <div class="flex flex-wrap gap-2" role="group" aria-label="Nivel del grupo">
            <button v-for="n in NIVELES" :key="n.valor" type="button" :aria-pressed="form.nivelGrupo === n.valor"
                    class="chip" :class="form.nivelGrupo === n.valor && 'chip-activo'" @click="form.nivelGrupo = n.valor"><svg v-if="form.nivelGrupo === n.valor" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>{{ n.texto }}</button>
          </div>
        </div>
        <div>
          <p class="label-style">¿Cuántos retos?</p>
          <div class="flex flex-wrap gap-2" role="group" aria-label="Número de retos">
            <button v-for="n in [1, 2, 3]" :key="n" type="button" :aria-pressed="form.cantidad === n"
                    class="chip w-11 justify-center" :class="form.cantidad === n && 'chip-activo'" @click="form.cantidad = n">{{ n }}</button>
          </div>
        </div>
      </div>

      <!-- Acción de IA: violeta con destellos (GUIA_DISENO §2.1) -->
      <button type="button" :disabled="generando || !form.ciclo_id" @click="generar"
              class="mt-6 inline-flex h-12 items-center justify-center gap-2 rounded-full bg-violet-600 px-6 text-base font-bold text-white shadow-md shadow-violet-600/25 transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">
        <svg v-if="generando" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.8 3.5l1.2 3.6a2 2 0 001.3 1.3l3.6 1.2-3.6 1.2a2 2 0 00-1.3 1.3l-1.2 3.6-1.2-3.6a2 2 0 00-1.3-1.3L3.7 9.6l3.6-1.2a2 2 0 001.3-1.3zM18 14l.6 1.8 1.8.6-1.8.6L18 19l-.6-1.8-1.8-.6 1.8-.6z" /></svg>
        {{ generando ? 'Generando retos… (unos segundos)' : 'Generar retos con IA' }}
      </button>
    </section>

    <div v-if="error" role="alert" class="aviso-error"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="min-w-0 break-words">{{ error }}</span></div>

    <section v-if="retos.length" class="space-y-3">
      <h2 class="font-heading text-lg font-bold">Retos propuestos para {{ empresa.nombre_comercial }}</h2>
      <article v-for="r in retos" :key="r.uuid" class="overflow-hidden rounded-2xl border border-borde bg-white shadow-sm">
        <button type="button" class="flex w-full flex-col gap-1 p-4 text-left sm:p-5" :aria-expanded="abierto === r.uuid"
                @click="abierto = abierto === r.uuid ? null : r.uuid">
          <span class="w-fit rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Borrador · lo revisará DuaLab</span>
          <span class="break-words font-heading text-lg font-bold">{{ r.reto.titulo }}</span>
          <span class="break-words text-sm text-texto-secundario">{{ r.reto.subtitulo }}</span>
          <span class="text-xs text-gray-500">{{ r.familia }} · {{ r.reto.ciclo }}</span>
        </button>
        <div v-if="abierto === r.uuid" class="space-y-4 border-t border-gray-100 p-4 text-sm sm:p-5">
          <p v-if="r.reto.pregunta_reto" class="rounded-xl bg-rol/10 p-4 font-heading text-base font-bold text-azul-noche">{{ r.reto.pregunta_reto }}</p>
          <div v-if="r.reto.dificultades?.length">
            <p class="mb-1 font-bold">Qué está fallando</p>
            <ul class="list-disc space-y-1 pl-5 text-gray-600"><li v-for="(d, i) in r.reto.dificultades" :key="i">{{ d }}</li></ul>
          </div>
          <div v-if="r.reto.prototipos?.length">
            <p class="mb-1 font-bold">Qué entregaría el alumnado</p>
            <ul class="list-disc space-y-1 pl-5 text-gray-600"><li v-for="(p, i) in r.reto.prototipos" :key="i">{{ p }}</li></ul>
          </div>
        </div>
      </article>
    </section>
    </template>

    <div class="fila-pasos">
      <button type="button" class="btn-paso-atras" @click="emit('atras')">Volver</button>
      <!-- En la elección inicial ya está «Pasar a la firma»: aquí no se repite -->
      <button v-if="modo" type="button" class="btn-paso" :disabled="generando" @click="emit('siguiente')">
        <span class="sm:hidden">Siguiente</span><span class="hidden sm:inline">Siguiente: firma</span>
      </button>
    </div>
  </div>
</template>

