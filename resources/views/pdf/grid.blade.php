{{-- Rejilla genérica del motor (doc 02): pinta imágenes a tamaño físico exacto
     con marcas de corte. Todo va posicionado en absoluto con coordenadas en mm
     (lo más fiable en DomPDF: nada de floats). Para layouts especiales, el
     export declara su propia vista (ver PdfExportContract::view). --}}
@php
    /** @var \Edc\Core\Pdf\PrintLayout $layout */
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
                // Con el hueco entre piezas menor que la marca (piezas casi
                // pegadas, p. ej. gap de ~1px), una marca hacia un vecino se
                // pintaría ENCIMA de su imagen: solo se dibujan las que caen
                // en espacio libre (bordes de página o hueco sin vecino) —
                // el corte interior va por la línea compartida, guiado por
                // las marcas del perímetro. Con hueco holgado (gap >= marca),
                // todas las marcas de siempre.
                $count = count($slots);
                $col = $i % $cols;
                $tight = $layout->gap < $mark;
                $canL = ! ($tight && $col > 0);
                $canR = ! ($tight && $col < $cols - 1 && $i + 1 < $count);
                $canU = ! ($tight && $i >= $cols);
                $canD = ! ($tight && $i + $cols < $count);
            @endphp
            <div class="slot" style="left: {{ $x }}mm; top: {{ $y }}mm;">
                <img src="{{ $slot['image'] }}" alt="">
            </div>
            @if ($mark > 0)
                {{-- superior izquierda --}}
                @if ($canL)
                    <div class="mark" style="left: {{ $x - $mark }}mm; top: {{ $y }}mm; width: {{ $mark }}mm; height: {{ $t }}mm;"></div>
                @endif
                @if ($canU)
                    <div class="mark" style="left: {{ $x }}mm; top: {{ $y - $mark }}mm; width: {{ $t }}mm; height: {{ $mark }}mm;"></div>
                @endif
                {{-- superior derecha --}}
                @if ($canR)
                    <div class="mark" style="left: {{ $x + $w }}mm; top: {{ $y }}mm; width: {{ $mark }}mm; height: {{ $t }}mm;"></div>
                @endif
                @if ($canU)
                    <div class="mark" style="left: {{ $x + $w - $t }}mm; top: {{ $y - $mark }}mm; width: {{ $t }}mm; height: {{ $mark }}mm;"></div>
                @endif
                {{-- inferior izquierda --}}
                @if ($canL)
                    <div class="mark" style="left: {{ $x - $mark }}mm; top: {{ $y + $h - $t }}mm; width: {{ $mark }}mm; height: {{ $t }}mm;"></div>
                @endif
                @if ($canD)
                    <div class="mark" style="left: {{ $x }}mm; top: {{ $y + $h }}mm; width: {{ $t }}mm; height: {{ $mark }}mm;"></div>
                @endif
                {{-- inferior derecha --}}
                @if ($canR)
                    <div class="mark" style="left: {{ $x + $w }}mm; top: {{ $y + $h - $t }}mm; width: {{ $mark }}mm; height: {{ $t }}mm;"></div>
                @endif
                @if ($canD)
                    <div class="mark" style="left: {{ $x + $w - $t }}mm; top: {{ $y + $h }}mm; width: {{ $t }}mm; height: {{ $mark }}mm;"></div>
                @endif
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
