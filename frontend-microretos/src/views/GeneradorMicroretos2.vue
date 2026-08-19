<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import api from '../api.js'; 

const familias = ref([]); 
const ciclos = ref([]);
const modulos = ref([]);

// --- CONTROL DE NAVEGACIÓN ---
const pasoActual = ref(1); 
const totalPasos = 3;

// --- VARIABLES B2B ---
const empresas = ref([]);
const familiasFiltradas = ref([]); 
const buscadorEmpresa = ref('');
const mostrarDropdownEmpresas = ref(false);
const empresaDetalle = ref(null); 

const seleccion = ref({
  // Paso 1
  empresaId: '', 
  empresaNombre: '',
  empresaSector: '',
  empresaUbicacion: '',
  empresaTamano: '', 
  empresaWeb: '',
  
  // Paso 2 (Diagnóstico)
  diaANormal: '',
  friccionArea: '',
  friccionProblema: '',
  consecuencias: [],
  restricciones: '',
  loQueNoQuieren: '',

  // Paso 3
  familia: '',
  cicloId: '', 
  duracion: '1 a 2 semanas',
  nivelGrupo: 'Medio',
});

// --- ESTADO PARA LOS MÓDULOS Y FEEDBACK ---
const moduloSeleccionado1 = ref('');
const moduloSeleccionado2 = ref('');
const modulosCurso1 = computed(() => modulos.value.filter(m => m.curso == 1));
const modulosCurso2 = computed(() => modulos.value.filter(m => m.curso == 2));

const microretoGenerado = ref(null);
const cargando = ref(false);
const actualizandoCRM = ref(false);
const crmActualizado = ref(false);
const guardadoExitoso = ref(false);

// --- NUEVO: FLAG PARA SABER SI HAY DIAGNÓSTICO PREVIO ---
const diagnosticoRecuperado = ref(false);

const consecuenciasOpciones = [
  'Errores frecuentes', 'Costes innecesarios', 'Pérdida de tiempo', 
  'Insatisfacción del cliente', 'Riesgos de seguridad', 'Desperdicio de materiales', 'Falta de comunicación'
];

// --- AUTOCOMPLETADO EMPRESAS ---
const empresasFiltradasBusqueda = computed(() => {
  if (!buscadorEmpresa.value) return empresas.value;
  return empresas.value.filter(e => 
    e.nombre_comercial.toLowerCase().includes(buscadorEmpresa.value.toLowerCase())
  );
});

const seleccionarEmpresa = (emp) => {
  seleccion.value.empresaId = emp.id;
  buscadorEmpresa.value = emp.nombre_comercial;
  mostrarDropdownEmpresas.value = false;
};

// --- VALIDACIONES ---
const paso1Valido = computed(() => seleccion.value.empresaNombre && seleccion.value.empresaSector && seleccion.value.empresaTamano);
const paso2Valido = computed(() => seleccion.value.friccionArea && seleccion.value.friccionProblema);
const paso3Valido = computed(() => seleccion.value.familia && seleccion.value.cicloId); 

// --- LÓGICA API ---
onMounted(async () => {
  try {
    const resEmpresas = await api.get('/empresas');
    empresas.value = resEmpresas.data; 
  } catch (e) { console.error("Error cargando empresas", e); }
});

// WATCHER: Cuando se selecciona una empresa (AQUÍ OCURRE EL AUTOCOMPLETADO TOTAL)
watch(() => seleccion.value.empresaId, async (nuevoId) => {
  // 1. Reseteamos todo por seguridad
  seleccion.value.familia = '';
  ciclos.value = [];
  seleccion.value.cicloId = '';
  modulos.value = [];
  moduloSeleccionado1.value = '';
  moduloSeleccionado2.value = '';
  familiasFiltradas.value = [];
  empresaDetalle.value = null;
  crmActualizado.value = false;
  diagnosticoRecuperado.value = false;

  // Limpiamos Paso 2
  seleccion.value.diaANormal = '';
  seleccion.value.friccionArea = '';
  seleccion.value.friccionProblema = '';
  seleccion.value.consecuencias = [];
  seleccion.value.restricciones = '';
  seleccion.value.loQueNoQuieren = '';

  if (!nuevoId) {
    seleccion.value.empresaNombre = '';
    seleccion.value.empresaSector = '';
    seleccion.value.empresaTamano = '';
    return;
  }

  const emp = empresas.value.find(e => e.id === nuevoId);
  if(emp) {
    // Autocompletar Paso 1
    empresaDetalle.value = emp; 
    seleccion.value.empresaNombre = emp.nombre_comercial;
    seleccion.value.empresaSector = emp.sector || ''; 
    seleccion.value.empresaTamano = emp.tamano || ''; 
    seleccion.value.empresaWeb = emp.web || '';
    
    let ubi = [];
    if (emp.municipio) ubi.push(emp.municipio);
    if (emp.provincia) ubi.push(emp.provincia);
    seleccion.value.empresaUbicacion = ubi.join(', ');

    // --- NUEVO: Autocompletar Paso 2 (Diagnóstico) ---
    if (emp.friccion_area && emp.friccion_problema) {
      diagnosticoRecuperado.value = true;
      seleccion.value.diaANormal = emp.dia_a_normal || '';
      seleccion.value.friccionArea = emp.friccion_area;
      seleccion.value.friccionProblema = emp.friccion_problema;
      seleccion.value.restricciones = emp.restricciones || '';
      seleccion.value.loQueNoQuieren = emp.lo_que_no_quieren || '';
      
      // Convertir el string guardado de consecuencias ('Costes, Tiempo') de vuelta a array
      if (emp.consecuencias) {
        seleccion.value.consecuencias = emp.consecuencias.split(',').map(s => s.trim());
      }
    }
  }

  // Traer familias
  try {
    const resFam = await api.get(`/empresas/${nuevoId}/familias`);
    familiasFiltradas.value = resFam.data;
  } catch(e) { console.error("Error cargando familias", e); }
});

watch(() => seleccion.value.familia, async (val) => {
  if (!val) return;
  const res = await api.get(`/familias/${encodeURIComponent(val)}/ciclos`);
  ciclos.value = res.data;
  seleccion.value.cicloId = ''; 
  modulos.value = []; 
  moduloSeleccionado1.value = '';
  moduloSeleccionado2.value = '';
});

watch(() => seleccion.value.cicloId, async (val) => {
  if (!val) return;
  const res = await api.get(`/ciclos/${val}/modulos`);
  modulos.value = res.data;
  moduloSeleccionado1.value = '';
  moduloSeleccionado2.value = '';
});

const avanzarPaso = () => { if (pasoActual.value < totalPasos) pasoActual.value++; window.scrollTo({top: 0, behavior: 'smooth'}); };
const retrocederPaso = () => { if (pasoActual.value > 1) pasoActual.value--; window.scrollTo({top: 0, behavior: 'smooth'}); };

const safeUrl = (url) => {
  if (!url) return '#';
  return /^https?:\/\//i.test(url) ? url : '#';
};

// --- ACTUALIZAR CRM (AHORA GUARDA PASO 1 Y PASO 2) ---
const guardarInfoEmpresa = async () => {
  if (!seleccion.value.empresaId) return;
  actualizandoCRM.value = true;
  try {
    // Mandamos todo el payload al backend
    await api.put(`/empresas/${seleccion.value.empresaId}`, {
      // Datos Paso 1
      sector: seleccion.value.empresaSector,
      tamano: seleccion.value.empresaTamano,
      web: seleccion.value.empresaWeb,
      
      // Datos Paso 2 (Diagnóstico)
      diaANormal: seleccion.value.diaANormal,
      friccionArea: seleccion.value.friccionArea,
      friccionProblema: seleccion.value.friccionProblema,
      consecuencias: seleccion.value.consecuencias, // El backend lo convertirá a string
      restricciones: seleccion.value.restricciones,
      loQueNoQuieren: seleccion.value.loQueNoQuieren
    });
    
    // Actualizamos la variable local en memoria para no tener que recargar la web
    const emp = empresas.value.find(e => e.id === seleccion.value.empresaId);
    if(emp) {
      emp.sector = seleccion.value.empresaSector;
      emp.tamano = seleccion.value.empresaTamano;
      emp.web = seleccion.value.empresaWeb;
      
      emp.dia_a_normal = seleccion.value.diaANormal;
      emp.friccion_area = seleccion.value.friccionArea;
      emp.friccion_problema = seleccion.value.friccionProblema;
      emp.consecuencias = seleccion.value.consecuencias.join(', ');
      emp.restricciones = seleccion.value.restricciones;
      emp.lo_que_no_quieren = seleccion.value.loQueNoQuieren;
    }
    
    crmActualizado.value = true;
    setTimeout(() => crmActualizado.value = false, 3000); 
  } catch(e) {
    alert("Error al actualizar la empresa en la BD.");
  } finally {
    actualizandoCRM.value = false;
  }
};

const generarReto = async () => {
  cargando.value = true;
  try {
    let modulosArray = [moduloSeleccionado1.value, moduloSeleccionado2.value].filter(Boolean);
    const nombresModulosSeleccionados = modulosArray.map(id => {
      return modulos.value.find(m => m.id === id)?.nombre;
    }).filter(Boolean); 

    const moduloNombreTxt = nombresModulosSeleccionados.length > 0 
      ? nombresModulosSeleccionados.join(' y ') : 'A determinar por IA';

    const res = await api.post('/generar-microreto', {
      ...seleccion.value,
      ciclo_nombre: ciclos.value.find(c => c.id === seleccion.value.cicloId)?.nombre,
      modulo_nombre: moduloNombreTxt,
      ciclo_id: seleccion.value.cicloId,
      modulo_id: modulosArray.length > 0 ? modulosArray : null, 
      nivelGrupo: seleccion.value.nivelGrupo
    });
    microretoGenerado.value = res.data;
    setTimeout(() => { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); }, 100);
  } catch (e) { console.error(e); alert("Error al contactar con la IA"); }
  finally { cargando.value = false; }
};

const guardar = async () => {
  try {
    let modulosArray = [moduloSeleccionado1.value, moduloSeleccionado2.value].filter(Boolean);
    const nombresModulosSeleccionados = modulosArray.map(id => {
      return modulos.value.find(m => m.id === id)?.nombre;
    }).filter(Boolean);

    await api.post('/guardar-microreto-bd', {
      ...microretoGenerado.value,
      ciclo: ciclos.value.find(c => c.id === seleccion.value.cicloId)?.nombre,
      modulo: nombresModulosSeleccionados.length > 0 ? nombresModulosSeleccionados.join(' y ') : 'Transversal',
      curso: nombresModulosSeleccionados.length === 0 ? 'ambos_cursos' : undefined,
      duracion: seleccion.value.duracion,
      nivel_grupo: seleccion.value.nivelGrupo,
      es_simulado: !!(empresaDetalle.value?.es_simulada),
    });
    guardadoExitoso.value = true;
  } catch (e) { alert("Error al guardar"); }
};
</script>

<template>
  <div class="min-h-screen dark:bg-[#0a0c10] p-4 md:p-12 transition-colors duration-500 font-sans text-slate-900 dark:text-slate-200">
    <div class="max-w-6xl mx-auto">
      
      <header class="mb-10 text-center">
        <div class="inline-flex items-center gap-3 mb-4 bg-white dark:bg-slate-900 p-2 px-4 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
          <div class="bg-emerald-500 p-1.5 rounded-lg">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          </div>
          <span class="font-black text-xl tracking-tighter uppercase dark:text-white italic">DuaLab <span class="text-emerald-500 not-italic text-xs">Studio Tool</span></span>
        </div>
        <h1 class="text-5xl font-black tracking-tight mb-4 bg-clip-text text-transparent bg-gradient-to-r from-slate-900 to-slate-500 dark:from-white dark:to-slate-400">
          Factoría de Micro-Retos
        </h1>
        <p class="text-slate-500 dark:text-slate-400 max-w-2xl mx-auto text-base leading-relaxed italic">
          Convierte problemas empresariales reales en retos educativos clasificados por el currículo oficial.
        </p>
      </header>

      <div class="max-w-3xl mx-auto mb-16 relative">
        <div class="flex justify-between items-center relative z-10">
          <div v-for="step in totalPasos" :key="step" class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black transition-all duration-500 shadow-xl"
              :class="pasoActual >= step ? 'bg-emerald-500 text-white scale-110 shadow-emerald-500/20' : 'bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-800 text-slate-400'">
              <span v-if="pasoActual > step">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
              </span>
              <span v-else>{{ step }}</span>
            </div>
            <span class="text-[10px] font-black uppercase mt-3 tracking-widest text-center" :class="pasoActual >= step ? 'text-emerald-500' : 'text-slate-400'">
              {{ step === 1 ? '1. Datos Empresa' : step === 2 ? '2. Diagnóstico de Empresa' : '3. Match Académico' }}
            </span>
          </div>
        </div>
        <div class="absolute top-6 left-0 w-full h-1 bg-slate-200 dark:bg-slate-800 -z-0 rounded-full"></div>
        <div class="absolute top-6 left-0 h-1 bg-emerald-500 transition-all duration-700 -z-0 rounded-full" :style="{ width: ((pasoActual - 1) / (totalPasos - 1)) * 100 + '%' }"></div>
      </div>

      <main class="min-h-[400px]">
        
        <transition name="fade" mode="out-in">
          <div v-if="pasoActual === 1" class="space-y-8 animate-in slide-in-from-bottom-4 duration-500">
            <section class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-10 border border-slate-200 dark:border-slate-800 shadow-sm">
              <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center text-emerald-500">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <h2 class="text-2xl font-black uppercase tracking-tight">Buscar en CRM DuaLab</h2>
              </div>
              
              <div class="mb-10 relative z-20">
                <label class="label-style !ml-2">Escribe para buscar empresa</label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                  </div>
                  <input 
                    v-model="buscadorEmpresa" 
                    @focus="mostrarDropdownEmpresas = true"
                    class="input-style pl-14 text-lg border-emerald-200" 
                    placeholder="Ej: Fundación Sergio Alonso..." 
                  />
                </div>
                
                <div v-if="mostrarDropdownEmpresas && empresasFiltradasBusqueda.length > 0" 
                     class="absolute w-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl max-h-64 overflow-y-auto">
                  <button v-for="emp in empresasFiltradasBusqueda" :key="emp.id" 
                          @click="seleccionarEmpresa(emp)"
                          class="w-full text-left px-6 py-4 border-b border-slate-100 dark:border-slate-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors last:border-0">
                    <span class="font-bold text-slate-800 dark:text-slate-200 block">{{ emp.nombre_comercial }}</span>
                    <span class="text-xs text-slate-500 uppercase tracking-widest">{{ emp.sector || 'Sin sector especificado' }}</span>
                  </button>
                </div>
              </div>

              <div v-if="empresaDetalle" class="bg-slate-50 dark:bg-slate-800/50 rounded-3xl p-8 border border-slate-200 dark:border-slate-700 mb-8 animate-in fade-in duration-500">
                <div class="flex items-center gap-3 mb-6">
                  <div class="w-2 h-6 bg-emerald-500 rounded-full"></div>
                  <h3 class="font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest text-sm">Ficha de Contacto</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  <div v-if="empresaDetalle.persona_contacto || empresaDetalle.telefono || empresaDetalle.email_general">
                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-widest mb-1">Contacto Directo</p>
                    <p v-if="empresaDetalle.persona_contacto" class="font-semibold text-sm">{{ empresaDetalle.persona_contacto }}</p>
                    <p v-if="empresaDetalle.telefono" class="text-slate-600 dark:text-slate-300 text-sm flex items-center gap-2 mt-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg> {{ empresaDetalle.telefono }}
                    </p>
                    <p v-if="empresaDetalle.email_general" class="text-slate-600 dark:text-slate-300 text-sm flex items-center gap-2 mt-1 truncate">
                      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> {{ empresaDetalle.email_general }}
                    </p>
                  </div>

                  <div v-if="empresaDetalle.direccion || empresaDetalle.municipio || empresaDetalle.codigo_postal">
                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-widest mb-1">Ubicación</p>
                    <p class="text-sm text-slate-700 dark:text-slate-300 leading-tight">
                      {{ empresaDetalle.direccion }} {{ empresaDetalle.numero }} <br>
                      {{ empresaDetalle.codigo_postal }} - {{ empresaDetalle.municipio }} <span v-if="empresaDetalle.provincia">({{ empresaDetalle.provincia }})</span>
                    </p>
                  </div>

                  <div v-if="empresaDetalle.actividad || empresaDetalle.web">
                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-widest mb-1">Actividad / Web</p>
                    <p v-if="empresaDetalle.actividad" class="text-sm text-slate-700 dark:text-slate-300 line-clamp-2" :title="empresaDetalle.actividad">{{ empresaDetalle.actividad }}</p>
                    <a v-if="empresaDetalle.web"
                       :href="safeUrl(empresaDetalle.web)"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-emerald-500 hover:text-emerald-600 font-bold text-sm truncate flex items-center gap-1 mt-1">
                      {{ empresaDetalle.web.replace(/^https?:\/\//, '') }}
                    </a>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative">
                <div v-if="!seleccion.empresaId" class="absolute inset-0 bg-white/50 dark:bg-slate-900/50 z-10 rounded-2xl flex items-center justify-center backdrop-blur-[2px]">
                  <span class="bg-slate-900 text-white px-4 py-2 rounded-full text-xs font-bold uppercase tracking-widest shadow-xl">Busca una empresa arriba 👆</span>
                </div>

                <div class="col-span-2">
                  <label class="label-style">Nombre Comercial *</label>
                  <input v-model="seleccion.empresaNombre" class="input-style bg-slate-100" readonly />
                </div>
                
                <div>
                  <label class="label-style" :class="!seleccion.empresaSector && seleccion.empresaId ? 'text-red-500' : ''">Sector de Actividad *</label>
                  <input v-model="seleccion.empresaSector" class="input-style" :class="!seleccion.empresaSector && seleccion.empresaId ? 'border-red-500 bg-red-50 placeholder:text-red-300 focus:border-red-600 animate-pulse' : ''" placeholder="¡FALTA INFO! Rellénalo por favor..." />
                </div>

                <div>
                  <label class="label-style" :class="!seleccion.empresaTamano && seleccion.empresaId ? 'text-red-500' : ''">Tamaño de la Empresa *</label>
                  <select v-model="seleccion.empresaTamano" class="input-style" :class="!seleccion.empresaTamano && seleccion.empresaId ? 'border-red-500 bg-red-50 text-red-500 focus:border-red-600 animate-pulse' : ''">
                    <option value="" disabled selected>¡FALTA INFO! Selecciona...</option>
                    <option value="Micropyme (1-10)">Micropyme (1 a 10 empleados)</option>
                    <option value="Pequeña (10-50)">Pequeña (10 a 50 empleados)</option>
                    <option value="Mediana (50-250)">Mediana (50 a 250 empleados)</option>
                    <option value="Grande (+250)">Grande (Más de 250 empleados)</option>
                  </select>
                </div>

                <div class="col-span-2">
                  <label class="label-style">Web (Opcional)</label>
                  <input v-model="seleccion.empresaWeb" class="input-style" placeholder="https://..." />
                </div>
              </div>
            </section>
          </div>
        </transition>

        <transition name="fade" mode="out-in">
          <div v-if="pasoActual === 2" class="space-y-8 animate-in slide-in-from-bottom-4 duration-500">
             <section class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-10 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
              <div class="absolute top-0 right-0 bg-amber-500/10 text-amber-600 px-6 py-2 text-[10px] font-black uppercase tracking-widest rounded-bl-3xl">
                Diagnóstico Consultor DuaLab
              </div>
              <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center text-amber-500">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h2 class="text-2xl font-black uppercase tracking-tight">Análisis de la Fricción</h2>
              </div>

              <div v-if="diagnosticoRecuperado" class="mb-10 p-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-3xl flex gap-5 items-start shadow-inner">
                <div class="bg-blue-500 text-white p-2.5 rounded-2xl shrink-0 mt-1 shadow-md shadow-blue-500/30">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                  <h4 class="font-black text-blue-900 dark:text-blue-300 uppercase tracking-widest text-xs mb-2">Diagnóstico Previo Recuperado</h4>
                  <p class="text-sm text-blue-700 dark:text-blue-400 leading-relaxed font-medium">
                    Ya existe un contexto asociado a esta empresa en la base de datos. Puedes usar esta misma información para generar <strong class="font-black text-blue-800 dark:text-blue-200">nuevos enfoques</strong> con la IA, o modificar el texto a continuación si el problema de la empresa ha cambiado.
                  </p>
                </div>
              </div>

              <div class="space-y-8">
                <div>
                  <label class="label-style">Contexto: ¿Qué hace la empresa en un día normal?</label>
                  <textarea v-model="seleccion.diaANormal" class="input-style h-24" placeholder="Describe procesos, departamentos implicados..."></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div>
                    <label class="label-style">Área de fricción *</label>
                    <input v-model="seleccion.friccionArea" class="input-style" placeholder="Ej: Control de Stock, Facturación..." />
                  </div>
                  <div>
                    <label class="label-style">¿Qué ocurre hoy y por qué es un problema? *</label>
                    <textarea v-model="seleccion.friccionProblema" class="input-style h-24" placeholder="El 'dolor' tal cual te lo han contado..."></textarea>
                  </div>
                </div>
                <div>
                  <label class="label-style mb-4 block">Consecuencias (Selección múltiple)</label>
                  <div class="flex flex-wrap gap-2">
                    <button v-for="opt in consecuenciasOpciones" :key="opt" @click="seleccion.consecuencias.includes(opt) ? seleccion.consecuencias = seleccion.consecuencias.filter(c => c !== opt) : seleccion.consecuencias.push(opt)"
                      :class="seleccion.consecuencias.includes(opt) ? 'bg-amber-500 text-white border-amber-500 shadow-lg shadow-amber-500/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 border-transparent'"
                      class="px-5 py-2.5 rounded-2xl border text-[10px] font-black uppercase transition-all">
                      {{ opt }}
                    </button>
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div>
                    <label class="label-style">Restricciones Reales (Hardware, licencias...)</label>
                    <input v-model="seleccion.restricciones" class="input-style" placeholder="Ej: No tienen presupuesto para licencias caras..." />
                  </div>
                  <div>
                    <label class="label-style">¿Qué NO quieren como solución?</label>
                    <input v-model="seleccion.loQueNoQuieren" class="input-style" placeholder="Ej: Apps que requieran mucho mantenimiento..." />
                  </div>
                </div>
              </div>
            </section>
          </div>
        </transition>

        <transition name="fade" mode="out-in">
          <div v-if="pasoActual === 3" class="space-y-8 animate-in slide-in-from-bottom-4 duration-500">
            <section class="bg-white dark:bg-slate-900 rounded-[2.5rem] p-10 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
              <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-500">
                   <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                </div>
                <div>
                  <h2 class="text-2xl font-black uppercase tracking-tight">Match Académico</h2>
                </div>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="col-span-2 md:col-span-1">
                  <label class="label-style">Familia Profesional Asociada *</label>
                  <select v-model="seleccion.familia" class="input-style bg-emerald-50/50 border-emerald-200">
                    <option value="">Selecciona Familia...</option>
                    <option v-for="f in familiasFiltradas" :key="f" :value="f">{{ f }}</option>
                  </select>
                </div>

                <div class="col-span-2 md:col-span-1">
                  <label class="label-style">Nivel del Grupo-Clase *</label>
                  <select v-model="seleccion.nivelGrupo" class="input-style">
                    <option value="Bajo">Básico (Ej: FP Básica o 1º de GM)</option>
                    <option value="Medio">Medio (Ej: 2º de GM o 1º de GS)</option>
                    <option value="Alto">Alto (Ej: 2º de GS o Especialización)</option>
                  </select>
                </div>

                <div class="col-span-2 mt-4">
                  <label class="label-style !mb-4">Ciclo Formativo Implicado *</label>
                  
                  <div v-if="ciclos.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                     <button v-for="c in ciclos" :key="c.id" 
                        @click="seleccion.cicloId = c.id"
                        :class="seleccion.cicloId === c.id 
                            ? 'bg-emerald-500 text-white border-emerald-500 shadow-md shadow-emerald-500/20' 
                            : 'bg-white dark:bg-slate-800 text-slate-600 border-slate-200 hover:border-emerald-300'"
                        class="text-left px-5 py-4 rounded-2xl border-2 transition-all duration-200 flex items-center gap-3 relative overflow-hidden group">
                        
                        <div class="shrink-0 transition-transform" :class="seleccion.cicloId === c.id ? 'scale-100' : 'scale-0 opacity-0'">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        
                        <div class="flex-1" :class="seleccion.cicloId !== c.id && 'pl-2'">
                            <span class="text-sm font-bold leading-tight">{{ c.nombre }}</span>
                        </div>
                     </button>
                  </div>
                  <div v-else class="bg-slate-50 border border-dashed border-slate-300 rounded-3xl p-6 text-center">
                      <p class="text-slate-500 text-sm">Selecciona una Familia Profesional para ver sus ciclos.</p>
                  </div>
                </div>

                <div class="col-span-2 mt-4 pt-6 border-t border-slate-100">
                  <div class="flex items-center justify-between mb-4">
                     <label class="label-style !mb-0">Filtro de Módulos (Opcional)</label>
                     <span class="text-xs text-slate-400 italic">Déjalos vacíos para que decida la IA</span>
                  </div>
                 
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="bg-blue-50/50 p-6 rounded-3xl border border-blue-100">
                      <label class="label-style text-blue-600">Módulo de 1º Curso</label>
                      <select v-model="moduloSeleccionado1" :disabled="modulosCurso1.length === 0" class="input-style border-white shadow-sm">
                        <option value="">A elección de la IA...</option>
                        <option v-for="m in modulosCurso1" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                      </select>
                      <p v-if="modulosCurso1.length === 0 && seleccion.cicloId" class="text-xs text-slate-400 mt-2 ml-2 italic">Sin módulos de 1º cargados.</p>
                    </div>

                    <div class="bg-amber-50/50 p-6 rounded-3xl border border-amber-100">
                      <label class="label-style text-amber-600">Módulo de 2º Curso</label>
                      <select v-model="moduloSeleccionado2" :disabled="modulosCurso2.length === 0" class="input-style border-white shadow-sm">
                        <option value="">A elección de la IA...</option>
                        <option v-for="m in modulosCurso2" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                      </select>
                      <p v-if="modulosCurso2.length === 0 && seleccion.cicloId" class="text-xs text-slate-400 mt-2 ml-2 italic">Sin módulos de 2º cargados.</p>
                    </div>
                  </div>
                </div>

              </div>
            </section>
          </div>
        </transition>

      </main>

      <div class="mt-16 flex flex-col items-center gap-6">
        
        <div v-if="pasoActual === totalPasos && seleccion.empresaId && !crmActualizado" class="bg-amber-50 border border-amber-200 shadow-md rounded-2xl p-5 flex items-start md:items-center gap-4 w-full max-w-4xl animate-in slide-in-from-bottom-2">
          <div class="bg-amber-100 text-amber-600 p-2 rounded-full shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          </div>
          <div class="flex-1">
            <h4 class="text-amber-800 font-black text-sm uppercase tracking-widest mb-1">¡No pierdas tu trabajo!</h4>
            <p class="text-amber-700 text-sm font-medium">Si has modificado el Sector, el Tamaño o el contexto del Problema, haz clic en <strong class="font-black text-amber-900">Actualizar Empresa</strong> antes de generar el reto para guardarlo en la base de datos.</p>
          </div>
        </div>

        <div class="flex flex-wrap md:flex-nowrap gap-4 w-full max-w-4xl">
          <button v-if="pasoActual > 1" @click="retrocederPaso" class="flex-1 min-w-[150px] px-10 py-6 bg-slate-200 dark:bg-slate-800 rounded-full font-black text-xs tracking-widest transition-all hover:bg-slate-300 active:scale-95">
            VOLVER
          </button>
          
          <button v-if="pasoActual < totalPasos" @click="avanzarPaso" 
            :disabled="(pasoActual === 1 && !paso1Valido) || (pasoActual === 2 && !paso2Valido)"
            class="flex-[2] min-w-[200px] px-10 py-6 bg-emerald-600 text-white rounded-full font-black text-xs tracking-widest shadow-2xl shadow-emerald-500/30 disabled:opacity-20 transition-all hover:scale-105 active:scale-95">
            SIGUIENTE PASO
          </button>

          <button v-if="pasoActual === totalPasos" @click="guardarInfoEmpresa" :disabled="actualizandoCRM || crmActualizado"
            class="flex-1 min-w-[200px] px-6 py-6 border-2 border-slate-300 text-slate-700 bg-white rounded-full font-black text-xs tracking-widest transition-all hover:bg-slate-50 active:scale-95 flex items-center justify-center gap-2"
            :class="crmActualizado ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : ''">
            <template v-if="actualizandoCRM">
              <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
              GUARDANDO...
            </template>
            <template v-else-if="crmActualizado">
              <span class="text-emerald-600 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                INFO GUARDADA
              </span>
            </template>
            <template v-else>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
              ACTUALIZAR EMPRESA
            </template>
          </button>

          <button v-if="pasoActual === totalPasos" @click="generarReto" :disabled="!paso3Valido || cargando"
            class="flex-[2] min-w-[250px] px-8 py-6 bg-emerald-600 text-white rounded-full font-black text-lg shadow-[0_20px_50px_rgba(16,185,129,0.3)] hover:shadow-[0_25px_60px_rgba(16,185,129,0.5)] transition-all hover:-translate-y-1 active:scale-95 disabled:opacity-30 flex items-center justify-center gap-3">
            <template v-if="!cargando">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
              <span class="uppercase tracking-wide">Procesar con IA</span>
            </template>
            <template v-else>
              <div class="w-5 h-5 border-3 border-white/30 border-t-white rounded-full animate-spin"></div>
              <span class="text-sm">GENERANDO RETO...</span>
            </template>
          </button>
        </div>
      </div>

      <transition name="fade-up">
        <div v-if="microretoGenerado" class="mt-24 pb-20 space-y-8 font-sans">
          
          <div class="flex justify-between items-center bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div>
              <h3 class="text-lg font-black text-slate-800">Previsualización del Reto</h3>
              <p class="text-sm text-slate-500">Diseño listo para el aula.</p>
            </div>
            <button @click="guardar" :disabled="guardadoExitoso" class="btn-save" :class="guardadoExitoso ? 'bg-emerald-600' : ''">
              {{ guardadoExitoso ? '✓ GUARDADO EN BD' : 'GUARDAR EN BIBLIOTECA' }}
            </button>
          </div>
          
          <div class="mx-auto max-w-5xl space-y-6">
            
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl p-10 md:p-16 shadow-2xl relative overflow-hidden">
              <div class="absolute top-0 right-0 bg-emerald-500 text-white px-6 py-2 font-black text-xs tracking-widest uppercase rounded-bl-2xl shadow-lg">
                Ficha Alumnado
              </div>
              <div class="absolute -bottom-10 -right-10 text-white/5 opacity-50">
                <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
              </div>
              
              <div class="relative z-10">
                <p class="text-emerald-400 font-bold text-xs tracking-[0.2em] uppercase mb-4">DuaLab Studio</p>
                <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight leading-tight mb-4">
                  {{ microretoGenerado.titulo }}
                </h1>
                <h2 class="text-xl text-slate-300 font-medium leading-relaxed mb-10 max-w-3xl">
                  {{ microretoGenerado.subtitulo }}
                </h2>
                
                <div class="flex flex-wrap gap-4">
                  <span class="flex items-center gap-2 px-5 py-3 bg-white/10 backdrop-blur-md border border-white/20 text-white rounded-xl text-sm font-bold shadow-lg">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    {{ microretoGenerado.empresa_nombre }}
                  </span>
                  <span class="flex items-center gap-2 px-5 py-3 bg-white/10 backdrop-blur-md border border-white/20 text-white rounded-xl text-sm font-bold shadow-lg">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Nivel: {{ seleccion.nivelGrupo }}
                  </span>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              
              <div class="lg:col-span-1 space-y-6">
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 h-full">
                  <h3 class="flex items-center gap-3 text-slate-800 font-black uppercase text-xs tracking-widest mb-6">
                    <div class="p-2 bg-blue-50 text-blue-500 rounded-lg">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                    </div>
                    Conoce a {{ microretoGenerado.empresa_nombre }}
                  </h3>
                  <div class="space-y-6">
                    <div>
                      <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-2">Actividad</p>
                      <p class="text-slate-700 text-sm leading-relaxed">{{ microretoGenerado.quien_es }}</p>
                    </div>
                    <div>
                      <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-2">Su día a día</p>
                      <p class="text-slate-700 text-sm leading-relaxed">{{ microretoGenerado.dia_a_dia }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-white p-8 md:p-10 rounded-3xl shadow-sm border border-slate-200">
                  <div class="mb-10">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-700 font-black text-xs uppercase tracking-widest rounded-lg mb-4 border border-emerald-100">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      El Desafío Principal
                    </div>
                    <p class="text-2xl md:text-3xl font-black text-slate-900 leading-snug">
                      {{ microretoGenerado.pregunta_reto }}
                    </p>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="bg-amber-50/50 p-6 rounded-2xl border border-amber-100">
                      <h4 class="text-amber-800 font-bold uppercase text-xs tracking-widest mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Problemas actuales
                      </h4>
                      <ul class="space-y-3">
                        <li v-for="(item, i) in microretoGenerado.dificultades" :key="i" class="flex items-start gap-3 text-sm text-slate-700">
                          <span class="text-amber-500 font-black mt-0.5">•</span> <span>{{ item }}</span>
                        </li>
                      </ul>
                    </div>
                    
                    <div class="bg-blue-50/50 p-6 rounded-2xl border border-blue-100">
                      <h4 class="text-blue-800 font-bold uppercase text-xs tracking-widest mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Qué necesitan resolver
                      </h4>
                      <ul class="space-y-3">
                        <li v-for="(item, i) in microretoGenerado.que_necesitan" :key="i" class="flex items-start gap-3 text-sm text-slate-700">
                          <span class="text-blue-500 font-black mt-0.5">•</span> <span>{{ item }}</span>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div>
                    <h4 class="text-slate-800 font-bold uppercase text-xs tracking-widest mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                      Limitaciones del proyecto
                    </h4>
                    <ul class="space-y-3">
                      <li v-for="(item, i) in microretoGenerado.limitaciones" :key="i" class="flex items-start gap-3 text-sm text-slate-600">
                        <span class="text-red-500 font-black mt-0.5">✕</span> <span>{{ item }}</span>
                      </li>
                    </ul>
                  </div>
                  <div>
                    <h4 class="text-slate-800 font-bold uppercase text-xs tracking-widest mb-4 flex items-center gap-2">
                      <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                      Prototipos sugeridos
                    </h4>
                    <ul class="space-y-3">
                      <li v-for="(item, i) in microretoGenerado.prototipos" :key="i" class="flex items-start gap-3 text-sm text-slate-600">
                        <span class="text-emerald-500 font-black mt-0.5">→</span> <span>{{ item }}</span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-emerald-900 rounded-3xl p-10 md:p-14 shadow-xl mt-8">
              
              <div class="flex flex-col md:flex-row md:items-end justify-between border-b border-emerald-800 pb-8 mb-10 gap-6">
                <div>
                  <div class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-800 text-emerald-300 font-black text-xs uppercase tracking-widest rounded-lg mb-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                    Aprendizaje Aplicado
                  </div>
                  <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">Relación del reto con tu ciclo</h2>
                </div>
                
                <div class="bg-emerald-950 p-4 rounded-xl border border-emerald-800">
                  <p class="text-emerald-500 text-[10px] font-black uppercase tracking-widest mb-2">Impacto Sostenible</p>
                  <div class="flex flex-col gap-1">
                    <span v-for="ods in microretoGenerado.ods_sugeridos" :key="ods" class="text-sm font-bold text-emerald-100 flex items-center gap-2">
                      <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span> {{ ods }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div v-for="evalObj in microretoGenerado.evaluacion_oficial" :key="evalObj.modulo" class="bg-emerald-950/50 p-8 rounded-2xl border border-emerald-800 shadow-inner flex flex-col">
                  <div class="mb-6">
                    <p class="text-emerald-500 font-bold text-[10px] tracking-widest uppercase mb-2">Módulo Implicado</p>
                    <h4 class="text-2xl font-black text-white leading-tight">{{ evalObj.modulo }}</h4>
                  </div>
                  
                  <div class="space-y-6 grow">
                    <div class="bg-emerald-900 p-5 rounded-xl border border-emerald-800/50">
                      <p class="text-emerald-400 text-[10px] uppercase tracking-widest font-black mb-2">Resultado de Aprendizaje</p>
                      <p class="text-sm font-medium text-emerald-50 leading-relaxed">{{ evalObj.ra }}</p>
                    </div>

                    <div>
                      <p class="text-emerald-500 text-[10px] uppercase tracking-widest font-black mb-3">Criterios a Evaluar</p>
                      <ul class="space-y-3">
                        <li v-for="(ce, i) in evalObj.ce" :key="i" class="text-sm text-emerald-200 flex items-start gap-3">
                          <span class="text-emerald-400 font-bold mt-0.5">✓</span> {{ ce }}
                        </li>
                      </ul>
                    </div>
                  </div>

                  <div class="mt-8 pt-6 border-t border-emerald-800/50">
                    <p class="text-emerald-300 text-sm leading-relaxed">
                      <strong class="text-emerald-400 font-black uppercase text-[10px] tracking-widest block mb-1">Aplicación Práctica:</strong> 
                      {{ evalObj.aplicacion }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="microretoGenerado.variantes && microretoGenerado.variantes.length > 0" class="bg-indigo-50 border border-indigo-100 p-8 rounded-3xl mt-6">
              <h3 class="flex items-center gap-3 text-indigo-900 font-black uppercase text-xs tracking-widest mb-4">
                <div class="p-2 bg-indigo-100 text-indigo-600 rounded-lg">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
                Variantes del Reto
              </h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-for="(varItem, i) in microretoGenerado.variantes" :key="i" class="bg-white p-5 rounded-xl shadow-sm border border-indigo-50">
                  <strong v-if="varItem.includes(':')" class="text-indigo-700 block mb-2 font-black">
                    {{ varItem.split(':')[0] }}
                  </strong>
                  <span class="text-sm text-slate-600 leading-relaxed">{{ varItem.includes(':') ? varItem.substring(varItem.indexOf(':') + 1).trim() : varItem }}</span>
                </div>
              </div>
            </div>

          </div> 
          
          <div class="bg-slate-900 text-slate-200 p-10 md:p-14 rounded-3xl shadow-2xl mx-auto max-w-5xl border border-slate-800 mt-16 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/10 blur-3xl rounded-full"></div>
            
            <div class="flex items-center gap-4 mb-8">
              <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-500 border border-amber-500/30">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
              </div>
              <div>
                <span class="text-amber-500 font-bold text-[10px] tracking-[0.2em] uppercase block mb-1">Uso Exclusivo Docente</span>
                <h2 class="text-2xl font-black text-white">Guía de Implementación</h2>
              </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
              <div v-for="(tip, i) in microretoGenerado.tips_profesorado" :key="i" class="bg-slate-800/80 p-8 rounded-2xl border border-slate-700">
                <div class="text-sm text-slate-300 leading-relaxed">
                  <strong v-if="tip.includes(':')" class="text-amber-400 block mb-4 uppercase tracking-widest text-xs font-black flex items-center gap-2">
                    <svg v-if="i===0" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ tip.split(':')[0] }}
                  </strong>
                  <span class="block text-slate-400">
                    {{ tip.includes(':') ? tip.substring(tip.indexOf(':') + 1).trim() : tip }}
                  </span>
                </div>
              </div>
            </div>
            
          </div>

        </div>
      </transition>

    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.input-style {
  @apply w-full bg-white border-2 border-slate-100 rounded-3xl p-5 text-sm font-bold focus:border-emerald-500 focus:bg-white outline-none transition-all placeholder:opacity-30 disabled:opacity-30 shadow-sm;
}
.label-style {
  @apply text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-6 mb-3 block;
}
.btn-save {
  @apply px-10 py-5 bg-slate-900 text-white rounded-[2rem] font-black text-xs uppercase tracking-widest shadow-2xl transition-all hover:scale-105 active:scale-95;
}

.fade-enter-active, .fade-leave-active { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.fade-enter-from { opacity: 0; transform: translateX(20px); }
.fade-leave-to { opacity: 0; transform: translateX(-20px); }
.fade-up-enter-active { transition: all 1s cubic-bezier(0.16, 1, 0.3, 1); }
.fade-up-enter-from { opacity: 0; transform: translateY(100px); }
</style>