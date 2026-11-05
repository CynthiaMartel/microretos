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

// Familias de FP "propias" de cada sector: las que cualquiera asociaría a esa empresa.
// Sirve para destacar los retos cruzados (p. ej. una panadería con un reto de Comercio y
// Marketing), que enseñan que toda empresa necesita perfiles de fuera de su sector.
export const FAMILIAS_PROPIAS_SECTOR = {
  'Tecnología e informática':           ['Informática y Comunicaciones', 'Telecomunicaciones', 'Electricidad y Electrónica'],
  'Consultoría y servicios a empresas': ['Administración y Gestión'],
  'Marketing, publicidad y diseño':     ['Comercio y Marketing', 'Imagen y Sonido', 'Artes Gráficas'],
  'Comercio y distribución':            ['Comercio y Marketing', 'Logística y Almacén'],
  'Industria y fabricación':            ['Fabricación Mecánica', 'Instalación y Mantenimiento', 'Química', 'Madera, Mueble y Corcho', 'Textil, Confección y Piel', 'Vidrio y Cerámica', 'Industrias Extractivas', 'Aeronáutica'],
  'Alimentación y agroalimentario':     ['Industrias Alimentarias', 'Agraria'],
  'Agricultura, pesca y jardinería':    ['Agraria', 'Marítimo-Pesquera'],
  'Construcción e inmobiliaria':        ['Edificación y Obra Civil', 'Madera, Mueble y Corcho'],
  'Energía e instalaciones':            ['Energía y Agua', 'Electricidad y Electrónica', 'Instalación y Mantenimiento'],
  'Automoción':                         ['Transporte y Mantenimiento de Vehículos'],
  'Transporte y logística':             ['Logística y Almacén', 'Transporte y Mantenimiento de Vehículos'],
  'Hostelería, restauración y turismo': ['Hostelería y Turismo', 'Industrias Alimentarias'],
  'Salud, estética y bienestar':        ['Sanidad', 'Imagen Personal', 'Biotecnología'],
  'Servicios sociales y tercer sector': ['Servicios Socioculturales y a la Comunidad'],
  'Cultura, deporte y ocio':            ['Actividades Físicas y Deportivas', 'Artes y Artesanías', 'Imagen y Sonido'],
  'Medio ambiente':                     ['Seguridad y Medio Ambiente', 'Energía y Agua'],
}

// true = la familia del reto no es la del sector de la empresa; null = no se puede saber
// (sector antiguo en texto libre, o reto sin familia).
export const esRetoCruzado = (sector, familia) => {
  const propias = FAMILIAS_PROPIAS_SECTOR[sector]
  if (!propias || !familia) return null
  return !propias.includes(familia)
}
