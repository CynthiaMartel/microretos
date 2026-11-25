// Pantalla de acceso que se proyecta en el aula (/proyectos/:uuid/pantalla-acceso → PantallaAcceso.vue).
export default {
  rutas: ['pantalla-acceso'],
  titulo: 'Pantalla de acceso',
  pasos: [
    { el: 'pqr-cabecera', titulo: 'Proyecta esta pantalla',
      texto: 'Es lo que el alumnado ve en el proyector al empezar el encuentro: el código de la clase y el QR de cada grupo, para entrar en su espacio de trabajo.' },
    { el: 'pqr-codigo-clase', titulo: 'Código de la clase',
      texto: 'La primera vez, el alumnado lo escribe en la página de unirse, elige su grupo y queda dentro con su alias.' },
    { el: 'pqr-instrucciones', titulo: 'Cómo entrar',
      texto: 'Las instrucciones para el alumnado, con la dirección web a la que tienen que ir. Léelas en voz alta la primera vez.' },
    { el: 'pqr-grupos', titulo: 'Un QR por grupo',
      texto: 'Cada grupo tiene su QR y su código. Escanear el QR lleva directamente a su espacio, sin elegir nada.' },
    { el: 'pqr-grupo', titulo: 'Código del grupo',
      texto: 'Que cada grupo lo apunte: les sirve para volver a su espacio de trabajo otro día y seguir donde lo dejaron.' },
    { el: 'pqr-volver', titulo: 'Volver',
      texto: 'Cuando todos hayan entrado, vuelve a la lista para dar acceso a otro encuentro.' },
    { titulo: 'Y mientras trabajan…',
      texto: 'Los grupos avanzan por las fases en su espacio. Tú puedes ver su avance en «Seguimiento de grupos», dentro de la biblioteca de encuentros.' },
  ],
}
