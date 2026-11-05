import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './comercial.css' // incluye src/style.css
import App from './App.vue'
import router from './router.js'

// App comercial: comparte api.js, servicios, store de auth y estilos con la
// app principal (src/), pero es otro build con su propio router (ver vite.comercial.config.js).
const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
