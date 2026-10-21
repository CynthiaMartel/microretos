<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import TallerPasos from '../components/TallerPasos.vue'
import TallerResumen from '../components/TallerResumen.vue'
import TallerArbolRaCe from '../components/TallerArbolRaCe.vue'
import ListaEditableOrdenable from '../components/ListaEditableOrdenable.vue'
import TarjetaRetoAsociado from '../components/TarjetaRetoAsociado.vue'
import TallerSeleccionRaCe from '../components/TallerSeleccionRaCe.vue'
import ModalTaller from '../components/ModalTaller.vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import api from '../api.js';
import { useUIState } from '../composables/useUIState.js';
import { useAuthStore } from '../stores/auth.js';
import TourPromptModal from '../components/TourPromptModal.vue';
import ValidarDocenteModal from '../components/ValidarDocenteModal.vue';
import MicroretoModal from '../components/MicroretoModal.vue';
import RaCeGrid from '../components/RaCeGrid.vue';
import { FASES_PROYECTO, CLASES_PROYECTO_DEFECTO, duracionPorFase, COLOR_MAP_FASES } from '../config/fasesProyecto.js';

const route      = useRoute();
const router     = useRouter();
const authStore  = useAuthStore();

// El centro educativo se autorrellena con el del docente logueado y queda bloqueado
// para que no pueda asignarse un proyecto a un centro distinto al suyo.
const centroBloqueado = computed(() => !!authStore.userCentroId);

const paso              = ref(1);
const pasoMaxAlcanzado  = ref(1);
const totalPasos = 7;

watch(paso, (v) => { if (v > pasoMaxAlcanzado.value) pasoMaxAlcanzado.value = v })
const guardando         = ref(false);
const cargando          = ref(false);
const cargandoProyecto  = ref(false);
const proyectoValidado  = ref(false);
const uuid           = ref(route.params.uuid || null);
const isLoaded       = ref(false);
const errorMsg       = ref('');
const publicadoExito = ref(false);
const modalPublicarVisible   = ref(false);
const dropdownEstadoAbierto  = ref(false);
const modalRecurso           = ref(null); // { url, label, tipo: 'video'|'imagen'|'pdf'|'otro' }

function abrirRecurso(item) {
  const filename = (item.filename || '').toLowerCase();
  let tipo = 'otro';
  if (item.resource_type === 'video' || /\.(mp4|mov|avi|webm|mkv)$/.test(filename)) tipo = 'video';
  else if (item.resource_type === 'image' || /\.(jpg|jpeg|png|gif|webp|svg)$/.test(filename)) tipo = 'imagen';
  else if (/\.pdf$/.test(filename)) tipo = 'pdf';
  modalRecurso.value = { url: item.url, label: item.label || item.filename, tipo };
}

// Estado del proyecto con etiqueta y color para el desplegable
const estadoOpciones = {
  en_edicion: { label: 'En edición', dot: 'bg-amber-400',  text: 'text-amber-700',  bg: 'bg-amber-50' },
  archivado:  { label: 'Archivar',   dot: 'bg-gray-400',   text: 'text-gray-500',   bg: 'bg-gray-50' },
  propuesta:  { label: 'Propuesta',  dot: 'bg-violet-400', text: 'text-violet-700', bg: 'bg-violet-50' },
  validado:   { label: 'Validado',   dot: 'bg-rol-fuerte',  text: 'text-rol-fuerte',  bg: 'bg-rol/10' },
  completado: { label: 'Completado', dot: 'bg-sky-500',    text: 'text-sky-700',    bg: 'bg-sky-50' },
};

// Label dinámico del botón de estado — distingue si ya se envió por mail o no
const labelEstadoBtn = computed(() => {
  const e = form.value.estado;
  if (e === 'propuesta') {
    return form.value.enviado_a_empresa_mail
      ? 'Propuesta · SÍ enviada por mail'
      : 'Propuesta · NO enviada por mail';
  }
  return estadoOpciones[e]?.label || 'En edición';
});

const modalBorradorAviso    = ref(false);
const modalPropuestaAviso   = ref(false);
const modalConfirmEnvio     = ref(false);
const modalValidarDocente   = ref(false);
const validandoDocente      = ref(false);

async function validarComoDocente() {
  if (validandoDocente.value || !uuid.value) return;
  validandoDocente.value = true;
  try {
    await api.post(`/startup/proyectos/${uuid.value}/validar-docente`, { decision: 'validar' });
    form.value.docente_validado = true;
    form.value.estado = 'validado';
    modalValidarDocente.value = false;
    modalPropuestaAviso.value = false;
  } catch { /* no crítico */ } finally {
    validandoDocente.value = false;
  }
}
const urlCopiadaModal       = ref(false);
const infoEmpresaAbierta    = ref(false);
const tokenEmpresa          = ref('');
const confirmEnvioTexto     = ref('');

const confirmEnvioValido = computed(() =>
  confirmEnvioTexto.value.trim().toLowerCase() === 'enviar'
);

function abrirConfirmEnvio() {
  confirmEnvioTexto.value = '';
  infoEmpresaAbierta.value = false;
  modalConfirmEnvio.value = true;
}

async function confirmarEnvio() {
  if (!confirmEnvioValido.value) return;
  modalConfirmEnvio.value = false;
  // Marcar el proyecto como enviado a empresa — actualiza BD y el form local
  if (uuid.value) {
    try {
      await api.put(`/startup/proyectos/${uuid.value}`, { enviado_a_empresa_mail: true });
      form.value.enviado_a_empresa_mail = true; // refleja el cambio inmediatamente en el desplegable
    } catch {
      // No es crítico — el usuario ya copió el enlace
    }
  }
}

const landingUrl = computed(() => {
  if (!tokenEmpresa.value) return '';
  const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
  const base = isLocal ? window.location.origin : 'https://dualab.es';
  return `${base}/startup/landing/${tokenEmpresa.value}`;
});

async function copiarUrlModal() {
  await navigator.clipboard.writeText(landingUrl.value);
  urlCopiadaModal.value = true;
  setTimeout(() => { urlCopiadaModal.value = false; }, 2500);
}

const familias   = ref([]);
const ciclos     = ref([]);
const modulos    = ref([]);
const empresas   = ref([]);
const centros    = ref([]);
const microretos = ref([]);

// Autocomplete desde microreto
const autocompletando        = ref(false);
const pendingCicloId         = ref(null);   // ciclo a seleccionar cuando carguen los ciclos del watch
const cursoAutocompletado    = ref(false);  // para destacar el campo curso
const modulosAutocompletados = ref(false);  // para mostrar callout en paso 4
const raCeAutocompletado     = ref(false);  // para mostrar callout en paso 4
const mrEvalOficial          = ref([]);     // evaluacion_oficial del microreto vinculado

const microretoVinculado = computed(() =>
  form.value.microreto_id
    ? microretos.value.find(m => m.id == form.value.microreto_id)
    : null
)

// Contexto crudo del reto original (biblioteca), como referencia fija para todos
// los "Sugerir con IA" del wizard — independiente de si el docente ha editado
// después los campos propios de la propuesta (diseno_reto, fundamentacion...).
const contextoRetoOrigen = computed(() => {
  const mr = microretoVinculado.value
  if (!mr) return undefined
  const partes = []
  if (mr.empresa?.nombre_comercial) partes.push(`Empresa: ${mr.empresa.nombre_comercial}`)
  if (mr.quien_es)      partes.push(`Quién es la empresa: ${mr.quien_es}`)
  if (mr.dia_a_dia)     partes.push(`Día a día: ${mr.dia_a_dia}`)
  if (mr.que_necesitan) partes.push(`Qué necesitan: ${Array.isArray(mr.que_necesitan) ? mr.que_necesitan.join('; ') : mr.que_necesitan}`)
  if (mr.dificultades)  partes.push(`Dificultades: ${Array.isArray(mr.dificultades) ? mr.dificultades.join('; ') : mr.dificultades}`)
  if (mr.limitaciones)  partes.push(`Limitaciones: ${Array.isArray(mr.limitaciones) ? mr.limitaciones.join('; ') : mr.limitaciones}`)
  return partes.length ? partes.join('\n') : undefined
})

const retoEmpresaNombre = computed(() =>
  empresas.value.find(e => e.id == form.value.empresa_id)?.nombre_comercial
  || microretoVinculado.value?.empresa?.nombre_comercial
  || ''
)
const empresaDesdeReto = computed(() =>
  !!(microretoVinculado.value?.empresa_id && microretoVinculado.value.empresa_id == form.value.empresa_id)
)
const retoFamiliaNombre = computed(() =>
  microretoVinculado.value?.familia
  || familias.value.find(f => f.id == form.value.familia_id)?.nombre
  || ''
)
const retoCicloNombre = computed(() =>
  ciclos.value.find(c => c.id == form.value.ciclo_id)?.nombre
  || microretoVinculado.value?.ciclo
  || ''
)

function formatFecha(isoDate) {
  if (!isoDate) return ''
  const d = new Date(isoDate + 'T12:00:00')
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' })
}

// ── Picker de reto ────────────────────────────────────────────────────────────
const microretoModalId  = ref(null)
const retoBusqueda      = ref('')
const retoFiltroFamilia = ref('')
const retoFiltroCiclo   = ref('')
const retoFiltroCurso   = ref('')
// Desplegable de miniaturas: plegado por defecto; se abre al buscar o filtrar.
const retosAbiertos     = ref(false)
watch([retoBusqueda, retoFiltroFamilia, retoFiltroCiclo, retoFiltroCurso], (vals) => {
  if (vals.some(Boolean)) retosAbiertos.value = true
})

const familiasFiltroRetos = computed(() =>
  [...new Set(microretos.value.map(m => m.familia).filter(Boolean))].sort()
)
const ciclosFiltroRetos = computed(() =>
  [...new Set(microretos.value.map(m => m.ciclo || '').filter(Boolean))].sort()
)
const retosFiltrados = computed(() => {
  let list = microretos.value
  const q = retoBusqueda.value.trim().toLowerCase()
  if (q) list = list.filter(m =>
    m.titulo?.toLowerCase().includes(q) ||
    m.empresa?.nombre_comercial?.toLowerCase().includes(q) ||
    m.pregunta_reto?.toLowerCase().includes(q)
  )
  if (retoFiltroFamilia.value) list = list.filter(m => m.familia === retoFiltroFamilia.value)
  if (retoFiltroCiclo.value)   list = list.filter(m => (m.ciclo || '') === retoFiltroCiclo.value)
  if (retoFiltroCurso.value === 'ambos_cursos') {
    list = list.filter(m => m.curso === 'ambos_cursos')
  } else if (retoFiltroCurso.value === '1º') {
    // 1º solo puede abordar retos exclusivamente de 1º.
    list = list.filter(m => String(m.curso ?? '') === '1')
  } else if (retoFiltroCurso.value === '2º') {
    // 2º puede con todo: su propio curso, el de 1º (ya cursado) y los de ambos cursos.
    list = list.filter(m => String(m.curso ?? '') === '2' || String(m.curso ?? '') === '1' || m.curso === 'ambos_cursos')
  }
  return list.slice(0, 60)
})

// Etiqueta legible del curso de un reto — 'ambos_cursos' significa que el
// módulo del que parte existe en 1º y 2º, o que la IA lo generó para encajar
// con varios módulos a la vez, así que vale para cualquiera de los dos cursos.
function cursoLabel(curso) {
  if (curso === 'ambos_cursos') return 'Ambos Cursos: 1º y 2º'
  return curso ? `${curso}º` : ''
}

async function seleccionarReto(mr) {
  form.value.microreto_id = mr.id
  await autocompletarDesdeMicroreto(mr, null)
}

function limpiarReto() {
  form.value.microreto_id = ''
  form.value.empresa_id   = ''
  form.value.familia_id   = ''
  form.value.ciclo_id     = ''
  form.value.curso        = ''
  retoBusqueda.value      = ''
  retoFiltroFamilia.value = ''
  retoFiltroCiclo.value   = ''
  retoFiltroCurso.value   = ''
  retosAbiertos.value     = true
}

const form = ref({
  titulo: '', empresa_id: '', centro_id: '', familia_id: '', ciclo_id: '',
  curso: '', microreto_id: '',
  datos_empresa: { nombre: '', cif: '', sector: '', actividad: '', persona_contacto: '', email: '', telefono: '', web: '', descripcion: '' },
  datos_centro: { nombre: '', municipio: '', docente_nombre: '', docente_email: '' },
  equipo: { docente_responsable: '' },
  modulos_seleccionados: [],
  evaluacion_oficial: [],
  fundamentacion: { contexto: '', justificacion: '', innovacion: '' },
  diseno_reto: { descripcion: '', pregunta_reto: '', restricciones: '', entregables: '' },
  diseno_microproyecto: { fases: [], clases: [], metodologia: '', cronograma: '' },
  resumen: { texto: '' },
  objetivos: { lista: [] },
  kpis: { lista: [] },
  estado: 'en_edicion',
  enviado_a_empresa_mail: false,
});

// ── Estado local de recursos (no se guarda en BD — vive en Cloudinary) ────────
const videosLocales     = ref([])
const documentosLocales = ref([])
const imagenesLocales   = ref([])
const imagenPortadaId   = ref(null)
const imagenPortadaUrl  = ref('')   // imagen_portada_url del proyecto (respaldo si la lista no la trae)

// ── Helpers recursos (Cloudinary) ────────────────────────────────────────────
const labelVideo     = ref('')
const labelDocumento = ref('')
const subiendoVideo  = ref(false)
const subiendoDoc    = ref(false)
const subiendoImagen = ref(false)
const errorSubida    = ref('')

// Subida genérica — etiqueta el archivo con el UUID del microproyecto en Cloudinary
async function subirArchivo(tipo, event) {
  const file = event.target.files?.[0]
  if (!file) return

  if (!uuid.value) {
    errorSubida.value = 'Guarda el proyecto al menos una vez antes de adjuntar archivos.'
    return
  }

  const esvideo  = tipo === 'video'
  const esimagen = tipo === 'imagen'
  if (esvideo) subiendoVideo.value = true
  else if (esimagen) subiendoImagen.value = true
  else subiendoDoc.value = true
  errorSubida.value = ''

  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('microproyecto_uuid', uuid.value)
    formData.append('tipo', tipo)
    const etiqueta = esvideo ? labelVideo.value : labelDocumento.value
    if (!esimagen && etiqueta) formData.append('label', etiqueta)

    const res = await api.post('/upload/recurso', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const entrada = {
      id:            res.data.id,
      label:         res.data.label || res.data.filename,
      url:           res.data.url,
      public_id:     res.data.public_id,
      resource_type: res.data.resource_type,
      filename:      res.data.filename,
    }
    if (esvideo) {
      videosLocales.value.push(entrada)
      labelVideo.value = ''
    } else if (esimagen) {
      imagenesLocales.value.push(entrada)
      if (!imagenPortadaId.value) imagenPortadaId.value = entrada.id
    } else {
      documentosLocales.value.push(entrada)
      labelDocumento.value = ''
    }
  } catch (e) {
    errorSubida.value = e.response?.data?.message || e.response?.data?.errors?.file?.[0] || 'Error al subir el archivo a Cloudinary.'
  } finally {
    subiendoVideo.value  = false
    subiendoDoc.value    = false
    subiendoImagen.value = false
    if (event.target) event.target.value = ''
  }
}

async function removeDocumento(i) {
  const doc = documentosLocales.value[i]
  documentosLocales.value.splice(i, 1)
  if (doc?.public_id) {
    try {
      await api.delete('/upload/recurso', {
        data: { public_id: doc.public_id, resource_type: doc.resource_type || 'raw' },
      })
    } catch { /* si falla el borrado remoto, no bloqueamos al usuario */ }
  }
}

async function removeVideo(i) {
  const vid = videosLocales.value[i]
  videosLocales.value.splice(i, 1)
  if (vid?.public_id) {
    try {
      await api.delete('/upload/recurso', {
        data: { public_id: vid.public_id, resource_type: vid.resource_type || 'video' },
      })
    } catch { /* idem */ }
  }
}

async function removeImagen(i) {
  const img = imagenesLocales.value[i]
  imagenesLocales.value.splice(i, 1)
  if (img?.id === imagenPortadaId.value) {
    imagenPortadaId.value = imagenesLocales.value[0]?.id ?? null
  }
  if (img?.public_id) {
    try {
      await api.delete('/upload/recurso', {
        data: { public_id: img.public_id, resource_type: 'image' },
      })
    } catch { /* idem */ }
  }
}

async function marcarPortada(img) {
  imagenPortadaId.value = img.id
  try {
    await api.put('/upload/recurso/portada', {
      microproyecto_uuid: uuid.value,
      recurso_id: img.id,
    })
  } catch { /* si falla, se corrige solo al recargar el proyecto */ }
}

// ── Fases del proyecto ──────────────────────────────────────────────────────
// Fijas: son las mismas 5 fases que el equipo recorrerá en su workspace
// (EquipoWorkspace.vue). Nombre/descripción no se editan. La duración por fase
// no se fija directamente: se deriva del calendario de sesiones de abajo, donde
// una misma sesión puede cubrir varias fases (ver fasesProyecto.js).
const colorMapFases = COLOR_MAP_FASES;
// Solo inicializa si aún no tiene la forma esperada (proyecto nuevo o antiguo
// sin fases/sesiones) — si ya hay datos guardados, se respeta lo editado por el docente.
watch(() => paso.value === 5, (enPaso5) => {
  if (!enPaso5) return;
  if (form.value.diseno_microproyecto.fases.length !== FASES_PROYECTO.length) {
    form.value.diseno_microproyecto.fases = FASES_PROYECTO.map(f => ({
      nombre: f.label, descripcion: f.desc,
    }));
  }
  if (!Array.isArray(form.value.diseno_microproyecto.clases) || form.value.diseno_microproyecto.clases.length === 0) {
    form.value.diseno_microproyecto.clases = CLASES_PROYECTO_DEFECTO.map(c => ({ fases: [...c.fases] }));
  }
}, { immediate: true });

function addSesion() { form.value.diseno_microproyecto.clases.push({ fases: [] }); }
function removeSesion(i) { form.value.diseno_microproyecto.clases.splice(i, 1); }
function toggleFaseEnSesion(i, numFase) {
  const sesion = form.value.diseno_microproyecto.clases[i];
  const pos = sesion.fases.indexOf(numFase);
  if (pos === -1) sesion.fases.push(numFase);
  else sesion.fases.splice(pos, 1);
}

// ── Helpers listas ────────────────────────────────────────────────────────────
// Lo que sugiere la IA para objetivos y KPIs queda aparte, pendiente de revisar: no
// entra en form (ni se guarda) hasta que el docente lo acepta (GUIA_DISENO §8.4).
const objetivosSugeridos = ref([]);
const kpisSugeridos      = ref([]);
function anadirSugerencias(destino, pendientes, nuevas) {
  const vistas = new Set([...destino, ...pendientes.value].map(normalizarTexto));
  nuevas.forEach(t => {
    const clave = normalizarTexto(t);
    if (clave && !vistas.has(clave)) { pendientes.value.push(t); vistas.add(clave); }
  });
}
function aceptarSugerencia(lista, pendientes, i) {
  lista.push(pendientes.value[i]);
  pendientes.value.splice(i, 1);
}
function aceptarTodas(lista, pendientes) {
  lista.push(...pendientes.value);
  pendientes.value = [];
}
const sugerenciasPendientes = computed(() => objetivosSugeridos.value.length + kpisSugeridos.value.length);
const avisoPaso6 = ref('');
function siguienteDesdePaso6() {
  if (sugerenciasPendientes.value) {
    avisoPaso6.value = `Tienes ${sugerenciasPendientes.value} sugerencia(s) de la IA sin revisar: añádelas o descártalas antes de seguir.`;
    return;
  }
  avisoPaso6.value = '';
  guardar(7);
}

const sugirendoKpis = ref(false);
const errorKpis = ref('');
async function sugerirKpis() {
  sugirendoKpis.value = true; errorKpis.value = '';
  try {
    const res = await api.post('/startup/sugerir-kpis', {
      titulo:        form.value.titulo        || undefined,
      pregunta_reto: form.value.diseno_reto?.pregunta_reto || undefined,
      descripcion:   form.value.diseno_reto?.descripcion   || undefined,
      entregables:   form.value.diseno_reto?.entregables   || undefined,
      objetivos:     form.value.objetivos?.lista?.length ? form.value.objetivos.lista : undefined,
      ra_ce:         form.value.evaluacion_oficial.length ? serializarEvaluacionOficialATexto(form.value.evaluacion_oficial) : undefined,
      reto_origen:   contextoRetoOrigen.value,
    });
    anadirSugerencias(form.value.kpis.lista, kpisSugeridos, res.data.kpis ?? []);
  } catch {
    errorKpis.value = 'Error al contactar con la IA. Inténtalo de nuevo.';
  } finally {
    sugirendoKpis.value = false;
  }
}

const sugiriendoObjetivos = ref(false);
const errorObjetivos = ref('');
async function sugerirObjetivos() {
  sugiriendoObjetivos.value = true; errorObjetivos.value = '';
  try {
    const res = await api.post('/startup/sugerir-objetivos', {
      titulo:        form.value.titulo || undefined,
      pregunta_reto: form.value.diseno_reto?.pregunta_reto || undefined,
      descripcion:   form.value.diseno_reto?.descripcion   || undefined,
      entregables:   form.value.diseno_reto?.entregables   || undefined,
      ra_ce:         form.value.evaluacion_oficial.length ? serializarEvaluacionOficialATexto(form.value.evaluacion_oficial) : undefined,
      reto_origen:   contextoRetoOrigen.value,
    });
    anadirSugerencias(form.value.objetivos.lista, objetivosSugeridos, res.data.objetivos ?? []);
  } catch {
    errorObjetivos.value = 'Error al contactar con la IA. Inténtalo de nuevo.';
  } finally {
    sugiriendoObjetivos.value = false;
  }
}

const sugiriendoFundamentacion = ref(false);
const errorFundamentacion = ref('');
async function sugerirFundamentacion() {
  sugiriendoFundamentacion.value = true; errorFundamentacion.value = '';
  try {
    const res = await api.post('/startup/sugerir-fundamentacion', {
      titulo:        form.value.titulo || undefined,
      pregunta_reto: form.value.diseno_reto?.pregunta_reto || undefined,
      descripcion:   form.value.diseno_reto?.descripcion   || undefined,
      contexto:      form.value.fundamentacion?.contexto   || undefined,
      ra_ce:         form.value.evaluacion_oficial.length ? serializarEvaluacionOficialATexto(form.value.evaluacion_oficial) : undefined,
      reto_origen:   contextoRetoOrigen.value,
    });
    if (res.data.justificacion) form.value.fundamentacion.justificacion = res.data.justificacion;
    if (res.data.innovacion)    form.value.fundamentacion.innovacion    = res.data.innovacion;
  } catch {
    errorFundamentacion.value = 'Error al contactar con la IA. Inténtalo de nuevo.';
  } finally {
    sugiriendoFundamentacion.value = false;
  }
}

const sugiriendoMetodologia = ref(false);
const errorMetodologia = ref('');
async function sugerirMetodologia() {
  sugiriendoMetodologia.value = true; errorMetodologia.value = '';
  try {
    const res = await api.post('/startup/sugerir-metodologia', {
      titulo:        form.value.titulo || undefined,
      pregunta_reto: form.value.diseno_reto?.pregunta_reto || undefined,
      descripcion:   form.value.diseno_reto?.descripcion   || undefined,
      ciclo:         retoCicloNombre.value || undefined,
      curso:         form.value.curso || undefined,
      empresa:       retoEmpresaNombre.value
                       ? `${retoEmpresaNombre.value}${form.value.datos_empresa?.sector ? ' (sector: ' + form.value.datos_empresa.sector + ')' : ''}`
                       : undefined,
      modulos:       form.value.modulos_seleccionados?.length ? form.value.modulos_seleccionados.map(m => m.nombre).join(', ') : undefined,
      fases:         FASES_PROYECTO.map(f => `${f.label} (${duracionPorFase(form.value.diseno_microproyecto.clases, f.num)} sesión(es))`).join(', '),
      reto_origen:   contextoRetoOrigen.value,
    });
    if (res.data.metodologia) form.value.diseno_microproyecto.metodologia = res.data.metodologia;
    if (res.data.resumen)     form.value.resumen.texto                   = res.data.resumen;
  } catch {
    errorMetodologia.value = 'Error al contactar con la IA. Inténtalo de nuevo.';
  } finally {
    sugiriendoMetodologia.value = false;
  }
}

// ── Módulos ───────────────────────────────────────────────────────────────────
function toggleModulo(m) {
  const idx = form.value.modulos_seleccionados.findIndex(x => x.id === m.id);
  if (idx >= 0) form.value.modulos_seleccionados.splice(idx, 1);
  else form.value.modulos_seleccionados.push({ id: m.id, nombre: m.nombre });
}
function moduloSeleccionado(id) { return form.value.modulos_seleccionados.some(m => m.id === id); }

// ── RA/CE selection ───────────────────────────────────────────────────────────
// Paso 3: la selección parte de los RA/CE originales del reto; desde ahí se amplía en
// modal con «Seleccionar otros» (catálogo con los ya elegidos marcados) o a mano.
const modalRaCe        = ref('')       // '' | 'catalogo' | 'manual'
const arbolModal       = ref(null)     // TallerArbolRaCe del modal (Plegar / Desplegar todo)
function abrirModalRaCe(nombre) {
  modalRaCe.value = nombre
  if (nombre === 'catalogo') cargarCatalogoRaCe()
}
// Vuelve a dejar exactamente los RA/CE del reto (deshace quitar/añadir).
async function restaurarRaCeDelReto() {
  const original = microretoVinculado.value?.evaluacion_oficial
  if (!Array.isArray(original) || !original.length) return
  if (!window.confirm('Se sustituirá la selección actual por los RA y CE originales del reto. ¿Continuar?')) return
  form.value.evaluacion_oficial = clonarEvaluacionOficial(original)
  await cargarCatalogoRaCe()
}
const catalogoRaCe     = ref([])       // [{ modulo, moduloId, ras: [{id,orden,descripcion,criterios:[...]}] }]
const cargandoCatalogo = ref(false)
const cargandoIaRaCe   = ref(false)
const sugerenciaIaPendienteRevision = ref(false) // true tras usar "Sugerir con IA" — recuerda revisar antes de publicar
const ceIdsIa          = ref(new Set())  // CE añadidos por la IA (etiqueta «Sugerencia IA» en el árbol)
const textosIa         = ref(new Set())  // RA sin id añadidos por la IA (texto normalizado)
function marcarSugerenciasRevisadas() {
  sugerenciaIaPendienteRevision.value = false
  ceIdsIa.value = new Set()
  textosIa.value = new Set()
}
const raExpandido      = ref({})       // { raId: bool }
const raChecked        = ref({})       // { raId: bool }
const ceChecked        = ref({})       // { ceId: bool }

function normalizarTexto(t) {
  return (t || '').trim().toLowerCase().replace(/\s+/g, ' ')
}

let claveCatalogoEnCurso = ''
async function cargarCatalogoRaCe() {
  const mods = form.value.modulos_seleccionados
  if (!mods.length) { catalogoRaCe.value = []; return }
  // Al abrir un proyecto en el paso 3 saltan a la vez el watch del paso y el de los
  // módulos: una sola petición por combinación de módulos.
  const clave = mods.map(m => m.id).join(',')
  if (cargandoCatalogo.value && clave === claveCatalogoEnCurso) return
  claveCatalogoEnCurso = clave
  cargandoCatalogo.value = true
  try {
    const results = await Promise.all(mods.map(m => api.get(`/modulos/${m.id}/ra-ce`)))
    catalogoRaCe.value = results.map((res, i) => ({
      modulo:   mods[i].nombre,
      moduloId: mods[i].id,
      ras:      res.data.ra || [],
    }))
    raExpandido.value = {}; raChecked.value = {}; ceChecked.value = {}

    // CE que ya están en "RA/CE de este proyecto" — se marcan como ya seleccionados,
    // por id exacto (ahora que evaluacion_oficial los conserva). Se compara contra el
    // estado actual del proyecto (no contra raCeDelReto) para que, si ya se quitó
    // algo, no vuelva a aparecer marcado. Las entradas manuales (ra_id null) nunca
    // podrán marcar nada aquí — no están en ningún catálogo, por definición.
    const ceIdsDeEsteProyecto = new Set()
    form.value.evaluacion_oficial.forEach(e => (e.ce_ids || []).forEach(id => ceIdsDeEsteProyecto.add(id)))
    // Entradas sin ce_ids (antiguas o copiadas de un reto): sus CE se marcan por texto
    const textosPorRa = new Map()
    form.value.evaluacion_oficial.forEach(e => {
      if (e.ra_id) textosPorRa.set(e.ra_id, new Set((e.ce || []).map(normalizarTexto)))
    })

    catalogoRaCe.value.forEach(mod => {
      mod.ras.forEach(ra => {
        raExpandido.value[ra.id] = true
        ra.criterios.forEach(ce => {
          ceChecked.value[ce.id] = ceIdsDeEsteProyecto.has(ce.id)
            || !!textosPorRa.get(ra.id)?.has(normalizarTexto(ce.descripcion))
        })
        raChecked.value[ra.id] = ra.criterios.length > 0 && ra.criterios.every(ce => ceChecked.value[ce.id])
      })
    })
  } finally {
    cargandoCatalogo.value = false
  }
}

// El árbol del paso 3 aplica cada casilla al momento, pero solo sobre la entrada de ESE
// RA en evaluacion_oficial (conserva su «aplicacion» y su posición). Nunca reconstruye la
// lista entera: así, abrir el paso no borra entradas que el catálogo no sabe mapear
// (RA/CE con texto pero sin ce_ids, manuales, de módulos sin catálogo).
function sincronizarRa(ra, mod) {
  const ces = ra.criterios.filter(ce => ceChecked.value[ce.id])
  const lista = form.value.evaluacion_oficial
  const idx = lista.findIndex(e => e.ra_id === ra.id)
  // CE de la entrada que no están en el catálogo de este RA (texto sin id): las casillas no
  // los controlan, así que se conservan siempre
  const delCatalogo = new Set(ra.criterios.map(c => normalizarTexto(c.descripcion)))
  const ajenos = idx >= 0 ? (lista[idx].ce || []).filter(t => !delCatalogo.has(normalizarTexto(t))) : []
  if (!ces.length && !ajenos.length) {
    if (idx >= 0) lista.splice(idx, 1)
  } else if (idx >= 0) {
    lista[idx].ce_ids = ces.map(c => c.id)
    lista[idx].ce     = [...ces.map(c => c.descripcion), ...ajenos]
  } else {
    lista.push({ modulo: mod.modulo, ra_id: ra.id, ra: ra.descripcion,
                 ce_ids: ces.map(c => c.id), ce: ces.map(c => c.descripcion), aplicacion: '' })
  }
  raChecked.value[ra.id] = ces.length === ra.criterios.length
}

function toggleRa(ra, mod) {
  const v = raEstado(ra) !== 'all'
  ra.criterios.forEach(ce => { ceChecked.value[ce.id] = v })
  sincronizarRa(ra, mod)
}

function toggleCe(ce, ra, mod) {
  ceChecked.value[ce.id] = !ceChecked.value[ce.id]
  sincronizarRa(ra, mod)
}

// Casilla del módulo. Quitar un módulo con RA/CE elegidos pide confirmación y quita
// también esas entradas de la propuesta.
function toggleModuloArbol(m) {
  if (moduloSeleccionado(m.id)) {
    const n = form.value.evaluacion_oficial.filter(e => e.modulo === m.nombre).length
    if (n && !window.confirm(`Al quitar «${m.nombre}» se quitarán también sus ${n} RA de esta propuesta. ¿Continuar?`)) return
    form.value.evaluacion_oficial = form.value.evaluacion_oficial.filter(e => e.modulo !== m.nombre)
  }
  toggleModulo(m)
}

function quitarEntradaRaCe(i) {
  form.value.evaluacion_oficial.splice(i, 1)
}
function quitarCeDeEntrada(i, j) {
  const entry = form.value.evaluacion_oficial[i]
  if (!entry) return
  entry.ce.splice(j, 1)
  if (Array.isArray(entry.ce_ids)) entry.ce_ids.splice(j, 1)
  if (!entry.ce.length) form.value.evaluacion_oficial.splice(i, 1)
}

const raIdsCatalogo = computed(() => {
  const ids = new Set()
  catalogoRaCe.value.forEach(mod => mod.ras.forEach(ra => ids.add(ra.id)))
  return ids
})

function raEstado(ra) {
  const n = ra.criterios.filter(ce => ceChecked.value[ce.id]).length
  if (n === 0) return 'none'
  if (n === ra.criterios.length) return 'all'
  return 'some'
}

// Añade las entradas de "nuevas" que no estén ya en "actuales" (por ra_id exacto, o
// por texto normalizado si no hay id — entradas manuales), para que "Sugerir otros
// con IA" amplíe la selección en vez de reemplazarla.
function mergeEvaluacionOficial(actuales, nuevas) {
  const clave = e => e.ra_id ? `id:${e.ra_id}` : `txt:${normalizarTexto(e.ra)}`
  const existentes = new Set(actuales.map(clave))
  const aAgregar = nuevas.filter(e => !existentes.has(clave(e)))
  return [...actuales, ...aAgregar]
}

async function sugerirRaCeConIa() {
  cargandoIaRaCe.value = true
  try {
    const res = await api.post('/startup/sugerir-ra-ce', {
      modulo_ids:   form.value.modulos_seleccionados.map(m => m.id),
      titulo:       form.value.titulo,
      pregunta_reto: form.value.diseno_reto.pregunta_reto,
      descripcion:  form.value.diseno_reto.descripcion,
      contexto:     form.value.fundamentacion.contexto,
      reto_origen:  contextoRetoOrigen.value,
    })
    const sugeridos = res.data.seleccion || []
    if (sugeridos.length) {
      const antes = form.value.evaluacion_oficial.length
      form.value.evaluacion_oficial = mergeEvaluacionOficial(form.value.evaluacion_oficial, sugeridos)
      // Marca lo nuevo como «Sugerencia IA» en el árbol hasta que el docente lo revise.
      form.value.evaluacion_oficial.slice(antes).forEach(e => {
        ;(e.ce_ids || []).forEach(id => ceIdsIa.value.add(id))
        if (!e.ra_id) textosIa.value.add(normalizarTexto(e.ra))
      })
      sugerenciaIaPendienteRevision.value = true
      await cargarCatalogoRaCe() // refleja en las casillas lo que ha añadido la IA
    }
  } catch (e) {
    console.error('Error sugiriendo RA/CE con IA', e)
  } finally {
    cargandoIaRaCe.value = false
  }
}

// El catálogo oficial de los módulos marcados se carga al entrar en el paso 3 y cuando
// cambian los módulos estando en él.
watch(paso, (v) => { if (v === 3) cargarCatalogoRaCe() })
watch(() => form.value.modulos_seleccionados, () => {
  if (paso.value === 3) cargarCatalogoRaCe()
}, { deep: true })

const busquedaRaCe = ref('')

const catalogoFiltrado = computed(() => {
  const q = busquedaRaCe.value.trim().toLowerCase()
  if (!q) return catalogoRaCe.value
  return catalogoRaCe.value.map(mod => {
    const ras = mod.ras.map(ra => {
      const raMatch = ra.descripcion.toLowerCase().includes(q)
      const criterios = raMatch
        ? ra.criterios
        : ra.criterios.filter(ce => ce.descripcion.toLowerCase().includes(q))
      return criterios.length ? { ...ra, criterios } : null
    }).filter(Boolean)
    return ras.length ? { ...mod, ras } : null
  }).filter(Boolean)
})

// RA/CE oficiales del reto vinculado — siempre reflejan el reto, independientemente
// de lo que el docente escriba o seleccione aparte en el cuadro de edición libre.
const raCeDelReto = computed(() => {
  const evalOficial = microretoVinculado.value?.evaluacion_oficial
  if (!Array.isArray(evalOficial) || !evalOficial.length) return []
  const porModulo = {}
  const orden = []
  evalOficial.forEach(e => {
    const modulo = e.modulo || 'Sin módulo'
    if (!porModulo[modulo]) { porModulo[modulo] = { modulo, ras: [] }; orden.push(modulo) }
    porModulo[modulo].ras.push({
      descripcion: e.ra || '',
      ra_id: e.ra_id ?? null,
      criterios: Array.isArray(e.ce) ? e.ce : (e.ce ? [e.ce] : []),
      ce_ids: Array.isArray(e.ce_ids) ? e.ce_ids : [],
    })
  })
  return orden.map(m => porModulo[m])
})

// Serializa una lista evaluacion_oficial al mismo formato de texto plano que usa el
// backend ("[Módulo]\nRA:...\nCE:\n  • ...") — solo para dar contexto textual a los
// endpoints de IA de KPIs/fundamentación, que siguen esperando un string.
function serializarEvaluacionOficialATexto(lista) {
  return lista.map(e => {
    const ces = (e.ce || []).map(c => `  • ${c}`).join('\n')
    return `[${e.modulo}]\nRA: ${e.ra}\nCE:\n${ces}`
  }).join('\n\n')
}

// Clona profundamente una lista evaluacion_oficial (p. ej. la del reto vinculado) para
// copiarla al proyecto sin que ambas compartan referencias — si no se clona, borrar un
// CE en "de este proyecto" mutaría también los datos del reto original.
function clonarEvaluacionOficial(lista) {
  return lista.map(e => ({
    modulo: e.modulo || '',
    ra_id: e.ra_id ?? null,
    ra: e.ra || '',
    ce_ids: Array.isArray(e.ce_ids) ? [...e.ce_ids] : [],
    ce: Array.isArray(e.ce) ? [...e.ce] : (e.ce ? [e.ce] : []),
    aplicacion: e.aplicacion || '',
  }))
}

// Formulario "Añadir RA/CE manualmente" — para RA/CE que no están en el catálogo oficial.
const nuevoRaManual = ref({ moduloId: '', ra: '', ces: [''] })

const raManualValido = computed(() =>
  !!nuevoRaManual.value.moduloId &&
  !!nuevoRaManual.value.ra.trim() &&
  nuevoRaManual.value.ces.some(c => c.trim())
)

function addCeManualField() { nuevoRaManual.value.ces.push('') }
function removeCeManualField(i) { nuevoRaManual.value.ces.splice(i, 1) }

function anadirRaManualAlProyecto() {
  if (!raManualValido.value) return
  const modulo = form.value.modulos_seleccionados.find(m => m.id === nuevoRaManual.value.moduloId)
  if (!modulo) return
  const ces = nuevoRaManual.value.ces.map(c => c.trim()).filter(Boolean)
  form.value.evaluacion_oficial.push({
    modulo: modulo.nombre,
    ra_id: null,
    ra: nuevoRaManual.value.ra.trim(),
    ce_ids: [],
    ce: ces,
    aplicacion: '',
  })
  nuevoRaManual.value = { moduloId: nuevoRaManual.value.moduloId, ra: '', ces: [''] }
}

// true cuando el docente ha ampliado/cambiado el RA/CE del proyecto más allá de lo
// que traía el reto vinculado (vía "Sugerir otros con IA" o selección manual).
const raCeDivergeDelReto = computed(() => {
  const original = microretoVinculado.value?.evaluacion_oficial
  if (!Array.isArray(original) || !original.length) return false
  const normalizarLista = (lista) => lista.map(e => ({
    ra_id: e.ra_id ?? null,
    ra: normalizarTexto(e.ra),
    ce_ids: [...(e.ce_ids || [])].sort((a, b) => a - b),
    ce: [...(e.ce || [])].map(normalizarTexto).sort(),
  })).sort((a, b) => (a.ra > b.ra ? 1 : a.ra < b.ra ? -1 : 0))
  return JSON.stringify(normalizarLista(original)) !== JSON.stringify(normalizarLista(form.value.evaluacion_oficial))
})

// Totales de la propuesta (incluye RA/CE manuales y de módulos sin catálogo).
const totalCeSeleccionados = computed(() =>
  form.value.evaluacion_oficial.reduce((n, e) => n + (e.ce?.length || 0), 0)
)
const totalRaSeleccionados = computed(() => form.value.evaluacion_oficial.length)

// RA/CE del reto sin ids (datos antiguos): se reconocen por texto «ra|ce».
const textosDelReto = computed(() => {
  const t = new Set()
  ;(microretoVinculado.value?.evaluacion_oficial || []).forEach(e =>
    (Array.isArray(e.ce) ? e.ce : [e.ce]).forEach(ce => t.add(`${normalizarTexto(e.ra)}|${normalizarTexto(ce)}`)))
  return t
})

// CE que traía el reto vinculado — etiqueta «Del reto» en el árbol.
const ceIdsDelReto = computed(() => {
  const ids = new Set()
  ;(microretoVinculado.value?.evaluacion_oficial || []).forEach(e => (e.ce_ids || []).forEach(id => ids.add(id)))
  return ids
})

// ── Catálogos ─────────────────────────────────────────────────────────────────
async function cargarCatalogos() {
  const [rE, rC, rF, rM] = await Promise.all([
    api.get('/empresas'), api.get('/centros'),
    api.get('/familias'), api.get('/microretos'),
  ]);
  empresas.value   = rE.data;
  centros.value    = rC.data;
  familias.value   = rF.data;
  microretos.value = rM.data;
}

watch(() => form.value.familia_id, async (id) => {
  if (cargandoProyecto.value) return;
  ciclos.value  = []; modulos.value = [];
  form.value.ciclo_id = ''; form.value.modulos_seleccionados = [];
  if (!id) return;
  const fam = familias.value.find(f => f.id == id);
  if (!fam) return;
  const res = await api.get(`/familias/${encodeURIComponent(fam.nombre)}/ciclos`);
  ciclos.value = res.data;
  // Aplicar ciclo pendiente de autocomplete (viene después de cargar ciclos)
  if (pendingCicloId.value) {
    form.value.ciclo_id = pendingCicloId.value;
    pendingCicloId.value = null;
  }
});

watch(() => form.value.ciclo_id, async (id) => {
  if (cargandoProyecto.value) return;
  modulos.value = []; form.value.modulos_seleccionados = [];
  if (!id) return;
  const res = await api.get(`/ciclos/${id}/modulos`);
  modulos.value = res.data;
  // Auto-seleccionar módulos que coincidan con los del microreto vinculado
  if (mrEvalOficial.value.length) {
    const nombresEval = mrEvalOficial.value.map(e => e.modulo?.toLowerCase().trim()).filter(Boolean);
    const matches = modulos.value.filter(m =>
      nombresEval.some(n => m.nombre?.toLowerCase().includes(n) || n.includes(m.nombre?.toLowerCase()))
    );
    if (matches.length) {
      form.value.modulos_seleccionados = matches.map(m => ({ id: m.id, nombre: m.nombre }));
      modulosAutocompletados.value = true;
    }
  }
});

watch(() => form.value.empresa_id, (id) => {
  if (cargandoProyecto.value) return;
  const e = empresas.value.find(x => x.id == id);
  if (!e) return;
  form.value.datos_empresa = {
    nombre:            e.nombre_comercial || '',
    cif:               e.cif || '',
    sector:            e.sector || '',
    actividad:         e.actividad || '',
    persona_contacto:  e.persona_contacto || '',
    email:             e.email_contacto || e.email_general || '',
    telefono:          e.telefono || '',
    web:               e.web || '',
    descripcion:       e.dia_a_normal || '',
  };
  if (e.familias?.length === 1) form.value.familia_id = e.familias[0].id;
});

watch(() => form.value.centro_id, (id) => {
  if (cargandoProyecto.value) return;
  const c = centros.value.find(x => x.id == id);
  if (!c) return;
  form.value.datos_centro.nombre    = c.nombre    || '';
  form.value.datos_centro.municipio = c.municipio || '';
});

// ── Autocomplete desde microreto ─────────────────────────────────────────────
async function autocompletarDesdeMicroreto(mr, sesion = null) {
  if (!mr) return;
  autocompletando.value = true;

  // Guardar evaluacion_oficial para auto-seleccionar módulos cuando carguen
  if (Array.isArray(mr.evaluacion_oficial) && mr.evaluacion_oficial.length) {
    mrEvalOficial.value = mr.evaluacion_oficial;
  }

  // ── Paso 1: Básicos ───────────────────────────────────────────────────────
  if (!form.value.titulo && mr.titulo) form.value.titulo = mr.titulo;

  // Solo autocompletar cuando el reto es exclusivamente de 1º o 2º — un reto de
  // "ambos cursos" no debe forzar un curso concreto en el Encuentro, que siempre
  // representa un grupo-clase real de un único año.
  if (String(mr.curso) === '1' || String(mr.curso) === '2') {
    form.value.curso = String(mr.curso) === '1' ? '1º' : '2º';
    cursoAutocompletado.value = true;
    setTimeout(() => { cursoAutocompletado.value = false; }, 6000);
  }

  // ── Paso 5: El Reto ───────────────────────────────────────────────────────
  if (!form.value.diseno_reto.pregunta_reto && mr.pregunta_reto)
    form.value.diseno_reto.pregunta_reto = mr.pregunta_reto;

  const contexto = [mr.quien_es, mr.dia_a_dia].filter(Boolean).join('\n\n');
  if (!form.value.fundamentacion.contexto && contexto)
    form.value.fundamentacion.contexto = contexto;

  // Descripción del reto: qué necesita la empresa
  if (!form.value.diseno_reto.descripcion) {
    const queNecesitan = Array.isArray(mr.que_necesitan) ? mr.que_necesitan.join('\n') : (mr.que_necesitan || '');
    const friccion     = [mr.empresa?.friccion_area, mr.empresa?.friccion_problema].filter(Boolean).join('. ');
    const descripcion  = queNecesitan || friccion;
    if (descripcion) form.value.diseno_reto.descripcion = descripcion;
  }

  // Restricciones: dificultades + limitaciones
  if (!form.value.diseno_reto.restricciones) {
    const dificultades = Array.isArray(mr.dificultades) ? mr.dificultades : (mr.dificultades ? [mr.dificultades] : []);
    const limitaciones = Array.isArray(mr.limitaciones) ? mr.limitaciones : (mr.limitaciones ? [mr.limitaciones] : []);
    const restricciones = [...dificultades, ...limitaciones].join(', ');
    if (restricciones) form.value.diseno_reto.restricciones = restricciones;
  }

  // Entregables: prototipos esperados
  if (!form.value.diseno_reto.entregables && Array.isArray(mr.prototipos) && mr.prototipos.length)
    form.value.diseno_reto.entregables = mr.prototipos.join('\n');

  // ── FK: empresa → dispara watch que rellena datos_empresa ─────────────────
  if (mr.empresa_id) {
    // Reservar el ciclo pendiente ANTES de que la cascada de watches lo resetee
    if (mr.ciclo_id) pendingCicloId.value = mr.ciclo_id;

    form.value.empresa_id = mr.empresa_id;

    // Si el watch no configuró familia (empresa con varias), usar la del microreto
    await nextTick();
    if (!form.value.familia_id && mr.empresa?.familias?.length) {
      form.value.familia_id = mr.empresa.familias[0].id;
    }
  }

  // ── FK: centro → empresa.centro_id o búsqueda por nombre desde sesión ────
  if (!form.value.centro_id) {
    const centroId = mr.empresa?.centro_id ?? mr.empresa?.centroEducativo?.id ?? null;
    if (centroId) {
      form.value.centro_id = centroId; // el watch rellena datos_centro.nombre
    } else if (sesion?.centro_educativo) {
      // Intentar coincidencia por nombre en el catálogo de centros
      const q = sesion.centro_educativo.toLowerCase().trim();
      const matched = centros.value.find(c =>
        (c.nombre || '').toLowerCase().includes(q) || q.includes((c.nombre || '').toLowerCase())
      );
      if (matched) {
        form.value.centro_id = matched.id; // el watch rellena datos_centro.nombre
      } else {
        // Fallback directo: escribir el nombre tal cual viene de la sesión
        form.value.datos_centro.nombre = sesion.centro_educativo;
      }
    }
  }

  // ── RA/CE desde evaluacion_oficial ───────────────────────────────────────
  if (!form.value.evaluacion_oficial.length && Array.isArray(mr.evaluacion_oficial) && mr.evaluacion_oficial.length) {
    form.value.evaluacion_oficial = clonarEvaluacionOficial(mr.evaluacion_oficial);
    raCeAutocompletado.value = true;
  }

  setTimeout(() => { autocompletando.value = false; }, 3000);
}

async function cargarProyecto() {
  if (!uuid.value) return;
  cargando.value = true;
  cargandoProyecto.value = true;
  try {
    const [proyRes, recRes] = await Promise.all([
      api.get(`/startup/proyectos/${uuid.value}`),
      api.get('/upload/recursos', { params: { microproyecto: uuid.value } }),
    ]);
    const p = proyRes.data;
    // Defensa: un proyecto sin reto vinculado (dato legacy o manipulado) no puede
    // abrir directamente en un paso avanzado — se fuerza de vuelta al paso 1.
    paso.value = p.microreto_id ? (p.paso_actual || 1) : 1;
    pasoMaxAlcanzado.value = p.microreto_id ? (p.paso_actual || 1) : 1;
    proyectoValidado.value = !!p.empresa_validado;
    if (p.estado === 'en_edicion') modalBorradorAviso.value = true;
    Object.assign(form.value, {
      titulo: p.titulo || '', empresa_id: p.empresa_id || '',
      centro_id: p.centro_id || '', familia_id: p.familia_id || '',
      ciclo_id: p.ciclo_id || '', curso: p.curso || '',
      microreto_id: p.microreto_id || '', estado: p.estado || 'en_edicion',
      enviado_a_empresa_mail: !!p.enviado_a_empresa_mail,
      ...(p.datos_empresa    && { datos_empresa: p.datos_empresa }),
      ...(p.datos_centro     && { datos_centro: p.datos_centro }),
      ...(p.equipo           && { equipo: p.equipo }),
      ...(p.modulos_seleccionados && { modulos_seleccionados: p.modulos_seleccionados }),
      ...(Array.isArray(p.evaluacion_oficial) && { evaluacion_oficial: clonarEvaluacionOficial(p.evaluacion_oficial) }),
      ...(p.fundamentacion   && { fundamentacion: p.fundamentacion }),
      ...(p.diseno_reto      && { diseno_reto: p.diseno_reto }),
      ...(p.diseno_microproyecto && { diseno_microproyecto: p.diseno_microproyecto }),
      ...(p.resumen          && { resumen: p.resumen }),
      ...(p.objetivos        && { objetivos: p.objetivos }),
      ...(p.kpis             && { kpis: p.kpis }),
    });
    // Cargar ciclos y módulos en cascada sin que los watchers vacíen los valores
    if (p.familia_id) {
      const fam = familias.value.find(f => f.id == p.familia_id);
      if (fam) {
        const res = await api.get(`/familias/${encodeURIComponent(fam.nombre)}/ciclos`);
        ciclos.value = res.data;
      }
    }
    if (p.ciclo_id) {
      const res = await api.get(`/ciclos/${p.ciclo_id}/modulos`);
      modulos.value = res.data;
    }
    // Recursos viven en Cloudinary — se cargan aparte
    videosLocales.value     = recRes.data.videos    || [];
    documentosLocales.value = recRes.data.documentos || [];
    imagenesLocales.value   = recRes.data.imagenes  || [];
    imagenPortadaId.value   = recRes.data.imagen_portada_id ?? null;
    imagenPortadaUrl.value  = p.imagen_portada_url || '';
    ultimaModificacion.value = p.updated_at || null;
  } finally {
    cargandoProyecto.value = false;
    cargando.value = false;
  }
}

onMounted(async () => {
  setTimeout(() => { isLoaded.value = true; }, 80);
  await cargarCatalogos();
  if (!uuid.value && authStore.userCentroId) form.value.centro_id = authStore.userCentroId;
  await cargarProyecto();
  // A partir de aquí, cualquier cambio del formulario cuenta como «sin guardar».
  await nextTick();
  seguimientoActivo.value = true;
  // Tour prompt desactivado temporalmente — reactivar poniendo showTourPrompt.value = true cuando se necesite.
  // await nextTick();
  // if (!modalBorradorAviso.value) showTourPrompt.value = true;
});

// ── Guardar ───────────────────────────────────────────────────────────────────
// `enviado`: foto del formulario al empezar a guardar. Si se escribió algo mientras se
// guardaba, ese cambio no viajó en la petición: sigue «Sin guardar» (y avisa al salir).
function marcarGuardado(data, enviado) {
  sinGuardar.value = JSON.stringify(form.value) !== enviado;
  ultimaModificacion.value = data?.updated_at || new Date().toISOString();
}

async function guardar(siguientePaso) {
  guardando.value = true; errorMsg.value = '';
  const enviado = JSON.stringify(form.value);
  try {
    const limpiar = lista => (lista || []).map(t => String(t).trim()).filter(Boolean);
    const payload = {
      ...form.value,
      objetivos: { ...form.value.objetivos, lista: limpiar(form.value.objetivos.lista) },
      kpis:      { ...form.value.kpis,      lista: limpiar(form.value.kpis.lista) },
      paso_actual: siguientePaso,
    };
    if (uuid.value) {
      const res = await api.put(`/startup/proyectos/${uuid.value}`, payload);
      proyectoValidado.value = !!res.data.empresa_validado;
      if (res.data.token_empresa) tokenEmpresa.value = res.data.token_empresa;
      marcarGuardado(res.data, enviado);
    } else {
      const res = await api.post('/startup/proyectos', {
        titulo:       form.value.titulo,
        microreto_id: form.value.microreto_id,
      });
      uuid.value = res.data.uuid;
      const upd = await api.put(`/startup/proyectos/${uuid.value}`, payload);
      proyectoValidado.value = !!upd.data.empresa_validado;
      if (upd.data.token_empresa) tokenEmpresa.value = upd.data.token_empresa;
      marcarGuardado(upd.data, enviado);
      router.replace({ name: 'startup-day-editar', params: { uuid: uuid.value } });
    }
    paso.value = siguientePaso;
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Error al guardar. Inténtalo de nuevo.';
  } finally {
    guardando.value = false;
  }
}

function mostrarModalPublicar() {
  modalPublicarVisible.value = true;
}

async function confirmarGuardarBorrador() {
  modalPublicarVisible.value = false;
  form.value.estado = 'en_edicion';
  await guardar(paso.value);
}

async function archivarProyecto() {
  form.value.estado = 'archivado';
  await guardar(paso.value);
  dropdownEstadoAbierto.value = false;
}

async function aprobarProyecto() {
  dropdownEstadoAbierto.value = false;
  form.value.estado = 'propuesta';
  await guardar(paso.value);
  if (!errorMsg.value) modalPropuestaAviso.value = true;
}

// Cierra el modal de validación (Vía A empresa / Vía B docente) y, una vez
// gestionada la validación, muestra la pantalla "¡Proyecto aprobado!".
function cerrarModalPropuestaAviso() {
  modalPropuestaAviso.value = false;
  publicadoExito.value = true;
}

async function seleccionarEstado(estado) {
  dropdownEstadoAbierto.value = false;
  if (estado === 'en_edicion') {
    form.value.estado = 'en_edicion';
    await guardar(paso.value);
  } else if (estado === 'archivado') {
    await archivarProyecto();
  } else if (estado === 'propuesta') {
    await aprobarProyecto();
  }
}

// Título de la cabecera en cada paso (titulo + destacado en el color del rol).
const pasos = [
  { num: 1, label: 'Básicos',   titulo: 'Datos',         destacado: 'básicos' },
  { num: 2, label: 'Empresa',   titulo: 'Datos de la',   destacado: 'empresa' },
  { num: 3, label: 'Currículo', titulo: 'Currículo y',   destacado: 'criterios' },
  { num: 4, label: 'El reto',   titulo: 'Definición del', destacado: 'reto' },
  { num: 5, label: 'Propuesta', titulo: 'Diseño de la',  destacado: 'propuesta' },
  { num: 6, label: 'Objetivos', titulo: 'Objetivos y',   destacado: 'KPIs' },
  { num: 7, label: 'Publicar',  titulo: 'Revisar y',     destacado: 'publicar' },
];
const pasoInfo = computed(() => pasos[paso.value - 1]);
const subtituloPaso = computed(() => ({
  1: uuid.value ? 'Revisa los datos de base de la propuesta.' : 'Elige el reto de la biblioteca al que responde esta propuesta y completa los datos de base.',
  2: empresaDesdeReto.value ? 'Información autocompletada desde el reto. Solo lectura.' : 'Confirma o completa la información de la empresa colaboradora.',
  3: 'Marca los módulos, resultados de aprendizaje y criterios de evaluación que se trabajan en esta propuesta.',
  4: 'Define el contexto, la fundamentación y el reto central de la propuesta.',
  5: 'Define las fases, la metodología y el cronograma del trabajo.',
  6: 'Define los objetivos de la propuesta y los indicadores de éxito.',
  7: 'Revisa la propuesta, añade materiales y decide cuándo enviarla a validar.',
})[paso.value]);
// Navegar a un paso ya alcanzado (sin reto vinculado solo se puede estar en el 1).
function puedeIrAPaso(num) { return num === 1 || !!form.value.microreto_id }

// ── Chips de cabecera y panel lateral (solo datos reales) ─────────────────────
const cursoTexto = computed(() => {
  const c = form.value.curso || microretoVinculado.value?.curso
  return c ? (c === 'ambos_cursos' ? '1º y 2º curso' : `${String(c).replace('º', '')}º curso`) : ''
})
const tipoEmpresa = computed(() => {
  const emp = empresas.value.find(e => e.id == form.value.empresa_id) || microretoVinculado.value?.empresa
  if (!emp || emp.es_simulada === undefined) return ''
  return emp.es_simulada ? 'ficticia' : 'real'
})
const estadoResumen = computed(() => {
  const e = form.value.estado
  if (e === 'propuesta') return proyectoValidado.value
    ? { label: 'Validada por la empresa', clase: 'bg-emerald-50 text-emerald-800' }
    : { label: 'Pendiente de validar', clase: 'bg-amber-50 text-amber-800' }
  if (e === 'validado')   return { label: 'Validado', clase: 'bg-emerald-50 text-emerald-800' }
  if (e === 'completado') return { label: 'Completado', clase: 'bg-sky-50 text-sky-800' }
  if (e === 'archivado')  return { label: 'Archivado', clase: 'bg-gray-100 text-gray-700' }
  return { label: 'Borrador', clase: 'bg-gray-100 text-gray-700' }
})
const portadaUrl = computed(() =>
  imagenesLocales.value.find(i => i.id === imagenPortadaId.value)?.url || imagenPortadaUrl.value || ''
)

// Lista con iconos del resumen (crear_proyecto.png): empresa, familia, curso, duración, nivel.
const datosResumen = computed(() => [
  { icono: 'empresa',    valor: retoEmpresaNombre.value, ancho: true },
  { icono: 'familia',    valor: retoFamiliaNombre.value, ancho: true },
  { icono: 'curso',      valor: [cursoTexto.value, retoCicloNombre.value].filter(Boolean).join(' · '), ancho: true },
  { icono: 'calendario', valor: microretoVinculado.value?.duracion ? String(microretoVinculado.value.duracion) : '' },
  { icono: 'nivel',      valor: microretoVinculado.value?.nivel_grupo ? `Nivel: ${microretoVinculado.value.nivel_grupo}` : '' },
].filter(d => d.valor))

// Recuadro «Antes de continuar» de la columna derecha: avisos primero; si no hay, el
// consejo del paso.
const CONSEJO_PASO = {
  1: 'Verifica que el reto y los datos del centro sean correctos. Podrás modificarlos más adelante.',
  2: 'Revisa el email de contacto: se usará para enviar a la empresa el enlace de validación.',
  3: 'Marca solo los criterios que de verdad se trabajan: aparecerán en la propuesta que verá la empresa.',
  4: 'Cuanto más concretos sean la pregunta reto y los entregables, más fácil será evaluar el resultado.',
  5: 'El resumen ejecutivo es lo primero que leerá la empresa al abrir el enlace de validación.',
  6: 'Añade al menos un objetivo. Los KPIs son opcionales, pero hacen la propuesta evaluable.',
  7: 'De momento es una propuesta: cuando la valide la empresa (o tú como docente), pasará a llamarse proyecto.',
}
const antesDeContinuar = computed(() => {
  if (proyectoValidado.value) return { tipo: 'aviso', titulo: 'Ya validado por la empresa',
    texto: 'Si modificas el contenido, la empresa tendrá que validarlo de nuevo.' }
  if (paso.value === 3 && sugerenciaIaPendienteRevision.value) return { tipo: 'aviso', titulo: 'Sugerencias de la IA sin revisar',
    texto: 'Revisa los RA/CE con la etiqueta «Sugerencia IA» y desmarca los que no encajen.' }
  if (paso.value === 6 && sugerenciasPendientes.value) return { tipo: 'aviso', titulo: 'Sugerencias de la IA sin revisar',
    texto: `Tienes ${sugerenciasPendientes.value} sugerencia(s) pendientes: añádelas o descártalas antes de seguir.` }
  return { tipo: 'info', titulo: 'Antes de continuar', texto: CONSEJO_PASO[paso.value] }
})

// Tarjeta «Sugerencias con IA» del panel lateral: lanza la IA del paso actual.
const iaPaso = computed(() => {
  switch (paso.value) {
    case 3: return { texto: 'Te ayudamos a seleccionar los RA y CE más adecuados para este reto.', boton: 'Sugerir RA y CE',
                     cargando: cargandoIaRaCe.value, deshabilitado: !form.value.modulos_seleccionados.length,
                     motivo: 'Marca al menos un módulo.' }
    case 4: return { texto: 'Te proponemos la justificación y la innovación a partir del reto y su currículo.', boton: 'Sugerir fundamentación',
                     cargando: sugiriendoFundamentacion.value }
    case 5: return { texto: 'Te proponemos la metodología, el cronograma y el resumen a partir de las fases.', boton: 'Sugerir metodología',
                     cargando: sugiriendoMetodologia.value }
    case 6: return { texto: 'Te ayudamos a definir objetivos medibles y KPIs adecuados. Revisa cada sugerencia antes de añadirla.', boton: 'Generar sugerencias',
                     cargando: sugiriendoObjetivos.value || sugirendoKpis.value }
    default: return null
  }
})
async function sugerirDesdePanel() {
  if (paso.value === 3) return sugerirRaCeConIa()
  if (paso.value === 4) return sugerirFundamentacion()
  if (paso.value === 5) return sugerirMetodologia()
  if (paso.value === 6) { await sugerirObjetivos(); await sugerirKpis() }
}

// ── Estado de guardado («Guardado» / «Guardando…» / «Sin guardar») ───────────
// Se guarda al pulsar Siguiente (no hay botón «Guardar borrador»): este indicador y el
// aviso al salir evitan perder cambios.
const sinGuardar         = ref(false)
const ultimaModificacion = ref(null)
const seguimientoActivo  = ref(false)
// También mientras se guarda: marcarGuardado() decide al terminar si ese cambio llegó a enviarse
watch(form, () => { if (seguimientoActivo.value) sinGuardar.value = true }, { deep: true })
const horaGuardado = computed(() => {
  if (!ultimaModificacion.value) return ''
  const d = new Date(ultimaModificacion.value)
  if (isNaN(d)) return ''
  const hoy = new Date().toDateString() === d.toDateString()
  return hoy
    ? d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
})
function avisoAntesDeCerrar(e) {
  if (!sinGuardar.value) return
  e.preventDefault()
  e.returnValue = ''
}
window.addEventListener('beforeunload', avisoAntesDeCerrar)
onUnmounted(() => window.removeEventListener('beforeunload', avisoAntesDeCerrar))
onBeforeRouteLeave((to) => {
  // El paso de /proyectos/crear a /proyectos/:uuid/editar tras el primer guardado no es salir.
  if (to.name === 'startup-day-editar' && to.params.uuid === uuid.value) return true
  if (sinGuardar.value && !window.confirm('Tienes cambios sin guardar en esta propuesta. Si sales ahora, se perderán. ¿Salir igualmente?')) return false
  return true
})

// ── Tour guiado ───────────────────────────────────────────────────────────────
const { tourActivo } = useUIState();
const modoGuia = ref(false);
const showTourPrompt = ref(false);
function activarTourDesdeModal() { showTourPrompt.value = false; modoGuia.value = true }
function omitirTourDesdeModal()  { showTourPrompt.value = false }

const guiaWizard = [
  {
    titulo: 'Paso 1 · Datos básicos',
    texto: 'Elige el reto de la biblioteca al que responde esta propuesta. Si ya tienes una sesión registrada, puedes vincularla para autocompletar empresa, centro y ciclo. La sesión es opcional: también puedes crear la propuesta directamente desde el reto. Tu centro educativo se autorrellena y queda bloqueado; completa además los datos del docente responsable, que verá la empresa al abrir el enlace de validación.',
  },
  {
    titulo: 'Paso 2 · Datos de la empresa',
    texto: 'Completa o corrige la ficha de la empresa colaboradora. Estos datos aparecerán en el dossier de la propuesta que verá la empresa. Revisa especialmente el email de contacto, que se usará para enviar el enlace de validación de la propuesta.',
  },
  {
    titulo: 'Paso 3 · Módulos y currículum',
    texto: 'Selecciona los módulos formativos del ciclo que se trabajan en esta propuesta. Si el reto vinculado ya tenía módulos asignados, aparecerán pre-seleccionados. Añade también los RA/CE (Resultados de Aprendizaje y Criterios de Evaluación) más relevantes para justificar la propuesta ante la programación oficial.',
  },
  {
    titulo: 'Paso 4 · El reto',
    texto: 'Define el núcleo de la propuesta: la fundamentación (contexto de partida, justificación pedagógica e innovación) y el diseño del reto (descripción de la problemática, pregunta reto en formato "¿Cómo podríamos…?", restricciones que condicionan la solución y los entregables que el grupo debe producir). Cuanto más concreto, más fácil será la evaluación final.',
  },
  {
    titulo: 'Paso 5 · Diseño de la propuesta',
    texto: 'Revisa las 5 fases del proyecto (las mismas que el grupo recorrerá en su workspace cuando la propuesta se convierta en proyecto) con su duración orientativa, describe la metodología que seguirá el grupo y esboza el cronograma con los hitos clave. Termina con un resumen ejecutivo de 3-4 líneas que la empresa verá al abrir el enlace de validación.',
  },
  {
    titulo: 'Paso 6 · Objetivos y KPIs',
    texto: 'Define los objetivos de aprendizaje de la propuesta (qué competencias desarrollará el alumnado) y los indicadores de éxito o KPIs (cómo medirá la empresa que el reto se ha resuelto correctamente). Los KPIs hacen la propuesta evaluable y aumentan el compromiso de la empresa con el resultado final.',
  },
  {
    titulo: 'Paso 7 · Publicar',
    texto: 'Revisa el resumen de la propuesta. Aquí también puedes adjuntar vídeos o documentos de presentación que la empresa verá al abrir el enlace de validación. La propuesta se guarda como borrador por defecto. Usa el desplegable "Estado del proyecto" → "Propuesta" para generar el enlace único y enviárselo a la empresa cuando estés listo. En cuanto se valide, pasará a llamarse proyecto.',
  },
];

// La guía solo se abre a petición (botón Ayuda), nunca sola al cambiar de paso.
watch(modoGuia, (val) => { tourActivo.value = val; });
onUnmounted(() => { tourActivo.value = false; });
</script>

<template>
  <div class="min-h-screen font-sans text-[#1F2937] pt-16 md:pt-16"
       :class="isLoaded ? 'opacity-100' : 'opacity-0 translate-y-3'"
       style="transition: opacity 0.4s ease, translate 0.4s ease">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-175 h-100
                bg-primary-400 opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <!-- ══ TOUR OVERLAY ══════════════════════════════════════════════════════ -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      leave-active-class="transition-all duration-150 ease-in"
      leave-to-class="opacity-0 scale-95">
      <div v-if="modoGuia" class="fixed inset-0 z-[9990] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/55 pointer-events-auto" />
        <div class="relative pointer-events-auto w-full max-w-md bg-[#1a2332] border border-white/15
                    rounded-3xl shadow-2xl p-7 text-white"
             @click.stop>
          <!-- Cabecera con progreso -->
          <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-2xl bg-rol/20 flex items-center justify-center
                        text-rol-fuerte font-bold text-base shrink-0">
              {{ paso }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold uppercase tracking-widest text-white/40 mb-1.5">
                Guía · Paso {{ paso }} de {{ totalPasos }}
              </p>
              <div class="flex gap-1">
                <span v-for="i in totalPasos" :key="i"
                      class="h-[3px] rounded-full transition-all duration-300"
                      :class="i <= paso ? 'bg-rol-fuerte w-5' : 'bg-white/20 w-3'" />
              </div>
            </div>
          </div>
          <!-- Título del paso -->
          <p class="text-xs font-bold uppercase tracking-widest text-rol-fuerte mb-2">
            {{ guiaWizard[paso - 1].titulo }}
          </p>
          <!-- Descripción -->
          <p class="text-[13px] text-white/85 leading-relaxed mb-6">
            {{ guiaWizard[paso - 1].texto }}
          </p>
          <!-- Botones -->
          <div class="flex items-center gap-2">
            <button @click="modoGuia = false"
                    class="flex-1 py-2.5 rounded-full bg-rol-fuerte text-white text-xs font-bold
                           uppercase tracking-widest hover:bg-rol/90 transition-all">
              Entendido ✓
            </button>
            <button @click="modoGuia = false"
                    class="px-3 py-2.5 rounded-full bg-white/5 border border-white/10 text-white/40
                           text-xs font-bold uppercase tracking-widest hover:text-white/60 transition-all">
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Contenido: columna principal + panel lateral (diseño crear_proyecto*.png) -->
    <div class="relative z-10 mx-auto max-w-[90rem] px-4 pb-10 pt-5 sm:px-6 lg:px-5 xl:px-8 2xl:px-10">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-5 xl:gap-8">
      <div class="min-w-0 flex-1 space-y-5">

      <!-- Cabecera: misma pieza que el resto de vistas; título según el paso -->
      <CabeceraSeccion plano :titulo="pasoInfo.titulo" :destacado="pasoInfo.destacado" color="text-rol-fuerte"
                       :antetitulo="`Taller de Ideas · Paso ${paso} de ${totalPasos}`"
                       :volver="{ name: 'startup-day' }" volver-label="Volver a proyectos" :subtitulo="subtituloPaso">
        <div class="flex flex-wrap items-center gap-3">
          <!-- Ayuda: mismo botón y sitio en todas las vistas -->
          <button type="button" @click="modoGuia = true" aria-label="Ayuda: guía de este paso"
                  class="accion-secundaria max-sm:w-10 max-sm:px-0" title="Guía de este paso">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="max-sm:sr-only">Ayuda</span>
          </button>
        </div>
      </CabeceraSeccion>

      <!-- Chips con datos reales + estado de guardado (diseño crear_proyecto.png) -->
      <div class="flex flex-wrap items-center gap-2">
        <span v-if="retoEmpresaNombre" class="chip-taller max-w-full">
          <svg class="h-4 w-4 shrink-0 text-empresas-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          <span class="truncate">{{ retoEmpresaNombre }}</span>
        </span>
        <span v-if="cursoTexto || retoCicloNombre" class="chip-taller max-w-full">
          <svg class="h-4 w-4 shrink-0 text-empresas-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span class="truncate">{{ [cursoTexto, retoCicloNombre].filter(Boolean).join(' · ') }}</span>
        </span>
        <span v-if="microretoVinculado?.duracion" class="chip-taller">
          <svg class="h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          {{ microretoVinculado.duracion }}
        </span>
        <span v-if="microretoVinculado?.nivel_grupo" class="chip-taller">
          <svg class="h-4 w-4 shrink-0 text-alumnos-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6m6 0h6m-6 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0h6m-6 0V5a2 2 0 012-2h2a2 2 0 012 2v14"/></svg>
          Nivel: {{ microretoVinculado.nivel_grupo }}
        </span>

        <!-- Estado de guardado (se guarda al pulsar Siguiente) -->
        <span class="inline-flex items-center gap-1.5 px-1 text-[13px]" role="status" aria-live="polite">
          <template v-if="guardando">
            <svg class="h-4 w-4 animate-spin text-gray-500" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
            <span class="text-gray-600">Guardando…</span>
          </template>
          <template v-else-if="sinGuardar">
            <span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true" />
            <span class="font-medium text-amber-800">Sin guardar · se guarda al pulsar Siguiente</span>
          </template>
          <template v-else-if="horaGuardado">
            <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-texto-secundario">Última modificación: {{ horaGuardado }}</span>
          </template>
        </span>
      </div>

      <TallerPasos :pasos="pasos" :actual="paso" :maximo="pasoMaxAlcanzado" :puede-ir="puedeIrAPaso"
                   @ir="paso = $event" />

      <!-- Aviso: proyecto validado por empresa -->
      <div v-if="proyectoValidado"
           class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 lg:hidden">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <p class="text-sm text-amber-800">
          Este proyecto ya ha sido validado por la empresa. Modificar el contenido requerirá una nueva validación.
        </p>
      </div>

      <!-- Error -->
      <div v-if="errorMsg"
           class="mb-4 bg-red-50 border border-red-200 rounded-2xl px-4 py-3 text-sm text-red-600 font-semibold">
        {{ errorMsg }}
      </div>

      <!-- Banner autocomplete -->
      <Transition enter-active-class="transition-all duration-300"
                  enter-from-class="opacity-0 -translate-y-2"
                  leave-active-class="transition-all duration-300"
                  leave-to-class="opacity-0 -translate-y-2">
        <div v-if="autocompletando"
             class="mb-4 flex items-center gap-3 bg-rol/8 border border-rol/25
                    rounded-2xl px-4 py-3">
          <svg class="w-4 h-4 text-rol-fuerte shrink-0 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          <p class="text-sm font-semibold text-rol-fuerte">
            Datos autocompletados desde el reto vinculado. Revisa y ajusta lo que necesites.
          </p>
        </div>
      </Transition>

      <!-- Spinner carga inicial -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
        <svg class="animate-spin w-10 h-10 text-rol-fuerte mb-3" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="text-rol-fuerte font-bold tracking-widest uppercase text-xs animate-pulse">Cargando...</p>
      </div>

      <template v-else>

        <!-- ═══ PASO 1: Básicos ═══ -->
        <div v-if="paso === 1">

            <!-- Reto elegido (diseño crear_encuentro.png) -->
            <TarjetaRetoAsociado v-if="microretoVinculado" class="mb-8"
                                 :titulo="microretoVinculado.titulo"
                                 :pregunta="microretoVinculado.pregunta_reto || ''"
                                 :empresa="retoEmpresaNombre"
                                 :tipo-empresa="tipoEmpresa"
                                 :estado="uuid ? estadoResumen : null"
                                 :familia="microretoVinculado.familia || ''"
                                 :curso="cursoLabel(microretoVinculado.curso) ? `${cursoLabel(microretoVinculado.curso)} curso` : ''"
                                 :ciclo="microretoVinculado.ciclo || ''"
                                 :puede-cambiar="!uuid"
                                 @cambiar="limpiarReto" />

            <!-- Selector de reto (creación, mientras no hay reto elegido) -->
            <div v-if="!uuid && !microretoVinculado"
                 class="bg-rol/[0.04] rounded-2xl border-2 border-rol/25 shadow-sm
                        p-6 space-y-4 mb-8">

              <!-- Header -->
              <div class="flex items-start justify-between gap-3 pb-3 border-b border-rol/15">
                <div class="flex items-start gap-3">
                  <div class="w-8 h-8 rounded-xl bg-rol/15 border border-rol/30
                              flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.346.346a.5.5 0 01-.354.146H9.88a.5.5 0 01-.354-.146l-.345-.346z"/>
                    </svg>
                  </div>
                  <div>
                    <p class="text-xs font-bold text-[#121212]">Reto <span class="text-red-500">*</span></p>
                    <p class="text-xs text-gray-400 mt-0.5">Selecciona el reto de la biblioteca al que responde esta propuesta.</p>
                  </div>
                </div>
              </div>

              <!-- Buscador + filtros + grid -->
              <div class="space-y-3">

                <!-- Buscador -->
                <div class="relative">
                  <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300 pointer-events-none"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                  </svg>
                  <input v-model="retoBusqueda" type="text"
                         placeholder="Buscar por título, empresa o pregunta del reto…"
                         class="field-input pl-10" />
                </div>

                <!-- Filtros -->
                <div class="flex flex-wrap gap-2">
                  <select v-model="retoFiltroFamilia"
                          class="text-xs font-medium px-3 py-1.5 rounded-xl border border-gray-200
                                 bg-white text-gray-600 hover:border-gray-300 transition-colors focus:outline-none
                                 focus:ring-1 focus:ring-rol/40">
                    <option value="">Familia</option>
                    <option v-for="f in familiasFiltroRetos" :key="f" :value="f">{{ f }}</option>
                  </select>
                  <select v-model="retoFiltroCiclo"
                          class="text-xs font-medium px-3 py-1.5 rounded-xl border border-gray-200
                                 bg-white text-gray-600 hover:border-gray-300 transition-colors focus:outline-none
                                 focus:ring-1 focus:ring-rol/40">
                    <option value="">Ciclo</option>
                    <option v-for="c in ciclosFiltroRetos" :key="c" :value="c">{{ c }}</option>
                  </select>
                  <div class="flex rounded-full border border-gray-200 overflow-hidden text-xs font-bold uppercase tracking-widest">
                    <button v-for="op in ['', '1º', '2º', 'ambos_cursos']" :key="op"
                            @click="retoFiltroCurso = op"
                            :title="op === 'ambos_cursos' ? 'Ambos Cursos: posibilidad 1º y 2º' : ''"
                            :class="['px-3 py-1.5 rounded-full transition-colors',
                                     retoFiltroCurso === op
                                       ? 'bg-rol-fuerte text-white'
                                       : 'bg-white text-gray-400 hover:bg-gray-50']">
                      {{ op === '' ? 'Todos' : op === 'ambos_cursos' ? 'Ambos Cursos' : op }}
                    </button>
                  </div>
                  <button v-if="retoBusqueda || retoFiltroFamilia || retoFiltroCiclo || retoFiltroCurso"
                          @click="retoBusqueda = ''; retoFiltroFamilia = ''; retoFiltroCiclo = ''; retoFiltroCurso = ''"
                          class="text-xs font-bold uppercase tracking-widest px-3 py-1.5 rounded-full
                                 border border-gray-200 text-gray-400 hover:border-red-200 hover:text-red-400
                                 transition-colors">
                    Limpiar
                  </button>
                </div>

                <!-- Desplegable de miniaturas -->
                <button type="button" @click="retosAbiertos = !retosAbiertos"
                        :aria-expanded="retosAbiertos"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-2xl
                               bg-white border border-gray-200 hover:border-rol/40
                               transition-colors text-left">
                  <span class="text-xs font-bold text-[#1F2937]">
                    {{ retosAbiertos ? 'Ocultar retos' : 'Ver retos de la biblioteca' }}
                    <span class="ml-1.5 text-xs font-bold px-1.5 py-0.5 rounded-full
                                 bg-rol/10 text-rol-fuerte border border-rol/20">
                      {{ retosFiltrados.length }}
                    </span>
                  </span>
                  <svg :class="['w-4 h-4 text-gray-400 transition-transform', retosAbiertos && 'rotate-180']"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>

                <template v-if="retosAbiertos">
                <!-- Sin resultados -->
                <p v-if="!retosFiltrados.length"
                   class="text-xs text-gray-400 font-medium text-center py-8">
                  Sin resultados para los filtros actuales.
                </p>

                <!-- Grid de miniaturas -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[26rem] overflow-y-auto pr-1 -mr-1">
                  <div v-for="mr in retosFiltrados" :key="mr.id"
                       class="relative rounded-2xl border border-gray-100 bg-white
                              hover:border-rol/40 hover:bg-rol/5 hover:shadow-sm
                              transition-all group">
                    <button @click="seleccionarReto(mr)"
                            class="text-left w-full px-4 pt-3.5 pb-10">
                      <div class="flex items-start justify-between gap-2 mb-1.5">
                        <p class="text-sm font-bold text-[#1F2937] leading-snug line-clamp-2
                                  group-hover:text-rol-fuerte transition-colors">
                          {{ mr.titulo }}
                        </p>
                        <span v-if="mr.curso"
                              class="shrink-0 text-xs font-bold uppercase px-1.5 py-0.5 rounded-full
                                     bg-primary-400/20 text-primary-700 border border-primary-400/30">
                          {{ cursoLabel(mr.curso) }}
                        </span>
                      </div>
                      <p v-if="mr.pregunta_reto"
                         class="text-xs text-gray-400 leading-relaxed line-clamp-2 mb-2">
                        {{ mr.pregunta_reto }}
                      </p>
                      <div class="flex flex-wrap gap-1">
                        <span v-if="mr.empresa?.nombre_comercial"
                              class="tag tag-gray">{{ mr.empresa.nombre_comercial }}</span>
                        <span v-if="mr.familia" class="tag tag-gray">{{ mr.familia }}</span>
                        <span v-if="mr.ciclo"   class="tag tag-gray text-xs">{{ mr.ciclo }}</span>
                      </div>
                    </button>
                    <button @click.stop="microretoModalId = mr.id"
                            class="absolute bottom-2.5 right-3 flex items-center gap-1
                                   text-xs font-bold uppercase tracking-widest
                                   text-gray-400 hover:text-rol-fuerte transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                 -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      Ver reto
                    </button>
                  </div>
                </div>
                </template>

              </div>
            </div>

            <!-- Resto de campos básicos -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
              <div class="pb-3 border-b border-gray-100">
                <p class="text-xs font-bold text-[#121212]">Datos de la propuesta</p>
                <p class="text-xs text-gray-400 mt-0.5">Completa la información básica de tu propuesta.</p>
              </div>
              <div>
                <label class="field-label">Título de la propuesta *</label>
                <input v-model="form.titulo" type="text" required class="field-input"
                       placeholder="Ej: Rediseño de packaging sostenible para EcoFab" />
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <!-- Empresa: read-only del reto -->
                <div>
                  <label class="field-label flex items-center gap-1.5">
                    Empresa colaboradora
                    <span v-if="microretoVinculado"
                          class="text-xs font-bold uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-rol/10 text-rol-fuerte border border-rol/20">
                      Del reto
                    </span>
                  </label>
                  <div v-if="microretoVinculado"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-50
                              border border-gray-100 text-sm font-medium text-[#1F2937]">
                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    {{ retoEmpresaNombre || '—' }}
                  </div>
                  <select v-else v-model="form.empresa_id" class="field-input">
                    <option value="">— Seleccionar empresa —</option>
                    <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_comercial }}</option>
                  </select>
                </div>

                <!-- Centro: bloqueado cuando se autorrellena con el centro del docente logueado -->
                <div>
                  <label class="field-label flex items-center gap-1.5">
                    Centro educativo
                    <span v-if="centroBloqueado"
                          class="text-xs font-bold uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-rol/10 text-rol-fuerte border border-rol/20">
                      Tu centro
                    </span>
                  </label>
                  <div v-if="centroBloqueado"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-50
                              border border-gray-100 text-sm font-medium text-[#1F2937]">
                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    {{ centros.find(c => c.id == form.centro_id)?.nombre || authStore.userCentroNombre || '—' }}
                  </div>
                  <select v-else v-model="form.centro_id" class="field-input">
                    <option value="">— Seleccionar centro —</option>
                    <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                  </select>
                </div>

                <!-- Familia: read-only del reto -->
                <div>
                  <label class="field-label flex items-center gap-1.5">
                    Familia profesional
                    <span v-if="microretoVinculado"
                          class="text-xs font-bold uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-rol/10 text-rol-fuerte border border-rol/20">
                      Del reto
                    </span>
                  </label>
                  <div v-if="microretoVinculado"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-50
                              border border-gray-100 text-sm font-medium text-[#1F2937]">
                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    {{ retoFamiliaNombre || '—' }}
                  </div>
                  <select v-else v-model="form.familia_id" class="field-input">
                    <option value="">— Seleccionar familia —</option>
                    <option v-for="f in familias" :key="f.id" :value="f.id">{{ f.nombre }}</option>
                  </select>
                </div>

                <!-- Ciclo: read-only del reto -->
                <div>
                  <label class="field-label flex items-center gap-1.5">
                    Ciclo formativo
                    <span v-if="microretoVinculado"
                          class="text-xs font-bold uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-rol/10 text-rol-fuerte border border-rol/20">
                      Del reto
                    </span>
                  </label>
                  <div v-if="microretoVinculado"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-50
                              border border-gray-100 text-sm font-medium text-[#1F2937]">
                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    {{ retoCicloNombre || '—' }}
                  </div>
                  <select v-else v-model="form.ciclo_id" class="field-input" :disabled="!ciclos.length">
                    <option value="">— Seleccionar ciclo —</option>
                    <option v-for="c in ciclos" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                  </select>
                </div>

                <!-- Curso: read-only del reto -->
                <div>
                  <label class="field-label flex items-center gap-1.5">
                    Curso
                    <span v-if="microretoVinculado"
                          class="text-xs font-bold uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-rol/10 text-rol-fuerte border border-rol/20">
                      Del reto
                    </span>
                  </label>
                  <div v-if="microretoVinculado"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-50
                              border border-gray-100 text-sm font-medium text-[#1F2937]">
                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    {{ form.curso || '—' }}
                  </div>
                  <select v-else v-model="form.curso" class="field-input">
                    <option value="">— Curso —</option>
                    <option>1º</option><option>2º</option>
                  </select>
                </div>

                <!-- Datos de Docente Responsable -->
                <div class="sm:col-span-2 pt-3 mt-1 border-t border-gray-100">
                  <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Datos de Docente Responsable</p>
                </div>
                <div>
                  <label class="field-label">Docente responsable</label>
                  <input v-model="form.datos_centro.docente_nombre" type="text" class="field-input" />
                </div>
                <div>
                  <label class="field-label">Email docente</label>
                  <input v-model="form.datos_centro.docente_email" type="email" class="field-input" />
                </div>

              </div>
            </div>

            <!-- Aviso: composición del equipo de alumnado -->
            <div class="bg-[#F8FAFC] rounded-3xl border border-blue-100/60 px-5 py-4 mt-4 flex items-start gap-3">
              <svg class="w-4 h-4 text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <p class="text-xs text-gray-500 leading-relaxed">
                El número de grupos y el alumnado se configura en el
                <strong class="text-[#1F2937]">registro de sesión</strong>
                del dashboard. Desde
                <strong class="text-[#1F2937]">Sesiones registradas</strong>
                podrás generar el código de acceso una vez la propuesta sea validada y se convierta en proyecto.
              </p>
            </div>

            <div class="fila-pasos mt-5">
              <button @click="guardar(2)"
                      :disabled="!form.titulo.trim() || !form.microreto_id || guardando"
                      class="btn-paso">
                {{ guardando ? 'Guardando…' : 'Siguiente →' }}
              </button>
            </div>
        </div>

        <!-- ═══ PASO 2: Empresa ═══ -->
        <div v-if="paso === 2">

          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">

            <!-- Cabecera "Del reto" cuando es read-only -->
            <div v-if="empresaDesdeReto"
                 class="flex items-center gap-2 px-3 py-2 rounded-xl bg-rol/8
                        border border-rol/20 text-xs font-bold text-rol-fuerte">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              Datos heredados del reto — no editables
            </div>

            <!-- Read-only (empresa del reto) -->
            <template v-if="empresaDesdeReto">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-for="[label, val] in [
                  ['Nombre / Razón social', form.datos_empresa.nombre],
                  ['CIF',                  form.datos_empresa.cif],
                  ['Sector',               form.datos_empresa.sector],
                  ['Actividad principal',  form.datos_empresa.actividad],
                  ['Persona de contacto',  form.datos_empresa.persona_contacto],
                  ['Email de contacto',    form.datos_empresa.email],
                  ['Teléfono',             form.datos_empresa.telefono],
                  ['Web',                  form.datos_empresa.web],
                ]" :key="label">
                  <div>
                    <p class="field-label">{{ label }}</p>
                    <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-50
                                border border-gray-100 text-sm text-[#1F2937] min-h-[2.5rem]">
                      <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                      </svg>
                      <span class="min-w-0 truncate">{{ val || '—' }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="form.datos_empresa.descripcion">
                <p class="field-label">Descripción breve</p>
                <div class="px-3 py-2.5 rounded-xl bg-gray-50 border border-gray-100
                            text-sm text-[#1F2937] leading-relaxed">
                  {{ form.datos_empresa.descripcion }}
                </div>
              </div>
            </template>

            <!-- Editable (sin reto o empresa diferente) -->
            <template v-else>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="field-label">Nombre / Razón social</label><input v-model="form.datos_empresa.nombre" type="text" class="field-input" /></div>
                <div><label class="field-label">CIF</label><input v-model="form.datos_empresa.cif" type="text" class="field-input" /></div>
                <div><label class="field-label">Sector</label><input v-model="form.datos_empresa.sector" type="text" class="field-input" /></div>
                <div><label class="field-label">Actividad principal</label><input v-model="form.datos_empresa.actividad" type="text" class="field-input" /></div>
                <div><label class="field-label">Persona de contacto</label><input v-model="form.datos_empresa.persona_contacto" type="text" class="field-input" /></div>
                <div><label class="field-label">Email de contacto</label><input v-model="form.datos_empresa.email" type="email" class="field-input" /></div>
                <div><label class="field-label">Teléfono</label><input v-model="form.datos_empresa.telefono" type="tel" class="field-input" /></div>
                <div><label class="field-label">Web</label><input v-model="form.datos_empresa.web" type="url" placeholder="https://" class="field-input" /></div>
              </div>
              <div>
                <label class="field-label">Descripción breve</label>
                <textarea v-model="form.datos_empresa.descripcion" rows="3" class="field-input resize-none"
                          placeholder="Qué hace la empresa, cuál es su propuesta de valor…" />
              </div>
            </template>

          </div>

          <div class="fila-pasos mt-5">
            <button @click="paso = 1" class="btn-paso-atras">← Anterior</button>
            <button @click="guardar(3)" :disabled="guardando" class="btn-paso">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 3: Módulos y RA/CE ═══ -->
        <div v-if="paso === 3">
          <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6">

            <!-- Cabecera de la tarjeta -->
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div class="min-w-0">
                <h2 class="text-lg font-bold text-azul-noche">Resultados de aprendizaje y criterios de evaluación</h2>
                <p class="mt-0.5 text-sm text-texto-secundario" aria-live="polite">
                  <template v-if="totalRaSeleccionados">
                    <strong class="font-semibold text-azul-noche">{{ totalCeSeleccionados }} CE</strong> en
                    <strong class="font-semibold text-azul-noche">{{ totalRaSeleccionados }} RA</strong> seleccionados
                    · {{ form.modulos_seleccionados.length }} módulo(s)
                  </template>
                  <template v-else>Marca un módulo y elige sus criterios de evaluación.</template>
                </p>
              </div>
              <button type="button" @click="sugerirRaCeConIa"
                      :disabled="cargandoIaRaCe || !form.modulos_seleccionados.length"
                      :title="form.modulos_seleccionados.length ? 'La IA propone RA y CE del catálogo oficial' : 'Marca al menos un módulo'"
                      class="inline-flex h-10 shrink-0 items-center justify-center gap-2 self-start rounded-full border border-violet-200 bg-violet-50 px-4 text-sm font-semibold text-violet-800 transition-colors hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-50">
                <svg v-if="cargandoIaRaCe" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                {{ cargandoIaRaCe ? 'Analizando currículo…' : 'Sugerir con IA' }}
              </button>
            </div>

            <div class="space-y-3">
              <!-- Aviso: selección autocompletada desde el reto -->
              <div v-if="raCeAutocompletado || modulosAutocompletados"
                   class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="min-w-0 flex-1 text-sm text-amber-900">
                  Los módulos, RA y CE vienen del reto vinculado. Revisa que estén alineados con la programación del ciclo:
                  puedes ajustar la selección o pedir una propuesta a la IA.
                </p>
                <button type="button" @click="raCeAutocompletado = false; modulosAutocompletados = false"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-amber-700 hover:bg-amber-100"
                        aria-label="Cerrar aviso">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>

              <!-- Aviso: la IA ha añadido RA/CE -->
              <div v-if="sugerenciaIaPendienteRevision"
                   class="flex flex-col gap-3 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 sm:flex-row sm:items-center">
                <p class="min-w-0 flex-1 text-sm text-violet-900">
                  La IA ha marcado nuevos RA/CE con la etiqueta <strong class="font-semibold">Sugerencia IA</strong>.
                  Revísalos y desmarca los que no encajen.
                </p>
                <button type="button" @click="marcarSugerenciasRevisadas"
                        class="inline-flex h-9 shrink-0 items-center self-start rounded-full bg-violet-700 px-4 text-sm font-semibold text-white hover:bg-violet-800 sm:self-auto">
                  Ya los he revisado
                </button>
              </div>

              <!-- RA y CE originales del reto = selección de la propuesta (parte de ellos) -->
              <div class="rounded-xl border border-rol/20 bg-rol/[0.03] p-3 sm:p-4">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                  <div class="min-w-0">
                    <h3 class="text-base font-bold text-azul-noche">RA y CE originales del reto</h3>
                    <p class="mt-0.5 text-sm text-texto-secundario">
                      <template v-if="raCeDivergeDelReto">Has cambiado la selección del reto: en la propuesta cuentan los de esta lista.</template>
                      <template v-else-if="raCeDelReto.length">Son los que propuso el reto. Quita los que no se trabajen o añade otros.</template>
                      <template v-else>Añade los RA y CE que se trabajan en esta propuesta.</template>
                    </p>
                  </div>
                  <button v-if="raCeDivergeDelReto && raCeDelReto.length" type="button" @click="restaurarRaCeDelReto"
                          class="inline-flex h-9 shrink-0 items-center gap-1.5 self-start rounded-full px-3 text-sm font-semibold text-gray-600 hover:bg-white hover:text-rol-fuerte">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    Restaurar los del reto
                  </button>
                </div>

                <TallerSeleccionRaCe v-if="form.evaluacion_oficial.length"
                                     :evaluacion="form.evaluacion_oficial"
                                     :ce-ids-reto="ceIdsDelReto"
                                     :textos-reto="textosDelReto"
                                     :ce-ids-ia="ceIdsIa"
                                     :textos-ia="textosIa"
                                     @quitar-entrada="i => { quitarEntradaRaCe(i); cargarCatalogoRaCe() }"
                                     @quitar-ce="(i, j) => { quitarCeDeEntrada(i, j); cargarCatalogoRaCe() }" />
                <p v-else class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-600">
                  <template v-if="microretoVinculado && !raCeDelReto.length">
                    El reto no tiene RA/CE oficiales asignados (probablemente su módulo aún no tiene el currículo del BOE cargado).
                    Añádelos desde el catálogo o a mano.
                  </template>
                  <template v-else>Todavía no hay RA ni CE en esta propuesta.</template>
                </p>

                <!-- Ampliar la selección: en modal (la lista del catálogo es muy larga) -->
                <div class="mt-4 flex flex-wrap gap-2 border-t border-rol/15 pt-4">
                  <button type="button" @click="abrirModalRaCe('catalogo')"
                          class="inline-flex h-10 items-center gap-2 rounded-full bg-rol-fuerte px-4 text-sm font-semibold text-white shadow-sm hover:bg-rol-fuerte/90">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Seleccionar otros
                  </button>
                  <button type="button" @click="abrirModalRaCe('manual')"
                          class="inline-flex h-10 items-center gap-2 rounded-full border border-rol-fuerte/30 bg-white px-4 text-sm font-semibold text-rol-fuerte hover:bg-rol-fuerte/5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Agregar manualmente
                  </button>
                </div>
              </div>
            </div>
          </section>

          <!-- Modal «Seleccionar otros»: catálogo oficial con los ya elegidos marcados -->
          <ModalTaller :abierto="modalRaCe === 'catalogo'" titulo="Seleccionar otros RA y CE"
                       subtitulo="Los que ya tiene la propuesta aparecen marcados. Lo que marques se aplica al momento."
                       @cerrar="modalRaCe = ''">
            <template #barra>
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <label for="buscar-ra-ce-modal" class="sr-only">Buscar resultado de aprendizaje o criterio</label>
                <div class="relative min-w-0 flex-1">
                  <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                  <input id="buscar-ra-ce-modal" v-model="busquedaRaCe" type="search" autofocus
                         placeholder="Buscar resultado de aprendizaje o criterio…"
                         class="w-full rounded-xl py-2 pl-9 pr-3 text-sm" />
                </div>
                <div class="flex items-center gap-1.5">
                  <button type="button" @click="arbolModal?.plegarTodo()"
                          class="inline-flex h-9 items-center gap-1 rounded-full border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    Plegar todo
                  </button>
                  <button type="button" @click="arbolModal?.desplegarTodo()"
                          class="inline-flex h-9 items-center gap-1 rounded-full border border-gray-200 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    Desplegar
                  </button>
                </div>
              </div>
            </template>

            <TallerArbolRaCe ref="arbolModal"
              :modulos="modulos"
              :seleccionados="form.modulos_seleccionados.map(m => m.id)"
              :catalogo="catalogoFiltrado"
              :ra-ids-catalogo="raIdsCatalogo"
              :cargando="cargandoCatalogo"
              :ce-checked="ceChecked"
              :evaluacion="form.evaluacion_oficial"
              :ce-ids-reto="ceIdsDelReto"
              :ce-ids-ia="ceIdsIa"
              :textos-ia="textosIa"
              :mostrar-extras="false"
              :con-buscador="false"
              @toggle-modulo="toggleModuloArbol"
              @toggle-ra="toggleRa"
              @toggle-ce="toggleCe" />

            <template #pie>
              <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-texto-secundario" aria-live="polite">
                  <strong class="font-semibold text-azul-noche">{{ totalCeSeleccionados }} CE</strong> en
                  <strong class="font-semibold text-azul-noche">{{ totalRaSeleccionados }} RA</strong> en la propuesta
                </p>
                <button type="button" @click="modalRaCe = ''"
                        class="inline-flex h-10 items-center rounded-full bg-rol-fuerte px-6 text-sm font-semibold text-white hover:bg-rol-fuerte/90">
                  Listo
                </button>
              </div>
            </template>
          </ModalTaller>

          <!-- Modal «Agregar manualmente»: RA/CE que no están en el catálogo -->
          <ModalTaller :abierto="modalRaCe === 'manual'" titulo="Agregar RA y CE manualmente"
                       subtitulo="Para resultados de aprendizaje y criterios que no están en el catálogo oficial del módulo."
                       ancho="w-[min(40rem,calc(100vw-2rem))]" @cerrar="modalRaCe = ''">
            <div class="space-y-3">
                                    <p v-if="!form.modulos_seleccionados.length" class="text-sm text-gray-700">
                    Marca antes un módulo en «Seleccionar otros».
                  </p>
                  <template v-else>
                    <div>
                      <label for="ra-manual-modulo" class="field-label">Módulo</label>
                      <select id="ra-manual-modulo" v-model="nuevoRaManual.moduloId" class="field-input" autofocus>
                        <option value="" disabled>Selecciona un módulo…</option>
                        <option v-for="m in form.modulos_seleccionados" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                      </select>
                    </div>
                    <div>
                      <label for="ra-manual-texto" class="field-label">Resultado de aprendizaje</label>
                      <input id="ra-manual-texto" v-model="nuevoRaManual.ra" type="text"
                             placeholder="Describe el resultado de aprendizaje…" class="field-input" />
                    </div>
                    <fieldset>
                      <legend class="field-label">Criterios de evaluación</legend>
                      <div class="space-y-2">
                        <div v-for="(ce, i) in nuevoRaManual.ces" :key="i" class="flex items-center gap-2">
                          <input v-model="nuevoRaManual.ces[i]" type="text" :placeholder="`Criterio ${i + 1}…`"
                                 :aria-label="`Criterio ${i + 1}`" class="field-input flex-1" />
                          <button v-if="nuevoRaManual.ces.length > 1" type="button" @click="removeCeManualField(i)"
                                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-red-50 hover:text-red-600"
                                  :aria-label="`Quitar criterio ${i + 1}`">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                          </button>
                        </div>
                      </div>
                      <button type="button" @click="addCeManualField"
                              class="mt-2 inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-rol-fuerte hover:bg-rol/10">
                        + Añadir otro criterio
                      </button>
                    </fieldset>
                    <div class="flex justify-end">
                      <button type="button" @click="anadirRaManualAlProyecto" :disabled="!raManualValido"
                              class="inline-flex h-10 items-center rounded-full bg-rol-fuerte px-5 text-sm font-semibold text-white hover:bg-rol-fuerte/90 disabled:cursor-not-allowed disabled:opacity-50">
                        Añadir a esta propuesta
                      </button>
                    </div>
                  </template>
            </div>
          </ModalTaller>

          <div class="fila-pasos mt-5">
            <button @click="paso = 2" class="btn-paso-atras">← Anterior</button>
            <button @click="guardar(4)" :disabled="guardando" class="btn-paso">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 4: El Reto ═══ -->
        <div v-if="paso === 4">

          <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Fundamentación</p>
                <button @click="sugerirFundamentacion" :disabled="sugiriendoFundamentacion"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full
                               bg-violet-50 border border-violet-200 text-violet-700
                               text-xs font-bold uppercase tracking-wider
                               hover:bg-violet-100 transition-all active:scale-95
                               disabled:opacity-60 disabled:cursor-not-allowed">
                  <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': sugiriendoFundamentacion }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M17.657 18.364l-.707-.707M12 20v1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l-.707-.707"/>
                  </svg>
                  {{ sugiriendoFundamentacion ? 'Generando…' : 'Sugerir con IA' }}
                </button>
              </div>
              <p v-if="errorFundamentacion" class="text-xs text-red-500">{{ errorFundamentacion }}</p>
              <div><label class="field-label">Contexto de la propuesta</label>
                <textarea v-model="form.fundamentacion.contexto" rows="3" class="field-input resize-none"
                          placeholder="¿Cuál es la situación de partida? ¿Qué problema o necesidad existe?" /></div>
              <div><label class="field-label">Justificación pedagógica</label>
                <textarea v-model="form.fundamentacion.justificacion" rows="3" class="field-input resize-none"
                          placeholder="¿Por qué este reto es relevante para el aprendizaje del alumnado?" /></div>
              <div><label class="field-label">Elemento innovador</label>
                <textarea v-model="form.fundamentacion.innovacion" rows="2" class="field-input resize-none"
                          placeholder="¿Qué tiene de innovador esta propuesta?" /></div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Diseño del reto</p>
              <div><label class="field-label">Descripción del reto *</label>
                <textarea v-model="form.diseno_reto.descripcion" rows="3" class="field-input resize-none" required
                          placeholder="Describe el reto al que se enfrenta el grupo…" /></div>
              <div><label class="field-label">Pregunta reto</label>
                <input v-model="form.diseno_reto.pregunta_reto" type="text" class="field-input"
                       placeholder="¿Cómo podríamos…?" /></div>
              <div><label class="field-label">Restricciones y condicionantes</label>
                <textarea v-model="form.diseno_reto.restricciones" rows="2" class="field-input resize-none"
                          placeholder="Presupuesto, plazos, materiales disponibles…" /></div>
              <div><label class="field-label">Entregables esperados</label>
                <textarea v-model="form.diseno_reto.entregables" rows="2" class="field-input resize-none"
                          placeholder="¿Qué debe entregar el grupo al final de la propuesta?" /></div>
            </div>
          </div>

          <div class="fila-pasos mt-5">
            <button @click="paso = 3" class="btn-paso-atras">← Anterior</button>
            <button @click="guardar(5)" :disabled="guardando" class="btn-paso">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 5: Diseño de la propuesta ═══ -->
        <div v-if="paso === 5">

          <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Fases de la propuesta</p>
                <p class="text-xs text-gray-400 mt-1">Son las mismas 5 fases que el grupo recorrerá en su workspace. La duración de cada una se calcula sola según el calendario de sesiones de abajo.</p>
              </div>
              <div class="space-y-2">
                <div v-for="f in FASES_PROYECTO" :key="f.num"
                     class="rounded-2xl border p-3 sm:p-4 flex items-center gap-3 sm:gap-4" :class="colorMapFases[f.color]">
                  <span class="text-lg leading-none shrink-0">{{ f.icono }}</span>
                  <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-[#1F2937]">F{{ f.num }} - {{ f.label }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ f.descLarga }}</p>
                  </div>
                  <span class="text-xs font-bold uppercase tracking-wide opacity-80 shrink-0 whitespace-nowrap">
                    {{ duracionPorFase(form.diseno_microproyecto.clases, f.num) }} sesión(es)
                  </span>
                </div>
              </div>

              <div class="pt-2 border-t border-gray-100">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Modificar duración de cada fase</p>
                <p class="text-xs text-gray-400 mt-1 mb-3">Marca qué fase(s) se trabajan en cada sesión — una misma sesión puede cubrir varias fases.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div v-for="(c, i) in form.diseno_microproyecto.clases" :key="i"
                       class="rounded-2xl border border-gray-200 bg-white p-4 space-y-3">
                    <div class="flex items-center justify-between">
                      <p class="text-sm font-bold text-[#1F2937]">Sesión {{ i + 1 }}</p>
                      <button @click="removeSesion(i)" type="button"
                              class="text-gray-300 hover:text-red-500 font-bold text-sm leading-none">×</button>
                    </div>
                    <div class="space-y-1.5">
                      <button v-for="f in FASES_PROYECTO" :key="f.num" type="button"
                              @click="toggleFaseEnSesion(i, f.num)"
                              class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2 border-2 transition-all text-left"
                              :class="c.fases.includes(f.num)
                                ? colorMapFases[f.color] + ' border-current shadow-sm'
                                : 'border-transparent bg-gray-50 text-gray-300 hover:bg-gray-100'">
                        <span class="text-lg leading-none shrink-0">{{ f.icono }}</span>
                        <span class="text-xs font-bold flex-1" :class="c.fases.includes(f.num) ? 'text-[#1F2937]' : 'text-gray-400'">
                          F{{ f.num }} - {{ f.label }}
                        </span>
                        <span v-if="c.fases.includes(f.num)" class="text-rol-fuerte font-bold text-sm shrink-0">✓</span>
                      </button>
                    </div>
                  </div>
                </div>
                <button @click="addSesion" type="button"
                        class="mt-3 px-4 py-2 rounded-full border border-dashed border-rol/40 text-rol-fuerte
                               text-xs font-bold uppercase tracking-widest hover:bg-rol/5 transition-all">
                  + Añadir sesión
                </button>
                <p class="text-xs font-bold text-[#1F2937] mt-3">Total: {{ form.diseno_microproyecto.clases.length }} sesión(es)</p>
              </div>
            </div>

            <!-- Cronograma general — hitos derivados de las fases + el calendario de arriba -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Cronograma general</p>
                <p class="text-xs text-gray-400 mt-1">Hito a conseguir en cada fase, según el calendario de sesiones de arriba.</p>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div v-for="f in FASES_PROYECTO" :key="f.num"
                     class="rounded-2xl border p-3 space-y-1" :class="colorMapFases[f.color]">
                  <div class="flex items-center gap-1.5">
                    <span class="text-base leading-none">{{ f.icono }}</span>
                    <p class="font-bold text-xs text-[#1F2937]">{{ f.label }}</p>
                  </div>
                  <p class="text-xs font-bold uppercase tracking-wide opacity-70">
                    {{ duracionPorFase(form.diseno_microproyecto.clases, f.num) }} sesión(es)
                  </p>
                  <p class="text-xs text-gray-600 leading-snug">🎯 {{ f.desc }}</p>
                </div>
              </div>
              <div class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3">
                <p class="text-xs text-amber-800">
                  📅 Las fechas reales de este cronograma (inicio y fin) se establecen al crear la sesión de clase que va a trabajar esta propuesta.
                </p>
              </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Metodología y resumen ejecutivo</p>
                <button @click="sugerirMetodologia" :disabled="sugiriendoMetodologia"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full
                               bg-violet-50 border border-violet-200 text-violet-700
                               text-xs font-bold uppercase tracking-wider
                               hover:bg-violet-100 transition-all active:scale-95
                               disabled:opacity-60 disabled:cursor-not-allowed">
                  <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': sugiriendoMetodologia }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M17.657 18.364l-.707-.707M12 20v1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l-.707-.707"/>
                  </svg>
                  {{ sugiriendoMetodologia ? 'Generando…' : 'Sugerir con IA' }}
                </button>
              </div>
              <p v-if="errorMetodologia" class="text-xs text-red-500">{{ errorMetodologia }}</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="field-label">Metodología</label>
                  <textarea v-model="form.diseno_microproyecto.metodologia" rows="6" class="field-input resize-none"
                            placeholder="Describe cómo se organizará el trabajo del grupo…" />
                </div>
                <div>
                  <label class="field-label">Resumen ejecutivo</label>
                  <textarea v-model="form.resumen.texto" rows="6" class="field-input resize-none"
                            placeholder="Síntesis de la propuesta para implementar...." />
                </div>
              </div>
            </div>
          </div>

          <div class="fila-pasos mt-5">
            <button @click="paso = 4" class="btn-paso-atras">← Anterior</button>
            <button @click="guardar(6)" :disabled="guardando" class="btn-paso">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 6: Objetivos y KPIs ═══ -->
        <div v-if="paso === 6">
          <div class="space-y-4">

            <!-- Objetivos del proyecto -->
            <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-objetivos">
              <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="flex min-w-0 flex-1 items-start gap-3 sm:gap-4">
                  <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte sm:h-12 sm:w-12" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><circle cx="12" cy="12" r="5" stroke-width="2"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg>
                  </span>
                  <div class="min-w-0">
                    <h2 id="titulo-objetivos" class="text-lg font-bold text-azul-noche">Objetivos del proyecto</h2>
                    <p class="mt-0.5 text-sm text-texto-secundario">Define qué se quiere conseguir con esta propuesta. Puedes añadir varios objetivos.</p>
                  </div>
                </div>
                <button type="button" @click="sugerirObjetivos" :disabled="sugiriendoObjetivos"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 self-start rounded-full border border-violet-200 bg-violet-50 px-4 text-sm font-semibold text-violet-800 transition-colors hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60">
                  <svg class="h-4 w-4" :class="{ 'animate-spin': sugiriendoObjetivos }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                  {{ sugiriendoObjetivos ? 'Generando…' : 'Sugerir con IA' }}
                </button>
              </div>

              <p class="mb-4 flex items-start gap-2 rounded-xl bg-rol/5 px-3 py-2 text-sm text-azul-noche">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Los objetivos deben estar alineados con los resultados de aprendizaje y con las necesidades de la empresa.
              </p>
              <p v-if="errorObjetivos" class="mb-3 text-sm text-red-700" role="alert">{{ errorObjetivos }}</p>

              <ListaEditableOrdenable v-model="form.objetivos.lista" nombre="objetivo"
                                      placeholder="Describe un objetivo…"
                                      vacio="Añade al menos un objetivo."
                                      :sugerencias="objetivosSugeridos"
                                      @aceptar="i => aceptarSugerencia(form.objetivos.lista, objetivosSugeridos, i)"
                                      @descartar="i => objetivosSugeridos.splice(i, 1)"
                                      @aceptar-todas="aceptarTodas(form.objetivos.lista, objetivosSugeridos)" />
            </section>

            <!-- Indicadores de éxito -->
            <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="titulo-kpis">
              <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="flex min-w-0 flex-1 items-start gap-3 sm:gap-4">
                  <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 sm:h-12 sm:w-12" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6m6 0h6m-6 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0h6m-6 0V5a2 2 0 012-2h2a2 2 0 012 2v14"/></svg>
                  </span>
                  <div class="min-w-0">
                    <h2 id="titulo-kpis" class="text-lg font-bold text-azul-noche">
                      Indicadores de éxito (KPIs)
                      <span class="ml-1 align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">Opcional</span>
                    </h2>
                    <p class="mt-0.5 text-sm text-texto-secundario">Define cómo se medirá el cumplimiento de los objetivos. Puedes añadir varios indicadores.</p>
                  </div>
                </div>
                <button type="button" @click="sugerirKpis" :disabled="sugirendoKpis"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 self-start rounded-full border border-violet-200 bg-violet-50 px-4 text-sm font-semibold text-violet-800 transition-colors hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60">
                  <svg class="h-4 w-4" :class="{ 'animate-spin': sugirendoKpis }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                  {{ sugirendoKpis ? 'Generando…' : 'Sugerir con IA' }}
                </button>
              </div>

              <p class="mb-4 flex items-start gap-2 rounded-xl bg-rol/5 px-3 py-2 text-sm text-azul-noche">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Los KPIs son opcionales, pero recomendados: hacen la propuesta evaluable para la empresa.
              </p>
              <p v-if="errorKpis" class="mb-3 text-sm text-red-700" role="alert">{{ errorKpis }}</p>

              <ListaEditableOrdenable v-model="form.kpis.lista" nombre="KPI"
                                      placeholder="Describe un indicador…"
                                      :sugerencias="kpisSugeridos"
                                      @aceptar="i => aceptarSugerencia(form.kpis.lista, kpisSugeridos, i)"
                                      @descartar="i => kpisSugeridos.splice(i, 1)"
                                      @aceptar-todas="aceptarTodas(form.kpis.lista, kpisSugeridos)" />
            </section>

            <p v-if="avisoPaso6" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
              {{ avisoPaso6 }}
            </p>
          </div>

          <div class="fila-pasos mt-5">
            <button @click="paso = 5" class="btn-paso-atras">← Anterior</button>
            <button @click="siguienteDesdePaso6" :disabled="guardando" class="btn-paso">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 7: Publicar ═══ -->
        <div v-if="paso === 7">

          <!-- ── Estado de éxito tras publicar ── -->
          <div v-if="publicadoExito" class="flex flex-col items-center text-center py-8 gap-6">
            <div class="w-20 h-20 rounded-full bg-rol/10 border-2 border-rol/20 flex items-center justify-center">
              <svg class="w-10 h-10 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            </div>
            <div>
              <h2 class="text-2xl font-bold text-azul-noche">¡Propuesta lista!</h2>
              <p class="text-gray-500 text-sm mt-1.5 max-w-sm mx-auto">
                El enlace de validación ya está listo. Puedes enviárselo a la empresa por correo para que valide la propuesta.
                Cuando la valide, pasará a llamarse <strong>proyecto</strong>.
              </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full max-w-sm">
              <button v-if="form.empresa_id"
                      @click="modalPropuestaAviso = true"
                      class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 bg-amber-500 text-white
                             rounded-full text-xs font-bold uppercase tracking-widest shadow-sm
                             hover:bg-amber-400 transition-all active:scale-95">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Enviar enlace a empresa
              </button>
              <button @click="router.push({ name: 'startup-day-detalle', params: { uuid } })"
                      class="flex-1 btn-secondary justify-center">
                Ver propuesta
              </button>
            </div>
          </div>

          <template v-else>
          <div class="mb-6">
            <p class="text-gray-600 text-sm">
              Márcala como <strong class="text-[#1F2937]">Propuesta</strong> para generar el enlace de validación
              y enviárselo a la empresa colaboradora. La empresa accederá al enlace, revisará la propuesta
              y decidirá si la valida o no.
            </p>
          </div>

          <!-- Nota fija: fase de propuesta -->
          <div class="flex items-start gap-2.5 bg-blue-50 border border-blue-200 rounded-2xl px-4 py-3 mb-4">
            <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-xs text-blue-700 leading-relaxed">
              Esto es, de momento, una <strong>propuesta</strong>. Cuando la publiques y la empresa (o el docente) la valide,
              pasará a llamarse <strong>proyecto</strong>.
            </p>
          </div>

          <!-- Resumen -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500 mb-4">Resumen de la propuesta</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Título</p>
                <p class="font-bold text-[#1F2937]">{{ form.titulo || '—' }}</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Empresa</p>
                <p class="font-bold text-[#1F2937]">{{ form.datos_empresa.nombre || '—' }}</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Docente</p>
                <p class="font-bold text-[#1F2937]">{{ form.datos_centro.docente_nombre || '—' }}</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Módulos</p>
                <p class="font-bold text-[#1F2937]">{{ form.modulos_seleccionados.length }} seleccionado(s)</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Objetivos</p>
                <p class="font-bold text-[#1F2937]">{{ form.objetivos.lista.length }} definido(s)</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Calendario</p>
                <p class="font-bold text-[#1F2937]">{{ form.diseno_microproyecto.clases?.length || 0 }} sesión(es)</p>
              </div>
            </div>
          </div>

          <!-- Recursos: vídeos y documentos para la empresa (almacenados en Cloudinary) -->
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
            <div class="flex items-center gap-3 mb-1">
              <div class="w-8 h-8 rounded-xl bg-rol/10 border border-rol/20
                          flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
              </div>
              <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-rol-fuerte">Recursos para la empresa</p>
                <p class="text-xs text-gray-400 mt-0.5">
                  Los archivos se suben a <strong>Cloudinary</strong>. La empresa los verá al abrir el enlace de validación.
                </p>
              </div>
            </div>

            <!-- Error de subida global -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 -translate-y-1">
              <p v-if="errorSubida" class="mt-3 text-xs text-red-500 font-medium">{{ errorSubida }}</p>
            </Transition>

            <div class="mt-5 space-y-5">

              <!-- ── VÍDEOS ─────────────────────────────────────────── -->
              <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Vídeos</p>

                <!-- Lista -->
                <div v-if="videosLocales.length" class="space-y-2 mb-3">
                  <div v-for="(v, i) in videosLocales" :key="i"
                       class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-100
                              hover:border-blue-200 hover:bg-blue-50/40 transition-colors group/vid">
                    <button @click="abrirRecurso(v)"
                            class="w-7 h-7 rounded-full bg-blue-50 shrink-0 flex items-center justify-center
                                   group-hover/vid:bg-blue-100 transition-colors">
                      <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                      </svg>
                    </button>
                    <button @click="abrirRecurso(v)" class="flex-1 min-w-0 text-left">
                      <p class="text-xs font-bold text-gray-700 truncate group-hover/vid:text-blue-600 transition-colors">{{ v.label || v.filename }}</p>
                      <p class="text-xs text-blue-400/80 truncate">Cloudinary · {{ v.filename }}</p>
                    </button>
                    <button @click.stop="removeVideo(i)"
                            class="w-6 h-6 rounded-full bg-red-50 flex items-center justify-center text-red-400 hover:bg-red-100 transition-colors shrink-0">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Uploader -->
                <div class="flex gap-2 items-center">
                  <input v-model="labelVideo" type="text" placeholder="Nombre del vídeo (opcional)"
                         class="w-44 shrink-0 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs
                                text-gray-700 placeholder-gray-300 focus:outline-none focus:border-rol-fuerte transition-colors"/>
                  <label class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl
                                border-2 border-dashed border-gray-200 bg-gray-50
                                cursor-pointer hover:border-rol/40 hover:bg-rol/5 transition-all"
                         :class="subiendoVideo ? 'opacity-50 pointer-events-none' : ''">
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span class="text-xs text-gray-400 font-medium">
                      {{ subiendoVideo ? 'Subiendo vídeo...' : 'Seleccionar vídeo (MP4, MOV, AVI, WebM...)' }}
                    </span>
                    <input type="file" accept="video/*"
                           class="sr-only" @change="subirArchivo('video', $event)" :disabled="subiendoVideo"/>
                  </label>
                </div>
              </div>

              <!-- ── BANCO DE IMÁGENES ─────────────────────────────────── -->
              <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Banco de imágenes</p>
                <p class="text-xs text-gray-400 mb-3">
                  También puede subir imágenes el alumnado desde su workspace. La primera
                  se marca como portada automáticamente; puedes cambiarla cuando quieras.
                </p>

                <!-- Galería -->
                <div v-if="imagenesLocales.length" class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-3">
                  <div v-for="(img, i) in imagenesLocales" :key="img.id"
                       class="relative group/img rounded-xl overflow-hidden border-2"
                       :class="img.id === imagenPortadaId ? 'border-rol-fuerte' : 'border-gray-100'">
                    <img :src="img.url" :alt="img.label || img.filename" class="w-full h-20 object-cover" />
                    <span v-if="img.id === imagenPortadaId"
                          class="absolute top-1 left-1 bg-rol-fuerte text-white text-xs font-bold
                                 uppercase tracking-wider px-1.5 py-0.5 rounded-full">Portada</span>
                    <!-- En móvil siempre visibles (no hay hover); desde sm, al pasar el ratón o con foco -->
                    <div class="absolute inset-0 bg-black/40 sm:bg-black/50 sm:opacity-0 sm:group-hover/img:opacity-100 sm:focus-within:opacity-100
                                transition-opacity flex items-center justify-center gap-1.5">
                      <button v-if="img.id !== imagenPortadaId" @click="marcarPortada(img)" type="button"
                              title="Marcar como portada" aria-label="Marcar como portada"
                              class="w-8 h-8 rounded-full bg-white/90 flex items-center justify-center text-rol-fuerte hover:bg-white">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                      </button>
                      <button @click.stop="removeImagen(i)" type="button" aria-label="Quitar imagen" title="Quitar imagen"
                              class="w-8 h-8 rounded-full bg-white/90 flex items-center justify-center text-red-400 hover:bg-white">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Uploader -->
                <label class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl
                              border-2 border-dashed border-gray-200 bg-gray-50
                              cursor-pointer hover:border-rol/40 hover:bg-rol/5 transition-all"
                       :class="subiendoImagen ? 'opacity-50 pointer-events-none' : ''">
                  <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                  <span class="text-xs text-gray-400 font-medium">
                    {{ subiendoImagen ? 'Subiendo imagen...' : 'Seleccionar imagen (PNG, JPG, GIF, WEBP)' }}
                  </span>
                  <input type="file" accept="image/png,image/jpeg,image/gif,image/webp"
                         class="sr-only" @change="subirArchivo('imagen', $event)" :disabled="subiendoImagen"/>
                </label>
                <p class="text-xs text-gray-300 mt-1.5 pl-1">Máx. 8 MB por imagen.</p>
              </div>

              <!-- ── DOCUMENTOS ─────────────────────────────────────── -->
              <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Documentos, otros archivos...</p>

                <!-- Lista -->
                <div v-if="documentosLocales.length" class="space-y-2 mb-3">
                  <div v-for="(d, i) in documentosLocales" :key="i"
                       class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-100
                              hover:border-rol/30 hover:bg-rol/5 transition-colors group/doc">
                    <button @click="abrirRecurso(d)"
                            class="w-7 h-7 rounded-full bg-rol/10 shrink-0 flex items-center justify-center
                                   group-hover/doc:bg-rol/20 transition-colors">
                      <svg class="w-3.5 h-3.5 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                      </svg>
                    </button>
                    <button @click="abrirRecurso(d)" class="flex-1 min-w-0 text-left">
                      <p class="text-xs font-bold text-gray-700 truncate group-hover/doc:text-rol-fuerte transition-colors">{{ d.label || d.filename }}</p>
                      <p class="text-xs text-blue-400/80 truncate">Cloudinary · {{ d.filename }}</p>
                    </button>
                    <button @click.stop="removeDocumento(i)"
                            class="w-6 h-6 rounded-full bg-red-50 flex items-center justify-center text-red-400 hover:bg-red-100 transition-colors shrink-0">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Uploader -->
                <div class="flex gap-2 items-center">
                  <input v-model="labelDocumento" type="text" placeholder="Nombre del documento (opcional)"
                         class="w-44 shrink-0 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs
                                text-gray-700 placeholder-gray-300 focus:outline-none focus:border-rol-fuerte transition-colors"/>
                  <label class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl
                                border-2 border-dashed border-gray-200 bg-gray-50
                                cursor-pointer hover:border-rol/40 hover:bg-rol/5 transition-all"
                         :class="subiendoDoc ? 'opacity-50 pointer-events-none' : ''">
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span class="text-xs text-gray-400 font-medium">
                      {{ subiendoDoc ? 'Subiendo archivo...' : 'Seleccionar archivo (PDF, Word, Excel, imagen...)' }}
                    </span>
                    <input type="file"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.gif,.webp,.zip"
                           class="sr-only" @change="subirArchivo('documento', $event)" :disabled="subiendoDoc"/>
                  </label>
                </div>
                <p class="text-xs text-gray-300 mt-1.5 pl-1">Máx. 20 MB por archivo · Los archivos se alojan en Cloudinary. La BD no almacena ningún fichero.</p>
              </div>

            </div>
          </div>

          <div class="bg-amber-50/80 border border-amber-200/60 rounded-2xl p-5 mb-5">
            <div class="flex items-start gap-3">
              <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <p class="text-sm text-[#1F2937] leading-relaxed">
                La propuesta se guarda como <strong>En edición</strong> por defecto. Usa el desplegable <strong>Estado del proyecto</strong> para cambiar el estado.
                Selecciona <strong>Propuesta</strong> para generar el enlace de validación empresa o para validar directamente como docente.
                Pasará a llamarse <strong>proyecto</strong> y quedará <strong>Validado</strong> cuando lo valide la empresa, el docente, o ambos.
              </p>
            </div>
          </div>

          <div class="fila-pasos">
            <button @click="paso = 6" class="btn-paso-atras self-start">← Anterior</button>
            <div class="flex min-w-0 flex-wrap gap-3 justify-end items-center">

              <!-- ── Desplegable Estado del proyecto ── -->
              <div class="relative">
                <button
                  @click="dropdownEstadoAbierto = !dropdownEstadoAbierto"
                  :disabled="guardando"
                  class="btn-secondary flex items-center gap-2"
                >
                  <!-- Dot + label del estado actual -->
                  <span :class="['w-2 h-2 rounded-full shrink-0 transition-colors',
                    form.estado === 'propuesta' && form.enviado_a_empresa_mail ? 'bg-rol-fuerte'
                    : estadoOpciones[form.estado]?.dot || 'bg-amber-400']" />
                  <span :class="form.estado === 'propuesta' && form.enviado_a_empresa_mail
                    ? 'text-rol-fuerte' : (estadoOpciones[form.estado]?.text || 'text-amber-700')">
                    {{ labelEstadoBtn }}
                  </span>
                  <svg class="w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>

                <!-- Menú desplegable -->
                <Transition enter-active-class="transition-all duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95 -translate-y-1"
                            leave-active-class="transition-all duration-100 ease-in"
                            leave-to-class="opacity-0 scale-95 -translate-y-1">
                  <div v-if="dropdownEstadoAbierto"
                       class="absolute bottom-full right-0 mb-2 bg-white border border-gray-100
                              rounded-2xl shadow-xl p-2 min-w-[190px] z-20">

                    <!-- En edición -->
                    <button @click="seleccionarEstado('en_edicion')"
                            :class="['w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-left transition-colors',
                                     form.estado === 'en_edicion'
                                       ? 'bg-amber-50 text-amber-700'
                                       : 'text-amber-700 hover:bg-amber-50']">
                      <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0" />
                      En edición
                      <svg v-if="form.estado === 'en_edicion'" class="w-3.5 h-3.5 ml-auto text-amber-500"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                    </button>

                    <!-- Archivar -->
                    <button @click="seleccionarEstado('archivado')"
                            :class="['w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-left transition-colors',
                                     form.estado === 'archivado'
                                       ? 'bg-gray-100 text-gray-600'
                                       : 'text-gray-500 hover:bg-gray-50']">
                      <span class="w-2 h-2 rounded-full bg-gray-400 shrink-0" />
                      Archivar
                      <svg v-if="form.estado === 'archivado'" class="w-3.5 h-3.5 ml-auto text-gray-400"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                    </button>

                    <div class="my-1 border-t border-gray-100" />

                    <!-- Propuesta + sub-estado + (i) -->
                    <div class="flex items-center gap-1 px-1">
                      <button @click="seleccionarEstado('propuesta')"
                              :class="['flex-1 flex flex-col gap-0.5 px-2 py-2.5 rounded-xl text-xs font-bold text-left transition-colors',
                                       form.estado === 'propuesta'
                                         ? (form.enviado_a_empresa_mail ? 'bg-blue-50 text-blue-700' : 'bg-violet-50 text-violet-700')
                                         : 'text-violet-700 hover:bg-violet-50']">
                        <span class="flex items-center gap-2">
                          <span class="w-2 h-2 rounded-full shrink-0"
                                :class="form.estado === 'propuesta' && form.enviado_a_empresa_mail ? 'bg-rol-fuerte' : 'bg-violet-400'" />
                          Propuesta
                          <svg v-if="form.estado === 'propuesta'" class="w-3.5 h-3.5 ml-auto"
                               :class="form.enviado_a_empresa_mail ? 'text-rol-fuerte' : 'text-violet-400'"
                               fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                          </svg>
                        </span>
                        <!-- Sub-estado visible solo cuando está en propuesta -->
                        <span v-if="form.estado === 'propuesta'"
                              class="text-xs font-bold uppercase tracking-wider pl-4"
                              :class="form.enviado_a_empresa_mail ? 'text-rol-fuerte' : 'text-violet-500'">
                          {{ form.enviado_a_empresa_mail ? '✅ SÍ enviada por mail' : '✉ NO enviada por mail' }}
                        </span>
                      </button>
                      <!-- Tooltip (i) -->
                      <div class="relative group/info shrink-0">
                        <button type="button" aria-label="Qué significa marcarla como Propuesta"
                                class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center cursor-help">
                          <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                        </button>
                        <div role="tooltip" class="absolute hidden group-hover/info:block group-focus-within/info:block bottom-full right-0 mb-2
                                    bg-[#1a2332] text-white text-xs rounded-xl p-3 w-60 z-30
                                    leading-relaxed shadow-2xl">
                          Márcala como <strong class="text-white">Propuesta</strong> para generar el enlace de validación empresa, o para validarla directamente como docente. Pasará a llamarse <strong class="text-white">proyecto</strong> y a <strong class="text-rol-fuerte">Validado</strong> cuando lo valide la empresa, el docente, o ambos.
                          <div class="absolute bottom-[-4px] right-3 w-2 h-2 bg-[#1a2332] rotate-45" />
                        </div>
                      </div>
                    </div>

                  </div>
                </Transition>

                <!-- Capa para cerrar al hacer clic fuera -->
                <div v-if="dropdownEstadoAbierto"
                     class="fixed inset-0 z-10"
                     @click="dropdownEstadoAbierto = false" />
              </div>

              <!-- Archivar Proyecto (antes: Guardar borrador) -->
              <button @click="archivarProyecto" :disabled="guardando" class="btn-secondary">
                {{ guardando ? 'Archivando…' : 'Archivar Proyecto' }}
              </button>

              <!-- Biblioteca de proyectos -->
              <button @click="router.push({ name: 'startup-day' })"
                      class="btn-secondary flex items-center gap-2">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                Biblioteca
              </button>

              <!-- Publicar propuesta → muestra modal, guarda como borrador -->
              <button @click="mostrarModalPublicar" :disabled="guardando || !form.titulo.trim()" class="btn-paso">
                {{ guardando ? 'Guardando…' : 'Publicar propuesta' }}
              </button>

            </div>
          </div>
          </template>
        </div>

      </template>
      </div><!-- /flex-1 wizard -->

      <!-- ── Panel lateral: resumen del proyecto + IA del paso (solo lg) ──────── -->
      <TallerResumen :titulo="form.titulo || microretoVinculado?.titulo || ''"
                     :pregunta="form.diseno_reto.pregunta_reto || microretoVinculado?.pregunta_reto || ''"
                     :portada-url="portadaUrl"
                     :datos="datosResumen"
                     :tipo-empresa="tipoEmpresa"
                     :estado="(microretoVinculado || form.titulo) ? estadoResumen : null"
                     :hay-reto="!!microretoVinculado"
                     :puede-editar-imagen="!!uuid && paso !== 7 && puedeIrAPaso(7) && pasoMaxAlcanzado >= 7"
                     :antes="antesDeContinuar"
                     :ia="iaPaso"
                     @ver-ficha="microretoVinculado && (microretoModalId = microretoVinculado.id)"
                     @editar-imagen="paso = 7"
                     @sugerir="sugerirDesdePanel" />

      </div><!-- /flex gap-8 -->
    </div>
  </div>

  <!-- ══ MODAL VALIDAR DOCENTE ════════════════════════════════════════════════ -->
  <ValidarDocenteModal
    :visible="modalValidarDocente"
    :loading="validandoDocente"
    @confirm="validarComoDocente"
    @cancel="modalValidarDocente = false"
  />

  <!-- ══ MODAL AVISO BORRADOR ════════════════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalBorradorAviso"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         @click.self="modalBorradorAviso = false">

      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" />

      <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8">

        <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-100
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </div>

        <h3 class="text-xl font-bold text-azul-noche text-center mb-4">
          Propuesta en edición
        </h3>

        <p class="text-sm text-gray-600 leading-relaxed text-center">
          Esta propuesta está <strong>En edición</strong> — aún no se ha enviado a validar.
          Cuando esté lista, usa el desplegable <strong>Estado del proyecto</strong> y selecciona
          <strong>Propuesta</strong> para enviarla a la empresa o validarla como docente.
        </p>

        <button
          @click="modalBorradorAviso = false"
          class="mt-7 w-full inline-flex items-center justify-center
                 px-6 py-3 bg-[#1F2937] text-white rounded-full
                 text-xs font-bold uppercase tracking-widest shadow-sm
                 hover:bg-[#1F2937]/80 transition-all active:scale-95"
        >
          Entendido, seguir editando
        </button>
      </div>
    </div>
  </Transition>

  <!-- ══ MODAL AVISO PROPUESTA ════════════════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalPropuestaAviso"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         @click.self="cerrarModalPropuestaAviso()">

      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cerrarModalPropuestaAviso()" />

      <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8 overflow-y-auto max-h-[90vh]">

        <!-- Icono -->
        <div class="w-14 h-14 rounded-2xl bg-rol/10 border border-rol/20
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
        </div>

        <!-- Badge estado -->
        <div class="flex justify-center mb-4">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full
                       bg-violet-50 border border-violet-200 text-violet-700
                       text-xs font-bold uppercase tracking-widest">
            <span class="w-1.5 h-1.5 rounded-full bg-violet-400 animate-pulse" />
            Propuesta · Pendiente de validar
          </span>
        </div>

        <h3 class="text-xl font-bold text-azul-noche text-center mb-2">
          Propuesta pendiente de validar
        </h3>
        <p class="text-sm text-gray-500 text-center mb-5 leading-relaxed">
          Elige cómo validar la propuesta — puedes usar una vía o las dos.
        </p>

        <!-- Vía A: Validación empresa -->
        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 mb-3">
          <p class="text-xs font-bold uppercase tracking-widest text-blue-500 mb-3">
            Vía A · Validación empresa
          </p>
          <div class="flex items-center gap-2 mb-3">
            <p class="flex-1 text-xs text-blue-400 truncate font-mono bg-white border border-blue-200
                       rounded-xl px-3 py-2 min-w-0">
              {{ landingUrl || '—' }}
            </p>
            <button @click="copiarUrlModal"
                    :class="['shrink-0 px-3 py-2 rounded-full text-xs font-bold border transition-all',
                             urlCopiadaModal
                               ? 'bg-rol/10 text-rol-fuerte border-rol/20'
                               : 'bg-white text-gray-500 border-gray-200 hover:border-rol-fuerte hover:text-rol-fuerte']">
              {{ urlCopiadaModal ? '¡Copiado!' : 'Copiar' }}
            </button>
          </div>

          <!-- Info empresa desplegable -->
          <div v-if="form.empresa_id" class="mb-3">
            <button @click="infoEmpresaAbierta = !infoEmpresaAbierta"
                    class="w-full flex items-center justify-between px-3 py-2 rounded-xl
                           bg-white border border-blue-200 text-xs font-bold text-blue-700
                           hover:border-rol/40 hover:text-rol-fuerte transition-all">
              <span class="flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-blue-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                {{ form.datos_empresa?.nombre || 'Ver info de la empresa' }}
              </span>
              <svg class="w-3.5 h-3.5 transition-transform duration-200 text-blue-300 shrink-0"
                   :class="infoEmpresaAbierta ? 'rotate-180' : ''"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <Transition
              enter-active-class="transition-all duration-200 ease-out overflow-hidden"
              enter-from-class="opacity-0 max-h-0"
              enter-to-class="opacity-100 max-h-96"
              leave-active-class="transition-all duration-150 ease-in overflow-hidden"
              leave-from-class="opacity-100 max-h-96"
              leave-to-class="opacity-0 max-h-0"
            >
              <div v-if="infoEmpresaAbierta"
                   class="mt-2 px-3 py-3 bg-white border border-blue-100 rounded-xl space-y-1.5">
                <p v-if="form.datos_empresa?.sector"
                   class="text-xs text-gray-400">
                  <span class="font-bold uppercase tracking-wider">Sector:</span>
                  {{ form.datos_empresa.sector }}
                </p>
                <p v-if="form.datos_empresa?.persona_contacto"
                   class="text-xs text-gray-400">
                  <span class="font-bold uppercase tracking-wider">Contacto:</span>
                  {{ form.datos_empresa.persona_contacto }}
                </p>
                <p v-if="form.datos_empresa?.email"
                   class="text-xs text-gray-400">
                  <span class="font-bold uppercase tracking-wider">Email:</span>
                  {{ form.datos_empresa.email }}
                </p>
                <p v-if="form.datos_empresa?.descripcion"
                   class="text-xs text-gray-500 leading-relaxed pt-1 border-t border-gray-100">
                  {{ form.datos_empresa.descripcion }}
                </p>
              </div>
            </Transition>
          </div>

          <!-- Botones: enviar + directorio -->
          <div class="flex flex-col gap-2">
            <button v-if="form.empresa_id"
                    @click="abrirConfirmEnvio"
                    :class="form.enviado_a_empresa_mail
                      ? 'bg-rol/10 border-rol/30 text-rol-fuerte hover:bg-rol/20'
                      : 'bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100'"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5
                           border rounded-full text-xs font-bold transition-all">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              {{ form.enviado_a_empresa_mail ? '✅ Reenviar enlace a la empresa' : '✉ Confirmar envío del enlace' }}
            </button>
            <button @click="router.push({ name: 'empresas' }); modalPropuestaAviso = false"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5
                           bg-white border border-blue-200 text-blue-600 rounded-full
                           text-xs font-bold hover:border-rol/40 hover:text-rol-fuerte transition-all">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              Ir al directorio de empresas
            </button>
          </div>
        </div>

        <!-- Vía B: Validación docente -->
        <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 mb-6">
          <p class="text-xs font-bold uppercase tracking-widest text-emerald-600 mb-3">
            Vía B · Validación docente
          </p>
          <p class="text-xs text-gray-600 leading-relaxed mb-3">
            Puedes validar la propuesta directamente sin esperar a la empresa.
            <span class="text-amber-600 font-bold">Esto no sustituye la validación empresa</span>
            — ambas son independientes y complementarias.
          </p>
          <button v-if="!form.docente_validado"
                  @click="modalValidarDocente = true; cerrarModalPropuestaAviso()"
                  class="w-full flex items-center justify-center gap-2 px-4 py-2.5
                         bg-emerald-600 text-white rounded-full
                         text-xs font-bold hover:bg-emerald-700 transition-all">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            Validar como docente
          </button>
          <div v-else class="flex items-center gap-2 px-3 py-2 bg-emerald-100 rounded-xl">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-xs font-bold text-emerald-700">Ya has validado esta propuesta como docente</p>
          </div>
        </div>

        <!-- Botones -->
        <div class="flex gap-3">
          <button
            @click="cerrarModalPropuestaAviso()"
            class="flex-1 inline-flex items-center justify-center
                   px-5 py-3 bg-gray-100 text-[#1F2937] rounded-full
                   text-xs font-bold uppercase tracking-widest
                   hover:bg-gray-200 transition-all active:scale-95"
          >
            Entendido
          </button>
          <button
            @click="cerrarModalPropuestaAviso()"
            class="flex-1 inline-flex items-center justify-center gap-2
                   px-5 py-3 bg-rol-fuerte text-white rounded-full
                   text-xs font-bold uppercase tracking-widest shadow-sm
                   hover:bg-rol/90 transition-all active:scale-95"
          >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            Ver propuesta
          </button>
        </div>

      </div>
    </div>
  </Transition>

  <!-- ══ MODAL CONFIRMACIÓN ENVÍO ENLACE ══════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalConfirmEnvio"
         class="fixed inset-0 z-[10000] flex items-center justify-center p-4"
         @click.self="modalConfirmEnvio = false">

      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="modalConfirmEnvio = false" />

      <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 overflow-y-auto max-h-[90vh]">

        <!-- Icono -->
        <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-200
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>

        <h3 class="text-xl font-bold text-azul-noche text-center mb-1">
          Confirmar envío
        </h3>
        <p class="text-sm text-gray-500 text-center mb-5 leading-relaxed">
          Estás a punto de abrir el panel de envío del enlace de validación para:
        </p>

        <!-- Tarjeta empresa -->
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-5">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 rounded-xl bg-rol/10 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
            </div>
            <p class="font-bold text-sm text-[#121212]">
              {{ form.datos_empresa?.nombre || '—' }}
            </p>
          </div>
          <div class="space-y-1 pl-10">
            <p v-if="form.datos_empresa?.sector" class="text-xs text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-xs">Sector:</span>
              {{ form.datos_empresa.sector }}
            </p>
            <p v-if="form.datos_empresa?.persona_contacto" class="text-xs text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-xs">Contacto:</span>
              {{ form.datos_empresa.persona_contacto }}
            </p>
            <p v-if="form.datos_empresa?.email" class="text-xs text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-xs">Email:</span>
              {{ form.datos_empresa.email }}
            </p>
          </div>
        </div>

        <!-- Campo de seguridad: escribe "enviar" -->
        <div class="mb-5">
          <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">
            Escribe <span class="font-bold text-[#1F2937] normal-case tracking-normal">enviar</span> para confirmar
          </label>
          <input
            v-model="confirmEnvioTexto"
            type="text"
            placeholder="Escribe enviar…"
            autocomplete="off"
            @keydown.enter.prevent="confirmEnvioValido && confirmarEnvio()"
            class="w-full border rounded-xl px-4 py-2.5 text-sm outline-none transition-all"
            :class="confirmEnvioTexto && !confirmEnvioValido
              ? 'border-red-300 bg-red-50 text-red-700 focus:ring-2 focus:ring-red-200'
              : confirmEnvioTexto && confirmEnvioValido
                ? 'border-rol/50 bg-rol/5 text-rol-fuerte focus:ring-2 focus:ring-rol/20'
                : 'border-gray-200 bg-white focus:border-gray-400 focus:ring-2 focus:ring-gray-100'"
          />
          <p v-if="confirmEnvioTexto && !confirmEnvioValido"
             class="mt-1.5 text-xs text-red-500 font-semibold">
            Escribe exactamente: enviar
          </p>
          <p v-if="confirmEnvioTexto && confirmEnvioValido"
             class="mt-1.5 text-xs text-rol-fuerte font-semibold">
            Confirmado. Ya puedes continuar.
          </p>
        </div>

        <!-- Botones principales -->
        <div class="flex gap-3 mb-3">
          <button
            @click="modalConfirmEnvio = false"
            class="flex-1 inline-flex items-center justify-center
                   px-4 py-2.5 bg-gray-100 text-[#1F2937] rounded-full
                   text-xs font-bold uppercase tracking-widest
                   hover:bg-gray-200 transition-all active:scale-95"
          >
            Cancelar
          </button>
          <button
            @click="confirmarEnvio"
            :disabled="!confirmEnvioValido"
            class="flex-1 inline-flex items-center justify-center gap-2
                   px-4 py-2.5 rounded-full text-xs font-bold uppercase tracking-widest
                   transition-all active:scale-95"
            :class="confirmEnvioValido
              ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-sm'
              : 'bg-gray-100 text-gray-300 cursor-not-allowed'"
          >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Confirmar y enviar
          </button>
        </div>

        <!-- Botón directorio de empresas -->
        <button
          @click="router.push({ name: 'empresas' }); modalConfirmEnvio = false; modalPropuestaAviso = false"
          class="w-full inline-flex items-center justify-center gap-2
                 px-4 py-2.5 bg-white border border-gray-200 text-gray-500 rounded-full
                 text-xs font-bold hover:border-rol/40 hover:text-rol-fuerte transition-all"
        >
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
          Ir al directorio de empresas
        </button>

      </div>
    </div>
  </Transition>

  <!-- ══ MODAL VISOR RECURSOS (vídeos / docs / imágenes) ══════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalRecurso"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @click.self="modalRecurso = null">

      <!-- Backdrop con blur -->
      <div class="absolute inset-0 bg-black/70 backdrop-blur-md" @click="modalRecurso = null" />

      <!-- Panel -->
      <div class="relative z-10 bg-white rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden
                  flex flex-col max-h-[90vh]">

        <!-- Cabecera -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
          <p class="text-sm font-bold text-[#121212] truncate pr-4">{{ modalRecurso.label }}</p>
          <button @click="modalRecurso = null"
                  class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center
                         text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-all shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Contenido según tipo -->
        <div class="flex-1 overflow-auto bg-gray-950 flex items-center justify-center min-h-[300px]">

          <!-- Vídeo -->
          <video v-if="modalRecurso.tipo === 'video'"
                 :src="modalRecurso.url"
                 controls autoplay
                 class="w-full max-h-[70vh] object-contain" />

          <!-- Imagen -->
          <img v-else-if="modalRecurso.tipo === 'imagen'"
               :src="modalRecurso.url"
               :alt="modalRecurso.label"
               class="max-w-full max-h-[70vh] object-contain" />

          <!-- PDF -->
          <iframe v-else-if="modalRecurso.tipo === 'pdf'"
                  :src="modalRecurso.url"
                  class="w-full h-[70vh] border-0" />

          <!-- Otro (enlace externo) -->
          <div v-else class="flex flex-col items-center gap-4 p-10 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-800 flex items-center justify-center">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
            </div>
            <p class="text-gray-400 text-sm">Este tipo de archivo no se puede previsualizar</p>
            <a :href="modalRecurso.url" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-rol-fuerte text-white
                      rounded-full text-xs font-bold uppercase tracking-widest
                      hover:bg-rol/90 transition-all">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
              </svg>
              Abrir en nueva pestaña
            </a>
          </div>

        </div>
      </div>
    </div>
  </Transition>

  <!-- ══ MODAL AVISO PUBLICAR ══════════════════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-200 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-150 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modalPublicarVisible"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @click.self="modalPublicarVisible = false">

      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" />

      <!-- Contenido -->
      <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8
                  transform transition-all duration-200"
           :class="modalPublicarVisible ? 'scale-100 opacity-100' : 'scale-95 opacity-0'">

        <!-- Icono -->
        <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-100
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>

        <h3 class="text-xl font-bold text-azul-noche text-center mb-4">
          Sobre la publicación de la propuesta
        </h3>

        <p class="text-sm text-gray-600 leading-relaxed text-center">
          Esta propuesta se guarda como <strong>borrador</strong> por defecto.
          Puedes modificar esta opción, pero se aconseja validar con la empresa
          previamente enviándoles el enlace, antes de marcarla como validada.
          El enlace se generará una vez selecciones <strong>Propuesta</strong> en el desplegable de Estado del proyecto.
          Al validarla, pasará a llamarse <strong>proyecto</strong>.
        </p>

        <button
          @click="confirmarGuardarBorrador"
          class="mt-7 w-full inline-flex items-center justify-center
                 px-6 py-3 bg-rol-fuerte text-white rounded-full
                 text-xs font-bold uppercase tracking-widest shadow-sm
                 hover:bg-rol/90 transition-all active:scale-95"
        >
          Entendido
        </button>
      </div>
    </div>
  </Transition>

  <!-- Modal: ¿Activar guía-tour? -->
  <TourPromptModal
    :show="showTourPrompt"
    titulo="¿Quieres activar la guía-tour?"
    descripcion="Explora el Taller de Ideas con una guía paso a paso."
    @activar="activarTourDesdeModal"
    @omitir="omitirTourDesdeModal"
  />

  <MicroretoModal :microreto-id="microretoModalId" @close="microretoModalId = null" />

</template>

<style scoped>
@reference "../style.css";

/* Chips de datos bajo la cabecera del wizard (diseño crear_proyecto*.png) */
.chip-taller {
  @apply inline-flex h-8 min-w-0 items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 text-[13px] text-gray-700 shadow-sm;
}

.field-label {
  @apply block text-xs font-bold uppercase tracking-[0.12em] text-gray-600 mb-1.5;
}
.field-input {
  @apply w-full bg-white border border-borde-campo rounded-xl px-4 py-2.5 text-sm
         text-[#1F2937] placeholder-gray-500 focus:outline-none focus:border-rol-fuerte
         transition-colors shadow-sm;
}
.btn-primary {
  @apply inline-flex items-center gap-2 px-6 py-2.5 bg-rol-fuerte text-white
         rounded-full text-xs font-bold uppercase tracking-widest shadow-sm
         hover:bg-rol-fuerte/90
         transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed;
}
.btn-secondary {
  @apply inline-flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200
         rounded-full text-xs font-bold uppercase tracking-widest text-[#1F2937]
         shadow-sm hover:border-rol-fuerte hover:text-rol-fuerte
         transition-all active:scale-95;
}
.tag {
  display: inline-flex; align-items: center;
  padding: 0.125rem 0.5rem; border-radius: 999px;
  font-size: 0.75rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
}
.tag-gray  { background: #F3F4F6; color: #6B7280; }
.tag-green { background: rgba(48,114,170,0.1); color: #3072AA; }
.tag-lime  { background: rgba(107,164,213,0.12); color: #275d8a; }
</style>
