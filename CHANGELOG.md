# Changelog — edc-motor/core

Backend Laravel reutilizable del motor. Versión de tren con `@edc-motor/ui` y
`@edc-motor/admin-kit` (tag `vX.Y.Z` en el monorepo).

## [Sin publicar]

### Cambiado

- **El menú pierde los GRUPOS y su jerarquía pasa a ser SIEMPRE la del CRM**
  (rediseño del menú de 0.4.24): fuera el tipo `group` y la columna `label`
  — "si quieres un grupo, haz una página" (una página con hijas actúa de
  desplegable). Migración `2026_07_20_000002` con guardas: en BBDD frescas
  la tabla ya nace con el esquema final; en las que migraron la 0.4.24,
  borra las filas de grupo y suelta la columna. El árbol deriva el anidado
  de `pages.parent_id` (nunca se copia a `menu_items`; su `parent_id` queda
  solo para colgar una RUTA bajo una página raíz) y es BIDIRECCIONAL:
  escribir la jerarquía desde el menú actualiza `pages.parent_id`/`order`,
  y cambiar la madre por el CRM se refleja al momento. API: `GET
  /api/admin/menu` + nuevo `PUT /api/admin/menu` (el árbol ENTERO de una
  vez — `items: [{id, parent_id, is_visible}]`, orden por posición,
  transaccional, valida padre = página raíz); desaparecen `POST /groups`,
  `PATCH /{item}`, `POST /reorder` y `DELETE /{item}`.
- **Bloques anidables SIN límite de niveles**: las reglas del bloque padre
  prohíben CICLOS (uno mismo o un descendiente propio), ya no cadenas; el
  `IndexBlock` calcula la profundidad real subiendo la cadena de padres
  (el orden de bloques es un preorden del árbol).
- **Páginas: un solo nivel, validado** (`PageController`): no se puede
  anidar una página bajo otra hija, encadenar niveles ni mover una página
  CON hijas dentro de otra (mismo patrón que tenían los bloques).

## [0.4.24] — 2026-07-20

### Añadido

- **Menú configurable de la web pública** (doc 10 ampliado): tabla
  `menu_items` (migración `2026_07_20_000001`), modelo `MenuItem`
  (traducible en `label`, solo para grupos) y `Edc\Core\Menu\MenuSync`, que
  garantiza exactamente un item por página NO home (publicada o no) y por
  cada `route_key` de la nueva config `motor.menu.routes` — los añade AL
  FINAL de la raíz y borra los huérfanos (página borrada o convertida en
  home; clave retirada de la config). Los grupos son del admin: `MenuSync`
  nunca los toca; al borrar uno, sus hijos pasan a la raíz.
- **Endpoints del menú**: admin (`GET /api/admin/menu` sincroniza y devuelve
  el árbol completo con la página embebida; `POST /api/admin/menu/groups`;
  `PATCH /api/admin/menu/{item}` para visibilidad/grupo/label; `POST
  /api/admin/menu/reorder`; `DELETE /api/admin/menu/{item}`, solo grupos —
  mismo reparto que las páginas, `can:manage-web`) y público (`GET
  /api/menu`: solo visibles, páginas además publicadas, grupos sin hijos
  visibles fuera; cacheado con `motor.content.cache_ttl`, clave
  `motor.menu.nav`, invalidada en los mismos puntos que `motor.pages.nav`
  —`PageService::forget/setHome/reorder`— y en cada escritura del menú). El
  endpoint viejo `pages/nav` sigue vivo (retrocompatibilidad).

### Cambiado

- **`IndexBlock`: etiqueta por título > subtítulo > contenido**: cada
  entrada usa el TÍTULO del bloque; sin título, su subtítulo; sin ninguno,
  el primer contenido traducible con valor, truncado a 80 — y si viene de
  un wysiwyg, SOLO el texto de su primera etiqueta (el primer párrafo).
  Antes valía el primer campo de texto que apareciera (y los subtítulos,
  ya textarea, habían quedado fuera).

## [0.4.23] — 2026-07-19

- Sin cambios propios: versión de tren.

## [0.4.22] — 2026-07-19

### Cambiado

- **`QuoteBlock`: el autor se alinea a la DERECHA por defecto**
  (`author_align`; los bloques con valor guardado no cambian).

## [0.4.21] — 2026-07-19

- Sin cambios propios: versión de tren.

## [0.4.20] — 2026-07-19

### Cambiado

- **Alineación propia de título y subtítulo** (campos comunes
  `title_align` / `subtitle_align`): izquierda/centrado/derecha, con "La
  del bloque" por defecto (el comportamiento de siempre; los bloques
  guardados no cambian).
- **`QuoteBlock`: alineación del autor**: nuevo select `author_align`
  (izquierda/centrado/derecha, por defecto izquierda).

## [0.4.19] — 2026-07-19

### Cambiado

- **El subtítulo de TODOS los bloques pasa a textarea** (`Field::textarea`):
  admite saltos de línea (el ui los respeta con `pre-line`). Los juegos con
  bloques propios que tengan subtítulo deberían hacer el mismo cambio.
- **Alineación por defecto de los bloques: JUSTIFICADO** (campo común
  `align`): los bloques guardados con una alineación explícita no cambian.
- **`CtaBlock`: alineación y tamaño del botón**: nuevo select
  `button_align` (izquierda/centrado/derecha, por defecto izquierda — en
  formato estrecho el ui centra siempre) y boolean `button_large` (más
  padding interior).

## [0.4.18] — 2026-07-19

### Cambiado

- **`RelatedBlock` sin campo "Número de elementos"**: `resolveData` trae
  SIEMPRE 6 ítems y es el grid del ui quien decide cuántos enseña por ancho
  (4 en 2×2 → 6 en 3×2 → 4 en 4×1 → 5 en 5×1) para que las filas salgan
  siempre completas. El `count` de bloques ya guardados se ignora y se
  descarta al volver a guardar (la validación deriva del esquema).

## [0.4.17] — 2026-07-19

- Sin cambios propios: versión de tren.

## [0.4.16] — 2026-07-19

### Añadido

- **Quitar la imagen de una entidad al guardar** (`HasImage`):
  `setImageFromRequest()` entiende `remove_image` (booleano; con clave
  propia, `remove_{clave}`) — sin fichero en la petición y con el flag a
  verdadero, vacía la colección `image` (el fichero desaparece del disco).
  Así el "quitar imagen" de los formularios viaja DIFERIDO con el guardado,
  igual que la subida (multipart en el store/update de la entidad); los
  juegos no cambian nada en el backend: el trait lo resuelve.

- **Subir copias de seguridad**: `POST api/admin/backups/upload` importa un
  zip de copia (spatie/laravel-backup o equivalente) validando extensión,
  tamaño (nueva clave `motor.backup.upload_max_mb`, 500 MB por defecto) y
  estructura — debe traer una BBDD dentro: dump SQL en `db-dumps/` o fichero
  `.sqlite`. Se guarda en el destino con prefijo `upload-` y el listado la
  marca con `origin: upload` ("subida").
- **Restaurar una copia**: `POST api/admin/backups/{file}/restore` importa
  la BBDD del zip MACHACANDO la actual (nuevo `BackupRestorer`; el admin
  pide doble confirmación). Con SQLite en fichero se sustituye el fichero de
  la BBDD tal cual (así empaqueta `MotorBackup`); con el resto de drivers se
  vacía el esquema tabla a tabla y se ejecuta el dump (`db-dumps/*.sql` del
  driver, o el primero). Límites documentados (también en el panel): SOLO la
  base de datos — el storage que traiga el zip no se restaura —, dumps sin
  comprimir, y puede invalidar los tokens de sesión vigentes. Limpia la
  caché al acabar; 422 si el zip no trae ninguna BBDD.
- **Origen de cada copia en el listado** (`origin`): `manual` (las del botón
  del admin, prefijo `manual-`), `upload` (subidas) o `auto` (nombre-fecha
  del scheduler). Derivado del nombre del fichero, sin estado aparte.

### Cambiado

- **Crear copia SIEMPRE en cola (DC-16)**: el POST ya no genera el zip en la
  petición (bloqueaba la web mientras tanto) — despacha `RunBackupJob` (202
  + `queued`) y el listado expone `pending` (flag en caché con TTL de 15 min
  que el job limpia al acabar) para que el admin sondee sin bloquear. Con la
  cola `sync` se difiere a después de la respuesta, con el mismo guard de
  tests que `HasPreviewImage::regeneratePreviews()`. La clave
  `motor.backup.queue` desaparece (ya no hay modo síncrono).

## [0.4.15] — 2026-07-17

- Sin cambios propios: versión de tren.

## [0.4.14] — 2026-07-16

- Sin cambios propios: versión de tren.

## [0.4.13] — 2026-07-16

- Sin cambios propios: versión de tren.

## [0.4.12] — 2026-07-15

- Sin cambios propios: versión de tren.

## [0.4.11] — 2026-07-15

### Corregido

- **El diferido de previews con la cola `sync` ya no se aplica en tests**:
  `regeneratePreviews()` solo usa `dispatchAfterResponse()` fuera de la
  suite (guardado por `app()->runningUnitTests()`). En tests, el diferido de
  0.4.8 apuntaba a los terminating callbacks — que no corren al guardar un
  modelo fuera de una petición, no se limpian entre peticiones simuladas y
  esquivan `Queue::fake()` — y hacía la suite no determinista (renders
  tardíos, duplicados o posteriores a un borrado). Con la cola `sync` de
  tests el despacho vuelve a ser inline, como antes de 0.4.8; en
  instalaciones reales nada cambia (con `sync` se sigue difiriendo a después
  de la respuesta para que guardar nunca se cuelgue).

## [0.4.10] — 2026-07-14

- Sin cambios propios: versión de tren.

## [0.4.9] — 2026-07-13

- Sin cambios propios: versión de tren.

## [0.4.8] — 2026-07-13

### Añadido

- **Ordenación en el listado de usuarios del admin**: `GET /admin/users`
  acepta `?sort` con el contrato de los index — `name`/omitido (alfabético,
  el orden de siempre), `name_desc`, `latest` y `oldest` (por id).

### Corregido

- **La búsqueda de `HasFilters` respeta el locale activo**: `scopeFilter`
  hace el LIKE de cada campo de `$searchable` sobre el json del locale
  activo (`campo->locale`) cuando el campo es traducible (antes buscaba
  sobre el json crudo y mezclaba locales). Sigue recorriendo TODOS los
  campos del array, agrupados en un `where` propio para no pisar el resto
  de filtros (status, etc.).
- **Guardar una entidad renderizable ya no se cuelga con la cola `sync`**:
  `regeneratePreviews()` difiere la generación a después de la respuesta
  cuando el driver es `sync` (antes Browsershot corría inline en la petición
  y el guardado podía colgarse y acabar en 500). La plantilla pasa a
  `QUEUE_CONNECTION=database` en su `.env.example` (el `npm run dev` de los
  juegos ya arranca el worker). **Migración de juegos existentes**: poner
  `QUEUE_CONNECTION=database` en `api/.env`.

## [0.4.7] — 2026-07-12

- Sin cambios propios: versión de tren.

## [0.4.6] — 2026-07-12

### Añadido

- El `?sort` del catálogo público acepta también `oldest` (id ascendente).

## [0.4.5] — 2026-07-12

### Añadido

- **Ordenación en el catálogo público**: el modo lista de
  `GET /api/catalog/{key}` acepta `?sort` — `name` (ascendente por el `name`
  del locale activo), `name_desc` (descendente) y `latest`/omitido (id
  descendente, el comportamiento de siempre). El modo `random` lo ignora.

## [0.4.4] — 2026-07-12

### Añadido

- **Catálogo público genérico**: `GET /api/catalog/{key}` sirve cualquier
  entidad del registry de previews (404 si la clave no existe), sin auth y
  solo publicadas (si el modelo usa `HasPublishedState`). Modo lista con
  `?page`/`?per_page` (24, tope 48), `?search` (sobre el `name` del locale
  activo) y meta de paginación estándar; modo `?mode=random&count=N` (1..12,
  default 4) sin paginar; `?exclude=<id>` para que los singles dejen fuera la
  entidad actual. Ítem: `{id, name, slug|null, preview|null}`
  (`Edc\Core\Previews\CatalogItem`).
- **Bloque `related`** (categoría `data`, el primero del motor): rejilla de
  entidades relacionadas de cualquier clave del registry de previews —
  título/subtítulo, entidad (opciones en vivo del registry), modo
  `latest|random`, `count` (1..12, default 4) y botón opcional al índice.
  `resolveData` devuelve `{key, items}` en formato de ítem de catálogo y no
  revienta si la clave se desregistra. Requiere versión nueva de
  `@edc-motor/ui` (componente `BlockRelated`).

## [0.4.3] — 2026-07-11

- Sin cambios propios: versión de tren.

## [0.4.2] — 2026-07-10

### Cambiado

- **El pie de página es texto rico**: `footer_text` admite el HTML del
  wysiwyg (hasta 2000 caracteres por idioma) y se **sanea por lista blanca**
  al guardar, igual que los bloques del CRM.

## [0.4.0] — 2026-07-07

### Añadido

- **Bloques anidados de un nivel** (`parent_id`, validado: misma página, sin
  encadenar): el hijo se renderiza justo después de su padre y el **índice
  automático** lo saca **indentado** (`items[].depth`).
- **Layout de imagen en columnas** (bloques texto y CTA): `image_fit`
  (contener / cubrir / rellenar, con el alto que marca el texto de al lado) e
  `image_columns` (reparto izquierda:derecha 1:1 … 4:3).
- **Subtítulo en todos los bloques** de presentación; el título ya no es
  obligatorio en ninguno (cabecera incluida).

### Cambiado

- Campos comunes con **valores por defecto**: anchura `wide` (~1200px) y
  alineación izquierda.
- El saneado de texto rico **tira los párrafos vacíos** que cuela el editor
  (`<p> </p>`, `<p><br></p>`).
- Subida de imágenes hasta **10 MB**.

**Migración del cascarón** (si no tocaste esos archivos, cópialos de
`plantilla/`): `admin/src/views/pages/PageSingleView.vue` (panel de la página
en el single), las claves i18n `pages.blocks.parent` / `parentNone` en
`admin/src/i18n/locales/*.json` y
`app/src/assets/scss/components/_app-header.scss` (logo del header más alto
en ancho: 34 → 44 → 56 → 68px por breakpoint).

## [0.3.1] — 2026-07-07

### Corregido

- **Los SVG vuelven a poder subirse** (logo, fondos, imágenes de bloque): la
  regla `image` de Laravel excluye SVG por defecto y la subida los rechazaba
  con "debe ser una imagen". Ahora se admiten (`image:allow_svg`) y se
  guardan **saneados**: sin `<script>`, handlers `on*`, `javascript:` ni
  `foreignObject` (el logo se inlinea en la web pública).

## [0.3.0] — 2026-07-07

### Cambiado

- **Logo traducible** (Configuración): `logo` pasa de una URL única a un mapa
  `{locale: URL}` con fallback al locale por defecto; en el payload público,
  `logo` viaja siempre normalizado a mapa y `logo_inline` pasa a ser un mapa
  por idioma con el contenido de los SVG del disco (currentColor hereda el
  acento). El formato antiguo (string) se sigue aceptando y se normaliza al
  leer. **Migración del cascarón** (juegos generados con la plantilla ≤0.2.0,
  sin tocar esos archivos: cópialos de `plantilla/`): `app/src/stores/site.ts`,
  `app/src/components/AppHeader.vue`,
  `admin/src/views/settings/SettingsView.vue` y
  `admin/src/components/pages/PageFormModal.vue`.
- **Subidas de imagen sin huérfanos**: `POST /admin/content/uploads` guarda
  con el **nombre original** saneado (sufijo `-2`, `-3`… solo si colisiona) y
  borra el fichero sustituido si llega `replaces`; nuevo
  `DELETE /admin/content/uploads` para el botón "quitar" (acotado a
  `content/`, sin traversal).

## [0.2.0] — 2026-07-06

### Cambiado

- **Renombrado del vendor/scope a `edc-motor`** (DC-21 revisada): el paquete
  Composer pasa de `bgm/core` a **`edc-motor/core`** (namespace PHP
  `Edc\Core`) y los npm a **`@edc-motor/ui`** y **`@edc-motor/admin-kit`**.
  Migración de un juego existente: actualizar `composer.json`/`package.json`,
  los imports (`@bgm/` → `@edc-motor/`), el namespace en `config/motor.php` y
  las clases propias, y las clases CSS `bgm-*` → `edc-*`.
- **Licencia GPL-3.0-only** y publicación en registros públicos: Packagist
  (`edc-motor/core`, vía el repo split `bildurre/edc-core`) y npmjs
  (org `edc-motor`). El consumo por clon hermano deja de ser necesario.

## [0.1.0] — 2026-07-05

Primera versión etiquetada (Fases 0–7 del plan).

### Añadido

- **Auth y usuarios (doc 05)**: login/logout con Sanctum, registro con
  verificación de email (DC-14), forgot/reset password (broker + URL del
  frontend configurable), gestión de usuarios y **permisos del motor**
  (`manage-game` / `manage-web` / `manage-users` vía Spatie + Gate, roles
  `admin`/`editor` en config, sincronía única en
  `MotorAuth::syncRolesAndPermissions()`).
- **Comportamientos de modelo (doc 04)**: traits `HasFilters`,
  `HasPublishedState`, `HasImage` (MediaLibrary), `ResolvesBySlug` +
  slug traducible (Spatie Sluggable/Translatable), soft deletes con
  restore/force, locales de contenido configurables.
- **Render a PNG (doc 01)**: `Previews` (registro por tipo), render con
  Browsershot contra la ruta `/_render` del frontend, invalidación al
  guardar, endpoints `api/admin/previews/*` (lotes, por entidad, huérfanos).
- **PDF (doc 02)**: `Pdfs` (registro de exports por juego: globales y
  por-entidad), generación con DomPDF (layouts con marcas de corte,
  tamaños por export), versionado de fichero al regenerar, **colección
  temporal** de usuario *y de invitado* (`guest_token` +
  `X-Collection-Token`), descargas públicas de permanentes
  (`GET /api/downloads`), nombres de archivo por el **nombre de la
  entidad** (nunca el id) y limpieza programable (`pdf:cleanup`).
- **CRM de páginas y bloques (doc 03)**: Page/Block jerárquicos,
  traducibles y reordenables, SEO por página, home única, bloques sin
  columnas (todo en `settings`), catálogo de 10 tipos, **DSL de campos**
  (`Field::…` con `group`/`repeater`/`entity`, sanitización y validación
  recursivas), render público (`renderData`).
- **Configuración de la web**: `SiteSettings` (título, logo, favicon,
  acento fijo/aleatorio, fuentes body/headers/**especial**, webfonts
  subibles), endpoint público y de admin.
- **Backup de BBDD (doc 06, DC-16)**: spatie/laravel-backup configurado
  desde `motor.backup` (`MotorBackup::applyConfig()`), API de copias
  (crear en cola, listar, descargar, borrar), **copia automática**
  configurable en runtime (`BackupSettings` + `MotorBackup::schedule()`)
  y guía de restauración.
- **Web pública (doc 10)**: endpoints públicos de entidades (publicadas,
  slug resoluble en cualquier locale), `SitemapRegistry` + facade
  `Sitemap` (`GET /sitemap.xml`), monitor de salud.
- **Privacidad y correos**: el registro exige aceptación explícita
  (`privacy`), guarda el `locale` del usuario (registro/login) y las
  notificaciones de Laravel salen en su idioma (`preferredLocale` +
  traducciones JSON es/eu del motor).
- **Nav pública con hijas**: `GET /api/pages/nav` incluye las páginas hijas
  publicadas (submenú del nav, patrón CDL).
- **Colección en la cuenta**: al autenticarse con cabecera de invitado, los
  items y PDF temporales del token se ADOPTAN al usuario (merge; a igual
  item, gana el de más copias).
- **Handoff web <-> admin**: `POST /auth/handoff` (código de un solo uso,
  60 s) + `POST /auth/handoff/consume` (canje público por token propio):
  los enlaces cruzados entre las SPA mantienen la sesión sin exponer el
  token en la URL. Las escrituras oportunistas (p. ej. `users.locale`) van
  con `rescue()`: una migración pendiente no tumba el login.
- **Acciones "de todas" en PDF** (espejo de previews): `generate-missing`,
  `regenerate-all` y `DELETE ?type=` + `stats` por idioma en el catálogo.
- **Iconos**: edición (renombrar / sustituir imagen) además de alta y borrado.
- Migraciones consolidadas, seeder demo completo, config publicable
  (`motor.php`), Pint propio y suite Pest.
