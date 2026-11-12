// Imagen base del icono de la app comercial: el logo de DuaLab tal cual (pwa/icono-dualab.png)
// con una insignia verde de maletín abajo a la derecha, para distinguirla de DuaLab en la misma
// tablet. La usa `npm run iconos:comercial` (pwa-assets.comercial.config.js), que le pone el
// fondo azul noche y genera todos los tamaños.
//   node pwa/icono-comercial.mjs <salida.png>
import sharp from 'sharp'

const salida = process.argv[2]
const LADO = 860 // mismo lienzo que icono-dualab.png

// Insignia: círculo verde de empresas con borde azul noche (la separa del logo) y maletín blanco.
// Dentro del lienzo del logo: el generador añade el margen de Android (maskable), así que la
// insignia nunca cae en la zona que recorta el círculo o el squircle.
const R = 165
const CX = LADO - R - 10
const CY = LADO - R - 10
const insignia = `
<svg xmlns="http://www.w3.org/2000/svg" width="${LADO}" height="${LADO}">
  <circle cx="${CX}" cy="${CY}" r="${R}" fill="#509928" stroke="#17283E" stroke-width="26"/>
  <g transform="translate(${CX - 92} ${CY - 92}) scale(7.67)" fill="none" stroke="#fff"
     stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3" y="7.5" width="18" height="12.5" rx="2.2"/>
    <path d="M8.5 7.5V5.6A1.6 1.6 0 0 1 10.1 4h3.8a1.6 1.6 0 0 1 1.6 1.6v1.9"/>
    <path d="M3 12.6c5.6 2.2 12.4 2.2 18 0"/>
    <path d="M12 12.2v2.2"/>
  </g>
</svg>`

// El logo se reduce un poco para que la insignia no tape el pétalo turquesa
const logo = await sharp(new URL('./icono-dualab.png', import.meta.url).pathname)
  .resize(Math.round(LADO * 0.86))
  .toBuffer()

await sharp({ create: { width: LADO, height: LADO, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } } })
  .composite([
    { input: logo, top: 0, left: 0 },
    { input: Buffer.from(insignia), top: 0, left: 0 },
  ])
  .png()
  .toFile(salida)
