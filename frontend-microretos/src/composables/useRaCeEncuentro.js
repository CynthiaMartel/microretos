import { ref, computed } from 'vue'
import api from '../api.js'

// Agrupa evaluacion_oficial (array plano {modulo, ra, ce, aplicacion}) por módulo
// para el bloque desplegable de RA/CE del modal de detalle de encuentro.
export function useRaCeEncuentro() {
  const raCeEncuentro     = ref([])
  const cargandoRaCe      = ref(false)
  const modulosExpandidos = ref(new Set())

  const raCeBlocksEncuentro = computed(() => {
    const entradas = raCeEncuentro.value
    if (!Array.isArray(entradas) || !entradas.length) return []
    const mapa = new Map()
    for (const e of entradas) {
      const nombre = e.modulo || 'Sin módulo'
      if (!mapa.has(nombre)) mapa.set(nombre, [])
      mapa.get(nombre).push(e)
    }
    return [...mapa.entries()].map(([modulo, items]) => ({ modulo, items }))
  })

  function toggleModulo(nombre) {
    const s = new Set(modulosExpandidos.value)
    if (s.has(nombre)) s.delete(nombre)
    else s.add(nombre)
    modulosExpandidos.value = s
  }

  async function cargarRaCe(microproyectoUuid) {
    raCeEncuentro.value     = []
    modulosExpandidos.value = new Set()
    if (!microproyectoUuid) return
    cargandoRaCe.value = true
    try {
      const res = await api.get(`/startup/proyectos/${microproyectoUuid}`)
      raCeEncuentro.value = res.data.evaluacion_oficial || []
    } catch {
      /* no crítico */
    } finally {
      cargandoRaCe.value = false
    }
  }

  return { cargandoRaCe, modulosExpandidos, raCeBlocksEncuentro, toggleModulo, cargarRaCe }
}
