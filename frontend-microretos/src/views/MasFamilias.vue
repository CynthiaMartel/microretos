<!-- Ruta: /retos/familias (name: mas-familias). Resto de familias que quedan tras las visibles en la Biblioteca. -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../api.js';
import { iconoFamilia, colorFamilia } from '../utils/familiaIconos.js';

const router = useRouter();
const route = useRoute();

// Debe coincidir con BibliotecaMicroretos.vue para que "Ver más familias" muestre exactamente el resto
const FAMILIAS_DESTACADAS = ['Comercio y Marketing', 'Administración y Gestión', 'Informática y Comunicaciones'];
const FAMILIAS_VISIBLES_EXTRA = 8;

const cargando = ref(true);
const familias = ref([]);
const microretos = ref([]);
const centro = typeof route.query.centro === 'string' ? route.query.centro : '';

const conteoPorFamilia = computed(() => {
  const mapa = {};
  const d = centro
    ? microretos.value.filter(m => (m.centro_educativo || m.centro) === centro)
    : microretos.value;
  d.forEach(m => { if (m.familia) mapa[m.familia] = (mapa[m.familia] || 0) + 1; });
  return mapa;
});

const familiasOcultas = computed(() =>
  familias.value
    .filter(f => !FAMILIAS_DESTACADAS.includes(f.nombre))
    .slice(FAMILIAS_VISIBLES_EXTRA)
);

const cargarDatos = async () => {
  cargando.value = true;
  try {
    const [resFamilias, resMicroretos] = await Promise.all([
      api.get('/familias'),
      api.get('/microretos'),
    ]);
    familias.value = resFamilias.data.map(f =>
      typeof f === 'string' ? { nombre: f, imagen_url: null } : f
    );
    microretos.value = resMicroretos.data;
  } catch (error) {
    console.error('Error al cargar familias:', error);
  } finally {
    cargando.value = false;
  }
};

onMounted(cargarDatos);

const volver = () => router.push({ name: 'biblioteca' });

const seleccionarFamilia = (nombre) => {
  router.push({ name: 'biblioteca', query: { familia: nombre, ...(centro ? { centro } : {}) } });
};
</script>

<template>
  <div class="min-h-screen font-sans text-[#1F2937] pt-12 md:pt-12">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                bg-[#99CC33] opacity-5 blur-[120px] rounded-full pointer-events-none z-0" />

    <div class="relative z-10 max-w-6xl mx-auto px-4 py-8 md:px-8 md:py-12">

      <div class="mb-8">
        <button @click="volver"
                class="inline-flex items-center gap-2 px-5 py-2.5
                       bg-white border border-gray-200 rounded-full
                       text-xs font-black uppercase tracking-widest text-[#1F2937]
                       shadow-sm hover:border-[#00A859] hover:text-[#00A859]
                       transition-all active:scale-95">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                  d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
          Volver a la Biblioteca
        </button>
      </div>

      <h1 class="text-2xl md:text-4xl font-black text-[#1F2937] tracking-tight leading-tight mb-2">
        Más familias profesionales
      </h1>
      <p class="text-gray-400 text-sm font-bold uppercase tracking-widest mb-10">
        Selecciona una familia para explorar sus micro-retos
      </p>

      <div v-if="cargando" class="text-center py-20 text-gray-400 text-sm font-bold uppercase tracking-widest">
        Cargando familias…
      </div>

      <div v-else-if="familiasOcultas.length > 0"
           class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="familia in familiasOcultas"
          :key="familia.nombre"
          @click="seleccionarFamilia(familia.nombre)"
          @keydown.enter.prevent="seleccionarFamilia(familia.nombre)"
          @keydown.space.prevent="seleccionarFamilia(familia.nombre)"
          role="button"
          tabindex="0"
          class="group relative rounded-[1.5rem] overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 bg-white text-left focus:outline-none focus:ring-2 focus:ring-[#00A859]/40 cursor-pointer">

          <div :class="['relative h-44 overflow-hidden bg-gradient-to-br flex items-center justify-center', colorFamilia(familia.nombre).bg]">
            <svg :class="['w-16 h-16 group-hover:scale-110 transition-transform duration-300', colorFamilia(familia.nombre).icon]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path v-for="d in iconoFamilia(familia.nombre)" :key="d"
                stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="d" />
            </svg>
            <div
              v-if="conteoPorFamilia[familia.nombre]"
              class="absolute top-3 right-3 bg-[#00A859] text-white text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full shadow">
              {{ conteoPorFamilia[familia.nombre] }} reto{{ conteoPorFamilia[familia.nombre] !== 1 ? 's' : '' }}
            </div>
          </div>

          <div class="p-5">
            <h3 class="font-black text-[#1F2937] text-base leading-tight mb-3 group-hover:text-[#00A859] transition-colors line-clamp-2">
              {{ familia.nombre }}
            </h3>
            <div class="flex items-center gap-2 text-[#00A859] text-xs font-black uppercase tracking-widest">
              <span>Explorar</span>
              <svg class="w-3.5 h-3.5 transform group-hover:translate-x-1 transition-transform"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-20 bg-white rounded-[2rem] border border-dashed border-gray-300 shadow-sm">
        <h3 class="text-[#1F2937] font-black text-2xl mb-2">No hay más familias</h3>
        <p class="text-gray-500 text-sm max-w-md mx-auto">Todas las familias ya se muestran en la Biblioteca.</p>
      </div>

    </div>
  </div>
</template>
