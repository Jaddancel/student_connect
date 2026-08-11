<?php

use App\Models\Officer;
use App\Services\AssistantKnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // CACHE_STORE=array in phpunit.xml persists for the whole process — flush
    // so assistant.kb.* entries never leak between tests.
    Cache::flush();
});

it('redirects unauthenticated requests', function () {
    $this->post(route('assistant.chat'), [
        'messages' => [['role' => 'user', 'content' => 'hi']],
    ])->assertRedirect(route('home'));
});

it("excludes superadmin routes from an officer's index", function () {
    $officer = recordsUser(3);
    $org = recordsOrganization('Index Org', 'IO');
    Officer::create([
        'role' => 'officer',
        'position' => 'Officer',
        'organization' => $org->organization_id,
        'user' => $officer->getKey(),
        'member_since' => now(),
    ]);

    $this->actingAs($officer);

    $index = app(AssistantKnowledgeBase::class)->forUser($officer);

    $routes = collect($index['pages'])->pluck('route')->filter();
    expect($routes->contains(fn ($route) => str_starts_with($route, 'superadmin.')))->toBeFalse();

    $paths = collect($index['pages'])->pluck('path');
    expect($paths)->not->toContain('/superadmin/backups');
});

it('strips [[route:...]] tokens outside the users index and keeps ones inside it', function () {
    Http::fake([
        '*/api/chat' => Http::response([
            'message' => ['content' => 'Open Form Builder [[route:admin.form-builder.index]] to publish. Backups live at [[route:superadmin.backups.index]].'],
        ], 200),
    ]);

    $admin = recordsUser(2);

    $response = $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [['role' => 'user', 'content' => 'how do I publish a form?']],
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $data = $response->json();

    expect($data['reply'])->not->toContain('[[route:');
    expect(collect($data['links'])->pluck('path')->all())->toBe(['/admin/form-builder']);
});

it('leaves the markdown in a reply intact while stripping tokens', function () {
    // Leading indentation is what nests the sub-list — the whitespace cleanup
    // that tidies up after a stripped token must not flatten it.
    Http::fake([
        '*/api/chat' => Http::response([
            'message' => ['content' => <<<'REPLY'
                1. Open **Form Builder** [[route:admin.form-builder.index]]  and add fields:
                   - a `text` field
                   - a `date` field
                2. Attach a PDF template — it publishes on save.
                REPLY],
        ], 200),
    ]);

    $admin = recordsUser(2);

    $reply = $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [['role' => 'user', 'content' => 'how do I publish a form?']],
        ])
        ->assertOk()
        ->json('reply');

    expect($reply)->not->toContain('[[route:');
    expect($reply)->toContain('**Form Builder**');
    expect($reply)->toContain('`text`');
    expect($reply)->toContain("\n   - a `text` field");
    expect($reply)->toContain("\n   - a `date` field");
    // The double space the stripped token left mid-sentence still collapses.
    expect($reply)->toContain('**Form Builder** and add fields:');
});

it('returns ok:false when the sidecar is down', function () {
    Http::fake(['*/api/chat' => Http::response('', 503)]);

    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ])
        ->assertOk()
        ->assertJson(['ok' => false, 'reply' => '', 'links' => []]);
});

it('degrades rather than answering with an empty reply', function () {
    // Nothing to render is not a successful turn: `ok: true` with a blank
    // reply is what surfaced as an empty (or "undefined") chat bubble.
    Http::fake(['*/api/chat' => Http::response(['message' => ['content' => '   ']], 200)]);

    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ])
        ->assertOk()
        ->assertJson(['ok' => false, 'reply' => '', 'links' => [], 'note' => 'empty reply']);
});

it('stands on its links when the reply was nothing but route tokens', function () {
    Http::fake([
        '*/api/chat' => Http::response([
            'message' => ['content' => '[[route:admin.form-builder.index]]'],
        ], 200),
    ]);

    $admin = recordsUser(2);

    $data = $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [['role' => 'user', 'content' => 'where is the form builder?']],
        ])
        ->assertOk()
        ->assertJson(['ok' => true])
        ->json();

    expect($data['reply'])->not->toBe('');
    expect(collect($data['links'])->pluck('path')->all())->toBe(['/admin/form-builder']);
});

it('rejects an oversized payload cleanly as json', function () {
    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => array_fill(0, 21, ['role' => 'user', 'content' => 'hi']),
        ])
        ->assertOk()
        ->assertJson(['ok' => false, 'note' => 'invalid request']);
});
