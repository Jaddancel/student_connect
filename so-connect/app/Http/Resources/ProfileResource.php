<?php

namespace App\Http\Resources;

use App\Models\Profile\ProfileAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'occupation' => $this->occupation,
            'address' => ProfileAddress::findOrFail($this->address)->toResource(),
        ];
    }
}
