<?php

use App\Support\NameParser;

it('splits a plain First Middle Last name', function () {
    expect(NameParser::split('Juan Miguel Cruz'))->toBe([
        'first_name' => 'Juan Miguel',
        'middle_name' => '',
        'last_name' => 'Cruz',
    ]);
});

it('splits a comma-form Last, First Middle name', function () {
    expect(NameParser::split('Cruz, Jane Marie'))->toBe([
        'first_name' => 'Jane Marie',
        'middle_name' => '',
        'last_name' => 'Cruz',
    ]);
});

it('keeps a middle initial as the middle name in comma form', function () {
    expect(NameParser::split('Dela Cruz, Juan P.'))->toBe([
        'first_name' => 'Juan',
        'middle_name' => 'P.',
        'last_name' => 'Dela Cruz',
    ]);
});

it('folds a generational suffix onto the surname', function () {
    expect(NameParser::split('John Smith Jr.'))->toBe([
        'first_name' => 'John',
        'middle_name' => '',
        'last_name' => 'Smith Jr.',
    ]);
});

it('treats a lone token as the first name', function () {
    expect(NameParser::split('Madonna'))->toBe([
        'first_name' => 'Madonna',
        'middle_name' => '',
        'last_name' => '',
    ]);
});

it('returns empty parts for a blank name', function () {
    expect(NameParser::split('   '))->toBe([
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
    ]);
});
