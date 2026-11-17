// Textos comunes de la bandeja «Primer contacto» (BandejaFormularios y FichaFormulario)
export const ESTADOS_FORMULARIO = {
  nuevo:     { texto: 'Nuevo',     clases: 'bg-alumnos/15 text-alumnos-dark' },
  leido:     { texto: 'Leído',     clases: 'bg-gray-100 text-texto-secundario' },
  archivado: { texto: 'Archivado', clases: 'bg-gray-100 text-texto-secundario' },
}

// Captación creada en la app a partir del formulario (webhook de la web, origen 2)
export const ESTADOS_CAPTACION = {
  pendiente_validacion: 'pendiente de validar',
  validada: 'validada',
  descartada: 'descartada',
  borrador: 'en borrador',
}

export const textoCaptacion = (c) =>
  c ? `Captación de ${c.tipo === 'centro' ? 'centro' : 'empresa'} ${ESTADOS_CAPTACION[c.estado] ?? c.estado}` : ''

export const fechaHora = (iso) => {
  if (!iso) return ''
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleString('es-ES', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
