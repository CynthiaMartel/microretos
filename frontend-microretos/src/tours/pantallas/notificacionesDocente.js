// Notificaciones (/notificaciones → NotificacionesDocente.vue). El superadmin tiene además el apartado
// «Captación comercial» (?apartado=captacion).
export default {
  rutas: ['notificaciones'],
  titulo: 'Notificaciones',
  pasos: ({ route, auth }) => {
    const captacion = !!auth?.isSuperAdmin && route.query.apartado === 'captacion'
    const pasos = [
      { el: 'notif-cabecera', titulo: 'Notificaciones',
        texto: 'Los avisos de la plataforma: la valoración de las empresas, retos ajustados que esperan tu validación, fases completadas por tus grupos e invitaciones a encuentros de otros docentes.' },
      { el: 'notif-leer-todas', titulo: 'Marcar todas como leídas',
        texto: 'Si ya las has revisado, déjalas todas como leídas de una vez. El número de la campana del menú se pone a cero.' },
      { el: 'notif-apartados', titulo: 'Avisos y captación',
        texto: '«Avisos» son tus notificaciones. «Captación comercial» reúne las empresas y los centros que llegan de los comerciales y de la web, para validarlos o descartarlos.' },
    ]
    if (captacion) {
      pasos.push(
        { el: 'notif-captacion-empresas', titulo: 'Empresas captadas',
          texto: 'Las empresas que han entrevistado los comerciales o que han escrito desde la web, con sus retos en borrador. Al validarlas se dan de alta y se colocan en uno o varios centros; también puedes fusionarlas con una empresa que ya exista.' },
        { el: 'notif-captacion-centros', titulo: 'Centros captados',
          texto: 'Los centros educativos interesados. Valídalos dando de alta el centro o enlazándolo con uno que ya exista, o descártalos.' },
      )
      return pasos
    }
    pasos.push(
      { el: 'notif-filtros', titulo: 'Filtra',
        texto: 'Quédate con las no leídas o con un tipo: empresas, validación, grupos o encuentros. Junto a «No leídas» ves cuántas te quedan.' },
      { el: 'notif-aviso', titulo: 'Cada aviso',
        texto: 'El punto naranja indica que no lo has leído. Al pulsarlo se marca como leído y te lleva a la pantalla donde actuar: el reto ajustado, el grupo o el encuentro.' },
      { el: 'notif-mas', titulo: 'Cargar más',
        texto: 'Las notificaciones se cargan por tandas. Pulsa para ver las más antiguas.' },
    )
    return pasos
  },
}
