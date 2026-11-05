// Preguntas de la entrevista diagnóstica de empresa (docs/diccionario_preguntas_diagnostico_empresa.md).
// `campo` es el nombre camelCase que espera el backend (UpdateDiagnosticoEmpresaRequest) y `max`
// su límite (en las de opciones, del texto ya unido con ", "). La usa la app comercial; el Generador de Retos aún tiene su propia
// copia de los textos y las opciones (GeneradorMicroretos.vue): cambiar las dos a la vez.
export const OPCIONES_CONSECUENCIAS = [
  'Errores frecuentes', 'Costes innecesarios', 'Pérdida de tiempo',
  'Insatisfacción del cliente', 'Riesgos de seguridad', 'Desperdicio de materiales', 'Falta de comunicación interna',
]

export const OPCIONES_LIMITACIONES = [
  'Presupuesto Cero/Muy Bajo', 'Equipos obsoletos', 'Internet inestable',
  'Software cerrado', 'Resistencia al cambio', 'Espacio reducido',
  'Falta de tiempo', 'Normativa RGPD',
]

export const PREGUNTAS_DIAGNOSTICO = [
  { campo: 'diaANormal', pregunta: '¿Qué ofrece su empresa y qué hace en su día a día?', obligatorio: true, max: 1000,
    ayuda: 'Productos o servicios, clientes y cómo trabajan en un día normal.' },
  { campo: 'friccionArea', pregunta: '¿Qué tarea da más trabajo del que debería?', obligatorio: true, max: 400,
    ayuda: 'Un proceso concreto: facturación, pedidos, redes sociales, almacén…' },
  { campo: 'friccionProblema', pregunta: '¿Por qué? Cuéntanos qué ocurre hoy', obligatorio: true, max: 1200,
    ayuda: 'Qué falla, cada cuánto pasa y a quién afecta.' },
  { campo: 'consecuencias', pregunta: 'Si pudieran mejorar algo YA mismo, ¿qué sería?', obligatorio: false, max: 2000,
    opciones: OPCIONES_CONSECUENCIAS, otra: 'Otra mejora' },
  { campo: 'restricciones', pregunta: '¿Han probado a solucionarlo? ¿Qué limitaciones tienen?', obligatorio: false, max: 600,
    opciones: OPCIONES_LIMITACIONES, otra: 'Otra limitación' },
  { campo: 'loQueNoQuieren', pregunta: '¿Qué NO quieren bajo ningún concepto?', obligatorio: false, max: 500 },
  { campo: 'expectativasAlumno', pregunta: 'Si tuvieras a un alumno aquí, ¿qué esperas que realice?', obligatorio: false, max: 800 },
]

// Columna de la empresa (snake_case, EmpresaCaptacionResource) de cada pregunta
export const COLUMNA_DE = {
  diaANormal: 'dia_a_normal', friccionArea: 'friccion_area', friccionProblema: 'friccion_problema',
  consecuencias: 'consecuencias', restricciones: 'restricciones', loQueNoQuieren: 'lo_que_no_quieren',
  expectativasAlumno: 'expectativas_alumno',
}
