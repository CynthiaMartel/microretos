// Recorrido del menú lateral y de la barra superior (Guía → «Recorrido del menú»).
// Las entradas salen de GRUPOS_NAV, así que una entrada nueva del panel entra sola en el
// recorrido con su `tip`; aquí solo se amplía la explicación de cada una.
import { GRUPOS_NAV } from '../config/navegacion.js'

const TEXTOS = {
  inicio: 'Tu punto de partida: lo que tienes pendiente y accesos directos a lo que más usas.',
  'retos-proyectos': 'Aquí empieza todo. Genera un reto con IA o elige uno de la biblioteca y ajústalo a tu programación: curso, módulos, RA y CE y objetivos. En «Mis retos ajustados» tienes los que preparas, los validados y los completados.',
  'encuentros-grupos': 'Lleva al aula un reto ajustado y validado: crea el encuentro (fecha, clase y grupos), sigue el avance de cada grupo fase a fase y evalúa sus RA y CE.',
  'planes-proyecto': 'La segunda etapa, durante las prácticas: un plan de proyecto amplía un reto ajustado para trabajarlo en la empresa, con su cronograma de hitos.',
  alumnado: 'Da acceso a tu alumnado con el QR o el código del encuentro, consulta el listado con su grupo, fase y nota, y mira las pantallas tal como las ve el alumnado.',
  empresas: 'Las empresas colaboradoras de tu centro y sus datos de contacto. Al terminar cada reto, la empresa valora las soluciones de tus grupos.',
  calendario: 'Tu día a día en un calendario: encuentros, hitos de los planes, notas y tareas.',
  'agenda-practicas': 'Cómo avanza en la empresa el alumnado de tus planes: fase de cada grupo, retrasos según los hitos y horas anotadas.',
  notificaciones: 'Avisos de las empresas, de tus grupos e invitaciones de otros docentes. El número naranja indica cuántos no has leído.',
  recursos: 'Cómo funciona DUALAB, el recorrido completo de las dos etapas y material de ayuda.',
}

const ADMINISTRACION = [
  { el: 'nav-usuarios', titulo: 'Usuarios', texto: 'Gestiona las cuentas de docentes y empresas: altas, roles y bloqueos.' },
  { el: 'nav-base-datos', titulo: 'Base de datos', texto: 'Empresas, centros educativos, familias y ciclos del ecosistema DUALAB.' },
  { el: 'nav-papelera', titulo: 'Papelera', texto: 'Lo que se ha eliminado: restáuralo o bórralo definitivamente.' },
]

export function pasosMenu(auth) {
  const entradas = GRUPOS_NAV.flatMap(g => g.items)
    .filter(i => !i.proximamente && auth.canAccess(i.routeName))
    .map(i => ({ el: `nav-${i.key}`, panel: true, lado: 'derecha', titulo: i.label, texto: TEXTOS[i.key] ?? i.tip }))

  return [
    { titulo: 'Te enseñamos el menú',
      texto: 'Un recorrido rápido por el menú lateral y la barra superior. Puedes avanzar con «Siguiente» o con las flechas del teclado, y salir cuando quieras con la ✕ o Escape.' },
    { el: 'tb-menu', titulo: 'Abre el menú', texto: 'En el móvil y la tablet el menú está plegado: ábrelo y ciérralo con este botón.' },
    ...entradas,
    ...ADMINISTRACION.map(p => ({ ...p, panel: true, lado: 'derecha' })),
    { el: 'nav-instalar', panel: true, lado: 'derecha', titulo: 'Instalar app',
      texto: 'Instala DUALAB en tu ordenador o tu móvil para abrirla como una app más, con su icono. Solo aparece si aún no la tienes instalada.' },
    { el: 'tb-conoce', titulo: 'Conoce DUALAB', texto: 'El recorrido completo de la plataforma, «¿Qué es DUALAB?» y las guías, en la sección Recursos.' },
    { el: 'tb-guia', titulo: 'Guía', texto: 'Vuelve aquí cuando quieras. «Recorrido del menú» repite este recorrido y «Esta pantalla» te explica la pantalla en la que estás.' },
    { el: 'tb-usuario', titulo: 'Tu cuenta', texto: 'Tus datos en «Mi usuario» y «Cerrar sesión».' },
    { titulo: '¡Listo!',
      texto: 'Ya conoces el menú. Entra en cualquier sección y pulsa «Guía» → «Esta pantalla» para ver qué puedes hacer en ella.' },
  ]
}
