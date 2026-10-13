// Migas "dónde estás" comunes a las vistas filtradas por familia profesional
// (bibliotecas de retos y proyectos, completados, encuentros, seguimiento de grupos):
//   Familias › <familia> › <ciclo> › <módulo> › <nivel propio de la vista>
// Cada nivel es { label, to } (enlace) o { label, accion } (botón que quita los filtros de
// debajo), o null si no aplica. El último nivel presente es el actual (destacado, sin enlace).
// Devuelve las props de MigasNavegacion: { items, actual }.
export function migasFamilia({ raiz = null, familia = null, ciclo = null, modulo = null, ultimo = null } = {}) {
  const niveles = [raiz, familia, ciclo, modulo, ultimo && { label: ultimo }].filter(n => n?.label)
  const actual = niveles.pop()
  return { items: niveles, actual: actual?.label || '' }
}
