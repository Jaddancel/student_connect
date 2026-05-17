<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Organization;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventPlanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        $allOrgIds = array_unique(array_merge($officerOrgIds, $presidentOrgIds));

        if (empty($allOrgIds) && (int) $user->user_type !== 2) {
            return view('pages.sidebar.event-plans', [
                'title' => 'Event Plans',
                'grouped' => [],
                'organizations' => collect(),
            ]);
        }

        $query = EventPlan::query()->with(['event']);

        if ((int) $user->user_type !== 2) {
            $query->whereIn('organization_id', $allOrgIds);
        }

        $plans = $query->orderByDesc('created_at')->get();

        $grouped = [
            'pending' => $plans->where('status', 'pending')->values(),
            'approved' => $plans->where('status', 'approved')->values(),
            'rejected' => $plans->where('status', 'rejected')->values(),
            'junked' => $plans->where('status', 'junked')->values(),
        ];

        $personIds = $plans->flatMap(fn ($p) => $p->persons_responsible ?? [])->unique()->filter()->values()->all();
        $personNames = [];

        if (! empty($personIds)) {
            $personNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $personIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()
                ->pluck('name', 'user_id')
                ->all();
        }

        $orgIds = $plans->pluck('organization_id')->unique()->filter()->values()->all();
        $orgNames = [];

        if (! empty($orgIds)) {
            $orgNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $orgIds)
                ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->get()
                ->pluck('name', 'organization_id')
                ->all();
        }

        return view('pages.sidebar.event-plans', [
            'title' => 'Event Plans',
            'grouped' => $grouped,
            'personNames' => $personNames,
            'orgNames' => $orgNames,
        ]);
    }

    public function storeEvent(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $plan = EventPlan::query()->findOrFail($id);

        if ($plan->status !== 'approved') {
            return back()->withErrors(['plan' => 'Only approved plans can be converted to events.']);
        }

        if ($plan->event_id !== null) {
            return back()->withErrors(['plan' => 'This plan already has an associated event.']);
        }

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        $allOrgIds = array_unique(array_merge($officerOrgIds, $presidentOrgIds));

        if ((int) $user->user_type !== 2 && ! in_array((int) $plan->organization_id, $allOrgIds, true)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'desc_text' => ['nullable', 'string', 'max:5000'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after_or_equal:start_time'],
        ]);

        $eventDetail = EventDetail::query()->create([
            'name' => $validated['name'],
            'location' => $validated['location'],
            'desc_text' => $validated['desc_text'] ?? '',
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        $event = Event::query()->create([
            'organization' => (int) $plan->organization_id,
            'creator' => $userId,
            'event_detail' => (int) $eventDetail->getKey(),
        ]);

        $plan->update(['event_id' => (int) $event->getKey()]);

        return redirect()->route('event-plans')->with('success', 'Event created successfully and added to the calendar.');
    }

    public function junk(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $plan = EventPlan::query()->findOrFail($id);

        if (! in_array($plan->status, ['pending', 'approved'], true)) {
            return back()->withErrors(['plan' => 'Only pending or approved plans can be junked.']);
        }

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        $allOrgIds = array_unique(array_merge($officerOrgIds, $presidentOrgIds));

        if ((int) $user->user_type !== 2 && ! in_array((int) $plan->organization_id, $allOrgIds, true)) {
            abort(403);
        }

        $plan->update(['status' => 'junked']);

        return redirect()->route('event-plans')->with('success', 'Event plan junked.');
    }
}
