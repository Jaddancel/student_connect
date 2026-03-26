<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Organization\organizationDetail as OrganizationDetail;
use App\Models\Request;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orgNames = [
            'Buklod-Lahi',
            'Ecological and Solid Waste Management Society',
            '3D Sighters',
            'TAU Bulalayaw',
            'Ladies Dormitory Organization',
            'Men\'s Dormitory Organization',
            'Mulat TAU Deabte Society',
            'Passion, Rhythm, Inspiration, Melody, Excellence (PRIME)',
            'Ranchers\' Club Philippines - TAU Chapter',
            'Rodeo Club',
            'TAU-Global Ambassadors',
            'Veterinary Student Achiever\'s Society',
            'Philippine Consortium for Science, Mathematics, and Technology',
            'Campus Mover\'s For Christ',
            'Christian Brotherhood International-TAU Chapter',
            'Christian Youth for Nation',
            'Latter-Day Saint Student Association',
            'Student Catholic Action of the Philippines-TAU Unit',
            'Alpha Phi Omega',
            'Alpha Kappa RHO',
            'TAU Gamma Phi/Sigma',
            'Gamma Sigma Scorpions (Vermilliom Chapter)',
            'United Ilocandia',
            'Venerable Knight Veterinarians/Venerable Lady Veterinarians',
            // 24 Organizations
        ];

        foreach ($orgNames as $orgName) {
            $organizationDetail = OrganizationDetail::factory()->create([
                'organization_name' => $orgName,
            ]);

            $organization = Organization::query()->create([
                'organization_detail' => $organizationDetail->getKey(),
                'organization_type' => fake()->numberBetween(0, 3),
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
}
