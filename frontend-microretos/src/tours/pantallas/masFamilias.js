// Más familias profesionales (/retos/familias → MasFamilias.vue): el resto de familias que no
// caben en el Explorador de retos. Pantalla de paso: elegir una familia lleva al explorador filtrado.
export default {
  rutas: ['mas-familias'],
  titulo: 'Más familias profesionales',
  pasos: [
    { el: 'fam-cabecera', titulo: 'Más familias profesionales',
      texto: 'Aquí están el resto de familias profesionales que no salen destacadas en el Explorador de retos. Úsala para encontrar retos de familias menos habituales.' },
    { el: 'fam-lista', titulo: 'Todas las familias',
      texto: 'Cada tarjeta es una familia profesional. Están ordenadas igual que en el explorador, a continuación de las que ya se ven allí.' },
    { el: 'fam-tarjeta', titulo: 'Elige una familia',
      texto: 'La etiqueta de la esquina te dice cuántos retos tiene. Pulsa la tarjeta y volverás al Explorador de retos con esa familia ya filtrada.' },
    { el: 'fam-vacio', titulo: 'No hay más familias',
      texto: 'Todas las familias ya se ven en el Explorador de retos. Vuelve atrás para buscar allí.' },
    { titulo: '¿Y ahora?',
      texto: 'En el explorador abre el reto que te interese con «Ver reto» y, si te convence, pulsa «Trabajar este reto» para empezar el Ajuste del reto.' },
  ],
}
