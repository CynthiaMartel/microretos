import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'

// Acceso del alumnado a su workspace con el código de su grupo (XXX-000), sin cuenta; con el
// código de la clase lleva a «Unirse» para elegir grupo. Lo usan
// «Retomar workspace» (/workspace-proyecto), que recuerda el grupo en el dispositivo, y el login de
// la app (desplegable «¿Eres alumnado?»), que NO lo recuerda: en un dispositivo compartido otra
// persona podría entrar en un workspace que no es el suyo, así que allí siempre se pide el código.
const CLAVES = ['dualab_equipo_token', 'dualab_equipo_nombre', 'dualab_proyecto_titulo', 'dualab_equipo_etapa']
const leer = (k) => { try { return localStorage.getItem(k) } catch { return null } }

// 403 = código de clase que existe pero aún sin grupos: «Unirse» explica qué falta
async function esCodigoClase(codigo) {
  try { await api.get(`/clase/${codigo}`); return true } catch (e) { return e.response?.status === 403 }
}

export function useAccesoWorkspace({ recordar = true } = {}) {
  const router   = useRouter()
  const codigo   = ref('')
  const cargando = ref(false)
  const error    = ref('')

  const token = recordar ? leer('dualab_equipo_token') : null
  const equipoGuardado = ref(token ? {
    token,
    nombre: leer('dualab_equipo_nombre') || 'mi equipo',
    titulo: leer('dualab_proyecto_titulo') || '',
    etapa: leer('dualab_equipo_etapa') || 'taller',
  } : null)

  const codigoValido = computed(() => /^[A-Z]{3}-\d{3}$/.test(codigo.value))

  // Formato XXX-000 mientras se escribe
  function onInput(e) {
    let val = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 6)
    if (val.length > 3) val = val.slice(0, 3) + '-' + val.slice(3)
    codigo.value = val
    error.value  = ''
  }

  async function entrar() {
    if (!codigoValido.value || cargando.value) return
    cargando.value = true
    error.value = ''
    try {
      const res = await api.get(`/equipo/unirse/${codigo.value}`)
      if (recordar) {
        try {
          localStorage.setItem('dualab_equipo_token', res.data.token)
          localStorage.setItem('dualab_equipo_nombre', res.data.nombre_equipo)
          localStorage.setItem('dualab_proyecto_titulo', res.data.proyecto_titulo)
          localStorage.setItem('dualab_equipo_etapa', res.data.etapa || 'taller')
        } catch { /* sin almacenamiento: entra igualmente, pero no se recordará */ }
      }
      router.push({ name: 'equipo-workspace', params: { token: res.data.token } })
    } catch (e) {
      // No es de un grupo: si es el código de la clase, a «Unirse» con el código ya puesto para
      // elegir grupo (sin recordar el grupo si venimos del login)
      if (e.response?.status === 404 && await esCodigoClase(codigo.value)) {
        router.push({ name: 'unirse-equipo', query: { codigo: codigo.value, ...(recordar ? {} : { recordar: '0' }) } })
        return
      }
      error.value = e.response?.data?.error || 'No se encontró ese código. Comprueba que lo has escrito bien.'
    } finally {
      cargando.value = false
    }
  }

  function continuar() {
    if (equipoGuardado.value) router.push({ name: 'equipo-workspace', params: { token: equipoGuardado.value.token } })
  }

  function olvidar() {
    try { CLAVES.forEach(k => localStorage.removeItem(k)) } catch { /* sin almacenamiento */ }
    equipoGuardado.value = null
  }

  return { codigo, cargando, error, codigoValido, equipoGuardado, onInput, entrar, continuar, olvidar }
}
