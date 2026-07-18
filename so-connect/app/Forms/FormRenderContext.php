<?php

namespace App\Forms;

use App\Models\Form;
use App\Models\Organization;
use App\Support\OrganizationField;
use App\Support\UniversalField;
use Illuminate\Http\Request;

/**
 * Builds the view data a builder form needs to render (fields, profile
 * prefill, adviser options, special field data and client-side visibility
 * conditions). Shared by {@see \App\Http\Controllers\FormRenderController} (the
 * standalone /forms/{route} page) and the calendar drawer, which embeds the
 * new_event form inline via components/form/builder-form.blade.php.
 */
final class FormRenderContext
{
    /**
     * @return array{form:Form, fields:\Illuminate\Support\Collection, prefill:array<string,mixed>, hidden:array<string,string>, advisers:array<int,mixed>, special:array<string,mixed>, conditions:array<string,mixed>}
     */
    public static function build(Form $form, Request $request): array
    {
        $fields = $form->fields()->get();
        $organization = OrganizationField::resolveOrganization($request->user());

        return [
            'form' => $form,
            'fields' => $fields,
            'prefill' => self::profilePrefill($request, $fields, $organization),
            'hidden' => [],
            'advisers' => OrganizationField::advisers($organization),
            'special' => SpecialFieldData::resolve($request->user(), $fields, $form),
            'conditions' => ConditionEvaluator::clientConditions($fields),
        ];
    }

    /**
     * Prefill each field carrying a universal_key from the submitter's profile
     * (or their organization, for org-scoped keys).
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\Form\FormDescription>  $fields
     * @return array<string,mixed>
     */
    public static function profilePrefill(Request $request, $fields, ?Organization $organization): array
    {
        // `profile` is a FK column on users and shadows the relation, so
        // `$user->profile` returns the id — load the related model explicitly.
        $profile = $request->user()?->profile()->first();

        $prefill = [];
        foreach ($fields as $field) {
            $key = $field->universal_key;
            if (! $key || FieldType::isFileLike($field->field_type)) {
                continue;
            }

            $value = UniversalField::isOrgField($key)
                ? OrganizationField::value($organization, $key)
                : ($profile ? UniversalField::valueFor($profile, $key) : null);

            if ($value !== null && $value !== '') {
                $prefill[$field->field_key] = $value;
            }
        }

        return $prefill;
    }
}
