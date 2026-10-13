<!-- Ruta: /noticias/:tipo — listado completo, accedido desde las secciones de noticias de /panel-docente -->
<script setup>
import CabeceraSeccion from '../components/CabeceraSeccion.vue'
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { noticiasDualab, novedadesPlataforma } from '../data/noticiasMock.js'

const route  = useRoute()
const router = useRouter()
const isLoaded = ref(false)

const TIPOS = {
  dualab: {
    items: noticiasDualab,
    titulo: 'Noticias DuaLab',
    descripcion: 'Convocatorias, formación y comunidad del ecosistema DuaLab.',
  },
  plataforma: {
    items: novedadesPlataforma,
    titulo: 'Novedades plataforma DuaLab',
    descripcion: 'Nuevas funcionalidades, guías y recursos de la plataforma.',
  },
}

const config = computed(() => TIPOS[route.params.tipo] ?? TIPOS.dualab)

onMounted(() => { setTimeout(() => { isLoaded.value = true }, 80) })
</script>

<template>
  <div class="min-h-screen p-4 md:p-10 font-sans text-[#1F2937] pt-16 md:pt-16">

    <div class="relative z-10 max-w-6xl mx-auto"
         :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'"
         style="transition: opacity 0.4s ease, transform 0.4s ease">

      <CabeceraSeccion :titulo="config.titulo" :volver="{ name: 'inicio-docente' }"
                       :subtitulo="config.descripcion" class="mb-8" />

      <!-- Estado vacío -->
      <div v-if="!config.items.length" class="text-center py-16">
        <p class="text-gray-400 text-sm font-medium">Todavía no hay noticias en esta sección.</p>
      </div>

      <!-- Grid de tarjetas -->
      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="item in config.items" :key="item.id"
             class="rounded-2xl overflow-hidden shadow-sm border border-white/50 bg-white cursor-default">
          <div class="relative h-44 overflow-hidden">
            <img :src="item.imagen" :alt="item.alt" class="w-full h-full object-cover" loading="lazy" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent
                        flex flex-col justify-end p-3">
              <span class="text-[8px] font-black uppercase tracking-widest text-white/75 mb-1">{{ item.categoria }}</span>
              <p class="text-white font-black text-sm leading-snug drop-shadow-sm">{{ item.titulo }}</p>
            </div>
          </div>
          <div class="bg-white px-3 py-2.5">
            <p class="text-[11px] text-gray-400 font-medium">{{ item.subtitulo }}</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
