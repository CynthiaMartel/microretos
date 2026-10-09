import { ref } from 'vue'
import { getContadorNoLeidas } from '../services/notificacionService.js'

// Contador de no leídas compartido (badge del SidePanel y vista Notificaciones).
// Se refresca al navegar, como mucho una vez cada 30 s: sin sondeo continuo.
const noLeidas = ref(0)
let ultimaConsulta = 0
const MIN_INTERVALO_MS = 30_000

async function refrescarNoLeidas({ forzar = false } = {}) {
  if (!forzar && Date.now() - ultimaConsulta < MIN_INTERVALO_MS) return
  ultimaConsulta = Date.now()
  try {
    const { data } = await getContadorNoLeidas()
    noLeidas.value = Number(data.no_leidas) || 0
  } catch {
    // Sin contador no se rompe nada: el badge simplemente no se muestra
  }
}

export function useNotificaciones() {
  return { noLeidas, refrescarNoLeidas }
}
