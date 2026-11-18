// Añade ?v=<huella> a los enlaces de los iconos de index.html (favicon, apple-touch-icon) al
// compilar. Si se cambia un icono (npm run iconos:herramientas), cambia su URL y el navegador lo
// pide de nuevo en vez de seguir con el de su caché (el favicon se guarda aparte y no se refresca
// al recargar). La huella sale del contenido del archivo en public/.
import { createHash } from 'node:crypto'
import { readFileSync, existsSync } from 'node:fs'
import { join } from 'node:path'

export function versionarIconos() {
  let publicDir = ''
  return {
    name: 'versionar-iconos',
    apply: 'build',
    configResolved(config) { publicDir = config.publicDir },
    transformIndexHtml(html) {
      return html.replace(/(href=")\/(favicon\.ico|favicon\.png|apple-touch-icon\.png)(")/g, (todo, a, archivo, c) => {
        const ruta = join(publicDir, archivo)
        if (!existsSync(ruta)) return todo
        const huella = createHash('md5').update(readFileSync(ruta)).digest('hex').slice(0, 8)
        return `${a}/${archivo}?v=${huella}${c}`
      })
    },
  }
}
