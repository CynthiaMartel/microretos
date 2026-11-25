// Dar acceso al alumnado (/pantalla-acceso → PantallaAccesoLista.vue): lanzador para proyectar
// el QR y el código del encuentro en clase.
export default {
  rutas: ['pantalla-acceso-lista'],
  titulo: 'Dar acceso al alumnado',
  pasos: [
    { el: 'acc-cabecera', titulo: 'Dar acceso al alumnado',
      texto: 'El día del encuentro, desde aquí proyectas en el aula el QR y el código para que cada grupo entre en su espacio de trabajo.' },
    { el: 'acc-hoy', titulo: 'En curso hoy',
      texto: 'Los encuentros que tienes hoy, a mano: copia el código de la clase o pulsa «Proyectar» para abrir la pantalla de acceso.' },
    { el: 'acc-filtros', titulo: 'Busca otro encuentro',
      texto: 'Para encuentros de otros días: busca por reto ajustado, código o alias y filtra por clase o curso académico.' },
    { el: 'acc-lista', titulo: 'Otros encuentros',
      texto: 'Solo aparecen los encuentros que ya tienen grupos y código de acceso, del más reciente al más antiguo.' },
    { el: 'acc-codigo', titulo: 'Código de la clase',
      texto: 'Es el código que escribe el alumnado la primera vez para unirse. Con el botón de al lado lo copias para pegarlo donde quieras.' },
    { el: 'acc-proyectar', titulo: 'Proyectar',
      texto: 'Abre a pantalla completa el código de la clase y el QR de cada grupo, listo para proyectar en el aula.' },
    { el: 'acc-ver-mas', titulo: 'Ver más',
      texto: 'Muestra más encuentros de la lista si buscas uno antiguo.' },
    { titulo: '¿Aún no tienes código?',
      texto: 'Si un encuentro no aparece, aún no tiene grupos o código. Créalo en «Crear encuentro» o genera el código desde la llave de su tarjeta en la biblioteca de encuentros.' },
  ],
}
