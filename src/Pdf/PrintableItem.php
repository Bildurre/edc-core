<?php

namespace Bgm\Core\Pdf;

use Bgm\Core\Previews\PreviewableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Un hueco imprimible del PDF: una imagen (normalmente la preview PNG de una
 * entidad) repetida N copias. El job expande copias y resuelve las previews
 * que falten antes de componer.
 */
class PrintableItem
{
    protected function __construct(
        public readonly ?string $image,
        public readonly int $copies,
        /** Entidad renderizable pendiente de resolver a PNG (por locale). */
        public readonly ?Model $previewable = null,
    ) {}

    /** Ítem a partir de una imagen concreta (ruta absoluta o URL). */
    public static function image(string $image, int $copies = 1): self
    {
        return new self($image, max(1, $copies));
    }

    /**
     * Ítem a partir de una entidad renderizable (doc 01): el job usará su
     * preview PNG del locale del PDF, generándola si no existe.
     */
    public static function preview(Model&PreviewableContract $entity, int $copies = 1): self
    {
        return new self(null, max(1, $copies), $entity);
    }
}
