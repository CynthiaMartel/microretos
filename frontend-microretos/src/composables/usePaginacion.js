import { ref, computed, watch } from 'vue'

// Paginación en cliente de una lista reactiva. Si la lista encoge (se borran
// elementos) y la página actual queda vacía, retrocede a la última que exista.
export function usePaginacion(lista, porPagina) {
  const pagina       = ref(0)
  const totalPaginas = computed(() => Math.ceil(lista.value.length / porPagina))
  const itemsPagina  = computed(() => lista.value.slice(pagina.value * porPagina, (pagina.value + 1) * porPagina))

  watch(totalPaginas, (n) => { if (pagina.value > Math.max(n - 1, 0)) pagina.value = Math.max(n - 1, 0) })

  return { pagina, totalPaginas, itemsPagina }
}
