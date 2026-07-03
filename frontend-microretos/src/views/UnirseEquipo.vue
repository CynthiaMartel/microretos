<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'

const router   = useRouter()
const codigo   = ref('')
const cargando = ref(false)
const error    = ref('')
// resultado puede ser:
//   { tipo: 'clase', proyecto_titulo, curso, equipos: [{id, nombre, token, miembros}] }
//   { tipo: 'equipo', token, nombre_equipo, proyecto_titulo, fase_actual }
const resultado = ref(null)
const isLoaded  = ref(false)

onMounted(() => {
  setTimeout(() => { isLoaded.value = true }, 60)

  // Si el alumno ya tiene un equipo guardado en localStorage, mostrar acceso rápido
  const tokenGuardado = localStorage.getItem('dualab_equipo_token')
  if (tokenGuardado) {
    equipoGuardado.value = {
      token:  tokenGuardado,
      nombre: localStorage.getItem('dualab_equipo_nombre') || 'mi equipo',
      titulo: localStorage.getItem('dualab_proyecto_titulo') || '',
    }
  }
})

// Acceso rápido si el alumno ya se unió antes
const equipoGuardado = ref(null)

function limpiarGuardado() {
  localStorage.removeItem('dualab_equipo_token')
  localStorage.removeItem('dualab_equipo_nombre')
  localStorage.removeItem('dualab_proyecto_titulo')
  equipoGuardado.value = null
}

// Auto-formatea el input: mayúsculas + añade guion tras 3ª letra
function onInput(e) {
  let val = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 6)
  if (val.length > 3) val = val.slice(0, 3) + '-' + val.slice(3)
  codigo.value = val
  error.value  = ''
  resultado.value = null
}

const codigoValido = computed(() => /^[A-Z]{3}-\d{3}$/.test(codigo.value))

async function unirse() {
  if (!codigoValido.value) return
  cargando.value  = true
  error.value     = ''
  resultado.value = null

  try {
    // Primero intentar código de clase (nuevo flujo: elige equipo)
    const res = await api.get(`/clase/${codigo.value}`)
    resultado.value = { tipo: 'clase', ...res.data }
  } catch (e) {
    if (e.response?.status === 404) {
      // Fallback: código de equipo directo (flujo anterior)
      try {
        const res2 = await api.get(`/equipo/unirse/${codigo.value}`)
        resultado.value = { tipo: 'equipo', ...res2.data }
      } catch (e2) {
        error.value = e2.response?.data?.error || 'No se encontró ese código. Comprueba que lo has escrito bien.'
      }
    } else {
      error.value = e.response?.data?.error || 'El proyecto no está activo todavía.'
    }
  } finally {
    cargando.value = false
  }
}

function accederAlWorkspace() {
  const { token, nombre_equipo, proyecto_titulo } = resultado.value
  localStorage.setItem('dualab_equipo_token', token)
  localStorage.setItem('dualab_equipo_nombre', nombre_equipo)
  localStorage.setItem('dualab_proyecto_titulo', proyecto_titulo)
  router.push({ name: 'equipo-workspace', params: { token } })
}

function seleccionarEquipo(equipo) {
  localStorage.setItem('dualab_equipo_token', equipo.token)
  localStorage.setItem('dualab_equipo_nombre', equipo.nombre)
  localStorage.setItem('dualab_proyecto_titulo', resultado.value.proyecto_titulo)
  router.push({ name: 'equipo-workspace', params: { token: equipo.token } })
}

function continuarConEquipoGuardado() {
  router.push({ name: 'equipo-workspace', params: { token: equipoGuardado.value.token } })
}
</script>

<template>
  <div class="min-h-screen bg-[#0f1923] flex flex-col items-center justify-center px-4 py-8 font-sans"
       :class="isLoaded ? 'opacity-100' : 'opacity-0'"
       style="transition: opacity 0.3s ease">

    <!-- Fondo decorativo -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                bg-[#00A859] opacity-[0.07] blur-[120px] rounded-full pointer-events-none" />

    <div class="relative z-10 w-full max-w-sm">

      <!-- Logo + título -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-3xl
                    bg-[#00A859]/15 border border-[#00A859]/30 mb-4">
          <svg class="w-8 h-8 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 2L2 7l10 5 10-5-10-5zm0 10l-10-5m10 5l10-5m-10 5v10"/>
          </svg>
        </div>
        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-[#00A859] mb-1">DuaLab</p>
        <h1 class="text-2xl font-black text-white leading-tight">Únete a tu proyecto</h1>
        <p class="text-sm text-white/50 mt-2">Introduce el código que te ha dado el docente</p>
      </div>

      <!-- Acceso rápido si ya tiene equipo guardado -->
      <div v-if="equipoGuardado && !resultado"
           class="mb-5 bg-[#00A859]/10 border border-[#00A859]/25 rounded-3xl p-4">
        <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-2">Sesión guardada</p>
        <p class="text-sm font-bold text-white leading-snug mb-0.5">{{ equipoGuardado.nombre }}</p>
        <p class="text-xs text-white/40 mb-3 truncate">{{ equipoGuardado.titulo }}</p>
        <div class="flex gap-2">
          <button @click="continuarConEquipoGuardado"
                  class="flex-1 py-2.5 rounded-2xl bg-[#00A859] text-white text-[11px] font-black
                         uppercase tracking-widest hover:bg-[#00A859]/90 transition-all">
            Continuar →
          </button>
          <button @click="limpiarGuardado"
                  class="px-3 py-2.5 rounded-2xl bg-white/5 border border-white/10
                         text-white/40 text-[10px] font-black uppercase tracking-wider
                         hover:text-white/60 transition-all">
            Otro
          </button>
        </div>
      </div>

      <!-- Tarjeta de entrada de código -->
      <div v-if="!resultado" class="bg-white/[0.05] border border-white/10 rounded-3xl p-6">

        <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-3">
          Código de equipo
        </label>

        <!-- Input principal — grande para móvil -->
        <div class="relative mb-4">
          <input
            :value="codigo"
            @input="onInput"
            type="text"
            maxlength="7"
            placeholder="ABC-123"
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
            :class="[
              'w-full text-center text-3xl font-black tracking-[0.25em] rounded-2xl px-4 py-5',
              'bg-white/8 border-2 text-white placeholder-white/20',
              'focus:outline-none transition-all duration-200',
              error
                ? 'border-red-500/60 focus:border-red-500'
                : codigoValido
                  ? 'border-[#00A859]/60 focus:border-[#00A859]'
                  : 'border-white/10 focus:border-white/30',
            ]"
            @keydown.enter="codigoValido && !cargando && unirse()"
          />
          <!-- Indicador de validez -->
          <div v-if="codigoValido"
               class="absolute right-4 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-[#00A859] animate-pulse"/>
        </div>

        <!-- Error -->
        <p v-if="error" class="text-red-400 text-xs font-semibold text-center mb-3 leading-snug">
          {{ error }}
        </p>

        <!-- Formato de ayuda -->
        <p class="text-white/25 text-[11px] text-center mb-4">
          Formato: 3 letras + guion + 3 números · Ej: <span class="font-black text-white/40">XKM-479</span>
        </p>

        <button
          @click="unirse"
          :disabled="!codigoValido || cargando"
          :class="[
            'w-full py-4 rounded-2xl text-sm font-black uppercase tracking-widest transition-all duration-200',
            codigoValido && !cargando
              ? 'bg-[#00A859] text-white hover:bg-[#00A859]/90 shadow-lg shadow-[#00A859]/20'
              : 'bg-white/5 text-white/20 cursor-not-allowed',
          ]">
          <span v-if="cargando" class="flex items-center justify-center gap-2">
            <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
              <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
            </svg>
            Buscando...
          </span>
          <span v-else>Entrar →</span>
        </button>
      </div>

      <!-- Resultado: código de clase → seleccionar equipo -->
      <Transition enter-active-class="transition-all duration-300 ease-out"
                  enter-from-class="opacity-0 scale-95"
                  leave-active-class="transition-all duration-200 ease-in"
                  leave-to-class="opacity-0 scale-95">
        <div v-if="resultado?.tipo === 'clase'" class="space-y-3">

          <div class="text-center mb-2">
            <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-1">¡Proyecto encontrado!</p>
            <p class="text-lg font-black text-white leading-snug">{{ resultado.proyecto_titulo }}</p>
            <p v-if="resultado.curso" class="text-xs text-white/40 mt-0.5">{{ resultado.curso }}</p>
          </div>

          <p class="text-[10px] font-black uppercase tracking-widest text-white/40 text-center">
            Selecciona tu equipo
          </p>

          <!-- Tarjetas de equipo -->
          <div class="space-y-2">
            <button
              v-for="equipo in resultado.equipos" :key="equipo.id"
              @click="seleccionarEquipo(equipo)"
              class="w-full text-left bg-white/[0.05] border border-white/10 rounded-2xl px-4 py-3.5
                     hover:border-[#00A859]/40 hover:bg-[#00A859]/8 transition-all group"
            >
              <div class="flex items-center justify-between gap-2">
                <div>
                  <p class="text-sm font-black text-white group-hover:text-[#00A859] transition-colors">
                    {{ equipo.nombre }}
                  </p>
                  <p v-if="equipo.miembros?.length" class="text-[11px] text-white/40 mt-0.5">
                    {{ equipo.miembros.join(' · ') }}
                  </p>
                  <p v-else class="text-[11px] text-white/25 mt-0.5 italic">Sin miembros asignados</p>
                </div>
                <svg class="w-4 h-4 text-white/20 group-hover:text-[#00A859] shrink-0 transition-colors"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </div>
            </button>
          </div>

          <button @click="resultado = null; codigo = ''"
                  class="w-full py-3 rounded-2xl bg-white/5 border border-white/10
                         text-white/40 text-[11px] font-black uppercase tracking-widest
                         hover:text-white/60 transition-all mt-1">
            Usar otro código
          </button>
        </div>
      </Transition>

      <!-- Resultado: código de equipo directo (acceso directo) -->
      <Transition enter-active-class="transition-all duration-300 ease-out"
                  enter-from-class="opacity-0 scale-95"
                  leave-active-class="transition-all duration-200 ease-in"
                  leave-to-class="opacity-0 scale-95">
        <div v-if="resultado?.tipo === 'equipo'"
             class="bg-white/[0.05] border border-[#00A859]/30 rounded-3xl p-6 text-center">
          <div class="w-16 h-16 rounded-3xl bg-[#00A859]/15 border border-[#00A859]/30
                      flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-[#00A859]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <p class="text-[9px] font-black uppercase tracking-widest text-[#00A859] mb-2">¡Equipo encontrado!</p>
          <p class="text-xl font-black text-white mb-1">{{ resultado.nombre_equipo }}</p>
          <p class="text-sm text-white/50 mb-6 leading-snug">{{ resultado.proyecto_titulo }}</p>
          <button @click="accederAlWorkspace"
                  class="w-full py-4 rounded-2xl bg-[#00A859] text-white text-sm font-black
                         uppercase tracking-widest hover:bg-[#00A859]/90 transition-all
                         shadow-lg shadow-[#00A859]/20">
            Acceder al workspace →
          </button>
          <button @click="resultado = null; codigo = ''"
                  class="mt-3 w-full py-3 rounded-2xl bg-white/5 border border-white/10
                         text-white/40 text-[11px] font-black uppercase tracking-widest
                         hover:text-white/60 transition-all">
            Usar otro código
          </button>
        </div>
      </Transition>

      <!-- Footer -->
      <p class="text-center text-white/20 text-[11px] mt-6">
        DuaLab Studio · Microproyectos
      </p>
    </div>
  </div>
</template>
