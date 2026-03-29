<?php

namespace Database\Seeders;

<<<<<<< HEAD
=======
use App\Models\Approval;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Organization\organizationDetail as OrganizationDetail;
use App\Models\Request;
>>>>>>> main
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
<<<<<<< HEAD
    public function run(): void {}
=======
    public function run(): void
    {
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
        ];

        foreach ($orgNameAndTypes as $orgNameAndType) {
            $organizationDetail = OrganizationDetail::factory()->create([
                'organization_name' => $orgNameAndType[0],
            ]);

            $organization = Organization::query()->create([
                'organization_detail' => $organizationDetail->getKey(),
                'organization_type' => $orgNameAndType[1],
            ]);

            $president = Member::factory()->president()->create([
                'organization' => $organization->getKey(),
            ]);

            $this->createMembershipApprovalForMember(
                $president,
                $organization->getKey(),
                $president->user
            );

            $officers = Member::factory()->officer()->count(9)->create([
                'organization' => $organization->getKey(),
            ]);

            foreach ($officers as $officer) {
                $this->createMembershipApprovalForMember(
                    $officer,
                    $organization->getKey(),
                    $president->user
                );
            }

            $members = Member::factory()->member()->count(10)->create([
                'organization' => $organization->getKey(),
            ]);

            foreach ($members as $member) {
                $this->createMembershipApprovalForMember(
                    $member,
                    $organization->getKey(),
                    $president->user
                );
            }

            $organization->organizationDetail?->update([
                'president' => $president->getKey(),
            ]);
        }
    }

    private function createMembershipApprovalForMember(Member $member, int $organizationId, int $adminId): void
    {
        // ActionService format for membership request payload: organization_id|user_id
        $membershipRequest = Request::create([
            'action' => $organizationId.'|'.$member->user,
            'action_type' => 0,
        ]);

        $approval = Approval::create([
            'admin' => $adminId,
            'approval_timestamp' => now(),
            'request' => $membershipRequest->getKey(),
            'decision' => 'approved',
        ]);

        $member->update([
            'approval_id' => $approval->getKey(),
        ]);
    }
>>>>>>> main
}
