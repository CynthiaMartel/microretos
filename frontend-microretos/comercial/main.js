import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './comercial.css' // incluye src/style.css
import App from './App.vue'
import router from './router.js'
import '../src/composables/useInstalarApp.js' // escucha beforeinstallprompt desde el arranque

// Igual que src/main.js: tras un deploy, una pestaña con la versión anterior puede pedir el
// chunk de una vista que ya no existe → se recarga una vez para traer el index.html nuevo.
window.addEventListener('vite:preloadError', (event) => {
  try {
    const ultima = Number(sessionStorage.getItem('recarga-chunk'))
    if (Date.now() - ultima < 10_000) return
    sessionStorage.setItem('recarga-chunk', String(Date.now()))
  } catch { return }
  event.preventDefault()
  window.location.reload()
})

// App comercial: comparte api.js, servicios, store de auth y estilos con la
// app principal (src/), pero es otro build con su propio router (ver vite.comercial.config.js).
const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
