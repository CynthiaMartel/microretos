// Preguntas de la entrevista diagnóstica de empresa (docs/diccionario_preguntas_diagnostico_empresa.md).
// Los textos, límites y opciones están en preguntasDiagnostico.json, que es la única fuente del
// frontend: la usan la app comercial (entrevista y formulario por correo), el Generador de Retos
// y el resumen de la empresa ficticia con IA. El backend tiene su copia (EntrevistaIAService y
// los límites de StoreEmpresaRequest) y PreguntasDiagnosticoSincronizadasTest avisa si difieren.
//
// `campo` es el nombre camelCase que espera el backend (UpdateDiagnosticoEmpresaRequest), `max` su
// límite (en las de opciones, del texto ya unido con ", ") y `corta` la versión para resúmenes.
import datos from './preguntasDiagnostico.json'

export const OPCIONES_CONSECUENCIAS = datos.opciones.consecuencias
export const OPCIONES_LIMITACIONES = datos.opciones.restricciones

export const PREGUNTAS_DIAGNOSTICO = datos.preguntas.map(({ columna, opciones, ...p }) =>
  opciones ? { ...p, opciones: datos.opciones[opciones] } : p)

// Cada pregunta por su campo: PREGUNTA.friccionArea.pregunta
export const PREGUNTA = Object.fromEntries(PREGUNTAS_DIAGNOSTICO.map(p => [p.campo, p]))

// Columna de la empresa (snake_case, EmpresaCaptacionResource) de cada pregunta
export const COLUMNA_DE = Object.fromEntries(datos.preguntas.map(p => [p.campo, p.columna]))
