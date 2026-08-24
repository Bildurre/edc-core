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

    $items = $blocks->map(function ($block) use ($registry, $locale, $depthOf) {
        $type = $registry->get($block->type);
        $depth = min($depthOf($block), 3);

        return [
            'type' => $block->type,
            'category' => $type->category,
            's' => $type->localizeSettings($block->settings, $locale),
            'hTitle' => 'h'.min(2 + $depth, 5),
            'hSubtitle' => 'h'.min(3 + $depth, 6),
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

        ul, ol { list-style-position: outside; margin-left: 2em; margin-bottom: 1em; }
        ol { list-style-type: decimal; }
        ul { list-style-type: disc; }
        ul ul, ol ol, ul ol, ol ul { margin-left: 1.5em; margin-bottom: 0; }
        ul ul { list-style-type: circle; }
        ul ul ul { list-style-type: square; }
        li { margin-bottom: 0.3em; }
        li:last-child { margin-bottom: 0; }

        {{-- Tablas del wysiwyg, como las pintaba el viejo. --}}
        table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
        td, th { text-align: center; border-bottom: 1px solid grey; vertical-align: middle; padding: 1mm; }
        table img { display: inline-block; margin: 0; float: none; width: 11pt; height: 11pt; }

        {{-- Citas: la del BLOQUE cita, en la fuente especial del sitio,
             cursiva y centrada (como la web)... --}}
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

        {{-- Iconos del juego insertados en el texto: al tamaño de la línea. --}}
        img.rt-icon { width: 11pt !important; height: 11pt !important; display: inline-block; vertical-align: middle; }

        .block { margin-bottom: 2em; }
        .block--header { margin-bottom: 2em; padding-bottom: 1em; border-bottom: 2px solid #000; }

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
    </style>
</head>
<body>
    {{-- El título de la página, de portada: centrado, con la fuente de títulos. --}}
    <h1>{{ $page->getTranslation('title', $locale) }}</h1>

    @foreach ($items as $item)
        @php $s = $item['s']; @endphp

        @if ($item['type'] === 'header')
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
                    <blockquote>{!! $assets->inlineImages($s['quote']) !!}</blockquote>
                @endif
                @if (! blank($s['author'] ?? null))
                    <div class="quote-author" style="text-align: {{ in_array($s['author_align'] ?? 'right', ['left', 'center', 'right'], true) ? $s['author_align'] : 'right' }};">— {{ $s['author'] }}</div>
                @endif
            </div>

        @elseif ($item['type'] === 'faq')
            <div class="block block--faq">
                @if (! blank($s['title'] ?? null))
                    <{{ $item['hTitle'] }}{!! $styleAttr($headingAlign($s, 'title_align')) !!}>{{ $s['title'] }}</{{ $item['hTitle'] }}>
                @endif
                @if (! blank($s['subtitle'] ?? null))
                    <{{ $item['hSubtitle'] }}{!! $styleAttr($headingAlign($s, 'subtitle_align')) !!}>{{ $s['subtitle'] }}</{{ $item['hSubtitle'] }}>
                @endif
                @foreach ($s['items'] ?? [] as $faq)
                    @if (! blank($faq['question'] ?? null))
                        <div class="faq-question">{{ $faq['question'] }}</div>
                    @endif
                    @if (! blank($faq['answer'] ?? null))
                        <div{!! $styleAttr($bodyAlign($s)) !!}>{!! $assets->inlineImages($faq['answer']) !!}</div>
                    @endif
                @endforeach
            </div>

        @elseif (in_array($item['type'], ['text', 'text-card', 'cta'], true) || $item['category'] !== 'data')
            {{-- Texto (y variantes con cuerpo): tarjeta de texto, llamada a la
                 acción y cualquier tipo de presentación de un juego que siga
                 el contrato title/subtitle/body(+image). --}}
            @php
                $body = $assets->inlineImages(is_string($s['body'] ?? null) ? $s['body'] : '');
                [$leading, $rest] = $assets->splitLeadingHeadings($body);
                $image = $assets->imageDataUri($s['image'] ?? null);
                $float = $image ? $floatFor($s) : null;
                $position = $s['image_position'] ?? 'top';
            @endphp
            @continue(blank($s['title'] ?? null) && blank($s['subtitle'] ?? null) && trim($body) === '' && ! $image && blank($s['button_text'] ?? null))
            <div class="block block--{{ $item['type'] }}">
                @if (! blank($s['label'] ?? null))
                    <div class="label"{!! $styleAttr(in_array($s['label_align'] ?? 'left', ['center', 'right'], true) ? $s['label_align'] : null) !!}>{{ $s['label'] }}</div>
                @endif
                @if (! blank($s['title'] ?? null))
                    <{{ $item['hTitle'] }}{!! $styleAttr($headingAlign($s, 'title_align')) !!}>{{ $s['title'] }}</{{ $item['hTitle'] }}>
                @endif
                @if (! blank($s['subtitle'] ?? null))
                    <{{ $item['hSubtitle'] }}{!! $styleAttr($headingAlign($s, 'subtitle_align')) !!}>{{ $s['subtitle'] }}</{{ $item['hSubtitle'] }}>
                @endif
                {{-- Títulos iniciales del wysiwyg, ANTES de flotar la imagen:
                     a todo el ancho, sin empujarla (portado del viejo). --}}
                {!! $leading !!}
                <div class="block__content">
                    @if ($image && $float)
                        <img class="block__image block__image--{{ $float[0] }}" style="width: {{ $float[1] }}%;" src="{{ $image }}" alt="">
                    @elseif ($image && $position !== 'bottom')
                        <img class="block__image block__image--full" src="{{ $image }}" alt="">
                    @endif
                    @if (trim($rest) !== '')
                        <div{!! $styleAttr($bodyAlign($s)) !!}>{!! $rest !!}</div>
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
            @php $intro = $assets->inlineImages(is_string($s['intro'] ?? null) ? $s['intro'] : ''); @endphp
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
