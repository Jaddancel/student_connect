<?php

namespace App\Http\Controllers;

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
        $logoMap = $this->organizationLogoMap();

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

    private function organizationLogoMap(): array
    {
        $basePath = 'images/organizations';

        return [
            'Buklod-Lahi' => $this->logoPath("{$basePath}/SOCIO-CIVIC CATEGORY/BUKLOD LAHI TAU.JPG"),
            'Ecological and Solid Waste Management Society' => $this->logoPath("{$basePath}/SOCIO-CIVIC CATEGORY/ESWM TAU.JPG"),
            'TAU Bulalayaw' => $this->logoPath("{$basePath}/SOCIO-CIVIC CATEGORY/TAU BULALAYAW.JPG"),
            'Mulat TAU Deabte Society' => $this->logoPath("{$basePath}/SOCIO-CIVIC CATEGORY/TAU DEBATE SOCIETY.jpg"),
            'Ranchers\' Club Philippines - TAU Chapter' => $this->logoPath("{$basePath}/SOCIO-CIVIC CATEGORY/RANCHERS CLUB TAU CHAPTER.png"),
            'Rodeo Club' => $this->logoPath("{$basePath}/SOCIO-CIVIC CATEGORY/TAU RODEO CLUB.jpg"),
            'Veterinary Student Achievers\' Society' => $this->logoPath("{$basePath}/SOCIO-CIVIC CATEGORY/VSAS.jpg"),
            'Philippine Consortium for Science, Mathematics, and Technology' => $this->logoPath("{$basePath}/SOCIO-CIVIC CATEGORY/PCSMT.png"),
            "Campus Mover's For Christ" => $this->logoPath("{$basePath}/RELIGIOUS CATEGORY/CAMPUS MOVERS FOR CHRIST.jpg"),
            'Christian Brotherhood International-TAU Chapter' => $this->logoPath("{$basePath}/RELIGIOUS CATEGORY/CBI INTERNATIONAL.png"),
            'Christian Youth for Nation' => $this->logoPath("{$basePath}/RELIGIOUS CATEGORY/christian youth for nation.png"),
            'Latter-Day Saint Student Association' => $this->logoPath("{$basePath}/RELIGIOUS CATEGORY/TAU LATTER DAY SAINTS STUDENT ASSOC.JPG"),
            'Student Catholic Action of the Philippines-TAU Unit' => $this->logoPath("{$basePath}/RELIGIOUS CATEGORY/STUDENT CATHOLIC ACTION OF THE PHILIPPINES TAU UNIT.JPG"),
            'Alpha Phi Omega' => $this->logoPath("{$basePath}/FRATERNITIES AND SORORITIES/ALPHA PHI OMEGA.png"),
            'Alpha Kappa RHO' => $this->logoPath("{$basePath}/FRATERNITIES AND SORORITIES/Alpha_Kappa_Rho_.png"),
            'TAU Gamma Phi/Sigma' => $this->logoPath("{$basePath}/FRATERNITIES AND SORORITIES/TAU GAMMA PHI SIGMA.png"),
            'Gamma Sigma Scorpions (Vermilliom Chapter)' => $this->logoPath("{$basePath}/FRATERNITIES AND SORORITIES/GAMMA SIGMA SCORPIONS VERMILLION CHAPTER.jpg"),
            'United Ilocandia' => $this->logoPath("{$basePath}/FRATERNITIES AND SORORITIES/UNITED ILOVANDIA.jpg"),
            'Venerable Knight Veterinarians/Venerable Lady Veterinarians' => $this->logoPath("{$basePath}/FRATERNITIES AND SORORITIES/VENERABLE KNIGHT VET.jpg"),
            'LS - Agriculture and Homemaking Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS AGRI AND HOME MAKING CLUB.JPG"),
            'LS Math Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS MATH CLUB.JPG"),
            'LS - Arts Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS ART CLUB.JPG"),
            'LS Rondalla Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS RONDALLA CLUB.JPG"),
            'LS Boy Scout of the Philippines' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS BSP CLUB.JPG"),
            'LS Science Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS SCI CLUB.JPG"),
            'LS Social Science Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS SOCIAL SCIENCE CLUB.JPG"),
            'LS - Filipino Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS FILIPINO CLUB.jpg"),
            'LS Speech and Debate Society' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS SPEECH AND DEBATE CLUB.JPG"),
            'LS Dance' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS DANCE CLUB.JPG"),
            'LS Sports Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS SPORTS CLUB.JPG"),
            'LS - Glee Club' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS GLEE CLUB.JPG"),
            'LS Girl Scout of the Philippines' => $this->logoPath("{$basePath}/SPECIAL INTEREST CATEGORY/LS GSP CLUB.JPG"),
            'Golden Harvest' => $this->logoPath("{$basePath}/university sanctioned organizations/golden harvest.jpg"),
            'Reserved Officers Training Corps' => $this->logoPath("{$basePath}/university sanctioned organizations/rotc.jpg"),
            'Performing Guild' => $this->logoPath("{$basePath}/university sanctioned organizations/tau performing guild.jpg"),
            'Chorale' => $this->logoPath("{$basePath}/university sanctioned organizations/tau chorale.jpg"),
            'A.K.D.A.' => $this->logoPath("{$basePath}/university sanctioned organizations/akda.jpg"),
            'College of Agriculture and Forestry - Student Council' => $this->logoPath("{$basePath}/student government category/CAF SC.JPG"),
            'College of Arts and Sciences - Student Council' => $this->logoPath("{$basePath}/student government category/CAS SC.JPG"),
            'College of Veterinary Medicine - Student Council' => $this->logoPath("{$basePath}/student government category/CVM SC.JPG"),
            'College of Engineering and Technology - Student Council' => $this->logoPath("{$basePath}/student government category/CET SC.JPG"),
            'College of Business Management - Student Council' => $this->logoPath("{$basePath}/student government category/CBM SC.JPG"),
            'College of Education - Student Council' => $this->logoPath("{$basePath}/student government category/EDUC SC.jpg"),
            'Laboratory School - Student Council' => $this->logoPath("{$basePath}/student government category/LS SC.JPG"),
            'Supreme Student Council' => $this->logoPath("{$basePath}/student government category/TAU SC.JPG"),
        ];
    }

    private function logoPath(string $relativePath): string
    {
        $segments = array_map('rawurlencode', explode('/', $relativePath));

        return asset(implode('/', $segments));
    }
}
