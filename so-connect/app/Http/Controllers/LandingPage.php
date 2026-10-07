<?php

namespace App\Http\Controllers;

use App\Forms\SystemFunction;
use App\Helpers\OrganizationLogoHelper;
use App\Models\EventPlan;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LandingPage extends Controller
{
    public function view()
    {
        $organizationTypes = $this->organizationTypes();
        $organizations = $this->organizationDirectory();
        $organizationsByType = $organizations->groupBy('type')->all();
        $topFeed = $organizations->take(9);

        $organizationNameMap = $organizations
            ->mapWithKeys(fn ($organization) => [$organization['id'] => $organization['name']])
            ->all();

        $featuredPosts = Schema::hasTable('posts')
            ? Post::query()
                ->where('posts.status', 'published')
                ->when(Schema::hasTable('organization_scores'), function ($query) {
                    $query->leftJoinSub(
                        DB::table('organization_scores')
                            ->select('organization_id', DB::raw('MAX(total_weighted_score) as best_score'))
                            ->groupBy('organization_id'),
                        'org_scores',
                        'org_scores.organization_id', '=', 'posts.organization'
                    )->orderByDesc('org_scores.best_score');
                })
                ->orderByDesc('posts.is_featured')
                ->orderByDesc('posts.published_at')
                ->orderByDesc('posts.created_at')
                ->limit(3)
                ->get(['posts.*'])
            : collect();

        $recentActivities = Schema::hasTable('events') && Schema::hasTable('event_details')
            ? DB::table('events as e')
                ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
                ->leftJoin('organizations as o', 'o.organization_id', '=', 'e.organization')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->where('ed.start_time', '<', now())
                ->orderByDesc('ed.start_time')
                ->limit(3)
                ->get([
                    'e.event_id',
                    'ed.name as event_name',
                    'ed.location as event_location',
                    'ed.desc_text as event_description',
                    'ed.start_time',
                    'ed.end_time',
                    DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
                ])
            : collect();

        return view('landingPage.landingpage', compact(
            'organizationsByType',
            'organizationTypes',
            'topFeed',
            'featuredPosts',
            'organizationNameMap',
            'recentActivities'
        ));
    }

    public function organizationFeed(int $organizationId, ?string $slug = null)
    {
        $organizations = $this->organizationDirectory();
        $organization = $organizations->firstWhere('id', $organizationId) ?? abort(404);

        if ($slug !== null && $slug !== $organization['slug']) {
            return redirect()->route('organization-feed', [
                'organizationId' => $organization['id'],
                'slug' => $organization['slug'],
            ]);
        }

        $posts = Schema::hasTable('posts')
            ? Post::query()
                ->where('organization', $organizationId)
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(24)
                ->get()
            : collect();

        $publishedPostCount = $posts->filter(fn ($post) => ($post->status ?? 'published') === 'published')->count();

        return $this->organizationPage($organizations, $organization, 'posts', [
            'posts' => $posts,
            'publishedPostCount' => $publishedPostCount,
        ]);
    }

    public function organizationMembers(int $organizationId, string $slug)
    {
        $organizations = $this->organizationDirectory();
        $organization = $organizations->firstWhere('id', $organizationId) ?? abort(404);

        if ($slug !== $organization['slug']) {
            return redirect()->route('organization-members', [
                'organizationId' => $organization['id'],
                'slug' => $organization['slug'],
            ]);
        }

        $publishedPostCount = Schema::hasTable('posts')
            ? Post::query()->where('organization', $organizationId)->where('status', 'published')->count()
            : 0;

        return $this->organizationPage($organizations, $organization, 'members', [
            'publishedPostCount' => $publishedPostCount,
        ]);
    }

    public function organizationEvents(int $organizationId, string $slug)
    {
        $organizations = $this->organizationDirectory();
        $organization = $organizations->firstWhere('id', $organizationId) ?? abort(404);

        if ($slug !== $organization['slug']) {
            return redirect()->route('organization-events', [
                'organizationId' => $organization['id'],
                'slug' => $organization['slug'],
            ]);
        }

        $timezone = config('app.display_timezone', 'Asia/Manila');
        $now = now($timezone);
        $events = EventPlan::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'approved')
            ->whereNotNull('event_start_time')
            ->whereNotNull('event_end_time')
            ->whereHas('request', function ($query) {
                $query->whereHas('form', fn ($form) => $form->where('system_function', SystemFunction::NEW_EVENT))
                    ->whereExists(function ($approval) {
                        $approval->selectRaw('1')
                            ->from('approvals')
                            ->whereColumn('approvals.request', 'requests.request_id')
                            ->where('is_rejected', false)
                            ->whereNotNull('approved_at')
                            ->where(fn ($stage) => $stage->whereNull('stage')->orWhere('stage', 'admin'));
                    });
            })
            ->orderBy('event_start_time')
            ->orderBy('event_plan_id')
            ->get()
            ->map(function ($plan) use ($timezone) {
                // Builder times are stored as local wall-clock datetimes, not UTC.
                $start = Carbon::parse($plan->getRawOriginal('event_start_time'), $timezone);
                $end = Carbon::parse($plan->getRawOriginal('event_end_time'), $timezone);

                return [
                    'id' => (int) $plan->getKey(),
                    'title' => $plan->title,
                    'location' => $plan->event_location,
                    'date' => $start->format('F j, Y'),
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'start_time' => $start->format('g:i A'),
                    'end_time' => $end->format('g:i A'),
                ];
            });

        $upcomingEvents = $events->filter(fn ($event) => Carbon::parse($event['start'])->gt($now))->values();
        $events = $events->sortBy(fn ($event) => abs(Carbon::parse($event['start'])->getTimestamp() - $now->getTimestamp()))->values();
        $publishedPostCount = Post::query()->where('organization', $organizationId)->where('status', 'published')->count();

        return $this->organizationPage($organizations, $organization, 'events', [
            'events' => $events,
            'upcomingEvents' => $upcomingEvents,
            'publishedPostCount' => $publishedPostCount,
        ]);
    }

    private function organizationPage(Collection $organizations, array $organization, string $activeTab, array $data)
    {
        ['officers' => $officers, 'members' => $members] = $this->organizationRoster($organization['id']);

        return view('landingPage.organization', array_merge([
            'organization' => $organization,
            'organizationTypes' => $this->organizationTypes(),
            'organizationsByType' => $organizations->groupBy('type')->all(),
            'activeTab' => $activeTab,
            'officers' => $officers,
            'members' => $members,
            'memberCount' => $officers->count() + $members->count(),
            'posts' => collect(),
        ], $data));
    }

    /**
     * Public roster for an organization. "Officers" are rows whose organization
     * role is president/officer; everyone else is a regular member. A user is
     * listed once — under their highest role.
     *
     * @return array{officers: Collection, members: Collection}
     */
    private function organizationRoster(int $organizationId): array
    {
        if (! Schema::hasTable('organization_officers')) {
            return ['officers' => collect(), 'members' => collect()];
        }

        // Officer cards follow this order; any other position comes after, by name.
        $positionOrder = ['president' => 0, 'secretary' => 1, 'auditor' => 2, 'treasurer' => 3];

        $rows = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('oo.organization', $organizationId)
            ->get([
                'oo.org_officer_id',
                'oo.user',
                'oo.role',
                'oo.position',
                'oo.member_since',
                'p.first_name',
                'p.last_name',
                'p.photo',
                'p.course',
                'p.year_section',
                'p.course_year',
            ])
            ->map(function ($row) use ($positionOrder) {
                $role = strtolower((string) $row->role);
                $isOfficer = in_array($role, ['president', 'officer'], true);
                $position = trim((string) $row->position);

                if ($role === 'president') {
                    $title = 'President';
                } elseif ($isOfficer) {
                    $title = $position !== '' && strcasecmp($position, 'Others') !== 0 ? $position : 'Officer';
                } else {
                    $title = 'Member';
                }

                $name = trim(($row->first_name ?? '').' '.($row->last_name ?? ''));
                $courseYear = $row->course_year
                    ?: trim(implode(' ', array_filter([$row->course, $row->year_section])));

                return [
                    'user_id' => (int) $row->user,
                    'is_officer' => $isOfficer,
                    'name' => $name !== '' ? $name : 'Unnamed Member',
                    'initials' => strtoupper(substr((string) $row->first_name, 0, 1).substr((string) $row->last_name, 0, 1)) ?: '?',
                    'title' => $title,
                    'rank' => $isOfficer ? ($positionOrder[strtolower($title)] ?? count($positionOrder)) : PHP_INT_MAX,
                    'photo_url' => $row->photo ? '/storage/'.ltrim($row->photo, '/') : null,
                    'course_year' => $courseYear !== '' ? $courseYear : null,
                    'member_since' => $row->member_since ? Carbon::parse($row->member_since) : null,
                    'sort_name' => strtolower(($row->last_name ?? '').' '.($row->first_name ?? '')),
                ];
            })
            ->sortBy([['rank', 'asc'], ['sort_name', 'asc']])
            ->unique('user_id')
            ->values();

        return [
            'officers' => $rows->where('is_officer', true)->values(),
            'members' => $rows->where('is_officer', false)->values(),
        ];
    }

    private function organizationTypes(): array
    {
        return [
            1 => 'Socio-Civic',
            2 => 'Religious',
            3 => 'Fraternities-Sororities',
            4 => 'Special Interest',
            5 => 'University-Sanctioned',
            6 => 'Student Government',
        ];
    }

    private function sampleAnnouncements(): array
    {
        return [
            [
                'title' => 'Leadership Assembly and Open Forum',
                'excerpt' => 'Officers are inviting all members to propose student-led projects for the upcoming term.',
                'tag' => 'Announcement',
            ],
            [
                'title' => 'Community Outreach Drive',
                'excerpt' => 'Volunteers will visit partner communities this weekend for skills-sharing and clean-up activities.',
                'tag' => 'Volunteer',
            ],
            [
                'title' => 'Campus Skills Workshop Series',
                'excerpt' => 'A short workshop series opens next week featuring communication, budgeting, and event planning.',
                'tag' => 'Workshop',
            ],
            [
                'title' => 'Organization Week Feature Booth',
                'excerpt' => 'The group is preparing interactive booths and live demos for new student recruitment.',
                'tag' => 'Event',
            ],
            [
                'title' => 'Member Spotlight Stories',
                'excerpt' => 'This month highlights active members and their contributions to student life and campus culture.',
                'tag' => 'Spotlight',
            ],
            [
                'title' => 'Training and Mentorship Call',
                'excerpt' => 'Senior members are opening mentorship slots for first-year students interested in leadership roles.',
                'tag' => 'Mentorship',
            ],
        ];
    }

    private function organizationDirectory(): Collection
    {
        $templates = $this->sampleAnnouncements();
        $logoMap = OrganizationLogoHelper::map();

        $rows = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            // Accreditation-disabled orgs are hidden from the public directory.
            ->where(fn ($q) => $q->where('o.accreditation_status', '!=', 'disabled')->orWhereNull('o.accreditation_status'))
            ->select('o.organization_id', 'o.organization_type', 'od.name')
            ->orderBy('o.organization_id')
            ->get();

        return $rows->values()->map(function ($organization, $index) use ($templates, $logoMap) {
            $template = $templates[$index % count($templates)];
            $name = $organization->name ?: 'Unknown Organization '.$organization->organization_id;
            $baseSlug = Str::slug($name);

            return [
                'id' => (int) $organization->organization_id,
                'name' => $name,
                'type' => (int) $organization->organization_type,
                'slug' => ($baseSlug !== '' ? $baseSlug : 'organization').'-'.$organization->organization_id,
                'logo_url' => $logoMap[$name] ?? null,
                'announcement_title' => $template['title'],
                'announcement_excerpt' => $template['excerpt'],
                'announcement_tag' => $template['tag'],
                'announcement_time' => now()->subHours((($index % 8) + 1) * 2)->diffForHumans(),
            ];
        });
    }
}
