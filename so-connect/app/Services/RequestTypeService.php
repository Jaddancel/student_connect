<?php

namespace App\Services;

use App\Models\Form;
use App\Models\RequestType;

class RequestTypeService
{
    /** system_key prefix identifying a request type owned by one form. */
    public const FORM_KEY_PREFIX = 'form:';

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

    /**
     * Each form page owns its own request type, identified by the stable
     * system_key `form:<form_id>` (form names are not unique, request-type
     * names are — the key sidesteps that). Called on every builder save:
     * creates the type on first save, keeps its display name synced to the
     * form name after renames, and writes the link onto forms.request_type_id.
     */
    public function resolveFormType(Form $form, ?int $createdBy = null): RequestType
    {
        $systemKey = self::FORM_KEY_PREFIX.$form->getKey();

        $requestType = RequestType::query()->firstOrCreate(
            ['system_key' => $systemKey],
            [
                'name' => $this->availableFormTypeName($form, $systemKey),
                'category' => RequestType::CATEGORY_ORGANIZATION,
                'created_by' => $createdBy,
                'is_active' => true,
            ]
        );

        // Keep name (after form renames) and active flag (after a forms:wipe
        // deactivation followed by a rebuild reusing the id) in sync.
        $expectedName = $this->availableFormTypeName($form, $systemKey);
        if ($requestType->name !== $expectedName || ! $requestType->is_active) {
            $requestType->update(['name' => $expectedName, 'is_active' => true]);
        }

        if ((int) $form->request_type_id !== (int) $requestType->getKey()) {
            $form->forceFill(['request_type_id' => (int) $requestType->getKey()])->save();
        }

        return $requestType;
    }

    /**
     * "<Form name> Request", falling back to a #<form id> suffix when another
     * request type already holds that unique name.
     */
    private function availableFormTypeName(Form $form, string $systemKey): string
    {
        $name = trim((string) $form->name).' Request';

        $taken = RequestType::query()
            ->where('name', $name)
            ->where('system_key', '!=', $systemKey)
            ->exists();

        return $taken ? $name.' #'.$form->getKey() : $name;
    }
}
