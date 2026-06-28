<?php

namespace Bgm\Core\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Rutas de media predecibles (DC-15): {modelo}/{id}/{mediaId}/...
 * Las necesitan las previews y el PDF para localizar imágenes sin BD.
 */
class MotorPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->base($media) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->base($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->base($media) . '/responsive/';
    }

    protected function base(Media $media): string
    {
        $model = strtolower(class_basename($media->model_type));

        return "{$model}/{$media->model_id}/{$media->getKey()}";
    }
}
