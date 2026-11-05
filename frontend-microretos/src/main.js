import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import './composables/useInstalarApp.js' // escucha beforeinstallprompt desde el arranque

// Tras un deploy, una pestaña abierta con la versión anterior puede pedir el chunk de una
// vista que ya no existe: se recarga para traer el index.html nuevo. La hora de la última
// recarga en sessionStorage evita un bucle si el fallo es otro (p. ej. sin conexión).
window.addEventListener('vite:preloadError', (event) => {
  try {
    const ultima = Number(sessionStorage.getItem('recarga-chunk'))
    if (Date.now() - ultima < 10_000) return
    sessionStorage.setItem('recarga-chunk', String(Date.now()))
  } catch { return }
  event.preventDefault()
  window.location.reload()
})

const app = createApp(App)
app.use(createPinia())
app.use(router) 
app.mount('#app')
