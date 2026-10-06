<?php

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\Template;
use App\Reports\ReportDocxRenderer;
use App\Reports\ReportQueryEngine;
use App\Reports\SchemaCatalog;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function frTableForm(string $name, string $route, ?string $function, string $fieldKey, array $columns, ?array $rowTotal = null): Form
{
    $form = Form::query()->create([
        'name' => $name,
        'route_name' => $route,
        'system_function' => $function,
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>'],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id,
        'field_key' => $fieldKey,
        'field_label' => $name.' table',
        'field_type' => FieldType::TABLE_INPUT,
        'is_required' => false,
        'field_order' => 1,
        'field_options' => ['columns' => $columns, 'row_total' => $rowTotal ?? ['key' => null, 'label' => null, 'multiply' => []]],
    ]);

    return $form;
}

function frFundForm(): Form
{
    return frTableForm('Organization Fund Form', 'organization-fund-form', null, 'funds_table', [
        ['key' => 'fund_source', 'label' => 'Fund Source', 'type' => 'text'],
        ['key' => 'amount', 'label' => 'Amount', 'type' => 'number'],
    ]);
}

function frExpenseForm(): Form
{
    return frTableForm('After Event Report', 'after-event-report', SystemFunction::AFTER_EVENT_REPORT, 'expenses_table', [
        ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
        ['key' => 'quantity', 'label' => 'Quantity', 'type' => 'number'],
        ['key' => 'price_per_unit', 'label' => 'Price Per Unit', 'type' => 'number'],
    ], ['key' => 'total_row', 'label' => 'Total', 'op' => 'multiply', 'multiply' => ['quantity', 'price_per_unit']]);
}

/** A fund submission with a document-generation request; $approved: true, false (rejected) or null (pending). */
function frFundSubmission(Form $form, int $organizationId, array $rows, ?bool $approved, string $approvedAt): FormSubmission
{
    $submission = FormSubmission::query()->create([
        'form_id' => $form->id,
        'organization_id' => $organizationId,
        'payload' => ['funds_table' => $rows],
        'submitted_at' => $approvedAt,
    ]);
    $request = ActionRequest::query()->create([
        'action' => 'test',
        'action_type' => 3,
        'form_id' => $form->id,
        'organization_id' => $organizationId,
        'requested_at' => $approvedAt,
        'payload' => ['form_id' => $form->id, 'submission_id' => (int) $submission->getKey(), 'organization_id' => $organizationId],
    ]);
    if ($approved !== null) {
        Approval::query()->create([
            'request' => $request->getKey(),
            'admin' => recordsUser(2)->getKey(),
            'approved_at' => $approvedAt,
            'is_rejected' => ! $approved,
        ]);
    }

    return $submission;
}

function frExpenseSubmission(Form $form, int $organizationId, string $title, Carbon $endedAt, array $rows): FormSubmission
{
    $detail = EventDetail::query()->create([
        'name' => $title,
        'location' => 'Gym',
        'desc_text' => '',
        'start_time' => $endedAt->copy()->subHours(2),
        'end_time' => $endedAt,
    ]);
    $event = Event::query()->create(['organization' => $organizationId, 'creator' => null, 'event_detail' => (int) $detail->getKey()]);

    return FormSubmission::query()->create([
        'form_id' => $form->id,
        'organization_id' => $organizationId,
        'event_id' => (int) $event->getKey(),
        'payload' => ['expenses_table' => $rows],
        'submitted_at' => now(),
    ]);
}

function frDefinition(): array
{
    return ReportTemplateSeeder::definitions()['Financial Report']['definition'];
}

/** Runs the report as an admin whose selected (session) organization is $organizationId. */
function frRun(?int $organizationId, array $input = []): array
{
    test()->actingAs(recordsUser(2));
    session(['active_organization_id' => $organizationId]);

    return app(ReportQueryEngine::class)->run(frDefinition(), $input);
}

function frFixture(): array
{
    $org = recordsOrganization('Alpha Organization', 'ALP');
    $other = recordsOrganization('Beta Organization', 'BET');
    $funds = frFundForm();
    $expenses = frExpenseForm();

    return [$org, $other, $funds, $expenses];
}

beforeEach(function () {
    Storage::fake('public');
    app(SchemaCatalog::class)->forget();
    Semester::create(['name' => 'Current Sem', 'semester_number' => 1, 'starts_at' => Carbon::today()->subDays(60), 'vacation_days' => 0]);
});

it('offers the form tables only when their forms exist', function () {
    expect(app(SchemaCatalog::class)->has('organization_funds'))->toBeFalse()
        ->and(app(SchemaCatalog::class)->has('event_expenses'))->toBeFalse();

    frFixture();
    app(SchemaCatalog::class)->forget();
    $catalog = app(SchemaCatalog::class);

    expect($catalog->has('organization_funds'))->toBeTrue()
        ->and($catalog->hasColumn('organization_funds', 'fund_source'))->toBeTrue()
        ->and($catalog->hasColumn('event_expenses', 'total_row'))->toBeTrue()
        ->and($catalog->hasColumn('event_expenses', 'event_name'))->toBeTrue()
        ->and($catalog->relation('organization_funds', 'organization')['table'])->toBe('organizations')
        ->and($catalog->relation('organizations', 'event_expenses')['type'])->toBe('has_many');
});

it('collects funds from the latest approved fund submission of the organization', function () {
    [$org, $other, $funds] = frFixture();
    $orgId = (int) $org->getKey();

    frFundSubmission($funds, $orgId, [['fund_source' => 'Old dues', 'amount' => '999']], true, '2026-01-01 08:00:00');
    frFundSubmission($funds, $orgId, [['fund_source' => 'Membership fees', 'amount' => '1500'], ['fund_source' => 'Donations', 'amount' => '500.50']], true, '2026-02-01 08:00:00');
    frFundSubmission($funds, $orgId, [['fund_source' => 'Rejected', 'amount' => '7']], false, '2026-03-01 08:00:00');
    frFundSubmission($funds, $orgId, [['fund_source' => 'Pending', 'amount' => '8']], null, '2026-04-01 08:00:00');
    frFundSubmission($funds, (int) $other->getKey(), [['fund_source' => 'Beta only', 'amount' => '42']], true, '2026-05-01 08:00:00');

    $data = frRun($orgId)['data'];

    expect(array_column($data['funds'], 'fund_source'))->toBe(['Membership fees', 'Donations'])
        ->and(array_column($data['funds'], 'amount'))->toBe(['1,500.00', '500.50'])
        ->and($data['total_funds'])->toBe('2,000.50');
});

it('aggregates submitted after event expenses of the current semester', function () {
    [$org, $other, , $expenses] = frFixture();
    $orgId = (int) $org->getKey();
    $in = Carbon::today()->subDays(10);

    frExpenseSubmission($expenses, $orgId, 'Foundation Day', $in, [
        ['item' => 'Tarpaulin', 'quantity' => '2', 'price_per_unit' => '150', 'total_row' => 300],
        ['item' => 'Snacks', 'quantity' => '10', 'price_per_unit' => '25.5', 'total_row' => 255],
    ]);
    frExpenseSubmission($expenses, $orgId, 'Seminar', $in->copy()->subDays(5), [
        ['item' => 'Certificates', 'quantity' => '4', 'price_per_unit' => '50', 'total_row' => 200],
    ]);
    // Ended before the semester began, and another organization's: both excluded.
    frExpenseSubmission($expenses, $orgId, 'Last Semester Trip', Carbon::today()->subDays(120), [
        ['item' => 'Bus', 'quantity' => '1', 'price_per_unit' => '5000', 'total_row' => 5000],
    ]);
    frExpenseSubmission($expenses, (int) $other->getKey(), 'Beta Event', $in, [
        ['item' => 'Beta item', 'quantity' => '1', 'price_per_unit' => '1', 'total_row' => 1],
    ]);

    $result = frRun($orgId)['data'];

    expect(array_column($result['expenses'], 'item'))->toBe(['Certificates', 'Tarpaulin', 'Snacks'])
        ->and(array_column($result['expenses'], 'activity'))->toBe(['Seminar', 'Foundation Day', 'Foundation Day'])
        ->and($result['expenses'][1])->toMatchArray([
            'activity_date' => $in->copy()->subHours(2)->format('M j, Y'),
            'price_per_unit' => '150.00',
            'quantity' => '2',
            'total_price' => '300.00',
        ])
        ->and($result['total_expenses'])->toBe('755.00');
});

it('computes the balance of funds against expenses', function () {
    [$org, , $funds, $expenses] = frFixture();
    $orgId = (int) $org->getKey();
    frFundSubmission($funds, $orgId, [['fund_source' => 'Dues', 'amount' => '1000']], true, '2026-02-01 08:00:00');
    frExpenseSubmission($expenses, $orgId, 'Event', Carbon::today()->subDays(3), [
        ['item' => 'Banner', 'quantity' => '3', 'price_per_unit' => '100', 'total_row' => 300],
    ]);

    $data = frRun($orgId)['data'];

    expect($data['total_funds'])->toBe('1,000.00')
        ->and($data['total_expenses'])->toBe('300.00')
        ->and($data['balance'])->toBe('700.00');
});

it('prints the auditor and leaves the adviser blank', function () {
    [$org, , $funds, $expenses] = frFixture();
    $orgId = (int) $org->getKey();
    frFundSubmission($funds, $orgId, [['fund_source' => 'Dues', 'amount' => '1000']], true, '2026-02-01 08:00:00');
    frExpenseSubmission($expenses, $orgId, 'Welcome Party', Carbon::today()->subDays(3), [
        ['item' => 'Banner', 'quantity' => '3', 'price_per_unit' => '100', 'total_row' => 300],
    ]);

    $auditor = recordsUser(3, ['first_name' => 'Audrey', 'middle_name' => '', 'last_name' => 'Auditor']);
    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $orgId,
        'user' => (int) $auditor->getKey(),
        'position' => 'Auditor',
        'member_since' => '2026-01-15 00:00:00',
        'registered_at' => now(),
    ]);

    Storage::disk('public')->put('report-templates/financial.docx', ReportTemplateSeeder::document(ReportTemplateSeeder::definitions()['Financial Report']['layout']));
    $slot = new Template(['template_name' => 'Financial Report', 'docx_path' => 'report-templates/financial.docx']);

    $data = frRun($orgId)['data'];
    $path = app(ReportDocxRenderer::class)->render($slot, $data, recordsUser(2), $org);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    File::deleteDirectory(dirname($path));
    preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xml, $m);
    $text = html_entity_decode(implode(' | ', array_filter($m[1], fn ($t) => trim($t) !== '')));

    expect($text)->toContain('Alpha Organization')
        ->and($text)->toContain('S.Y. '.Semester::currentSchoolYear())
        ->and($text)->toContain('Dues')
        ->and($text)->toContain('Total Funds: 1,000.00')
        ->and($text)->toContain('Welcome Party')
        ->and($text)->toContain('Total Expenses: 300.00')
        ->and($text)->toContain('700.00')
        ->and($text)->toContain('Audrey')
        ->and($text)->toContain('Adviser')
        ->and($text)->not->toContain('{{');
});

it('always reports on the organization selected in the session', function () {
    [$org, $other, $funds] = frFixture();
    frFundSubmission($funds, (int) $org->getKey(), [['fund_source' => 'Alpha dues', 'amount' => '10']], true, '2026-02-01 08:00:00');
    frFundSubmission($funds, (int) $other->getKey(), [['fund_source' => 'Beta dues', 'amount' => '20']], true, '2026-02-01 08:00:00');

    // A submitted parameter cannot override the session's organization.
    $result = frRun((int) $other->getKey(), ['organization' => (int) $org->getKey()]);

    expect(array_column($result['data']['funds'], 'fund_source'))->toBe(['Beta dues'])
        ->and($result['params']['organization'])->toBe((int) $other->getKey());
});

it('refuses to generate without a usable selected organization', function () {
    [$org] = frFixture();

    expect(fn () => frRun(null))->toThrow(ValidationException::class, 'organization switcher');

    // An officer cannot report on an organization they do not belong to.
    $officer = recordsUser(3);
    test()->actingAs($officer);
    session(['active_organization_id' => (int) $org->getKey()]);
    expect(fn () => app(ReportQueryEngine::class)->run(frDefinition(), ['organization' => (int) $org->getKey()]))
        ->toThrow(ValidationException::class);
});

it('does not ask for an organization on the Reports page', function () {
    [$org] = frFixture();
    (new ReportTemplateSeeder)->run();
    $admin = recordsUser(2);

    $html = $this->actingAs($admin)->withSession(['active_organization_id' => (int) $org->getKey()])
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Financial Report')
        ->assertSee('For Alpha Organization (your selected organization).')
        ->getContent();

    // Only "Organization Officers" still asks for an organization.
    expect(substr_count($html, 'name="params[organization]"'))->toBe(1);
});

it('lets any report filter an organization column by the session organization', function () {
    [$org, $other] = frFixture();
    (new ReportTemplateSeeder)->run();
    $definition = ['parameters' => [], 'tokens' => [[
        'id' => 'orgs', 'kind' => 'group', 'name' => 'orgs', 'entity' => 'organizations',
        'where' => [['column' => 'organization_id', 'op' => '=', 'param' => '@session_organization']],
        'order' => [], 'limit' => null,
        'children' => [['id' => 'n', 'kind' => 'value', 'name' => 'org_name', 'mode' => 'field', 'path' => ['detail', 'name'], 'format' => ['type' => 'text', 'pattern' => null, 'fallback' => '']]],
    ]]];

    test()->actingAs(recordsUser(2));
    session(['active_organization_id' => (int) $other->getKey()]);
    $data = app(ReportQueryEngine::class)->run($definition)['data'];
    expect(array_column($data['orgs'], 'org_name'))->toBe(['Beta Organization']);

    session(['active_organization_id' => null]);
    expect(fn () => app(ReportQueryEngine::class)->run($definition))->toThrow(ValidationException::class, 'organization switcher');

    // Only organization columns can use it.
    $definition['tokens'][0]['where'] = [['column' => 'organization_type', 'op' => '=', 'param' => '@session_organization']];
    expect(fn () => app(ReportQueryEngine::class)->run($definition, preview: true))->toThrow(ValidationException::class, 'not an organization column');
});
