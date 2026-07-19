<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function waiverTemplate(string $zoneName = 'participant', string $type = 'text'): array
{
    return [
        'reference' => ['width' => 800, 'height' => 600],
        'zones' => [['name' => $zoneName, 'type' => $type, 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10]],
    ];
}

it('validates a scanned waiver against expected details', function () {
    Http::fake([
        '*/waiver-scan' => Http::response([
            'ok' => true,
            'fields' => ['participant' => 'Juan Dela Cruz', 'date' => '2026-07-04'],
            'signature' => true,
            'stamp' => true,
        ], 200),
    ]);

    $this->actingAs(recordsUser(3))
        ->postJson(route('waiver.scan'), [
            'image' => 'data:image/png;base64,'.base64_encode('x'),
            'template' => waiverTemplate(),
            'expected' => [
                'participant' => ['value' => 'Juan Dela Cruz', 'type' => 'name'],
                'date' => ['value' => '2026-07-04', 'type' => 'date'],
            ],
        ])
        ->assertOk()
        ->assertJson(['ok' => true, 'valid' => true]);
});

it('reports invalid when a required stamp is missing', function () {
    config(['waiver.require_stamp' => true]);
    Http::fake([
        '*/waiver-scan' => Http::response(['ok' => true, 'fields' => [], 'signature' => true, 'stamp' => false], 200),
    ]);

    $this->actingAs(recordsUser(3))
        ->postJson(route('waiver.scan'), [
            'image' => 'data:image/png;base64,'.base64_encode('x'),
            'template' => waiverTemplate('seal', 'stamp'),
            'expected' => [],
        ])
        ->assertOk()
        ->assertJson(['ok' => true, 'valid' => false]);
});

it('reports unavailable when the sidecar is down', function () {
    Http::fake(['*/waiver-scan' => Http::response('', 503)]);

    $this->actingAs(recordsUser(3))
        ->postJson(route('waiver.scan'), [
            'image' => 'data:image/png;base64,'.base64_encode('x'),
            'template' => waiverTemplate(),
        ])
        ->assertOk()
        ->assertJson(['ok' => false, 'status' => 'unavailable']);
});
