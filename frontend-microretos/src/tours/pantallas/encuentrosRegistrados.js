// Biblioteca de encuentros (/encuentros → EncuentrosRegistrados.vue). Dos pestañas sobre la misma
// lista: «Gestión» (por defecto) y «Seguimiento de grupos» (?ver=seguimiento).
export default {
  rutas: ['encuentros-registrados'],
  titulo: ({ route }) => (route.query.ver === 'seguimiento' ? 'Seguimiento de grupos' : 'Biblioteca de encuentros'),
  pasos: ({ route }) => {
    const seguimiento = route.query.ver === 'seguimiento'
    const comunes = [
      { el: 'encr-cabecera', titulo: 'Encuentros y grupos',
        texto: 'Todos tus encuentros con el alumnado: aquí gestionas fechas, códigos y grupos y, cuando ya trabajan, sigues su avance fase a fase.' },
      { el: 'encr-nuevo', titulo: 'Nuevo encuentro',
        texto: 'Crea un encuentro a partir de un reto ajustado validado: fecha, Clase y reparto del alumnado en grupos.' },
      { el: 'encr-pestanas', titulo: 'Gestión o Seguimiento',
        texto: seguimiento
          ? 'Estás en «Seguimiento de grupos»: en qué fase va cada grupo, lo que entrega y su diagnóstico. En «Gestión» tienes fechas, códigos y reparto de grupos.'
          : 'Estás en «Gestión»: fechas, códigos de acceso, reparto de grupos y compartir. En «Seguimiento de grupos» ves cómo avanza cada grupo. El número es cuántos encuentros hay en cada una.' },
      { el: 'encr-filtros', titulo: 'Busca y filtra',
        texto: 'Busca por el título del reto ajustado y acota por ciclo, módulo, nivel, Clase, empresa o sector. Arriba eliges el curso académico.' },
      { el: 'encr-mas-filtros', titulo: 'Más filtros',
        texto: seguimiento
          ? 'Abre los filtros rápidos: estado de los grupos, solo los que tienen diagnóstico, familia profesional, de quién es y fechas.'
          : 'Abre los filtros rápidos: si ya tienen código de acceso, familia profesional, de quién es y fechas.' },
      { el: 'encr-filtros-rapidos', titulo: 'Filtros rápidos',
        texto: 'Cada opción muestra cuántos encuentros encajan. «Limpiar» quita todos los filtros de golpe.' },
      { el: 'encr-resultados', titulo: 'Resultados',
        texto: seguimiento
          ? 'Cuántos encuentros y grupos estás siguiendo. Ordena por «Más avance» para ver primero a quien va más adelantado.'
          : 'Cuántos encuentros encajan con tus filtros. Aquí cambias el orden y eliges verlos en cuadrícula o en lista.' },
    ]
    const gestion = [
      { el: 'encr-tarjeta', titulo: 'Cada encuentro',
        texto: 'El curso, la Clase, si ya tiene código de acceso, el reto ajustado que trabaja, la fecha, el alumnado y el ciclo. Pulsa el título para ver toda su información.' },
      { el: 'encr-grupos', titulo: 'Sus grupos',
        texto: 'Los grupos del encuentro y la fase en la que va cada uno. Pulsa uno para ir directo a su seguimiento.' },
      { el: 'encr-ver-seguimiento', titulo: 'Ver seguimiento',
        texto: 'Salta a la pestaña «Seguimiento de grupos» con este encuentro ya abierto.' },
      { el: 'encr-mas-info', titulo: 'Más información',
        texto: 'Abre la ficha del encuentro con sus grupos, el alumnado y los RA y CE que se trabajan.' },
      { el: 'encr-llave', titulo: 'Códigos del alumnado',
        texto: 'La llave guarda el código de acceso de la clase y el código de sugerencia IA: cópialos o genéralos. Desde aquí también proyectas la pantalla de acceso. El punto verde indica que ya hay acceso dado.' },
      { el: 'encr-menu', titulo: 'Más acciones',
        texto: 'Editar grupos, compartir el encuentro con otro docente, ver la ficha del reto original o del reto ajustado, o eliminar el encuentro.' },
    ]
    const seg = [
      { el: 'encr-seg-tarjeta', titulo: 'Cada encuentro',
        texto: 'El reto ajustado, la fecha, el ciclo, la Clase y el estado de sus grupos sin tener que desplegar: completados, en curso, sin iniciar y diagnósticos disponibles.' },
      { el: 'encr-seg-ver-grupos', titulo: 'Ver los grupos',
        texto: 'Despliega los grupos del encuentro con la fase en la que va cada uno y sus miembros (con alias).' },
      { el: 'encr-seg-detalle', titulo: 'Detalle de grupos',
        texto: 'Abre el encuentro con todo lo que ha entregado cada grupo fase a fase, su evaluación y su diagnóstico final.' },
      { el: 'encr-seg-menu', titulo: 'Más acciones',
        texto: 'Más información del encuentro, desplegar todos los grupos, ir a «Evaluación curricular y diagnósticos» o copiar los códigos del alumnado.' },
    ]
    return [
      ...comunes,
      ...(seguimiento ? seg : gestion),
      { el: 'encr-paginacion', titulo: 'Más páginas',
        texto: 'Si tienes muchos encuentros, se reparten en páginas. Muévete entre ellas desde aquí.' },
      seguimiento
        ? { titulo: '¿Y al terminar?',
            texto: 'Cuando los grupos completen sus fases, evalúa sus RA y CE y genera su diagnóstico final desde «Detalle de grupos» o «Evaluación curricular y diagnósticos».' }
        : { titulo: '¿Y ahora?',
            texto: 'El día del encuentro, proyecta la pantalla de acceso desde la llave de la tarjeta para que cada grupo entre en su espacio. Después, sigue su avance en «Seguimiento de grupos».' },
    ]
  },
}
