// Iconos de cada herramienta de DuaLab: el logo en blanco (silueta) sobre el color de la
// herramienta y una pastilla con su letra, para distinguirlas en las pestañas y en la pantalla
// de inicio de la tablet. La app DuaLab conserva el logo a color (npm run iconos).
//   npm run iconos:herramientas            → todas
//   node pwa/iconos-herramientas.mjs nexo  → solo una
//   node pwa/iconos-herramientas.mjs --barra [nombres] → solo el icono de la barra superior
// El icono de la barra superior de cada herramienta (`barra`) es el mismo que el favicon, a más
// resolución: así la barra y la pestaña se reconocen igual.
// En la pestaña (favicon, 16–48 px) la letra no se leería: allí solo va el fondo y el logo,
// más grande. Los iconos grandes (inicio de la tablet, PWA) llevan la pastilla.
import sharp from 'sharp'
import { mkdir, writeFile } from 'node:fs/promises'

const raiz = new URL('../', import.meta.url).pathname
const LOGO = new URL('./icono-dualab.png', import.meta.url).pathname

const HERRAMIENTAS = {
  comercial: { color: '#509928', letra: 'C', destino: 'comercial/public', pwa: true, barra: 'comercial/icono-barra.png' },
  central: { color: '#17283E', letra: 'DC', destino: 'central/public', pwa: true, barra: 'central/icono-barra.png' },
  nexo: { color: '#FF8920', letra: 'N', destino: 'nexo/public', pwa: true },
  // Misma build que la web pública: el router cambia el favicon al entrar en /gestion-web
  'gestion-web': {
    color: '#19A7A8', letra: 'W', destino: '../FRONTOFFICE/dualab_proyecto/frontend/public/gestion-web-icono', sinIco: true,
    barra: '../FRONTOFFICE/dualab_proyecto/frontend/src/assets/gestion-web-barra.png',
  },
}

// Barra superior: 44 px a la vista, a 2× y un poco más para pantallas de alta densidad
async function generarBarra(nombre) {
  const h = HERRAMIENTAS[nombre]
  if (!h.barra) return
  await writeFile(raiz + h.barra, await icono(h, 128, { redondeado: true }))
  console.log(`✓ ${nombre} (barra) → ${h.barra}`)
}

// Logo en blanco: se conserva solo la forma (canal alfa) del logo a color
async function logoBlanco(lado) {
  const alfa = await sharp(LOGO)
    .resize(lado, lado, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .ensureAlpha().extractChannel(3).toBuffer()
  return sharp({ create: { width: lado, height: lado, channels: 3, background: '#ffffff' } })
    .joinChannel(alfa).png().toBuffer()
}

function pastilla(lado, letra, color) {
  const k = lado / 512
  const alto = 150 * k, ancho = (letra.length > 1 ? 250 : 180) * k, margen = 14 * k
  const x = lado - ancho - margen, y = lado - alto - margen
  return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${lado}" height="${lado}">
    <rect x="${x}" y="${y}" width="${ancho}" height="${alto}" rx="${alto / 2}" fill="#fff" stroke="${color}" stroke-width="${14 * k}"/>
    <text x="${x + ancho / 2}" y="${y + alto / 2 + 38 * k}" text-anchor="middle" font-family="Arial Black, Helvetica Neue, Arial"
          font-weight="900" font-size="${110 * k}" fill="${color}">${letra}</text></svg>`)
}

// redondeado: esquinas propias (favicon, PWA «any»); sin redondear cuando el sistema recorta
// (apple-touch-icon, maskable). zona: fracción útil del lienzo (maskable: solo el 80 % central).
async function icono(h, lado, { conLetra, redondeado, zona = 1 }) {
  const fondo = Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${lado}" height="${lado}">
    <rect width="${lado}" height="${lado}" rx="${redondeado ? lado * 0.22 : 0}" fill="${h.color}"/></svg>`)
  const util = Math.round(lado * zona)
  const borde = Math.round((lado - util) / 2)
  // Con letra, el logo sube un poco a la izquierda para dejar sitio a la pastilla
  const logoLado = Math.round(util * (conLetra ? 0.66 : 0.8))
  const desplaz = conLetra ? Math.round(util * 0.06) : 0
  const pos = borde + Math.round((util - logoLado) / 2) - desplaz
  const capas = [{ input: await logoBlanco(logoLado), left: pos, top: pos }]
  if (conLetra) {
    const p = await sharp(pastilla(util, h.letra, h.color)).png().toBuffer()
    capas.push({ input: p, left: borde, top: borde })
  }
  return sharp(fondo).composite(capas).png().toBuffer()
}

// .ico con un único PNG dentro (válido en todos los navegadores actuales)
function ico(png, lado) {
  const cab = Buffer.alloc(22)
  cab.writeUInt16LE(0, 0); cab.writeUInt16LE(1, 2); cab.writeUInt16LE(1, 4)
  cab.writeUInt8(lado, 6); cab.writeUInt8(lado, 7)
  cab.writeUInt16LE(1, 10); cab.writeUInt16LE(32, 12)
  cab.writeUInt32LE(png.length, 14); cab.writeUInt32LE(22, 18)
  return Buffer.concat([cab, png])
}

async function generar(nombre) {
  const h = HERRAMIENTAS[nombre]
  const dir = raiz + h.destino
  await mkdir(dir, { recursive: true })
  const escribir = (archivo, buf) => writeFile(`${dir}/${archivo}`, buf)

  await escribir('favicon.png', await icono(h, 64, { redondeado: true }))
  if (!h.sinIco) await escribir('favicon.ico', ico(await icono(h, 48, { redondeado: true }), 48))
  await escribir('apple-touch-icon.png', await icono(h, 180, { conLetra: true }))
  if (h.pwa) {
    await escribir('pwa-192x192.png', await icono(h, 192, { conLetra: true, redondeado: true }))
    await escribir('pwa-512x512.png', await icono(h, 512, { conLetra: true, redondeado: true }))
    await escribir('maskable-icon-512x512.png', await icono(h, 512, { conLetra: true, zona: 0.8 }))
  }
  console.log(`✓ ${nombre} → ${h.destino}`)
  await generarBarra(nombre)
}

const args = process.argv.slice(2)
const soloBarra = args.includes('--barra')
const pedidas = args.filter(a => a !== '--barra')
for (const nombre of pedidas.length ? pedidas : Object.keys(HERRAMIENTAS)) {
  if (!HERRAMIENTAS[nombre]) throw new Error(`Herramienta desconocida: ${nombre}`)
  await (soloBarra ? generarBarra(nombre) : generar(nombre))
}
