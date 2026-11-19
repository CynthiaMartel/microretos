import { ref } from 'vue'

// Aviso «Este acceso es solo para el superadmin» (AvisoSoloSuperadmin.vue, montado en App.vue). Lo abren
// el panel lateral (Base de datos o Papelera con candado) y el router (si se entra por la URL) para
// Admin Central DuaLab: { nombre: 'Base de datos', destino: { name: 'base-datos' } } o null.
export const avisoSuperadmin = ref(null)
