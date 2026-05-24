<?php

namespace App\Http\Controllers;

use App\Helpers\FormTemplateHelper;
use App\Http\Resources\ApprovalResource;
use App\Mail\OfficerActivationMail;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Profile;
use App\Models\Profile\profileAddress;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RequestDecisionController extends Controller
{
    public function store(Request $request, int $requestId, DocumentGenerationService $documentGenerationService): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
        ]);

        $actionRequest = ActionRequest::query()->with('requestType')->findOrFail($requestId);
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $actionType = (int) $actionRequest->action_type;
        $requestType = $actionRequest->requestType;
        $systemKey = (string) ($requestType?->system_key ?? '');

        $requiredDashboard = $this->requiredDashboardForRequest($actionType, $systemKey, $actionRequest);

        if (! Gate::forUser($user)->allows('access-dashboard', $requiredDashboard)) {
            return response()->json([
                'message' => 'You are not authorized to decide this request.',
            ], 403);
        }

        $authorizedOrganizationIds = $this->authorizedOrganizationIdsForRequest($actionType, $systemKey, (int) $user->getKey());

        if ($authorizedOrganizationIds !== null) {
            $organizationId = $this->requestOrganizationId($actionRequest, $actionType, $systemKey);

            if ($organizationId !== null && ! in_array($organizationId, $authorizedOrganizationIds, true)) {
                return response()->json([
                    'message' => 'You are not authorized to decide this request.',
                ], 403);
            }
        }

        $generatedDocumentId = null;

        if ($this->isDocumentGenerationRequest($actionType, $systemKey)
            && $validated['decision'] === 'approve') {
            try {
                $generatedDocument = $documentGenerationService->generateFromApprovedRequest(
                    $actionRequest,
                    (int) $user->getKey(),
                );

                $generatedDocumentId = (int) $generatedDocument->getKey();
            } catch (\Throwable $throwable) {
                return response()->json([
                    'message' => $throwable->getMessage(),
                ], 422);
            }

            $this->maybeCreateOrganization($actionRequest);
        }

        $approval = Approval::query()->updateOrCreate(
            ['request' => (int) $actionRequest->getKey()],
            [
                'admin' => (int) $user->getKey(),
                'approved_at' => now(),
                'is_rejected' => $validated['decision'] === 'reject',
            ]
        );

        if ($generatedDocumentId > 0) {
            \App\Models\GeneratedDocument::where('generated_document_id', $generatedDocumentId)
                ->update(['approval_id' => (int) $approval->approval_id]);
        }

        if ($this->isMembershipRequest($actionType, $systemKey) && $validated['decision'] === 'approve') {
            [$targetOrganizationId, $targetUserId] = $this->resolveMembershipDetails($actionRequest);

            if ($targetOrganizationId > 0 && $targetUserId > 0) {
                DB::table('organization_officers')
                    ->where('user', $targetUserId)
                    ->where('organization', $targetOrganizationId)
                    ->whereIn('role', ['officer', 'president'])
                    ->delete();

                DB::table('organization_officers')->updateOrInsert(
                    ['user' => $targetUserId, 'organization' => $targetOrganizationId],
                    ['role' => 'member', 'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now()]
                );
            }
        }

        if ($this->isRoleChangeRequest($actionType, $systemKey) && $validated['decision'] === 'approve') {
            [$targetUserId, $targetOrganizationId, $currentRole] = $this->resolveRoleChangeDetails($actionRequest);

            if ($targetOrganizationId > 0 && $targetUserId > 0) {
                if ($currentRole === 'member') {
                    DB::table('organization_officers')->updateOrInsert(
                        ['user' => $targetUserId, 'organization' => $targetOrganizationId],
                        ['role' => 'officer', 'yearterm' => null, 'registered_at' => now(), 'reassigned_at' => now()]
                    );
                }

                if ($currentRole === 'officer') {
                    // Demote existing president(s) in this org before promoting the new one
                    DB::table('organization_officers')
                        ->where('organization', $targetOrganizationId)
                        ->where('role', 'president')
                        ->where('user', '!=', $targetUserId)
                        ->update(['role' => 'officer', 'reassigned_at' => now()]);

                    DB::table('organization_officers')
                        ->where('user', $targetUserId)
                        ->where('organization', $targetOrganizationId)
                        ->update(['role' => 'president', 'reassigned_at' => now()]);

                    $hasPresidentRole = DB::table('organization_officers')
                        ->where('user', $targetUserId)
                        ->where('organization', $targetOrganizationId)
                        ->where('role', 'president')
                        ->exists();

                    if (! $hasPresidentRole) {
                        DB::table('organization_officers')->insert([
                            'user' => $targetUserId,
                            'organization' => $targetOrganizationId,
                            'role' => 'president',
                            'yearterm' => null,
                            'member_since' => now(),
                            'registered_at' => now(),
                            'reassigned_at' => now(),
                        ]);
                    }

                }

                // Auto-create a pre-filled FormSubmission for the Student Leader Directory
                $this->createPromotionFormSubmission(
                    $targetUserId,
                    $targetOrganizationId,
                    $currentRole === 'member' ? 'Officer' : 'President',
                    (int) $actionRequest->getKey(),
                    (int) $user->getKey(),
                );
            }
        }

        if ($this->isEventRequest($actionType, $systemKey) && $validated['decision'] === 'approve') {
            [$targetOrganizationId, $requesterUserId, $eventName, $eventStartTime, $eventEndTime, $eventDescription, $eventLocation] = $this->resolveEventDetails($actionRequest);

            if ($targetOrganizationId <= 0 || $eventName === '' || $eventStartTime === '' || $eventEndTime === '' || $eventLocation === '') {
                return response()->json([
                    'message' => 'Invalid event request payload.',
                ], 422);
            }

            $eventDetail = EventDetail::query()->create([
                'name' => $eventName,
                'location' => $eventLocation,
                'desc_text' => $eventDescription,
                'start_time' => $eventStartTime,
                'end_time' => $eventEndTime,
            ]);

            Event::query()->create([
                'organization' => $targetOrganizationId,
                'creator' => $requesterUserId > 0 ? $requesterUserId : (int) $actionRequest->user,
                'event_detail' => (int) $eventDetail->getKey(),
            ]);
        }

        if ($this->isNewOfficerRequest($actionType) && $validated['decision'] === 'approve') {
            $payload = (array) ($actionRequest->payload ?? []);

            DB::transaction(function () use ($payload, $approval) {
                $addr = profileAddress::create([
                    'country'  => 'Philippines',
                    'province' => '',
                    'town'     => '',
                    'barangay' => (string) ($payload['present_address'] ?? ''),
                ]);

                $profile = Profile::create([
                    'first_name'              => (string) ($payload['first_name'] ?? ''),
                    'middle_name'             => (string) ($payload['middle_name'] ?? ''),
                    'last_name'               => (string) ($payload['last_name'] ?? ''),
                    'contact_number'          => (string) ($payload['contact_number'] ?? ''),
                    'age'                     => (int)    ($payload['age'] ?? 0),
                    'sex'                     => (string) ($payload['sex'] ?? ''),
                    'religion'                => (string) ($payload['religious_affiliation'] ?? ''),
                    'nationality'             => (string) ($payload['nationality'] ?? ''),
                    'birthday'                => (string) ($payload['birthday'] ?? ''),
                    'birthplace'              => (string) ($payload['birthplace'] ?? ''),
                    'course_year'             => trim(($payload['course'] ?? '') . ' - ' . ($payload['year_level'] ?? '')),
                    'occupation'              => 'Student',
                    'address'                 => (int) $addr->profile_address_id,
                    'position'                => (string) ($payload['position'] ?? ''),
                    'photo'                   => (string) ($payload['photo'] ?? ''),
                    'home_address'            => (string) ($payload['home_address'] ?? ''),
                    'parents_guardian'        => (string) ($payload['parents_guardian'] ?? ''),
                    'talents_hobbies'         => (string) ($payload['talents_hobbies'] ?? ''),
                    'financial_support'       => $payload['financial_support'] ?? [],
                    'scholar_provider'        => (string) ($payload['scholar_provider'] ?? ''),
                    'financial_support_other' => (string) ($payload['others_specify'] ?? ''),
                    'student_id'              => (string) ($payload['student_id'] ?? ''),
                    'id_photo_front'          => (string) ($payload['id_photo_front'] ?? ''),
                    'id_photo_back'           => (string) ($payload['id_photo_back'] ?? ''),
                ]);

                $newUser = User::create([
                    'user_email'      => (string) ($payload['email'] ?? ''),
                    'user_password'   => (string) ($payload['password'] ?? Hash::make(Str::random(16))),
                    'user_type'       => 3,
                    'profile'         => (int) $profile->profile_id,
                    'profile_pending' => false,
                ]);

                $orgId = (int) ($payload['organization_id'] ?? 0);
                if ($orgId > 0) {
                    DB::table('organization_officers')->insert([
                        'user'          => (int) $newUser->user_id,
                        'approval'      => (int) $approval->approval_id,
                        'organization'  => $orgId,
                        'role'          => 'officer',
                        'yearterm'      => null,
                        'member_since'  => now(),
                        'registered_at' => now(),
                        'reassigned_at' => now(),
                    ]);
                }
            });

            // Send activation email after the transaction completes
            $recipientEmail = (string) ($payload['email'] ?? '');
            if ($recipientEmail !== '') {
                $rawToken = Str::random(64);
                DB::table('invitation_tokens')->updateOrInsert(
                    ['user_email' => $recipientEmail],
                    [
                        'token'      => hash('sha256', $rawToken),
                        'created_at' => now(),
                        'expires_at' => now()->addHours(72),
                    ]
                );

                try {
                    Mail::to($recipientEmail)->send(new OfficerActivationMail(
                        recipientEmail:   $recipientEmail,
                        organizationName: (string) ($payload['organization_name'] ?? ''),
                        position:         (string) ($payload['position'] ?? ''),
                        activationUrl:    route('invitation.verify', ['token' => $rawToken]),
                    ));
                } catch (\Throwable) {
                    // Email failure does not roll back the approval
                }
            }
        }

        if ($this->isEventPlanRequest($actionType, $systemKey)) {
            $payload = (array) ($actionRequest->payload ?? []);
            $eventPlanId = (int) ($payload['event_plan_id'] ?? 0);

            if ($eventPlanId > 0) {
                $newStatus = $validated['decision'] === 'approve' ? 'approved' : 'rejected';
                EventPlan::query()->where('event_plan_id', $eventPlanId)->update(['status' => $newStatus]);
            }
        }

        return response()->json([
            'message' => $validated['decision'] === 'approve'
                ? 'Request approved successfully.'
                : 'Request rejected successfully.',
            'generated_document_id' => $generatedDocumentId,
            'data' => ApprovalResource::make($approval->fresh()),
        ]);
    }

    public function approveWithSignatures(Request $request, int $requestId, DocumentGenerationService $documentGenerationService): JsonResponse
    {
        $validated = $request->validate([
            'chair'            => ['required', 'string', 'max:255'],
            'director'         => ['required', 'string', 'max:255'],
            'signatureChair'   => ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
            'signatureDirector'=> ['required', 'file', 'mimes:jpeg,png', 'max:2048'],
        ]);

        $actionRequest = ActionRequest::query()->with('requestType')->findOrFail($requestId);
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $actionType = (int) $actionRequest->action_type;
        $requestType = $actionRequest->requestType;
        $systemKey = (string) ($requestType?->system_key ?? '');

        if (! $this->isDocumentGenerationRequest($actionType, $systemKey)) {
            return response()->json(['message' => 'This endpoint is only for document generation requests.'], 422);
        }

        $requiredDashboard = $this->requiredDashboardForRequest($actionType, $systemKey, $actionRequest);
        if (! Gate::forUser($user)->allows('access-dashboard', $requiredDashboard)) {
            return response()->json(['message' => 'You are not authorized to decide this request.'], 403);
        }

        [$organizationId, $submissionId] = FormTemplateHelper::decodeDocumentGenerationAction($actionRequest->action);

        $submission = FormSubmission::query()->find($submissionId);
        if (! $submission) {
            return response()->json(['message' => 'Submission not found.'], 422);
        }

        $sigDir = 'form-signatures/' . now()->format('Y/m');
        $sigChairPath = '';
        $sigDirectorPath = '';

        if ($request->hasFile('signatureChair') && $request->file('signatureChair')->isValid()) {
            $file = $request->file('signatureChair');
            $sigChairPath = $file->storeAs($sigDir, Str::lower(Str::random(16)) . '.' . $file->getClientOriginalExtension(), 'public');
        }

        if ($request->hasFile('signatureDirector') && $request->file('signatureDirector')->isValid()) {
            $file = $request->file('signatureDirector');
            $sigDirectorPath = $file->storeAs($sigDir, Str::lower(Str::random(16)) . '.' . $file->getClientOriginalExtension(), 'public');
        }

        $payload = (array) ($submission->payload ?? []);
        $payload['chair']            = $validated['chair'];
        $payload['director']         = $validated['director'];
        $payload['signatureChair']   = $sigChairPath;
        $payload['signatureDirector']= $sigDirectorPath;
        $submission->update(['payload' => $payload]);

        try {
            $generatedDocument = $documentGenerationService->generateFromApprovedRequest(
                $actionRequest,
                (int) $user->getKey(),
            );
            $generatedDocumentId = (int) $generatedDocument->getKey();
        } catch (\Throwable $throwable) {
            return response()->json(['message' => $throwable->getMessage()], 422);
        }

        $this->maybeCreateOrganization($actionRequest);

        $approval = Approval::query()->updateOrCreate(
            ['request' => (int) $actionRequest->getKey()],
            [
                'admin'       => (int) $user->getKey(),
                'approved_at' => now(),
                'is_rejected' => false,
            ]
        );

        if ($generatedDocumentId > 0) {
            \App\Models\GeneratedDocument::where('generated_document_id', $generatedDocumentId)
                ->update(['approval_id' => (int) $approval->approval_id]);
        }

        return response()->json([
            'message'               => 'Request approved successfully.',
            'generated_document_id' => $generatedDocumentId,
            'data'                  => ApprovalResource::make($approval->fresh()),
        ]);
    }

    private function requiredDashboardForRequest(int $actionType, string $systemKey, ?ActionRequest $actionRequest = null): string
    {
        if ($systemKey === RequestType::SYSTEM_KEY_PROFILE_MATCH || $actionType === 9) {
            return 'superadmin';
        }

        if ($this->isAdminScopeRequest($actionType, $systemKey)) {
            return 'sysadmin';
        }

        // Member-initiated president requests (officer → president) must be approved by Admin
        if ($this->isRoleChangeRequest($actionType, $systemKey) && $actionRequest !== null) {
            $payload = (array) ($actionRequest->payload ?? []);
            if (($payload['member_initiated'] ?? false) && ($payload['current_role'] ?? '') === 'officer') {
                return 'sysadmin';
            }
        }

        if ($this->isPresidentScopeRequest($actionType, $systemKey)) {
            return 'president';
        }

        return 'admin';
    }

    private function authorizedOrganizationIdsForRequest(int $actionType, string $systemKey, int $userId): ?array
    {
        if ($systemKey === RequestType::SYSTEM_KEY_PROFILE_MATCH || $actionType === 9) {
            return null;
        }

        if ($this->isAdminScopeRequest($actionType, $systemKey)) {
            return null;
        }

        if ($this->isPresidentScopeRequest($actionType, $systemKey)) {
            return OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        }

        return OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
    }

    private function isAdminScopeRequest(int $actionType, string $systemKey): bool
    {
        return in_array($actionType, [3, 4, 8, 11], true)
            || in_array($systemKey, [
                RequestType::SYSTEM_KEY_FORM_GENERATION,
                RequestType::SYSTEM_KEY_FORM_ACCESS,
                RequestType::SYSTEM_KEY_FORM_UPLOAD,
            ], true);
    }

    private function isNewOfficerRequest(int $actionType): bool
    {
        return $actionType === 11;
    }

    private function isPresidentScopeRequest(int $actionType, string $systemKey): bool
    {
        return in_array($actionType, [2, 7, 10], true)
            || in_array($systemKey, [
                RequestType::SYSTEM_KEY_EVENT,
                RequestType::SYSTEM_KEY_ROLE_CHANGE,
                RequestType::SYSTEM_KEY_EVENT_PLAN,
            ], true);
    }

    private function isEventPlanRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === 10 || $systemKey === RequestType::SYSTEM_KEY_EVENT_PLAN;
    }

    private function isMembershipRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === 1 || $systemKey === RequestType::SYSTEM_KEY_MEMBERSHIP;
    }

    private function isRoleChangeRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === 7 || $systemKey === RequestType::SYSTEM_KEY_ROLE_CHANGE;
    }

    private function isEventRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === 2 || $systemKey === RequestType::SYSTEM_KEY_EVENT;
    }

    private function isDocumentGenerationRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION
            || $systemKey === RequestType::SYSTEM_KEY_FORM_GENERATION;
    }

    private function requestOrganizationId(ActionRequest $actionRequest, int $actionType, string $systemKey): ?int
    {
        $organizationId = (int) ($actionRequest->organization_id ?? 0);

        if ($organizationId > 0) {
            return $organizationId;
        }

        $payload = (array) ($actionRequest->payload ?? []);
        $payloadOrganizationId = (int) ($payload['organization_id'] ?? 0);

        if ($payloadOrganizationId > 0) {
            return $payloadOrganizationId;
        }

        if ($this->isMembershipRequest($actionType, $systemKey)) {
            return $this->parseMembershipAction($actionRequest->action)[0] ?: null;
        }

        if ($this->isRoleChangeRequest($actionType, $systemKey)) {
            return $this->parseRoleChangeAction($actionRequest->action)[1] ?: null;
        }

        if ($this->isEventRequest($actionType, $systemKey)) {
            return $this->parseEventAction($actionRequest->action)[0] ?: null;
        }

        if ($this->isDocumentGenerationRequest($actionType, $systemKey)
            || $systemKey === RequestType::SYSTEM_KEY_FORM_ACCESS
            || $actionType === FormTemplateHelper::ACTION_TYPE_DOCUMENT_ACCESS
            || $actionType === FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD) {
            $organizationId = OrganizationAuthorizationService::extractOrganizationIdFromAction($actionRequest->action);

            return $organizationId > 0 ? $organizationId : null;
        }

        return null;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveMembershipDetails(ActionRequest $actionRequest): array
    {
        $payload = (array) ($actionRequest->payload ?? []);

        $organizationId = (int) ($payload['organization_id'] ?? 0);
        $targetUserId = (int) ($payload['user_id'] ?? 0);

        if ($organizationId > 0 && $targetUserId > 0) {
            return [$organizationId, $targetUserId];
        }

        return $this->parseMembershipAction($actionRequest->action);
    }

    /**
     * @return array{0:int,1:int,2:string}
     */
    private function resolveRoleChangeDetails(ActionRequest $actionRequest): array
    {
        $payload = (array) ($actionRequest->payload ?? []);

        $targetUserId = (int) ($payload['target_user_id'] ?? 0);
        $organizationId = (int) ($payload['organization_id'] ?? 0);
        $currentRole = in_array((string) ($payload['current_role'] ?? ''), ['member', 'officer', 'president'], true)
            ? (string) $payload['current_role']
            : 'member';

        if ($targetUserId > 0 && $organizationId > 0) {
            return [$targetUserId, $organizationId, $currentRole];
        }

        return $this->parseRoleChangeAction($actionRequest->action);
    }

    /**
     * @return array{0:int,1:int,2:string,3:string,4:string,5:string,6:string}
     */
    private function resolveEventDetails(ActionRequest $actionRequest): array
    {
        $payload = (array) ($actionRequest->payload ?? []);

        $organizationId = (int) ($payload['organization_id'] ?? 0);
        $requesterUserId = (int) ($payload['user_id'] ?? 0);
        $eventName = trim((string) ($payload['name'] ?? ''));
        $eventStartTime = trim((string) ($payload['start_time'] ?? ''));
        $eventEndTime = trim((string) ($payload['end_time'] ?? ''));
        $eventDescription = trim((string) ($payload['desc_text'] ?? ''));
        $eventLocation = trim((string) ($payload['location'] ?? ''));

        if ($organizationId > 0 && $requesterUserId > 0 && $eventName !== '' && $eventStartTime !== '' && $eventEndTime !== '' && $eventLocation !== '') {
            return [$organizationId, $requesterUserId, $eventName, $eventStartTime, $eventEndTime, $eventDescription, $eventLocation];
        }

        return $this->parseEventAction($actionRequest->action);
    }

    private function maybeCreateOrganization(ActionRequest $actionRequest): void
    {
        $payload = (array) ($actionRequest->payload ?? []);

        if (! ($payload['create_organization'] ?? false)) {
            return;
        }

        $submissionId = (int) ($payload['submission_id'] ?? 0);
        if ($submissionId <= 0) {
            return;
        }

        $submission = FormSubmission::query()->find($submissionId);
        if (! $submission) {
            return;
        }

        $orgName = trim((string) (($submission->payload ?? [])['organization'] ?? ''));
        if ($orgName === '') {
            return;
        }

        $exists = DB::table('organization_details')->where('name', $orgName)->exists();
        if ($exists) {
            return;
        }

        $detail = \App\Models\Organization\OrganizationDetail::create(['name' => $orgName]);
        \App\Models\Organization::create(['detail' => $detail->getKey()]);
    }

    protected function parseMembershipAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 2) {
            return [0, 0];
        }

        $organizationId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $userId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;

        return [$organizationId, $userId];
    }

    /**
     * @return array{0:int,1:int,2:string}
     */
    protected function parseRoleChangeAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 3) {
            return [0, 0, 'member'];
        }

        $userId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $organizationId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $currentRole = in_array($parts[2], ['member', 'officer', 'president'], true)
            ? $parts[2]
            : 'member';

        return [$userId, $organizationId, $currentRole];
    }

    /**
    * @return array{0:int,1:int,2:string,3:string,4:string,5:string,6:string}
     */
    protected function parseEventAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 7) {
            return [0, 0, '', '', '', '', ''];
        }

        $organizationId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $requesterUserId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $eventName = $parts[2];
        $eventStartTime = $parts[3];
        $eventEndTime = $parts[4];
        $eventDescription = $parts[5];
        $eventLocation = implode('|', array_slice($parts, 6));

        return [$organizationId, $requesterUserId, $eventName, $eventStartTime, $eventEndTime, $eventDescription, $eventLocation];
    }

    private function createPromotionFormSubmission(
        int $targetUserId,
        int $organizationId,
        string $requestedRole,
        int $promotionRequestId,
        int $approverUserId,
    ): void {
        $form = Form::query()->where('route_name', 'student-leader-directory')->first();

        if (! $form) {
            return;
        }

        $userRow = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->leftJoin('profile_addresses as pa', 'pa.profile_address_id', '=', 'p.address')
            ->where('u.user_id', $targetUserId)
            ->select([
                'p.first_name', 'p.middle_name', 'p.last_name',
                'p.occupation',
                'pa.barangay', 'pa.town', 'pa.province',
            ])
            ->first();

        $fullName = $userRow
            ? trim(implode(' ', array_filter([$userRow->first_name, $userRow->middle_name, $userRow->last_name])))
            : '';

        $address = $userRow
            ? trim(implode(', ', array_filter([$userRow->barangay, $userRow->town, $userRow->province])))
            : '';

        $orgName = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $organizationId)
            ->value('od.name') ?? '';

        FormSubmission::query()->create([
            'form_id' => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'submitted_by' => $approverUserId,
            'submitted_at' => now(),
            'payload' => [
                'name' => $fullName,
                'position' => $requestedRole,
                'organization' => $orgName,
                'present_address' => $address,
                'date_filed' => now()->format('Y-m-d'),
                'course' => $userRow?->occupation ?? '',
                'promotion_request_id' => $promotionRequestId,
            ],
        ]);
    }
}
