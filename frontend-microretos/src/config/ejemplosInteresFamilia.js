// Ejemplo de «interés» (para qué quiere la empresa al alumnado de una familia) para los
// placeholders: modal de empresa y paso 3 del Generador. Las familias de FP de la tabla `familias`; se busca
// sin tildes ni mayúsculas y, si la familia no está, se usa el genérico.
const EJEMPLOS_INTERES = {
  'actividades fisicas y deportivas': 'Área de clientes: programa de actividades para socios mayores de 60 años',
  'aeronautica': 'Hangar: plan de mantenimiento preventivo de la flota de avionetas',
  'administracion y gestion': 'Departamento de administración: ordenar la facturación y los cobros pendientes',
  'agraria': 'Explotación: plan de riego eficiente para reducir el consumo de agua',
  'artes graficas': 'Departamento de comunicación: rediseño del catálogo impreso de productos',
  'artes y artesanias': 'Taller: colección de piezas artesanales para la tienda online',
  'biotecnologia': 'Laboratorio: ensayo de un nuevo cultivo celular y su control de calidad',
  'comercio y marketing': 'Departamento de marketing: campaña de lanzamiento en redes',
  'edificacion y obra civil': 'Oficina técnica: presupuesto y planificación de la reforma de una nave',
  'electricidad y electronica': 'Mantenimiento: automatizar la iluminación de las instalaciones',
  'energia y agua': 'Instalaciones: estudio de autoconsumo con placas solares',
  'fabricacion mecanica': 'Producción: rediseñar una pieza para reducir el desperdicio de material',
  'hosteleria y turismo': 'Restaurante: nueva carta de temporada con producto local',
  'imagen personal': 'Salón: servicio de asesoría de imagen para eventos',
  'imagen y sonido': 'Departamento de comunicación: vídeo corporativo para la web',
  'industrias alimentarias': 'Producción: nuevo producto sin lactosa y su control de calidad',
  'industrias extractivas': 'Cantera: plan de seguridad para las operaciones de voladura',
  'informatica y comunicaciones': 'Departamento técnico: app de reservas para los clientes',
  'instalacion y mantenimiento': 'Mantenimiento: plan preventivo de la maquinaria de la planta',
  'logistica y almacen': 'Almacén: reorganizar la ubicación del stock para preparar pedidos más rápido',
  'madera, mueble y corcho': 'Taller: diseño de una línea de mobiliario a medida',
  'maritimo-pesquera': 'Flota: registro digital de capturas y mantenimiento de las embarcaciones',
  'quimica': 'Laboratorio: control de calidad de un nuevo producto de limpieza',
  'sanidad': 'Clínica: protocolo de atención y seguimiento de pacientes',
  'seguridad y medio ambiente': 'Calidad y medio ambiente: plan de gestión de residuos',
  'servicios socioculturales y a la comunidad': 'Área social: programa de actividades para las familias usuarias',
  'telecomunicaciones': 'Departamento técnico: despliegue de la red wifi en las instalaciones de un cliente',
  'textil, confeccion y piel': 'Taller: colección de uniformes de trabajo personalizados',
  'transporte y mantenimiento de vehiculos': 'Taller: protocolo de revisión de la flota de furgonetas',
  'vidrio y ceramica': 'Producción: nueva línea de piezas cerámicas decorativas',
}
const EJEMPLO_GENERICO = 'Departamento de marketing: campaña de lanzamiento en redes'
const normalizar = (t) => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
export const ejemploInteres = (nombre) => EJEMPLOS_INTERES[normalizar(nombre)] ?? EJEMPLO_GENERICO
