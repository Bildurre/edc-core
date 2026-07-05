# Changelog — bgm/core

Backend Laravel reutilizable del motor. Versión de tren con `@bgm/ui` y
`@bgm/admin-kit` (tag `vX.Y.Z` en el monorepo).

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
- **Handoff web <-> admin**: `POST /auth/handoff` (código de un solo uso,
  60 s) + `POST /auth/handoff/consume` (canje público por token propio):
  los enlaces cruzados entre las SPA mantienen la sesión sin exponer el
  token en la URL. Las escrituras oportunistas (p. ej. `users.locale`) van
  con `rescue()`: una migración pendiente no tumba el login.
- **Acciones "de todas" en PDF** (espejo de previews): `generate-missing`,
  `regenerate-all` y `DELETE ?type=` + `stats` por idioma en el catálogo.
- **Iconos**: edición (renombrar / sustituir imagen) además de alta y borrado.
- Migraciones consolidadas, seeder demo completo, config publicable
  (`motor.php`), Pint propio y suite Pest (118 tests) en el playground.
