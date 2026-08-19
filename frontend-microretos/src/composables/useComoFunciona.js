import { ref } from 'vue'

const abierto = ref(false)

export function useComoFunciona() {
  return {
    comoFuncionaAbierto: abierto,
    abrirComoFunciona: () => (abierto.value = true),
    cerrarComoFunciona: () => (abierto.value = false),
  }
}
