<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api.js';
import { duracionPorFase } from '../config/fasesProyecto.js';

const route = useRoute();
const proyecto  = ref(null);
const cargando  = ref(true);
const error     = ref(false);
const enviado   = ref(false);
const enviando  = ref(false);
const isLoaded  = ref(false);

// La guía puede ocultarse una vez que el usuario la ha leído
const guiaVisible = ref(true);

const respuestas = ref({
  reto_comprensible:   '',
  objetivos_alineados: '',
  equipo_adecuado:     '',
  viabilidad:          '',
});
const comentarios = ref('');
const decision    = ref('');   // 'validar' | 'no_validar_aun' — campo obligatorio

onMounted(async () => {
  setTimeout(() => { isLoaded.value = true; }, 80);
  try {
    const res = await api.get(`/startup/landing/${route.params.token}`);
    proyecto.value = res.data;
  } catch {
    error.value = true;
  } finally {
    cargando.value = false;
  }
});

async function enviarValidacion() {
  if (!decision.value) return; // el select es obligatorio, HTML ya lo valida, doble seguro
  enviando.value = true;
  try {
    const res = await api.post(`/startup/landing/${route.params.token}/validar`, {
      decision:    decision.value,
      respuestas:  respuestas.value,
      comentarios: comentarios.value,
    });
    // Guardamos la decisión para mostrar el mensaje de confirmación correcto
    enviado.value = res.data.decision ?? decision.value;
  } finally {
    enviando.value = false;
  }
}

const preguntas = [
  { key: 'reto_comprensible',   label: '¿El planteamiento del reto es comprensible y realista?' },
  { key: 'objetivos_alineados', label: '¿Los objetivos del proyecto se alinean con las necesidades de la empresa?' },
  { key: 'equipo_adecuado',     label: '¿El perfil del equipo de alumnos os parece adecuado para este reto?' },
  { key: 'viabilidad',          label: '¿Consideráis que el proyecto es viable en el contexto de vuestra empresa?' },
];

const raCeBlocks = computed(() => {
  const texto = proyecto.value?.ra_ce
  if (!texto?.trim()) return []
  return texto.split('\n\n').map(block => {
    const lines = block.split('\n')
    const modulo = lines[0]?.replace(/^\[|\]$/g, '').trim() || ''
    const ra     = lines[1]?.replace(/^RA:\s*/, '').trim() || ''
    const ces    = lines.slice(3).map(l => l.replace(/^\s*•\s*/, '').trim()).filter(Boolean)
    return { modulo, ra, ces }
  }).filter(b => b.modulo)
})

function youtubeId(url) {
  const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([^&?/\s]+)/);
  return m ? m[1] : null;
}
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC] font-sans text-[#1F2937] flex flex-col">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[700px] h-[400px]
                bg-[#99CC33] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <!-- Header -->
    <header class="relative z-10 border-b border-gray-100 bg-white/80 backdrop-blur px-6 py-4 flex items-center justify-between gap-3 shadow-sm">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 2L2 7l10 5 10-5-10-5zm0 10l-10-5m10 5l10-5m-10 5v10"/>
          </svg>
        </div>
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">DuaLab</p>
          <p class="text-xs text-gray-400">Portal de validación empresa</p>
        </div>
      </div>
      <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#00A859]/8 border border-[#00A859]/15">
        <span class="w-1.5 h-1.5 rounded-full bg-[#00A859] animate-pulse"/>
        <span class="text-[9px] font-black uppercase tracking-widest text-[#00A859]">Proyecto activo</span>
      </div>
    </header>

    <div class="relative z-10 flex-1 flex items-start justify-center px-4 py-10">
      <div class="w-full max-w-2xl"
           :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
           style="transition: opacity 0.4s ease, transform 0.4s ease">

        <!-- Cargando -->
        <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
          <svg class="animate-spin w-12 h-12 text-[#00A859] mb-4" viewBox="0 0 24 24">
            <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
          </svg>
          <p class="text-[#00A859] font-black tracking-widest uppercase text-sm animate-pulse">Cargando...</p>
        </div>

        <!-- Error -->
        <div v-else-if="error"
             class="text-center py-24 bg-white rounded-[2rem] border border-dashed border-gray-200 shadow-sm">
          <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-100">
            <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="text-gray-500 text-sm font-medium">El enlace no es válido o ha expirado.</p>
        </div>

        <!-- Confirmación enviada — "Validar propuesta" -->
        <div v-else-if="enviado === 'validar'"
             class="text-center py-16 bg-white rounded-[2rem] border border-gray-100 shadow-sm">
          <div class="w-20 h-20 rounded-full bg-[#00A859]/10 border border-[#00A859]/20 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <h2 class="text-xl font-black text-[#121212] mb-2">¡Propuesta validada!</h2>
          <p class="text-gray-400 text-sm max-w-sm mx-auto">
            Muchas gracias por vuestra confianza. El equipo docente recibirá vuestra validación
            y se pondrá en contacto para los próximos pasos.
          </p>
          <div class="mt-6 flex items-center justify-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-[#00A859]"/>
            <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">DuaLab · Gracias por colaborar</span>
          </div>
        </div>

        <!-- Confirmación enviada — "No validar aún" -->
        <div v-else-if="enviado === 'no_validar_aun'"
             class="text-center py-16 bg-white rounded-[2rem] border border-amber-100 shadow-sm">
          <div class="w-20 h-20 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <h2 class="text-xl font-black text-[#121212] mb-2">Respuesta registrada</h2>
          <p class="text-gray-400 text-sm max-w-sm mx-auto">
            Hemos registrado vuestra respuesta de "no validar aún". El equipo docente la recibirá
            y se pondrá en contacto con vosotros para resolver cualquier duda antes de continuar.
          </p>
          <div class="mt-6 flex items-center justify-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"/>
            <span class="text-[10px] font-black uppercase tracking-widest text-amber-500">DuaLab · Gracias por vuestra respuesta</span>
          </div>
        </div>

        <!-- Contenido -->
        <template v-else-if="proyecto">

          <!-- Badge StartUp Day -->
          <div class="inline-flex items-center gap-2 mb-5 px-3 py-1 rounded-full
                      bg-[#00A859]/10 border border-[#00A859]/20">
            <span class="w-2 h-2 rounded-full bg-[#00A859]" />
            <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">StartUp Day · Proyecto</span>
          </div>

          <!-- ════════════════════════════════════════════════════════
               GUÍA EXPLICATIVA PARA EL EMPRESARIO (5.4)
          ═══════════════════════════════════════════════════════════ -->
          <Transition name="slide-guide">
            <div v-if="guiaVisible"
                 class="bg-white border border-gray-100 rounded-[1.5rem] shadow-sm p-6 mb-5">

              <!-- Encabezado guía -->
              <div class="flex items-start justify-between gap-3 mb-5">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20
                              flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Bienvenido/a</p>
                    <h2 class="text-base font-black text-[#121212]">¿Qué es este portal y qué se espera de vosotros?</h2>
                  </div>
                </div>
                <button @click="guiaVisible = false"
                        class="shrink-0 text-gray-300 hover:text-gray-500 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <!-- Qué es DuaLab -->
              <div class="p-4 rounded-2xl bg-[#00A859]/5 border border-[#00A859]/10 mb-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-[#00A859] mb-2">¿Qué es DuaLab?</p>
                <p class="text-sm text-gray-600 leading-relaxed">
                  <strong class="text-[#121212]">DuaLab</strong> es la plataforma que conecta centros de Formación Profesional
                  con empresas del tejido productivo para que el alumnado trabaje en
                  <strong class="text-[#121212]">retos reales</strong>.
                  Vuestro papel como empresa es fundamental: vuestra experiencia ayuda al equipo
                  a comprobar si su propuesta tiene valor y viabilidad real.
                </p>
              </div>

              <!-- Qué es un proyecto -->
              <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 mb-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">¿Qué es un proyecto?</p>
                <p class="text-sm text-gray-600 leading-relaxed">
                  Un proyecto es una propuesta de trabajo del alumnado en torno a un reto
                  relacionado con vuestra empresa. No es un encargo real ni un compromiso contractual —
                  es un ejercicio de aprendizaje donde los alumnos y alumnas practican cómo
                  <strong class="text-[#121212]">detectar problemas, diseñar soluciones y presentar resultados</strong>
                  a una empresa real.
                </p>
              </div>

              <!-- Pasos -->
              <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">¿Qué tenéis que hacer?</p>
              <div class="space-y-2 mb-5">
                <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                  <div class="w-6 h-6 rounded-full bg-[#00A859] text-white text-[10px] font-black
                              flex items-center justify-center shrink-0 mt-0.5">1</div>
                  <div>
                    <p class="text-xs font-bold text-[#1F2937]">Leed la propuesta del equipo</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">Revisar el título del reto, los objetivos y la composición del equipo que lo ha desarrollado.</p>
                  </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                  <div class="w-6 h-6 rounded-full bg-[#00A859] text-white text-[10px] font-black
                              flex items-center justify-center shrink-0 mt-0.5">2</div>
                  <div>
                    <p class="text-xs font-bold text-[#1F2937]">Explorad los recursos adjuntos</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">El equipo docente puede haber adjuntado vídeos o documentos para daros más contexto sobre el trabajo realizado.</p>
                  </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                  <div class="w-6 h-6 rounded-full bg-[#00A859] text-white text-[10px] font-black
                              flex items-center justify-center shrink-0 mt-0.5">3</div>
                  <div>
                    <p class="text-xs font-bold text-[#1F2937]">Responded las preguntas de valoración</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">Son solo 4 preguntas breves con respuesta Sí / No / Parcialmente. Podéis añadir comentarios adicionales si lo consideráis.</p>
                  </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                  <div class="w-6 h-6 rounded-full bg-[#99CC33] text-white text-[10px] font-black
                              flex items-center justify-center shrink-0 mt-0.5">✓</div>
                  <div>
                    <p class="text-xs font-bold text-[#1F2937]">Enviad la valoración</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">El equipo docente recibirá vuestro feedback y os trasladará los próximos pasos. ¡Gracias por vuestra participación!</p>
                  </div>
                </div>
              </div>

              <!-- Tiempo estimado + cerrar -->
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                  <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span class="text-[10px] text-gray-400">Tiempo estimado: <strong class="text-gray-600">3-5 minutos</strong></span>
                </div>
                <button @click="guiaVisible = false"
                        class="text-xs font-bold text-[#00A859] hover:text-[#009950] transition-colors">
                  Entendido, ir al proyecto →
                </button>
              </div>
            </div>
          </Transition>

          <!-- Botón para mostrar guía si se ocultó -->
          <button v-if="!guiaVisible" @click="guiaVisible = true"
                  class="mb-4 text-[10px] font-bold text-gray-400 hover:text-[#00A859] transition-colors
                         flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Ver guía de instrucciones
          </button>

          <!-- Ya validado -->
          <div v-if="proyecto.empresa_validado"
               class="bg-[#00A859]/5 border border-[#00A859]/20 rounded-2xl p-5 mb-6 flex items-start gap-3">
            <svg class="w-5 h-5 text-[#00A859] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-bold text-[#00A859]">Este proyecto ya ha sido validado. Gracias por vuestra participación.</p>
          </div>

          <!-- Ficha del proyecto -->
          <div class="bg-white border border-gray-100 rounded-[1.5rem] shadow-sm p-6 mb-5 space-y-6">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Proyecto</p>
              <h1 class="text-xl font-black text-[#121212]">{{ proyecto.titulo }}</h1>
            </div>

            <!-- Resumen ejecutivo -->
            <div v-if="proyecto.resumen?.texto">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">Resumen ejecutivo</p>
              <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.resumen.texto }}</p>
            </div>

            <!-- Contexto -->
            <div v-if="proyecto.fundamentacion?.contexto">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">Contexto del proyecto</p>
              <p class="text-sm text-gray-600 leading-relaxed">{{ proyecto.fundamentacion.contexto }}</p>
              <p v-if="proyecto.fundamentacion.justificacion" class="text-sm text-gray-600 leading-relaxed mt-2">{{ proyecto.fundamentacion.justificacion }}</p>
              <div v-if="proyecto.fundamentacion.innovacion"
                   class="mt-3 flex items-start gap-2 p-3 bg-[#99CC33]/8 border border-[#99CC33]/20 rounded-xl">
                <span class="text-[10px] font-black uppercase tracking-widest text-[#99CC33] shrink-0 mt-0.5">Innovación</span>
                <p class="text-xs text-gray-600">{{ proyecto.fundamentacion.innovacion }}</p>
              </div>
            </div>

            <!-- El reto -->
            <div v-if="proyecto.diseno_reto?.descripcion || proyecto.diseno_reto?.pregunta_reto">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">El reto</p>
              <p v-if="proyecto.diseno_reto.pregunta_reto"
                 class="text-sm font-bold text-[#00A859] italic mb-2">
                "{{ proyecto.diseno_reto.pregunta_reto }}"
              </p>
              <p v-if="proyecto.diseno_reto.descripcion"
                 class="text-sm text-gray-600 leading-relaxed">{{ proyecto.diseno_reto.descripcion }}</p>
              <div v-if="proyecto.diseno_reto.entregables" class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1">Entregables</p>
                <p class="text-xs text-gray-500 leading-relaxed">{{ proyecto.diseno_reto.entregables }}</p>
              </div>
            </div>

            <!-- Reto origen (microreto) -->
            <div v-if="proyecto.reto_origen?.que_necesitan?.length || proyecto.reto_origen?.dificultades?.length">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">Necesidades identificadas en la empresa</p>
              <div class="space-y-1.5">
                <div v-for="(item, i) in (proyecto.reto_origen.que_necesitan || [])" :key="'nec-'+i"
                     class="flex items-start gap-2 text-sm text-gray-600">
                  <span class="text-[#00A859] mt-0.5 shrink-0 font-bold">›</span>{{ item }}
                </div>
                <div v-for="(item, i) in (proyecto.reto_origen.dificultades || [])" :key="'dif-'+i"
                     class="flex items-start gap-2 text-sm text-gray-500">
                  <span class="text-amber-400 mt-0.5 shrink-0 font-bold">›</span>{{ item }}
                </div>
              </div>
            </div>

            <!-- Objetivos -->
            <div v-if="proyecto.objetivos?.lista?.length">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">Objetivos</p>
              <ul class="space-y-1.5">
                <li v-for="obj in proyecto.objetivos.lista" :key="obj"
                    class="text-sm text-gray-600 flex items-start gap-2">
                  <span class="text-[#00A859] mt-0.5 shrink-0 font-bold">›</span>{{ obj }}
                </li>
              </ul>
            </div>

            <!-- Fases -->
            <div v-if="proyecto.diseno_microproyecto?.fases?.length">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3">Fases del proyecto</p>
              <ol class="space-y-2.5">
                <li v-for="(f, i) in proyecto.diseno_microproyecto.fases" :key="i"
                    class="flex items-start gap-3">
                  <span class="w-5 h-5 rounded-full bg-[#00A859]/10 text-[#00A859] font-black text-[10px]
                               flex items-center justify-center shrink-0 mt-0.5">{{ i + 1 }}</span>
                  <div>
                    <p class="text-sm font-bold text-[#1F2937]">{{ f.nombre }}
                      <span v-if="duracionPorFase(proyecto.diseno_microproyecto.clases, i)" class="text-gray-400 font-normal text-xs">
                        · {{ duracionPorFase(proyecto.diseno_microproyecto.clases, i) }} clase(s)
                      </span>
                    </p>
                    <p v-if="f.descripcion" class="text-xs text-gray-400 mt-0.5 leading-snug">{{ f.descripcion }}</p>
                  </div>
                </li>
              </ol>
            </div>

            <!-- KPIs -->
            <div v-if="proyecto.kpis?.lista?.length">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">Indicadores de éxito (KPIs)</p>
              <ul class="space-y-1.5">
                <li v-for="kpi in proyecto.kpis.lista" :key="kpi"
                    class="flex items-start gap-2 text-sm text-gray-600">
                  <span class="text-[#99CC33] mt-0.5 shrink-0">✓</span>{{ kpi }}
                </li>
              </ul>
            </div>

            <!-- Docente responsable -->
            <div v-if="proyecto.datos_centro?.docente_nombre">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">Docente responsable</p>
              <div class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-100 rounded-xl">
                <div class="w-8 h-8 rounded-full bg-[#00A859]/10 border border-[#00A859]/20
                            flex items-center justify-center shrink-0 text-[#00A859] font-black text-sm">
                  {{ proyecto.datos_centro.docente_nombre.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <p class="text-sm font-bold text-[#1F2937]">{{ proyecto.datos_centro.docente_nombre }}</p>
                  <a v-if="proyecto.datos_centro.docente_email"
                     :href="`mailto:${proyecto.datos_centro.docente_email}`"
                     class="text-xs text-[#00A859] hover:underline">
                    {{ proyecto.datos_centro.docente_email }}
                  </a>
                </div>
                <div v-if="proyecto.datos_centro.nombre" class="ml-auto text-right">
                  <p class="text-xs text-gray-500">{{ proyecto.datos_centro.nombre }}</p>
                  <p v-if="proyecto.datos_centro.municipio" class="text-[10px] text-gray-400">{{ proyecto.datos_centro.municipio }}</p>
                </div>
              </div>
            </div>

            <!-- Equipo -->
            <div v-if="proyecto.equipo?.alumnos?.length">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">Equipo</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="a in proyecto.equipo.alumnos" :key="a.nombre"
                      class="text-xs bg-gray-50 border border-gray-200 px-3 py-1 rounded-full text-gray-600">
                  {{ a.nombre }}<span v-if="a.rol" class="text-gray-400"> · {{ a.rol }}</span>
                </span>
              </div>
            </div>

            <!-- Módulos FP -->
            <div v-if="proyecto.modulos_seleccionados?.length">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">Módulos profesionales</p>
              <div class="flex flex-wrap gap-1.5">
                <span v-for="m in proyecto.modulos_seleccionados" :key="m.id"
                      class="text-xs bg-[#00A859]/8 border border-[#00A859]/15 text-[#00A859] px-2.5 py-1 rounded-full">
                  {{ m.nombre }}
                </span>
              </div>
            </div>
          </div>

          <!-- RA/CE -->
          <div v-if="raCeBlocks.length || proyecto.ra_ce?.trim()"
               class="bg-white border border-gray-100 rounded-[1.5rem] shadow-sm p-6 mb-5">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-4">
              Resultados de Aprendizaje y Criterios de Evaluación
            </p>
            <!-- Formato estructurado -->
            <div v-if="raCeBlocks.length" class="space-y-4">
              <div v-for="(block, i) in raCeBlocks" :key="i"
                   class="border border-gray-100 rounded-xl p-3.5">
                <p class="text-[10px] font-black uppercase tracking-widest text-[#00A859] mb-1">{{ block.modulo }}</p>
                <p class="text-sm font-semibold text-[#1F2937] mb-2">{{ block.ra }}</p>
                <ul v-if="block.ces.length" class="space-y-1 pl-1">
                  <li v-for="(ce, j) in block.ces" :key="j"
                      class="flex items-start gap-2 text-xs text-gray-500">
                    <span class="text-amber-400 shrink-0 font-bold mt-0.5">•</span>{{ ce }}
                  </li>
                </ul>
              </div>
            </div>
            <!-- Texto libre -->
            <p v-else class="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{{ proyecto.ra_ce }}</p>
          </div>

          <!-- ════ RECURSOS: vídeos y documentos ════ -->
          <div v-if="proyecto.recursos && (proyecto.recursos.videos?.length || proyecto.recursos.documentos?.length)"
               class="bg-white border border-gray-100 rounded-[1.5rem] shadow-sm p-6 mb-5">

            <div class="flex items-center gap-3 mb-5">
              <div class="w-8 h-8 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20
                          flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                </svg>
              </div>
              <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#00A859]">Recursos del proyecto</p>
                <p class="text-xs text-gray-400 mt-0.5">Material adicional preparado por el equipo docente</p>
              </div>
            </div>

            <!-- Vídeos -->
            <div v-if="proyecto.recursos.videos?.length" class="mb-4">
              <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-3">Vídeos</p>
              <div class="space-y-3">
                <div v-for="v in proyecto.recursos.videos" :key="v.url">
                  <!-- Embed YouTube -->
                  <template v-if="youtubeId(v.url)">
                    <p v-if="v.label" class="text-xs font-bold text-gray-600 mb-1.5">{{ v.label }}</p>
                    <div class="rounded-xl overflow-hidden border border-gray-100 aspect-video">
                      <iframe
                        :src="`https://www.youtube.com/embed/${youtubeId(v.url)}`"
                        class="w-full h-full"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                      />
                    </div>
                  </template>
                  <!-- Vídeo directo (Cloudinary) — reproducción nativa -->
                  <template v-else>
                    <p v-if="v.label" class="text-xs font-bold text-gray-600 mb-1.5">{{ v.label }}</p>
                    <video :src="v.url" controls preload="metadata"
                           class="w-full rounded-xl border border-gray-100 bg-black max-h-72" />
                  </template>
                </div>
              </div>
            </div>

            <!-- Documentos -->
            <div v-if="proyecto.recursos.documentos?.length">
              <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-3">Documentos</p>
              <div class="space-y-2">
                <a v-for="d in proyecto.recursos.documentos" :key="d.url"
                   :href="d.url" target="_blank" rel="noopener"
                   class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100
                          hover:border-[#00A859]/30 hover:bg-[#00A859]/5 transition-all">
                  <div class="w-8 h-8 rounded-lg bg-[#00A859]/10 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-gray-700 truncate">{{ d.label || 'Ver documento' }}</p>
                    <p class="text-[9px] text-gray-400 truncate">{{ d.url }}</p>
                  </div>
                  <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                  </svg>
                </a>
              </div>
            </div>
          </div>

          <!-- Formulario validación (solo si no está ya validado por empresa) -->
          <form v-if="!proyecto.empresa_validado" @submit.prevent="enviarValidacion"
                class="bg-white border border-gray-100 rounded-[1.5rem] shadow-sm p-6 space-y-6">

            <div class="pb-4 border-b border-gray-100">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#00A859] mb-1">Valoración empresa</p>
              <p class="text-sm text-gray-400">Por favor, responded las siguientes preguntas sobre el proyecto.</p>
              <!-- Aviso si ya habían respondido "no validar aún" antes -->
              <div v-if="proyecto.empresa_no_valida_aun"
                   class="mt-3 flex items-start gap-2 px-3 py-2.5 rounded-xl bg-amber-50 border border-amber-200">
                <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs text-amber-700 font-medium">
                  Ya habíais indicado "No validar aún". Podéis cambiar vuestra respuesta rellenando el formulario de nuevo.
                </p>
              </div>
            </div>

            <!-- Preguntas de valoración -->
            <div v-for="preg in preguntas" :key="preg.key" class="space-y-2.5">
              <label class="text-sm text-[#1F2937] font-medium block">{{ preg.label }}</label>
              <div class="flex flex-wrap gap-3">
                <label v-for="op in ['Sí', 'No', 'Parcialmente']" :key="op"
                       :class="[
                         'flex items-center gap-2 cursor-pointer px-4 py-2 rounded-full border text-sm font-medium transition-all',
                         respuestas[preg.key] === op
                           ? 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]'
                           : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-[#00A859]/30 hover:text-[#00A859]'
                       ]">
                  <input type="radio" :name="preg.key" :value="op" v-model="respuestas[preg.key]"
                         class="sr-only" required />
                  {{ op }}
                </label>
              </div>
            </div>

            <!-- Comentarios -->
            <div>
              <label class="text-sm text-[#1F2937] font-medium block mb-2">
                Comentarios adicionales <span class="text-gray-400 font-normal">(opcional)</span>
              </label>
              <textarea
                v-model="comentarios" rows="4" maxlength="2000"
                placeholder="Sugerencias, dudas o cualquier observación que queráis compartir con el equipo..."
                class="w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm
                       text-[#1F2937] placeholder-gray-400 shadow-sm
                       focus:outline-none focus:border-[#00A859] transition-colors resize-none"
              />
            </div>

            <!-- ── DECISIÓN (obligatoria) ─────────────────────────────────── -->
            <div class="pt-2 border-t border-gray-100">
              <label class="text-sm text-[#1F2937] font-medium block mb-2">
                Decisión sobre la propuesta
                <span class="text-red-400 ml-1" title="Campo obligatorio">*</span>
              </label>
              <p class="text-xs text-gray-400 mb-3">
                Seleccionad si validáis la propuesta tal como está o si aún necesitáis más tiempo o información.
              </p>
              <div class="relative">
                <select
                  v-model="decision"
                  required
                  class="w-full appearance-none bg-white border rounded-2xl px-4 py-3 text-sm
                         font-medium shadow-sm transition-colors cursor-pointer
                         focus:outline-none pr-10"
                  :class="decision === 'validar'
                    ? 'border-[#00A859]/40 text-[#00A859] bg-[#00A859]/5 focus:border-[#00A859]'
                    : decision === 'no_validar_aun'
                      ? 'border-amber-300 text-amber-700 bg-amber-50 focus:border-amber-400'
                      : 'border-gray-200 text-gray-400 focus:border-[#00A859]'"
                >
                  <option value="" disabled>— Selecciona una opción —</option>
                  <option value="validar">✅ Validar propuesta</option>
                  <option value="no_validar_aun">⏳ No validar propuesta aún</option>
                </select>
                <!-- Icono chevron decorativo -->
                <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </div>
              </div>

              <!-- Ayuda contextual según selección -->
              <Transition name="slide-guide">
                <div v-if="decision === 'validar'"
                     class="mt-2.5 flex items-start gap-2 px-3 py-2.5 rounded-xl bg-[#00A859]/5 border border-[#00A859]/20">
                  <svg class="w-3.5 h-3.5 text-[#00A859] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  </svg>
                  <p class="text-[11px] text-[#00A859] font-medium leading-snug">
                    El proyecto quedará marcado como validado por vuestra empresa. El equipo docente recibirá la notificación.
                  </p>
                </div>
                <div v-else-if="decision === 'no_validar_aun'"
                     class="mt-2.5 flex items-start gap-2 px-3 py-2.5 rounded-xl bg-amber-50 border border-amber-200">
                  <svg class="w-3.5 h-3.5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <p class="text-[11px] text-amber-700 font-medium leading-snug">
                    El equipo docente sabrá que habéis revisado la propuesta pero que aún no podéis validarla.
                    Se pondrán en contacto con vosotros para resolver cualquier duda.
                  </p>
                </div>
              </Transition>
            </div>
            <!-- ── FIN DECISIÓN ───────────────────────────────────────────── -->

            <button type="submit" :disabled="enviando || !decision"
                    class="w-full py-3.5 rounded-full text-xs font-black uppercase tracking-widest
                           text-white shadow-sm transition-all active:scale-[0.99]
                           disabled:opacity-40 disabled:cursor-not-allowed"
                    :class="decision === 'validar'
                      ? 'bg-[#00A859] hover:bg-[#00A859]/90 hover:shadow-[0_0_0_3px_rgba(0,168,89,0.2)]'
                      : decision === 'no_validar_aun'
                        ? 'bg-amber-500 hover:bg-amber-500/90 hover:shadow-[0_0_0_3px_rgba(245,158,11,0.2)]'
                        : 'bg-gray-400 cursor-not-allowed'">
              <span v-if="enviando">Enviando respuesta...</span>
              <span v-else-if="decision === 'validar'">✓ Enviar respuesta — Validar propuesta</span>
              <span v-else-if="decision === 'no_validar_aun'">Enviar respuesta — No validar aún</span>
              <span v-else>Selecciona una decisión para continuar</span>
            </button>

            <p class="text-center text-[10px] text-gray-400">
              Vuestra valoración llegará directamente al equipo docente.
              No hay respuestas correctas o incorrectas — lo que más ayuda es vuestra opinión sincera.
            </p>
          </form>

        </template>
      </div>
    </div>

    <!-- Footer -->
    <footer class="relative z-10 border-t border-gray-100 bg-white/60 px-6 py-4 text-center">
      <p class="text-[10px] text-gray-300">
        Dua<span class="text-[#00A859] font-bold">Lab</span> · Plataforma de retos para FP Dual
      </p>
    </footer>
  </div>
</template>

<style scoped>
.slide-guide-enter-active { transition: all 0.3s ease; overflow: hidden; }
.slide-guide-leave-active  { transition: all 0.25s ease; overflow: hidden; }
.slide-guide-enter-from, .slide-guide-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(-8px);
}
.slide-guide-enter-to, .slide-guide-leave-from {
  max-height: 2000px;
}
</style>
