// Nombre de un equipo de alumnado tal y como lo ve el DOCENTE ("grupo"). En BD los
// equipos se crean como "Equipo N" (EncuentroController) y el alumnado los sigue viendo
// así; en las vistas del docente ese nombre por defecto se muestra como "Grupo N".
// Si el equipo eligió un nombre propio, se respeta tal cual.
export function nombreGrupo(equipo) {
  const nombre = String(equipo?.nombre ?? '').trim()
  const porDefecto = nombre.match(/^equipo\s+(\d+)$/i)
  if (porDefecto) return `Grupo ${porDefecto[1]}`
  if (nombre) return nombre
  return equipo?.numero_equipo ? `Grupo ${equipo.numero_equipo}` : 'Grupo'
}
