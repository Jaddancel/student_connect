<?php

use App\Models\ReportTemplate;
use App\Models\Template;
use App\Reports\ReportDefinitionValidator;
use App\Reports\ReportDocxRenderer;
use App\Reports\ReportPalette;
use App\Reports\ReportQueryEngine;
use App\Reports\SchemaCatalog;
use App\Services\DocxTemplateService;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function rtOfficer(int $organizationId, string $role, string $first, ?string $email = null): void
{
    $user = recordsUser(3, ['first_name' => $first, 'middle_name' => '', 'last_name' => 'Tester']);
    if ($email) {
        $user->forceFill(['user_email' => $email])->save();
    }
    DB::table('organization_officers')->insert([
        'role' => $role,
        'organization' => $organizationId,
        'user' => (int) $user->getKey(),
        'position' => $role === 'president' ? 'President' : 'Secretary',
        'member_since' => '2026-01-15 00:00:00',
        'registered_at' => now(),
    ]);
}

/** Two orgs: Alpha (president + officer + member), Beta (one officer). */
function rtFixture(): array
{
    $alpha = recordsOrganization('Alpha Organization', 'ALP');
    $beta = recordsOrganization('Beta Organization', 'BET');
    $beta->forceFill(['organization_type' => 2])->save();

    rtOfficer((int) $alpha->getKey(), 'officer', 'Olive', 'olive@example.test');
    rtOfficer((int) $alpha->getKey(), 'president', 'Paula');
    rtOfficer((int) $alpha->getKey(), 'member', 'Mark');
    rtOfficer((int) $beta->getKey(), 'officer', 'Bert');

    return [$alpha, $beta];
}

function rtDefinition(string $name): array
{
    return ReportTemplateSeeder::definitions()[$name]['definition'];
}

function rtDocxText(string $path): string
{
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xml, $m);

    return html_entity_decode(implode(' | ', array_filter($m[1], fn ($t) => trim($t) !== '')));
}

beforeEach(fn () => app(SchemaCatalog::class)->forget());

// ── Catalog ──────────────────────────────────────────────────────────────

it('introspects the schema with relations and hides sensitive data', function () {
    $catalog = app(SchemaCatalog::class);

    expect($catalog->has('organizations'))->toBeTrue()
        ->and($catalog->has('migrations'))->toBeFalse()
        ->and($catalog->has('sessions'))->toBeFalse()
        ->and($catalog->hasColumn('users', 'user_email'))->toBeTrue()
        ->and($catalog->hasColumn('users', 'user_password'))->toBeFalse()
        ->and($catalog->primaryKey('organizations'))->toBe('organization_id');

    expect($catalog->relation('organizations', 'detail'))
        ->toMatchArray(['type' => 'belongs_to', 'table' => 'organization_details'])
        ->and($catalog->relation('organizations', 'organization_officers'))
        ->toMatchArray(['type' => 'has_many', 'table' => 'organization_officers', 'foreign' => 'organization'])
        ->and($catalog->relation('organization_advisers', 'organization'))
        ->toMatchArray(['type' => 'belongs_to', 'table' => 'organizations']);
});

// ── Validator ────────────────────────────────────────────────────────────

it('rejects unknown, sensitive or malformed definitions', function (array $definition, string $message) {
    try {
        app(ReportDefinitionValidator::class)->validate($definition);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect(implode(' ', $exception->errors()['definition']))->toContain($message);
    }
})->with([
    'unknown table' => [['tokens' => [['kind' => 'group', 'name' => 'g', 'entity' => 'nope']]], 'choose a table'],
    'denied table' => [['tokens' => [['kind' => 'value', 'name' => 'v', 'from' => 'sessions', 'path' => ['id']]]], 'choose the table'],
    'sensitive column' => [['tokens' => [['kind' => 'value', 'name' => 'v', 'from' => 'users', 'path' => ['user_password']]]], 'not an available column'],
    'bad op' => [['tokens' => [['kind' => 'group', 'name' => 'g', 'entity' => 'organizations', 'where' => [['column' => 'organization_id', 'op' => 'drop']]]]], 'unknown WHERE operator'],
    'undefined param' => [['tokens' => [['kind' => 'group', 'name' => 'g', 'entity' => 'organizations', 'where' => [['column' => 'organization_id', 'op' => '=', 'param' => 'x']]]]], 'undefined parameter'],
    'duplicate names' => [['tokens' => [
        ['kind' => 'value', 'name' => 'a', 'from' => 'organizations', 'path' => ['organization_id']],
        ['kind' => 'value', 'name' => 'a', 'from' => 'organizations', 'path' => ['organization_id']],
    ]], 'already uses this name'],
    'bad name' => [['tokens' => [['kind' => 'value', 'name' => 'Bad Name', 'from' => 'organizations', 'path' => ['organization_id']]]], 'names must be'],
    'too deep' => [['tokens' => [['kind' => 'group', 'name' => 'a', 'entity' => 'organizations', 'children' => [
        ['kind' => 'group', 'name' => 'b', 'relation' => 'organization_officers', 'children' => [
            ['kind' => 'group', 'name' => 'c', 'relation' => 'evaluations', 'children' => [
                ['kind' => 'group', 'name' => 'd', 'relation' => 'nothing'],
            ]],
        ]],
    ]]]], 'at most 3 levels'],
]);

// ── Engine ───────────────────────────────────────────────────────────────

it('resolves the Registered Organizations definition like the old report', function () {
    [$alpha, $beta] = rtFixture();

    $result = app(ReportQueryEngine::class)->run(rtDefinition('Registered Organizations'));
    $orgs = collect($result['data']['orgs'])->keyBy('org_id');

    expect($result['data']['total_orgs'])->toBe('2')
        ->and($orgs[(string) $alpha->getKey()])->toMatchArray([
            'org_name' => 'Alpha Organization', 'initials' => 'ALP', 'type' => 'Socio-Civic', 'officer_count' => '2',
        ])
        ->and($orgs[(string) $beta->getKey()])->toMatchArray(['type' => 'Religious', 'officer_count' => '1']);
});

it('resolves nested groups, filters, formats and optional parameters', function () {
    [$alpha, $beta] = rtFixture();
    $engine = app(ReportQueryEngine::class);

    $all = $engine->run(rtDefinition('Organization Officers'));
    expect($all['data']['orgs'])->toHaveCount(2);

    $alphaOfficers = $all['data']['orgs'][0]['officers'];
    expect(array_column($alphaOfficers, 'first_name'))->toBe(['Paula', 'Olive'])
        ->and($alphaOfficers[0]['role'])->toBe('President')
        ->and($alphaOfficers[1]['email'])->toBe('olive@example.test')
        ->and($alphaOfficers[1]['member_since'])->toBe('2026-01-15');

    $filtered = $engine->run(rtDefinition('Organization Officers'), ['organization' => (string) $beta->getKey()]);
    expect($filtered['data']['orgs'])->toHaveCount(1)
        ->and($filtered['data']['orgs'][0]['org_name'])->toBe('Beta Organization');

    $options = $engine->parameterOptions(rtDefinition('Organization Officers')['parameters'][0]);
    expect(array_column($options, 'label'))->toContain('Alpha Organization', 'Beta Organization');
});

it('caps rows in preview mode and enforces required parameters', function () {
    foreach (range(1, 12) as $i) {
        recordsOrganization('Org '.$i);
    }
    $engine = app(ReportQueryEngine::class);
    $definition = ['tokens' => [['kind' => 'group', 'name' => 'orgs', 'entity' => 'organizations', 'children' => [
        ['kind' => 'value', 'name' => 'id', 'path' => ['organization_id']],
    ]]]];

    $preview = $engine->run($definition, [], preview: true);
    expect($preview['data']['orgs'])->toHaveCount(10)->and($preview['truncated'])->toBeTrue();
    expect($engine->run($definition)['data']['orgs'])->toHaveCount(12);

    $required = $definition + ['parameters' => [['name' => 'who', 'type' => 'text', 'required' => true]]];
    expect(fn () => $engine->run($required))->toThrow(ValidationException::class);
});

// ── Renderer ─────────────────────────────────────────────────────────────

it('expands nested blocks and table rows into the document', function () {
    Storage::fake('public');
    [$alpha] = rtFixture();
    $bytes = ReportTemplateSeeder::document(ReportTemplateSeeder::definitions()['Organization Officers']['layout']);
    Storage::disk('public')->put('report-templates/test.docx', $bytes);
    $slot = new Template(['template_name' => 'Officers', 'docx_path' => 'report-templates/test.docx']);

    $data = app(ReportQueryEngine::class)->run(rtDefinition('Organization Officers'))['data'];
    $path = app(ReportDocxRenderer::class)->render($slot, $data, recordsUser(2));
    $text = rtDocxText($path);
    File::deleteDirectory(dirname($path));

    expect($text)->toContain('Alpha Organization (ALP)')
        ->and($text)->toContain('Beta Organization (BET)')
        ->and($text)->toContain('Paula  Tester')
        ->and($text)->toContain('olive@example.test')
        ->and($text)->not->toContain('Mark')
        ->and($text)->not->toContain('{{');
    // Alpha's heading precedes its officers, which precede Beta's heading.
    expect(strpos($text, 'Alpha Organization'))->toBeLessThan(strpos($text, 'Olive'))
        ->and(strpos($text, 'Olive'))->toBeLessThan(strpos($text, 'Beta Organization'));
});

it('builds palette entries for values, groups and universal tokens', function () {
    $definition = app(ReportDefinitionValidator::class)->validate(rtDefinition('Organization Officers'));
    $tokens = collect(ReportPalette::tokens($definition));

    $group = $tokens->where('key', 'orgs.officers');
    expect($group)->toHaveCount(1)
        ->and($group->first()['actions'])->toBe(['table', 'block'])
        ->and($group->first()['known'])->toBe(['#orgs.officers', '/orgs.officers'])
        ->and($group->first()['children'][0]['key'])->toBe('orgs.officers.first_name')
        ->and($tokens->pluck('key'))->toContain('system.current_date', 'profile.org_name');
});

// ── HTTP ─────────────────────────────────────────────────────────────────

it('restricts report templates to administrators', function () {
    $this->actingAs(recordsUser(3))->get(route('admin.report-templates.index'))->assertForbidden();
    $this->actingAs(recordsUser(1))->get(route('reports.index'))->assertForbidden();
    $this->actingAs(recordsUser(2))->get(route('admin.report-templates.create'))->assertOk()->assertSee('Token List');
});

it('creates, previews, updates and deletes a report template', function () {
    Storage::fake('public');
    rtFixture();
    $admin = recordsUser(2);
    $definition = rtDefinition('Registered Organizations');

    $this->actingAs($admin)->postJson(route('admin.report-templates.preview'), ['definition' => $definition])
        ->assertOk()->assertJsonPath('data.total_orgs', '2');
    $this->actingAs($admin)->postJson(route('admin.report-templates.preview'), ['definition' => ['tokens' => [['kind' => 'group', 'name' => 'x', 'entity' => 'sessions']]]])
        ->assertStatus(422);
    $this->actingAs($admin)->getJson(route('admin.report-templates.schema'))
        ->assertOk()->assertJsonFragment(['name' => 'organizations']);

    $this->actingAs($admin)->postJson(route('admin.report-templates.store'), [
        'name' => 'Org roster', 'description' => 'All orgs', 'audience' => 'officers', 'is_active' => true, 'definition' => $definition,
    ])->assertOk()->assertJsonPath('redirect', route('admin.report-templates.index'));

    $report = ReportTemplate::query()->where('name', 'Org roster')->sole();
    expect($report->templates()->where('is_active', true)->count())->toBe(1)
        ->and($report->audience)->toBe('officers');

    $this->actingAs($admin)->postJson(route('admin.report-templates.store'), [
        'name' => 'Bad audience', 'audience' => 'everyone', 'definition' => $definition,
    ])->assertStatus(422);

    $this->actingAs($admin)->putJson(route('admin.report-templates.update', $report), [
        'name' => 'Org roster v2', 'audience' => 'all', 'is_active' => false, 'definition' => $definition,
    ])->assertOk();
    expect($report->refresh()->name)->toBe('Org roster v2')->and($report->is_active)->toBeFalse()->and($report->audience)->toBe('all');

    $this->actingAs($admin)->putJson(route('admin.report-templates.update', $report), [
        'name' => 'Empty', 'audience' => 'all', 'definition' => ['tokens' => []],
    ])->assertStatus(422);

    $this->actingAs($admin)->delete(route('admin.report-templates.destroy', $report))
        ->assertRedirect(route('admin.report-templates.index'));
    expect(ReportTemplate::query()->count())->toBe(0)
        ->and(Template::query()->whereNotNull('report_template_id')->count())->toBe(0);
});

it('syncs a report draft for the printed-template editor', function () {
    Storage::fake('public');
    $admin = recordsUser(2);

    $response = $this->actingAs($admin)->postJson(route('admin.report-templates.draft.sync'), [
        'name' => 'Draft report', 'definition' => rtDefinition('Organization Officers'),
    ])->assertOk();

    $draft = app(\App\Services\FormPrintTemplateService::class)->readDraft($response->json('draftId'));
    expect($draft['owner'])->toBe('report')
        ->and(collect($draft['tokens'])->pluck('key'))->toContain('orgs', 'orgs.officers')
        ->and($response->json('slots'))->toHaveCount(1);
});

it('lists seeded reports and generates them with parameters', function () {
    Storage::fake('public');
    [$alpha, $beta] = rtFixture();
    (new ReportTemplateSeeder)->run();
    $admin = recordsUser(2);

    $this->actingAs($admin)->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Registered Organizations')
        ->assertSee('Organization Officers')
        ->assertSee('Beta Organization');

    $captured = null;
    $this->partialMock(DocxTemplateService::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('toPdf')->andReturnUsing(function ($docx) use (&$captured) {
            $captured = rtDocxText($docx);
            File::put(dirname($docx).'/out.pdf', '%PDF-1.4');

            return dirname($docx).'/out.pdf';
        });
    });

    $officers = ReportTemplate::query()->where('name', 'Organization Officers')->sole();
    $response = $this->actingAs($admin)->get(route('reports.generate', [
        'reportTemplate' => $officers, 'format' => 'pdf', 'params' => ['organization' => $beta->getKey()],
    ]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('organization-officers-')
        ->and($captured)->toContain('Beta Organization')
        ->and($captured)->toContain('Bert')
        ->and($captured)->not->toContain('Alpha Organization');

    $docx = $this->actingAs($admin)->get(route('reports.generate', ['reportTemplate' => ReportTemplate::query()->where('name', 'Registered Organizations')->sole(), 'format' => 'docx']));
    $docx->assertOk();
    expect($docx->headers->get('Content-Type'))->toContain('wordprocessingml');
});

it('limits the Reports page to each template\'s audience', function () {
    Storage::fake('public');
    $org = recordsOrganization('Audience Org');
    $officer = recordsUser(3);
    DB::table('organization_officers')->insert([
        'role' => 'officer', 'organization' => $org->getKey(), 'user' => $officer->getKey(),
        'registered_at' => now(),
    ]);
    $admin = recordsUser(2);
    $plain = recordsUser(3);
    $definition = rtDefinition('Registered Organizations');

    foreach (['all' => 'Everyone Report', 'admins' => 'Admins Report', 'officers' => 'Officers Report'] as $audience => $name) {
        ReportTemplate::query()->create(['name' => $name, 'audience' => $audience, 'definition' => $definition, 'is_active' => true]);
    }

    $this->actingAs($officer)->get(route('reports.index'))
        ->assertOk()->assertSee('Everyone Report')->assertSee('Officers Report')->assertDontSee('Admins Report');
    $this->actingAs($admin)->get(route('reports.index'))
        ->assertOk()->assertSee('Everyone Report')->assertSee('Admins Report')->assertDontSee('Officers Report');
    $this->actingAs($plain)->get(route('reports.index'))->assertForbidden();

    $adminsOnly = ReportTemplate::query()->where('audience', 'admins')->sole();
    $officersOnly = ReportTemplate::query()->where('audience', 'officers')->sole();
    $this->actingAs($officer)->get(route('reports.generate', $adminsOnly))->assertNotFound();
    $this->actingAs($admin)->get(route('reports.generate', $officersOnly))->assertNotFound();

    $this->actingAs($officer)->get('/admin/reports')->assertRedirect('/reports');
    $this->actingAs($officer)->get(route('admin.report-templates.index'))->assertForbidden();
});

it('shows the Reports link in the sidebar for officers', function () {
    $org = recordsOrganization('Menu Org');
    $officer = recordsUser(3);
    DB::table('organization_officers')->insert([
        'role' => 'president', 'organization' => $org->getKey(), 'user' => $officer->getKey(), 'registered_at' => now(),
    ]);

    $this->actingAs($officer)->get(route('reports.index'))->assertOk()->assertSee('href="/reports"', false);
});

it('returns generation failures as JSON for the Reports page modal', function () {
    Storage::fake('public');
    $admin = recordsUser(2);
    $report = ReportTemplate::query()->create([
        'name' => 'No slots', 'audience' => 'admins', 'is_active' => true,
        'definition' => rtDefinition('Registered Organizations'),
    ]);

    $this->actingAs($admin)->getJson(route('reports.generate', $report))
        ->assertStatus(500)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'no printed template'));
});
