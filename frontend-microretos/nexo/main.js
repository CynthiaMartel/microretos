import { createApp } from 'vue'
import './nexo.css' // incluye src/style.css
import App from './App.vue'
import '../src/composables/useInstalarApp.js' // escucha beforeinstallprompt desde el arranque

// Nexo DuaLab: una sola pantalla, sin router ni store (no hay sesión que guardar)
createApp(App).mount('#app')
