<?php

namespace App\Services;

use App\Models\RequestType;

class RequestTypeService
{
    public function resolveSystemType(string $systemKey, string $name, string $category, ?int $createdBy = null): RequestType
    {
        return RequestType::query()->firstOrCreate(
            ['system_key' => $systemKey],
            [
                'name' => $name,
                'category' => $category,
                'created_by' => $createdBy,
                'is_active' => true,
            ]
        );
    }
}
