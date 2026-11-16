import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// DuaLab Central (central.dualab.es): herramienta del superadmin. Un build aparte que comparte
// src/ (api.js, store de auth, estilos y las vistas Base de datos, Papelera, Usuarios y los
// paneles de captación) con la app principal, como la app comercial.
//   npm run dev:central    → http://localhost:5179
//   npm run build:central  → dist-central/ (se sube al subdominio)
// Mismo backend que la app principal, con sesión Sanctum propia (__HERRAMIENTA__, ver api.js). Las vistas siguen
// también en la app hasta comprobar Central en producción (TAREAS_PENDIENTES T16).

// Identificador de este build: __APP_VERSION__ en el bundle y dist-central/version.json
// (composables/useNuevaVersion.js los compara para avisar de un deploy nuevo)
const APP_VERSION = Date.now().toString(36)

const versionJson = () => ({
  name: 'version-json',
  apply: 'build',
  generateBundle() {
    this.emitFile({ type: 'asset', fileName: 'version.json', source: JSON.stringify({ version: APP_VERSION }) })
  },
})

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '../', '') // lee el .env de la raíz del repo, como vite.config.js

  return {
    root: fileURLToPath(new URL('./central', import.meta.url)),
    publicDir: fileURLToPath(new URL('./central/public', import.meta.url)),
    envDir: fileURLToPath(new URL('.', import.meta.url)),
    plugins: [vue(), tailwindcss(), versionJson()],
    // Cookie XSRF-TOKEN-central: sesión propia, aparte de la app y de la comercial (src/api.js)
    define: {
      __APP_VERSION__: JSON.stringify(APP_VERSION),
      __HERRAMIENTA__: JSON.stringify('central'),
    },
    build: {
      outDir: fileURLToPath(new URL('./dist-central', import.meta.url)),
      emptyOutDir: true,
    },
    server: {
      host: '0.0.0.0',
      port: Number(env.VITE_CENTRAL_PORT) || 5179,
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
