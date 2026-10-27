<script setup>
import { computed, onMounted, ref } from 'vue'
import { _reg } from '../config/app.js'
import { _bt, _bv } from '../utils/tokens.js'
import { useCredits } from '../composables/useCredits.js'
import ModalTaller from './ModalTaller.vue'

const _x = (s) =>
  decodeURIComponent(
    atob(s).split('').map(c => '%' + c.charCodeAt(0).toString(16).padStart(2, '0')).join('')
  )

const _app = computed(() => _x(_reg._i))
const _yr  = computed(() => _x(_reg._j))

const autores = computed(() => [
  { nombre: _x(_reg._a), rol: _x(_reg._e), formacion: _x(_reg._k), icono: 'code', actual: true },
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
</script>

<template>
  <template v-if="verified">

    <!-- ── Footer strip — oscuro ─────────────────────────────────────────────── -->
    <!-- Alto fijo de 2.5rem: el SidePanel acaba justo encima (h-[calc(100dvh-6.5rem)]) y
         AccesoLayout lo descuenta en escritorio -->
    <footer class="fixed bottom-0 left-0 right-0 z-40 h-10
                   bg-[#0f1720]/90 backdrop-blur-sm border-t border-white/8
                   flex items-center justify-between gap-3
                   px-3 sm:px-5 text-[11px] text-white/35 select-none">
      <span class="min-w-0 truncate">
        <span class="sm:hidden">Nacida de las prácticas de empresa {{ _yr }}</span>
        <span class="hidden sm:inline">Aplicación nacida de las prácticas de empresa del curso {{ _yr }}.</span>
      </span>
      <!-- Acerca de: discreto, a juego con el texto del pie -->
      <button @click="abrir"
        class="foot-btn shrink-0 text-white/45 hover:text-white/80 hover:bg-white/5">
        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
        </svg>
        Acerca de
      </button>
    </footer>

    <!-- ── Modal ──────────────────────────────────────────────────────────────
         Misma estructura que los modales de bienvenida y «Proyecto en propuesta»:
         ModalTaller con la barra de colores del logo, cabecera centrada y pie. -->
    <ModalTaller :abierto="abierto" titulo="Equipo de desarrollo" marca
                 ancho="w-[min(32rem,calc(100vw-2rem))]" @cerrar="cerrar">
      <template #cabecera="{ idTitulo }">
        <div class="pl-10 text-center">
          <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-xl bg-rol/15 text-rol-fuerte sm:h-16 sm:w-16 sm:rounded-2xl" aria-hidden="true">
            <svg class="h-6 w-6 sm:h-8 sm:w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
            </svg>
          </span>
          <p class="mt-2 sm:mt-3">
            <span class="inline-flex items-center gap-2 rounded-full border border-rol/30 bg-rol/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-rol-fuerte">
              <span class="h-2 w-2 rounded-full bg-rol" aria-hidden="true" />
              {{ _app }} · {{ _yr }}
            </span>
          </p>
          <h2 :id="idTitulo" class="mt-2 font-heading text-xl font-bold text-azul-noche sm:mt-3 sm:text-3xl">
            Equipo de <span class="text-rol-fuerte">desarrollo</span>
          </h2>
          <p class="mx-auto mt-2 max-w-md text-sm text-texto-secundario sm:text-base">
            Aplicación nacida de las prácticas de empresa del curso {{ _yr }}.
          </p>
        </div>
      </template>

      <ul class="space-y-3">
        <li v-for="(a, i) in autores" :key="i"
            class="flex items-start gap-3 rounded-2xl border p-4 sm:gap-4"
            :class="a.icono === 'shield' ? 'border-administraciones/20 bg-administraciones/5' : 'border-rol/20 bg-rol/5'">
          <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                :class="a.icono === 'shield' ? 'bg-administraciones/15 text-administraciones-dark' : 'bg-rol/15 text-rol-fuerte'"
                aria-hidden="true">
            <svg v-if="a.icono === 'code'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
            </svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955
                   11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824
                   10.29 9 11.622 5.176-1.332 9-6.03 9-11.622
                   0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </span>
          <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span class="break-words text-sm font-bold text-azul-noche sm:text-base">{{ a.nombre }}</span>
              <!-- Fuera de la firma HMAC: solo marca visual de quien sigue en el equipo -->
              <span v-if="a.actual" class="inline-flex items-center gap-1.5 rounded-full border border-empresas/30 bg-empresas/10 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-empresas-dark">
                <span class="h-1.5 w-1.5 rounded-full bg-empresas" aria-hidden="true" />
                Equipo actual
              </span>
            </p>
            <p class="mt-0.5 break-words text-sm font-semibold"
               :class="a.icono === 'shield' ? 'text-administraciones-dark' : 'text-rol-fuerte'">
              {{ a.rol }}
            </p>
            <p class="mt-1 break-words text-xs text-texto-secundario">{{ a.formacion }}</p>
          </div>
        </li>
      </ul>

      <template #pie>
        <div class="flex justify-center">
          <button type="button" @click="cerrar"
                  class="inline-flex h-11 items-center justify-center rounded-full bg-rol-fuerte px-8 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rol-fuerte/90">
            Cerrar
          </button>
        </div>
      </template>
    </ModalTaller>

  </template>
</template>

<style scoped>
/* Botón del pie: 36px de alto (objetivo táctil mínimo) dentro de sus 2.5rem */
.foot-btn {
  display: inline-flex; align-items: center; gap: 0.35rem;
  height: 2.25rem; padding: 0 0.6rem; border-radius: 9999px;
  font-size: 11px; font-weight: 600; letter-spacing: 0.02em;
  white-space: nowrap; transition: background-color 150ms ease, color 150ms ease;
}
</style>
