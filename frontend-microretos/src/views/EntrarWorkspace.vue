<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'

const router   = useRouter()
const codigo   = ref('')
const cargando = ref(false)
const error    = ref('')
const isLoaded = ref(false)

const equipoGuardado = ref(null)

onMounted(() => {
  setTimeout(() => { isLoaded.value = true }, 60)

  const tokenGuardado = localStorage.getItem('dualab_equipo_token')
  if (tokenGuardado) {
    equipoGuardado.value = {
      token:  tokenGuardado,
      nombre: localStorage.getItem('dualab_equipo_nombre') || 'mi equipo',
      titulo: localStorage.getItem('dualab_proyecto_titulo') || '',
    }
  }
})

function limpiarGuardado() {
  localStorage.removeItem('dualab_equipo_token')
  localStorage.removeItem('dualab_equipo_nombre')
  localStorage.removeItem('dualab_proyecto_titulo')
  equipoGuardado.value = null
}

function onInput(e) {
  let val = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 6)
  if (val.length > 3) val = val.slice(0, 3) + '-' + val.slice(3)
  codigo.value = val
  error.value  = ''
}

const codigoValido = computed(() => /^[A-Z]{3}-\d{3}$/.test(codigo.value))

async function entrar() {
  if (!codigoValido.value) return
  cargando.value = true
  error.value = ''
  try {
    const res = await api.get(`/equipo/unirse/${codigo.value}`)
    localStorage.setItem('dualab_equipo_token', res.data.token)
    localStorage.setItem('dualab_equipo_nombre', res.data.nombre_equipo)
    localStorage.setItem('dualab_proyecto_titulo', res.data.proyecto_titulo)
    router.push({ name: 'equipo-workspace', params: { token: res.data.token } })
  } catch (e) {
    error.value = e.response?.data?.error || 'No se encontró ese código. Comprueba que lo has escrito bien.'
  } finally {
    cargando.value = false
  }
}

function continuarConEquipoGuardado() {
  router.push({ name: 'equipo-workspace', params: { token: equipoGuardado.value.token } })
}
</script>

<template>
  <div class="min-h-screen bg-[#0f1923] flex flex-col items-center justify-center px-4 py-8 font-sans"
       :class="isLoaded ? 'opacity-100' : 'opacity-0'"
       style="transition: opacity 0.3s ease">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                bg-[#00A859] opacity-[0.07] blur-[120px] rounded-full pointer-events-none" />

    <div class="relative z-10 w-full max-w-sm">

      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-3xl
                    bg-[#00A859]/15 border border-[#00A859]/30 mb-4">
          <svg class="w-8 h-8 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-[#00A859] mb-1">DuaLab</p>
        <h1 class="text-2xl font-black text-white leading-tight">Tu flujo de trabajo</h1>
        <p class="text-sm text-white/50 mt-2">Mete tu código para ver tu flujo de trabajo</p>
      </div>

      <!-- Acceso rápido si ya tiene equipo guardado en este dispositivo -->
      <div v-if="equipoGuardado" class="mb-5 bg-[#00A859]/10 border border-[#00A859]/25 rounded-3xl p-4">
        <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-2">Sesión guardada</p>
        <p class="text-sm font-bold text-white leading-snug mb-0.5">{{ equipoGuardado.nombre }}</p>
        <p class="text-xs text-white/40 mb-3 truncate">{{ equipoGuardado.titulo }}</p>
        <div class="flex gap-2">
          <button @click="continuarConEquipoGuardado"
                  class="flex-1 py-2.5 rounded-xl bg-[#00A859] text-white text-xs font-black uppercase tracking-wider">
            Continuar
          </button>
          <button @click="limpiarGuardado"
                  class="px-3 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white/40 text-xs font-black uppercase tracking-wider">
            Otro código
          </button>
        </div>
      </div>

      <template v-else>
        <input :value="codigo" @input="onInput" type="text" placeholder="XXX-000" maxlength="7"
               autocapitalize="characters"
               class="w-full text-center text-2xl font-black tracking-[0.3em] text-white
                      bg-white/5 border border-white/10 rounded-2xl px-4 py-4 mb-3
                      focus:outline-none focus:border-[#00A859]/60 placeholder-white/20"
               @keydown.enter="entrar" />

        <p v-if="error" class="text-xs text-red-400 font-semibold text-center mb-3">{{ error }}</p>

        <button @click="entrar" :disabled="!codigoValido || cargando"
                :class="['w-full py-3.5 rounded-2xl text-sm font-black uppercase tracking-widest transition-all',
                         codigoValido ? 'bg-[#00A859] text-white hover:bg-[#00A859]/90' : 'bg-white/5 text-white/20 cursor-not-allowed']">
          {{ cargando ? 'Buscando…' : 'Ver mi flujo de trabajo →' }}
        </button>

        <p class="text-xs text-white/30 text-center mt-4">
          ¿Todavía no tienes equipo?
          <button @click="router.push({ name: 'unirse-equipo' })" class="text-[#00A859] font-bold hover:underline">Únete aquí</button>
        </p>
      </template>

    </div>
  </div>
</template>
