// Asistente «Ajuste del reto» (/proyectos/crear y /proyectos/:uuid/editar → StartupDayWizard.vue).
// Cuatro pasos en la misma ruta: cada bloque solo existe en su paso, así que el tour enseña solo
// los del paso visible (el motor salta los que no están en pantalla). También sirve para el plan
// de proyecto, que usa el mismo asistente.
export default {
  rutas: ['startup-day-crear', 'startup-day-editar'],
  titulo: 'Ajuste del reto',
  pasos: [
    { el: 'sdw-cabecera', titulo: 'Ajuste del reto',
      texto: 'Aquí haces tuyo un reto: eliges el curso, los módulos, los RA y CE, la metodología y los objetivos. Lo que viene de la empresa y el diseño del reto se copia solo. Al terminar, lo validas y lo llevas al aula.' },
    { el: 'sdw-ayuda', titulo: 'Ayuda',
      texto: 'Abre una guía corta del paso en el que estás.' },
    { el: 'sdw-chips', titulo: 'De un vistazo',
      texto: 'La empresa, el curso y el nivel del reto elegido. A la derecha ves si hay cambios sin guardar: se guardan al pulsar «Siguiente».' },
    { el: 'sdw-pasos', titulo: 'Los cuatro pasos',
      texto: 'Reto, Currículo, Objetivos y Validar. Pulsa un paso ya alcanzado para volver a él sin perder nada.' },

    // Paso 1 · Reto y curso
    { el: 'sdw-ajuste-existente', titulo: 'Ya lo estabas ajustando',
      texto: 'Ya tienes un ajuste de este reto. Puedes seguir con ese o crear otro distinto.' },
    { el: 'sdw-elegir-reto', titulo: 'Elige el reto',
      texto: 'Busca en la biblioteca el reto que quieres trabajar. Al elegirlo se rellenan solos la empresa, la familia, el ciclo y los módulos.' },
    { el: 'sdw-reto', titulo: 'El reto elegido',
      texto: 'El reto del que parte tu ajuste. Puedes abrir su ficha completa o, mientras lo creas, cambiarlo por otro.' },
    { el: 'sdw-datos', titulo: 'Datos del reto ajustado',
      texto: 'El título con el que lo vas a trabajar y, si el reto vale para 1º y 2º, el curso con el que lo harás. Comprueba también tus datos como docente responsable.' },

    // Paso 2 · Currículo
    { el: 'sdw-race', titulo: 'RA y CE',
      texto: 'Los resultados de aprendizaje y criterios de evaluación que propone el reto. Quita los que no vayas a trabajar; si cambias la selección, puedes restaurar la del reto.' },
    { el: 'sdw-ia-race', titulo: 'Sugerir con IA',
      texto: 'La IA propone RA y CE del catálogo oficial para tus módulos. Llegan marcados como «Sugerencia IA» para que los revises.' },
    { el: 'sdw-race-ampliar', titulo: 'Añadir más',
      texto: '«Seleccionar otros» abre el catálogo oficial; «Agregar manualmente», para los que no estén en él.' },

    // Paso 3 · Metodología y objetivos
    { el: 'sdw-metodologia', titulo: 'Metodología',
      texto: 'Cómo se organizará el trabajo de los grupos y un resumen del reto ajustado. Con «Sugerir con IA» tienes un borrador para retocar. Las sesiones se planifican después, en el encuentro.' },
    { el: 'sdw-plan', titulo: 'Plan de prácticas',
      texto: 'Solo en un plan de proyecto: las horas estimadas y qué espera la empresa del alumnado durante las prácticas.' },
    { el: 'sdw-objetivos', titulo: 'Objetivos',
      texto: 'Qué quieres que consiga tu alumnado, alineado con los RA y con lo que necesita la empresa. Acepta o descarta las sugerencias de la IA y ordénalos.' },
    { el: 'sdw-kpis', titulo: 'Indicadores de éxito',
      texto: 'Opcionales, pero hacen el reto evaluable: cómo sabrás si se han cumplido los objetivos.' },

    // Paso 4 · Revisar y validar
    { el: 'sdw-resumen', titulo: 'Revisa el resumen',
      texto: 'Título, empresa y docente, y desplegables con módulos, objetivos e indicadores. Repásalo antes de validar.' },
    { el: 'sdw-mas-ajustes', titulo: 'Más ajustes',
      texto: 'La empresa, la fundamentación y el diseño del reto vienen del reto original. Ábrelo solo si necesitas cambiar algo.' },
    { el: 'sdw-portada', titulo: 'Imagen de portada',
      texto: 'Opcional: la imagen que verás en la tarjeta del reto ajustado. Elige una del banco o sube la tuya.' },
    { el: 'sdw-estado', titulo: 'Estado',
      texto: 'Déjalo en edición para seguir más tarde, archívalo o vuelve a la lista de tus retos ajustados.' },
    { el: 'sdw-validar', titulo: 'Validar y terminar',
      texto: 'Validar significa «lo he revisado y lo quiero trabajar». Una vez validado, podrás llevarlo al aula en un encuentro creativo.' },
    { el: 'sdw-empezar', titulo: '¿Quieres empezar ya?',
      texto: 'Ya está validado. «Empezar hoy» crea el encuentro con la fecha de hoy para proyectar el QR; «Otro día» te deja elegir la fecha y preparar los grupos.' },

    // Comunes
    { el: 'sdw-botones', titulo: 'Anterior y Siguiente',
      texto: '«Siguiente» guarda lo que has hecho y te lleva al paso siguiente; «Anterior» vuelve atrás sin perder nada.' },
    { el: 'sdw-resumen-lateral', titulo: 'Resumen lateral',
      texto: 'Tu reto ajustado tal como va quedando, lo que falta antes de continuar y la ayuda de la IA para el paso en el que estás.' },
  ],
}
