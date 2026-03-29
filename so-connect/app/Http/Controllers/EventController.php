<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4
use Illuminate\Http\Request;

class EventController extends Controller
{
    //
<<<<<<< HEAD
=======
=======
use App\Models\Event;
use App\Models\Organization;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\ActionService;
use App\Services\UserOrganizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EventController extends Controller
{
    public function dashboard(UserOrganizationService $userOrganizationService)
    {
        $events = Event::with('details')
            ->whereHas('details', function ($query) {
                $query->where('event_start_time', '>=', now());
            })
            ->get()
            ->sortBy(fn ($event) => $event->details->event_start_date);

        $userOrganizations = $userOrganizationService->getUserOrganizations(auth()->user());

        $organizationIds = auth()->user()
            ?->member()
            ->pluck('organization')
            ->filter()
            ->unique()
            ->values();

        $memberDashboardEvents = collect();
        $memberPendingMembershipRequests = collect();

        if ($organizationIds && $organizationIds->isNotEmpty()) {
            $memberDashboardEvents = Event::with(['details', 'organizationRelation.organizationDetail'])
                ->whereIn('organization', $organizationIds)
                ->whereHas('details', function ($query) {
                    $query->where('event_start_time', '>=', now()->startOfDay());
                })
                ->get()
                ->filter(fn ($event) => $event->details)
                ->sortBy(fn ($event) => $event->details->event_start_time)
                ->values()
                ->map(function ($event) {
                    return [
                        'id' => $event->event_id,
                        'title' => $event->details->event_name,
                        'description' => $event->details->event_desc_text,
                        'location' => $event->details->event_location,
                        'organization' => $event->organizationRelation?->organizationDetail?->organization_name ?? 'Unknown Organization',
                        'start' => $event->details->event_start_time?->toIso8601String(),
                        'end' => $event->details->event_end_time?->toIso8601String(),
                        'date' => $event->details->event_start_time?->toDateString(),
                    ];
                })
                ->values();

            $parsedRequests = RequestModel::where('action_type', 0)
                ->doesntHave('approval')
                ->get()
                ->map(function ($request) {
                    $parts = explode('|', (string) $request->action);

                    if (count($parts) < 2) {
                        return null;
                    }

                    return [
                        'request_id' => $request->request_id,
                        'organization_id' => (int) $parts[0],
                        'user_id' => (int) $parts[1],
                        'requested_at' => $request->request_made_at,
                    ];
                })
                ->filter()
                ->filter(fn ($request) => $organizationIds->contains($request['organization_id']))
                ->values();

            $users = User::with('profile')
                ->whereIn('user_id', $parsedRequests->pluck('user_id')->unique()->values())
                ->get()
                ->keyBy('user_id');

            $organizations = Organization::with('organizationDetail')
                ->whereIn('organization_id', $parsedRequests->pluck('organization_id')->unique()->values())
                ->get()
                ->keyBy('organization_id');

            $memberPendingMembershipRequests = $parsedRequests
                ->map(function ($request) use ($users, $organizations) {
                    $user = $users->get($request['user_id']);
                    $organization = $organizations->get($request['organization_id']);

                    if (! $user || ! $organization) {
                        return null;
                    }

                    $profile = $user->profile;
                    $fullName = trim(implode(' ', array_filter([
                        $profile?->first_name,
                        $profile?->middle_name,
                        $profile?->last_name,
                    ])));

                    return [
                        'id' => $request['request_id'],
                        'requester_name' => $fullName !== '' ? $fullName : 'Unknown User',
                        'organization_name' => $organization->organizationDetail->organization_name ?? 'Unknown Organization',
                        'requested_at' => $request['requested_at'],
                    ];
                })
                ->filter()
                ->sortByDesc(fn ($request) => $request['requested_at'])
                ->values();
        }

        return view('dashboard', compact('events', 'memberDashboardEvents', 'memberPendingMembershipRequests', 'userOrganizations'));
    }

    public function calendar()
    {
        $approvedOrganizationIds = $this->getApprovedOrganizationIds();

        $events = Event::with('details')
            ->whereIn('organization', $approvedOrganizationIds)
            ->get()
            ->filter(fn ($event) => $event->details)
            ->sortBy(fn ($event) => $event->details->event_start_date);

        return view('calendar', compact('events'));
    }

    public function allEvents()
    {
        $approvedOrganizationIds = $this->getApprovedOrganizationIds();

        if ($approvedOrganizationIds->isEmpty()) {
            return response()->json([]);
        }

        $events = Event::with('details')
            ->whereIn('organization', $approvedOrganizationIds)
            ->get()
            ->filter(fn ($event) => $event->details)
            ->values()
            ->map(function ($event) {
                return [
                    'title' => $event->details->event_name,
                    'start' => $event->details->event_start_time,
                    'end' => $event->details->event_end_time,
                    'extendedProps' => [
                        'location' => $event->details->event_location,
                        'description' => $event->details->event_desc_text,
                    ],
                ];
            });

        return response()->json($events);
    }

    protected function getApprovedOrganizationIds(): Collection
    {
        return auth()->user()
            ->member()
            ->whereNotNull('approval_id')
            ->pluck('organization')
            ->filter()
            ->map(fn ($organizationId) => (int) $organizationId)
            ->unique()
            ->values();
    }

    public function createEventRequest(Request $request)
    {
        $command = $request->validate([
            'organization_id' => 'required|integer',
            'event_name' => 'required|string|max:255',
            'event_start_time' => 'required|date',
            'event_end_time' => 'required|date|after_or_equal:event_start_time',
            'event_desc_text' => 'nullable|string',
        ]);

        $command['user_id'] = (int) auth()->id();

        (new ActionService)->passAction($command, 1); // Assuming '1' is the action type code for event creation requests.

        return redirect()->back()->with('success', 'Event creation request submitted successfully.');
    }

    public function eventRegistrationForm()
    {
        $organizationIds = auth()->user()
            ?->member()
            ->pluck('organization')
            ->filter()
            ->unique()
            ->values();

        $organizationsOfUser = Organization::with('organizationDetail')
            ->whereIn('organization_id', $organizationIds)
            ->get();

        return view('event.create', compact('organizationsOfUser'));
    }
>>>>>>> main
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4
}
