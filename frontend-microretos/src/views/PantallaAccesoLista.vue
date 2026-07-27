<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'

const router = useRouter()

const cargando   = ref(true)
const error      = ref('')
const encuentros = ref([])

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const res = await api.get('/encuentros')
    encuentros.value = res.data
  } catch (e) {
    error.value = 'Error al cargar tus encuentros.'
  } finally {
    cargando.value = false
  }
}

// Solo tiene sentido proyectar encuentros que ya tienen equipos creados (codigo_clase)
const encuentrosConEquipos = computed(() =>
  encuentros.value.filter(e => e.codigo_clase && e.microproyecto_uuid)
)

function abrirPantalla(encuentro) {
  router.push({ name: 'pantalla-acceso', params: { uuid: encuentro.microproyecto_uuid } })
}

onMounted(cargar)
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC]">
    <div class="sticky top-0 z-20 bg-white/90 backdrop-blur-sm border-b border-gray-100 px-4 py-3 flex items-center gap-3">
      <button @click="router.back()"
              class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <p class="text-xs font-black uppercase tracking-widest text-[#00A859]">Pantalla de acceso</p>
        <p class="text-sm font-bold text-[#121212]">Elige el encuentro que quieres proyectar</p>
      </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 py-6 space-y-3">

      <div v-if="cargando" class="flex items-center justify-center py-24">
        <div class="w-8 h-8 border-2 border-[#00A859] border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error" class="rounded-3xl bg-red-50 border border-red-200 p-8 text-center text-red-600 text-sm font-semibold">
        {{ error }}
      </div>

      <template v-else>
        <div v-if="!encuentrosConEquipos.length" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-10 text-center">
          <p class="text-gray-400 text-sm mb-4">Ningún encuentro tiene equipos creados todavía.</p>
          <button @click="router.push('/dashboard/encuentros')"
                  class="px-4 py-2 rounded-xl bg-[#00A859] text-white text-xs font-black uppercase tracking-wider">
            Ir a Crear/Ver Encuentros
          </button>
        </div>

        <button v-for="e in encuentrosConEquipos" :key="e.id"
                @click="abrirPantalla(e)"
                class="w-full bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4
                       flex items-center gap-4 hover:border-[#00A859]/40 transition-all text-left">
          <div class="flex-1 min-w-0">
            <p class="font-black text-[#121212]">{{ e.grupo || e.proyecto_titulo || 'Sin nombre' }}</p>
            <p class="text-xs text-gray-400">{{ e.ciclo_formativo }} · código {{ e.codigo_clase }}</p>
          </div>
          <span class="shrink-0 px-3 py-1.5 rounded-xl bg-violet-50 text-violet-700 text-[10px] font-black uppercase tracking-wider">
            Proyectar →
          </span>
        </button>
      </template>

    </div>
  </div>
</template>
