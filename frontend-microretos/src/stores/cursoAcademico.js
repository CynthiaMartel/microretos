import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

// Curso académico seleccionado, compartido por el panel y las bibliotecas (proyectos,
// proyectos completados, encuentros, retos, mis equipos): al cambiarlo en una vista se
// mantiene en las demás.
//
// Un curso va de septiembre a agosto y se identifica por su año de inicio (2025 = 2025/26).
// Regla común de pertenencia (la misma del panel):
//   · proyecto  → fecha de su primer encuentro (encuentro_fecha); si no tiene, created_at
//   · encuentro → fecha
//   · reto      → creado_en / created_at
//
// Por defecto se muestra 2025/26 (el curso completo de la demo). La elección se recuerda en
// localStorage: es solo una preferencia de interfaz, no un dato sensible.
export const CURSO_POR_DEFECTO = 2025
const CLAVE = 'curso_academico'

const _pf = (s) => (s ? (String(s).includes('T') || String(s).includes(' ') ? new Date(String(s).replace(' ', 'T')) : new Date(`${s}T12:00:00`)) : null)

export const cursoDeFecha = (fecha) => {
  const d = fecha instanceof Date ? fecha : _pf(fecha)
  if (!d || isNaN(d)) return null
  return d.getMonth() >= 8 ? d.getFullYear() : d.getFullYear() - 1
}
export const etiquetaCurso = (y) => `${y}/${String(y + 1).slice(-2)}`
export const cursoActual = () => cursoDeFecha(new Date())

export const cursoDeProyecto  = (p) => cursoDeFecha(p?.encuentro_fecha || p?.created_at)
export const cursoDeEncuentro = (e) => cursoDeFecha(e?.fecha)
export const cursoDeReto      = (r) => cursoDeFecha(r?.creado_en || r?.created_at)

export const useCursoAcademicoStore = defineStore('cursoAcademico', () => {
  const leer = () => {
    try {
      const v = localStorage.getItem(CLAVE)
      if (v === 'todos') return null
      const n = Number(v)
      return Number.isInteger(n) && n > 2000 ? n : CURSO_POR_DEFECTO
    } catch { return CURSO_POR_DEFECTO }
  }

  // null = "Todos los cursos" (solo se ofrece en las bibliotecas; el panel siempre muestra uno)
  const curso = ref(leer())
  watch(curso, (v) => {
    try { localStorage.setItem(CLAVE, v === null ? 'todos' : String(v)) } catch { /* sin almacenamiento */ }
  })

  /** ¿El curso y (año de inicio) pasa el filtro actual? */
  const coincide = (y) => curso.value === null || y === curso.value

  /** Cursos a ofrecer: del actual hacia atrás hasta 2024/25, más los que aparezcan en los datos. */
  const opciones = (extra = []) => {
    const set = new Set([cursoActual(), CURSO_POR_DEFECTO, 2024, ...extra.filter(Boolean)])
    for (let y = cursoActual(); y >= 2024; y--) set.add(y)
    return [...set].sort((a, b) => b - a)
  }

  return { curso, coincide, opciones }
})
