# Tareas pendientes — microretos

> Backlog de tareas funcionales (las de seguridad van en `SECURITY_FIXES.md`).
> Al terminar una, moverla a `TAREAS_YA_REALIZADAS_PARA_LOVABLE.md`.

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
