<?php

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActionRequestResource extends JsonResource
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
            'user' => $this->user,
            'profile' => Profile::findOrFail($this->requester->profile)->toResource(),
            'action_type' => $this->action_type,
            'requested_at' => $this->requested_at,
        ];
    }
}
