{{-- Rejilla genérica del motor (doc 02): pinta imágenes a tamaño físico exacto
     con marcas de corte. Todo va posicionado en absoluto con coordenadas en mm
     (lo más fiable en DomPDF: nada de floats). Para layouts especiales, el
     export declara su propia vista (ver PdfExportContract::view). --}}
@php
    /** @var \Bgm\Core\Pdf\PrintLayout $layout */
    $cols = $layout->columns();
    $mark = $layout->cropMarks ? $layout->cropMarkLength : 0;
    // El margen de página cede sitio a las marcas; el contenido se desplaza
    // ese mismo offset para que las piezas queden donde tocaba (sin coords
    // negativas, que DomPDF no maneja bien).
    $pageMargin = max(0, $layout->margin - $mark);
    $offset = $layout->margin - $pageMargin;
    $w = $layout->itemWidth;
    $h = $layout->itemHeight;
    $stepX = $w + $layout->gap;
    $stepY = $h + $layout->gap;
    $t = 0.25; // grosor de las marcas (mm)
@endphp
<!DOCTYPE html>
<html lang="{{ $pdf->locale }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: {{ $pageMargin }}mm; }
        * { margin: 0; padding: 0; }
        .page { position: relative; page-break-after: always; }
        .page--last { page-break-after: avoid; }
        .slot { position: absolute; }
        .slot img { width: {{ $w }}mm; height: {{ $h }}mm; display: block; }
        .mark { position: absolute; background: #000; }
    </style>
</head>
<body>
@foreach ($pages as $slots)
    <div class="page {{ $loop->last ? 'page--last' : '' }}" style="height: {{ $offset + $layout->rows() * $stepY }}mm;">
        @foreach ($slots as $i => $slot)
            @php
                $x = $offset + ($i % $cols) * $stepX;
                $y = $offset + intdiv($i, $cols) * $stepY;
            @endphp
            <div class="slot" style="left: {{ $x }}mm; top: {{ $y }}mm;">
                <img src="{{ $slot['image'] }}" alt="">
            </div>
            @if ($mark > 0)
                {{-- superior izquierda --}}
                <div class="mark" style="left: {{ $x - $mark }}mm; top: {{ $y }}mm; width: {{ $mark }}mm; height: {{ $t }}mm;"></div>
                <div class="mark" style="left: {{ $x }}mm; top: {{ $y - $mark }}mm; width: {{ $t }}mm; height: {{ $mark }}mm;"></div>
                {{-- superior derecha --}}
                <div class="mark" style="left: {{ $x + $w }}mm; top: {{ $y }}mm; width: {{ $mark }}mm; height: {{ $t }}mm;"></div>
                <div class="mark" style="left: {{ $x + $w - $t }}mm; top: {{ $y - $mark }}mm; width: {{ $t }}mm; height: {{ $mark }}mm;"></div>
                {{-- inferior izquierda --}}
                <div class="mark" style="left: {{ $x - $mark }}mm; top: {{ $y + $h - $t }}mm; width: {{ $mark }}mm; height: {{ $t }}mm;"></div>
                <div class="mark" style="left: {{ $x }}mm; top: {{ $y + $h }}mm; width: {{ $t }}mm; height: {{ $mark }}mm;"></div>
                {{-- inferior derecha --}}
                <div class="mark" style="left: {{ $x + $w }}mm; top: {{ $y + $h - $t }}mm; width: {{ $mark }}mm; height: {{ $t }}mm;"></div>
                <div class="mark" style="left: {{ $x + $w - $t }}mm; top: {{ $y + $h }}mm; width: {{ $t }}mm; height: {{ $mark }}mm;"></div>
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
