// Hubs del menú lateral (/seccion/:seccion → SeccionHub.vue). El recorrido sale del propio
// contenido de config/navegacion.js (SECCIONES), así que un hub o una tarjeta nueva entra sola.
import { SECCIONES } from '../../config/navegacion.js'

// Texto plano de los segmentos de ConceptoClave (sin enlaces ni negritas)
const textoSegmentos = (segmentos = []) =>
  segmentos.map(s => (s.br ? '\n' : s.t ?? '')).join('').replace(/[ \t]+\n/g, '\n').trim()

const esAccion = (card) => !!(card.ancho || card.integrada)

function pasosHub({ route }) {
  const s = SECCIONES[route.params.seccion]
  if (!s) return []
  const nombre = [s.titulo, s.destacado].filter(Boolean).join(' ')
  const pasos = [
    { el: 'hub-cabecera', titulo: nombre, texto: s.subtitulo },
  ]
  if (s.conceptos?.length) {
    pasos.push({ el: 'hub-conceptos', titulo: 'La idea clave',
      texto: `${s.conceptos.map(c => textoSegmentos(c.segmentos)).join('\n\n')}\n\nLos enlaces subrayados de este recuadro te llevan directamente a cada herramienta.` })
  }
  for (const card of s.cards ?? []) {
    pasos.push({ el: `hub-card-${card.titulo}`, titulo: card.titulo,
      texto: card.proximamente
        ? `${card.desc} Está en preparación: pronto podrás usarlo.`
        : `${card.desc}${esAccion(card) ? ' Pulsa la tarjeta para abrirla.' : ''}` })
  }
  if (s.accesoRapido) {
    pasos.push({ el: 'hub-acceso', titulo: 'Dar acceso al encuentro',
      texto: 'Los encuentros que tienes hoy, listos para proyectar el QR en el aula. Desde aquí también puedes dar acceso a cualquier otro encuentro.' })
  }
  const b = s.bloqueSecundario
  if (b) {
    pasos.push({ el: 'hub-bloque', titulo: b.titulo, texto: b.texto })
    for (const card of b.cards ?? []) {
      pasos.push({ el: `hub-bloque-${card.titulo}`, titulo: card.etiqueta ? `${card.titulo} · ${card.etiqueta}` : card.titulo, texto: card.desc })
    }
  }
  if (s.flujo) {
    const conConcepto = s.flujo.fases.some(f => f.concepto)
    pasos.push({ el: 'hub-flujo', lado: 'arriba', titulo: s.flujo.titulo,
      texto: `${s.flujo.subtitulo || 'El recorrido de esta sección, paso a paso.'}${conConcepto ? ' Pulsa el nombre destacado de una fase para ver qué significa.' : ''}` })
    pasos.push({ el: 'hub-como-funciona', titulo: '¿Cómo funciona DUALAB?',
      texto: 'Abre la explicación completa de la plataforma: qué es un reto, un reto ajustado, un encuentro y cómo encajan.' })
  }
  return pasos
}

export default {
  rutas: ['seccion'],
  titulo: ({ route }) => {
    const s = SECCIONES[route.params.seccion]
    return s ? [s.titulo, s.destacado].filter(Boolean).join(' ') : 'Sección'
  },
  pasos: pasosHub,
}
