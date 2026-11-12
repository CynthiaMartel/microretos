import { defineConfig } from '@vite-pwa/assets-generator/config'

// Iconos de la app comercial (comercial.dualab.es), con `npm run iconos:comercial`.
// Mismas medidas y fondo azul noche que pwa-assets.config.js; la imagen es el logo de DuaLab
// con la insignia verde de maletín (pwa/icono-comercial.mjs), para que en la tablet del
// comercial no se confunda con la app de DuaLab.
// El generador escribe junto a la imagen: el script la crea en .pwa-comercial/, genera allí
// y pasa el resultado a comercial/public/.
export default defineConfig({
  headLinkOptions: { preset: '2023' },
  preset: {
    transparent: {
      sizes: [64, 192, 512],
      favicons: [[48, 'favicon.ico']],
      padding: 0.05,
    },
    maskable: {
      sizes: [512],
      padding: 0.3,
      resizeOptions: { background: '#17283E' },
    },
    apple: {
      sizes: [180],
      padding: 0.2,
      resizeOptions: { background: '#17283E' },
    },
  },
  images: ['.pwa-comercial/icono-comercial.png'],
})
