# Changelog — edc-motor/core

Backend Laravel reutilizable del motor. Versión de tren con `@edc-motor/ui` y
`@edc-motor/admin-kit` (tag `vX.Y.Z` en el monorepo).

## [Sin publicar]

### Añadido

- **`php artisan motor:rewrite-urls {from} {to} [--dry-run]`**: sustituye un
  origen de URL por otro en TODO el contenido de la base de datos (texto,
  bloques y configuración; JSON plano y con barras escapadas). Para después
  de importar la BD de otro entorno, cuyas imágenes e iconos quedan con las
  URL absolutas del origen (p. ej. `http://localhost:8010/storage/...`).

## [0.5.45] — 2026-09-04

- Sin cambios propios: versión de tren.

## [0.5.44] — 2026-09-03

- Sin cambios propios: versión de tren.

## [0.5.43] — 2026-09-03

### Añadido

- **`Field::visibleWhen($campo, $valores)`**: visibilidad condicional en el
  formulario del admin (p. ej. «Origen» solo con entidad = mazos). Viaja en
  el esquema como `visible_when: {field, values}`; validar y guardar siguen
  igual.

### Cambiado

- **El campo `icon` del DSL guarda un icono lucide** (nombre kebab-case,
  p. ej. `layout-grid`) del catálogo curado del motor, en vez de la URL de
  un icono del juego. Las pestañas (`tabs`) lo usan así; el icono va ahora a
  todo el ancho de su fila del repetidor.

## [0.5.42] — 2026-09-03

### Añadido

- **Bloque «Pestañas»** (`tabs`), el primer bloque CONTENEDOR del motor:
  título, subtítulo y un repetidor de pestañas (texto traducible, icono de
  la biblioteca del juego y ancla opcional para enlazar). Su contenido son
  sus bloques HIJOS (`parent_id`): el hijo n.º N es la pestaña N, con sus
  propios descendientes dentro; vale cualquier bloque, también los de datos
  del juego (un índice de entidad por pestaña).
- **Campo `icon` del DSL** (`Field::make('icon', 'icon')`): el admin lo
  elige por nombre entre los iconos del juego y guarda su URL.
- **`parent_id` en el payload público** de cada bloque (`GET /pages/{slug}`):
  el front agrupa en los contenedores a sus descendientes; el resto sigue en
  flujo.

### Cambiado

- **PDF de página con pestañas**: los hijos de un bloque `tabs` se imprimen
  en secuencia tras él, cada uno precedido por el nombre de su pestaña y con
  sus propios títulos un nivel más abajo.

**Migración del cascarón**: la página pública pinta sus bloques con
`BlockFlow` de `@edc-motor/ui` (ver su CHANGELOG) y el single de página del
admin pasa las etiquetas `tab` / `tabsMissing` / `tabsExtra` a `PageBlocks`
(claves i18n `pages.blocks.*`; ver `plantilla/`).

## [0.5.41] — 2026-09-03

- Sin cambios propios: versión de tren.

## [0.5.40] — 2026-09-03

- Sin cambios propios: versión de tren.

## [0.5.39] — 2026-09-02

- Sin cambios propios: versión de tren.

## [0.5.38] — 2026-09-02

- Sin cambios propios: versión de tren.

## [0.5.37] — 2026-09-02

- Sin cambios propios: versión de tren.

## [0.5.36] — 2026-09-02

### Revertido

- **Configuración del sitio: vuelve el acento fijo o ALEATORIO de 0.5.34**
  (`accent_mode`, `accent_color`, `accent_colors`); desaparece
  `accent_2_color` y el preset de fondo `token:accent-2-soft` (lo guardado
  con él deja de validar; el preset del acento vuelve a llamarse «Acento»).
  Se revierten la paleta de 18 tonos de 0.5.35 y el color del juego
  (`game_color`) de 0.5.36.

## [0.5.36] — 2026-09-02

### Cambiado

- **Configuración del sitio: un solo color, el del juego.** `game_color`
  (acento 3, por defecto `#0b936b`) sustituye a `accent_color` y
  `accent_2_color`: marca (índigo) y acción (coral) son fijos de la IP en el
  tema del ui. Las claves antiguas se ignoran y `update()` las retira.
- **Fondo de bloque:** presets «Marca», «Acción» y nuevo «Juego»
  (`token:accent-3-soft`).

## [0.5.35] — 2026-09-02

### Cambiado

- **Configuración del sitio: dos acentos fijos.** `accent_color` (acento 1,
  marca) y nuevo `accent_2_color` (acento 2, acción), ambos hex; por defecto
  el violeta y el naranja de la paleta del tema del ui (`#955dcd`, `#b26900`).
  Desaparecen el modo aleatorio y sus claves (`accent_mode`, `accent_colors`):
  la validación ya no las acepta y `update()` las retira de lo guardado.
- **Fondo de bloque:** preset `token:accent-2-soft` («Acento 2») junto al
  del acento 1, que pasa a llamarse «Acento 1».

## [0.5.34] — 2026-09-02

- Sin cambios propios: versión de tren.

## [0.5.33] — 2026-09-02

- Sin cambios propios: versión de tren.

## [0.5.32] — 2026-09-02

- Sin cambios propios: versión de tren.

## [0.5.31] — 2026-09-02

- Sin cambios propios: versión de tren.

## [0.5.30] — 2026-09-02

### Cambiado

- **El storage en la copia AUTOMÁTICA es un ajuste del admin**:
  `BackupSettings` gana `include_media` (base: `motor.backup.include_media`),
  `PUT /admin/backups/schedule` lo acepta y `MotorBackup::applyConfig()` sin
  argumento lo lee de ahí (es la config que usa el `backup:run` del
  scheduler). La copia MANUAL decide por su cuenta en cada copia:
  `RunBackupJob` reaplica la config con su `include_media` explícito cuando
  difiere del de la automática (antes solo reaplicaba al pedir storage, y
  una automática con storage lo colaba en la manual sin marcar).
- **La copia con storage lleva SOLO los originales**: `applyConfig()`
  excluye `previews/` y `pdfs/` (se regeneran desde el admin y son el
  grueso del peso) y guarda las entradas del zip relativas al proyecto
  (`relative_path = base_path()`), restaurables en cualquier instalación.
- **Restaurar devuelve el storage**: si el zip trae ficheros bajo
  `storage/app/public/` (relativos, o absolutos de copias viejas),
  `BackupRestorer` los escribe en el disco público del motor pisando lo que
  haya (rutas normalizadas, nada de `..`); la respuesta del restore expone
  `restored_files`. Las previews y los PDF se regeneran después.
- **La copia manual con storage ya lleva el storage también en el
  worker**: `RunBackupJob` (y el scheduler) ejecutan la copia con
  `MotorBackup::run()`, que construye el job de spatie de un `Config`
  fresco a partir de `config('backup')`. `backup:run` recibe su `Config`
  inyectado al construirse el comando — en el boot del kernel de consola —
  así que en un worker de cola de larga vida ignoraba la config reaplicada
  por el job y la manual «con storage» salía solo con el dump (en la
  suite, con cola sync, pasaba porque el comando se construía después).
- **Copias grandes**: `motor.backup.upload_max_mb` pasa de 500 a 1024 (con
  los originales del storage dentro una copia ronda los cientos de MB;
  nginx y PHP tienen que ir a la par), `GET /admin/backups` expone `upload_max_mb` para que la vista valide
  con el tope real, y el timeout de `RunBackupJob` sube a una hora.

## [0.5.29] — 2026-09-01

- Sin cambios propios: versión de tren.

## [0.5.28] — 2026-09-01

### Cambiado

- **Las copias de seguridad van SOLO con la base de datos por defecto**
  (`motor.backup.include_media` pasa a `false`): el storage pesa demasiado
  para copiarlo en cada automática y para subirlo al restaurar. La copia
  MANUAL puede pedirlo puntualmente: `POST /admin/backups` acepta
  `include_media` y `RunBackupJob` reaplica la config de spatie con el
  storage solo para esa ejecución (olvidando el `Config` *scoped* de
  spatie v10, que congela `config('backup')` en su primera resolución).

## [0.5.27] — 2026-08-31

- Sin cambios propios: versión de tren.

## [0.5.26] — 2026-08-30

- Sin cambios propios: versión de tren.

## [0.5.25] — 2026-08-29

- Sin cambios propios: versión de tren.

## [0.5.24] — 2026-08-29

### Cambiado

- **Las imágenes de la rejilla van a DomPDF a resolución de impresión**
  (`PrintImageOptimizer`, nuevo): antes de componer, cada imagen local se
  reescala a `motor.pdf.print_dpi` (300 por defecto, el estándar de
  imprenta; 0 desactiva) y se aplana a JPEG sobre blanco (el papel). La
  ruta del canal alfa de los PNG en DomPDF se procesa píxel a píxel en
  PHP: una página 3x3 de previews de carta (PNG alfa a 1500x2100) tardaba
  ~11,5 s — con JPEG a 300 dpi, ~0,1 s (x100). Era lo que ponía los PDF
  recortables en ~5 minutos por documento (y contra el timeout del job).
  Nunca se amplía, los data-URIs/URLs pasan tal cual y cualquier fallo
  devuelve la imagen original.

## [0.5.23] — 2026-08-29

### Cambiado

- **Rejilla de impresión (`pdf/grid.blade.php`): marcas de corte
  conscientes del hueco** — cuando el `gap` del layout es menor que la
  marca (piezas casi pegadas, p. ej. ~1px para cortar por la línea
  compartida), una marca hacia la pieza vecina se pintaría ENCIMA de su
  imagen: ahora solo se dibujan las que caen en espacio libre (bordes de
  página o hueco sin vecino). Con hueco holgado, todas las de siempre.

## [0.5.22] — 2026-08-29

- Sin cambios propios: versión de tren.

## [0.5.21] — 2026-08-29

- Sin cambios propios: versión de tren.

## [0.5.20] — 2026-08-29

- Sin cambios propios: versión de tren.

## [0.5.19] — 2026-08-26

- Sin cambios propios: versión de tren.

## [0.5.18] — 2026-08-25

- Sin cambios propios: versión de tren.

## [0.5.17] — 2026-08-24

### Añadido

- **Plantilla de página «Bloques compactos»** (`compact-blocks` en
  `motor.content.templates`): pensada para imprimir. En el PDF de páginas
  cada bloque viaja ENTERO (`page-break-inside: avoid` por bloque: el que
  no cabe en lo que queda de página salta completo a la siguiente; un
  bloque más largo que una página entera DomPDF lo parte igualmente) y
  toda la escala tipográfica se encoge: cuerpo 10pt (antes 11pt), títulos
  un escalón menos (18/16.5/15/13.5/12/11pt), interlineado 1.15 y menos
  aire entre párrafos (0.6em), items de lista (0.15em) y bloques (1.2em).
  En la web no cambia nada: sin entrada en el `templateRegistry` de la
  SPA cae al layout por defecto. La clave de la plantilla viaja ahora
  como clase del `body` del PDF (`tpl-{clave}`) para CUALQUIER plantilla:
  un juego con plantillas propias puede publicar la vista
  (`resources/views/vendor/motor/pdf/page.blade.php`) y estilar su clase.
  OJO: un config `motor.php` publicado que redefina `content.templates`
  pisa el catálogo del paquete — debe listar también las plantillas del
  motor que quiera ofrecer.

- **Nombre LEGIBLE de los PDF generados** (`PdfExportContract::displayName`,
  con implementación por defecto en `PdfExport`): para exports con
  entidad dueña sale de su nombre/título traducible en el locale del
  PDF; los exports globales declaran etiquetas por idioma con el nuevo
  gancho protegido `labels()` (`['es' => 'Contadores recortables', ...]`);
  y sin nada de eso cae al filename embellecido (sin sufijo de idioma,
  guiones a espacios, mayúscula inicial). `GeneratedPdf::displayName()`
  lo resuelve desde el registro; los PDF de usuario (type `collection`)
  y los de exports desregistrados conservan su `filename`.
  `GET /api/downloads` añade la clave `title` a cada ítem (el `filename`
  de la BD no cambia) y `PdfController::download` emite el
  `Content-Disposition` (inline y attachment) con ese nombre + `.pdf`:
  `filename*` UTF-8 (RFC 5987) y fallback ASCII transliterado, así la
  pestaña del navegador se titula «Catálogo de héroes.pdf». Las
  descargas de las colecciones temporales de usuario no cambian.
- **Impresión propia por bloque en el PDF de páginas**
  (`BlockType::pdfView()`, opcional): un bloque de datos puede aportar
  su parcial Blade de PDF; `motor::pdf.page` lo incluye con los mismos
  datos que el render público (`resolveData`), los settings localizados,
  el locale, `PdfPageAssets` y los helpers de la plantilla. Sin
  declararlo, todo sigue igual (los bloques de datos imprimen solo su
  parte textual).
- `PdfPageAssets::printableHtml()` (imágenes embebidas + tablas
  normalizadas), `normalizeTables()` y `splitFirstElement()` para la
  plantilla del PDF de páginas.

### Arreglado

- **Los iconos del wysiwyg salen centrados con la línea en el PDF de
  páginas**: DomPDF trata `vertical-align: middle` como `baseline` (el
  icono quedaba apoyado en la línea base y sobresalía por arriba); ahora
  `img.rt-icon` baja con `vertical-align: -2pt` (−1.8pt en la plantilla
  compacta, icono de 10pt), que deja su centro ~0.36em sobre la línea
  base — la misma geometría que el render web (1.2em con
  `vertical-align: -0.24em`), medido sobre el PDF rasterizado.

### Cambiado

- **El PDF de páginas del CRM no deja títulos huérfanos**: el
  título/subtítulo de cada bloque viaja con el ARRANQUE de su contenido
  (primer elemento del cuerpo; la primera pregunta-respuesta en el FAQ)
  en un contenedor `page-break-inside: avoid` (`.block__lead`, con
  `page-break-after: avoid` de segunda barrera para arranques altos —
  tabla/lista — que no se agrupan); cabecera, cita y bloques de datos
  van enteros de una pieza. Verificado con DomPDF real en el corte de
  página: título y arranque saltan juntos.
- **Cita y tarjeta de texto en el papel**: el bloque `quote` se imprime
  sobre una banda de fondo gris muy claro (`#f2f2f2`) y el
  `text-card` como recuadro con borde (`1pt #666`) levemente más
  estrecho que la columna del cuerpo.
- **Tablas del wysiwyg**: menos aire VERTICAL en las celdas
  (`padding: 0.3mm 1mm`) y la primera fila de `<th>` se mueve a un
  `<thead>` real al componer (TipTap la emite dentro del tbody): DomPDF
  repite las cabeceras en cada página cuando la tabla cruza de página
  (con su filete inferior más marcado).

## [0.5.16] — 2026-08-24

- Sin cambios propios: versión de tren.

## [0.5.15] — 2026-08-24

### Cambiado

- **El PDF de páginas del CRM estrena el estilo maquetado del viejo CDL**
  (`motor::pdf.page` + `PdfPageAssets`, nuevo): cuerpo 11pt justificado
  con márgenes de 2cm, jerarquía de títulos 21/19/17/15/13pt sin saltos
  de página tras un título (las cabeceras de bloque, un punto más
  grandes y con su filete inferior), imágenes de bloque flotadas con el
  texto rodeándolas según `image_position` (en columnas, el ancho sale
  del reparto configurado; los títulos iniciales del wysiwyg se extraen
  antes del float para que queden a todo el ancho), la cita del bloque
  `quote` en cursiva y centrada con la fuente ESPECIAL, listas, tablas e
  iconos `rt-icon` del wysiwyg al tamaño de la línea. Las TRES fuentes
  configuradas del sitio (títulos/cuerpo/especial, doc 10) viajan
  embebidas en base64: DomPDF no traga WOFF2, así que por cada fichero
  configurado se busca un hermano `.ttf/.otf/.woff` con el mismo nombre
  (en `public/fonts` del API o en `fonts/` del disco del motor; a las
  familias sin negrita o cursiva propias se les alian las caras que
  falten) y, si no hay ninguno utilizable, la familia cae con elegancia
  a serif/sans del sistema. Los títulos de bloques ANIDADOS bajan un
  nivel por profundidad (h2 → h3 → …), los bloques con DATOS del juego
  imprimen solo su parte textual (título/subtítulo/introducción) y el
  bloque índice sigue fuera del papel.

## [0.5.14] — 2026-08-03

### Añadido

- **El índice de la colección expone los PDF temporales vigentes**
  (`GET /api/pdf-collection`, `PdfCollectionController::index()`): clave
  nueva `generated` — los `GeneratedPdf` tipo `collection` del dueño
  actual (usuario o token de invitado) NO caducados, más recientes
  primero, con `{id, status, filename, locale, url, size, generated_at,
  expires_at}`. Los `ready` mantienen el enlace de descarga tras recargar
  la página y un `pending` permite a la SPA retomar el sondeo; los
  `failed` no salen. `data` (los items) no cambia.

## [0.5.13] — 2026-08-03

### Cambiado

- **Solo dos velos: 60 y 80 %** (`BlockType::commonFields()`): los presets
  del campo `background` quedan en `token:veil-60`, `token:veil-80`
  (nuevo) y `token:accent-soft`; `token:veil-15/-30/-85` pasan a
  `legacyValues` (siguen validando y renderizando).

## [0.5.12] — 2026-08-03

- Sin cambios propios: versión de tren.

## [0.5.11] — 2026-08-03

### Cambiado

- **Los presets del campo común `background` pasan a los VELOS del fondo
  de página** (`BlockType::commonFields()`): «Velo 15 %»
  (`token:veil-15`), «Velo 30 %» (`token:veil-30`), «Velo 60 %»
  (`token:veil-60`) y «Velo 85 %» (`token:veil-85`) — el color de fondo
  de página del tema (`--bg`) a esa opacidad (custom properties de
  `_theme.scss` en `@edc-motor/ui`): sobre la imagen de fondo ennegrecen
  en oscuro y emblanquecen en claro — más el «Acento» translúcido
  (`token:accent-soft`), que se mantiene. Los grises neutros de 0.5.10
  (`token:neutral-soft`, `token:neutral`, `token:neutral-strong`) se
  retiran del picker pero siguen validando (`->legacyValues()`, junto a
  los `token:surface*`/`token:accent-500` de 0.5.8) y renderizando.

## [0.5.10] — 2026-08-03

### Añadido

- **DSL de campos: modificador `->legacyValues()`** — valores RETIRADOS de
  un campo `color` con presets: no viajan en la serialización (el picker
  ya no los ofrece) pero la validación los sigue aceptando, para que lo
  guardado cuando eran preset no se rompa.
- **Descarga de PDF en línea** — `GET /api/pdfs/{id}/download?inline=1`
  responde `Content-Disposition: inline`: el navegador abre el PDF en la
  pestaña en vez de descargarlo. Sin el flag, `attachment` como siempre;
  mismas reglas de acceso (permanentes públicos, temporales solo dueño/
  admin).

### Cambiado

- **Los presets del campo común `background` pasan a los grises
  translúcidos por grados** (`BlockType::commonFields()`): «Gris suave»
  (`token:neutral-soft`), «Gris» (`token:neutral`), «Gris fuerte»
  (`token:neutral-strong`) y «Acento» (`token:accent-soft`) — custom
  properties translúcidas del tema (`_theme.scss` de `@edc-motor/ui`) con
  grado fijo por token y por tema; el medio calca al «Gris» estático de la
  paleta. Los presets opacos de 0.5.8 (`token:surface`, `token:surface-2`,
  `token:surface-3`, `token:accent-500`) se retiran del picker pero siguen
  validando (`->legacyValues()`) y renderizando.

## [0.5.9] — 2026-08-02

- Sin cambios propios: versión de tren.

## [0.5.8] — 2026-08-02

### Añadido

- **DSL de campos: modificador `->options()`** — encadenable en cualquier
  campo (valor => etiqueta). En un campo `color` son los presets DINÁMICOS
  del tema: valores `token:<nombre>` que el front resuelve a la custom
  property `var(--<nombre>)` del tema activo. Viajan en la serialización
  del esquema (`options`, como en un select) y la validación del color se
  amplía: con presets declarados se admite un hex (`#rgb[a]`/`#rrggbb[aa]`,
  retrocompatible con lo ya guardado) o uno de SUS valores — un `token:*`
  desconocido es 422. Sin options, el color valida como siempre (string
  libre ≤ 32).

### Cambiado

- **El campo común `background` declara presets dinámicos del tema**
  (`BlockType::commonFields()`): «Fondo de tarjeta» (`token:surface`),
  «Superficie 2» (`token:surface-2`), «Superficie 3» (`token:surface-3`) y
  «Acento» (`token:accent-500`) — la escala de superficies + el acento. El
  valor se guarda SEMÁNTICO y estable (`token:*`, nunca el `var(...)`
  crudo); las etiquetas siguen el camino de las de un select
  (castellano aquí, `blockOptions.background.*` en el admin).

## [0.5.7] — 2026-08-01

- Sin cambios propios: versión de tren.

## [0.5.6] — 2026-07-31

- Sin cambios propios: versión de tren.

## [0.5.5] — 2026-07-31

- Sin cambios propios: versión de tren.

## [0.5.4] — 2026-07-31

### Añadido

- **DSL de campos: `Field::row('nombre')`** — modificador encadenable de
  PRESENTACIÓN: los campos de un bloque que declaren el mismo nombre de
  fila se pintan juntos en el formulario del admin (columnas iguales
  mientras quepan; apilan en angosto — lo resuelve `SchemaFields` del
  admin-kit). Viaja en la serialización del esquema (`row`); ni la
  validación ni el guardado cambian. Un juego puede usarlo igual en sus
  propios bloques.

### Cambiado

- **`RelatedBlock` y `CtaBlock` estrenan las filas declaradas**: en
  `related`, Entidad+Modo y Con botón+Texto del botón comparten fila; en
  `cta`, Texto del botón+Enlace del botón van juntos y Botón
  grande+Alineación+Estilo del botón forman la segunda fila (con ello
  `button_align` deja de emparejarse por convención con `button_text`).

## [0.5.3] — 2026-07-30

- Sin cambios propios: versión de tren.

## [0.5.2] — 2026-07-30

- **Previews PNG sobre el host de la petición** (`HasPreviewImage::previewUrl`,
  y con él `previewUrls()` y los ítems de catálogo): la URL del disco de
  previews se construye con APP_URL, que puede no coincidir con el host/puerto
  real por el que llega la petición, y los PNG salían inaccesibles en los
  catálogos públicos (índices de cartas/héroes y las listas de las fichas de
  facción/mazo, también dentro de los bloques del CRM). Ahora pasa por el
  mismo arreglo que ya tenía `HasImage::imageUrl()`.
- Nuevo helper `Edc\Core\Support\PublicUrl::onRequestHost(?string $url)`:
  reconstruye cualquier URL absoluta de fichero sobre el host de la petición
  actual (en CLI recae en APP_URL). `HasImage::imageUrl()` y
  `HasPreviewImage::previewUrl()` lo comparten; cualquier juego puede
  reutilizarlo para campos de imagen propios.

## [0.5.1] — 2026-07-30

- Sin cambios propios: versión de tren.

## [0.5.0] — 2026-07-30

- Sin cambios propios: versión de tren.

## [0.4.39] — 2026-07-29

- Sin cambios propios: versión de tren.

## [0.4.38] — 2026-07-28

- Sin cambios propios: versión de tren.

## [0.4.37] — 2026-07-27

- Sin cambios propios: versión de tren.

## [0.4.36] — 2026-07-26

- Sin cambios propios: versión de tren.

## [0.4.35] — 2026-07-26

- Sin cambios propios: versión de tren.

## [0.4.34] — 2026-07-26

- Sin cambios propios: versión de tren.

## [0.4.33] — 2026-07-26

### Cambiado

- **Búsqueda y orden "a lo humano": sin distinguir mayúsculas NI acentos**
  (`SqlFold` nuevo + `HasFilters` + listado de usuarios): "CaMiON"
  encuentra "Camión" y "nu" encuentra "Ñu". Los valores JSON traducibles
  comparan en binario (collation utf8mb4_bin) y el lower() de SQLite solo
  baja ASCII, así que el plegado se construye explícito en SQL (lower +
  cadena de replace: á é í ó ú ü ñ, en ambas cajas) y se aplica a la
  columna Y al término. `SqlFold::expression()`/`::term()` quedan públicos
  para que los juegos plieguen igual sus propios ORDER BY alfabéticos
  (el `SortsIndex` del juego). El listado de usuarios busca y ordena
  plegado también. Tests en el playground.

## [0.4.32] — 2026-07-26

- Sin cambios propios: versión de tren.

## [0.4.31] — 2026-07-25

- Sin cambios propios: versión de tren.

## [0.4.30] — 2026-07-25

### Añadido

- **`index_backgrounds` en los site settings: fondos para las vistas
  índice del app pública** (`SiteSettings` + `SiteSettingsController`):
  mapa `{clave: URL|null}` — las claves las define cada JUEGO (p. ej.
  `cards`, `downloads`, `life-counter`…). Validación `sometimes array`
  (máx. 24 claves) con URL de hasta 2048 caracteres por valor; por
  defecto mapa vacío. Viaja en GET /api/site como el resto de ajustes y
  se guarda/borra desde el admin del juego con el patrón de subida
  diferida del favicon (uploads gestionados de `content/`). La SPA lo
  pinta con el `PageBackground` de `@edc-motor/ui`, igual que el fondo
  de una página del CRM.

## [0.4.29] — 2026-07-24

- Sin cambios propios: versión de tren.

## [0.4.28] — 2026-07-22

### Cambiado

- **`HtmlSanitizer`: tablas, `h5` e imágenes con tamaño**. La lista blanca
  gana `table`, `thead`, `tbody`, `tr` (clase), `th`/`td` (`colspan`,
  `rowspan`, clase) — el normalizador de nodos las trata como cualquier
  otra etiqueta permitida (recursión + filtrado de atributos), así que
  conserva la anidación real de la tabla en vez de aplanar `tr`/`td` como
  hacía antes con cualquier etiqueta desconocida. También `h5` (llegaba
  hasta `h4`) y `width`/`height` en `img` (para dimensionar imágenes del
  richtext — el `style` SIGUE fuera de la lista blanca a propósito: para
  dimensionar imágenes en el wysiwyg se usan los atributos `width`/
  `height`, no CSS en línea). Tests Pest nuevos
  (`tests/Feature/HtmlSanitizerTest.php`): tabla completa con clases y
  `colspan` sobrevive, `style` se elimina, `<script>`/`onclick` fuera,
  una etiqueta no permitida DENTRO de una tabla se limpia sin romper su
  estructura, `h5` permitido y listas anidadas conservadas.

## [0.4.27] — 2026-07-21

### Arreglado

- **`PageRenderer`: los bloques ya NO se agrupan por nivel** — el render
  público reordenaba los bloques (padre + sus hijos directos, código de la
  era "un solo nivel") en vez de pintarlos tal cual vienen en `order`; con
  el anidado multinivel de 0.4.25 eso dejaba nietos y bisnietos
  desplazados al FINAL de la página, fuera de sitio respecto al índice.
  Ahora `PageRenderer::build()` confía en `order` sin reordenar — mismo
  criterio que `IndexBlock::resolveData` (el admin persiste el preorden
  completo del árbol) —: que un bloque sea hijo de otro afecta SOLO a la
  numeración/sangría del índice, nunca al orden visual. El PDF de página
  no compartía el bug.

## [0.4.26] — 2026-07-21

### Cambiado

- **`TextCardBlock`: alineación de la etiqueta**: nuevo select `label_align`
  (izquierda/centrado/derecha, por defecto izquierda).

## [0.4.25] — 2026-07-20

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
