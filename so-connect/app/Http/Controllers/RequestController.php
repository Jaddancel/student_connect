<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RequestController extends Controller
{
<<<<<<< HEAD
    //
=======
<<<<<<< HEAD
    //
=======
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

        return view('officer.membership_requests', ['requests' => $groupedRequests]);
    }

    public function getEventRequests(Request $request)
    {
        $presidentOrgIds = $this->getPresidentOrganizationIds();

        $parsedRequests = RequestModel::where('action_type', 1)
            ->with('approval')
            ->get()
            ->map(function ($req) {
                $parts = explode('|', (string) $req->action);

                if (count($parts) < 5) {
                    return null;
                }

                $organizationId = (int) $parts[0];
                $hasUserId = isset($parts[1]) && ctype_digit((string) $parts[1]);
                $userId = $hasUserId ? (int) $parts[1] : null;
                $offset = $hasUserId ? 2 : 1;

                if (! isset($parts[$offset + 2])) {
                    return null;
                }

                $eventDescriptionParts = array_slice($parts, $offset + 3);
                $eventDescription = implode('|', $eventDescriptionParts);

                $status = 'pending';
                if ($req->approval) {
                    $status = $req->approval->decision === 'rejected' ? 'rejected' : 'approved';
                }

                return [
                    'request_id' => $req->request_id,
                    'organization_id' => $organizationId,
                    'user_id' => $userId,
                    'event_name' => $parts[$offset] ?? 'Untitled Event',
                    'event_start_time' => $parts[$offset + 1] ?? null,
                    'event_end_time' => $parts[$offset + 2] ?? null,
                    'event_desc_text' => $eventDescription,
                    'requested_at' => $req->request_made_at,
                    'status' => $status,
                ];
            })
            ->filter()
            ->filter(fn ($req) => in_array($req['organization_id'], $presidentOrgIds, true))
            ->values();

        $users = User::with('profile')
            ->whereIn('user_id', $parsedRequests->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('user_id');

        $organizations = Organization::with('organizationDetail')
            ->whereIn('organization_id', $parsedRequests->pluck('organization_id')->unique()->values())
            ->get()
            ->keyBy('organization_id');

        $groupedRequests = $parsedRequests
            ->map(function ($req) use ($users, $organizations) {
                $organization = $organizations->get($req['organization_id']);

                if (! $organization) {
                    return null;
                }

                $requesterName = 'Unknown User';
                if ($req['user_id']) {
                    $user = $users->get($req['user_id']);
                    if ($user) {
                        $requesterName = trim(($user->profile->last_name ?? '').', '.($user->profile->first_name ?? '').' '.($user->profile->middle_name ?? ''));
                        if ($requesterName === '' || $requesterName === ',') {
                            $requesterName = 'Unknown User';
                        }
                    }
                }

                return (object) [
                    'id' => $req['request_id'],
                    'organization' => $organization,
                    'requester_name' => $requesterName,
                    'event_name' => $req['event_name'],
                    'event_start_time' => $req['event_start_time'],
                    'event_end_time' => $req['event_end_time'],
                    'event_desc_text' => $req['event_desc_text'],
                    'requested_at' => $req['requested_at'],
                    'status' => $req['status'],
                ];
            })
            ->filter()
            ->values();

        return view('admin.event_requests', ['requests' => $groupedRequests]);
    }

    protected function getPresidentOrganizationIds(): array
    {
        return auth()->user()
            ->member()
            ->whereHas('organizationrelation.organizationDetail', function ($query) {
                $query->whereColumn('organization_details.president', 'members.member_id');
            })
            ->pluck('organization')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
>>>>>>> main
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4
}
