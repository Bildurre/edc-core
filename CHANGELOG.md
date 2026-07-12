# Changelog — edc-motor/core

Backend Laravel reutilizable del motor. Versión de tren con `@edc-motor/ui` y
`@edc-motor/admin-kit` (tag `vX.Y.Z` en el monorepo).

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
