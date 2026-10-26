# Tareas pendientes — microretos

> Backlog de tareas funcionales (las de seguridad van en `SECURITY_FIXES.md`).
> Al terminar una, moverla a `TAREAS_YA_REALIZADAS_PARA_LOVABLE.md`.

---

## ⚠ IMPORTANTE — entrega 2026-10-10 (se trabaja el 2026-10-09)

Tres bloques derivados del nuevo panel docente (`/panel-docente`, `InicioDocente.vue`). El panel ya
filtra por curso académico en cliente; estas tareas lo llevan al backend y al resto de vistas.

**Regla común de curso académico:** septiembre → agosto. Un proyecto pertenece al curso de **su
encuentro**; si no tiene, al de su `created_at` (misma regla que ya usa el panel, ver `fechaProyecto`
en `InicioDocente.vue`).

**Ya hecho (2026-10-08), como paso previo:** `MicroproyectoController::index` precarga el id del
primer encuentro con `withMin` (antes, una consulta por proyecto: 373 proyectos → 6 consultas en
total) y tanto `MicroproyectoController::index` como `EncuentroController::index` tienen un techo de
500 registros. Es una medida provisional hasta que exista el filtro por curso de I3.

### I1 · Datos de la demo coherentes entre cursos — ✅ HECHO EN LOCAL (2026-10-09), falta producción

**Estado.** Copia previa en `storage/app/backups/microretos_antes_reubicar_curso_20261009_135718.sql`.
Aplicados en local `php artisan demo:reubicar-curso --commit` (4.700 registros de demo: 2025/26 completo,
2026/27 en arranque; duración de encuentros con `Microproyecto::fechaFinSugerida`) y
`php artisan demo:encuentros-futuros --commit` (4 encuentros futuros). Solo datos de DuaLab (centro 10)
y catálogo; nada del IES Ana Luisa, usuarios ni enlaces de retos. **Producción:** desplegar los dos
comandos, copia de seguridad en Hostinger, dry-run, `--commit` y `cache:clear`.


**Contexto.** En la BD local (recuento del 2026-10-08): los encuentros de 2025/26 empiezan en
diciembre (sep–nov vacíos); 6 encuentros de julio sin `num_alumnos`; todos los proyectos están
creados en julio o el 15/09/2026 y 251 de 369 no tienen encuentro, así que 2026/27 acumula casi
todos los proyectos en curso y 2025/26 casi solo los completados. Las gráficas del panel salen
muy dispares de un curso a otro.

**Qué hacer.** Comando artisan reproducible (en la línea de `DemoGenerarEmpresas`), **por defecto
en `--dry-run`** (solo muestra los cambios) y con `--apply` para escribir:
1. Repartir las fechas de los encuentros de 2025/26 entre septiembre y junio.
2. Dar a los proyectos sin encuentro un `created_at` coherente con su estado (completados en el
   curso anterior, borradores recientes).
3. Rellenar `num_alumnos` vacíos con valores realistas.

**Ojo:** ejecutarlo con `--apply` modifica la BD → solo con confirmación explícita de Cynthia.

**Done cuando:** el panel muestra para 2025/26 actividad de septiembre a junio, y 2026/27 un
reparto razonable de estados; el comando es idempotente y el `--dry-run` no escribe nada.

### I2 · Endpoint ligero del panel + resumen de curso para superadmin

**Contexto.** El panel calcula todo en el navegador a partir de `/encuentros`, `/startup/proyectos`
y `/microretos`, que devuelven fichas completas. Además, la jefatura necesita un resumen por curso.

**Qué hacer.**
1. `App\Services\ResumenCursoService`: calcula contadores, estados del donut, serie mensual del
   impacto acumulado, proyectos y empresas de un curso (para un docente, un centro o global).
2. `GET /api/panel-docente/resumen?curso=2026` (FormRequest con `curso` validado, Resource propio,
   `throttle`, caché en `file` por usuario y curso) → el panel deja de descargar los listados completos.
3. Resumen congelado: tabla `resumenes_curso` (centro_id, curso_academico, métricas JSON,
   cerrado_en), comando `cursos:cerrar {curso}` programado el 1 de septiembre y vista de
   superadmin con el resumen por centro y exportación. Acceso con Policy (solo superadmin).

**Done cuando:** el panel usa el endpoint (mismas cifras que hoy), el resumen congelado coincide
con lo que mostraba el panel ese curso, y un docente/admin recibe 403 en la vista de superadmin.

### I3 · Curso académico en todas las vistas — 🟡 FRONTEND HECHO (2026-10-09), falta backend

**Estado.** Store `stores/cursoAcademico.js` + `components/SelectorCurso.vue` (por defecto 2025/26, opción
"Todos los cursos" en bibliotecas) en el panel, Biblioteca de proyectos, Proyectos completados,
Encuentros, Biblioteca de retos y Mis equipos; el filtro "Curso" (1º/2º) ya se llama "Nivel". El listado
de proyectos devuelve `encuentro_fecha`. **Falta** el punto 1 (columna `curso_academico` + filtro en servidor).


**Contexto.** En Proyectos, Proyectos completados, Mis equipos y Biblioteca, el filtro "Curso"
significa **nivel (1º/2º)**, no curso académico → choque de nombres con el selector del panel.

**Qué hacer.**
1. Backend: columna `curso_academico` en `encuentros` y `microproyectos` (migración nueva +
   relleno de los existentes con la regla común — **escritura en BD: confirmar antes**), asignada
   automáticamente al crear; índice; los `index()` aceptan `?curso_academico=` validado por
   FormRequest (por defecto, el curso actual). Sustituye al techo provisional de 500.
2. Frontend: store Pinia con el curso seleccionado (preferencia en `localStorage`, no sensible) y
   selector global usado por el panel, Proyectos, Proyectos completados, Encuentros, Mis equipos y
   estadísticas de empresas. Renombrar el filtro actual "Curso" → **"Nivel"**.

**Done cuando:** cambiar de curso en cualquier vista filtra todas de forma coherente, cada vista
pide al backend solo su curso, y "Nivel" y "Curso académico" ya no se confunden.

---

## T1 · Script para rellenar la P5 de las empresas reales

**Contexto.** La P5 del diagnóstico («Si tuvieras a un alumno aquí, ¿qué esperas que realice?»,
columna `empresas.expectativas_alumno`) es obligatoria en el paso 2 del Generador de Retos.
Las empresas ficticias generadas por `DemoGenerarEmpresas` ya la traen (30 de 33), pero
**ninguna de las 66 empresas reales la tiene** (recuento del 2026-10-02). Mientras tanto, el
generador pide la P5 a mano para cada reto con esas empresas y no la guarda.

**Qué hacer.** Comando artisan (p. ej. `empresas:rellenar-p5`) que, para cada empresa real
(`es_simulada = false`) con `expectativas_alumno` vacío y diagnóstico completo (P1, P2 y P2b):

1. Pida a OpenAI una P5 coherente a partir del diagnóstico ya recogido (P1–P4, sector,
   tamaño), con el mismo tono y límite que `simularInfoEmpresa` (máx. 800 caracteres).
2. **Por defecto, en modo `--dry-run`**: solo muestra la propuesta por empresa (id + texto),
   sin escribir nada. Solo con `--apply` se guarda.
3. Acepte `--id=` para procesar una sola empresa y revisar el resultado antes del lote.
4. Registre en log qué empresas se actualizaron (por id, nunca nombres ni datos de contacto).
5. Llamadas a OpenAI secuenciales y con pausa entre ellas (límite de tokens por minuto).

**Ojo.**
- Son empresas reales con datos sensibles: la P5 generada es una propuesta, debería
  revisarla DuaLab antes de `--apply`. Valorar marcarla como generada por IA.
- Ejecutarlo en cualquier BD (local o producción) requiere confirmación explícita previa
  (regla de CLAUDE.md: ninguna mutación de BD sin permiso).

**Hecho cuando.** Las empresas reales con diagnóstico completo tienen P5, el generador la
muestra sola al elegir la empresa y ya no se pide a mano en el paso 2.

---

## T2 · Catálogo DuaLab — completado (2026-10-05)

Plantillas sin centro (`empresas.es_catalogo`), «Catálogo DuaLab» y «Usar en mi centro» en el
generador, «Añadir al catálogo DuaLab» al crear con IA (superadmin), sección propia en
«Base de datos» (con nº de centros que usan cada plantilla) y lote con
`php artisan demo:generar-empresas --catalogo --total=N [--familias=1,3] --commit`.

---

## T3 · Copia del diagnóstico en cada reto — pendiente: retos antiguos

Ya hecho: `microretos.diagnostico_empresa` se rellena al guardar un reto y la ficha/listado
la usan. Falta decidir si ejecutar `php artisan microretos:capturar-diagnostico --commit`
para los 746 retos anteriores (copia el diagnóstico de HOY de su empresa; el original de las
empresas ya editadas no se puede recuperar). Requiere confirmación explícita (regla de BD).

---

## T4 · Energía y Agua sin módulos ni RA/CE — pendiente

**Contexto.** Desde el 2026-10-13 una familia solo se ofrece para generar retos si alguno de sus
ciclos tiene módulos con RA y CE (`Familia::scopeConDatosParaRetos`). «Energía y Agua» tiene 2
ciclos en la BD local (ids 365 *Redes y Estaciones de Tratamiento de Aguas* y 366 *Energías
Renovables*) pero 0 módulos, así que sus plantillas del catálogo DuaLab están ocultas.

Existe `database/seeders/EnergiasRenovablesSeeder.php` (+ `energias_renovables_seed.sql`) con
familia, ciclo, módulos, RA y CE de Energías Renovables, pero no llegó a cargarlos: busca el
ciclo por `referenciaBOE = 'RD 1584/2011'` y en la BD está como `BOE-A-2013-2035`.

**Qué hacer.**
1. Revisar por qué el seeder no cargó los datos (referencia distinta, `INSERT IGNORE` que choca
   con ids existentes…) y si su SQL crearía un ciclo duplicado.
2. Cargar módulos/RA/CE de los dos ciclos, con el seeder corregido o con el importador de T5.
3. Comprobar en producción el mismo estado antes de ejecutar nada.

**Ojo.** Escribe en BD → solo con confirmación explícita de Cynthia.

**Hecho cuando.** Energía y Agua aparece con datos en «Base de datos» (sale del aviso de
plantillas ocultas) y se puede generar un reto con ella.

---

## T5 · Cargar módulos, RA y CE desde «Base de datos» — B hecho en local (2026-10-13), C pendiente

**B · Importador CSV — hecho.** En «Base de datos» → gestión de familias y ciclos, cada ciclo
tiene un botón de importar (y la etiqueta «Sin módulos» si no tiene RA/CE). Modal
`ImportarCurriculoModal.vue`: plantilla descargable (`;` + BOM, se abre en Excel), «Comprobar
archivo» (valida sin escribir) e «Importar». Backend: `POST /ciclos/{id}/curriculo/importar`
(superadmin, throttle 20/min, `ImportarCurriculoRequest`, `CurriculoImportService`). Solo añade:
los módulos que el ciclo ya tiene se omiten. Migración `2026_10_13_000001_add_indices_curriculo`
(índices en modulos/RA/CE: el catálogo pasó de ~1,8 s a ~40 ms). **Producción:** desplegar y
`migrate`.

**C · Edición manual — pendiente.** Formularios para crear, editar y borrar módulos, RA y CE
sueltos (corregir erratas sin reimportar).
**Ojo:** borrar o cambiar un RA/CE puede afectar a retos ya generados que los citan — revisar
antes cómo guardan los retos sus RA/CE (`microretos.ra_ce_origen` y relacionados).
Las tablas `modulos`, `resultados_aprendizaje` y `criterios_evaluacion` no tienen
`created_at`/`updated_at`: sus modelos necesitan `public $timestamps = false;` antes de
guardar con Eloquent (a `CicloFormativo` le pasaba y el alta/edición de ciclos daba 500).

**Hecho cuando.** Un superadmin puede corregir un módulo, RA o CE concreto desde «Base de datos».

---

## T6 · Tipo de KPI (cualitativo / cuantitativo) en el Taller de Ideas — pendiente

**Contexto.** El rediseño del paso 6 (`StartupDayWizard.vue`, diseño `src/assets/crear_proyecto_2.png`)
muestra un desplegable «Cualitativo / Cuantitativo» en cada KPI. Hoy `microproyectos.kpis` guarda
`{ lista: ["texto", …] }`, y ese formato lo leen el wizard, `MicroproyectoFichaPublicaResource`
(validación de la empresa), los PDF (`useMicroproyectoPdfExport`) y la ficha del proyecto.

**Qué hacer.** Pasar cada KPI a `{ texto, tipo }` aceptando también el formato antiguo (texto
suelto = sin tipo), validar `tipo` con `in:cualitativo,cuantitativo` en `UpdateMicroproyectoRequest`
y adaptar todos los lectores. Sin migración de esquema (es un JSON); si se normalizan los datos
existentes, preguntar antes (regla de BD).

## T7 · «Recursos de apoyo» en el Taller de Ideas — mostrado (2026-10-21), falta «Añadir recurso»

**Contexto.** Los diseños del wizard (`crear_proyecto*.png`) tienen en el panel lateral un bloque
«Recursos de apoyo» con «Añadir recurso». Desde el 2026-10-21 el bloque existe (`TallerResumen.vue`)
y muestra los mismos recursos de `/seccion/recursos` (`SECCIONES.recursos` en
`config/navegacion.js`: «¿Qué es DUALAB?», «Glosario», «Guías y plantillas», «Acerca de»), con
«Ver todos los recursos». No hay «Añadir recurso»: no existe tabla, endpoint ni policy.

**Qué hacer.** Decidir si son recursos globales de DuaLab (guías para docentes, gestionados por
admin) o adjuntos de cada proyecto (ya existen en el paso 7: vídeos, imágenes y documentos). Según
eso: modelo + migración + FormRequest + Resource + policy, y el bloque en `TallerResumen.vue`.

---

## T10 · Tokens de diseño en el resto de vistas — pendiente

Las vistas rediseñadas el 2026-10-21/22 (Taller de Ideas, crear encuentro, `/encuentros`, secciones,
explorador de retos, modal de propuesta) y los filtros comunes (`FiltrosPanel`, `FiltroSelect`,
`FiltroSegmentado`) ya usan los tokens. El resto sigue con grises, bordes y verdes de Tailwind, violeta como color de
IA, `#1F2937` como texto y textos de 8–11px. Aplicarlo vista a vista al tocar cada una (mismo
criterio que `docs/GUIA_DISENO.md` §2–§3): texto azul noche / `texto-secundario`, bordes
`border-borde`, éxito en verde de marca, IA en naranja con acciones en color del rol, mínimo
12px y sin `font-black`. Revisar fondos oscuros (ahí el texto secundario va en gris claro).

---

## T11 · Formato de explorador en el resto de listados — pendiente

Retos, Encuentros, Proyectos y Mis grupos ya siguen el mismo esquema (2026-10-22, ver
`docs/GUIA_DISENO.md` §7 y §8.2–§8.2c):
- cabecera común (`CabeceraSeccion`);
- sin capas de tarjetas de familia o módulo;
- `FiltrosPanel separadores` con iconos y botón «Más filtros»;
- lateral «Filtros rápidos» con casillas y contadores;
- título de resultados con niveles (`TituloResultados`);
- familia y módulos diferenciados en las tarjetas (`FamiliaModulos`);
- pie de tarjeta con botón principal y menú «⋯» (cierre con clic fuera o Escape, sin overlay);
- orden y paginación;
- tokens de diseño.

Faltan por pasar a ese formato:

- ~~**Lista de alumnado** — `AlumnadoListado.vue`~~ ✅ hecho (2026-10-26): filtros familia, ciclo, módulo (la API `/alumnado` envía ya `proyecto.modulos`), nivel y clase; lateral con estado, seguimiento, familia, proyecto y fechas; título con niveles; paginación de 20.
- ~~**Biblioteca de diagnósticos** — `BibliotecaDiagnosticos.vue`~~ ✅ hecho (2026-10-26): barra (búsqueda, curso, ciclo, módulo, nivel, clase), lateral (nota final por tramos, familia, de quién es), título con niveles, tarjetas con pie «Ver diagnóstico» + «⋯» (PDF, trabajo del grupo, ficha del proyecto), paginación de 9.
- ~~**Listado de empresas** — `EmpresasView.vue`~~ ✅ hecho (2026-10-26): barra (búsqueda, centro y provincia si hay más de uno); lateral (estado de contacto, sector y familia con contadores); título con familia, chips y orden (nombre, estado, más proyectos); tarjetas iguales con pie «Ver ficha» + «⋯» (enviar email, enlace de validación, copiar email); la ficha pasa a un modal accesible; paginación de 12. Los estados de contacto usan ya los valores reales de `StoreEmpresaRequest::ESTADOS_CONTACTO` (antes la vista tenía otros y ni el filtro ni los colores coincidían).
- ~~**Propuestas de empresas** — `PropuestasEmpresas.vue`~~ ✅ hecho (2026-10-26): se mantiene el tablero de 3 columnas (es un flujo, no una biblioteca); filtros solo buscador, curso académico, empresa y familia; 6 por columna con «Ver 6 más» / «Ver menos»; tokens.
- ~~**Gestión de usuarios** (admin) — `GestionUsuarios.vue`~~ ✅ hecho (2026-10-26): cabecera común con «Nueva cuenta»; pestañas Activas | Papelera con contador; barra (buscador por nombre, email, centro o empresa; centro solo para superadmin si hay varios); lateral (tipo de cuenta y estado con contadores); título, chips y orden (recientes, nombre, sin activar primero); tarjetas horizontales (una por fila) con «Activar»/«Editar»/«Restaurar» + «⋯» (centro, bloquear, papelera, eliminar definitivamente); paginación de 12. Los modales de crear, editar, centro y confirmación no cambian.
- **Noticias** — `NoticiasListado.vue`.
- **Notificaciones** — `NotificacionesDocente.vue`.
- ~~**Pantallas de acceso** — `PantallaAccesoLista.vue`~~ ✅ hecho (2026-10-26): lanzador, no biblioteca. Arriba «En curso hoy» (`AccesoRapidoEncuentros`, también en /seccion/alumnado) con código, copiar y «Proyectar»; debajo, el resto en lista compacta con buscador, clase y curso académico y «Ver más».
- **Papelera** — `PapeleraBaseDatos.vue`.

Revisar en cada una qué filtros tienen sentido: en alumnado, encuentro, grupo y fase; en empresas,
sector, provincia, real/ficticia e interés por familia. Comprobar también si alguna tiene todavía
capas de tarjetas por familia que haya que quitar. Al terminar cada vista, medirla a 360, 768 y
1280 px (`/general:responsive-audit`).
