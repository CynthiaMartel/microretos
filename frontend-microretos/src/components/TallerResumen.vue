<!-- Columna derecha del Taller de Ideas (diseños crear_proyecto.png / crear_encuentro.png),
     siempre visible y compacta:
     1. «Resumen del proyecto»: imagen grande (portada o ilustración) con lápiz, título,
        etiquetas REAL/FICTICIA + estado, pregunta, datos en 2 columnas, «Ver ficha completa»
        y, dentro, la nota «Antes de continuar» (consejo del paso o aviso en ámbar).
     2. «Sugerencias con IA»: la tarjeta entera es el botón de la IA del paso actual.
     3. «Recursos de apoyo»: los de /seccion/recursos (SECCIONES.recursos, una sola lista).
     ≥ lg: columna a la derecha; < lg: debajo del contenido del paso, a todo el ancho. -->
<script setup>
import MiniaturaReto from './MiniaturaReto.vue'
import { SECCIONES, ICONOS_NAV } from '../config/navegacion.js'
import { useComoFunciona } from '../composables/useComoFunciona.js'
import { useCredits } from '../composables/useCredits.js'

defineProps({
  titulo:      { type: String, default: '' },
  pregunta:    { type: String, default: '' },
  portadaUrl:  { type: String, default: '' },
  // Lista con iconos: [{ icono, valor, ancho? }] — solo filas con valor; `ancho` ocupa las 2 columnas.
  datos:       { type: Array, default: () => [] },
  // '' = sin empresa todavía · 'real' · 'ficticia' (empresa.es_simulada)
  tipoEmpresa: { type: String, default: '' },
  estado:      { type: Object, default: null },  // { label, clase }
  hayReto:     { type: Boolean, default: false },
  // Lápiz sobre la imagen (lleva al paso donde se sube la portada). Solo con proyecto guardado.
  puedeEditarImagen: { type: Boolean, default: false },
  // Recuadro «Antes de continuar»: { titulo, texto, tipo: 'info' | 'aviso' }
  antes:       { type: Object, default: null },
  ia:          { type: Object, default: null },  // { texto, boton, cargando, deshabilitado, motivo }
})
const emit = defineEmits(['ver-ficha', 'editar-imagen', 'sugerir'])

// Recursos de apoyo: mismos que la sección Recursos; los de modal se abren aquí mismo.
const recursos = SECCIONES.recursos.cards
const { abrirComoFunciona } = useComoFunciona()
const { abrirCreditos } = useCredits()
const ACCIONES = { comoFunciona: abrirComoFunciona, creditos: abrirCreditos }

// Iconos de la lista (heroicons outline, trazo 2) y su color.
const ICONOS = {
  empresa:  ['M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'text-empresas-dark'],
  familia:  ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'text-empresas-dark'],
  curso:    ['M12 14l9-5-9-5-9 5 9 5zm0 0v6m0-6l6.16-3.422A12.083 12.083 0 0118.82 17.5L12 21l-6.82-3.5a12.08 12.08 0 01.66-6.922L12 14z', 'text-empresas-dark'],
  calendario: ['M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'text-rol-fuerte'],
  nivel:    ['M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6m6 0h6m-6 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0h6m-6 0V5a2 2 0 012-2h2a2 2 0 012 2v14', 'text-alumnos-dark'],
  persona:  ['M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'text-rol-fuerte'],
  centro:   ['M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01', 'text-rol-fuerte'],
}
</script>

<template>
  <!-- Compacta, pero con la imagen grande (decisión de Cynthia: mejor la imagen aunque en
       pantallas de 800px de alto haga falta un poco de scroll, que la columna tiene propio). -->
  <aside class="flex w-full shrink-0 flex-col gap-3 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-7.75rem)] lg:w-60 lg:overflow-y-auto lg:overscroll-contain lg:pb-1 xl:w-72 2xl:w-[21rem]"
         aria-label="Resumen del proyecto">

    <!-- 1 · Resumen del proyecto (+ «Antes de continuar» dentro) -->
    <section class="rounded-2xl border border-borde bg-white p-3.5 shadow-sm">
      <div class="mb-2.5 flex items-center gap-2">
        <h2 class="min-w-0 flex-1 text-[15px] font-bold text-azul-noche">Resumen del proyecto</h2>
        <button v-if="hayReto" type="button" @click="emit('ver-ficha')"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rol-fuerte/30 text-rol-fuerte hover:bg-rol/10"
                aria-label="Ver la ficha completa del reto" title="Ver ficha completa">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </button>
      </div>

      <!-- Imagen grande (portada o ilustración), como en crear_proyecto.png -->
      <div class="relative">
        <MiniaturaReto :src="portadaUrl" class="flex aspect-[12/5] w-full" />
        <button v-if="puedeEditarImagen" type="button" @click="emit('editar-imagen')"
                class="absolute bottom-1.5 right-1.5 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white text-rol-fuerte shadow-md hover:bg-gray-50"
                aria-label="Cambiar la imagen del proyecto" title="Cambiar imagen (paso 7)">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
        </button>
      </div>

      <p class="mt-2.5 line-clamp-2 font-heading text-[15px] font-bold leading-snug text-azul-noche" :title="titulo">
        {{ (hayReto || titulo) ? (titulo || 'Proyecto sin título') : 'Aún no has elegido reto' }}
      </p>
      <div v-if="tipoEmpresa || estado" class="mt-1.5 flex flex-wrap gap-1">
        <span v-if="tipoEmpresa === 'real'"
              class="inline-flex items-center rounded-full bg-empresas/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-empresas-dark">Real</span>
        <span v-else-if="tipoEmpresa === 'ficticia'"
              class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-amber-800">
          <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Ficticia
        </span>
        <span v-if="estado" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold uppercase tracking-wide"
              :class="estado.clase">{{ estado.label }}</span>
      </div>

      <template v-if="hayReto || titulo">
        <p v-if="pregunta" class="mt-2.5 line-clamp-2 text-[13px] italic leading-snug text-texto-secundario" :title="pregunta">«{{ pregunta }}»</p>

        <ul v-if="datos.length" class="mt-2.5 grid grid-cols-1 gap-x-3 gap-y-1 text-xs xl:grid-cols-2">
          <li v-for="d in datos" :key="d.icono + d.valor" class="flex min-w-0 items-center gap-1.5"
              :class="d.ancho ? 'xl:col-span-2' : ''" :title="d.valor">
            <svg class="h-3.5 w-3.5 shrink-0" :class="ICONOS[d.icono]?.[1] || 'text-texto-secundario'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="ICONOS[d.icono]?.[0]"/>
            </svg>
            <span class="min-w-0 truncate text-azul-noche">{{ d.valor }}</span>
          </li>
        </ul>

      </template>
      <p v-else class="mt-2 text-[13px] leading-snug text-texto-secundario">
        Elige en el paso 1 el reto de la biblioteca: aquí verás su resumen mientras completas la propuesta.
      </p>

      <!-- Antes de continuar: nota dentro del resumen (aviso en ámbar si lo hay) -->
      <div v-if="antes" class="mt-3 flex items-start gap-2 rounded-xl px-3 py-2"
           :class="antes.tipo === 'aviso' ? 'bg-amber-50' : 'bg-rol/5'"
           :role="antes.tipo === 'aviso' ? 'alert' : undefined">
        <span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-white"
              :class="antes.tipo === 'aviso' ? 'bg-amber-600' : 'bg-rol-fuerte'" aria-hidden="true">
          <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" :d="antes.tipo === 'aviso' ? 'M12 8v4m0 4h.01' : 'M12 11v5m0-8h.01'"/></svg>
        </span>
        <p class="min-w-0 text-xs leading-snug" :class="antes.tipo === 'aviso' ? 'text-amber-900' : 'text-texto-secundario'">
          <strong class="font-bold" :class="antes.tipo === 'aviso' ? 'text-amber-900' : 'text-azul-noche'">{{ antes.titulo }}:</strong>
          {{ antes.texto }}
        </p>
      </div>
    </section>

    <!-- 2 · Sugerencias con IA: la tarjeta entera es el botón -->
    <button v-if="ia" type="button" @click="emit('sugerir')" :disabled="ia.cargando || ia.deshabilitado"
            :title="ia.deshabilitado && ia.motivo ? ia.motivo : ia.texto"
            class="group flex w-full items-center gap-3 rounded-2xl border border-alumnos/25 bg-alumnos/5 px-3.5 py-3 text-left transition-colors hover:bg-alumnos/10 disabled:cursor-not-allowed disabled:opacity-60">
      <svg v-if="ia.cargando" class="h-7 w-7 shrink-0 animate-spin text-alumnos" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
      <svg v-else class="h-7 w-7 shrink-0 text-alumnos" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M10 2.5l1.9 5.6 5.6 1.9-5.6 1.9L10 17.5l-1.9-5.6L2.5 10l5.6-1.9L10 2.5z"/>
        <path d="M18 13l.9 2.6 2.6.9-2.6.9L18 20l-.9-2.6-2.6-.9 2.6-.9L18 13z" opacity=".8"/>
      </svg>
      <span class="min-w-0 flex-1">
        <span class="block text-sm font-bold text-azul-noche">{{ ia.cargando ? 'Generando sugerencias…' : 'Sugerencias con IA' }}</span>
        <span class="block truncate text-xs text-texto-secundario">{{ ia.deshabilitado && ia.motivo ? ia.motivo : ia.boton }}</span>
      </span>
      <svg class="h-4 w-4 shrink-0 text-rol-fuerte transition-transform group-enabled:group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
    </button>

    <!-- 3 · Recursos de apoyo (los de /seccion/recursos) en cuadrícula 2×2 -->
    <section class="rounded-2xl border border-borde bg-white p-3.5 shadow-sm" aria-labelledby="titulo-recursos-apoyo">
      <div class="mb-2 flex items-center gap-2">
        <svg class="h-4 w-4 shrink-0 text-rol-fuerte" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M3 6.5A2.5 2.5 0 015.5 4h4.1a2 2 0 011.6.8l1 1.3a1 1 0 00.8.4h5.5A2.5 2.5 0 0121 9v8.5a2.5 2.5 0 01-2.5 2.5h-13A2.5 2.5 0 013 17.5v-11z"/>
        </svg>
        <h2 id="titulo-recursos-apoyo" class="min-w-0 flex-1 text-sm font-bold text-azul-noche">Recursos de apoyo</h2>
        <RouterLink to="/seccion/recursos" target="_blank"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rol-fuerte/30 text-rol-fuerte hover:bg-rol/10"
                    aria-label="Ver todos los recursos (se abre en otra pestaña)" title="Ver todos los recursos">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </RouterLink>
      </div>

      <ul class="grid grid-cols-2 gap-1.5">
        <li v-for="r in recursos" :key="r.titulo">
          <button type="button" :disabled="r.proximamente" @click="ACCIONES[r.accion]?.()"
                  :title="r.proximamente ? `${r.titulo} · próximamente` : r.desc"
                  class="flex h-full w-full min-w-0 items-center gap-2 rounded-xl border border-borde px-2 py-2 text-left transition-colors enabled:hover:border-rol/30 enabled:hover:bg-rol/5 disabled:cursor-default">
            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white"
                  :class="[r.tile, r.proximamente ? 'opacity-40' : '']" aria-hidden="true">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path :d="ICONOS_NAV[r.icon]" />
              </svg>
            </span>
            <span class="min-w-0 break-words">
              <span class="block text-xs font-semibold leading-tight" :class="r.proximamente ? 'text-texto-secundario' : 'text-azul-noche'">{{ r.titulo }}</span>
              <span v-if="r.proximamente" class="block text-xs leading-tight text-texto-secundario">Pronto</span>
            </span>
          </button>
        </li>
      </ul>
    </section>
  </aside>
</template>
