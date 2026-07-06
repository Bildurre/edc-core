<?php

namespace Edc\Core\Content\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Página para el admin: traducciones completas (para editar). */
class PageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getTranslations('title'),
            'description' => $this->getTranslations('description'),
            'slug' => $this->getTranslations('slug'),
            'meta_title' => $this->getTranslations('meta_title'),
            'meta_description' => $this->getTranslations('meta_description'),
            'parent_id' => $this->parent_id,
            'order' => $this->order,
            'template' => $this->template,
            'background_image' => $this->background_image,
            'is_published' => $this->is_published,
            'is_home' => $this->is_home,
            'is_printable' => $this->is_printable,
            'blocks_count' => $this->whenCounted('blocks'),
            'children_count' => $this->whenCounted('children'),
            'blocks' => BlockResource::collection($this->whenLoaded('blocks')),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
