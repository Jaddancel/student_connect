<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicySecurityRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'request_id' => $this->request_id,
            'action' => $this->action,
            'action_type' => $this->action_type,
            'requested_at' => $this->requested_at,
            'user' => $this->payload_user_id,
            'organization_id' => $this->organization_id,
            'request_organization' => $this->organization_name,
            'current_role' => $this->current_role,
            'requested_role' => $this->requested_role,
            'name_or_title' => $this->name_or_title,
            'profile' => $this->profile,
        ];
    }
}
