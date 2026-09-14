# DuaLab — Security Fixes Backlog (SAST/DAST)

> Formato optimizado para Claude Code. Cada ítem incluye archivo(s), acción concreta y criterio de done.
> Prioridades: 🔴 CRÍTICA → 🟠 ALTA → 🟡 MEDIA → 🟢 BAJA

---

## 🔴 CRÍTICAS

### C1 — Bypass de roles: empresa puede crear/editar centros, familias y ciclos ✅ RESUELTO
- **Archivo:** `routes/api.php`
- **Fix aplicado:** POST/PUT/DELETE de centros, familias y ciclos envueltos en `Route::middleware('admin')`. Papelera también movida al grupo `admin`. Un token de rol `empresa` o `docente` recibe 403 en esas rutas.
- **Done cuando:** Un token de rol `empresa` recibe 403 al hacer POST/PUT/DELETE sobre esos endpoints. ✓

### C2 — Frontend y backend no comparten la misma matriz de acceso ✅ RESUELTO
- **Archivos:** `routes/api.php`, `app/Http/Middleware/EnsureIsDocente.php`, `bootstrap/app.php`
- **Fix aplicado:** Creado middleware `EnsureIsDocente` (bloquea rol `empresa`, permite docente + admin + superadmin). Registrado como alias `docente`. Aplicado a: generación IA, guardar microretos, borrar microretos, tokens QR, sesiones, uploads, contacto de empresas, StartUp Day (proyectos + sugerencia RA/CE).
- **Matriz resultante:**
  - `empresa` → solo lectura pública (token) + rutas auth sin restricción de rol
  - `docente` → todo lo anterior + generación IA + sesiones + microproyectos + uploads
  - `admin` → todo lo anterior + centros + familias + ciclos + papelera + usuarios
  - `superadmin` → todo + asociar centro a usuario
- **Done cuando:** Cada endpoint de escritura tiene middleware de rol. ✓

### C3 — MicroretoIAController escribe en BD sin validar campos ✅ RESUELTO
- **Archivo:** `app/Http/Controllers/MicroretoIAController.php`
- **Fix aplicado:** Creado `app/Http/Requests/StoreMicroretoRequest.php` con lista blanca explícita de cada campo. `prepareForValidation()` aplica `strip_tags()` antes de validar. `guardarEnBD` usa `StoreMicroretoRequest` y `$request->validated()` en lugar de `$request->except([...])`. `guardarLote` usa validación inline con wildcards `microretos.*.campo` y límite `max:50`. `uuid` y los flags `_ui_*` quedan excluidos por no estar en las reglas.
- **Done cuando:** Un payload con `uuid` ajeno es rechazado — el modelo genera el suyo propio.

### C4 — PapeleraController: TypeError 500 con `$id` string malformado ✅ RESUELTO
- **Archivo:** `routes/api.php` (no `PapeleraController.php`)
- **Nota:** El fix original (`$request->validate(['id' => 'required|integer'])`) es incorrecto. `$id` viene de la URL, no del cuerpo del request, por lo que `$request->validate` nunca lo ve. La solución es en la ruta.
- **Fix aplicado:** `->whereNumber('id')` en las rutas `restaurar` y `destruir` de papelera. Laravel devuelve 404 directamente si `{id}` no es numérico, antes de llegar al controller.
- **Done cuando:** Una petición con `id=aaaaa` devuelve 404, no 500.

### C5 — AdminUserController no valida longitud de email/password (excepción SQL) ✅ YA ESTABA RESUELTO
- **Archivos:** `app/Http/Controllers/AdminUserController.php`
- **Estado:** El controller ya tenía `'email' => 'required|email|max:254'` y `'password' => ['required', 'string', 'max:128', ...]`. No era necesaria ninguna acción.
- **Done cuando:** Un email de 300 chars devuelve 422 sin tocar la BD. ✓

### C6 — DemoController usa `$familia` sin validar (posible abuso/inyección) ✅ RESUELTO
- **Archivos:** `routes/api.php`
- **Nota sobre el fix original:** El regex `'^[a-zA-Z0-9\s\-_%,.áéíóúÁÉÍÓÚñÑ]{1,100}$'` tenía dos problemas: (1) incluía `%` — los parámetros de ruta ya llegan decodificados, `%` no tiene sentido en un nombre de familia y podría usarse para evadir filtros; (2) los anchors `^` y `$` son redundantes porque Laravel ya los aplica internamente en restricciones de ruta.
- **Fix aplicado:** `->where('familia', '[a-zA-ZÀ-ÿ0-9 ,.\-]{1,100}')` en las rutas `/demos/{familia}` y `/demos/{familia}/microretos`. `À-ÿ` cubre todas las letras acentuadas del español sin listarlas una a una.
- **Done cuando:** Un string de 200 chars en `{familia}` devuelve 404.

### C7 — Dependencias JS con CVEs (esbuild, form-data, postcss, vite) ✅ RESUELTO
- **Archivo:** `package.json`, `package-lock.json`
- **Fix aplicado:** `npm audit fix` — actualizados 9 paquetes. `npm audit` = 0 vulnerabilidades.
- **Done cuando:** `npm audit` no reporta CVEs. ✓

### C8 — Dependencias PHP con CVEs (guzzle, symfony, commonmark) ✅ PARCIALMENTE RESUELTO
- **Archivos:** `composer.json`, `composer.lock`
- **Fix aplicado:** `composer update guzzlehttp/guzzle guzzlehttp/psr7 league/commonmark symfony/*` — 24 paquetes actualizados. Resueltos 18 de 21 CVEs.
- **Pendiente (3 CVEs):** `laravel/framework` tiene 2 advisories internos + CVE-2026-48019 (CRLF injection en validación de email, high). El fix requiere Laravel 13 — el constraint actual es `^12.0`. Actualizar a Laravel 13 es una decisión de proyecto (breaking changes posibles), no un `composer update` simple.
- **Done cuando:** `composer audit` limpio. Actualmente: 3 advisories en laravel/framework que requieren v13.

### C9 — Login genera tokens simultáneos sin límite razonable ✅ RESUELTO
- **Archivo:** `app/Http/Controllers/AdminAuthController.php`
- **Nota sobre el fix original:** `$user->tokens()->delete()` antes de crear el token habría cerrado sesión en todos los dispositivos en cada login — demasiado agresivo para uso normal (docente en móvil + tablet + escritorio).
- **Fix aplicado:** Límite reducido de 10 a 3 tokens concurrentes. Al login número 4, se borra el más antiguo automáticamente.
- **Done cuando:** Un cuarto login desde otro dispositivo invalida la sesión más antigua, no todas.

---

## 🟠 ALTAS

### A1 — Sin bloqueo de cuenta por intentos fallidos de login ✅ RESUELTO
- **Archivo:** `app/Http/Controllers/AdminAuthController.php`
- **Fix aplicado:** `RateLimiter` con clave `login.{email}.{ip}` — 10 intentos máximo en ventana de 15 minutos. En cada fallo se incrementa el contador; en login correcto se resetea. Devuelve 429 con minutos restantes.
- **Done cuando:** Tras 10 intentos fallidos la cuenta queda bloqueada 15 minutos. ✓

### A2 — Sin longitud máxima de contraseña en login ✅ RESUELTO (parcial)
- **Archivos:** `app/Http/Controllers/AdminAuthController.php`
- **Fix aplicado:** `'password' => 'required|string|max:128'` en validación de login. Previene payloads enormes que forzarían bcrypt con strings largísimos.
- **Nota:** El `min:16` del fix original rompería a usuarios con contraseñas más cortas ya registradas. La longitud mínima se aplica en registro y cambio de contraseña, no en login.
- **Done cuando:** Un payload con contraseña de 200 chars devuelve 422. ✓

### A3 — Autocompletado de contraseña habilitado en LoginModal ✅ YA ESTABA RESUELTO
- **Archivo:** `frontend-microretos/src/components/LoginModal.vue`
- **Estado:** El input ya tiene `autocomplete="current-password"` (línea 51). Correcto para formularios de login según spec HTML5.
- **Done cuando:** ✓

### A4 — Sesión puede renovarse indefinidamente sin expiración absoluta
- **Archivo:** `frontend-microretos/src/App.vue`
- **Fix:** Implementar un timestamp `session_started_at` en `localStorage`; forzar logout si supera X horas aunque el usuario esté activo.
- **Done cuando:** Tras el tiempo máximo absoluto se fuerza logout independientemente de la actividad.

### A5 — Cambio de rol no se refleja en sesiones activas ✅ RESUELTO
- **Archivos:** `app/Http/Controllers/AdminAuthController.php`, `frontend-microretos/src/stores/auth.js`
- **Fix aplicado:** `refresh()` ahora incluye `role` en la respuesta. El store compara el rol devuelto con el almacenado; si difieren, llama a `logout()` y fuerza reautenticación.
- **Done cuando:** Si un admin cambia el rol de un usuario logueado, su sesión expira en el siguiente refresh. ✓

### A6 — SESSION_SECURE_COOKIE desactivado
- **Archivo:** `.env`
- **Fix:** Añadir `SESSION_SECURE_COOKIE=true` en `.env` de producción.

### A7 — SESSION_ENCRYPT desactivado
- **Archivo:** `.env`
- **Fix:** Añadir `SESSION_ENCRYPT=true` en `.env` de producción.

### A8 — HSTS no configurado
- **Archivo:** `public/.htaccess`
- **Fix:** Añadir:
  ```apache
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
  ```

### A9 — Cabecera X-Powered-By expone versión de PHP
- **Archivo:** Configuración del servidor (`php.ini` / Apache)
- **Fix:** Añadir `expose_php = Off` en `php.ini` o `Header unset X-Powered-By` en Apache.

### A10 — Content-Security-Policy ausente ✅ RESUELTO
- **Archivo:** `app/Http/Middleware/SecurityHeaders.php`
- **Fix aplicado:** `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`. Las respuestas de la API son JSON puro — no cargan ningún recurso externo. `frame-ancestors 'none'` refuerza X-Frame-Options.

### A11 — Cookie XSRF-TOKEN sin flag HttpOnly
- **Archivo:** `config/session.php`
- **Nota:** Para Sanctum token-based (Bearer), el XSRF-TOKEN cookie es leído intencionalmente por JavaScript para incluirlo como cabecera. Marcarlo HttpOnly rompería la protección CSRF. No requiere acción.

### A12 — Cabeceras COOP / CORP ausentes ✅ RESUELTO
- **Archivo:** `app/Http/Middleware/SecurityHeaders.php`
- **Fix aplicado:** `Cross-Origin-Opener-Policy: same-origin` y `Cross-Origin-Resource-Policy: same-site`. Se omite COEP (`require-corp`) porque requeriría que Cloudinary añada cabeceras CORP en sus recursos — cambio en terceros que podría romper la carga de archivos sin pruebas previas.

### A13 — X-Content-Type-Options ausente ✅ YA ESTABA RESUELTO
- **Archivo:** `app/Http/Middleware/SecurityHeaders.php`
- **Estado:** `X-Content-Type-Options: nosniff` ya estaba en el middleware. ✓

### A14 — No se registran intentos de login fallidos ni fallos de control de acceso
- **Archivo:** (crear servicio de log centralizado)
- **Fix:** Implementar logging en: logins fallidos, accesos denegados por rol, tokens inválidos/expirados. Sin guardar contraseñas ni tokens en claro.

### A15 — Sin validación de entrada centralizada
- **Fix:** Crear Form Requests reutilizables con reglas comunes: charset UTF-8, rechazo de bytes nulos, saltos de línea, path traversal, longitudes máximas.

---

## 🟡 MEDIAS

### M1 — UploadController sin valor por defecto en límite de tamaño ✅ RESUELTO
- **Archivo:** `app/Http/Controllers/UploadController.php`
- **Fix aplicado:** `$maxMb = (int) config('services.cloudinary.upload_max_mb', 20)`. El cast `(int)` evita que un valor no numérico en el `.env` genere una regla de validación rota. El default `20` garantiza que si `UPLOAD_MAX_SIZE_MB` no está en el `.env`, el límite sigue siendo 20 MB en lugar de fallar silenciosamente con `max:0`.

### M2 — Hash SHA-1 en UploadController (líneas 69 y 172) — FALSO POSITIVO
- **Archivo:** `app/Http/Controllers/UploadController.php`
- **Análisis:** El SAST detectó `hash('sha1', ...)` pero estas líneas generan firmas HMAC para la API de Cloudinary, que las exige explícitamente en su documentación. No es un hash de contraseñas — es autenticación de mensaje con clave secreta. SHA-1 en contexto HMAC con clave privada es aceptable. No requiere acción.

### M3 — Regex con riesgo de ReDoS en LoginModal ✅ RESUELTO
- **Archivo:** `frontend-microretos/src/components/LoginModal.vue`
- **Fix aplicado:** Sustituida `/\S+@\S+\.\S+/` por `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`. Las clases negadas `[^\s@]` no tienen cuantificadores encadenados que provoquen backtracking superlineal. Mismo comportamiento de validación, sin riesgo de ReDoS ante inputs maliciosos largos.

### M4 — Complejidad cognitiva alta
- **Archivos:** `MicroretoIAController.php`, `DatosFPController.php`, `MicroproyectoController.php`, `StartupDayWizard.vue`
- **Fix:** Dividir métodos largos, extraer lógica a helpers/servicios. Objetivo: complejidad cognitiva < 15 por método (SonarQube).

### M5 — Problemas de accesibilidad en componentes Vue
- **Archivos:** Varios componentes Vue
- **Fix:** Asociar cada `<label>` con su `<input>` vía `for`/`id`, corregir contrastes, ajustar `autocomplete`.

### M6 — Código muerto, imports duplicados y TODOs pendientes
- **Fix:** Eliminar código comentado, limpiar imports duplicados, resolver o eliminar TODOs. Puede hacerse con ESLint + `no-unused-vars`.

### M7 — `window` en lugar de `globalThis` en frontend
- **Fix:** Buscar y reemplazar usos de `window` por `globalThis` donde aplique.

### M8 — Bloques `if` sin llaves en backend
- **Fix:** Añadir llaves `{}` a todos los bloques condicionales. Configurable con PHP-CS-Fixer.

### M9 — Literales duplicados en rutas y seeders
- **Archivos:** `routes/api.php`, seeders
- **Fix:** Extraer constantes o variables para strings repetidos.

### M10 — DatosFPController con demasiadas responsabilidades
- **Archivo:** `app/Http/Controllers/DatosFPController.php`
- **Fix:** Dividir en controladores más pequeños por responsabilidad. Reducir métodos con múltiples `return`.

### M11 — follow-redirects — fuga de cabeceras al redirigir ✅ RESUELTO
- **Archivo:** `package-lock.json`
- **Estado:** Versión instalada `1.16.0` (arrastrada por axios). Resuelto en el `npm audit fix` anterior.

### M12 — postcss — riesgo XSS en CSS dinámico ✅ RESUELTO
- **Archivo:** `package-lock.json`
- **Estado:** Actualizado en el `npm audit fix` anterior. `npm audit` = 0 vulnerabilidades.

### M13 — Stack Symfony por debajo de versión segura ✅ RESUELTO
- **Archivo:** `composer.lock`
- **Estado:** Todos los componentes symfony actualizados de 7.4.4 a 7.4.14 en el `composer update` anterior.

### M14 — nette/schema con vulnerabilidad media ✅ NO APLICA
- **Archivo:** `composer.lock`
- **Estado:** `nette/schema` está en v1.3.5, la última versión de la rama 1.x. No aparece en `composer audit`. El CVE detectado en la auditoría SAST original correspondía a una versión anterior ya superada.

### M15 — Límites de caracteres desalineados entre frontend, backend y BD
- **Archivos:** Varios controladores y vistas
- **Fix:** Inventariar límites reales de cada campo en BD y replicarlos como `max:N` en backend y `maxlength="N"` en frontend.

### M16 — Canonicalización incompleta de nombres de empresa (duplicados posibles)
- **Fix:** Implementar listado centralizado de empresas, normalizar nombre antes de comparar, validar duplicados antes de `INSERT`.

### M17 — Ruta `/startup/proyectos/{uuid}` muestra página en blanco con UUID inválido
- **Fix:** Añadir guard en el controlador/router para devolver 404 controlado cuando el UUID no existe.

### M18 — Lógica de autorización dispersa (sin Gates/Policies)
- **Archivo:** `app/Providers/AppServiceProvider.php` + controladores varios
- **Fix:** Migrar comprobaciones manuales de rol a `Gate::define()` en `AppServiceProvider` y usar `Gate::authorize()` en los controladores.

### M19 — Base de datos usa usuario `root` en producción
- **Archivo:** `.env` (producción)
- **Fix:** Crear usuario MySQL con privilegios mínimos (`SELECT, INSERT, UPDATE, DELETE`) solo sobre la BD del proyecto. Actualizar `.env`.

### M20 — Sin auditoría automática de cuentas inactivas
- **Archivo:** `app/Console/Kernel.php`
- **Fix:** Crear comando `php artisan users:deactivate-inactive` y programarlo diariamente en el Scheduler. Bloquear cuentas sin actividad en 30 días.

### M21 — APP_DEBUG puede estar activo en producción
- **Archivo:** `.env` (producción)
- **Fix:** Confirmar `APP_DEBUG=false`. Incluir en checklist de despliegue.

### M22 — Sin política documentada de gestión de claves criptográficas
- **Fix:** Documentar ciclo de vida de `APP_KEY` y credenciales externas: generación, rotación, revocación, almacenamiento.

### M23 — Contraseña de BD sin política de robustez documentada
- **Archivo:** `.env` (producción)
- **Fix:** Generar contraseña robusta (≥20 chars, aleatoria). Documentar política y rotación periódica.

### M24 — Sin proceso recurrente de actualización de dependencias
- **Fix:** Configurar GitHub Dependabot o equivalente. Establecer revisión mensual de `npm audit` y `composer audit`.

### M25 — Revisar configuración del servidor: listado de dirs, métodos HTTP, WebDAV
- **Archivo:** Configuración del servidor (producción)
- **Fix:** Deshabilitar listado de directorios, restringir a métodos HTTP necesarios (GET, POST, PUT, DELETE), desactivar WebDAV si no se usa.

---

## 🟢 BAJAS / MEJORAS

### B1 — Cambios en panel empresa no persisten al navegar adelante/atrás
- **Archivo:** Panel Generador (wizard)
- **Fix:** Revisar gestión de estado del wizard para que persista entre pasos (Pinia store o `sessionStorage`).

### B2 — Se puede reasignar centro educativo a empresa ya asignada sin aviso
- **Fix:** Bloquear o mostrar aviso de confirmación al intentar reasignar centro ya vinculado.

### B3 — Avisos de validación solo aparecen al final del formulario
- **Archivos:** Panel de proyectos / Panel Generador
- **Fix:** Mover validación a evento `blur`/`change` de cada campo, no solo al submit.

### B4 — Campos autocompletados (centro, empresa, reto) son editables libremente
- **Archivos:** Panel de proyectos (crear proyecto)
- **Fix:** Marcar campos como `readonly` tras autocompletar, o mostrar aviso si se editan.

### B5 — Se puede avanzar sin añadir ningún objetivo al proyecto
- **Archivo:** Panel de proyectos (paso objetivos)
- **Fix:** Bloquear el botón "continuar" hasta que haya al menos un objetivo. Mostrar aviso inmediato.

### B6 — Publicar/archivar proyecto se puede pulsar varias veces
- **Archivo:** Panel de proyectos (paso publicar)
- **Fix:** Deshabilitar el botón tras la primera acción o mostrar spinner + estado.

### B7 — Estado "validado" incorrecto aunque empresa indique que no es viable
- **Archivo:** Panel de proyectos (paso publicar/validación)
- **Fix:** Corregir lógica de estado para reflejar la respuesta real de la empresa.

### B8 — Selector de proyectos a validar no filtra por empresa
- **Archivo:** Panel directorio de empresas (enviar validación)
- **Fix:** Filtrar la lista para mostrar solo proyectos asociados a esa empresa/instituto.

### B9 — Dashboard docente permite guardar sesiones duplicadas sin aviso
- **Archivo:** Dashboard docente (creación de sesión)
- **Fix:** Detectar sesión con datos idénticos antes de guardar y mostrar confirmación.

### B10 — Validación de longitud en dashboard docente solo en backend
- **Archivos:** `app/Http/Controllers/SesionController.php`, `frontend-microretos/src/views/dashboarddocente.vue`
- **Fix:** Añadir `maxlength="255"` en frontend para nombre del instituto.

### B11 — Mensaje confuso en "crear nueva empresa"
- **Archivo:** Panel de base de datos
- **Fix:** Reescribir el texto del mensaje para mayor claridad.

### B12 — Se puede añadir empresa con estado "descartada"
- **Fix:** Definir regla de negocio. Si no debe permitirse, añadir validación explícita.

### B13 — Rol empresa puede ver secciones de docente
- **Fix:** Revisar y restringir en frontend y backend todas las acciones no permitidas al rol empresa.

### B14 — Login de empresa no filtra proyectos por su empresa concreta
- **Fix:** Asociar cuenta empresa a una empresa concreta al autenticar; filtrar todos los listados.

### B15 — Barra lateral muestra vista de administrador antes del login
- **Archivo:** Frontend (layout principal)
- **Fix:** Mostrar sidebar neutro/bienvenida cuando no hay sesión activa.

### B16 — Archivos CSV/Excel quedan en servidor tras importación
- **Archivo:** Rutas de importación (si se reactivan)
- **Fix:** Añadir `File::delete($path)` tras procesar el archivo.

### B17 — Sin escaneo antivirus en archivos subidos
- **Archivo:** `app/Http/Controllers/UploadController.php`
- **Fix:** Evaluar integrar ClamAV (o similar) antes de mover el archivo a almacenamiento definitivo.

### B18 — Contraseñas de docente/empresa no confirmadas en `.env`
- **Archivo:** `.env`
- **Fix:** Confirmar que están en `.env` / GitHub Secrets, no hardcodeadas en el código.

### B19 — Módulo de contacto empresas sin límite de intentos
- **Archivo:** `app/Http/Controllers/EmpresaContactoController.php`
- **Fix:** Añadir rate limiting en `verificarAcceso()`. Revisar autorización en `contactar()` y `enviarValidacion()`.

### B20 — Sin tabla de auditoría inmutable
- **Fix:** Verificar existencia; si no existe, implementar tabla append-only para acciones administrativas sensibles.

### B21 — Datos sensibles / menores sin anonimización
- **Fix:** Aplicar minimización de datos, cifrado donde corresponda y anonimizar identificadores de alumnado menor de edad.

### B22 — Falta documentar flujos de datos y autenticación
- **Archivo:** Documentación del proyecto
- **Fix:** Elaborar: flujo de usuario, autenticación/autorización, flujo de negocio con casos de abuso, DFD, diagrama de estado, trust boundaries y modelo STRIDE.

### B23 — Sin auditoría de cumplimiento RGPD/LOPDGDD
- **Fix:** Realizar checklist AEPD. Valorar EIPD si se tratan datos de menores.

### B24 — Sin autoevaluación ENS
- **Fix:** Realizar autoevaluación ENS según categoría de los datos tratados.

### B25 — Pruebas funcionales, de rendimiento y pentest pendientes
- **Fix:** Planificar tras aplicar fixes críticos y altos: tests funcionales, pruebas de carga y revisión manual OWASP ASVS completa.

---

## Notas

- El análisis DAST (OWASP ZAP) está **aún en curso**. Repetir escaneo tras aplicar los fixes de cabeceras (A8–A13).
- Orden de trabajo recomendado: **C → A → M → B**.
- Cada fix marcado con "Done cuando" tiene criterio verificable para CI o tests manuales.
