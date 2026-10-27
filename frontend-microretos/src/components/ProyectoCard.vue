<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { formatearFecha } from '../utils/fechas.js';
import { useAuthStore } from '../stores/auth.js';
import api from '../api.js';
import MiniaturaReto from './MiniaturaReto.vue';
import FamiliaModulos from './FamiliaModulos.vue';
import { useMicroproyectoPdfExport } from '../composables/useMicroproyectoPdfExport.js';

const props = defineProps({
  proyecto: { type: Object, required: true },
  resaltarEditar: { type: Boolean, default: false },
  // Módulo elegido en el filtro de la biblioteca: se muestra y se resalta en la tarjeta
  moduloActivo: { type: String, default: '' },
});
const emit = defineEmits(['eliminar']);

const router = useRouter();
const authStore = useAuthStore();

// Escaparate de la web pública (frontoffice dualab.es) — opt-in manual, solo superadmin
// (MicroproyectoPolicy::publicarEscaparate) y solo sobre proyectos completados.
const cambiandoVisibilidad = ref(false);
async function toggleVisiblePublico() {
  if (cambiandoVisibilidad.value) return;
  cambiandoVisibilidad.value = true;
  try {
    const { data } = await api.patch(`/startup/proyectos/${props.proyecto.uuid}/visible-publico`);
    props.proyecto.visible_publico = data.visible_publico;
  } catch {
    // Sin toast propio en esta card — el estado del botón no cambia si falla.
  } finally {
    cambiandoVisibilidad.value = false;
  }
}

// ── Menú «⋯» (como en las tarjetas de la Biblioteca de retos) ──────────────────
// Se cierra al pulsar fuera o con Escape (el foco vuelve al botón). Con listeners en el
// documento: un overlay fixed no serviría, porque el transform del hover de la tarjeta lo
// encerraría dentro de ella.
const menuAbierto = ref(false);
const raizMenu    = ref(null);
const botonMenu   = ref(null);
function cerrarMenu(devolverFoco = false) {
  menuAbierto.value = false;
  if (devolverFoco) nextTick(() => botonMenu.value?.focus());
}
function alPulsarFuera(e) { if (!raizMenu.value?.contains(e.target)) cerrarMenu(); }
function alPulsarTecla(e) { if (e.key === 'Escape') cerrarMenu(true); }
watch(menuAbierto, (abierto) => {
  const metodo = abierto ? 'addEventListener' : 'removeEventListener';
  document[metodo]('click', alPulsarFuera, true);
  document[metodo]('keydown', alPulsarTecla);
});
onBeforeUnmount(() => {
  document.removeEventListener('click', alPulsarFuera, true);
  document.removeEventListener('keydown', alPulsarTecla);
});

// PDF: el listado no trae la ficha completa, así que se pide (solo lectura) antes de generarlo.
const { descargarPDF } = useMicroproyectoPdfExport();
const generandoPDF = ref(false);
const errorAccion  = ref('');
async function descargarFicha() {
  cerrarMenu();
  if (generandoPDF.value) return;
  generandoPDF.value = true;
  errorAccion.value = '';
  try {
    const { data } = await api.get(`/startup/proyectos/${props.proyecto.uuid}`);
    descargarPDF(data);
  } catch {
    errorAccion.value = 'No se ha podido descargar el PDF. Inténtalo de nuevo.';
  } finally {
    generandoPDF.value = false;
  }
}
const verFicha = () => router.push({ name: 'startup-day-detalle', params: { uuid: props.proyecto.uuid } });

// Curso al que va dirigido: nivel (1º, 2º…) + ciclo, como en los chips de "Nivel" de /proyectos
const cursoDirigido = computed(() =>
  [props.proyecto.curso === 'ambos_cursos' ? '1º y 2º' : (props.proyecto.curso != null && props.proyecto.curso !== '' ? `${props.proyecto.curso}º` : null), props.proyecto.ciclo_nombre]
    .filter(Boolean).join(' · ')
);

// Módulos que trabaja: los elegidos en el asistente o, en proyectos antiguos, los de la
// evaluación oficial — mismo criterio que Microproyecto::nombresModulos() en el backend.

const modulos = computed(() => {
  const p = props.proyecto;
  let nombres = (p.modulos_seleccionados ?? []).map(m => m?.nombre).filter(Boolean);
  if (!nombres.length) nombres = (p.evaluacion_oficial ?? []).map(e => e?.modulo).filter(Boolean);
  return [...new Set(nombres.map(n => String(n).trim()))];
});

const fechaCreacion = computed(() => formatearFecha(props.proyecto.created_at));

function getEtiqueta(p) {
  if (p.estado === 'en_edicion') return 'En edición';
  if (p.estado === 'archivado')  return 'Archivado';
  if (p.estado === 'completado') return 'Completado';
  if (p.estado === 'validado') {
    if (p.empresa_validado && p.docente_validado) return 'Validado · Completo';
    if (p.empresa_validado)  return 'Validado · Empresa';
    if (p.docente_validado)  return 'Validado · Docente';
    return 'Validado';
  }
  if (p.empresa_no_valida_aun)    return 'No validar aún';
  if (p.enviado_a_empresa_mail)   return 'Esperando respuesta';
  return 'Pendiente enviar';
}
// Franja superior con el color del estado (mismos puntos que el filtro Estado de /proyectos):
// distingue de un vistazo lo que está en proceso de lo completado.
const FRANJA_ESTADO = {
  validado: 'bg-emerald-500', propuesta: 'bg-violet-500', en_edicion: 'bg-amber-500',
  archivado: 'bg-gray-300', completado: 'bg-sky-500',
};
// Etiqueta de estado: texto ≥ 4,5:1 sobre su fondo; el color acompaña al texto, no lo sustituye.
function getColor(p) {
  if (p.estado === 'en_edicion') return 'bg-amber-50 border-amber-200 text-amber-800';
  if (p.estado === 'archivado')  return 'bg-gray-100 border-borde text-texto-secundario';
  if (p.estado === 'completado') return 'bg-sky-50 border-sky-200 text-sky-800';
  if (p.estado === 'validado')   return 'bg-empresas/10 border-empresas/30 text-empresas-dark';
  if (p.empresa_no_valida_aun)   return 'bg-red-50 border-red-200 text-red-700';
  if (p.enviado_a_empresa_mail)  return 'bg-centros/10 border-centros/30 text-centros';
  return 'bg-violet-50 border-violet-200 text-violet-800';
}

// Foto del proyecto: es la del reto del que sale. Mientras los retos no tengan imagen se usa
// la portada subida al proyecto y, si no hay, la ilustración de marca (MiniaturaReto).
const foto = computed(() => props.proyecto.reto_imagen_url || props.proyecto.imagen_portada_url || '');
</script>

<template>
  <!-- Tarjeta compacta: miniatura (foto del reto) a la izquierda con estado y título a su lado;
       debajo, una línea de curso/módulo, otra de empresa y fecha, y la fila de acciones. -->
  <div
    class="group relative flex cursor-pointer flex-col rounded-2xl border border-borde bg-white shadow-sm
           transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
    :class="{ 'z-10': menuAbierto }"
    @click="router.push({ name: 'startup-day-detalle', params: { uuid: proyecto.uuid } })"
  >
    <span aria-hidden="true" class="h-1 shrink-0 rounded-t-2xl" :class="FRANJA_ESTADO[proyecto.estado] || 'bg-gray-200'" />

    <div class="flex flex-1 flex-col gap-2 p-3.5">
      <div class="flex min-w-0 gap-3">
        <!-- Marco de la foto (la del reto): mismo tamaño en todas las tarjetas, con o sin imagen -->
        <div class="h-[4.5rem] w-24 shrink-0 overflow-hidden rounded-xl border border-borde bg-gray-50">
          <MiniaturaReto :src="foto" class="!flex h-full w-full !rounded-none" />
        </div>
        <div class="min-w-0 flex-1">
          <div class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1">
            <span :class="['inline-flex min-w-0 items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold', getColor(proyecto)]">
              <svg v-if="proyecto.estado === 'completado'" class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3"/></svg>
              <span class="truncate">{{ getEtiqueta(proyecto) }}</span>
            </span>
            <span v-if="proyecto.paso_actual && proyecto.estado !== 'completado'" class="text-xs text-texto-secundario">Paso {{ proyecto.paso_actual }}/8</span>
            <!-- Completados: quién lo validó, en iconos (en «Validado · …» ya lo dice la etiqueta).
                 Colores del logo — empresa en verde, docente (centro) en azul -->
            <template v-if="proyecto.estado === 'completado'">
              <span v-if="proyecto.empresa_validado" title="Validado por la empresa"
                    class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-empresas/15 text-empresas-dark">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                <span class="sr-only">Validado por la empresa</span>
              </span>
              <span v-if="proyecto.docente_validado" title="Validado por el docente"
                    class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-centros/15 text-centros">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                <span class="sr-only">Validado por el docente</span>
              </span>
            </template>
          </div>
          <h3 class="line-clamp-2 font-heading text-[15px] font-bold leading-snug text-azul-noche transition-colors group-hover:text-rol-fuerte"
              :title="proyecto.titulo">
            {{ proyecto.titulo }}
          </h3>
        </div>
      </div>

      <!-- Curso al que va dirigido y, debajo, familia y módulos diferenciados con «+N» desplegable
           (FamiliaModulos, común a las bibliotecas) -->
      <p v-if="cursoDirigido" class="min-w-0 truncate text-xs font-semibold text-azul-noche" :title="cursoDirigido">{{ cursoDirigido }}</p>
      <FamiliaModulos :familia="proyecto.familia_nombre || ''" :modulos="modulos" :modulo-activo="moduloActivo" />

      <!-- Empresa · fecha de creación -->
      <div class="mt-auto flex min-w-0 items-center gap-3 text-xs text-texto-secundario">
        <span v-if="proyecto.empresa_nombre" class="flex min-w-0 items-center gap-1">
          <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
          </svg>
          <span class="truncate" :title="proyecto.centro_nombre ? `${proyecto.empresa_nombre} · ${proyecto.centro_nombre}` : proyecto.empresa_nombre">{{ proyecto.empresa_nombre }}</span>
        </span>
        <span v-if="fechaCreacion" class="ml-auto shrink-0" title="Fecha de creación">{{ fechaCreacion }}</span>
      </div>

      <!-- Sub-estado de una propuesta sin validar: qué falta hacer -->
      <p v-if="proyecto.estado === 'propuesta' && !proyecto.empresa_validado"
         class="flex items-start gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold leading-snug"
         :class="proyecto.empresa_no_valida_aun ? 'border-red-200 bg-red-50 text-red-700'
               : proyecto.enviado_a_empresa_mail ? 'border-centros/30 bg-centros/10 text-centros'
               : 'border-violet-200 bg-violet-50 text-violet-800'">
        <svg class="mt-px h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                :d="proyecto.empresa_no_valida_aun ? 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                  : proyecto.enviado_a_empresa_mail ? 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'
                  : 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'"/>
        </svg>
        <template v-if="proyecto.empresa_no_valida_aun">La empresa respondió «No validar aún»: revísalo</template>
        <template v-else-if="proyecto.enviado_a_empresa_mail">Enviado a la empresa, sin respuesta todavía</template>
        <template v-else>Pendiente de enviar a la empresa</template>
      </p>
    </div>

    <!-- Acciones: botón principal (Editar; la empresa, Ver proyecto) + menú «⋯» con el resto -->
    <p v-if="errorAccion" class="mx-3.5 mb-2 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700" role="alert">{{ errorAccion }}</p>
    <div class="flex items-center gap-2 border-t border-borde px-3.5 py-2" @click.stop>
      <button v-if="!authStore.isEmpresa" type="button"
        @click="router.push({ name: 'startup-day-editar', params: { uuid: proyecto.uuid } })"
        class="inline-flex h-9 flex-1 items-center justify-center rounded-full border text-sm font-semibold transition-colors"
        :class="resaltarEditar
          ? 'animate-pulse border-amber-300 bg-amber-50 text-amber-800 shadow-[0_0_0_3px_rgba(251,191,36,0.3)]'
          : 'border-rol-fuerte/30 bg-white text-rol-fuerte hover:bg-rol-fuerte/5'"
      >
        Editar
      </button>
      <button v-else type="button" @click="verFicha"
        class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-full border border-rol-fuerte/30 bg-white text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        Ver proyecto
      </button>

      <div ref="raizMenu" class="relative shrink-0">
        <button ref="botonMenu" type="button" @click="menuAbierto = !menuAbierto"
                :aria-expanded="menuAbierto" aria-haspopup="menu" :aria-label="`Más acciones de «${proyecto.titulo}»`"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-borde text-texto-secundario transition-colors hover:bg-gray-50"
                :class="{ 'bg-gray-50': menuAbierto }">
          <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
        </button>
        <div v-if="menuAbierto" role="menu"
             class="absolute bottom-full right-0 z-30 mb-1 w-[min(15rem,calc(100vw-3rem))] rounded-xl border border-borde bg-white py-1 shadow-lg">
          <button type="button" role="menuitem" @click="cerrarMenu(); verFicha()"
                  class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50">Ver ficha del proyecto</button>
          <button type="button" role="menuitem" @click="descargarFicha" :disabled="generandoPDF"
                  class="block w-full px-4 py-2 text-left text-sm text-azul-noche hover:bg-gray-50 disabled:opacity-50">
            {{ generandoPDF ? 'Generando PDF…' : 'Descargar PDF' }}
          </button>
          <template v-if="!authStore.isEmpresa">
            <!-- Escaparate de la web pública (frontoffice dualab.es): solo superadmin (lo comprueba
                 el backend, MicroproyectoPolicy::publicarEscaparate) y solo proyectos completados.
                 Color propio (turquesa de administraciones) para que no se confunda con las acciones
                 del docente; explicación al pasar el ratón o al llegar con el teclado. -->
            <div v-if="authStore.isSuperAdmin && proyecto.estado === 'completado'" class="group/escaparate relative">
              <button type="button" role="menuitem" aria-describedby="ayuda-escaparate"
                      @click="cerrarMenu(); toggleVisiblePublico()" :disabled="cambiandoVisibilidad"
                      class="mx-1 my-0.5 flex w-[calc(100%-0.5rem)] items-center gap-2 rounded-lg bg-administraciones/10 px-3 py-2 text-left text-sm font-semibold text-administraciones-dark hover:bg-administraciones/15 disabled:opacity-50">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/></svg>
                <span class="min-w-0 flex-1">{{ proyecto.visible_publico ? 'Ocultar de la web pública' : 'Publicar en la web pública' }}</span>
                <span class="shrink-0 rounded-full bg-white/70 px-1.5 py-0.5 text-[11px] font-bold">Superadmin</span>
              </button>
              <span id="ayuda-escaparate" role="tooltip"
                    class="pointer-events-none absolute bottom-full right-1 z-40 mb-1 hidden w-60 rounded-xl bg-azul-noche px-3 py-2 text-xs font-normal leading-snug text-white shadow-lg sm:group-hover/escaparate:block sm:group-focus-within/escaparate:block">
                Escaparate de dualab.es: los proyectos publicados aparecen en la web pública, visibles para cualquiera sin iniciar sesión. Solo el superadmin puede publicarlos u ocultarlos.
              </span>
              <!-- En móvil no hay hover: la explicación va como línea corta bajo la opción -->
              <p class="px-4 pb-1 text-[11px] leading-snug text-texto-secundario sm:hidden" aria-hidden="true">
                Visible en dualab.es sin iniciar sesión. Solo el superadmin puede cambiarlo.
              </p>
            </div>
            <button type="button" role="menuitem" @click="cerrarMenu(); emit('eliminar', proyecto)"
                    class="block w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Mover a la papelera</button>
          </template>
        </div>
      </div>
    </div>
    <!-- Aviso de que está en la web pública (lo ven todos los que gestionan; solo el superadmin lo cambia) -->
    <p v-if="!authStore.isEmpresa && proyecto.estado === 'completado' && proyecto.visible_publico"
       class="-mt-1 inline-flex items-center gap-1 px-3.5 pb-2 text-xs font-semibold text-administraciones-dark"
       title="Publicado en el escaparate de la web pública dualab.es">
      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
      Publicado en la web pública
    </p>
  </div>
</template>
