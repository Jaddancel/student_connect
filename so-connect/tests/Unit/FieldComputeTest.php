<?php

use App\Forms\FieldCompute;
use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Forms\FieldKit;
use App\Models\Form\FormDescription;
use Illuminate\Support\Collection;

it('computes the organization member total from the three year levels', function () {
    $fields = new Collection([
        new FormDescription(['field_key' => 'freshman', 'field_type' => FieldType::NUMBER]),
        new FormDescription(['field_key' => 'sophomore', 'field_type' => FieldType::NUMBER]),
        new FormDescription(['field_key' => 'junior', 'field_type' => FieldType::NUMBER]),
        new FormDescription([
            'field_key' => 'total',
            'field_type' => FieldType::COMPUTED,
            'field_options' => ['formula' => 'sum', 'args' => ['freshman', 'sophomore', 'junior']],
        ]),
    ]);

    $payload = FieldCompute::apply($fields, [
        'freshman' => 11,
        'sophomore' => 12,
        'junior' => 13,
        'total' => 999,
    ]);

    expect($payload['total'])->toBe(36)
        ->and(FieldKit::types(SystemFunction::NEW_ORGANIZATION_REGISTRATION))->toContain(FieldType::COMPUTED);
});