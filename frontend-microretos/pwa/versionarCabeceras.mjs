// Añade a index.html la huella del .htaccess de public/ al compilar. El service worker guarda
// index.html con las cabeceras que tenía al precachearlo (Permissions-Policy incluida) y solo lo
// renueva si cambia su contenido: sin esto, un cambio de cabeceras en el .htaccess (p. ej. permitir
// el micrófono) no llega nunca a la app instalada. Con la huella cambia index.html, el service
// worker nuevo lo vuelve a pedir y sale el aviso de versión nueva.
import { createHash } from 'node:crypto'
import { readFileSync, existsSync } from 'node:fs'
import { join } from 'node:path'

export function versionarCabeceras() {
  let publicDir = ''
  return {
    name: 'versionar-cabeceras',
    apply: 'build',
    configResolved(config) { publicDir = config.publicDir },
    transformIndexHtml(html) {
      const ruta = join(publicDir, '.htaccess')
      if (!existsSync(ruta)) return html
      const huella = createHash('md5').update(readFileSync(ruta)).digest('hex').slice(0, 8)
      return html.replace('</head>', `    <meta name="dualab-cabeceras" content="${huella}" />\n  </head>`)
    },
  }
}
