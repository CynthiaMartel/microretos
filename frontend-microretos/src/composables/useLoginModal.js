import { ref } from 'vue'

// Estado singleton a nivel de módulo — todos los componentes comparten la misma instancia
const showLogin        = ref(false)
const destinoTrasLogin = ref(null)

export function useLoginModal() {
  const openLogin = (destino = null) => {
    destinoTrasLogin.value = destino
    showLogin.value = true
  }
  return { showLogin, destinoTrasLogin, openLogin }
}
