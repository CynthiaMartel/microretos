// Dictado por voz para las respuestas de la entrevista. Dos modos:
//  - 'navegador' (gratis): reconocimiento de voz del propio navegador (Web Speech API). Chrome
//    y Edge lo hacen en servidores de Google, Safari en los de Apple; Firefox no lo tiene. Va
//    escribiendo mientras se habla.
//  - 'ia' (de pago, solo comercial con sesión): se graba el audio (máx. 2 min), el backend lo
//    pasa a texto con OpenAI y lo descarta. Mejor puntuación y más fiable en Safari.
// Ninguno guarda el audio. Los dos necesitan conexión.
import { ref, onUnmounted } from 'vue'
import { transcribirAudio } from '../../src/services/comercialService.js'

const Reconocimiento = typeof window !== 'undefined'
  ? (window.SpeechRecognition || window.webkitSpeechRecognition)
  : null

export const dictadoGratisDisponible = !!Reconocimiento
export const dictadoIaDisponible = typeof window !== 'undefined'
  && !!navigator.mediaDevices?.getUserMedia && typeof window.MediaRecorder !== 'undefined'

const MAX_MS = 2 * 60 * 1000
// Chrome en Android repite frases en modo continuo: allí se dicta frase a frase y se reanuda solo
const esAndroid = typeof navigator !== 'undefined' && /Android/i.test(navigator.userAgent)

const TIPOS_AUDIO = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus']
const extensionDe = (tipo) => (tipo.includes('mp4') ? 'mp4' : tipo.includes('ogg') ? 'ogg' : 'webm')

/**
 * @param {(texto: string) => void} alTexto  recibe cada trozo ya reconocido
 * @returns estado ('inactivo' | 'escuchando' | 'procesando'), parcial (lo que se va oyendo,
 *          modo navegador), error y alternar(modo)
 */
export function useDictado(alTexto) {
  const estado  = ref('inactivo')
  const parcial = ref('')
  const error   = ref('')

  let reconocimiento = null
  let seguir = false
  let grabadora = null
  let flujo = null
  let limite = null

  function iniciarNavegador() {
    reconocimiento = new Reconocimiento()
    reconocimiento.lang = 'es-ES'
    reconocimiento.continuous = !esAndroid
    reconocimiento.interimResults = true

    reconocimiento.onresult = (e) => {
      let oyendo = ''
      for (let i = e.resultIndex; i < e.results.length; i++) {
        const r = e.results[i]
        if (r.isFinal) {
          const texto = r[0].transcript.trim()
          if (texto) alTexto(texto)
        } else {
          oyendo += r[0].transcript
        }
      }
      parcial.value = oyendo
    }
    reconocimiento.onerror = (e) => {
      if (e.error === 'no-speech' || e.error === 'aborted') return
      seguir = false
      error.value = e.error === 'not-allowed' || e.error === 'service-not-allowed'
        ? 'Permite el uso del micrófono en el navegador para dictar.'
        : e.error === 'network'
        ? 'El dictado necesita conexión a internet.'
        : 'El dictado se ha detenido. Vuelve a pulsar para seguir.'
    }
    // Safari y Android lo paran tras una pausa: mientras no se pulse «Parar», se reanuda
    reconocimiento.onend = () => {
      parcial.value = ''
      if (seguir) {
        try { reconocimiento.start(); return } catch { /* se para abajo */ }
      }
      terminar()
    }

    seguir = true
    reconocimiento.start()
    estado.value = 'escuchando'
    limite = setTimeout(parar, MAX_MS)
  }

  async function iniciarIa() {
    try {
      flujo = await navigator.mediaDevices.getUserMedia({ audio: true })
    } catch {
      error.value = 'Permite el uso del micrófono en el navegador para dictar.'
      return
    }
    const tipo = TIPOS_AUDIO.find((t) => window.MediaRecorder.isTypeSupported?.(t)) ?? ''
    grabadora = new window.MediaRecorder(flujo, tipo ? { mimeType: tipo } : undefined)
    const trozos = []
    grabadora.ondataavailable = (e) => { if (e.data.size) trozos.push(e.data) }
    grabadora.onstop = async () => {
      flujo?.getTracks().forEach((t) => t.stop())
      flujo = null
      const tipoFinal = grabadora.mimeType || tipo || 'audio/webm'
      const audio = new Blob(trozos, { type: tipoFinal })
      grabadora = null
      if (audio.size < 1000) { terminar(); return } // pulsado sin hablar

      estado.value = 'procesando'
      try {
        const datos = new FormData()
        datos.append('audio', audio, `dictado.${extensionDe(tipoFinal)}`)
        const texto = (await transcribirAudio(datos)).data.data.texto
        if (texto) alTexto(texto)
      } catch (err) {
        error.value = err.response?.data?.error
          || err.response?.data?.errors?.audio?.[0]
          || (err.response?.status === 429 ? 'Demasiados dictados seguidos: espera un minuto.' : 'No se ha podido pasar a texto. Inténtalo de nuevo.')
      } finally {
        terminar()
      }
    }
    grabadora.start()
    estado.value = 'escuchando'
    limite = setTimeout(parar, MAX_MS)
  }

  function terminar() {
    clearTimeout(limite)
    limite = null
    seguir = false
    reconocimiento = null
    parcial.value = ''
    estado.value = 'inactivo'
  }

  function parar() {
    clearTimeout(limite)
    seguir = false
    if (reconocimiento) reconocimiento.stop()
    else if (grabadora?.state === 'recording') grabadora.stop()
    else terminar()
  }

  /** Empieza a dictar con ese modo o, si ya está dictando, para */
  function alternar(modo) {
    if (estado.value === 'procesando') return
    if (estado.value === 'escuchando') { parar(); return }
    error.value = ''
    if (!navigator.onLine) {
      error.value = 'El dictado necesita conexión a internet.'
      return
    }
    if (modo === 'ia' && dictadoIaDisponible) iniciarIa()
    else if (dictadoGratisDisponible) iniciarNavegador()
    else error.value = 'Este navegador no permite dictar. Prueba con Chrome o Safari.'
  }

  onUnmounted(() => {
    seguir = false
    clearTimeout(limite)
    try { reconocimiento?.abort() } catch { /* ya parado */ }
    if (grabadora?.state === 'recording') {
      grabadora.onstop = null
      grabadora.stop()
    }
    flujo?.getTracks().forEach((t) => t.stop())
  })

  return { estado, parcial, error, alternar }
}

/** Añade lo dictado al texto que ya había, con mayúscula al empezar frase */
export function anadirDictado(previo, nuevo) {
  const base = (previo ?? '').trimEnd()
  const finDeFrase = base === '' || /[.!?…:]$/.test(base)
  const trozo = finDeFrase ? nuevo.charAt(0).toUpperCase() + nuevo.slice(1) : nuevo
  return base ? `${base} ${trozo}` : trozo
}
