<?php

use App\Http\Controllers\Admin\AccomplishmentReportRequestController;
use App\Http\Controllers\Admin\ActivityRequestController as AdminActivityRequestController;
use App\Http\Controllers\Admin\AdminAccountCreationController;
use App\Http\Controllers\Admin\AdminOfficerCreationController;
use App\Http\Controllers\Admin\AdminWorkplanController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DatabaseViewController;
use App\Http\Controllers\Admin\FinancialReportRequestController;
use App\Http\Controllers\Admin\FormBuilderController;
use App\Http\Controllers\Admin\IdTemplateController;
use App\Http\Controllers\Admin\JointStatementRequestController;
use App\Http\Controllers\Admin\OrganizationScoringController;
use App\Http\Controllers\Admin\ProjectRequestController;
use App\Http\Controllers\Admin\RecognitionRequestController;
use App\Http\Controllers\Admin\RequestRecordController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\WorkplanRequestController;
use App\Http\Controllers\Auth\GoogleLinkController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\Auth\SystemSetupController;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\DashboardSearchController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventPlanController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FormBlankPdfController;
use App\Http\Controllers\FormDirectoryController;
use App\Http\Controllers\FormRenderController;
use App\Http\Controllers\ManualFormSessionController;
use App\Http\Controllers\GuestAccessController;
use App\Http\Controllers\IdScanController;
use App\Http\Controllers\LandingPage;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PolicySecurityRequestController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionRequestsPageController;
use App\Http\Controllers\RequestDecisionController;
use App\Http\Controllers\SidebarMenuController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\WorkplanController;
use App\Http\Resources\ActionRequestResource;
use App\Http\Resources\ApprovalResource;
use App\Http\Resources\UserResource;
use App\Models\Approval;
use App\Models\Organization;
use App\Models\Request;
use App\Models\Semester;
use App\Models\User;
use App\Models\Workplan;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// First-run system setup: creates the initial superadmin, who must confirm the
// email address before the account activates. Locked once one is confirmed.
Route::get('/setup', [SystemSetupController::class, 'create'])->name('setup.create');
Route::post('/setup', [SystemSetupController::class, 'store'])->name('setup.store');
Route::get('/setup/confirm', [SystemSetupController::class, 'pending'])->name('setup.pending');
Route::post('/setup/confirm/resend', [SystemSetupController::class, 'resend'])
    ->middleware('throttle:6,1')->name('setup.resend');
Route::get('/setup/confirm/{token}', [SystemSetupController::class, 'confirm'])->name('setup.confirm');
Route::post('/setup/restart', [SystemSetupController::class, 'restart'])->name('setup.restart');

Route::get('/', [LandingPage::class, 'view'])->name('home');
Route::get('/organizations/{organizationId}/{slug?}', [LandingPage::class, 'organizationFeed'])
    ->whereNumber('organizationId')
    ->name('organization-feed');

// dashboard pages
Route::get('/sample_dashboard', function () {
    return view('pages.dashboard.ecommerce', ['title' => 'E-commerce Dashboard']);
})->name('sample-dashboard');

Route::get('/dashboard', [Dashboard::class, 'viewDashboard'])->middleware('auth')->name('dashboard');

// Sign-up applicants: the post-submit page (public — they are not signed in
// yet) and the guest dashboard their confirmed account lands on.
Route::get('/signup/success', [GuestAccessController::class, 'success'])->name('signup.success');
Route::get('/guest', [GuestAccessController::class, 'dashboard'])
    ->middleware('auth')
    ->name('guest.dashboard');

// Auth routes.

Route::post('/login', Login::class)->middleware('guest');
Route::post('/signup', Register::class)->middleware('guest');
Route::post('/logout', Logout::class)->middleware('auth')->name('logout');

// Invitation activation (Flows 1, 2, 3)
Route::get('/invitation/verify', [InvitationController::class, 'verify'])
    ->middleware('guest')
    ->name('invitation.verify');

// New organization registration invitation redemption
Route::get('/organization-invitation/redeem', [\App\Http\Controllers\OrganizationInvitationController::class, 'redeem'])
    ->middleware('guest')
    ->name('organization-invitation.redeem');

// Public email availability check used by the New Officer Email field.
Route::get('/email-availability/check', [\App\Http\Controllers\EmailAvailabilityController::class, 'check'])
    ->name('email-availability.check');

// First-login password wizard (Flow 3 — admin accounts)
Route::get('/change-password', [PasswordChangeController::class, 'show'])
    ->middleware('auth')
    ->name('password.change');

Route::post('/change-password', [PasswordChangeController::class, 'update'])
    ->middleware('auth')
    ->name('password.change.update');

// Settings page (role-gated sections rendered inside the view).
Route::middleware('auth')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'show'])->name('settings');
    Route::post('/settings/notifications', [\App\Http\Controllers\SettingsController::class, 'updateNotifications'])
        ->name('settings.notifications');
    Route::post('/settings/password', [\App\Http\Controllers\SettingsController::class, 'updatePassword'])
        ->name('settings.password');
    Route::post('/settings/notify-days', [\App\Http\Controllers\SettingsController::class, 'updateNotifyDays'])
        ->name('settings.notify-days');
    Route::post('/settings/after-event-days', [\App\Http\Controllers\SettingsController::class, 'updateAfterEventDays'])
        ->name('settings.after-event-days');
    Route::post('/settings/accreditation-conditions', [\App\Http\Controllers\SettingsController::class, 'updateAccreditationConditions'])
        ->name('settings.accreditation-conditions');
    Route::post('/settings/backup-interval', [\App\Http\Controllers\SettingsController::class, 'updateBackupInterval'])
        ->name('settings.backup-interval');
});

// Notice shown to members whose organization's accreditation has lapsed
// (EnsureOrganizationAccredited redirects them here).
Route::get('/org-suspended', function () {
    return view('pages.org-suspended', ['title' => 'Organization Suspended']);
})->middleware('auth')->name('org-suspended');

// Auth pages.

Route::get('/login', fn () => redirect(route('home').'#auth'))->name('login');

// calender pages
Route::get('/calendar', function () {
    $user = request()->user();
    $isOfficerOrPresident = Gate::forUser($user)->allows('access-dashboard', 'officer')
        || Gate::forUser($user)->allows('access-dashboard', 'president');
    $canRequestEvent = $isOfficerOrPresident;
    $profileRow = DB::table('users as u')
        ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
        ->where('u.user_id', (int) $user->getKey())
        ->select(['p.first_name', 'p.middle_name', 'p.last_name', 'p.contact_number'])
        ->first();

    $presidentName = $profileRow ? trim(implode(' ', array_filter([
        $profileRow->first_name,
        $profileRow->middle_name,
        $profileRow->last_name,
    ]))) : '';

    $presidentContact = $profileRow?->contact_number ?? '';

    $eventRequestOrganizations = collect();
    $lockedOrgIds = [];
    $allSemesters = collect();
    $workplanStatuses = [];

    if ($canRequestEvent) {
        $eventRequestOrganizations = Organization::query()
            ->join('organization_officers as oo', 'oo.organization', '=', 'organizations.organization_id')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail')
            ->where('oo.user', (int) $user->getKey())
            ->orderBy('od.name')
            ->get([
                'organizations.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as name"),
            ])
            ->unique('organization_id')
            ->values();

        $activeSemester = Semester::currentlyActive();
        if ($activeSemester && $eventRequestOrganizations->isNotEmpty()) {
            $lockedOrgIds = Workplan::query()
                ->where('semester_id', $activeSemester->semester_id)
                ->whereIn('organization_id', $eventRequestOrganizations->pluck('organization_id'))
                ->whereIn('status', ['finalized', 'archived'])
                ->pluck('organization_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        // Semester data for calendar auto-toggle logic
        $allSemesters = Semester::query()->orderBy('starts_at')->get(['semester_id', 'starts_at', 'vacation_days']);

        if ($eventRequestOrganizations->isNotEmpty() && $allSemesters->isNotEmpty()) {
            $orgIds = $eventRequestOrganizations->pluck('organization_id')->all();
            Workplan::query()
                ->whereIn('organization_id', $orgIds)
                ->whereIn('semester_id', $allSemesters->pluck('semester_id'))
                ->get(['organization_id', 'semester_id', 'status'])
                ->each(function ($wp) use (&$workplanStatuses) {
                    $workplanStatuses[(int) $wp->organization_id][(int) $wp->semester_id] = $wp->status;
                });
        }
    }

    return view('pages.calendar', [
        'title' => 'Calendar',
        'canRequestEvent' => $canRequestEvent,
        'eventRequestOrganizations' => $eventRequestOrganizations,
        'lockedOrgIds' => $lockedOrgIds,
        'presidentName' => $presidentName,
        'presidentContact' => $presidentContact,
        'allSemesters' => $allSemesters,
        'workplanStatuses' => $workplanStatuses,
    ]);
})->middleware('auth')->name('calendar');

Route::get('/upcoming-events', [SidebarMenuController::class, 'upcomingEvents'])
    ->middleware('auth')
    ->name('upcoming-events');

// Membership registration is now the "Org Membership Registration"
// system-function form; /register redirects to whatever form is bound (or a
// friendly "disabled" message when none is).
Route::get('/register', fn () => redirect()->route('functions.show', 'membership_registration'))
    ->middleware('auth')
    ->name('register');

Route::get('/download-files', [SidebarMenuController::class, 'downloadFiles'])
    ->middleware('auth')
    ->name('download-files');

Route::get('/download-files/{documentId}/download', [SidebarMenuController::class, 'downloadDocument'])
    ->whereNumber('documentId')
    ->middleware('auth')
    ->name('download-files.download');

Route::get('/membership-requests', [SidebarMenuController::class, 'membershipRequests'])
    ->middleware('auth')
    ->name('membership-requests');

Route::get('/approval-requests', fn () => redirect()->route('membership-requests'))
    ->middleware('auth')
    ->name('approval-requests');

Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch'])
    ->whereNumber('organization')
    ->middleware('auth')
    ->name('organizations.switch');

Route::get('/request-forms', [SidebarMenuController::class, 'requestForms'])
    ->middleware(['auth', 'admin'])
    ->name('request-forms');

Route::post('/request-forms', [SidebarMenuController::class, 'storeRoleChangeRequest'])
    ->middleware(['auth', 'admin'])
    ->name('request-forms.store');

Route::get('/manage-organization', [OrganizationController::class, 'manage'])
    ->middleware('auth')
    ->name('manage-organization');

// Promotion requests management (President + Admin)
Route::get('/promotion-requests', [PromotionRequestsPageController::class, 'index'])
    ->middleware(['auth', 'admin'])->name('promotion-requests');
Route::get('/promotion-requests/{requestId}', [PromotionRequestsPageController::class, 'show'])
    ->whereNumber('requestId')->middleware(['auth', 'admin'])->name('promotion-requests.show');
Route::get('/promotion-requests/submissions/{submissionId}/confirm', [PromotionRequestsPageController::class, 'confirmation'])
    ->whereNumber('submissionId')->middleware(['auth', 'admin'])->name('promotion-requests.confirmation');
Route::post('/promotion-requests/submissions/{submissionId}/confirm', [PromotionRequestsPageController::class, 'confirm'])
    ->whereNumber('submissionId')->middleware(['auth', 'admin'])->name('promotion-requests.confirm');

Route::middleware(['auth', 'officer.or.admin'])->group(function () {
    Route::get('/event-plans', [EventPlanController::class, 'index'])
        ->name('event-plans');
    Route::patch('/event-plans/{id}/junk', [EventPlanController::class, 'junk'])
        ->whereNumber('id')
        ->name('event-plans.junk');
    Route::patch('/event-plans/{id}/revise', [EventPlanController::class, 'revise'])
        ->whereNumber('id')
        ->name('event-plans.revise');

    // The organization-recognition, accomplishment-report, activity-request,
    // project-request, financial-report and joint-statement forms are now
    // data-driven Form Builder pages (seeded with their historical route_name),
    // so their URLs fall through to the generic /forms/{routeName} renderer
    // below. The workplan review/download pages remain; workplan SUBMISSION is
    // the "New Workplan" system-function form.

    Route::get('/forms/workplan/{workplan_id}', [WorkplanController::class, 'review'])
        ->whereNumber('workplan_id')
        ->name('workplan.review');
    Route::get('/forms/workplan/{workplan_id}/download', [WorkplanController::class, 'downloadPdf'])
        ->whereNumber('workplan_id')
        ->name('workplan.download');

    Route::patch('/workplans/{workplan_id}/finalize', [EventPlanController::class, 'finalize'])
        ->whereNumber('workplan_id')
        ->name('workplans.finalize');
});

// Reports: generated from the admin-authored report templates. Open to admins
// and organization officers; each template's audience narrows who sees it.
Route::middleware(['auth', 'officer.or.admin'])->group(function () {
    // Old admin-only URL.
    Route::redirect('/admin/reports', '/reports');
    Route::get('/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])
        ->name('reports.index');
    Route::get('/reports/{reportTemplate}/generate', [\App\Http\Controllers\Admin\ReportController::class, 'generate'])
        ->whereNumber('reportTemplate')
        ->name('reports.generate');
});

// After Event Form: officials list concluded events and file their reports.
Route::get('/after-event-reports', [\App\Http\Controllers\AfterEventReportController::class, 'index'])
    ->middleware('auth')
    ->name('after-event-reports.index');

// Generic WYSIWYG-builder form renderer. Registered AFTER the literal /forms/*
// routes above so bespoke forms keep their dedicated pages; this catches any
// remaining single-segment form route_name created through the builder.
Route::middleware('auth')->group(function () {
    // Dedicated Forms directory (name + purpose search). Registered before the
    // `/forms/{routeName}` renderer so the literal `/forms` index wins.
    Route::get('/forms', [FormDirectoryController::class, 'index'])->name('forms.directory');

    // Stable entry point for a system function's bound form page (sign_up,
    // new_event, new_workplan, membership_registration — see SystemFunction).
    Route::get('/functions/{fn}', function (string $fn) {
        abort_unless(\App\Forms\SystemFunction::has($fn), 404);
        $form = \App\Forms\SystemFunction::form($fn);
        abort_if(! $form || ! $form->route_name, 404,
            'No form is bound to the "'.\App\Forms\SystemFunction::label($fn).'" function yet.');

        return redirect()->route('forms.render', $form->route_name);
    })->name('functions.show');
});

// The renderer itself is PUBLIC: FormRenderController authorizes per form — the
// Sign Up form is open to guests (public account request), every other form is
// gated to organization officers (403 otherwise). Registered after the /forms
// directory + literal /forms/* routes so those win.
//
// The blank printable PDF sits on a two-segment URL so it can never collide
// with the single-segment renderer; it is registered first anyway so the
// literal suffix always wins. FormBlankPdfController authorizes officers and
// presidents only (403 otherwise), mirroring the gated renderer.
Route::get('/forms/{routeName}/blank-pdf', [FormBlankPdfController::class, 'show'])
    ->name('forms.blank-pdf');
Route::get('/forms/{routeName}', [FormRenderController::class, 'show'])->name('forms.render');
Route::post('/forms/{routeName}', [FormRenderController::class, 'submit'])
    ->middleware('throttle:20,1')->name('forms.render.submit');

// Manual-filling sessions ("drafts"): start freezes the exact partial PDF, then
// the completed scan is uploaded and parsed. These live outside the auth group
// because a public form (Sign Up / New Organization) is fillable by guests;
// every action authorizes per session (owner, or a public resume token).
Route::post('/forms/{routeName}/manual/start', [ManualFormSessionController::class, 'start'])
    ->middleware('throttle:10,1')->name('manual.start');
Route::get('/manual/{session}', [ManualFormSessionController::class, 'show'])->name('manual.show');
Route::get('/manual/{session}/status', [ManualFormSessionController::class, 'status'])->name('manual.status');
Route::get('/manual/{session}/partial.pdf', [ManualFormSessionController::class, 'download'])->name('manual.download');
Route::post('/manual/{session}/scan', [ManualFormSessionController::class, 'upload'])
    ->middleware('throttle:20,1')->name('manual.upload');
Route::post('/manual/{session}/retry', [ManualFormSessionController::class, 'retry'])
    ->middleware('throttle:20,1')->name('manual.retry');
Route::get('/manual/{session}/documents/{document}/status', [ManualFormSessionController::class, 'statusDocument'])
    ->name('manual.documents.status');
Route::get('/manual/{session}/documents/{document}/partial.pdf', [ManualFormSessionController::class, 'downloadDocument'])
    ->name('manual.documents.download');
Route::post('/manual/{session}/documents/{document}/scans', [ManualFormSessionController::class, 'uploadDocument'])
    ->middleware('throttle:20,1')->name('manual.documents.upload');
Route::post('/manual/{session}/documents/{document}/retry', [ManualFormSessionController::class, 'retryDocument'])
    ->middleware('throttle:20,1')->name('manual.documents.retry');
Route::delete('/manual/{session}', [ManualFormSessionController::class, 'destroy'])->name('manual.destroy');

// The Drafts page is officer-only (type 3): a filtered list of the signed-in
// user's own open manual drafts. Public guests resume via their private link.
Route::get('/drafts', [ManualFormSessionController::class, 'drafts'])
    ->middleware('auth')->name('manual.drafts');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::patch('/posts/{postId}', [PostController::class, 'update'])->whereNumber('postId')->name('posts.update');
    Route::delete('/posts/{postId}', [PostController::class, 'destroy'])->whereNumber('postId')->name('posts.destroy');

    Route::get('/admin/semesters', [SemesterController::class, 'index'])->name('admin.semesters.index');
    Route::post('/admin/semesters', [SemesterController::class, 'store'])->name('admin.semesters.store');
    Route::get('/admin/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('admin.semesters.edit');
    Route::patch('/admin/semesters/{semester}', [SemesterController::class, 'update'])->name('admin.semesters.update');

    // Generic per-form request pages: every form page has its own queue
    // (forms bound to sign-up/new-event/new-workplan 404 — dedicated flows).
    Route::get('/admin/form-requests/{form}', [\App\Http\Controllers\Admin\FormRequestController::class, 'index'])
        ->whereNumber('form')
        ->name('admin.form-requests.index');
    Route::get('/admin/form-requests/{form}/{requestId}', [\App\Http\Controllers\Admin\FormRequestController::class, 'show'])
        ->whereNumber('form')
        ->whereNumber('requestId')
        ->name('admin.form-requests.show');
    Route::post('/admin/form-requests/{form}/{requestId}/decide', [\App\Http\Controllers\Admin\FormRequestController::class, 'decide'])
        ->whereNumber('form')
        ->whereNumber('requestId')
        ->name('admin.form-requests.decide');

    Route::get('/admin/activity-requests', [AdminActivityRequestController::class, 'index'])
        ->name('admin.activity-requests.index');
    Route::get('/admin/activity-requests/{requestId}', [AdminActivityRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.activity-requests.show');
    Route::post('/admin/activity-requests/{requestId}/decide', [AdminActivityRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.activity-requests.decide');

    Route::get('/admin/project-requests', [ProjectRequestController::class, 'index'])
        ->name('admin.project-requests.index');
    Route::get('/admin/project-requests/{requestId}', [ProjectRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.project-requests.show');
    Route::post('/admin/project-requests/{requestId}/decide', [ProjectRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.project-requests.decide');

    Route::get('/admin/joint-statement-requests', [JointStatementRequestController::class, 'index'])
        ->name('admin.joint-statement-requests.index');
    Route::get('/admin/joint-statement-requests/{requestId}', [JointStatementRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.joint-statement-requests.show');
    Route::post('/admin/joint-statement-requests/{requestId}/decide', [JointStatementRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.joint-statement-requests.decide');
    Route::post('/admin/joint-statement-requests/{requestId}/generate', [JointStatementRequestController::class, 'generate'])
        ->whereNumber('requestId')
        ->name('admin.joint-statement-requests.generate');

    Route::get('/admin/workplans', [AdminWorkplanController::class, 'index'])
        ->name('admin.workplans.index');

    Route::get('/admin/accomplishment-report-requests', [AccomplishmentReportRequestController::class, 'index'])
        ->name('admin.accomplishment-report-requests.index');
    Route::get('/admin/accomplishment-report-requests/{requestId}', [AccomplishmentReportRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.accomplishment-report-requests.show');
    Route::post('/admin/accomplishment-report-requests/{requestId}/decide', [AccomplishmentReportRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.accomplishment-report-requests.decide');

    Route::get('/admin/financial-report-requests', [FinancialReportRequestController::class, 'index'])
        ->name('admin.financial-report-requests.index');
    Route::get('/admin/financial-report-requests/{requestId}', [FinancialReportRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.financial-report-requests.show');
    Route::post('/admin/financial-report-requests/{requestId}/decide', [FinancialReportRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.financial-report-requests.decide');

    Route::get('/admin/recognition-requests', [RecognitionRequestController::class, 'index'])
        ->name('admin.recognition-requests.index');
    Route::get('/admin/recognition-requests/{requestId}', [RecognitionRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.recognition-requests.show');
    Route::post('/admin/recognition-requests/{requestId}/decide', [RecognitionRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.recognition-requests.decide');

    Route::get('/admin/workplan-requests', [WorkplanRequestController::class, 'index'])
        ->name('admin.workplan-requests.index');
    Route::get('/admin/workplan-requests/{requestId}', [WorkplanRequestController::class, 'show'])
        ->whereNumber('requestId')
        ->name('admin.workplan-requests.show');
    Route::post('/admin/workplan-requests/{requestId}/decide', [WorkplanRequestController::class, 'decide'])
        ->whereNumber('requestId')
        ->name('admin.workplan-requests.decide');

    Route::get('/admin/scoring', [OrganizationScoringController::class, 'index'])
        ->name('admin.scoring.index');
    Route::get('/admin/scoring/rankings', [OrganizationScoringController::class, 'rankings'])
        ->name('admin.scoring.rankings');
    Route::get('/admin/scoring/create', [OrganizationScoringController::class, 'create'])
        ->name('admin.scoring.create');
    Route::post('/admin/scoring', [OrganizationScoringController::class, 'store'])
        ->name('admin.scoring.store');
    Route::get('/admin/scoring/{id}/edit', [OrganizationScoringController::class, 'edit'])
        ->whereNumber('id')
        ->name('admin.scoring.edit');
    Route::put('/admin/scoring/{id}', [OrganizationScoringController::class, 'update'])
        ->whereNumber('id')
        ->name('admin.scoring.update');

    // Scratch-like trigger editor for the scoring system's criteria.
    Route::get('/admin/scoring-rules', [\App\Http\Controllers\Admin\ScoringRuleController::class, 'index'])
        ->name('admin.scoring.rules.index');
    Route::post('/admin/scoring-rules/criteria', [\App\Http\Controllers\Admin\ScoringRuleController::class, 'storeCriterion'])
        ->name('admin.scoring.criteria.store');
    Route::put('/admin/scoring-rules/criteria/{criterion}', [\App\Http\Controllers\Admin\ScoringRuleController::class, 'updateCriterion'])
        ->whereNumber('criterion')
        ->name('admin.scoring.criteria.update');
    Route::delete('/admin/scoring-rules/criteria/{criterion}', [\App\Http\Controllers\Admin\ScoringRuleController::class, 'destroyCriterion'])
        ->whereNumber('criterion')
        ->name('admin.scoring.criteria.destroy');
    Route::get('/admin/scoring-rules/{criterion}/edit', [\App\Http\Controllers\Admin\ScoringRuleController::class, 'editRule'])
        ->whereNumber('criterion')
        ->name('admin.scoring.rules.edit');
    Route::put('/admin/scoring-rules/{criterion}', [\App\Http\Controllers\Admin\ScoringRuleController::class, 'updateRule'])
        ->whereNumber('criterion')
        ->name('admin.scoring.rules.update');
    Route::patch('/admin/scoring-rules/{criterion}/toggle', [\App\Http\Controllers\Admin\ScoringRuleController::class, 'toggleRule'])
        ->whereNumber('criterion')
        ->name('admin.scoring.rules.toggle');
    Route::delete('/admin/scoring-rules/{criterion}/rule', [\App\Http\Controllers\Admin\ScoringRuleController::class, 'destroyRule'])
        ->whereNumber('criterion')
        ->name('admin.scoring.rules.destroy');

    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])
        ->name('admin.audit-logs.index');
    Route::get('/admin/audit-logs/export/json', [AuditLogController::class, 'exportJson'])
        ->name('admin.audit-logs.export.json');
    Route::get('/admin/audit-logs/export/print', [AuditLogController::class, 'exportPrint'])
        ->name('admin.audit-logs.export.print');
    Route::get('/admin/audit-logs/export/xlsx', [AuditLogController::class, 'exportXlsx'])
        ->name('admin.audit-logs.export.xlsx');

    Route::get('/admin/request-records', [RequestRecordController::class, 'index'])
        ->name('admin.request-records.index');
    Route::get('/admin/request-records/export/json', [RequestRecordController::class, 'exportJson'])
        ->name('admin.request-records.export.json');
    Route::get('/admin/request-records/export/print', [RequestRecordController::class, 'exportPrint'])
        ->name('admin.request-records.export.print');
    Route::get('/admin/request-records/export/xlsx', [RequestRecordController::class, 'exportXlsx'])
        ->name('admin.request-records.export.xlsx');

    Route::get('/admin/database-view', [DatabaseViewController::class, 'index'])
        ->name('admin.database-view.index');
    Route::get('/admin/database-view/officers', [DatabaseViewController::class, 'officers'])
        ->name('admin.database-view.officers');
    Route::get('/admin/database-view/orgs/export/json', [DatabaseViewController::class, 'exportOrgsJson'])
        ->name('admin.database-view.orgs.export.json');
    Route::get('/admin/database-view/orgs/export/print', [DatabaseViewController::class, 'exportOrgsPrint'])
        ->name('admin.database-view.orgs.export.print');
    Route::get('/admin/database-view/orgs/export/xlsx', [DatabaseViewController::class, 'exportOrgsXlsx'])
        ->name('admin.database-view.orgs.export.xlsx');
    Route::get('/admin/database-view/officers/export/json', [DatabaseViewController::class, 'exportOfficersJson'])
        ->name('admin.database-view.officers.export.json');
    Route::get('/admin/database-view/officers/export/print', [DatabaseViewController::class, 'exportOfficersPrint'])
        ->name('admin.database-view.officers.export.print');
    Route::get('/admin/database-view/officers/export/xlsx', [DatabaseViewController::class, 'exportOfficersXlsx'])
        ->name('admin.database-view.officers.export.xlsx');


    // Report Templates (wizard editor, see app/Reports).
    Route::get('/admin/report-templates', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'index'])
        ->name('admin.report-templates.index');
    Route::get('/admin/report-templates/create', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'create'])
        ->name('admin.report-templates.create');
    Route::get('/admin/report-templates/schema', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'schema'])
        ->name('admin.report-templates.schema');
    Route::post('/admin/report-templates/preview', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'preview'])
        ->name('admin.report-templates.preview');
    Route::post('/admin/report-templates/parameter-options', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'parameterOptions'])
        ->name('admin.report-templates.parameter-options');
    Route::post('/admin/report-templates/draft/sync', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'syncDraft'])
        ->name('admin.report-templates.draft.sync');
    Route::post('/admin/report-templates', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'store'])
        ->name('admin.report-templates.store');
    Route::get('/admin/report-templates/{reportTemplate}/edit', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'edit'])
        ->whereNumber('reportTemplate')
        ->name('admin.report-templates.edit');
    Route::put('/admin/report-templates/{reportTemplate}', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'update'])
        ->whereNumber('reportTemplate')
        ->name('admin.report-templates.update');
    Route::delete('/admin/report-templates/{reportTemplate}', [\App\Http\Controllers\Admin\ReportTemplateController::class, 'destroy'])
        ->whereNumber('reportTemplate')
        ->name('admin.report-templates.destroy');

    Route::get('/admin/officers/create', [AdminOfficerCreationController::class, 'create'])
        ->name('admin.officers.create');
    Route::post('/admin/officers/create', [AdminOfficerCreationController::class, 'store'])
        ->name('admin.officers.store');
});

Route::get('/documents', [DocumentController::class, 'index'])
    ->middleware('auth')
    ->name('documents.index');

Route::middleware(['auth', 'admin'])->group(function () {
    // WYSIWYG form builder (replacement for the DOCX Template Manager).
    // Form authoring is Admin-only (user_type 2) — super admins don't get this.
    Route::get('/admin/form-builder', [FormBuilderController::class, 'index'])
        ->name('admin.form-builder.index');
    Route::get('/admin/form-builder/create', [FormBuilderController::class, 'create'])
        ->name('admin.form-builder.create');
    // Typeahead for picking a signature field's expected signer(s): names + org role.
    Route::get('/admin/form-builder/signatory-search', [FormBuilderController::class, 'searchSignatories'])
        ->name('admin.form-builder.signatory-search');
    Route::post('/admin/form-builder', [FormBuilderController::class, 'store'])
        ->name('admin.form-builder.store');
    Route::post('/admin/form-builder/upload-asset', [FormBuilderController::class, 'uploadAsset'])
        ->name('admin.form-builder.upload-asset');
    Route::get('/admin/form-builder/{form}/preview', [FormBuilderController::class, 'preview'])
        ->name('admin.form-builder.preview');
    Route::get('/admin/form-builder/{form}/edit', [FormBuilderController::class, 'edit'])
        ->name('admin.form-builder.edit');
    Route::put('/admin/form-builder/{form}', [FormBuilderController::class, 'update'])
        ->name('admin.form-builder.update');
    Route::delete('/admin/form-builder/{form}', [FormBuilderController::class, 'destroy'])
        ->name('admin.form-builder.destroy');

    // Editor bootstrap for the form builder's "Printed template" step. Session
    // authenticated, unlike the /onlyoffice/* routes below.
    Route::get('/admin/form-builder/{form}/printed-template/config',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'config'])
        ->name('admin.form-builder.printed-template.config');

    // Replace the printed template's .docx with an admin-uploaded Word file.
    Route::post('/admin/form-builder/{form}/printed-template/import',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'import'])
        ->name('admin.form-builder.printed-template.import');

    // Non-persistent Step-2 drafts: let the printed-template editor open (and
    // its field-token palette reflect just-added fields) before the form is
    // saved. The draft lives only in the file cache — no DB writes until save.
    // Session-authenticated (admin), like the /printed-template/* routes above.
    Route::post('/admin/form-builder/draft/sync',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'syncDraft'])
        ->name('admin.form-builder.draft.sync');
    Route::get('/admin/form-builder/draft/{draftId}/printed-template/config',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftConfig'])
        ->where('draftId', '[A-Za-z0-9\-]+')
        ->name('admin.form-builder.draft.config');
    Route::post('/admin/form-builder/draft/{draftId}/printed-template/import',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftImport'])
        ->where('draftId', '[A-Za-z0-9\-]+')
        ->name('admin.form-builder.draft.import');
    // Draft version probe: the builder polls this after tearing the editor down
    // on save, to wait for the Document Server's final save callback (whose
    // storeDraftRevision bumps the version) before the draft is folded into the
    // real template — otherwise a save would adopt the stale pre-edit document.
    Route::get('/admin/form-builder/draft/{draftId}/printed-template/version',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftVersion'])
        ->where('draftId', '[A-Za-z0-9\-]+')
        ->name('admin.form-builder.draft.version');
    Route::post('/admin/form-builder/draft/{draftId}/slots',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'addDraftSlot'])
        ->name('admin.form-builder.draft.slots.store');
    Route::get('/admin/form-builder/draft/{draftId}/slots/{slotId}/config',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'slotConfig'])
        ->name('admin.form-builder.draft.slots.config');
    Route::get('/admin/form-builder/draft/{draftId}/slots/{slotId}/version',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'slotVersion'])
        ->name('admin.form-builder.draft.slots.version');
    Route::post('/admin/form-builder/draft/{draftId}/slots/{slotId}/import',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'importDraftSlot'])
        ->name('admin.form-builder.draft.slots.import');
    Route::delete('/admin/form-builder/draft/{draftId}/slots/{slotId}',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'removeDraftSlot'])
        ->name('admin.form-builder.draft.slots.destroy');

    // The upload / verify / activate pages are gone: printed templates are now
    // authored in the form builder's Step 2 editor and created automatically
    // per form, so there is nothing left to upload or hand-map.
    // Populate a printed template's .docx from posted field data (docx or pdf
    // back). Kept in the session-authenticated web group rather than under
    // /api — there is no stateless API guard configured, so an /api route
    // would be unauthenticated.
    Route::post('/admin/templates/{template}/generate',
        [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'generate'])
        ->name('admin.templates.generate');
});

Route::get('/superadmin/profile-requests', [SuperAdminController::class, 'profileRequests'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profile-requests');

Route::get('/superadmin/profiles/search', [SuperAdminController::class, 'searchProfiles'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles.search');

// Accreditation-disabled organizations: restore or hard-purge (super admin).
Route::middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/superadmin/organizations', [\App\Http\Controllers\Admin\OrganizationAccreditationController::class, 'index'])
        ->name('superadmin.organizations.index');
    Route::post('/superadmin/organizations/{organization}/restore', [\App\Http\Controllers\Admin\OrganizationAccreditationController::class, 'restore'])
        ->name('superadmin.organizations.restore');
    Route::delete('/superadmin/organizations/{organization}/purge', [\App\Http\Controllers\Admin\OrganizationAccreditationController::class, 'purge'])
        ->name('superadmin.organizations.purge');
});

// Database backups & restore (super admin).
Route::middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/superadmin/backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])
        ->name('superadmin.backups.index');
    Route::get('/superadmin/backups/archived', [\App\Http\Controllers\Admin\BackupController::class, 'archived'])
        ->name('superadmin.backups.archived');
    Route::post('/superadmin/backups', [\App\Http\Controllers\Admin\BackupController::class, 'store'])
        ->name('superadmin.backups.store');
    Route::get('/superadmin/backups/{filename}/download', [\App\Http\Controllers\Admin\BackupController::class, 'download'])
        ->name('superadmin.backups.download');
    Route::post('/superadmin/backups/{filename}/restore', [\App\Http\Controllers\Admin\BackupController::class, 'restore'])
        ->name('superadmin.backups.restore');
    Route::post('/superadmin/backups/{filename}/archive', [\App\Http\Controllers\Admin\BackupController::class, 'archive'])
        ->name('superadmin.backups.archive');
    Route::post('/superadmin/backups/{filename}/unarchive', [\App\Http\Controllers\Admin\BackupController::class, 'unarchive'])
        ->name('superadmin.backups.unarchive');
    Route::delete('/superadmin/backups/{filename}', [\App\Http\Controllers\Admin\BackupController::class, 'destroy'])
        ->name('superadmin.backups.destroy');
});

Route::post('/superadmin/profile-requests/auto-accept-suggested', [SuperAdminController::class, 'autoAcceptSuggestedRequests'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profile-requests.auto-accept-suggested');

Route::get('/superadmin/dashboard-builder', [SuperAdminController::class, 'dashboardBuilder'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.dashboard-builder');

Route::post('/superadmin/dashboard-builder', [SuperAdminController::class, 'storeDashboardWidget'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.dashboard-builder.store');

Route::patch('/superadmin/dashboard-builder/{widgetId}', [SuperAdminController::class, 'updateDashboardWidget'])
    ->whereNumber('widgetId')
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.dashboard-builder.update');

Route::post('/superadmin/profile-requests/{requestId}/decision', [SuperAdminController::class, 'decideProfileRequest'])
    ->whereNumber('requestId')
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profile-requests.decision');

Route::post('/superadmin/officer-account-requests/{requestId}/decision', [SuperAdminController::class, 'decideOfficerAccountRequest'])
    ->whereNumber('requestId')
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.officer-account-requests.decision');

Route::get('/superadmin/export', [ExportController::class, 'index'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export');

Route::get('/superadmin/export/org-data/json', [ExportController::class, 'exportOrgDataJson'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.org-data.json');

Route::get('/superadmin/export/org-data/print', [ExportController::class, 'exportOrgDataPrint'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.org-data.print');

Route::get('/superadmin/export/request-records/json', [ExportController::class, 'exportRequestRecordsJson'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.request-records.json');

Route::get('/superadmin/export/request-records/print', [ExportController::class, 'exportRequestRecordsPrint'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.request-records.print');

Route::get('/superadmin/export/login-logs/json', [ExportController::class, 'exportLoginLogsJson'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.login-logs.json');

Route::get('/superadmin/export/login-logs/print', [ExportController::class, 'exportLoginLogsPrint'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.login-logs.print');

Route::get('/superadmin/export/org-data/xlsx', [ExportController::class, 'exportOrgDataXlsx'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.org-data.xlsx');

Route::get('/superadmin/export/request-records/xlsx', [ExportController::class, 'exportRequestRecordsXlsx'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.request-records.xlsx');

Route::get('/superadmin/export/login-logs/xlsx', [ExportController::class, 'exportLoginLogsXlsx'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.export.login-logs.xlsx');

Route::get('/superadmin/data-sync', [SuperAdminController::class, 'dataSyncPage'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.data-sync');

Route::get('/superadmin/data-sync/export', [SuperAdminController::class, 'exportData'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.data-sync.export');

Route::post('/superadmin/data-sync/import-file', [SuperAdminController::class, 'importFromFile'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.data-sync.import-file');

Route::post('/superadmin/data-sync/import-api', [SuperAdminController::class, 'importFromApi'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.data-sync.import-api');

Route::get('/superadmin/accounts/create', [AdminAccountCreationController::class, 'create'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.accounts.create');

Route::post('/superadmin/accounts/create', [AdminAccountCreationController::class, 'store'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.accounts.store');

// Google "Sign in" popup for pre-filling admin (SuperAdmin) and officer (Admin)
// creation forms, AND the public landing-page "Sign up with Google" button.
// Shared by all three; stateless and reads the applicant's Google profile
// only — never logs anyone in — so it's intentionally public (no auth/guest
// middleware): the admin forms use it while signed in, the landing page uses
// it signed out. Path matches GOOGLE_REDIRECT_URI in .env.
Route::get('/admin/accounts/google/redirect', [GoogleLinkController::class, 'redirect'])
    ->name('admin.accounts.google.redirect');
Route::get('/admin/accounts/google/callback', [GoogleLinkController::class, 'callback'])
    ->name('admin.accounts.google.callback');

Route::get('/superadmin/scoring/audit', [OrganizationScoringController::class, 'audit'])
    ->middleware(['auth', 'superadmin'])
    ->name('admin.scoring.audit');

Route::get('/superadmin/scoring/audit/print', [OrganizationScoringController::class, 'auditPrint'])
    ->middleware(['auth', 'superadmin'])
    ->name('admin.scoring.audit.print');

Route::get('/superadmin/scoring/audit/xlsx', [OrganizationScoringController::class, 'auditXlsx'])
    ->middleware(['auth', 'superadmin'])
    ->name('admin.scoring.audit.xlsx');

Route::get('/superadmin/profiles', [SuperAdminController::class, 'profiles'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles');

Route::get('/superadmin/profiles/{id}/edit', [SuperAdminController::class, 'editProfile'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles.edit');

Route::patch('/superadmin/profiles/{id}', [SuperAdminController::class, 'updateProfile'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles.update');

Route::delete('/superadmin/profiles/{id}', [SuperAdminController::class, 'destroyProfile'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.profiles.destroy');

// profile pages
Route::get('/profile', function () {
    $user = auth()->user();
    if ($user && ! $user->profile()->exists()) {
        return redirect()->route('profile.create');
    }

    return view('pages.profile', ['title' => 'Profile']);
})->middleware('auth')->name('profile');

Route::get('/profile/create', [ProfileController::class, 'profileForm'])->middleware('auth')->name('profile.create');
Route::post('/profile/create', [ProfileController::class, 'store'])->middleware('auth')->name('profile.store');
Route::post('/profile/signature', [ProfileController::class, 'updateSignature'])->middleware('auth')->name('profile.signature');
Route::post('/signature/verify', [\App\Http\Controllers\SignatureVerificationController::class, 'verify'])->middleware('auth')->name('signature.verify');
Route::post('/signature/enroll', [\App\Http\Controllers\SignatureVerificationController::class, 'enroll'])->middleware('auth')->name('signature.enroll');
Route::post('/waiver-scan', [\App\Http\Controllers\WaiverScanController::class, 'scan'])->middleware('auth')->name('waiver.scan');

// Waiver template authoring (admin / super admin).
Route::middleware(['auth', 'admin.or.superadmin'])->group(function () {
    Route::get('/admin/waiver-templates', [\App\Http\Controllers\Admin\WaiverTemplateController::class, 'index'])
        ->name('admin.waiver-templates.index');
    Route::get('/admin/waiver-templates/create', [\App\Http\Controllers\Admin\WaiverTemplateController::class, 'create'])
        ->name('admin.waiver-templates.create');
    Route::post('/admin/waiver-templates', [\App\Http\Controllers\Admin\WaiverTemplateController::class, 'store'])
        ->name('admin.waiver-templates.store');
    Route::delete('/admin/waiver-templates/{waiverTemplate}', [\App\Http\Controllers\Admin\WaiverTemplateController::class, 'destroy'])
        ->name('admin.waiver-templates.destroy');
});

// Type-2 review of scanned-waiver submissions.
Route::get('/admin/waiver-review', [\App\Http\Controllers\Admin\WaiverReviewController::class, 'index'])
    ->middleware(['auth', 'admin'])
    ->name('admin.waiver-review.index');

// form pages
Route::get('/form-elements', function () {
    return view('pages.form.form-elements', ['title' => 'Form Elements']);
})->name('form-elements');

// tables pages
Route::get('/basic-tables', function () {
    return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
})->name('basic-tables');

// pages

Route::get('/blank', function () {
    return view('pages.blank', ['title' => 'Blank']);
})->name('blank');

// error pages
Route::get('/error-404', function () {
    return view('pages.errors.error-404', ['title' => 'Error 404']);
})->name('error-404');

// chart pages
Route::get('/line-chart', function () {
    return view('pages.chart.line-chart', ['title' => 'Line Chart']);
})->name('line-chart');

Route::get('/bar-chart', function () {
    return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
})->name('bar-chart');

// authentication pages
Route::get('/signin', fn () => redirect()->route('home'))->name('signin');

// Public sign-up: redirects to the "Sign Up" system-function form (the seeded
// Directory of Student Officers), forwarding the landing-page Google popup's
// prefill query params. Falls back to a friendly message when no form is bound.
Route::get('/signup', function (\Illuminate\Http\Request $request) {
    $form = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::SIGN_UP);
    abort_if($form === null || ! $form->route_name, 404,
        'Sign-up is not available right now — no form is bound to the Sign Up function.');

    return redirect()->route('forms.render', array_merge(
        ['routeName' => $form->route_name],
        $request->only(['google_id', 'first_name', 'last_name', 'email']),
    ));
})->middleware('guest')->name('signup');

// ui elements pages
Route::get('/alerts', function () {
    return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
})->name('alerts');

Route::get('/avatars', function () {
    return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
})->name('avatars');

Route::get('/badge', function () {
    return view('pages.ui-elements.badges', ['title' => 'Badges']);
})->name('badges');

Route::get('/buttons', function () {
    return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
})->name('buttons');

Route::get('/image', function () {
    return view('pages.ui-elements.images', ['title' => 'Images']);
})->name('images');

Route::get('/videos', function () {
    return view('pages.ui-elements.videos', ['title' => 'Videos']);
})->name('videos');

// API routes

Route::get('/api/policy-security/requests', [PolicySecurityRequestController::class, 'index'])
    ->middleware('auth');

Route::get('/api/policy-security/approvals', [PolicySecurityRequestController::class, 'approvals'])
    ->middleware('auth');

Route::get('/api/policy-security/stats', [PolicySecurityRequestController::class, 'stats'])
    ->middleware('auth');

$applyAuthorizedOrganizationScope = static function ($query, array $authorizedOrganizationIds) {
    return $query->where(function ($organizationScopeQuery) use ($authorizedOrganizationIds) {
        foreach ($authorizedOrganizationIds as $organizationId) {
            $organizationScopeQuery->orWhere('action', 'like', $organizationId.'|%');
        }
    });
};

Route::get('/api/requests/{actionType}', function (int $actionType) use ($applyAuthorizedOrganizationScope) {
    if ($actionType === 7) {
        return response()->json([
            'message' => 'Use the Policy and Security endpoint for this action type.',
        ], 403);
    }

    $user = request()->user();

    if (! $user) {
        abort(401);
    }

    $hours = max((int) request()->query('hours', 0), 0);
    $limit = min(max((int) request()->query('limit', 0), 0), 200);

    $isAdmin = (int) $user->user_type === 2;

    if ($isAdmin) {
        $query = Request::query()->where('action_type', $actionType)->orderByDesc('requested_at');

        if ($hours > 0) {
            $query->where('requested_at', '>=', now()->subHours($hours));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return ActionRequestResource::collection($query->get());
    }

    $authorizedOrganizationIds = in_array($actionType, [2, 3, 4, 8], true)
        ? OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey())
        : OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

    if (empty($authorizedOrganizationIds)) {
        return ActionRequestResource::collection(collect());
    }

    $query = $applyAuthorizedOrganizationScope(
        Request::query()->where('action_type', $actionType),
        $authorizedOrganizationIds
    )->orderByDesc('requested_at');

    if ($hours > 0) {
        $query->where('requested_at', '>=', now()->subHours($hours));
    }

    if ($limit > 0) {
        $query->limit($limit);
    }

    return ActionRequestResource::collection($query->get());
})->middleware('auth');

Route::post('/api/requests/{requestId}/decision', [RequestDecisionController::class, 'store'])
    ->whereNumber('requestId')
    ->middleware('auth');

Route::post('/api/requests/{requestId}/approve-with-signatures', [RequestDecisionController::class, 'approveWithSignatures'])
    ->whereNumber('requestId')
    ->middleware('auth')
    ->name('requests.approve-with-signatures');

Route::post('/api/events/requests', [EventController::class, 'storeEventPlanRequest'])
    ->middleware('auth')
    ->name('api.events.requests.store');

Route::post('/api/events/direct-request', [EventController::class, 'storeDirectEventRequest'])
    ->middleware('auth')
    ->name('api.events.direct-request.store');

Route::get('/api/organizations/{organizationId}/officers', [EventController::class, 'organizationOfficers'])
    ->whereNumber('organizationId')
    ->middleware('auth')
    ->name('api.organizations.officers');

Route::get('/api/organizations', function () {
    $ids = collect(explode(',', (string) request()->query('ids', '')))
        ->map(fn ($id) => (int) trim($id))
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values();

    if ($ids->isEmpty()) {
        return response()->json(['data' => []]);
    }

    $rows = DB::table('organizations as o')
        ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
        ->whereIn('o.organization_id', $ids->all())
        ->select('o.organization_id', 'od.name')
        ->get();

    $nameMap = $ids->mapWithKeys(function ($id) {
        return [(string) $id => 'Unknown Organization'];
    })->all();

    foreach ($rows as $row) {
        $id = (string) $row->organization_id;
        $nameMap[$id] = $row->name ?: 'Unknown Organization';
    }

    return response()->json(['data' => $nameMap]);
})->middleware('auth');

Route::get('/api/approvals/{actionType}', function (int $actionType) use ($applyAuthorizedOrganizationScope) {
    if ($actionType === 7) {
        return response()->json([
            'message' => 'Use the Policy and Security endpoint for this action type.',
        ], 403);
    }

    $user = request()->user();

    if (! $user) {
        abort(401);
    }

    $isAdmin = (int) $user->user_type === 2;

    if ($isAdmin) {
        $allRequestIds = Request::query()->where('action_type', $actionType)->select('request_id');

        return ApprovalResource::collection(
            Approval::query()
                ->whereIn('request', $allRequestIds)
                ->orderByDesc('approved_at')
                ->get()
        );
    }

    $authorizedOrganizationIds = in_array($actionType, [2, 3, 4, 8], true)
        ? OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey())
        : OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

    if (empty($authorizedOrganizationIds)) {
        return ApprovalResource::collection(collect());
    }

    $scopedRequestIds = $applyAuthorizedOrganizationScope(
        Request::query()->where('action_type', $actionType),
        $authorizedOrganizationIds
    )->select('request_id');

    return ApprovalResource::collection(
        Approval::query()
            ->whereIn('request', $scopedRequestIds)
            ->orderByDesc('approved_at')
            ->get()
    );
})->middleware('auth');

Route::get('/api/approvals/{status}/{actionType}', function (string $status, int $actionType) use ($applyAuthorizedOrganizationScope) {
    if ($actionType === 7) {
        return response()->json([
            'message' => 'Use the Policy and Security endpoint for this action type.',
        ], 403);
    }

    $user = request()->user();

    if (! $user) {
        abort(401);
    }

    $isAdmin = (int) $user->user_type === 2;

    if ($isAdmin) {
        $scopedRequestIds = Request::query()->where('action_type', $actionType)->select('request_id');
    } else {
        $authorizedOrganizationIds = in_array($actionType, [2, 3, 4, 8], true)
            ? OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey())
            : OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

        if (empty($authorizedOrganizationIds)) {
            return ApprovalResource::collection(collect());
        }

        $scopedRequestIds = $applyAuthorizedOrganizationScope(
            Request::query()->where('action_type', $actionType),
            $authorizedOrganizationIds
        )->select('request_id');
    }

    if ($status == 'approved') {
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', false)
                ->whereIn('request', $scopedRequestIds)
                ->get()
        );
    } elseif ($status == 'denied') {
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', true)
                ->whereIn('request', $scopedRequestIds)
                ->get()
        );
    } elseif ($status == 'pending') {
        return ApprovalResource::collection(
            Approval::query()
                ->where('is_rejected', null)
                ->whereIn('request', $scopedRequestIds)
                ->get()
        );
    }
})->middleware('auth');

Route::get('/api/user/{id}', function (string $id) {
    return User::findOrFail($id)->toResource();
})->middleware('auth');

Route::get('/api/users', function () {
    return UserResource::collection(User::all());
});

Route::get('/api/events/calendar', [EventController::class, 'calendarEvents'])
    ->middleware('auth')
    ->name('api.events.calendar');

Route::get('/api/dashboard-search', [DashboardSearchController::class, 'index'])
    ->middleware('auth')
    ->name('api.dashboard-search');

Route::get('/api/superadmin/data/export', [SuperAdminController::class, 'apiExport']);

Route::post('/api/superadmin/data/import', [SuperAdminController::class, 'apiImport'])
    ->withoutMiddleware([VerifyCsrfToken::class]);

// ── SuperAdmin ID-recognition template editor ───────────────────────────────
Route::middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/superadmin/id-templates', [IdTemplateController::class, 'index'])
        ->name('superadmin.id-templates.index');
    Route::get('/superadmin/id-templates/create', [IdTemplateController::class, 'create'])
        ->name('superadmin.id-templates.create');
    Route::post('/superadmin/id-templates', [IdTemplateController::class, 'store'])
        ->name('superadmin.id-templates.store');
    Route::post('/superadmin/id-templates/upload-image', [IdTemplateController::class, 'uploadImage'])
        ->name('superadmin.id-templates.upload-image');
    Route::get('/superadmin/id-templates/{idTemplate}/edit', [IdTemplateController::class, 'edit'])
        ->name('superadmin.id-templates.edit');
    Route::put('/superadmin/id-templates/{idTemplate}', [IdTemplateController::class, 'update'])
        ->name('superadmin.id-templates.update');
    Route::delete('/superadmin/id-templates/{idTemplate}', [IdTemplateController::class, 'destroy'])
        ->name('superadmin.id-templates.destroy');

    // Administrator action log (login/logout, scoring, form + ID-template
    // editing, scoring-rule changes) — searchable and exportable.
    Route::get('/superadmin/action-logs', [\App\Http\Controllers\Admin\ActionLogController::class, 'index'])
        ->name('superadmin.action-logs.index');
    Route::get('/superadmin/action-logs/export/json', [\App\Http\Controllers\Admin\ActionLogController::class, 'exportJson'])
        ->name('superadmin.action-logs.export.json');
    Route::get('/superadmin/action-logs/export/pdf', [\App\Http\Controllers\Admin\ActionLogController::class, 'exportPdf'])
        ->name('superadmin.action-logs.export.pdf');
    Route::get('/superadmin/action-logs/export/xlsx', [\App\Http\Controllers\Admin\ActionLogController::class, 'exportXlsx'])
        ->name('superadmin.action-logs.export.xlsx');
});

// Auto-scan pre-fill for the (public) student-leader-directory signup form.
// Intentionally NOT behind `auth` — that form is filled by users without an
// account yet. Fails soft when no active template / sidecar is available.
Route::post('/id-scan', [IdScanController::class, 'scan'])->name('id-scan.scan');

// Serves back a side's cached scan for this session (see IdScanRetryCache) so
// the wizard can show "already scanned" previews after a validation-failure
// reload instead of forcing a re-scan.
Route::get('/id-scan/retry/{side}', [IdScanController::class, 'retryPhoto'])
    ->where('side', 'front|back')
    ->name('id-scan.retry-photo');

// In-app AI assistant. Auth-only: the knowledge base is filtered by user_type,
// so an unauthenticated caller has no index to ground against. Throttled
// because each call occupies the single local GPU for several seconds.
Route::post('/assistant/chat', [\App\Http\Controllers\AssistantController::class, 'chat'])
    ->middleware(['auth', 'throttle:20,1'])->name('assistant.chat');

// ── OnlyOffice Document Server callbacks ────────────────────────────────────
// Deliberately outside the session-authenticated area: the callers are the
// Document Server (fetching and saving the .docx server-side) and the token
// palette running inside the editor's iframe — neither carries the admin's
// session cookie. Each request instead presents a short-lived HS256 token
// scoped to one template and one purpose, verified in the controller.
//
// The web group's session/CSRF/hardening middleware is stripped: CSRF would
// reject the server's POST, and SecurityHeaders' SAMEORIGIN + frame-ancestors
// would stop the Document Server framing the palette.
// Draft counterpart of the group below: same stateless HS256 auth and the same
// stripped middleware, but keyed on a file-cache draftId (a `did` claim) rather
// than a Template row, so the editor can open before the form is saved. Declared
// first so the literal "draft/" prefix is matched ahead of the {form} wildcard.
Route::prefix('onlyoffice/draft/{draftId}')
    ->where(['draftId' => '[A-Za-z0-9\-]+'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \App\Http\Middleware\SecurityHeaders::class,
        \App\Http\Middleware\EnsurePasswordChanged::class,
        \App\Http\Middleware\EnsureOrganizationAccredited::class,
        \App\Http\Middleware\PreventBackHistory::class,
    ])
    ->group(function () {
        Route::get('/document', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftDocument'])
            ->name('onlyoffice.draft.document');
        Route::post('/callback', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftCallback'])
            ->name('onlyoffice.draft.callback');
        Route::get('/config.json', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftPluginConfig'])
            ->name('onlyoffice.draft.plugin-config');
        Route::get('/token-usage', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftTokenUsage'])
            ->name('onlyoffice.draft.token-usage');
        Route::get('/{icon}', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'pluginIcon'])
            ->where('icon', 'icon(@2x)?\.png')
            ->name('onlyoffice.draft.plugin-icon');
        // Declared before the {token} catch-all so "config.json" isn't swallowed.
        Route::get('/plugin/config.json', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftPluginHandshake'])
            ->name('onlyoffice.draft.plugin-handshake');
        Route::get('/plugin/{token}', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'draftPlugin'])
            ->where('token', '[^/]+')
            ->name('onlyoffice.draft.plugin');
    });

Route::get('/onlyoffice/conversion/{conversionId}/source', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'conversionSource'])
    ->where(['conversionId' => '[A-Za-z0-9\-]+'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \App\Http\Middleware\SecurityHeaders::class,
        \App\Http\Middleware\EnsurePasswordChanged::class,
        \App\Http\Middleware\EnsureOrganizationAccredited::class,
        \App\Http\Middleware\PreventBackHistory::class,
    ])
    ->name('onlyoffice.conversion-source');

Route::prefix('onlyoffice/{form}')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \App\Http\Middleware\SecurityHeaders::class,
        \App\Http\Middleware\EnsurePasswordChanged::class,
        \App\Http\Middleware\EnsureOrganizationAccredited::class,
        \App\Http\Middleware\PreventBackHistory::class,
    ])
    ->group(function () {
        Route::get('/document', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'document'])
            ->name('onlyoffice.document');
        Route::post('/callback', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'callback'])
            ->name('onlyoffice.callback');
        // Must literally end in "config.json": OnlyOffice 9.4 derives the plugin's
        // baseUrl as url.substring(0, url.lastIndexOf("config.json")) — any other
        // name yields an empty baseUrl and the editor mangles the variation URL.
        Route::get('/config.json', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'pluginConfig'])
            ->name('onlyoffice.plugin-config');
        Route::get('/token-usage', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'tokenUsage'])
            ->name('onlyoffice.token-usage');
        // The plugin's toolbar icon. The editor resolves the config's
        // icons [icon.png, icon@2x.png] against the plugin baseUrl
        // (…/onlyoffice/{form}/), so both must be served here.
        Route::get('/{icon}', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'pluginIcon'])
            ->where('icon', 'icon(@2x)?\.png')
            ->name('onlyoffice.plugin-icon');
        // The plugin SDK (plugins.js) inside the iframe fetches "./config.json"
        // relative to the plugin page (…/plugin/{token}) to complete its init
        // handshake. That lands here — must be registered before the {token}
        // catch-all below, which would otherwise swallow "config.json".
        Route::get('/plugin/config.json', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'pluginHandshake'])
            ->name('onlyoffice.plugin-handshake');
        // Token is a path segment, not a query param: OnlyOffice appends its own
        // query string (theme-type, lang) to the plugin URL, which would corrupt
        // any token=… carried in the query.
        Route::get('/plugin/{token}', [\App\Http\Controllers\Admin\FormPrintTemplateController::class, 'plugin'])
            ->where('token', '[^/]+')
            ->name('onlyoffice.plugin');
    });
