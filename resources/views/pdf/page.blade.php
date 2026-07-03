{{-- PDF de una página del CRM: los bloques imprimibles como documento de
     texto (DomPDF). El HTML rico ya llega saneado (DC-09). --}}
@php
    /** @var \Bgm\Core\Pdf\Models\GeneratedPdf $pdf */
    $page = $pdf->source;
    $locale = $pdf->locale;
    $registry = app(\Bgm\Core\Content\BlockTypeRegistry::class);
    $blocks = $page->blocks()->printable()->get()
        ->filter(fn ($block) => $registry->has($block->type) && $block->type !== 'index');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #222; margin: 18mm 16mm; }
        h1 { font-size: 20pt; margin: 0 0 10mm; }
        h2 { font-size: 14pt; margin: 8mm 0 3mm; }
        h3 { font-size: 12pt; margin: 5mm 0 2mm; color: #555; }
        p { margin: 0 0 3mm; line-height: 1.45; }
        blockquote { margin: 4mm 6mm; padding-left: 4mm; border-left: 2pt solid #888; font-style: italic; }
        .label { font-size: 9pt; text-transform: uppercase; letter-spacing: 1pt; color: #777; }
        img.rt-icon { height: 11pt; vertical-align: middle; }
    </style>
</head>
<body>
    <h1>{{ $page->getTranslation('title', $locale) }}</h1>

    @foreach ($blocks as $block)
        @php
            $type = $registry->get($block->type);
            $s = $type->localizeSettings($block->settings, $locale);
        @endphp
        <section>
            @if (!empty($s['label']))<div class="label">{{ $s['label'] }}</div>@endif
            @if (!empty($s['title']))<h2>{{ $s['title'] }}</h2>@endif
            @if (!empty($s['subtitle']))<h3>{{ $s['subtitle'] }}</h3>@endif
            @if (!empty($s['body'])){!! $s['body'] !!}@endif
            @if (!empty($s['quote']))<blockquote>{!! $s['quote'] !!}@if (!empty($s['author']))<footer>— {{ $s['author'] }}</footer>@endif</blockquote>@endif
            @if (!empty($s['intro'])){!! $s['intro'] !!}@endif
        </section>
    @endforeach
</body>
</html>
