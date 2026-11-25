// Ficha del reto ajustado (/proyectos/:uuid → StartupDayDetalle.vue). Los botones cambian con el
// estado (sin validar, validado, completado) y con la etapa (plan de proyecto): el motor salta los
// que no están en pantalla. La empresa la ve en solo lectura.
export default {
  rutas: ['startup-day-detalle'],
  titulo: 'Ficha del reto ajustado',
  pasos: ({ auth }) => {
    const empresa = !!auth?.isEmpresa
    return [
      { el: 'sdd-cabecera', titulo: 'Ficha del reto ajustado',
        texto: empresa
          ? 'El reto ajustado completo: cómo lo ha adaptado el docente, con qué RA y CE, y cómo lo han resuelto los grupos.'
          : 'Todo tu reto ajustado en una hoja: qué has ajustado, si está validado y, cuando termina, las soluciones de los grupos. Desde aquí das el siguiente paso.' },
      { el: 'sdd-estado', titulo: 'Estado',
        texto: 'Si es un reto ajustado o un plan de proyecto y en qué punto está: en edición, sin validar, validado, completado o archivado.' },
      { el: 'sdd-validar', titulo: 'Validar',
        texto: 'Cuando hayas revisado módulos, RA y CE y objetivos y quieras trabajarlo con tu alumnado, valídalo. Sin validar no se puede llevar al aula.' },
      { el: 'sdd-encuentro', titulo: 'Crear encuentro',
        texto: 'Ya está validado: crea el encuentro creativo con la fecha, la Clase y los grupos, y proyecta el QR para que el alumnado entre.' },
      { el: 'sdd-cronograma', titulo: 'Crear cronograma',
        texto: 'En un plan de proyecto, las fechas, los hitos y el alumnado de las prácticas en la empresa.' },
      { el: 'sdd-ampliar', titulo: 'Ampliar en un plan',
        texto: 'Convierte este reto ajustado en un plan de proyecto para que el alumnado lo desarrolle en la empresa durante las prácticas.' },
      { el: 'sdd-completar', titulo: 'Marcar completado',
        texto: 'Cuando los grupos hayan terminado de trabajarlo, márcalo como completado. Pasará a la pestaña «Completados».' },
      { el: 'sdd-ir-resolucion', titulo: 'Ver resolución del alumnado',
        texto: 'Baja directamente a las soluciones que han entregado los grupos.' },
      { el: 'sdd-editar', titulo: 'Editar',
        texto: 'Vuelve al Ajuste del reto para cambiar lo que necesites. Si cambias algo importante de uno validado, tendrás que validarlo de nuevo.' },
      { el: 'sdd-acciones', titulo: 'Más acciones',
        texto: empresa
          ? 'Descarga la ficha en PDF o consulta el reto original del que parte.'
          : 'Descarga la ficha en PDF, consulta el reto original del que parte o archívalo si ya no lo vas a usar.' },
      { el: 'sdd-validacion', titulo: 'Validación',
        texto: 'Te recuerda si ya lo has validado. La empresa ya no valida al inicio: valorará el reto al final, con las soluciones de los grupos.' },
      { el: 'sdd-empresa', titulo: 'Empresa y docente',
        texto: 'La empresa que plantea el reto y el docente responsable, con su contacto.' },
      { el: 'sdd-curriculo', titulo: 'Currículo',
        texto: 'Los módulos y los RA y CE que trabajas. Despliega el bloque de RA y CE para ver cada criterio.' },
      { el: 'sdd-reto', titulo: 'El reto',
        texto: 'Lo que viene del reto de la empresa: la pregunta, el diseño, las restricciones, los entregables y la fundamentación.' },
      { el: 'sdd-fases', titulo: 'Metodología y fases',
        texto: 'Cómo se trabajará en el aula: las fases, el calendario de sesiones y la metodología.' },
      { el: 'sdd-objetivos', titulo: 'Objetivos',
        texto: 'Lo que quieres que consiga el alumnado y los indicadores para comprobarlo.' },
      { el: 'sdd-valoracion-empresa', titulo: 'Respuesta de la empresa',
        texto: 'Lo que respondió la empresa sobre este reto ajustado, con sus comentarios.' },
      { el: 'sdd-resolucion', titulo: 'Resolución del alumnado', lado: 'arriba',
        texto: 'Las soluciones de cada grupo, con su avance por fases. El alumnado aparece solo con su alias.' },
      ...(empresa ? [] : [{ titulo: '¿Y ahora?',
        texto: 'Si aún no está validado, valídalo. Si ya lo está, crea el encuentro y llévalo al aula. Al terminar, la empresa valorará el reto con las soluciones de los grupos.' }]),
    ]
  },
}
