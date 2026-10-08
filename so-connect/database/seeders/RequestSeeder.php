<?php

namespace Database\Seeders;

use App\Forms\Handlers\NewOrganizationRegistrationHandler;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Officer;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\Semester;
use App\Models\User;
use App\Models\Workplan;
use App\Services\AccreditationService;
use App\Services\DocumentGenerationService;
use App\Services\RequestTypeService;
use Database\Seeders\Support\SeedData;
use Database\Seeders\Support\SeedPayload;
use Database\Seeders\Support\SeedScoring;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RequestSeeder extends Seeder
{
    /** @var list<int> */
    private array $workplanOrganizations = [];

    /** @var list<int> */
    private array $accreditationOrganizations = [];

    /** @var list<int> */
    private array $disabledOrganizations = [];

    /** @var array<int, Workplan> */
    private array $workplans = [];

    /** @var list<EventPlan> */
    private array $concludedPlans = [];

    private Collection $administrators;

    public function run(): void
    {
        $this->administrators = User::query()->where('user_type', User::TYPE_ADMIN)->get();
        $this->seedOrganizationRegistrations();

        $organizations = Organization::query()->get()->shuffle();
        $this->workplanOrganizations = $organizations->take((int) round($organizations->count() * .70))
            ->modelKeys();
        $this->accreditationOrganizations = fake()->randomElements(
            $this->workplanOrganizations,
            (int) round(count($this->workplanOrganizations) * .25),
        );
        $this->disabledOrganizations = $organizations
            ->whereNotIn('organization_id', $this->workplanOrganizations)->take(2)->modelKeys();

        $protectedOfficers = $this->seedSignups();
        SeedData::at('2023-06-03 17:00:00', fn () => (new OrganizationSeeder)->shareOfficers($protectedOfficers));
        $this->seedWorkplans();
        $this->seedAccreditations();
        $this->seedMemberships();
        $this->seedEventRequests();
        $this->seedAfterEventReports();

        foreach (Form::query()->whereNull('system_function')->with('fields')->get() as $form) {
            $this->seedDocumentForm($form);
        }
        SeedData::at('2026-10-01 09:00:00', function () {
            foreach ($this->disabledOrganizations as $id) {
                app(AccreditationService::class)->disable(Organization::query()->findOrFail($id));
            }
        });
    }

    private function form(string $function): Form
    {
        return Form::query()->with('fields')->where('system_function', $function)->firstOrFail();
    }

    private function organizations(): Collection
    {
        return Organization::query()->whereNotIn('organization_id', $this->disabledOrganizations)->get();
    }

    /** Each burst is received by one organization on that day. */
    private function schedule(): array
    {
        $organizations = $this->organizations();
        $schedule = [];
        $days = collect(SeedData::burstDates())->groupBy(fn ($date) => substr($date, 0, 10));
        $used = [];
        foreach ($days as $dates) {
            $eligible = $organizations->whereNotIn('organization_id', $used);
            // A large double-burst uses the default cohort so approved sign-ups
            // still fit the eleven unnamed officer slots.
            if ($dates->count() > 15) {
                $eligible = $eligible->whereNotIn('organization_id', $this->workplanOrganizations);
            }
            $organization = $eligible->random();
            $used[] = $organization->getKey();
            foreach ($dates as $date) {
                $schedule[] = ['organization' => $organization, 'date' => $date];
            }
        }

        return $schedule;
    }

    /** Allocate exact acceptance quotas within each cohort and five declines overall. */
    private function statuses(array $schedule): array
    {
        $groups = [true => [], false => []];
        foreach ($schedule as $index => $entry) {
            $priority = in_array((int) $entry['organization']->getKey(), $this->workplanOrganizations, true);
            $groups[(int) $priority][] = $index;
        }
        $statuses = [];
        $priorityDeclines = (int) round(count($groups[1]) * .10);
        foreach ($groups as $priority => $indices) {
            $accepted = (int) round(count($indices) * ($priority ? .70 : .30));
            $declined = $priority ? $priorityDeclines : 5 - $priorityDeclines;
            $choices = fake()->shuffleArray([
                ...array_fill(0, $accepted, 'accepted'),
                ...array_fill(0, $declined, 'declined'),
                ...array_fill(0, count($indices) - $accepted - $declined, 'pending'),
            ]);
            foreach ($indices as $index => $requestIndex) {
                $statuses[$requestIndex] = $choices[$index];
            }
        }

        return $statuses;
    }

    private function submission(Form $form, ?Organization $organization, ?User $user, array $payload, ?int $eventId = null): FormSubmission
    {
        return FormSubmission::query()->create([
            'form_id' => $form->getKey(),
            'organization_id' => $organization?->getKey(),
            'event_id' => $eventId,
            'submitted_by' => $user?->getKey(),
            'payload' => $payload,
            'submitted_at' => now(),
        ]);
    }

    private function documentRequest(Form $form, Organization $organization, User $user, FormSubmission $submission): ActionRequest
    {
        return app(DocumentGenerationService::class)->createDocumentGenerationRequest(
            (int) $organization->getKey(),
            (int) $submission->getKey(),
            (int) $form->getKey(),
            (int) $user->getKey(),
        );
    }

    private function decision(ActionRequest $request, string $status, ?string $stage = null, ?int $reviewer = null): ?Approval
    {
        if ($status === 'pending') {
            return null;
        }

        return Approval::query()->create([
            'request' => $request->getKey(),
            'admin' => $reviewer ?? $this->administrators->random()->getKey(),
            'approved_at' => now()->addHour(),
            'stage' => $stage,
            'is_rejected' => $status === 'declined',
            'rejection_reason' => $status === 'declined' ? 'Supporting information did not meet the review requirements.' : null,
        ]);
    }

    private function seedOrganizationRegistrations(): void
    {
        $form = $this->form('new_organization_registration');
        $statuses = SeedData::statuses(30);
        $dates = SeedData::burstDates();
        foreach ($statuses as $index => $status) {
            // Accepted organizations exist from the beginning of the historical
            // sample, so later officer/workplan/event records cannot predate them.
            $date = $status === 'accepted'
                ? Carbon::parse('2023-06-02 09:00:00')->addMinutes($index * 3)->toDateTimeString()
                : $dates[$index];
            SeedData::at($date, function () use ($form, $index, $status) {
                $president = SeedData::user();
                $officer = SeedData::user();
                $payload = [
                    'organization_name' => 'Student Development Society '.($index + 1),
                    'organization_initials' => 'SDS'.($index + 1),
                    'organization_description' => 'Student-led service, leadership and academic development.',
                    'organization_type' => fake()->numberBetween(1, 6),
                    'freshman' => 20, 'sophomore' => 20, 'junior' => 20, 'total' => 60,
                    'president_email' => $president->user_email,
                    'officer_email' => $officer->user_email,
                    'officer_emails' => [$officer->user_email],
                    'president_is_registered' => true,
                    'officer_is_registered' => true,
                    'officer_registration_statuses' => [true],
                ];
                $submission = $this->submission($form, null, $president, $payload);
                $type = app(RequestTypeService::class)->resolveSystemType(
                    RequestType::SYSTEM_KEY_NEW_ORGANIZATION_REGISTRATION,
                    'New Organization Registration Request',
                    RequestType::CATEGORY_ORGANIZATION,
                );
                $request = ActionRequest::query()->create([
                    'action' => 'new_organization_registration',
                    'action_type' => NewOrganizationRegistrationHandler::ACTION_TYPE,
                    'form_id' => $form->getKey(),
                    'request_type_id' => $type->getKey(),
                    'user' => $president->getKey(),
                    'requested_by' => $president->getKey(),
                    'requested_at' => now(),
                    'payload' => $payload + ['form_submission_id' => $submission->getKey()],
                ]);
                $approval = $this->decision($request, $status);
                if ($status === 'accepted') {
                    SeedData::at($approval->approved_at->toDateTimeString(), function () use ($payload, $president, $officer, $request, $submission) {
                        $organization = (new OrganizationSeeder)->createOrganization(
                            $payload['organization_name'], $payload['organization_type'], $president, $officer,
                        );
                        $request->update([
                            'organization_id' => $organization->getKey(),
                            'payload' => $request->payload + ['created_organization_id' => $organization->getKey()],
                        ]);
                        $submission->update(['organization_id' => $organization->getKey()]);
                    });
                }
            });
        }
    }

    /** @return list<int> Officer slots retained for approved sign-up applicants. */
    private function seedSignups(): array
    {
        $form = $this->form('sign_up');
        $schedule = $this->schedule();
        $statuses = $this->statuses($schedule);
        $protected = [];
        foreach ($schedule as $index => $entry) {
            SeedData::at($entry['date'], function () use ($form, $entry, $statuses, $index, &$protected) {
                $organization = $entry['organization'];
                $applicant = SeedData::user(User::TYPE_GUEST);
                $payload = SeedPayload::for($form, $organization, $applicant, now());
                $payload['position'] = 'Others';
                $submission = $this->submission($form, $organization, $applicant, $payload);
                $request = ActionRequest::query()->create([
                    'action' => '0|'.$organization->getKey().'|new_officer',
                    'action_type' => 11,
                    'form_id' => $form->getKey(),
                    'organization_id' => $organization->getKey(),
                    'user' => $applicant->getKey(),
                    'requested_by' => $applicant->getKey(),
                    'requested_at' => now(),
                    'payload' => $payload + [
                        'pending_user_id' => $applicant->getKey(),
                        'form_submission_id' => $submission->getKey(),
                    ],
                ]);
                $approval = $this->decision($request, $statuses[$index]);
                if ($statuses[$index] === 'accepted') {
                    $applicant->update(['user_type' => User::TYPE_OFFICER]);
                    $slot = $organization->officersOfThisOrganization()
                        ->where('position', 'Others')->whereNotIn('org_officer_id', $protected)->firstOrFail();
                    $slot->forceFill([
                        'user' => $applicant->getKey(), 'approval' => $approval->getKey(),
                        'member_since' => $approval->approved_at,
                        'registered_at' => $approval->approved_at,
                        'reassigned_at' => $approval->approved_at,
                    ])->save();
                    $protected[] = (int) $slot->getKey();
                }
            });
        }

        return $protected;
    }

    private function seedWorkplans(): void
    {
        $form = $this->form('new_workplan');
        $accepted = fake()->randomElements(
            array_values(array_diff($this->workplanOrganizations, $this->accreditationOrganizations)),
            35 - count($this->accreditationOrganizations),
        );
        $accepted = [...$accepted, ...$this->accreditationOrganizations];
        $declined = fake()->randomElements(array_values(array_diff($this->workplanOrganizations, $accepted)), 5);
        $semesters = Semester::query()->where('starts_at', '<=', '2026-06-22')->get();
        foreach ($this->workplanOrganizations as $id) {
            $semester = $semesters->random();
            $date = $semester->starts_at->copy()->subDays(5)->setTime(9, 0);
            SeedData::at($date->toDateTimeString(), function () use ($form, $id, $semester, $accepted, $declined) {
                $organization = Organization::query()->findOrFail($id);
                $president = SeedPayload::president($organization);
                $status = in_array($id, $accepted, true) ? 'accepted' : (in_array($id, $declined, true) ? 'declined' : 'pending');
                $plan = EventPlan::query()->create([
                    'organization_id' => $id, 'created_by' => $president->getKey(),
                    'title' => 'Semester student development program',
                    'target_date' => $semester->starts_at->copy()->addDays(10),
                    'resources_needed' => 'Venue, materials, sound system and refreshments',
                    'persons_responsible' => [$president->getKey()],
                    'purpose_of_activity' => 'Student leadership and service development',
                    'status' => 'approved',
                ]);
                $payload = SeedPayload::for($form, $organization, $president, now());
                $row = [
                    'title' => $plan->title,
                    'target' => $plan->target_date->format('M d, Y'),
                    'resources' => $plan->resources_needed,
                    'people' => SeedPayload::name($president),
                ];
                $payload = array_merge($payload, [
                    'school_year' => $semester->schoolYear(),
                    'semester_id' => $semester->getKey(),
                    'approved_events' => [$plan->getKey()],
                    'activity_table' => [['title' => $plan->title, 'target_date' => $plan->target_date->toDateString()]],
                    'workplan_rows' => [$row],
                    'activities' => [$row['title']], 'target' => [$row['target']],
                    'resources' => [$row['resources']], 'people' => [$row['people']],
                ]);
                $submission = $this->submission($form, $organization, $president, $payload);
                $request = $this->documentRequest($form, $organization, $president, $submission);
                $approval = $this->decision($request, $status);
                $this->workplans[$id] = Workplan::query()->create([
                    'organization_id' => $id, 'semester_id' => $semester->getKey(),
                    'status' => $status === 'accepted' ? 'finalized' : 'active',
                    'finalized_at' => $status === 'accepted' ? $approval->approved_at : null,
                    'finalized_by' => $status === 'accepted' ? $president->getKey() : null,
                ]);
            });
        }
    }

    private function seedAccreditations(): void
    {
        $form = $this->form('org_accreditation');
        $statuses = SeedData::statuses(70);
        $ids = $this->accreditationOrganizations;
        while (count($ids) < 50) {
            $ids[] = fake()->randomElement($this->accreditationOrganizations);
        }
        foreach ($ids as $index => $id) {
            $workplan = $this->workplans[$id];
            $date = $workplan->finalized_at->copy()->addDays(fake()->numberBetween(1, 3));
            $date->addMinutes($index * 5);
            SeedData::at($date->toDateTimeString(), function () use ($form, $id, $workplan, $statuses, $index) {
                $organization = Organization::query()->findOrFail($id);
                $president = SeedPayload::president($organization);
                $payload = SeedPayload::for($form, $organization, $president, now());
                $payload['workplan_id'] = $workplan->getKey();
                $target = SeedScoring::target($form, $payload);
                $payload = $target['payload'] + ['_seed_criterion' => $target['criterion']];
                $submission = $this->submission($form, $organization, $president, $payload);
                $request = $this->documentRequest($form, $organization, $president, $submission);
                $this->decision($request, $statuses[$index]);
            });
        }
    }

    private function seedMemberships(): void
    {
        $form = $this->form('membership_registration');
        $schedule = $this->schedule();
        $statuses = $this->statuses($schedule);
        foreach ($schedule as $index => $entry) {
            SeedData::at($entry['date'], function () use ($form, $entry, $statuses, $index) {
                $organization = $entry['organization'];
                $applicant = SeedData::user();
                $payload = ['organization_id' => $organization->getKey(), 'position' => 'Others'];
                $submission = $this->submission($form, $organization, $applicant, $payload);
                $request = ActionRequest::query()->create([
                    'action' => $organization->getKey().'|'.$applicant->getKey(),
                    'action_type' => 1, 'request_type_id' => $form->request_type_id,
                    'form_id' => $form->getKey(), 'organization_id' => $organization->getKey(),
                    'requested_by' => $applicant->getKey(), 'user' => $applicant->getKey(),
                    'requested_at' => now(),
                    'payload' => $payload + [
                        'user_id' => $applicant->getKey(), 'requester_organization_id' => 0,
                        'form_submission_id' => $submission->getKey(),
                    ],
                ]);
                if ($statuses[$index] === 'accepted') {
                    $this->decision($request, 'accepted', 'president', (int) SeedPayload::president($organization)->getKey());
                    $approval = $this->decision($request, 'accepted', 'admin');
                    Officer::query()->create([
                        'organization' => $organization->getKey(), 'user' => $applicant->getKey(),
                        'role' => 'member', 'position' => 'Others', 'approval' => $approval->getKey(),
                        'member_since' => $approval->approved_at,
                    ]);
                } else {
                    $this->decision($request, $statuses[$index], 'president', (int) SeedPayload::president($organization)->getKey());
                }
            });
        }
    }

    private function seedEventRequests(): void
    {
        $form = $this->form('new_event');
        $schedule = $this->schedule();
        $statuses = $this->statuses($schedule);
        foreach ($schedule as $index => $entry) {
            $requested = Carbon::parse($entry['date'])->min(Carbon::parse('2026-10-20 09:00:00'));
            $semester = SeedPayload::semesterFor($requested->copy()->addDays(7));
            if ($requested->copy()->addDays(10)->gt($semester->endsAt())) {
                $semester = Semester::query()->where('starts_at', '>', $semester->starts_at)->orderBy('starts_at')->firstOrFail();
            }
            $targetDate = $requested->copy()->addDays(7)->max($semester->starts_at);
            SeedData::at($requested->toDateTimeString(), function () use ($form, $entry, $statuses, $index, $targetDate) {
                $organization = $entry['organization'];
                $president = SeedPayload::president($organization);
                $payload = SeedPayload::for($form, $organization, $president, now());
                $payload['target_date'] = $targetDate->toDateString();
                $target = SeedScoring::target($form, $payload);
                $payload = $target['payload'];
                $submission = $this->submission($form, $organization, $president, $payload);
                $request = $this->documentRequest($form, $organization, $president, $submission);
                $shared = $this->planFields($organization, $president, $payload);
                $parent = EventPlan::query()->create($shared + ['status' => 'pending']);
                $plan = EventPlan::query()->create($shared + [
                    'parent_plan_id' => $parent->getKey(), 'request_id' => $request->getKey(), 'status' => 'pending',
                ]);
                $request->update(['payload' => $request->payload + [
                    'event_plan_id' => $plan->getKey(), 'parent_plan_id' => $parent->getKey(),
                ]]);
                $approval = $this->decision($request, $statuses[$index]);
                if ($statuses[$index] === 'accepted') {
                    SeedData::at($approval->approved_at->toDateTimeString(), function () use ($plan, $parent) {
                        $event = $this->calendarEvent($plan);
                        $plan->update(['status' => 'approved', 'event_id' => $event->getKey()]);
                        $parent->update(['status' => 'approved', 'event_id' => $event->getKey()]);
                        $this->concludedPlans[] = $plan;
                    });
                } elseif ($statuses[$index] === 'declined') {
                    $plan->update(['status' => 'rejected']);
                }
            });
        }
    }

    private function planFields(Organization $organization, User $president, array $payload): array
    {
        return [
            'organization_id' => $organization->getKey(), 'created_by' => $president->getKey(),
            'title' => $payload['title'], 'target_date' => $payload['target_date'],
            'purpose_of_activity' => $payload['purpose_of_activity'],
            'resources_needed' => 'Venue, supplies, transport and refreshments',
            'persons_responsible' => [$president->getKey()],
            'event_location' => $payload['event_location'],
            'event_start_time' => $payload['target_date'].' '.$payload['event_start_time'],
            'event_end_time' => $payload['target_date'].' '.$payload['event_end_time'],
            'university_facilities' => $payload['university_facilities'],
            'president_name' => $payload['president_name'], 'president_contact' => $payload['president_contact'],
            'faculty_advisers' => $payload['faculty_advisers'],
            'activity_types' => [$payload['event_type']], 'area_scope' => $payload['area_scope'],
            'sponsor' => $payload['sponsor'], 'extension_services' => (bool) ($payload['extension_services'] ?? false),
            'cosponsor_count' => $payload['co_sponsored'] ? count($payload['dropdown']) : 0,
        ];
    }

    private function calendarEvent(EventPlan $plan): Event
    {
        $detail = EventDetail::query()->create([
            'name' => $plan->title, 'location' => $plan->event_location,
            'desc_text' => $plan->purpose_of_activity,
            'start_time' => $plan->event_start_time, 'end_time' => $plan->event_end_time,
        ]);

        return Event::query()->create([
            'organization' => $plan->organization_id, 'creator' => $plan->created_by,
            'event_detail' => $detail->getKey(),
        ]);
    }

    /**
     * Every organization files 5–10 after-event reports (each with 2–5 event
     * photos) spread over its semesters, with 1–2 in the running semester so
     * the After Event Form page has entries. Accepted New Event requests are
     * reported first; activities imported without a request fill the rest.
     * Each report is followed by a published post reusing its photos.
     */
    private function seedAfterEventReports(): void
    {
        $form = $this->form('after_event_report');
        // Reports are filed three days after an event; keep everything in the past.
        $cutoff = Carbon::now()->min(Carbon::parse(SeedData::END))->subDays(5)->setTime(16, 0);
        $semesters = Semester::query()->where('starts_at', '<=', $cutoff)->orderBy('starts_at')->get();
        $current = $semesters->last();
        $reported = [];

        foreach (Organization::query()->get() as $organization) {
            $id = (int) $organization->getKey();
            $target = fake()->numberBetween(5, 10);
            $plans = collect($this->concludedPlans)
                ->filter(fn (EventPlan $plan) => (int) $plan->organization_id === $id && $plan->event_end_time->lte($cutoff))
                ->shuffle()->take($target)->values()->all();
            $used = array_map(fn (EventPlan $plan) => $plan->target_date->toDateString(), $plans);
            $inCurrent = count(array_filter($plans, fn (EventPlan $plan) => $plan->event_end_time->gte($current->starts_at)));
            $wantCurrent = fake()->numberBetween(1, 2);

            while (count($plans) < $target) {
                $semester = $inCurrent < $wantCurrent ? $current : $semesters->random();
                $start = $semester->starts_at->copy()->addDays(5);
                $end = ($semester->endsAt() ?? $cutoff)->min($cutoff)->copy()->subDays(2);
                if ($end->lt($start)) {
                    continue;
                }
                $date = Carbon::createFromTimestamp(fake()->numberBetween($start->timestamp, $end->timestamp))->startOfDay();
                if (in_array($date->toDateString(), $used, true)) {
                    continue;
                }
                $used[] = $date->toDateString();
                $inCurrent += $semester->is($current) ? 1 : 0;
                SeedData::at($date->copy()->subDays(14)->setTime(10, 0)->toDateTimeString(), function () use ($organization, $date, &$plans) {
                    $president = SeedPayload::president($organization);
                    $payload = SeedPayload::for($this->form('new_event'), $organization, $president, now());
                    $payload['target_date'] = $date->toDateString();
                    $plan = EventPlan::query()->create($this->planFields($organization, $president, $payload) + ['status' => 'approved']);
                    $event = $this->calendarEvent($plan);
                    $plan->update(['event_id' => $event->getKey()]);
                    $plans[] = $plan;
                });
            }
            array_push($reported, ...$plans);
        }

        usort($reported, fn (EventPlan $a, EventPlan $b) => $a->event_end_time <=> $b->event_end_time);
        foreach ($reported as $plan) {
            $date = $plan->event_end_time->copy()->addDays(3)->setTime(17, 0);
            SeedData::at($date->toDateTimeString(), function () use ($form, $plan) {
                $organization = Organization::query()->findOrFail($plan->organization_id);
                $president = SeedPayload::president($organization);
                $payload = SeedPayload::for($form, $organization, $president, now());
                $source = $plan->request_id
                    ? FormSubmission::query()->findOrFail($plan->request->payload['submission_id'])
                    : null;
                $target = SeedScoring::target($form, $payload, $source?->payload ?? []);
                if ($source !== null) {
                    $source->update(['payload' => $target['linked']]);
                    $fields = $this->planFields($organization, $president, $target['linked']);
                    $plan->update($fields);
                    $plan->parentPlan?->update($fields);
                }
                $payload = $target['payload'] + ['_seed_criterion' => $target['criterion']];
                $photos = array_values(array_filter((array) ($payload['photo_documentation'] ?? [])));
                if (count($photos) < 2 || count($photos) > 5) {
                    $payload['photo_documentation'] = $photos = SeedData::eventPhotos();
                }
                $this->submission($form, $organization, $president, $payload, (int) $plan->event_id);
                $this->eventPost($organization, $plan->refresh(), $payload, $photos);
            });
        }
    }

    /** @param  list<string>  $photos */
    private function eventPost(Organization $organization, EventPlan $plan, array $payload, array $photos): void
    {
        $name = (string) $organization->detail()->value('name');
        $when = $plan->event_end_time->format('F j, Y');
        $attended = (int) ($payload['number_of_organization_members_attended'] ?? 0);
        $body = collect([
            "{$name} held {$plan->title} at {$plan->event_location} on {$when}.",
            $plan->purpose_of_activity ? 'The activity aimed to '.lcfirst(rtrim($plan->purpose_of_activity, '.')).'.' : null,
            $attended > 0 ? "{$attended} members of the organization took part, together with student participants and faculty advisers." : null,
            ! empty($payload['were_there_any_awards']) ? 'Congratulations to everyone recognized during the activity.' : null,
            'Thank you to everyone who joined and helped make this activity a success!',
        ])->filter()->implode("\n\n");

        SeedData::at(now()->addHours(fake()->numberBetween(1, 15))->toDateTimeString(), fn () => Post::query()->create([
            'organization' => $organization->getKey(),
            'title' => $plan->title.' Highlights',
            'excerpt' => Str::limit("Highlights from {$plan->title}, held at {$plan->event_location} on {$when}.", 277),
            'body' => $body,
            'tag' => 'Event',
            'image_path' => $photos[0],
            'image_paths' => $photos,
            'is_featured' => fake()->boolean(15),
            'published_at' => now(),
            'status' => 'published',
        ]));
    }

    private function seedDocumentForm(Form $form): void
    {
        $schedule = $this->schedule();
        $statuses = $this->statuses($schedule);
        foreach ($schedule as $index => $entry) {
            SeedData::at($entry['date'], function () use ($form, $entry, $statuses, $index) {
                $organization = $entry['organization'];
                $president = SeedPayload::president($organization);
                $target = SeedScoring::target($form, SeedPayload::for($form, $organization, $president, now()));
                $payload = $target['payload'] + ['_seed_criterion' => $target['criterion']];
                $submission = $this->submission($form, $organization, $president, $payload);
                $request = $this->documentRequest($form, $organization, $president, $submission);
                $this->decision($request, $statuses[$index]);
            });
        }
    }
}
