// Tipos de cuenta que se eligen al crear o editar en Usuarios, agrupados por la app a la que dan acceso.
// Lo usan el selector del formulario (SelectorTipoCuenta) y la explicación de todos (TiposCuentaModal).
// `val` es el role como texto, igual que en el formulario.

const I = {
  docente:   'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 3h6v4H9V3z',
  admin:     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 3h6v4H9V3zm0 11l2 2 4-4',
  empresa:   'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
  comercial: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 3h6v4H9V3zM9 13h6M9 17h4',
  red:       'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  practicas: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  central:   'M4 6a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm9 0a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2h-3a2 2 0 01-2-2V6zM4 15a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2H6a2 2 0 01-2-2v-3zm9 0a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2h-3a2 2 0 01-2-2v-3z',
  super:     'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
}

export const SECCIONES_TIPO_CUENTA = [
  {
    titulo: 'DuaLab App',
    tipos: [
      { val: '2', label: 'Docente', icono: I.docente, activo: 'bg-centros/10 border-centros/40 text-centros',
        texto: 'Entra en la app DuaLab de su centro: crea propuestas a partir de los retos, prepara los encuentros creativos con su alumnado y sigue el trabajo de los grupos. Necesita un centro educativo.' },
      { val: '4', label: 'Admin docente', icono: I.admin, activo: 'bg-administraciones/10 border-administraciones/40 text-administraciones',
        texto: 'Lo mismo que un docente y, además, gestiona las cuentas de los docentes de su centro. Necesita un centro educativo.' },
      { val: '3', label: 'Empresa', icono: I.empresa, activo: 'bg-empresas/10 border-empresas/40 text-empresas-dark',
        texto: 'Persona de una empresa colaboradora: consulta los retos y las propuestas de su empresa y las valida. En general solo puede leer. Necesita una empresa asignada.' },
    ],
  },
  {
    titulo: 'App comercial',
    tipos: [
      { val: '5', label: 'Comercial', icono: I.comercial, activo: 'bg-empresas/10 border-empresas/40 text-empresas-dark',
        texto: 'Solo usa la app comercial (comercial.dualab.es): capta empresas, hace la entrevista diagnóstica, prepara retos en borrador y recoge la firma. No tiene centro ni entra en la app de docentes.' },
    ],
  },
  {
    titulo: 'DuaLab Central',
    tipos: [
      { val: '6', label: 'Red DuaLab', icono: I.red, activo: 'bg-espacio-red/10 border-espacio-red/40 text-espacio-red-fuerte',
        texto: 'Entra en DuaLab Central, en Red DuaLab: correo de info@, captaciones, solicitudes de retos del profesorado y consulta de empresas y centros. No ve Seguimiento de prácticas, Base de datos, Usuarios ni Papelera.' },
      { val: '7', label: 'Gestión de prácticas', icono: I.practicas, activo: 'bg-espacio-practicas/10 border-espacio-practicas/40 text-espacio-practicas-fuerte',
        texto: 'Entra en DuaLab Central, en Seguimiento de prácticas: alumnado destacado, planes de proyecto y agenda de prácticas. No ve Red DuaLab, Base de datos, Usuarios ni Papelera.' },
      { val: '8', label: 'Admin Central DuaLab', icono: I.central, activo: 'bg-azul-noche/10 border-azul-noche/40 text-azul-noche',
        texto: 'Entra en DuaLab Central con Red DuaLab y Seguimiento de prácticas, y además gestiona Usuarios (sin crear ni tocar superadmins). No ve Base de datos ni Papelera.' },
    ],
  },
  {
    titulo: 'Superadmin',
    soloSuperadmin: true,   // solo al crear y solo lo ve un superadmin
    tipos: [
      { val: '1', label: 'Superadmin', icono: I.super, activo: 'bg-gray-100 border-azul-noche/50 text-azul-noche',
        texto: 'Lo ve y lo puede todo, también Base de datos, Usuarios y Papelera. La cuenta se crea ya activa y después no se puede editar, bloquear ni borrar desde Usuarios. Solo un superadmin puede crearla.' },
    ],
  },
]

export const tipoCuenta = (val) => SECCIONES_TIPO_CUENTA.flatMap(s => s.tipos).find(t => t.val === val) || null
