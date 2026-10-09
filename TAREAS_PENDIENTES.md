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
