<!-- Aviso del panel docente (/panel-docente, InicioDocente) cuando aún no ha indicado los módulos
     que imparte — docentes con centro y admins de centro con proyectos (`imparte`, del backend)
     (authStore.tieneModulos, de /perfil y del login). Sin ellos no aparece como candidato a
     colaborar en encuentros de sus compañeros.

     - Modal con el editor (PanelModulosDocente sin ficha) la primera vez; espera a que se cierre
       la bienvenida del login para no apilar dos modales.
     - «Recordármelo más tarde» lo aplaza 7 días para ese usuario (preferencia de interfaz en
       localStorage, con su id en la clave: en un ordenador compartido no afecta a otros).
     - Mientras falten, una franja en el panel con acceso directo al editor. -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useUIState } from '../composables/useUIState.js'
import Modal from './Modal.vue'
import PanelModulosDocente from './PanelModulosDocente.vue'

const authStore = useAuthStore()
const { showWelcome } = useUIState()

const aplazarKey   = () => `aviso-modulos-aplazado-hasta:${authStore.userId}`
const APLAZAR_DIAS = 7

const faltan  = computed(() => authStore.imparte && !authStore.tieneModulos)
const abierto = ref(false)

function aplazado() {
  try { return Number(localStorage.getItem(aplazarKey()) || 0) > Date.now() } catch { return false }
}
function aplazar() {
  try { localStorage.setItem(aplazarKey(), String(Date.now() + APLAZAR_DIAS * 864e5)) } catch { /* sin almacenamiento */ }
  abierto.value = false
}

function abrirSiToca() {
  if (faltan.value && !showWelcome.value && !aplazado()) abierto.value = true
}
// El login navega al panel y justo después lanza la bienvenida: se espera un instante y, si
// la bienvenida aparece con este abierto, se cierra (sin aplazar) y se reabre al terminar ella
onMounted(() => setTimeout(abrirSiToca, 800))
watch(showWelcome, (visible) => {
  if (visible) abierto.value = false
  else abrirSiToca()
})
</script>

<template>
  <div v-if="faltan">
  <!-- Franja en el panel -->
  <div v-if="!abierto"
       class="mb-4 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="min-w-0 text-xs text-amber-800 leading-relaxed">
      <strong>Indica los módulos que impartes</strong> para que tus compañeros del centro puedan invitarte
      a colaborar en sus encuentros.
    </p>
    <div class="flex shrink-0 flex-wrap items-center gap-2">
      <RouterLink to="/mi-usuario"
                  class="inline-flex min-h-9 items-center rounded-full border border-amber-300 bg-white px-4 py-1.5
                         text-[11px] font-black text-amber-800 transition-colors hover:bg-amber-100">
        Ir a Mi usuario
      </RouterLink>
      <button type="button" @click="abierto = true"
              class="inline-flex min-h-9 items-center rounded-full bg-rol-fuerte px-4 py-1.5 text-[11px] font-black text-white
                     transition-colors hover:bg-rol-fuerte/90">
        Completar ahora
      </button>
    </div>
  </div>

  <Modal :visible="abierto" max-width="max-w-2xl" card-class="overflow-y-auto p-5 sm:p-8" @cerrar="aplazar">
    <div class="space-y-5">
      <div class="pr-10">
        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Tu cuenta</p>
        <h2 class="mt-1 text-lg font-black text-[#1F2937] sm:text-xl">¿Qué módulos impartes?</h2>
      </div>
      <PanelModulosDocente :ficha="false" @guardado="abierto = false" />
      <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-4">
        <RouterLink to="/mi-usuario" class="inline-flex min-h-9 items-center text-xs font-semibold text-gray-500 underline-offset-2 hover:underline"
                    @click="abierto = false">
          Ir a Mi usuario
        </RouterLink>
        <button type="button" @click="aplazar"
                class="inline-flex min-h-9 items-center rounded-full border border-gray-200 bg-white px-4 py-1.5
                       text-[11px] font-black text-gray-600 transition-colors hover:bg-gray-50">
          Recordármelo más tarde
        </button>
      </div>
    </div>
  </Modal>
  </div>
</template>
