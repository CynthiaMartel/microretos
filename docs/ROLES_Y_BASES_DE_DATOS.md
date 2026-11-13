# Roles y bases de datos de cada herramienta

Quién puede entrar en cada herramienta de DuaLab, con qué cuenta y en qué base de datos vive
esa cuenta. Complementa a [MAPA_DOMINIOS.md](MAPA_DOMINIOS.md), que explica qué sirve cada dirección.

> Este archivo es público (como `CLAUDE.md`): sin contraseñas, correos de personas, IP ni rutas
> absolutas del servidor.

---

## Lo primero: hay dos bases de datos de usuarios

| | **App DuaLab** | **Web pública** |
|---|---|---|
| API | `api.dualab.es` | `info.dualab.es` |
| Base de datos | `…_microretos` | `…_front_office` |
| Carpeta en el servidor | `public_html/api-backend/` | `public_html/info/api-backend/` |
| Herramientas que la usan | App DuaLab, App comercial, DuaLab Central | Gestión web |

Una cuenta de una base de datos **no sirve** en la otra. Las dos tienen un rol llamado `admin`,
pero no tienen nada que ver: en la app es el admin de un centro educativo; en Gestión web es
quien administra la web.

## Cada herramienta

| Herramienta | Dirección | Base de datos | Quién entra |
|---|---|---|---|
| **App DuaLab** | `dualab.es/login` | `…_microretos` | superadmin, admin de centro, docente, empresa. El alumnado entra sin cuenta, con código (ver abajo) |
| **App comercial** | `comercial.dualab.es` | `…_microretos` | comercial y superadmin |
| **DuaLab Central** | `central.dualab.es` | `…_microretos` | solo superadmin |
| **Gestión web** | `dualab.es/gestion-web` | `…_front_office` | administración, edición, atención de contactos |
| **Nexo** | `nexo.dualab.es` | ninguna | todo el mundo: solo tiene botones a las demás, sin sesión |
| **Web pública** | `dualab.es/` | `…_front_office` | todo el mundo, sin sesión |

## Roles de la app DuaLab (`…_microretos`)

| Rol (nº en BD) | Qué hace | Herramientas | Quién crea la cuenta |
|---|---|---|---|
| `superadmin` (1) | Todo. Valida captaciones, gestiona usuarios, base de datos y papelera | App DuaLab, App comercial, Central | **Solo por consola** en el servidor: no se puede crear desde ninguna pantalla |
| `admin` (4) — admin de centro | Usuarios, familias y ciclos de **su** centro | App DuaLab | superadmin (Central → Usuarios) |
| `docente` (2) | Crea encuentros, gestiona retos, grupos y proyectos | App DuaLab | superadmin, o el admin de su centro (que solo puede crear docentes) |
| `empresa` (3) | Valida proyectos; en general solo lectura | App DuaLab | superadmin |
| `comercial` (5) | Capta empresas y centros: entrevista, retos en borrador, firma. Sin centro | App comercial | superadmin |

**Alumnado:** no tiene cuenta. Entra en `dualab.es/unirse` con el código corto que le da su
docente y trabaja en el espacio de su equipo. Solo se guarda un alias («Nombre Animal Color»),
nunca apellidos.

**Si una cuenta entra en una herramienta que no es la suya:**
- Comercial en la App DuaLab → aviso «Has entrado como comercial» con un botón para ir a la app
  comercial y otro para cerrar la sesión.
- Otro rol en la App comercial o en Central → se cierra la sesión y aparece el login con el aviso
  de rol no permitido.

## Roles de Gestión web (`…_front_office`)

| Rol (en BD) | En pantalla | Qué hace | Quién crea la cuenta |
|---|---|---|---|
| `admin` | Administración | Todo: aprueba contenido, configura la web (apariencia, idiomas, analítica, integraciones) y gestiona el equipo | El primero, por consola; los siguientes, otro admin desde Gestión web → Equipo → Usuarios |
| `editor` | Edición | Crea contenido (páginas, noticias, medios, SEO) y atiende formularios | admin, desde Gestión web → Equipo → Usuarios |
| `contactos` | Atención de contactos | Solo atiende los formularios recibidos | admin, desde Gestión web → Equipo → Usuarios |

Las cuentas de Gestión web no aparecen en Central, ni las de la app en Gestión web.

## Sesiones: una por herramienta

La App DuaLab, la App comercial y Central usan la misma API, pero **cada una tiene su propia
sesión**: entrar con otra cuenta en una de ellas no cambia las otras (por ejemplo, el superadmin
sigue en Central aunque pruebe una cuenta comercial en la app comercial). Por eso hay que iniciar
sesión en cada herramienta por separado.

Gestión web también tiene su propia sesión: no interfiere con las otras tres.

## Contraseñas olvidadas y cuentas por consola

Las contraseñas se guardan cifradas: nadie puede leerlas, solo cambiarlas.

| Caso | Cómo se resuelve |
|---|---|
| Cualquier cuenta de la app con un correo real | `dualab.es/login` → «¿Has olvidado tu contraseña?» (sirve también para entrar después en la App comercial o en Central) |
| Cuenta de Gestión web con un correo real | «¿Has olvidado tu contraseña?» en el login de Gestión web |
| Cuenta de Gestión web, por consola | En `info/api-backend`: `php artisan gestion-web:usuario <correo> --rol=admin --cambiar-contrasena` (pide la contraseña sin mostrarla) |
| Superadmin nuevo de la app | Por consola en `api-backend`, con `php artisan tinker`. Escribe en la base de datos de producción: hacerlo con cuidado y sin dejar la contraseña escrita en el comando |

**Correos inventados:** una cuenta con un correo que no existe (por ejemplo, la que crea el
seeder con `ADMIN_EMAIL`) no puede recuperar la contraseña por correo. En producción, mejor
cuentas con buzones reales, y bloquear las que queden con correos inventados.
