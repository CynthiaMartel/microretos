import { ref, computed, watch } from 'vue'

// Filtros «Empresa» y «Sector» (desplegables juntos en la barra de FiltrosPanel). Cada uno se
// acota por el otro: con un sector elegido, Empresa solo ofrece las de ese sector, y al revés.
// Si la opción elegida deja de estar entre las disponibles (otro centro, familia, pestaña…), se quita.
//   base:      lista reactiva de la que salen las opciones (sin aplicar estos dos filtros)
//   empresa / sector: refs (o computed con get/set) donde vive el valor; por defecto, refs nuevos
//   empresaDe / sectorDe: cómo leer el nombre de la empresa y su sector de cada elemento
export function useFiltroEmpresaSector(base, {
  empresa = ref(''),
  sector = ref(''),
  empresaDe = x => x.empresa_nombre,
  sectorDe = x => x.empresa_sector,
} = {}) {
  const unicos = lista => [...new Set(lista.filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'))

  const empresasDisponibles = computed(() => unicos(base.value
    .filter(x => !sector.value || sectorDe(x) === sector.value).map(empresaDe)))
  const sectoresDisponibles = computed(() => unicos(base.value
    .filter(x => !empresa.value || empresaDe(x) === empresa.value).map(sectorDe)))

  watch(empresasDisponibles, (lista) => { if (empresa.value && !lista.includes(empresa.value)) empresa.value = '' })
  watch(sectoresDisponibles, (lista) => { if (sector.value && !lista.includes(sector.value)) sector.value = '' })

  const cumpleEmpresaSector = x => (!empresa.value || empresaDe(x) === empresa.value)
    && (!sector.value || sectorDe(x) === sector.value)

  return { filtroEmpresa: empresa, filtroSector: sector, empresasDisponibles, sectoresDisponibles, cumpleEmpresaSector }
}
