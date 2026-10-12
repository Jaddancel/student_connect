<?php

use App\Support\AddressParser;

it('splits a full address into profile address columns', function () {
    expect(AddressParser::parse('123 Rizal St, Brgy. Lahug, Cebu City, Cebu, Philippines'))->toBe([
        'country' => 'Philippines',
        'province' => 'Cebu',
        'town' => 'Cebu City',
        'barangay' => '123 Rizal St, Brgy. Lahug',
    ]);
});

it('keeps a trailing city as the town when no province is given', function () {
    expect(AddressParser::parse('Purok 2, Lahug, Cebu City'))->toMatchArray([
        'province' => '',
        'town' => 'Cebu City',
        'barangay' => 'Purok 2, Lahug',
    ]);
});

it('reads a city followed by a province', function () {
    expect(AddressParser::parse('Quezon City, Metro Manila'))->toMatchArray([
        'province' => 'Metro Manila',
        'town' => 'Quezon City',
        'barangay' => '',
    ]);
});

it('fills short addresses from the barangay up', function () {
    expect(AddressParser::parse('Poblacion, Tagbilaran'))->toMatchArray([
        'province' => '',
        'town' => 'Tagbilaran',
        'barangay' => 'Poblacion',
    ])->and(AddressParser::parse('Poblacion'))->toMatchArray([
        'province' => '',
        'town' => '',
        'barangay' => 'Poblacion',
    ]);
});

it('accepts newlines as separators and drops postal codes and country aliases', function () {
    expect(AddressParser::parse("Brgy. San Isidro\nTalisay\nCebu 6045\nPH"))->toBe([
        'country' => 'Philippines',
        'province' => 'Cebu',
        'town' => 'Talisay',
        'barangay' => 'Brgy. San Isidro',
    ]);
});

it('returns empty parts for a blank address', function () {
    expect(AddressParser::parse(null))->toBe([
        'country' => 'Philippines',
        'province' => '',
        'town' => '',
        'barangay' => '',
    ])->and(AddressParser::parse('  ,  '))->toMatchArray(['barangay' => '']);
});
