<?php

namespace Database\Seeders;

use App\Models\Officer;
use App\Models\Organization;
use App\Models\Organization\OrganizationDetail;
use App\Models\OrganizationAdviser;
use App\Models\Post;
use App\Models\User;
use App\Models\YearTerm;
use Database\Factories\PostFactory;
use Database\Seeders\Support\SeedData;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        SeedData::at('2023-06-01 09:00:00', function () {
            $this->createYearTerms();
            $this->createOrganizations();
        });
    }

    protected function createYearTerms()
    {
        $yearTerms = array_map(fn ($year) => $year.' - '.($year + 1), range(2023, 2030));
        foreach ($yearTerms as $terms) {
            YearTerm::firstOrCreate([
                'year_term' => $terms,
            ]);
        }

    }

    protected function createOrganizations()
    {

        //  Org Type
        // 1 - Socio Civic, 2 - Religous, 3 - Fraternities and Sororities, 4 - Special Interest, 5 - University Sanctioned, Student Councils

        $orgNameAndTypes = [
            ['Buklod-Lahi', 1],
            ['Ecological and Solid Waste Management Society', 1],
            ['3D Sighters', 1],
            ['TAU Bulalayaw', 1],
            ['Ladies Dormitory Organization', 1],
            ['Men\'s Dormitory Organization', 1],
            ['Mulat TAU Deabte Society', 1],
            ['Passion, Rhythm, Inspiration, Melody, Excellence (PRIME)', 1],
            ['Ranchers\' Club Philippines - TAU Chapter', 1],
            ['Rodeo Club', 1],
            ['TAU-Global Ambassadors', 1],
            ['Veterinary Student Achievers\' Society', 1],
            ['Philippine Consortium for Science, Mathematics, and Technology', 1],
            ['Campus Mover\'s For Christ', 2],
            ['Christian Brotherhood International-TAU Chapter', 2],
            ['Christian Youth for Nation', 2],
            ['Latter-Day Saint Student Association', 2],
            ['Student Catholic Action of the Philippines-TAU Unit', 2],
            ['Alpha Phi Omega', 3],
            ['Alpha Kappa RHO', 3],
            ['TAU Gamma Phi/Sigma', 3],
            ['Gamma Sigma Scorpions (Vermilliom Chapter)', 3],
            ['United Ilocandia', 3],
            ['Venerable Knight Veterinarians/Venerable Lady Veterinarians', 3],
            ['LS - Agriculture and Homemaking Club', 4],
            ['LS Math Club', 4],
            ['LS - Arts Club', 4],
            ['LS Rondalla Club', 4],
            ['LS Boy Scout of the Philippines', 4],
            ['LS Science Club', 4],
            ['LS - Drum and Lyre Corps', 4],
            ['LS Social Science Club', 4],
            ['LS - Filipino Club', 4],
            ['LS Speech and Debate Society', 4],
            ['LS Dance', 4],
            ['LS Sports Club', 4],
            ['LS - Glee Club', 4],
            ['LS Girl Scout of the Philippines', 4],
            ['Golden Harvest', 5],
            ['Reserved Officers Training Corps', 5],
            ['Performing Guild', 5],
            ['Chorale', 5],
            ['A.K.D.A.', 5],
            ['College of Agriculture and Forestry - Student Council', 6],
            ['College of Arts and Sciences - Student Council', 6],
            ['College of Veterinary Medicine - Student Council', 6],
            ['College of Engineering and Technology - Student Council', 6],
            ['College of Business Management - Student Council', 6],
            ['College of Education - Student Council', 6],
            ['Laboratory School - Student Council', 6],
            ['Supreme Student Council', 6],
            ['TAU Lakas Angkan Youth Fellowship', 2],
            ['TAU Latter-Day Saints Student Association', 2],
            ['Daniel Generations', 2],
            ['Christian Brotherhood International - TAU Chapter', 2],
            ['TAU-SIBOL Association of DOST Scholars', 1],
        ];

        foreach ($orgNameAndTypes as $orgAndType) {
            $name = $orgAndType[0];
            $type = $orgAndType[1];
            $this->createOrganization($name, $type);
        }
    }

    public function createOrganization(string $name, int $type, ?User $president = null, ?User $officer = null): Organization
    {
        $detail = OrganizationDetail::factory()->create(['name' => $name]);
        $organization = Organization::query()->create([
            'organization_type' => $type,
            'detail' => $detail->getKey(),
            'accreditation_status' => 'active',
        ]);

        foreach (['President', 'Secretary', 'Auditor', 'Treasurer', ...array_fill(0, 11, 'Others')] as $index => $position) {
            $user = ($index === 0 ? $president : ($index === 4 ? $officer : null)) ?? SeedData::user();
            Officer::query()->create([
                'organization' => $organization->getKey(),
                'user' => $user->getKey(),
                'position' => $position,
                'role' => $position === 'President' ? 'president' : 'officer',
                'yearterm' => YearTerm::query()->where('year_term', '2023 - 2024')->value('year_term_code'),
                'member_since' => now(),
            ]);
        }

        foreach ([1, 2] as $index) {
            $adviser = SeedData::user(email: 'adviser-'.$organization->getKey().'-'.$index.'@example.com');
            $adviser->profile()->update(['occupation' => 'Faculty']);
            $profile = $adviser->profile()->firstOrFail();
            OrganizationAdviser::query()->create([
                'organization_id' => $organization->getKey(),
                'name' => $profile->first_name.' '.$profile->last_name,
            ]);
        }

        foreach (range(0, 9) as $index) {
            $date = fake()->dateTimeBetween(now(), SeedData::END)->format('Y-m-d H:i:s');
            SeedData::at($date, fn () => Post::factory()->forOrganization((int) $organization->getKey())->create([
                'published_at' => now(),
                'is_featured' => in_array($index, [1, 6], true),
                'image_path' => ($index + 1) % 4 === 0 ? PostFactory::randomImagePath() : null,
            ]));
        }

        return $organization;
    }

    public function shareOfficers(array $protectedOfficerIds = []): void
    {
        $organizations = Organization::query()->orderBy('organization_id')->get();
        foreach ($organizations as $index => $first) {
            foreach ($organizations->slice($index + 1) as $second) {
                if (! fake()->boolean(25)) {
                    continue;
                }
                $kind = fake()->numberBetween(1, 100);
                $sourceNamed = $kind <= 15;
                $targetNamed = $kind <= 5;
                $source = $first->officersOfThisOrganization()
                    ->whereIn('role', ['officer', 'president'])
                    ->where('position', $sourceNamed ? '!=' : '=', 'Others')
                    ->whereNotIn('org_officer_id', $protectedOfficerIds)
                    ->whereNotIn('user', $second->officersOfThisOrganization()->pluck('user'))
                    ->inRandomOrder()->first();
                $target = $second->officersOfThisOrganization()
                    ->whereIn('role', ['officer', 'president'])
                    ->where('position', $targetNamed ? '!=' : '=', 'Others')
                    ->whereNotIn('org_officer_id', $protectedOfficerIds)
                    ->inRandomOrder()->first();
                if ($source === null || $target === null) {
                    continue;
                }
                $target->update(['user' => $source->user]);
            }
        }
    }
}
