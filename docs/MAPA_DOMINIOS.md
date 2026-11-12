# Mapa de dominios, APIs y bases de datos de DuaLab

Qué sirve cada dirección, de qué repositorio sale, qué carpeta del servidor ocupa y qué base de
datos usa. **Léelo antes de borrar, restaurar o mover nada en hPanel**: el 2026-11-12 se borró
`info.dualab.es` pensando que no se usaba y la web pública se quedó sin API ni base de datos hasta
restaurarlas desde las copias de Hostinger.

Comprobación rápida de que todo responde: `./scripts/comprobar-dominios.sh`

> Este archivo es público (como `CLAUDE.md`): sin ID de cuenta, IP ni rutas absolutas del
> servidor. Las carpetas se indican desde `public_html` del sitio dualab.es en Hostinger.

---

## Las dos piezas: dos backends, dos bases de datos, un mismo dominio

| | App DuaLab (repo `microretos`) | Web pública (repo `frontoffice_dualab`) |
|---|---|---|
| **API** | `https://api.dualab.es` | `https://info.dualab.es/api-backend/public` |
| **Base de datos** | `…_microretos` (usuario `…_admin`) | `…_front_office` (usuario `…_front_office`) |
| **Qué guarda** | Usuarios, centros, familias, ciclos, empresas, retos, encuentros, grupos, captaciones, firmas | Textos de páginas, noticias, medios, apariencia, SEO, formularios recibidos, usuarios del panel de gestión web |
| **Cómo se despliega** | `./deploy.sh` (raíz de microretos) + subir a mano `dist-comercial/`, `dist-central/`, `dist-nexo/` | `FRONTOFFICE/dualab_proyecto/deploy.sh` |

Las dos APIs solo se hablan en un punto: los formularios de **empresas y centros** de la web viajan
por un **webhook firmado** (`FRONTOFFICE_WEBHOOK_SECRETO`, el mismo en los dos `.env` y en Gestión web
→ Integraciones) a `https://api.dualab.es/api/integraciones/frontoffice/formularios` y se convierten en
captaciones que valida el superadmin en DuaLab Central.

## Cada dirección

| Dirección | Qué es | Repo | Carpeta en el servidor | API que usa |
|---|---|---|---|---|
| `dualab.es/login`, `/retos`, `/panel-docente`… (todo lo que no sea de la web) | **App DuaLab** (docentes, alumnado, empresas, admin de centro) | microretos | `public_html/` | api.dualab.es |
| `dualab.es/` (portada), `/centros`, `/empresas-asociadas`, `/para-alumnado`, `/entidades`, `/familias`, `/contacto`, `/noticias`, `/proyectos-destacados`, `/retos-destacados` | **Web pública** (lista explícita en el `.htaccess` de la raíz; `/noticias/dualab` y `/noticias/plataforma` son de la app) | frontoffice | `public_html/home/` (el `.htaccess` de la raíz manda allí estas rutas) | info.dualab.es (+ api.dualab.es para el escaparate de retos) |
| `dualab.es/gestion-web` | **Gestión web** (panel de la web: admin, edición, atención de contactos) | frontoffice | `public_html/home/` (misma build que la web) | info.dualab.es |
| `api.dualab.es` | **API de la app** (Laravel) | microretos | `public_html/api-backend/` | — |
| `info.dualab.es` | **API de la web** (Laravel). Es un **sitio propio en hPanel**: borrarlo borra también su base de datos | frontoffice | `public_html/info/` (Laravel en `info/api-backend/`) | — |
| `comercial.dualab.es` | **App comercial** (entrevistas a empresas y centros) | microretos | `public_html/comercial/` ← `dist-comercial/` | api.dualab.es |
| `central.dualab.es` | **DuaLab Central** (superadmin: captaciones, base de datos, usuarios, papelera) | microretos | `public_html/central/` ← `dist-central/` | api.dualab.es |
| `nexo.dualab.es` | **Nexo DuaLab** (página de acceso: un botón por herramienta, sin sesión) | microretos | `public_html/nexo/` ← `dist-nexo/` | ninguna |

## En local

| Puerto | Qué | Cómo se arranca |
|---|---|---|
| 5173 | App DuaLab | `cd frontend-microretos && npm run dev` |
| 5174 | Web pública + Gestión web | `cd FRONTOFFICE/dualab_proyecto/frontend && npm run dev` |
| 5176 | Sail (Vite dentro de Docker) | `./vendor/bin/sail up -d` |
| 5177 | App comercial | `npm run dev:comercial` |
| 5178 | Nexo | `npm run dev:nexo` |
| 5179 | DuaLab Central | `npm run dev:central` |
| 8000 | API de la app (Sail) | `./vendor/bin/sail up -d` |
| 8001 | API de la web | `cd FRONTOFFICE/dualab_proyecto/backend && php artisan serve --port=8001` |
| 8025 / 8026 | Mailpit (correos de prueba) | con Sail |

## Sesiones y cookies (por qué importan los `.env`)

Todo vive bajo `.dualab.es`, así que las cookies se comparten entre subdominios:

- Cada backend necesita **su propio nombre de cookie de sesión** (`SESSION_COOKIE`; si no, sale de
  `APP_NAME`). Si coincidieran, entrar en un panel cerraría la sesión del otro.
- `SANCTUM_STATEFUL_DOMAINS` y `CORS_ALLOWED_ORIGINS` de cada API deben listar los sitios que la usan:
  - app: `dualab.es`, `www.dualab.es`, `comercial.dualab.es`, `central.dualab.es`
  - web: `dualab.es`, `www.dualab.es`, `info.dualab.es`
- La app comercial y Central comparten sesión con la app DuaLab (mismo backend): una cuenta
  comercial que abra `dualab.es` acaba en la app comercial, y es lo esperado.

## Antes de tocar nada en hPanel

1. Mira esta tabla: ¿la dirección o la carpeta aparece aquí? Entonces se usa.
2. Haz copia de las dos bases de datos y de los dos `.env` (en `~/backups`, con `chmod 600`).
3. Los sitios propios de hPanel (como `info.dualab.es`) llevan sus bases de datos: borrar el sitio las borra.
4. Después, `./scripts/comprobar-dominios.sh` para ver que todo sigue respondiendo.
