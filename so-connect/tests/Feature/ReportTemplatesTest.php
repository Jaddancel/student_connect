<?php

use App\Forms\SystemFunction;
use App\Models\Form;
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

function rtAccredit(int $organizationId, Form $form, string $approvedAt, bool $rejected = false): void
{
    $requestId = DB::table('requests')->insertGetId([
        'action' => 'Accreditation request',
        'action_type' => 1,
        'form_id' => $form->getKey(),
        'organization_id' => $organizationId,
        'requested_at' => $approvedAt,
    ], 'request_id');

    DB::table('approvals')->insert([
        'request' => $requestId,
        'approved_at' => $approvedAt,
        'is_rejected' => $rejected,
    ]);
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

it('filters all three accreditation reports by school year and excludes rejected applications', function () {
    [$alpha, $beta] = rtFixture();
    $form = Form::query()->create([
        'name' => 'Organization Accreditation',
        'route_name' => 'organization-accreditation-report-test',
        'system_function' => SystemFunction::ORG_ACCREDITATION,
        'is_active' => true,
        'is_published' => true,
    ]);

    rtAccredit((int) $alpha->getKey(), $form, '2025-09-15 10:00:00');
    rtAccredit((int) $beta->getKey(), $form, '2026-09-15 10:00:00');
    rtAccredit((int) $alpha->getKey(), $form, '2026-10-01 10:00:00', rejected: true);

    $engine = app(ReportQueryEngine::class);
    $organizations = $engine->run(rtDefinition('Accredited School Organizations'), [
        'school_year' => '2025–2026',
    ])['data'];
    $members = $engine->run(rtDefinition('Members of Accredited Organizations'), [
        'school_year' => '2025–2026',
    ])['data']['members'];
    $officers = $engine->run(rtDefinition('Officers of Accredited Organizations'), [
        'school_year' => '2025–2026',
    ])['data']['officers'];

    expect($organizations['school_year'])->toBe('2025–2026')
        ->and($organizations['total_organizations'])->toBe('1')
        ->and($organizations['organizations'])->toHaveCount(1)
        ->and($organizations['organizations'][0]['name'])->toBe('Alpha Organization')
        ->and(array_column($members, 'first_name'))->toBe(['Mark'])
        ->and(array_column($officers, 'first_name'))->toContain('Olive', 'Paula')
        ->and(array_column($officers, 'first_name'))->not->toContain('Bert');

    $nextYear = $engine->run(rtDefinition('Accredited School Organizations'), [
        'school_year' => '2026–2027',
    ])['data'];
    expect($nextYear['organizations'])->toHaveCount(1)
        ->and($nextYear['organizations'][0]['name'])->toBe('Beta Organization');
});

it('filters all three accreditation reports by organization type and organization', function () {
    [$alpha, $beta] = rtFixture();
    $form = Form::query()->create([
        'name' => 'Organization Accreditation',
        'route_name' => 'organization-accreditation-report-test',
        'system_function' => SystemFunction::ORG_ACCREDITATION,
        'is_active' => true,
        'is_published' => true,
    ]);
    rtAccredit((int) $alpha->getKey(), $form, '2026-09-15 10:00:00');
    rtAccredit((int) $beta->getKey(), $form, '2026-09-16 10:00:00');

    $engine = app(ReportQueryEngine::class);
    $validator = app(ReportDefinitionValidator::class);
    $year = ['school_year' => '2026–2027'];

    foreach (['Accredited School Organizations', 'Members of Accredited Organizations', 'Officers of Accredited Organizations'] as $name) {
        $definition = $validator->validate(rtDefinition($name));
        expect(array_column($definition['parameters'], 'name'))->toContain('organization_type', 'organization');
    }

    $byType = $engine->run(rtDefinition('Accredited School Organizations'), $year + ['organization_type' => '2'])['data'];
    expect($byType['organizations'])->toHaveCount(1)
        ->and($byType['organizations'][0]['name'])->toBe('Beta Organization')
        ->and($byType['total_organizations'])->toBe('1');

    $byOrg = $engine->run(rtDefinition('Accredited School Organizations'), $year + ['organization' => (string) $alpha->getKey()])['data'];
    expect(array_column($byOrg['organizations'], 'name'))->toBe(['Alpha Organization']);

    $members = $engine->run(rtDefinition('Members of Accredited Organizations'), $year + ['organization' => (string) $alpha->getKey()])['data']['members'];
    $officers = $engine->run(rtDefinition('Officers of Accredited Organizations'), $year + ['organization_type' => '2'])['data']['officers'];
    expect(array_column($members, 'first_name'))->toBe(['Mark'])
        ->and(array_column($officers, 'first_name'))->toBe(['Bert']);

    $none = $engine->run(rtDefinition('Members of Accredited Organizations'), $year + ['organization_type' => '2', 'organization' => (string) $alpha->getKey()])['data']['members'];
    expect($none)->toBeEmpty();

    $options = $engine->parameterOptions(rtDefinition('Accredited School Organizations')['parameters'][1]);
    expect(array_column($options, 'label'))->toContain('Socio-Civic', 'Religious');
});

it('offers school years and defaults accreditation reports to the current school year', function () {
    \Illuminate\Support\Carbon::setTestNow('2026-10-16 08:00:00');
    $definition = app(ReportDefinitionValidator::class)
        ->validate(rtDefinition('Accredited School Organizations'));
    $parameter = $definition['parameters'][0];
    $engine = app(ReportQueryEngine::class);

    expect($engine->parameterDefault($parameter))->toBe('2026–2027')
        ->and(array_column($engine->parameterOptions($parameter), 'value'))->toContain('2026–2027');

    \Illuminate\Support\Carbon::setTestNow();
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

it('computes arithmetic over sibling value tokens, per row and at the top level', function () {
    [$alpha, $beta] = rtFixture();
    $definition = ['tokens' => [
        ['kind' => 'value', 'name' => 'total_orgs', 'mode' => 'aggregate', 'from' => 'organizations', 'fn' => 'count'],
        ['kind' => 'value', 'name' => 'doubled', 'mode' => 'compute', 'expression' => 'total_orgs * 2 + 0.5'],
        ['kind' => 'group', 'name' => 'orgs', 'entity' => 'organizations', 'order' => [['column' => 'organization_id']], 'children' => [
            ['kind' => 'value', 'name' => 'officer_count', 'mode' => 'aggregate', 'fn' => 'count', 'relation' => 'organization_officers',
                'where' => [['column' => 'role', 'op' => 'in', 'value' => ['officer', 'president']]]],
            // Declared before the token it reads: dependency order still applies.
            ['kind' => 'value', 'name' => 'plus_one', 'mode' => 'compute', 'expression' => 'score + 1'],
            ['kind' => 'value', 'name' => 'score', 'mode' => 'compute', 'expression' => '(officer_count + 3) * -2 % 5',
                'format' => ['type' => 'number', 'pattern' => '1']],
            ['kind' => 'value', 'name' => 'ratio', 'mode' => 'compute', 'expression' => 'officer_count / (officer_count - officer_count)',
                'format' => ['fallback' => 'n/a']],
        ]],
    ]];

    $data = app(ReportQueryEngine::class)->run($definition)['data'];

    expect($data['doubled'])->toBe('4.5')
        // Alpha has two officers: (2 + 3) * -2 % 5 is -0, which prints as 0.
        ->and($data['orgs'][0])->toMatchArray(['officer_count' => '2', 'score' => '0.0', 'plus_one' => '1', 'ratio' => 'n/a'])
        ->and($data['orgs'][1])->toMatchArray(['officer_count' => '1', 'score' => '-3.0', 'plus_one' => '-2']);
});

it('rejects invalid compute formulas', function (string $expression, string $message) {
    $definition = ['tokens' => [
        ['kind' => 'value', 'name' => 'a', 'mode' => 'aggregate', 'from' => 'organizations', 'fn' => 'count'],
        ['kind' => 'value', 'name' => 'b', 'mode' => 'compute', 'expression' => $expression],
        ['kind' => 'value', 'name' => 'c', 'mode' => 'compute', 'expression' => 'b + 1'],
    ]];

    try {
        app(ReportDefinitionValidator::class)->validate($definition);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect(implode(' ', $e->errors()['definition']))->toContain($message);
    }
})->with([
    'empty' => ['', 'enter a formula'],
    'unknown token' => ['a + nope', '"nope" is not a value token'],
    'self reference' => ['b + 1', 'its own value'],
    'loop' => ['c', 'in a loop'],
    'bad character' => ['a ^ 2', 'unexpected "^"'],
    'unclosed' => ['(a + 1', 'not closed'],
    'dangling operator' => ['a +', 'ends unexpectedly'],
    'function call' => ['sqrt(a)', 'unexpected "("'],
]);

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
        ->assertSee('Accredited School Organizations')
        ->assertSee('Members of Accredited Organizations')
        ->assertSee('Officers of Accredited Organizations')
        ->assertSee('Registered Organizations')
        ->assertSee('Organization Officers')
        ->assertSee('Beta Organization');

    // Organization and school year come from the global report filters; other parameters stay on the report's form.
    $page = $this->actingAs($admin)->get(route('reports.index'))->getContent();
    $accredited = ReportTemplate::query()->where('name', 'Accredited School Organizations')->sole();
    $form = rtReportForm($page, $accredited);
    expect($form)->toContain('name="params[school_year]" :value="filters.schoolYear"')
        ->and($form)->toContain('name="params[organization]" :value="filters.organization"')
        ->and($form)->toContain('name="params[organization_type]"');

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
