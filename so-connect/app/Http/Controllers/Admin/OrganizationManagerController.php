<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Services\OrganizationOfficerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationManagerController extends Controller
{
    public function __construct(private readonly OrganizationOfficerManager $manager) {}

    public function index(Request $request): View
    {
        $typeOptions = OrganizationType::options();
        $typeFilter = (int) $request->query('organization_type', 0);
        if (! array_key_exists($typeFilter, $typeOptions)) {
            $typeFilter = null;
        }

        $organizations = $this->manager->activeOrganizations($typeFilter);
        $allowedIds = $organizations->pluck('organization_id')->all();

        $selectedOrganizationId = (int) $request->query('organization_id', 0);
        if (! in_array($selectedOrganizationId, $allowedIds, true)) {
            $selectedOrganizationId = (int) ($allowedIds[0] ?? 0);
        }

        return view('pages.admin.organization-manager.index', [
            'title' => 'Organization Manager',
            'organizations' => $organizations,
            'selectedOrganizationId' => $selectedOrganizationId,
            'typeOptions' => $typeOptions,
            'typeFilter' => $typeFilter,
            'board' => $selectedOrganizationId > 0 ? $this->manager->board($selectedOrganizationId) : null,
        ]);
    }

    public function candidates(Request $request, int $organization): JsonResponse
    {
        abort_unless($this->manager->isActive($organization), 404);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->manager->searchCandidates($organization, (string) ($validated['q'] ?? '')),
        ]);
    }

    public function update(Request $request, int $organization): RedirectResponse
    {
        abort_unless($this->manager->isActive($organization), 404);

        $validated = $request->validate([
            'position' => ['required', 'string', Rule::in(OrganizationOfficerManager::POSITIONS)],
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->manager->assign(
            $organization,
            $validated['position'],
            (int) $validated['user_id'],
            (int) $request->user()->getKey(),
        );

        return redirect()
            ->route('admin.organization-manager.index', ['organization_id' => $organization])
            ->with('success', $validated['position'].' updated successfully.');
    }
}
