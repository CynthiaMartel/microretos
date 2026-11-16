import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// Nexo DuaLab (nexo.dualab.es): página de acceso común a las herramientas de DuaLab.
// Solo enlaza al acceso de cada una: sin sesión, sin API y sin datos (cada herramienta
// tiene su propio login y su propia cookie; ver TAREAS_PENDIENTES T16).
//   npm run dev:nexo    → http://localhost:5178
//   npm run build:nexo  → dist-nexo/ (se sube al subdominio)

// Identificador de este build: __APP_VERSION__ en el bundle y dist-nexo/version.json
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
  const env = loadEnv(mode, '../', '')

  return {
    root: fileURLToPath(new URL('./nexo', import.meta.url)),
    publicDir: fileURLToPath(new URL('./nexo/public', import.meta.url)),
    envDir: fileURLToPath(new URL('.', import.meta.url)),
    plugins: [vue(), tailwindcss(), versionJson()],
    define: {
      __APP_VERSION__: JSON.stringify(APP_VERSION),
    },
    build: {
      outDir: fileURLToPath(new URL('./dist-nexo', import.meta.url)),
      emptyOutDir: true,
    },
    server: {
      host: '0.0.0.0',
      port: Number(env.VITE_NEXO_PORT) || 5178,
      strictPort: true,
    },
  }
})
