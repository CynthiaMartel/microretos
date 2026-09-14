# Informe — Calidad de datos en la biblioteca de Microretos

Fecha: 2026-07-16
Alcance: calidad de datos de la tabla `microretos` — columna `curso` y contenido de `evaluacion_oficial` (RA/CE).

Informe hermano: [INFORME_PROYECTOS_ENCUENTROS.md](INFORME_PROYECTOS_ENCUENTROS.md) — correspondencia microproyecto↔encuentro.

---

## 1. `microretos.curso` — backfill de "transversal" (hecho)

Hallazgo: **877 de 880 microretos (99.7%) tenían `curso = NULL`**. Investigando si era derivable mecánicamente desde `modulo`/`ciclo_id`:

- `modulos` sí tiene columna `curso`, pero **868/880 (98.6%) tienen `modulo = "Transversal"`** — no hay fila real de `modulos` contra la que hacer join.
- Solo 12 microretos tienen un módulo específico con nombre real; de esos, 3 ya tenían `curso='2'` explícito y 9 siguen sin curso (candidatos a revisión manual, no tocados).

**Interpretación aceptada**: "Transversal" significa que el reto vale tanto para 1º como para 2º de su ciclo (módulo presente en ambos cursos, o reto generado por IA para encajar con varios módulos a la vez).

**Cambios aplicados**:
- Migración: `microretos.curso` pasó de `tinyint` a `varchar(20)` (no podía guardar texto).
- Backfill: 868 filas (`modulo='Transversal'` y `curso` nulo) → `curso = 'transversal'`.
- Frontend — filtro y badge "Transversal: 1º y/o 2º" añadidos en: `StartupDayWizard.vue` (selector de reto del wizard), `BibliotecaMicroretos.vue` (biblioteca), `DetalleMicroreto.vue`, `PublicMicroreto.vue`. Los filtros por "1º"/"2º" ahora incluyen también los transversales (antes los ocultaban silenciosamente).

**Pendiente**: los 9 microretos con módulo específico y `curso` nulo — revisión manual, no forman parte del backfill masivo (podrían tener un curso real derivable de su módulo concreto, y no queremos etiquetarlos como transversal por error).

## 2. `evaluacion_oficial` (RA/CE) — comando de reparación ya existe, no se ha ejecutado

Al analizar el pendiente de RA/CE encontré que **ya existe un comando Artisan dedicado a esto**: `app/Console/Commands/RepararRaCeMicroretos.php` (`microretos:reparar-ra-ce`). Repara `evaluacion_oficial` de microretos generados antes del fix de la IA (los que tienen RA/CE en texto libre sin `ra_id`/`ce_ids` reales), en 3 niveles de coste creciente:

1. **Match por similitud de texto** contra el catálogo real del módulo (gratis, `similar_text` con umbral configurable, por defecto 70%).
2. **Selección por IA closed-book** si no hay match de texto suficiente — mismo enfoque que `RaCeCatalogoService`/`sugerirRaCe`: la IA solo elige `ra_id`/`ce_ids` de un currículo cerrado, nunca redacta texto.
3. **Manual** si el módulo ni siquiera existe en el catálogo (aún no importado del BOE) — se deja tal cual y se reporta.

Incluye una lista blanca explícita de "ciclos hermanos" (ej. Paisajismo→Jardinería, Carpintería Básico→Carpintería/Diseño) para los casos en que un microreto de un ciclo referencia el currículo de una familia profesional muy cercana — nunca busca de forma global entre ciclos, para no enlazar currículo equivocado. Es idempotente (si una entrada ya tiene `ra_id`, la salta) y por defecto es **dry-run** (no persiste nada sin `--commit`).

**Estado actual (verificado hoy)**: el comando existe pero **no se ha ejecutado con `--commit`** — de 878 entradas de `evaluacion_oficial` en 872 microretos candidatos (no simulados, sin demo), **299 entradas (en 299 microretos) siguen sin `ra_id` real**.

**Uso recomendado**:
```bash
# 1. Dry-run primero, para ver el reparto texto/IA/sin-resolver sin tocar nada
php artisan microretos:reparar-ra-ce

# 2. Si el reparto parece razonable, aplicar de verdad
php artisan microretos:reparar-ra-ce --commit

# Opcional: limitar el lote o ajustar el umbral de similitud de texto
php artisan microretos:reparar-ra-ce --commit --limit=100 --umbral=75
```

## 3. Pendiente

- **Ejecutar `microretos:reparar-ra-ce --commit`** (ver punto 2) — 299 entradas pendientes.
- **9 microretos** con módulo específico y `curso` nulo (ver punto 1) — revisión manual.
- Tras ejecutar el comando, revisar la tabla de "sin resolver" que imprime (motivo: módulo no encontrado en catálogo, o sin match de texto ni de IA) — esos quedan para revisión manual caso a caso.

---

## Recomendaciones para producción

1. **Ejecutar el comando de RA/CE en dry-run primero** y revisar el reparto texto/IA/sin-resolver antes de decidir el umbral (`--umbral`) definitivo — un umbral muy bajo puede enlazar RA/CE incorrectos con falsa confianza.
2. **Backup antes de `--commit`** — el comando modifica `evaluacion_oficial` en potencialmente cientos de filas; hacer snapshot de BD antes de aplicar.
3. Las llamadas de Nivel 2 usan la API de OpenAI con reintentos y backoff — ejecutar el `--commit` fuera de horas punta o con `--limit` en varias tandas si el volumen de llamadas IA es alto, para no competir con el resto de endpoints `/startup/sugerir-*` en producción.
4. **Revisar los 9 microretos sin curso pendientes** (punto 1) antes de que un docente los encuentre filtrando por curso y no aparezcan en ningún filtro (ni 1º/2º ni Transversal).
5. **Smoke test de `/api/demos` y `/api/demos/{familia}/microretos`** tras el cambio de tipo de `curso` — son rutas públicas sin autenticación, conviene confirmar que siguen sirviendo datos correctamente antes de anunciar el cambio.

Ver también las recomendaciones de producción del informe hermano (limpieza de código zombi, rename Sesion→Encuentro) antes de desplegar ambos cambios juntos.
