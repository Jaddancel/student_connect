<?php

use App\Models\AppSetting;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\ReportTemplate;
use App\Models\ScoringCategory;
use App\Models\ScoringCriterion;
use App\Models\ScoringRule;
use App\Models\Template;
use App\Services\BackupService;
use App\Services\ConfigBackup\ConfigExporter;
use App\Services\ConfigBackup\ConfigImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('backups');
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    Queue::fake();
});

function dbBackupDir(): string
{
    return (string) config('backup.backup.name', config('app.name', 'laravel-backup'));
}

function configBackupDir(): string
{
    return dbBackupDir().'-config';
}

/**
 * A configured system: a form tied to an organization with a field and a
 * printed template, a report template, a tally rule pointing at the form and
 * an accreditation setting listing it.
 *
 * @return array{form: Form, rule: ScoringRule, report: ReportTemplate, orgId: int, userId: int}
 */
function seedConfiguration(): array
{
    $userId = (int) recordsUser(2)->getKey();
    $detailId = DB::table('organization_details')->insertGetId(['name' => 'Cfg Org', 'detail_text' => 'x', 'initials' => 'CO']);
    $orgId = DB::table('organizations')->insertGetId(['organization_type' => 1, 'detail' => $detailId]);

    $form = Form::create([
        'name' => 'Club Survey', 'route_name' => 'club-survey', 'is_active' => true, 'is_published' => true,
        'organization_id' => $orgId, 'created_by' => $userId, 'icon' => null, 'show_in_sidebar' => false,
        'layout' => ['rows' => [['columns' => [['span' => 12, 'fields' => ['q1']]]]]],
    ]);
    FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'q1', 'field_label' => 'Question 1',
        'field_type' => 'text', 'is_required' => true, 'field_order' => 1,
    ]);
    Storage::disk('public')->put('form-templates/'.$form->id.'/printed-template.docx', 'DOCX-ORIGINAL');
    Template::create([
        'form_id' => $form->id, 'organization_id' => $orgId, 'uploaded_by' => $userId, 'template_name' => 'Survey print',
        'docx_path' => 'form-templates/'.$form->id.'/printed-template.docx', 'version' => 3, 'is_active' => true, 'slot_order' => 0,
    ]);

    $report = ReportTemplate::create([
        'name' => 'Org Summary', 'audience' => 'admins', 'is_active' => true, 'created_by' => $userId,
        'definition' => ['parameters' => [], 'tokens' => [[
            'id' => 'orgs', 'kind' => 'group', 'entity' => 'organizations',
            'where' => [['column' => 'organization_id', 'op' => '=', 'value' => $orgId], ['column' => 'organization_id', 'op' => '=', 'param' => 'organization']],
        ]]],
    ]);
    Storage::disk('public')->put('report-templates/'.$report->id.'/report.docx', 'REPORT-DOCX');
    Template::create([
        'report_template_id' => $report->id, 'template_name' => 'Report doc',
        'docx_path' => 'report-templates/'.$report->id.'/report.docx', 'version' => 1, 'is_active' => true, 'slot_order' => 0,
    ]);

    ScoringCategory::query()->updateOrCreate(['key' => 'cfgtest'], ['label' => 'Config Test', 'cap' => 10, 'sort_order' => 99]);
    $criterion = ScoringCriterion::create(['key' => 'cfgtest_survey', 'category_key' => 'cfgtest', 'label' => 'Filed a survey', 'weight' => 2, 'sort_order' => 1]);
    $rule = ScoringRule::create([
        'criterion_id' => $criterion->getKey(), 'enabled' => true, 'updated_by' => $userId,
        'trigger' => ['when' => ['source' => 'form_submission', 'status' => 'approved', 'form_id' => $form->id], 'then' => ['add' => ['kind' => 'const', 'value' => 1]]],
    ]);

    AppSetting::put('accreditation.conditions', ['required_forms' => [$form->id]]);
    AppSetting::put('accreditation.notify_days', 14);

    return compact('form', 'rule', 'report', 'orgId', 'userId');
}

function exportConfig(): string
{
    $path = tempnam(sys_get_temp_dir(), 'cfg_').'.zip';
    app(ConfigExporter::class)->export($path);

    return $path;
}

it('exports configuration without organization or user ties and with natural-key references', function () {
    seedConfiguration();
    $zip = new ZipArchive;
    $zip->open(exportConfig());

    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    $config = json_decode($zip->getFromName('config.json'), true);
    $raw = $zip->getFromName('config.json');

    expect($manifest['type'])->toBe('configuration')
        ->and($raw)->not->toContain('organization_id": ')
        ->and($raw)->not->toContain('created_by')
        ->and($raw)->not->toContain('uploaded_by')
        ->and($raw)->not->toContain('updated_by');

    $form = collect($config['forms'])->firstWhere('ref.route_name', 'club-survey');
    expect($form['attributes'])->not->toHaveKey('organization_id')
        ->and($form['attributes']['show_in_sidebar'])->toBeFalse()
        ->and($zip->getFromName($form['templates'][0]['file']))->toBe('DOCX-ORIGINAL');

    $rule = collect($config['scoring']['rules'])->firstWhere('criterion_key', 'cfgtest_survey');
    expect($rule['trigger']['when'])->not->toHaveKey('form_id')
        ->and($rule['trigger']['when']['form']['route_name'])->toBe('club-survey');

    $setting = collect($config['settings'])->firstWhere('key', 'accreditation.conditions');
    expect($setting['value']['required_forms'][0]['route_name'])->toBe('club-survey');

    // A literal organization filter is blanked; the session-organization param is kept.
    $where = collect($config['report_templates'])->firstWhere('attributes.name', 'Org Summary')['attributes']['definition']['tokens'][0]['where'];
    expect($where[0]['value'])->toBeNull()->and($where[1]['param'])->toBe('organization');

    expect(DB::table('requests')->count())->toBe(0); // export is read-only
});

it('merges a configuration backup: updates, re-creates and never deletes', function () {
    ['form' => $form, 'report' => $report, 'orgId' => $orgId] = seedConfiguration();
    $zipPath = exportConfig();

    // Drift after the backup.
    $form->update(['name' => 'Renamed']);
    $form->fields()->where('field_key', 'q1')->update(['field_label' => 'Changed']);
    Storage::disk('public')->put('form-templates/'.$form->id.'/printed-template.docx', 'DOCX-EDITED');
    ScoringRule::query()->delete();
    $report->delete();
    AppSetting::put('accreditation.notify_days', 3);
    $extra = Form::create(['name' => 'Not in backup', 'route_name' => 'not-in-backup', 'is_active' => true, 'is_published' => true]);

    $summary = app(ConfigImporter::class)->import($zipPath);

    $form->refresh();
    expect($form->name)->toBe('Club Survey')
        ->and($form->organization_id)->toBe($orgId) // local tie kept on merge
        ->and($form->fields()->first()->field_label)->toBe('Question 1')
        ->and(Form::find($extra->id))->not->toBeNull()
        ->and(AppSetting::get('accreditation.notify_days'))->toBe(14);

    $template = $form->activeTemplates()->first();
    expect($template->version)->toBe(4)
        ->and($template->docx_path)->not->toBe('form-templates/'.$form->id.'/printed-template.docx')
        ->and(Storage::disk('public')->get($template->docx_path))->toBe('DOCX-ORIGINAL');

    $rule = ScoringRule::query()->sole();
    expect($rule->trigger['when']['form_id'])->toBe($form->id)->and($rule->enabled)->toBeTrue();

    $restoredReport = ReportTemplate::query()->where('name', 'Org Summary')->sole();
    expect(Storage::disk('public')->get($restoredReport->activeTemplates()->first()->docx_path))->toBe('REPORT-DOCX')
        ->and($restoredReport->created_by)->toBeNull();

    expect($summary['created'])->toMatchArray(['scoring_rules' => 1, 'report_templates' => 1])
        ->and($summary['updated'])->toHaveKey('forms')
        ->and($summary['warnings'])->toBe([]);
});

it('remaps form references to new ids when forms are recreated', function () {
    ['form' => $form] = seedConfiguration();
    $zipPath = exportConfig();

    $oldId = $form->id;
    $form->delete();

    app(ConfigImporter::class)->import($zipPath);

    $recreated = Form::query()->where('route_name', 'club-survey')->sole();
    expect($recreated->id)->not->toBe($oldId)
        ->and($recreated->organization_id)->toBeNull()
        ->and($recreated->created_by)->toBeNull()
        ->and($recreated->show_in_sidebar)->toBeFalse()
        ->and($recreated->request_type_id)->not->toBeNull()
        ->and($recreated->fields()->count())->toBe(1)
        ->and($recreated->activeTemplates()->first()->organization_id)->toBeNull()
        ->and(ScoringRule::query()->sole()->trigger['when']['form_id'])->toBe($recreated->id)
        ->and(AppSetting::get('accreditation.conditions'))->toBe(['required_forms' => [$recreated->id]]);
});

it('disables tally rules and drops settings that point at forms missing from the backup', function () {
    ['form' => $form] = seedConfiguration();
    $zipPath = exportConfig();

    // Simulate an archive whose form section no longer has the referenced form.
    $zip = new ZipArchive;
    $zip->open($zipPath);
    $config = json_decode($zip->getFromName('config.json'), true);
    $config['forms'] = [];
    $zip->addFromString('config.json', json_encode($config));
    $zip->close();
    $form->delete();

    $summary = app(ConfigImporter::class)->import($zipPath);

    expect(ScoringRule::query()->sole()->enabled)->toBeFalse()
        ->and(AppSetting::get('accreditation.conditions'))->toBe(['required_forms' => []])
        ->and($summary['warnings'])->toHaveCount(2);
});

it('rejects unsafe or incompatible configuration archives without changing anything', function (Closure $tamper, string $message) {
    seedConfiguration();
    $zipPath = exportConfig();
    $zip = new ZipArchive;
    $zip->open($zipPath);
    $tamper($zip);
    $zip->close();

    Form::query()->update(['name' => 'Untouched']);

    expect(fn () => app(ConfigImporter::class)->import($zipPath))->toThrow(RuntimeException::class, $message);
    expect(Form::query()->where('name', '!=', 'Untouched')->count())->toBe(0);
})->with([
    'path traversal entry' => [fn (ZipArchive $z) => $z->addFromString('../evil.php', '<?php'), 'unexpected file'],
    'newer schema' => [function (ZipArchive $z) {
        $m = json_decode($z->getFromName('manifest.json'), true);
        $m['migrations'][] = '2999_01_01_000000_future';
        $z->addFromString('manifest.json', json_encode($m));
    }, 'newer database schema'],
    'invalid payload' => [function (ZipArchive $z) {
        $c = json_decode($z->getFromName('config.json'), true);
        $c['forms'][0]['attributes']['route_name'] = '../x';
        $z->addFromString('config.json', json_encode($c));
    }, 'invalid'],
    'missing document' => [function (ZipArchive $z) {
        $c = json_decode($z->getFromName('config.json'), true);
        $c['forms'][0]['templates'] = [['name' => 'x', 'slot_order' => 0, 'file' => 'files/'.str_repeat('a', 40).'.docx']];
        $z->addFromString('config.json', json_encode($c));
    }, 'missing a template document'],
]);

it('creates, lists, archives and restores configuration backups through the backups page', function () {
    seedConfiguration();
    $admin = recordsUser(1);
    Storage::disk('backups')->put(dbBackupDir().'/2026-01-01-00-00-00.zip', 'db');

    $this->actingAs($admin)
        ->post(route('superadmin.backups.store'), ['type' => 'configuration'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Configuration backup created.');

    $service = app(BackupService::class);
    $config = collect($service->list())->firstWhere('type', 'configuration');
    expect($config['name'])->toStartWith('config-')
        ->and(Storage::disk('backups')->exists(configBackupDir().'/'.$config['name']))->toBeTrue()
        ->and(collect($service->list())->firstWhere('type', 'database')['name'])->toBe('2026-01-01-00-00-00.zip')
        ->and(DB::table('action_logs')->where('action', 'config_backup_created')->exists())->toBeTrue();

    $this->get(route('superadmin.backups.index'))->assertOk()->assertSee('Configuration')->assertSee($config['name']);

    Form::query()->where('route_name', 'club-survey')->update(['name' => 'Drifted']);

    $this->post(route('superadmin.backups.restore', $config['name']))
        ->assertRedirect()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Configuration merged from '.$config['name']) && str_contains($msg, 'safety backup'));

    expect(Form::query()->where('route_name', 'club-survey')->value('name'))->toBe('Club Survey')
        ->and(collect($service->list())->where('type', 'configuration'))->toHaveCount(2)
        ->and(DB::table('action_logs')->where('action', 'config_backup_restored')->exists())->toBeTrue();

    $service->archive($config['name']);
    expect(Storage::disk('backups')->exists(configBackupDir().'/archive/'.$config['name']))->toBeTrue()
        ->and(collect($service->listArchived())->firstWhere('name', $config['name'])['type'])->toBe('configuration');
    $this->get(route('superadmin.backups.archived'))->assertOk()->assertSee($config['name']);
});

it('detects and merges an uploaded configuration backup', function () {
    seedConfiguration();
    $path = exportConfig();
    Form::query()->where('route_name', 'club-survey')->update(['name' => 'Drifted']);

    $this->actingAs(recordsUser(1))
        ->post(route('superadmin.backups.restore-upload'), [
            'backup_file' => new UploadedFile($path, 'config-2026.zip', 'application/zip', null, true),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Configuration merged from uploaded file config-2026.zip'));

    expect(Form::query()->where('route_name', 'club-survey')->value('name'))->toBe('Club Survey');
});

it('keeps scheduled backups database-only and counts only database backups for the interval', function () {
    Storage::disk('backups')->put(configBackupDir().'/config-2026-01-01-00-00-00.zip', 'x');

    $this->mock(BackupService::class, function ($mock) {
        $mock->shouldReceive('list')->andReturn([
            ['name' => 'config-new.zip', 'type' => 'configuration', 'size' => 1, 'last_modified' => now()->timestamp],
            ['name' => 'old.zip', 'type' => 'database', 'size' => 1, 'last_modified' => now()->subDays(3)->timestamp],
        ]);
        $mock->shouldReceive('create')->once()->withNoArgs();
    });

    $this->artisan('backup:auto')->assertSuccessful();
});

it('leaves tally rules alone when their form was already missing where the backup was taken', function () {
    ['rule' => $rule] = seedConfiguration();
    $trigger = $rule->trigger;
    $trigger['when']['form_id'] = 999999;
    $rule->update(['trigger' => $trigger]);

    $summary = app(ConfigImporter::class)->import(exportConfig());

    $rule->refresh();
    expect($rule->enabled)->toBeTrue()
        ->and($rule->trigger['when']['form_id'])->toBe(999999)
        ->and($summary['warnings'])->toBe([]);
});
