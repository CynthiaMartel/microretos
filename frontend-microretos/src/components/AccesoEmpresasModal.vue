<script setup>
import { ref, onMounted } from 'vue'
import api from '../api.js'

const emit = defineEmits(['desbloqueado', 'cerrar'])

const passwordInput = ref('')
const verificando   = ref(false)
const errorAcceso   = ref('')
const exitoso       = ref(false)
const visible       = ref(false)

onMounted(() => setTimeout(() => { visible.value = true }, 60))

async function verificarAcceso() {
  if (!passwordInput.value || verificando.value) return
  verificando.value = true
  errorAcceso.value = ''
  try {
    await api.post('/empresas/verificar-acceso', { password: passwordInput.value }, { skipAuthRedirect: true })
    exitoso.value = true
    sessionStorage.setItem('empresas_module_unlocked', 'true')
    setTimeout(() => emit('desbloqueado'), 900)
  } catch {
    errorAcceso.value = 'Contraseña incorrecta. Inténtalo de nuevo.'
    passwordInput.value = ''
  } finally {
    verificando.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
    :class="visible ? 'opacity-100' : 'opacity-0'"
    style="transition: opacity 0.4s ease"
    @click.self="!exitoso && $emit('cerrar')"
  >
    <div class="relative bg-[#1a2332] border border-white/10 rounded-[2rem]
                shadow-2xl max-w-sm w-full p-8 text-white">

      <!-- Icono (cambia al hacer éxito) -->
      <div class="flex justify-center mb-6">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center transition-all duration-500"
             :class="exitoso
               ? 'bg-[#00A859]/20 border border-[#00A859]/40'
               : 'bg-blue-500/10 border border-blue-500/20'">
          <Transition name="icon-swap" mode="out-in">
            <svg v-if="exitoso" key="check" class="w-8 h-8 text-[#00A859]"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            <svg v-else key="lock" class="w-8 h-8 text-blue-400"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="1.5"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
          </Transition>
        </div>
      </div>

      <h1 class="text-2xl font-black text-center tracking-tight mb-1">
        Módulo <span class="text-blue-400">Empresas</span>
      </h1>

      <!-- Estado éxito -->
      <Transition name="fade">
        <div v-if="exitoso" class="text-center mt-6 space-y-1">
          <p class="text-[#00A859] font-black text-sm uppercase tracking-widest">Acceso concedido</p>
          <p class="text-white/40 text-xs">Cargando directorio de empresas...</p>
        </div>
      </Transition>

      <!-- Formulario -->
      <Transition name="fade">
        <div v-if="!exitoso">
          <p class="text-white/40 text-sm text-center mt-2 mb-8">
            Este módulo requiere contraseña especial para proteger el contacto directo con empresas.
          </p>

          <form @submit.prevent="verificarAcceso" class="space-y-4">
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">
                Contraseña de acceso
              </label>
              <input
                v-model="passwordInput"
                type="password"
                placeholder="Introduce la contraseña..."
                :disabled="verificando"
                :class="[
                  'w-full bg-white/5 border rounded-xl px-4 py-3 text-sm text-white placeholder-white/20',
                  'focus:outline-none transition-colors',
                  errorAcceso
                    ? 'border-red-500/60 focus:border-red-400/70'
                    : 'border-white/10 focus:border-blue-400/50'
                ]"
              />
            </div>

            <!-- Error -->
            <Transition name="fade">
              <div v-if="errorAcceso"
                   class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl
                          bg-red-500/10 border border-red-500/25">
                <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-red-400 text-xs font-semibold">{{ errorAcceso }}</p>
              </div>
            </Transition>

            <button
              type="submit"
              :disabled="verificando || !passwordInput"
              class="w-full py-3 rounded-full bg-[#00A859] text-white font-black text-xs
                     uppercase tracking-widest transition-all
                     hover:bg-[#009950] disabled:opacity-40 disabled:cursor-not-allowed
                     flex items-center justify-center gap-2"
            >
              <svg v-if="verificando" class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/>
              </svg>
              {{ verificando ? 'Analizando credenciales...' : 'Desbloquear módulo' }}
            </button>

            <button
              type="button"
              @click="$emit('cerrar')"
              class="w-full py-2.5 rounded-full bg-transparent text-white/40 font-bold text-xs
                     uppercase tracking-widest transition-all border border-white/10
                     hover:text-white/70 hover:border-white/20 flex items-center justify-center gap-2"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
              Volver
            </button>
          </form>
        </div>
      </Transition>

      <p class="text-center mt-6 text-white/20 text-[10px]">
        Dua<span class="text-[#00A859]">Lab</span> · Módulo protegido
      </p>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

.icon-swap-enter-active, .icon-swap-leave-active { transition: all 0.25s ease; }
.icon-swap-enter-from  { opacity: 0; transform: scale(0.5); }
.icon-swap-leave-to    { opacity: 0; transform: scale(1.3); }
</style>
