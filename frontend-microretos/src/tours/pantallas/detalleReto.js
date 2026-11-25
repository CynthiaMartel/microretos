// Ficha del reto (/retos/:id → DetalleMicroreto.vue). El contenido lo pinta FichaReto.vue, que
// comparten otras vistas: sus bloques se señalan con selectores dentro de [data-tour="det-ficha"]
// en vez de marcar el componente común.
const FICHA = '[data-tour="det-ficha"]'

export default {
  rutas: ['detalle-microreto'],
  titulo: 'Ficha del reto',
  pasos: ({ auth }) => {
    const empresa = !!auth?.isEmpresa
    return [
      { el: 'det-cabecera', titulo: 'Ficha del reto',
        texto: empresa
          ? 'El reto completo, tal como lo verá el alumnado: tu empresa, la necesidad que plantea y qué se le pide.'
          : 'Todo el reto en una página. Léelo con calma antes de decidir si lo trabajas en el aula: la empresa, su necesidad y qué se pide al alumnado.' },
      { el: 'det-pdf', titulo: 'Descargar PDF',
        texto: 'Descarga la ficha en PDF para leerla sin conexión, imprimirla o compartirla con tu departamento.' },
      { el: 'det-qr', titulo: 'Generar QR',
        texto: 'Crea un acceso temporal con QR para que el alumnado vea este reto desde su móvil sin iniciar sesión. Caduca a las 48 horas y puedes revocarlo cuando quieras.' },
      { el: 'det-trabajar', titulo: 'Trabajar este reto',
        texto: 'Si el reto te convence, pulsa aquí: se abre el Ajuste del reto con este reto ya elegido para adaptarlo a tu curso, tus módulos, RA y CE y objetivos.' },
      { el: `${FICHA} > div:first-child > div:first-child`, titulo: 'La empresa y la pregunta',
        texto: 'El título del reto, la pregunta que deberá responder el alumnado y los datos de la empresa que lo plantea.' },
      { el: `${FICHA} .border-l-4`, titulo: 'Pregunta del reto',
        texto: 'Es el corazón del reto: la pregunta a la que cada grupo tendrá que dar una solución. Encima tienes el diagnóstico de la empresa, su día a día y sus dificultades.' },
      { el: `${FICHA} div.pt-2:has(> .bg-amber-50)`, titulo: 'Módulos, RA y CE',
        texto: empresa
          ? 'Los módulos, RA y CE del ciclo que se trabajan con este reto.'
          : 'Los módulos, RA y CE que la IA cree que mejor encajan. No son definitivos: los cambias en el Ajuste del reto.' },
      { el: `${FICHA} > div:nth-child(2)`, titulo: 'Guía de implementación',
        texto: 'Consejos solo para ti, que el alumnado no ve: cómo plantear el reto en clase y qué tener en cuenta en cada momento.' },
      ...(empresa ? [] : [{ titulo: '¿Y ahora?',
        texto: 'Pulsa «Trabajar este reto» para hacerlo tuyo en el Ajuste del reto. Cuando lo valides, tendrás tu reto ajustado listo para preparar el encuentro creativo.' }]),
    ]
  },
}
