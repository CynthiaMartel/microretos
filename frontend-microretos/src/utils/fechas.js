// Fecha ISO → «dd/mm/aaaa» (o '' si no es válida).
export const formatearFecha = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
