# Guía de diseño — DuaLab (frontend-microretos)

Documento de referencia para cualquier vista o componente `.vue` nuevo o modificado.
Une en un solo sitio lo que ya estaba repartido entre el Manual de marca e imagen
DUALAB V2.0, `frontend-microretos/src/style.css` y `CLAUDE.md`, e incorpora la propuesta
de sistema de diseño adaptada a lo que ya tiene la app.

- **Fuente de verdad de los valores:** el bloque `@theme` de `src/style.css`. Si esta guía y
  `style.css` no coinciden, manda `style.css`; después se corrige la guía.
- **Normas de forma y responsive:** siguen en `CLAUDE.md`. Aquí solo se resumen (§5).
- Leyenda de estado: ✅ ya existe · 🆕 propuesto, aún no está en el código · ⚠ hay que corregirlo.

---

## 1. Tokens

La propuesta usaba variables `--dl-*`. **No se crean**: la app ya tiene esos colores como tokens
de Tailwind v4 (`bg-centros`, `text-azul-noche`…). Equivalencias:

| Propuesta | Valor | Token real (`@theme`) | Clase Tailwind | Estado |
|---|---|---|---|---|
| `--dl-navy` | `#17283E` | `--color-azul-noche` | `bg-azul-noche` | ✅ |
| `--dl-blue` | `#3072AA` | `--color-centros` / `--color-primary-600` | `bg-centros` | ✅ |
| `--dl-green` | `#509928` | `--color-empresas` | `bg-empresas` | ✅ |
| `--dl-orange` | `#FF8920` | `--color-alumnos` | `bg-alumnos` | ✅ |
| `--dl-teal` | `#19A7A8` | `--color-administraciones` | `bg-administraciones` | ✅ |
| `--dl-bg` | `#F4F7FA` | fondo de `body` (fijo en `style.css`) | — | ✅ |
| `--dl-surface` | `#FFFFFF` | — | `bg-white` | ✅ |
| `--dl-text` | `#17283E` | `--color-azul-noche` | `text-azul-noche` | ✅ para titulares |
| `--dl-muted` | `#526176` | `--color-texto-secundario` | `text-texto-secundario` | 🆕 |
| `--dl-border` | `#D9E1EA` | `--color-borde` | `border-borde` | 🆕 solo bordes decorativos (ver §2.3) |
| `--dl-focus` | `#155EEF` | se usa `--color-centros` (azul de marca) | `ring-centros` | ❌ no se añade otro azul |
| `--dl-radius` | `12px` | `rounded-xl` de Tailwind (12px) | `rounded-xl` | ✅ |
| `--dl-space` | `8px` | escala de Tailwind (`2` = 8px) | `p-2`, `gap-2`… | ✅ |

Variantes de contraste que ya existen y deben usarse (no inventar otras):

| Token | Valor | Para qué |
|---|---|---|
| `--color-centros-light` | `#6BA4D5` | texto/iconos azules sobre azul noche |
| `--color-empresas-light` | `#6EC13F` | texto verde sobre azul noche |
| `--color-empresas-dark` | `#3D7A1E` | texto verde sobre fondo claro |
| `--color-alumnos-dark` | `#B85E0A` | texto naranja sobre fondo claro |
| `--color-administraciones-dark` | `#0F7C7D` | 🆕 texto turquesa sobre fondo claro (5,0:1) |

**Tipografías** ✅: `--font-heading` Montserrat (600/700) en `h1–h6` y `font-sans` Inter (400–700)
en el resto. Se cargan en la primera línea de `style.css`.

---

## 2. Color

### 2.1 Un color para actuar, el resto con significado

- **Color de acción = color del rol** (decidido 2026-10-21). Los botones primarios, el foco y
  los acentos usan el color de quien ha iniciado sesión (`useRoleTheme`): azul con sesión de docente,
  turquesa con admin/superadmin y verde con empresa. El color del rol también está en el *chrome*
  (TopBar, SidePanel, elemento activo de navegación) y en la palabra destacada de `CabeceraSeccion`.
- **Fondo de botón con texto blanco:** azul `centros` tal cual (5,1:1); para turquesa y verde se usan
  sus variantes `-dark` (`#0F7C7D` 5,0:1 y `#3D7A1E` 5,2:1), porque los colores de marca puros con
  texto blanco no llegan a 4,5:1 (ver §2.2).
- **Colores de colectivo con significado fijo:** verde = empresa, naranja = alumnado,
  turquesa = administración, azul = centro/docente. Si una etiqueta habla de una empresa, va en
  verde aunque la sesión sea de docente.
- **IA:** violeta (`violet-*`) en botones «Sugerir con IA» y etiquetas «Sugerencia IA», siempre
  con el icono de destellos.
- **Estados** (semántica, no marca): éxito `emerald-*`, aviso `amber-*`, error `red-*`,
  información en el color del rol. Destructivo = rojo, siempre con confirmación.
- Evitar pantallas muy coloridas: como máximo color del rol + estados + IA.

### 2.2 Contraste medido (WCAG 2.2 AA)

Umbrales: 4,5:1 texto normal · 3:1 texto grande (≥ 24px, o ≥ 18,5px en negrita) e iconos/bordes
que hacen falta para entender la interfaz.

| Combinación | Ratio | Uso permitido |
|---|---|---|
| `azul-noche` sobre `#F4F7FA` | 13,9 | todo |
| texto de `body` `#4B5563` sobre `#F4F7FA` | 7,0 | todo |
| `#526176` sobre blanco / `#F4F7FA` | 6,3 / 5,9 | texto secundario |
| `centros` sobre blanco / `#F4F7FA` | 5,1 / 4,8 | texto, enlaces, iconos |
| blanco sobre `centros` | 5,1 | botones primarios |
| blanco sobre `empresas` | 3,5 | ⚠ solo texto grande o negrita ≥ 18,5px |
| blanco sobre `administraciones` | 2,9 | ⚠ no vale para texto: usar `-dark` de fondo o texto oscuro |
| blanco sobre `alumnos` | 2,4 | ⚠ no vale para texto: usar texto `azul-noche` sobre naranja |
| `administraciones` como texto sobre blanco | 2,9 | ⚠ usar `--color-administraciones-dark` |
| placeholder `#9CA3AF` sobre campo `#F2F7FB` | 2,4 | ⚠ subir a `#6B7280` (4,8) |

> ⚠ El comentario de `--color-alumnos-dark` en `style.css` dice que el naranja «vale de sobra
> como fondo con texto blanco encima». No es así: da 2,4:1. Hay que corregir el comentario y
> revisar los botones y badges naranjas con texto blanco.

### 2.3 Bordes

- Bordes decorativos de tarjetas y separadores: `border-borde` (`#D9E1EA`) o `border-gray-200`.
  No necesitan contraste.
- **Bordes de campos** (`input`, `select`, `textarea`): son lo que permite ver el campo, así que
  necesitan 3:1. El borde actual `#C7DCEC` da 1,4:1 ⚠. Propuesta: borde `#7D8CA1` en reposo
  (3,4:1 sobre blanco y 3,2:1 sobre el fondo del campo); foco en `centros` como ahora.

---

## 3. Tipografía

| Rol | Tamaño | Clase | Notas |
|---|---|---|---|
| Título de vista (`CabeceraSeccion`) | 24 → 36px | `text-2xl sm:text-4xl` | Montserrat |
| Título de tarjeta/sección | 16–20px | `text-base`–`text-xl` | Montserrat 600/700 |
| Cuerpo | 14–16px | `text-sm`–`text-base` | Inter, `leading-relaxed` (1,4–1,6) |
| Texto secundario, metadatos | 12–13px | `text-xs`, `text-[13px]` | **mínimo** |
| Etiquetas en mayúsculas (`.label-style`, badges) | 12px | `text-xs uppercase tracking-wide` | `font-bold`, no `font-black` |

⚠ **Deuda actual:** hay unos 750 usos de `text-[10px]`, 250 de `text-[11px]`, 180 de `text-[9px]`
y 7 de `text-[8px]`, todos por debajo del mínimo de 12px. No se corrige de golpe: cada vista que
se toque sube sus textos a `text-xs` como mínimo. `.label-style` (10px) es el primero que conviene
cambiar, porque se usa en todo el generador.

- `font-black` (900) no está cargada (Inter se carga hasta 700): el navegador la simula con 700.
  En código nuevo, usar `font-bold`.

---

## 4. Espaciado, superficies y sombras

- Espaciado en múltiplos de 4/8px (escala de Tailwind). No usar valores arbitrarios como `p-[13px]`.
- Tarjetas: `p-4 sm:p-6` (16–24px), `bg-white`, borde sutil, `shadow-sm`. Sombras grandes
  (`shadow-xl/2xl`) solo en modales y elementos flotantes.
- Separación entre bloques de una vista: `space-y-6` / `gap-6`; dentro de una tarjeta: `gap-3`–`gap-4`.

## 5. Formas (resumen de `CLAUDE.md`)

- Botones, pestañas, chips y etiquetas de estado → píldora (`rounded-full`). Botón de solo icono → círculo.
- Tarjetas, paneles y campos → `rounded-xl` / `rounded-2xl`; modales `rounded-[2rem]`.
- Pasos de asistente → `.fila-pasos` con `.btn-paso-atras` a la izquierda y `.btn-paso` a la derecha.
- Cabecera de vista → `.accion-principal`, `.accion-secundaria`, `.accion-icono`.
- Excepciones (nav del SidePanel, tarjetas-selector, filas de menú): ver `CLAUDE.md`.

---

## 6. Estados, feedback y accesibilidad

### 6.1 Estados de los datos
Toda vista o bloque que cargue datos contempla cuatro estados:

1. **Carga:** *skeleton* con la forma del contenido (`animate-pulse` + bloques `bg-gray-200
   rounded-xl`), no un spinner a pantalla completa.
2. **Vacío:** icono + frase que explica por qué está vacío + acción para salir de ahí si la hay
   («Crear reto», «Limpiar filtros»).
3. **Error:** mensaje entendible + botón «Reintentar». Nunca el texto técnico del servidor.
4. **Con datos.**

No se inventan cifras: las métricas salen de la API. Si no hay dato, se muestra «—» y no un número
de ejemplo de los diseños.

### 6.2 No depender solo del color
Validaciones, selección de RA/CE, estados de proyecto: siempre **color + icono + texto**
(p. ej. ✓ «Validado por la empresa», no solo un punto verde). Los mensajes de error van junto al
campo, con `aria-describedby`.

### 6.3 Foco y teclado
- Todo elemento interactivo tiene foco visible: `focus-visible:outline-2 focus-visible:outline-offset-2
  focus-visible:outline-centros` (sobre fondo oscuro, `outline-white`). Hoy solo unos 20 archivos lo
  tienen ⚠. 🆕 Propuesta: una regla global en `@layer base` de `style.css` para `a, button,
  [tabindex]`, así no hay que repetirlo en cada componente.
- Objetivos táctiles de 36px como mínimo (`h-9`; los botones de acción ya miden `h-10`/`h-11`).
- Iconos: `@heroicons/vue` (outline para navegación y acciones, solid para estados). Botón de solo
  icono con `aria-label` y `title`.

### 6.4 Modales
Requisitos para todos (hoy `Modal.vue` y `ValidarDocenteModal.vue` no cumplen ninguno ⚠):
`role="dialog"`, `aria-modal="true"` y `aria-labelledby` apuntando al título; foco inicial en el
primer control o en el título; foco atrapado dentro mientras está abierto; cerrar con `Escape`;
al cerrar, el foco vuelve al botón que lo abrió. 🆕 Lo ideal es meterlo una sola vez en `Modal.vue`
(o en un composable `useModalAccesible`) y que el resto de modales lo usen.

### 6.5 Gráficos
Todo gráfico lleva al lado una frase con la conclusión («12 proyectos validados este trimestre,
4 más que el anterior») y una tabla equivalente, aunque esté plegada.

---

## 7. Plantilla de vista (componentes comunes)

- `CabeceraSeccion`: flecha volver encima del título (una por vista), y a la derecha Ayuda →
  papelera de superadmin → acción principal (la última).
- `FiltrosPanel`: buscador con etiqueta visible + `SelectorCurso`; `FiltroSegmentado` (≤ 5 opciones)
  o `FiltroSelect` (listas largas); pie con número de resultados y «Limpiar filtros».
- Criterio: si cambia lo que se ve → filtro; si hace algo → cabecera. Los estados («Completados»)
  son opciones del filtro Estado, no botones.
- Capas internas en la URL (query) + `MigasNavegacion`. Paginación: `PaginacionFlechas` + `usePaginacion`.

### Terminología
«retos» (nunca «microretos» en la interfaz) · «Taller de Ideas» (nunca «StartUp Day») · para el
docente, «grupo» y «Clase»; para el alumnado, «equipo» · alumnado siempre por alias, nunca con
nombre real.

---

## 8. Especificación por pantalla

Adaptación de la propuesta a las vistas reales. Lo que ya cumple no se repite; se listan los
requisitos y lo que falta.

### 8.1 Dashboard — `InicioDocente.vue` / `DashboardDocente.vue`
- Se mantienen SidePanel, TopBar y acceso a módulos. El elemento activo del SidePanel se distingue
  con color + fondo + indicador lateral, no solo con color.
- Orden: bienvenida y acciones frecuentes → resumen del curso → proyectos y validaciones pendientes
  → encuentros → tareas.
- Tarjetas de métricas: dato de la API, periodo («este curso», «últimos 30 días») y los cuatro
  estados de §6.1.
- Gráficos según §6.5.

### 8.2 Explorador de Retos — `BibliotecaMicroretos.vue`
- Cabecera con `CabeceraSeccion` ✅. Un solo buscador en `FiltrosPanel`, con etiqueta visible.
- Filtros: ciclo, módulo, nivel, dificultad, empresa (real/ficticia) y tipo de información.
  Total de resultados, filtros activos como chips quitables y «Limpiar filtros». Filtros en la query
  de la URL.
- Tarjeta: título, pregunta del reto, empresa, familia profesional, ciclo, curso, nivel y fecha.
- Etiquetas «Empresa ficticia» e «Información simulada» siempre visibles en la tarjeta y en el
  detalle, con icono y en gris/ámbar neutro: no pueden parecerse a la etiqueta de una empresa real (verde).
- Acciones de la tarjeta: «Ver detalle»; «Crear proyecto» solo si el rol puede (lo decide el
  backend, el frontend solo oculta el botón); «Descargar PDF» solo donde existe exportación real.
- Carga, vacío, error y paginación; la tarjeta entera se puede recorrer con teclado.

### 8.3 Taller de Ideas: currículo y criterios — `StartupDayWizard.vue`
- Indicador «Paso X de 7» ✅. La barra de progreso calcula pasos *completados*
  (`(paso − 1) / 6`), así que en el paso 3 marca 33 %: es coherente, pero si se muestra el porcentaje
  en texto debe decir «2 de 7 completados» para que no parezca un error.
- Jerarquía Módulo → RA → CE en acordeones; CE con checkbox, contador de seleccionados y resumen
  fijo (lateral en escritorio, plegable abajo en móvil).
- Bloques separados y titulados: «RA/CE del reto de origen» (solo lectura) y «RA/CE de esta propuesta».
- Sugerencias de IA con etiqueta «Sugerencia IA», editables y sin aplicar hasta que el docente las acepte.
- Los códigos y textos oficiales de RA/CE no se editan desde la interfaz.
- Guardado: estado visible («Guardado», «Guardando…», «Sin guardar») y aviso al salir con cambios pendientes.

### 8.4 Taller de Ideas: objetivos y KPI — `StartupDayWizard.vue`
- Dos secciones: «Objetivos del proyecto» (obligatorios según la regla actual del backend) e
  «Indicadores de éxito» (opcionales; no bloquean el avance).
- Añadir, editar, reordenar y eliminar; eliminar pide confirmación si el ítem tiene texto.
- Errores junto al campo. Lo que sugiere la IA no se guarda sin aceptación del docente.
- `.fila-pasos` siempre visible al final del paso; si falla el guardado al pulsar Siguiente, aviso y no avanza.

### 8.5 Modal de validación — `StartupDayDetalle.vue` / `ValidarDocenteModal.vue`
- Título «Proyecto en propuesta» y estado «Pendiente de validar» ✅.
- Dos vías separadas visualmente:
  - **Empresa:** empresa asociada, enlace generado por el backend, botones «Copiar» y «Enviar»
    con el flujo actual. El «Enviado» solo aparece cuando el servidor lo confirma.
  - **Docente:** «Validar como docente» → confirmación → el permiso lo comprueba el servidor.
- Texto aclaratorio: «La validación docente no sustituye a la de la empresa». Dos estados separados.
- Nunca enlaces de ejemplo ni envíos simulados.
- Accesibilidad de modal según §6.4 ⚠.

### 8.6 Ficha de proyecto — `StartupDayDetalle.vue` / `ProyectoFichaModal.vue`
- Encabezado: título, pregunta, estado real, identificador y acciones según permisos.
- Pestañas en píldora (o anclas): Resumen, Currículo, Propuesta, Objetivos, Grupo, Validaciones,
  Historial. Una pestaña sin datos no se muestra.
- Bloque de validación: empresa y docente por separado, con fecha, responsable y acción disponible.
- Panel lateral (debajo en móvil): empresa, centro, ciclo, curso, docente.
- Enlace público copiable con estado de envío.
- PDF y archivar solo si existe el endpoint y la policy en backend; archivar/eliminar con confirmación
  (`Eliminar*Modal`).

---

## 9. Cambios propuestos en el código (pendientes de aprobar)

Ordenados por impacto/esfuerzo. Ninguno está aplicado.

1. `style.css`: añadir `--color-texto-secundario`, `--color-borde` y `--color-administraciones-dark`;
   placeholder a `#6B7280`; corregir el comentario del naranja.
2. `style.css`: foco visible global en `@layer base`.
3. `Modal.vue`: role/aria, Escape, trampa de foco y devolución del foco; migrar `ValidarDocenteModal` a él.
4. `.label-style` a 12px; subir `text-[8px]`–`text-[11px]` en cada vista que se toque.
5. Borde de campos con 3:1.
6. Revisar botones y badges de texto blanco sobre naranja y turquesa.
7. Botones de acción en el color del rol (§2.1): variable CSS del rol en la raíz y `.accion-principal` / `.btn-paso` leyéndola.
