import { ref, computed } from 'vue';

// Sentinela para agrupar proyectos sin familia_id (legacy) — nunca se ocultan,
// a diferencia de BibliotecaMicroretos.vue que sí puede descartar retos huérfanos.
export const SIN_FAMILIA = '__sin_familia__';

// Filtros por familia/ciclo/curso + búsqueda, compartidos entre /proyectos y
// /proyectos/terminados — mismo patrón (familia → ciclo/curso dependientes)
// que BibliotecaMicroretos.vue. `filtroFamilia` actúa como selector de "capa":
// '' = capa de familias (o vista "Todos" en capa de detalle, según la vista lo use),
// un nombre o SIN_FAMILIA = capa de detalle filtrada a esa familia.
export function useFiltrosProyectos(proyectosBase) {
  const busqueda      = ref('');
  const filtroFamilia = ref('');
  const filtroCiclo   = ref('');
  const filtroCurso   = ref('');

  const resetFiltrosDetalle = () => {
    filtroCiclo.value = '';
    filtroCurso.value = '';
  };

  function coincideFamilia(p) {
    if (filtroFamilia.value === SIN_FAMILIA) return !p.familia_nombre;
    if (filtroFamilia.value) return p.familia_nombre === filtroFamilia.value;
    return true;
  }

  const familiasDisponibles = computed(() => {
    const mapa = new Map();
    proyectosBase.value.forEach(p => {
      if (p.familia_nombre) mapa.set(p.familia_nombre, (mapa.get(p.familia_nombre) || 0) + 1);
    });
    return [...mapa.entries()]
      .map(([nombre, count]) => ({ nombre, count }))
      .sort((a, b) => a.nombre.localeCompare(b.nombre));
  });

  const countSinFamilia = computed(() =>
    proyectosBase.value.filter(p => !p.familia_nombre).length
  );

  const ciclosDisponibles = computed(() => {
    if (!filtroFamilia.value) return [];
    const d = proyectosBase.value.filter(coincideFamilia);
    return [...new Set(d.map(p => p.ciclo_nombre).filter(Boolean))].sort();
  });

  const cursosDisponibles = computed(() => {
    let d = proyectosBase.value.filter(coincideFamilia);
    if (filtroCiclo.value) d = d.filter(p => p.ciclo_nombre === filtroCiclo.value);
    return [...new Set(d.map(p => p.curso).filter(v => v != null))].sort((a, b) => String(a).localeCompare(String(b)));
  });

  function seleccionarFamilia(nombreOSentinela) {
    filtroFamilia.value = filtroFamilia.value === nombreOSentinela ? '' : nombreOSentinela;
    resetFiltrosDetalle();
  }

  function seleccionarCiclo(nombre) {
    filtroCiclo.value = filtroCiclo.value === nombre ? '' : nombre;
    filtroCurso.value = '';
  }

  function seleccionarCurso(curso) {
    filtroCurso.value = filtroCurso.value === curso ? '' : curso;
  }

  function volverAFamilias() {
    filtroFamilia.value = '';
    busqueda.value = '';
    resetFiltrosDetalle();
  }

  function limpiarFiltrosDetalle() {
    busqueda.value = '';
    resetFiltrosDetalle();
  }

  const hayFiltrosDetalleActivos = computed(() =>
    !!(filtroCiclo.value || filtroCurso.value || busqueda.value)
  );

  function aplicarFiltros(lista) {
    let out = lista.filter(coincideFamilia);
    if (filtroCiclo.value) out = out.filter(p => p.ciclo_nombre === filtroCiclo.value);
    if (filtroCurso.value) out = out.filter(p => String(p.curso) === String(filtroCurso.value));
    if (busqueda.value.trim()) {
      const q = busqueda.value.toLowerCase();
      out = out.filter(p =>
        p.titulo?.toLowerCase().includes(q) ||
        p.empresa_nombre?.toLowerCase().includes(q) ||
        p.centro_nombre?.toLowerCase().includes(q)
      );
    }
    return out;
  }

  return {
    busqueda, filtroFamilia, filtroCiclo, filtroCurso,
    familiasDisponibles, countSinFamilia, ciclosDisponibles, cursosDisponibles,
    coincideFamilia, seleccionarFamilia, seleccionarCiclo, seleccionarCurso,
    volverAFamilias, limpiarFiltrosDetalle, hayFiltrosDetalleActivos, aplicarFiltros,
  };
}
