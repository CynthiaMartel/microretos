import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './central.css' // incluye src/style.css
import App from './App.vue'
import router from './router.js'
import '../src/composables/useInstalarApp.js' // escucha beforeinstallprompt desde el arranque

// Tras un deploy, una pestaña con la versión anterior puede pedir el chunk de una vista que ya
// no existe → se recarga una vez para traer el index.html nuevo (igual que src/main.js).
window.addEventListener('vite:preloadError', (event) => {
  try {
    const ultima = Number(sessionStorage.getItem('recarga-chunk'))
    if (Date.now() - ultima < 10_000) return
    sessionStorage.setItem('recarga-chunk', String(Date.now()))
  } catch { return }
  event.preventDefault()
  window.location.reload()
})

// DuaLab Central: comparte api.js, store de auth, estilos y vistas con la app principal (src/),
// con su propio router y su propio diseño (barra superior + panel lateral).
const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
