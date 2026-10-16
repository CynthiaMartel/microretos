// Lista cerrada de sectores de empresa. El sector es la categoría general (uno solo);
// lo concreto que hace la empresa va en `actividad`, y las áreas con las que quiere
// trabajar, en sus familias con su interés.
// Copia de app/Support/SectoresEmpresa.php (el backend valida contra esa): cambiar las dos a la vez.
export const SECTORES_EMPRESA = [
  'Tecnología e informática',
  'Consultoría y servicios a empresas',
  'Marketing, publicidad y diseño',
  'Comercio y distribución',
  'Industria y fabricación',
  'Alimentación y agroalimentario',
  'Agricultura, pesca y jardinería',
  'Construcción e inmobiliaria',
  'Energía e instalaciones',
  'Automoción',
  'Transporte y logística',
  'Hostelería, restauración y turismo',
  'Salud, estética y bienestar',
  'Servicios sociales y tercer sector',
  'Cultura, deporte y ocio',
  'Medio ambiente',
]

// Las empresas anteriores a la lista guardan sectores en texto libre hasta que se
// reasignen: se muestran tal cual, pero al editar hay que elegir uno de la lista.
export const esSectorDeLista = (sector) => SECTORES_EMPRESA.includes(sector)
