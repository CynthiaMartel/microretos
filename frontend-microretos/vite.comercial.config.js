import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { versionarIconos } from './pwa/versionarIconos.mjs'
import { versionarCabeceras } from './pwa/versionarCabeceras.mjs'

// App comercial (comercial.dualab.es): un build aparte que comparte
// src/ (api.js, servicios, store de auth, estilos, componentes) con la app principal.
//   npm run dev:comercial    → http://localhost:5177
//   npm run build:comercial  → dist-comercial/ (se sube al subdominio)
// Mismo backend que la app principal, con sesión Sanctum propia (__HERRAMIENTA__, ver api.js).
//
// Instalable como app (PWA) igual que la principal (vite.config.js): manifest, service worker
// con la carcasa de la app, aviso sin conexión y aviso de versión nueva (version.json).

// Identificador de este build: __APP_VERSION__ en el bundle y dist-comercial/version.json
// (composables/useNuevaVersion.js los compara para avisar de un deploy nuevo)
const APP_VERSION = Date.now().toString(36)

const versionJson = () => ({
  name: 'version-json',
  apply: 'build',
  generateBundle() {
    this.emitFile({ type: 'asset', fileName: 'version.json', source: JSON.stringify({ version: APP_VERSION }) })
  },
})

const pwa = () => VitePWA({
  // 'prompt': nunca se recarga sola a mitad de una entrevista; el aviso "Hay una versión
  // nueva" de App.vue recarga cuando el comercial lo pulsa
  registerType: 'prompt',
  injectRegister: 'script',
  includeAssets: ['favicon.ico', 'favicon.png', 'apple-touch-icon.png'],
  manifest: {
    id: '/',
    name: 'DuaLab Comercial',
    short_name: 'DuaLab Com.',
    description: 'Entrevista a empresas y retos en directo para el equipo comercial de DuaLab',
    lang: 'es',
    // El subdominio es solo de esta app: la raíz es «Empresas» (o el acceso, sin sesión)
    start_url: '/',
    scope: '/',
    // Sin orientation: en la tablet se usa tanto en horizontal como en vertical
    display: 'standalone',
    theme_color: '#17283E',
    background_color: '#17283E',
    icons: [
      { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png' },
      { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png' },
      { src: 'maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
    ],
  },
  workbox: {
    // Solo la carcasa. Nunca respuestas de la API: datos de empresas, DNI y firma del
    // empresario no se quedan guardados en la tablet. version.json tampoco (.json fuera).
    // Incluye las imágenes de /assets (logo, fotos del acceso y del banner): son pocas y así
    // la app abre completa aunque en la empresa no haya cobertura
    globPatterns: ['**/*.{js,css,html,ico,svg,woff2,webp,png}'],
    navigateFallback: 'index.html',
    navigateFallbackDenylist: [/^\/api\//, /^\/sanctum\//],
    // Las tipografías van en el build (@fontsource, .woff2): entran en la precaché de arriba
    cleanupOutdatedCaches: true,
  },
})

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '../', '') // lee el .env de la raíz del repo, como vite.config.js

  return {
    root: fileURLToPath(new URL('./comercial', import.meta.url)),
    // Iconos propios (npm run iconos:herramientas) y .htaccess del subdominio; VITE_* desde la
    // misma carpeta que la app principal
    publicDir: fileURLToPath(new URL('./comercial/public', import.meta.url)),
    envDir: fileURLToPath(new URL('.', import.meta.url)),
    // Caché propia: si comparten node_modules/.vite, un `vite --force` de otra app borra sus deps
    cacheDir: fileURLToPath(new URL('./node_modules/.vite-comercial', import.meta.url)),
    plugins: [
      vue(),
      tailwindcss(),
      versionJson(),
      versionarIconos(),
      versionarCabeceras(),
      pwa(),
    ],
    define: {
      __APP_VERSION__: JSON.stringify(APP_VERSION),
      // Cookie XSRF-TOKEN-comercial: sesión propia, aparte de la app y de Central (src/api.js)
      __HERRAMIENTA__: JSON.stringify('comercial'),
    },
    build: {
      outDir: fileURLToPath(new URL('./dist-comercial', import.meta.url)),
      emptyOutDir: true,
    },
    server: {
      host: '0.0.0.0',
      port: Number(env.VITE_COMERCIAL_PORT) || 5177,
      strictPort: true,
      // Igual que vite.config.js: la cookie de sesión se planta en el origen que ve el navegador
      proxy: {
        '/api': {
          target: `http://localhost:${env.APP_PORT || 8000}`,
          changeOrigin: true,
          headers: { Accept: 'application/json' },
        },
        '/sanctum': {
          target: `http://localhost:${env.APP_PORT || 8000}`,
          changeOrigin: true,
        },
      },
    },
  }
})
