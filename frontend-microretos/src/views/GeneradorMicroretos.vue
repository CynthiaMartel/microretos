<script setup>
import { ref, onMounted, onUnmounted, watch, computed, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';
import { useAuthStore } from '../stores/auth';
import LoginModal from '../components/LoginModal.vue';
import InsertModifyEmpresa from '../components/InsertModifyEmpresa.vue';
import { useUIState } from '../composables/useUIState.js';
import TourPromptModal from '../components/TourPromptModal.vue';

const authStore = useAuthStore();
const router = useRouter();
const showLogin = ref(false);

// Para docentes: identifica si el usuario es docente (oculta funciones de admin)
const esDocente = computed(() => authStore.isDocente);

const familias = ref([]); 
const ciclos = ref([]);
const modulos = ref([]);

// --- ESTADO PARA ANIMACIONES DE ENTRADA ---
const isLoaded = ref(false);

// --- CONTROL DE NAVEGACIÓN ---
const pasoActual = ref(1); 
const totalPasos = 3;

// --- VARIABLES B2B ---
const empresas = ref([]);
const familiasFiltradas = ref([]); 
const buscadorEmpresa = ref('');
const filtroTipoEmpresa = ref('');   // '' = todas, 'simulada', 'real'
const mostrarDropdownEmpresas = ref(false);
const empresaDetalle = ref(null);

// --- MODAL INSERT/MODIFY EMPRESA ---
const mostrarNuevaEmpresa = ref(false);
const mostrarEditarEmpresa = ref(false);
const accionPendienteLogin = ref(null);
const insertModifyRef = ref(null);

// --- ETADO MODO DEMO Y SIMULACIÓN ---
const esModoDemo = ref(false);
const demoFamiliaActiva = ref('');
const demoCicloNombre = ref('');
const demoModuloNombre = ref('');
const esInfoSimulada = ref(false);
const cargandoSimulacion = ref(false);
let autoSelectDemoCycle = false;

// --- SELECTOR DE DEMOS ---
const demosDisponibles = ref([]);
const mostrarSelectorDemo = ref(false);
const demoSelectorRef = ref(null);

// Mapa de iconos SVG por familia profesional
const demosIconos = {
  'Informática y Comunicaciones': 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  'Administración y Gestión':     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
  'Comercio y Marketing':         'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
  'Sanidad':                      'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
  'Electricidad y Electrónica':   'M13 10V3L4 14h7v7l9-11h-7z',
  'Hostelería y Turismo':         'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1z',
};
const iconoFallback = 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10';

// --- FILTRO DE COLEGIOS ---
const centroFiltro = ref('');
const centrosDisponibles = computed(() => {
  const centros = empresas.value.map(e => e.centro_educativo).filter(Boolean);
  return [...new Set(centros)].sort();
});

const sectoresDisponibles = computed(() => {
  const sectores = empresas.value.map(e => e.sector).filter(Boolean);
  return [...new Set(sectores)].sort();
});

const todasLasFamilias = ref([]);

const seleccion = ref({
  empresaId: '', 
  empresaNombre: '',
  empresaCentro: '', 
  empresaSector: '',
  empresaUbicacion: '',
  empresaTamano: '', 
  empresaWeb: '',
  
  diaANormal: '',        
  friccionArea: '',      
  friccionProblema: '', 
  restricciones: [],    
  otraLimitacion: '',   
  loQueNoQuieren: '',   
  consecuencias: [],    
  otraConsecuencia: '', 
  expectativasAlumno: '', 

  familia: '',
  cicloId: '', 
  cursoSeleccionado: 2,
  duracion: '1 a 2 semanas',
  nivelGrupo: 'Medio',
  
  cantidadMicroretos: 3, 
});

const modulosSeleccionados = ref([]); 

const modulosDelCurso = computed(() => {
  return modulos.value.filter(m => m.curso == seleccion.value.cursoSeleccionado);
});

const microretosGenerados = ref([]);
const cargando = ref(false);
const actualizandoCRM = ref(false);
const crmActualizado = ref(false);
const diagnosticoRecuperado = ref(false);

const guardandoTodos = ref(false);
const todosGuardados = ref(false);

const consecuenciasOpciones = [
  'Errores frecuentes', 'Costes innecesarios', 'Pérdida de tiempo', 
  'Insatisfacción del cliente', 'Riesgos de seguridad', 'Desperdicio de materiales', 'Falta de comunicación interna'
];

const limitacionesOpciones = [
  'Presupuesto Cero/Muy Bajo', 'Equipos obsoletos', 'Internet inestable',
  'Software cerrado', 'Resistencia al cambio', 'Espacio reducido',
  'Falta de tiempo', 'Normativa RGPD'
];

// Límites de caracteres por campo. Aviso al 85%, bloqueo en max.
// Objetivo: ~1.600 tokens de contexto de empresa en el peor caso (≈ 5.800 chars ÷ 3,5).
const CHAR_LIMITS = {
  diaANormal:         { max: 1000, warn: 850 },
  friccionArea:       { max: 400,  warn: 340 },
  friccionProblema:   { max: 1200, warn: 1020 },
  otraLimitacion:     { max: 600,  warn: 510 },
  loQueNoQuieren:     { max: 500,  warn: 425 },
  otraConsecuencia:   { max: 300,  warn: 255 },
  expectativasAlumno: { max: 800,  warn: 680 },
};
// Reactivo en template: Vue rastrea seleccion.value al renderizar
const charInfo = (field) => {
  const { max, warn } = CHAR_LIMITS[field];
  const len = (seleccion.value[field] || '').length;
  return { len, max, isWarning: len >= warn, isOver: len >= max };
};

const popupActivo = ref(null);
const abrirPopup = (tipo, id) => { popupActivo.value = { tipo, id }; };
const cerrarPopup = () => { popupActivo.value = null; };

// Solo permite https:// y http://. Cualquier otro protocolo (javascript:, data:, vbscript:)
// se reemplaza por '#' para evitar XSS almacenado vía campo Web de empresa.
const safeUrl = (url) => {
  if (!url) return '#';
  return /^https?:\/\//i.test(url) ? url : '#';
};

const empresaWebEsValida = computed(() => {
  const url = seleccion.value.empresaWeb;
  if (!url) return true;
  return /^https?:\/\//i.test(url);
});

const ayudas = {
  1: { 
    info: "Detalla la actividad principal de la empresa, su modelo de negocio y quiénes conforman el equipo. Esto ayuda a la IA a dimensionar la solución.", 
    ejemplo: "Somos una agencia de logística especializada en última milla para e-commerce. Contamos con 15 repartidores y 3 personas en oficina gestionando rutas y atención al cliente." 
  },
  2: { 
    info: "Identifica el 'cuello de botella' o proceso que genera más fricción, consume más tiempo absurdo o provoca más errores en el día a día.", 
    ejemplo: "La planificación matutina de rutas. Actualmente se hace en una pizarra y cruzando hojas de Excel, lo que provoca que los repartidores salgan tarde y se crucen en las mismas zonas de la ciudad." 
  },
  3: { 
    info: "Menciona intentos previos de solución o barreras reales (presupuesto, conocimientos técnicos, resistencia al cambio) que impidan resolverlo fácilmente.", 
    ejemplo: "Probamos un software de pago corporativo pero era muy complejo y los repartidores no lo usaban. Además, no tenemos presupuesto para comprar tablets nuevas para todos los vehículos." 
  },
  4: { 
    info: "Visualiza el escenario ideal. ¿Qué cambiaría radicalmente en la empresa si este problema se resolviera hoy mismo?", 
    ejemplo: "Ahorraríamos 2 horas de trabajo administrativo al día, reduciríamos el gasto en combustible un 15% y los clientes recibirían sus paquetes mucho antes." 
  },
  5: { 
    info: "Acota qué esperas que el alumnado produzca como resultado final del reto (un diseño, una maqueta, un plan, un script, un prototipo...).", 
    ejemplo: "Esperamos que analicen el flujo actual y diseñen un prototipo funcional en AppSheet o Glide (herramientas No-Code) que permita a los repartidores ver su ruta optimizada en su propio móvil." 
  }
};

const getDatosPaso2Preparados = () => {
  let limitacionesFinales = [...seleccion.value.restricciones];
  if (seleccion.value.otraLimitacion) limitacionesFinales.push(seleccion.value.otraLimitacion);
  const restriccionesStr = limitacionesFinales.join(', ');
  
  let consecuenciasFinales = [...seleccion.value.consecuencias];
  if (seleccion.value.otraConsecuencia) consecuenciasFinales.push(seleccion.value.otraConsecuencia);
  const consecuenciasStr = consecuenciasFinales.join(', ');
  
  return { restriccionesStr, consecuenciasStr, consecuenciasArray: consecuenciasFinales };
};

const empresasFiltradasBusqueda = computed(() => {
  let filtradas = empresas.value;
  if (centroFiltro.value) {
    filtradas = filtradas.filter(e => e.centro_educativo === centroFiltro.value);
  }
  if (filtroTipoEmpresa.value === 'simulada') {
    filtradas = filtradas.filter(e => e.es_simulada);
  } else if (filtroTipoEmpresa.value === 'real') {
    filtradas = filtradas.filter(e => !e.es_simulada);
  }
  if (buscadorEmpresa.value) {
    filtradas = filtradas.filter(e =>
      e.nombre_comercial?.toLowerCase().includes(buscadorEmpresa.value.toLowerCase())
    );
  }
  return filtradas;
});

const seleccionarEmpresa = (emp) => {
  seleccion.value.empresaId = emp.id;
  seleccion.value.empresaNombre = emp.nombre_comercial;
  buscadorEmpresa.value = emp.nombre_comercial;
  mostrarDropdownEmpresas.value = false;
};

const abrirModalEmpresa = () => {
  if (seleccion.value.empresaId) {
    mostrarEditarEmpresa.value = true;
  } else {
    mostrarNuevaEmpresa.value = true;
  }
};

const onEmpresaCreada = (empresa) => {
  empresas.value.push(empresa);
  seleccionarEmpresa(empresa);
};

const onEmpresaActualizada = (empresa) => {
  const idx = empresas.value.findIndex(e => String(e.id) === String(empresa.id));
  if (idx !== -1) empresas.value[idx] = empresa;
  if (String(seleccion.value.empresaId) === String(empresa.id)) {
    empresaDetalle.value = empresa;
    buscadorEmpresa.value = empresa.nombre_comercial;
    seleccion.value.empresaNombre = empresa.nombre_comercial;
    seleccion.value.empresaSector = empresa.sector || '';
    seleccion.value.empresaTamano = empresa.tamano || '';
    seleccion.value.empresaWeb = empresa.web || '';
    seleccion.value.empresaCentro = empresa.centro_educativo || '';
  }
};

const onNecesitaLoginEmpresa = (accion) => {
  accionPendienteLogin.value = accion;
  showLogin.value = true;
};

const onBuscadorInput = () => {
  mostrarDropdownEmpresas.value = true;
  // Si el usuario borra el campo, limpiamos la empresa seleccionada
  if (!buscadorEmpresa.value) {
    seleccion.value.empresaId = '';
    seleccion.value.empresaNombre = '';
    empresaDetalle.value = null;
  }
};

watch(buscadorEmpresa, (val) => {
  if (val && empresas.value.length > 0) {
    mostrarDropdownEmpresas.value = true;
  }
});

// Si el usuario cambia de centro después de haber elegido empresa, la empresa
// queda huérfana (pertenece al centro anterior). Limpiarla para forzar una nueva elección.
watch(centroFiltro, (newVal, oldVal) => {
  if (oldVal !== newVal && (seleccion.value.empresaId || buscadorEmpresa.value)) {
    seleccion.value.empresaId = '';
    seleccion.value.empresaNombre = '';
    buscadorEmpresa.value = '';
    empresaDetalle.value = null;
    mostrarDropdownEmpresas.value = false;
  }
});

// --- FUNCIÓN LIMPIAR FORMULARIO (BOTÓN ROJO) ---
const limpiarFormulario = () => {
  if(confirm("¿Estás seguro de que quieres vaciar el formulario y empezar de cero?")) {
    pasoActual.value = 1;
    buscadorEmpresa.value = '';
    centroFiltro.value = ''; // Limpiamos el filtro del colegio también
    empresaDetalle.value = null;
    microretosGenerados.value = [];
    crmActualizado.value = false;
    diagnosticoRecuperado.value = false;
    todosGuardados.value = false;
    esModoDemo.value = false;
    mostrarSelectorDemo.value = false;
    esInfoSimulada.value = false;
    cargandoSimulacion.value = false;
    modulosSeleccionados.value = [];
    sectorEsLibre.value = false;
    mostrarDropdownSector.value = false;
    
    seleccion.value = {
      empresaId: '', empresaNombre: '', empresaCentro: '', empresaSector: '',
      empresaUbicacion: '', empresaTamano: '', empresaWeb: '',
      diaANormal: '', friccionArea: '', friccionProblema: '', restricciones: [],
      otraLimitacion: '', loQueNoQuieren: '', consecuencias: [], otraConsecuencia: '', expectativasAlumno: '',
      familia: '', cicloId: '', cursoSeleccionado: 2, duracion: '1 a 2 semanas', nivelGrupo: 'Medio', cantidadMicroretos: 3
    };
    window.scrollTo({top: 0, behavior: 'smooth'});
  }
};

// --- FUNCIÓN PARA CARGAR DATOS DEMO DESDE API ---
const cargarDemo = async (familiaProfesional) => {
  mostrarSelectorDemo.value = false;
  try {
    const res = await api.get(`/demos/${encodeURIComponent(familiaProfesional)}`);
    const demo = res.data;

    esModoDemo.value = true;
    seleccion.value.empresaId = '';
    centroFiltro.value = demo.empresa_centro || '';
    buscadorEmpresa.value = demo.empresa_nombre;
    empresaDetalle.value = null;
    diagnosticoRecuperado.value = false;

    // Paso 1
    seleccion.value.empresaNombre = demo.empresa_nombre;
    seleccion.value.empresaSector = demo.empresa_sector;
    seleccion.value.empresaTamano = demo.empresa_tamano;
    seleccion.value.empresaWeb = demo.empresa_web || '';
    seleccion.value.empresaCentro = demo.empresa_centro || 'IES DEMO';

    // Paso 2
    seleccion.value.diaANormal = demo.dia_a_normal;
    seleccion.value.friccionArea = demo.friccion_area;
    seleccion.value.friccionProblema = demo.friccion_problema;
    seleccion.value.restricciones = demo.restricciones || [];
    seleccion.value.otraLimitacion = demo.otra_limitacion || '';
    seleccion.value.loQueNoQuieren = demo.lo_que_no_quieren || '';
    seleccion.value.consecuencias = demo.consecuencias || [];
    seleccion.value.otraConsecuencia = demo.otra_consecuencia || '';
    seleccion.value.expectativasAlumno = demo.expectativas_alumno || '';

    // Paso 3
    familiasFiltradas.value = [demo.familia_profesional];
    seleccion.value.nivelGrupo = (demo.nivel_grupo === 'Básico' ? 'Bajo' : demo.nivel_grupo) || 'Medio';
    seleccion.value.cursoSeleccionado = demo.curso_seleccionado || 2;
    seleccion.value.duracion = demo.duracion || '1 a 2 semanas';
    seleccion.value.cantidadMicroretos = 1;

    autoSelectDemoCycle = true;
    seleccion.value.familia = demo.familia_profesional;
    demoFamiliaActiva.value = demo.familia_profesional;
    demoCicloNombre.value  = demo.ciclo_nombre  || '';
    demoModuloNombre.value = demo.modulo_nombre || '';
  } catch (e) {
    console.error('Error cargando demo:', e);
    alert('Error al cargar la demo seleccionada.');
  }
};

// --- FUNCIÓN PARA CARGAR MICRORETOS DE DEMO (simula procesado IA) ---
const generarRetoDemo = async () => {
  cargando.value = true;
  todosGuardados.value = false;
  try {
    // Simula el tiempo de procesado de la IA
    await new Promise(resolve => setTimeout(resolve, 2800));

    const res = await api.get(`/demos/${encodeURIComponent(demoFamiliaActiva.value)}/microretos`);

    if (res.data?.microretos?.length) {
      res.data.microretos.forEach(reto => {
        microretosGenerados.value.unshift({
          ...reto,
          _ui_guardado: true,   // ya están en BD, no se guardan de nuevo
          _ui_guardando: false,
        });
      });
      setTimeout(() => { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); }, 300);
    } else {
      alert('No hay microretos guardados para esta demo todavía.');
    }
  } catch (e) {
    console.error('Error cargando microretos demo:', e);
    alert('Error al cargar los microretos de la demo.');
  } finally {
    cargando.value = false;
  }
};

const paso1Valido = computed(() => seleccion.value.empresaNombre && seleccion.value.empresaSector && seleccion.value.empresaTamano);
const paso2Valido = computed(() => seleccion.value.diaANormal && seleccion.value.friccionProblema && seleccion.value.expectativasAlumno);
const paso3Valido = computed(() => {
  if (esModoDemo.value) return !!seleccion.value.familia;
  return !!(seleccion.value.familia && seleccion.value.cicloId && seleccion.value.cursoSeleccionado);
});

// Cierra el dropdown al hacer clic fuera
const buscadorRef = ref(null);
const sectorRef = ref(null);
const mostrarDropdownSector = ref(false);
const sectorEsLibre = ref(false);

const seleccionarSector = (sector) => {
  seleccion.value.empresaSector = sector;
  mostrarDropdownSector.value = false;
};
const activarSectorLibre = () => {
  seleccion.value.empresaSector = '';
  mostrarDropdownSector.value = false;
  sectorEsLibre.value = true;
};

const cerrarDropdownFuera = (e) => {
  if (buscadorRef.value && !buscadorRef.value.contains(e.target)) {
    mostrarDropdownEmpresas.value = false;
  }
  if (demoSelectorRef.value && !demoSelectorRef.value.contains(e.target)) {
    mostrarSelectorDemo.value = false;
  }
  if (sectorRef.value && !sectorRef.value.contains(e.target)) {
    mostrarDropdownSector.value = false;
  }
  estadoDropdownGenAbierto.value = false;
};

const cargarEmpresas = async () => {
  try {
    const [resEmpresas, resFamilias] = await Promise.all([
      api.get('/empresas'),
      api.get('/familias'),
    ]);
    empresas.value = resEmpresas.data;
    todasLasFamilias.value = resFamilias.data.map(f => f.nombre ?? f).filter(Boolean).sort();
  } catch (e) {
    console.error(e);
  }
};

const onLoginSuccess = async () => {
  showLogin.value = false;
  await cargarEmpresas();
  if (accionPendienteLogin.value) {
    insertModifyRef.value?.abrirTrasLogin(accionPendienteLogin.value);
    accionPendienteLogin.value = null;
  }
};

onMounted(async () => {
  // Guarda de seguridad en componente: el router ya redirige antes de montar,
  // pero este check actúa como barrera extra ante manipulaciones del token en memoria.
  if (!authStore.isAuthenticated) {
    router.replace({ path: '/', query: { redirect: '/microretos' } })
    return
  }

  document.addEventListener('click', cerrarDropdownFuera);
  setTimeout(() => { isLoaded.value = true; }, 100);

  // Carga pública: lista de demos disponibles (no requiere auth)
  try {
    const resDemos = await api.get('/demos');
    demosDisponibles.value = resDemos.data;
  } catch (e) {
    console.error('Error cargando demos:', e);
  }

  if (!authStore.isAuthenticated) {
    showLogin.value = true;
  } else {
    await cargarEmpresas();
    // Docentes: fijar automáticamente el centro educativo (no editable)
    if (authStore.isDocente && authStore.userCentroNombre) {
      centroFiltro.value = authStore.userCentroNombre;
    }
    await nextTick();
    pasoGuia.value = 1;
    showTourPrompt.value = true;
  }
});

onUnmounted(() => {
  document.removeEventListener('click', cerrarDropdownFuera);
  tourActivo.value = false;
  window.removeEventListener('scroll', onScrollGuia);
});

watch(() => seleccion.value.empresaId, async (nuevoId) => {
  seleccion.value.familia = ''; ciclos.value = []; seleccion.value.cicloId = ''; modulos.value = [];
  modulosSeleccionados.value = []; familiasFiltradas.value = [];
  empresaDetalle.value = null; crmActualizado.value = false; diagnosticoRecuperado.value = false;
  microretosGenerados.value = [];
  sectorEsLibre.value = false;
  mostrarDropdownSector.value = false;

  if (!nuevoId) return;

  const emp = empresas.value.find(e => String(e.id) === String(nuevoId));
  if(emp) {
    empresaDetalle.value = emp; 
    seleccion.value.empresaNombre = emp.nombre_comercial;
    seleccion.value.empresaCentro = emp.centro_educativo || ''; 
    seleccion.value.empresaSector = emp.sector || ''; 
    seleccion.value.empresaTamano = emp.tamano || ''; 
    seleccion.value.empresaWeb = emp.web || '';
    seleccion.value.empresaUbicacion = [emp.municipio, emp.provincia].filter(Boolean).join(', ');

    if (emp.friccion_area && emp.friccion_problema) {
      diagnosticoRecuperado.value = true;
      seleccion.value.diaANormal = emp.dia_a_normal || '';
      seleccion.value.friccionArea = emp.friccion_area;
      seleccion.value.friccionProblema = emp.friccion_problema;
      seleccion.value.loQueNoQuieren = emp.lo_que_no_quieren || '';
      if (emp.consecuencias) seleccion.value.consecuencias = emp.consecuencias.split(',').map(s => s.trim());
      if (emp.restricciones) seleccion.value.restricciones = emp.restricciones.split(',').map(s => s.trim());
    }
  }
  try {
    const resFam = await api.get(`/empresas/${nuevoId}/familias`);
    familiasFiltradas.value = resFam.data;
  } catch(e) { console.error(e); }
});

watch(() => seleccion.value.familia, async (val) => {
  // Limpiar siempre los campos dependientes al cambiar familia, incluso si el nuevo valor es vacío
  ciclos.value = [];
  seleccion.value.cicloId = '';
  modulos.value = [];
  modulosSeleccionados.value = [];

  if (!val) return;

  const url = seleccion.value.empresaCentro
    ? `/familias/${encodeURIComponent(val)}/ciclos?centro=${encodeURIComponent(seleccion.value.empresaCentro)}`
    : `/familias/${encodeURIComponent(val)}/ciclos`;

  const res = await api.get(url);
  ciclos.value = res.data;

  if (autoSelectDemoCycle && ciclos.value.length > 0) {
    const nombreBuscado = demoCicloNombre.value;
    const match = ciclos.value.find(c => c.nombre === nombreBuscado)
               || ciclos.value.find(c => nombreBuscado && c.nombre.toLowerCase().includes(nombreBuscado.split(' ')[0].toLowerCase()))
               || ciclos.value[0];
    seleccion.value.cicloId = match.id;
    autoSelectDemoCycle = false;
  }
});

watch(() => seleccion.value.cicloId, async (val) => {
  if (!val) return;
  const res = await api.get(`/ciclos/${val}/modulos`);
  modulos.value = res.data;
  modulosSeleccionados.value = [];

  if (esModoDemo.value && demoModuloNombre.value) {
    const nombreBuscado = demoModuloNombre.value;
    const lista = modulos.value.filter(m => m.curso == seleccion.value.cursoSeleccionado);
    const match = lista.find(m => m.nombre === nombreBuscado)
               || lista.find(m => nombreBuscado && m.nombre.toLowerCase().includes(nombreBuscado.split(' ')[0].toLowerCase()))
               || lista[0];
    if (match) modulosSeleccionados.value = [match.id];
  }
});

watch(() => seleccion.value.cursoSeleccionado, () => {
  modulosSeleccionados.value = [];
});

const avanzarPaso = async () => {
  if (pasoActual.value < totalPasos) pasoActual.value++;
  window.scrollTo({ top: 0, behavior: 'instant' });
  await nextTick();
  pasoGuia.value = 1;
  modoGuia.value = true;
};
const retrocederPaso = () => { if (pasoActual.value > 1) pasoActual.value--; window.scrollTo({top: 0, behavior: 'smooth'}); };

const guardarInfoEmpresa = async () => {
  actualizandoCRM.value = true;
  const datosP2 = getDatosPaso2Preparados();
  
  const payload = {
    nombreComercial: seleccion.value.empresaNombre, centroEducativo: seleccion.value.empresaCentro, sector: seleccion.value.empresaSector, tamano: seleccion.value.empresaTamano, web: seleccion.value.empresaWeb,
    diaANormal: seleccion.value.diaANormal, friccionArea: seleccion.value.friccionArea, friccionProblema: seleccion.value.friccionProblema, consecuencias: datosP2.consecuenciasStr, restricciones: datosP2.restriccionesStr, loQueNoQuieren: seleccion.value.loQueNoQuieren,
    familia: seleccion.value.familia
  };

  try {
    if (seleccion.value.empresaId) {
      await api.put(`/empresas/${seleccion.value.empresaId}`, payload);
      const emp = empresas.value.find(e => String(e.id) === String(seleccion.value.empresaId));
      if(emp) Object.assign(emp, { ...payload, centro_educativo: payload.centroEducativo, dia_a_normal: payload.diaANormal, friccion_area: payload.friccionArea, friccion_problema: payload.friccionProblema, lo_que_no_quieren: payload.loQueNoQuieren });
    } else {
      const res = await api.post('/empresas', payload);
      seleccion.value.empresaId = res.data.empresa.id;
      empresas.value.push(res.data.empresa);
    }
    crmActualizado.value = true;
    setTimeout(() => crmActualizado.value = false, 3000); 
  } catch(e) { alert("Error al procesar la empresa en la BD."); } finally { actualizandoCRM.value = false; }
};

const toggleInfoSimulada = async () => {
  if (esModoDemo.value || cargandoSimulacion.value) return;

  // Si ya está activo, simplemente desactivar
  if (esInfoSimulada.value) {
    esInfoSimulada.value = false;
    return;
  }

  if (!seleccion.value.empresaNombre || !seleccion.value.empresaSector) {
    alert('Selecciona una empresa con nombre y sector antes de generar información simulada.');
    return;
  }

  cargandoSimulacion.value = true;
  try {
    const res = await api.post('/simular-info-empresa', {
      empresaNombre:    seleccion.value.empresaNombre,
      empresaSector:    seleccion.value.empresaSector,
      empresaTamano:    seleccion.value.empresaTamano    || '',
      empresaUbicacion: seleccion.value.empresaUbicacion || '',
    });

    const d = res.data;
    seleccion.value.diaANormal         = d.diaANormal         || '';
    seleccion.value.friccionArea       = d.friccionArea       || '';
    seleccion.value.friccionProblema   = d.friccionProblema   || '';
    seleccion.value.restricciones      = Array.isArray(d.restricciones)  ? d.restricciones  : [];
    seleccion.value.otraLimitacion     = d.otraLimitacion     || '';
    seleccion.value.loQueNoQuieren     = d.loQueNoQuieren     || '';
    seleccion.value.consecuencias      = Array.isArray(d.consecuencias)  ? d.consecuencias  : [];
    seleccion.value.otraConsecuencia   = d.otraConsecuencia   || '';
    seleccion.value.expectativasAlumno = d.expectativasAlumno || '';

    esInfoSimulada.value = true;

    // Navegar al paso 2 para que el usuario vea los campos rellenos
    if (pasoActual.value === 1) pasoActual.value = 2;
  } catch (e) {
    console.error(e);
    alert('Error al generar información simulada. Inténtalo de nuevo.');
  } finally {
    cargandoSimulacion.value = false;
  }
};

const generarReto = async () => {
  cargando.value = true;
  todosGuardados.value = false; 
  try {
    const nombresModulosSeleccionados = modulosSeleccionados.value.map(id => modulos.value.find(m => m.id === id)?.nombre).filter(Boolean); 
    const moduloNombreTxt = nombresModulosSeleccionados.length > 0 ? nombresModulosSeleccionados.join(' y ') : `A determinar por IA (${seleccion.value.cursoSeleccionado}º Curso)`;
    const datosP2 = getDatosPaso2Preparados();
    
    const res = await api.post('/generar-microreto', {
      ...seleccion.value, restricciones: datosP2.restriccionesStr, consecuencias: datosP2.consecuenciasArray,
      ciclo_nombre: ciclos.value.find(c => c.id === seleccion.value.cicloId)?.nombre, modulo_nombre: moduloNombreTxt,
      ciclo_id: seleccion.value.cicloId, modulo_id: modulosSeleccionados.value.length > 0 ? modulosSeleccionados.value : null,
      nivelGrupo: seleccion.value.nivelGrupo, expectativasAlumno: seleccion.value.expectativasAlumno, 
      cursoSeleccionado: seleccion.value.cursoSeleccionado,
      cantidad: seleccion.value.cantidadMicroretos 
    });

    if (res.data && res.data.microretos) {
        const cicloNombreSnapshot = ciclos.value.find(c => c.id === seleccion.value.cicloId)?.nombre;
        res.data.microretos.forEach(reto => {
            microretosGenerados.value.unshift({
                ...reto,
                // Snapshot del contexto en el momento de generación para evitar
                // que cambios posteriores en la selección sobreescriban estos valores
                empresa_id:   seleccion.value.empresaId || null,
                ciclo_id:     seleccion.value.cicloId   || null,
                ciclo:        cicloNombreSnapshot,
                modulo:       moduloNombreTxt,
                curso:        seleccion.value.cursoSeleccionado,
                duracion:     seleccion.value.duracion,
                nivel_grupo:  seleccion.value.nivelGrupo,
                es_simulado:  esInfoSimulada.value || !!(empresaDetalle.value?.es_simulada),
                _ui_familia:  seleccion.value.familia,
                _ui_guardado: false,
                _ui_guardando: false
            });
        });
    }

    setTimeout(() => { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); }, 300);
  } catch (e) { console.error(e); alert("Error al contactar con la IA"); } finally { cargando.value = false; }
};

const guardarTodos = async () => {
  guardandoTodos.value = true;
  try {
    const payload = microretosGenerados.value
      .filter(r => !r._ui_guardado)
      .map(({ _ui_guardado, _ui_guardando, _ui_familia, ...retoLimpio }) => retoLimpio); // Eliminar claves UI; el contexto ya fue snapshotado en cada reto al generarse

    if (payload.length > 0) {
      await api.post('/guardar-microretos-lote', { microretos: payload });
      microretosGenerados.value.forEach(r => r._ui_guardado = true);
      todosGuardados.value = true;
    } else {
      alert("Todos los retos mostrados ya estaban guardados.");
    }
  } catch (e) { 
    console.error("Error al guardar el lote:", e);
    alert("Error al guardar el lote de retos en BD");
  } finally { 
    guardandoTodos.value = false; 
  }
};

const guardar = async (index) => {
  const reto = microretosGenerados.value[index];
  reto._ui_guardando = true;
  try {
    const { _ui_guardado, _ui_guardando, _ui_familia, ...retoLimpio } = reto; // Eliminar claves UI; el contexto ya fue snapshotado en el reto al generarse

    await api.post('/guardar-microreto-bd', retoLimpio);
    reto._ui_guardado = true;
  } catch (e) { 
    console.error("Error al guardar:", e);
    alert("Error al guardar este reto");
  } finally { 
    reto._ui_guardando = false; 
  }
};

// Computed que indica si hay "contexto de empresa" suficiente
const tieneContextoEmpresa = computed(() => {
  return !!(seleccion.value.empresaId || seleccion.value.empresaNombre || buscadorEmpresa.value);
});

const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });

// ─── TOUR GUIADO (tres conjuntos independientes, uno por paso) ────────────
const { tourActivo } = useUIState()
const modoGuia = ref(false)
const showTourPrompt = ref(false)
function activarTourDesdeModal() { showTourPrompt.value = false; modoGuia.value = true }
function omitirTourDesdeModal()  { showTourPrompt.value = false }
const pasoGuia = ref(1)

// Refs paso 1
const refCentroEducativo = ref(null)
const refInfoSimulada    = ref(null)
const refBaseDatos       = ref(null)
const refInsertarEmpresa = ref(null)
const refBtnGuia         = ref(null)
// Refs paso 2
const refEncabezadoProblema = ref(null)
const refPreguntaFriccion   = ref(null)
const refExpectativas       = ref(null)
// Refs paso 3
const refFamiliaSelect     = ref(null)
const refNivelExigencia    = ref(null)
const refCicloGrid         = ref(null)
const refCursoAlumno       = ref(null)
const refModulosSection    = ref(null)
const refCantidadVariantes = ref(null)
const refGuardarEmpresa    = ref(null)
const refBtnGenerar        = ref(null)
// Refs reutilizados (de otra lógica existente)

// Tres conjuntos de pasos, cada uno para su paso de formulario
const guiaPasos1 = [
  { ref: 'refCentroEducativo', seccion: 'busqueda', texto: 'Selecciona primero tu centro educativo. Esto filtra el listado de empresas y ciclos, mostrando únicamente los vinculados a tu centro.' },
  { ref: 'refBuscadorEmpresa', seccion: 'busqueda', texto: 'Busca y selecciona la empresa con la que vas a generar el microreto. Puedes elegir entre empresas simuladas (creadas para practicar) y empresas reales colaboradoras de tu centro.' },
  { ref: 'refBotonesDemo',     seccion: 'acciones', texto: 'Carga una demo preconfigurada para ver cómo funciona el generador de principio a fin. Al elegir una familia profesional del desplegable, se rellenan automáticamente todos los campos de los tres pasos con datos de ejemplo listos para generar.' },
  { ref: 'refInfoSimulada',    seccion: 'acciones', texto: 'La IA se comporta como la empresa seleccionada en el Paso 1 y rellena automáticamente todos los campos del diagnóstico (Paso 2) simulando lo que esa empresa habría dicho en una reunión real. Muy útil si no has podido hacer la reunión con la empresa. Puedes revisar y editar los datos generados antes de continuar.' },
  { ref: 'refBaseDatos',       seccion: 'acciones', texto: 'Accede a la base de datos completa de la plataforma: empresas, centros, familias profesionales y ciclos formativos disponibles. Desde aquí puedes revisar o añadir empresas que no aparezcan en el buscador.' },
  { ref: 'refInsertarEmpresa', seccion: 'acciones', texto: 'Registra una nueva empresa en la base de datos o modifica los datos de la empresa actualmente seleccionada. Los cambios quedan guardados para todos los usuarios de tu centro.' },
  { ref: 'refBtnGuia',         seccion: null,       texto: 'Pulsa este botón en cualquier momento para volver a ver esta guía.' },
]
const guiaPasos2 = [
  { ref: 'refEncabezadoProblema', seccion: 'diagnostico', texto: 'Paso 2 — Realidad de la empresa: aquí transcribes lo que la empresa te ha contado en la reunión de diagnóstico. Estos datos son la materia prima con la que la IA construirá el microreto. Si no has hecho la reunión, puedes usar el botón "Información Simulada" del Paso 1.' },
  { ref: 'refPreguntaFriccion',   seccion: 'diagnostico', texto: 'El corazón del microreto: describe el área donde se produce el problema (¿marketing, producción, logística, atención al cliente...?) y explica por qué ocurre. Cuanto más específico y concreto seas aquí, mejor y más relevante será el microreto que genere la IA.' },
  { ref: 'refExpectativas',       seccion: 'diagnostico', texto: 'Indica qué resultado concreto esperas que el alumnado entregue al final del reto: un informe, un prototipo, un plan de acción, una maqueta digital... La IA usará esto para definir el objetivo del reto y los criterios de evaluación.' },
]
const guiaPasos3 = [
  { ref: 'refFamiliaSelect',     seccion: 'match', texto: 'Selecciona la familia profesional del ciclo que va a trabajar el reto. Esto determina qué ciclos aparecerán abajo y qué módulos y RA/CE se cruzarán con el diagnóstico de empresa.' },
  { ref: 'refNivelExigencia',    seccion: 'match', texto: 'Ajusta el nivel de dificultad según el dominio real del grupo: Básico para grupos en formación inicial, Medio para grupos estándar, Alto para grupos avanzados o de segundo año. La IA calibrará la complejidad del reto en consecuencia.' },
  { ref: 'refCicloGrid',         seccion: 'match', texto: 'Selecciona el ciclo formativo concreto del grupo que resolverá el reto. Aparecen los ciclos vinculados tanto a la empresa seleccionada como a tu centro educativo.' },
  { ref: 'refCursoAlumno',       seccion: 'match', texto: 'Indica si el grupo es de 1º o 2º curso. Este dato, combinado con el nivel de exigencia, permite a la IA ajustar la profundidad y el enfoque competencial del reto.' },
  { ref: 'refModulosSection',    seccion: 'match', texto: 'Si quieres que el reto se centre en un módulo concreto, selecciónalo aquí. Si lo dejas vacío, la IA cruzará el diagnóstico con todos los módulos del ciclo y elegirá el más relevante automáticamente.' },
  { ref: 'refCantidadVariantes', seccion: 'match', texto: 'Elige cuántas variantes del microreto quieres generar. Cada variante presenta un enfoque distinto para el mismo problema de empresa, permitiéndote elegir la que mejor encaje con tu grupo.' },
  { ref: 'refGuardarEmpresa',    seccion: 'match', texto: 'Si has completado o modificado datos de la empresa durante este flujo, guárdalos aquí antes de generar. Así el registro queda actualizado en la base de datos para futuros usos.' },
  { ref: 'refBtnGenerar',        seccion: 'match', texto: '¡Todo listo! Pulsa aquí para que la IA genere el microreto cruzando el diagnóstico de empresa del Paso 2 con el perfil académico del alumnado que has configurado en este paso. El proceso tarda unos segundos.' },
  { ref: 'refBtnGuia',           seccion: null,    texto: 'Pulsa este botón cuando quieras volver a ver esta guía en cualquier momento.' },
]

const guiaPasosActual = computed(() => {
  const docente = authStore.isDocente
  if (pasoActual.value === 1) {
    return docente
      ? guiaPasos1.filter(s => !['refBaseDatos', 'refInsertarEmpresa'].includes(s.ref))
      : guiaPasos1
  }
  if (pasoActual.value === 2) return guiaPasos2
  if (pasoActual.value === 3) {
    return docente
      ? guiaPasos3.filter(s => s.ref !== 'refGuardarEmpresa')
      : guiaPasos3
  }
  return []
})

const tourRefs = {
  // Paso 1
  refCentroEducativo,
  refBuscadorEmpresa: buscadorRef,
  refBotonesDemo: demoSelectorRef,
  refInfoSimulada,
  refBaseDatos,
  refInsertarEmpresa,
  refBtnGuia,
  // Paso 2
  refEncabezadoProblema,
  refPreguntaFriccion,
  refExpectativas,
  // Paso 3
  refFamiliaSelect,
  refNivelExigencia,
  refCicloGrid,
  refCursoAlumno,
  refModulosSection,
  refCantidadVariantes,
  refGuardarEmpresa,
  refBtnGenerar,
}

const tourTargetActivo = computed(() => {
  if (!modoGuia.value) return null
  return guiaPasosActual.value[pasoGuia.value - 1]?.ref ?? null
})

const seccionActiva = computed(() => {
  if (!modoGuia.value) return null
  return guiaPasosActual.value[pasoGuia.value - 1]?.seccion ?? null
})

const bocadilloPos = ref({ top: 0, left: 0, arrowLeft: 130, dir: 'top' })

function recalcularBocadillo() {
  const paso = guiaPasosActual.value[pasoGuia.value - 1]
  if (!paso) return
  const rawEl = tourRefs[paso.ref]?.value
  const el = rawEl?.$el ?? rawEl
  if (!el) return
  const rect = el.getBoundingClientRect()
  const TOOLTIP_W = 272
  const TOOLTIP_H = 155
  const MARGIN    = 12
  const WIN_W = window.innerWidth
  const WIN_H = window.innerHeight

  const visibleTop    = Math.max(0, rect.top)
  const visibleBottom = Math.min(WIN_H, rect.bottom)
  const centerX       = rect.left + rect.width / 2

  let tooltipLeft = centerX - TOOLTIP_W / 2
  tooltipLeft = Math.max(10, Math.min(tooltipLeft, WIN_W - TOOLTIP_W - 10))
  const arrowLeft = Math.max(18, Math.min(centerX - tooltipLeft, TOOLTIP_W - 18))

  let dir, tooltipTop
  if (WIN_H - visibleBottom > TOOLTIP_H + MARGIN) {
    dir = 'top'
    tooltipTop = visibleBottom + MARGIN
  } else if (visibleTop > TOOLTIP_H + MARGIN) {
    dir = 'bottom'
    tooltipTop = visibleTop - MARGIN - TOOLTIP_H
  } else {
    dir = 'top'
    tooltipTop = WIN_H * 0.6
  }
  tooltipTop = Math.max(10, Math.min(tooltipTop, WIN_H - TOOLTIP_H - 10))

  bocadilloPos.value = { top: tooltipTop, left: tooltipLeft, arrowLeft, dir }
}

async function avanzarGuia() {
  if (pasoGuia.value < guiaPasosActual.value.length) {
    pasoGuia.value++
    await nextTick()
    scrollYRecalcular()
  } else {
    modoGuia.value = false
  }
}

function saltarGuia() {
  modoGuia.value = false
}

async function scrollYRecalcular() {
  const paso = guiaPasosActual.value[pasoGuia.value - 1]
  if (!paso) return
  const rawEl = tourRefs[paso.ref]?.value
  const el = rawEl?.$el ?? rawEl
  if (el) el.scrollIntoView({ behavior: 'instant', block: 'nearest' })
  requestAnimationFrame(() => requestAnimationFrame(recalcularBocadillo))
}

function onScrollGuia() {
  if (modoGuia.value) requestAnimationFrame(recalcularBocadillo)
}

watch(modoGuia, async (v) => {
  tourActivo.value = v
  if (v) {
    window.addEventListener('scroll', onScrollGuia, { passive: true })
    await nextTick()
    requestAnimationFrame(() => requestAnimationFrame(recalcularBocadillo))
  } else {
    window.removeEventListener('scroll', onScrollGuia)
  }
})

// ── ESTADO DE CONTACTO (editable inline) ──────────────
const ESTADOS_OPCIONES_GEN = [
  'Pendiente de llamar',
  'Llamado - Información obtenida',
  'Llamado - Negativa',
  'Llamado - Llamar más tarde',
  'En colaboración activa',
  'Descartada',
]
const ESTADO_BADGE_GEN = {
  'Pendiente de llamar':            { bg: 'bg-amber-100',    text: 'text-amber-700',  border: 'border-amber-300',    dot: 'bg-amber-400', pulse: true  },
  'Llamado - Información obtenida': { bg: 'bg-[#00A859]/10', text: 'text-[#00A859]',  border: 'border-[#00A859]/30', dot: 'bg-[#00A859]', pulse: false },
  'Llamado - Negativa':             { bg: 'bg-red-50',       text: 'text-red-600',    border: 'border-red-200',      dot: 'bg-red-400',   pulse: false },
  'Llamado - Llamar más tarde':     { bg: 'bg-blue-50',      text: 'text-blue-600',   border: 'border-blue-200',     dot: 'bg-blue-400',  pulse: false },
  'En colaboración activa':         { bg: 'bg-gray-100',     text: 'text-gray-700',   border: 'border-gray-300',     dot: 'bg-gray-600',  pulse: false },
  'Descartada':                     { bg: 'bg-gray-50',      text: 'text-gray-400',   border: 'border-gray-200',     dot: 'bg-gray-300',  pulse: false },
}
const estadoBadgeGen = (estado) =>
  ESTADO_BADGE_GEN[estado] ?? { bg: 'bg-gray-100', text: 'text-gray-500', border: 'border-gray-200', dot: 'bg-gray-300', pulse: false }

const estadoDropdownGenAbierto = ref(false)
const guardandoEstadoGen       = ref(false)

async function guardarEstadoGen(nuevoEstado) {
  if (!empresaDetalle.value?.id || guardandoEstadoGen.value) return
  guardandoEstadoGen.value = true
  estadoDropdownGenAbierto.value = false
  try {
    await api.patch(`/empresas/${empresaDetalle.value.id}/estado`, { estadoContacto: nuevoEstado || null })
    empresaDetalle.value.estado_contacto = nuevoEstado || null
    const emp = empresas.value.find(e => String(e.id) === String(empresaDetalle.value.id))
    if (emp) emp.estado_contacto = nuevoEstado || null
  } catch (e) {
    console.error('Error actualizando estado:', e)
  } finally {
    guardandoEstadoGen.value = false
  }
}

</script>

<template>
  <div class="min-h-screen p-4 md:p-12 transition-colors duration-500 font-sans text-[#1F2937] overflow-x-hidden pt-12 md:pt-12">
    
    <!-- ══════════ TOUR OVERLAY ════════════════════════════════════════════ -->
  <Transition name="sp-fade">
    <div v-if="modoGuia" class="fixed inset-0 z-[9990] pointer-events-none">

      <!-- ── PASO 2: modal explicativo centrado ── -->
      <template v-if="pasoActual === 2">
        <div class="absolute inset-x-0 top-12 bottom-0 bg-black/50 pointer-events-auto" />
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none" style="z-index:9992">
          <div class="pointer-events-auto w-[380px] max-w-[calc(100vw-2rem)] bg-[#1a2332] border border-white/15 rounded-3xl shadow-2xl p-7 text-white" @click.stop>
            <!-- Cabecera -->
            <div class="flex items-center gap-3 mb-5">
              <div class="w-9 h-9 rounded-2xl bg-[#00A859]/20 flex items-center justify-center text-[#00A859] font-black text-sm shrink-0">
                {{ pasoGuia }}
              </div>
              <div class="flex-1">
                <p class="text-[9px] font-black uppercase tracking-widest text-white/40 mb-0.5">Paso {{ pasoGuia }} de {{ guiaPasosActual.length }}</p>
                <div class="flex gap-1">
                  <span v-for="i in guiaPasosActual.length" :key="i"
                        class="h-[3px] rounded-full transition-all duration-300"
                        :class="i <= pasoGuia ? 'bg-[#00A859] w-5' : 'bg-white/20 w-3'" />
                </div>
              </div>
            </div>
            <!-- Texto -->
            <p class="text-[13px] text-white/85 leading-relaxed mb-6">{{ guiaPasosActual[pasoGuia - 1].texto }}</p>
            <!-- Botones -->
            <div class="flex items-center gap-2">
              <button @click="avanzarGuia"
                      class="flex-1 py-2.5 rounded-xl bg-[#00A859] text-white text-[10px] font-black uppercase tracking-widest hover:bg-[#00A859]/90 transition-all">
                {{ pasoGuia < guiaPasosActual.length ? 'Siguiente →' : 'Finalizar' }}
              </button>
              <button @click="saltarGuia"
                      class="px-3 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white/40 text-[10px] font-black uppercase tracking-widest hover:text-white/60 transition-all">
                Saltar
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- ── PASO 1 & 3: bocadillo spotlight ── -->
      <template v-else>
        <!-- Backdrop bloqueante transparente — bloquea interacción sin oscurecer el elemento activo -->
        <!-- top-12 para no tapar el TopBar (h-12) y que el logo siga siendo clickable -->
        <div class="absolute inset-x-0 top-12 bottom-0 pointer-events-auto" />

        <div class="absolute pointer-events-auto"
             :style="{ top: bocadilloPos.top + 'px', left: bocadilloPos.left + 'px', width: '272px', zIndex: 9992 }">

          <div v-if="bocadilloPos.dir === 'top'"
               class="absolute -top-[10px] w-0 h-0"
               :style="{
                 left: bocadilloPos.arrowLeft + 'px',
                 transform: 'translateX(-50%)',
                 borderLeft: '9px solid transparent',
                 borderRight: '9px solid transparent',
                 borderBottom: '10px solid #1a2332'
               }" />

          <div class="bg-[#1a2332] border border-white/15 rounded-2xl shadow-2xl p-4 text-white">
            <div class="flex items-center justify-between mb-2.5">
              <div class="flex gap-1 items-center">
                <span v-for="i in guiaPasosActual.length" :key="i"
                      class="h-[3px] rounded-full transition-all duration-300"
                      :class="i <= pasoGuia ? 'bg-[#00A859] w-5' : 'bg-white/20 w-3'" />
              </div>
              <span class="text-[9px] font-bold text-white/40">{{ pasoGuia }}/{{ guiaPasosActual.length }}</span>
            </div>
            <p class="text-[11px] text-white/85 leading-relaxed mb-3">{{ guiaPasosActual[pasoGuia - 1].texto }}</p>
            <div class="flex items-center gap-2">
              <button @click="avanzarGuia"
                      class="flex-1 py-1.5 rounded-xl bg-[#00A859] text-white text-[9px] font-black uppercase tracking-widest hover:bg-[#00A859]/90 transition-all">
                {{ pasoGuia < guiaPasosActual.length ? 'Siguiente →' : 'Finalizar' }}
              </button>
              <button @click="saltarGuia"
                      class="px-2.5 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white/40 text-[9px] font-black uppercase tracking-widest hover:text-white/60 transition-all">
                Saltar
              </button>
            </div>
          </div>

          <div v-if="bocadilloPos.dir === 'bottom'"
               class="absolute -bottom-[10px] w-0 h-0"
               :style="{
                 left: bocadilloPos.arrowLeft + 'px',
                 transform: 'translateX(-50%)',
                 borderLeft: '9px solid transparent',
                 borderRight: '9px solid transparent',
                 borderTop: '10px solid #1a2332'
               }" />
        </div>
      </template>

    </div>
  </Transition>

  <div class="max-w-6xl mx-auto">

      <header class="mb-10 text-center flex flex-col items-center">
        <div class="inline-flex items-center mb-8 bg-[#1F2937] py-3 sm:py-4 pr-6 sm:pr-10 pl-4 sm:pl-6 rounded-[3rem] shadow-lg border border-[#333333] transition-all duration-1000 ease-out transform"
             :class="isLoaded ? 'translate-y-0 opacity-100' : '-translate-y-10 opacity-0'">
          <img src="../assets/logo.png" alt="Logo DuaLab" class="h-20 sm:h-32 md:h-40 w-auto object-contain -mr-3 sm:-mr-4 md:-mr-8 relative z-10" />
          <span class="font-black text-2xl sm:text-4xl md:text-5xl tracking-tighter uppercase text-white italic relative z-20">
            Dua<span class="text-[#00A859]">Lab</span><span class="text-[#99CC33] not-italic text-sm sm:text-lg md:text-xl ml-1">Studio Tool</span>
          </span>
        </div>
        
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-4 text-[#121212] transition-all duration-1000 delay-150 ease-out transform"
            :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
          Factoría de <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">Retos</span>
        </h1>
        
        <p class="text-gray-500 max-w-2xl mx-auto text-base md:text-lg leading-relaxed font-medium transition-all duration-1000 delay-300 ease-out transform"
           :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
          Convierte problemas empresariales reales en retos educativos clasificados por el currículo oficial.
        </p>

        <!-- Botón Guía -->
        <div class="flex justify-center mt-5 transition-all duration-1000 ease-out transform"
             :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'"
             style="transition-delay:450ms">
          <button
            ref="refBtnGuia"
            @click="modoGuia = true; pasoGuia = 1"
            class="flex items-center gap-2 px-4 py-2 bg-blue-50 rounded-2xl border border-blue-100
                   shadow-sm text-blue-500 text-xs font-black uppercase tracking-wider
                   hover:bg-blue-100 hover:border-blue-200 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Activar Guía
          </button>
        </div>

      </header>

      <div class="max-w-3xl mx-auto mb-16 relative transition-all duration-1000 delay-500 ease-out transform"
           :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
        <div class="flex justify-between items-center relative z-10">
          <div v-for="step in totalPasos" :key="step" class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center font-black transition-all duration-500 shadow-sm"
              :class="pasoActual >= step ? 'bg-gradient-to-r from-[#00A859] to-[#99CC33] text-white scale-110 shadow-lg' : 'bg-white border-2 border-gray-200 text-gray-400'">
              <span v-if="pasoActual > step"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>
              <span v-else>{{ step }}</span>
            </div>
            <span class="text-[10px] font-black uppercase mt-3 tracking-widest text-center" :class="pasoActual >= step ? 'text-[#00A859]' : 'text-gray-400'">
              {{ step === 1 ? '1. Datos Empresa' : step === 2 ? '2. El Problema' : '3. Match Académico' }}
            </span>
          </div>
        </div>
        <div class="absolute top-6 left-0 w-full h-1 bg-gray-200 -z-0 rounded-full"></div>
        <div class="absolute top-6 left-0 h-1 bg-gradient-to-r from-[#00A859] to-[#99CC33] transition-all duration-700 -z-0 rounded-full" :style="{ width: ((pasoActual - 1) / (totalPasos - 1)) * 100 + '%' }"></div>
      </div>

      <main class="min-h-[400px]">
        <transition name="fade" mode="out-in">
          <div v-if="pasoActual === 1" class="space-y-8 animate-in slide-in-from-bottom-4 duration-500">
            <section class="bg-white rounded-[2.5rem] p-8 md:p-10 border border-gray-100 shadow-[0_20px_50px_rgb(0,0,0,0.05)] relative z-10">
              <transition name="fade">
                <div v-if="seleccion.empresaNombre" class="absolute -top-4 left-8">
                  <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white text-[#1F2937] text-xs font-bold tracking-wide border border-gray-100 border-b-0 shadow-[0_-4px_8px_rgb(0,0,0,0.04)]">
                    <svg class="w-3.5 h-3.5 shrink-0 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Empresa: {{ seleccion.empresaNombre }}
                  </span>
                </div>
              </transition>

              <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 mb-8">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 rounded-2xl bg-[#00A859]/10 flex items-center justify-center text-[#00A859]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                  </div>
                  <h2 class="text-2xl font-black uppercase tracking-tight text-[#1F2937]">Buscar en DuaLab</h2>
                </div>
                
                <div class="flex flex-wrap items-center gap-2"
                     :class="{ 'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'acciones' }">
                  <!-- Cargar Demo -->
                  <div class="relative" ref="demoSelectorRef"
                       :class="{ 'tour-active': tourTargetActivo === 'refBotonesDemo' }">
                    <button
                      @click="mostrarSelectorDemo = !mostrarSelectorDemo"
                      :class="esModoDemo ? 'bg-[#00A859]/10 text-[#00A859] border-[#00A859]/30 hover:bg-[#00A859]/15' : 'bg-white text-[#00A859] hover:bg-gray-50 border-gray-200 hover:border-[#00A859] shadow-sm'"
                      class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 border">
                      <template v-if="esModoDemo">
                        <svg class="w-3.5 h-3.5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        <span>DEMO ACTIVA</span>
                        <svg class="w-3 h-3 transition-transform duration-200" :class="mostrarSelectorDemo ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                      </template>
                      <template v-else>
                        <span>Cargar Demo</span>
                        <svg class="w-3 h-3 transition-transform duration-200" :class="mostrarSelectorDemo ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                      </template>
                    </button>
                    <Transition name="dropdown">
                      <div v-if="mostrarSelectorDemo && demosDisponibles.length > 0"
                        class="absolute left-0 top-full mt-2 bg-white border border-gray-200 rounded-2xl shadow-xl z-50 min-w-[230px] overflow-hidden">
                        <button
                          v-for="demo in demosDisponibles"
                          :key="demo.id"
                          @click="cargarDemo(demo.familia_profesional)"
                          class="w-full text-left px-5 py-3 text-sm font-semibold text-[#1F2937] hover:bg-[#00A859]/5 hover:text-[#00A859] transition-colors border-b border-gray-100 last:border-0 flex items-center gap-3">
                          <svg class="w-4 h-4 shrink-0 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="demosIconos[demo.familia_profesional] || iconoFallback"/>
                          </svg>
                          {{ demo.familia_profesional }}
                        </button>
                      </div>
                    </Transition>
                  </div>

                  <!-- Información Simulada -->
                  <button ref="refInfoSimulada"
                    @click="toggleInfoSimulada()"
                    :disabled="esModoDemo || cargandoSimulacion"
                    :class="[
                      esModoDemo || cargandoSimulacion ? 'opacity-40 cursor-not-allowed bg-white text-gray-400 border-gray-200' : esInfoSimulada ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-md' : 'bg-white text-gray-500 hover:bg-gray-50 border-gray-200 shadow-sm',
                      tourTargetActivo === 'refInfoSimulada' ? 'tour-active' : ''
                    ]"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 border">
                    <svg v-if="cargandoSimulacion" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <svg v-else-if="esInfoSimulada" class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span v-if="cargandoSimulacion">Generando...</span>
                    <span v-else>Información Simulada</span>
                  </button>

                  <!-- Separador vertical -->
                  <span class="hidden sm:block w-px h-6 bg-gray-200 mx-1 rounded-full" />

                  <!-- Ver base de datos — solo admin -->
                  <RouterLink
                    v-if="!esDocente"
                    ref="refBaseDatos"
                    to="/base-datos"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 border bg-white text-gray-600 hover:bg-gray-50 border-gray-200 hover:border-gray-400 shadow-sm"
                    :class="{ 'tour-active': tourTargetActivo === 'refBaseDatos' }"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <ellipse cx="12" cy="5" rx="9" ry="3"/>
                      <path d="M21 12c0 1.657-4.03 3-9 3S3 13.657 3 12"/>
                      <path d="M3 5v14c0 1.657 4.03 3 9 3s9-1.343 9-3V5"/>
                    </svg>
                    Base de datos
                  </RouterLink>

                  <!-- Insertar / Modificar empresa — solo admin -->
                  <button v-if="!esDocente" ref="refInsertarEmpresa" @click="abrirModalEmpresa"
                    :class="[
                      seleccion.empresaId
                        ? 'bg-[#00A859] text-white border-[#00A859] hover:bg-[#007a42] shadow-md'
                        : 'bg-white text-[#1F2937] hover:bg-gray-50 border-gray-200 shadow-sm',
                      tourTargetActivo === 'refInsertarEmpresa' ? 'tour-active' : ''
                    ]"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 border">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="seleccion.empresaId" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7z"/>
                    </svg>
                    {{ seleccion.empresaId ? 'Modificar datos empresa' : 'Insertar nueva empresa' }}
                  </button>

                  <!-- Vaciar -->
                  <button @click="limpiarFormulario" class="px-5 py-2.5 bg-white text-red-500 hover:bg-red-50 hover:border-red-500 border border-gray-200 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Vaciar
                  </button>
                </div>
              </div>

              <div v-if="esModoDemo"
                class="mb-6 flex items-center gap-3 bg-[#00A859]/5 border border-[#00A859]/20 rounded-2xl px-5 py-3">
                <svg class="w-4 h-4 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs font-bold text-[#00A859] uppercase tracking-widest">
                  Modo Demo activo — los campos están bloqueados. Pulsa "Vaciar" para editarlos.
                </p>
              </div>

              <div class="mb-10 relative z-20 grid grid-cols-1 md:grid-cols-2 gap-6"
                   :class="{ 'tour-seccion-blur': modoGuia && seccionActiva !== null && seccionActiva !== 'busqueda' }">
                <!-- PASO 1: Centro educativo obligatorio -->
                <div ref="refCentroEducativo"
                     class="rounded-2xl transition-all duration-300"
                     :class="[
                       !centroFiltro && !esModoDemo ? 'step-glow-active' : '',
                       tourTargetActivo === 'refCentroEducativo' ? 'tour-active' : ''
                     ]">
                  <label class="label-style" :class="!centroFiltro && !esModoDemo ? '!text-[#00A859]' : ''">
                    {{ !centroFiltro && !esModoDemo ? '① Centro Educativo *' : 'Centro Educativo *' }}
                  </label>
                  <input v-if="esModoDemo" type="text" value="IES DEMO" disabled class="input-style opacity-70 cursor-not-allowed bg-gray-50" />
                  <input v-else-if="esDocente && authStore.userCentroNombre"
                    type="text"
                    :value="authStore.userCentroNombre"
                    disabled
                    class="input-style opacity-80 cursor-not-allowed bg-gray-50"
                    title="Tu centro educativo está fijado según tu cuenta"
                  />
                  <select v-else v-model="centroFiltro" class="input-style">
                    <option value="">Selecciona tu centro...</option>
                    <option v-for="centro in centrosDisponibles" :key="centro" :value="centro">{{ centro }}</option>
                  </select>
                </div>

                <!-- PASO 2: Buscar empresa (bloqueado hasta elegir centro) -->
                <div class="relative rounded-2xl transition-all duration-300" ref="buscadorRef"
                     :class="[
                       centroFiltro && !seleccion.empresaId && !esModoDemo ? 'step-glow-empresa' : '',
                       tourTargetActivo === 'refBuscadorEmpresa' ? 'tour-active' : ''
                     ]">
                  <label class="label-style"
                    :class="centroFiltro && !seleccion.empresaId && !esModoDemo ? '!text-[#00A859]' : (!centroFiltro && !esModoDemo ? 'opacity-40' : '')">
                    <template v-if="centroFiltro && !seleccion.empresaId && !esModoDemo">② Elige empresa</template>
                    <template v-else-if="!centroFiltro && !esModoDemo">Empresa (elige primero un centro)</template>
                    <template v-else>Buscar empresa</template>
                  </label>

                  <!-- Filtro simulada/real — visible solo cuando hay centro seleccionado -->
                  <div v-if="centroFiltro && !esModoDemo" class="flex items-center gap-1.5 mb-2">
                    <button
                      v-for="(label, val) in { '': 'Todas', 'simulada': 'Simuladas', 'real': 'Verídicas' }"
                      :key="val"
                      @click="filtroTipoEmpresa = val; if (!buscadorEmpresa) mostrarDropdownEmpresas = true"
                      :class="filtroTipoEmpresa === val
                        ? (val === 'simulada' ? 'bg-[#1F2937] text-white border-[#1F2937]' : val === 'real' ? 'bg-[#00A859] text-white border-[#00A859]' : 'bg-gray-200 text-gray-700 border-gray-300')
                        : 'bg-white text-gray-400 border-gray-200 hover:border-gray-400'"
                      class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border transition-all"
                    >{{ label }}</button>
                  </div>

                  <div class="relative">
                    <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none">
                      <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                      </svg>
                    </div>

                    <input
                      v-model="buscadorEmpresa"
                      @input="onBuscadorInput"
                      @focus="mostrarDropdownEmpresas = true"
                      :disabled="!centroFiltro && !esModoDemo"
                      autocomplete="new-password"
                      name="buscar-empresa-dualab"
                      type="search"
                      class="input-style !pl-14 text-lg"
                      :placeholder="!centroFiltro && !esModoDemo ? 'Selecciona primero un centro...' : 'Ej: Fundación Sergio Alonso...'"
                    />
                  </div>

                  <Transition name="dropdown">
                    <div
                      v-if="mostrarDropdownEmpresas && empresasFiltradasBusqueda.length > 0"
                      class="absolute w-full mt-2 bg-[#1F2937] border border-[#374151] rounded-2xl shadow-2xl max-h-64 overflow-y-auto z-50"
                    >
                      <div v-if="buscadorEmpresa" class="px-6 pt-3 pb-1">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                          {{ empresasFiltradasBusqueda.length }} resultado(s) para "{{ buscadorEmpresa }}"
                        </span>
                      </div>

                      <button
                        v-for="emp in empresasFiltradasBusqueda"
                        :key="emp.id"
                        @mousedown.prevent="seleccionarEmpresa(emp)"
                        class="w-full text-left px-6 py-4 border-b border-[#374151] hover:bg-[#374151] transition-colors last:border-0"
                      >
                        <span class="font-bold text-white block">{{ emp.nombre_comercial }}</span>
                        <span class="text-xs text-gray-400 uppercase tracking-widest">
                          {{ emp.sector || 'Sin sector especificado' }}
                        </span>
                      </button>
                    </div>
                  </Transition>

                  <!-- Mensaje cuando no hay resultados (oculto en modo demo) -->
                  <div
                    v-if="!esModoDemo && mostrarDropdownEmpresas && buscadorEmpresa && empresasFiltradasBusqueda.length === 0"
                    class="absolute w-full mt-2 bg-[#1F2937] border border-[#374151] rounded-2xl shadow-2xl z-50 px-6 py-5"
                  >
                    <p class="text-gray-400 text-sm">No se encontró ninguna empresa con ese nombre.</p>
                  </div>
                </div>
              </div>

            <div v-if="empresaDetalle" class="bg-white rounded-3xl p-8 border border-gray-100 mb-8 animate-in fade-in duration-500">

            <!-- Cabecera empresa -->
            <div class="mb-8">
              <div class="flex items-center gap-3 mb-4">
                <div class="w-2 h-6 bg-[#00A859] rounded-full shrink-0"></div>
                <h3 class="font-black text-[#1F2937] uppercase tracking-widest text-sm">Ficha de Empresa</h3>
              </div>

              <!-- Nombre + razón social -->
              <p class="font-black text-[#1F2937] text-xl leading-tight ml-5">{{ empresaDetalle.nombre_comercial }}</p>
              <p v-if="empresaDetalle.razon_social && empresaDetalle.razon_social !== empresaDetalle.nombre_comercial"
                class="text-sm text-gray-400 ml-5 mt-0.5">{{ empresaDetalle.razon_social }}</p>

              <!-- Chips: sector, tamaño, CIF -->
              <div class="flex flex-wrap gap-2 ml-5 mt-3">
                <span v-if="empresaDetalle.sector"
                  class="text-[10px] font-black uppercase tracking-widest bg-[#00A859]/10 text-[#00A859] px-2.5 py-1 rounded-full">
                  {{ empresaDetalle.sector }}
                </span>
                <span v-if="empresaDetalle.tamano"
                  class="text-[10px] font-black uppercase tracking-widest bg-blue-50 text-blue-500 px-2.5 py-1 rounded-full">
                  {{ empresaDetalle.tamano }}
                </span>
                <span v-if="empresaDetalle.cif"
                  class="text-[10px] font-black uppercase tracking-widest bg-gray-100 text-gray-500 px-2.5 py-1 rounded-full">
                  CIF: {{ empresaDetalle.cif }}
                </span>
              </div>
            </div>
              
              <!-- Estado de contacto (editable inline) — solo admin -->
              <div v-if="!esModoDemo && !esDocente" class="mb-6">
                <div class="flex items-center gap-3 mb-3 flex-wrap">
                  <h3 class="font-black text-[#1F2937] uppercase tracking-widest text-sm">Estado de Contacto</h3>

                  <!-- Dropdown editable -->
                  <div class="relative inline-block" @click.stop>
                    <button
                      @click="estadoDropdownGenAbierto = !estadoDropdownGenAbierto"
                      :disabled="guardandoEstadoGen"
                      :class="empresaDetalle.estado_contacto
                        ? [estadoBadgeGen(empresaDetalle.estado_contacto).bg, estadoBadgeGen(empresaDetalle.estado_contacto).text, estadoBadgeGen(empresaDetalle.estado_contacto).border]
                        : 'bg-gray-100 text-gray-400 border-gray-200'"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border cursor-pointer hover:opacity-80 transition-opacity disabled:opacity-40"
                    >
                      <span v-if="empresaDetalle.estado_contacto"
                        :class="[estadoBadgeGen(empresaDetalle.estado_contacto).dot, estadoBadgeGen(empresaDetalle.estado_contacto).pulse ? 'animate-pulse' : '']"
                        class="w-1.5 h-1.5 rounded-full shrink-0"></span>
                      <svg v-if="guardandoEstadoGen" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                      </svg>
                      {{ guardandoEstadoGen ? 'Guardando...' : (empresaDetalle.estado_contacto || 'Sin estado') }}
                      <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                      </svg>
                    </button>
                    <Transition name="dropdown">
                      <div v-if="estadoDropdownGenAbierto"
                        class="absolute left-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden min-w-[240px]">
                        <button
                          v-for="opcion in ESTADOS_OPCIONES_GEN" :key="opcion"
                          @click="guardarEstadoGen(opcion)"
                          :class="[estadoBadgeGen(opcion).bg, estadoBadgeGen(opcion).text, 'hover:opacity-80']"
                          class="w-full text-left px-4 py-2.5 flex items-center gap-2 text-[11px] font-black uppercase tracking-widest border-b border-white/40 last:border-0 transition-opacity"
                        >
                          <span :class="[estadoBadgeGen(opcion).dot, estadoBadgeGen(opcion).pulse ? 'animate-pulse' : '']" class="w-2 h-2 rounded-full shrink-0"></span>
                          {{ opcion }}
                        </button>
                        <button
                          @click="guardarEstadoGen('')"
                          class="w-full text-left px-4 py-2.5 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-gray-400 hover:bg-gray-50 border-t border-gray-100 transition-colors"
                        >
                          <span class="w-2 h-2 rounded-full shrink-0 bg-gray-300"></span>
                          Sin estado
                        </button>
                      </div>
                    </Transition>
                  </div>
                </div>

                <!-- Banner para pendiente de llamar -->
                <Transition name="fade">
                  <div v-if="empresaDetalle.estado_contacto === 'Pendiente de llamar'"
                    class="flex items-center gap-3 bg-amber-50 border border-amber-300 rounded-2xl px-4 py-3">
                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <p class="text-xs font-bold text-amber-700">
                      Empresa <span class="uppercase tracking-wider">pendiente de llamar</span> — contacta con ella antes de generar retos con su información.
                    </p>
                  </div>
                </Transition>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-6 border-t border-gray-200/60">
                <div v-if="empresaDetalle.persona_contacto || empresaDetalle.telefono || empresaDetalle.email_general">
                  <p class="text-[10px] uppercase font-bold text-gray-400 tracking-widest mb-1">Contacto Directo</p>
                  <p v-if="empresaDetalle.persona_contacto" class="font-semibold text-[#1F2937] text-sm">{{ empresaDetalle.persona_contacto }}</p>
                  <p v-if="empresaDetalle.telefono" class="text-gray-600 text-sm flex items-center gap-2 mt-1">
                    <svg class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg> {{ empresaDetalle.telefono }}
                  </p>
                  <p v-if="empresaDetalle.email_general" class="text-gray-600 text-sm flex items-center gap-2 mt-1 truncate">
                    <svg class="w-4 h-4 shrink-0 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> {{ empresaDetalle.email_general }}
                  </p>
                </div>

                <div v-if="empresaDetalle.direccion || empresaDetalle.municipio || empresaDetalle.codigo_postal">
                  <p class="text-[10px] uppercase font-bold text-gray-400 tracking-widest mb-1">Ubicación</p>
                  <p class="text-sm text-gray-600 leading-tight">
                    {{ empresaDetalle.direccion }} {{ empresaDetalle.numero }} <br>
                    {{ empresaDetalle.codigo_postal }} - {{ empresaDetalle.municipio }} <span v-if="empresaDetalle.provincia">({{ empresaDetalle.provincia }})</span>
                  </p>
                </div>

                <div v-if="empresaDetalle.actividad || empresaDetalle.web">
                  <p class="text-[10px] uppercase font-bold text-gray-400 tracking-widest mb-1">Actividad / Web</p>
                  <p v-if="empresaDetalle.actividad" class="text-sm text-gray-600 line-clamp-2" :title="empresaDetalle.actividad">{{ empresaDetalle.actividad }}</p>
                  <a v-if="empresaDetalle.web"
                     :href="safeUrl(empresaDetalle.web)"
                     target="_blank"
                     rel="noopener noreferrer"
                     class="text-[#00A859] hover:underline font-bold text-sm truncate flex items-center gap-1 mt-1">
                    {{ empresaDetalle.web.replace(/^https?:\/\//, '') }}
                  </a>
                </div>
              </div>
            </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative">

                <div v-if="!tieneContextoEmpresa" class="absolute inset-0 bg-white/70 z-10 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                  <div class="bg-[#1F2937] text-white px-5 py-4 rounded-2xl shadow-xl flex flex-col sm:flex-row items-center gap-3 mx-4">
                    <div class="flex items-center gap-2">
                      <span class="bg-[#00A859] text-white rounded-full w-5 h-5 flex items-center justify-center text-[9px] font-black shrink-0">1</span>
                      <span class="text-[10px] font-black uppercase tracking-widest">Elige tu centro</span>
                    </div>
                    <span class="text-gray-500 text-xs hidden sm:block">·</span>
                    <div class="flex items-center gap-2">
                      <span class="bg-[#00A859] text-white rounded-full w-5 h-5 flex items-center justify-center text-[9px] font-black shrink-0">2</span>
                      <span class="text-[10px] font-black uppercase tracking-widest">Elige empresa</span>
                    </div>
                    <span class="text-gray-500 text-xs hidden sm:block">·</span>
                    <div class="flex items-center gap-2">
                      <span class="bg-gray-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-[9px] font-black shrink-0">3</span>
                      <span class="text-[10px] font-black uppercase tracking-widest">o usa la Demo 👆</span>
                    </div>
                  </div>
                </div>
                
                <div>
                  <label class="label-style" :class="!seleccion.empresaSector && (seleccion.empresaId || seleccion.empresaNombre) ? 'text-red-500' : ''">Sector de Actividad *</label>

                  <!-- Modo demo: input bloqueado -->
                  <input v-if="esModoDemo" :value="seleccion.empresaSector" disabled class="input-style opacity-70 cursor-not-allowed bg-gray-50" />

                  <!-- Modo libre: campo de texto con vuelta al listado -->
                  <div v-else-if="sectorEsLibre" class="flex gap-2">
                    <input
                      v-model="seleccion.empresaSector"
                      class="input-style flex-1"
                      placeholder="Escribe el sector de actividad..."
                      autofocus
                    />
                    <button
                      type="button"
                      @click="sectorEsLibre = false"
                      class="px-4 py-2 rounded-xl border border-gray-200 text-gray-500 text-xs font-bold hover:bg-gray-50 transition-all whitespace-nowrap"
                    >← Lista</button>
                  </div>

                  <!-- Modo dropdown -->
                  <div v-else class="relative" ref="sectorRef">
                    <button
                      type="button"
                      @click="mostrarDropdownSector = !mostrarDropdownSector"
                      class="input-style w-full text-left flex items-center justify-between gap-2"
                      :class="!seleccion.empresaSector && (seleccion.empresaId || seleccion.empresaNombre) ? 'border-red-500 bg-red-900/30 text-red-400' : ''"
                    >
                      <span :class="seleccion.empresaSector ? 'text-[#1F2937]' : (!seleccion.empresaSector && (seleccion.empresaId || seleccion.empresaNombre) ? 'text-red-400' : 'text-gray-400')">
                        {{ seleccion.empresaSector || '¡FALTA INFO! Selecciona...' }}
                      </span>
                      <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200" :class="mostrarDropdownSector ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                      </svg>
                    </button>

                    <Transition name="dropdown">
                      <div v-if="mostrarDropdownSector"
                        class="absolute w-full mt-2 bg-[#1F2937] border border-[#374151] rounded-2xl shadow-2xl max-h-64 overflow-y-auto z-50">
                        <button
                          v-for="sector in sectoresDisponibles"
                          :key="sector"
                          type="button"
                          @mousedown.prevent="seleccionarSector(sector)"
                          class="w-full text-left px-6 py-3 border-b border-[#374151] hover:bg-[#374151] transition-colors last:border-0 flex items-center justify-between"
                          :class="sector === seleccion.empresaSector ? 'bg-[#374151]' : ''"
                        >
                          <span class="font-semibold text-white text-sm">{{ sector }}</span>
                          <svg v-if="sector === seleccion.empresaSector" class="w-4 h-4 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                          </svg>
                        </button>
                        <button
                          type="button"
                          @mousedown.prevent="activarSectorLibre"
                          class="w-full text-left px-6 py-3 hover:bg-[#374151] transition-colors flex items-center gap-2 border-t border-[#374151]"
                        >
                          <svg class="w-4 h-4 text-[#99CC33] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                          </svg>
                          <span class="text-[#99CC33] text-sm font-bold uppercase tracking-widest">Escribir</span>
                        </button>
                      </div>
                    </Transition>
                  </div>
                </div>

                <div>
                  <label class="label-style" :class="!seleccion.empresaTamano && (seleccion.empresaId || seleccion.empresaNombre) ? 'text-red-500' : ''">Tamaño de la Empresa *</label>
                  <select v-model="seleccion.empresaTamano" :disabled="esModoDemo" class="input-style" :class="!seleccion.empresaTamano && (seleccion.empresaId || seleccion.empresaNombre) ? 'border-red-500 bg-red-900/30 text-red-400 focus:border-red-500 focus:bg-red-900/40' : ''">
                    <option value="" disabled selected>¡FALTA INFO! Selecciona...</option>
                    <option value="Micropyme (1-10)">Micropyme (1 a 10 empleados)</option>
                    <option value="Pequeña (10-50)">Pequeña (10 a 50 empleados)</option>
                    <option value="Mediana (50-250)">Mediana (50 a 250 empleados)</option>
                    <option value="Grande (+250)">Grande (Más de 250 empleados)</option>
                  </select>
                </div>

                <div class="col-span-2">
                  <label class="label-style" :class="seleccion.empresaWeb && !empresaWebEsValida ? 'text-red-500' : ''">
                    Web (Opcional)
                  </label>
                  <input
                    v-model="seleccion.empresaWeb"
                    :disabled="esModoDemo"
                    class="input-style"
                    :class="seleccion.empresaWeb && !empresaWebEsValida ? 'border-red-500 focus:border-red-500' : ''"
                    placeholder="https://..."
                  />
                  <p v-if="seleccion.empresaWeb && !empresaWebEsValida" class="text-red-500 text-[10px] font-bold mt-1 pl-1">
                    La URL debe empezar por https:// o http://
                  </p>
                </div>
              </div>
            </section>
          </div>
        </transition>

        <transition name="fade" mode="out-in">
          <div v-if="pasoActual === 2" class="space-y-8 animate-in slide-in-from-bottom-4 duration-500">
             <section class="bg-white rounded-[2.5rem] p-8 md:p-10 border border-gray-100 shadow-[0_20px_50px_rgb(0,0,0,0.05)] relative z-10">
              <transition name="fade">
                <div v-if="seleccion.empresaNombre" class="absolute -top-4 left-8">
                  <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white text-[#1F2937] text-xs font-bold tracking-wide border border-gray-100 border-b-0 shadow-[0_-4px_8px_rgb(0,0,0,0.04)]">
                    <svg class="w-3.5 h-3.5 shrink-0 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Empresa: {{ seleccion.empresaNombre }}
                  </span>
                </div>
              </transition>
              <div class="absolute top-0 right-0 bg-gray-50 text-[#1F2937] px-6 py-2 text-[10px] font-black uppercase tracking-widest rounded-bl-3xl border-b border-l border-gray-100">
                Entrevista de Diagnóstico
              </div>
              
              <div ref="refEncabezadoProblema" class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 mb-8 mt-4 xl:mt-0"
                   :class="{ 'tour-active': tourTargetActivo === 'refEncabezadoProblema' }">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 rounded-2xl bg-[#99CC33]/15 flex items-center justify-center text-[#00A859]">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                  </div>
                  <h2 class="text-2xl font-black uppercase tracking-tight text-[#1F2937]">Realidad de la Empresa</h2>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <button @click="toggleInfoSimulada()"
                    :disabled="esModoDemo || cargandoSimulacion"
                    :class="esModoDemo || cargandoSimulacion ? 'opacity-40 cursor-not-allowed bg-white text-gray-400 border-gray-200' : esInfoSimulada ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-md' : 'bg-white text-gray-500 hover:bg-gray-50 border-gray-200 shadow-sm'"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 border">
                    <svg v-if="cargandoSimulacion" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <svg v-else-if="esInfoSimulada" class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span v-if="cargandoSimulacion">Generando...</span>
                    <span v-else>Información Simulada</span>
                  </button>

                  <span v-if="esModoDemo"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase flex items-center gap-2 border bg-[#00A859]/10 text-[#00A859] border-[#00A859]/20 cursor-default">
                    ✓ DEMO ACTIVA
                  </span>

                  <!--
                  <button @click="cargarDemo" :disabled="esModoDemo" 
                    :class="esModoDemo ? 'bg-[#00A859]/10 text-[#00A859] border-[#00A859]/20 cursor-default' : 'bg-white text-[#00A859] hover:bg-gray-50 border-gray-200 hover:border-[#00A859] shadow-sm'"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 border">
                    <span v-if="esModoDemo">✓ DEMO ACTIVA</span>
                    <span v-else> Cargar Demo</span>
                  </button>
                  -->
                  <button @click="limpiarFormulario" class="px-5 py-2.5 bg-white text-red-500 hover:bg-red-50 hover:border-red-500 border border-gray-200 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Vaciar
                  </button>
                </div>
              </div>

              <div v-if="esModoDemo" 
                class="mb-6 flex items-center gap-3 bg-[#00A859]/5 border border-[#00A859]/20 rounded-2xl px-5 py-3">
                <svg class="w-4 h-4 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs font-bold text-[#00A859] uppercase tracking-widest">
                  Modo Demo activo — los campos están bloqueados. Pulsa "Vaciar" para editarlos.
                </p>
              </div>

              <div v-if="diagnosticoRecuperado" class="mb-10 p-5 md:p-6 bg-[#00A859]/5 border border-[#00A859]/20 rounded-3xl flex gap-4 md:gap-5 items-start">
                <div class="bg-gradient-to-r from-[#00A859] to-[#99CC33] text-white p-2.5 rounded-2xl shrink-0 mt-1 shadow-md">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                  <h4 class="font-black text-[#00A859] uppercase tracking-widest text-xs mb-1">Información Previa Detectada</h4>
                  <p class="text-sm text-gray-600 leading-relaxed font-medium">
                    Hemos recuperado las respuestas de una sesión anterior. Puedes mantenerlas para generar nuevas variantes del reto o editarlas si la situación ha cambiado.
                  </p>
                </div>
              </div>

              <div class="space-y-10">
                <div>
                  <div class="flex items-center gap-2 mb-3">
                    <label class="label-style !mb-0 flex items-center gap-2">
                      <span class="bg-[#1F2937] text-white w-5 h-5 flex items-center justify-center rounded-full text-[10px]">1</span>
                      ¿Qué ofrece su empresa y qué hace en su día a día?
                    </label>
                    <button @click="abrirPopup('info', 1)" class="text-gray-400 hover:text-[#00A859] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                    <button @click="abrirPopup('ejemplo', 1)" class="text-gray-400 hover:text-[#99CC33] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg></button>
                  </div>
                  <textarea v-model="seleccion.diaANormal" :disabled="esModoDemo" :maxlength="CHAR_LIMITS.diaANormal.max" class="input-style h-24" placeholder="Ej: Somos una empresa de servicios informáticos..."></textarea>
                  <div class="flex items-center justify-end gap-2 mt-1 h-4">
                    <span v-if="charInfo('diaANormal').isWarning && !charInfo('diaANormal').isOver" class="text-amber-400 text-[10px]">Cerca del límite — sé conciso</span>
                    <span class="text-[10px] transition-colors" :class="charInfo('diaANormal').isOver ? 'text-red-400 font-bold' : charInfo('diaANormal').isWarning ? 'text-amber-400' : 'text-gray-600'">{{ charInfo('diaANormal').len }}/{{ CHAR_LIMITS.diaANormal.max }}</span>
                  </div>
                </div>

                <div ref="refPreguntaFriccion" class="grid grid-cols-1 md:grid-cols-2 gap-8"
                     :class="{ 'tour-active': tourTargetActivo === 'refPreguntaFriccion' }">
                  <div>
                    <div class="flex items-center gap-2 mb-3">
                      <label class="label-style !mb-0 flex items-center gap-2">
                        <span class="bg-[#1F2937] text-white w-5 h-5 flex items-center justify-center rounded-full text-[10px]">2</span>
                        ¿Qué tarea da más trabajo del que debería?
                      </label>
                      <button @click="abrirPopup('info', 2)" class="text-gray-400 hover:text-[#00A859] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                      <button @click="abrirPopup('ejemplo', 2)" class="text-gray-400 hover:text-[#99CC33] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg></button>
                    </div>
                    <textarea v-model="seleccion.friccionArea" :disabled="esModoDemo" :maxlength="CHAR_LIMITS.friccionArea.max" class="input-style h-16" placeholder="Ej: Registro manual de albaranes..."></textarea>
                    <div class="flex items-center justify-end gap-2 mt-1 h-4">
                      <span v-if="charInfo('friccionArea').isWarning && !charInfo('friccionArea').isOver" class="text-amber-400 text-[10px]">Cerca del límite — sé conciso</span>
                      <span class="text-[10px] transition-colors" :class="charInfo('friccionArea').isOver ? 'text-red-400 font-bold' : charInfo('friccionArea').isWarning ? 'text-amber-400' : 'text-gray-600'">{{ charInfo('friccionArea').len }}/{{ CHAR_LIMITS.friccionArea.max }}</span>
                    </div>
                  </div>
                  <div>
                    <label class="label-style flex items-center gap-2 mb-3">
                      <span class="bg-[#1F2937] text-white w-5 h-5 flex items-center justify-center rounded-full text-[10px]">2b</span>
                      ¿Por qué? Cuéntanos qué ocurre hoy
                    </label>
                    <textarea v-model="seleccion.friccionProblema" :disabled="esModoDemo" :maxlength="CHAR_LIMITS.friccionProblema.max" class="input-style h-24" placeholder="Se pierde mucho tiempo porque... hay errores cuando..."></textarea>
                    <div class="flex items-center justify-end gap-2 mt-1 h-4">
                      <span v-if="charInfo('friccionProblema').isWarning && !charInfo('friccionProblema').isOver" class="text-amber-400 text-[10px]">Cerca del límite — sé conciso</span>
                      <span class="text-[10px] transition-colors" :class="charInfo('friccionProblema').isOver ? 'text-red-400 font-bold' : charInfo('friccionProblema').isWarning ? 'text-amber-400' : 'text-gray-600'">{{ charInfo('friccionProblema').len }}/{{ CHAR_LIMITS.friccionProblema.max }}</span>
                    </div>
                  </div>
                </div>

                <div>
                  <div class="flex items-center gap-2 mb-4">
                    <label class="label-style !mb-0 flex items-center gap-2">
                      <span class="bg-[#1F2937] text-white w-5 h-5 flex items-center justify-center rounded-full text-[10px]">3</span>
                      ¿Han probado solucionarlo? ¿Qué limitaciones tienen?
                    </label>
                    <button @click="abrirPopup('info', 3)" class="text-gray-400 hover:text-[#00A859] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                    <button @click="abrirPopup('ejemplo', 3)" class="text-gray-400 hover:text-[#99CC33] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg></button>
                  </div>
                  
                  <div class="flex flex-wrap gap-2 mb-4">
                    <button v-for="opt in limitacionesOpciones" :key="opt" 
                      @click="!esModoDemo && (seleccion.restricciones.includes(opt) ? seleccion.restricciones = seleccion.restricciones.filter(c => c !== opt) : seleccion.restricciones.push(opt))"
                      :disabled="esModoDemo"
                      :class="seleccion.restricciones.includes(opt) ? 'bg-gradient-to-r from-[#00A859] to-[#99CC33] text-white border-transparent shadow-md' : 'bg-[#1F2937] text-gray-300 border-transparent hover:border-[#00A859]/50'"
                      class="px-5 py-2.5 rounded-2xl border-2 text-[10px] font-black uppercase transition-all shadow-sm">
                      {{ opt }}
                    </button>
                  </div>
                  <textarea v-model="seleccion.otraLimitacion" :disabled="esModoDemo" :maxlength="CHAR_LIMITS.otraLimitacion.max" class="input-style h-20" placeholder="Describe aquí otros intentos de solución o detalles de las limitaciones..."></textarea>
                  <div class="flex items-center justify-end gap-2 mt-1 h-4">
                    <span v-if="charInfo('otraLimitacion').isWarning && !charInfo('otraLimitacion').isOver" class="text-amber-400 text-[10px]">Cerca del límite — sé conciso</span>
                    <span class="text-[10px] transition-colors" :class="charInfo('otraLimitacion').isOver ? 'text-red-400 font-bold' : charInfo('otraLimitacion').isWarning ? 'text-amber-400' : 'text-gray-600'">{{ charInfo('otraLimitacion').len }}/{{ CHAR_LIMITS.otraLimitacion.max }}</span>
                  </div>
                </div>

                <div>
                  <label class="label-style flex items-center gap-2 mb-3">
                    <span class="bg-[#1F2937] text-white w-5 h-5 flex items-center justify-center rounded-full text-[10px]">3b</span>
                    ¿Qué NO quieren bajo ningún concepto?
                  </label>
                  <textarea v-model="seleccion.loQueNoQuieren" :disabled="esModoDemo" :maxlength="CHAR_LIMITS.loQueNoQuieren.max" class="input-style h-16" placeholder="Ej: Nada que requiera suscripción mensual..."></textarea>
                  <div class="flex items-center justify-end gap-2 mt-1 h-4">
                    <span v-if="charInfo('loQueNoQuieren').isWarning && !charInfo('loQueNoQuieren').isOver" class="text-amber-400 text-[10px]">Cerca del límite — sé conciso</span>
                    <span class="text-[10px] transition-colors" :class="charInfo('loQueNoQuieren').isOver ? 'text-red-400 font-bold' : charInfo('loQueNoQuieren').isWarning ? 'text-amber-400' : 'text-gray-600'">{{ charInfo('loQueNoQuieren').len }}/{{ CHAR_LIMITS.loQueNoQuieren.max }}</span>
                  </div>
                </div>

                <div>
                  <div class="flex items-center gap-2 mb-4">
                    <label class="label-style !mb-0 flex items-center gap-2">
                      <span class="bg-[#1F2937] text-white w-5 h-5 flex items-center justify-center rounded-full text-[10px]">4</span>
                      Si pudieran mejorar algo YA mismo...
                    </label>
                    <button @click="abrirPopup('info', 4)" class="text-gray-400 hover:text-[#00A859] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                    <button @click="abrirPopup('ejemplo', 4)" class="text-gray-400 hover:text-[#99CC33] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg></button>
                  </div>
                  
                  <div class="flex flex-wrap gap-2 mb-4">
                    <button v-for="opt in consecuenciasOpciones" :key="opt" 
                      @click="!esModoDemo && (seleccion.consecuencias.includes(opt) ? seleccion.consecuencias = seleccion.consecuencias.filter(c => c !== opt) : seleccion.consecuencias.push(opt))"
                      :disabled="esModoDemo"
                      :class="seleccion.consecuencias.includes(opt) ? 'bg-gradient-to-r from-[#00A859] to-[#99CC33] text-white border-transparent shadow-md' : 'bg-[#1F2937] text-gray-300 border-transparent hover:border-[#99CC33]/50'"
                      class="px-5 py-2.5 rounded-2xl border-2 text-[10px] font-black uppercase transition-all shadow-sm">
                      {{ opt }}
                    </button>
                  </div>
                  <input v-model="seleccion.otraConsecuencia" :disabled="esModoDemo" :maxlength="CHAR_LIMITS.otraConsecuencia.max" class="input-style" placeholder="Otra mejora específica (Opcional)..." />
                  <div class="flex items-center justify-end gap-2 mt-1 h-4">
                    <span v-if="charInfo('otraConsecuencia').isWarning && !charInfo('otraConsecuencia').isOver" class="text-amber-400 text-[10px]">Cerca del límite</span>
                    <span class="text-[10px] transition-colors" :class="charInfo('otraConsecuencia').isOver ? 'text-red-400 font-bold' : charInfo('otraConsecuencia').isWarning ? 'text-amber-400' : 'text-gray-600'">{{ charInfo('otraConsecuencia').len }}/{{ CHAR_LIMITS.otraConsecuencia.max }}</span>
                  </div>
                </div>

                <div ref="refExpectativas" class="bg-gray-50 p-6 rounded-3xl border"
                     :class="[!seleccion.expectativasAlumno && !esModoDemo ? 'border-red-200' : 'border-gray-100', tourTargetActivo === 'refExpectativas' ? 'tour-active' : '']">
                  <div class="flex items-center gap-2 mb-3">
                    <label class="label-style !mb-0 flex items-center gap-2" :class="!seleccion.expectativasAlumno && !esModoDemo ? 'text-red-500' : ''">
                      <span class="bg-[#1F2937] text-white w-5 h-5 flex items-center justify-center rounded-full text-[10px]">5</span>
                      Si tuvieras a un alumno aquí, ¿qué esperas que realice? *
                    </label>
                    <button @click="abrirPopup('info', 5)" class="text-gray-400 hover:text-[#00A859] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                    <button @click="abrirPopup('ejemplo', 5)" class="text-gray-400 hover:text-[#99CC33] transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg></button>
                  </div>
                  <textarea v-model="seleccion.expectativasAlumno" :disabled="esModoDemo" :maxlength="CHAR_LIMITS.expectativasAlumno.max" class="input-style h-24" placeholder="Ej: Que investigue herramientas gratuitas y proponga un prototipo sencillo..."></textarea>
                  <div class="flex items-center justify-end gap-2 mt-1 h-4">
                    <span v-if="charInfo('expectativasAlumno').isWarning && !charInfo('expectativasAlumno').isOver" class="text-amber-400 text-[10px]">Cerca del límite — sé conciso</span>
                    <span class="text-[10px] transition-colors" :class="charInfo('expectativasAlumno').isOver ? 'text-red-400 font-bold' : charInfo('expectativasAlumno').isWarning ? 'text-amber-400' : 'text-gray-600'">{{ charInfo('expectativasAlumno').len }}/{{ CHAR_LIMITS.expectativasAlumno.max }}</span>
                  </div>
                </div>

              </div>
            </section>
          </div>
        </transition>

        <transition name="fade" mode="out-in">
          <div v-if="pasoActual === 3" class="space-y-8 animate-in slide-in-from-bottom-4 duration-500">
            <section class="bg-white rounded-[2.5rem] p-8 md:p-10 border border-gray-100 shadow-[0_20px_50px_rgb(0,0,0,0.05)] relative z-10">
              <transition name="fade">
                <div v-if="seleccion.empresaNombre" class="absolute -top-4 left-8">
                  <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white text-[#1F2937] text-xs font-bold tracking-wide border border-gray-100 border-b-0 shadow-[0_-4px_8px_rgb(0,0,0,0.04)]">
                    <svg class="w-3.5 h-3.5 shrink-0 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Empresa: {{ seleccion.empresaNombre }}
                  </span>
                </div>
              </transition>
              <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 mb-8 mt-4 xl:mt-0">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-12 rounded-2xl bg-[#00A859]/10 flex items-center justify-center text-[#00A859]">
                     <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                  </div>
                  <h2 class="text-2xl font-black uppercase tracking-tight text-[#1F2937]">Match Académico</h2>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <button @click="toggleInfoSimulada()"
                    :disabled="esModoDemo || cargandoSimulacion"
                    :class="esModoDemo || cargandoSimulacion ? 'opacity-40 cursor-not-allowed bg-white text-gray-400 border-gray-200' : esInfoSimulada ? 'bg-[#1F2937] text-white border-[#1F2937] shadow-md' : 'bg-white text-gray-500 hover:bg-gray-50 border-gray-200 shadow-sm'"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 border">
                    <svg v-if="cargandoSimulacion" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <svg v-else-if="esInfoSimulada" class="w-4 h-4 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span v-if="cargandoSimulacion">Generando...</span>
                    <span v-else>Información Simulada</span>
                  </button>

                  <span v-if="esModoDemo"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase flex items-center gap-2 border bg-[#00A859]/10 text-[#00A859] border-[#00A859]/20 cursor-default">
                    ✓ DEMO ACTIVA
                  </span>

                  <!--
                  <button @click="cargarDemo" :disabled="esModoDemo" 
                    :class="esModoDemo ? 'bg-[#00A859]/10 text-[#00A859] border-[#00A859]/20 cursor-default' : 'bg-white text-[#00A859] hover:bg-gray-50 border-gray-200 hover:border-[#00A859] shadow-sm'"
                    class="px-5 py-2.5 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 border">
                    <span v-if="esModoDemo">✓ DEMO ACTIVA</span>
                    <span v-else> Cargar Demo</span>
                  </button>
                  -->
                  <button @click="limpiarFormulario" class="px-5 py-2.5 bg-white text-red-500 hover:bg-red-50 hover:border-red-500 border border-gray-200 rounded-full font-bold text-xs tracking-widest uppercase transition-all flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Vaciar
                  </button>
                </div>
              </div>
              
              <div v-if="esModoDemo" 
                class="mb-6 flex items-center gap-3 bg-[#00A859]/5 border border-[#00A859]/20 rounded-2xl px-5 py-3">
                <svg class="w-4 h-4 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs font-bold text-[#00A859] uppercase tracking-widest">
                  Modo Demo activo — los campos están bloqueados. Pulsa "Vaciar" para editarlos.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div ref="refFamiliaSelect" class="col-span-2 md:col-span-1"
                     :class="{ 'tour-active': tourTargetActivo === 'refFamiliaSelect' }">
                  <label class="label-style">Familia Profesional Asociada *</label>
                  <select v-model="seleccion.familia" :disabled="esModoDemo" class="input-style">
                    <option value="">Selecciona Familia...</option>
                    <option v-for="f in familiasFiltradas" :key="f" :value="f">{{ f }}</option>
                  </select>
                </div>

                <div ref="refNivelExigencia" class="col-span-2 md:col-span-1"
                     :class="{ 'tour-active': tourTargetActivo === 'refNivelExigencia' }">
                  <label class="label-style">Nivel de exigencia del reto *</label>
                  <select v-model="seleccion.nivelGrupo" :disabled="esModoDemo" class="input-style">
                    <option value="Bajo">Básico — El grupo tiene un nivel de comprensión inicial; el reto debe ser sencillo y guiado</option>
                    <option value="Medio">Medio — El grupo maneja conceptos con soltura; el reto puede tener cierta complejidad y autonomía</option>
                    <option value="Alto">Alto — El grupo domina la materia; el reto es exigente, abierto y requiere criterio propio</option>
                  </select>
                </div>

                <div ref="refCicloGrid" class="col-span-2 mt-4"
                     :class="{ 'tour-active': tourTargetActivo === 'refCicloGrid' }">
                  <label class="label-style !mb-4">Ciclo Formativo Implicado *</label>

                  <!-- Ciclos de BD -->
                  <div v-if="ciclos.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                     <button v-for="c in ciclos" :key="c.id"
                        @click="!esModoDemo && (seleccion.cicloId = c.id)"
                        :disabled="esModoDemo"
                        :class="seleccion.cicloId === c.id
                            ? 'bg-gradient-to-r from-[#00A859] to-[#99CC33] border-transparent text-white'
                            : 'bg-[#1F2937] border-transparent text-gray-300 hover:border-[#00A859]/40'"
                        class="text-left px-5 py-4 rounded-2xl border-2 transition-all duration-200 flex items-center gap-3 relative overflow-hidden group shadow-sm">
                        <div class="shrink-0 transition-transform" :class="seleccion.cicloId === c.id ? 'scale-100' : 'scale-0 opacity-0 hidden'">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="flex-1">
                            <span class="text-sm font-bold leading-tight">{{ c.nombre }}</span>
                        </div>
                     </button>
                  </div>
                  <!-- Tarjeta virtual cuando la familia no está en BD pero hay demo activa -->
                  <div v-else-if="esModoDemo && demoCicloNombre" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div class="text-left px-5 py-4 rounded-2xl border-2 border-transparent bg-gradient-to-r from-[#00A859] to-[#99CC33] text-white flex items-center gap-3 shadow-sm">
                      <div class="shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                      </div>
                      <div class="flex-1">
                        <span class="text-sm font-bold leading-tight">{{ demoCicloNombre }}</span>
                        <p class="text-[10px] text-white/70 mt-0.5 uppercase tracking-wide">Ciclo de demo</p>
                      </div>
                    </div>
                  </div>
                  <!-- Estado vacío estándar -->
                  <div v-else class="bg-gray-50 border border-dashed border-gray-200 rounded-3xl p-6 text-center">
                      <p class="text-gray-500 text-sm">Selecciona una Familia Profesional para ver sus ciclos.</p>
                  </div>
                </div>

                <div ref="refCursoAlumno" class="col-span-2 mt-6"
                     :class="{ 'tour-active': tourTargetActivo === 'refCursoAlumno' }">
                  <label class="label-style !mb-3">Curso del Alumnado *</label>
                  <div class="flex bg-[#1F2937] p-1.5 rounded-2xl w-full md:w-1/2 shadow-inner">
                    <button @click="!esModoDemo && (seleccion.cursoSeleccionado = 1)" 
                      :disabled="esModoDemo"
                      :class="seleccion.cursoSeleccionado === 1 ? 'bg-[#374151] text-white shadow font-black' : 'text-gray-400 hover:text-white'" 
                      class="flex-1 py-3 rounded-xl text-sm transition-all">
                      1º Curso
                    </button>
                    <button @click="!esModoDemo && (seleccion.cursoSeleccionado = 2)" 
                      :disabled="esModoDemo"
                      :class="seleccion.cursoSeleccionado === 2 ? 'bg-[#374151] text-white shadow font-black' : 'text-gray-400 hover:text-white'" 
                      class="flex-1 py-3 rounded-xl text-sm transition-all">
                      2º Curso
                    </button>
                  </div>
                </div>

                <div ref="refModulosSection" class="col-span-2 mt-2 pt-6 border-t border-gray-100"
                     :class="{ 'tour-active': tourTargetActivo === 'refModulosSection' }">
                  <div class="w-full bg-gray-50 p-6 rounded-3xl border border-gray-200 relative">
                    <div class="flex items-center justify-between mb-3">
                      <label class="label-style !mb-0 !ml-0">Forzar Módulo Específico (Opcional)</label>
                      <span class="text-xs text-gray-400 italic">Si no eliges, la IA cruzará con todos.</span>
                    </div>
                    
                    <!-- Módulo virtual cuando la demo no tiene ciclo en BD -->
                    <div v-if="esModoDemo && modulosDelCurso.length === 0 && demoModuloNombre"
                      class="input-style min-h-[60px] flex items-center gap-2 bg-[#00A859]/5 border-[#00A859]/20 text-[#1F2937] cursor-default">
                      <svg class="w-4 h-4 text-[#00A859] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                      <span class="text-sm font-semibold">{{ demoModuloNombre }}</span>
                      <span class="ml-auto text-[10px] text-gray-400 uppercase tracking-wide">Módulo de demo</span>
                    </div>
                    <select v-else v-model="modulosSeleccionados" multiple :disabled="esModoDemo || modulosDelCurso.length === 0" class="input-style min-h-[120px]">
                      <option v-for="m in modulosDelCurso" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                    </select>
                    <p v-if="!esModoDemo && modulosDelCurso.length === 0 && seleccion.cicloId" class="text-xs text-red-500 mt-2 italic">No hay módulos cargados para este curso.</p>
                  </div>
                </div>

                <div ref="refCantidadVariantes" class="col-span-2 mt-2 pt-6 border-t border-gray-100"
                     :class="{ 'tour-active': tourTargetActivo === 'refCantidadVariantes' }">
                  <div class="w-full md:w-1/3">
                    <label class="label-style !mb-3 text-[#1F2937]">Cantidad de Variantes a Generar *</label>
                    <select v-model="seleccion.cantidadMicroretos" :disabled="esModoDemo" class="input-style focus:!border-[#00A859]">
                      <option v-for="n in [1, 2, 3, 4, 5]" :key="n" :value="n">
                        Generar {{ n }} Variante{{ n > 1 ? 's' : '' }}
                      </option>
                    </select>
                    <p class="text-xs text-gray-500 mt-2 italic pl-4">La IA diseñará enfoques distintos para el mismo problema.</p>
                  </div>
                </div>

              </div>
            </section>
          </div>
        </transition>

      </main>

      <div class="mt-16 flex flex-col items-center gap-6">
        
        <!-- Aviso de guardar empresa — solo admin (docentes no pueden editar empresas) -->
        <div v-if="!esDocente && pasoActual === totalPasos && !crmActualizado" class="bg-yellow-50 border border-yellow-200 shadow-sm rounded-2xl p-5 flex items-start md:items-center gap-4 w-full max-w-4xl animate-in slide-in-from-bottom-2">
          <div class="bg-yellow-400 text-white p-2 rounded-full shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          </div>
          <div class="flex-1">
            <h4 class="text-yellow-800 font-black text-sm uppercase tracking-widest mb-1">¡No pierdas tu trabajo!</h4>
            <p class="text-yellow-900 text-sm font-medium">Si has modificado el Sector, el Tamaño o el contexto del Problema, haz clic en <strong class="font-black">Actualizar Empresa</strong> antes de generar el reto para guardarlo en la base de datos.</p>
          </div>
        </div>

        <div class="flex flex-wrap md:flex-nowrap gap-4 w-full max-w-4xl">
          <button v-if="pasoActual > 1" @click="retrocederPaso" class="flex-1 min-w-[150px] px-10 py-6 bg-white text-[#1F2937] border-2 border-gray-200 rounded-full font-black text-xs tracking-widest transition-all hover:bg-gray-50 hover:border-gray-300 active:scale-95">
            VOLVER
          </button>
          
          <button v-if="pasoActual < totalPasos" @click="avanzarPaso" 
            :disabled="(pasoActual === 1 && !paso1Valido) || (pasoActual === 2 && !paso2Valido)"
            class="flex-[2] min-w-[200px] px-10 py-6 bg-gradient-to-r from-[#00A859] to-[#99CC33] text-white rounded-full font-black text-xs tracking-widest shadow-md hover:shadow-lg disabled:opacity-30 transition-all hover:scale-105 active:scale-95">
            SIGUIENTE PASO
          </button>

          <!-- Guardar/Actualizar empresa — solo admin -->
          <button ref="refGuardarEmpresa" v-if="pasoActual === totalPasos && !esDocente" @click="guardarInfoEmpresa" :disabled="actualizandoCRM || crmActualizado"
            class="flex-1 min-w-[200px] px-6 py-6 border-2 text-[#1F2937] bg-white border-gray-200 hover:border-[#00A859] hover:text-[#00A859] rounded-full font-black text-xs tracking-widest transition-all active:scale-95 flex items-center justify-center gap-2"
            :class="[crmActualizado ? '!border-[#00A859] !bg-[#00A859]/5 !text-[#00A859]' : '', tourTargetActivo === 'refGuardarEmpresa' ? 'tour-active' : '']">
            <template v-if="actualizandoCRM">
              <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
              GUARDANDO...
            </template>
            <template v-else-if="crmActualizado">
              <span class="text-[#00A859] flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                INFO GUARDADA
              </span>
            </template>
            <template v-else>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
              {{ seleccion.empresaId ? 'ACTUALIZAR EMPRESA' : 'GUARDAR EMPRESA' }}
            </template>
          </button>

          <button ref="refBtnGenerar" v-if="pasoActual === totalPasos" @click="esModoDemo ? generarRetoDemo() : generarReto()" :disabled="!paso3Valido || cargando"
            :class="[esModoDemo ? 'from-[#1F2937] to-[#374151] shadow-[0_10px_30px_rgba(31,41,55,0.3)] hover:shadow-[0_15px_40px_rgba(31,41,55,0.4)]' : 'from-[#00A859] to-[#99CC33] shadow-[0_10px_30px_rgba(0,168,89,0.3)] hover:shadow-[0_15px_40px_rgba(153,204,51,0.4)]', tourTargetActivo === 'refBtnGenerar' ? 'tour-active' : '']"
            class="flex-[2] min-w-[250px] px-8 py-6 bg-gradient-to-r text-white rounded-full font-black text-lg transition-all hover:-translate-y-1 active:scale-95 disabled:opacity-40 flex items-center justify-center gap-3">
            <template v-if="!cargando">
              <svg v-if="esModoDemo" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
              <span class="uppercase tracking-wide">
                <template v-if="esModoDemo">{{ microretosGenerados.length > 0 ? 'Regenerar demo' : 'Procesar DEMO con IA' }}</template>
                <template v-else>{{ microretosGenerados.length > 0 ? 'Generar más variantes' : 'Procesar con IA' }}</template>
              </span>
            </template>
            <template v-else>
              <div class="w-5 h-5 border-3 border-white/30 border-t-white rounded-full animate-spin"></div>
              <span class="text-sm">{{ esModoDemo ? 'CARGANDO DEMO...' : `GENERANDO ${seleccion.cantidadMicroretos} RETO(S)...` }}</span>
            </template>
          </button>
        </div>
      </div>

      <div v-if="microretosGenerados.length > 0" class="mt-24 pb-20 space-y-32 font-sans relative">
        
        <div v-if="microretosGenerados.length > 1" class="flex justify-center md:justify-end mb-8 sticky top-16 z-50">
            <button @click="guardarTodos" :disabled="todosGuardados || guardandoTodos" class="px-8 py-4 bg-white border-2 border-gray-200 text-[#1F2937] hover:border-[#00A859] hover:text-[#00A859] rounded-full font-black text-xs md:text-sm uppercase tracking-widest shadow-lg transition-all hover:-translate-y-1 active:scale-95 disabled:hover:translate-y-0 disabled:opacity-80 flex items-center gap-2">
                <template v-if="guardandoTodos">
                  <svg class="animate-spin w-5 h-5" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg> 
                  GUARDANDO TODOS...
                </template>
                <template v-else-if="todosGuardados">
                  <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                  ✓ TODOS GUARDADOS
                </template>
                <template v-else>
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                  GUARDAR LOS {{ microretosGenerados.length }} RETOS
                </template>
            </button>
        </div>

        <div v-for="(reto, index) in microretosGenerados" :key="index" class="relative animate-in slide-in-from-bottom-8 duration-700">
          
          <div v-if="microretosGenerados.length > 1" class="absolute -top-16 left-0 w-full flex items-center justify-center z-10">
            <div class="w-full h-[1px] bg-gradient-to-r from-transparent via-gray-300 to-transparent"></div>
            <span class="absolute px-6 py-2 bg-white border border-gray-200 text-gray-500 rounded-full text-xs font-black uppercase tracking-widest shadow-sm">
              Variante #{{ microretosGenerados.length - index }}
            </span>
          </div>

          <div class="flex flex-col md:flex-row justify-between items-start md:items-center bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-8 gap-4">
            <div>
              <h3 class="text-lg font-black text-[#1F2937]">Previsualización de la Ficha Técnica</h3>
              <p class="text-sm text-gray-500">Formato oficial de archivo.</p>
            </div>
            <button @click="guardar(index)" :disabled="reto._ui_guardado || reto._ui_guardando" class="btn-save w-full md:w-auto" :class="reto._ui_guardado ? 'border-[#00A859] bg-[#00A859]/5 text-[#00A859]' : ''">
              <span v-if="reto._ui_guardando" class="flex items-center gap-2"><svg class="animate-spin w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg> GUARDANDO...</span>
              <span v-else-if="reto._ui_guardado">✓ PUBLICADO EN BD</span>
              <span v-else>GUARDAR ESTA VERSIÓN</span>
            </button>
          </div>
          
          <div class="bg-white rounded-[2rem] shadow-[0_20px_50px_rgb(0,0,0,0.06)] mx-auto max-w-5xl overflow-hidden border border-gray-100 relative z-20">
            <div class="bg-gray-50 border-b border-gray-100 p-10 md:px-16 pt-12">
              <p class="text-[#00A859] font-bold text-[10px] tracking-[0.2em] uppercase mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                DuaLab · Ficha de Reto
              </p>
              <h1 class="text-3xl md:text-5xl font-black text-[#1F2937] tracking-tight leading-tight mb-2">
                {{ reto.titulo }}
              </h1>
              <h2 class="text-lg md:text-xl text-gray-500 font-medium leading-relaxed mb-8">
                {{ reto.subtitulo }}
              </h2>
              
              <div class="flex flex-wrap gap-3">
                <span class="flex items-center gap-2 px-4 py-2 bg-[#1F2937] text-white rounded-lg text-xs font-bold uppercase tracking-wider shadow-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                  {{ reto.empresa_nombre }}
                </span>
                <span class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-[#1F2937] rounded-lg text-xs font-bold uppercase tracking-wider">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2-2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                  {{ reto._ui_familia || seleccion.familia }}
                </span>
                <span class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-500 rounded-lg text-xs font-bold uppercase tracking-wider">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                  Nivel: {{ reto.nivel_grupo || seleccion.nivelGrupo }} ({{ reto.curso || seleccion.cursoSeleccionado }}º)
                </span>
              </div>
            </div>

            <div class="p-10 md:p-16 space-y-12">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div>
                  <h3 class="flex items-center gap-2 text-[#00A859] font-bold uppercase text-xs tracking-widest border-b border-gray-100 pb-2 mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                    ¿Quién es {{ reto.empresa_nombre }}?
                  </h3>
                  <p class="text-gray-600 text-sm leading-relaxed">{{ reto.quien_es }}</p>
                </div>
                <div>
                  <h3 class="flex items-center gap-2 text-[#00A859] font-bold uppercase text-xs tracking-widest border-b border-gray-100 pb-2 mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Su día a día
                  </h3>
                  <p class="text-gray-600 text-sm leading-relaxed">{{ reto.dia_a_dia }}</p>
                </div>
              </div>

              <div>
                <h3 class="flex items-center gap-2 text-yellow-600 font-bold uppercase text-xs tracking-widest border-b border-gray-100 pb-2 mb-4">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                  Dificultades
                </h3>
                <ul class="space-y-2 pl-2">
                  <li v-for="(item, i) in reto.dificultades" :key="i" class="flex items-start gap-3 text-sm text-gray-700">
                    <span class="text-yellow-500 font-black mt-0.5">•</span><span>{{ item }}</span>
                  </li>
                </ul>
              </div>

              <div class="bg-gradient-to-r from-gray-50 to-white border-l-4 border-[#00A859] p-8 rounded-r-2xl shadow-sm border-y border-r border-gray-100">
                <h3 class="text-[#00A859] font-black uppercase text-[10px] tracking-[0.2em] mb-2 flex items-center gap-2">Pregunta del Reto</h3>
                <p class="text-xl md:text-2xl font-bold text-[#1F2937] leading-snug">{{ reto.pregunta_reto }}</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div>
                  <h3 class="flex items-center gap-2 text-[#00A859] font-bold uppercase text-xs tracking-widest border-b border-gray-100 pb-2 mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Qué necesitan
                  </h3>
                  <ul class="space-y-2 pl-2">
                    <li v-for="(item, i) in reto.que_necesitan" :key="i" class="flex items-start gap-3 text-sm text-gray-700">
                      <span class="text-[#00A859] font-black mt-0.5">•</span><span>{{ item }}</span>
                    </li>
                  </ul>
                </div>
                <div>
                  <h3 class="flex items-center gap-2 text-red-500 font-bold uppercase text-xs tracking-widest border-b border-gray-100 pb-2 mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    Limitaciones
                  </h3>
                  <ul class="space-y-2 pl-2">
                    <li v-for="(item, i) in reto.limitaciones" :key="i" class="flex items-start gap-3 text-sm text-gray-700">
                      <span class="text-red-500 font-black mt-0.5">•</span><span>{{ item }}</span>
                    </li>
                  </ul>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <div>
                  <h3 class="flex items-center gap-2 text-[#00A859] font-bold uppercase text-xs tracking-widest border-b border-gray-100 pb-2 mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Ejemplos de Prototipos
                  </h3>
                  <ul class="space-y-2 pl-2">
                    <li v-for="(item, i) in reto.prototipos" :key="i" class="flex items-start gap-3 text-sm text-gray-700">
                      <span class="text-[#00A859] font-black mt-0.5">•</span><span>{{ item }}</span>
                    </li>
                  </ul>
                </div>
                <div>
                  <h3 class="flex items-center gap-2 text-blue-600 font-bold uppercase text-xs tracking-widest border-b border-gray-100 pb-2 mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    ODS Sugeridos
                  </h3>
                  <ul class="space-y-2 pl-2">
                    <li v-for="ods in reto.ods_sugeridos" :key="ods" class="text-sm font-semibold text-[#1F2937]">{{ ods }}</li>
                  </ul>
                </div>
              </div>

              <div class="pt-6">
                <h3 class="flex items-center gap-2 text-[#1F2937] font-bold uppercase text-xs tracking-widest border-b-2 border-gray-200 pb-2 mb-6">
                  <svg class="w-5 h-5 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                  RA/CE Seleccionados
                </h3>
                
                <div class="space-y-6">
                  <div v-for="evalObj in reto.evaluacion_oficial" :key="evalObj.modulo" class="bg-white border border-gray-200 p-6 rounded-2xl shadow-sm">
                    <p class="text-xs uppercase font-bold text-gray-400 mb-1">Módulo</p>
                    <p class="font-black text-[#1F2937] text-lg mb-4">{{ evalObj.modulo }}</p>
                    <div class="mb-4">
                      <p class="text-xs uppercase font-bold text-[#00A859] mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg> Resultado de Aprendizaje
                      </p>
                      <p class="text-sm font-semibold text-gray-700 bg-gray-50 p-4 rounded-xl border border-gray-100">{{ evalObj.ra }}</p>
                    </div>
                    <div class="mb-4">
                      <p class="text-xs uppercase font-bold text-gray-500 mb-2">Criterios de Evaluación:</p>
                      <ul class="space-y-2">
                        <li v-for="(ce, i) in evalObj.ce" :key="i" class="text-sm text-gray-600 flex items-start gap-2">
                          <span class="text-[#00A859] font-bold mt-0.5">✓</span> {{ ce }}
                        </li>
                      </ul>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                      <p class="text-sm text-gray-500 italic"><span class="font-bold not-italic text-[#1F2937]">Aplicación:</span> {{ evalObj.aplicacion }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="reto.variantes && reto.variantes.length > 0" class="pt-6">
                <h3 class="flex items-center gap-2 text-[#00A859] font-bold uppercase text-xs tracking-widest border-b border-gray-100 pb-2 mb-4">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                  Variantes
                </h3>
                <ul class="space-y-3">
                  <li v-for="(varItem, i) in reto.variantes" :key="i" class="text-sm text-gray-700 bg-gray-50 border border-gray-200 p-4 rounded-xl shadow-sm">
                    <strong v-if="varItem.includes(':')" class="text-[#1F2937] block mb-1">{{ varItem.split(':')[0] }}</strong>
                    <span>{{ varItem.includes(':') ? varItem.substring(varItem.indexOf(':') + 1).trim() : varItem }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div> 
          
          <div class="bg-gray-50 text-[#1F2937] p-10 md:p-16 rounded-[2rem] shadow-sm mx-auto max-w-5xl border border-gray-200 relative mt-12">
            <div class="absolute top-0 right-0 bg-white border-b border-l border-gray-200 text-gray-500 px-6 py-2 font-bold text-xs tracking-widest uppercase rounded-bl-xl shadow-sm">Uso Exclusivo Docente</div>
            <h2 class="text-2xl font-black text-[#1F2937] mb-2 mt-2 flex items-center gap-3">
              <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
              Guía de Implementación
            </h2>
            <p class="text-gray-500 text-sm mb-12">Recomendaciones pedagógicas para dinamizar el reto.</p>
            
            <div class="grid grid-cols-1 gap-6">
              <div v-for="(tip, i) in reto.tips_profesorado" :key="i" class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <div class="text-sm text-gray-700 leading-relaxed">
                  <strong v-if="tip.includes(':')" class="text-[#00A859] block mb-2 uppercase tracking-wider text-xs">
                    <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="i===0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ tip.split(':')[0] }}
                  </strong>
                  <span class="block text-gray-600">{{ tip.includes(':') ? tip.substring(tip.indexOf(':') + 1).trim() : tip }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <transition name="fade">
        <div v-if="popupActivo" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="cerrarPopup">
          <div class="bg-white rounded-3xl p-8 max-w-lg w-full shadow-2xl border border-gray-100 relative">
            <button @click="cerrarPopup" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 p-2 rounded-full transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            <div class="flex items-center gap-3 mb-6">
              <div :class="popupActivo.tipo === 'info' ? 'bg-[#00A859]/10 text-[#00A859]' : 'bg-[#99CC33]/20 text-[#00A859]'" class="p-3 rounded-2xl">
                <svg v-if="popupActivo.tipo === 'info'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
              </div>
              <h3 class="text-xl font-black uppercase tracking-tight text-[#1F2937]">{{ popupActivo.tipo === 'info' ? 'Ayuda' : 'Ejemplo' }}</h3>
            </div>
            <p class="text-gray-600 text-base md:text-lg leading-relaxed bg-gray-50 p-5 rounded-2xl border border-gray-100">{{ ayudas[popupActivo.id][popupActivo.tipo] }}</p>
          </div>
        </div>
      </transition>

    </div>
  </div>

  <!-- Botón volver arriba: visible en paso 3 -->
  <transition name="fade">
    <button
      v-if="pasoActual === 3"
      @click="scrollToTop"
      class="fixed bottom-8 right-8 z-50 w-14 h-14 bg-[#1F2937] text-white rounded-full shadow-2xl flex items-center justify-center hover:bg-[#00A859] transition-all duration-300 hover:-translate-y-1 active:scale-95 border-2 border-white/10"
      title="Volver arriba"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
      </svg>
    </button>
  </transition>

  <LoginModal v-model="showLogin" @login-success="onLoginSuccess" />

  <InsertModifyEmpresa
    ref="insertModifyRef"
    v-model:mostrarNuevaEmpresa="mostrarNuevaEmpresa"
    v-model:mostrarEditarEmpresa="mostrarEditarEmpresa"
    :nombreBuscado="buscadorEmpresa"
    :familiasProfesionales="todasLasFamilias"
    :centrosDisponibles="centrosDisponibles"
    :empresaAEditar="empresaDetalle"
    @empresa-creada="onEmpresaCreada"
    @empresa-actualizada="onEmpresaActualizada"
    @necesita-login="onNecesitaLoginEmpresa"
  />

  <!-- Modal: ¿Activar guía-tour? -->
  <TourPromptModal
    :show="showTourPrompt"
    titulo="¿Quieres activar la guía-tour?"
    descripcion="Explora el generador de microretos con una guía paso a paso."
    @activar="activarTourDesdeModal"
    @omitir="omitirTourDesdeModal"
  />
</template>

<style scoped>
@reference "../style.css";

/* Estilos adaptados: Inputs OSCUROS sobre fondo claro para alto contraste */
.input-style {
  @apply w-full border-2 rounded-2xl p-4 text-sm font-semibold outline-none transition-all shadow-inner disabled:opacity-50;
  background-color: #F0FBF4;
  color: #1F2937;
  border-color: #BBE8D0;
}

.input-style::placeholder {
  color: #9CA3AF;
}

.input-style:focus {
  background-color: #E6F7EE;
  border-color: #00A859;
  box-shadow: 0 0 0 4px rgba(0, 168, 89, 0.15);
}

.label-style {
  @apply text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 ml-4 mb-2 block;
}

.btn-save {
  @apply px-8 py-4 bg-white border-2 border-gray-200 text-[#1F2937] rounded-full font-black text-xs uppercase tracking-widest shadow-sm transition-all hover:-translate-y-1 hover:border-[#00A859] hover:text-[#00A859] active:scale-95 disabled:hover:translate-y-0 disabled:opacity-50;
}

/* Animaciones más suaves acordes a una interfaz clara */
.fade-enter-active, .fade-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.fade-enter-from { opacity: 0; transform: translateX(10px); }
.fade-leave-to { opacity: 0; transform: translateX(-10px); }
.fade-up-enter-active { transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
.fade-up-enter-from { opacity: 0; transform: translateY(40px); }

.dropdown-enter-active, .dropdown-leave-active {
  transition: all 0.15s ease;
}
.dropdown-enter-from, .dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* Brillo guía de pasos */
.step-glow-active {
  animation: glow-green 2s ease-in-out infinite;
}
.step-glow-empresa {
  animation: glow-lime 2s ease-in-out infinite;
}
@keyframes glow-green {
  0%, 100% { box-shadow: 0 0 0 2px #00A859, 0 0 14px rgba(0, 168, 89, 0.25); }
  50%       { box-shadow: 0 0 0 3px #00A859, 0 0 24px rgba(0, 168, 89, 0.45); }
}
@keyframes glow-lime {
  0%, 100% { box-shadow: 0 0 0 2px #99CC33, 0 0 14px rgba(153, 204, 51, 0.25); }
  50%       { box-shadow: 0 0 0 3px #99CC33, 0 0 24px rgba(153, 204, 51, 0.45); }
}

/* ─── Tour guiado ────────────────────────────────────────────────────────── */
.tour-active {
  box-shadow: 0 0 0 3px #00A859, 0 0 0 8px rgba(0, 168, 89, 0.2), 0 4px 20px rgba(0,0,0,0.1) !important;
  border-radius: 1rem;
  transition: box-shadow 0.25s ease;
  position: relative;
  z-index: 9991;
}

.tour-seccion-blur {
  filter: blur(2px);
  opacity: 0.4;
  pointer-events: none;
  transition: filter 0.3s ease, opacity 0.3s ease;
}

.sp-fade-enter-active, .sp-fade-leave-active { transition: opacity 200ms ease; }
.sp-fade-enter-from, .sp-fade-leave-to { opacity: 0; }
</style>