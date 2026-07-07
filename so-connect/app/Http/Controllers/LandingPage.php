<?php

namespace App\Http\Controllers;

use App\Helpers\OrganizationLogoHelper;
use App\Models\Post;
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
        $organizationTypes = $this->organizationTypes();
        $organizations = $this->organizationDirectory();
        $organizationsByType = $organizations->groupBy('type')->all();

        $organization = $organizations->firstWhere('id', $organizationId);
        if (! $organization) {
            abort(404);
        }

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

        return view('landingPage.organization', compact('organization', 'posts', 'organizationTypes', 'organizationsByType'));
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
