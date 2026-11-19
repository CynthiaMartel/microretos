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

// ── Solicitudes de retos de docentes (BandejaSolicitudesReto y FichaSolicitudReto) ──
// fase: SolicitudRetoResource::fase
export const FASES_SOLICITUD = {
  pendiente:     { texto: 'Esperando al docente', clases: 'bg-gray-100 text-texto-secundario' },
  respondida:    { texto: 'Por generar',          clases: 'bg-alumnos/15 text-alumnos-dark' },
  con_retos:     { texto: 'Retos generados',      clases: 'bg-centros/10 text-centros' },
  avisada:       { texto: 'Docente avisado',      clases: 'bg-empresas/10 text-empresas-dark' },
  con_encuentro: { texto: 'Con encuentro',        clases: 'bg-empresas/15 text-empresas-dark' },
  descartada:    { texto: 'Descartada',           clases: 'bg-gray-100 text-texto-secundario line-through' },
}

// Sesión que propuso el docente: «mié 25 nov · 12:10–14:00»
export const textoSesion = (s) => {
  if (!s?.fecha) return ''
  const dia = new Date(s.fecha + 'T00:00:00').toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' })
  return s.hora_inicio ? `${dia} · ${s.hora_inicio}${s.hora_fin ? '–' + s.hora_fin : ''}` : dia
}

// Recordatorio del encuentro: hay sesión propuesta y aún no hay encuentro creado
export const faltaEncuentro = (s) => !!s?.sesion?.fecha && !['con_encuentro', 'descartada', 'pendiente'].includes(s.fase)

// La app DuaLab (generador): mismo dominio raíz que Central en producción; en local, su puerto
export const urlApp = () => window.location.hostname.endsWith('dualab.es') ? 'https://dualab.es' : 'http://localhost:5173'
