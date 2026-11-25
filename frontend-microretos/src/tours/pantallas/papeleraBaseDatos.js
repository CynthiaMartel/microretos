// Papelera de la base de datos (/papelera → PapeleraBaseDatos.vue, solo superadmin): lo eliminado
// en Base de datos y en Red DuaLab (Central), con restaurar, borrar definitivo y purga automática.
export default {
  rutas: ['papelera'],
  titulo: 'Papelera',
  pasos: [
    { el: 'pap-cabecera', titulo: 'Papelera',
      texto: 'Aquí va todo lo que se elimina de la base de datos y de Red DuaLab. Mientras esté aquí puedes recuperarlo.' },
    { el: 'pap-vaciar', titulo: 'Vaciar papelera',
      texto: 'Borra para siempre todo lo que hay en la papelera (o solo lo del filtro elegido). Tendrás que escribir VACIAR para confirmar. No se puede deshacer.' },
    { el: 'pap-todos', titulo: 'Todos',
      texto: 'Muestra todos los elementos eliminados juntos, del más reciente al más antiguo.' },
    { el: 'pap-grupo-red', titulo: 'Red DuaLab',
      texto: 'Lo eliminado en Central: captaciones de empresas y centros, retos en borrador, Retos a medida y correos de info@. Pulsa un tipo para ver solo ese.' },
    { el: 'pap-grupo-datos', titulo: 'Base de datos',
      texto: 'Lo eliminado en la base de datos: empresas, centros, familias, ciclos, retos, encuentros… El número de cada botón es cuántos hay.' },
    { el: 'pap-purga', titulo: 'Borrado automático',
      texto: 'Algunos tipos se borran solos cuando llevan cierto tiempo en la papelera. Este aviso te dice cuándo se borra el primero para que lo restaures a tiempo si lo necesitas.' },
    { el: 'pap-item', titulo: 'Cada elemento',
      texto: 'Nombre, tipo y cuándo se eliminó. Si se borra solo, verás cuántos días le quedan (en naranja si queda una semana o menos).' },
    { el: 'pap-restaurar', titulo: 'Restaurar',
      texto: 'Devuelve el elemento a su sitio, tal como estaba antes de eliminarlo.' },
    { el: 'pap-eliminar', titulo: 'Eliminar permanentemente',
      texto: 'Borra el elemento y todos sus datos asociados para siempre. No se puede deshacer y queda registrado en el Historial de Central con tu nombre.' },
    { el: 'pap-vacia', titulo: 'Papelera vacía',
      texto: 'No hay nada eliminado con el filtro elegido. Prueba con «Todos» o con otro tipo.' },
  ],
}
