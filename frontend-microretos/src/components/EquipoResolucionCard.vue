<!-- Tarjeta de UN equipo (progreso por fases, diagnóstico final, reflexiones) — pieza
     compartida entre MisGruposDetalle.vue (gestión docente, con acordeón: un equipo/fase
     abierto a la vez) y EquiposSeguimiento.vue (ficha del proyecto, de solo lectura, con
     `expandido-siempre` para no depender de ningún estado de apertura). Antes cada vista
     tenía su propia copia de este bloque, lo que ya había hecho divergir tamaños y estilos
     entre ambas — vive aquí una sola vez para que "cómo se ve" no pueda desincronizarse.

     Todo lo que es mutación (generar/regenerar diagnóstico, evaluar RA/CE) NO vive aquí:
     se proyecta mediante los slots `acciones-cabecera`, `evaluacion-fase-4` y
     `diagnostico-acciones`, que solo MisGruposDetalle.vue rellena. La ficha del proyecto
     no los usa — sin contenido en el slot, sencillamente no se pinta nada ahí. -->
<script setup>
import { computed } from 'vue'
import { FASES_PROYECTO, progresoPonderado } from '../config/fasesProyecto.js'

const props = defineProps({
  equipo: { type: Object, required: true },
  // true (ficha del proyecto): sin acordeón, todo el contenido siempre visible, cabeceras
  // no interactivas. false (gestión docente): respeta `abierto`/`fasesAbiertas` del padre.
  expandidoSiempre: { type: Boolean, default: false },
  abierto: { type: Boolean, default: false },
  // Set de claves "equipoId-numFase" abiertas, igual que `fasesAbiertas` en MisGruposDetalle.vue.
  // Al abrir el equipo todas sus fases arrancan aquí dentro (el padre las precarga); el
  // usuario puede replegar las que no le interesen sin afectar a las demás.
  fasesAbiertas: { type: Set, default: () => new Set() },
})
const emit = defineEmits(['toggle-equipo', 'toggle-fase'])

const FASES = FASES_PROYECTO

const ROLES = {
  portavoz:      { label: 'Portavoz',      color: 'bg-blue-100 text-blue-700' },
  tiempos:       { label: 'Tiempos',       color: 'bg-amber-100 text-amber-700' },
  documentacion: { label: 'Documentación', color: 'bg-violet-100 text-violet-700' },
  foco:          { label: 'Foco',          color: 'bg-emerald-100 text-emerald-700' },
}

const FASE_COLORS = {
  slate:  { bg: 'bg-slate-100',  text: 'text-slate-600',  dot: 'bg-slate-400' },
  blue:   { bg: 'bg-blue-100',   text: 'text-blue-600',   dot: 'bg-blue-400' },
  amber:  { bg: 'bg-amber-100',  text: 'text-amber-600',  dot: 'bg-amber-400' },
  orange: { bg: 'bg-orange-100', text: 'text-orange-600', dot: 'bg-orange-400' },
  green:  { bg: 'bg-green-100',  text: 'text-green-600',  dot: 'bg-green-400' },
}

const equipoEstaAbierto = computed(() => props.expandidoSiempre || props.abierto)

function faseEstaAbierta(faseNum) {
  return props.expandidoSiempre || props.fasesAbiertas.has(`${props.equipo.id}-${faseNum}`)
}

// La reflexión grupal (una sola, el portavoz) y las individuales (una por alumno/a que
// quiera añadirla, opcionales) se guardan mezcladas en `equipo.reflexiones` — se separan
// aquí para que las individuales tengan su propio bloque con el nombre en grande, en vez
// de perderse dentro de una lista plana donde solo se distinguían por una etiqueta diminuta.
const reflexionGrupal = computed(() => props.equipo.reflexiones.find(r => r.tipo === 'grupal'))
const reflexionesIndividuales = computed(() => props.equipo.reflexiones.filter(r => r.tipo === 'individual'))

function progresoPct() {
  return progresoPonderado(props.equipo.fases)
}

function estadoBadge() {
  const fa = props.equipo.fase_actual
  if (fa === 0 && props.equipo.fases_completas === 0) return { label: 'Sin iniciar', cls: 'bg-gray-100 text-gray-500' }
  if (props.equipo.fases_completas === 5)              return { label: 'Completado', cls: 'bg-emerald-100 text-emerald-700' }
  return { label: `Fase ${fa} · ${FASES[fa]?.label}`, cls: 'bg-blue-100 text-blue-700' }
}

// Idénticos a los que tenía MisGruposDetalle.vue — ver sus comentarios originales:
// 'lista' (una fila por elemento) vs 'texto' (párrafo suelto) vs 'preguntas' (síntesis F1).
function formatDatos(datos) {
  if (!datos) return []
  const entries = []
  for (const [k, v] of Object.entries(datos)) {
    if (k === 'evaluacion_docente') continue
    const entry = formatEntradaFase(v)
    if (entry) entries.push({ clave: k.replace(/_/g, ' '), ...entry })
  }
  return entries
}

function formatEntradaFase(v) {
  if (v === null || v === undefined) return null
  if (typeof v === 'string') {
    const texto = v.trim()
    return texto ? { tipo: 'texto', valor: texto } : null
  }
  if (typeof v === 'boolean') return { tipo: 'texto', valor: v ? 'Sí' : 'No' }
  if (Array.isArray(v)) {
    if (v.length && v.every(item => item && typeof item === 'object' && 'pregunta' in item)) {
      const items = v
        .map(item => ({ pregunta: item.pregunta, respuesta: item.respuesta?.trim() || '' }))
        .filter(item => item.pregunta)
      return items.length ? { tipo: 'preguntas', items } : null
    }
    const items = v.map(formatItemFase).filter(Boolean)
    return items.length ? { tipo: 'lista', items } : null
  }
  if (typeof v === 'object') {
    const items = Object.entries(v)
      .map(([k, val]) => {
        const texto = formatItemFase(val)
        return texto ? `${k.replace(/_/g, ' ')}: ${texto}` : null
      })
      .filter(Boolean)
    return items.length ? { tipo: 'lista', items } : null
  }
  return null
}

function formatItemFase(item) {
  if (item === null || item === undefined || item === '') return ''
  if (typeof item === 'string' || typeof item === 'number') return String(item)
  if (typeof item === 'boolean') return item ? 'Sí' : 'No'
  if (Array.isArray(item)) return item.map(formatItemFase).filter(Boolean).join(', ')
  if (typeof item === 'object') {
    if ('pregunta' in item) return `${item.pregunta}: ${item.respuesta || '—'}`
    if ('nombre' in item)   return item.rol ? `${item.nombre} (${item.rol})` : item.nombre
    return Object.values(item).filter(x => typeof x === 'string' && x).join(' · ')
  }
  return ''
}
</script>

<template>
  <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">

    <!-- Cabecera del equipo -->
    <div @click="!expandidoSiempre && emit('toggle-equipo')"
         @keydown.enter="!expandidoSiempre && emit('toggle-equipo')"
         :role="expandidoSiempre ? undefined : 'button'"
         :tabindex="expandidoSiempre ? undefined : 0"
         :class="['w-full px-5 py-4 flex items-center gap-4 transition-colors text-left',
                  expandidoSiempre ? '' : 'hover:bg-gray-50 cursor-pointer']">

      <!-- Progreso circular -->
      <div class="shrink-0 w-12 h-12 relative">
        <svg class="w-12 h-12 -rotate-90" viewBox="0 0 48 48">
          <circle cx="24" cy="24" r="20" fill="none" stroke="#F3F4F6" stroke-width="4"/>
          <circle cx="24" cy="24" r="20" fill="none" stroke="#00A859" stroke-width="4"
                  :stroke-dasharray="`${progresoPct() * 1.257} 125.7`"
                  stroke-linecap="round"/>
        </svg>
        <span class="absolute inset-0 flex items-center justify-center text-[10px] font-black text-[#00A859]">
          {{ progresoPct() }}%
        </span>
      </div>

      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <p class="font-black text-[#121212]">{{ equipo.nombre }}</p>
          <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black', estadoBadge().cls]">
            {{ estadoBadge().label }}
          </span>
        </div>
        <!-- Miembros -->
        <div class="flex flex-wrap gap-1.5 mt-1.5">
          <span v-for="m in equipo.miembros" :key="m.id"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 text-gray-700 text-sm font-bold">
            {{ m.nombre }}
            <span v-if="m.rol" :class="['px-1.5 py-px rounded-full text-[9px] font-black', ROLES[m.rol]?.color]">
              {{ ROLES[m.rol]?.label }}
            </span>
          </span>
        </div>
      </div>

      <!-- Fases visuales -->
      <div class="shrink-0 hidden sm:flex items-center gap-1.5">
        <div v-for="f in FASES" :key="f.num" :title="f.label" class="flex flex-col items-center gap-0.5">
          <div :class="[
                 'w-7 h-7 rounded-lg flex items-center justify-center text-xs',
                 equipo.fases[f.num]?.validado_docente
                   ? 'bg-emerald-500 text-white'
                   : equipo.fases[f.num]?.completada
                     ? 'bg-[#00A859]/20 text-[#00A859]'
                     : equipo.fase_actual === f.num
                       ? 'bg-blue-100 text-blue-600 ring-1 ring-blue-300'
                       : 'bg-gray-100 text-gray-400'
               ]">
            {{ f.icono }}
          </div>
          <span class="text-[8px] font-black text-gray-400 uppercase tracking-wider">F{{ f.num }}</span>
        </div>
      </div>

      <slot name="acciones-cabecera" :equipo="equipo" />

      <svg v-if="!expandidoSiempre"
           :class="['w-4 h-4 text-gray-400 shrink-0 transition-transform', equipoEstaAbierto ? 'rotate-180' : '']"
           fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </div>

    <!-- Detalle del equipo -->
    <div v-if="equipoEstaAbierto" class="border-t border-gray-100 px-5 py-4 space-y-3">

      <!-- Fases -->
      <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Progreso por fases</p>
      <div class="space-y-2">
        <div v-for="f in FASES" :key="f.num" class="rounded-2xl border border-gray-100 overflow-hidden">

          <div @click="!expandidoSiempre && emit('toggle-fase', f.num)"
               :class="['w-full px-4 py-3 flex items-center gap-3 transition-colors text-left',
                        expandidoSiempre ? '' : 'hover:bg-gray-50 cursor-pointer']">
            <span :class="['w-8 h-8 rounded-xl flex items-center justify-center text-sm shrink-0',
                           FASE_COLORS[f.color].bg, FASE_COLORS[f.color].text]">{{ f.icono }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-[#1F2937]">{{ f.label }}</p>
              <p class="text-xs text-gray-400">{{ f.desc }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span v-if="equipo.fases[f.num]?.validado_docente"
                    class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black">
                Validado
              </span>
              <span v-else-if="equipo.fases[f.num]?.completada"
                    class="px-2 py-0.5 rounded-full bg-[#00A859]/10 text-[#00A859] text-[10px] font-black">
                Completa
              </span>
              <span v-else-if="equipo.fase_actual === f.num"
                    class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 text-[10px] font-black">
                En progreso
              </span>
              <span v-else class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-400 text-[10px] font-semibold">
                Pendiente
              </span>
              <svg v-if="!expandidoSiempre"
                   :class="['w-3.5 h-3.5 text-gray-400 transition-transform', faseEstaAbierta(f.num) ? 'rotate-180' : '']"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </div>
          </div>

          <!-- Contenido de la fase -->
          <div v-if="faseEstaAbierta(f.num)" class="px-4 pb-4 border-t border-gray-50 pt-3">
            <template v-if="equipo.fases[f.num]?.datos">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div v-for="entry in formatDatos(equipo.fases[f.num].datos)" :key="entry.clave"
                     :class="['rounded-xl border border-gray-100 bg-gray-50 p-3',
                              entry.tipo !== 'texto' || entry.valor?.length > 70 ? 'sm:col-span-2' : '']">
                  <p :class="['flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider mb-2', FASE_COLORS[f.color].text]">
                    <span :class="['w-1.5 h-1.5 rounded-full shrink-0', FASE_COLORS[f.color].dot]"></span>
                    {{ entry.clave }}
                  </p>

                  <p v-if="entry.tipo === 'texto'" class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line">{{ entry.valor }}</p>

                  <div v-else-if="entry.tipo === 'preguntas'" class="space-y-3">
                    <div v-for="(item, i) in entry.items" :key="i" class="space-y-1.5">
                      <p :class="['flex items-start gap-2 text-sm font-black leading-snug rounded-xl px-3 py-2',
                                  FASE_COLORS[f.color].bg, FASE_COLORS[f.color].text]">
                        <span class="shrink-0 w-5 h-5 rounded-full bg-white/70 flex items-center justify-center text-[11px] font-black">{{ i + 1 }}</span>
                        {{ item.pregunta }}
                      </p>
                      <p class="text-sm text-[#1F2937] leading-relaxed bg-white
                                border border-gray-100 border-l-4 border-l-[#00A859] rounded-lg px-2.5 py-1.5 ml-3">
                        <span v-if="item.respuesta">{{ item.respuesta }}</span>
                        <span v-else class="text-gray-400 italic">Sin responder</span>
                      </p>
                    </div>
                  </div>

                  <ul v-else class="space-y-1.5">
                    <li v-for="(item, i) in entry.items" :key="i"
                        class="text-sm text-[#1F2937] leading-relaxed border-l-2 border-[#00A859]/30 pl-2.5">
                      {{ item }}
                    </li>
                  </ul>
                </div>
              </div>
            </template>
            <p v-else class="text-xs text-gray-400 italic">Sin contenido registrado en esta fase.</p>
            <div v-if="equipo.fases[f.num]?.nota_docente !== null && equipo.fases[f.num]?.nota_docente !== undefined"
                 class="mt-3 flex items-center gap-2">
              <span class="text-xs font-black text-gray-500">Nota:</span>
              <span class="text-sm font-black text-emerald-700">{{ equipo.fases[f.num].nota_docente }}</span>
            </div>
            <div v-if="equipo.fases[f.num]?.observaciones_docente"
                 class="mt-2 p-3 bg-amber-50 border border-amber-100 rounded-xl">
              <p class="text-[10px] font-black uppercase tracking-wider text-amber-600 mb-1">Observaciones docente</p>
              <p class="text-xs text-amber-800">{{ equipo.fases[f.num].observaciones_docente }}</p>
            </div>

            <slot v-if="f.num === 4" name="evaluacion-fase-4" :equipo="equipo" />
          </div>
        </div>
      </div>

      <!-- Diagnóstico final IA — solo con las 5 fases completas -->
      <div v-if="equipo.fases_completas === 5" class="mt-2 pt-4 border-t border-gray-100 space-y-3">
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Diagnóstico final</p>

        <div v-if="equipo.diagnostico_final" class="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-4 space-y-3">
          <p class="text-sm text-[#1F2937] leading-relaxed">{{ equipo.diagnostico_final.resumen }}</p>

          <div v-if="equipo.diagnostico_final.fortalezas?.length" class="space-y-1">
            <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Fortalezas</p>
            <ul class="space-y-1">
              <li v-for="(f, i) in equipo.diagnostico_final.fortalezas" :key="i"
                  class="text-xs text-[#1F2937] leading-relaxed border-l-2 border-emerald-300 pl-2.5">{{ f }}</li>
            </ul>
          </div>

          <div v-if="equipo.diagnostico_final.areas_mejora?.length" class="space-y-1">
            <p class="text-[10px] font-black uppercase tracking-wider text-amber-600">Áreas de mejora</p>
            <ul class="space-y-1">
              <li v-for="(a, i) in equipo.diagnostico_final.areas_mejora" :key="i"
                  class="text-xs text-[#1F2937] leading-relaxed border-l-2 border-amber-300 pl-2.5">{{ a }}</li>
            </ul>
          </div>

          <div v-if="equipo.diagnostico_final.valoracion_ra_ce" class="space-y-1">
            <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Valoración RA/CE</p>
            <p class="text-xs text-[#1F2937] leading-relaxed">{{ equipo.diagnostico_final.valoracion_ra_ce }}</p>
          </div>

          <p v-if="equipo.diagnostico_final.conclusion" class="text-xs font-semibold text-[#1F2937] italic">
            {{ equipo.diagnostico_final.conclusion }}
          </p>

          <p v-if="equipo.diagnostico_generado_en" class="text-[10px] text-gray-400">
            Generado el {{ new Date(equipo.diagnostico_generado_en).toLocaleString('es-ES') }}
          </p>
        </div>
        <p v-else class="text-xs text-gray-400 italic">Todavía no se ha generado el diagnóstico final de este equipo.</p>

        <slot name="diagnostico-acciones" :equipo="equipo" />
      </div>

      <!-- Reflexión grupal -->
      <div v-if="reflexionGrupal" class="mt-2">
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Reflexión grupal</p>
        <div class="p-3 bg-violet-50 border border-violet-100 rounded-2xl space-y-1">
          <div v-for="(resp, idx) in reflexionGrupal.respuestas" :key="idx">
            <p v-if="resp.respuesta" class="text-xs text-[#1F2937] leading-relaxed">
              <span class="text-gray-400">{{ resp.pregunta }}:</span> {{ resp.respuesta }}
            </p>
          </div>
        </div>
      </div>

      <!-- Reflexiones individuales — su propio bloque, con el nombre del alumno/a en
           grande: antes se mezclaban con la grupal en una lista plana donde solo se
           distinguían por una etiqueta diminuta, y quedaban fácilmente desapercibidas. -->
      <div v-if="reflexionesIndividuales.length" class="mt-2">
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
          Reflexiones individuales ({{ reflexionesIndividuales.length }})
        </p>
        <div class="space-y-2">
          <div v-for="r in reflexionesIndividuales" :key="r.id"
               class="p-3 bg-violet-50 border border-violet-100 rounded-2xl">
            <p class="text-sm font-black text-[#121212] mb-1.5">{{ r.autor_nombre || 'Alumno/a' }}</p>
            <div v-if="r.respuestas" class="space-y-1">
              <div v-for="(resp, idx) in r.respuestas" :key="idx">
                <p v-if="resp.respuesta" class="text-xs text-[#1F2937] leading-relaxed">
                  <span class="text-gray-400">{{ resp.pregunta }}:</span> {{ resp.respuesta }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
