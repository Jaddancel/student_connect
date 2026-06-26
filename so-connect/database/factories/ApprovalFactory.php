<?php

namespace Database\Factories;

use App\Models\Approval;
use App\Models\Officer;
use App\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approved_at' => now(),
            'request' => null,
            'admin' => null,
        ];
    }

    public function approveMemberships()
    {
        return $this->state(function () {
            $request = $this->pendingMembershipRequest();
            $orgId   = $request ? $this->parseMembershipAction($request->action)[0] : null;

            return [
                'admin' => Officer::query()->where('organization', $orgId)->inRandomOrder()->first()?->getKey(),
                'request' => $request?->getKey(),
                'is_rejected' => false,
            ];
        })->afterCreating(function (Approval $approval) {
            $request = Request::find($approval->request);

            if (! $request) {
                return;
            }

            [$orgId, $userId] = $this->parseMembershipAction($request->action);

            if (! $orgId || ! $userId) {
                return;
            }

            \Illuminate\Support\Facades\DB::table('organization_officers')->insert([
                'organization'  => $orgId,
                'user'          => $userId,
                'approval'      => $approval->getKey(),
                'role'          => 'member',
                'member_since'  => now(),
                'registered_at' => now(),
                'reassigned_at' => now(),
            ]);
        });
    }

    public function denyMemberships()
    {
        // A denied membership records the rejection only — it must NOT add the
        // applicant to the organization.
        return $this->state(function () {
            $request = $this->pendingMembershipRequest();
            $orgId   = $request ? $this->parseMembershipAction($request->action)[0] : null;

            return [
                'admin' => Officer::query()->where('organization', $orgId)->inRandomOrder()->first()?->getKey(),
                'request' => $request?->getKey(),
                'is_rejected' => true,
            ];
        });
    }

    /**
     * Pick a membership request (action_type 1) that has not been resolved yet,
     * so each approval/denial maps to a distinct, real applicant.
     */
    protected function pendingMembershipRequest(): ?Request
    {
        return Request::query()
            ->where('action_type', 1)
            ->whereDoesntHave('approval')
            ->inRandomOrder()
            ->first();
    }

    /**
     * Membership request actions are stored as "organizationId|userId".
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function parseMembershipAction(?string $action): array
    {
        return array_pad(explode('|', (string) $action, 2), 2, null);
    }
}
