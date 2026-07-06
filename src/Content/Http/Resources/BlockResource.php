<?php

namespace Edc\Core\Content\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Bloque para el admin: settings en crudo (el BlockEditor los edita). */
class BlockResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'type' => $this->type,
            'order' => $this->order,
            'settings' => $this->settings ?? (object) [],
            'is_printable' => $this->is_printable,
            'is_indexable' => $this->is_indexable,
        ];
    }
}
