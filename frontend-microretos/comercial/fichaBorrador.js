// Los retos que genera el comercial son borradores (aún no son microretos): para enseñarlos con
// la misma ficha que la app DuaLab (MicroretoModal, prop datosReto) se completan con lo que la
// ficha toma de la empresa: nombre, sector, tamaño y el diagnóstico de la entrevista.
import { COLUMNA_DE } from '../src/config/preguntasDiagnostico.js'

export function fichaDeBorrador(borrador, empresa) {
  const diagnostico = empresa
    ? Object.fromEntries(Object.values(COLUMNA_DE).map(c => [c, empresa[c] ?? null]))
    : null
  return {
    ...borrador.reto,
    familia: borrador.familia,
    empresa_nombre: empresa?.nombre_comercial ?? borrador.captacion?.nombre_comercial ?? '',
    empresa_es_simulada: false, // la app comercial solo capta empresas reales
    empresa: empresa ? { sector: empresa.sector, tamano: empresa.tamano, ...diagnostico } : null,
  }
}
