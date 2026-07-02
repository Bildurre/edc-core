<?php

namespace Bgm\Core\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames(),
            'can_access_admin' => $this->canAccessAdmin(),
            'email_verified' => $this->email_verified_at !== null,
        ];
    }
}
