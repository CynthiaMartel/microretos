import { VitePWA } from 'vite-plugin-pwa'

// Instalable como app (PWA) para las herramientas internas (DuaLab Central, Nexo), con el mismo
// criterio que la comercial (vite.comercial.config.js): solo se guarda la carcasa de la app, nunca
// respuestas de la API; se actualiza con el aviso «Hay una versión nueva» (registerType 'prompt').
// Iconos: npm run iconos:herramientas (pwa/iconos-herramientas.mjs, opción pwa: true).
export const pwaHerramienta = ({ name, shortName, description, themeColor = '#17283E' }) => VitePWA({
  registerType: 'prompt',
  injectRegister: 'script',
  includeAssets: ['favicon.ico', 'favicon.png', 'apple-touch-icon.png'],
  manifest: {
    id: '/',
    name,
    short_name: shortName,
    description,
    lang: 'es',
    start_url: '/',
    scope: '/',
    display: 'standalone',
    theme_color: themeColor,
    background_color: themeColor,
    icons: [
      { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png' },
      { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png' },
      { src: 'maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
    ],
  },
  workbox: {
    globPatterns: ['**/*.{js,css,html,ico,svg,woff2,webp,png}'],
    navigateFallback: 'index.html',
    navigateFallbackDenylist: [/^\/api\//, /^\/sanctum\//],
    cleanupOutdatedCaches: true,
    runtimeCaching: [
      { urlPattern: ({ url }) => url.origin === 'https://fonts.googleapis.com', handler: 'StaleWhileRevalidate', options: { cacheName: 'google-fonts-css' } },
      { urlPattern: ({ url }) => url.origin === 'https://fonts.gstatic.com', handler: 'CacheFirst',
        options: { cacheName: 'google-fonts', expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 * 365 }, cacheableResponse: { statuses: [0, 200] } } },
    ],
  },
})
