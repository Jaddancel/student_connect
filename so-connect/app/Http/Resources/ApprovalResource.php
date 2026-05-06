<?php

namespace App\Http\Resources;

use App\Models\Member;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Http\Resources\ActionRequestResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(HttpRequest $request): array
    {
        $approvingOfficer = $this->adminThatApproved;
        $approvingMember = $approvingOfficer ? Member::query()->find($approvingOfficer->member) : null;
        $approvingUser = $approvingMember ? User::query()->find($approvingMember->user) : null;

        return [
            'id' => $this->approval_id,
            'approved_at' => $this->approved_at,
            'admin' => UserResource::make($approvingUser),
            'status' => $this->is_rejected,
            'request' => ActionRequestResource::make(RequestModel::query()->find($this->request)),
        ];
    }
}
