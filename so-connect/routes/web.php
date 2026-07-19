<?php

use App\Http\Controllers\Admin\AccomplishmentReportRequestController;
use App\Http\Controllers\Admin\AdminAccountCreationController;
use App\Http\Controllers\Admin\AdminOfficerCreationController;
use App\Http\Controllers\Admin\AdminWorkplanController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DatabaseViewController;
use App\Http\Controllers\Admin\IdTemplateController;
use App\Http\Controllers\Admin\RequestRecordController;
use App\Http\Controllers\Admin\ActivityRequestController as AdminActivityRequestController;
use App\Http\Controllers\Admin\FinancialReportRequestController;
use App\Http\Controllers\Admin\JointStatementRequestController;
use App\Http\Controllers\Admin\OrganizationScoringController;
use App\Http\Controllers\Admin\ProjectRequestController;
use App\Http\Controllers\Admin\RecognitionRequestController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\TemplateManagerController;
use App\Http\Controllers\Admin\WorkplanRequestController;
use App\Http\Controllers\Auth\GoogleLinkController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\DashboardSearchController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventPlanController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\Admin\FormBuilderController;
use App\Http\Controllers\FormDirectoryController;
use App\Http\Controllers\FormRenderController;
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
use App\Http\Controllers\UserController;
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

Route::get('/', [LandingPage::class, 'view'])->name('home');
Route::get('/organizations/{organizationId}/{slug?}', [LandingPage::class, 'organizationFeed'])
    ->whereNumber('organizationId')
    ->name('organization-feed');

// dashboard pages
Route::get('/sample_dashboard', function () {
    return view('pages.dashboard.ecommerce', ['title' => 'E-commerce Dashboard']);
})->name('sample-dashboard');

Route::get('/dashboard/president', function () {
    return view('pages.dashboard.administrator', ['title' => 'President Dashboard']);
})->middleware(['auth', 'dashboard.access:president'])->name('president-dashboard');

Route::get('/dashboard/admin', function () {
    return view('pages.dashboard.administrator', [
        'title' => 'Admin Dashboard',
        'canDecide' => false,
    ]);
})->middleware(['auth', 'admin'])->name('admin-dashboard');

Route::get('/dashboard/officer', [Dashboard::class, 'officerDashboard'])
    ->middleware(['auth', 'dashboard.access:officer'])->name('officer-dashboard');

Route::get('/dashboard', [Dashboard::class, 'viewDashboard'])->middleware('auth')->name('dashboard');

// Auth routes.

Route::post('/login', Login::class)->middleware('guest');
Route::post('/signup', Register::class)->middleware('guest');
Route::post('/logout', Logout::class)->middleware('auth')->name('logout');

// Invitation activation (Flows 1, 2, 3)
Route::get('/invitation/verify', [InvitationController::class, 'verify'])
    ->middleware('guest')
    ->name('invitation.verify');

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

Route::get('/approval-requests', [SidebarMenuController::class, 'approvalRequests'])
    ->middleware('auth')
    ->name('approval-requests');

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
    Route::post('/event-plans/{id}/create-event', [EventPlanController::class, 'storeEvent'])
        ->whereNumber('id')
        ->name('event-plans.create-event');
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
Route::get('/forms/{routeName}', [FormRenderController::class, 'show'])->name('forms.render');
Route::post('/forms/{routeName}', [FormRenderController::class, 'submit'])
    ->middleware('throttle:20,1')->name('forms.render.submit');

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

    // PDF reports — registered organizations and officers per organization.
    Route::get('/admin/reports', [\App\Http\Controllers\Admin\OrganizationReportController::class, 'index'])
        ->name('admin.reports.index');
    Route::get('/admin/reports/organizations', [\App\Http\Controllers\Admin\OrganizationReportController::class, 'organizations'])
        ->name('admin.reports.organizations');
    Route::get('/admin/reports/officers', [\App\Http\Controllers\Admin\OrganizationReportController::class, 'officers'])
        ->name('admin.reports.officers');

    Route::get('/admin/officers/create', [AdminOfficerCreationController::class, 'create'])
        ->name('admin.officers.create');
    Route::post('/admin/officers/create', [AdminOfficerCreationController::class, 'store'])
        ->name('admin.officers.store');
});

Route::get('/documents', [DocumentController::class, 'index'])
    ->middleware('auth')
    ->name('documents.index');

Route::middleware(['auth', 'admin.or.superadmin'])->group(function () {
    // WYSIWYG form builder (replacement for the DOCX Template Manager).
    Route::get('/admin/form-builder', [FormBuilderController::class, 'index'])
        ->name('admin.form-builder.index');
    Route::get('/admin/form-builder/create', [FormBuilderController::class, 'create'])
        ->name('admin.form-builder.create');
    Route::post('/admin/form-builder', [FormBuilderController::class, 'store'])
        ->name('admin.form-builder.store');
    Route::post('/admin/form-builder/upload-asset', [FormBuilderController::class, 'uploadAsset'])
        ->name('admin.form-builder.upload-asset');
    // Printed-PDF template DOCX interchange (operates on in-wizard state).
    Route::post('/admin/form-builder/template/export-docx', [FormBuilderController::class, 'exportDocx'])
        ->name('admin.form-builder.template.export-docx');
    Route::post('/admin/form-builder/template/import-docx', [FormBuilderController::class, 'importDocx'])
        ->name('admin.form-builder.template.import-docx');
    Route::get('/admin/form-builder/{form}/preview', [FormBuilderController::class, 'preview'])
        ->name('admin.form-builder.preview');
    Route::get('/admin/form-builder/{form}/edit', [FormBuilderController::class, 'edit'])
        ->name('admin.form-builder.edit');
    Route::put('/admin/form-builder/{form}', [FormBuilderController::class, 'update'])
        ->name('admin.form-builder.update');
    Route::delete('/admin/form-builder/{form}', [FormBuilderController::class, 'destroy'])
        ->name('admin.form-builder.destroy');

    Route::get('/admin/templates', [TemplateManagerController::class, 'index'])
        ->name('admin.templates.index');
    Route::get('/admin/templates/{form}/upload', [TemplateManagerController::class, 'showUpload'])
        ->name('admin.templates.upload');
    Route::post('/admin/templates/{form}/upload', [TemplateManagerController::class, 'storeUpload'])
        ->name('admin.templates.store');
    Route::get('/admin/templates/{template}/verify', [TemplateManagerController::class, 'showVerify'])
        ->name('admin.templates.verify');
    Route::post('/admin/templates/{template}/confirm', [TemplateManagerController::class, 'confirm'])
        ->name('admin.templates.confirm');
    Route::delete('/admin/templates/{template}', [TemplateManagerController::class, 'destroy'])
        ->name('admin.templates.destroy');
    Route::get('/admin/templates/field-reference', [TemplateManagerController::class, 'fieldReference'])
        ->name('admin.templates.field-reference');
});

Route::get('/superadmin/dashboard', [SuperAdminController::class, 'monitoringDashboard'])
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.dashboard');

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
    Route::post('/superadmin/backups', [\App\Http\Controllers\Admin\BackupController::class, 'store'])
        ->name('superadmin.backups.store');
    Route::get('/superadmin/backups/{filename}/download', [\App\Http\Controllers\Admin\BackupController::class, 'download'])
        ->name('superadmin.backups.download');
    Route::post('/superadmin/backups/{filename}/restore', [\App\Http\Controllers\Admin\BackupController::class, 'restore'])
        ->name('superadmin.backups.restore');
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

// Signature reference registry maintenance (super admin).
Route::middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/superadmin/signature-references', [\App\Http\Controllers\Admin\SignatureReferenceController::class, 'index'])
        ->name('superadmin.signature-references.index');
    Route::delete('/superadmin/signature-references/{reference}', [\App\Http\Controllers\Admin\SignatureReferenceController::class, 'destroy'])
        ->name('superadmin.signature-references.destroy');
});

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
