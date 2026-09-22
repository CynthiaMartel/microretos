<script setup>
import { computed, onMounted, ref } from 'vue'
import { _reg } from '../config/app.js'
import { _bt, _bv } from '../utils/tokens.js'
import { useCredits } from '../composables/useCredits.js'

const _x = (s) =>
  decodeURIComponent(
    atob(s).split('').map(c => '%' + c.charCodeAt(0).toString(16).padStart(2, '0')).join('')
  )

const _app = computed(() => _x(_reg._i))
const _yr  = computed(() => _x(_reg._j))

const autores = computed(() => [
  { nombre: _x(_reg._a), rol: _x(_reg._e), formacion: _x(_reg._g), icono: 'code'   },
  { nombre: _x(_reg._b), rol: _x(_reg._e), formacion: _x(_reg._g), icono: 'code'   },
  { nombre: _x(_reg._c), rol: _x(_reg._f), formacion: _x(_reg._h), icono: 'shield' },
])

const verified = ref(false)
const { creditosAbierto: abierto, abrirCreditos: abrir, cerrarCreditos: cerrar } = useCredits()

const _xv = async () => {
  try {
    const _kh = _bt.join('')
    const _kb = new Uint8Array(_kh.match(/.{2}/g).map(b => parseInt(b, 16)))
    const _ck = await crypto.subtle.importKey(
      'raw', _kb, { name: 'HMAC', hash: 'SHA-256' }, false, ['verify']
    )
    const _pl = JSON.stringify(
      autores.value.map(a => ({ n: a.nombre, r: a.rol, f: a.formacion }))
    )
    const _sb = Uint8Array.from(atob(_bv), c => c.charCodeAt(0))
    const _ok = await crypto.subtle.verify('HMAC', _ck, _sb, new TextEncoder().encode(_pl))
    verified.value = _ok
  } catch {
    verified.value = false
  }
}

onMounted(_xv)

const onOverlay = (e) => { if (e.target === e.currentTarget) cerrar() }
</script>

<template>
  <template v-if="verified">

    <!-- ── Footer strip — oscuro ─────────────────────────────────────────────── -->
    <footer class="fixed bottom-0 left-0 right-0 z-40
                   bg-[#0f1720]/90 backdrop-blur-sm border-t border-white/8
                   flex items-center justify-between
                   px-5 py-2 text-[11px] text-white/35 select-none">
      <span>Web app desarrollada íntegramente por alumnado de prácticas de empresa</span>
      <button @click="abrir"
        class="text-primary-700/70 hover:text-primary-700 transition-colors font-semibold tracking-wide">
        Acerca de
      </button>
    </footer>

    <!-- ── Modal ────────────────────────────────────────────────────────────── -->
    <Transition name="credit-fade">
      <div v-if="abierto"
        class="fixed inset-0 z-[9998] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        @click="onOverlay">

        <div class="bg-white border border-gray-100 rounded-[2rem] shadow-2xl
                    w-full max-w-lg overflow-hidden">

          <!-- Cabecera -->
          <div class="bg-[#F0FBF4] border-b border-[#BBE8D0] px-8 py-6 flex items-start justify-between">
            <div>
              <p class="text-primary-700 text-xs font-bold uppercase tracking-widest mb-1">{{ _app }}</p>
              <h2 class="text-2xl font-black tracking-tight text-[#1F2937]">Equipo de desarrollo</h2>
              <p class="text-gray-500 text-xs mt-1">Aplicación desarrollada íntegramente por alumnado de prácticas de empresa</p>
            </div>
            <button @click="cerrar" class="text-gray-300 hover:text-gray-500 transition-colors mt-1 p-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Autores -->
          <div class="px-8 py-6 space-y-3 bg-white">
            <div v-for="(a, i) in autores" :key="i"
              class="flex items-start gap-4 p-4 rounded-2xl bg-[#F8FAFC] border border-gray-100
                     hover:border-[#BBE8D0] hover:bg-[#F0FBF4] transition-all">

              <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center"
                :class="a.icono === 'shield'
                  ? 'bg-violet-50 border border-violet-200'
                  : 'bg-[#F0FBF4] border border-[#BBE8D0]'">
                <svg v-if="a.icono === 'code'" class="w-5 h-5 text-primary-700"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                <svg v-else class="w-5 h-5 text-violet-500"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955
                       11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824
                       10.29 9 11.622 5.176-1.332 9-6.03 9-11.622
                       0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
              </div>

              <div class="min-w-0">
                <p class="font-bold text-sm text-[#1F2937]">{{ a.nombre }}</p>
                <p class="text-[11px] mt-0.5 leading-relaxed"
                  :class="a.icono === 'shield' ? 'text-violet-600' : 'text-primary-700'">
                  {{ a.rol }}
                </p>
                <p class="text-[10px] text-gray-400 mt-1">{{ a.formacion }}</p>
              </div>
            </div>
          </div>

          <!-- Pie -->
          <div class="bg-gray-50 border-t border-gray-100 px-8 py-4 flex items-center justify-between">
            <span class="text-[10px] text-gray-400">{{ _yr }}</span>
            <span class="text-[10px] text-gray-400">Prácticas de empresa · {{ _yr }}</span>
          </div>
        </div>
      </div>
    </Transition>

  </template>
</template>

<style scoped>
.credit-fade-enter-active,
.credit-fade-leave-active { transition: opacity 0.2s ease; }
.credit-fade-enter-from,
.credit-fade-leave-to     { opacity: 0; }
</style>
