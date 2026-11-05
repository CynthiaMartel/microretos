import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// App comercial (comercial.dualab.es): un build aparte que comparte
// src/ (api.js, servicios, store de auth, estilos, componentes) con la app principal.
//   npm run dev:comercial    → http://localhost:5177
//   npm run build:comercial  → dist-comercial/ (se sube al subdominio)
// Mismo backend y misma sesión Sanctum que la app principal (ver api.js).
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '../', '') // lee el .env de la raíz del repo, como vite.config.js

  return {
    root: fileURLToPath(new URL('./comercial', import.meta.url)),
    // Favicon e iconos de la app principal; VITE_* desde la misma carpeta que la app principal
    publicDir: fileURLToPath(new URL('./public', import.meta.url)),
    envDir: fileURLToPath(new URL('.', import.meta.url)),
    plugins: [
      vue(),
      tailwindcss(),
    ],
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
