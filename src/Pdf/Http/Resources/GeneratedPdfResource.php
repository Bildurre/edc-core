<?php

namespace Bgm\Core\Pdf\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedPdfResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source_id' => $this->source_id,
            'locale' => $this->locale,
            'layout' => $this->layout,
            'filename' => $this->filename,
            'status' => $this->status,
            'error' => $this->error,
            'url' => $this->url(),
            'is_permanent' => $this->is_permanent,
            'expires_at' => $this->expires_at,
            'generated_at' => $this->generated_at,
        ];
    }
}
