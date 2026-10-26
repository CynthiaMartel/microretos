// Roles del alumnado dentro de su grupo (los reparte el grupo en la fase 0 del espacio de
// trabajo, EquipoWorkspace.vue). Una sola fuente para las vistas del docente (Mis grupos,
// detalle de grupos, Listado de alumnado): mismos nombres que ve el alumnado y una frase que
// explica qué hace cada uno. Colores con los tokens de diseño (docs/GUIA_DISENO.md §2).
export const ROLES_GRUPO = {
  portavoz:      { label: 'Portavoz',            clase: 'bg-centros/10 text-centros',
                   ayuda: 'Representa al grupo: explica sus ideas y comunica lo que se decide.' },
  tiempos:       { label: 'Gestor/a de tiempos', clase: 'bg-amber-50 text-amber-800',
                   ayuda: 'Cuida el ritmo: controla los tiempos de cada fase y avisa al grupo.' },
  documentacion: { label: 'Documentador/a',      clase: 'bg-empresas/10 text-empresas-dark',
                   ayuda: 'Se asegura de que el trabajo del grupo queda registrado.' },
  foco:          { label: 'Responsable de foco', clase: 'bg-violet-50 text-violet-800',
                   ayuda: 'Evita que el grupo se disperse y recuerda el reto.' },
}

export const rolGrupo = (clave) => ROLES_GRUPO[clave] || null
