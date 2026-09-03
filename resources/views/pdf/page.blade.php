{{-- PDF de una página del CRM (doc 03 + doc 02): documento de texto maquetado
     con DomPDF, portado del estilo de los PDF de páginas del viejo CDL —
     cuerpo 11pt justificado con márgenes de 2cm, títulos con la fuente de
     TÍTULOS configurada del sitio (jerarquía 21/19/17/15/13pt, sin saltos de
     página tras un título), imágenes flotadas con el texto rodeándolas y la
     cita en la fuente ESPECIAL en cursiva y centrada, como en la web.

     Las fuentes configuradas del sitio (doc 10) viajan embebidas en base64
     (PdfPageAssets); si alguna no tiene fichero TTF/OTF/WOFF utilizable
     (DomPDF no traga WOFF2), esa familia cae a serif/sans del sistema. Los
     bloques con datos del juego (category 'data') imprimen solo su parte
     textual (título/subtítulo/introducción): sus listados viven en la web.
     El HTML rico ya llega saneado (DC-09). --}}
@php
    /** @var \Edc\Core\Pdf\Models\GeneratedPdf $pdf */
    $page = $pdf->source;
    $locale = $pdf->locale;
    // La plantilla de la página viaja como clase del body (tpl-{clave}): las
    // del motor con efecto en papel definen aquí sus reglas (compact-blocks);
    // un juego con plantillas propias puede engancharse publicando la vista
    // (resources/views/vendor/motor/pdf/page.blade.php) y estilando su clase.
    $template = $page->template ?: 'default';
    $registry = app(\Edc\Core\Content\BlockTypeRegistry::class);
    $assets = app(\Edc\Core\Pdf\PdfPageAssets::class);
    $fonts = $assets->fonts();

    // El bloque índice no se imprime: es navegación de la web (sin números
    // de página que ofrecer aquí).
    $blocks = $page->blocks()->printable()->get()
        ->filter(fn ($block) => $registry->has($block->type) && $block->type !== 'index');

    // Profundidad por la cadena de padres DENTRO de lo imprimible (mismo
    // criterio que IndexBlock): el título de un bloque anidado baja un nivel
    // por profundidad — h2 en raíz, h3 a un nivel, etc. (tope h5).
    $byId = $blocks->keyBy('id');
    $depthOf = function ($block) use (&$depthOf, $byId): int {
        $parentId = $block->parent_id;

        return $parentId && $byId->has($parentId) ? 1 + $depthOf($byId->get($parentId)) : 0;
    };

    // Pestañas: los hijos de un bloque `tabs` se imprimen en secuencia tras
    // él, cada uno precedido por el NOMBRE de su pestaña (el hijo n.º N es
    // la pestaña N, contando TODOS los hijos de la página aunque alguno no
    // se imprima) y con sus propios títulos un nivel más abajo.
    $allBlocks = $page->blocks()->orderBy('order')->get();
    $tabLabelOf = function ($block) use ($allBlocks, $registry, $locale): ?string {
        $parent = $block->parent_id ? $allBlocks->firstWhere('id', $block->parent_id) : null;
        if (! $parent || $parent->type !== 'tabs' || ! $registry->has('tabs')) {
            return null;
        }
        $index = $allBlocks->where('parent_id', $parent->id)->values()
            ->search(fn ($sibling) => $sibling->id === $block->id);
        $tabs = $registry->get('tabs')->localizeSettings($parent->settings, $locale)['tabs'] ?? [];
        $label = $tabs[$index]['label'] ?? null;

        return is_string($label) && trim($label) !== '' ? $label : null;
    };

    // Alineaciones (campos comunes): el cuerpo hereda la del bloque (justify
    // por defecto, como el viejo); título/subtítulo con 'inherit' siguen al
    // bloque salvo justificado, que los deja a la izquierda (como la web).
    $styleAttr = fn (?string $align) => $align ? " style=\"text-align: {$align};\"" : '';
    $headingAlign = function (array $s, string $field) {
        $align = $s[$field] ?? 'inherit';
        if ($align === 'inherit') {
            $align = $s['align'] ?? 'justify';
        }

        return in_array($align, ['center', 'right'], true) ? $align : null;
    };
    $bodyAlign = fn (array $s) => in_array($s['align'] ?? 'justify', ['left', 'center', 'right'], true)
        ? $s['align']
        : null;

    // Posición de la imagen de bloque: [lado, ancho %] si flota, o null
    // (arriba/abajo van a todo el ancho). Con 'clear-*' el texto la rodea al
    // 50% (como el viejo); en columnas el ancho sale del reparto configurado,
    // acotado al 25–60% para que la página respire.
    $floatFor = function (array $s): ?array {
        $position = $s['image_position'] ?? 'top';
        if (in_array($position, ['clear-left', 'clear-right'], true)) {
            return [str_ends_with($position, 'left') ? 'left' : 'right', 50];
        }
        if (! in_array($position, ['left', 'right'], true)) {
            return null;
        }
        $parts = array_map('intval', explode(':', ($s['image_columns'] ?? '2:3') ?: '2:3'));
        [$left, $right] = [max(1, $parts[0] ?? 1), max(1, $parts[1] ?? 1)];
        $share = ($position === 'left' ? $left : $right) / ($left + $right);

        return [$position, (int) min(60, max(25, round($share * 100)))];
    };

    $items = $blocks->map(function ($block) use ($registry, $locale, $depthOf, $tabLabelOf) {
        $type = $registry->get($block->type);
        $tabLabel = $tabLabelOf($block);
        $tabDepth = min($depthOf($block), 3);
        $depth = min($depthOf($block) + ($tabLabel !== null ? 1 : 0), 3);

        // Impresión propia del bloque (BlockType::pdfView, p. ej. la lista
        // de contadores de CDL): su parcial recibe los mismos datos que el
        // render público (resolveData).
        $pdfView = $type->pdfView();

        return [
            'type' => $block->type,
            'category' => $type->category,
            's' => $type->localizeSettings($block->settings, $locale),
            'hTitle' => 'h'.min(2 + $depth, 5),
            'hSubtitle' => 'h'.min(3 + $depth, 6),
            'tabLabel' => $tabLabel,
            'hTab' => 'h'.min(2 + $tabDepth, 5),
            'pdfView' => $pdfView,
            'data' => $pdfView !== null ? $type->resolveData($block, $locale) : [],
            'block' => $block,
        ];
    });
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $page->getTranslation('title', $locale) }}</title>
    <style>
        {{-- Fuentes del sitio embebidas (base64). Sin caras utilizables no se
             emite @font-face y la familia cae a la pila de reserva. OJO: se
             declara SIEMPRE format('truetype') — el parser de @font-face de
             DomPDF 3 descarta cualquier otro literal, aunque su FontLib sí
             carga TTF/OTF/WOFF detectándolos por cabecera. --}}
        @foreach ($fonts as $font)
            @foreach ($font['faces'] as $face)
        @font-face {
            font-family: '{{ $font['family'] }}';
            src: url('{{ $face['src'] }}') format('truetype');
            font-weight: {{ $face['weight'] }};
            font-style: {{ $face['style'] }};
        }
            @endforeach
        @endforeach

        * { margin: 0; padding: 0; }

        {{-- Margen de 2cm como padding del body, igual que el viejo: DomPDF
             aplica el estilo de @page al frame raíz y el reset universal lo
             pisaría (margin: 0), así que @page aquí no vale. --}}
        body {
            font-family: 'pdf-body', {!! $fonts['body']['fallback'] !!};
            font-size: 11pt;
            line-height: 1.2;
            color: #000;
            background: #fff;
            padding: 2cm;
            text-align: justify;
        }

        {{-- Jerarquía de títulos (fuente de títulos del sitio): a todo el
             ancho, limpian los floats y no dejan un salto de página justo
             detrás. --}}
        h1, h2, h3, h4, h5, h6 {
            font-family: 'pdf-headings', {!! $fonts['headings']['fallback'] !!};
            clear: both;
            width: 100%;
            display: block;
            page-break-after: avoid;
            page-break-inside: avoid;
        }

        h1 { font-size: 21pt; text-align: center; margin-bottom: 1.5em; }
        h2 { font-size: 19pt; margin: 1em 0 0.5em; }
        h3 { font-size: 17pt; margin: 0.8em 0 0.4em; }
        h4 { font-size: 15pt; margin: 0.8em 0 0.4em; }
        h5 { font-size: 13pt; margin: 0.8em 0 0.4em; }
        h6 { font-size: 12pt; margin: 0.8em 0 0.4em; }

        {{-- Cabeceras de bloque: un punto más grandes que su nivel. --}}
        .block--header h2 { font-size: 21pt; }
        .block--header h3 { font-size: 19pt; }
        .block--header h4 { font-size: 17pt; }
        .block--header h5 { font-size: 15pt; }

        p { margin-bottom: 1em; }

        {{-- El título/subtítulo de un bloque nunca quedan huérfanos al final
             de una página: viajan con el ARRANQUE de su contenido (primer
             elemento) en este contenedor, que DomPDF no parte — los
             page-break-after: avoid de los títulos solos no bastan. El
             after: avoid añade la segunda barrera para los arranques que no
             se agrupan (tabla/lista como primer elemento). --}}
        .block__lead { page-break-inside: avoid; page-break-after: avoid; }

        ul, ol { list-style-position: outside; margin-left: 2em; margin-bottom: 1em; }
        ol { list-style-type: decimal; }
        ul { list-style-type: disc; }
        ul ul, ol ol, ul ol, ol ul { margin-left: 1.5em; margin-bottom: 0; }
        ul ul { list-style-type: circle; }
        ul ul ul { list-style-type: square; }
        li { margin-bottom: 0.3em; }
        li:last-child { margin-bottom: 0; }

        {{-- Tablas del wysiwyg, como las pintaba el viejo (celdas con menos
             aire VERTICAL). La fila de cabeceras viaja en un <thead> real
             (normalizeTables): DomPDF la repite en cada página si la tabla
             cruza de página. --}}
        table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
        td, th { text-align: center; border-bottom: 1px solid grey; vertical-align: middle; padding: 0.3mm 1mm; }
        thead th { border-bottom: 1.5px solid #000; }
        table img { display: inline-block; margin: 0; float: none; width: 11pt; height: 11pt; }

        {{-- Citas: el BLOQUE cita va sobre una banda de fondo gris muy claro
             (suave, imprimible), y su texto en la fuente especial del sitio,
             cursiva y centrada (como la web)... --}}
        .block--quote {
            background-color: #f2f2f2;
            padding: 0.8em 1em 0.1em;
            page-break-inside: avoid;
        }
        .block--quote blockquote {
            font-family: 'pdf-special', {!! $fonts['special']['fallback'] !!};
            font-style: italic;
            font-size: 15pt;
            line-height: 1.4;
            text-align: center;
            margin: 0.5em 2em 1em;
            page-break-inside: avoid;
        }
        .block--quote .quote-author {
            font-family: 'pdf-special', {!! $fonts['special']['fallback'] !!};
            font-style: italic;
            font-size: 13pt;
            margin: -0.5em 2em 1em;
        }
        {{-- ...y la del wysiwyg, discreta con su filete. --}}
        .block__content blockquote {
            margin: 0.5em 2em 1em;
            padding-left: 0.8em;
            border-left: 2pt solid #999;
            font-style: italic;
        }

        {{-- Iconos del juego insertados en el texto: al tamaño de la línea y
             CENTRADOS con ella, como en la web (img.rt-icon a 1.2em con
             vertical-align -0.24em). DomPDF trata 'middle' como 'baseline'
             (el icono queda apoyado en la línea base, o sea desplazado hacia
             arriba), pero sí respeta desplazamientos en pt: -2pt baja un
             icono de 11pt hasta dejar su centro ~0.36em sobre la línea base,
             la misma geometría que el render web (medido sobre el PDF). --}}
        img.rt-icon { width: 11pt !important; height: 11pt !important; display: inline-block; vertical-align: -2pt; }

        .block { margin-bottom: 2em; }
        {{-- Cabecera y bloques de datos (solo su parte textual): cortos,
             enteros de una pieza — su título tampoco se queda huérfano. --}}
        .block--header { margin-bottom: 2em; padding-bottom: 1em; border-bottom: 2px solid #000; page-break-inside: avoid; }
        .block--data { page-break-inside: avoid; }
        {{-- Nombre de la pestaña (contenedor `tabs`) que precede a cada hijo:
             pegado al bloque que titula, nunca huérfano al pie. --}}
        .block--tab-label { margin-bottom: 0.4em; page-break-after: avoid; }

        {{-- Tarjeta de texto: recuadro con borde, levemente más estrecha que
             la columna del cuerpo (margen lateral extra) y con aire interior
             para que el texto no toque el filete. --}}
        .block--text-card {
            border: 1pt solid #666;
            padding: 1em 1.2em 0.2em;
            margin-left: 1.2em;
            margin-right: 1.2em;
        }

        {{-- Imagen del bloque: flotada (el texto la rodea; ancho inline según
             la posición configurada) o a todo el ancho arriba/abajo. --}}
        .block__image { page-break-inside: avoid; }
        .block__image--left { float: left; margin: 0 1em 0.5em 0; }
        .block__image--right { float: right; margin: 0 0 0.5em 1em; }
        .block__image--full { display: block; margin: 0 auto 1em; max-width: 100%; }
        .block__content img { max-width: 100%; }

        .label {
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1pt;
            color: #555;
            margin-bottom: 0.5em;
        }

        .faq-question {
            font-family: 'pdf-headings', {!! $fonts['headings']['fallback'] !!};
            font-size: 13pt;
            margin: 0.8em 0 0.3em;
            page-break-after: avoid;
        }

        .cta-button { margin-bottom: 1em; font-weight: bold; }

        .clear { clear: both; }

        {{-- Plantilla «Bloques compactos» (motor.content.templates:
             compact-blocks): en papel, cada bloque viaja ENTERO — si no cabe
             en lo que queda de página salta completo a la siguiente (un
             bloque más largo que una página entera DomPDF lo parte
             igualmente: no hay dónde meterlo de una pieza) — y toda la
             escala se encoge un punto: cuerpo 10pt, títulos un escalón menos,
             interlineado 1.15 y menos aire entre párrafos, items y bloques.
             En la web esta plantilla no cambia nada (la SPA cae al layout por
             defecto si su registro no la conoce). --}}
        body.tpl-compact-blocks { font-size: 10pt; line-height: 1.15; }
        .tpl-compact-blocks h1 { font-size: 18pt; margin-bottom: 1em; }
        .tpl-compact-blocks h2 { font-size: 16.5pt; margin: 0.7em 0 0.3em; }
        .tpl-compact-blocks h3 { font-size: 15pt; margin: 0.6em 0 0.3em; }
        .tpl-compact-blocks h4 { font-size: 13.5pt; margin: 0.6em 0 0.3em; }
        .tpl-compact-blocks h5 { font-size: 12pt; margin: 0.6em 0 0.3em; }
        .tpl-compact-blocks h6 { font-size: 11pt; margin: 0.6em 0 0.3em; }
        .tpl-compact-blocks .block--header h2 { font-size: 18pt; }
        .tpl-compact-blocks .block--header h3 { font-size: 16.5pt; }
        .tpl-compact-blocks .block--header h4 { font-size: 15pt; }
        .tpl-compact-blocks .block--header h5 { font-size: 13.5pt; }
        .tpl-compact-blocks p { margin-bottom: 0.6em; }
        .tpl-compact-blocks ul, .tpl-compact-blocks ol { margin-bottom: 0.6em; }
        .tpl-compact-blocks li { margin-bottom: 0.15em; }
        .tpl-compact-blocks table { margin-bottom: 0.6em; }
        .tpl-compact-blocks .block--quote blockquote { font-size: 13pt; line-height: 1.25; margin: 0.4em 1.5em 0.7em; }
        .tpl-compact-blocks .block--quote .quote-author { font-size: 11.5pt; margin: -0.4em 1.5em 0.7em; }
        .tpl-compact-blocks .block__content blockquote { margin: 0.4em 1.5em 0.7em; }
        .tpl-compact-blocks .faq-question { font-size: 11.5pt; margin: 0.6em 0 0.2em; }
        .tpl-compact-blocks .label { font-size: 8pt; margin-bottom: 0.35em; }
        .tpl-compact-blocks .cta-button { margin-bottom: 0.6em; }
        .tpl-compact-blocks .block--text-card { padding: 0.7em 0.9em 0.1em; }
        {{-- Iconos y miniaturas de tabla, al nuevo cuerpo de 10pt (el
             desplazamiento baja en proporción). --}}
        .tpl-compact-blocks img.rt-icon { width: 10pt !important; height: 10pt !important; vertical-align: -1.8pt; }
        .tpl-compact-blocks table img { width: 10pt; height: 10pt; }
        {{-- El corazón de la plantilla: el bloque NO se parte entre páginas
             (los pdfView propios de un juego quedan fuera: imprimen su propio
             marcado). Menos aire también entre bloques. --}}
        .tpl-compact-blocks .block { margin-bottom: 1.2em; page-break-inside: avoid; }
        .tpl-compact-blocks .block--header { margin-bottom: 1.2em; padding-bottom: 0.6em; }
    </style>
</head>
<body class="tpl-{{ $template }}">
    {{-- El título de la página, de portada: centrado, con la fuente de títulos. --}}
    <h1>{{ $page->getTranslation('title', $locale) }}</h1>

    @foreach ($items as $item)
        @php $s = $item['s']; @endphp

        @if ($item['tabLabel'] !== null)
            {{-- Nombre de la pestaña que contiene este bloque (contenedor
                 `tabs`): en papel las pestañas van en secuencia. --}}
            <div class="block block--tab-label">
                <{{ $item['hTab'] }}>{{ $item['tabLabel'] }}</{{ $item['hTab'] }}>
            </div>
        @endif

        @if ($item['pdfView'] !== null)
            {{-- Impresión PROPIA del bloque (BlockType::pdfView): el juego
                 manda — el parcial recibe lo mismo que su render público. --}}
            @include($item['pdfView'], [
                'block' => $item['block'],
                's' => $s,
                'data' => $item['data'],
                'locale' => $locale,
                'assets' => $assets,
                'hTitle' => $item['hTitle'],
                'hSubtitle' => $item['hSubtitle'],
                'styleAttr' => $styleAttr,
                'headingAlign' => $headingAlign,
                'bodyAlign' => $bodyAlign,
            ])

        @elseif ($item['type'] === 'header')
            @continue(blank($s['title'] ?? null) && blank($s['subtitle'] ?? null))
            <div class="block block--header">
                @if (! blank($s['title'] ?? null))
                    <{{ $item['hTitle'] }}{!! $styleAttr($headingAlign($s, 'title_align')) !!}>{{ $s['title'] }}</{{ $item['hTitle'] }}>
                @endif
                @if (! blank($s['subtitle'] ?? null))
                    <{{ $item['hSubtitle'] }}{!! $styleAttr($headingAlign($s, 'subtitle_align')) !!}>{{ $s['subtitle'] }}</{{ $item['hSubtitle'] }}>
                @endif
            </div>

        @elseif ($item['type'] === 'quote')
            <div class="block block--quote">
                @if (! blank($s['title'] ?? null))
                    <{{ $item['hTitle'] }}{!! $styleAttr($headingAlign($s, 'title_align')) !!}>{{ $s['title'] }}</{{ $item['hTitle'] }}>
                @endif
                @if (! blank($s['subtitle'] ?? null))
                    <{{ $item['hSubtitle'] }}{!! $styleAttr($headingAlign($s, 'subtitle_align')) !!}>{{ $s['subtitle'] }}</{{ $item['hSubtitle'] }}>
                @endif
                @if (! blank($s['quote'] ?? null))
                    <blockquote>{!! $assets->printableHtml($s['quote']) !!}</blockquote>
                @endif
                @if (! blank($s['author'] ?? null))
                    <div class="quote-author" style="text-align: {{ in_array($s['author_align'] ?? 'right', ['left', 'center', 'right'], true) ? $s['author_align'] : 'right' }};">— {{ $s['author'] }}</div>
                @endif
            </div>

        @elseif ($item['type'] === 'faq')
            {{-- El título/subtítulo viajan con la PRIMERA pregunta-respuesta
                 (block__lead): nunca huérfanos al final de página. --}}
            <div class="block block--faq">
                @foreach ($s['items'] ?? [] as $faq)
                    @if ($loop->first)
                        <div class="block__lead">
                        @if (! blank($s['title'] ?? null))
                            <{{ $item['hTitle'] }}{!! $styleAttr($headingAlign($s, 'title_align')) !!}>{{ $s['title'] }}</{{ $item['hTitle'] }}>
                        @endif
                        @if (! blank($s['subtitle'] ?? null))
                            <{{ $item['hSubtitle'] }}{!! $styleAttr($headingAlign($s, 'subtitle_align')) !!}>{{ $s['subtitle'] }}</{{ $item['hSubtitle'] }}>
                        @endif
                    @endif
                    @if (! blank($faq['question'] ?? null))
                        <div class="faq-question">{{ $faq['question'] }}</div>
                    @endif
                    @if (! blank($faq['answer'] ?? null))
                        <div{!! $styleAttr($bodyAlign($s)) !!}>{!! $assets->printableHtml($faq['answer']) !!}</div>
                    @endif
                    @if ($loop->first)
                        </div>
                    @endif
                @endforeach
                @if (($s['items'] ?? []) === [])
                    <div class="block__lead">
                        @if (! blank($s['title'] ?? null))
                            <{{ $item['hTitle'] }}{!! $styleAttr($headingAlign($s, 'title_align')) !!}>{{ $s['title'] }}</{{ $item['hTitle'] }}>
                        @endif
                        @if (! blank($s['subtitle'] ?? null))
                            <{{ $item['hSubtitle'] }}{!! $styleAttr($headingAlign($s, 'subtitle_align')) !!}>{{ $s['subtitle'] }}</{{ $item['hSubtitle'] }}>
                        @endif
                    </div>
                @endif
            </div>

        @elseif (in_array($item['type'], ['text', 'text-card', 'cta'], true) || $item['category'] !== 'data')
            {{-- Texto (y variantes con cuerpo): tarjeta de texto, llamada a la
                 acción y cualquier tipo de presentación de un juego que siga
                 el contrato title/subtitle/body(+image). --}}
            @php
                $body = $assets->printableHtml(is_string($s['body'] ?? null) ? $s['body'] : '');
                [$leading, $rest] = $assets->splitLeadingHeadings($body);
                // El ARRANQUE del cuerpo (primer elemento) viaja con el
                // título en block__lead (nunca huérfanos); el resto fluye.
                [$firstChunk, $tail] = $assets->splitFirstElement($rest);
                $image = $assets->imageDataUri($s['image'] ?? null);
                $float = $image ? $floatFor($s) : null;
                $position = $s['image_position'] ?? 'top';
            @endphp
            @continue(blank($s['title'] ?? null) && blank($s['subtitle'] ?? null) && trim($body) === '' && ! $image && blank($s['button_text'] ?? null))
            <div class="block block--{{ $item['type'] }}">
                <div class="block__lead">
                    @if (! blank($s['label'] ?? null))
                        <div class="label"{!! $styleAttr(in_array($s['label_align'] ?? 'left', ['center', 'right'], true) ? $s['label_align'] : null) !!}>{{ $s['label'] }}</div>
                    @endif
                    @if (! blank($s['title'] ?? null))
                        <{{ $item['hTitle'] }}{!! $styleAttr($headingAlign($s, 'title_align')) !!}>{{ $s['title'] }}</{{ $item['hTitle'] }}>
                    @endif
                    @if (! blank($s['subtitle'] ?? null))
                        <{{ $item['hSubtitle'] }}{!! $styleAttr($headingAlign($s, 'subtitle_align')) !!}>{{ $s['subtitle'] }}</{{ $item['hSubtitle'] }}>
                    @endif
                    {{-- Títulos iniciales del wysiwyg, ANTES de flotar la
                         imagen: a todo el ancho, sin empujarla (del viejo). --}}
                    {!! $leading !!}
                    <div class="block__content">
                        @if ($image && $float)
                            <img class="block__image block__image--{{ $float[0] }}" style="width: {{ $float[1] }}%;" src="{{ $image }}" alt="">
                        @elseif ($image && $position !== 'bottom')
                            <img class="block__image block__image--full" src="{{ $image }}" alt="">
                        @endif
                        @if (trim($firstChunk) !== '')
                            <div{!! $styleAttr($bodyAlign($s)) !!}>{!! $firstChunk !!}</div>
                        @endif
                    </div>
                </div>
                <div class="block__content">
                    @if (trim($tail) !== '')
                        <div{!! $styleAttr($bodyAlign($s)) !!}>{!! $tail !!}</div>
                    @endif
                    @if ($image && ! $float && $position === 'bottom')
                        <img class="block__image block__image--full" src="{{ $image }}" alt="">
                    @endif
                    <div class="clear"></div>
                </div>
                @if ($item['type'] === 'cta' && ! blank($s['button_text'] ?? null))
                    <div class="cta-button"{!! $styleAttr(in_array($s['button_align'] ?? 'left', ['center', 'right'], true) ? $s['button_align'] : null) !!}>
                        @if (is_string($s['button_url'] ?? null) && preg_match('#^https?://#', $s['button_url']))
                            <a href="{{ $s['button_url'] }}">{{ $s['button_text'] }}</a>
                        @else
                            {{ $s['button_text'] }}
                        @endif
                    </div>
                @endif
            </div>

        @else
            {{-- Bloques con DATOS del juego (counters-list, related, índices,
                 descargas…): al papel va solo su parte textual — el listado es
                 de la web (el viejo hacía lo propio: omitía estos bloques). --}}
            @php $intro = $assets->printableHtml(is_string($s['intro'] ?? null) ? $s['intro'] : ''); @endphp
            @continue(blank($s['title'] ?? null) && blank($s['subtitle'] ?? null) && trim($intro) === '')
            <div class="block block--data">
                @if (! blank($s['title'] ?? null))
                    <{{ $item['hTitle'] }}{!! $styleAttr($headingAlign($s, 'title_align')) !!}>{{ $s['title'] }}</{{ $item['hTitle'] }}>
                @endif
                @if (! blank($s['subtitle'] ?? null))
                    <{{ $item['hSubtitle'] }}{!! $styleAttr($headingAlign($s, 'subtitle_align')) !!}>{{ $s['subtitle'] }}</{{ $item['hSubtitle'] }}>
                @endif
                @if (trim($intro) !== '')
                    <div{!! $styleAttr($bodyAlign($s)) !!}>{!! $intro !!}</div>
                @endif
            </div>
        @endif
    @endforeach
</body>
</html>
