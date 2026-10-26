# Tareas ya realizadas — microretos

> Resumen en palabras sencillas de lo que ya se ha construido, del 16 de junio al 30 de julio de 2026.

---

1. **Seguridad de la aplicación reforzada**: se revisó quién puede hacer qué según su tipo de usuario (profesor, administrador, empresa), y se creó el primer panel de control para el profesorado.

2. **Perfil de usuario y datos del centro educativo**: los usuarios ya pueden ver y editar su perfil, los centros educativos tienen imagen propia, y se amplió la pantalla de inicio del profesorado.

3. **Espacio de trabajo por equipos de alumnos**: se creó el lugar donde cada equipo trabaja su proyecto paso a paso, con sus fases, sus tareas y sus reflexiones finales.

4. **Rediseño del asistente de creación de proyectos** (el proceso guiado para arrancar un proyecto nuevo, "Startup Day"), más claro y más fácil de seguir.

5. **Reorganización del calendario de clases**: lo que antes se llamaba "sesión" ahora se llama "encuentro", con un calendario más claro, y los datos del currículo académico (qué enseña cada módulo) ya están conectados de verdad en vez de ser aproximados.

6. **Ficha del reto visible por código**: cada equipo puede ver la ficha completa del reto de la empresa con solo su código de acceso, sin necesidad de iniciar sesión.

7. **Corrección de un fallo de seguridad**: un profesor podía llegar a ver y gestionar equipos que no eran los suyos; ya está arreglado, cada profesor solo ve los suyos.

8. **Primeras pantallas de acceso para el alumnado**: pantallas sencillas para que los alumnos entren directamente a su grupo y a su proyecto.

9. **Mejoras al repartir el alumnado en equipos**: antes, si se cambiaba el reparto, se podía perder el trabajo ya hecho; ahora se actualiza sin borrar nada.

10. **Protección de los datos de las empresas**: los datos de contacto de las empresas (teléfono, email, identificación fiscal) ya no se pueden ver desde donde no se debe.

11. **Corrección de varios errores detectados al usar la aplicación** y ajustes visuales (por ejemplo, un problema de desbordamiento en la pantalla del móvil).

12. **Nueva ventana para validar una propuesta** (22/10/2026): al marcar un proyecto como propuesta aparece una ventana más clara con las dos formas de validarlo, cada una en su recuadro: por la empresa (enlace para copiar, datos de la empresa y cómo enviárselo) o directamente por el profesor. Explica que una no sustituye a la otra, avisa si algo falla en vez de quedarse callada, se usa igual con el teclado y se ve bien en el móvil. Es la misma ventana en el asistente de creación y en la ficha del proyecto.

13. **Nuevo explorador de retos** (22/10/2026): la biblioteca de retos empieza con las tarjetas de las familias profesionales (con su número de retos y su PDF); al entrar en una, o al pulsar «Ver todos los retos», se abre el explorador, donde los retos se filtran desde arriba (buscador, curso, ciclo, módulo, nivel y dificultad) o desde un panel lateral de «filtros rápidos» (una o varias familias, empresa real o ficticia, información simulada, empresa y duración). Muestra cuántos hay, permite ordenarlos, verlos en cuadrícula o en lista y pasar de página. Cada tarjeta deja claro si la empresa es real o ficticia y si la información es simulada, y reúne en un menú las acciones de descargar, publicar en el escaparate o eliminar. Los enlaces antiguos a una familia siguen funcionando.

14. **Biblioteca de proyectos más sencilla** (22/10/2026): ya no hay que pasar por las tarjetas de familias ni de estados. Se entra directamente a los proyectos completados y arriba se puede cambiar a «En proceso», cada uno con su número; al lado queda el recordatorio de que una propuesta todavía no es un proyecto. Los filtros son los mismos que en la biblioteca de retos (buscador, curso, ciclo, módulo y nivel) y, en el panel de «filtros rápidos», la familia y el estado del proyecto o quién lo validó. Los archivados quedan ocultos salvo que se pidan. Cada tarjeta lleva una franja del color de su estado, un marco para la foto del reto y, si está completado, un trofeo. Los enlaces antiguos a «Proyectos completados» siguen funcionando.

15. **Seguimiento de grupos más directo** (22/10/2026): ya no hay que entrar por tarjetas de familia y de módulo para llegar a un encuentro. Se ven todos los encuentros con sus grupos y se filtran como en el resto de bibliotecas: buscador, curso, ciclo, módulo y nivel arriba, y en «filtros rápidos» el estado de los grupos (en curso, sin iniciar, completados), los que tienen diagnóstico listo o grupos sin empezar, la familia y si son tuyos o compartidos contigo. Se pueden ordenar por fecha o por avance y pasar de página.

16. **Listado de alumnado como las demás bibliotecas** (26/10/2026): se filtra igual que retos, encuentros, proyectos y grupos (buscador por alias, curso, ciclo, módulo, nivel y clase arriba; en «filtros rápidos», el estado del proyecto, si tiene nota final o diagnóstico o le falta la reflexión, la familia, el proyecto y las fechas). Cada alumno muestra la familia y los módulos del proyecto, su grupo y su rol dentro del grupo explicado al pasar el ratón (portavoz, gestor/a de tiempos, documentador/a o responsable de foco). Se puede ordenar y pasar de página.

17. **Biblioteca de diagnósticos como las demás** (26/10/2026): los diagnósticos finales de los grupos se filtran igual que en el resto de bibliotecas (buscador, curso, ciclo, módulo, nivel y clase; en «filtros rápidos», la nota final por tramos, la familia y si el encuentro es tuyo o compartido contigo). Cada tarjeta es un encuentro (con el nombre de su proyecto, su nota media, la familia y los módulos y, si te lo ha compartido otro docente, «Docente: nombre») y dentro aparecen sus grupos con diagnóstico, cada uno con su nota final y su resumen; desde el menú se descargan los PDF o se abre la ficha del proyecto. La ventana del diagnóstico se usa también con el teclado. Se puede ordenar por fecha, nota media o proyecto y pasar de página.

18. **Dar acceso al alumnado en un clic** (26/10/2026): la pantalla para proyectar el acceso en clase enseña arriba los encuentros que están en curso hoy, cada uno con su código bien grande, un botón para copiarlo y «Proyectar»; debajo, el resto en una lista corta con buscador y clase. Lo mismo aparece al entrar en la sección «Acceso del alumnado», y en la biblioteca de encuentros la llave de cada tarjeta tiene ahora «Proyectar pantalla de acceso».

19. **Propuestas a empresas más manejables** (26/10/2026): el tablero de envíos mantiene sus tres columnas (pendientes de enviar, esperando respuesta y respuesta de la empresa), pero cada una enseña 6 propuestas y se amplía con «Ver 6 más». Se puede buscar por proyecto o empresa y filtrar por empresa, familia y curso académico.

---

*Documento generado el 30/07/2026 a partir del historial de trabajo real del proyecto (commits desde el 16/06/2026).*

20. **Listado de empresas como las demás bibliotecas** (26/10/2026): las empresas colaboradoras se buscan y filtran igual que el resto (buscador arriba; en «filtros rápidos», el estado de contacto, el sector y la familia, cada uno con su número). Las tarjetas tienen todas el mismo tamaño y muestran lo básico (sector, familia, centro y email); «Ver ficha» abre la ficha completa en una ventana, con sus proyectos, y el menú «⋯» permite enviarle un email, mandarle el enlace de validación o copiar su email. Se pueden ordenar por nombre, estado o número de proyectos y pasar de página.

21. **Gestión de cuentas como las demás bibliotecas** (26/10/2026): las cuentas de docentes y empresas se ven en tarjetas iguales y se filtran como en el resto (buscador por nombre, email, centro o empresa; en «filtros rápidos», el tipo de cuenta y si está activada, sin activar o bloqueada, cada uno con su número). Arriba se elige entre cuentas activas y papelera. Cada tarjeta lleva su acción principal (activar, editar o restaurar) y el resto en el menú «⋯». Se pueden ordenar por fecha, nombre o las pendientes de activar y pasar de página.
