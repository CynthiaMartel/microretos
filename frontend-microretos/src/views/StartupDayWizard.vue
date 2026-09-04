<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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
  validado:   { label: 'Validado',   dot: 'bg-[#00A859]',  text: 'text-[#00A859]',  bg: 'bg-[#00A859]/10' },
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
const nuevoObjetivo = ref('');
function addObjetivo() { if (!nuevoObjetivo.value.trim()) return; form.value.objetivos.lista.push(nuevoObjetivo.value.trim()); nuevoObjetivo.value = ''; }
function removeObjetivo(i) { form.value.objetivos.lista.splice(i, 1); }

const nuevoKpi = ref('');
function addKpi() { if (!nuevoKpi.value.trim()) return; form.value.kpis.lista.push(nuevoKpi.value.trim()); nuevoKpi.value = ''; }
function removeKpi(i) { form.value.kpis.lista.splice(i, 1); }

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
    const sugeridos = res.data.kpis ?? [];
    const existentes = new Set(form.value.kpis.lista);
    sugeridos.forEach(k => { if (!existentes.has(k)) form.value.kpis.lista.push(k); });
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
    const sugeridos = res.data.objetivos ?? [];
    const existentes = new Set(form.value.objetivos.lista);
    sugeridos.forEach(o => { if (!existentes.has(o)) form.value.objetivos.lista.push(o); });
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
const modoRaCe         = ref('texto')  // 'ia' | 'texto'
const mostrarSeleccionManual = ref(false) // desplegable "Seleccionar otros manualmente" dentro de Datos del proyecto
const catalogoRaCe     = ref([])       // [{ modulo, moduloId, ras: [{id,orden,descripcion,criterios:[...]}] }]
const cargandoCatalogo = ref(false)
const cargandoIaRaCe   = ref(false)
const sugerenciaIaPendienteRevision = ref(false) // true tras usar "Sugerir con IA" — recuerda revisar antes de publicar
const raExpandido      = ref({})       // { raId: bool }
const raChecked        = ref({})       // { raId: bool }
const ceChecked        = ref({})       // { ceId: bool }

function normalizarTexto(t) {
  return (t || '').trim().toLowerCase().replace(/\s+/g, ' ')
}

async function cargarCatalogoRaCe() {
  const mods = form.value.modulos_seleccionados
  if (!mods.length) { catalogoRaCe.value = []; return }
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

    catalogoRaCe.value.forEach(mod => {
      mod.ras.forEach(ra => {
        raExpandido.value[ra.id] = true
        ra.criterios.forEach(ce => {
          ceChecked.value[ce.id] = ceIdsDeEsteProyecto.has(ce.id)
        })
        raChecked.value[ra.id] = ra.criterios.length > 0 && ra.criterios.every(ce => ceChecked.value[ce.id])
      })
    })
  } finally {
    cargandoCatalogo.value = false
  }
}

function toggleRa(ra) {
  const v = !raChecked.value[ra.id]
  raChecked.value[ra.id] = v
  ra.criterios.forEach(ce => { ceChecked.value[ce.id] = v })
}

function raEstado(ra) {
  const n = ra.criterios.filter(ce => ceChecked.value[ce.id]).length
  if (n === 0) return 'none'
  if (n === ra.criterios.length) return 'all'
  return 'some'
}

// Entradas de "RA/CE de este proyecto" que no proceden del catálogo oficial cargado
// ahora mismo (ra_id null porque se añadieron a mano, o de un módulo no seleccionado
// en este momento) — se preservan al reconstruir la selección desde los checkboxes,
// para no borrarlas por accidente.
function raCeFueraDeCatalogo() {
  const raIdsCatalogo = new Set()
  catalogoRaCe.value.forEach(mod => mod.ras.forEach(ra => raIdsCatalogo.add(ra.id)))
  return form.value.evaluacion_oficial.filter(e => !e.ra_id || !raIdsCatalogo.has(e.ra_id))
}

function aplicarSeleccionManual() {
  const seleccionCatalogo = []
  catalogoRaCe.value.forEach(mod => {
    mod.ras.forEach(ra => {
      const ces = ra.criterios.filter(ce => ceChecked.value[ce.id])
      if (ces.length) {
        seleccionCatalogo.push({
          modulo: mod.modulo,
          ra_id: ra.id,
          ra: ra.descripcion,
          ce_ids: ces.map(c => c.id),
          ce: ces.map(c => c.descripcion),
          aplicacion: '',
        })
      }
    })
  })
  form.value.evaluacion_oficial = [...seleccionCatalogo, ...raCeFueraDeCatalogo()]
  mostrarSeleccionManual.value = false
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
      form.value.evaluacion_oficial = mergeEvaluacionOficial(form.value.evaluacion_oficial, sugeridos)
      sugerenciaIaPendienteRevision.value = true
      modoRaCe.value = 'texto'
    }
  } catch (e) {
    console.error('Error sugiriendo RA/CE con IA', e)
  } finally {
    cargandoIaRaCe.value = false
  }
}

watch(modoRaCe, (modo) => {
  if (modo === 'ia') cargarCatalogoRaCe()
})

watch(mostrarSeleccionManual, (abierto) => {
  if (abierto) cargarCatalogoRaCe()
})

watch(() => form.value.modulos_seleccionados, () => {
  if (mostrarSeleccionManual.value || modoRaCe.value === 'ia') cargarCatalogoRaCe()
}, { deep: true })

watch(ceChecked, () => {
  if (!mostrarSeleccionManual.value) return
  const seleccionCatalogo = []
  catalogoRaCe.value.forEach(mod => {
    mod.ras.forEach(ra => {
      const ces = ra.criterios.filter(ce => ceChecked.value[ce.id])
      if (ces.length) {
        seleccionCatalogo.push({
          modulo: mod.modulo,
          ra_id: ra.id,
          ra: ra.descripcion,
          ce_ids: ces.map(c => c.id),
          ce: ces.map(c => c.descripcion),
          aplicacion: '',
        })
      }
    })
  })
  form.value.evaluacion_oficial = [...seleccionCatalogo, ...raCeFueraDeCatalogo()]
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

// RA/CE de este proyecto — lo que realmente se guarda y se verá en la ficha final
// (form.evaluacion_oficial). Empieza siendo copia del reto, pero puede crecer con
// "Sugerir otros con IA", "Seleccionar otros manualmente" o "Añadir RA/CE manualmente".
// Cada ra lleva _flatIndex = su posición real en form.evaluacion_oficial, para poder
// editar (borrar CE) sin tener que reconstruir el índice a partir del grid agrupado.
const raCeDeEsteProyecto = computed(() => {
  const porModulo = {}
  const orden = []
  form.value.evaluacion_oficial.forEach((entry, flatIdx) => {
    const modulo = entry.modulo || 'Sin módulo'
    if (!porModulo[modulo]) { porModulo[modulo] = { modulo, ras: [] }; orden.push(modulo) }
    porModulo[modulo].ras.push({
      descripcion: entry.ra || '',
      criterios: Array.isArray(entry.ce) ? entry.ce : [],
      _flatIndex: flatIdx,
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

// Quita un único CE de "RA/CE de este proyecto" — si el RA se queda sin CE, se elimina
// también. Nunca toca raCeDelReto (siempre se recalcula desde el reto vinculado, aparte).
function eliminarCeDeProyecto(modIdx, raIdx, ceIdx) {
  const ra = raCeDeEsteProyecto.value[modIdx]?.ras[raIdx]
  if (!ra) return
  const entry = form.value.evaluacion_oficial[ra._flatIndex]
  if (!entry) return
  entry.ce.splice(ceIdx, 1)
  if (Array.isArray(entry.ce_ids)) entry.ce_ids.splice(ceIdx, 1)
  if (!entry.ce.length) form.value.evaluacion_oficial.splice(ra._flatIndex, 1)
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

const totalCeSeleccionados = computed(() =>
  Object.values(ceChecked.value).filter(Boolean).length
)
const totalRaSeleccionados = computed(() =>
  catalogoRaCe.value.reduce((acc, mod) =>
    acc + mod.ras.filter(ra => raEstado(ra) !== 'none').length, 0
  )
)

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
  // Tour prompt desactivado temporalmente — reactivar poniendo showTourPrompt.value = true cuando se necesite.
  // await nextTick();
  // if (!modalBorradorAviso.value) showTourPrompt.value = true;
});

// ── Guardar ───────────────────────────────────────────────────────────────────
async function guardar(siguientePaso) {
  guardando.value = true; errorMsg.value = '';
  try {
    const payload = { ...form.value, paso_actual: siguientePaso };
    if (uuid.value) {
      const res = await api.put(`/startup/proyectos/${uuid.value}`, payload);
      proyectoValidado.value = !!res.data.empresa_validado;
      if (res.data.token_empresa) tokenEmpresa.value = res.data.token_empresa;
    } else {
      const res = await api.post('/startup/proyectos', {
        titulo:       form.value.titulo,
        microreto_id: form.value.microreto_id,
      });
      uuid.value = res.data.uuid;
      const upd = await api.put(`/startup/proyectos/${uuid.value}`, payload);
      proyectoValidado.value = !!upd.data.empresa_validado;
      if (upd.data.token_empresa) tokenEmpresa.value = upd.data.token_empresa;
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

const progreso = computed(() => Math.round(((paso.value - 1) / (totalPasos - 1)) * 100));
const pasos = [
  { num: 1, label: 'Básicos' }, { num: 2, label: 'Empresa' },
  { num: 3, label: 'Currículo' },{ num: 4, label: 'El Reto' },
  { num: 5, label: 'Propuesta' }, { num: 6, label: 'Objetivos' },
  { num: 7, label: 'Publicar' },
];

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
    texto: 'Define el núcleo de la propuesta: la fundamentación (contexto de partida, justificación pedagógica e innovación) y el diseño del reto (descripción de la problemática, pregunta reto en formato "¿Cómo podríamos…?", restricciones que condicionan la solución y los entregables que el equipo debe producir). Cuanto más concreto, más fácil será la evaluación final.',
  },
  {
    titulo: 'Paso 5 · Diseño de la propuesta',
    texto: 'Revisa las 5 fases del proyecto (las mismas que el equipo recorrerá en su workspace cuando la propuesta se convierta en proyecto) con su duración orientativa, describe la metodología que seguirá el equipo y esboza el cronograma con los hitos clave. Termina con un resumen ejecutivo de 3-4 líneas que la empresa verá al abrir el enlace de validación.',
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

watch(paso, () => { modoGuia.value = true; });
watch(modoGuia, (val) => { tourActivo.value = val; });
onUnmounted(() => { tourActivo.value = false; });
</script>

<template>
  <div class="min-h-screen font-sans text-[#1F2937] pt-12 md:pt-12"
       :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
       style="transition: opacity 0.4s ease, transform 0.4s ease">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-175 h-100
                bg-[#99CC33] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <!-- Barra de progreso superior -->
    <div class="sticky top-12 z-20 bg-[#F8FAFC]/95 backdrop-blur border-b border-gray-100 shadow-sm">
      <div class="max-w-3xl mx-auto px-4 py-3">
        <div class="flex items-center gap-4 mb-2">
          <button @click="router.push({ name: 'startup-day' })"
                  class="inline-flex items-center gap-1.5 text-gray-500 hover:text-[#00A859]
                         transition-colors text-xs font-black uppercase tracking-widest shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Todos los Proyectos
          </button>
          <div class="flex-1 min-w-0">
            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-[#00A859]">
              StartUp Day · Paso {{ paso }} de {{ totalPasos }}
            </p>
            <p class="text-xs font-bold text-gray-600 truncate">{{ form.titulo || 'Nueva propuesta' }}</p>
          </div>
          <span class="text-xs font-black text-gray-400 shrink-0">{{ progreso }}%</span>
          <button @click="modoGuia = true"
                  title="Ver guía de este paso"
                  class="px-2.5 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 shrink-0
                         flex items-center justify-center text-blue-500 text-[10px] font-black
                         hover:bg-blue-500/20 transition-all uppercase tracking-widest">
            Guía
          </button>
        </div>

        <!-- Barra progreso -->
        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden mb-2">
          <div class="h-full bg-linear-to-r from-[#00A859] to-[#99CC33] rounded-full transition-all duration-500"
               :style="{ width: progreso + '%' }"/>
        </div>

        <!-- Pasos mini -->
        <div class="flex gap-1 overflow-x-auto scrollbar-none">
          <button v-for="p in pasos" :key="p.num"
                  @click="(p.num === 1 || form.microreto_id) && p.num <= pasoMaxAlcanzado && (paso = p.num)"
                  :class="[
                    'flex-1 min-w-13 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider transition-all',
                    p.num === paso
                      ? 'bg-[#00A859]/10 text-[#00A859] border border-[#00A859]/30'
                      : (p.num === 1 || form.microreto_id) && p.num <= pasoMaxAlcanzado
                        ? 'bg-gray-100 text-gray-500 hover:text-[#00A859] border border-gray-200 cursor-pointer'
                        : 'bg-transparent text-gray-300 border border-transparent cursor-default'
                  ]">
            {{ p.label }}
          </button>
        </div>

        <!-- Aviso: proyecto validado por empresa -->
        <div v-if="proyectoValidado"
             class="mt-2 flex items-center gap-2 px-3 py-2 rounded-xl bg-amber-50 border border-amber-200">
          <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          <p class="text-[10px] font-bold text-amber-700 leading-tight">
            Este proyecto ya ha sido validado por la empresa. Modificar el contenido requerirá una nueva validación.
          </p>
        </div>
      </div>
    </div>

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
            <div class="w-10 h-10 rounded-2xl bg-[#00A859]/20 flex items-center justify-center
                        text-[#00A859] font-black text-base shrink-0">
              {{ paso }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[9px] font-black uppercase tracking-widest text-white/40 mb-1.5">
                Guía · Paso {{ paso }} de {{ totalPasos }}
              </p>
              <div class="flex gap-1">
                <span v-for="i in totalPasos" :key="i"
                      class="h-[3px] rounded-full transition-all duration-300"
                      :class="i <= paso ? 'bg-[#00A859] w-5' : 'bg-white/20 w-3'" />
              </div>
            </div>
          </div>
          <!-- Título del paso -->
          <p class="text-[10px] font-black uppercase tracking-widest text-[#00A859] mb-2">
            {{ guiaWizard[paso - 1].titulo }}
          </p>
          <!-- Descripción -->
          <p class="text-[13px] text-white/85 leading-relaxed mb-6">
            {{ guiaWizard[paso - 1].texto }}
          </p>
          <!-- Botones -->
          <div class="flex items-center gap-2">
            <button @click="modoGuia = false"
                    class="flex-1 py-2.5 rounded-xl bg-[#00A859] text-white text-[10px] font-black
                           uppercase tracking-widest hover:bg-[#00A859]/90 transition-all">
              Entendido ✓
            </button>
            <button @click="modoGuia = false"
                    class="px-3 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white/40
                           text-[10px] font-black uppercase tracking-widest hover:text-white/60 transition-all">
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Contenido -->
    <div class="relative z-10 max-w-5xl mx-auto px-4 py-8">
      <div class="flex gap-8 items-start">
      <div class="flex-1 min-w-0">

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
             class="mb-4 flex items-center gap-3 bg-[#00A859]/8 border border-[#00A859]/25
                    rounded-2xl px-4 py-3">
          <svg class="w-4 h-4 text-[#00A859] shrink-0 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          <p class="text-sm font-semibold text-[#00A859]">
            Datos autocompletados desde el reto vinculado. Revisa y ajusta lo que necesites.
          </p>
        </div>
      </Transition>

      <!-- Spinner carga inicial -->
      <div v-if="cargando" class="flex flex-col items-center justify-center py-32">
        <svg class="animate-spin w-10 h-10 text-[#00A859] mb-3" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
        </svg>
        <p class="text-[#00A859] font-black tracking-widest uppercase text-xs animate-pulse">Cargando...</p>
      </div>

      <template v-else>

        <!-- ═══ PASO 1: Básicos ═══ -->
        <div v-if="paso === 1">
          <div class="mb-6">
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Paso 1</span>
            </div>
            <h2 class="text-2xl font-black text-[#121212]">Datos básicos</h2>
            <p class="text-gray-500 text-sm mt-1">
              {{ uuid ? 'Revisa los datos de base de la propuesta.' : 'Elige el reto de la biblioteca al que responde esta propuesta y completa los datos de base.' }}
            </p>
          </div>

            <!-- Selector de reto (creación) -->
            <div v-if="!uuid"
                 class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-4 mb-4">

              <!-- Header -->
              <div class="flex items-start justify-between gap-3 pb-3 border-b border-gray-100">
                <div class="flex items-start gap-3">
                  <div class="w-8 h-8 rounded-xl bg-[#00A859]/15 border border-[#00A859]/30
                              flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.346.346a.5.5 0 01-.354.146H9.88a.5.5 0 01-.354-.146l-.345-.346z"/>
                    </svg>
                  </div>
                  <div>
                    <p class="text-xs font-black text-[#121212]">Reto <span class="text-red-500">*</span></p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Selecciona el reto de la biblioteca al que responde esta propuesta.</p>
                  </div>
                </div>
                <button v-if="microretoVinculado" @click="limpiarReto"
                        class="shrink-0 px-3 py-1.5 rounded-xl bg-white border border-gray-200
                               text-[10px] font-black uppercase tracking-widest text-gray-400
                               hover:border-[#00A859] hover:text-[#00A859] transition-all">
                  Cambiar
                </button>
              </div>

              <!-- Reto seleccionado -->
              <div v-if="microretoVinculado"
                   class="rounded-2xl border border-[#00A859]/30 bg-[#00A859]/5 p-4">
                <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-1">Seleccionado</p>
                <p class="text-sm font-black text-[#1F2937] leading-snug mb-2">{{ microretoVinculado.titulo }}</p>
                <div class="flex flex-wrap gap-1.5">
                  <span v-if="microretoVinculado.empresa?.nombre_comercial"
                        class="tag tag-gray">{{ microretoVinculado.empresa.nombre_comercial }}</span>
                  <span v-if="microretoVinculado.familia" class="tag tag-gray">{{ microretoVinculado.familia }}</span>
                  <span v-if="microretoVinculado.ciclo"   class="tag tag-gray">{{ microretoVinculado.ciclo }}</span>
                  <span v-if="microretoVinculado.curso"
                        class="tag tag-lime">{{ cursoLabel(microretoVinculado.curso) }}</span>
                </div>
              </div>

              <!-- Buscador + filtros + grid -->
              <div v-else class="space-y-3">

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
                          class="text-[11px] font-medium px-3 py-1.5 rounded-xl border border-gray-200
                                 bg-white text-gray-600 hover:border-gray-300 transition-colors focus:outline-none
                                 focus:ring-1 focus:ring-[#00A859]/40">
                    <option value="">Familia</option>
                    <option v-for="f in familiasFiltroRetos" :key="f" :value="f">{{ f }}</option>
                  </select>
                  <select v-model="retoFiltroCiclo"
                          class="text-[11px] font-medium px-3 py-1.5 rounded-xl border border-gray-200
                                 bg-white text-gray-600 hover:border-gray-300 transition-colors focus:outline-none
                                 focus:ring-1 focus:ring-[#00A859]/40">
                    <option value="">Ciclo</option>
                    <option v-for="c in ciclosFiltroRetos" :key="c" :value="c">{{ c }}</option>
                  </select>
                  <div class="flex rounded-xl border border-gray-200 overflow-hidden text-[11px] font-black uppercase tracking-widest">
                    <button v-for="op in ['', '1º', '2º', 'ambos_cursos']" :key="op"
                            @click="retoFiltroCurso = op"
                            :title="op === 'ambos_cursos' ? 'Ambos Cursos: posibilidad 1º y 2º' : ''"
                            :class="['px-3 py-1.5 transition-colors',
                                     retoFiltroCurso === op
                                       ? 'bg-[#00A859] text-white'
                                       : 'bg-white text-gray-400 hover:bg-gray-50']">
                      {{ op === '' ? 'Todos' : op === 'ambos_cursos' ? 'Ambos Cursos' : op }}
                    </button>
                  </div>
                  <button v-if="retoBusqueda || retoFiltroFamilia || retoFiltroCiclo || retoFiltroCurso"
                          @click="retoBusqueda = ''; retoFiltroFamilia = ''; retoFiltroCiclo = ''; retoFiltroCurso = ''"
                          class="text-[11px] font-black uppercase tracking-widest px-3 py-1.5 rounded-xl
                                 border border-gray-200 text-gray-400 hover:border-red-200 hover:text-red-400
                                 transition-colors">
                    Limpiar
                  </button>
                </div>

                <!-- Contador -->
                <p class="text-[10px] text-gray-400 font-medium">
                  {{ retosFiltrados.length }} reto{{ retosFiltrados.length !== 1 ? 's' : '' }}
                </p>

                <!-- Sin resultados -->
                <p v-if="!retosFiltrados.length"
                   class="text-xs text-gray-400 font-medium text-center py-8">
                  Sin resultados para los filtros actuales.
                </p>

                <!-- Grid de miniaturas -->
                <div v-else class="grid sm:grid-cols-2 gap-2 max-h-[26rem] overflow-y-auto pr-1 -mr-1">
                  <div v-for="mr in retosFiltrados" :key="mr.id"
                       class="relative rounded-2xl border border-gray-100 bg-gray-50
                              hover:border-[#00A859]/40 hover:bg-[#00A859]/5 hover:shadow-sm
                              transition-all group">
                    <button @click="seleccionarReto(mr)"
                            class="text-left w-full px-4 pt-3.5 pb-10">
                      <div class="flex items-start justify-between gap-2 mb-1.5">
                        <p class="text-sm font-black text-[#1F2937] leading-snug line-clamp-2
                                  group-hover:text-[#005c2e] transition-colors">
                          {{ mr.titulo }}
                        </p>
                        <span v-if="mr.curso"
                              class="shrink-0 text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full
                                     bg-[#99CC33]/20 text-[#4a6600] border border-[#99CC33]/30">
                          {{ cursoLabel(mr.curso) }}
                        </span>
                      </div>
                      <p v-if="mr.pregunta_reto"
                         class="text-[11px] text-gray-400 leading-relaxed line-clamp-2 mb-2">
                        {{ mr.pregunta_reto }}
                      </p>
                      <div class="flex flex-wrap gap-1">
                        <span v-if="mr.empresa?.nombre_comercial"
                              class="tag tag-gray">{{ mr.empresa.nombre_comercial }}</span>
                        <span v-if="mr.familia" class="tag tag-gray">{{ mr.familia }}</span>
                        <span v-if="mr.ciclo"   class="tag tag-gray text-[9px]">{{ mr.ciclo }}</span>
                      </div>
                    </button>
                    <button @click.stop="microretoModalId = mr.id"
                            class="absolute bottom-2.5 right-3 flex items-center gap-1
                                   text-[10px] font-black uppercase tracking-widest
                                   text-gray-400 hover:text-[#00A859] transition-colors">
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

              </div>
            </div>

            <!-- Resto de campos básicos -->
            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-5">
              <div>
                <label class="field-label">Título de la propuesta *</label>
                <input v-model="form.titulo" type="text" required class="field-input"
                       placeholder="Ej: Rediseño de packaging sostenible para EcoFab" />
              </div>
              <div class="grid sm:grid-cols-2 gap-4">

                <!-- Empresa: read-only del reto -->
                <div>
                  <label class="field-label flex items-center gap-1.5">
                    Empresa colaboradora
                    <span v-if="microretoVinculado"
                          class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-[#00A859]/10 text-[#00A859] border border-[#00A859]/20">
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
                          class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-[#00A859]/10 text-[#00A859] border border-[#00A859]/20">
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
                          class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-[#00A859]/10 text-[#00A859] border border-[#00A859]/20">
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
                          class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-[#00A859]/10 text-[#00A859] border border-[#00A859]/20">
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
                          class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5
                                 rounded-full bg-[#00A859]/10 text-[#00A859] border border-[#00A859]/20">
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
                  <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Datos de Docente Responsable</p>
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
                El número de equipos y el alumnado se configura en el
                <strong class="text-[#1F2937]">registro de sesión</strong>
                del dashboard. Desde
                <strong class="text-[#1F2937]">Sesiones registradas</strong>
                podrás generar el código de acceso una vez la propuesta sea validada y se convierta en proyecto.
              </p>
            </div>

            <div class="flex justify-end mt-5">
              <button @click="guardar(2)"
                      :disabled="!form.titulo.trim() || !form.microreto_id || guardando"
                      class="btn-primary">
                {{ guardando ? 'Guardando…' : 'Siguiente →' }}
              </button>
            </div>
        </div>

        <!-- ═══ PASO 2: Empresa ═══ -->
        <div v-if="paso === 2">
          <div class="mb-6">
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Paso 2</span>
            </div>
            <h2 class="text-2xl font-black text-[#121212]">Datos de la empresa</h2>
            <p class="text-gray-500 text-sm mt-1">
              {{ empresaDesdeReto ? 'Información autocompleta desde el reto. Solo lectura.' : 'Confirma o completa la información de la empresa colaboradora.' }}
            </p>
          </div>

          <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-4">

            <!-- Cabecera "Del reto" cuando es read-only -->
            <div v-if="empresaDesdeReto"
                 class="flex items-center gap-2 px-3 py-2 rounded-xl bg-[#00A859]/8
                        border border-[#00A859]/20 text-[11px] font-bold text-[#00A859]">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              Datos heredados del reto — no editables
            </div>

            <!-- Read-only (empresa del reto) -->
            <template v-if="empresaDesdeReto">
              <div class="grid sm:grid-cols-2 gap-4">
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
                      <span class="truncate">{{ val || '—' }}</span>
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
              <div class="grid sm:grid-cols-2 gap-4">
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

          <div class="flex justify-between mt-5">
            <button @click="paso = 1" class="btn-secondary">← Anterior</button>
            <button @click="guardar(3)" :disabled="guardando" class="btn-primary">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 3: Módulos y RA/CE ═══ -->
        <div v-if="paso === 3">
          <div class="mb-6">
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Paso 3</span>
            </div>
            <h2 class="text-2xl font-black text-[#121212]">Módulos y currículum</h2>
            <p class="text-gray-500 text-sm mt-1">Selecciona los módulos del ciclo que se trabajan en esta propuesta.</p>
          </div>

          <div class="space-y-4">

            <!-- Callout módulos autocompletados -->
            <Transition enter-active-class="transition-all duration-300"
                        enter-from-class="opacity-0 -translate-y-2"
                        leave-active-class="transition-all duration-200"
                        leave-to-class="opacity-0 -translate-y-2">
              <div v-if="modulosAutocompletados"
                   class="flex items-start gap-3 bg-[#00A859]/8 border border-[#00A859]/25
                          rounded-2xl px-4 py-3">
                <svg class="w-4 h-4 text-[#00A859] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-black text-[#00A859] mb-0.5">Módulos seleccionados automáticamente</p>
                  <p class="text-xs text-[#00A859]/70 leading-relaxed">
                    Esto se basa en el reto vinculado. Si crees que se trabajan otros módulos,
                    revisa el reto para que esté acorde, o ajusta la selección manualmente.
                  </p>
                </div>
                <button @click="modulosAutocompletados = false"
                        class="shrink-0 text-[#00A859]/40 hover:text-[#00A859] transition-colors mt-0.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </Transition>

            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-4">Módulos formativos</p>
              <div v-if="!modulos.length" class="text-center py-8 text-gray-400 text-sm">
                {{ form.ciclo_id ? 'Cargando módulos…' : 'Selecciona un ciclo en el paso 1 para ver los módulos' }}
              </div>
              <div v-else class="grid sm:grid-cols-2 gap-2">
                <button v-for="m in modulos" :key="m.id" @click="toggleModulo(m)"
                        :class="[
                          'text-left px-4 py-3 rounded-2xl border text-sm font-semibold transition-all',
                          moduloSeleccionado(m.id)
                            ? 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859]'
                            : 'bg-gray-50 border-gray-200 text-gray-600 hover:border-[#00A859]/40 hover:text-[#1F2937]'
                        ]">
                  <span class="text-[10px] text-gray-400 block mb-0.5 font-normal">{{ m.codigoBOE }}</span>
                  {{ m.nombre }}
                </button>
              </div>
            </div>

            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6">
              <!-- Cabecera -->
              <div class="flex items-start justify-between gap-3 mb-4">
                <label class="field-label">Resultados de Aprendizaje y Criterios de Evaluación</label>
                <Transition enter-active-class="transition-all duration-300"
                            enter-from-class="opacity-0 scale-75"
                            leave-active-class="transition-all duration-200"
                            leave-to-class="opacity-0 scale-75">
                  <span v-if="raCeAutocompletado"
                        class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-full
                               bg-amber-50 border border-amber-200 text-amber-600
                               text-[9px] font-black uppercase tracking-widest">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400" />
                    Del reto
                  </span>
                </Transition>
              </div>

              <!-- Callout autocompletado -->
              <Transition enter-active-class="transition-all duration-300"
                          enter-from-class="opacity-0 -translate-y-2"
                          leave-active-class="transition-all duration-200"
                          leave-to-class="opacity-0 -translate-y-2">
                <div v-if="raCeAutocompletado"
                     class="flex items-start gap-3 bg-amber-50 border border-amber-200
                            rounded-2xl px-4 py-3 mb-4">
                  <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <p class="text-xs text-amber-700 leading-relaxed flex-1">
                    RA y CE generados a partir del reto vinculado. Revisa que estén alineados con tu
                    programación o ajusta la selección.
                  </p>
                  <button @click="raCeAutocompletado = false"
                          class="shrink-0 text-amber-400 hover:text-amber-600 transition-colors mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </Transition>

              <!-- Selector de modo -->
              <div class="flex gap-2 mb-5 p-1 bg-gray-100 rounded-2xl">
                <button v-for="modo in [
                    { key: 'ia',     label: 'Sugerir con IA',   icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z' },
                    { key: 'texto',  label: 'Datos de la propuesta', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
                  ]" :key="modo.key" @click="modoRaCe = modo.key"
                  :class="[
                    'flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all',
                    modoRaCe === modo.key
                      ? 'bg-white shadow text-[#00A859]'
                      : 'text-gray-500 hover:text-gray-700'
                  ]">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="modo.icon"/>
                  </svg>
                  <span class="hidden sm:inline">{{ modo.label }}</span>
                </button>
              </div>

              <!-- ── MODO IA ── -->
              <div v-if="modoRaCe === 'ia'" class="space-y-4">
                <div class="bg-violet-50 border border-violet-200 rounded-2xl px-4 py-3 text-sm text-violet-700 leading-relaxed">
                  La IA analizará el contexto de la propuesta y los módulos seleccionados para sugerir los RA y CE más relevantes del catálogo oficial.
                </div>
                <button @click="sugerirRaCeConIa"
                        :disabled="cargandoIaRaCe || !form.modulos_seleccionados.length"
                        class="w-full py-4 rounded-2xl text-sm font-black tracking-wide transition-all flex items-center justify-center gap-2
                               bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-40">
                  <template v-if="cargandoIaRaCe">
                    <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24">
                      <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
                    </svg>
                    Analizando currículum…
                  </template>
                  <template v-else>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    Sugerir otros con IA
                  </template>
                </button>
                <p v-if="!form.modulos_seleccionados.length" class="text-xs text-gray-400 text-center">
                  Selecciona al menos un módulo para usar esta opción.
                </p>
              </div>

              <!-- ── MODO DATOS DE LA PROPUESTA ── -->
              <div v-else class="space-y-5">

                <!-- Grid(s) de RA/CE: del reto vinculado y/o de esta propuesta -->
                <div>

                  <!-- Aviso: la propuesta ya no coincide con el original del reto -->
                  <div v-if="raCeDivergeDelReto"
                       class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3 mb-3">
                    <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <p class="text-xs text-amber-700 leading-relaxed">
                      Si decides añadir otros RA/CE y/o cambiar los existentes, estos no coincidirá con los que aparecen en la ficha del reto asociado.
                      Ten en cuenta que la sección "RA/CE de esta propuesta" contendrán los definitivos, aunque en el reto original puedan aparecer otros
                      (los que la IA sugirió originalmente en la generación del reto). Revisa ambas secciones
                    </p>
                  </div>

                  <!-- Aviso: selección hecha por IA, revisar -->
                  <div v-if="raCeDelReto.length"
                       class="flex items-start gap-2.5 bg-blue-50 border border-blue-200 rounded-2xl px-4 py-3 mb-3">
                    <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-xs text-blue-700 leading-relaxed">
                      Esta selección de RA/CE la hizo la IA al generar el reto — revísala antes de publicar la propuesta.
                    </p>
                  </div>

                  <!-- RA/CE originales del reto asociado (solo lectura, siempre refleja el reto) -->
                  <div class="mb-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">
                      RA/CE originales del reto asociado
                    </p>
                    <RaCeGrid v-if="raCeDelReto.length" :items="raCeDelReto" />

                    <!-- Sin reto vinculado -->
                    <div v-else-if="!microretoVinculado"
                         class="py-8 text-center text-sm text-gray-400 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                      Esta propuesta no tiene un reto vinculado — no hay RA/CE de referencia.
                    </div>

                    <!-- Reto vinculado pero sin RA/CE cargados en BD -->
                    <div v-else
                         class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3">
                      <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                      </svg>
                      <p class="text-xs text-amber-700 leading-relaxed">
                        El reto asociado no tiene RA/CE oficiales asignados — probablemente el módulo aún no tiene
                        currículo cargado en la base de datos (pendiente de importar del BOE). Añádelos manualmente
                        con "Seleccionar otros manualmente" o "Añadir RA/CE manualmente".
                      </p>
                    </div>
                  </div>

                  <!-- RA/CE de esta propuesta (editable — lo que realmente se guarda) -->
                  <div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">
                      RA/CE de esta propuesta
                    </p>

                    <!-- Aviso: contenido sugerido por IA, revisar -->
                    <div v-if="sugerenciaIaPendienteRevision"
                         class="flex items-start gap-2.5 bg-blue-50 border border-blue-200 rounded-2xl px-4 py-3 mb-3">
                      <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      <p class="text-xs text-blue-700 leading-relaxed">
                        La IA ha añadido nuevos RA/CE a esta sección — revísalos y quita con el botón × los que no encajen.
                      </p>
                    </div>

                    <RaCeGrid v-if="raCeDeEsteProyecto.length" :items="raCeDeEsteProyecto"
                              editable @remove-ce="eliminarCeDeProyecto" />
                    <div v-else
                         class="py-4 text-center text-xs text-gray-400 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                      Sin RA/CE en esta propuesta todavía.
                    </div>
                  </div>
                </div>

                <!-- Desplegable: Seleccionar otros manualmente -->
                <div class="rounded-2xl border border-gray-100 overflow-hidden">
                  <button @click="mostrarSeleccionManual = !mostrarSeleccionManual"
                          class="w-full flex items-center justify-between gap-2 px-4 py-3
                                 bg-gray-50/80 hover:bg-gray-100 transition-colors">
                    <span class="flex items-center gap-2 text-xs font-bold text-gray-700">
                      <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                      </svg>
                      Seleccionar otros manualmente
                    </span>
                    <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 transition-transform duration-200"
                         :class="mostrarSeleccionManual ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </button>

                  <div v-if="mostrarSeleccionManual" class="p-4 border-t border-gray-100">

                    <!-- Cargando -->
                    <div v-if="cargandoCatalogo" class="flex flex-col items-center justify-center py-12 gap-3">
                      <div class="w-8 h-8 rounded-full border-4 border-[#00A859]/20 border-t-[#00A859] animate-spin" />
                      <p class="text-sm text-gray-400">Cargando catálogo…</p>
                    </div>

                    <!-- Sin módulos seleccionados -->
                    <div v-else-if="!catalogoRaCe.length"
                         class="py-8 text-center text-sm text-gray-400 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                      <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                      </svg>
                      Selecciona módulos en la sección superior para ver su catálogo de RA y CE.
                    </div>

                    <div v-else>
                      <!-- Aviso: los ya marcados coinciden con el reto -->
                      <div class="flex items-start gap-2.5 bg-[#00A859]/8 border border-[#00A859]/20 rounded-2xl px-4 py-3 mb-4">
                        <svg class="w-4 h-4 text-[#00A859] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-xs text-[#00A859]/80 leading-relaxed">
                          Los CE ya marcados coinciden con los del reto asociado. Añade otros marcando los que falten.
                        </p>
                      </div>

                      <!-- Buscador -->
                      <div class="relative mb-4">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input v-model="busquedaRaCe" type="text"
                               placeholder="Buscar resultado de aprendizaje o criterio de evaluación…"
                               class="w-full pl-9 pr-8 py-2.5 text-sm bg-gray-50 border border-gray-200
                                      rounded-2xl focus:outline-none focus:border-[#00A859]/50 focus:bg-white
                                      transition-colors placeholder-gray-400" />
                        <button v-if="busquedaRaCe" @click="busquedaRaCe = ''"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                          </svg>
                        </button>
                      </div>

                      <!-- Barra de contadores -->
                      <div class="flex flex-wrap items-center gap-3 px-4 py-2 mb-3 bg-indigo-50/60 border border-indigo-100 rounded-2xl">
                        <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mr-1">Selección:</span>
                        <div class="flex items-center gap-1.5">
                          <span class="w-1.5 h-1.5 rounded-full bg-[#00A859] shrink-0" />
                          <span class="text-[11px] font-black text-[#00A859]">{{ totalRaSeleccionados }}</span>
                          <span class="text-[11px] text-[#00A859]/70">RA</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                          <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
                          <span class="text-[11px] font-black text-amber-600">{{ totalCeSeleccionados }}</span>
                          <span class="text-[11px] text-amber-500/70">CE</span>
                        </div>
                      </div>

                      <!-- Sin resultados de búsqueda -->
                      <div v-if="!catalogoFiltrado.length"
                           class="py-6 text-center text-sm text-gray-400 bg-gray-50 rounded-2xl">
                        Sin resultados para "<span class="font-semibold">{{ busquedaRaCe }}</span>"
                      </div>

                      <!-- Lista de módulos al estilo CatalogoBoeModal -->
                      <div v-else class="space-y-2">
                        <div v-for="mod in catalogoFiltrado" :key="mod.moduloId"
                             class="rounded-2xl border border-gray-100 overflow-hidden">

                          <!-- Cabecera módulo -->
                          <div class="flex items-center gap-2 px-4 py-3 bg-gray-50/80 border-b border-gray-100">
                            <span class="text-[10px] font-black uppercase tracking-widest
                                         bg-indigo-100/60 text-indigo-500 px-2 py-0.5 rounded-full shrink-0">MF</span>
                            <span class="flex-1 text-xs font-bold text-gray-700">{{ mod.modulo }}</span>
                            <span class="text-[10px] text-gray-400">{{ mod.ras.length }} RA</span>
                          </div>

                          <!-- Resultados de Aprendizaje -->
                          <div class="divide-y divide-gray-50">
                            <div v-for="ra in mod.ras" :key="ra.id"
                                 class="overflow-hidden"
                                 :class="raEstado(ra) !== 'none' ? 'bg-[#00A859]/4' : ''">

                              <!-- Fila RA -->
                              <div class="flex items-start gap-2.5 px-4 py-3">
                                <!-- Checkbox RA -->
                                <button @click="toggleRa(ra)"
                                        class="mt-0.5 shrink-0 w-4 h-4 rounded border-2 flex items-center justify-center transition-all"
                                        :class="raEstado(ra) === 'all'
                                          ? 'bg-[#00A859] border-[#00A859]'
                                          : raEstado(ra) === 'some'
                                            ? 'bg-[#00A859]/30 border-[#00A859]'
                                            : 'bg-white border-gray-300 hover:border-[#00A859]/50'">
                                  <svg v-if="raEstado(ra) !== 'none'" class="w-2.5 h-2.5 text-white"
                                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path v-if="raEstado(ra) === 'all'" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 12h14"/>
                                  </svg>
                                </button>
                                <!-- Badge RA + texto (igual que CatalogoBoeModal) -->
                                <div class="flex items-center gap-1.5 shrink-0 mt-0.5">
                                  <span class="text-[9px] font-black uppercase tracking-widest text-[#00A859]
                                               bg-[#00A859]/10 px-2 py-0.5 rounded-full">RA{{ ra.orden }}</span>
                                  <span class="text-[9px] text-gray-300">#{{ ra.id }}</span>
                                </div>
                                <p class="flex-1 text-[11px] font-semibold text-gray-700 leading-snug">{{ ra.descripcion }}</p>
                                <!-- Expand toggle -->
                                <button @click="raExpandido[ra.id] = !raExpandido[ra.id]"
                                        class="shrink-0 text-gray-400 hover:text-gray-600 transition-colors mt-0.5 ml-1">
                                  <svg class="w-3.5 h-3.5 transition-transform duration-200"
                                       :class="raExpandido[ra.id] ? 'rotate-180' : ''"
                                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                  </svg>
                                </button>
                              </div>

                              <!-- Criterios de Evaluación (igual que CatalogoBoeModal) -->
                              <div v-if="raExpandido[ra.id]"
                                   class="border-t border-[#00A859]/15 px-4 pb-3 pt-2.5 bg-white/60">
                                <div class="flex items-center gap-1.5 mb-2">
                                  <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
                                  <span class="text-[9px] font-black uppercase tracking-widest text-amber-600">
                                    Criterios de Evaluación
                                  </span>
                                </div>
                                <div class="space-y-1.5 pl-2">
                                  <label v-for="ce in ra.criterios" :key="ce.id"
                                         class="flex items-start gap-2.5 cursor-pointer group">
                                    <div class="mt-0.5 shrink-0 w-3.5 h-3.5 rounded border-2 flex items-center justify-center transition-all"
                                         :class="ceChecked[ce.id]
                                           ? 'bg-amber-400 border-amber-400'
                                           : 'bg-white border-gray-300 group-hover:border-amber-300'">
                                      <svg v-if="ceChecked[ce.id]" class="w-2 h-2 text-white"
                                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                      </svg>
                                    </div>
                                    <input type="checkbox" v-model="ceChecked[ce.id]" class="sr-only" />
                                    <span class="text-[10px] text-gray-600 leading-snug">
                                      <span class="font-bold text-amber-500 mr-1">{{ ce.orden }}.</span>{{ ce.descripcion }}
                                    </span>
                                  </label>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Pie: botón aplicar -->
                      <div class="mt-4 flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-400">
                          <template v-if="totalCeSeleccionados > 0">
                            {{ totalCeSeleccionados }} CE de {{ totalRaSeleccionados }} RA seleccionados
                          </template>
                          <template v-else>
                            Marca los CE que se trabajarán en la propuesta.
                          </template>
                        </p>
                        <button @click="aplicarSeleccionManual"
                                :disabled="totalCeSeleccionados === 0"
                                class="px-5 py-3 bg-[#00A859] text-white rounded-2xl text-sm font-black tracking-wide
                                       hover:bg-[#00A859]/90 disabled:opacity-40 transition-colors shrink-0">
                          Aplicar selección →
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Añadir RA/CE manualmente -->
                <div>
                  <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2">
                    Añadir RA/CE manualmente
                  </p>
                  <p class="text-xs text-gray-400 mb-3">
                    Para RA/CE que no aparecen en el catálogo oficial del módulo. Se añaden directamente a "RA/CE de esta propuesta".
                  </p>

                  <div class="rounded-2xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50/80 border-b border-gray-100">
                      <select v-model="nuevoRaManual.moduloId" class="field-input">
                        <option value="" disabled>Selecciona un módulo…</option>
                        <option v-for="m in form.modulos_seleccionados" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                      </select>
                    </div>

                    <div class="p-4 space-y-3 bg-[#00A859]/4">
                      <div class="flex items-start gap-2.5">
                        <span class="text-[9px] font-black uppercase tracking-widest text-[#00A859]
                                     bg-[#00A859]/10 px-2 py-0.5 rounded-full shrink-0 mt-2.5">RA</span>
                        <input v-model="nuevoRaManual.ra" type="text"
                               placeholder="Describe el resultado de aprendizaje…"
                               class="field-input flex-1" />
                      </div>

                      <div class="pl-1">
                        <div class="flex items-center gap-1.5 mb-1.5">
                          <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
                          <span class="text-[9px] font-black uppercase tracking-widest text-amber-600">
                            Criterios de Evaluación
                          </span>
                        </div>
                        <div class="space-y-2 pl-2">
                          <div v-for="(ce, i) in nuevoRaManual.ces" :key="i" class="flex items-center gap-2">
                            <input v-model="nuevoRaManual.ces[i]" type="text" :placeholder="`Criterio ${i + 1}…`"
                                   class="field-input flex-1" />
                            <button v-if="nuevoRaManual.ces.length > 1" @click="removeCeManualField(i)"
                                    class="text-gray-400 hover:text-red-500 shrink-0 font-bold">×</button>
                          </div>
                        </div>
                        <button @click="addCeManualField"
                                class="mt-2 text-[11px] font-bold text-amber-600 hover:text-amber-700 transition-colors">
                          + Añadir otro criterio
                        </button>
                      </div>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 flex justify-end">
                      <button @click="anadirRaManualAlProyecto" :disabled="!raManualValido"
                              class="px-5 py-2.5 bg-[#00A859] text-white rounded-2xl text-sm font-black tracking-wide
                                     hover:bg-[#00A859]/90 disabled:opacity-40 transition-colors">
                        Añadir a esta propuesta →
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-between mt-5">
            <button @click="paso = 2" class="btn-secondary">← Anterior</button>
            <button @click="() => { if (mostrarSeleccionManual && totalCeSeleccionados > 0) aplicarSeleccionManual(); guardar(4); }" :disabled="guardando" class="btn-primary">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 4: El Reto ═══ -->
        <div v-if="paso === 4">
          <div class="mb-6">
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Paso 4</span>
            </div>
            <h2 class="text-2xl font-black text-[#121212]">El reto</h2>
            <p class="text-gray-500 text-sm mt-1">Define el contexto, la fundamentación y el reto central de la propuesta.</p>
          </div>

          <div class="space-y-4">
            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div class="flex items-center justify-between">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Fundamentación</p>
                <button @click="sugerirFundamentacion" :disabled="sugiriendoFundamentacion"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                               bg-violet-50 border border-violet-200 text-violet-700
                               text-[10px] font-black uppercase tracking-wider
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

            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-4">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Diseño del reto</p>
              <div><label class="field-label">Descripción del reto *</label>
                <textarea v-model="form.diseno_reto.descripcion" rows="3" class="field-input resize-none" required
                          placeholder="Describe el reto al que se enfrenta el equipo…" /></div>
              <div><label class="field-label">Pregunta reto</label>
                <input v-model="form.diseno_reto.pregunta_reto" type="text" class="field-input"
                       placeholder="¿Cómo podríamos…?" /></div>
              <div><label class="field-label">Restricciones y condicionantes</label>
                <textarea v-model="form.diseno_reto.restricciones" rows="2" class="field-input resize-none"
                          placeholder="Presupuesto, plazos, materiales disponibles…" /></div>
              <div><label class="field-label">Entregables esperados</label>
                <textarea v-model="form.diseno_reto.entregables" rows="2" class="field-input resize-none"
                          placeholder="¿Qué debe entregar el equipo al final de la propuesta?" /></div>
            </div>
          </div>

          <div class="flex justify-between mt-5">
            <button @click="paso = 3" class="btn-secondary">← Anterior</button>
            <button @click="guardar(5)" :disabled="guardando" class="btn-primary">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 5: Diseño de la propuesta ═══ -->
        <div v-if="paso === 5">
          <div class="mb-6">
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Paso 5</span>
            </div>
            <h2 class="text-2xl font-black text-[#121212]">Diseño de la propuesta</h2>
            <p class="text-gray-500 text-sm mt-1">Define las fases, metodología y cronograma del trabajo.</p>
          </div>

          <div class="space-y-4">
            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Fases de la propuesta</p>
                <p class="text-xs text-gray-400 mt-1">Son las mismas 5 fases que el equipo recorrerá en su workspace. La duración de cada una se calcula sola según el calendario de sesiones de abajo.</p>
              </div>
              <div class="space-y-2">
                <div v-for="f in FASES_PROYECTO" :key="f.num"
                     class="rounded-2xl border p-3 sm:p-4 flex items-center gap-3 sm:gap-4" :class="colorMapFases[f.color]">
                  <span class="text-lg leading-none shrink-0">{{ f.icono }}</span>
                  <div class="flex-1 min-w-0">
                    <p class="font-black text-sm text-[#1F2937]">F{{ f.num }} - {{ f.label }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ f.descLarga }}</p>
                  </div>
                  <span class="text-[11px] font-bold uppercase tracking-wide opacity-80 shrink-0 whitespace-nowrap">
                    {{ duracionPorFase(form.diseno_microproyecto.clases, f.num) }} sesión(es)
                  </span>
                </div>
              </div>

              <div class="pt-2 border-t border-gray-100">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Modificar duración de cada fase</p>
                <p class="text-xs text-gray-400 mt-1 mb-3">Marca qué fase(s) se trabajan en cada sesión — una misma sesión puede cubrir varias fases.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div v-for="(c, i) in form.diseno_microproyecto.clases" :key="i"
                       class="rounded-2xl border border-gray-200 bg-white p-4 space-y-3">
                    <div class="flex items-center justify-between">
                      <p class="text-sm font-black text-[#1F2937]">Sesión {{ i + 1 }}</p>
                      <button @click="removeSesion(i)" type="button"
                              class="text-gray-300 hover:text-red-500 font-black text-sm leading-none">×</button>
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
                        <span v-if="c.fases.includes(f.num)" class="text-[#00A859] font-black text-sm shrink-0">✓</span>
                      </button>
                    </div>
                  </div>
                </div>
                <button @click="addSesion" type="button"
                        class="mt-3 px-4 py-2 rounded-xl border border-dashed border-[#00A859]/40 text-[#00A859]
                               text-xs font-black uppercase tracking-widest hover:bg-[#00A859]/5 transition-all">
                  + Añadir sesión
                </button>
                <p class="text-xs font-bold text-[#1F2937] mt-3">Total: {{ form.diseno_microproyecto.clases.length }} sesión(es)</p>
              </div>
            </div>

            <!-- Cronograma general — hitos derivados de las fases + el calendario de arriba -->
            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Cronograma general</p>
                <p class="text-xs text-gray-400 mt-1">Hito a conseguir en cada fase, según el calendario de sesiones de arriba.</p>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div v-for="f in FASES_PROYECTO" :key="f.num"
                     class="rounded-2xl border p-3 space-y-1" :class="colorMapFases[f.color]">
                  <div class="flex items-center gap-1.5">
                    <span class="text-base leading-none">{{ f.icono }}</span>
                    <p class="font-black text-xs text-[#1F2937]">{{ f.label }}</p>
                  </div>
                  <p class="text-[9px] font-bold uppercase tracking-wide opacity-70">
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

            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-4">
              <div class="flex items-center justify-between">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Metodología y resumen ejecutivo</p>
                <button @click="sugerirMetodologia" :disabled="sugiriendoMetodologia"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                               bg-violet-50 border border-violet-200 text-violet-700
                               text-[10px] font-black uppercase tracking-wider
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
                            placeholder="Describe cómo se organizará el trabajo del equipo…" />
                </div>
                <div>
                  <label class="field-label">Resumen ejecutivo</label>
                  <textarea v-model="form.resumen.texto" rows="6" class="field-input resize-none"
                            placeholder="Síntesis de la propuesta para implementar...." />
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-between mt-5">
            <button @click="paso = 4" class="btn-secondary">← Anterior</button>
            <button @click="guardar(6)" :disabled="guardando" class="btn-primary">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 6: Objetivos y KPIs ═══ -->
        <div v-if="paso === 6">
          <div class="mb-6">
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Paso 6</span>
            </div>
            <h2 class="text-2xl font-black text-[#121212]">Objetivos y KPIs</h2>
            <p class="text-gray-500 text-sm mt-1">Define los objetivos de la propuesta y los indicadores de éxito.</p>
          </div>

          <div class="space-y-4">
            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-3">
              <div class="flex items-center justify-between">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Objetivos</p>
                <button @click="sugerirObjetivos" :disabled="sugiriendoObjetivos"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                               bg-violet-50 border border-violet-200 text-violet-700
                               text-[10px] font-black uppercase tracking-wider
                               hover:bg-violet-100 transition-all active:scale-95
                               disabled:opacity-60 disabled:cursor-not-allowed">
                  <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': sugiriendoObjetivos }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M17.657 18.364l-.707-.707M12 20v1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l-.707-.707"/>
                  </svg>
                  {{ sugiriendoObjetivos ? 'Generando…' : 'Sugerir con IA' }}
                </button>
              </div>
              <p v-if="errorObjetivos" class="text-xs text-red-500">{{ errorObjetivos }}</p>
              <div class="flex gap-2">
                <input v-model="nuevoObjetivo" type="text" placeholder="Añadir objetivo…"
                       class="field-input flex-1" @keyup.enter="addObjetivo" />
                <button @click="addObjetivo"
                        class="shrink-0 px-4 py-2.5 bg-[#00A859] text-white rounded-2xl
                               text-sm font-black hover:bg-[#00A859]/90 transition-all active:scale-95">+</button>
              </div>
              <ul v-if="form.objetivos.lista.length" class="space-y-2">
                <li v-for="(obj, i) in form.objetivos.lista" :key="i"
                    class="flex items-center gap-2 text-sm text-[#1F2937]">
                  <span class="text-[#00A859] font-black shrink-0">›</span>
                  <span class="flex-1">{{ obj }}</span>
                  <button @click="removeObjetivo(i)" class="text-gray-400 hover:text-red-500 font-bold">×</button>
                </li>
              </ul>
              <p v-else class="text-xs text-gray-400 italic">Añade al menos un objetivo</p>
            </div>

            <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 space-y-3">
              <div class="flex items-center justify-between">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Indicadores de éxito (KPIs)</p>
                <button @click="sugerirKpis" :disabled="sugirendoKpis"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                               bg-violet-50 border border-violet-200 text-violet-700
                               text-[10px] font-black uppercase tracking-wider
                               hover:bg-violet-100 transition-all active:scale-95
                               disabled:opacity-60 disabled:cursor-not-allowed">
                  <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': sugirendoKpis }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M17.657 18.364l-.707-.707M12 20v1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l-.707-.707"/>
                  </svg>
                  {{ sugirendoKpis ? 'Generando…' : 'Sugerir con IA' }}
                </button>
              </div>
              <p v-if="errorKpis" class="text-xs text-red-500">{{ errorKpis }}</p>
              <div class="flex gap-2">
                <input v-model="nuevoKpi" type="text" placeholder="Añadir KPI o indicador…"
                       class="field-input flex-1" @keyup.enter="addKpi" />
                <button @click="addKpi"
                        class="shrink-0 px-4 py-2.5 bg-[#00A859] text-white rounded-2xl
                               text-sm font-black hover:bg-[#00A859]/90 transition-all active:scale-95">+</button>
              </div>
              <ul v-if="form.kpis.lista.length" class="space-y-2">
                <li v-for="(kpi, i) in form.kpis.lista" :key="i"
                    class="flex items-center gap-2 text-sm text-[#1F2937]">
                  <span class="text-[#99CC33] font-black shrink-0">✓</span>
                  <span class="flex-1">{{ kpi }}</span>
                  <button @click="removeKpi(i)" class="text-gray-400 hover:text-red-500 font-bold">×</button>
                </li>
              </ul>
              <p v-else class="text-xs text-gray-400 italic">Los KPIs son opcionales pero recomendados para la validación empresa. Usa la IA para generar sugerencias.</p>
            </div>
          </div>

          <div class="flex justify-between mt-5">
            <button @click="paso = 5" class="btn-secondary">← Anterior</button>
            <button @click="guardar(7)" :disabled="guardando" class="btn-primary">{{ guardando ? 'Guardando…' : 'Siguiente →' }}</button>
          </div>
        </div>

        <!-- ═══ PASO 7: Publicar ═══ -->
        <div v-if="paso === 7">

          <!-- ── Estado de éxito tras publicar ── -->
          <div v-if="publicadoExito" class="flex flex-col items-center text-center py-8 gap-6">
            <div class="w-20 h-20 rounded-full bg-[#00A859]/10 border-2 border-[#00A859]/20 flex items-center justify-center">
              <svg class="w-10 h-10 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            </div>
            <div>
              <h2 class="text-2xl font-black text-[#121212]">¡Propuesta lista!</h2>
              <p class="text-gray-500 text-sm mt-1.5 max-w-sm mx-auto">
                El enlace de validación ya está listo. Puedes enviárselo a la empresa por correo para que valide la propuesta.
                Cuando la valide, pasará a llamarse <strong>proyecto</strong>.
              </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full max-w-sm">
              <button v-if="form.empresa_id"
                      @click="modalPropuestaAviso = true"
                      class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 bg-amber-500 text-white
                             rounded-full text-xs font-black uppercase tracking-widest shadow-sm
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
            <div class="inline-flex items-center gap-2 mb-2 px-3 py-1 rounded-full bg-[#00A859]/10 border border-[#00A859]/20">
              <span class="text-[10px] font-black uppercase tracking-widest text-[#00A859]">Paso 7</span>
            </div>
            <h2 class="text-2xl font-black text-[#121212]">Publicar propuesta</h2>
            <p class="text-gray-500 text-sm mt-1">
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
          <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 mb-4">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-4">Resumen de la propuesta</p>
            <div class="grid sm:grid-cols-2 gap-4 text-sm">
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Título</p>
                <p class="font-bold text-[#1F2937]">{{ form.titulo || '—' }}</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Empresa</p>
                <p class="font-bold text-[#1F2937]">{{ form.datos_empresa.nombre || '—' }}</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Docente</p>
                <p class="font-bold text-[#1F2937]">{{ form.datos_centro.docente_nombre || '—' }}</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Módulos</p>
                <p class="font-bold text-[#1F2937]">{{ form.modulos_seleccionados.length }} seleccionado(s)</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Objetivos</p>
                <p class="font-bold text-[#1F2937]">{{ form.objetivos.lista.length }} definido(s)</p>
              </div>
              <div class="p-3 bg-gray-50 rounded-2xl">
                <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-1">Calendario</p>
                <p class="font-bold text-[#1F2937]">{{ form.diseno_microproyecto.clases?.length || 0 }} sesión(es)</p>
              </div>
            </div>
          </div>

          <!-- Recursos: vídeos y documentos para la empresa (almacenados en Cloudinary) -->
          <div class="bg-white rounded-4xl border border-gray-100 shadow-sm p-6 mb-4">
            <div class="flex items-center gap-3 mb-1">
              <div class="w-8 h-8 rounded-xl bg-[#00A859]/10 border border-[#00A859]/20
                          flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
              </div>
              <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#00A859]">Recursos para la empresa</p>
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
                <p class="text-[10px] font-black uppercase tracking-wider text-gray-500 mb-3">Vídeos</p>

                <!-- Lista -->
                <div v-if="videosLocales.length" class="space-y-2 mb-3">
                  <div v-for="(v, i) in videosLocales" :key="i"
                       class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-100
                              hover:border-blue-200 hover:bg-blue-50/40 transition-colors group/vid">
                    <button @click="abrirRecurso(v)"
                            class="w-7 h-7 rounded-lg bg-blue-50 shrink-0 flex items-center justify-center
                                   group-hover/vid:bg-blue-100 transition-colors">
                      <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                      </svg>
                    </button>
                    <button @click="abrirRecurso(v)" class="flex-1 min-w-0 text-left">
                      <p class="text-xs font-bold text-gray-700 truncate group-hover/vid:text-blue-600 transition-colors">{{ v.label || v.filename }}</p>
                      <p class="text-[9px] text-blue-400/80 truncate">Cloudinary · {{ v.filename }}</p>
                    </button>
                    <button @click.stop="removeVideo(i)"
                            class="w-6 h-6 rounded-lg bg-red-50 flex items-center justify-center text-red-400 hover:bg-red-100 transition-colors shrink-0">
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
                                text-gray-700 placeholder-gray-300 focus:outline-none focus:border-[#00A859] transition-colors"/>
                  <label class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl
                                border-2 border-dashed border-gray-200 bg-gray-50
                                cursor-pointer hover:border-[#00A859]/40 hover:bg-[#00A859]/5 transition-all"
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
                <p class="text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Banco de imágenes</p>
                <p class="text-[10px] text-gray-400 mb-3">
                  También puede subir imágenes el alumnado desde su workspace. La primera
                  se marca como portada automáticamente; puedes cambiarla cuando quieras.
                </p>

                <!-- Galería -->
                <div v-if="imagenesLocales.length" class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-3">
                  <div v-for="(img, i) in imagenesLocales" :key="img.id"
                       class="relative group/img rounded-xl overflow-hidden border-2"
                       :class="img.id === imagenPortadaId ? 'border-[#00A859]' : 'border-gray-100'">
                    <img :src="img.url" :alt="img.label || img.filename" class="w-full h-20 object-cover" />
                    <span v-if="img.id === imagenPortadaId"
                          class="absolute top-1 left-1 bg-[#00A859] text-white text-[8px] font-black
                                 uppercase tracking-wider px-1.5 py-0.5 rounded-full">Portada</span>
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover/img:opacity-100
                                transition-opacity flex items-center justify-center gap-1.5">
                      <button v-if="img.id !== imagenPortadaId" @click="marcarPortada(img)" type="button"
                              title="Marcar como portada"
                              class="w-6 h-6 rounded-lg bg-white/90 flex items-center justify-center text-[#00A859] hover:bg-white">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                      </button>
                      <button @click.stop="removeImagen(i)" type="button"
                              class="w-6 h-6 rounded-lg bg-white/90 flex items-center justify-center text-red-400 hover:bg-white">
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
                              cursor-pointer hover:border-[#00A859]/40 hover:bg-[#00A859]/5 transition-all"
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
                <p class="text-[9px] text-gray-300 mt-1.5 pl-1">Máx. 8 MB por imagen.</p>
              </div>

              <!-- ── DOCUMENTOS ─────────────────────────────────────── -->
              <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-gray-500 mb-3">Documentos, otros archivos...</p>

                <!-- Lista -->
                <div v-if="documentosLocales.length" class="space-y-2 mb-3">
                  <div v-for="(d, i) in documentosLocales" :key="i"
                       class="flex items-center gap-2 p-2.5 bg-gray-50 rounded-xl border border-gray-100
                              hover:border-[#00A859]/30 hover:bg-[#00A859]/5 transition-colors group/doc">
                    <button @click="abrirRecurso(d)"
                            class="w-7 h-7 rounded-lg bg-[#00A859]/10 shrink-0 flex items-center justify-center
                                   group-hover/doc:bg-[#00A859]/20 transition-colors">
                      <svg class="w-3.5 h-3.5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                      </svg>
                    </button>
                    <button @click="abrirRecurso(d)" class="flex-1 min-w-0 text-left">
                      <p class="text-xs font-bold text-gray-700 truncate group-hover/doc:text-[#00A859] transition-colors">{{ d.label || d.filename }}</p>
                      <p class="text-[9px] text-blue-400/80 truncate">Cloudinary · {{ d.filename }}</p>
                    </button>
                    <button @click.stop="removeDocumento(i)"
                            class="w-6 h-6 rounded-lg bg-red-50 flex items-center justify-center text-red-400 hover:bg-red-100 transition-colors shrink-0">
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
                                text-gray-700 placeholder-gray-300 focus:outline-none focus:border-[#00A859] transition-colors"/>
                  <label class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl
                                border-2 border-dashed border-gray-200 bg-gray-50
                                cursor-pointer hover:border-[#00A859]/40 hover:bg-[#00A859]/5 transition-all"
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
                <p class="text-[9px] text-gray-300 mt-1.5 pl-1">Máx. 20 MB por archivo · Los archivos se alojan en Cloudinary. La BD no almacena ningún fichero.</p>
              </div>

            </div>
          </div>

          <div class="bg-amber-50/80 border border-amber-200/60 rounded-4xl p-5 mb-5">
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

          <div class="flex flex-col sm:flex-row justify-between gap-3">
            <button @click="paso = 6" class="btn-secondary">← Anterior</button>
            <div class="flex flex-wrap gap-3 justify-end items-center">

              <!-- ── Desplegable Estado del proyecto ── -->
              <div class="relative">
                <button
                  @click="dropdownEstadoAbierto = !dropdownEstadoAbierto"
                  :disabled="guardando"
                  class="btn-secondary flex items-center gap-2"
                >
                  <!-- Dot + label del estado actual -->
                  <span :class="['w-2 h-2 rounded-full shrink-0 transition-colors',
                    form.estado === 'propuesta' && form.enviado_a_empresa_mail ? 'bg-[#00A859]'
                    : estadoOpciones[form.estado]?.dot || 'bg-amber-400']" />
                  <span :class="form.estado === 'propuesta' && form.enviado_a_empresa_mail
                    ? 'text-[#00A859]' : (estadoOpciones[form.estado]?.text || 'text-amber-700')">
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
                                :class="form.estado === 'propuesta' && form.enviado_a_empresa_mail ? 'bg-[#00A859]' : 'bg-violet-400'" />
                          Propuesta
                          <svg v-if="form.estado === 'propuesta'" class="w-3.5 h-3.5 ml-auto"
                               :class="form.enviado_a_empresa_mail ? 'text-[#00A859]' : 'text-violet-400'"
                               fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                          </svg>
                        </span>
                        <!-- Sub-estado visible solo cuando está en propuesta -->
                        <span v-if="form.estado === 'propuesta'"
                              class="text-[9px] font-black uppercase tracking-wider pl-4"
                              :class="form.enviado_a_empresa_mail ? 'text-[#00A859]' : 'text-violet-500'">
                          {{ form.enviado_a_empresa_mail ? '✅ SÍ enviada por mail' : '✉ NO enviada por mail' }}
                        </span>
                      </button>
                      <!-- Tooltip (i) -->
                      <div class="relative group/info shrink-0">
                        <div class="w-5 h-5 rounded-full bg-gray-100 flex items-center justify-center cursor-help">
                          <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                        </div>
                        <div class="absolute hidden group-hover/info:block bottom-full right-0 mb-2
                                    bg-[#1a2332] text-white text-[11px] rounded-xl p-3 w-60 z-30
                                    leading-relaxed shadow-2xl">
                          Márcala como <strong class="text-white">Propuesta</strong> para generar el enlace de validación empresa, o para validarla directamente como docente. Pasará a llamarse <strong class="text-white">proyecto</strong> y a <strong class="text-[#00A859]">Validado</strong> cuando lo valide la empresa, el docente, o ambos.
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
              <button @click="mostrarModalPublicar" :disabled="guardando || !form.titulo.trim()" class="btn-primary">
                {{ guardando ? 'Guardando…' : 'Publicar propuesta' }}
              </button>

            </div>
          </div>
          </template>
        </div>

      </template>
      </div><!-- /flex-1 wizard -->

      <!-- ── Panel lateral: info del reto ─────────────────────────────────── -->
      <aside v-if="microretoVinculado"
             class="hidden lg:flex flex-col gap-4 w-72 shrink-0 sticky self-start rounded-3xl
                    border border-gray-100 bg-white shadow-sm p-5 overflow-hidden"
             style="top: 7.5rem">

        <!-- Header -->
        <div class="flex items-start justify-between gap-2">
          <span class="text-[9px] font-black uppercase tracking-widest text-[#00A859]">Reto del proyecto</span>
          <button v-if="!uuid" @click="limpiarReto"
                  class="text-[9px] font-black uppercase tracking-widest text-gray-400
                         hover:text-red-400 transition-colors shrink-0">
            Cambiar
          </button>
        </div>

        <!-- Titulo -->
        <div>
          <p class="text-sm font-black text-[#121212] leading-snug">{{ microretoVinculado.titulo }}</p>
          <p v-if="microretoVinculado.pregunta_reto"
             class="text-[11px] text-gray-500 mt-2 leading-relaxed line-clamp-4 italic">
            "{{ microretoVinculado.pregunta_reto }}"
          </p>
        </div>

        <!-- Chips -->
        <div class="flex flex-wrap gap-1.5">
          <span v-if="retoEmpresaNombre" class="tag tag-gray">{{ retoEmpresaNombre }}</span>
          <span v-if="microretoVinculado.familia" class="tag tag-gray">{{ microretoVinculado.familia }}</span>
          <span v-if="microretoVinculado.ciclo"
                class="tag tag-gray text-[9px] max-w-full truncate">{{ microretoVinculado.ciclo }}</span>
          <span v-if="microretoVinculado.curso"
                class="tag tag-lime">{{ cursoLabel(microretoVinculado.curso) }}</span>
        </div>

        <!-- Ver ficha completa -->
        <button @click="microretoModalId = microretoVinculado.id"
                class="flex items-center justify-center gap-1.5 w-full py-2 rounded-2xl
                       border border-gray-200 text-[10px] font-black uppercase tracking-widest
                       text-gray-500 hover:border-[#00A859] hover:text-[#00A859] transition-all">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                     -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          Ver ficha completa
        </button>

        <!-- Nota fija: fase de propuesta -->
        <div v-if="form.estado !== 'validado'"
             class="flex items-start gap-2 rounded-2xl border border-blue-100 bg-blue-50 p-3">
          <svg class="w-3.5 h-3.5 text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p class="text-[10px] text-blue-700 leading-relaxed">
            Esto es, de momento, una <strong>propuesta</strong>. Al publicarla y ser validada,
            pasará a llamarse <strong>proyecto</strong>.
          </p>
        </div>

      </aside>

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
         @click.self="modalBorradorAviso = false; modoGuia.value = true">

      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" />

      <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8">

        <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-100
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </div>

        <h3 class="text-xl font-black text-[#121212] text-center mb-4">
          Propuesta en edición
        </h3>

        <p class="text-sm text-gray-600 leading-relaxed text-center">
          Esta propuesta está <strong>En edición</strong> — aún no se ha enviado a validar.
          Cuando esté lista, usa el desplegable <strong>Estado del proyecto</strong> y selecciona
          <strong>Propuesta</strong> para enviarla a la empresa o validarla como docente.
        </p>

        <button
          @click="modalBorradorAviso = false; modoGuia.value = true"
          class="mt-7 w-full inline-flex items-center justify-center
                 px-6 py-3 bg-[#1F2937] text-white rounded-full
                 text-xs font-black uppercase tracking-widest shadow-sm
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
        <div class="w-14 h-14 rounded-2xl bg-[#00A859]/10 border border-[#00A859]/20
                    flex items-center justify-center mb-5 mx-auto">
          <svg class="w-7 h-7 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
        </div>

        <!-- Badge estado -->
        <div class="flex justify-center mb-4">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full
                       bg-violet-50 border border-violet-200 text-violet-700
                       text-[10px] font-black uppercase tracking-widest">
            <span class="w-1.5 h-1.5 rounded-full bg-violet-400 animate-pulse" />
            Propuesta · Pendiente de validar
          </span>
        </div>

        <h3 class="text-xl font-black text-[#121212] text-center mb-2">
          Propuesta pendiente de validar
        </h3>
        <p class="text-sm text-gray-500 text-center mb-5 leading-relaxed">
          Elige cómo validar la propuesta — puedes usar una vía o las dos.
        </p>

        <!-- Vía A: Validación empresa -->
        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 mb-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-blue-500 mb-3">
            Vía A · Validación empresa
          </p>
          <div class="flex items-center gap-2 mb-3">
            <p class="flex-1 text-xs text-blue-400 truncate font-mono bg-white border border-blue-200
                       rounded-xl px-3 py-2 min-w-0">
              {{ landingUrl || '—' }}
            </p>
            <button @click="copiarUrlModal"
                    :class="['shrink-0 px-3 py-2 rounded-xl text-xs font-bold border transition-all',
                             urlCopiadaModal
                               ? 'bg-[#00A859]/10 text-[#00A859] border-[#00A859]/20'
                               : 'bg-white text-gray-500 border-gray-200 hover:border-[#00A859] hover:text-[#00A859]']">
              {{ urlCopiadaModal ? '¡Copiado!' : 'Copiar' }}
            </button>
          </div>

          <!-- Info empresa desplegable -->
          <div v-if="form.empresa_id" class="mb-3">
            <button @click="infoEmpresaAbierta = !infoEmpresaAbierta"
                    class="w-full flex items-center justify-between px-3 py-2 rounded-xl
                           bg-white border border-blue-200 text-xs font-bold text-blue-700
                           hover:border-[#00A859]/40 hover:text-[#00A859] transition-all">
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
                   class="text-[10px] text-gray-400">
                  <span class="font-black uppercase tracking-wider">Sector:</span>
                  {{ form.datos_empresa.sector }}
                </p>
                <p v-if="form.datos_empresa?.persona_contacto"
                   class="text-[10px] text-gray-400">
                  <span class="font-black uppercase tracking-wider">Contacto:</span>
                  {{ form.datos_empresa.persona_contacto }}
                </p>
                <p v-if="form.datos_empresa?.email"
                   class="text-[10px] text-gray-400">
                  <span class="font-black uppercase tracking-wider">Email:</span>
                  {{ form.datos_empresa.email }}
                </p>
                <p v-if="form.datos_empresa?.descripcion"
                   class="text-[10px] text-gray-500 leading-relaxed pt-1 border-t border-gray-100">
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
                      ? 'bg-[#00A859]/10 border-[#00A859]/30 text-[#00A859] hover:bg-[#00A859]/20'
                      : 'bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100'"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5
                           border rounded-xl text-xs font-bold transition-all">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              {{ form.enviado_a_empresa_mail ? '✅ Reenviar enlace a la empresa' : '✉ Confirmar envío del enlace' }}
            </button>
            <button @click="router.push({ name: 'empresas' }); modalPropuestaAviso = false"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5
                           bg-white border border-blue-200 text-blue-600 rounded-xl
                           text-xs font-bold hover:border-[#00A859]/40 hover:text-[#00A859] transition-all">
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
          <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600 mb-3">
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
                         bg-emerald-600 text-white rounded-xl
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
                   text-xs font-black uppercase tracking-widest
                   hover:bg-gray-200 transition-all active:scale-95"
          >
            Entendido
          </button>
          <button
            @click="cerrarModalPropuestaAviso()"
            class="flex-1 inline-flex items-center justify-center gap-2
                   px-5 py-3 bg-[#00A859] text-white rounded-full
                   text-xs font-black uppercase tracking-widest shadow-sm
                   hover:bg-[#00A859]/90 transition-all active:scale-95"
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

        <h3 class="text-xl font-black text-[#121212] text-center mb-1">
          Confirmar envío
        </h3>
        <p class="text-sm text-gray-500 text-center mb-5 leading-relaxed">
          Estás a punto de abrir el panel de envío del enlace de validación para:
        </p>

        <!-- Tarjeta empresa -->
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-5">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 rounded-xl bg-[#00A859]/10 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
            </div>
            <p class="font-black text-sm text-[#121212]">
              {{ form.datos_empresa?.nombre || '—' }}
            </p>
          </div>
          <div class="space-y-1 pl-10">
            <p v-if="form.datos_empresa?.sector" class="text-[11px] text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-[10px]">Sector:</span>
              {{ form.datos_empresa.sector }}
            </p>
            <p v-if="form.datos_empresa?.persona_contacto" class="text-[11px] text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-[10px]">Contacto:</span>
              {{ form.datos_empresa.persona_contacto }}
            </p>
            <p v-if="form.datos_empresa?.email" class="text-[11px] text-gray-500">
              <span class="font-bold text-gray-400 uppercase tracking-wider text-[10px]">Email:</span>
              {{ form.datos_empresa.email }}
            </p>
          </div>
        </div>

        <!-- Campo de seguridad: escribe "enviar" -->
        <div class="mb-5">
          <label class="block text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">
            Escribe <span class="font-black text-[#1F2937] normal-case tracking-normal">enviar</span> para confirmar
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
                ? 'border-[#00A859]/50 bg-[#00A859]/5 text-[#00A859] focus:ring-2 focus:ring-[#00A859]/20'
                : 'border-gray-200 bg-white focus:border-gray-400 focus:ring-2 focus:ring-gray-100'"
          />
          <p v-if="confirmEnvioTexto && !confirmEnvioValido"
             class="mt-1.5 text-[10px] text-red-500 font-semibold">
            Escribe exactamente: enviar
          </p>
          <p v-if="confirmEnvioTexto && confirmEnvioValido"
             class="mt-1.5 text-[10px] text-[#00A859] font-semibold">
            Confirmado. Ya puedes continuar.
          </p>
        </div>

        <!-- Botones principales -->
        <div class="flex gap-3 mb-3">
          <button
            @click="modalConfirmEnvio = false"
            class="flex-1 inline-flex items-center justify-center
                   px-4 py-2.5 bg-gray-100 text-[#1F2937] rounded-full
                   text-xs font-black uppercase tracking-widest
                   hover:bg-gray-200 transition-all active:scale-95"
          >
            Cancelar
          </button>
          <button
            @click="confirmarEnvio"
            :disabled="!confirmEnvioValido"
            class="flex-1 inline-flex items-center justify-center gap-2
                   px-4 py-2.5 rounded-full text-xs font-black uppercase tracking-widest
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
                 text-xs font-bold hover:border-[#00A859]/40 hover:text-[#00A859] transition-all"
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
          <p class="text-sm font-black text-[#121212] truncate pr-4">{{ modalRecurso.label }}</p>
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
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#00A859] text-white
                      rounded-full text-xs font-black uppercase tracking-widest
                      hover:bg-[#00A859]/90 transition-all">
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

        <h3 class="text-xl font-black text-[#121212] text-center mb-4">
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
                 px-6 py-3 bg-[#00A859] text-white rounded-full
                 text-xs font-black uppercase tracking-widest shadow-sm
                 hover:bg-[#00A859]/90 transition-all active:scale-95"
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
    descripcion="Explora el wizard de Startup Day con una guía paso a paso."
    @activar="activarTourDesdeModal"
    @omitir="omitirTourDesdeModal"
  />

  <MicroretoModal :microreto-id="microretoModalId" @close="microretoModalId = null" />

</template>

<style scoped>
@reference "../style.css";

.field-label {
  @apply block text-[10px] font-black uppercase tracking-[0.15em] text-gray-500 mb-1.5;
}
.field-input {
  @apply w-full bg-white border border-gray-200 rounded-2xl px-4 py-2.5 text-sm
         text-[#1F2937] placeholder-gray-400 focus:outline-none focus:border-[#00A859]
         transition-colors shadow-sm;
}
.btn-primary {
  @apply inline-flex items-center gap-2 px-6 py-2.5 bg-[#00A859] text-white
         rounded-full text-xs font-black uppercase tracking-widest shadow-sm
         hover:bg-[#00A859]/90 hover:shadow-[0_0_0_3px_rgba(0,168,89,0.15)]
         transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed;
}
.btn-secondary {
  @apply inline-flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200
         rounded-full text-xs font-black uppercase tracking-widest text-[#1F2937]
         shadow-sm hover:border-[#00A859] hover:text-[#00A859]
         transition-all active:scale-95;
}
.tag {
  display: inline-flex; align-items: center;
  padding: 0.125rem 0.5rem; border-radius: 999px;
  font-size: 0.625rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.1em;
}
.tag-gray  { background: #F3F4F6; color: #6B7280; }
.tag-green { background: rgba(0,168,89,0.1); color: #00A859; }
.tag-lime  { background: rgba(153,204,51,0.12); color: #5a7a00; }
</style>
