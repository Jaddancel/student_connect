<?php

namespace App\Support;

use App\Models\Approval;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reads a pending applicant's sign-up history — the action_type=11 requests
 * filed against their guest account, and each one's approval decision.
 *
 * The state a guest sees ("pending", "rejected", how many attempts are left) is
 * derived from these rows rather than stored on the account, so the admin's
 * decision is the single source of truth.
 */
class SignupRequests
{
    /** action_type of a "create this account" request. */
    public const ACTION_TYPE = 11;

    /**
     * Every sign-up request this user has filed, newest first.
     *
     * @return Collection<int,ActionRequest>
     */
    public static function forUser(User $user): Collection
    {
        return ActionRequest::query()
            ->where('action_type', self::ACTION_TYPE)
            ->where('user', (int) $user->getKey())
            ->orderByDesc('requested_at')
            ->orderByDesc('request_id')
            ->get();
    }

    /**
     * The state of this user's latest sign-up request.
     *
     * @return array{status:string, request:?ActionRequest, approval:?Approval, reason:string, rejections:int}
     *                                                                                                        status is one of none|pending|approved|rejected.
     */
    public static function statusFor(User $user): array
    {
        $requests = self::forUser($user);
        $approvals = self::approvalsFor($requests);

        $latest = $requests->first();
        $approval = $latest ? $approvals->get((int) $latest->request_id) : null;

        $status = match (true) {
            $latest === null => 'none',
            $approval === null => 'pending',
            (bool) $approval->is_rejected => 'rejected',
            default => 'approved',
        };

        return [
            'status' => $status,
            'request' => $latest,
            'approval' => $approval,
            'reason' => (string) ($approval?->rejection_reason ?? ''),
            'rejections' => $approvals->filter(fn (Approval $a) => (bool) $a->is_rejected)->count(),
        ];
    }

    /** How many of this user's sign-up requests have been rejected. */
    public static function rejectionCount(User $user): int
    {
        return self::approvalsFor(self::forUser($user))
            ->filter(fn (Approval $approval) => (bool) $approval->is_rejected)
            ->count();
    }

    /**
     * @param  Collection<int,ActionRequest>  $requests
     * @return Collection<int,Approval>  keyed by request id
     */
    private static function approvalsFor(Collection $requests): Collection
    {
        if ($requests->isEmpty()) {
            return collect();
        }

        return Approval::query()
            ->whereIn('request', $requests->map(fn (ActionRequest $r) => (int) $r->request_id)->all())
            ->get()
            ->keyBy(fn (Approval $approval) => (int) $approval->request);
    }
}
