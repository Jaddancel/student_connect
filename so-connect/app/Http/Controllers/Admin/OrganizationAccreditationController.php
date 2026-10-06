<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AccreditationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super-admin management of accreditation-disabled organizations: restore an
 * org before its grace period elapses, or hard-purge it. Purge is the only
 * destructive action here and is guarded by the superadmin route middleware.
 */
class OrganizationAccreditationController extends Controller
{
    public function __construct(private readonly AccreditationService $accreditation) {}

    public function index(): View
    {
        $disabled = Organization::query()
            ->where('accreditation_status', AccreditationService::STATUS_DISABLED)
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail')
            ->orderBy('organizations.accreditation_disabled_at')
            ->get([
                'organizations.organization_id',
                'organizations.accreditation_disabled_at',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as name"),
            ])
            ->map(function ($org) {
                $disabledAt = $org->accreditation_disabled_at
                    ? \Illuminate\Support\Carbon::parse($org->accreditation_disabled_at)
                    : null;
                $purgeAt = $this->accreditation->purgeEligibleAt($disabledAt);

                return [
                    'id' => (int) $org->organization_id,
                    'name' => $org->name,
                    'disabled_at' => $disabledAt,
                    'purge_eligible_at' => $purgeAt,
                    'purge_eligible' => $purgeAt !== null && now()->startOfDay()->greaterThanOrEqualTo($purgeAt),
                ];
            });

        return view('pages.admin.organization-accreditation.index', [
            'title' => 'Organization Accreditation',
            'disabled' => $disabled,
            'graceDays' => $this->accreditation->purgeGraceDays(),
        ]);
    }

    public function restore(Organization $organization): RedirectResponse
    {
        $this->accreditation->restore($organization);

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_SETTINGS,
            'accreditation_restored',
            'Restored organization #'.$organization->getKey().' from accreditation suspension',
            ['organization_id' => (int) $organization->getKey()],
        );

        return back()->with('success', 'Organization restored.');
    }

    public function purge(Organization $organization): RedirectResponse
    {
        $id = (int) $organization->getKey();
        $this->accreditation->purge($organization);

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_SETTINGS,
            'accreditation_purged',
            'Purged organization #'.$id.' after accreditation grace period',
            ['organization_id' => $id],
        );

        return redirect()->route('superadmin.organizations.index')
            ->with('success', 'Organization permanently deleted.');
    }
}
