<!-- Panel lateral de DuaLab Central (uno por espacio: route.meta.espacio, central/espacios.js): misma estética que el SidePanel de DuaLab y el de la app
     comercial (azul noche, .nav-item con indicador lateral, banner de marca al fondo), en turquesa.
     Fijo en lg+, cajón deslizante en móvil y tablet. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getResumenCaptacion } from '../../src/services/captacionService.js'
import { getFormulariosWeb, getCorreos } from '../../src/services/primerContactoService.js'
import { listarSolicitudes } from '../../src/services/solicitudRetoService.js'
import logo from '../../src/assets/logo_colores.png'
import { ESPACIOS, ICONOS, espacioDe, puedeVerItem, conCandado } from '../espacios.js'
import { avisoSuperadmin } from '../avisoSuperadmin.js'
import { useAuthStore } from '../../src/stores/auth.js'

const abierto = defineModel('abierto', { type: Boolean, default: false })

const route  = useRoute()
const router = useRouter()

// Grupos del espacio actual (central/espacios.js) + un grupo común para cambiar de espacio y Mi usuario
const espacio = computed(() => espacioDe(route.meta.espacio))
const GRUPO_COMUN = {
  titulo: 'Central',
  items: [
    { key: 'espacios', label: 'Cambiar de espacio', desplegable: true,
      icono: 'M4 6a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm9 0a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2h-3a2 2 0 01-2-2V6zm-9 9a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2H6a2 2 0 01-2-2v-3zm9 0a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2h-3a2 2 0 01-2-2v-3z' },
    { key: 'usuario', label: 'Mi usuario', ruta: { name: 'mi-usuario' }, nombres: ['mi-usuario'], icono: ICONOS.usuario },
  ],
}
const auth = useAuthStore()
const GRUPOS = computed(() => [...(espacio.value?.grupos ?? []), GRUPO_COMUN]
  .map(g => ({ ...g, items: g.items.filter(i => puedeVerItem(auth.userRole, i) || conCandado(auth.userRole, i)) }))
  .filter(g => g.items.length))
const activo = item => !!item.nombres?.includes(route.name)

// «Cambiar de espacio» se despliega con los otros espacios (como en la barra superior)
const espaciosAbierto = ref(false)
const otrosEspacios = computed(() => ESPACIOS.filter(e => e.clave !== espacio.value?.clave))
const PUNTO_ESPACIO = { 'espacio-red': 'bg-espacio-red', 'espacio-practicas': 'bg-espacio-practicas' }
function irAEspacio(e) {
  abierto.value = false
  espaciosAbierto.value = false
  router.push(e ? e.ruta : { name: 'inicio' })
}

function ir(item) {
  if (item.desplegable) { espaciosAbierto.value = !espaciosAbierto.value; return }
  abierto.value = false
  // Solo superadmin (Admin Central DuaLab la ve con candado): aviso para iniciar sesión como superadmin
  if (conCandado(auth.userRole, item)) { avisoSuperadmin.value = { nombre: item.label, destino: item.ruta }; return }
  if (item.externo) window.open(item.externo, '_blank', 'noopener')
  else router.push(item.ruta)
}

// Contadores del espacio actual: captaciones por validar (empresas + centros + retos) y formularios
// nuevos de la web. Se refrescan al navegar, como mucho cada 30 s y solo el del espacio abierto.
const CONTADORES = {
  captaciones: { aria: 'pendientes de validar', cargar: async () => {
    const { data } = await getResumenCaptacion()
    return (data.empresas_pendientes ?? 0) + (data.centros_pendientes ?? 0) + (data.retos_pendientes ?? 0)
  } },
  formularios: { aria: 'formularios nuevos', cargar: async () => {
    const { data } = await getFormulariosWeb({ per_page: 1 })
    return data.resumen?.por_estado?.nuevo ?? 0
  } },
  correo: { aria: 'correos sin leer', cargar: async () => {
    const { data } = await getCorreos({ per_page: 1 })
    return data.resumen?.por_estado?.nuevo ?? 0
  } },
  solicitudes: { aria: 'solicitudes de retos por generar', cargar: async () => {
    const { data } = await listarSolicitudes({ per_page: 1 })
    return data.resumen?.por_trabajar ?? 0
  } },
}
const pendientes = ref({})
const ultima = {}
async function refrescar() {
  const claves = GRUPOS.value.flatMap(g => g.items).map(i => i.contador).filter(Boolean)
  for (const clave of claves) {
    if (Date.now() - (ultima[clave] ?? 0) < 30000) continue
    ultima[clave] = Date.now()
    CONTADORES[clave].cargar().then(n => { pendientes.value[clave] = n }).catch(() => { /* el contador no es crítico */ })
  }
}
onMounted(refrescar)
watch(() => route.fullPath, () => { abierto.value = false; refrescar() })
const textoPendientes = (clave) => (pendientes.value[clave] > 99 ? '99+' : String(pendientes.value[clave]))
</script>

<template>
  <Transition name="velo">
    <div v-if="abierto" class="fixed inset-0 top-16 z-30 bg-black/40 backdrop-blur-[1px] lg:hidden" aria-hidden="true" @click="abierto = false" />
  </Transition>

  <aside id="panel-central" aria-label="Navegación de DuaLab Central"
         class="panel-lateral fixed left-0 top-16 z-40 flex h-[calc(100dvh-6.5rem)] w-72 max-w-[85vw] flex-col border-r border-white/10 bg-[var(--color-panel,#17283E)] shadow-[6px_0_32px_rgba(0,0,0,0.25)] transition-transform duration-300 ease-in-out lg:translate-x-0"
         :class="abierto ? 'translate-x-0' : '-translate-x-full'">
    <nav class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-3 py-3">
      <!-- Cabecera del espacio: distinta de los títulos de sección (CONTACTOS, SOPORTE…) para que no parezca uno más -->
      <div v-if="espacio" class="mb-2 flex items-center gap-2.5 border-b border-white/10 px-3 pb-3 pt-1">
        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="PUNTO_ESPACIO[espacio.color]" aria-hidden="true" />
        <p class="min-w-0 truncate font-heading text-base font-bold text-white">{{ espacio.nombre }}</p>
      </div>
      <template v-for="(grupo, g) in GRUPOS" :key="grupo.titulo || g">
        <p v-if="grupo.titulo" class="nav-grupo">{{ grupo.titulo }}</p>
        <div class="space-y-0.5">
          <template v-for="item in grupo.items" :key="item.key">
          <div v-if="item.separadorAntes" class="mx-3 my-1.5 border-t border-white/10" role="separator" />
          <button type="button" class="nav-item w-full text-left"
                  :class="activo(item) ? 'nav-item--activo' : 'nav-item--idle'"
                  :aria-current="activo(item) ? 'page' : undefined"
                  :aria-expanded="item.desplegable ? espaciosAbierto : undefined" @click="ir(item)">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path :d="item.icono" />
            </svg>
            <span class="min-w-0 flex-1">
              <span class="block truncate">{{ item.label }}</span>
              <span v-if="item.detalle" class="block whitespace-normal break-words text-[11px] font-semibold leading-tight opacity-60">{{ item.detalle }}</span>
            </span>
            <span v-if="item.contador && pendientes[item.contador]" class="nav-badge" :aria-label="`${pendientes[item.contador]} ${CONTADORES[item.contador].aria}`">{{ textoPendientes(item.contador) }}</span>
            <svg v-if="item.externo" class="h-3.5 w-3.5 shrink-0 text-white/40" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            <span v-if="item.externo" class="sr-only">(se abre en otra pestaña)</span>
            <span v-if="item.proximamente" class="text-[10px] font-bold uppercase tracking-wider text-white/40">Pronto</span>
            <svg v-if="conCandado(auth.userRole, item)" class="h-3.5 w-3.5 shrink-0 text-white/50" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <span v-if="conCandado(auth.userRole, item)" class="sr-only">(solo superadmin)</span>
            <svg v-if="item.desplegable" class="h-4 w-4 shrink-0 text-white/50 transition-transform" :class="espaciosAbierto && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
          </button>
          <!-- Otros espacios (submenú) -->
          <div v-if="item.desplegable && espaciosAbierto" class="mb-1 ml-9 space-y-0.5 border-l border-white/10 pl-2">
            <button v-for="e in otrosEspacios" :key="e.clave" type="button" @click="irAEspacio(e)"
                    class="flex min-h-10 w-full items-center gap-2.5 rounded-xl px-2.5 text-left text-sm font-semibold text-white/75 transition-colors hover:bg-white/5 hover:text-white">
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="PUNTO_ESPACIO[e.color]" aria-hidden="true" />
              <span class="min-w-0 truncate">{{ e.nombre }}</span>
            </button>
            <button type="button" @click="irAEspacio(null)"
                    class="flex min-h-10 w-full items-center gap-2.5 rounded-xl px-2.5 text-left text-xs font-semibold text-white/55 transition-colors hover:bg-white/5 hover:text-white">
              Ver todos los espacios
            </button>
          </div>
          </template>
        </div>
      </template>

      <div class="mt-auto shrink-0 pt-4 [@media(max-height:600px)]:hidden" aria-hidden="true">
        <div class="panel-banner banner-central relative h-24 overflow-hidden rounded-2xl border border-white/15 px-4 py-3 shadow-[0_8px_24px_rgba(0,0,0,0.25)] sm:h-28">
          <svg class="absolute inset-x-0 bottom-0 h-12 w-full" viewBox="0 0 240 48" preserveAspectRatio="none">
            <path d="M0 30 C60 14 120 46 240 22 V48 H0Z" fill="#FF8920" opacity="0.85" />
            <path d="M0 38 C70 26 140 50 240 34 V48 H0Z" fill="#3FC7C8" opacity="0.9" />
            <path d="M0 44 C80 38 160 52 240 44 V48 H0Z" fill="#ffffff" opacity="0.35" />
          </svg>
          <img :src="logo" alt="" class="panel-banner-logo absolute right-3 top-1/2 h-16 w-auto -translate-y-1/2 object-contain drop-shadow-[0_4px_10px_rgba(0,0,0,0.35)] sm:right-4 sm:h-20" />
          <p class="relative text-base font-black tracking-wide text-white">DUALAB</p>
          <p class="panel-banner-sub relative mt-1 max-w-[8.5rem] text-xs font-semibold leading-snug text-white/85">{{ espacio?.nombre ?? 'Central del superadmin' }}</p>
        </div>
      </div>
    </nav>
  </aside>
</template>

<style scoped>
/* Mismas medidas que .nav-item del SidePanel de DuaLab y de la app comercial */
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 10px 12px;
  border-radius: 1rem;
  font-size: 0.875rem;
  font-weight: 700;
  transition: background-color 150ms ease, color 150ms ease;
  white-space: nowrap;
  overflow: hidden;
}
.nav-item--idle { color: rgba(255, 255, 255, 0.68); }
.nav-item--idle:hover { background-color: rgba(255, 255, 255, 0.07); color: rgba(255, 255, 255, 0.9); }
/* Color del espacio en su variante clara (--color-rol-claro): legible sobre el azul noche */
.nav-item--activo {
  background: color-mix(in srgb, var(--color-rol-claro) 18%, transparent);
  color: var(--color-rol-claro);
  box-shadow: inset 3px 0 0 var(--color-rol-claro);
}
.nav-badge {
  min-width: 1.4rem;
  border-radius: 9999px;
  background: #FF8920;
  padding: 0.05rem 0.4rem;
  text-align: center;
  font-size: 0.7rem;
  font-weight: 800;
  color: #17283E;
}
.banner-central { background-image: linear-gradient(to bottom right, var(--color-rol-fuerte), color-mix(in srgb, var(--color-rol-fuerte) 75%, #000), color-mix(in srgb, var(--color-rol-fuerte) 50%, #000)); }
.nav-grupo {
  padding: 14px 12px 6px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.55);
  user-select: none;
}
.nav-icon { width: 17px; height: 17px; flex-shrink: 0; }

.velo-enter-active, .velo-leave-active { transition: opacity 0.2s ease; }
.velo-enter-from, .velo-leave-to { opacity: 0; }

nav::-webkit-scrollbar { width: 3px; }
nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.12); border-radius: 99px; }
</style>
