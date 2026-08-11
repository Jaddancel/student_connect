<?php

namespace App\Http\Controllers;

use App\Forms\SystemFunction;
use App\Helpers\FormTemplateHelper;
use App\Http\Resources\ApprovalResource;
use App\Mail\OfficerActivationMail;
use App\Mail\SignupApprovedMail;
use App\Mail\SignupRejectedMail;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use App\Support\OfficerProfileData;
use App\Support\SignupRequests;
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
    /**
     * How many times a sign-up may be rejected before the applicant's guest
     * account is closed. The count is per account, so it resets if they ever
     * start over with a fresh sign-up.
     */
    public const MAX_SIGNUP_REJECTIONS = 3;

    public function store(Request $request, int $requestId, DocumentGenerationService $documentGenerationService): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
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
                'rejection_reason' => $validated['decision'] === 'reject'
                    ? (($validated['rejection_reason'] ?? '') !== '' ? $validated['rejection_reason'] : null)
                    : null,
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
            $applicant = $this->pendingApplicant($payload);

            // Sign-ups create the applicant's account up front (as a pending
            // guest), so approving it is a promotion. Requests filed before
            // that — still sitting in the queue — carry no account, and are
            // approved the old way: create it here, then email an activation
            // link so the address is proven before the first sign-in.
            $newUserId = $applicant
                ? $this->promoteApplicant($applicant, $payload, $approval)
                : $this->createOfficerAccount($payload, $approval);

            $this->sendApprovalMail($applicant, $payload);

            // Generate the Directory of Student Leader PDF now that the officer account exists
            $formSubmissionId = (int) ($payload['form_submission_id'] ?? 0);
            if ($formSubmissionId > 0 && $newUserId > 0) {
                $submission = FormSubmission::query()->find($formSubmissionId);
                if ($submission) {
                    $submission->update(['submitted_by' => $newUserId]);
                    try {
                        $generatedDoc = $documentGenerationService->generateFromSubmission(
                            $submission->fresh(['form']),
                            (int) $actionRequest->getKey(),
                            (int) $user->getKey(),
                        );
                        $generatedDoc->update(['approval_id' => (int) $approval->approval_id]);
                    } catch (\Throwable) {
                        // Document generation failure does not roll back the approval
                    }
                }
            }
        }

        if ($this->isNewOfficerRequest($actionType) && $validated['decision'] === 'reject') {
            $this->handleSignupRejection($actionRequest, $approval);
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

    /**
     * The pending guest account a sign-up request belongs to, if it has one.
     * Requests filed before pending accounts existed simply return null.
     *
     * @param  array<string,mixed>  $payload
     */
    private function pendingApplicant(array $payload): ?User
    {
        $applicantId = (int) ($payload['pending_user_id'] ?? 0);
        if ($applicantId <= 0) {
            return null;
        }

        $applicant = User::query()->find($applicantId);

        return $applicant && $applicant->isGuest() ? $applicant : null;
    }

    /**
     * Approve a sign-up whose account already exists: the guest becomes an
     * officer and joins the organization they applied to.
     *
     * @param  array<string,mixed>  $payload
     * @return int  the promoted user's id
     */
    private function promoteApplicant(User $applicant, array $payload, Approval $approval): int
    {
        DB::transaction(function () use ($applicant, $payload, $approval) {
            $applicant->forceFill([
                'user_type' => User::TYPE_OFFICER,
                // Approval implies the address is good; a confirmed applicant
                // is already verified, and this covers the rare case where an
                // admin approves before they got round to the email.
                'email_verified_at' => $applicant->email_verified_at ?? now(),
            ])->save();

            $organizationId = (int) ($payload['organization_id'] ?? 0);
            if ($organizationId > 0) {
                DB::table('organization_officers')->updateOrInsert(
                    ['user' => (int) $applicant->getKey(), 'organization' => $organizationId],
                    [
                        'approval' => (int) $approval->approval_id,
                        'role' => 'officer',
                        'yearterm' => null,
                        'member_since' => now(),
                        'registered_at' => now(),
                        'reassigned_at' => now(),
                    ],
                );
            }
        });

        return (int) $applicant->getKey();
    }

    /**
     * Legacy path: create the officer account at approval time, for sign-up
     * requests filed before applicants got a pending account of their own.
     *
     * @param  array<string,mixed>  $payload
     * @return int  the new user's id
     */
    private function createOfficerAccount(array $payload, Approval $approval): int
    {
        $newUserId = 0;

        DB::transaction(function () use ($payload, $approval, &$newUserId) {
            $address = OfficerProfileData::createAddress($payload);
            $profile = Profile::create(OfficerProfileData::fromPayload($payload, (int) $address->profile_address_id));

            $newUser = User::create([
                'user_email' => (string) ($payload['email'] ?? ''),
                'google_id' => $payload['google_id'] ?? null,
                // A Google-linked email is already verified by Google.
                'email_verified_at' => ! empty($payload['google_id']) ? now() : null,
                'user_password' => (string) ($payload['password'] ?? Hash::make(Str::random(16))),
                'user_type' => User::TYPE_OFFICER,
                'profile' => (int) $profile->profile_id,
                'profile_pending' => false,
            ]);

            $organizationId = (int) ($payload['organization_id'] ?? 0);
            if ($organizationId > 0) {
                DB::table('organization_officers')->insert([
                    'user' => (int) $newUser->user_id,
                    'approval' => (int) $approval->approval_id,
                    'organization' => $organizationId,
                    'role' => 'officer',
                    'yearterm' => null,
                    'member_since' => now(),
                    'registered_at' => now(),
                    'reassigned_at' => now(),
                ]);
            }

            $newUserId = (int) $newUser->user_id;
        });

        return $newUserId;
    }

    /**
     * Tell the applicant they were approved: a promoted account is ready to
     * sign in to, a freshly created one still needs its activation link.
     *
     * @param  array<string,mixed>  $payload
     */
    private function sendApprovalMail(?User $applicant, array $payload): void
    {
        $recipientEmail = (string) ($payload['email'] ?? '');
        if ($recipientEmail === '') {
            return;
        }

        try {
            if ($applicant !== null) {
                Mail::to($recipientEmail)->send(new SignupApprovedMail(
                    firstName: (string) ($payload['first_name'] ?? ''),
                    organizationName: (string) ($payload['organization_name'] ?? ''),
                    position: (string) ($payload['position'] ?? ''),
                    dashboardUrl: route('dashboard'),
                ));

                return;
            }

            $rawToken = Str::random(64);
            DB::table('invitation_tokens')->updateOrInsert(
                ['user_email' => $recipientEmail],
                [
                    'token' => hash('sha256', $rawToken),
                    'created_at' => now(),
                    'expires_at' => now()->addHours(72),
                ],
            );

            Mail::to($recipientEmail)->send(new OfficerActivationMail(
                recipientEmail: $recipientEmail,
                organizationName: (string) ($payload['organization_name'] ?? ''),
                position: (string) ($payload['position'] ?? ''),
                activationUrl: route('invitation.verify', ['token' => $rawToken]),
            ));
        } catch (\Throwable) {
            // Email failure does not roll back the approval.
        }
    }

    /**
     * A rejected sign-up: tell the applicant why, and how many attempts they
     * have left. On the final rejection the guest account is closed — the
     * request rows survive (their `user` column is nulled by the FK), so the
     * audit trail of what was submitted and why it was refused is kept.
     */
    private function handleSignupRejection(ActionRequest $actionRequest, Approval $approval): void
    {
        $payload = (array) ($actionRequest->payload ?? []);
        $applicant = $this->pendingApplicant($payload);

        if ($applicant === null) {
            return; // legacy request: no account exists to notify or close
        }

        $rejections = SignupRequests::rejectionCount($applicant);
        $isFinal = $rejections >= self::MAX_SIGNUP_REJECTIONS;
        $signUpForm = SystemFunction::form(SystemFunction::SIGN_UP);

        try {
            Mail::to((string) $applicant->user_email)->send(new SignupRejectedMail(
                firstName: (string) ($payload['first_name'] ?? ''),
                reason: (string) ($approval->rejection_reason ?? ''),
                attemptsLeft: max(0, self::MAX_SIGNUP_REJECTIONS - $rejections),
                accountDeleted: $isFinal,
                retryUrl: $signUpForm && $signUpForm->route_name
                    ? route('forms.render', $signUpForm->route_name)
                    : route('login'),
            ));
        } catch (\Throwable) {
            // Email failure does not roll back the decision.
        }

        if ($isFinal) {
            $this->closeGuestAccount($applicant);
        }
    }

    /**
     * Remove a guest account that ran out of attempts, along with the profile
     * it owns and any unused confirmation token. Sign-up requests and form
     * submissions are left in place for the record.
     */
    private function closeGuestAccount(User $applicant): void
    {
        DB::transaction(function () use ($applicant) {
            $profileId = (int) ($applicant->profile ?? 0);
            $email = (string) $applicant->user_email;

            $applicant->delete();

            if ($profileId > 0) {
                Profile::query()->where('profile_id', $profileId)->delete();
            }

            DB::table('invitation_tokens')->where('user_email', $email)->delete();
        });
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
        $form = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::SIGN_UP);

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
