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
        $requester = $this->requester;
        $profileId = (int) ($requester?->profile ?? 0);
        $profile = $profileId > 0 ? Profile::query()->find($profileId) : null;

        return [
            'request_id' => $this->request_id,
            'action' => $this->action,
            'user' => $this->user,
            'profile' => ProfileResource::make($profile),
            'action_type' => $this->action_type,
            'requested_at' => $this->requested_at,
        ];
    }
}
