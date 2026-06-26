<?php

use App\Services\FieldCandidateExtractor;

beforeEach(function () {
    $this->extractor = new FieldCandidateExtractor;
});

/**
 * @param  array<int, array<string, mixed>>  $fields
 * @return array<string, array<string, mixed>>
 */
function byKey(array $fields): array
{
    return collect($fields)->keyBy('field_key')->all();
}

it('detects colon labels and infers their types', function () {
    $fields = $this->extractor->extract("Student Name:\nDate of Birth:\nEmail Address:");

    expect($fields)->toHaveCount(3);
    $byKey = byKey($fields);
    expect($byKey['student_name']['field_type'])->toBe('text');
    expect($byKey['date_of_birth']['field_type'])->toBe('date');
    expect($byKey['email_address']['field_type'])->toBe('email');
});

it('splits a table row into one field per cell, in order', function () {
    $fields = $this->extractor->extract('First Name | Middle Name | Last Name');

    expect($fields)->toHaveCount(3);
    expect(array_column($fields, 'field_key'))->toBe(['first_name', 'middle_name', 'last_name']);
    expect(array_column($fields, 'field_order'))->toBe([1, 2, 3]);
});

it('reads the label before an underscore fill blank', function () {
    $fields = $this->extractor->extract('Date Filed _________');

    expect($fields)->toHaveCount(1);
    expect($fields[0]['label'])->toBe('Date Filed');
    expect($fields[0]['field_type'])->toBe('date');
});

it('infers field_type for each enum group', function () {
    $cases = [
        'Email Address' => 'email',
        'Age' => 'number',
        'Objectives' => 'textarea',
        'I agree to the terms' => 'checkbox',
        'Position Title' => 'text',
    ];

    foreach ($cases as $label => $expected) {
        $fields = $this->extractor->extract($label.':');
        expect($fields[0]['field_type'])->toBe($expected, "label: {$label}");
    }
});

it('excludes title, long instructions, and signature lines', function () {
    $text = implode("\n", [
        'STUDENT ACTIVITY REQUEST FORM',
        'Please complete every section of this form legibly and submit it to the office.',
        'Student Name:',
        'Signature of Approving Officer:',
    ]);

    $fields = $this->extractor->extract($text);

    expect(array_column($fields, 'field_key'))->toBe(['student_name']);
});

it('dedupes repeated labels with numeric suffixes', function () {
    $fields = $this->extractor->extract("Name:\nName:");

    expect(array_column($fields, 'field_key'))->toBe(['name', 'name_2']);
});

it('marks optional labels as not required', function () {
    $fields = $this->extractor->extract('Middle Name (optional):');

    expect($fields[0]['is_required'])->toBeFalse();
});

it('marks unmarked labels as required', function () {
    $fields = $this->extractor->extract('Last Name:');

    expect($fields[0]['is_required'])->toBeTrue();
});

it('never returns empty for a realistic multi-line form', function () {
    $text = implode("\n", [
        'ORGANIZATION ACCREDITATION FORM',
        'Organization Name:',
        'Adviser | Position | Contact Number',
        'Objectives',
        'Date Filed ______',
        'Email Address:',
    ]);

    $fields = $this->extractor->extract($text);

    // org name, adviser, position, contact number, objectives, date filed, email
    expect(count($fields))->toBeGreaterThanOrEqual(6);
    expect(array_column($fields, 'field_key'))->toContain('email_address');
});
