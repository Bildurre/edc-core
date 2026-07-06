<?php

namespace Edc\Core\Pdf;

use InvalidArgumentException;

/**
 * Preset de impresión (DC-07): tamaño de pieza, papel, márgenes, separación y
 * marcas de corte. Las columnas y filas por página se calculan del papel; el
 * juego solo declara medidas (config motor.pdf.layouts).
 */
class PrintLayout
{
    /** Tamaños de papel en mm (vertical). */
    protected const PAPERS = [
        'a4' => [210, 297],
        'a3' => [297, 420],
        'letter' => [216, 279],
    ];

    public function __construct(
        public readonly string $key,
        public readonly string $paper = 'a4',
        public readonly string $orientation = 'portrait',
        public readonly float $itemWidth = 88,
        public readonly float $itemHeight = 126,
        public readonly float $margin = 10,
        public readonly float $gap = 6,
        public readonly bool $cropMarks = true,
        public readonly float $cropMarkLength = 4,
    ) {}

    /** Carga un preset de config motor.pdf.layouts. */
    public static function fromConfig(string $key): self
    {
        $config = config("motor.pdf.layouts.{$key}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Layout de PDF desconocido: {$key}");
        }

        return new self(
            key: $key,
            paper: $config['paper'] ?? 'a4',
            orientation: $config['orientation'] ?? 'portrait',
            itemWidth: (float) ($config['item_width'] ?? 88),
            itemHeight: (float) ($config['item_height'] ?? 126),
            margin: (float) ($config['margin'] ?? 10),
            gap: (float) ($config['gap'] ?? 6),
            cropMarks: (bool) ($config['crop_marks'] ?? true),
            cropMarkLength: (float) ($config['crop_mark_length'] ?? 4),
        );
    }

    /** Ancho del papel en mm (según orientación). */
    public function paperWidth(): float
    {
        [$w, $h] = self::PAPERS[$this->paper] ?? self::PAPERS['a4'];

        return $this->orientation === 'landscape' ? $h : $w;
    }

    /** Alto del papel en mm (según orientación). */
    public function paperHeight(): float
    {
        [$w, $h] = self::PAPERS[$this->paper] ?? self::PAPERS['a4'];

        return $this->orientation === 'landscape' ? $w : $h;
    }

    public function columns(): int
    {
        return max(1, (int) floor(
            ($this->paperWidth() - 2 * $this->margin + $this->gap) / ($this->itemWidth + $this->gap)
        ));
    }

    public function rows(): int
    {
        return max(1, (int) floor(
            ($this->paperHeight() - 2 * $this->margin + $this->gap) / ($this->itemHeight + $this->gap)
        ));
    }

    /** Piezas por página. */
    public function capacity(): int
    {
        return $this->columns() * $this->rows();
    }
}
