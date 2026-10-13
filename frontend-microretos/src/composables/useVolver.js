import { useRouter } from 'vue-router'

// "Volver" a la vista anterior: si se llegó navegando dentro de la app, atrás en el
// historial (vuelve exactamente a la vista de la que se venía); si se entró por enlace
// directo o recarga no hay vista anterior, y se lleva a `fallback` (el nivel de arriba).
export function useVolver() {
  const router = useRouter()
  return (fallback) => {
    // vue-router guarda en history.state.back la ruta desde la que se llegó
    if (window.history.state?.back) router.back()
    else router.push(fallback)
  }
}
