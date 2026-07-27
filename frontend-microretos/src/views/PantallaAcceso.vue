<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import api from '../api.js'

const route  = useRoute()
const router = useRouter()

const cargando = ref(true)
const error     = ref('')
const proyectoTitulo = ref('')
const equipos   = ref([])
const canvases  = ref({})

const baseUrl = import.meta.env.VITE_APP_BASE_URL || window.location.origin
const baseUrlSinProtocolo = baseUrl.replace(/^https?:\/\//, '')

function buildJoinUrl(token) {
  return `${baseUrl}/proyecto/equipo/${token}`
}

function setCanvasRef(el, id) {
  if (el) canvases.value[id] = el
}

async function pintarQRs() {
  await nextTick()
  for (const equipo of equipos.value) {
    const canvas = canvases.value[equipo.id]
    if (!canvas) continue
    await QRCode.toCanvas(canvas, buildJoinUrl(equipo.token), {
      width: 180,
      margin: 2,
      color: { dark: '#1F2937', light: '#FFFFFF' },
    })
  }
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const res = await api.get(`/startup/proyectos/${route.params.uuid}/pantalla-acceso`)
    proyectoTitulo.value = res.data.proyecto_titulo
    equipos.value = res.data.equipos
  } catch (e) {
    error.value = e.response?.status === 404
      ? 'Proyecto no encontrado o sin equipos creados todavía.'
      : 'Error al cargar la pantalla de acceso.'
  } finally {
    cargando.value = false
  }
  if (!error.value) await pintarQRs()
}

onMounted(cargar)
</script>

<template>
  <div class="min-h-screen bg-[#0C1220] text-white px-6 py-8">
    <div class="max-w-6xl mx-auto">

      <div class="flex items-center justify-between mb-8">
        <div>
          <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#00A859] mb-1">Pantalla de acceso — alumnado</p>
          <h1 class="text-2xl font-black">{{ proyectoTitulo || 'Cargando...' }}</h1>
        </div>
        <button @click="router.back()"
                class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-all text-xs font-black uppercase tracking-wider">
          ← Volver
        </button>
      </div>

      <div v-if="cargando" class="flex items-center justify-center py-24">
        <div class="w-8 h-8 border-2 border-[#00A859] border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error" class="rounded-3xl bg-red-950/40 border border-red-500/30 p-8 text-center text-red-300 text-sm font-semibold">
        {{ error }}
      </div>

      <template v-else>
        <p class="text-sm text-gray-400 mb-6">
          Cada alumno/a escanea el código QR de su equipo con el móvil, o entra en
          <strong class="text-white">{{ baseUrlSinProtocolo }}/unirse</strong>
          y escribe el código de su equipo.
        </p>

        <div v-if="!equipos.length" class="rounded-3xl bg-white/5 border border-white/10 p-10 text-center text-gray-400">
          No hay equipos creados todavía para este proyecto.
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <div v-for="equipo in equipos" :key="equipo.id"
               class="bg-white rounded-3xl p-5 flex flex-col items-center text-center gap-3">
            <p class="text-sm font-black text-[#1F2937]">{{ equipo.nombre }}</p>
            <canvas :ref="el => setCanvasRef(el, equipo.id)" class="rounded-xl"></canvas>
            <p class="text-2xl font-black tracking-[0.2em] text-[#00A859]">{{ equipo.codigo_acceso }}</p>
          </div>
        </div>
      </template>

    </div>
  </div>
</template>
