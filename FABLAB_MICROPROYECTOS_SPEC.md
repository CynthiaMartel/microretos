# FAB LAB — Especificación de Microproyectos para Análisis Comparativo

> **Instrucción para Claude Code:** Este documento describe la metodología de microproyectos del proyecto FAB LAB (formación profesional + empresas). Tu tarea es **comparar esta especificación con la implementación actual del proyecto en VS Code** e identificar:
> 1. Qué fases/objetivos están implementados, parcialmente implementados o ausentes.
> 2. Qué entregables de cada fase tienen soporte en el modelo de datos / UI / endpoints.
> 3. Qué roles del sistema (alumnado, profesorado, dinamizador, empresa) están cubiertos por el RBAC actual.
> 4. Qué guías/plantillas (entrevistas, formularios, prototipado, presentación, reflexión) están digitalizadas vs. faltan.
> 5. Brechas concretas y archivos del repositorio donde deberían implementarse (Laravel/Vue/Inertia).
>
> Devuelve el análisis como un **backlog priorizado en Markdown** con IDs estructurados (ej. `GAP-F1-001`), rutas de archivo sugeridas, y referencias al modelo de datos.

---

## 1. Contexto general

- **Nombre del proyecto base:** FAB LAB — "Conectando alumnado y empresas"
- **Marco:** Proyecto de Innovación e Investigación Aplicadas y Transferencia del Conocimiento en FP.
- **Eje técnico:** Inteligencia artificial en la gestión eficiente de proyectos de innovación en red con sistemas multiplataforma.
- **Metodología:** microproyectos = retos reales propuestos por empresas, ejecutados por equipos de alumnado de FP en 5 fases secuenciales.
- **Idioma de la plataforma:** español.

## 2. Actores del sistema (roles)

| Rol | Responsabilidad nuclear |
|---|---|
| **Alumnado (equipo)** | Ejecuta el microproyecto, genera entregables y evidencias. |
| **Profesorado** | Supervisa, valida cumplimiento de Resultados de Aprendizaje (RA), evalúa con rúbricas. |
| **Dinamizador/a** | Facilita metodología, plantillas, dinámicas y tiempos. |
| **Empresa / tutor de empresa** | Aporta el reto, responde dudas, valida operativamente el entregable. |

> Verifica que el RBAC del proyecto contemple estos 4 roles (alumnado puede agruparse en *equipos* con sub-roles internos: portavoz, responsable de tiempos, documentación, foco).

## 3. Estructura general de un microproyecto

Un microproyecto se ejecuta en **5 fases secuenciales** (Fase 0 → Fase 4). Cada fase tiene: objetivo, roles, secuencia de trabajo, recursos y entregables propios. Los entregables de una fase son input de la siguiente.

```
Fase 0: Startup Day  →  Fase 1: Análisis  →  Fase 2: Diseño/Prototipado  →  Fase 3: Desarrollo/Entrega  →  Fase 4: Cierre
```

---

## 4. Fase 0 — Startup Day

### Objetivo
Jornada intensiva de arranque: el alumnado comprende el reto desde la perspectiva del usuario, conforma equipos, ideación y produce un **primer prototipo defendible** + pitch. No busca producto final, sino activar el equipo y generar resultado comunicable.

### Estructura (4 bloques secuenciales)
1. **Organización del equipo** → equipos equilibrados + asignación de roles internos (portavoz, tiempos, documentación, foco) + contrato de equipo.
2. **Comprender el problema** → contextualización del reto, roleplay, identificación de fricciones; salida: **problema en una sola frase**.
3. **Ideación** → lluvia de ideas + matriz esfuerzo-impacto → **solución seleccionada y justificada**.
4. **Desarrollo y prototipado** → esbozo + prototipo comunicable.
5. **Presentación y feedback** → pitch + feedback accionable.

### Entregables Fase 0
- Definición del problema (1 frase) + usuario/contexto afectado.
- Ideas exploradas + criterio de selección.
- Prototipo inicial comunicable.
- Pitch estructurado + feedback registrado.

### Recursos requeridos en plataforma
- Cuaderno StartupDay (digital).
- Fichas de microproyectos.
- Plataforma FabLab (la app objetivo).

---

## 5. Fase 1 — Análisis

### Objetivo
Comprender en profundidad el reto: contexto, necesidades de empresa, condiciones del entorno. Producir un **informe inicial** que servirá de base para las siguientes fases.

### Secuencia de trabajo
1. **Recogida de datos** — entrevistas breves (5–10 min) a 2–3 personas clave + formularios digitales (Google Forms / Typeform / MS Forms) al personal de empresa.
2. **Síntesis** — sistematización en la plataforma.
3. **Discusión y validación** — exposición interna + feedback profesorado/dinamizador + ajustes.

### Plantilla de Entrevista (estructura digitalizable)
- **A. Datos del entrevistado:** nombre (opcional), cargo/rol, antigüedad.
- **B. Preguntas clave:**
  1. Necesidad/problema principal.
  2. Importancia del reto en la actividad diaria.
  3. Beneficiarios directos.
  4. Cambios positivos esperados.
  5. Limitaciones actuales.
  6. Recursos ya existentes.
  7. Expectativas de resultado.
  8. Características imprescindibles.
  9. Consejos/recomendaciones.
  10. Aspectos a evitar.
- **C. Cierre:** disponibilidad para dudas posteriores (sí/no) + observaciones del equipo.

### Plantilla de Formulario (estructura digitalizable)
- **A.** Datos generales (nombre opcional, área, antigüedad).
- **B. Percepción del problema:** problema principal, afectación al trabajo diario, intentos previos.
- **C. Recursos y limitaciones.**
- **D. Expectativas del microproyecto:** resultado útil + aspectos imprescindibles.
- **E. Impacto y mejoras:** beneficiarios + cambios positivos.
- **F. Opiniones abiertas.**

### Entregables Fase 1
- Síntesis de entrevistas.
- Respuestas del formulario.
- Informe inicial con: visión clara del estado inicial + necesidades + limitaciones + oportunidades + alcance realista.

---

## 6. Fase 2 — Diseño y Prototipado

### Objetivo
Transformar los hallazgos del análisis en una **propuesta tangible**: estructura de la solución + prototipo inicial validable. No es producto final, es borrador funcional.

### Secuencia de trabajo
1. Revisión de hallazgos del informe de Fase 1 → requisitos clave.
2. Definición de la solución (propuesta + alcance: qué incluye y qué no).
3. Creación del prototipo inicial (croquis, maqueta, boceto digital Canva/Figma/Genially, diagrama de procesos Miro/Draw.io).
4. Presentación + feedback → ajustes registrados para Fase 3.

### Principios del prototipado rápido
Simplicidad, rapidez (≤1h por iteración), iteración basada en feedback, accesibilidad de herramientas.

### Tipos de prototipo soportados
- Croquis/boceto papel
- Storyboard / mapa visual
- Maqueta física
- Prototipo digital sencillo
- Diagrama de procesos

### Estructura del pitch (máx. 5 secciones)
1. Título y reto (1 frase).
2. Hallazgos clave de Fase 1 (2–3 datos justificativos).
3. Propuesta de solución (idea + diferencial).
4. Prototipo inicial (visualización).
5. Próximos pasos y dudas.

### Entregables Fase 2
- Prototipo inicial (físico/digital/mixto).
- Presentación breve (máx. 5 diapositivas).
- Apoyo audiovisual opcional.
- Convergencia validada entre empresa + alumnado + profesorado sobre el rumbo.

---

## 7. Fase 3 — Desarrollo y Entrega

### Objetivo
Ejecutar las tareas/microtareas definidas en la **ficha del microproyecto** para transformar el prototipo de Fase 2 en un **entregable funcional, completo y validado** por la empresa.

### Secuencia de trabajo (cada paso alineado con la ficha)
1. **Digitalización avanzada del prototipo** — del croquis validado a plano/producto digital detallado.
2. **Integración de elementos específicos** — itinerarios accesibles, procesos, datos, funcionalidades según ficha.
3. **Incorporación de información complementaria** — capas adicionales: contexto, microclimas, diagramas, flujos, señalización.
4. **Diseño final y visualización** — símbolos, leyendas, capas interactivas, maquetación, coherencia visual y usabilidad.
5. **Revisión colaborativa (QA interno)** — ajustes según feedback antes de entregar.
6. **Entrega de la versión final** — archivos editables + versión final (PDF/enlace) en repositorio/plataforma según estructura definida por el Hub.

### Entregables Fase 3
- Versión final del entregable principal.
- Evidencias de ejecución (archivos editables + final + ubicación en repositorio).
- Validación académica (profesorado) + operativa (empresa).

---

## 8. Fase 4 — Cierre

### Objetivo
Finalizar el microproyecto: presentación clara, validación por empresa y profesorado, reflexión del alumnado, documentación final, métricas de impacto.

### Secuencia de trabajo
1. **Preparación de la presentación final** (5 diapositivas):
   - D1: Reto inicial.
   - D2: Proceso de trabajo (resumen de fases).
   - D3: Solución propuesta y entregable final.
   - D4: Impacto y beneficios esperados (incl. ODS si aplica).
   - D5: Aprendizajes y próximos pasos.
2. **Entrega de documentación final** — paquete compilado: entregable principal + informe del microproyecto (objetivos, fases, tareas, resultados, KPIs) + archivos editables + actas de validación y feedback.
3. **Presentación y validación con la empresa** — sesión pública; empresa valida; profesorado recoge feedback.
4. **Reflexión individual + grupal:**
   - 5 preguntas individuales: qué aprendí nuevo / parte más desafiante / habilidad más practicada / qué mejoraría de mi desempeño / aplicabilidad futura.
   - Reflexión grupal: qué funcionó / qué mejorar / aplicabilidad.
5. **Evaluación y cierre académico** — profesorado evalúa con rúbricas de RA + dinamizador facilita retro final + informe de cierre.

### Entregables Fase 4
- Presentación final (diapositivas/recurso visual).
- Informe del microproyecto (objetivos, fases, resultados, KPIs, aprendizajes).
- Reflexiones individuales y grupales documentadas.
- Acta de validación de empresa.
- Evaluación académica registrada.

---

## 9. Modelo de datos mínimo esperado (para comparar con el esquema MySQL actual)

A nivel orientativo, una implementación coherente debería contemplar entidades como:

- `microprojects` (reto, empresa, ficha, fase actual, estado)
- `teams` (microproyecto, miembros, roles internos, contrato de equipo)
- `team_members` (alumno, rol interno: portavoz / tiempos / documentación / foco)
- `phases` (0–4, con objetivo, estado, fechas) o estados enumerados en `microprojects`
- `deliverables` (fase, tipo, archivo, versión, validado_por, fecha_validacion)
- `interviews` (Fase 1: entrevistado, respuestas estructuradas, observaciones)
- `survey_responses` (Fase 1: formulario digital)
- `prototypes` (Fase 0/2: tipo, archivo, iteración)
- `pitches` / `presentations` (Fase 0/2/4: estructura, feedback recibido)
- `feedback_entries` (origen: empresa/profesorado/dinamizador; fase; accionable)
- `reflections` (Fase 4: individual + grupal, preguntas estandarizadas)
- `evaluations` (rúbricas RA, profesorado, evidencias)
- `kpis` / `metrics` (Fase 4: impacto, ODS si aplica)
- `validation_acts` (Fase 3/4: acta empresa, acta profesorado)

> Si tu modelo actual usa otros nombres, mantén la equivalencia conceptual.

## 10. Resultados de Aprendizaje (RA) y rúbricas

El profesorado evalúa con rúbricas vinculadas a Resultados de Aprendizaje. La plataforma debería permitir:
- Definir rúbricas por microproyecto.
- Asociar criterios a entregables/fases.
- Registrar evaluaciones por alumno y por equipo.

---

## 11. Instrucciones específicas para Claude Code

Cuando ejecutes la comparación, devuelve un informe estructurado así:

```markdown
# Análisis comparativo FAB LAB ↔ Proyecto actual

## Resumen ejecutivo
- Cobertura global estimada: X%
- Fases cubiertas: [...]
- Brechas críticas (P0): [...]

## Cobertura por fase
### Fase 0 — Startup Day
- Estado: [✅ Completa / 🟡 Parcial / ❌ Ausente]
- Entregables soportados: [...]
- Entidades del modelo asociadas: [...]
- Gaps detectados:
  - **GAP-F0-001** | Prioridad: P[0-2] | Descripción | Archivo sugerido: `app/Models/...` / `resources/js/Pages/...`

### Fase 1 — Análisis
...(misma estructura)

### Fase 2, 3, 4
...

## Cobertura por rol (RBAC)
- Alumnado: ...
- Profesorado: ...
- Dinamizador: ...
- Empresa: ...

## Cobertura de plantillas/guías
- Plantilla de entrevista: [estado] | tabla `interviews` | formulario en `Pages/Phase1/Interview.vue`
- Plantilla de formulario: ...
- Guía de prototipado: ...
- Guía de presentación: ...
- Plantilla de reflexión individual: ...

## Brechas priorizadas (backlog accionable)
| ID | Fase | Prioridad | Tipo (modelo/endpoint/UI/migración) | Descripción | Archivo sugerido |
|----|------|-----------|-------------------------------------|-------------|------------------|
| GAP-F1-001 | 1 | P0 | Modelo+UI | Falta formulario digital de entrevista | `app/Models/Interview.php`, `database/migrations/...`, `resources/js/Pages/Microprojects/Phase1/InterviewForm.vue` |
| ... | | | | | |

## Recomendaciones de implementación
1. ...
2. ...
```

**Reglas para el análisis:**
- Lee primero `app/Models/`, `database/migrations/`, `routes/web.php`, `routes/api.php`, y `resources/js/Pages/` para inferir el estado actual.
- Si existe documentación previa (README, docs/), úsala como referencia secundaria.
- Marca como **P0** todo lo que bloquee el flujo de fase a fase (sin esto, el microproyecto no progresa).
- Marca como **P1** los entregables/plantillas obligatorios pero no bloqueantes.
- Marca como **P2** mejoras de UX, KPIs avanzados, integraciones opcionales.
- No inventes rutas: si no encuentras un archivo, propón una ruta coherente con la convención Laravel/Inertia ya presente en el repo.
- Resultado final: archivo Markdown único, listo para usar como backlog en VS Code.
