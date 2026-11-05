import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { readFileSync } from 'node:fs'

// Identificador de este build: va incrustado en el bundle (__APP_VERSION__) y en
// dist/version.json. Las pestañas abiertas comparan ambos para avisar de un deploy nuevo
// (composables/useNuevaVersion.js).
const APP_VERSION = Date.now().toString(36)

const versionJson = () => ({
  name: 'version-json',
  apply: 'build',
  generateBundle() {
    this.emitFile({ type: 'asset', fileName: 'version.json', source: JSON.stringify({ version: APP_VERSION }) })
  },
})

// En producción dualab.es comparte dominio con el frontoffice (/, /noticias, /centros,
// /contacto… los sirve /home por el .htaccess de la raíz). El service worker solo puede
// contestar con el index.html de esta app a las rutas de esta app: se sacan del router
// (path: '…', con :param → un segmento) y se comparan exactas, así /noticias/:tipo es de
// la app pero /noticias sigue siendo del frontoffice. La raíz "/" nunca entra.
const rutasApp = [...readFileSync(new URL('./src/router/index.js', import.meta.url), 'utf8')
  .matchAll(/path:\s*'([^']+)'/g)]
  .map(([, path]) => path)
  .filter((path) => path !== '/')
  .map((path) => new RegExp(`^${path.replace(/:\w+/g, '[^/]+')}/?(\\?.*)?$`))

const pwa = () => VitePWA({
  // 'prompt': la versión nueva no se activa sola ni recarga a mitad de un formulario. El aviso
  // "Hay una versión nueva" ya existe (useNuevaVersion + version.json) y su recargarLimpio()
  // da de baja el service worker antiguo; al recargar se registra el nuevo.
  registerType: 'prompt',
  injectRegister: 'script',
  includeAssets: ['favicon.ico', 'favicon.png', 'apple-touch-icon.png'],
  manifest: {
    id: '/',
    name: 'DuaLab',
    short_name: 'DuaLab',
    description: 'Un ecosistema de oportunidades entre centros educativos y empresas',
    lang: 'es',
    // /login y no /: la raíz es la home del frontoffice. Con sesión, el router manda
    // a cada rol a su inicio.
    start_url: '/login',
    scope: '/',
    display: 'standalone',
    theme_color: '#17283E',
    background_color: '#F4F7FA',
    icons: [
      { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png' },
      { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png' },
      { src: 'maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
    ],
  },
  workbox: {
    // Solo la "carcasa" de la app. Nunca respuestas de /api ni /sanctum: son datos personales
    // y en los ordenadores de aula los usa más de una persona. version.json tampoco (.json
    // fuera del patrón): tiene que llegar siempre del servidor.
    globPatterns: ['**/*.{js,css,html,ico,svg,woff2}', '*.png'],
    navigateFallback: 'index.html',
    navigateFallbackAllowlist: rutasApp,
    navigateFallbackDenylist: [/^\/api\//, /^\/sanctum\//, /^\/home\//],
    cleanupOutdatedCaches: true,
    runtimeCaching: [
      {
        // Fotos e ilustraciones de /assets: se guardan la primera vez que se ven
        urlPattern: ({ sameOrigin, url }) => sameOrigin && url.pathname.startsWith('/assets/') && /\.(png|jpe?g|webp|gif)$/.test(url.pathname),
        handler: 'CacheFirst',
        options: { cacheName: 'imagenes', expiration: { maxEntries: 60, maxAgeSeconds: 60 * 60 * 24 * 30 } },
      },
      {
        // Tipografías de style.css (Inter, Montserrat) e InicioDocente (Caveat)
        urlPattern: ({ url }) => url.origin === 'https://fonts.googleapis.com',
        handler: 'StaleWhileRevalidate',
        options: { cacheName: 'google-fonts-css' },
      },
      {
        urlPattern: ({ url }) => url.origin === 'https://fonts.gstatic.com',
        handler: 'CacheFirst',
        options: {
          cacheName: 'google-fonts',
          expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 * 365 },
          cacheableResponse: { statuses: [0, 200] },
        },
      },
    ],
  },
})

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '../', '') // lee el .env de la raíz del repo !!

  return {
    plugins: [
      vue(),
      tailwindcss(),
      versionJson(),
      pwa(),
    ],
    define: {
      __APP_VERSION__: JSON.stringify(APP_VERSION),
    },
    server: {
      host: '0.0.0.0',
      port: Number(env.VITE_PORT) || 5173,
      strictPort: true,
      proxy: {
        '/api': {
          target: `http://localhost:${env.APP_PORT || 8000}`,
          changeOrigin: true,
          headers: { Accept: 'application/json' },
        },
        // Sanctum SPA stateful: /sanctum/csrf-cookie vive en el grupo 'web' del backend,
        // fuera de /api — necesita el mismo proxy para que la cookie de sesión se plante
        // en el origen que el navegador ve (localhost:5173), no en localhost:8000.
        '/sanctum': {
          target: `http://localhost:${env.APP_PORT || 8000}`,
          changeOrigin: true,
        }
      }
    }
  }
})