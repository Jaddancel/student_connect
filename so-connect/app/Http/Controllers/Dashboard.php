<?php

namespace App\Http\Controllers;

use App\Helpers\OrganizationLogoHelper;
use App\Models\ManualFormSession;
use App\Models\Organization;
use App\Models\Semester;
use App\Services\AfterEventReportService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Dashboard extends Controller
{
    public function viewDashboard()
    {
        $user = auth()->user();

        $userType = (int) $user->user_type;

        // Sign-up applicant awaiting approval: they hold no membership, so the
        // officer dashboard would be empty — send them to their request status.
        if ($user->isGuest()) {
            return redirect()->route('guest.dashboard');
        }

        if (! $user->profile && ! $user->profile_pending) {
            return redirect()->route('profile.create');
        }

        if ($userType === 1) {
            return app()->call([app(SuperAdminController::class), 'monitoringDashboard']);
        }

        // Admin.
        if ($userType === 2) {
            return view('pages.dashboard.administrator', [
                'title' => 'Admin Dashboard',
                'canDecide' => false,
            ]);
        }

        return $this->officerDashboard();
    }

    public function officerDashboard()
    {
        $user = auth()->user();
        $userId = (int) $user->getKey();

        $organizationIds = collect(OrganizationAuthorizationService::memberOrganizationIdsForUser($userId));
        $officerOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);

        $organization = Organization::query()
            ->with('detail')
            ->withCount(['officersOfThisOrganization as officer_count' => fn ($query) => $query->whereIn('role', ['officer', 'president'])])
            ->whereIn('organization_id', $organizationIds->all())
            ->first();

        $organizationDetail = $organization?->getRelation('detail');
        $organizationRole = $organization
            ? DB::table('organization_officers')
                ->where('user', $userId)
                ->where('organization', $organization->getKey())
                ->first(['role', 'position'])
            : null;
        $roleLabel = $organizationRole?->position
            ?: ucfirst((string) ($organizationRole?->role ?: 'Student'));
        $semesterEnd = Semester::current()?->endsAt()?->endOfDay();

        $upcomingEvents = collect();

        if ($organizationIds->isNotEmpty()) {
            $upcomingEvents = DB::table('events as e')
                ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
                ->leftJoin('organizations as o', 'o.organization_id', '=', 'e.organization')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('e.organization', $organizationIds->all())
                ->where('ed.start_time', '>=', now())
                ->orderBy('ed.start_time')
                ->limit(5)
                ->get([
                    'e.event_id',
                    'e.organization',
                    'ed.name as event_name',
                    'ed.location as event_location',
                    'ed.start_time',
                    'ed.end_time',
                    DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
                ]);
        }

        $openDraftCount = ManualFormSession::query()
            ->where('user_id', $userId)
            ->whereIn('status', ManualFormSession::OPEN_STATUSES)
            ->count();

        $requestRows = DB::table('requests as r')
            ->leftJoin('forms as f', 'f.id', '=', 'r.form_id')
            ->leftJoin('request_types as rt', 'rt.request_type_id', '=', 'r.request_type_id')
            ->where('r.requested_by', $userId)
            ->orderByDesc('r.requested_at')
            ->limit(5)
            ->get([
                'r.request_id',
                'r.action_type',
                'r.requested_at',
                'f.name as form_name',
                'f.route_name as form_route_name',
                'rt.name as request_type_name',
            ]);

        $approvalsByRequest = DB::table('approvals')
            ->whereIn('request', $requestRows->pluck('request_id'))
            ->orderByDesc('approved_at')
            ->get(['request', 'stage', 'is_rejected', 'rejection_reason', 'approved_at'])
            ->groupBy('request');

        $recentRequests = $requestRows->map(function ($row) use ($approvalsByRequest) {
            $approvals = $approvalsByRequest->get($row->request_id, collect());

            // Membership requests need a final admin decision; a president's
            // approval alone only advances the request to the OSSD queue.
            $decision = (int) $row->action_type === 1
                ? ($approvals->first(fn ($approval) => in_array($approval->stage, [null, '', 'admin'], true))
                    ?? $approvals->first(fn ($approval) => $approval->stage === 'president' && (bool) $approval->is_rejected))
                : $approvals->first();
            $status = $decision === null
                ? 'pending'
                : ((bool) $decision->is_rejected ? 'rejected' : 'approved');

            return [
                'id' => (int) $row->request_id,
                'name' => $row->form_name ?: ($row->request_type_name ?: 'Submission'),
                'route' => $row->form_route_name ? route('forms.render', $row->form_route_name) : null,
                'submitted_at' => $row->requested_at ? Carbon::parse($row->requested_at) : null,
                'status' => $status,
                'status_label' => match ($status) {
                    'approved' => 'Approved',
                    'rejected' => 'Needs revision',
                    default => 'Awaiting review',
                },
                'rejection_reason' => (string) ($decision?->rejection_reason ?? ''),
            ];
        });

        $pendingRequestCount = DB::table('requests as r')
            ->where('r.requested_by', $userId)
            ->where(function ($query) {
                $query->where(function ($standard) {
                    $standard->where('r.action_type', '!=', 1)
                        ->whereNotExists(fn ($approval) => $approval->selectRaw('1')
                            ->from('approvals as a')
                            ->whereColumn('a.request', 'r.request_id'));
                })->orWhere(function ($membership) {
                    $membership->where('r.action_type', 1)
                        ->whereNotExists(fn ($approval) => $approval->selectRaw('1')
                            ->from('approvals as a')
                            ->whereColumn('a.request', 'r.request_id')
                            ->where(function ($final) {
                                $final->whereNull('a.stage')
                                    ->orWhere('a.stage', '')
                                    ->orWhere('a.stage', 'admin')
                                    ->orWhere(fn ($presidentRejection) => $presidentRejection
                                        ->where('a.stage', 'president')
                                        ->where('a.is_rejected', true));
                            }));
                });
            })
            ->count();

        $unfiledAfterEvents = collect();
        try {
            $afterEvents = app(AfterEventReportService::class);
            if ($afterEvents->enabled()) {
                $unfiledAfterEvents = $afterEvents->unfiledEvents($officerOrganizationIds);
            }
        } catch (\Throwable) {
            // Keep the dashboard available while optional form bindings are being configured.
        }

        $profile = $user->profileRecord()->first();

        return view('pages.dashboard.officer', [
            'title' => 'Dashboard',
            'studentName' => $profile?->first_name ?: 'Student',
            'upcomingEvents' => $upcomingEvents,
            'organization' => $organization,
            'organizationDetail' => $organizationDetail,
            'organizationLogo' => OrganizationLogoHelper::map()[$organizationDetail?->name ?? ''] ?? null,
            'organizationRole' => $roleLabel,
            'semesterEnd' => $semesterEnd,
            'semesterDaysRemaining' => $semesterEnd ? max(0, (int) ceil(now()->diffInSeconds($semesterEnd, false) / 86400)) : null,
            'openDraftCount' => $openDraftCount,
            'pendingRequestCount' => $pendingRequestCount,
            'recentRequests' => $recentRequests,
            'unfiledAfterEvents' => $unfiledAfterEvents,
            'canSubmitForms' => $officerOrganizationIds !== [],
        ]);
    }
}
