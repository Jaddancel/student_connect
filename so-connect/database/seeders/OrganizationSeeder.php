<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\YearTerm;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $this->createYearTerms();
        $this->createOrganizations();
    }

    protected function createYearTerms()
    {
        $yearTerms = [
            '2020 - 2021',
            '2021 - 2022',
            '2022 - 2023',
            '2023 - 2024',
            '2024 - 2025',
            '2025 - 2026',
        ];
        foreach ($yearTerms as $terms) {
            YearTerm::create([
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
            ['TAU-SIBOL Association of DOST Scholars', 1]
        ];

        foreach ($orgNameAndTypes as $orgAndType) {
            $name = $orgAndType[0];
            $type = $orgAndType[1];
            Organization::factory()->makeOrganization($name, $type)->create();
        }
    }
}
