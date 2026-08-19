import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '../', '') // lee el .env de la raíz del repo !!

  return {
    plugins: [
      vue(),
      tailwindcss(),
    ],
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