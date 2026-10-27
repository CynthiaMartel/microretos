<!-- Columna derecha de «Crear encuentro» (diseño crear_encuentro.png):
     1. «Resumen del encuentro»: miniatura del proyecto, título, etiquetas (estado y empresa),
        familia y curso; ficha clave/valor con lo que se va rellenando y, dentro de la misma
        tarjeta, el recuadro «Antes de continuar» (consejo del paso o aviso en ámbar).
     2. «Información del reto»: acceso a la ficha completa del reto del proyecto.
     Solo datos reales: no hay «Sugerencias con IA» porque no existe IA para encuentros.
     Mismo tamaño y ritmo que TallerResumen (/proyectos/crear): 240px en lg, 288px en xl,
     336px en 2xl; separación 12px y relleno 14px. ≥ lg: fija a la derecha con scroll propio;
     < lg: debajo del formulario, a todo el ancho. -->
<script setup>
import MiniaturaReto from './MiniaturaReto.vue'

defineProps({
  proyecto: { type: Object, default: null },  // proyecto asociado (listado /startup/proyectos)
  estado:   { type: Object, default: null },  // { label, cls } — estadoProyectoBadge
  // Ficha clave/valor: [{ icono, label, valor }] — solo filas con valor.
  datos:    { type: Array, default: () => [] },
  // Recuadro «Antes de continuar»: { titulo, texto, tipo: 'info' | 'aviso' }
  antes:    { type: Object, default: null },
})
const emit = defineEmits(['ver-proyecto', 'ver-reto'])

const ICONOS = {
  centro:     'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01',
  clase:      'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
  calendario: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  alumnado:   'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
  reloj:      'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
  grupos:     'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
}
</script>

<template>
  <aside class="flex w-full shrink-0 flex-col gap-3 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-7.75rem)] lg:w-60 lg:overflow-y-auto lg:overscroll-contain lg:pb-1 xl:w-72 2xl:w-[21rem]"
         aria-label="Resumen del encuentro">

    <!-- 1 · Resumen del encuentro (+ «Antes de continuar» dentro) -->
    <section class="rounded-2xl border border-borde bg-white p-3.5 shadow-sm">
      <div class="mb-2.5 flex items-center gap-2.5">
        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rol/10 text-rol-fuerte" aria-hidden="true">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </span>
        <h2 class="min-w-0 flex-1 text-[15px] font-bold text-azul-noche">Resumen del encuentro</h2>
        <button v-if="proyecto" type="button" @click="emit('ver-proyecto')"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rol-fuerte/30 text-rol-fuerte hover:bg-rol/10"
                aria-label="Ver la ficha del proyecto" title="Ver ficha del proyecto">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        </button>
      </div>

      <div class="flex items-start gap-3">
        <MiniaturaReto :src="proyecto?.imagen_portada_url || ''" class="flex h-14 w-[4.5rem]" />
        <div class="min-w-0 flex-1">
          <p class="line-clamp-2 font-heading text-sm font-bold leading-snug text-azul-noche" :title="proyecto?.titulo">
            {{ proyecto?.titulo || 'Aún no has asociado proyecto' }}
          </p>
          <div v-if="estado || proyecto?.empresa_nombre" class="mt-1.5 flex flex-wrap gap-1">
            <span v-if="estado" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold uppercase tracking-wide"
                  :class="estado.cls">{{ estado.label }}</span>
            <span v-if="proyecto?.empresa_nombre"
                  class="inline-flex max-w-full items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-azul-noche">
              <span class="truncate">{{ proyecto.empresa_nombre }}</span>
            </span>
          </div>
          <div v-if="proyecto?.familia_nombre || proyecto?.curso" class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-texto-secundario">
            <span v-if="proyecto?.familia_nombre" class="inline-flex min-w-0 items-center gap-1">
              <svg class="h-3.5 w-3.5 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="ICONOS.alumnado"/></svg>
              <span class="truncate">{{ proyecto.familia_nombre }}</span>
            </span>
            <span v-if="proyecto?.curso" class="inline-flex items-center gap-1">
              <svg class="h-3.5 w-3.5 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="ICONOS.calendario"/></svg>
              {{ proyecto.curso === 'ambos_cursos' ? '1º y 2º curso' : `${String(proyecto.curso).replace('º', '')}º curso` }}
            </span>
          </div>
        </div>
      </div>
      <p v-if="!proyecto" class="mt-2 text-[13px] leading-snug text-texto-secundario">
        Asocia en el paso 1 el proyecto que se trabajará: aquí verás el resumen mientras completas el encuentro.
      </p>

      <!-- En lg (columna estrecha) la etiqueta va encima del valor; desde xl, en la misma fila -->
      <dl v-if="datos.length" class="mt-3 divide-y divide-borde border-t border-borde">
        <div v-for="d in datos" :key="d.label"
             class="grid grid-cols-[1.25rem_minmax(0,1fr)_minmax(0,1fr)] items-start gap-x-2.5 py-2 text-[13px] lg:grid-cols-[1.25rem_minmax(0,1fr)] xl:grid-cols-[1.25rem_minmax(0,1fr)_minmax(0,1fr)]">
          <svg class="mt-0.5 h-4 w-4 text-texto-secundario lg:row-span-2 xl:row-span-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="ICONOS[d.icono] || ICONOS.centro"/>
          </svg>
          <dt class="text-texto-secundario">{{ d.label }}</dt>
          <dd class="break-words font-semibold text-azul-noche">{{ d.valor }}</dd>
        </div>
      </dl>

      <!-- Antes de continuar -->
      <div v-if="antes" class="mt-3 flex items-start gap-2 rounded-xl px-3 py-2"
           :class="antes.tipo === 'aviso' ? 'bg-amber-50' : 'bg-rol/5'"
           :role="antes.tipo === 'aviso' ? 'alert' : undefined">
        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-white"
              :class="antes.tipo === 'aviso' ? 'bg-amber-600' : 'bg-rol-fuerte'" aria-hidden="true">
          <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" :d="antes.tipo === 'aviso' ? 'M12 9v3m0 4h.01' : 'M12 11v5m0-8h.01'"/></svg>
        </span>
        <div class="min-w-0">
          <p class="text-[13px] font-bold" :class="antes.tipo === 'aviso' ? 'text-amber-900' : 'text-azul-noche'">{{ antes.titulo }}</p>
          <p class="mt-0.5 text-xs leading-snug" :class="antes.tipo === 'aviso' ? 'text-amber-900' : 'text-texto-secundario'">{{ antes.texto }}</p>
        </div>
      </div>
    </section>

    <!-- 2 · Información del reto -->
    <section v-if="proyecto?.microreto_id" class="rounded-2xl border border-alumnos/20 bg-alumnos/5 p-3.5">
      <div class="flex items-start gap-2.5">
        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-alumnos text-azul-noche" aria-hidden="true">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </span>
        <div class="min-w-0">
          <h2 class="text-sm font-bold text-azul-noche">Información del reto</h2>
          <p class="mt-0.5 text-[13px] leading-snug text-texto-secundario">
            Consulta la ficha completa del reto para revisar los objetivos, el currículo y el contexto de la empresa.
          </p>
        </div>
      </div>
      <button type="button" @click="emit('ver-reto')"
              class="mt-2.5 inline-flex h-9 w-full items-center justify-center gap-2 rounded-full border border-rol-fuerte/40 bg-white text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        Ver ficha del reto
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
      </button>
    </section>
  </aside>
</template>
