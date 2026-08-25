# microretos — Contexto del proyecto

Complementa las instrucciones globales de `~/.claude/CLAUDE.md`. Las reglas de Laravel, Vue, seguridad y escalabilidad están allí; aquí solo lo específico de este proyecto.

---

## Stack e infraestructura

- **Backend:** Laravel 11, PHP, MySQL
- **Frontend:** Vue 3 SPA, Vite — directorio `frontend-microretos/`
- **Auth:** Sanctum SPA (cookies HttpOnly, nunca tokens en localStorage)
- **Hosting:** Hostinger compartido — sin Redis, sin workers persistentes
  - `CACHE_STORE=file` (no database, no redis)
  - `QUEUE_CONNECTION=database` + scheduler de Laravel para colas
  - Ruta producción: ver `.env` de producción (no versionada) — no incluir la ruta real ni el ID de cuenta de hosting aquí, este archivo es público en GitHub

## Ramas Git

- `main` → producción (deploy a Hostinger)
- `develop` → integración, rama principal de trabajo diario
- Features en ramas propias → PR a `develop` → merge a `main` para deploy

## Roles de usuario

| Rol | Acceso |
|-----|--------|
| `superadmin` | Todo |
| `admin` | Gestión de usuarios, centros, familias, ciclos |
| `docente` | Crea sesiones, gestiona microretos y microproyectos |
| `empresa` | Valida microproyectos — solo lectura en general |

Middleware disponibles: `auth:sanctum`, `EnsureIsAdmin`, `EnsureIsSuperAdmin`.
Los roles `docente` y `empresa` no tienen middleware propio todavía — pendiente en SECURITY_FIXES.md.

## Base de datos — nunca modificar sin permiso explícito

**Regla absoluta, sin excepciones:** bajo ningún concepto se modifica ningún dato en ninguna base de datos de este proyecto — ni siquiera en local/Docker, ni siquiera en registros de prueba — sin preguntar antes y recibir confirmación explícita de Cynthia.

Esto incluye, entre otros:
- Cambiar contraseñas de usuarios (aunque sean cuentas de prueba) para poder loguearse y verificar algo visualmente.
- Crear, editar o borrar filas en cualquier tabla para probar una feature.
- Ejecutar `php artisan tinker` con cualquier operación que escriba en BD (`->save()`, `->update()`, `->delete()`, `Model::create()`, etc.).
- Ejecutar seeders, factories o comandos artisan que muten datos.

Si para verificar una tarea (visual o funcionalmente) hace falta tocar la base de datos, la única acción permitida es **preguntar primero**, explicando qué se necesita cambiar y por qué — por ejemplo: *"para probar esto en el navegador necesito una sesión de [rol] — ¿me das credenciales de un usuario existente, o prefieres comprobarlo tú visualmente mientras yo me limito a revisar el código?"*. Nunca actuar primero y avisar después.

Esta regla aplica incluso cuando el cambio parece trivial, reversible o de bajo riesgo (entorno local, cuenta de prueba, dato ya obsoleto) — el criterio no es "cuánto importa el dato", es que ninguna mutación de BD ocurre sin supervisión y confirmación explícita previa.

## Estructura de directorios relevante

```
microretos/                         ← raíz del proyecto (backend)
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/             ← EnsureIsAdmin, EnsureIsSuperAdmin, SecurityHeaders
│   │   └── Requests/              ← FormRequests existentes
│   ├── Models/
│   └── Services/                  ← lógica de negocio extraída de controllers
├── routes/
│   └── api.php                    ← todos los endpoints
├── database/
│   ├── migrations/
│   └── seeders/
├── frontend-microretos/            ← Vue 3 SPA
│   └── src/
│       ├── components/
│       ├── views/
│       ├── stores/                 ← Pinia
│       ├── router/
│       └── services/              ← llamadas axios por recurso
├── SECURITY_FIXES.md              ← backlog de seguridad priorizado
├── CLAUDE.md                      ← este archivo
└── .claude/
    └── commands/
        ├── specific_dualab/       ← comandos exclusivos de este proyecto
        └── general/               ← comandos portables a otros proyectos
```

## Backlog de seguridad — SECURITY_FIXES.md

Existe `SECURITY_FIXES.md` en la raíz con todos los issues de seguridad detectados en auditoría SAST/DAST, clasificados por prioridad:

- `C1–C9` → **Críticos** — resolver primero, son explotables
- `A1–A15` → **Altos** — planificar en < 1 semana
- `M1–M25` → **Medios** — siguiente sprint
- `B1–B25` → **Bajos** — backlog

Cada ítem incluye: archivos afectados, fix concreto y criterio "Done cuando" verificable.

---

## Agentes contextuales — específicos de este proyecto

Los agentes generales están definidos en `~/.claude/CLAUDE.md`. Además de esos, en este proyecto propón los siguientes cuando detectes la situación. Siempre en una línea, siempre pidiendo permiso.

| Situación | Agente |
|-----------|--------|
| El usuario menciona deploy, push a `main` o Hostinger | `/specific_dualab:deploy-check` (proponer **antes** de proceder) |
| El usuario inicia una sesión hablando de seguridad o menciona SECURITY_FIXES.md sin ítem concreto | `/specific_dualab:security-audit` |
| El usuario quiere resolver un ítem concreto de SECURITY_FIXES.md (C1, A3, M7…) | `/specific_dualab:fix [código]` |
| Se añaden o modifican rutas en `routes/api.php` | `/general:route-audit` |
| Se modifican componentes `.vue` o archivos en `frontend-microretos/src/` | `/general:vue-audit` |
| La tarea termina con cambios sin commitear | `/general:commit` |
