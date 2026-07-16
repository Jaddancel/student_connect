<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\User;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionRequestsPageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $presidentOrganizationIds = $isAdmin
            ? null
            : OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

        if (! $isAdmin && empty($presidentOrganizationIds)) {
            abort(403, 'You are not authorized to view promotion requests.');
        }

        $allRequests = ActionRequest::query()
            ->whereIn('action_type', [7, 11])
            ->orderByDesc('requested_at')
            ->get();

        $rows = $allRequests
            ->map(function ($actionRequest) use ($presidentOrganizationIds, $isAdmin) {
                $actionType = (int) $actionRequest->action_type;

                if ($actionType === 11) {
                    if (! $isAdmin) {
                        return null;
                    }

                    [$organizationId] = $this->parseNewOfficerAction($actionRequest->action);
                    $payload = (array) ($actionRequest->payload ?? []);

                    $photoPath = (string) ($payload['photo'] ?? '');

                    return [
                        'request_id'      => (int) $actionRequest->request_id,
                        'target_user_id'  => 0,
                        'organization_id' => $organizationId,
                        'current_role'    => 'new',
                        'requested_role'  => 'officer',
                        'requested_at'    => $actionRequest->requested_at,
                        'member_initiated' => false,
                        'display_type'    => 'new_officer',
                        'payload_name'    => trim(($payload['first_name'] ?? '').' '.($payload['last_name'] ?? '')),
                        'review_payload'  => [
                            'photo_url'            => $photoPath ? Storage::url($photoPath) : null,
                            'email'                => $payload['email'] ?? '',
                            'position'             => $payload['position'] ?? '',
                            'contact_number'       => $payload['contact_number'] ?? '',
                            'age'                  => $payload['age'] ?? '',
                            'sex'                  => $payload['sex'] ?? '',
                            'religious_affiliation' => $payload['religious_affiliation'] ?? '',
                            'nationality'          => $payload['nationality'] ?? '',
                            'birthday'             => $payload['birthday'] ?? '',
                            'birthplace'           => $payload['birthplace'] ?? '',
                            'present_address'      => $payload['present_address'] ?? '',
                            'home_address'         => $payload['home_address'] ?? '',
                            'parents_guardian'     => $payload['parents_guardian'] ?? '',
                            'course'               => $payload['course'] ?? '',
                            'year_level'           => $payload['year_level'] ?? '',
                            'talents_hobbies'      => $payload['talents_hobbies'] ?? '',
                            'financial_support'    => $payload['financial_support'] ?? [],
                            'scholar_provider'     => $payload['scholar_provider'] ?? '',
                            'others_specify'       => $payload['others_specify'] ?? '',
                            'faculty_advisers'     => $payload['faculty_advisers'] ?? '',
                            'semester'             => $payload['semester'] ?? '',
                            'season'               => $payload['season'] ?? '',
                            'school_year'          => $payload['school_year'] ?? '',
                            'date_filed'           => $payload['date_filed'] ?? '',
                        ],
                    ];
                }

                [$targetUserId, $organizationId, $currentRole] = $this->parseRoleChangeAction($actionRequest->action);

                if ($presidentOrganizationIds !== null && ! in_array($organizationId, $presidentOrganizationIds, true)) {
                    return null;
                }

                return [
                    'request_id'      => (int) $actionRequest->request_id,
                    'target_user_id'  => $targetUserId,
                    'organization_id' => $organizationId,
                    'current_role'    => $currentRole,
                    'requested_role'  => $this->nextRole($currentRole),
                    'requested_at'    => $actionRequest->requested_at,
                    'member_initiated' => (bool) (((array) ($actionRequest->payload ?? []))['member_initiated'] ?? false),
                    'display_type'    => 'role_change',
                    'payload_name'    => null,
                    'review_payload'  => null,
                ];
            })
            ->filter()
            ->values();

        $approvalMap = Approval::query()
            ->whereIn('request', $rows->pluck('request_id')->all())
            ->get(['request', 'is_rejected', 'approved_at'])
            ->keyBy('request');

        $orgNameMap = $this->orgNameMap($rows->pluck('organization_id')->unique()->filter()->values()->all());
        $userNameMap = $this->userNameMap($rows->pluck('target_user_id')->unique()->filter()->values()->all());

        $pendingCount = 0;
        $approvedCount = 0;
        $rejectedCount = 0;

        // Load FormSubmissions linked to approved role-change requests so we can show "Review Draft"
        $form = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::SIGN_UP);
        $submissionMap = [];
        if ($form) {
            $approvedRequestIds = $approvalMap->where('is_rejected', false)->keys()->all();
            if (! empty($approvedRequestIds)) {
                FormSubmission::query()
                    ->where('form_id', $form->id)
                    ->whereNotNull('payload->promotion_request_id')
                    ->get()
                    ->each(function ($submission) use (&$submissionMap) {
                        $rid = (int) (((array) $submission->payload)['promotion_request_id'] ?? 0);
                        if ($rid > 0) {
                            $submissionMap[$rid] = (int) $submission->getKey();
                        }
                    });
            }
        }

        $rows = $rows->map(function (array $row) use ($approvalMap, $orgNameMap, $userNameMap, &$pendingCount, &$approvedCount, &$rejectedCount, $submissionMap) {
            $approval = $approvalMap->get($row['request_id']);
            $status = $approval === null ? 'pending' : ($approval->is_rejected ? 'rejected' : 'approved');

            match ($status) {
                'pending' => $pendingCount++,
                'approved' => $approvedCount++,
                'rejected' => $rejectedCount++,
                default => null,
            };

            $submissionId = $submissionMap[$row['request_id']] ?? null;

            $requesterName = $row['display_type'] === 'new_officer'
                ? $row['payload_name']
                : ($userNameMap[$row['target_user_id']] ?? 'Unknown User');

            return array_merge($row, [
                'organization_name' => $orgNameMap[$row['organization_id']] ?? 'Unknown Organization',
                'requester_name'    => $requesterName,
                'status'            => $status,
                'approved_at'       => $approval?->approved_at,
                'can_decide'        => $status === 'pending',
                'submission_id'     => $submissionId,
            ]);
        })->values();

        return view('pages.sidebar.promotion-requests', [
            'title' => 'Promotion Requests',
            'rows' => $rows,
            'isAdmin' => $isAdmin,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
        ]);
    }

    public function show(int $requestId, Request $request)
    {
        $user = $request->user();
        $isAdmin = (int) $user->user_type === 2;

        if (! $isAdmin) {
            abort(403);
        }

        $actionRequest = ActionRequest::query()->findOrFail($requestId);
        $actionType = (int) $actionRequest->action_type;
        $payload = (array) ($actionRequest->payload ?? []);

        $approval = Approval::query()->where('request', $requestId)->first();

        if ($actionType === 11) {
            [$organizationId] = $this->parseNewOfficerAction($actionRequest->action);
            $photoPath = (string) ($payload['photo'] ?? '');
            $displayType = 'new_officer';
            $reviewPayload = [
                'photo_url'             => $photoPath ? Storage::url($photoPath) : null,
                'email'                 => $payload['email'] ?? '',
                'position'             => $payload['position'] ?? '',
                'first_name'           => $payload['first_name'] ?? '',
                'middle_name'          => $payload['middle_name'] ?? '',
                'last_name'            => $payload['last_name'] ?? '',
                'contact_number'       => $payload['contact_number'] ?? '',
                'age'                  => $payload['age'] ?? '',
                'sex'                  => $payload['sex'] ?? '',
                'religious_affiliation' => $payload['religious_affiliation'] ?? '',
                'nationality'          => $payload['nationality'] ?? '',
                'birthday'             => $payload['birthday'] ?? '',
                'birthplace'           => $payload['birthplace'] ?? '',
                'present_address'      => $payload['present_address'] ?? '',
                'home_address'         => $payload['home_address'] ?? '',
                'parents_guardian'     => $payload['parents_guardian'] ?? '',
                'course'               => $payload['course'] ?? '',
                'year_level'           => $payload['year_level'] ?? '',
                'talents_hobbies'      => $payload['talents_hobbies'] ?? '',
                'financial_support'    => $payload['financial_support'] ?? [],
                'scholar_provider'     => $payload['scholar_provider'] ?? '',
                'others_specify'       => $payload['others_specify'] ?? '',
                'faculty_advisers'     => $payload['faculty_advisers'] ?? '',
                'semester'             => $payload['semester'] ?? '',
                'season'               => $payload['season'] ?? '',
                'school_year'          => $payload['school_year'] ?? '',
                'date_filed'           => $payload['date_filed'] ?? '',
                'student_id'           => $payload['student_id'] ?? '',
                'id_photo_front_url'   => ($payload['id_photo_front'] ?? '') ? Storage::url($payload['id_photo_front']) : null,
                'id_photo_back_url'    => ($payload['id_photo_back'] ?? '') ? Storage::url($payload['id_photo_back']) : null,
            ];
            $requesterName = trim(($payload['first_name'] ?? '').' '.($payload['last_name'] ?? ''));
            $currentRole = 'new';
            $requestedRole = 'officer';
            $memberInitiated = false;
        } else {
            [$targetUserId, $organizationId, $currentRole] = $this->parseRoleChangeAction($actionRequest->action);
            $displayType = 'role_change';
            $reviewPayload = null;
            $memberInitiated = (bool) ($payload['member_initiated'] ?? false);
            $requestedRole = $this->nextRole($currentRole);
            $userNameMap = $this->userNameMap([$targetUserId]);
            $requesterName = $userNameMap[$targetUserId] ?? 'Unknown User';
        }

        $orgNameMap = $this->orgNameMap([$organizationId]);
        $orgName = $orgNameMap[$organizationId] ?? 'Unknown Organization';

        return view('pages.sidebar.promotion-request-show', [
            'title'           => 'Review Promotion Request',
            'actionRequest'   => $actionRequest,
            'displayType'     => $displayType,
            'reviewPayload'   => $reviewPayload,
            'approval'        => $approval,
            'orgName'         => $orgName,
            'requesterName'   => $requesterName,
            'currentRole'     => $currentRole,
            'requestedRole'   => $requestedRole,
            'memberInitiated' => $memberInitiated,
        ]);
    }

    public function confirmation(int $submissionId, Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $submission = FormSubmission::query()->with('form')->findOrFail($submissionId);
        $organizationId = (int) ($submission->organization_id ?? 0);

        if (! $isAdmin) {
            $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
            if (! in_array($organizationId, $presidentOrganizationIds, true)) {
                abort(403, 'You are not authorized to confirm this document.');
            }
        }

        $payload = (array) ($submission->payload ?? []);

        if (empty($payload['school_year'])) {
            $payload['school_year'] = Semester::current()?->schoolYear() ?? '';
        }

        return view('pages.sidebar.promotion-confirmation', [
            'title' => 'Confirm Promotion Details',
            'submission' => $submission,
            'payload' => $payload,
        ]);
    }

    public function confirm(int $submissionId, Request $request, DocumentGenerationService $documentGenerationService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();
        $isAdmin = (int) $user->user_type === 2;

        $submission = FormSubmission::query()->findOrFail($submissionId);
        $organizationId = (int) ($submission->organization_id ?? 0);

        if (! $isAdmin) {
            $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
            if (! in_array($organizationId, $presidentOrganizationIds, true)) {
                abort(403);
            }
        }

        $incoming = $request->except(['_token']);
        $currentPayload = (array) ($submission->payload ?? []);
        $mergedPayload = array_merge($currentPayload, array_filter($incoming, fn ($v) => $v !== null && $v !== ''));

        // Handle checkbox array for financial support
        if ($request->has('financial_support')) {
            $financial = (array) $request->input('financial_support', []);
            $mergedPayload['c1'] = in_array('parents_guardians', $financial) ? 'ü' : '';
            $mergedPayload['c2'] = in_array('scholarship', $financial) ? 'ü' : '';
            $mergedPayload['c3'] = in_array('assistantship', $financial) ? 'ü' : '';
            $mergedPayload['c4'] = in_array('others', $financial) ? 'ü' : '';
        }

        // Handle file uploads (photo, signature)
        foreach (['photo', 'signature'] as $fileField) {
            if ($request->hasFile($fileField) && $request->file($fileField)->isValid()) {
                $ext = $request->file($fileField)->getClientOriginalExtension();
                $filename = Str::uuid().'.'.$ext;
                $path = $request->file($fileField)->storeAs(
                    'form-submissions/'.$submissionId,
                    $filename,
                    ['disk' => 'public']
                );
                $mergedPayload[$fileField] = $path;
            }
        }

        $submission->update(['payload' => $mergedPayload]);

        $form = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::SIGN_UP);

        if (! $form) {
            return redirect()->route('promotion-requests')
                ->with('status', 'Details saved. No Student Leader Directory form found — document not generated.');
        }

        try {
            $documentGenerationService->generateFromSubmission(
                $submission->fresh(['form']),
                null,
                $userId,
            );

            return redirect()->route('download-files')
                ->with('success', 'Document generated successfully and is now available for download.');
        } catch (\Throwable $e) {
            return redirect()->route('promotion-requests')
                ->with('status', 'Details saved, but document generation failed: '.$e->getMessage());
        }
    }

    private function parseNewOfficerAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));
        $organizationId = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 0;

        return [$organizationId];
    }

    private function parseRoleChangeAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 3) {
            return [0, 0, 'member'];
        }

        $userId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $organizationId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $currentRole = in_array($parts[2], ['member', 'officer', 'president'], true) ? $parts[2] : 'member';

        return [$userId, $organizationId, $currentRole];
    }

    private function nextRole(string $currentRole): string
    {
        return match ($currentRole) {
            'member' => 'officer',
            'officer' => 'president',
            default => 'president',
        };
    }

    private function orgNameMap(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('o.organization_id', $ids)
            ->get(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name")])
            ->mapWithKeys(fn ($r) => [(int) $r->organization_id => (string) $r->name])
            ->all();
    }

    private function userNameMap(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('u.user_id', $ids)
            ->get(['u.user_id', 'p.first_name', 'p.middle_name', 'p.last_name', 'u.user_email'])
            ->mapWithKeys(function ($r) {
                $name = trim(implode(' ', array_filter([$r->first_name, $r->middle_name, $r->last_name])));
                return [(int) $r->user_id => $name !== '' ? $name : (string) $r->user_email];
            })
            ->all();
    }
}
