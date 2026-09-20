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
    config()->set('services.gemini', [
        'api_key' => 'test-gemini-key',
        'url' => 'https://generativelanguage.googleapis.test/v1beta',
        'timeout' => 30,
        'model' => 'gemini-3.8-flash',
    ]);
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
        'https://generativelanguage.googleapis.test/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [[
                    'text' => 'Open Form Builder [[route:admin.form-builder.index]] to publish. Backups live at [[route:superadmin.backups.index]].',
                ]]]],
            ],
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

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://generativelanguage.googleapis.test/v1beta/models/gemini-3.8-flash:generateContent'
            && $data['systemInstruction']['parts'][0]['text'] !== ''
            && $data['contents'][0] === [
                'role' => 'user',
                'parts' => [['text' => 'how do I publish a form?']],
            ]
            && $data['generationConfig']['temperature'] === 0.3;
    });
});

it('maps browser assistant turns to Gemini model turns', function () {
    Http::fake([
        'https://generativelanguage.googleapis.test/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Use Form Builder.']]]]],
        ], 200),
    ]);

    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [
                ['role' => 'user', 'content' => 'Where can I create a form?'],
                ['role' => 'assistant', 'content' => 'You can use Form Builder.'],
                ['role' => 'user', 'content' => 'Show me the page.'],
            ],
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    Http::assertSent(function ($request) {
        return collect($request->data()['contents'])->pluck('role')->all() === ['user', 'model', 'user'];
    });
});

it('leaves the markdown in a reply intact while stripping tokens', function () {
    // Leading indentation is what nests the sub-list — the whitespace cleanup
    // that tidies up after a stripped token must not flatten it.
    Http::fake([
        'https://generativelanguage.googleapis.test/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => "1. Open **Form Builder** [[route:admin.form-builder.index]]  and add fields:\n   - a `text` field\n   - a `date` field\n2. Attach a PDF template — it publishes on save."]]]],
            ],
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

it('returns a page-aware opening greeting and model-authored suggestions', function () {
    Http::fake([
        'https://generativelanguage.googleapis.test/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [[
                    'text' => 'Welcome to Form Builder. [[suggestion:How do I create a form?]] [[suggestion:How do I add a field?]] [[suggestion:How do I publish a form?]]',
                ]]]],
            ],
        ], 200),
    ]);

    $admin = recordsUser(2);

    $data = $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [],
            'current_path' => '/admin/form-builder',
            'opening' => true,
        ])
        ->assertOk()
        ->assertJson(['ok' => true])
        ->json();

    expect($data['reply'])->toBe('Welcome to Form Builder.')
        ->and($data['suggestions'])->toBe([
            'How do I create a form?',
            'How do I add a field?',
            'How do I publish a form?',
        ]);
});

it('turns opening question bullets into suggestions when the model omits suggestion tokens', function () {
    Http::fake([
        'https://generativelanguage.googleapis.test/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [[
                    'text' => "Welcome to Event Plans. Here are some useful next steps:\n\n- How do I create an event plan?\n- How do I update a pending event?\n- Where can I see approved events?",
                ]]]],
            ],
        ], 200),
    ]);

    $admin = recordsUser(2);

    $data = $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [],
            'current_path' => '/event-plans',
            'opening' => true,
        ])
        ->assertOk()
        ->assertJson(['ok' => true])
        ->json();

    expect($data['reply'])->toBe('Welcome to Event Plans. Here are some useful next steps:')
        ->and($data['suggestions'])->toBe([
            'How do I create an event plan?',
            'How do I update a pending event?',
            'Where can I see approved events?',
        ]);
});

it('returns ok:false when Gemini returns an error', function () {
    Http::fake(['https://generativelanguage.googleapis.test/*' => Http::response('', 503)]);

    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ])
        ->assertOk()
        ->assertJson(['ok' => false, 'reply' => '', 'links' => []]);
});

it('degrades without calling Gemini when its API key is missing', function () {
    config()->set('services.gemini.api_key', '');
    Http::fake();

    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ])
        ->assertOk()
        ->assertJson(['ok' => false, 'note' => 'assistant unavailable']);

    Http::assertNothingSent();
});

it('degrades when Gemini returns no candidate text', function () {
    Http::fake([
        'https://generativelanguage.googleapis.test/*' => Http::response(['candidates' => []], 200),
    ]);

    $admin = recordsUser(2);

    $this->actingAs($admin)
        ->postJson(route('assistant.chat'), [
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ])
        ->assertOk()
        ->assertJson(['ok' => false, 'note' => 'assistant error']);
});

it('degrades rather than answering with an empty reply', function () {
    // Nothing to render is not a successful turn: `ok: true` with a blank
    // reply is what surfaced as an empty (or "undefined") chat bubble.
    Http::fake(['https://generativelanguage.googleapis.test/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => '   ']]]]],
    ], 200)]);

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
        'https://generativelanguage.googleapis.test/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [[
                    'text' => '[[route:admin.form-builder.index]]',
                ]]]],
            ],
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
