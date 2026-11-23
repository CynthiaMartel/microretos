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
- **Dominios, APIs y bases de datos:** [docs/MAPA_DOMINIOS.md](docs/MAPA_DOMINIOS.md) — qué sirve `dualab.es`, `api.dualab.es`, `info.dualab.es` (API de la web, sitio propio en hPanel con su propia BD: no borrarlo), `comercial.`, `central.` y `nexo.dualab.es`, y sus puertos en local. Leerlo antes de tocar nada en hPanel; comprobar con `./scripts/comprobar-dominios.sh`.
- **Apps del repo:** app DuaLab (`frontend-microretos/src`), app comercial (`comercial/`), DuaLab Central (`central/`, solo superadmin) y Nexo (`nexo/`, página de acceso). La web pública y Gestión web están en el repo aparte `FRONTOFFICE/dualab_proyecto`.

## Etapas: Taller de ideas y Plan de proyecto (rediseño en curso, nov-2026)

El recorrido del alumnado se divide en dos etapas. Plan por fases y decisiones en `TAREAS_PENDIENTES.md` (sección P).

1. **Taller de ideas** (en el aula). Reto → **Ajuste del reto** (el docente hace suyo el reto: curso, módulos, RA/CE, metodología y objetivos; la empresa y el diseño se copian del reto; en BD es `microproyectos`, que no cambia de nombre) → **validación del docente** («lo he revisado y lo quiero trabajar»; la empresa ya no valida al inicio) → **encuentro creativo** (fecha, clase, grupos y planificación de sesiones por fases) → workspace F0–F4 → al terminar, **la empresa valora el reto** con las soluciones de los grupos y dice si le interesa seguir con un proyecto.
2. **Plan de proyecto** (en la empresa, durante las prácticas). Parte de un reto ajustado terminado (ampliado), de otro reto o de una necesidad que surja en las prácticas sin reto en la plataforma → validación del docente o de DuaLab → **cronograma del plan** (hitos durante meses) → mismo workspace por fases, con subfases y más contenido, y los mismos RA/CE más «qué espera la empresa de ti».

Términos (desde el 23-nov-2026): **Reto ajustado** = el documento del docente (antes «Propuesta»; el asistente es el «Ajuste del reto», la sección del panel es «Retos»); **Solución** = lo que entrega cada grupo (F3). «Proyecto» queda para el Plan de proyecto. A la empresa y al alumnado se les habla de «reto», nunca de «reto ajustado».

- **Quién pasa a prácticas lo decide DuaLab**, como empresa intermediaria, eligiendo al alumnado más destacado; no lo decide la empresa. En la plataforma solo hay alias: DuaLab pregunta al docente quién es cada alias (p. ej. «Lucía Nutria Azul») y envía los datos reales a la empresa **fuera de la plataforma**.
- La empresa entra con un enlace con token, sin cuenta.

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
| `comercial` (5) | Solo la app comercial (`frontend-microretos/comercial/`, subdominio comercial.dualab.es): capta empresas reales, hace la entrevista diagnóstica, genera retos en borrador y recoge la firma. Sin centro ni acceso a la app de docentes |

Middleware disponibles: `auth:sanctum`, `EnsureIsAdmin`, `EnsureIsSuperAdmin`, `EnsureIsDocente` (lista blanca: docente, admin, superadmin), `EnsureIsComercial` (comercial y superadmin).

**Captaciones:** lo que llega de fuera (entrevista del comercial = origen 1, formulario web del frontoffice = origen 2, tabla `captacion_origenes`) se guarda en `empresa_captaciones` / `centro_captaciones`, nunca directamente en `empresas` / `centro_educativo`. El superadmin las valida desde Notificaciones → «Captación comercial»: al validar una empresa se crea en `empresas` (sin `centro_id`) y se coloca en uno o varios centros (`centro_empresa`); o se descarta. Firma y retos en borrador (`microreto_borradores`) cuelgan de la captación; los retos se publican tras validar (un `Microreto` por centro). Al validar se puede fusionar con una empresa o un centro que ya existan (no se duplican). Los centros se validan dando de alta (o enlazando) un `centro_educativo`. Los formularios web llegan por `POST /api/integraciones/frontoffice/formularios`, firmados con HMAC (`FRONTOFFICE_WEBHOOK_SECRETO`, el mismo que en Gestión web → Integraciones del frontoffice); los de administraciones y el rápido se ignoran. Textos legales en `config/captacion.php` (de ejemplo hasta `CAPTACION_TEXTOS_REVISADOS=true`).
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

## Responsive — norma obligatoria en el frontend

Toda vista o componente `.vue` nuevo o modificado debe funcionar en **360px (móvil), 768px (tablet) y 1280px (escritorio)**, sin scroll horizontal y sin contenido cortado. Una tarea de frontend no está terminada si solo se ha pensado en escritorio.

- **Mobile-first:** la clase sin prefijo es la de móvil; los prefijos (`sm:` 640 · `md:` 768 · `lg:` 1024 · `xl:` 1280) abren el layout. Ejemplo: `grid-cols-1 md:grid-cols-2 lg:grid-cols-3`, nunca `grid-cols-3` sin prefijo.
- **Sin anchos fijos que desborden:** nada de `w-[600px]` sin prefijo. En modales y paneles flotantes, `w-[min(40rem,calc(100vw-2rem))]` o `max-w-*` + `w-full`.
- **Textos variables** (títulos, empresas, emails): `min-w-0` + `truncate` o `break-words`; nunca `whitespace-nowrap` sin control de overflow.
- **Tablas:** dentro de `overflow-x-auto`, o con vista alternativa en tarjetas para móvil.
- **Filas `flex`** con varios elementos: `flex-wrap` o `flex-col sm:flex-row`.
- **Táctil:** nada que dependa solo de hover (acciones con `group-hover`, tooltips sin alternativa); objetivos táctiles de unos 36px como mínimo.
- **Tipografía y espaciado** grandes, con versión reducida en móvil (`text-2xl sm:text-4xl`, `p-4 sm:p-8`).
- **Elementos `fixed`** (toasts, barras): limitados en alto (`max-h-[70vh]` + scroll) y sin tapar contenido en móvil.

Antes de dar por terminada una tarea que toque layout, revisar el código contra estas reglas y proponer `/general:responsive-audit [Vista]`. Para verificarlo en el navegador hace falta una sesión: pedir credenciales de un usuario existente; nunca crear usuarios ni cambiar contraseñas (ver regla de base de datos).

## Diseño — formas y botones (norma obligatoria en el frontend)

Toda vista o componente `.vue` nuevo o modificado sigue estas formas. La revisa `/general:responsive-audit` junto con el responsive.

- **Botones → píldora** (`rounded-full`): acciones, botones de modal, pestañas, chips y etiquetas de estado. Nunca un botón rectangular con esquinas curvas (`rounded-lg`/`xl`/`2xl`).
- **Botones solo icono** (cerrar ✕, menú, bombilla…) → círculo (`rounded-full` con ancho = alto).
- **Tarjetas, paneles, modales, contenedores de grid y campos** (`input`, `select`, `textarea`) → rectángulo con esquinas curvas (`rounded-xl`/`2xl`, modales `rounded-[2rem]`). Nunca con los laterales redondeados (`rounded-full`), ni siquiera los buscadores.
- **Pestañas / controles segmentados:** cada pestaña en píldora y su carril (la franja que solo envuelve las pestañas) también en píldora.
- **Excepciones** (no son botones de acción, son filas o tarjetas; mantienen su rectángulo curvo o su forma de lista):
  - entradas de navegación del SidePanel (`.nav-item`);
  - botones que son tarjetas o casillas de selección de varias líneas (icono + título + descripción, cards de hub). Un selector de una sola línea (p. ej. "Empresa real / ficticia") sí es botón: píldora;
  - opciones de menús desplegables y filas de lista a todo el ancho.
- Lo redondo decorativo no cambia: avatares, puntos de estado, barras de progreso, spinners.
- **Botones de paso** (asistentes y formularios por fases): clases comunes de `style.css`.
  - Fila `.fila-pasos`: **Volver/Anterior a la izquierda** (`.btn-paso-atras`) y **Siguiente/Continuar a la derecha** (`.btn-paso`), en la misma fila también en móvil.
  - Mismo alto, relleno y tipografía en todos los pasos y vistas; **ancho proporcional al texto** (nunca `w-full`, `flex-1` ni `flex-[2]`).
  - Misma posición en cada paso: al final del contenido del paso. Si el texto no cabe en 360px, versión corta en móvil (`Siguiente` en vez de `Siguiente: Contacto`), no apilar.
- Las acciones de cabecera usan `.accion-principal`, `.accion-secundaria` y `.accion-icono` (ya en píldora/círculo).

---

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
| Se crean o modifican vistas/componentes `.vue` que afectan al layout | `/general:responsive-audit [Vista]` |
| La tarea termina con cambios sin commitear | `/general:commit` |
