# Análisis comparativo FAB LAB ↔ Proyecto actual (DuaLab Studio)

> Generado el 2026-06-29. Basado en lectura directa de `app/Models/`, `database/migrations/`,
> `routes/api.php`, `frontend-microretos/src/views/` y `frontend-microretos/src/router/index.js`.

---

## Resumen ejecutivo

| Métrica | Valor |
|---|---|
| **Cobertura global estimada** | ~28 % |
| **Fases con soporte** | F0 parcial, F2 parcial, F3 parcial |
| **Fases sin soporte** | F1 (Análisis), F4 (Cierre) |
| **Brechas P0 (bloqueantes)** | 6 |
| **Brechas P1 (obligatorias)** | 9 |
| **Brechas P2 (mejoras)** | 7 |

### Estado por fase

| Fase | Estado | Cobertura estimada |
|---|---|---|
| F0 — Startup Day | 🟡 Parcial | 30 % — recoge *resultados* (equipo, diseño, RA/CE), no *el proceso* (brainstorming, pitches, contrato de equipo) |
| F1 — Análisis | ❌ Ausente | 10 % — solo texto libre en `diseno_reto`, sin entrevistas ni formularios estructurados |
| F2 — Diseño/Prototipado | 🟡 Parcial | 40 % — fases + recursos (Cloudinary), sin tipos de prototipo ni pitch estructurado |
| F3 — Desarrollo/Entrega | 🟡 Parcial | 45 % — validación empresa operativa, recursos como entregables, sin actas ni validación académica formal |
| F4 — Cierre | ❌ Ausente | 15 % — KPIs y empresa_validado existen, sin reflexiones, rúbricas ni informe final |

### Notas previas al análisis

1. **Stack real**: Vue 3 SPA + Laravel 11 API (sin Inertia). Las rutas de archivo sugeridas usan esta convención.
2. **Nomenclatura**: el proyecto llama `microproyectos` a lo que la spec llama *microproyecto ejecutado*, y `microretos` a las fichas-reto de empresa.
3. **Roles existentes**: `SUPERADMIN(1)`, `DOCENTE(2)`, `EMPRESA(3)`, `ADMIN(4)`. No existe `ALUMNADO` ni `DINAMIZADOR`.

---

## Cobertura por fase

### Fase 0 — Startup Day

**Estado:** 🟡 Parcial

**Qué está cubierto:**
- Conformación de equipo: `microproyectos.equipo` (JSON con `alumnos[{nombre, rol}]` y `docente_responsable`).
- Vinculación al reto (microreto): FK `microreto_id` + autocomplete desde `MicroretoIAController`.
- Diseño del reto: `diseno_reto` JSON (`descripcion`, `pregunta_reto`, `restricciones`, `entregables`).
- Módulos, RA/CE vinculados al currículo.

**Entidades del modelo asociadas:**
`microproyectos`, `sesiones`, `microretos`, `empresas`, `ciclos_formativos`, `modulos`, `resultados_aprendizaje`, `criterios_evaluacion`

**Gaps detectados:**

- **GAP-F0-001** | Prioridad: P1 | El campo `equipo.alumnos[].rol` acepta texto libre; los cuatro roles canónicos del spec (portavoz, tiempos, documentación, foco) no están definidos como enum ni validados. | Archivo: `app/Http/Requests/StoreMicroproyectoRequest.php` (nueva), `database/migrations/xxxx_add_rol_enum_to_equipo.php` (o validación en request)

- **GAP-F0-002** | Prioridad: P1 | No existe "contrato de equipo" (compromiso firmado digitalmente o registrado). El wizard pasa directamente de equipo → currículo sin este artefacto. | Archivo: `microproyectos.equipo` (añadir campo `contrato_firmado`, `contrato_fecha`) + `frontend-microretos/src/views/StartupDayWizard.vue` paso 3

- **GAP-F0-003** | Prioridad: P1 | No existe captura de "problema en una sola frase" como campo explícito. El `diseno_reto.descripcion` es texto libre multiusos. | Archivo: `microproyectos.diseno_reto` (añadir `problema_frase`), `StartupDayWizard.vue` paso 5

- **GAP-F0-004** | Prioridad: P2 | No hay herramienta de ideación (brainstorm + matriz esfuerzo-impacto). La solución seleccionada no está capturada con su justificación. | Archivo: nuevo `frontend-microretos/src/views/StartupDayIdeacion.vue` + campo `ideacion` en `microproyectos`

- **GAP-F0-005** | Prioridad: P2 | El "cuaderno StartupDay" digital (bloques 1–4 en tiempo real durante la jornada) no existe. El wizard es un formulario offline-style, no un instrumento dinámico de jornada. | Archivo: nuevo `frontend-microretos/src/views/StartupDayCuaderno.vue`

- **GAP-F0-006** | Prioridad: P2 | Pitch de cierre de Startup Day no tiene estructura capturada ni feedback registrado. El campo `diseno_reto.entregables` es texto libre. | Archivo: tabla nueva `pitches` + `frontend-microretos/src/views/StartupDayPitch.vue`

---

### Fase 1 — Análisis

**Estado:** ❌ Ausente

**Qué está cubierto:**
- `diseno_reto.descripcion` y `diseno_reto.restricciones` recogen parte del análisis de forma narrativa.
- El microreto origen (`microretos`) contiene `quien_es`, `dia_a_dia`, `dificultades`, `limitaciones`, `que_necesitan` — información de partida de la empresa.

**Entidades del modelo asociadas:**
`microretos` (lectura; no existe tabla propia de esta fase)

**Gaps detectados:**

- **GAP-F1-001** | Prioridad: P0 | No existe tabla ni modelo `interviews`. La plantilla de entrevista (10 preguntas estructuradas, datos del entrevistado, cierre) no está digitalizada. Sin esto, el equipo no puede registrar las entrevistas en la plataforma. | Archivo: `database/migrations/xxxx_create_interviews_table.php`, `app/Models/Interview.php`, `app/Http/Controllers/InterviewController.php`, `frontend-microretos/src/views/Fase1InterviewForm.vue`

- **GAP-F1-002** | Prioridad: P0 | No existe tabla `survey_responses`. El formulario de empresa (secciones A–F: percepción del problema, recursos, expectativas, impacto) no tiene soporte en BD. | Archivo: `database/migrations/xxxx_create_survey_responses_table.php`, `app/Models/SurveyResponse.php`, `frontend-microretos/src/views/Fase1SurveyForm.vue`

- **GAP-F1-003** | Prioridad: P0 | No existe "informe inicial" como entidad. El análisis de Fase 1 (visión del estado inicial, necesidades, limitaciones, oportunidades, alcance) no se genera ni almacena de forma estructurada. | Archivo: `database/migrations/xxxx_create_phase_reports_table.php`, `app/Models/PhaseReport.php`, campo `fase1_informe` en `microproyectos` o tabla independiente

- **GAP-F1-004** | Prioridad: P1 | Los endpoints de entrevistas y formularios no existen en `routes/api.php`. | Archivo: `routes/api.php` (añadir grupo `/startup/proyectos/{uuid}/fase1/...`)

- **GAP-F1-005** | Prioridad: P2 | No hay workflow de síntesis + validación interna (exposición al dinamizador/profesorado para dar feedback antes de pasar a Fase 2). | Archivo: tabla `feedback_entries` (ver GAP-F0-006), endpoint y UI de feedback por fase

---

### Fase 2 — Diseño y Prototipado

**Estado:** 🟡 Parcial

**Qué está cubierto:**
- `diseno_microproyecto` JSON: `fases[]` (nombre, descripción, duración), `metodologia`, `cronograma`. Cubierto en el wizard paso 6.
- Recursos (vídeos + documentos) en Cloudinary vía `microproyecto_recursos`. Pueden actuar como evidencia del prototipo.
- `fundamentacion` JSON: `contexto`, `justificacion`, `innovacion`.

**Entidades del modelo asociadas:**
`microproyectos` (`diseno_microproyecto`, `fundamentacion`, `resumen`), `microproyecto_recursos`

**Gaps detectados:**

- **GAP-F2-001** | Prioridad: P1 | Los prototipos no tienen tipo explícito (croquis papel / storyboard / maqueta física / prototipo digital / diagrama de procesos). `microproyecto_recursos.tipo` solo distingue `video`/`documento`. | Archivo: `database/migrations/xxxx_add_prototipo_type_to_recursos.php` (añadir enum), `app/Models/MicroproyectoRecurso.php`

- **GAP-F2-002** | Prioridad: P1 | No hay iteraciones de prototipo. Cada recurso es una versión, pero no hay campo `iteracion` ni historial de feedback entre versiones. | Archivo: `database/migrations/xxxx_add_iteracion_to_microproyecto_recursos.php`, `app/Models/MicroproyectoRecurso.php`

- **GAP-F2-003** | Prioridad: P1 | La estructura del pitch de Fase 2 (5 secciones: título+reto, hallazgos clave, propuesta, prototipo, próximos pasos) no está capturada. El wizard tiene texto libre en `resumen.texto`. | Archivo: nueva clave `pitch_estructura` en `microproyectos.diseno_microproyecto` o tabla `pitches`

- **GAP-F2-004** | Prioridad: P2 | No hay mecanismo de "convergencia validada" (empresa + alumnado + profesorado acuerdan el rumbo antes de Fase 3). Solo existe validación empresa de Fase 3/4. | Archivo: tabla `feedback_entries` con campo `fase` y `origen` (empresa/profesorado/dinamizador)

---

### Fase 3 — Desarrollo y Entrega

**Estado:** 🟡 Parcial

**Qué está cubierto:**
- `microproyecto_recursos`: vídeos + documentos como evidencias de ejecución.
- Validación empresa: `empresa_validado`, `validacion_empresa` JSON, landing pública por token.
- Estados del proyecto: `borrador` / `publicado` / `archivado`.
- `enviado_a_empresa_mail`, `empresa_no_valida_aun` como flags de seguimiento.

**Entidades del modelo asociadas:**
`microproyectos`, `microproyecto_recursos`

**Gaps detectados:**

- **GAP-F3-001** | Prioridad: P0 | No existe validación académica (profesorado) separada de la validación operativa (empresa). La plataforma solo registra la respuesta de empresa; el docente no puede validar formalmente los entregables desde la app. | Archivo: `database/migrations/xxxx_add_validacion_academica_to_microproyectos.php` (campos `docente_validado`, `docente_validacion_fecha`, `docente_notas`), `routes/api.php` (endpoint PATCH), `frontend-microretos/src/views/StartupDayDetalle.vue`

- **GAP-F3-002** | Prioridad: P1 | No existe tabla `validation_acts` (actas formales). El `validacion_empresa` es solo un JSON de respuestas, no un documento de acta con firma/fecha/estado oficial. | Archivo: `database/migrations/xxxx_create_validation_acts_table.php`, `app/Models/ValidationAct.php`

- **GAP-F3-003** | Prioridad: P2 | No hay proceso de QA interno (revisión colaborativa antes de entregar). El wizard no tiene un paso de revisión/checklist antes de cambiar estado a `publicado`. | Archivo: nuevo paso o sección en `frontend-microretos/src/views/StartupDayWizard.vue` paso 8

---

### Fase 4 — Cierre

**Estado:** ❌ Ausente

**Qué está cubierto:**
- `kpis.lista[]` en microproyectos (texto libre).
- `empresa_validado` flag que actúa como señal de cierre.
- `microproyecto_recursos` podría contener la presentación final como documento.

**Entidades del modelo asociadas:**
`microproyectos` (`kpis`, `empresa_validado`)

**Gaps detectados:**

- **GAP-F4-001** | Prioridad: P0 | No existe tabla `reflections`. Las 5 preguntas de reflexión individual (qué aprendí / parte más desafiante / habilidad practicada / qué mejoraría / aplicabilidad futura) y la reflexión grupal (qué funcionó / qué mejorar / aplicabilidad) no tienen soporte. | Archivo: `database/migrations/xxxx_create_reflections_table.php`, `app/Models/Reflection.php`, `app/Http/Controllers/ReflectionController.php`, `frontend-microretos/src/views/Fase4Reflexion.vue`

- **GAP-F4-002** | Prioridad: P0 | No existe sistema de evaluación con rúbricas. El modelo `ResultadoAprendizaje` existe pero solo como catálogo; no hay tabla `evaluations` donde el profesorado registre la nota/evidencia por RA para cada alumno/equipo. | Archivo: `database/migrations/xxxx_create_evaluations_table.php`, `app/Models/Evaluation.php`, `app/Http/Controllers/EvaluationController.php`, `frontend-microretos/src/views/Fase4Evaluacion.vue`

- **GAP-F4-003** | Prioridad: P1 | No existe "informe del microproyecto" (documento compilado: objetivos, fases, tareas, resultados, KPIs, aprendizajes). El wizard genera campos sueltos pero no un informe exportable. | Archivo: nuevo composable `frontend-microretos/src/composables/useMicroproyectoInforme.js` + endpoint de exportación PDF en `app/Http/Controllers/MicroproyectoController.php`

- **GAP-F4-004** | Prioridad: P1 | Los KPIs (`kpis.lista[]`) son texto libre sin valor medido, sin ODS asociados, sin comparación antes/después. | Archivo: migración para cambiar estructura de `kpis` a `[{descripcion, valor_meta, valor_real, ods_codigo}]`, `StartupDayWizard.vue` paso 7

- **GAP-F4-005** | Prioridad: P1 | No existe acta de validación de empresa separada del formulario de validación (cuatro preguntas fijas). El spec pide un "acta" formal. | Ver GAP-F3-002 (`validation_acts`)

- **GAP-F4-006** | Prioridad: P2 | La presentación final de 5 diapositivas (D1 reto inicial → D5 aprendizajes) no tiene plantilla estructurada en la plataforma. Solo puede adjuntarse como documento Cloudinary. | Archivo: nueva sección en wizard paso 8 o `frontend-microretos/src/views/Fase4Presentacion.vue`

---

## Cobertura por rol (RBAC)

| Rol spec | Rol en plataforma | Estado | Observaciones |
|---|---|---|---|
| **Alumnado (equipo)** | ❌ No existe | Ausente | Los alumnos aparecen como datos en `equipo.alumnos[]` pero no tienen cuenta de usuario, no pueden acceder a la plataforma, ni completar reflexiones ni ver su propio proyecto. |
| **Profesorado** | ✅ `DOCENTE (2)` | Implementado parcialmente | Puede crear sesiones, microretos y microproyectos. **Falta**: validación académica formal, registro de evaluación con rúbricas, acceso restringido a sus propios proyectos (actualmente ve todos). |
| **Dinamizador/a** | ❌ No existe | Ausente | No hay rol ni acceso diferenciado. Las funciones de dinamizador (facilitar plantillas, dar feedback por fase) no tienen soporte en RBAC. |
| **Empresa / tutor empresa** | ✅ `EMPRESA (3)` | Implementado parcialmente | Puede ver la biblioteca de microretos y la lista de microproyectos, y validar vía landing pública. **Falta**: acceso directo a sus propios proyectos filtrados, comunicación bidireccional, respuesta estructurada a Fase 1. |

### Brechas RBAC críticas

- **GAP-RBAC-001** | Prioridad: P0 | Falta rol `ALUMNADO (5)`. Sin él, los alumnos no pueden acceder a la plataforma para completar reflexiones (F4), ver el estado de su proyecto, ni subir evidencias. | Archivo: `database/migrations/xxxx_add_alumnado_role_to_users.php`, `app/Models/User.php` (añadir `ROLE_ALUMNADO = 5`), `frontend-microretos/src/router/index.js` (rutas alumnado)

- **GAP-RBAC-002** | Prioridad: P1 | Falta rol `DINAMIZADOR (6)`. Sin él, la facilitación metodológica no tiene acceso diferenciado del docente. | Archivo: mismos que GAP-RBAC-001

- **GAP-RBAC-003** | Prioridad: P1 | El docente ve TODOS los microproyectos (`Microproyecto::with(...)->get()`). Debería ver solo los suyos (filtrado por `sesiones.user_id`). | Archivo: `app/Http/Controllers/MicroproyectoController.php::index()` — añadir `whereHas('sesion', fn($q) => $q->where('user_id', auth()->id()))`

---

## Cobertura de plantillas / guías

| Plantilla/guía spec | Estado | Entidad/Tabla | Archivo UI |
|---|---|---|---|
| **Cuaderno StartupDay** (jornada digital) | ❌ Ausente | — | `frontend-microretos/src/views/StartupDayCuaderno.vue` (nuevo) |
| **Plantilla de entrevista** (Fase 1, 10 preguntas) | ❌ Ausente | tabla `interviews` | `frontend-microretos/src/views/Fase1InterviewForm.vue` (nuevo) |
| **Plantilla de formulario** (Fase 1, secciones A–F) | ❌ Ausente | tabla `survey_responses` | `frontend-microretos/src/views/Fase1SurveyForm.vue` (nuevo) |
| **Guía de prototipado** (tipos + principios) | ❌ Ausente | campo `tipo` en `microproyecto_recursos` | `StartupDayWizard.vue` paso 8 (ampliar) |
| **Estructura del pitch** (5 secciones, Fases 0/2/4) | 🟡 Parcial | `microproyectos.resumen` (texto libre) | `StartupDayWizard.vue` paso 8 (estructurar) |
| **Plantilla reflexión individual** (5 preguntas) | ❌ Ausente | tabla `reflections` | `frontend-microretos/src/views/Fase4Reflexion.vue` (nuevo) |
| **Plantilla reflexión grupal** (3 preguntas) | ❌ Ausente | tabla `reflections` | mismo archivo, sección grupal |
| **Rúbrica de evaluación RA** | ❌ Ausente | tabla `evaluations` | `frontend-microretos/src/views/Fase4Evaluacion.vue` (nuevo) |
| **Acta de validación empresa** | 🟡 Parcial | `microproyectos.validacion_empresa` JSON | `StartupDayLanding.vue` (ampliar con 4 preguntas + firma) |
| **Informe del microproyecto** (exportable) | ❌ Ausente | composable/PDF | `useMicroproyectoInforme.js` (nuevo) |

---

## Brechas priorizadas — Backlog accionable

| ID | Fase | Prio | Tipo | Descripción | Archivo sugerido |
|---|---|---|---|---|---|
| GAP-RBAC-001 | Transversal | P0 | Modelo+Migración+UI | Rol ALUMNADO (5) — usuarios alumnos con acceso restringido a su proyecto | `app/Models/User.php`, `xxxx_add_alumnado_role.php`, `router/index.js` |
| GAP-F1-001 | 1 | P0 | Modelo+Migración+Endpoint+UI | Tabla `interviews` + plantilla de entrevista digitalizada (10 preguntas estructuradas) | `app/Models/Interview.php`, `xxxx_create_interviews_table.php`, `app/Http/Controllers/InterviewController.php`, `Fase1InterviewForm.vue` |
| GAP-F1-002 | 1 | P0 | Modelo+Migración+Endpoint+UI | Tabla `survey_responses` + formulario Fase 1 (secciones A–F) | `app/Models/SurveyResponse.php`, `xxxx_create_survey_responses_table.php`, `Fase1SurveyForm.vue` |
| GAP-F1-003 | 1 | P0 | Modelo+Migración | Tabla/campo `phase_reports` — informe inicial Fase 1 (visión estado, necesidades, alcance) | `app/Models/PhaseReport.php`, `xxxx_create_phase_reports_table.php` |
| GAP-F3-001 | 3 | P0 | Migración+Endpoint+UI | Validación académica docente separada de validación empresa | `xxxx_add_validacion_academica_to_microproyectos.php`, `MicroproyectoController.php`, `StartupDayDetalle.vue` |
| GAP-F4-001 | 4 | P0 | Modelo+Migración+Endpoint+UI | Tabla `reflections` — reflexiones individuales (5 preguntas) y grupales (3 preguntas) | `app/Models/Reflection.php`, `xxxx_create_reflections_table.php`, `ReflectionController.php`, `Fase4Reflexion.vue` |
| GAP-F4-002 | 4 | P0 | Modelo+Migración+Endpoint+UI | Tabla `evaluations` — rúbricas RA por alumno/equipo, registradas por docente | `app/Models/Evaluation.php`, `xxxx_create_evaluations_table.php`, `EvaluationController.php`, `Fase4Evaluacion.vue` |
| GAP-RBAC-002 | Transversal | P1 | Modelo+Migración+UI | Rol DINAMIZADOR (6) — acceso a facilitar plantillas y dar feedback por fase | `app/Models/User.php`, `xxxx_add_dinamizador_role.php`, `router/index.js` |
| GAP-RBAC-003 | Transversal | P1 | Endpoint | Filtrar microproyectos por docente autenticado (ahora devuelve todos) | `app/Http/Controllers/MicroproyectoController.php::index()` |
| GAP-F0-002 | 0 | P1 | Migración+UI | Contrato de equipo — campo en `equipo` + UI en wizard paso 3 | `StartupDayWizard.vue` paso 3, `microproyectos` campo `equipo.contrato_firmado` |
| GAP-F0-003 | 0 | P1 | Migración+UI | "Problema en una frase" como campo explícito en `diseno_reto` | `StartupDayWizard.vue` paso 5, `microproyectos.diseno_reto` (`problema_frase`) |
| GAP-F2-001 | 2 | P1 | Migración+Modelo | Tipo de prototipo en `microproyecto_recursos` (enum: croquis/storyboard/maqueta/digital/diagrama) | `xxxx_add_prototipo_type_to_recursos.php`, `app/Models/MicroproyectoRecurso.php` |
| GAP-F2-002 | 2 | P1 | Migración+Modelo | Campo `iteracion` en `microproyecto_recursos` para historial de versiones | `xxxx_add_iteracion_to_microproyecto_recursos.php` |
| GAP-F3-002 | 3/4 | P1 | Modelo+Migración | Tabla `validation_acts` — actas formales de validación empresa y académica | `app/Models/ValidationAct.php`, `xxxx_create_validation_acts_table.php` |
| GAP-F4-003 | 4 | P1 | UI+Endpoint | Informe final exportable (PDF) — objetivos, fases, resultados, KPIs, aprendizajes | `useMicroproyectoInforme.js`, `MicroproyectoController.php` (endpoint export) |
| GAP-F4-004 | 4 | P1 | Migración+UI | KPIs estructurados: `{descripcion, valor_meta, valor_real, ods_codigo}` (ahora texto libre) | `StartupDayWizard.vue` paso 7, migración de estructura JSON |
| GAP-F1-004 | 1 | P1 | Endpoint | Rutas API Fase 1 en `routes/api.php` | `routes/api.php` grupo `/startup/proyectos/{uuid}/fase1/...` |
| GAP-F0-004 | 0 | P2 | UI | Herramienta de ideación: brainstorm + matriz esfuerzo-impacto → solución seleccionada | `frontend-microretos/src/views/StartupDayIdeacion.vue` |
| GAP-F2-003 | 2 | P2 | UI+Migración | Estructura del pitch Fase 2 (5 secciones) — ahora es texto libre en `resumen` | `StartupDayWizard.vue` o `microproyectos.diseno_microproyecto.pitch_estructura` |
| GAP-F2-004 | 2 | P2 | Modelo+UI | Tabla `feedback_entries` — feedback por fase con origen (empresa/profesorado/dinamizador) | `app/Models/FeedbackEntry.php`, `xxxx_create_feedback_entries_table.php` |
| GAP-F3-003 | 3 | P2 | UI | Checklist de QA interno antes de publicar (paso previo a cambiar estado a `publicado`) | `StartupDayWizard.vue` paso 8 |
| GAP-F4-006 | 4 | P2 | UI | Plantilla de presentación final (5 diapositivas estructuradas) | `frontend-microretos/src/views/Fase4Presentacion.vue` |
| GAP-F0-005 | 0 | P2 | UI | Cuaderno digital de jornada StartupDay (bloques 1–4 en tiempo real) | `frontend-microretos/src/views/StartupDayCuaderno.vue` |

---

## Recomendaciones de implementación

### Orden de desarrollo sugerido

**Sprint 1 — Fundación de datos faltantes (P0 blockers)**
1. Crear `interviews` y `survey_responses` (GAP-F1-001, GAP-F1-002): son independientes y paralelas. Dos migraciones + dos modelos + dos controllers + dos FormRequests.
2. Crear `reflections` (GAP-F4-001): sencillo, tablas pequeñas. Desbloquea el cierre de Fase 4.
3. Ampliar validación académica en `microproyectos` (GAP-F3-001): solo campos nuevos + endpoint PATCH.

**Sprint 2 — Roles y acceso**
4. Rol ALUMNADO (GAP-RBAC-001): migración + constante en User + guards de router + vista inicial alumnado.
5. Filtro docente en `index()` (GAP-RBAC-003): una línea en el controller, alto impacto en seguridad.
6. Rol DINAMIZADOR (GAP-RBAC-002): puede compartir sprint con alumnado.

**Sprint 3 — Evaluación y cierre (P0 restante)**
7. Tabla `evaluations` con rúbricas RA (GAP-F4-002): depende de que roles alumnado existan.
8. `validation_acts` (GAP-F3-002): sustitución del JSON actual por entidad formal.

**Sprint 4 — Plantillas y mejoras P1**
9. Pitch estructurado Fase 2 (GAP-F2-003).
10. Tipos de prototipo + iteraciones (GAP-F2-001, GAP-F2-002).
11. KPIs estructurados con ODS (GAP-F4-004).
12. Informe exportable PDF (GAP-F4-003).

**Sprint 5 — UX y P2**
13. Herramienta de ideación (GAP-F0-004).
14. Cuaderno de jornada (GAP-F0-005).
15. `feedback_entries` transversal (GAP-F2-004).

### Aviso de seguridad en código existente

⚠ **ALERTA DE SEGURIDAD — `localStorage` para tokens de auth**

`frontend-microretos/src/router/index.js` almacena `admin_token`, `admin_token_created_at`, `user_role` y `user_name` en `localStorage`. Según las reglas del proyecto (CLAUDE.md), los tokens de autenticación **deben ir en cookies HttpOnly via Sanctum**, nunca en `localStorage`. Esta vulnerabilidad es preexistente y está fuera del scope de este análisis, pero debe corregirse antes de producción. Referencia: `frontend-microretos/src/router/index.js` líneas 95–100.

---

*Archivo generado automáticamente mediante análisis de código. Para cada gap, crear una issue o tarea antes de implementar.*
