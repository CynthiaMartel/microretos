<!-- Valoración final de la empresa (P3, Taller de ideas): la empresa, sin cuenta y desde un enlace
     con token que caduca, ve la propuesta y la solución que entregó cada grupo en F3 y valora la
     propuesta entera una sola vez. Sin datos del alumnado (ni alias): solo «Grupo N».
     Preguntas en services/valoracionEmpresaService.js (mismas claves que el FormRequest). -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { getValoracionPublica, enviarValoracion, PREGUNTAS_VALORACION } from '../services/valoracionEmpresaService.js'

const route = useRoute()
const datos    = ref(null)
const cargando = ref(true)
const error    = ref('')      // mensaje para enlace inválido o caducado
const enviando = ref(false)
const errorEnvio = ref('')
const enviada  = ref(false)

const global      = ref(0)
const respuestas  = ref(Object.fromEntries(PREGUNTAS_VALORACION.map(p => [p.key, ''])))
const comentarios = ref('')

const completa = computed(() => global.value >= 1 && PREGUNTAS_VALORACION.every(p => respuestas.value[p.key]))
const respondida = computed(() => enviada.value || datos.value?.valoracion?.respondida)
const entregados = computed(() => (datos.value?.grupos || []).filter(g => g.entregada).length)
// «Sí» o «Quizá» a seguir con un proyecto: se le dice que DuaLab le contactará
const interesProyecto = computed(() => {
  const r = enviada.value ? respuestas.value : (datos.value?.valoracion?.respuestas || {})
  return ['Sí', 'Quizá'].includes(r.interes_plan_practicas)
})

const fecha = (iso) => iso ? new Date(iso + (iso.length === 10 ? 'T00:00:00' : '')).toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' }) : ''

onMounted(async () => {
  try {
    const res = await getValoracionPublica(route.params.token)
    datos.value = res.data
  } catch (e) {
    error.value = e.response?.status === 410
      ? 'Este enlace ha caducado. Pide uno nuevo al docente que os lo envió.'
      : 'No encontramos esta valoración. Revisa que el enlace esté completo.'
  } finally {
    cargando.value = false
  }
})

async function enviar() {
  if (!completa.value || enviando.value) return
  enviando.value = true
  errorEnvio.value = ''
  try {
    await enviarValoracion(route.params.token, {
      valoracion_global: global.value,
      respuestas: respuestas.value,
      comentarios: comentarios.value || null,
    })
    enviada.value = true
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    errorEnvio.value = e.response?.data?.message || 'No se pudo enviar la valoración. Inténtalo de nuevo.'
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-[#F8FAFC] font-sans text-[#1F2937]">
    <header class="border-b border-gray-100 bg-white/90 px-4 py-4 shadow-sm backdrop-blur sm:px-6">
      <div class="mx-auto flex max-w-3xl items-center gap-3">
        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-empresas/20 bg-empresas/10 text-empresas" aria-hidden="true">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 7l10 5 10-5-10-5zm0 10l-10-5m10 5l10-5m-10 5v10"/></svg>
        </span>
        <div class="min-w-0">
          <p class="text-[10px] font-black uppercase tracking-widest text-empresas">DUALAB</p>
          <p class="text-xs text-gray-500">Valoración de la empresa</p>
        </div>
      </div>
    </header>

    <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-6 sm:py-10">
      <p v-if="cargando" class="py-16 text-center text-sm text-texto-secundario" role="status">Cargando el reto…</p>

      <div v-else-if="error" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center" role="alert">
        <p class="font-bold text-amber-900">{{ error }}</p>
      </div>

      <template v-else-if="datos">
        <!-- Gracias / ya respondida -->
        <div v-if="respondida" class="mb-6 rounded-2xl border border-empresas/30 bg-empresas/10 p-5 sm:p-6" role="status">
          <p class="text-lg font-bold text-empresas-dark">¡Gracias por vuestra valoración!</p>
          <p class="mt-1 text-sm text-azul-noche">La hemos hecho llegar al equipo docente. Este enlace ya no admite más respuestas.</p>
          <p v-if="interesProyecto" class="mt-2 text-sm text-azul-noche">Como os interesa seguir con un proyecto, DuaLab se pondrá en contacto con vosotros para estudiar un plan de proyecto con alumnado en prácticas.</p>
        </div>

        <!-- Reto -->
        <section class="rounded-2xl border border-borde bg-white p-5 shadow-sm sm:p-7" aria-labelledby="titulo-propuesta">
          <p class="text-[10px] font-black uppercase tracking-widest text-empresas">Reto trabajado en el aula</p>
          <h1 id="titulo-propuesta" class="mt-1 break-words font-heading text-2xl font-bold text-azul-noche sm:text-3xl">{{ datos.propuesta.titulo }}</h1>
          <p v-if="datos.propuesta.pregunta_reto" class="mt-2 break-words text-base italic text-rol-fuerte">«{{ datos.propuesta.pregunta_reto }}»</p>
          <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
            <div v-if="datos.propuesta.empresa" class="min-w-0"><dt class="text-xs font-bold uppercase tracking-wide text-texto-secundario">Empresa</dt><dd class="truncate font-semibold">{{ datos.propuesta.empresa }}</dd></div>
            <div v-if="datos.propuesta.centro" class="min-w-0"><dt class="text-xs font-bold uppercase tracking-wide text-texto-secundario">Centro</dt><dd class="truncate font-semibold">{{ datos.propuesta.centro }}</dd></div>
            <div v-if="datos.propuesta.ciclo" class="min-w-0"><dt class="text-xs font-bold uppercase tracking-wide text-texto-secundario">Ciclo</dt><dd class="truncate font-semibold">{{ datos.propuesta.ciclo }}</dd></div>
          </dl>
          <p v-if="datos.encuentro.fecha" class="mt-3 text-sm text-texto-secundario">
            Trabajado en clase desde el {{ fecha(datos.encuentro.fecha) }}<template v-if="datos.encuentro.fecha_fin"> hasta el {{ fecha(datos.encuentro.fecha_fin) }}</template>.
          </p>
        </section>

        <!-- Soluciones de los grupos -->
        <section class="mt-6" aria-labelledby="titulo-soluciones">
          <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="titulo-soluciones" class="text-lg font-bold text-azul-noche">Soluciones de los grupos</h2>
            <p class="text-sm text-texto-secundario">{{ entregados }} de {{ datos.grupos.length }} han entregado</p>
          </div>
          <ul class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <li v-for="(g, i) in datos.grupos" :key="i" class="min-w-0 rounded-2xl border border-borde bg-white p-4 shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-bold text-azul-noche">{{ g.nombre }}</p>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                      :class="g.entregada ? 'bg-empresas/10 text-empresas-dark' : 'bg-gray-100 text-texto-secundario'">
                  {{ g.entregada ? 'Solución entregada' : 'Sin entregar' }}
                </span>
              </div>
              <template v-if="g.entrega">
                <p v-if="g.entrega.descripcion" class="mt-2 whitespace-pre-line break-words text-sm text-azul-noche">{{ g.entrega.descripcion }}</p>
                <a v-if="g.entrega.url" :href="g.entrega.url" target="_blank" rel="noopener noreferrer nofollow"
                   class="mt-2 inline-flex min-h-9 max-w-full items-center gap-1.5 break-all text-sm font-semibold text-rol-fuerte underline">
                  Ver entregable
                </a>
              </template>
              <ul v-if="g.prototipos.length" class="mt-2 flex flex-wrap gap-1.5" aria-label="Prototipos">
                <li v-for="(p, j) in g.prototipos" :key="j" class="min-w-0 max-w-full">
                  <a :href="p.url" target="_blank" rel="noopener noreferrer nofollow"
                     class="inline-flex min-h-9 max-w-full items-center rounded-full border border-borde px-3 text-xs font-semibold text-azul-noche hover:bg-gray-50">
                    <span class="truncate">{{ p.label || `Prototipo ${j + 1}` }}</span>
                  </a>
                </li>
              </ul>
            </li>
          </ul>
        </section>

        <!-- Formulario de valoración -->
        <form v-if="!respondida" class="mt-6 rounded-2xl border border-borde bg-white p-5 shadow-sm sm:p-7" @submit.prevent="enviar"
              aria-labelledby="titulo-valorar">
          <h2 id="titulo-valorar" class="text-lg font-bold text-azul-noche">Vuestra valoración del reto</h2>
          <p class="mt-1 text-sm text-texto-secundario">Valorad el reto en su conjunto, a partir de las soluciones de los grupos. Solo se puede enviar una vez.</p>
          <p class="mt-3 flex items-start gap-2.5 rounded-xl border border-empresas/25 bg-empresas/5 px-3 py-2.5 text-sm text-azul-noche">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-empresas-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>A partir de este reto puede nacer un <strong>proyecto en vuestra empresa</strong>: alumnado en prácticas que lo desarrolla con vosotros durante meses. Si os interesa, indicadlo en la última pregunta; DuaLab elige al alumnado más destacado y os contacta.</span>
          </p>

          <fieldset class="mt-5">
            <legend class="text-sm font-semibold text-azul-noche">Valoración global (1 = poco útil, 5 = muy útil)</legend>
            <div class="mt-2 flex flex-wrap gap-2">
              <button v-for="n in 5" :key="n" type="button" @click="global = n" :aria-pressed="global === n"
                      class="inline-flex h-11 w-11 items-center justify-center rounded-full border text-base font-bold transition-colors"
                      :class="global === n ? 'border-empresas-dark bg-empresas-dark text-white' : 'border-borde bg-white text-azul-noche hover:bg-empresas/10'">
                {{ n }}
              </button>
            </div>
          </fieldset>

          <fieldset v-for="p in PREGUNTAS_VALORACION" :key="p.key" class="mt-5">
            <legend class="text-sm font-semibold text-azul-noche">{{ p.label }}</legend>
            <div class="mt-2 flex flex-wrap gap-2">
              <button v-for="o in p.opciones" :key="o" type="button" @click="respuestas[p.key] = o" :aria-pressed="respuestas[p.key] === o"
                      class="inline-flex min-h-9 items-center rounded-full border px-4 text-sm font-semibold transition-colors"
                      :class="respuestas[p.key] === o ? 'border-empresas-dark bg-empresas-dark text-white' : 'border-borde bg-white text-azul-noche hover:bg-empresas/10'">
                {{ o }}
              </button>
            </div>
          </fieldset>

          <label for="val-comentarios" class="mt-5 block text-sm font-semibold text-azul-noche">Comentarios (opcional)</label>
          <textarea id="val-comentarios" v-model="comentarios" rows="4" maxlength="3000"
                    class="mt-1.5 w-full rounded-xl border border-borde-campo px-3 py-2.5 text-sm"
                    placeholder="Qué os ha parecido, qué destacaríais o qué mejoraríais…" />

          <p v-if="errorEnvio" class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ errorEnvio }}</p>

          <div class="mt-5 flex flex-wrap items-center justify-end gap-3">
            <p v-if="!completa" class="text-xs text-texto-secundario">Marca la valoración global y responde a todas las preguntas.</p>
            <button type="submit" :disabled="!completa || enviando"
                    class="inline-flex h-11 items-center justify-center rounded-full bg-empresas-dark px-6 text-sm font-semibold text-white shadow-sm hover:bg-empresas-dark/90 disabled:cursor-not-allowed disabled:opacity-50">
              {{ enviando ? 'Enviando…' : 'Enviar valoración' }}
            </button>
          </div>
        </form>
      </template>
    </main>
  </div>
</template>
