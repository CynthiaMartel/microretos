<!-- Definición de un concepto de DuaLab (reto, proyecto, encuentro, seguimiento…) en la vista
     donde se usa. Mismo estilo y textos que los bloques de conceptos de ComoFuncionaModal.vue.
     El texto llega en segmentos ({ t, b, color?, ruta?, routeName?, tip? } o { br: true })
     para resaltar palabras sin usar v-html:
     - `color` opcional resalta ese segmento con el color de otro concepto (p. ej. 'centros' para el reto).
     - `ruta` convierte el segmento en enlace subrayado, con `tip` como texto al pasar el cursor;
       si el rol no tiene acceso a `routeName`, se muestra como texto normal.
     - `{ br: true }` separa el texto en párrafos.
     Si la vista provee 'rutaResaltada' (SeccionHub.vue), al pasar el cursor o enfocar un
     enlace se publica su ruta para iluminar el botón de debajo que lleva al mismo sitio. -->
<script setup>
import { inject } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useComoFunciona } from '../composables/useComoFunciona'

const props = defineProps({
  // Color de marca: 'centros' (retos) | 'empresas' (proyectos) | 'alumnos' (encuentros) | 'administraciones'
  color:     { type: String, default: 'centros' },
  segmentos: { type: Array, required: true },
})

// Clases completas (no interpoladas) para que Tailwind las detecte
const COLORES = {
  centros:          { caja: 'bg-centros/10 border-centros/30',                   fuerte: 'text-centros' },
  empresas:         { caja: 'bg-empresas/10 border-empresas/30',                 fuerte: 'text-empresas-dark' },
  alumnos:          { caja: 'bg-alumnos/10 border-alumnos/30',                   fuerte: 'text-alumnos-dark' },
  administraciones: { caja: 'bg-administraciones/10 border-administraciones/30', fuerte: 'text-[#0F7273]' },
}
const c = COLORES[props.color] ?? COLORES.centros

const authStore = useAuthStore()
// Si el concepto está dentro del modal "Cómo funciona", al seguir un enlace se cierra
const { cerrarComoFunciona } = useComoFunciona()
const esEnlace = (s) => s.ruta && (!s.routeName || authStore.canAccess(s.routeName))
// Opcional: fuera de SeccionHub no hay nada que resaltar
const rutaResaltada = inject('rutaResaltada', null)
const resaltar = (ruta) => { if (rutaResaltada) rutaResaltada.value = ruta }
const claseTexto = (s) => [s.b && 'font-black', (s.b || esEnlace(s)) && (COLORES[s.color] ?? c).fuerte]
</script>

<template>
  <div class="rounded-2xl border-2 p-3 shadow-sm sm:p-4" :class="c.caja">
    <p class="text-[13px] leading-relaxed text-gray-900 break-words sm:text-sm">
      <template v-for="(s, i) in segmentos" :key="i"><span v-if="s.br" class="block h-2" aria-hidden="true" /><span v-else-if="esEnlace(s)" class="cc-enlace"><router-link
            :to="s.ruta" :aria-label="s.tip ? `${s.t} — ${s.tip}` : undefined"
            class="underline decoration-2 underline-offset-2 hover:opacity-80" :class="claseTexto(s)"
            @mouseenter="resaltar(s.ruta)" @mouseleave="resaltar(null)"
            @focus="resaltar(s.ruta)" @blur="resaltar(null)"
            @click="resaltar(null); cerrarComoFunciona()">{{ s.t }}</router-link><span v-if="s.tip" class="cc-tip" role="tooltip">{{ s.tip }}</span></span><span v-else-if="s.b" :class="claseTexto(s)">{{ s.t }}</span><template v-else>{{ s.t }}</template></template>
    </p>
  </div>
</template>

<style scoped>
.cc-enlace { position: relative; display: inline; }
.cc-tip {
  position: absolute; left: 50%; bottom: calc(100% + 6px); transform: translateX(-50%);
  width: max-content; max-width: min(14rem, 70vw); text-align: center;
  pointer-events: none; z-index: 30;
  padding: 4px 8px; border-radius: 8px; font-size: 11px; font-weight: 600;
  background: #1F2937; color: #fff; opacity: 0; transition: opacity 120ms ease;
}
.cc-enlace:hover .cc-tip,
.cc-enlace:focus-within .cc-tip { opacity: 1; }
/* En pantallas táctiles no hay hover: el tooltip no aporta y quedaría pegado tras el toque */
@media (hover: none) { .cc-tip { display: none; } }
</style>
