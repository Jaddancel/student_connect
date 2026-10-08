<?php

use App\Forms\FieldType;
use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Request as ActionRequest;
use App\Models\ScoringRule;
use App\Models\Semester;
use App\Models\User;
use App\Models\Workplan;
use App\Services\AccreditationService;
use App\Services\AfterEventReportService;
use App\Services\Scoring\ScoringRuleEngine;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\SeedData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::query()->where('user_email', 'baseline-superadmin@tests.local')->delete();
});

it('seeds historical accounts and configuration-compliant workflows with exact form caps', function () {
    fake()->seed(20261007);
    Storage::fake('public');
    Storage::fake('seed-assets');
    $assets = Storage::disk('seed-assets');
    foreach (['portraits', 'event_photo'] as $folder) {
        foreach (range(1, 5) as $index) {
            $image = imagecreatetruecolor(120, 120);
            imagefill($image, 0, 0, imagecolorallocate($image, $index * 40, 50, 100));
            ob_start();
            imagejpeg($image);
            $assets->put($folder.'/'.$index.'.jpg', ob_get_clean());
            imagedestroy($image);
        }
    }
    config(['seeding.assets_path' => $assets->path(''), 'documents.disk' => 'public']);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->orderBy('user_id')->first()->user_type)->toBe(User::TYPE_SUPERADMIN)
        ->and(User::query()->where('user_type', User::TYPE_SUPERADMIN)->count())->toBe(1)
        ->and(User::query()->where('user_type', User::TYPE_ADMIN)->count())->toBe(3)
        ->and(User::query()->orderBy('user_id')->skip(1)->take(3)->pluck('user_type')->all())->toBe([2, 2, 2])
        ->and(Organization::query()->count())->toBe(71)
        ->and(GeneratedDocument::query()->count())->toBe(0)
        ->and(Form::query()->count())->toBe(10)
        ->and(FormSubmission::query()->count())->toBeGreaterThanOrEqual(450 + 71 * 5)
        ->and(ActionRequest::query()->count())->toBe(450);

    $hashes = [];
    foreach (User::query()->with('profile')->get() as $user) {
        $profile = $user->getRelation('profile');
        expect($profile)->not->toBeNull();
        Storage::disk('public')->assertExists($profile->photo);
        Storage::disk('public')->assertExists($profile->signature_path);
        $path = Storage::disk('public')->path($profile->signature_path);
        $size = getimagesize($path);
        expect([$size[0], $size[1], $size[2]])->toBe([100, 100, IMAGETYPE_JPEG]);
        $hashes[] = sha1_file($path);
        expect(Carbon::parse($user->user_created_at)->between(Carbon::parse(SeedData::START), Carbon::parse(SeedData::END)))->toBeTrue();
    }
    expect(count(array_unique($hashes)))->toBe(count($hashes));

    foreach (Organization::query()->get() as $organization) {
        $officers = $organization->officersOfThisOrganization()->whereIn('role', ['officer', 'president'])->get();
        expect($officers)->toHaveCount(15)
            ->and($officers->pluck('user')->unique())->toHaveCount(15);
        foreach (['President', 'Secretary', 'Auditor', 'Treasurer'] as $position) {
            expect($officers->where('position', $position))->toHaveCount(1);
        }
        expect($officers->where('position', 'Others'))->toHaveCount(11);
    }
    expect(DB::table('organization_officers')->whereIn('role', ['officer', 'president'])
        ->select('user')->groupBy('user')->havingRaw('COUNT(*) > 1')->get())->not->toBeEmpty();

    $workplanOrgs = Workplan::query()->pluck('organization_id')->all();
    expect(Workplan::query()->count())->toBe(50)
        ->and(Workplan::query()->where('status', 'finalized')->count())->toBe(35);

    foreach (Form::query()->with('fields')->get() as $form) {
        $submissions = FormSubmission::query()->where('form_id', $form->getKey())->get();
        $requests = ActionRequest::query()->where('form_id', $form->getKey())->get();
        if ($form->system_function === 'after_event_report') {
            expect($requests)->toHaveCount(0)
                ->and($submissions->pluck('event_id')->unique())->toHaveCount($submissions->count())
                ->and($submissions->countBy('organization_id')->keys())->toHaveCount(71)
                ->and(Post::query()->where('tag', 'Event')->where('status', 'published')->count())->toBe($submissions->count());
            foreach ($submissions->countBy('organization_id') as $count) {
                expect($count)->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(10);
            }
        } else {
            expect($submissions)->toHaveCount(50)
                ->and($requests)->toHaveCount(50);
            $decisions = Approval::query()->whereIn('request', $requests->modelKeys())
                ->where(fn ($q) => $q->whereNull('stage')->orWhereIn('stage', ['admin', 'president']))
                ->get()->unique('request');
            expect($decisions->where('is_rejected', true))->toHaveCount(5);
            if ($form->system_function === 'new_organization_registration') {
                expect($decisions->where('is_rejected', false))->toHaveCount(15);
            } else {
                foreach ([true, false] as $priority) {
                    $cohort = $requests->filter(fn ($request) => in_array($request->organization_id, $workplanOrgs, true) === $priority);
                    $accepted = $decisions->whereIn('request', $cohort->modelKeys())->where('is_rejected', false)->count();
                    expect($accepted)->toBe((int) round($cohort->count() * ($priority ? .70 : .30)));
                }
            }
        }

        foreach ($submissions as $submission) {
            $payload = $submission->payload;
            expect($submission->submitted_at->between(Carbon::parse(SeedData::START), Carbon::parse(SeedData::END)))->toBeTrue();
            $rules = [];
            foreach ($form->fields as $field) {
                $options = (array) $field->field_options;
                $options['source_values'] = Organization::query()->pluck('organization_id')->all();
                if (FieldType::isFileLike($field->field_type) || $field->field_type === FieldType::SIGNATURE) {
                    if ($field->is_required) {
                        expect($payload[$field->field_key] ?? null)->not->toBeEmpty();
                    }
                    foreach ((array) ($payload[$field->field_key] ?? []) as $path) {
                        Storage::disk('public')->assertExists($path);
                    }

                    continue;
                }
                $fieldRules = FieldType::validationRules($field->field_type, $field->is_required, $options);
                $rules[$field->field_key] = array_values(array_filter($fieldRules, fn ($rule) => $rule !== 'confirmed'));
                if ($field->field_type !== FieldType::MULTI_IMAGE) {
                    foreach (FieldType::nestedValidationRules($field->field_type, $options) as $suffix => $nested) {
                        $rules[$field->field_key.'.'.$suffix] = $nested;
                    }
                }
            }
            $validator = Validator::make($payload, $rules);
            expect($validator->errors()->all())->toBe([]);

            if ($form->system_function === 'after_event_report') {
                $photos = $payload['photo_documentation'];
                expect(count($photos))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(5)
                    ->and(count(array_unique($photos)))->toBe(count($photos));
                foreach ($photos as $path) {
                    Storage::disk('public')->assertExists($path);
                }
                $event = DB::table('events')->join('event_details', 'event_detail_id', '=', 'event_detail')
                    ->where('event_id', $submission->event_id)->first();
                expect(Carbon::parse($event->end_time)->addDays(3)->lte($submission->submitted_at))->toBeTrue();
                $semester = Semester::query()->where('starts_at', '<=', $submission->submitted_at)->orderByDesc('starts_at')->firstOrFail();
                expect(Carbon::parse($event->end_time)->between($semester->starts_at, $semester->endsAt()))->toBeTrue();
                expect($payload['total_expenses'])->toEqual(array_sum(array_column($payload['expenses_table'], 'total_row')));
            }
            if (! empty($payload['_seed_criterion'])) {
                $rule = ScoringRule::query()->whereHas('criterion', fn ($q) => $q->where('key', $payload['_seed_criterion']))->firstOrFail();
                $linked = $submission->event_id
                    ? app(AfterEventReportService::class)->sourceSubmission((int) $submission->event_id)?->payload ?? []
                    : [];
                expect(app(ScoringRuleEngine::class)->tallyRecord($rule->trigger, [
                    'values' => $payload, 'linked_values' => $linked, 'user_id' => $submission->submitted_by,
                ]))->toBeGreaterThan(0);
            }
        }
    }

    $accreditation = Form::query()->where('system_function', 'org_accreditation')->firstOrFail();
    $accreditedOrgs = ActionRequest::query()->where('form_id', $accreditation->getKey())->pluck('organization_id')->unique();
    expect($accreditedOrgs)->toHaveCount(13);
    foreach (ActionRequest::query()->where('form_id', $accreditation->getKey())->get() as $request) {
        $submission = FormSubmission::query()->findOrFail($request->payload['submission_id']);
        $workplan = Workplan::query()->findOrFail($submission->payload['workplan_id']);
        expect($workplan->status)->toBe('finalized')
            ->and($workplan->finalized_at->lt($request->requested_at))->toBeTrue()
            ->and($workplan->organization_id)->toBe($request->organization_id);
    }
    $disabled = Organization::query()->where('accreditation_status', 'disabled')->get();
    expect($disabled)->toHaveCount(2);
    foreach ($disabled as $organization) {
        expect(app(AccreditationService::class)->isCompliant($organization, Carbon::parse(SeedData::END)))->toBeFalse();
    }
    expect(ActionRequest::query()->whereNotBetween('requested_at', [SeedData::START, SeedData::END])->count())->toBe(0)
        ->and(Approval::query()->whereNotBetween('approved_at', [SeedData::START, SeedData::END])->count())->toBe(0);
});

it('refuses to append demo data to an existing installation', function () {
    recordsUser(User::TYPE_SUPERADMIN);
    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(RuntimeException::class, 'empty database');
    expect(User::query()->count())->toBe(1);
});
