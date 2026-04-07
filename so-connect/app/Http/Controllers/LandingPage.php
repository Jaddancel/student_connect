<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LandingPage extends Controller
{
    public function view()
    {
        $organizationTypes = $this->organizationTypes();
        $organizations = $this->organizationDirectory();
        $organizationsByType = $organizations->groupBy('type')->all();
        $topFeed = $organizations->take(9);

        return view('landingPage.landingpage', compact('organizationsByType', 'organizationTypes', 'topFeed'));
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

        $templates = $this->sampleAnnouncements();
        $orgFeed = collect(range(1, 8))->map(function ($offset) use ($templates) {
            $template = $templates[($offset - 1) % count($templates)];

            return [
                'title' => $template['title'],
                'excerpt' => $template['excerpt'],
                'tag' => $template['tag'],
                'time' => now()->subHours($offset * 4)->diffForHumans(),
                'audience' => ['Open to all students', 'Members only', 'New applicants welcome'][$offset % 3],
            ];
        });

        return view('landingPage.organization', compact('organization', 'orgFeed', 'organizationTypes', 'organizationsByType'));
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

        $rows = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select('o.organization_id', 'o.organization_type', 'od.name')
            ->orderBy('o.organization_id')
            ->get();

        return $rows->values()->map(function ($organization, $index) use ($templates) {
            $template = $templates[$index % count($templates)];
            $name = $organization->name ?: 'Unknown Organization '.$organization->organization_id;
            $baseSlug = Str::slug($name);

            return [
                'id' => (int) $organization->organization_id,
                'name' => $name,
                'type' => (int) $organization->organization_type,
                'slug' => ($baseSlug !== '' ? $baseSlug : 'organization').'-'.$organization->organization_id,
                'announcement_title' => $template['title'],
                'announcement_excerpt' => $template['excerpt'],
                'announcement_tag' => $template['tag'],
                'announcement_time' => now()->subHours((($index % 8) + 1) * 2)->diffForHumans(),
            ];
        });
    }
}
