<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function getMembershipRequests(Request $request)
    {
        $userOrgIds = auth()->user()->member()->pluck('organization')->map(fn ($id) => (int) $id)->toArray();

        $parsedRequests = RequestModel::where('action_type', 0)
            ->with('approval')
            ->get()
            ->map(function ($req) {
                $parts = explode('|', (string) $req->action);
                if (count($parts) < 2) {
                    return null;
                }

                $status = 'pending';
                if ($req->approval) {
                    $status = $req->approval->decision === 'rejected' ? 'rejected' : 'approved';
                }

                return [
                    'request_id' => $req->request_id,
                    'organization_id' => (int) $parts[0],
                    'user_id' => (int) $parts[1],
                    'requested_at' => $req->request_made_at,
                    'status' => $status,
                ];
            })
            ->filter()
            ->filter(fn ($req) => in_array($req['organization_id'], $userOrgIds, true))
            ->values();

        $users = User::with('profile')
            ->whereIn('user_id', $parsedRequests->pluck('user_id')->unique()->values())
            ->get()
            ->keyBy('user_id');

        $organizations = Organization::with('organizationDetail')
            ->whereIn('organization_id', $parsedRequests->pluck('organization_id')->unique()->values())
            ->get()
            ->keyBy('organization_id');

        $groupedRequests = $parsedRequests
            ->map(function ($req) use ($users, $organizations) {
                $user = $users->get($req['user_id']);
                $organization = $organizations->get($req['organization_id']);

                if (! $user || ! $organization) {
                    return null;
                }

                return (object) [
                    'id' => $req['request_id'],
                    'user' => $user,
                    'organization' => $organization,
                    'requested_at' => $req['requested_at'],
                    'status' => $req['status'],
                ];
            })
            ->filter()
            ->values();

        return view('admin.membership_requests', ['requests' => $groupedRequests]);
    }
}
