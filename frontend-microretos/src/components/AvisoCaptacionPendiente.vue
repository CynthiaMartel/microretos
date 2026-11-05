<script setup>
/**
 * Aviso al superadmin al entrar: empresas captadas por comerciales que esperan centro y retos
 * de comerciales sin validar. Los contadores se piden en vivo (si otro superadmin ya las colocó,
 * no avisa) y el aviso sale una vez por sesión del navegador; el detalle está en Notificaciones.
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Modal from './Modal.vue'
import { getResumenCaptacion } from '../services/captacionService.js'

const CLAVE_VISTO = 'aviso-captacion-visto'

const router  = useRouter()
const visible = ref(false)
const resumen = ref({ empresas_pendientes: 0, retos_pendientes: 0 })

onMounted(async () => {
  try {
    if (sessionStorage.getItem(CLAVE_VISTO)) return
  } catch { /* sin sessionStorage: se avisa igualmente */ }

  try {
    const { data } = await getResumenCaptacion()
    resumen.value = data
    visible.value = data.empresas_pendientes > 0 || data.retos_pendientes > 0
  } catch { /* el aviso es opcional: si falla, ya está en Notificaciones */ }
})

const cerrar = () => {
  visible.value = false
  try { sessionStorage.setItem(CLAVE_VISTO, '1') } catch { /* nada */ }
}

const revisar = () => {
  cerrar()
  router.push({ path: '/notificaciones', query: { apartado: 'captacion' } })
}
</script>

<template>
  <Modal :visible="visible" max-width="max-w-md" card-class="p-6 sm:p-8" @cerrar="cerrar">
    <div class="flex flex-col gap-4">
      <span class="flex h-12 w-12 items-center justify-center rounded-full bg-empresas/15 text-empresas" aria-hidden="true">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1" />
        </svg>
      </span>
      <h2 class="pr-10 font-heading text-xl font-bold text-azul-noche">Captación comercial pendiente</h2>
      <ul class="space-y-2 text-sm text-texto-secundario">
        <li v-if="resumen.empresas_pendientes > 0">
          <strong class="text-azul-noche">{{ resumen.empresas_pendientes }}</strong>
          {{ resumen.empresas_pendientes === 1 ? 'empresa nueva espera' : 'empresas nuevas esperan' }}
          que la coloques en uno o varios centros.
        </li>
        <li v-if="resumen.retos_pendientes > 0">
          <strong class="text-azul-noche">{{ resumen.retos_pendientes }}</strong>
          {{ resumen.retos_pendientes === 1 ? 'reto creado por comercial está pendiente' : 'retos creados por comercial están pendientes' }}
          de validar para publicar.
        </li>
      </ul>
      <div class="fila-pasos pt-2">
        <button type="button" class="btn-paso-atras" @click="cerrar">Más tarde</button>
        <button type="button" class="btn-paso" @click="revisar">Revisar ahora</button>
      </div>
    </div>
  </Modal>
</template>
