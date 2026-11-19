<!-- Contactos → «Empresas y centros»: elegir qué consultar, con el resumen de cada uno (empresas reales y
     ficticias, centros). Las dos vistas son de solo lectura (EmpresasCentral, CentrosCentral); editar es de
     Base de datos. Tarjetas de hub: rectángulo curvo, no píldora (GUIA_DISENO). -->
<script setup>
import { computed, onMounted, ref } from 'vue'
import CabeceraSeccion from '../../src/components/CabeceraSeccion.vue'
import { getEmpresasCentral, getCentrosCentral } from '../../src/services/consultaCentralService.js'
import { ICONOS } from '../espacios.js'

// null = cargando · undefined = no se pudo cargar
const cifras = ref({ empresas: null, reales: null, ficticias: null, centros: null })

onMounted(async () => {
  const total = (p) => p.then(r => r.data.meta?.total ?? 0).catch(() => undefined)
  const [empresas, reales, ficticias, centros] = await Promise.all([
    total(getEmpresasCentral({ per_page: 1 })), total(getEmpresasCentral({ per_page: 1, tipo: 'real' })),
    total(getEmpresasCentral({ per_page: 1, tipo: 'ficticia' })), total(getCentrosCentral({ per_page: 1 })),
  ])
  cifras.value = { empresas, reales, ficticias, centros }
})

const valor = (n) => (n === null ? '…' : n === undefined ? '—' : n)
const OPCIONES = computed(() => [
  {
    ruta: { name: 'empresas-central' }, titulo: 'Empresas', icono: ICONOS.empresas, total: cifras.value.empresas,
    texto: 'Cada empresa con los centros a los que está asociada, sus familias y su contacto.',
    detalle: [{ t: 'reales', v: cifras.value.reales }, { t: 'ficticias', v: cifras.value.ficticias }],
  },
  {
    ruta: { name: 'centros-central' }, titulo: 'Centros', icono: ICONOS.centros, total: cifras.value.centros,
    texto: 'Cada centro educativo con sus ciclos por familia, sus empresas y su profesorado.',
    detalle: [],
  },
])
</script>

<template>
  <div class="relative min-h-screen pt-16 font-sans text-azul-noche">
    <div class="relative z-10 mx-auto max-w-[90rem] space-y-5 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
      <CabeceraSeccion antetitulo="Contactos" titulo="Empresas y" destacado="centros" color="text-rol"
                       :volver="{ name: 'red-inicio' }" volver-label="Red DuaLab"
                       subtitulo="Consulta las empresas y los centros de DuaLab. Solo lectura: los cambios se hacen en Base de datos." />

      <ul class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <li v-for="o in OPCIONES" :key="o.titulo">
          <RouterLink :to="o.ruta"
                      class="group flex h-full flex-col rounded-2xl border border-borde bg-white p-5 shadow-sm transition hover:border-rol/40 hover:shadow-md sm:p-6">
            <span class="flex items-start justify-between gap-4">
              <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rol/10 text-rol-fuerte" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path :d="o.icono" /></svg>
              </span>
              <span class="text-right">
                <span class="block font-heading text-4xl font-black text-rol-fuerte sm:text-5xl" :aria-label="`${valor(o.total)} ${o.titulo.toLowerCase()}`">{{ valor(o.total) }}</span>
                <span class="block text-xs font-semibold uppercase tracking-wider text-texto-secundario">en total</span>
              </span>
            </span>
            <span class="mt-4 font-heading text-xl font-bold">{{ o.titulo }}</span>
            <span class="mt-1 text-sm text-texto-secundario">{{ o.texto }}</span>
            <span v-if="o.detalle.length" class="mt-3 flex flex-wrap gap-1.5">
              <span v-for="d in o.detalle" :key="d.t" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-bold text-texto-secundario">{{ valor(d.v) }} {{ d.t }}</span>
            </span>
            <span class="mt-auto pt-4 text-sm font-bold text-rol-fuerte">Ver {{ o.titulo.toLowerCase() }} →</span>
          </RouterLink>
        </li>
      </ul>
    </div>
  </div>
</template>
