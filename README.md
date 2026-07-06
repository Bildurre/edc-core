# edc-motor/core

Núcleo backend de **EdC Motor** (Espadas de Ceniza Motor): un paquete Laravel
reutilizable para construir webs de juegos de mesa de imprimir-y-jugar.
Cada juego instala el motor y programa solo sus entidades (cartas, fichas…);
el motor pone el resto:

- **Auth y roles**: Sanctum, registro con verificación de email localizada,
  reset de contraseña, permisos `manage-game` / `manage-web` / `manage-users`,
  SSO por código de un solo uso entre la web y el admin.
- **Comportamientos de modelo**: publicado/borrador, soft deletes, imagen
  (Spatie MediaLibrary), slug traducible, filtros unificados, i18n de campos.
- **Render a PNG**: cada entidad se fotografía desde la web pública con
  Chromium headless (Browsershot) y queda como media adjunta.
- **PDF recortables**: DomPDF ensambla los PNG en hojas A4 con marcas de
  corte; catálogo de exports por juego + colecciones a la carta de usuarios
  e invitados.
- **CRM de páginas y bloques** con DSL de campos, plantillas y bloque índice.
- **Configuración de la web** (título, logo, fuentes, acento), gestión de
  usuarios, **backup** de BBDD (manual y programado) y monitor de salud.
- **API pública** con SEO: sitemap, robots, nav con jerarquía, descargas.

## Instalación

```bash
composer require edc-motor/core
php artisan vendor:publish --tag=motor-config   # config/motor.php
php artisan migrate
php artisan motor:install                       # roles y permisos base
```

Requiere PHP >= 8.2 y Laravel >= 11. Para el render a PNG hace falta un
Chromium (el de puppeteer o el del sistema vía `MOTOR_CHROME_PATH`).

Las interfaces (web pública y panel de administración) son SPAs Vue 3 que se
montan con los paquetes hermanos [`@edc-motor/ui`](https://www.npmjs.com/package/@edc-motor/ui)
y [`@edc-motor/admin-kit`](https://www.npmjs.com/package/@edc-motor/admin-kit).

## Versionado

Versión *de tren*: `edc-motor/core`, `@edc-motor/ui` y `@edc-motor/admin-kit`
comparten número y se etiquetan juntos. Mientras el motor esté en `0.x`, una
versión menor puede romper API; los cambios se detallan en `CHANGELOG.md`.

## Desarrollo

Este repositorio es un **split de solo lectura** generado desde el monorepo
[`bildurre/boardgame_motor`](https://github.com/bildurre/boardgame_motor)
(directorio `packages/core`). Issues y pull requests, en el monorepo.

## Licencia

[GPL-3.0-only](LICENSE).
