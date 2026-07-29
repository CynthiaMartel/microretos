# Informe de seguimiento — microretos

> Periodo cubierto: 16 de junio – 28 de julio de 2026.
> Elaborado a partir del historial de commits y de `TAREAS_PENDIENTES.txt` (roadmap interno, última actualización 2026-07-21).
> Autora: Cynthia (única desarrolladora del proyecto, 22h/semana).

---

## 1. Resumen ejecutivo

- Desde el 16 de junio se han integrado **7 commits grandes** que cubren: seguridad de rutas y modelos, panel docente, perfil de usuario, calendario de encuentros, workspace de equipos (fases/tareas/reflexiones), rediseño del wizard Startup Day y el renombrado Sesión→Encuentro con datos curriculares (RA/CE) reales.
- El roadmap restante (`TAREAS_PENDIENTES.txt` + testing/seguridad/marketing añadidos en esta revisión, ver secciones 4 y 5) estima **169-244h** de trabajo efectivo, con punto medio **206,5h ≈ 47 jornadas ≈ 9,4 semanas**.
- **Fecha de fin estimada (a partir de hoy, 28/07/2026):**

  | Escenario | Horas | Fin estimado |
  |---|---|---|
  | Optimista (todo sale bien, sin imprevistos) | 169h | **18/09/2026** |
  | Medio (más realista) | 206,5h | **30/09/2026** |
  | Pesimista (imprevistos habituales) | 244h | **13/10/2026** |

  **⚠ Sobre el plazo de septiembre pedido:** solo el escenario optimista —cero imprevistos, sin bloqueos, sin desviaciones del plan— llega dentro de septiembre, y por poco (18/09). El escenario medio, que es el que refleja mejor cómo se ha desarrollado el proyecto hasta ahora (ver punto siguiente y sección 6), termina el **30/09**, y cualquier imprevisto adicional lo empuja a **octubre**. Septiembre es alcanzable solo en el mejor de los casos, no como plazo garantizado.

  **El 13/10 (pesimista) tampoco es un techo fijo.** Es solo el peor caso *que se puede calcular con lo que ya sabemos hoy* (jornadas altas de cada tarea). No cubre lo que todavía no sabemos: un hallazgo de seguridad grande, un cambio de alcance a mitad de fase, una baja de varios días, o que la Fase 7 (Evaluación 360) resulte más compleja de lo previsto tras investigarla. Por los mismos motivos de la sección 6 (una sola persona, sin margen semanal real, sin paralelismo, sin segunda opinión en decisiones grandes), el plazo final no es una fecha que se pueda prometer con seguridad — es una fecha que se puede seguir moviendo mientras esos factores sigan presentes.
- El trabajo real de la última semana **se ha desviado del orden planificado**: el plan preveía cerrar Fase 0 (datos RA/CE) y Fase 1 (seguridad/aislamiento) antes de tocar Fase 2 (workspace alumnado), pero el commit del 27/07 ya construye pantallas de acceso de alumnado (`MisGrupos`, `PantallaAcceso`, `EntrarWorkspace`) — trabajo de Fase 2 — junto con un fix de IDOR no planificado. Esto es normal en desarrollo real, pero desplaza las fechas del calendario original.
- Hay cambios sin commitear en este momento (ajustes en `EquipoWorkspace.vue`, `SidePanel.vue`, `EquipoPublicoController.php`) — trabajo en curso, ver sección 3.
- **La plataforma está en fase beta**: antes de considerarla lista para producción real (no solo "funciona"), falta un pase formal de testing funcional, una auditoría de seguridad más profunda que el backlog actual de `SECURITY_FIXES.md`, y una validación con usuarios reales (centro + empresa piloto) de cara a la salida a producción/marketing. Estas tres cosas se han añadido como tareas explícitas en la Fase 11 (sección 4) y son las responsables de que el cierre se mueva de mediados a finales de septiembre respecto a la estimación anterior.

---

## 2. Tareas finalizadas (16 jun – 27 jul 2026)

| Fecha | Commit | Qué se entregó |
|---|---|---|
| 26/06 | `bc0f39b` — panel docente, sidebar permanente, seguridad y migraciones | Middleware `EnsureIsSuperAdmin`, FormRequests de sesiones/microretos, `SECURITY_FIXES.md` inicial (backlog de auditoría), rediseño de sidebar y panel docente, gestión de usuarios ampliada |
| 26/06 | `aa1bb0e` — perfil de usuario, imagen de centro, panel de inicio | `UpdatePerfilRequest`, imagen de centro educativo (migración + seeder), vista `MiUsuario.vue`, panel de inicio del docente ampliado |
| 29/06 | `17d0cd4` — calendario interactivo, panel de notas, docs FABLAB | Documentación de arquitectura (`CLAUDE.md`, `FABLAB_MICROPROYECTOS_SPEC.md`, `docs/fablab-gap-analysis.md`) — sin cambios de código, solo documentación técnica |
| 03/07 | `a9455d1` — workspace de alumnos: fases, tareas y reflexiones | Módulo `Equipo` completo (miembros, fases, tareas, reflexiones), políticas de autorización (`MicroproyectoPolicy`), middleware `EnsureIsDocente`, vista pública `UnirseEquipo.vue` y `EquipoWorkspace.vue` (primera versión) |
| 07/07 | `4665726` — invertir relación sesión-proyecto, rediseño wizard | Migración que invierte la relación Sesión↔Microproyecto, `EquipoPrototipo`, rediseño amplio de `StartupDayWizard.vue` |
| 16/07 | `3112bdc` — rename Sesión→Encuentro, calendario por fase, RA/CE reales | Renombrado completo del dominio Sesión→Encuentro (modelo, tabla, controllers, requests, vistas), `RaCeCatalogoService`, comando `RepararRaCeMicroretos`, informes de calidad de datos |
| 27/07 | `dc99da8` — ficha pública del reto, IDOR fix, pantallas de acceso alumnado | Ficha pública de reto vía token (`MicroretoFichaService`), **fix de IDOR** en `EquipoGestionController` (un docente ya solo gestiona equipos de sus propios encuentros), migración a FormRequests, tareas genéricas vs. complejas, endpoint `mis-grupos`, nuevas vistas `MisGrupos.vue` / `PantallaAcceso*.vue` / `EntrarWorkspace.vue`, creación de `TAREAS_PENDIENTES.txt` |

---

## 3. Tareas en curso (ahora, sin commitear)

Trabajo abierto sobre lo entregado el 27/07, todavía sin cerrar:

- `EquipoPublicoController.php` — expone los nuevos campos `tipo` y `obligatoria` de las tareas en el workspace público.
- `EquipoWorkspace.vue` — ajustes de UI ligados a esos mismos campos (tareas genéricas/complejas).
- `SidePanel.vue` — continúa el rediseño de navegación iniciado el 27/07.

No hay estimación de horas propia en el roadmap para este pulido puntual; se considera parte del cierre de la Fase 2 (ver sección 4).

---

## 4. Tareas previstas — roadmap restante

Basado en `TAREAS_PENDIENTES.txt`, con la **Fase 11 ampliada** en esta revisión para reflejar que se trata de una versión **beta**: no basta con "probarla", hace falta un pase de QA (control de calidad: pruebas sistemáticas antes de lanzar) funcional formal, una auditoría de seguridad dedicada tipo pentest (simular un ataque real para encontrar huecos) siguiendo OWASP (la guía estándar de buenas prácticas de seguridad web) — más allá de ir tachando ítems de `SECURITY_FIXES.md` — y una validación de cara al mercado real (centro/empresa piloto, páginas públicas) antes de dar por cerrado el proyecto. Orden pensado por dependencias, pero **reordenable según prioridad** — de hecho, ya se ha reordenado una vez en la práctica (ver sección 6).

Fechas de inicio/fin calculadas para el **escenario medio** (206,5h totales), a partir de hoy 28/07/2026, respetando el horario real (L-Ma 5h, Mi-Vi 4h, sin fines de semana):

| Fase | Contenido | Estimación | Fechas (escenario medio) | Estado |
|---|---|---|---|---|
| **0** — Datos base | 0.1 Importar RA/CE desde Excel · 0.2 Limpiar/crear retos | 10-14h | 28 jul – 30 jul (**julio**) | 🟡 Parcial — RA/CE con ids reales ya resuelto (16/07); falta la importación completa desde Excel |
| **1** — Seguridad y aislamiento | 1.1 Aislamiento de progreso por equipo · 1.2 Pseudonimización de alumnado · 1.3 Revisar `SECURITY_FIXES.md` · 1.4 Redirecciones por rol | 18-27h | 30 jul – 6 ago (**julio-agosto**) | 🟡 Parcial — el fix de IDOR de equipos (27/07) cubre parte de 1.1 |
| **2** — Núcleo del perfil alumnado | 2.1 Login/pantalla simplificada · 2.2 Workspace del proyecto | 10-14h | 6 ago – 11 ago (**agosto**) | 🟡 En marcha — pantallas de acceso ya creadas (27/07 + hoy), falta cerrar y ocultar menú/rol |
| **3** — IA, informe final y biblioteca | 3.1 IA en tareas · 3.2 Informe con validación curricular · 3.3 Biblioteca de proyectos | 22-30h | 11 ago – 19 ago (**agosto**) | ⬜ Pendiente |
| **4** — Encuentros y validación | 4.1 Solo asociar proyectos validados por docente | 3-4h | 19 ago – 20 ago (**agosto**) | ⬜ Pendiente |
| **5** — Multimedia | 5.1 Banco de imágenes de proyecto · 5.2 Imagen de centro | 9-14h | 20 ago – 24 ago (**agosto**) | ⬜ Pendiente |
| **6** — Noticias | 6.1 CRUD · 6.2 CKEditor + sanitización · 6.3 Rol editor | 11-16h | 24 ago – 27 ago (**agosto**) | ⬜ Pendiente |
| **7** — Evaluación 360 | 7.1 Investigación · 7.2 Diseño · 7.3 Implementación | 18-26h | 28 ago – 3 sep (**agosto-septiembre**) | ⬜ Pendiente — mayor incertidumbre |
| **8** — Seguimiento docente | 8.1 Vistas de seguimiento · 8.2 Optimización panel docente | 10-14h | 4 sep – 8 sep (**septiembre**) | ⬜ Pendiente |
| **9** — Calidad IA pedagógica | 9.1 Revisar retos generados · 9.2 Mejorar propuestas | 12-16h | 8 sep – 11 sep (**septiembre**) | ⬜ Pendiente |
| **10** — UX y documentación | 10.1 Ayuda global · 10.2 Guías tour · 10.3 Documentación | 14-20h | 14 sep – 17 sep (**septiembre**) | ⬜ Pendiente |
| **11** — Beta: QA (control de calidad), seguridad, marketing y despliegue | 11.1 Pruebas funcionales completas (regresión beta) · **11.2 Auditoría de seguridad formal (pentest = simular un ataque real / OWASP = guía estándar de seguridad web)** · **11.3 Testeo de marketing / validación con usuarios piloto** · 11.4 Deploy a producción · 11.5 Buffer imprevistos | 32-49h | 17 sep – 30 sep (**septiembre**) | ⬜ Pendiente — ampliada en esta revisión |

**Total restante estimado:** 169-244h (punto medio 206,5h) — antes de esta revisión (sin testing/seguridad/marketing dedicados) era 153-220h; la diferencia (+16-24h) es exactamente lo que añade convertir la Fase 11 en un cierre de beta real en vez de un simple "probar y desplegar".

**Qué cubre cada parte nueva de la Fase 11:**
- **11.1 Pruebas funcionales (beta):** regresión manual de las 12 fases anteriores, con los distintos roles (docente/admin/alumnado/empresa), no solo "que no rompa" sino que el flujo completo tenga sentido de punta a punta.
- **11.2 Auditoría de seguridad formal:** revisión dedicada tipo pentest/OWASP sobre la app ya completa (autenticación, IDOR, XSS, rate limiting, cabeceras) — distinta de ir resolviendo ítems puntuales de `SECURITY_FIXES.md` sobre la marcha; aquí se revisa el conjunto ya integrado.
- **11.3 Testeo de marketing / validación con usuarios piloto:** probar la experiencia con un centro y una empresa reales antes de un lanzamiento amplio, y revisar las páginas públicas de cara al exterior (landing de Startup Day, ficha pública de reto) — son la cara visible del producto para quien no tiene cuenta.

---

## 5. Calendario previsto — horas, días y mes (escenario medio, 206,5h)

Horario real: L-V 8:30h, con jornadas de 5h (L, Ma) y 4h (Mi, Ju, Vi) = **22h/semana**. Calculado con script día a día desde **hoy, 28/07/2026**, para evitar errores de arrastre manual entre tareas. Incluye ya las horas de testing/seguridad/marketing añadidas en la Fase 11.

| Semana | Fechas | Mes | Tareas trabajadas | Horas semana |
|---|---|---|---|---|
| 1 | 28-30 jul | Julio | 0.1 Importar RA/CE → 0.2 Limpiar retos → 1.1 Aislamiento (inicio) | 13h (semana corta, empieza en martes) |
| 2 | 3-7 ago | Agosto | 1.1 (fin) → 1.2 Pseudonimización → 1.3 SECURITY_FIXES.md → 1.4 Redirecciones → 2.1 Login alumnado (inicio) | 22h |
| 3 | 10-14 ago | Agosto | 2.1 (fin) → 2.2 Workspace alumnado → 3.1 IA en tareas → 3.2 Informe RA/CE (inicio) | 22h |
| 4 | 17-21 ago | Agosto | 3.2 (fin) → 3.3 Biblioteca → 4.1 Validación encuentros → 5.1 Imagen proyecto | 22h |
| 5 | 24-28 ago | Agosto | 5.1 (fin) → 5.2 Imagen centro → 6.1 CRUD noticias → 6.2 CKEditor → 6.3 Rol editor → 7.1 Investigación eval. 360 (inicio) | 22h |
| 6 | 31 ago-4 sep | Ago-Sep | 7.1 (fin) → 7.2 Diseño eval. 360 → 7.3 Implementación eval. 360 → 8.1 Seguimiento alumnado (inicio) | 22h |
| 7 | 7-11 sep | Septiembre | 8.1 (fin) → 8.2 Optimización panel → 9.1 Revisar retos IA → 9.2 Mejorar propuestas IA | 22h |
| 8 | 14-18 sep | Septiembre | 10.1 Ayuda global → 10.2 Guías tour → 10.3 Documentación → **11.1 Pruebas funcionales beta** (inicio) | 22h |
| 9 | 21-25 sep | Septiembre | 11.1 (fin) → **11.2 Auditoría de seguridad formal** → **11.3 Testeo de marketing / usuarios piloto** (inicio) | 22h |
| 10 | 28-30 sep | Septiembre | 11.3 (fin) → 11.4 Deploy a producción → 11.5 Buffer imprevistos → **cierre 30/09/2026** | 8h (semana corta, termina en miércoles) |

**Comparativa de escenarios (fin estimado desde hoy 28/07/2026):**

| Escenario | Horas totales | Semanas (22h/sem) | Fecha de fin |
|---|---|---|---|
| Optimista — todo sale bien | 169h | ~7,7 semanas | **18/09/2026** |
| Medio — más realista | 206,5h | ~9,4 semanas | **30/09/2026** |
| Pesimista — imprevistos habituales | 244h | ~11,1 semanas | **13/10/2026** |

**Lectura para el plazo de septiembre:** para llegar dentro de septiembre hace falta el escenario optimista sin desviaciones — algo que ya no ha ocurrido ni una sola semana desde que empezó el roadmap (sección 6). El escenario medio, que es el que mejor representa el ritmo real de trabajo, cierra el **30 de septiembre**, literalmente el último día del mes, sin margen para ningún imprevisto adicional. Cualquier bloqueo (una duda de alcance, un hallazgo de seguridad, una semana con menos horas disponibles) lo empuja a octubre.

**El 13/10 no es el límite real, es donde se acaban los números que podemos calcular hoy.** Más allá de esa fecha entramos en terreno que no se puede estimar con datos: no sabemos si aparecerá otro hallazgo de seguridad como el del 27/07, si la Fase 7 cambiará de alcance al investigarla, o si habrá alguna semana con menos de 22h disponibles. Con una sola persona en el proyecto, sin margen semanal real y sin nadie que revise las decisiones grandes antes de tomarlas (sección 6), cada uno de esos imprevistos se traduce directamente en más tiempo, sin nada que lo absorba. Por eso el rango honesto no es "hasta el 13/10 como mucho", sino "18/09 en el mejor caso, y a partir de ahí, cuanto más se aleje de ese mejor caso, menos predecible es la fecha real."

---

## 6. Dificultades encontradas en el día a día

1. **22h/semana no son 22h de desarrollo puro.** El horario cubre 5 jornadas de 4-5h, pero cualquier imprevisto (una reunión, una duda de un docente, un bug urgente en producción) consume directamente ese margen — no hay tiempo "de sobra" que absorba desviaciones. Una sola jornada perdida ya representa ~20% de una semana completa.
2. **El orden real de trabajo se desvía del plan.** El roadmap preveía cerrar Fase 0 (datos) y Fase 1 (seguridad/aislamiento) antes de tocar Fase 2 (alumnado). En la práctica, el commit del 27/07 ya adelantó parte de Fase 2 (pantallas de acceso de alumnado) y coló un fix de IDOR no planificado (`EquipoGestionController`) al descubrirlo durante otro trabajo. Cada vez que aparece un hallazgo de este tipo, se prioriza sobre el plan porque es una vulnerabilidad activa — correcto de hacer, pero no estaba presupuestado en horas.
3. **Trabajar en solitario elimina el paralelismo.** Todo el desarrollo (BD, backend, frontend, seguridad, documentación) pasa por una sola persona de forma secuencial. No hay revisión de código en paralelo ni reparto de fases independientes (p. ej., mientras se cierra seguridad no se puede avanzar en paralelo en UX), lo que hace que cualquier bloqueo (una duda de enfoque, un bug difícil de reproducir) detenga *todo* el avance, no solo una parte.
4. **Falta de segunda opinión en decisiones de diseño.** Cambios estructurales grandes (el rename Sesión→Encuentro del 16/07, la inversión de la relación sesión-proyecto del 07/07) se han decidido y ejecutado en solitario. Sin una revisión externa antes de implementar, el riesgo de tener que revertir o rehacer trabajo si el enfoque no es el correcto recae enteramente en el tiempo ya limitado disponible.
5. **Fases con alta incertidumbre inflan el margen de error total.** La Fase 7 (Evaluación 360) tiene una horquilla amplia (18-26h) precisamente porque depende de una investigación previa que puede cambiar el alcance — cualquier sorpresa ahí se propaga al resto del calendario, que es secuencial.
6. **El buffer del final (Fase 11.5, 8-12h) es el único margen de seguridad de todo el proyecto**, y está al final del calendario — si los imprevistos se acumulan antes de llegar ahí, el buffer se consume o se agota antes de tener margen real para QA (control de calidad) y despliegue.
7. **El plazo de septiembre pedido no incluía margen para cerrar bien la beta.** La estimación previa a esta revisión (153-220h) no contemplaba un pase de seguridad formal ni validación con usuarios reales antes de salir a producción — solo "probar la beta y desplegar". Al hacer explícito ese trabajo (sección 4), el punto medio del proyecto pasa de mediados a finales de septiembre (30/09), y el pesimista se va a mediados de octubre. No es que el trabajo haya crecido: es que antes no estaba contado.
8. **Septiembre es una fecha comunicada "hacia arriba", no una estimación técnica.** Cuando el plazo lo fija quien no ve el día a día del desarrollo, existe el riesgo de que se interprete como comprometido en firme, cuando en realidad solo el escenario optimista (sin imprevistos, sin hallazgos de seguridad, sin cambios de alcance) lo cumple. Comunicarlo ahora, con margen, es más barato que descubrirlo en septiembre.
9. **El retraso no está acotado en octubre — es imprevisible, no solo "más largo".** El escenario pesimista (13/10) se calcula con las mismas tareas que ya conocemos, solo que en su horquilla de horas más alta. No incluye nada que hoy no sepamos que va a pasar: otro hallazgo de seguridad como el del 27/07, un cambio de alcance en la Fase 7 (Evaluación 360) al investigarla, una baja o imprevisto personal de varios días, o un bloqueo técnico que obligue a rehacer algo ya construido. Y como el proyecto lo lleva una sola persona junior, sin margen semanal real (punto 1), sin paralelismo (punto 3) y sin segunda opinión en decisiones grandes (punto 4), no hay nada en el proceso que absorba esos imprevistos — cada uno se traduce directamente en más semanas, sin techo definido. Por eso, más que "en el peor caso, mediados de octubre", lo honesto es decir que a partir del escenario optimista (18/09), cuanto más se aleje la realidad de ese mejor caso, menos se puede predecir cuándo se acabará.

---

## 7. Propuestas para mitigar

1. **Reservar explícitamente un "colchón de imprevistos" semanal**, no solo al final. Por ejemplo, presupuestar 1 de las 22h semanales como margen no asignado a ninguna fase, en vez de confiar todo el margen al buffer final de Fase 11.3.
2. **Congelar el orden de fases por sprint corto (1 semana) en vez de por todo el roadmap.** Si aparece un hallazgo tipo IDOR a mitad de una fase, decidir explícitamente si se aborda ahora o se anota en `SECURITY_FIXES.md`/`TAREAS_PENDIENTES.txt` para el siguiente sprint, en lugar de intercalarlo de forma reactiva. Esto hace visible el coste de cada desvío del plan.
3. **Pedir una revisión de código puntual (no continua) en los cambios estructurales grandes** — específicamente antes de migraciones que renombran/invierten relaciones (como Sesión→Encuentro), donde revertir después es caro. No requiere una segunda desarrolladora a tiempo completo: basta con una revisión de diseño de 30-60 min por parte de alguien del equipo antes de ejecutar la migración.
4. **Reducir la incertidumbre de Fase 7 (Evaluación 360) antes de que llegue su turno**, adelantando la investigación (7.1) como tarea de bajo compromiso en huecos pequeños (p. ej. los últimos 30 min de una jornada), para que cuando llegue el sprint de implementación la horquilla de horas ya esté más ajustada.
5. **Reportar el desvío de fechas de forma proactiva y periódica** (semanal o quincenal) en vez de descubrirlo al final del calendario — este mismo informe puede repetirse cada 2 semanas comparando lo planificado contra lo entregado, para que cualquier ajuste de expectativas (fecha de entrega, alcance) se negocie con margen y no bajo presión.
6. **Si el alcance total (169-244h) no es negociable pero las 22h/semana sí lo son puntualmente**, valorar con el responsable del proyecto si es viable ampliar horas en semanas concretas de mayor carga (p. ej. Fase 7) a cambio de menos horas en semanas de tareas más mecánicas (p. ej. Fase 5 o 6), en lugar de mantener un ritmo fijo que no refleja la dificultad real de cada fase.
7. **Comunicar ahora, no en septiembre, que el plazo realista es "finales de septiembre en el mejor caso, posible deslizamiento a octubre".** Presentar las tres fechas de la sección 5 (18/09 optimista, 30/09 medio, 13/10 pesimista) en vez de una fecha única, para que quien decide el lanzamiento pueda elegir con datos: adelantar el alcance (quitar fases del MVP de septiembre y dejarlas para después), aceptar el deslizamiento, o aportar más horas/recursos puntuales.
8. **No recortar la Fase 11 (testing/seguridad/marketing) para intentar encajar en septiembre.** Es la parte que menos conviene comprimir: un fallo de seguridad o una mala primera impresión con el centro/empresa piloto cuesta mucho más caro después del lanzamiento que las 32-49h que cuesta hacerlo bien antes.

---

*Documento generado el 28/07/2026 a partir de `git log` (commits desde 16/06/2026) y `TAREAS_PENDIENTES.txt`. Actualizar en la siguiente revisión de sprint.*
